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

    /** Fertige Seiten davor (30.09.2026, Akte für den Anwalt); die laufende steht in $teile. */
    private array $seiten = [];

    /** Dokumenteigenschaften (/Info), z. B. Titel und eine unsichtbare Kennung. */
    private array $info = [];

    /** Eingebettete TrueType-Schriften (07.10.2026, Dokumentstil „Vecom Gold“): name => [kurz, ttf, maße]. */
    private array $schriften = [];

    /** PNG-Bilder: je Eintrag [farbdaten(zlib), alpha(zlib)|null, breite, hoehe, farbraum]. */
    private array $pngs = [];

    /** Ordner der PDF-Schriften (TTF + Maße als JSON, erzeugt aus den Web-Schriften des Repos). */
    public const SCHRIFT_ORDNER = __DIR__ . '/../schrift/pdf';

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

    /* ------------------------------------------------------------------ */
    /*  Eingebettete Schriften (07.10.2026)                                */
    /* ------------------------------------------------------------------ */

    /** Ist die Schrift vorhanden? (TTF + JSON im Schriftordner) */
    public static function schriftDa(string $name): bool
    {
        return is_file(self::SCHRIFT_ORDNER . '/' . $name . '.ttf') && is_file(self::SCHRIFT_ORDNER . '/' . $name . '.json');
    }

    /** Lädt die Schrift einmal und gibt ihr Kürzel (/T1, /T2 …) zurück. */
    private function schrift(string $name): array
    {
        if (!isset($this->schriften[$name])) {
            if (!self::schriftDa($name)) { throw new RuntimeException('PDF-Schrift fehlt: ' . $name); }
            $m = json_decode((string) file_get_contents(self::SCHRIFT_ORDNER . '/' . $name . '.json'), true);
            $this->schriften[$name] = ['T' . (count($this->schriften) + 1), (string) file_get_contents(self::SCHRIFT_ORDNER . '/' . $name . '.ttf'), $m];
        }
        return $this->schriften[$name];
    }

    /** Breite in Punkt in einer eingebetteten Schrift; $sperrung = Zeichenabstand in Punkt. */
    public function breite(string $inhalt, float $groesse, string $schrift, float $sperrung = 0): float
    {
        $w = $this->kodieren($inhalt);
        $m = $this->schrift($schrift)[2];
        $summe = 0;
        foreach (str_split($w) as $z) { $o = ord($z); $summe += ($o >= 32 ? ($m['breiten'][$o - 32] ?? 500) : 0); }
        return $summe / 1000 * $groesse + max(0, strlen($w) - 1) * $sperrung;
    }

    /**
     * Text in einer eingebetteten Schrift (inter-400, inter-500, inter-600, cormorant-500 …).
     * $sperrung: zusätzlicher Zeichenabstand in Punkt (für gesperrte Großbuchstaben).
     */
    public function schreiben(float $x, float $y, string $inhalt, float $groesse, string $schrift,
                              array $farbe = [0, 0, 0], string $ausrichtung = 'links', float $sperrung = 0): float
    {
        $w = $this->kodieren($inhalt);
        if ($w === '') { return 0.0; }
        [$kurz] = $this->schrift($schrift);
        $b = $this->breite($inhalt, $groesse, $schrift, $sperrung);
        if ($ausrichtung !== 'links') { $x = $ausrichtung === 'rechts' ? $x - $b : $x - $b / 2; }
        $this->teile[] = sprintf("BT /%s %.2F Tf %.2F Tc %.3F %.3F %.3F rg %.2F %.2F Td (%s) Tj ET",
            $kurz, $groesse, $sperrung, $farbe[0], $farbe[1], $farbe[2], $x, $this->hoehe - $y, $this->maskieren($w));
        return $b;
    }

    /** Umbrechen in einer eingebetteten Schrift. */
    public function umbrechenIn(string $text, float $breite, float $groesse, string $schrift): array
    {
        $aus = [];
        foreach (preg_split('~\R~', $text) ?: [] as $absatz) {
            $zeile = '';
            foreach (preg_split('~\s+~', trim($absatz)) ?: [] as $wort) {
                if ($wort === '') { continue; }
                $versuch = $zeile === '' ? $wort : "$zeile $wort";
                if ($zeile !== '' && $this->breite($versuch, $groesse, $schrift) > $breite) { $aus[] = $zeile; $zeile = $wort; }
                else { $zeile = $versuch; }
            }
            $aus[] = $zeile;
        }
        return $aus;
    }

    /* ------------------------------------------------------------------ */
    /*  PNG mit Transparenz (07.10.2026)                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Ein PNG (8 Bit: Grau, RGB, Palette, mit oder ohne Alpha) an eine feste Stelle.
     * Die Pixel werden entpackt, entfiltert und als Farbe + Maske (SMask) eingebettet.
     * Gibt false zurück, wenn das Bild nicht lesbar ist — dann fehlt eben das Bild.
     */
    public function bildPng(string $png, float $x, float $y, float $breite, float $hoehe): bool
    {
        $d = self::pngLesen($png);
        if ($d === null) { return false; }
        $this->pngs[] = $d;
        $this->teile[] = sprintf("q\n%.2F 0 0 %.2F %.2F %.2F cm\n/Pn%d Do\nQ", $breite, $hoehe, $x, $this->hoehe - $y - $hoehe, count($this->pngs));
        return true;
    }

    /** @return array{0:string,1:?string,2:int,3:int,4:string}|null */
    public static function pngLesen(string $png): ?array
    {
        if (substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") { return null; }
        $i = 8; $n = strlen($png); $idat = ''; $plte = ''; $trns = ''; $w = $h = $tiefe = $typ = 0;
        while ($i + 8 <= $n) {
            $len = unpack('N', substr($png, $i, 4))[1]; $art = substr($png, $i + 4, 4); $inh = substr($png, $i + 8, $len);
            if ($art === 'IHDR') { $k = unpack('Nw/Nh/Ct/Cf', $inh); $w = $k['w']; $h = $k['h']; $tiefe = $k['t']; $typ = $k['f']; if (ord($inh[12]) !== 0) { return null; } }
            elseif ($art === 'PLTE') { $plte = $inh; }
            elseif ($art === 'tRNS') { $trns = $inh; }
            elseif ($art === 'IDAT') { $idat .= $inh; }
            elseif ($art === 'IEND') { break; }
            $i += 12 + $len;
        }
        if ($tiefe !== 8 || $w < 1 || $h < 1 || $w * $h > 4_000_000) { return null; }
        $kan = [0 => 1, 2 => 3, 3 => 1, 4 => 2, 6 => 4][$typ] ?? 0;
        if ($kan === 0) { return null; }
        $roh = @gzuncompress($idat);
        if ($roh === false) { return null; }
        $zeile = $w * $kan;
        if (strlen($roh) < ($zeile + 1) * $h) { return null; }
        $farbe = ''; $alpha = ''; $vor = str_repeat("\0", $zeile); $mitAlpha = in_array($typ, [4, 6], true) || ($typ === 3 && $trns !== '');
        for ($r = 0; $r < $h; $r++) {
            $f = ord($roh[$r * ($zeile + 1)]); $z = substr($roh, $r * ($zeile + 1) + 1, $zeile); $neu = $z;
            if ($f !== 0) {
                $neu = '';
                for ($c = 0; $c < $zeile; $c++) {
                    $a = $c >= $kan ? ord($neu[$c - $kan]) : 0; $b = ord($vor[$c]); $cc = $c >= $kan ? ord($vor[$c - $kan]) : 0; $x0 = ord($z[$c]);
                    $v = match ($f) { 1 => $x0 + $a, 2 => $x0 + $b, 3 => $x0 + intdiv($a + $b, 2), 4 => $x0 + self::paeth($a, $b, $cc), default => $x0 };
                    $neu .= chr($v & 0xFF);
                }
            }
            $vor = $neu;
            if ($typ === 2 || $typ === 0) { $farbe .= $neu; }
            elseif ($typ === 6) { for ($c = 0; $c < $zeile; $c += 4) { $farbe .= substr($neu, $c, 3); $alpha .= $neu[$c + 3]; } }
            elseif ($typ === 4) { for ($c = 0; $c < $zeile; $c += 2) { $farbe .= $neu[$c]; $alpha .= $neu[$c + 1]; } }
            else { for ($c = 0; $c < $zeile; $c++) { $ix = ord($neu[$c]); $farbe .= substr($plte, $ix * 3, 3) ?: "\0\0\0"; if ($mitAlpha) { $alpha .= $ix < strlen($trns) ? $trns[$ix] : "\xFF"; } } }
        }
        $raum = in_array($typ, [0, 4], true) ? 'DeviceGray' : 'DeviceRGB';
        return [gzcompress($farbe, 9), $mitAlpha ? gzcompress($alpha, 9) : null, $w, $h, $raum];
    }

    private static function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c; $pa = abs($p - $a); $pb = abs($p - $b); $pc = abs($p - $c);
        return ($pa <= $pb && $pa <= $pc) ? $a : ($pb <= $pc ? $b : $c);
    }

    /** Beginnt eine neue Seite. Alles Weitere landet auf ihr. */
    public function neueSeite(): void
    {
        $this->seiten[] = $this->teile;
        $this->teile = [];
    }

    public function seitenzahl(): int { return count($this->seiten) + 1; }

    /** Nachträglich auf eine fertige Seite zeichnen (1-basiert) — z. B. „Seite 1/3“, wenn die Gesamtzahl feststeht. */
    public function anSeite(int $nr, callable $zeichnen): void
    {
        $gesamt = $this->seitenzahl();
        if ($nr < 1 || $nr > $gesamt) { return; }
        if ($nr === $gesamt) { $zeichnen($this); return; }
        $jetzt = $this->teile;
        $this->teile = $this->seiten[$nr - 1];
        $zeichnen($this);
        $this->seiten[$nr - 1] = $this->teile;
        $this->teile = $jetzt;
    }

    public function seitenHoehe(): float { return $this->hoehe; }
    public function seitenBreite(): float { return $this->breite; }

    /** Dokumenteigenschaften: Title, Author, Subject, Keywords. */
    public function info(array $werte): void
    {
        foreach ($werte as $k => $v) {
            if (in_array($k, ['Title', 'Author', 'Subject', 'Keywords', 'Creator'], true)) { $this->info[$k] = (string) $v; }
        }
    }

    /** Fertiges PDF als Zeichenkette. */
    public function fertig(): string
    {
        if ($this->schriften !== [] || $this->pngs !== []) { return $this->fertigAllgemein(); }
        if ($this->seiten !== [] || $this->info !== []) { return $this->fertigMehrseitig(); }
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

    /**
     * Mehrere Seiten und/oder Dokumenteigenschaften. Eigener Weg, damit die
     * einseitigen Belege Byte für Byte bleiben, wie sie sind.
     * Nummern: 1 Katalog, 2 Seitenbaum, 3/4 Schriften, dann Bilder, dann je
     * Seite Seite + Inhalt, zuletzt /Info.
     */
    private function fertigMehrseitig(): string
    {
        $alle = array_merge($this->seiten, [$this->teile]);
        $nBild = count($this->bilder);
        $xobjekte = '';
        foreach ($this->bilder as $nr => $_) { $xobjekte .= sprintf('/Im%d %d 0 R ', $nr + 1, 5 + $nr); }
        $mittel = '/Font << /F1 3 0 R /F2 4 0 R >>' . ($xobjekte !== '' ? ' /XObject << ' . $xobjekte . '>>' : '');
        $ersteSeite = 5 + $nBild;
        $kids = [];
        foreach ($alle as $i => $_) { $kids[] = ($ersteSeite + 2 * $i) . ' 0 R'; }

        $objekte = [
            "<< /Type /Catalog /Pages 2 0 R >>",
            sprintf("<< /Type /Pages /Kids [%s] /Count %d >>", implode(' ', $kids), count($alle)),
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
        foreach ($alle as $i => $teile) {
            $inhalt = implode("\n", $teile);
            $objekte[] = sprintf("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << %s >> /Contents %d 0 R >>",
                $this->breite, $this->hoehe, $mittel, $ersteSeite + 2 * $i + 1);
            $objekte[] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($inhalt) + 1, $inhalt);
        }
        $infoNr = 0;
        if ($this->info !== []) {
            $felder = '';
            foreach ($this->info as $k => $v) { $felder .= '/' . $k . ' (' . $this->maskieren($this->kodieren($v)) . ') '; }
            $objekte[] = '<< ' . $felder . '>>';
            $infoNr = count($objekte);
        }

        $pdf = "%PDF-1.4\n";
        $stellen = [];
        foreach ($objekte as $i => $o) {
            $stellen[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n$o\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objekte) + 1) . "\n0000000000 65535 f \n";
        foreach ($stellen as $st) { $pdf .= sprintf("%010d 00000 n \n", $st); }
        $pdf .= "trailer\n<< /Size " . (count($objekte) + 1) . " /Root 1 0 R" . ($infoNr ? " /Info $infoNr 0 R" : '') . " >>\n"
              . "startxref\n$xref\n%%EOF\n";
        return $pdf;
    }

    /**
     * Der allgemeine Weg (07.10.2026): eingebettete Schriften, JPEG und PNG,
     * mehrere Seiten, /Info. Die Nummern werden fortlaufend vergeben.
     */
    private function fertigAllgemein(): string
    {
        $objekte = [];
        $neu = static function (string $o) use (&$objekte): int { $objekte[] = $o; return count($objekte); };
        $neu(''); $neu('');   // 1 Katalog, 2 Seitenbaum — unten gefüllt
        $f1 = $neu("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>");
        $f2 = $neu("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>");
        $fonts = '/F1 ' . $f1 . ' 0 R /F2 ' . $f2 . ' 0 R';
        foreach ($this->schriften as $name => [$kurz, $ttf, $m]) {
            $basis = 'VECOM' . chr(65 + (int) substr($kurz, 1) % 26) . '+' . preg_replace('~[^A-Za-z0-9]~', '', (string) $m['name']);
            $z = gzcompress($ttf, 9);
            $datei = $neu(sprintf("<< /Length %d /Length1 %d /Filter /FlateDecode >>\nstream\n%s\nendstream", strlen($z) + 1, strlen($ttf), $z));
            $desk = $neu(sprintf("<< /Type /FontDescriptor /FontName /%s /Flags %d /FontBBox [%s] /ItalicAngle 0 /Ascent %d /Descent %d /CapHeight %d /StemV %d /FontFile2 %d 0 R >>",
                $basis, (int) ($m['flags'] ?? 32), implode(' ', array_map('intval', (array) $m['bbox'])), (int) $m['ascent'], (int) $m['descent'], (int) $m['capHeight'], (int) ($m['stemV'] ?? 80), $datei));
            $fid = $neu(sprintf("<< /Type /Font /Subtype /TrueType /BaseFont /%s /FirstChar 32 /LastChar 255 /Widths [%s] /Encoding /WinAnsiEncoding /FontDescriptor %d 0 R >>",
                $basis, implode(' ', array_map('intval', (array) $m['breiten'])), $desk));
            $fonts .= ' /' . $kurz . ' ' . $fid . ' 0 R';
        }
        $xo = '';
        foreach ($this->bilder as $nr => [$daten, $bb, $bh, $farbraum]) {
            $id = $neu(sprintf("<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /%s /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream",
                $bb, $bh, $farbraum, strlen($daten) + 1, $daten));
            $xo .= sprintf('/Im%d %d 0 R ', $nr + 1, $id);
        }
        foreach ($this->pngs as $nr => [$farbe, $alpha, $bb, $bh, $raum]) {
            $maske = '';
            if ($alpha !== null) {
                $mid = $neu(sprintf("<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length %d >>\nstream\n%s\nendstream",
                    $bb, $bh, strlen($alpha) + 1, $alpha));
                $maske = ' /SMask ' . $mid . ' 0 R';
            }
            $id = $neu(sprintf("<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /%s /BitsPerComponent 8 /Filter /FlateDecode%s /Length %d >>\nstream\n%s\nendstream",
                $bb, $bh, $raum, $maske, strlen($farbe) + 1, $farbe));
            $xo .= sprintf('/Pn%d %d 0 R ', $nr + 1, $id);
        }
        $mittel = '/Font << ' . $fonts . ' >>' . ($xo !== '' ? ' /XObject << ' . $xo . '>>' : '');
        $kids = [];
        foreach (array_merge($this->seiten, [$this->teile]) as $teile) {
            $inhalt = gzcompress(implode("\n", $teile), 6);
            $cid = $neu(sprintf("<< /Length %d /Filter /FlateDecode >>\nstream\n%s\nendstream", strlen($inhalt) + 1, $inhalt));
            $kids[] = $neu(sprintf("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << %s >> /Contents %d 0 R >>", $this->breite, $this->hoehe, $mittel, $cid)) . ' 0 R';
        }
        $objekte[0] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objekte[1] = sprintf("<< /Type /Pages /Kids [%s] /Count %d >>", implode(' ', $kids), count($kids));
        $infoNr = 0;
        if ($this->info !== []) {
            $felder = '';
            foreach ($this->info as $k => $v) { $felder .= '/' . $k . ' (' . $this->maskieren($this->kodieren($v)) . ') '; }
            $infoNr = $neu('<< ' . $felder . '/Producer (Vecom Verwaltung) >>');
        }
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $stellen = [];
        foreach ($objekte as $i => $o) { $stellen[] = strlen($pdf); $pdf .= ($i + 1) . " 0 obj\n$o\nendobj\n"; }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objekte) + 1) . "\n0000000000 65535 f \n";
        foreach ($stellen as $st) { $pdf .= sprintf("%010d 00000 n \n", $st); }
        $pdf .= "trailer\n<< /Size " . (count($objekte) + 1) . " /Root 1 0 R" . ($infoNr ? " /Info $infoNr 0 R" : '') . " >>\nstartxref\n$xref\n%%EOF\n";
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
