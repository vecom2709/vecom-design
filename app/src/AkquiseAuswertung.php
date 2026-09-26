<?php
declare(strict_types=1);

require_once __DIR__ . '/Akquise.php';

/**
 * Trichter, Wochenziel und Karte der Akquise (26.09.2026, Uwe: Ja).
 *
 * TRICHTER: Wie viele Betriebe kommen von Stufe zu Stufe -- gefunden, geprüft,
 * angesprochen, Analyse-Seite geöffnet, geantwortet, interessiert, Kunde.
 * Nach Branche, Kanal oder Textvariante. Die Frage dahinter ist nicht „wie
 * viel haben wir verschickt“, sondern „was davon wirkt“ -- deshalb steht
 * neben jeder Zahl der Anteil an der Stufe davor.
 *
 * Gezählt wird je BETRIEB, nicht je Versand: Ein Betrieb, der auf den Brief
 * antwortet, zählt einmal, auch wenn danach drei Mails hin und her gingen.
 */
final class AkquiseAuswertung
{
    public const STUFEN = ['gefunden', 'geprueft', 'angesprochen', 'geoeffnet', 'antwort', 'interesse', 'kunde'];
    public const POSITIV = ['INTERESTED', 'MORE_INFO', 'CALL_REQUEST', 'PRICE_REQUEST'];
    public const WOCHENZIEL_VORGABE = 10;

    /**
     * @param string $nach branche | kanal | variante
     * @return list<array{gruppe:string, werte:array<string,int>}>
     */
    public static function trichter(string $nach = 'branche', int $tage = 365): array
    {
        $seit = date('Y-m-d H:i:s', strtotime('-' . max(1, $tage) . ' days'));
        $pos = "'" . implode("','", self::POSITIV) . "'";
        // Je Betrieb: der letzte Versand (Kanal, Vorlage) im Zeitraum.
        $zeilen = Db::all("SELECT f.id, f.branche, f.audit_status, f.kontakt_status,
                                  v.kanal, vo.variante,
                                  (SELECT COALESCE(MAX(a.aufrufe), 0) FROM akq_analysen a WHERE a.firma_id = f.id) AS aufrufe,
                                  (SELECT COUNT(*) FROM akq_antworten r WHERE r.firma_id = f.id) AS antworten,
                                  (SELECT COUNT(*) FROM akq_antworten r WHERE r.firma_id = f.id AND r.klasse IN ($pos)) AS positiv
                             FROM akq_firmen f
                        LEFT JOIN akq_versand v ON v.id = (SELECT MAX(v2.id) FROM akq_versand v2 WHERE v2.firma_id = f.id AND v2.status IN ('gesendet','von_hand') AND v2.created_at >= ?)
                        LEFT JOIN akq_vorlagen vo ON vo.id = v.vorlage_id
                            WHERE f.created_at >= ? OR v.id IS NOT NULL", [$seit, $seit]);
        $gruppen = [];
        foreach ($zeilen as $z) {
            $angesprochen = $z['kanal'] !== null;
            if ($nach !== 'branche' && !$angesprochen) { continue; }   // Kanal und Variante gibt es erst ab der Ansprache
            $g = match ($nach) {
                'kanal' => (string) $z['kanal'],
                'variante' => (string) ($z['variante'] ?? '–'),
                default => (string) ($z['branche'] ?? ''),
            };
            $gruppen[$g] ??= array_fill_keys(self::STUFEN, 0);
            $w = &$gruppen[$g];
            $w['gefunden']++;
            if ($z['audit_status'] === 'fertig' || $angesprochen) { $w['geprueft']++; }
            if ($angesprochen) { $w['angesprochen']++; }
            if ($angesprochen && (int) $z['aufrufe'] > 0) { $w['geoeffnet']++; }
            if ($angesprochen && (int) $z['antworten'] > 0) { $w['antwort']++; }
            if ($angesprochen && (int) $z['positiv'] > 0) { $w['interesse']++; }
            if ($z['kontakt_status'] === 'kunde') { $w['kunde']++; }
            unset($w);
        }
        uasort($gruppen, static fn($a, $b) => [$b['angesprochen'], $b['gefunden']] <=> [$a['angesprochen'], $a['gefunden']]);
        $aus = [];
        foreach ($gruppen as $g => $w) { $aus[] = ['gruppe' => (string) $g, 'werte' => $w]; }
        return $aus;
    }

    /** Summe über alle Gruppen. @param list<array{gruppe:string,werte:array<string,int>}> $zeilen */
    public static function summe(array $zeilen): array
    {
        $s = array_fill_keys(self::STUFEN, 0);
        foreach ($zeilen as $z) { foreach ($z['werte'] as $k => $v) { $s[$k] += $v; } }
        return $s;
    }

    public static function wochenziel(): int
    {
        $w = (int) Db::wert("SELECT svalue FROM settings WHERE skey = 'akq_wochenziel'", [], self::WOCHENZIEL_VORGABE);
        return $w > 0 ? $w : self::WOCHENZIEL_VORGABE;
    }

    public static function wochenzielSetzen(int $n): void
    {
        $n = max(1, min(500, $n));
        Db::run("INSERT INTO settings (skey, svalue) VALUES ('akq_wochenziel', ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)", [(string) $n]);
    }

    /**
     * Stand der laufenden Woche (Montag bis heute): Ansprachen je Tag.
     * @return array{ziel:int, erreicht:int, tage:array<string,int>, montag:string}
     */
    public static function woche(?int $jetzt = null): array
    {
        $jetzt ??= time();
        $montag = date('Y-m-d', strtotime('monday this week', $jetzt));
        $tage = [];
        for ($i = 0; $i < 7; $i++) { $tage[date('Y-m-d', strtotime($montag . ' +' . $i . ' days'))] = 0; }
        foreach (Db::all("SELECT DATE(created_at) AS tag, COUNT(DISTINCT firma_id) AS n FROM akq_versand
                           WHERE status IN ('gesendet','von_hand') AND created_at >= ? GROUP BY DATE(created_at)", [$montag . ' 00:00:00']) as $r) {
            if (isset($tage[(string) $r['tag']])) { $tage[(string) $r['tag']] = (int) $r['n']; }
        }
        return ['ziel' => self::wochenziel(), 'erreicht' => array_sum($tage), 'tage' => $tage, 'montag' => $montag];
    }

    /**
     * Punkte für die Karte: nur Betriebe mit Koordinaten, keine Kontaktdaten.
     * farbe: gruen (starke Chance, noch frei) · gelb (Chance) · grau (schon
     * kontaktiert / geringe Chance) · rot (gesperrt/abgelehnt) · blau (Kunde).
     * @return list<array{id:int,n:string,la:float,lo:float,f:string,o:string,b:string,s:?int}>
     */
    public static function kartenpunkte(int $max = 3000): array
    {
        $aus = [];
        foreach (Db::all('SELECT id, name, lat, lon, stadt, branche, score, kontakt_status, gesperrt, url FROM akq_firmen
                           WHERE lat IS NOT NULL AND lon IS NOT NULL ORDER BY COALESCE(score, 0) DESC LIMIT ' . max(1, $max)) as $z) {
            $s = $z['score'] === null ? null : (int) $z['score'];
            $f = match (true) {
                (int) $z['gesperrt'] === 1 || in_array($z['kontakt_status'], ['abgelehnt', 'gesperrt'], true) => 'rot',
                $z['kontakt_status'] === 'kunde' => 'blau',
                in_array($z['kontakt_status'], ['kontaktiert', 'geantwortet'], true) => 'grau',
                trim((string) $z['url']) === '' || ($s ?? 0) >= 71 => 'gruen',
                ($s ?? 0) >= 51 => 'gelb',
                default => 'grau',
            };
            $aus[] = ['id' => (int) $z['id'], 'n' => (string) $z['name'], 'la' => (float) $z['lat'], 'lo' => (float) $z['lon'], 'f' => $f,
                      'o' => (string) ($z['stadt'] ?? ''), 'b' => Akquise::branchenName($z['branche']), 's' => $s];
        }
        return $aus;
    }
}
