<?php
declare(strict_types=1);

require_once __DIR__ . '/MkKampagne.php';

/* ==========================================================================
   MkZielgruppe.php — Zielgruppen und Recherche
   (Growth Engine, Marketing-Studio Schritt 1, 01.10.2026, Uwe: „Ja, so bauen“).

   WER SCHREIBT

   Claude, über Uwes Claude-Abo — nicht der Server (Uwe: „soll hier rüber,
   da ich Abo bei Claude“). Claude liest über die Worker-Tür (akquise.php,
   Aktion marketing_daten) die echten Zahlen je Branche, recherchiert im Netz
   und liefert Profile und Funde als ENTWURF zurück (marketing_zielgruppe,
   marketing_recherche). Freigegeben wird nur hier, in der Verwaltung.

   WORAUF EIN PROFIL STEHT

   Auf zwei Beinen, und beide sind sichtbar:
     1. Datengrundlage — was Vecom selbst gemessen hat: geprüfte Betriebe,
        häufigste Befunde, Anteil ohne Website, Kampagnen dieser Branche.
     2. Recherche — mit Quellen (Titel, Adresse, Datum). Eine Aussage ohne
        Quelle und ohne eigene Zahl gehört nicht hinein.
   ========================================================================== */

final class MkZielgruppe
{
    public const LAENDER = ['IT' => 'Italien', 'DE' => 'Deutschland'];
    public const ARTEN = ['thema' => 'Thema', 'trend' => 'Trend', 'frage' => 'Häufige Frage', 'wettbewerb' => 'Wettbewerb', 'plattform' => 'Plattform'];
    public const STATUS_RECHERCHE = ['neu' => 'Neu', 'gemerkt' => 'Gemerkt', 'verwendet' => 'Verwendet', 'verworfen' => 'Verworfen'];

    /** Listen im Profil: Schlüssel => [Überschrift, höchstens Einträge]. */
    public const LISTEN = [
        'probleme'     => ['Probleme und Schmerzpunkte', 12],
        'wuensche'     => ['Wünsche und Ziele', 12],
        'einwaende'    => ['Einwände vor dem Kauf', 12],
        'fragen'       => ['Fragen, die sie stellen', 12],
        'suchbegriffe' => ['Wie sie suchen (Suchbegriffe)', 20],
        'kanaele'      => ['Wo sie erreichbar sind', 10],
        'botschaften'  => ['Botschaften, die tragen', 10],
        'organisch'    => ['Ideen für organische Inhalte', 12],
    ];
    /** Felder für bezahlte Werbung. */
    public const BEZAHLT = ['zielgruppe' => 'Zielgruppe für Anzeigen (Meta)', 'keywords' => 'Suchwörter für Google-Anzeigen', 'budget' => 'Budget-Einschätzung', 'hinweise' => 'Worauf achten'];

    /* ------------------------------------------------------------------ */
    /* Datengrundlage (echte eigene Zahlen)                                */
    /* ------------------------------------------------------------------ */

    /**
     * Was Vecom selbst über diese Branche weiß — gezählt, nicht geschätzt.
     * @return array{geprueft:int, ohne_website:int, firmen:int, score:?int, befunde:list<array{code:string,titel:string,anteil:float,n:int}>, kampagnen:array}
     */
    public static function datengrundlage(string $branche, string $land): array
    {
        $a = [$branche, $land];
        $wert = static fn(string $sql, array $p = []) => (int) (self::still(static fn() => Db::wert($sql, $p, 0), 0));
        $firmen = $wert('SELECT COUNT(*) FROM akq_firmen WHERE branche = ? AND land = ?', $a);
        $geprueft = $wert("SELECT COUNT(DISTINCT f.id) FROM akq_firmen f JOIN akq_audits au ON au.firma_id = f.id WHERE f.branche = ? AND f.land = ?", $a);
        $ohne = $wert("SELECT COUNT(*) FROM akq_firmen WHERE branche = ? AND land = ? AND (domain IS NULL OR domain = '' OR audit_status = 'keine_website')", $a);
        $score = self::still(static fn() => Db::wert('SELECT ROUND(AVG(score)) FROM akq_firmen WHERE branche = ? AND land = ? AND score IS NOT NULL', $a, null), null);
        $befunde = [];
        foreach ((array) self::still(static fn() => Db::all("SELECT b.code, MAX(b.titel) AS titel, COUNT(DISTINCT b.firma_id) AS n
                FROM akq_befunde b JOIN akq_firmen f ON f.id = b.firma_id
               WHERE f.branche = ? AND f.land = ? AND b.status = 'VERIFIED'
            GROUP BY b.code ORDER BY n DESC LIMIT 10", $a), []) as $r) {
            $befunde[] = ['code' => (string) $r['code'], 'titel' => (string) $r['titel'], 'n' => (int) $r['n'],
                          'anteil' => $geprueft > 0 ? round((int) $r['n'] / $geprueft * 100, 1) : 0.0];
        }
        $kamp = (array) self::still(static function () use ($branche): array {
            $l = MkKampagne::liste(date('Y-m-d', strtotime('-365 days')), date('Y-m-d'), ['branche' => $branche]);
            return ['anzahl' => count($l['kampagnen']), 'klicks' => $l['summe']['klicks'], 'leads' => $l['summe']['leads'], 'kunden' => $l['summe']['kunden'], 'umsatz' => $l['summe']['umsatz']];
        }, ['anzahl' => 0, 'klicks' => 0, 'leads' => 0, 'kunden' => 0, 'umsatz' => 0]);
        return ['firmen' => $firmen, 'geprueft' => $geprueft, 'ohne_website' => $ohne, 'score' => $score !== null ? (int) $score : null, 'befunde' => $befunde, 'kampagnen' => $kamp];
    }

    private static function still(callable $fn, mixed $ersatz): mixed
    {
        try { return $fn(); } catch (Throwable $e) { return $ersatz; }
    }

    /* ------------------------------------------------------------------ */
    /* Profile                                                             */
    /* ------------------------------------------------------------------ */

    /** Eine Liste von Texten säubern: nur Zeichenketten, gekürzt, ohne Leere und Doppel. */
    private static function liste(mixed $roh, int $max, int $laenge = 300): array
    {
        $aus = [];
        foreach (is_array($roh) ? $roh : [] as $v) {
            if (!is_string($v) && !is_numeric($v)) { continue; }
            $t = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $v)) ?? '');
            if ($t === '' || in_array($t, $aus, true)) { continue; }
            $aus[] = mb_substr($t, 0, $laenge);
            if (count($aus) >= $max) { break; }
        }
        return $aus;
    }

    private static function text(mixed $roh, int $laenge): string
    {
        return is_string($roh) ? mb_substr(trim(preg_replace('/[ \t]+/u', ' ', strip_tags($roh)) ?? ''), 0, $laenge) : '';
    }

    /** Quellen: nur http(s)-Adressen, Titel und Datum gekürzt. @return list<array{titel:string,url:string,datum:string}> */
    public static function quellen(mixed $roh, int $max = 20): array
    {
        $aus = [];
        foreach (is_array($roh) ? $roh : [] as $q) {
            if (!is_array($q)) { continue; }
            $url = trim((string) ($q['url'] ?? ''));
            if (!preg_match('~^https?://[^\s"<>]{4,480}$~i', $url)) { continue; }
            $aus[] = ['titel' => self::text($q['titel'] ?? $url, 200) ?: $url, 'url' => $url, 'datum' => self::text($q['datum'] ?? '', 20)];
            if (count($aus) >= $max) { break; }
        }
        return $aus;
    }

    /**
     * Ein Profil prüfen und säubern. Pflicht: Branche aus dem Wortschatz,
     * Land IT/DE, Titel, Kurzbeschreibung, mindestens drei Probleme und
     * mindestens eine Quelle.
     * @return array<string,mixed>|string
     */
    public static function pruefen(array $d): array|string
    {
        $branche = (string) ($d['branche'] ?? '');
        $land = strtoupper((string) ($d['land'] ?? ''));
        if (!isset(MkKampagne::branchen()[$branche])) { return 'Unbekannte Branche „' . mb_substr($branche, 0, 40) . '“.'; }
        if (!isset(self::LAENDER[$land])) { return 'Land muss IT oder DE sein.'; }
        $p = ['branche' => $branche, 'land' => $land, 'titel' => self::text($d['titel'] ?? '', 160), 'kurz' => self::text($d['kurz'] ?? '', 1200),
              'ansprache' => self::text($d['ansprache'] ?? '', 800)];
        if ($p['titel'] === '' || $p['kurz'] === '') { return 'Titel und Kurzbeschreibung fehlen.'; }
        foreach (self::LISTEN as $k => [, $max]) { $p[$k] = self::liste($d[$k] ?? [], $max); }
        if (count($p['probleme']) < 3) { return 'Mindestens drei Probleme — sonst ist es kein Profil.'; }
        $b = is_array($d['bezahlt'] ?? null) ? $d['bezahlt'] : [];
        $p['bezahlt'] = ['zielgruppe' => self::text($b['zielgruppe'] ?? '', 800), 'keywords' => self::liste($b['keywords'] ?? [], 25, 80),
                         'budget' => self::text($b['budget'] ?? '', 400), 'hinweise' => self::text($b['hinweise'] ?? '', 800)];
        $p['quellen'] = self::quellen($d['quellen'] ?? []);
        if ($p['quellen'] === []) { return 'Mindestens eine Quelle (http/https) — Aussagen ohne Beleg gehören nicht hinein.'; }
        return $p;
    }

    /**
     * Ein Profil von Claude übernehmen — immer als Entwurf. War schon eines
     * freigegeben, bleibt es als „vorher“ stehen, bis Uwe die Überarbeitung
     * freigibt oder verwirft.
     * @return array{ok:bool, id?:int, hinweis?:string}
     */
    public static function melden(array $d): array
    {
        $p = self::pruefen($d);
        if (is_string($p)) { return ['ok' => false, 'hinweis' => $p]; }
        $alt = Db::one('SELECT * FROM mk_zielgruppen WHERE branche = ? AND land = ?', [$p['branche'], $p['land']]);
        $json = json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($alt) {
            $vorher = $alt['status'] === 'freigegeben' ? $alt['profil'] : $alt['vorher'];
            Db::update('mk_zielgruppen', (int) $alt['id'], ['titel' => $p['titel'], 'profil' => $json, 'vorher' => $vorher, 'status' => 'entwurf']);
            $id = (int) $alt['id'];
        } else {
            $id = (int) Db::insert('mk_zielgruppen', ['branche' => $p['branche'], 'land' => $p['land'], 'titel' => $p['titel'], 'profil' => $json]);
        }
        Events::protokoll('zielgruppe_entwurf', 'Zielgruppe „' . $p['titel'] . '“ als Entwurf geliefert', null, null, null, ['zielgruppe_id' => $id]);
        return ['ok' => true, 'id' => $id];
    }

    public static function laden(int $id): ?array
    {
        $z = Db::one('SELECT * FROM mk_zielgruppen WHERE id = ?', [$id]);
        if (!$z) { return null; }
        $z['p'] = json_decode((string) $z['profil'], true) ?: [];
        $z['v'] = $z['vorher'] ? (json_decode((string) $z['vorher'], true) ?: null) : null;
        return $z;
    }

    public static function alle(): array
    {
        return Db::all('SELECT id, branche, land, titel, status, freigegeben_am, updated_at, vorher IS NOT NULL AS ueberarbeitung FROM mk_zielgruppen ORDER BY land, titel');
    }

    /** Freigegebene Profile (für Content und Kampagnen). */
    public static function freigegeben(string $branche, string $land): ?array
    {
        $z = Db::one("SELECT * FROM mk_zielgruppen WHERE branche = ? AND land = ?", [$branche, $land]);
        if (!$z) { return null; }
        $roh = $z['status'] === 'freigegeben' ? $z['profil'] : $z['vorher'];
        return $roh ? (json_decode((string) $roh, true) ?: null) : null;
    }

    public static function freigeben(int $id): ?string
    {
        $z = self::laden($id);
        if ($z === null) { return 'Zielgruppe nicht gefunden.'; }
        Db::update('mk_zielgruppen', $id, ['status' => 'freigegeben', 'vorher' => null, 'freigegeben_am' => date('Y-m-d H:i:s')]);
        Events::pruefspur('zielgruppe_freigegeben', 'mk_zielgruppen', $id, ['status' => $z['status']], ['status' => 'freigegeben']);
        return null;
    }

    /** Überarbeitung verwerfen: zurück zum zuletzt freigegebenen Stand — oder ganz weg, wenn es nie einen gab. */
    public static function verwerfen(int $id): ?string
    {
        $z = self::laden($id);
        if ($z === null) { return 'Zielgruppe nicht gefunden.'; }
        if ($z['vorher']) {
            $v = json_decode((string) $z['vorher'], true) ?: [];
            Db::update('mk_zielgruppen', $id, ['profil' => $z['vorher'], 'vorher' => null, 'status' => 'freigegeben', 'titel' => mb_substr((string) ($v['titel'] ?? $z['titel']), 0, 160)]);
        } else {
            Db::run('DELETE FROM mk_zielgruppen WHERE id = ?', [$id]);
        }
        Events::pruefspur('zielgruppe_verworfen', 'mk_zielgruppen', $id, ['status' => $z['status']], []);
        return null;
    }

    /** Branchen und Länder, für die es noch kein Profil gibt — nach Zahl geprüfter Betriebe. */
    public static function fehlend(int $max = 12): array
    {
        $da = [];
        foreach (Db::all('SELECT branche, land FROM mk_zielgruppen') as $r) { $da[$r['branche'] . '|' . $r['land']] = true; }
        $aus = [];
        foreach ((array) self::still(static fn() => Db::all("SELECT branche, land, COUNT(*) AS n FROM akq_firmen WHERE branche IS NOT NULL AND branche <> '' GROUP BY branche, land ORDER BY n DESC"), []) as $r) {
            if (isset($da[$r['branche'] . '|' . $r['land']]) || !isset(MkKampagne::branchen()[$r['branche']])) { continue; }
            $aus[] = ['branche' => (string) $r['branche'], 'land' => (string) $r['land'], 'firmen' => (int) $r['n']];
            if (count($aus) >= $max) { break; }
        }
        return $aus;
    }

    /* ------------------------------------------------------------------ */
    /* Recherche                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Funde übernehmen (bis zu 50 je Aufruf). Doppelte (gleiche Art, gleicher
     * Titel, gleiche Branche) werden übersprungen, nicht überschrieben.
     * @return array{ok:bool, neu:int, doppelt:int, fehler:list<string>}
     */
    public static function rechercheMelden(array $funde): array
    {
        $neu = 0; $doppelt = 0; $fehler = [];
        foreach (array_slice($funde, 0, 50) as $i => $f) {
            if (!is_array($f)) { $fehler[] = "#$i: kein Objekt"; continue; }
            $art = (string) ($f['art'] ?? '');
            $titel = self::text($f['titel'] ?? '', 200);
            $text = self::text($f['text'] ?? '', 1500);
            $branche = (string) ($f['branche'] ?? '');
            $land = strtoupper((string) ($f['land'] ?? ''));
            $quellen = self::quellen($f['quellen'] ?? [], 8);
            if (!isset(self::ARTEN[$art])) { $fehler[] = "#$i: unbekannte Art"; continue; }
            if ($titel === '' || $text === '') { $fehler[] = "#$i: Titel oder Text fehlt"; continue; }
            if ($quellen === []) { $fehler[] = "#$i: ohne Quelle"; continue; }
            if ($branche !== '' && !isset(MkKampagne::branchen()[$branche])) { $branche = ''; }
            if ($land !== '' && !isset(self::LAENDER[$land])) { $land = ''; }
            $fp = sha1($art . '|' . mb_strtolower($titel) . '|' . $branche . '|' . $land);
            if ((int) Db::wert('SELECT COUNT(*) FROM mk_recherche WHERE fingerabdruck = ?', [$fp], 0) > 0) { $doppelt++; continue; }
            Db::insert('mk_recherche', ['art' => $art, 'titel' => $titel, 'text' => $text, 'branche' => $branche, 'land' => $land,
                'relevanz' => max(1, min(5, (int) ($f['relevanz'] ?? 3))), 'quellen' => json_encode($quellen, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'fingerabdruck' => $fp]);
            $neu++;
        }
        return ['ok' => true, 'neu' => $neu, 'doppelt' => $doppelt, 'fehler' => $fehler];
    }

    public static function recherche(array $f = [], int $max = 100): array
    {
        $w = []; $a = [];
        $status = (string) ($f['status'] ?? '');
        if (isset(self::STATUS_RECHERCHE[$status])) { $w[] = 'status = ?'; $a[] = $status; } else { $w[] = "status <> 'verworfen'"; }
        if (isset(self::ARTEN[(string) ($f['art'] ?? '')])) { $w[] = 'art = ?'; $a[] = (string) $f['art']; }
        if (isset(MkKampagne::branchen()[(string) ($f['branche'] ?? '')])) { $w[] = 'branche = ?'; $a[] = (string) $f['branche']; }
        $zeilen = Db::all('SELECT * FROM mk_recherche WHERE ' . implode(' AND ', $w) . ' ORDER BY (status = \'neu\') DESC, relevanz DESC, created_at DESC LIMIT ' . max(1, min(500, $max)), $a);
        foreach ($zeilen as $i => $z) { $zeilen[$i]['q'] = json_decode((string) $z['quellen'], true) ?: []; }
        return $zeilen;
    }

    public static function rechercheStatus(int $id, string $status): ?string
    {
        if (!isset(self::STATUS_RECHERCHE[$status])) { return 'Unbekannter Status.'; }
        $n = Db::run('UPDATE mk_recherche SET status = ? WHERE id = ?', [$status, $id])->rowCount();
        return $n > 0 || Db::wert('SELECT id FROM mk_recherche WHERE id = ?', [$id], null) !== null ? null : 'Fund nicht gefunden.';
    }

    /* ------------------------------------------------------------------ */
    /* Was Claude zum Recherchieren bekommt                                */
    /* ------------------------------------------------------------------ */

    /**
     * Alles, was Claude für Recherche und Profile braucht — ohne Personen:
     * Branchenwortschatz, Datengrundlage je Branche mit Betrieben, vorhandene
     * Profile (Titel, Stand), die letzten Funde (nur Titel), Kampagnen je Branche.
     */
    public static function datenFuerClaude(?string $nurBranche = null, ?string $nurLand = null): array
    {
        $branchen = [];
        foreach ((array) self::still(static fn() => Db::all("SELECT branche, land, COUNT(*) AS n FROM akq_firmen WHERE branche IS NOT NULL AND branche <> '' GROUP BY branche, land ORDER BY n DESC"), []) as $r) {
            if ($nurBranche !== null && $r['branche'] !== $nurBranche) { continue; }
            if ($nurLand !== null && $r['land'] !== $nurLand) { continue; }
            if (!isset(MkKampagne::branchen()[$r['branche']])) { continue; }
            $branchen[] = ['branche' => (string) $r['branche'], 'land' => (string) $r['land'], 'name' => MkKampagne::branchen()[$r['branche']],
                           'daten' => self::datengrundlage((string) $r['branche'], (string) $r['land'])];
            if (count($branchen) >= 30) { break; }
        }
        return [
            'ok' => true, 'stand' => date('c'),
            'hinweis' => 'Nur Entwürfe liefern (marketing_zielgruppe, marketing_recherche). Jede Aussage mit Quelle oder eigener Zahl. Keine Personendaten.',
            'wortschatz' => MkKampagne::branchen(),
            'branchen' => $branchen,
            'profile' => array_map(static fn($z) => ['branche' => $z['branche'], 'land' => $z['land'], 'titel' => $z['titel'], 'status' => $z['status'], 'stand' => $z['updated_at']], self::alle()),
            'letzte_funde' => array_map(static fn($r) => ['art' => $r['art'], 'titel' => $r['titel'], 'branche' => $r['branche'], 'land' => $r['land']],
                (array) self::still(static fn() => Db::all('SELECT art, titel, branche, land FROM mk_recherche ORDER BY id DESC LIMIT 80'), [])),
            'schema_zielgruppe' => ['felder' => array_merge(['branche', 'land', 'titel', 'kurz', 'ansprache'], array_keys(self::LISTEN)),
                                    'bezahlt' => array_keys(self::BEZAHLT), 'quellen' => ['titel', 'url', 'datum']],
            'arten_recherche' => array_keys(self::ARTEN),
        ];
    }
}
