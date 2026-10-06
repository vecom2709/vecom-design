<?php
declare(strict_types=1);

/**
 * Das AI-Wissen (AI Office Stufe 2, V8, 07.10.2026): die Kapitel aus
 * PROJEKT.md, CLAUDE.md, VECOM-STANDARD.md und AKQUISE.md, wie sie
 * tools/wissen.mjs beim Bauen nach app/data/wissen.json schreibt.
 *
 * Hier wird nur gelesen und gesucht. Zerlegt wird an genau einer Stelle
 * (tools/wissen.mjs) — zwei Zerleger hätten früher oder später zwei
 * verschiedene Kapitellisten.
 *
 * Die Suche ist schlicht gehalten (BM25 über ein paar hundert Kapitel, im
 * Speicher): schnell genug, und das Ergebnis lässt sich nachvollziehen —
 * Claude bekommt die Stelle und liest dann selbst.
 */
final class Wissen
{
    /** So viel Text gibt ein Aufruf höchstens heraus; der Rest kommt mit „ab“. */
    public const SEITE = 20000;

    private static ?array $daten = null;

    public static function pfad(): string { return dirname(__DIR__) . '/data/wissen.json'; }

    /** @return array{stand:string, quellen:array, kapitel:array}|null */
    public static function laden(?string $pfad = null): ?array
    {
        if ($pfad === null && self::$daten !== null) { return self::$daten; }
        $p = $pfad ?? self::pfad();
        if (!is_file($p)) { return null; }
        $d = json_decode((string) file_get_contents($p), true);
        if (!is_array($d) || !isset($d['kapitel']) || !is_array($d['kapitel'])) { return null; }
        if ($pfad === null) { self::$daten = $d; }
        return $d;
    }

    /** Für die Prüfung: eine andere Datei unterschieben. */
    public static function setzen(?array $d): void { self::$daten = $d; }

    /** @return array{stand:string, quellen:array, kapitel:int}|null */
    public static function stand(): ?array
    {
        $d = self::laden();
        return $d === null ? null : ['stand' => (string) ($d['stand'] ?? ''), 'quellen' => (array) ($d['quellen'] ?? []), 'kapitel' => count($d['kapitel'])];
    }

    /** Das Inhaltsverzeichnis, wahlweise nur eine Quelle (z. B. „PROJEKT.md“) oder ab einem Datum. */
    public static function inhalt(string $quelle = '', string $seit = ''): array
    {
        $d = self::laden();
        if ($d === null) { return []; }
        $aus = [];
        foreach ($d['kapitel'] as $k) {
            if ($quelle !== '' && strcasecmp((string) $k['quelle'], $quelle) !== 0) { continue; }
            if ($seit !== '' && ((string) ($k['datum'] ?? '') === '' || (string) $k['datum'] < $seit)) { continue; }
            $aus[] = ['id' => $k['id'], 'quelle' => $k['quelle'], 'titel' => $k['titel'], 'datum' => $k['datum'], 'zeichen' => mb_strlen((string) $k['text'])];
        }
        return $aus;
    }

    /** Wörter, die in jedem zweiten Kapitel stehen und nichts unterscheiden. */
    private const LEER = ['und', 'oder', 'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'einen', 'einem', 'einer', 'vor', 'nach',
        'mit', 'für', 'von', 'bei', 'aus', 'auf', 'ist', 'sind', 'war', 'wird', 'werden', 'nicht', 'noch', 'nur', 'auch', 'wie', 'was',
        'wer', 'wann', 'warum', 'jeder', 'jede', 'jedem', 'jeden', 'alle', 'alles', 'hat', 'haben', 'kann', 'soll', 'über', 'unter', 'zum', 'zur'];

    /**
     * Suche nach BM25 (das übliche Maß der Volltextsuche): seltene Wörter zählen
     * mehr als häufige, ein Treffer im Titel dreifach, und ein langes Kapitel
     * gewinnt nicht allein dadurch, dass es lang ist. Gemessen am 07.10.2026:
     * mit bloßem Zählen stand bei „Kette vor jedem Deploy“ viermal das
     * 56.000-Zeichen-Protokoll vorn und nie das Kapitel aus CLAUDE.md.
     *
     * @return list<array{id:string, quelle:string, titel:string, datum:?string, auszug:string, punkte:int}>
     */
    public static function suchen(string $frage, int $anzahl = 8): array
    {
        $d = self::laden();
        if ($d === null) { return []; }
        $woerter = array_values(array_unique(array_filter(
            preg_split('/[^\p{L}\p{N}_-]+/u', mb_strtolower($frage)) ?: [],
            static fn($w) => mb_strlen($w) >= 3 && !in_array($w, self::LEER, true))));
        if ($woerter === []) { return []; }
        $n = count($d['kapitel']);
        $laengen = []; $tf = []; $df = array_fill_keys($woerter, 0);
        foreach ($d['kapitel'] as $i => $k) {
            $text = mb_strtolower((string) $k['text']);
            $titel = mb_strtolower((string) $k['titel']);
            $laengen[$i] = max(1, mb_strlen($text));
            foreach ($woerter as $w) {
                $c = substr_count($text, $w); $t = substr_count($titel, $w);
                $tf[$i][$w] = [$c, $t];
                if ($c + $t > 0) { $df[$w]++; }
            }
        }
        $mittel = array_sum($laengen) / max(1, $n);
        $treffer = [];
        foreach ($d['kapitel'] as $i => $k) {
            $punkte = 0.0; $gefunden = 0;
            foreach ($woerter as $w) {
                [$c, $t] = $tf[$i][$w];
                if ($c + $t === 0) { continue; }
                $gefunden++;
                $idf = log(1 + ($n - $df[$w] + 0.5) / ($df[$w] + 0.5));
                $punkte += $idf * ($c * 2.2) / ($c + 1.2 * (0.25 + 0.75 * $laengen[$i] / $mittel)) + 3 * $idf * min($t, 1);
            }
            if ($gefunden === 0) { continue; }
            $punkte *= $gefunden / count($woerter);   // wer alle Wörter hat, steht vorn
            $treffer[] = ['id' => $k['id'], 'quelle' => $k['quelle'], 'titel' => $k['titel'], 'datum' => $k['datum'],
                          'auszug' => self::auszug((string) $k['text'], $woerter), 'punkte' => (int) round($punkte * 100)];
        }
        usort($treffer, static fn($a, $b) => $b['punkte'] <=> $a['punkte']);
        return array_slice($treffer, 0, max(1, min(20, $anzahl)));
    }

    /** Ein Kapitel, seitenweise. @return array{id:string,quelle:string,titel:string,datum:?string,text:string,ab:int,weiter:?int,zeichen:int}|null */
    public static function kapitel(string $id, int $ab = 0): ?array
    {
        $d = self::laden();
        if ($d === null) { return null; }
        foreach ($d['kapitel'] as $k) {
            if ((string) $k['id'] !== $id) { continue; }
            $text = (string) $k['text'];
            $gesamt = mb_strlen($text);
            $ab = max(0, min($ab, $gesamt));
            $stueck = mb_substr($text, $ab, self::SEITE);
            $weiter = $ab + mb_strlen($stueck) < $gesamt ? $ab + mb_strlen($stueck) : null;
            return ['id' => $k['id'], 'quelle' => $k['quelle'], 'titel' => $k['titel'], 'datum' => $k['datum'],
                    'text' => $stueck, 'ab' => $ab, 'weiter' => $weiter, 'zeichen' => $gesamt];
        }
        return null;
    }

    /** Rund 300 Zeichen um die erste Fundstelle. */
    private static function auszug(string $text, array $woerter): string
    {
        $klein = mb_strtolower($text);
        $pos = null;
        foreach ($woerter as $w) {
            $p = mb_strpos($klein, $w);
            if ($p !== false && ($pos === null || $p < $pos)) { $pos = $p; }
        }
        $start = max(0, (int) $pos - 120);
        $s = trim(preg_replace('/\s+/u', ' ', mb_substr($text, $start, 320)) ?? '');
        return ($start > 0 ? '… ' : '') . $s . ' …';
    }
}
