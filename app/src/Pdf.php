<?php
declare(strict_types=1);

/**
 * Ein sehr kleiner PDF-Schreiber — genug fuer einen einseitigen Beleg.
 *
 * Warum selbst geschrieben: Auf dem Webspace gibt es keinen Composer und
 * keine PDF-Erweiterung. Eine fremde Bibliothek haette bedeutet, ein paar
 * hundert Dateien per FTP hochzuladen und sie fortan zu pflegen. Ein Beleg
 * besteht aus Text an festen Stellen und ein paar Linien — dafuer reicht
 * das hier, und es kann nichts kaputtgehen, was ich nicht sehe.
 *
 * Benutzt werden nur die 14 Standardschriften (hier Helvetica), die jedes
 * PDF-Programm ohnehin kennt. Nichts wird eingebettet, deshalb bleiben die
 * Dateien winzig.
 *
 * Koordinaten werden von OBEN LINKS gezaehlt, in Punkt (72 = 1 Zoll).
 * Intern rechnet PDF von unten — das nimmt diese Klasse ab.
 */
final class Pdf
{
    public const A4_BREIT = 595.28;
    public const A4_HOCH  = 841.89;

    private array $teile = [];

    public function __construct(
        private float $breite = self::A4_BREIT,
        private float $hoehe  = self::A4_HOCH,
    ) {}

    /**
     * Eine Zeile Text. $ausrichtung: links, rechts oder mitte.
     * Gibt die gezeichnete Breite zurueck — praktisch, um direkt anzusetzen.
     */
    public function text(float $x, float $y, string $inhalt, float $groesse = 10,
                         bool $fett = false, string $ausrichtung = 'links', array $farbe = [0, 0, 0]): float
    {
        $inhalt = $this->kodieren($inhalt);
        if ($inhalt === '') { return 0.0; }
        $gezeichnet = $this->textbreite($inhalt, $groesse, $fett);

        if ($ausrichtung !== 'links') {
            $b = $this->textbreite($inhalt, $groesse, $fett);
            $x = $ausrichtung === 'rechts' ? $x - $b : $x - $b / 2;
        }

        $this->teile[] = sprintf(
            "BT /%s %.2F Tf %.3F %.3F %.3F rg %.2F %.2F Td (%s) Tj ET",
            $fett ? 'F2' : 'F1', $groesse,
            $farbe[0], $farbe[1], $farbe[2],
            $x, $this->hoehe - $y, $this->maskieren($inhalt)
        );
        return $gezeichnet;
    }

    /** Mehrere Zeilen mit festem Abstand. Gibt die neue Y-Position zurueck. */
    public function zeilen(float $x, float $y, array $zeilen, float $groesse = 10,
                           bool $fett = false, float $abstand = 1.45, array $farbe = [0, 0, 0]): float
    {
        foreach ($zeilen as $z) {
            $this->text($x, $y, (string) $z, $groesse, $fett, 'links', $farbe);
            $y += $groesse * $abstand;
        }
        return $y;
    }

    public function linie(float $x1, float $y1, float $x2, float $y2,
                          float $dicke = 0.6, array $farbe = [0.8, 0.8, 0.8]): void
    {
        $this->teile[] = sprintf("%.3F %.3F %.3F RG %.2F w %.2F %.2F m %.2F %.2F l S",
            $farbe[0], $farbe[1], $farbe[2], $dicke,
            $x1, $this->hoehe - $y1, $x2, $this->hoehe - $y2);
    }

    public function flaeche(float $x, float $y, float $breite, float $hoehe, array $farbe = [0.96, 0.96, 0.97]): void
    {
        $this->teile[] = sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f",
            $farbe[0], $farbe[1], $farbe[2], $x, $this->hoehe - $y - $hoehe, $breite, $hoehe);
    }

    /**
     * Bricht Text auf eine Breite um und gibt die Zeilen zurueck. Rechnet
     * mit denselben Breiten wie die Ausgabe, damit nichts ueberlaeuft.
     */
    public function umbrechen(string $text, float $breite, float $groesse = 10, bool $fett = false): array
    {
        $aus = [];
        foreach (preg_split('~\R~', $text) ?: [] as $absatz) {
            $zeile = '';
            foreach (preg_split('~\s+~', trim($absatz)) ?: [] as $wort) {
                if ($wort === '') { continue; }
                $versuch = $zeile === '' ? $wort : "$zeile $wort";
                if ($this->textbreite($this->kodieren($versuch), $groesse, $fett) > $breite && $zeile !== '') {
                    $aus[] = $zeile;
                    $zeile = $wort;
                } else {
                    $zeile = $versuch;
                }
            }
            $aus[] = $zeile;
        }
        return $aus;
    }

    /** Fertiges PDF als Zeichenkette. */
    public function fertig(): string
    {
        $inhalt = implode("\n", $this->teile);

        $objekte = [
            "<< /Type /Catalog /Pages 2 0 R >>",
            "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            sprintf("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] "
                . "/Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> /Contents 4 0 R >>",
                $this->breite, $this->hoehe),
            sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($inhalt) + 1, $inhalt),
            "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
            "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>",
        ];

        $pdf = "%PDF-1.4\n";
        $stellen = [];
        foreach ($objekte as $i => $o) {
            $stellen[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n$o\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objekte) + 1) . "\n0000000000 65535 f \n";
        foreach ($stellen as $s) { $pdf .= sprintf("%010d 00000 n \n", $s); }
        $pdf .= "trailer\n<< /Size " . (count($objekte) + 1) . " /Root 1 0 R >>\n"
              . "startxref\n$xref\n%%EOF\n";
        return $pdf;
    }

    /* ---------- Innenleben ---------- */

    /** UTF-8 nach WinAnsi — das koennen die Standardschriften. */
    private function kodieren(string $s): string
    {
        $um = @iconv('UTF-8', 'CP1252//TRANSLIT', $s);
        if ($um === false) {
            $um = @iconv('UTF-8', 'CP1252//IGNORE', $s);
        }
        return (string) ($um === false ? preg_replace('~[^\x20-\x7e]~', '', $s) : $um);
    }

    private function maskieren(string $s): string
    {
        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $s);
    }

    /**
     * Breite in Punkt, nach den echten Zeichenbreiten von Helvetica.
     *
     * Geschaetzte Breiten waren hier ein Fehler: Das Wort VECOM kam sieben
     * Punkt zu schmal heraus, und die Wortmarke klebte zusammen. Bei
     * rechtsbuendigen Betraegen faellt so etwas noch mehr auf, deshalb
     * stehen hier die Werte aus der Schrift selbst.
     */
    private function textbreite(string $winAnsi, float $groesse, bool $fett): float
    {
        static $tabellen = null;
        if ($tabellen === null) {
            // Breiten fuer Zeichen 32 bis 126, in Tausendstel der Schriftgroesse.
            $normal = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,
                556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,
                1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,
                667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,
                333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,
                556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
            $fettwerte = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,
                556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,
                975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,
                667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,
                333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,
                611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];
            $bauen = static function (array $werte): array {
                $t = [];
                foreach ($werte as $i => $w) { $t[32 + $i] = $w; }
                // Zeichen ueber 126: Umlaute und Akzente sind so breit wie der
                // Grundbuchstabe, alles Uebrige bekommt die Breite von "o".
                $grund = [196=>65, 214=>79, 220=>85, 228=>97, 246=>111, 252=>117, 223=>115,
                          192=>65, 193=>65, 194=>65, 200=>69, 201=>69, 202=>69, 204=>73, 205=>73,
                          210=>79, 211=>79, 217=>85, 218=>85, 224=>97, 225=>97, 226=>97,
                          232=>101, 233=>101, 234=>101, 236=>105, 237=>105, 242=>111, 243=>111,
                          249=>117, 250=>117, 231=>99, 199=>67, 241=>110, 209=>78];
                for ($c = 127; $c <= 255; $c++) {
                    $t[$c] = $t[$grund[$c] ?? 111] ?? 556;
                }
                $t[128] = $t[69];    // Euro-Zeichen, ungefaehr wie ein E
                $t[150] = 556;       // Gedankenstrich
                $t[151] = 1000;      // langer Gedankenstrich
                $t[145] = $t[146] = $t[39];
                $t[147] = $t[148] = $t[34];
                return $t;
            };
            $tabellen = ['normal' => $bauen($normal), 'fett' => $bauen($fettwerte)];
        }

        $tabelle = $tabellen[$fett ? 'fett' : 'normal'];
        $summe = 0;
        foreach (str_split($winAnsi) as $zeichen) {
            $summe += $tabelle[ord($zeichen)] ?? 556;
        }
        return $summe / 1000 * $groesse;
    }
}
