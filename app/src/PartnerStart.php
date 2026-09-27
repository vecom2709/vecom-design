<?php
declare(strict_types=1);

/**
 * Erste Schritte und Wochenverlauf im Partner-Dashboard (27.09.2026, Uwe: Ja).
 *
 * ERSTE SCHRITTE: Neue Partner sahen bisher genau einen „nächsten Schritt“ --
 * richtig, aber ohne Gefühl dafür, wie weit es noch ist. Die Liste zeigt alle
 * sechs auf einmal, abgehakt, was erledigt ist, und hebt nur den nächsten
 * offenen hervor (ein Ding je Bildschirm). Sind alle erledigt, verschwindet
 * sie ganz -- eine Checkliste, die für immer „6 von 6“ zeigt, ist Rauschen.
 *
 * Jeder Haken wird aus Tatsachen in der Datenbank abgelesen, nicht vom
 * Partner abgehakt: Ein Häkchen, das man selbst setzen kann, lügt irgendwann.
 *
 * WOCHENVERLAUF: Besuche der Empfehlungsseite, neue Kunden, Verkäufe -- je
 * Woche, die letzten acht. Besuche sind die Tageszähler aus partner_klicks
 * (ohne IP, ohne Keks); mehr wissen wir nicht und wollen es auch nicht.
 */
final class PartnerStart
{
    public const SCHRITTE = ['vereinbarung', 'weg', 'profil', 'seite', 'teilen', 'app'];
    public const ANKER = ['vereinbarung' => 'start', 'weg' => 'wege', 'profil' => 'profil', 'seite' => 'seite', 'teilen' => 'werbung', 'app' => 'app'];
    public const WOCHEN = 8;

    /**
     * @return array{erledigt:array<string,bool>, n:int, alle:int, naechster:?string}
     */
    public static function schritte(array $p): array
    {
        $id = (int) $p['id'];
        $weg = PartnerWege::weg($p);
        $e = [
            'vereinbarung' => !empty($p['vereinbarung_am']),
            'weg' => $weg !== null && PartnerWege::bereit($p, $weg),
            'profil' => !empty($p['foto_am']) || trim((string) ($p['profil_satz'] ?? '')) !== '',
            'seite' => !empty($p['seite_am']),
            'teilen' => (int) self::still(static fn() => Db::wert('SELECT COALESCE(SUM(anzahl),0) FROM partner_klicks WHERE partner_id = ?', [$id], 0), 0) > 0
                        || (int) self::still(static fn() => Db::wert('SELECT COUNT(*) FROM partner_zuordnungen WHERE partner_id = ?', [$id], 0), 0) > 0,
            'app' => (int) self::still(static fn() => Db::wert('SELECT COUNT(*) FROM partner_push WHERE partner_id = ?', [$id], 0), 0) > 0,
        ];
        $naechster = null;
        foreach (self::SCHRITTE as $s) { if (!$e[$s]) { $naechster = $s; break; } }
        return ['erledigt' => $e, 'n' => count(array_filter($e)), 'alle' => count($e), 'naechster' => $naechster];
    }

    /**
     * Die letzten acht Wochen, älteste zuerst. Die laufende Woche zählt mit
     * (bis heute) -- sonst sähe ein Partner, der heute geteilt hat, nichts.
     *
     * @return list<array{montag:string, besuche:int, kunden:int, verkaeufe:int}>
     */
    public static function wochen(int $partnerId, ?int $jetzt = null): array
    {
        $jetzt ??= time();
        $montag = strtotime('monday this week', $jetzt);
        $start = strtotime('-' . (self::WOCHEN - 1) . ' weeks', $montag);
        $aus = [];
        for ($i = 0; $i < self::WOCHEN; $i++) {
            $aus[date('Y-m-d', strtotime('+' . $i . ' weeks', $start))] = ['besuche' => 0, 'kunden' => 0, 'verkaeufe' => 0];
        }
        $von = date('Y-m-d', $start);
        $woche = static function (string $tag): string {
            $t = strtotime(substr($tag, 0, 10) . ' 12:00');
            return date('Y-m-d', strtotime('monday this week', $t));
        };
        foreach (self::still(static fn() => Db::all('SELECT tag, anzahl FROM partner_klicks WHERE partner_id = ? AND tag >= ?', [$partnerId, $von]), []) as $z) {
            $w = $woche((string) $z['tag']); if (isset($aus[$w])) { $aus[$w]['besuche'] += (int) $z['anzahl']; }
        }
        foreach (self::still(static fn() => Db::all('SELECT created_at FROM partner_zuordnungen WHERE partner_id = ? AND created_at >= ?', [$partnerId, $von . ' 00:00:00']), []) as $z) {
            $w = $woche((string) $z['created_at']); if (isset($aus[$w])) { $aus[$w]['kunden']++; }
        }
        foreach (self::still(static fn() => Db::all("SELECT created_at FROM partner_provisionen WHERE partner_id = ? AND status <> 'storniert' AND created_at >= ?", [$partnerId, $von . ' 00:00:00']), []) as $z) {
            $w = $woche((string) $z['created_at']); if (isset($aus[$w])) { $aus[$w]['verkaeufe']++; }
        }
        $liste = [];
        foreach ($aus as $m => $w) { $liste[] = ['montag' => $m] + $w; }
        return $liste;
    }

    /**
     * Balken als SVG, auf dem Server gezeichnet -- kein Diagramm-Skript für
     * acht Zahlen. Besuche als ruhiger Balken, neue Kunden als Goldbalken
     * davor, Verkäufe als Zahl darüber. Die Zahlen stehen zusätzlich als
     * Tabelle daneben (für Bildschirmleser und wer es genau wissen will).
     *
     * @param list<array{montag:string,besuche:int,kunden:int,verkaeufe:int}> $wochen
     */
    public static function svg(array $wochen, string $beschriftung): string
    {
        $b = 360; $h = 150; $unten = 20; $oben = 30; $n = max(1, count($wochen));
        $max = max(1, ...array_map(static fn($w) => max($w['besuche'], $w['kunden']), $wochen));
        $spalte = $b / $n; $breite = min(30, $spalte * 0.64);
        $s = '<svg viewBox="0 0 ' . $b . ' ' . $h . '" width="100%" role="img" aria-label="' . htmlspecialchars($beschriftung, ENT_QUOTES) . '" class="ws-svg">';
        $s .= '<line x1="0" y1="' . ($h - $unten) . '" x2="' . $b . '" y2="' . ($h - $unten) . '" stroke="currentColor" stroke-opacity=".18"/>';
        foreach ($wochen as $i => $w) {
            $x = $i * $spalte + ($spalte - $breite) / 2;
            $hoehe = ($h - $unten - $oben);
            $hb = $w['besuche'] > 0 ? max(3, $hoehe * $w['besuche'] / $max) : 0;
            $hk = $w['kunden'] > 0 ? max(4, $hoehe * $w['kunden'] / $max) : 0;
            if ($hb > 0) { $s .= '<rect x="' . round($x, 1) . '" y="' . round($h - $unten - $hb, 1) . '" width="' . round($breite, 1) . '" height="' . round($hb, 1) . '" rx="4" class="ws-b"/>'; }
            if ($hk > 0) { $s .= '<rect x="' . round($x + $breite * 0.22, 1) . '" y="' . round($h - $unten - $hk, 1) . '" width="' . round($breite * 0.56, 1) . '" height="' . round($hk, 1) . '" rx="3" class="ws-k"/>'; }
            $mitte = round($x + $breite / 2, 1);
            if ($w['besuche'] > 0) { $s .= '<text x="' . $mitte . '" y="' . round($h - $unten - $hb - 4, 1) . '" text-anchor="middle" class="ws-z">' . $w['besuche'] . '</text>'; }
            if ($w['verkaeufe'] > 0) { $s .= '<text x="' . $mitte . '" y="' . round(min($h - $unten - $hb - 17, $h - $unten - 19), 1) . '" text-anchor="middle" class="ws-v">★' . $w['verkaeufe'] . '</text>'; }
            $s .= '<text x="' . $mitte . '" y="' . ($h - 5) . '" text-anchor="middle" class="ws-d">' . date('d.m', strtotime($w['montag'])) . '</text>';
        }
        return $s . '</svg>';
    }

    /** @template T @param callable():T $f @param T $sonst @return T */
    private static function still(callable $f, mixed $sonst = null): mixed
    {
        try { return $f(); } catch (Throwable $e) { return $sonst; }
    }
}
