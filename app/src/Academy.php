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
    public const MERK_ARTEN = ['modul', 'einwand', 'leistung', 'kontakt'];
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
        $daten += ['module' => [], 'kontakt' => [], 'einwaende' => [], 'leistungen' => [], 'finder' => [], 'woerter' => []];
        usort($daten['module'], static fn($a, $b) => (int) $a['nr'] <=> (int) $b['nr']);
        return self::$cache[$sprache] = $daten;
    }

    public static function modul(string $slug, string $sprache): ?array
    {
        foreach (self::inhalte($sprache)['module'] as $m) { if ($m['slug'] === $slug) { return $m; } }
        return null;
    }

    public static function eintrag(string $art, string $slug, string $sprache): ?array
    {
        $liste = match ($art) { 'einwand' => 'einwaende', 'leistung' => 'leistungen', 'kontakt' => 'kontakt', 'modul' => 'module', default => '' };
        if ($liste === '') { return null; }
        foreach (self::inhalte($sprache)[$liste] as $e) { if (($e['slug'] ?? '') === $slug) { return $e; } }
        return null;
    }

    /** Gibt es diesen Eintrag? Nur bekannte Slugs werden gespeichert. */
    public static function gibt(string $art, string $slug): bool
    {
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
        ];
    }
}
