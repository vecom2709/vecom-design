<?php
declare(strict_types=1);

/* ==========================================================================
   Statistik.php — Wer kommt, woher, und was wirkt (25.09.2026).

   WARUM ES DIESE SEITE GIBT

   „Statistiken" stand seit dem 06.09. als letzte Platzhalterseite in der
   Verwaltung. Umsatz, Bestellungen und Pakete zeigt „Zahlen" (dashboard)
   schon — eine zweite Seite dafuer waere eine zweite Wahrheit. Was fehlte,
   liegt ausserhalb der Datenbank: Die Website schreibt seit Wochen zwei
   Zaehldateien, und keine davon war irgendwo zu sehen.

     besuche.csv  (z.php)  Datum · Stunde · Herkunfts-Domain · Geraeteart
     demo.csv     (d.php)  Datum · Stunde · Ereignis · Geraeteart

   Im Cockpit standen davon nur zwei Summen (gesamt, heute). Welche Demo
   Leute oeffnen, benutzen und bei welcher sie „So etwas fuer mich" druecken,
   stand nirgends — gezaehlt seit dem 23.09., gelesen von niemandem.

   Beide Dateien enthalten keine IP-Adresse und keinen Keks. Hier wird
   nur gezaehlt, nichts einzelnes angezeigt.

   WARUM DIE WURZEL EIN PARAMETER IST

   Die Pruefkette liest ihre eigenen Probedateien, nicht die echten. Ohne
   den Parameter koennte sie diese Klasse nur gegen den Webspace pruefen —
   also gar nicht.
   ========================================================================== */

final class Statistik
{
    /** Demo => [Kennung der Kachel, Praefixe der Ereignisse, Ereignisse fuer „So etwas fuer mich"]. */
    public const DEMOS = [
        'Villa'           => ['villa',   ['villa'],                                     []],
        'Autohaus'        => ['auto',    ['auto', 'modell', 'kleinwagen', 'mittelklasse'], ['cta-auto', 'auto-kunde']],
        'Shop (Schuh)'    => ['shop',    ['schuh', 'produkt'],                          ['cta-shop', 'produkt-kunde']],
        'Möbel (Tisch)'   => ['tisch',   ['tisch'],                                     ['tisch-kunde']],
        'Weingut'         => ['wein',    ['wein', 'ar-wein'],                           []],
        'Schmuck & Uhren' => ['schmuck', ['schmuck', 'ar-schmuck'],                     []],
        'Küche'           => ['kueche',  ['kueche', 'ar-kueche'],                       ['cta-kueche', 'kueche-kunde']],
        'Gastronomie'     => ['gastro',  ['gastro', 'ar-gastro', 'logo'],               []],
        'Friseur'         => ['salon',   ['salon', 'haar'],                             ['haar-kunde']],
        'Spedition'       => ['lkw',     ['lkw'],                                       []],
    ];

    /** Alles, was nicht zu einer Demo gehoert, mit einem Wort, das man versteht. */
    public const WEGE = [
        'zugang-hero'      => 'E-Mail oben im Aufmacher eingetragen',
        'zugang-kontakt'   => 'E-Mail unten im Kontakt eingetragen',
        'zugang-vorschau'  => 'E-Mail nach der 30-Sekunden-Vorschau eingetragen',
        'vorschau-gezeigt' => '30-Sekunden-Vorschau angesehen',
        'vorschau-logo'    => 'eigenes Logo in die Vorschau gelegt',
        'einblick'         => 'Dashboard-Einblick durchgeklickt',
        'ka-cta'           => '„So etwas für mich" im Ablauf gedrückt',
        'ka-gesendet'      => 'Ablauf-Anfrage abgeschickt',
        'whatsapp'         => 'WhatsApp-Knopf gedrückt',
        'anruf'            => 'Anruf-Knopf gedrückt',
        'rueckruf-offen'   => 'Rückruf-Formular geöffnet',
        'rueckruf-gesendet'=> 'Rückruf-Wunsch abgeschickt',
    ];

    public static function wurzel(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Liest eine Zaehldatei zeilenweise ab einem Tag. Gestreamt, nicht mit
     * file(): besuche.csv waechst jeden Tag und soll die Seite nicht mit
     * der Zeit langsamer machen, als sie sein muss.
     *
     * @return bool ob es die Datei gibt
     */
    private static function lesen(string $datei, string $ab, callable $je): bool
    {
        if (!is_readable($datei)) { return false; }
        $fh = fopen($datei, 'r');
        if (!$fh) { return false; }
        while (($z = fgets($fh)) !== false) {
            $t = explode("\t", rtrim($z, "\r\n"));
            // Das Datum steht vorn und ist ISO — ein Textvergleich genuegt.
            if (count($t) < 4 || strlen($t[0]) !== 10 || $t[0] < $ab) { continue; }
            $je($t);
        }
        fclose($fh);
        return true;
    }

    /** Besuche: Wochenverlauf, Quellen, Geraete, Stunden. */
    public static function besuche(int $wochen = 26, int $tage = 90, ?string $wurzel = null, ?string $heute = null): array
    {
        $heute ??= date('Y-m-d');
        // Wochen beginnen am Montag; die laufende Woche ist die letzte Saeule.
        $montag = date('Y-m-d', strtotime($heute . ' -' . ((int) date('N', strtotime($heute)) - 1) . ' days'));
        $start  = date('Y-m-d', strtotime("$montag -" . ($wochen - 1) . ' weeks'));
        $abTage = date('Y-m-d', strtotime("$heute -" . ($tage - 1) . ' days'));

        $woche = [];
        for ($i = 0; $i < $wochen; $i++) { $woche[date('Y-m-d', strtotime("$start +$i weeks"))] = 0; }
        $quellen = []; $geraete = ['Rechner' => 0, 'Handy' => 0]; $stunden = array_fill(0, 24, 0);
        $seiten = []; $kampagnen = [];
        $imZeitraum = 0; $heuteZahl = 0;

        $da = self::lesen(($wurzel ?? self::wurzel()) . '/besuche.csv', min($start, $abTage),
            static function (array $t) use (&$woche, &$quellen, &$geraete, &$stunden, &$imZeitraum, &$heuteZahl, &$seiten, &$kampagnen, $start, $abTage, $heute): void {
                /* Seit 26.09.2026 sechs Spalten (Seite, Kampagne); ältere
                   Zeilen haben vier und zählen weiter mit. */
                [$tag, $std, $host, $geraet] = $t;
                $seite = (string) ($t[4] ?? ''); $kampagne = (string) ($t[5] ?? '');
                if ($tag > $heute) { return; }
                if ($tag >= $start) {
                    $mo = date('Y-m-d', strtotime($tag . ' -' . ((int) date('N', strtotime($tag)) - 1) . ' days'));
                    if (isset($woche[$mo])) { $woche[$mo]++; }
                }
                if ($tag === $heute) { $heuteZahl++; }
                if ($tag < $abTage) { return; }
                $imZeitraum++;
                $q = $host === '' ? 'direkt / eigene Seite' : $host;
                $quellen[$q] = ($quellen[$q] ?? 0) + 1;
                if (isset($geraete[$geraet])) { $geraete[$geraet]++; }
                if ($seite !== '') { $seiten[$seite] = ($seiten[$seite] ?? 0) + 1; }
                if ($kampagne !== '') { $kampagnen[$kampagne] = ($kampagnen[$kampagne] ?? 0) + 1; }
                $h = (int) $std;
                if ($h >= 0 && $h < 24) { $stunden[$h]++; }
            });

        arsort($quellen); arsort($seiten); arsort($kampagnen);
        $wochenListe = [];
        foreach ($woche as $ab => $n) { $wochenListe[] = ['ab' => $ab, 'zahl' => $n]; }
        return [
            'da' => $da, 'tage' => $tage, 'heute' => $heuteZahl, 'summe' => $imZeitraum,
            'wochen' => $wochenListe, 'quellen' => array_slice($quellen, 0, 10, true),
            'geraete' => $geraete, 'stunden' => $stunden,
            'seiten' => array_slice($seiten, 0, 10, true), 'kampagnen' => array_slice($kampagnen, 0, 10, true),
        ];
    }

    /** Demos: je Kachel geoeffnet, benutzt, „So etwas fuer mich"; dazu die Wege und „Mein Betrieb ist …". */
    public static function demos(int $tage = 90, ?string $wurzel = null, ?string $heute = null): array
    {
        $heute ??= date('Y-m-d');
        $ab = date('Y-m-d', strtotime("$heute -" . ($tage - 1) . ' days'));
        $zahl = [];
        $da = self::lesen(($wurzel ?? self::wurzel()) . '/demo.csv', $ab,
            static function (array $t) use (&$zahl, $heute): void {
                if ($t[0] > $heute) { return; }
                $zahl[$t[2]] = ($zahl[$t[2]] ?? 0) + 1;
            });

        $demos = [];
        foreach (self::DEMOS as $name => [$kachel, $praefixe, $anfrage]) {
            $r = ['name' => $name, 'geoeffnet' => $zahl["demo-$kachel"] ?? 0, 'benutzt' => 0, 'anfrage' => 0];
            foreach ($zahl as $e => $n) {
                if (in_array($e, $anfrage, true)) { $r['anfrage'] += $n; continue; }
                if ($e === "demo-$kachel") { continue; }
                foreach ($praefixe as $p) {
                    if ($e === $p || str_starts_with($e, "$p-")) { $r['benutzt'] += $n; break; }
                }
            }
            $demos[] = $r;
        }
        usort($demos, static fn(array $a, array $b): int => [$b['geoeffnet'], $b['benutzt']] <=> [$a['geoeffnet'], $a['benutzt']]);

        $wege = [];
        foreach (self::WEGE as $e => $wort) { $wege[] = ['wort' => $wort, 'zahl' => $zahl[$e] ?? 0]; }

        // „Mein Betrieb ist …": gewaehlt und danach wirklich die Demo geoeffnet.
        $betrieb = [];
        foreach ($zahl as $e => $n) {
            if (preg_match('~^betrieb-oeffnen-([a-z]+)$~', $e, $m)) { $betrieb[$m[1]]['geoeffnet'] = $n; }
            elseif (preg_match('~^betrieb-([a-z]+)$~', $e, $m)) { $betrieb[$m[1]]['gewaehlt'] = $n; }
        }
        foreach ($betrieb as $k => $v) { $betrieb[$k] += ['gewaehlt' => 0, 'geoeffnet' => 0]; }
        uasort($betrieb, static fn(array $a, array $b): int => $b['gewaehlt'] <=> $a['gewaehlt']);

        // Fallstudien: gewaehlt · Kundenseite besucht · Vorher/Nachher.
        $arbeiten = [];
        foreach ($zahl as $e => $n) {
            if (preg_match('~^arbeit-(besuch-|vergleich-)?([a-z]+)$~', $e, $m)) {
                $art = ['' => 'gewaehlt', 'besuch-' => 'besucht', 'vergleich-' => 'vergleich'][$m[1]];
                $arbeiten[$m[2]][$art] = ($arbeiten[$m[2]][$art] ?? 0) + $n;
            }
        }
        foreach ($arbeiten as $k => $v) { $arbeiten[$k] += ['gewaehlt' => 0, 'besucht' => 0, 'vergleich' => 0]; }
        ksort($arbeiten);

        return ['da' => $da, 'tage' => $tage, 'summe' => array_sum($zahl),
                'demos' => $demos, 'wege' => $wege, 'betrieb' => $betrieb, 'arbeiten' => $arbeiten];
    }

    /** Umsatz je Kalenderjahr — „Zahlen" zeigt nur die letzten zwoelf Monate. */
    public static function umsatzJahre(): array
    {
        return Db::all(
            "SELECT YEAR(paid_at) AS jahr, SUM(amount_cents) AS summe, COUNT(*) AS zahlungen
             FROM payments WHERE status = 'bezahlt' AND paid_at IS NOT NULL
             GROUP BY jahr ORDER BY jahr DESC");
    }
}
