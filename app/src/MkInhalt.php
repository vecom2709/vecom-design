<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/MkKampagne.php';
require_once __DIR__ . '/MkZielgruppe.php';

/**
 * Content-Studio (Marketing-Studio Schritt 2, 01.10.2026).
 *
 * Claude schreibt aus einer FREIGEGEBENEN Zielgruppe Inhalte — organisch
 * (Beitrag, Karussell, Reel-Skript, Story, Telegram, Google-Profil) und
 * bezahlt (Meta-Anzeige, Google-Suchanzeige). Alles kommt als Entwurf; Uwe
 * kann jedes Feld ändern. Bei der Freigabe bekommt jedes Stück ein eigenes
 * Werbemittel in einer Kampagne und damit einen eigenen Link
 * (/k/kampagne/werbemittel): Jeder Klick zählt bis zum Kunden.
 *
 * Grenzen der Plattformen (Stand 10/2026):
 *   Google-Suchanzeige: bis 15 Überschriften à 30 Zeichen (mind. 3), bis 4
 *     Beschreibungen à 90 (mind. 2), Pfad je 15 — Google-Ads-Hilfe 7684791
 *   Meta-Anzeige: Primärtext 125 sichtbar, Überschrift 27–40 empfohlen,
 *     Beschreibung 25–30; bis 5 Varianten je Feld
 *   Instagram-Text 2.200 Zeichen, höchstens 30 Hashtags; Telegram-Bildtext
 *     1.024; Beitrag im Google-Unternehmensprofil 1.500
 */
final class MkInhalt
{
    public const ARTEN = ['organisch' => 'Organisch', 'bezahlt' => 'Bezahlt'];
    public const STATUS = ['entwurf' => 'Entwurf', 'freigegeben' => 'Freigegeben', 'veroeffentlicht' => 'Veröffentlicht', 'verworfen' => 'Verworfen'];
    public const SPRACHEN = ['it' => 'Italienisch', 'de' => 'Deutsch', 'en' => 'Englisch'];

    /**
     * Die Sprache des Textes, sichtbar wie das Land (01.10.2026, Uwe: „nicht alles korrekt
     * einsortiert zwischen Italienisch, Deutsch und Englisch“ → Vorschlag 1). Der Titel ist
     * immer deutsch (für Uwe); diese Marke sagt, in welcher Sprache gepostet wird.
     */
    public const FLAGGEN = ['it' => "\u{1F1EE}\u{1F1F9}", 'de' => "\u{1F1E9}\u{1F1EA}", 'en' => "\u{1F1EC}\u{1F1E7}"];

    /**
     * Was im Telegram-Kanal steht (01.10.2026, Uwe: Ja zu „Telegram passend zum Kanal“):
     * Der Kanal ist deutsch zuerst. Ein italienisches Stück geht deshalb mit der deutschen
     * Fassung davor raus — 🇩🇪 Deutsch, Leerzeile, 🇮🇹 Original. Schreibt Claude das Stück
     * schon zweisprachig (Fähnchen im Text), bleibt es, wie es ist; passt beides nicht in die
     * Grenze von Telegram, geht das Original allein. Dieselbe Fassung zeigt die Freigabe.
     */
    public static function telegramText(array $x, int $max = 4000): string
    {
        $t = trim((string) ($x['f']['text'] ?? ''));
        $sp = (string) ($x['sprache'] ?? 'de');
        if ($sp === 'de' || preg_match('/\x{1F1E9}\x{1F1EA}/u', $t)) { return $t; }
        $de = trim((string) ($x['uebersetzung'] ?? ''));
        if ($de === '' || $t === '') { return $t; }
        $zus = self::FLAGGEN['de'] . ' ' . $de . "\n\n" . (self::FLAGGEN[$sp] ?? '') . ' ' . $t;
        return mb_strlen($zus) <= $max ? $zus : $t;
    }

    public static function spracheMarke(string $sp): string
    {
        $sp = isset(self::SPRACHEN[$sp]) ? $sp : 'it';
        return '<span class="marke2 mk-sprache" title="In dieser Sprache wird gepostet">' . self::FLAGGEN[$sp] . ' Text auf ' . htmlspecialchars(self::SPRACHEN[$sp], ENT_QUOTES, 'UTF-8') . '</span>';
    }

    /** Format => [Wort, Art, Plattformen, Werbemittel-Art] */
    public const FORMATE = [
        'beitrag'        => ['Beitrag', 'organisch', ['instagram', 'facebook', 'linkedin', 'threads'], 'beitrag'],
        'karussell'      => ['Karussell', 'organisch', ['instagram', 'facebook', 'linkedin'], 'karussell'],
        'reel'           => ['Reel / Kurzvideo (Skript)', 'organisch', ['instagram', 'tiktok', 'facebook', 'youtube'], 'reel'],
        'story'          => ['Story', 'organisch', ['instagram', 'facebook'], 'story'],
        'telegram'       => ['Telegram-Beitrag', 'organisch', ['telegram'], 'beitrag'],
        'profil'         => ['Beitrag im Google-Unternehmensprofil', 'organisch', ['google'], 'beitrag'],
        'meta_anzeige'   => ['Meta-Anzeige (Facebook/Instagram)', 'bezahlt', ['facebook', 'instagram'], 'anzeige'],
        'google_anzeige' => ['Google-Suchanzeige', 'bezahlt', ['google'], 'anzeige'],
    ];
    /** Plattformen, die man beim Auftrag wählen kann. */
    public const PLATTFORMEN = ['instagram', 'facebook', 'tiktok', 'linkedin', 'telegram', 'google', 'youtube'];
    /** Wo ein Link im Text klickbar ist — sonst gehört er in Bio, Sticker oder Knopf. */
    public const LINK_IM_TEXT = ['facebook', 'telegram', 'linkedin', 'threads'];

    public const G = [
        'text' => 2200, 'hook' => 150, 'cta' => 80, 'hashtag' => 40, 'hashtags' => 30,
        'folie_titel' => 60, 'folie_text' => 250, 'szene_bild' => 200, 'szene_einblendung' => 100, 'szene_sprecher' => 300,
        'telegram' => 1024, 'knopf' => 40, 'profil' => 1500,
        'primaertext' => 600, 'primaertext_sichtbar' => 125, 'meta_ueberschrift' => 60, 'meta_ueberschrift_empf' => 40, 'meta_beschreibung' => 60, 'meta_varianten' => 5,
        'g_ueberschrift' => 30, 'g_beschreibung' => 90, 'g_pfad' => 15,
    ];
    /** Handlungsknöpfe einer Meta-Anzeige (Werbeanzeigenmanager). */
    public const META_CTA = ['LEARN_MORE' => 'Mehr dazu', 'GET_QUOTE' => 'Angebot einholen', 'CONTACT_US' => 'Kontakt aufnehmen',
                             'BOOK_NOW' => 'Jetzt buchen', 'SIGN_UP' => 'Registrieren', 'WHATSAPP_MESSAGE' => 'WhatsApp-Nachricht senden'];

    /* ------------------------------------------------------------------ */
    /* Säubern                                                             */
    /* ------------------------------------------------------------------ */

    private static function text(mixed $roh, int $max): string
    {
        if (!is_string($roh) && !is_numeric($roh)) { return ''; }
        $t = strip_tags((string) $roh);
        $t = preg_replace("/[ \t]+/u", ' ', $t) ?? $t;
        $t = preg_replace("/\n{3,}/u", "\n\n", str_replace("\r", '', $t)) ?? $t;
        return mb_substr(trim($t), 0, $max);
    }

    /** Liste säubern. $streng: zu Lange fallen weg statt gekürzt (Google-Anzeigen). @return array{0:list<string>,1:int} [Liste, weggefallen] */
    private static function zeilen(mixed $roh, int $max, int $laenge, bool $streng = false): array
    {
        $aus = []; $weg = 0;
        if (is_string($roh)) { $roh = preg_split('/\r?\n/', $roh) ?: []; }
        foreach (is_array($roh) ? $roh : [] as $v) {
            if (!is_string($v) && !is_numeric($v)) { continue; }
            $t = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $v)) ?? '');
            if ($t === '' || in_array($t, $aus, true)) { continue; }
            if (mb_strlen($t) > $laenge) { if ($streng) { $weg++; continue; } $t = mb_substr($t, 0, $laenge); }
            if (count($aus) >= $max) { $weg++; continue; }
            $aus[] = $t;
        }
        return [$aus, $weg];
    }

    private static function hashtags(mixed $roh): array
    {
        if (is_string($roh)) { $roh = preg_split('/[\s,]+/u', $roh) ?: []; }
        $aus = [];
        foreach (is_array($roh) ? $roh : [] as $h) {
            $h = preg_replace('/[^\p{L}\p{N}_]/u', '', (string) $h) ?? '';
            if ($h === '' || mb_strlen($h) > self::G['hashtag']) { continue; }
            $h = '#' . $h;
            if (!in_array(mb_strtolower($h), array_map('mb_strtolower', $aus), true)) { $aus[] = $h; }
            if (count($aus) >= self::G['hashtags']) { break; }
        }
        return $aus;
    }

    /** Folien: [{titel, text}] — aus JSON oder Zeilen „Titel | Text“. */
    private static function folien(mixed $roh, int $max): array
    {
        if (is_string($roh)) {
            $zeilen = [];
            foreach (preg_split('/\r?\n/', $roh) ?: [] as $z) { if (trim($z) === '') { continue; } [$a, $b] = array_pad(explode('|', $z, 2), 2, ''); $zeilen[] = ['titel' => $a, 'text' => $b]; }
            $roh = $zeilen;
        }
        $aus = [];
        foreach (is_array($roh) ? $roh : [] as $f) {
            if (!is_array($f)) { continue; }
            $t = self::text($f['titel'] ?? '', self::G['folie_titel']);
            $x = self::text($f['text'] ?? '', self::G['folie_text']);
            if ($t === '' && $x === '') { continue; }
            $aus[] = ['titel' => $t, 'text' => $x];
            if (count($aus) >= $max) { break; }
        }
        return $aus;
    }

    /** Szenen eines Kurzvideos: [{sekunden, bild, einblendung, sprecher}] — aus JSON oder „Sek | Bild | Einblendung | Sprecher“. */
    private static function szenen(mixed $roh): array
    {
        if (is_string($roh)) {
            $zeilen = [];
            foreach (preg_split('/\r?\n/', $roh) ?: [] as $z) {
                if (trim($z) === '') { continue; }
                [$s, $b, $e, $p] = array_pad(explode('|', $z, 4), 4, '');
                $zeilen[] = ['sekunden' => trim($s), 'bild' => $b, 'einblendung' => $e, 'sprecher' => $p];
            }
            $roh = $zeilen;
        }
        $aus = [];
        foreach (is_array($roh) ? $roh : [] as $s) {
            if (!is_array($s)) { continue; }
            $z = ['sekunden' => max(1, min(60, (int) ($s['sekunden'] ?? 3))), 'bild' => self::text($s['bild'] ?? '', self::G['szene_bild']),
                  'einblendung' => self::text($s['einblendung'] ?? '', self::G['szene_einblendung']), 'sprecher' => self::text($s['sprecher'] ?? '', self::G['szene_sprecher'])];
            if ($z['bild'] === '' && $z['einblendung'] === '' && $z['sprecher'] === '') { continue; }
            $aus[] = $z;
            if (count($aus) >= 12) { break; }
        }
        return $aus;
    }

    /**
     * Ein Stück prüfen und säubern. Hält es die Grenzen der Plattform nicht
     * ein (z. B. weniger als drei Google-Überschriften bis 30 Zeichen), ist
     * es kein Stück: Text statt Array.
     * @return array<string,mixed>|string
     */
    public static function pruefen(array $d, string $land = 'IT'): array|string
    {
        $format = (string) ($d['format'] ?? '');
        if (!isset(self::FORMATE[$format])) { return 'Unbekanntes Format „' . mb_substr($format, 0, 30) . '“.'; }
        [$wort, $art, $plattformen] = self::FORMATE[$format];
        $plattform = (string) ($d['plattform'] ?? $plattformen[0]);
        if (!in_array($plattform, $plattformen, true)) { return $wort . ' passt nicht zu „' . mb_substr($plattform, 0, 20) . '“.'; }
        $sprache = strtolower((string) ($d['sprache'] ?? ''));
        if (!isset(self::SPRACHEN[$sprache])) { $sprache = $land === 'DE' ? 'de' : 'it'; }
        $f = is_array($d['felder'] ?? null) ? $d['felder'] : [];
        $g = self::G;
        $felder = [];
        switch ($format) {
            case 'beitrag':
                $felder = ['hook' => self::text($f['hook'] ?? '', $g['hook']), 'text' => self::text($f['text'] ?? '', $g['text']),
                           'hashtags' => self::hashtags($f['hashtags'] ?? []), 'cta' => self::text($f['cta'] ?? '', $g['cta'])];
                if ($felder['text'] === '') { return 'Beitrag ohne Text.'; }
                break;
            case 'karussell':
                $felder = ['hook' => self::text($f['hook'] ?? '', $g['hook']), 'folien' => self::folien($f['folien'] ?? [], 10),
                           'text' => self::text($f['text'] ?? '', $g['text']), 'hashtags' => self::hashtags($f['hashtags'] ?? []), 'cta' => self::text($f['cta'] ?? '', $g['cta'])];
                if (count($felder['folien']) < 3) { return 'Ein Karussell braucht mindestens drei Folien.'; }
                break;
            case 'reel':
                $felder = ['hook' => self::text($f['hook'] ?? '', $g['hook']), 'szenen' => self::szenen($f['szenen'] ?? []),
                           'text' => self::text($f['text'] ?? '', $g['text']), 'hashtags' => self::hashtags($f['hashtags'] ?? []), 'cta' => self::text($f['cta'] ?? '', $g['cta'])];
                if ($felder['hook'] === '' || count($felder['szenen']) < 2) { return 'Ein Reel-Skript braucht einen Einstieg und mindestens zwei Szenen.'; }
                break;
            case 'story':
                $felder = ['folien' => self::folien($f['folien'] ?? [], 5), 'cta' => self::text($f['cta'] ?? '', $g['knopf'])];
                if (count($felder['folien']) < 1) { return 'Eine Story braucht mindestens eine Folie.'; }
                break;
            case 'telegram':
                $felder = ['text' => self::text($f['text'] ?? '', $g['telegram']), 'knopf' => self::text($f['knopf'] ?? $f['cta'] ?? '', $g['knopf'])];
                if ($felder['text'] === '') { return 'Telegram-Beitrag ohne Text.'; }
                break;
            case 'profil':
                $felder = ['text' => self::text($f['text'] ?? '', $g['profil']), 'cta' => self::text($f['cta'] ?? '', $g['knopf'])];
                if ($felder['text'] === '') { return 'Profil-Beitrag ohne Text.'; }
                break;
            case 'meta_anzeige':
                [$pt] = self::zeilen($f['primaertexte'] ?? [], $g['meta_varianten'], $g['primaertext']);
                [$ue] = self::zeilen($f['ueberschriften'] ?? [], $g['meta_varianten'], $g['meta_ueberschrift']);
                $cta = (string) ($f['cta'] ?? 'LEARN_MORE');
                $felder = ['primaertexte' => $pt, 'ueberschriften' => $ue, 'beschreibung' => self::text($f['beschreibung'] ?? '', $g['meta_beschreibung']),
                           'cta' => isset(self::META_CTA[$cta]) ? $cta : 'LEARN_MORE'];
                if ($pt === [] || $ue === []) { return 'Meta-Anzeige braucht mindestens einen Primärtext und eine Überschrift.'; }
                break;
            case 'google_anzeige':
                [$ue, $ueWeg] = self::zeilen($f['ueberschriften'] ?? [], 15, $g['g_ueberschrift'], true);
                [$be, $beWeg] = self::zeilen($f['beschreibungen'] ?? [], 4, $g['g_beschreibung'], true);
                [$kw] = self::zeilen($f['keywords'] ?? [], 25, 80);
                /* Marketing-Studio 6 (S5): Ausschlüsse, damit niemand klickt, der nur „gratis“, „Kurs“ oder „Job“ sucht. */
                [$aus] = self::zeilen($f['ausschluesse'] ?? [], 30, 80);
                $pfad = static fn($p) => mb_substr(preg_replace('/[\s\/]+/u', '-', trim((string) $p)) ?? '', 0, 15);
                $felder = ['ueberschriften' => $ue, 'beschreibungen' => $be, 'pfad1' => $pfad($f['pfad1'] ?? ''), 'pfad2' => $pfad($f['pfad2'] ?? ''), 'keywords' => $kw, 'ausschluesse' => $aus];
                if (count($ue) < 3) { return 'Google-Anzeige: mindestens drei Überschriften bis 30 Zeichen' . ($ueWeg ? " ($ueWeg zu lang)" : '') . '.'; }
                if (count($be) < 2) { return 'Google-Anzeige: mindestens zwei Beschreibungen bis 90 Zeichen' . ($beWeg ? " ($beWeg zu lang)" : '') . '.'; }
                break;
        }
        $titel = self::text($d['titel'] ?? '', 160);
        if ($titel === '') {
            foreach ([$felder['hook'] ?? '', $felder['ueberschriften'][0] ?? '', $felder['folien'][0]['titel'] ?? '', mb_substr((string) ($felder['text'] ?? ''), 0, 60),
                      mb_substr((string) ($felder['primaertexte'][0] ?? ''), 0, 60), $wort] as $kandidat) {
                $kandidat = trim(preg_replace('/\s+/u', ' ', (string) $kandidat) ?? '');
                if ($kandidat !== '') { $titel = mb_substr($kandidat, 0, 160); break; }
            }
        }
        $funde = [];
        foreach (is_array($d['fund_ids'] ?? null) ? $d['fund_ids'] : [] as $fi) { if ((int) $fi > 0) { $funde[] = (int) $fi; } }
        return ['format' => $format, 'art' => $art, 'plattform' => $plattform, 'sprache' => $sprache, 'titel' => $titel, 'felder' => $felder,
                'bildidee' => self::text($d['bildidee'] ?? '', 1200), 'bild_prompt' => self::text($d['bild_prompt'] ?? '', 2000), 'begruendung' => self::text($d['begruendung'] ?? '', 800),
                'fund_ids' => implode(',', array_slice(array_unique($funde), 0, 20)),
                /* Marketing-Studio 5: die deutsche Fassung eines italienischen Stücks, nur zum Lesen für Uwe. */
                'uebersetzung' => $sprache !== 'de' ? (self::text($d['uebersetzung'] ?? '', 6000) ?: null) : null];
    }

    /* ------------------------------------------------------------------ */
    /* Abliefern, laden, ändern                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Stücke von Claude übernehmen — immer als Entwurf, zur Zielgruppe des Auftrags.
     * @return array{ok:bool, neu:int, fehler:list<string>}
     */
    public static function melden(array $stuecke, int $auftragId): array
    {
        $a = Db::one("SELECT * FROM mk_auftraege WHERE id = ? AND art = 'inhalte'", [$auftragId]);
        if (!$a) { return ['ok' => false, 'neu' => 0, 'fehler' => ['Auftrag unbekannt.']]; }
        $p = json_decode((string) $a['parameter'], true) ?: [];
        $zg = Db::one('SELECT id, branche, land FROM mk_zielgruppen WHERE id = ?', [(int) ($p['zielgruppe_id'] ?? 0)]);
        if (!$zg) { return ['ok' => false, 'neu' => 0, 'fehler' => ['Zielgruppe des Auftrags gibt es nicht mehr.']]; }
        $neu = 0; $fehler = [];
        foreach (array_slice($stuecke, 0, 30) as $i => $s) {
            if (!is_array($s)) { $fehler[] = "#$i: kein Objekt"; continue; }
            $x = self::pruefen($s, (string) $zg['land']);
            if (is_string($x)) { $fehler[] = "#$i: $x"; continue; }
            $x['felder'] = json_encode($x['felder'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            Db::insert('mk_inhalte', $x + ['auftrag_id' => $auftragId, 'zielgruppe_id' => (int) $zg['id'], 'branche' => (string) $zg['branche'], 'land' => (string) $zg['land'],
                'kampagne_id' => isset($p['kampagne_id']) && (int) $p['kampagne_id'] > 0 ? (int) $p['kampagne_id'] : null]);
            $neu++;
        }
        return ['ok' => true, 'neu' => $neu, 'fehler' => $fehler];
    }

    public static function laden(int $id): ?array
    {
        $x = Db::one('SELECT * FROM mk_inhalte WHERE id = ?', [$id]);
        if (!$x) { return null; }
        $x['f'] = json_decode((string) $x['felder'], true) ?: [];
        return $x;
    }

    public static function liste(array $filter = [], int $max = 200): array
    {
        $w = []; $a = [];
        $st = (string) ($filter['status'] ?? '');
        if (isset(self::STATUS[$st])) { $w[] = 'status = ?'; $a[] = $st; } else { $w[] = "status <> 'verworfen'"; }
        if (isset(self::ARTEN[(string) ($filter['art'] ?? '')])) { $w[] = 'art = ?'; $a[] = (string) $filter['art']; }
        if (isset(MkKampagne::PLATTFORMEN[(string) ($filter['plattform'] ?? '')])) { $w[] = 'plattform = ?'; $a[] = (string) $filter['plattform']; }
        if ((int) ($filter['zielgruppe'] ?? 0) > 0) { $w[] = 'zielgruppe_id = ?'; $a[] = (int) $filter['zielgruppe']; }
        if (in_array((string) ($filter['land'] ?? ''), ['IT', 'DE'], true)) { $w[] = 'land = ?'; $a[] = (string) $filter['land']; }
        $zeilen = Db::all('SELECT * FROM mk_inhalte WHERE ' . implode(' AND ', $w)
            . " ORDER BY FIELD(status, 'entwurf', 'freigegeben', 'veroeffentlicht', 'verworfen'), id DESC LIMIT " . max(1, min(500, $max)), $a);
        foreach ($zeilen as $i => $z) { $zeilen[$i]['f'] = json_decode((string) $z['felder'], true) ?: []; }
        return $zeilen;
    }

    /** Zählung je Status (für Reiter und Kopf). */
    public static function zaehlen(?string $land = null): array
    {
        $aus = array_fill_keys(array_keys(self::STATUS), 0);
        $nurLand = in_array($land, ['IT', 'DE'], true);
        foreach (Db::all('SELECT status, COUNT(*) AS n FROM mk_inhalte' . ($nurLand ? ' WHERE land = ?' : '') . ' GROUP BY status', $nurLand ? [$land] : []) as $r) { $aus[(string) $r['status']] = (int) $r['n']; }
        return $aus;
    }

    /** Uwe ändert Felder. Formularfelder: f_<feld>; Listen und Folien als Zeilen. */
    public static function speichern(int $id, array $post): ?string
    {
        $x = self::laden($id);
        if ($x === null) { return 'Inhalt nicht gefunden.'; }
        if (!in_array($x['status'], ['entwurf', 'freigegeben'], true)) { return 'Veröffentlichte oder verworfene Inhalte bleiben, wie sie sind.'; }
        $felder = [];
        foreach ($post as $k => $v) { if (str_starts_with((string) $k, 'f_')) { $felder[substr((string) $k, 2)] = $v; } }
        $neu = self::pruefen(['format' => $x['format'], 'plattform' => $x['plattform'], 'sprache' => $x['sprache'], 'titel' => $post['titel'] ?? $x['titel'],
            'felder' => $felder + $x['f'], 'bildidee' => $post['bildidee'] ?? $x['bildidee'], 'bild_prompt' => $post['bild_prompt'] ?? ($x['bild_prompt'] ?? ''), 'begruendung' => $x['begruendung'],
            'fund_ids' => array_filter(explode(',', (string) $x['fund_ids']))], (string) $x['land']);
        if (is_string($neu)) { return $neu; }
        Db::update('mk_inhalte', $id, ['titel' => $neu['titel'], 'felder' => json_encode($neu['felder'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'bildidee' => $neu['bildidee'], 'bild_prompt' => $neu['bild_prompt']]);
        if ($x['creative_id'] && $neu['titel'] !== $x['titel']) {
            Db::run('UPDATE mk_creatives SET name = ? WHERE id = ?', [mb_substr($neu['titel'], 0, 120), (int) $x['creative_id']]);
        }
        return null;
    }

    /* ------------------------------------------------------------------ */
    /* Freigeben = eigener Link                                            */
    /* ------------------------------------------------------------------ */

    /** Die passende eigene Zielseite für Branche und Land. */
    public static function zielSeite(string $branche, string $land): string
    {
        $it = ['restaurant' => '/siti-web-ristoranti.html', 'bar_cafe' => '/siti-web-ristoranti.html', 'baeckerei' => '/siti-web-ristoranti.html',
               'ferienwohnung' => '/siti-web-bed-and-breakfast.html', 'agriturismo' => '/siti-web-bed-and-breakfast.html', 'hotel' => '/sito-o-booking.html',
               'friseur' => '/siti-web-parrucchieri.html', 'beauty' => '/siti-web-parrucchieri.html', 'handwerk' => '/siti-web-artigiani.html', 'bau' => '/siti-web-artigiani.html'];
        $de = ['restaurant' => '/de/website-restaurant-cafe.html', 'bar_cafe' => '/de/website-restaurant-cafe.html', 'baeckerei' => '/de/website-restaurant-cafe.html',
               'friseur' => '/de/website-friseur.html', 'beauty' => '/de/website-friseur.html', 'handwerk' => '/de/website-handwerker.html', 'bau' => '/de/website-handwerker.html',
               'werkstatt' => '/de/website-kfz-werkstatt.html', 'ferienwohnung' => '/de/website-pension-ferienwohnung.html', 'hotel' => '/de/website-pension-ferienwohnung.html'];
        $z = $land === 'DE' ? ($de[$branche] ?? '/de/') : ($it[$branche] ?? '/');
        return MkKampagne::zielOk($z) ? $z : ($land === 'DE' ? '/de/' : '/');
    }

    private const MONATE = [1 => 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];

    /* Marketing-Studio 6 (S2, Uwe: „ja“): Alle Beiträge und Anzeigen führen auf
       den kostenlosen Website-Check — ein Ziel, eine Zahl (Checks), und auf
       der Seite selbst der Weg weiter (ausführliche Analyse, Preis). Für
       Deutschland hängt der Kampagnenlink ?lang=de an (MkKampagne::zielAdresse). */
    public const CHECK = '/analisi.php';

    /**
     * Kampagne für ein Stück: die gewählte — sonst „Branche Land · Plattform ·
     * Website-Check · Monat“, angelegt oder wiederverwendet; mit Land und
     * Zielgruppe, damit sie bei der Zielgruppe und im richtigen Land steht.
     */
    public static function kampagneFuer(array $x): int|string
    {
        if ((int) ($x['kampagne_id'] ?? 0) > 0 && MkKampagne::laden((int) $x['kampagne_id']) !== null) { return (int) $x['kampagne_id']; }
        $plattform = trim(preg_replace('/\s*\(.*\)$/u', '', (string) (MkKampagne::PLATTFORMEN[$x['plattform']] ?? $x['plattform'])) ?? '');
        $name = (MkKampagne::branchen()[$x['branche']] ?? $x['branche']) . ' ' . $x['land'] . ' · ' . $plattform . ' · Website-Check'
              . ' · ' . self::MONATE[(int) date('n')] . ' ' . date('Y') . ($x['art'] === 'bezahlt' ? ' · Anzeigen' : '');
        $da = Db::wert("SELECT id FROM mk_kampagnen WHERE name = ? AND status <> 'beendet' AND partner_id IS NULL ORDER BY id DESC LIMIT 1", [$name], null);
        if ($da !== null) { return (int) $da; }
        return MkKampagne::anlegen(['name' => $name, 'plattform' => $x['plattform'], 'ziel' => self::CHECK, 'land' => (string) $x['land'],
            'zielgruppe_id' => (int) ($x['zielgruppe_id'] ?? 0), 'ziel_art' => 'website_check', 'branche' => (string) $x['branche'], 'cta' => 'website_check',
            'notiz' => 'Automatisch angelegt vom Content-Studio (Inhalt #' . (int) $x['id'] . ').']);
    }

    /**
     * Freigabe-Stapel (Marketing-Studio 6): der nächste Entwurf in diesem Land —
     * ältester zuerst; was Uwe auf „später“ gelegt hat, kommt erst danach wieder.
     */
    public static function naechster(string $land, array $spaeter = []): ?array
    {
        $ids = array_map('intval', array_column(Db::all("SELECT id FROM mk_inhalte WHERE status = 'entwurf' AND land = ? ORDER BY id", [$land]), 'id'));
        if ($ids === []) { return null; }
        $spaeter = array_values(array_intersect(array_map('intval', $spaeter), $ids));
        $vorn = array_values(array_diff($ids, $spaeter));
        return self::laden($vorn[0] ?? $spaeter[0]);
    }

    /** Freigeben: Kampagne sichern, eigenes Werbemittel anlegen, benutzte Funde als verwendet merken. */
    public static function freigeben(int $id, ?int $kampagneId = null): ?string
    {
        $x = self::laden($id);
        if ($x === null) { return 'Inhalt nicht gefunden.'; }
        if ($x['status'] !== 'entwurf') { return 'Nur Entwürfe lassen sich freigeben.'; }
        if ($kampagneId !== null && $kampagneId > 0) {
            if (MkKampagne::laden($kampagneId) === null) { return 'Kampagne nicht gefunden.'; }
            $x['kampagne_id'] = $kampagneId;
        }
        $k = self::kampagneFuer($x);
        if (is_string($k)) { return $k; }
        $cr = MkKampagne::werbemittelAnlegen($k, ['name' => mb_substr(trim((string) $x['titel']) ?: self::FORMATE[$x['format']][0], 0, 120), 'art' => self::FORMATE[$x['format']][3]]);
        if (is_string($cr)) { return $cr; }
        Db::update('mk_inhalte', $id, ['status' => 'freigegeben', 'kampagne_id' => $k, 'creative_id' => $cr, 'freigegeben_am' => date('Y-m-d H:i:s')]);
        foreach (array_filter(explode(',', (string) $x['fund_ids'])) as $fi) { MkZielgruppe::rechercheStatus((int) $fi, 'verwendet'); }
        Events::pruefspur('inhalt_freigegeben', 'mk_inhalte', $id, ['status' => 'entwurf'], ['status' => 'freigegeben', 'kampagne_id' => $k, 'creative_id' => $cr]);
        return null;
    }

    public static function veroeffentlicht(int $id): ?string
    {
        $n = Db::run("UPDATE mk_inhalte SET status = 'veroeffentlicht', veroeffentlicht_am = NOW() WHERE id = ? AND status = 'freigegeben'", [$id])->rowCount();
        return $n > 0 ? null : 'Erst freigeben, dann veröffentlichen.';
    }

    public static function verwerfen(int $id): ?string
    {
        $n = Db::run("UPDATE mk_inhalte SET status = 'verworfen' WHERE id = ? AND status = 'entwurf'", [$id])->rowCount();
        return $n > 0 ? null : 'Nur Entwürfe lassen sich verwerfen.';
    }

    /** Kampagne und Werbemittel eines freigegebenen Stücks. @return array{0:?array,1:?array} */
    public static function kampagne(array $x): array
    {
        $k = $x['kampagne_id'] ? MkKampagne::laden((int) $x['kampagne_id']) : null;
        $cr = $x['creative_id'] ? Db::one('SELECT * FROM mk_creatives WHERE id = ?', [(int) $x['creative_id']]) : null;
        return [$k, $cr];
    }

    /** Der eigene Link — erst ab Freigabe. */
    public static function link(array $x): ?string
    {
        [$k, $cr] = self::kampagne($x);
        return $k && $cr ? MkKampagne::link($k, $cr) : null;
    }

    /** Fertiger Text zum Kopieren (Text, Hashtags, Link, wo er klickbar ist). */
    public static function kopiertext(array $x): string
    {
        $f = $x['f']; $link = self::link($x);
        $teile = [];
        switch ($x['format']) {
            case 'beitrag': case 'karussell': case 'reel':
                if (($f['hook'] ?? '') !== '' && !str_starts_with((string) ($f['text'] ?? ''), (string) $f['hook'])) { $teile[] = $f['hook']; }
                $teile[] = (string) ($f['text'] ?? '');
                if (($f['cta'] ?? '') !== '') { $teile[] = $f['cta']; }
                if ($link && in_array($x['plattform'], self::LINK_IM_TEXT, true)) { $teile[] = $link; }
                if (!empty($f['hashtags'])) { $teile[] = implode(' ', $f['hashtags']); }
                break;
            case 'story':
                foreach ($f['folien'] ?? [] as $i => $fo) { $teile[] = ($i + 1) . '. ' . trim($fo['titel'] . ' — ' . $fo['text'], ' —'); }
                if ($link) { $teile[] = 'Link-Sticker: ' . $link; }
                break;
            case 'telegram': case 'profil':
                $teile[] = $x['format'] === 'telegram' ? self::telegramText($x) : (string) ($f['text'] ?? '');
                if ($link) { $teile[] = $link; }
                break;
            case 'meta_anzeige':
                foreach ($f['primaertexte'] ?? [] as $i => $t) { $teile[] = 'Primärtext ' . ($i + 1) . ': ' . $t; }
                foreach ($f['ueberschriften'] ?? [] as $i => $t) { $teile[] = 'Überschrift ' . ($i + 1) . ': ' . $t; }
                if (($f['beschreibung'] ?? '') !== '') { $teile[] = 'Beschreibung: ' . $f['beschreibung']; }
                $teile[] = 'Knopf: ' . (self::META_CTA[$f['cta'] ?? ''] ?? 'Mehr dazu');
                if ($link) { $teile[] = 'Website-URL: ' . $link; }
                break;
            case 'google_anzeige':
                foreach ($f['ueberschriften'] ?? [] as $i => $t) { $teile[] = 'Überschrift ' . ($i + 1) . ': ' . $t; }
                foreach ($f['beschreibungen'] ?? [] as $i => $t) { $teile[] = 'Beschreibung ' . ($i + 1) . ': ' . $t; }
                if (($f['pfad1'] ?? '') !== '') { $teile[] = 'Pfad: ' . $f['pfad1'] . (($f['pfad2'] ?? '') !== '' ? ' / ' . $f['pfad2'] : ''); }
                if ($link) { $teile[] = 'Finale URL: ' . $link; }
                if (!empty($f['keywords'])) { $teile[] = 'Keywords: ' . implode(', ', $f['keywords']); }
                if (!empty($f['ausschluesse'])) { $teile[] = 'Ausschließende Keywords: ' . implode(', ', $f['ausschluesse']); }
                break;
        }
        return trim(implode("\n\n", array_filter(array_map('trim', array_map('strval', $teile)), static fn($t) => $t !== '')));
    }

    /** Für Claude: was es zu den Formaten wissen muss. */
    public static function formateFuerClaude(array $plattformen, string $umfang): array
    {
        $aus = [];
        foreach (self::FORMATE as $k => [$wort, $art, $pl]) {
            if ($umfang !== 'beides' && $art !== $umfang) { continue; }
            $pl = array_values(array_intersect($pl, $plattformen));
            if ($pl === []) { continue; }
            $aus[$k] = ['wort' => $wort, 'art' => $art, 'plattformen' => $pl];
        }
        return $aus;
    }
}
