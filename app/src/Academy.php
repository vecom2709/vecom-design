<?php
declare(strict_types=1);

/* ==========================================================================
   Academy — Vecom Partner Academy & Verkaufstraining (Etappe 1, 05.10.2026,
   Uwe: „Ja, Etappe 1 bauen“).

   DIE INHALTE LIEGEN IM REPOSITORY, NICHT IN DER DATENBANK
   app/data/academy/{de,it,en}.json: acht Module, Kontaktwege, Einwände,
   Leistungen in drei Ebenen. Deutsch ist die Quelle (duzt den Partner;
   Sätze an Betriebe stehen im Sie), Italienisch bleibt beim „Lei“.
   Fehlt eine Sprache, gilt Italienisch, dann Deutsch.

   DIE DATENBANK HÄLT NUR, WAS JE PARTNER ENTSTEHT (Migration 180)
   Fortschritt, Merkliste, eigene Notizen — immer über die Partner-ID, die
   partner.php aus Link und Gerät kennt, nie über eine ID aus der Anfrage.
   Dazu anonyme Tageszähler (was geöffnet/gesucht wird) für die Verwaltung:
   ohne Partner und ohne Freitext.

   KEINE ZUSAGEN
   Kein Inhalt nennt Preise, Rabatte oder Termine. Der Weg führt immer zum
   Fragebogen über den Partnerlink — den Richtpreis rechnet der Baukasten,
   das Angebot macht Vecom. Die Kette prüft das an den Inhaltsdateien.
   ========================================================================== */
final class Academy
{
    public const SPRACHEN = ['it', 'de', 'en'];
    public const MERK_ARTEN = ['modul', 'einwand', 'leistung', 'kontakt', 'pdf'];
    /** Eigene PDFs der Verwaltung: höchstens so groß (Etappe 2). */
    public const PDF_MAX = 10 * 1024 * 1024;
    /**
     * Die Bibliothek (Etappe 2): jedes PDF entsteht aus den Inhalten — immer
     * aktuell, in der Sprache des Partners, mit denselben Zahlen wie die
     * Vereinbarung. Slug → [Modul, Zusatz (kontakt|leistungen|einwaende|vereinbarung|''), Kategorie].
     */
    public const DOKUMENTE = [
        'grundlagen'    => ['vecom-verstehen', '', 'grundlagen'],
        'kunden-finden' => ['kunden-finden', '', 'kunden'],
        'erstkontakt'   => ['erstkontakt', 'kontakt', 'gespraech'],
        'gespraech'     => ['gespraech-fuehren', '', 'gespraech'],
        'bedarf'        => ['bedarf-erkennen', '', 'gespraech'],
        'leistungen'    => ['vecom-praesentieren', 'leistungen', 'vecom'],
        'einwaende'     => ['einwaende', 'einwaende', 'gespraech'],
        'auftrag'       => ['zum-auftrag', '', 'ablauf'],
        'regeln'        => ['', 'vereinbarung', 'regeln'],
    ];
    public const NOTIZ_MAX = 2000;
    public const NOTIZEN_HOECHSTENS = 200;

    /** @var array<string,array> */
    private static array $cache = [];
    /** Für die Kette: anderer Ordner mit Inhalten. */
    public static ?string $ordner = null;

    /** Alle Inhalte einer Sprache (mit Rückfall it → de). */
    public static function inhalte(string $sprache): array
    {
        $sprache = in_array($sprache, self::SPRACHEN, true) ? $sprache : 'it';
        if (isset(self::$cache[$sprache])) { return self::$cache[$sprache]; }
        $ordner = self::$ordner ?? dirname(__DIR__) . '/data/academy';
        $daten = [];
        foreach ([$sprache, 'it', 'de'] as $l) {
            $f = $ordner . '/' . $l . '.json';
            if (is_file($f)) {
                $d = json_decode((string) file_get_contents($f), true);
                if (is_array($d) && isset($d['module'])) { $daten = $d; break; }
            }
        }
        $daten += ['module' => [], 'kontakt' => [], 'einwaende' => [], 'leistungen' => [], 'finder' => [], 'woerter' => [],
                   'bedarf' => [], 'bedarf_grund' => [], 'finder_ergebnis' => [], 'lagen' => []];
        /* Schalter der Verwaltung (Etappe 2): aktiv, Pflicht, Reihenfolge. Ohne Tabelle gilt die Datei. */
        $schalter = self::schalter();
        $alle = [];
        foreach ($daten['module'] as $m) {
            $sw = $schalter[$m['slug']] ?? null;
            $m['aktiv'] = $sw === null || (int) $sw['aktiv'] === 1;
            if ($sw !== null && $sw['pflicht'] !== null) { $m['pflicht'] = (int) $sw['pflicht'] === 1; }
            $m['reihe'] = $sw !== null && $sw['reihe'] !== null ? (int) $sw['reihe'] : (int) $m['nr'];
            $alle[] = $m;
        }
        usort($alle, static fn($a, $b) => [$a['reihe'], (int) $a['nr']] <=> [$b['reihe'], (int) $b['nr']]);
        $daten['alle_module'] = $alle;
        $daten['module'] = array_values(array_filter($alle, static fn($m) => $m['aktiv']));
        return self::$cache[$sprache] = $daten;
    }

    /** Zwischenspeicher leeren (nach einer Änderung der Schalter, und für die Kette). */
    public static function vergessen(): void { self::$cache = []; }

    /** @return array<string,array> Slug → Schalter */
    public static function schalter(): array
    {
        try {
            $o = [];
            foreach (Db::all('SELECT slug, aktiv, pflicht, reihe FROM academy_module') as $z) { $o[(string) $z['slug']] = $z; }
            return $o;
        } catch (Throwable $e) { return []; }
    }

    /** Schalter eines Moduls setzen (Verwaltung). Unbekannte Module werden abgelehnt. */
    public static function schalterSetzen(string $slug, bool $aktiv, ?bool $pflicht, ?int $reihe): bool
    {
        $da = false;
        foreach (self::inhalte('de')['alle_module'] as $m) { if ($m['slug'] === $slug) { $da = true; } }
        if (!$da) { return false; }
        Db::run('INSERT INTO academy_module (slug, aktiv, pflicht, reihe) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE aktiv = VALUES(aktiv), pflicht = VALUES(pflicht), reihe = VALUES(reihe), updated_at = NOW()',
            [$slug, $aktiv ? 1 : 0, $pflicht === null ? null : ($pflicht ? 1 : 0), $reihe === null ? null : max(-99, min(999, $reihe))]);
        self::vergessen();
        return true;
    }

    public static function modul(string $slug, string $sprache): ?array
    {
        foreach (self::inhalte($sprache)['module'] as $m) { if ($m['slug'] === $slug) { return $m; } }
        return null;
    }

    public static function eintrag(string $art, string $slug, string $sprache): ?array
    {
        if ($art === 'pdf') { $d = self::dokument($slug, $sprache, true); return $d ? ['slug' => $slug, 'titel' => $d['titel']] : null; }
        $liste = match ($art) { 'einwand' => 'einwaende', 'leistung' => 'leistungen', 'kontakt' => 'kontakt', 'modul' => 'module', 'lage' => 'lagen', default => '' };
        if ($liste === '') { return null; }
        foreach (self::inhalte($sprache)[$liste] as $e) { if (($e['slug'] ?? '') === $slug) { return $e; } }
        return null;
    }

    /** Gibt es diesen Eintrag? Nur bekannte Slugs werden gespeichert. */
    public static function gibt(string $art, string $slug): bool
    {
        if ($art === 'pdf') { return self::dokument($slug, 'de', true) !== null; }
        return in_array($art, self::MERK_ARTEN, true) && self::eintrag($art, $slug, 'de') !== null;
    }

    /** Lektionen und (falls vorhanden) Wissenstest: so viele Schritte hat ein Modul. */
    public static function schritte(array $m): int
    {
        return count($m['lektionen'] ?? []) + (!empty($m['fragen']) ? 1 : 0);
    }

    /* ---------------------------------------------------------------- Fortschritt */

    /** @return array<string,array> Modul-Slug → Zeile */
    public static function fortschritt(int $partnerId): array
    {
        $o = [];
        foreach (Db::all('SELECT * FROM academy_fortschritt WHERE partner_id = ?', [$partnerId]) as $z) { $o[(string) $z['modul']] = $z; }
        return $o;
    }

    /** Gelesene Lektionen eines Moduls als Liste von Nummern. */
    public static function gelesen(array $zeile): array
    {
        $s = trim((string) ($zeile['lektionen'] ?? ''));
        return $s === '' ? [] : array_values(array_unique(array_map('intval', explode(',', $s))));
    }

    /**
     * Überblick für Startseite und Command Center: Prozent über alle Schritte,
     * fertige Module, zuletzt bearbeitet, nächstes empfohlenes Modul (Pflicht vor Kür,
     * dann in der Reihenfolge der Module).
     */
    public static function stand(int $partnerId, string $sprache): array
    {
        $mods = self::inhalte($sprache)['module'];
        $f = self::fortschritt($partnerId);
        $alle = 0; $getan = 0; $fertig = 0; $zuletzt = null; $zuletztAm = '';
        $naechstes = null; $naechstesKuer = null;
        foreach ($mods as $m) {
            $n = self::schritte($m); $alle += $n;
            $z = $f[$m['slug']] ?? null;
            $g = $z ? min(count($m['lektionen'] ?? []), count(self::gelesen($z))) + (!empty($m['fragen']) && $z['fertig_am'] !== null ? 1 : 0) : 0;
            if ($z && $z['fertig_am'] !== null) { $g = $n; $fertig++; }
            $getan += $g;
            if ($z && (string) $z['zuletzt_am'] > $zuletztAm) { $zuletztAm = (string) $z['zuletzt_am']; $zuletzt = $m; }
            if (!$z || $z['fertig_am'] === null) {
                if (!empty($m['pflicht'])) { $naechstes ??= $m; } else { $naechstesKuer ??= $m; }
            }
        }
        $naechstes ??= $naechstesKuer;
        return [
            'prozent' => $alle > 0 ? (int) round(100 * $getan / $alle) : 0,
            'fertig' => $fertig, 'gesamt' => count($mods),
            'zuletzt' => $zuletzt, 'naechstes' => $naechstes,
            'begonnen' => $f !== [],
        ];
    }

    /** Lektion als gelesen vermerken (beim Öffnen). Unbekannte Module/Nummern werden ignoriert. */
    public static function lektionGelesen(int $partnerId, string $modul, int $nr): void
    {
        $m = self::modul($modul, 'de');
        if (!$m || $nr < 0 || $nr >= count($m['lektionen'] ?? [])) { return; }
        $z = Db::one('SELECT lektionen FROM academy_fortschritt WHERE partner_id = ? AND modul = ?', [$partnerId, $modul]);
        $liste = $z ? self::gelesen($z) : [];
        if (!in_array($nr, $liste, true)) { $liste[] = $nr; sort($liste); }
        Db::run('INSERT INTO academy_fortschritt (partner_id, modul, lektionen) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE lektionen = VALUES(lektionen), zuletzt_am = NOW()',
            [$partnerId, $modul, implode(',', $liste)]);
        if ($nr === 0) { self::zaehlen('modul', $modul); }
    }

    /**
     * Modul abschließen. Mit Wissenstest: die Antworten werden ausgewertet (kein
     * Durchfallen — das Ergebnis zeigt, was richtig war). Ohne Test: alle Lektionen
     * müssen gelesen sein.
     * @param array<int|string,mixed> $antworten Frage-Nr → gewählte Antwort-Nr
     * @return array{ok:bool, richtig?:int, fragen?:int, auswertung?:array}
     */
    public static function abschliessen(int $partnerId, string $modul, array $antworten): array
    {
        $m = self::modul($modul, 'de');
        if (!$m) { return ['ok' => false]; }
        $z = Db::one('SELECT lektionen FROM academy_fortschritt WHERE partner_id = ? AND modul = ?', [$partnerId, $modul]);
        if (count(self::gelesen($z ?? [])) < count($m['lektionen'] ?? [])) { return ['ok' => false]; }
        $fragen = $m['fragen'] ?? [];
        $richtig = 0; $auswertung = [];
        foreach ($fragen as $i => $fr) {
            $wahl = isset($antworten[$i]) && is_numeric($antworten[$i]) ? (int) $antworten[$i] : -1;
            $ok = $wahl === (int) $fr['richtig'];
            $richtig += $ok ? 1 : 0;
            $auswertung[$i] = ['wahl' => $wahl, 'ok' => $ok];
        }
        Db::run('UPDATE academy_fortschritt SET fertig_am = COALESCE(fertig_am, NOW()), zuletzt_am = NOW(),
                 test_richtig = ?, test_fragen = ? WHERE partner_id = ? AND modul = ?',
            [$fragen ? $richtig : null, $fragen ? count($fragen) : null, $partnerId, $modul]);
        return ['ok' => true, 'richtig' => $richtig, 'fragen' => count($fragen), 'auswertung' => $auswertung];
    }

    /* ---------------------------------------------------------------- Merkliste */

    public static function merken(int $partnerId, string $art, string $ziel, bool $an): bool
    {
        if (!self::gibt($art, $ziel)) { return false; }
        if ($an) {
            Db::run('INSERT IGNORE INTO academy_merkliste (partner_id, art, ziel) VALUES (?, ?, ?)', [$partnerId, $art, $ziel]);
        } else {
            Db::run('DELETE FROM academy_merkliste WHERE partner_id = ? AND art = ? AND ziel = ?', [$partnerId, $art, $ziel]);
        }
        return true;
    }

    /** @return list<array{art:string,ziel:string}> */
    public static function merkliste(int $partnerId): array
    {
        return array_values(array_filter(
            Db::all('SELECT art, ziel FROM academy_merkliste WHERE partner_id = ? ORDER BY created_at DESC', [$partnerId]),
            static fn($z) => self::gibt((string) $z['art'], (string) $z['ziel'])));
    }

    public static function gemerkt(array $liste, string $art, string $ziel): bool
    {
        foreach ($liste as $z) { if ($z['art'] === $art && $z['ziel'] === $ziel) { return true; } }
        return false;
    }

    /* ---------------------------------------------------------------- Notizen */

    /** Eigene Notiz speichern. Leere oder zu viele Notizen werden abgelehnt. */
    public static function notizSpeichern(int $partnerId, string $modul, string $text): bool
    {
        $text = trim(mb_substr(str_replace("\r", '', $text), 0, self::NOTIZ_MAX));
        if ($text === '') { return false; }
        if ($modul !== '' && !self::modul($modul, 'de')) { $modul = ''; }
        if ((int) Db::wert('SELECT COUNT(*) FROM academy_notizen WHERE partner_id = ?', [$partnerId]) >= self::NOTIZEN_HOECHSTENS) { return false; }
        Db::run('INSERT INTO academy_notizen (partner_id, modul, text) VALUES (?, ?, ?)', [$partnerId, $modul, $text]);
        return true;
    }

    public static function notizen(int $partnerId, string $modul = ''): array
    {
        return $modul === ''
            ? Db::all('SELECT id, modul, text, created_at FROM academy_notizen WHERE partner_id = ? ORDER BY id DESC LIMIT 50', [$partnerId])
            : Db::all('SELECT id, modul, text, created_at FROM academy_notizen WHERE partner_id = ? AND modul = ? ORDER BY id DESC', [$partnerId, $modul]);
    }

    /** Nur eigene Notizen lassen sich löschen. */
    public static function notizLoeschen(int $partnerId, int $id): bool
    {
        return Db::run('DELETE FROM academy_notizen WHERE id = ? AND partner_id = ?', [$id, $partnerId])->rowCount() === 1;
    }

    /* ---------------------------------------------------------------- Suche */

    /**
     * Sucht in Modulen, Einwänden, Leistungen und Kontaktwegen.
     * @return list<array{art:string,slug:string,titel:string,auszug:string,modul?:string,lektion?:int}>
     */
    public static function suche(string $q, string $sprache): array
    {
        $q = trim(mb_strtolower(mb_substr($q, 0, 60)));
        if (mb_strlen($q) < 2) { return []; }
        $woerter = array_values(array_filter(preg_split('~\s+~u', $q) ?: [], static fn($w) => mb_strlen($w) >= 2));
        $d = self::inhalte($sprache);
        $treffer = [];
        $passt = static function (string $text) use ($woerter): int {
            $t = mb_strtolower($text); $n = 0;
            foreach ($woerter as $w) { if (str_contains($t, $w)) { $n++; } }
            return $n === count($woerter) ? $n : 0;
        };
        $flach = static function ($v) use (&$flach): string {
            return is_array($v) ? implode(' ', array_map($flach, $v)) : (is_scalar($v) ? (string) $v : '');
        };
        foreach ($d['einwaende'] as $e) {
            if ($s = $passt($flach($e))) { $treffer[] = ['art' => 'einwand', 'slug' => $e['slug'], 'titel' => $e['satz'], 'auszug' => $e['dahinter'], 'w' => $s + 3]; }
        }
        foreach ($d['leistungen'] as $e) {
            if ($s = $passt($flach($e))) { $treffer[] = ['art' => 'leistung', 'slug' => $e['slug'], 'titel' => $e['name'], 'auszug' => $e['kurz'], 'w' => $s + 2]; }
        }
        foreach ($d['kontakt'] as $e) {
            if ($s = $passt($flach($e))) { $treffer[] = ['art' => 'kontakt', 'slug' => $e['slug'], 'titel' => $e['name'], 'auszug' => $e['ziel'], 'w' => $s + 2]; }
        }
        foreach ($d['module'] as $m) {
            foreach (($m['lektionen'] ?? []) as $i => $l) {
                if ($s = $passt($flach($l))) {
                    $treffer[] = ['art' => 'lektion', 'slug' => $m['slug'], 'lektion' => $i, 'titel' => $l['titel'], 'auszug' => $m['titel'], 'w' => $s + ($passt($l['titel']) ? 2 : 0)];
                }
            }
        }
        usort($treffer, static fn($a, $b) => $b['w'] <=> $a['w']);
        $treffer = array_slice($treffer, 0, 20);
        if ($treffer) { self::zaehlen('suche', $treffer[0]['art'] . ':' . $treffer[0]['slug']); }
        return $treffer;
    }

    /* ---------------------------------------------------------------- Bedarf, Kundenfinder, Lage (Etappe 2) */

    /**
     * Bedarfsassistent: aus den Antworten werden passende Leistungen — nur
     * Empfehlungen, nichts wird gebucht, kein Preis. Unbekannte Antworten zählen nicht.
     * @param array<string,mixed> $a
     * @return list<string> Leistungs-Slugs in sinnvoller Reihenfolge
     */
    public static function bedarfAuswerten(array $a): array
    {
        $hat = static function (string $k, string $wert) use ($a): bool {
            $v = $a[$k] ?? null;
            return is_array($v) ? in_array($wert, array_map('strval', $v), true) : (string) $v === $wert;
        };
        $o = ['webdesign'];
        if ($hat('website', 'alt') || $hat('website', 'nein')) { $o[] = 'mobil'; }
        if ($hat('termine', 'ja') || $hat('ziel', 'zeit')) { $o[] = 'buchung'; }
        if ($hat('produkte', 'ja') || $hat('ziel', 'verkaufen')) { $o[] = 'shop'; }
        if ($hat('ausland', 'ja')) { $o[] = 'sprachen'; }
        if ($hat('google', 'ja') || $hat('ziel', 'kunden')) { $o[] = 'seo'; }
        if (!$hat('material', 'logo') || $hat('ziel', 'eindruck')) { $o[] = 'branding'; }
        if ($hat('zeigen', 'ja')) { $o[] = '3d'; }
        if ($hat('betreuung', 'ja')) { $o[] = 'wartung'; $o[] = 'hosting'; }
        return array_values(array_unique($o));
    }

    /**
     * Kundenfinder-Checkliste: Punkte für sichtbare Probleme und Potenzial.
     * Nur, was der Partner selbst angekreuzt hat — keine erfundenen Befunde.
     * @return array{stufe:string, punkte:float, beantwortet:int}
     */
    public static function finderAuswerten(array $a): array
    {
        $w = [
            'website' => ['nein' => 4], 'modern' => ['nein' => 2, 'unklar' => 1], 'mobil' => ['nein' => 2, 'unklar' => 1],
            'https' => ['nein' => 2], 'kontakt' => ['nein' => 1], 'google' => ['nein' => 1, 'unklar' => 0.5],
            'termine' => ['ja' => 1], 'shop' => ['ja' => 1], 'sprachen' => ['ja' => 1],
            'seo' => ['mittel' => 1, 'hoch' => 2], 'branding' => ['mittel' => 1, 'hoch' => 2], 'erlebnis' => ['mittel' => 0.5, 'hoch' => 1],
        ];
        $p = 0.0; $n = 0;
        foreach ($w as $k => $werte) {
            $v = (string) ($a[$k] ?? '');
            if ($v === '') { continue; }
            $n++;
            $p += $werte[$v] ?? 0;
        }
        // Ohne Website zählen modern/mobil/https nicht doppelt — sie gibt es dann gar nicht.
        if ((string) ($a['website'] ?? '') === 'nein') { $p = max($p, 8.0); }
        return ['stufe' => $p >= 8 ? 'hoch' : ($p >= 4 ? 'mittel' : 'gering'), 'punkte' => $p, 'beantwortet' => $n];
    }

    /**
     * Lage eines Betriebs aus der eigenen Reservierung oder der Anrufliste (für
     * „Was mache ich jetzt?“). Nur eigene Reservierungen; sonst null.
     */
    public static function lageFirma(int $partnerId, int $firmaId): ?string
    {
        try {
            $r = Db::one('SELECT herkunft, anruf_status, angeschrieben_am FROM partner_reservierungen
                           WHERE partner_id = ? AND firma_id = ? AND bis >= CURDATE()', [$partnerId, $firmaId]);
        } catch (Throwable $e) { return null; }
        return $r ? self::lageAusZeile($r) : null;
    }

    public static function lageAusZeile(array $r): string
    {
        $status = (string) ($r['anruf_status'] ?? '');
        if ((string) ($r['herkunft'] ?? '') === 'vecom') {
            if ($status === 'zugestimmt') { return 'interesse'; }
            return in_array($status, ['nicht_erreicht', 'nicht_erreichbar'], true) ? 'nicht-erreicht' : 'anrufen';
        }
        $an = (string) ($r['angeschrieben_am'] ?? '');
        if ($an === '') { return 'reserviert'; }
        return strtotime($an) <= time() - 3 * 86400 ? 'nachfassen' : 'angeschrieben';
    }

    /* ---------------------------------------------------------------- Bibliothek (Etappe 2) */

    /** Stand der Inhalte: jüngste Änderung der Inhaltsdateien. */
    public static function stand_datum(): string
    {
        $o = self::$ordner ?? dirname(__DIR__) . '/data/academy';
        $t = 0;
        foreach (['de', 'it', 'en'] as $l) { $t = max($t, (int) @filemtime("$o/$l.json")); }
        return $t > 0 ? date('Y-m-d', $t) : date('Y-m-d');
    }

    /**
     * Ein Dokument der Bibliothek. Slug aus DOKUMENTE oder „u<ID>“ (eigenes PDF der Verwaltung).
     * @return array{slug:string,titel:string,kategorie:string,version:string,stand:string,eigen:bool,id?:int}|null
     */
    public static function dokument(string $slug, string $sprache, bool $auchArchiv = false): ?array
    {
        if (isset(self::DOKUMENTE[$slug])) {
            [$modul, $zusatz, $kat] = self::DOKUMENTE[$slug];
            $titel = $modul !== '' ? (string) (self::modul($modul, $sprache)['titel'] ?? '') : '';
            if ($modul !== '' && $titel === '') { return null; }   // Modul abgeschaltet
            if ($zusatz === 'vereinbarung') { $titel = Texte::h(Texte::ACADEMY['d_regeln'], $sprache); }
            return ['slug' => $slug, 'titel' => $titel, 'kategorie' => $kat, 'version' => self::stand_datum(), 'stand' => self::stand_datum(), 'eigen' => false];
        }
        if (!preg_match('~^u(\d{1,9})$~', $slug, $m)) { return null; }
        try {
            $z = Db::one('SELECT id, titel, kategorie, sprache, version, updated_at, archiviert FROM academy_dokumente WHERE id = ?', [(int) $m[1]]);
        } catch (Throwable $e) { return null; }
        if (!$z || (!$auchArchiv && (int) $z['archiviert'] === 1)) { return null; }
        if (!$auchArchiv && !in_array((string) $z['sprache'], ['alle', $sprache], true)) { return null; }
        return ['slug' => $slug, 'titel' => (string) $z['titel'], 'kategorie' => (string) $z['kategorie'], 'version' => 'v' . (int) $z['version'],
                'stand' => substr((string) $z['updated_at'], 0, 10), 'eigen' => true, 'id' => (int) $z['id']];
    }

    /** @return list<array> Alle Dokumente, die ein Partner in dieser Sprache sieht. */
    public static function dokumente(string $sprache): array
    {
        $o = [];
        foreach (array_keys(self::DOKUMENTE) as $s) { if ($d = self::dokument($s, $sprache)) { $o[] = $d; } }
        try {
            foreach (Db::all("SELECT id FROM academy_dokumente WHERE archiviert = 0 AND sprache IN ('alle', ?) ORDER BY id", [$sprache]) as $z) {
                if ($d = self::dokument('u' . (int) $z['id'], $sprache)) { $o[] = $d; }
            }
        } catch (Throwable $e) { }
        return $o;
    }

    /** Inhalt eines eigenen PDFs (nur nicht archivierte, passende Sprache). */
    public static function eigenesPdf(int $id, string $sprache): ?string
    {
        if (!self::dokument('u' . $id, $sprache)) { return null; }
        $b = Db::wert('SELECT datei FROM academy_dokumente WHERE id = ?', [$id], null);
        return is_string($b) ? $b : null;
    }

    /** Hochgeladenes PDF prüfen und speichern; mit $ersetzeId wird es eine neue Version. @return int|string ID oder Fehlercode */
    public static function pdfSpeichern(string $pfad, string $name, string $titel, string $kategorie, string $sprache, int $ersetzeId = 0): int|string
    {
        if (!is_file($pfad)) { return 'keine_datei'; }
        $groesse = (int) filesize($pfad);
        if ($groesse <= 0 || $groesse > self::PDF_MAX) { return 'zu_gross'; }
        $kopf = (string) file_get_contents($pfad, false, null, 0, 5);
        $typ = function_exists('finfo_open') ? (string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $pfad) : 'application/pdf';
        if ($kopf !== '%PDF-' || $typ !== 'application/pdf') { return 'kein_pdf'; }
        $titel = trim(mb_substr($titel, 0, 160));
        if ($titel === '') { return 'titel'; }
        $kategorie = preg_match('~^[a-z]{2,20}$~', $kategorie) ? $kategorie : 'eigene';
        $sprache = in_array($sprache, ['alle', 'it', 'de', 'en'], true) ? $sprache : 'alle';
        $datei = (string) preg_replace('~[^A-Za-z0-9._-]+~', '-', mb_substr(basename($name), 0, 100));
        if (!str_ends_with(strtolower($datei), '.pdf')) { $datei .= '.pdf'; }
        $inhalt = (string) file_get_contents($pfad);
        if ($ersetzeId > 0) {
            $n = Db::run('UPDATE academy_dokumente SET titel = ?, kategorie = ?, sprache = ?, dateiname = ?, groesse = ?, datei = ?,
                           version = version + 1, archiviert = 0, updated_at = NOW() WHERE id = ?', [$titel, $kategorie, $sprache, $datei, $groesse, $inhalt, $ersetzeId])->rowCount();
            return $n === 1 ? $ersetzeId : 'unbekannt';
        }
        Db::run('INSERT INTO academy_dokumente (titel, kategorie, sprache, dateiname, groesse, datei) VALUES (?, ?, ?, ?, ?, ?)',
            [$titel, $kategorie, $sprache, $datei, $groesse, $inhalt]);
        return (int) Db::wert('SELECT LAST_INSERT_ID()');
    }

    public static function pdfArchivieren(int $id, bool $archiv): bool
    {
        return Db::run('UPDATE academy_dokumente SET archiviert = ? WHERE id = ?', [$archiv ? 1 : 0, $id])->rowCount() === 1;
    }

    public static function gesehen(int $partnerId, string $ziel): void
    {
        if (!preg_match('~^[a-z0-9-]{1,40}$~', $ziel)) { return; }
        try { Db::run('INSERT INTO academy_gesehen (partner_id, ziel) VALUES (?, ?) ON DUPLICATE KEY UPDATE am = NOW()', [$partnerId, $ziel]); } catch (Throwable $e) { }
    }

    /** @return list<string> Zuletzt angesehene Dokumente (Slugs), neueste zuerst. */
    public static function zuletzt(int $partnerId, int $n = 3): array
    {
        try {
            return array_map(static fn($z) => (string) $z['ziel'],
                Db::all('SELECT ziel FROM academy_gesehen WHERE partner_id = ? ORDER BY am DESC LIMIT ' . max(1, min(20, $n)), [$partnerId]));
        } catch (Throwable $e) { return []; }
    }

    /* ---------------------------------------------------------------- Neue Schulung melden (Etappe 2) */

    /**
     * Ziel einer Meldung „Neue Schulung verfügbar“: ein Modul oder ein Dokument.
     * @return array{titel:string, ak:array}|null Titel in der Sprache und die Academy-Adresse
     */
    public static function meldeZiel(string $ziel, string $sprache): ?array
    {
        [$art, $slug] = array_pad(explode(':', $ziel, 2), 2, '');
        if ($art === 'modul' && ($m = self::modul($slug, $sprache))) {
            return ['titel' => (string) $m['titel'], 'ak' => ['ak' => 'modul', 'm' => $slug, 'l' => '0']];
        }
        if ($art === 'pdf' && ($d = self::dokument($slug, $sprache))) {
            return ['titel' => (string) $d['titel'], 'ak' => ['ak' => 'bibliothek']];
        }
        return null;
    }

    /**
     * „Neue Schulung verfügbar“: merkt sich das Ziel (Hinweis auf der Academy-Startseite,
     * 14 Tage) und schickt allen freigeschalteten Partnern einen Hinweis aufs Handy —
     * jedem in seiner Sprache. Nur aus der Verwaltung, mit Rückfrage (Ablauf::TRAGWEITE).
     * @return array{ok:bool, partner:int, push:int}
     */
    public static function melden(string $ziel): array
    {
        if (self::meldeZiel($ziel, 'de') === null) { return ['ok' => false, 'partner' => 0, 'push' => 0]; }
        require_once __DIR__ . '/PartnerPost.php';
        require_once __DIR__ . '/PartnerSchutz.php';
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('academy_neu', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)",
            [json_encode(['ziel' => $ziel, 'am' => date('Y-m-d')])]);
        $np = 0; $push = 0;
        foreach (Db::all("SELECT p.* FROM partner p WHERE p.status = 'aktiv' AND " . PartnerSchutz::sqlFrei('p')) as $p) {
            $sp = in_array((string) $p['sprache'], self::SPRACHEN, true) ? (string) $p['sprache'] : 'it';
            $z = self::meldeZiel($ziel, $sp);
            if (!$z) { continue; }
            $np++;
            $link = Partner::portalLink($p) . '&' . http_build_query($z['ak']);
            try {
                $push += PartnerPost::push((int) $p['id'], Texte::h(Texte::ACADEMY['neu_titel'], $sp),
                    strtr(Texte::h(Texte::ACADEMY['neu_text'], $sp), ['{was}' => $z['titel']]), $link);
            } catch (Throwable $e) { }
        }
        return ['ok' => true, 'partner' => $np, 'push' => $push];
    }

    /** Die letzte Meldung, solange sie jünger als 14 Tage ist. @return array{ziel:string, am:string}|null */
    public static function neu(): ?array
    {
        try { $j = json_decode((string) Db::wert("SELECT svalue FROM settings WHERE skey = 'academy_neu'", [], ''), true); } catch (Throwable $e) { return null; }
        if (!is_array($j) || empty($j['ziel']) || empty($j['am'])) { return null; }
        return strtotime((string) $j['am']) >= strtotime('-14 days') ? ['ziel' => (string) $j['ziel'], 'am' => (string) $j['am']] : null;
    }

    /* ---------------------------------------------------------------- Zähler und Platzhalter */

    /** Anonymer Tageszähler — nie mit Partner oder Freitext. */
    public static function zaehlen(string $art, string $ziel): void
    {
        if (!preg_match('~^[a-z]{2,12}$~', $art) || !preg_match('~^[a-z0-9:-]{1,40}$~', $ziel)) { return; }
        try {
            Db::run('INSERT INTO academy_zaehler (tag, art, ziel, n) VALUES (CURDATE(), ?, ?, 1) ON DUPLICATE KEY UPDATE n = n + 1', [$art, $ziel]);
        } catch (Throwable $e) { /* Statistik darf nie eine Seite kosten */ }
    }

    /** Dieselben Zahlen wie in der Vereinbarung und unter „Geld“. */
    public static function platzhalter(array $p, string $sprache): array
    {
        $satz = Partner::satzFuer($p);
        return [
            '{satz}' => $satz['art'] === 'fest'
                ? Fmt::geld($satz['wert']) . ($sprache === 'it' ? ' per vendita' : ($sprache === 'en' ? ' per sale' : ' je Verkauf'))
                : Partner::satzWort($satz),
            '{min}' => Fmt::geld(Partner::zahl('partner_mindest_cents')),
            '{tage}' => (string) max(14, Partner::zahl('partner_sperrtage')),
            '{zuordnung}' => (string) Partner::zahl('partner_zuordnung_monate'),
        ];
    }

    /** Statistik für die Verwaltung (Etappe 2 zeigt mehr): nur Zahlen, keine Namen. */
    public static function statistik(int $tage = 30): array
    {
        return [
            'partner_aktiv' => (int) Db::wert('SELECT COUNT(DISTINCT partner_id) FROM academy_fortschritt WHERE zuletzt_am >= NOW() - INTERVAL ? DAY', [$tage]),
            'module_fertig' => (int) Db::wert('SELECT COUNT(*) FROM academy_fortschritt WHERE fertig_am IS NOT NULL'),
            'oft' => Db::all('SELECT art, ziel, SUM(n) AS n FROM academy_zaehler WHERE tag >= CURDATE() - INTERVAL ? DAY GROUP BY art, ziel ORDER BY n DESC LIMIT 15', [$tage]),
            'einwaende' => Db::all("SELECT ziel, SUM(n) AS n FROM academy_zaehler WHERE art = 'einwand' AND tag >= CURDATE() - INTERVAL ? DAY GROUP BY ziel ORDER BY n DESC LIMIT 8", [$tage]),
            'pdfs' => Db::all("SELECT ziel, SUM(n) AS n FROM academy_zaehler WHERE art = 'pdf' AND tag >= CURDATE() - INTERVAL ? DAY GROUP BY ziel ORDER BY n DESC LIMIT 8", [$tage]),
            'partner_mit' => (int) Db::wert('SELECT COUNT(DISTINCT partner_id) FROM academy_fortschritt'),
            'je_modul' => Db::all('SELECT modul, COUNT(*) AS begonnen, SUM(fertig_am IS NOT NULL) AS fertig FROM academy_fortschritt GROUP BY modul'),
        ];
    }
}
