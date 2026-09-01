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

    /** Eingebettete Bilder: je Eintrag [daten, breite, hoehe, farbraum]. */
    private array $bilder = [];

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

    /**
     * Ein JPEG an eine feste Stelle setzen, Groesse in Punkt.
     *
     * Warum ausgerechnet JPEG: Ein PDF kann einen JPEG-Datenstrom
     * unveraendert aufnehmen (Filter DCTDecode) — das Bild wird also nicht
     * umgerechnet, sondern durchgereicht. Fuer PNG mit Transparenz muesste
     * die Klasse Zlib-Stroeme und eine Maske bauen; das braucht ein Briefkopf
     * nicht, der ohnehin auf weissem Papier steht.
     *
     * Gibt zurueck, ob das Bild angenommen wurde. Ein kaputtes oder fehlendes
     * Logo darf keinen Beleg verhindern — dann steht eben nur der Schriftzug.
     */
    public function bild(string $jpeg, float $x, float $y, float $breite, float $hoehe): bool
    {
        $kopf = self::jpegKopf($jpeg);
        if ($kopf === null) { return false; }

        $this->bilder[] = [$jpeg, $kopf['breite'], $kopf['hoehe'], $kopf['farbraum']];
        $name = 'Im' . count($this->bilder);

        // q/Q klammert die Verschiebung ein, damit sie nichts danach betrifft.
        // Die Matrix skaliert das Einheitsquadrat auf die gewuenschte Groesse.
        $this->teile[] = sprintf(
            "q\n%.2F 0 0 %.2F %.2F %.2F cm\n/%s Do\nQ",
            $breite, $hoehe, $x, $this->hoehe - $y - $hoehe, $name
        );
        return true;
    }

    /**
     * Breite, Hoehe und Farbraum aus dem JPEG-Kopf lesen.
     *
     * Gesucht wird der SOF-Abschnitt. Er kann hinter beliebig vielen anderen
     * Abschnitten stehen, deshalb wird die Kette der Marker abgelaufen statt
     * an einer festen Stelle nachzusehen.
     *
     * @return array{breite:int,hoehe:int,farbraum:string}|null
     */
    private static function jpegKopf(string $d): ?array
    {
        $n = strlen($d);
        if ($n < 4 || substr($d, 0, 2) !== "\xFF\xD8") { return null; }

        $i = 2;
        while ($i + 3 < $n) {
            if ($d[$i] !== "\xFF") { return null; }
            $marker = ord($d[$i + 1]);
            // Fuellbytes und Marker ohne Nutzlast ueberspringen.
            if ($marker === 0xFF) { $i++; continue; }
            if ($marker === 0xD8 || ($marker >= 0xD0 && $marker <= 0xD9)) { $i += 2; continue; }

            $laenge = (ord($d[$i + 2]) << 8) + ord($d[$i + 3]);
            if ($laenge < 2) { return null; }

            // SOF0/1/2/9/10 tragen die Masse. DHT, DAC und SOS nicht.
            $istSof = in_array($marker, [0xC0, 0xC1, 0xC2, 0xC3, 0xC5, 0xC6, 0xC7,
                                         0xC9, 0xCA, 0xCB, 0xCD, 0xCE, 0xCF], true);
            if ($istSof) {
                if ($i + 9 >= $n) { return null; }
                $hoehe  = (ord($d[$i + 5]) << 8) + ord($d[$i + 6]);
                $breite = (ord($d[$i + 7]) << 8) + ord($d[$i + 8]);
                $kanaele = ord($d[$i + 9]);
                $farbraum = match ($kanaele) {
                    1 => 'DeviceGray',
                    4 => 'DeviceCMYK',
                    default => 'DeviceRGB',
                };
                if ($breite < 1 || $hoehe < 1) { return null; }
                return ['breite' => $breite, 'hoehe' => $hoehe, 'farbraum' => $farbraum];
            }
            if ($marker === 0xDA) { return null; }   // Bilddaten beginnen, kein SOF gefunden
            $i += 2 + $laenge;
        }
        return null;
    }

    /** Fertiges PDF als Zeichenkette. */
    public function fertig(): string
    {
        $inhalt = implode("\n", $this->teile);

        // Die Bilder bekommen die Nummern nach den beiden Schriften.
        $xobjekte = '';
        foreach ($this->bilder as $nr => $_) {
            $xobjekte .= sprintf('/Im%d %d 0 R ', $nr + 1, 7 + $nr);
        }
        $mittel = $xobjekte !== ''
            ? sprintf('/Font << /F1 5 0 R /F2 6 0 R >> /XObject << %s>>', $xobjekte)
            : '/Font << /F1 5 0 R /F2 6 0 R >>';

        $objekte = [
            "<< /Type /Catalog /Pages 2 0 R >>",
            "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            sprintf("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] "
                . "/Resources << %s >> /Contents 4 0 R >>",
                $this->breite, $this->hoehe, $mittel),
            sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($inhalt) + 1, $inhalt),
            "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
            "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>",
        ];

        foreach ($this->bilder as [$daten, $bb, $bh, $farbraum]) {
            $objekte[] = sprintf(
                "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /%s "
                . "/BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $bb, $bh, $farbraum, strlen($daten) + 1, $daten
            );
        }

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
