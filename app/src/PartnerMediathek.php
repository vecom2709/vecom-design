<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Texte.php';
require_once __DIR__ . '/PartnerBranche.php';

/* ==========================================================================
   PartnerMediathek.php — fertige Inhalte für Partner als Karten
   (Phase 3, 05.10.2026; Uwe: „Gleich mit Tabelle und Verwaltung“).

   Zwei Quellen, eine Liste:
   1. EIGENE Karten aus partner_mediathek — Uwe legt sie in der Verwaltung an
      (Text, Bild, Verweis auf einen Bereich). Nur „aktiv“ ist sichtbar.
   2. Karten aus dem, was es schon gibt — Beitrag des Tages (PartnerKalender),
      Branchen-Texte (PARTNER_BRANCHEN), Werbe-Vorlagen je Kanal (PartnerWerbung
      samt Uwes eigener Fassung), laufende Aktion, Arbeiten von Vecom. Sie werden
      beim Anzeigen gebaut, nie kopiert — wer dort einen Text ändert, ändert
      ihn auch hier.

   Jeder Text bekommt den Link des Partners: {link} wird ersetzt; fehlt er,
   wird er angehängt (ein Inhalt ohne Link bringt dem Partner nichts). Öffentliche
   Kanäle bekommen die Werbekennzeichnung, wenn der Text keine trägt.
   ========================================================================== */
final class PartnerMediathek
{
    public const ZWECKE = ['neukunden', 'vertrauen', 'angebot', 'anlass', 'vorstellung', 'referenzen'];
    public const KANAELE = ['whatsapp', 'telegram', 'instagram', 'facebook', 'tiktok', 'email', 'linkedin', 'druck', 'persoenlich'];
    public const STATUS = ['entwurf', 'aktiv', 'archiv'];
    /** Kanäle, auf denen ein Beitrag öffentlich ist — dort gehört „#Werbung“ dazu. */
    public const OEFFENTLICH = ['instagram', 'facebook', 'tiktok', 'linkedin'];
    /** Bereiche im Partnerbereich, auf die eine Karte verweisen darf (Sprungmarken, die es gibt). */
    public const ANKER = ['werbung', 'branchen', 'medien', 'galerie3d', 'beitraege', 'gutschein', 'stimmen-teilen', 'stimme-sammeln',
        'antworten', 'arbeiten-teilen', 'erfolge', 'kalender', 'aktion', 'link'];
    public const BILD_MAX_BYTE = 8_000_000;
    public const BILD_PX = 1600;
    public const TEXT_MAX = 2000;
    private const WERBUNG = ['it' => '#adv', 'de' => '#Werbung', 'en' => '#ad'];

    private static function still(callable $f, mixed $sonst): mixed
    {
        try { return $f(); } catch (Throwable $e) { return $sonst; }
    }

    /** ',a,b,' → ['a','b'] */
    public static function liste(string $csv): array
    {
        return array_values(array_filter(explode(',', $csv), static fn($x) => $x !== ''));
    }

    /** ['a','b'] → ',a,b,' (nur bekannte Werte; leer = alle) */
    public static function csv(array $werte, array $erlaubt): string
    {
        $w = array_values(array_intersect($erlaubt, array_map('strval', $werte)));
        return $w ? ',' . implode(',', $w) . ',' : '';
    }

    /* ---------------------------------------------------------------------
       Für den Partner
       --------------------------------------------------------------------- */

    /**
     * Alle Karten für einen Partner, gefiltert. $filter: zweck, branche (einer der zwölf), kanal.
     * @return list<array{id:string, quelle:string, zweck:string, titel:string, text:string, betreff:string,
     *                    branchen:list<string>, kanaele:list<string>, bild:?string, anker:string, link:string, sort:int}>
     */
    public static function karten(array $p, string $sprache, array $filter = [], ?int $jetzt = null): array
    {
        $zweck = in_array((string) ($filter['zweck'] ?? ''), self::ZWECKE, true) ? (string) $filter['zweck'] : '';
        $branche = PartnerBranche::von((string) ($filter['branche'] ?? ''));
        $kanal = in_array((string) ($filter['kanal'] ?? ''), self::KANAELE, true) ? (string) $filter['kanal'] : '';
        $alle = array_merge(self::eigene($p, $sprache), self::vorhandene($p, $sprache, $jetzt ?? time()));
        $aus = array_values(array_filter($alle, static fn(array $k): bool =>
            ($zweck === '' || $k['zweck'] === $zweck)
            && ($branche === '' || !$k['branchen'] || in_array($branche, $k['branchen'], true))
            && ($kanal === '' || !$k['kanaele'] || in_array($kanal, $k['kanaele'], true))));
        usort($aus, static fn(array $a, array $b): int => [$a['sort'], $a['id']] <=> [$b['sort'], $b['id']]);
        return $aus;
    }

    /** Text fertig machen: {link}/{name} ersetzen, Link anhängen, wenn er fehlt, öffentlich mit Werbekennzeichnung. */
    public static function fertig(string $text, string $link, string $name, array $kanaele, string $sprache): string
    {
        $text = trim($text);
        if ($text === '') { return ''; }
        if (!str_contains($text, '{link}') && $link !== '' && !str_contains($text, $link)) { $text .= "\n\n{link}"; }
        $text = strtr($text, ['{link}' => $link, '{name}' => $name]);
        if (array_intersect($kanaele, self::OEFFENTLICH) && !str_contains($text, '#')) { $text .= "\n\n" . (self::WERBUNG[$sprache] ?? '#ad'); }
        return $text;
    }

    /** Uwes eigene Karten (aktiv). */
    private static function eigene(array $p, string $sprache): array
    {
        require_once __DIR__ . '/Partner.php';
        require_once __DIR__ . '/PartnerWerbung.php';
        $name = Partner::anzeigeName($p);
        $aus = [];
        foreach ((array) self::still(static fn() => Db::all("SELECT id, zweck, titel_it, titel_de, titel_en, text_it, text_de, text_en, branchen, kanaele, anker,
                 bild_am, sort FROM partner_mediathek WHERE status = 'aktiv' ORDER BY sort, id LIMIT 300"), []) as $z) {
            $anker = in_array((string) $z['anker'], self::ANKER, true) ? (string) $z['anker'] : '';
            if ($anker !== '' && !self::verfuegbar($p, $anker, $sprache)) { continue; }
            $kan = self::liste((string) $z['kanaele']);
            $link = PartnerWerbung::link($p, 'mt-' . (int) $z['id']);   // je Karte zählbar: /p/CODE/mt-12
            $titel = self::sprachfeld($z, 'titel', $sprache);
            $roh = self::sprachfeld($z, 'text', $sprache);
            $aus[] = ['id' => 'm' . (int) $z['id'], 'quelle' => 'eigen', 'zweck' => (string) $z['zweck'], 'titel' => $titel,
                'text' => $anker !== '' ? $roh : self::fertig($roh, $link, $name, $kan, $sprache), 'betreff' => '',
                'branchen' => self::liste((string) $z['branchen']), 'kanaele' => $kan,
                'bild' => !empty($z['bild_am']) ? self::bildAdresse((int) $z['id'], (string) $z['bild_am']) : null,
                'anker' => $anker, 'link' => $link, 'sort' => (int) $z['sort']];
        }
        return $aus;
    }

    /** Feld in der Sprache des Partners, sonst Italienisch, sonst Deutsch. */
    private static function sprachfeld(array $z, string $feld, string $sprache): string
    {
        foreach ([$sprache, 'it', 'de', 'en'] as $l) {
            $w = trim((string) ($z[$feld . '_' . $l] ?? ''));
            if ($w !== '') { return $w; }
        }
        return '';
    }

    /** Ein Verweis zeigt nur auf Bereiche, die der Partner auch sieht. */
    private static function verfuegbar(array $p, string $anker, string $sprache): bool
    {
        $pid = (int) $p['id'];
        return match ($anker) {
            'galerie3d' => (bool) self::still(static function () use ($p) { require_once __DIR__ . '/MkMedium.php'; return MkMedium::galerieFuerPartner($p); }, []),
            'beitraege' => (bool) self::still(static function () use ($p, $sprache) { require_once __DIR__ . '/MkPartnerBeitraege.php'; return MkPartnerBeitraege::fuerPartner($p, $sprache); }, []),
            'erfolge'   => (bool) self::still(static function () use ($pid) { require_once __DIR__ . '/PartnerErfolg.php'; return PartnerErfolg::liste($pid); }, []),
            'aktion'    => (bool) self::still(static function () { require_once __DIR__ . '/PartnerMarketing.php'; return PartnerMarketing::aktion(); }, null),
            default     => true,
        };
    }

    /** Karten aus dem, was es schon gibt — gebaut, nicht kopiert. */
    private static function vorhandene(array $p, string $sprache, int $jetzt): array
    {
        require_once __DIR__ . '/Partner.php';
        require_once __DIR__ . '/PartnerWerbung.php';
        require_once __DIR__ . '/PartnerKalender.php';
        require_once __DIR__ . '/PartnerMarketing.php';
        $M = Texte::PARTNER_MKT;
        $t = static fn(array $x, array $r = []): string => strtr(Texte::h($x, $sprache), $r);
        $aus = [];
        // Beitrag des Tages (an Anlässen: Zweck „Anlass“)
        $tag = self::still(static fn() => PartnerKalender::tag($p, $sprache, $jetzt), null);
        if (is_array($tag)) {
            $kan = ['instagram', 'facebook', 'whatsapp', 'telegram'];
            $aus[] = ['id' => 's-tag', 'quelle' => 'vorhanden', 'zweck' => $tag['anlass'] ? 'anlass' : 'neukunden', 'titel' => $t($M['sk_tag'], ['{titel}' => $tag['titel']]),
                'text' => self::fertig($tag['text'], '', '', $kan, $sprache), 'betreff' => '', 'branchen' => [], 'kanaele' => $kan, 'bild' => null,
                'anker' => '', 'mehr' => 'kalender', 'link' => '', 'sort' => 1];
        }
        // Branchen-Texte: je Paket eine WhatsApp-Nachricht und ein Beitrag, für alle Branchen, die zu dem Paket gehören
        $paketBranchen = [];
        foreach (PartnerBranche::PAKET as $b12 => $paket) { $paketBranchen[$paket][] = $b12; }
        foreach ($paketBranchen as $paket => $b12) {
            $b = self::still(static fn() => PartnerMarketing::branche($p, $paket, $sprache), null);
            if (!is_array($b)) { continue; }
            $name = implode(', ', array_map(static fn($x) => PartnerBranche::name($x, $sprache), $b12));
            $aus[] = ['id' => 's-wa-' . $paket, 'quelle' => 'vorhanden', 'zweck' => 'neukunden', 'titel' => $t($M['sk_wa'], ['{branche}' => $name]),
                'text' => $b['wa'], 'betreff' => '', 'branchen' => $b12, 'kanaele' => ['whatsapp', 'telegram'], 'bild' => null, 'anker' => '', 'link' => '', 'sort' => 20];
            $aus[] = ['id' => 's-post-' . $paket, 'quelle' => 'vorhanden', 'zweck' => 'neukunden', 'titel' => $t($M['sk_post'], ['{branche}' => $name]),
                'text' => self::fertig($b['post'], '', '', ['instagram'], $sprache), 'betreff' => '', 'branchen' => $b12,
                'kanaele' => ['instagram', 'facebook', 'linkedin'], 'bild' => null, 'anker' => '', 'link' => '', 'sort' => 21];
        }
        // Werbe-Vorlagen je Kanal (mit Uwes eigener Fassung aus /partner/vorlagen)
        $kanalMt = ['whatsapp' => 'whatsapp', 'telegram' => 'telegram', 'instagram' => 'instagram', 'facebook' => 'facebook', 'tiktok' => 'tiktok',
                    'email' => 'email', 'linkedin' => 'linkedin', 'sms' => 'whatsapp'];
        foreach ((array) self::still(static fn() => PartnerWerbung::vorlagen($p, $sprache), []) as $k => $liste) {
            if (!isset($kanalMt[$k])) { continue; }
            foreach ($liste as $v) {
                $kan = [$kanalMt[$k]];
                $aus[] = ['id' => 's-v-' . $v['id'], 'quelle' => 'vorhanden', 'zweck' => 'neukunden', 'titel' => $v['titel'] . ' · ' . ($k === 'sms' ? 'SMS' : $t($M['kanaele'][$kanalMt[$k]])),
                    'text' => self::fertig((string) $v['text'], (string) $v['link'], '', $kan, $sprache), 'betreff' => (string) $v['betreff'], 'branchen' => [], 'kanaele' => $kan,
                    'bild' => null, 'anker' => '', 'link' => (string) $v['link'], 'sort' => 30];
            }
        }
        // Laufende Aktion
        $akt = self::still(static fn() => PartnerMarketing::aktion($jetzt, $sprache), null);
        if (is_array($akt)) {
            $kan = ['whatsapp', 'instagram', 'facebook', 'telegram'];
            $aus[] = ['id' => 's-aktion', 'quelle' => 'vorhanden', 'zweck' => 'angebot', 'titel' => $t($M['sk_aktion']),
                'text' => self::fertig((string) self::still(static fn() => PartnerMarketing::aktionBeitrag($p, $akt, $sprache), ''), '', '', $kan, $sprache),
                'betreff' => '', 'branchen' => [], 'kanaele' => $kan, 'bild' => null, 'anker' => '', 'link' => '', 'sort' => 2];
        }
        // Arbeiten von Vecom als fertiger Beitrag
        $arb = (string) self::still(static fn() => PartnerMarketing::arbeitenBeitrag($p, $sprache), '');
        if ($arb !== '') {
            $kan = ['instagram', 'facebook', 'whatsapp'];
            $aus[] = ['id' => 's-arbeiten', 'quelle' => 'vorhanden', 'zweck' => 'referenzen', 'titel' => $t($M['sk_arbeiten']),
                'text' => self::fertig($arb, '', '', $kan, $sprache), 'betreff' => '', 'branchen' => [], 'kanaele' => $kan, 'bild' => null, 'anker' => '', 'link' => '', 'sort' => 40];
        }
        return array_values(array_filter($aus, static fn(array $k): bool => trim($k['text']) !== ''));
    }

    /**
     * Assistent (Punkt 12): drei Karten, die zu Branche, Kanal und Ziel am besten passen. Fertige Texte vor
     * Verweisen, Uwes eigene leicht vor den vorhandenen, und nie zwei Vorlagen desselben Kanals hintereinander
     * ohne Grund — es zählt Passung, nicht Menge. @return list<array<string,mixed>>
     */
    public static function vorschlaege(array $p, string $sprache, string $branche, string $kanal, string $zweck, int $n = 3, ?int $jetzt = null): array
    {
        $b = PartnerBranche::von($branche);
        $k = in_array($kanal, self::KANAELE, true) ? $kanal : '';
        $z = in_array($zweck, self::ZWECKE, true) ? $zweck : 'neukunden';
        // Nicht nach Kanal FILTERN, sondern danach werten: Ein schmaler Kanal („persönlich“) soll nicht drei Karten
        // liefern, die zum Ziel nicht passen — lieber die passende Karte für einen Nachbarkanal.
        $wertung = [];
        foreach (self::karten($p, $sprache, ['branche' => $b], $jetzt) as $i => $karte) {
            $w = 0.0;
            $w += $karte['zweck'] === $z ? 4 : 0;
            $w += $k !== '' && in_array($k, $karte['kanaele'], true) ? 2 : (!$karte['kanaele'] ? 1 : 0);
            $w += $b !== '' && in_array($b, $karte['branchen'], true) ? 2 : 0;
            $w += $karte['anker'] === '' ? 1 : -1;
            $w += $karte['quelle'] === 'eigen' ? 0.5 : 0;
            $w += $karte['id'] === 's-tag' && $karte['zweck'] === 'anlass' ? 1.5 : 0;   // ein Anlass heute ist ein guter Grund
            $wertung[] = [$w, -$i, $karte];
        }
        usort($wertung, static fn($a, $c) => [$c[0], $c[1]] <=> [$a[0], $a[1]]);
        return array_map(static fn($x) => $x[2], array_slice($wertung, 0, max(1, $n)));
    }

    /* ---------------------------------------------------------------------
       Verwaltung
       --------------------------------------------------------------------- */

    /** @return list<array<string,mixed>> ohne Bilddaten */
    public static function verwaltungListe(): array
    {
        return Db::all('SELECT id, zweck, titel_it, titel_de, titel_en, text_it, text_de, text_en, branchen, kanaele, anker, bild_am, status, sort, schluessel,
                created_at, updated_at FROM partner_mediathek ORDER BY FIELD(status, \'aktiv\', \'entwurf\', \'archiv\'), sort, id');
    }

    /**
     * Anlegen oder ändern. Pflicht: Zweck, ein Titel, und entweder ein Text, ein Bild oder ein Verweis.
     * @return array{ok:bool, id?:int, grund?:string}
     */
    public static function speichern(array $d, ?int $id = null, ?string $bildPfad = null, int $bildGroesse = 0): array
    {
        $zweck = (string) ($d['zweck'] ?? '');
        if (!in_array($zweck, self::ZWECKE, true)) { return ['ok' => false, 'grund' => 'zweck']; }
        $f = static fn(string $k, int $max): string => mb_substr(trim((string) preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+~u', '', (string) ($d[$k] ?? ''))), 0, $max);
        $zeile = ['zweck' => $zweck];
        foreach (['it', 'de', 'en'] as $l) {
            $zeile['titel_' . $l] = $f('titel_' . $l, 120);
            $zeile['text_' . $l] = $f('text_' . $l, self::TEXT_MAX);
        }
        if ($zeile['titel_it'] === '' && $zeile['titel_de'] === '' && $zeile['titel_en'] === '') { return ['ok' => false, 'grund' => 'titel']; }
        $zeile['branchen'] = self::csv((array) ($d['branchen'] ?? []), PartnerBranche::ALLE);
        $zeile['kanaele'] = self::csv((array) ($d['kanaele'] ?? []), self::KANAELE);
        $zeile['anker'] = in_array((string) ($d['anker'] ?? ''), self::ANKER, true) ? (string) $d['anker'] : '';
        $zeile['status'] = in_array((string) ($d['status'] ?? ''), self::STATUS, true) ? (string) $d['status'] : 'entwurf';
        $zeile['sort'] = max(0, min(999, (int) ($d['sort'] ?? 100)));
        $hatText = $zeile['text_it'] !== '' || $zeile['text_de'] !== '' || $zeile['text_en'] !== '';
        $altBild = $id !== null && (bool) Db::wert('SELECT bild_am IS NOT NULL FROM partner_mediathek WHERE id = ?', [$id], false) && empty($d['bild_weg']);
        if (!$hatText && $zeile['anker'] === '' && $bildPfad === null && !$altBild) { return ['ok' => false, 'grund' => 'inhalt']; }
        $bild = null;
        if ($bildPfad !== null) {
            $bild = self::bildRechnen($bildPfad, $bildGroesse);
            if (!is_string($bild) || str_starts_with($bild, 'fehler:')) { return ['ok' => false, 'grund' => substr((string) $bild, 7) ?: 'bild_art']; }
        }
        if ($id === null) {
            if ($bild !== null) { $zeile['bild'] = $bild; $zeile['bild_am'] = date('Y-m-d H:i:s'); }
            return ['ok' => true, 'id' => (int) Db::insert('partner_mediathek', $zeile)];
        }
        if (!Db::one('SELECT id FROM partner_mediathek WHERE id = ?', [$id])) { return ['ok' => false, 'grund' => 'fehlt']; }
        $set = []; $werte = [];
        foreach ($zeile as $k => $v) { $set[] = "$k = ?"; $werte[] = $v; }
        if ($bild !== null) { $set[] = 'bild = ?'; $werte[] = $bild; $set[] = 'bild_am = NOW()'; }
        elseif (!empty($d['bild_weg'])) { $set[] = 'bild = NULL'; $set[] = 'bild_am = NULL'; }
        $werte[] = $id;
        Db::run('UPDATE partner_mediathek SET ' . implode(', ', $set) . ' WHERE id = ?', $werte);
        return ['ok' => true, 'id' => $id];
    }

    public static function statusSetzen(int $id, string $status): bool
    {
        if (!in_array($status, self::STATUS, true)) { return false; }
        return Db::run('UPDATE partner_mediathek SET status = ? WHERE id = ?', [$status, $id])->rowCount() > 0;
    }

    /** Bild neu rechnen: höchstens 1600 px an der langen Seite, WebP, ohne EXIF. @return string WebP-Daten oder „fehler:grund“ */
    public static function bildRechnen(string $pfad, int $groesse): string
    {
        if ($groesse <= 0 || $groesse > self::BILD_MAX_BYTE) { return 'fehler:bild_gross'; }
        $info = @getimagesize($pfad);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) { return 'fehler:bild_art'; }
        if ($info[0] * $info[1] > 60_000_000) { return 'fehler:bild_gross'; }   // Riesenbilder sprengen beim Dekodieren den Speicher
        $roh = @imagecreatefromstring((string) file_get_contents($pfad));
        if (!$roh) { return 'fehler:bild_art'; }
        $b = imagesx($roh); $h = imagesy($roh);
        $f = min(1.0, self::BILD_PX / max($b, $h));
        $nb = max(1, (int) round($b * $f)); $nh = max(1, (int) round($h * $f));
        $neu = imagecreatetruecolor($nb, $nh);
        imagealphablending($neu, false); imagesavealpha($neu, true);
        imagecopyresampled($neu, $roh, 0, 0, 0, 0, $nb, $nh, $b, $h);
        ob_start(); imagewebp($neu, null, 84); $webp = (string) ob_get_clean();
        imagedestroy($roh); imagedestroy($neu);
        return $webp !== '' ? $webp : 'fehler:bild_art';
    }

    /** Bilddaten einer AKTIVEN Karte (p.php?mt=). */
    public static function bildDaten(int $id): ?string
    {
        $b = Db::wert("SELECT bild FROM partner_mediathek WHERE id = ? AND status = 'aktiv' AND bild IS NOT NULL", [$id], null);
        return is_string($b) && $b !== '' ? $b : null;
    }

    /** Adresse mit Versionsanhang: neues Bild = neue Adresse, kein alter Zwischenspeicher. */
    public static function bildAdresse(int $id, string $bildAm): string
    {
        return '/p.php?mt=' . $id . '&v=' . substr(md5($bildAm), 0, 8);
    }
}
