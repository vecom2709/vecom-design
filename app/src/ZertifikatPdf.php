<?php
declare(strict_types=1);

/* ==========================================================================
   ZertifikatPdf — das Zertifikat der Partner Academy als hochwertiges PDF
   (05.10.2026, Uwe: „professioneller, schöner, mit Vecom-Logo, der Name viel
   eleganter“).

   Eigener kleiner PDF-Schreiber statt Pdf.php: Das Zertifikat braucht die
   Hausschriften (Marcellus für Versalien, Cormorant Garamond für Text und
   den Namen in Kursiv) als eingebettete TrueType-Schriften, das Vecom-V als
   Vektorpfad mit dem goldenen Verlauf der Marke und ein Siegel mit Text im
   Kreis. Pdf.php bleibt für Belege unverändert (Byte für Byte).

   Quellen in app/data/academy/zertifikat/: *.ttf (aus assets/fonts, SIL OFL),
   *.json (Zeichenbreiten für WinAnsi, Kennwerte), logo.json (Umriss des V aus
   assets/img/vecom-v.svg, Verlaufsstopps).
   ========================================================================== */
final class ZertifikatPdf
{
    public const B = 841.89;   // A4 quer
    public const H = 595.28;
    private const NAVY = [0.055, 0.106, 0.239];     // #0E1B3D
    private const GOLD = [0.66, 0.50, 0.20];        // dunkles Gold für Schrift
    private const GOLD_HELL = [0.84, 0.69, 0.38];
    private const PAPIER = [0.988, 0.976, 0.949];   // Elfenbein
    private const GRAU = [0.36, 0.38, 0.45];
    private const WASSER = [0.968, 0.945, 0.893];   // Wasserzeichen

    /** Schriften: Kürzel → [Datei, PDF-Name, kursiv] */
    private const SCHRIFTEN = [
        'M'  => ['marcellus', 'Marcellus-Regular', false],
        'C'  => ['cormorant', 'CormorantGaramond-Medium', false],
        'CK' => ['cormorant-kursiv', 'CormorantGaramond-MediumItalic', true],
    ];

    private string $s = '';
    private array $meta = [];
    private array $logo = [];

    /**
     * @param array $t Texte in der Sprache des Zertifikats:
     *   titel, fuer, satz, ergebnis, datum, nummer, pruefen, intern, siegel
     */
    public static function erzeugen(array $z, array $t, string $pruefUrl): string
    {
        $o = new self();
        return $o->bauen($z, $t, $pruefUrl);
    }

    private function __construct()
    {
        $d = dirname(__DIR__) . '/data/academy/zertifikat';
        foreach (self::SCHRIFTEN as $k => [$datei]) {
            $this->meta[$k] = json_decode((string) file_get_contents("$d/$datei.json"), true) + ['datei' => "$d/$datei.ttf"];
        }
        $this->logo = json_decode((string) file_get_contents("$d/logo.json"), true);
    }

    /* ---------------------------------------------------------------- Zeichnen */

    private function y(float $y): float { return self::H - $y; }

    private function farbe(array $c, bool $fuellen = true): string
    {
        return sprintf('%.3F %.3F %.3F %s ', $c[0], $c[1], $c[2], $fuellen ? 'rg' : 'RG');
    }

    private function kodieren(string $t): string
    {
        $t = strtr($t, ['Š' => 'S', 'š' => 's', 'Ž' => 'Z', 'ž' => 'z', 'Ÿ' => 'Y', 'ƒ' => 'f']);
        $u = @iconv('UTF-8', 'CP1252//TRANSLIT', $t);
        return $u === false ? (string) preg_replace('~[^\x20-\x7e]~', '', $t) : $u;
    }

    /** Breite in Punkt, mit Zeichenabstand. */
    private function breite(string $win, string $f, float $g, float $abstand = 0): float
    {
        $w = 0;
        $tab = $this->meta[$f]['widths'];
        for ($i = 0, $n = strlen($win); $i < $n; $i++) { $c = ord($win[$i]); $w += $c >= 32 ? ($tab[$c - 32] ?? 500) : 0; }
        return $w * $g / 1000 + $abstand * max(0, strlen($win) - 1);
    }

    /** Text; Marcellus kann nur ASCII — sonst Cormorant. $aus: links|mitte|rechts. Gibt die Breite zurück. */
    private function text(float $x, float $y, string $t, string $f, float $g, array $c, string $aus = 'mitte', float $abstand = 0): float
    {
        if ($f === 'M' && preg_match('~[^\x20-\x7e]~', $t)) { $f = 'C'; }
        $win = $this->kodieren($t);
        $b = $this->breite($win, $f, $g, $abstand);
        $x = $aus === 'mitte' ? $x - $b / 2 : ($aus === 'rechts' ? $x - $b : $x);
        $this->s .= 'BT ' . $this->farbe($c) . sprintf('/%s %.2F Tf %.2F Tc %.2F %.2F Td (%s) Tj ET', $f, $g, $abstand, $x, $this->y($y),
            str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $win)) . "\n";
        return $b;
    }

    /** Text auf einem Kreis (Siegel), oben beginnend, im Uhrzeigersinn. */
    private function kreistext(float $cx, float $cy, float $r, string $t, string $f, float $g, array $c, float $abstand, string $mitteVon = ''): void
    {
        $win = $this->kodieren($t);
        $tab = $this->meta[$f]['widths'];
        // Oben mittig steht $mitte (z. B. „PARTNER ACADEMY“), der Rest läuft rundherum.
        $pos = $mitteVon !== '' ? strpos($win, $this->kodieren($mitteVon)) : false;
        $vor = $pos === false ? $this->breite($win, $f, $g, $abstand) / 2
            : $this->breite(substr($win, 0, $pos), $f, $g, $abstand) + $abstand + $this->breite($this->kodieren($mitteVon), $f, $g, $abstand) / 2;
        $winkel = M_PI / 2 + $vor / $r;
        $this->s .= 'BT ' . $this->farbe($c) . sprintf('/%s %.2F Tf ', $f, $g);
        for ($i = 0, $n = strlen($win); $i < $n; $i++) {
            $w = ($tab[ord($win[$i]) - 32] ?? 500) * $g / 1000;
            $mitte = $winkel - ($w / 2) / $r;
            $a = $mitte - M_PI / 2;   // Grundlinie tangential
            $px = $cx + $r * cos($mitte) - cos($a) * $w / 2;
            $py = $this->y($cy) + $r * sin($mitte) - sin($a) * $w / 2;
            $this->s .= sprintf('%.4F %.4F %.4F %.4F %.2F %.2F Tm (%s) Tj ', cos($a), sin($a), -sin($a), cos($a), $px, $py,
                str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $win[$i]));
            $winkel -= ($w + $abstand) / $r;
        }
        $this->s .= "ET\n";
    }

    private function linie(float $x1, float $y1, float $x2, float $y2, float $d, array $c): void
    {
        $this->s .= $this->farbe($c, false) . sprintf('%.2F w %.2F %.2F m %.2F %.2F l S', $d, $x1, $this->y($y1), $x2, $this->y($y2)) . "\n";
    }

    private function rechteck(float $x, float $y, float $b, float $h, array $c, ?float $strich = null): void
    {
        $this->s .= ($strich === null ? $this->farbe($c) : $this->farbe($c, false) . sprintf('%.2F w ', $strich))
            . sprintf('%.2F %.2F %.2F %.2F re %s', $x, $this->y($y + $h), $b, $h, $strich === null ? 'f' : 'S') . "\n";
    }

    private function raute(float $x, float $y, float $r, array $c): void
    {
        $Y = $this->y($y);
        $this->s .= $this->farbe($c) . sprintf('%.2F %.2F m %.2F %.2F l %.2F %.2F l %.2F %.2F l h f', $x, $Y + $r, $x + $r, $Y, $x, $Y - $r, $x - $r, $Y) . "\n";
    }

    private function kreisPfad(float $cx, float $cy, float $r): string
    {
        $k = 0.5523 * $r; $Y = $this->y($cy);
        return sprintf('%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c %.2F %.2F %.2F %.2F %.2F %.2F c h ',
            $cx + $r, $Y, $cx + $r, $Y + $k, $cx + $k, $Y + $r, $cx, $Y + $r, $cx - $k, $Y + $r, $cx - $r, $Y + $k, $cx - $r, $Y,
            $cx - $r, $Y - $k, $cx - $k, $Y - $r, $cx, $Y - $r, $cx + $k, $Y - $r, $cx + $r, $Y - $k, $cx + $r, $Y);
    }

    /** Das Vecom-V in ein Rechteck (oben links x,y, Höhe h); Füllung: Verlauf oder Farbe. */
    private function logo(float $x, float $y, float $h, ?array $farbe = null): float
    {
        [$bx, $by, $bw, $bh] = $this->logo['box'];
        $s = $h / $bh; $b = $bw * $s;
        $pfad = '';
        foreach ($this->logo['pfade'] as $p) {
            foreach ($p as $i => [$px, $py]) { $pfad .= sprintf('%.1F %.1F %s ', $px, $py, $i === 0 ? 'm' : 'l'); }
            $pfad .= 'h ';
        }
        // SVG-Koordinaten → Seite: verschieben, skalieren, y spiegeln
        $this->s .= sprintf("q %.4F 0 0 %.4F %.2F %.2F cm\n", $s, -$s, $x - $bx * $s, $this->y($y) + $by * $s);
        $this->s .= $farbe === null
            ? $pfad . "W* n /Sh1 sh Q\n"
            : $this->farbe($farbe) . $pfad . "f* Q\n";
        return $b;
    }

    /** Goldener Verlauf (Achse in SVG-Koordinaten des Logos — passt für Logo und Siegel). */
    private function verlauf(): string
    {
        $st = $this->logo['stops'];
        $rgb = static fn(string $h): string => sprintf('%.3F %.3F %.3F', hexdec(substr($h, 0, 2)) / 255, hexdec(substr($h, 2, 2)) / 255, hexdec(substr($h, 4, 2)) / 255);
        $fn = []; $grenzen = []; $enc = [];
        for ($i = 0; $i < count($st) - 1; $i++) {
            $fn[] = sprintf('<< /FunctionType 2 /Domain [0 1] /C0 [%s] /C1 [%s] /N 1 >>', $rgb($st[$i][1]), $rgb($st[$i + 1][1]));
            if ($i > 0) { $grenzen[] = $st[$i][0]; }
            $enc[] = '0 1';
        }
        [$bx, $by, $bw, $bh] = $this->logo['box'];
        return sprintf('<< /ShadingType 2 /ColorSpace /DeviceRGB /Coords [%.1F %.1F %.1F %.1F] /Extend [true true] /Function << /FunctionType 3 /Domain [0 1] /Functions [%s] /Bounds [%s] /Encode [%s] >> >>',
            $bx, $by, $bx + $bw, $by + $bh, implode(' ', $fn), implode(' ', $grenzen), implode(' ', $enc));
    }

    /* ---------------------------------------------------------------- Aufbau */

    private function bauen(array $z, array $t, string $url): string
    {
        $B = self::B; $H = self::H; $m = $B / 2;
        // Papier, Rahmen, Ecken
        $this->rechteck(0, 0, $B, $H, self::PAPIER);
        $this->rechteck(18, 18, $B - 36, $H - 36, self::NAVY, 9);
        $this->rechteck(31, 31, $B - 62, $H - 62, self::GOLD_HELL, 1.1);
        $this->rechteck(35, 35, $B - 70, $H - 70, self::GOLD_HELL, 0.4);
        foreach ([[35, 35, 1, 1], [$B - 35, 35, -1, 1], [35, $H - 35, 1, -1], [$B - 35, $H - 35, -1, -1]] as [$ex, $ey, $sx, $sy]) {
            $this->linie($ex + $sx * 8, $ey + $sy * 8, $ex + $sx * 38, $ey + $sy * 8, 0.9, self::GOLD);
            $this->linie($ex + $sx * 8, $ey + $sy * 8, $ex + $sx * 8, $ey + $sy * 38, 0.9, self::GOLD);
            $this->raute($ex + $sx * 8, $ey + $sy * 8, 3.2, self::GOLD);
        }
        // Wasserzeichen: großes V, sehr hell
        $wh = 300; $ww = $wh * $this->logo['box'][2] / $this->logo['box'][3];
        $this->logo($m - $ww / 2, 170, $wh, self::WASSER);

        // Kopf: Logo, Wortmarke
        $lh = 50; $lw = $lh * $this->logo['box'][2] / $this->logo['box'][3];
        $this->logo($m - $lw / 2, 50, $lh);
        $this->text($m, 124, 'VECOM DESIGN', 'M', 15, self::NAVY, 'mitte', 4.5);
        $this->text($m, 139, 'PARTNER ACADEMY', 'M', 8, self::GOLD, 'mitte', 3.2);

        // Titel mit Ornament
        $this->text($m, 194, mb_strtoupper($t['titel']), 'M', 34, self::NAVY, 'mitte', 9);
        $this->linie($m - 160, 213, $m - 14, 213, 0.7, self::GOLD);
        $this->linie($m + 14, 213, $m + 160, 213, 0.7, self::GOLD);
        $this->raute($m, 213, 4, self::GOLD);
        $this->raute($m - 164, 213, 1.8, self::GOLD);
        $this->raute($m + 164, 213, 1.8, self::GOLD);

        // Name: Cormorant Kursiv, groß; passt sich der Länge an
        $this->text($m, 245, $t['fuer'], 'CK', 15, self::GRAU);
        $name = trim((string) $z['name']);
        $g = 54;
        while ($g > 26 && $this->breite($this->kodieren($name), 'CK', $g) > 600) { $g -= 2; }
        $this->text($m, 300, $name, 'CK', $g, self::NAVY);
        $this->linie($m - 210, 318, $m + 210, 318, 0.6, self::GOLD_HELL);
        $this->raute($m - 214, 318, 2, self::GOLD);
        $this->raute($m + 214, 318, 2, self::GOLD);

        // Satz und Ergebnis
        $y = 350;
        foreach ($this->umbrechen($t['satz'], 'C', 15, 540) as $zeile) { $this->text($m, $y, $zeile, 'C', 15, self::NAVY); $y += 20; }
        $this->text($m, $y + 8, $t['ergebnis'], 'CK', 13.5, self::GOLD);

        // Fuß: Datum links, Siegel mitte, Prüfnummer rechts
        $yb = 478;
        foreach ([[205, Fmt::datum((string) $z['ausgestellt_am']), $t['datum'], 0], [$B - 205, (string) $z['nummer'], $t['nummer'], 1.4]] as [$cx, $wert, $label, $ab]) {
            $this->text($cx, $yb, $wert, 'C', 16, self::NAVY, 'mitte', $ab);
            $this->linie($cx - 92, $yb + 9, $cx + 92, $yb + 9, 0.5, self::GOLD_HELL);
            $this->text($cx, $yb + 24, $label, 'CK', 11, self::GRAU);
        }
        $this->siegel($m, 470, 47, $t['siegel']);

        // Prüfadresse und Hinweis
        $this->text($m, $H - 60, $t['pruefen'] . '  ' . $url, 'C', 9.5, self::GRAU);
        $this->text($m, $H - 47, $t['intern'], 'CK', 9, self::GRAU);

        return $this->dokument($t['titel'] . ' — ' . $name);
    }

    private function siegel(float $cx, float $cy, float $r, string $text): void
    {
        // Goldene Scheibe (Verlauf), Ringe, Text im Kreis, innen navy mit goldenem V
        [$bx, $by, $bw, $bh] = $this->logo['box'];
        $s = (2 * $r) / $bh;   // Verlauf über die Scheibe legen
        $this->s .= 'q ' . $this->kreisPfad($cx, $cy, $r) . 'W n ' . sprintf("%.4F 0 0 %.4F %.2F %.2F cm /Sh1 sh Q\n", $s, -$s, $cx - $r - $bx * $s, $this->y($cy) + $r + $by * $s);
        $this->s .= $this->farbe(self::NAVY, false) . '0.8 w ' . $this->kreisPfad($cx, $cy, $r - 4) . "S\n";
        $this->s .= $this->farbe(self::NAVY, false) . '0.5 w ' . $this->kreisPfad($cx, $cy, $r - 6.5) . "S\n";
        $this->kreistext($cx, $cy, $r - 15.5, $text, 'M', 6.6, self::NAVY, 1.1, 'PARTNER ACADEMY');
        $this->s .= $this->farbe(self::NAVY) . $this->kreisPfad($cx, $cy, $r - 22) . "f\n";
        $this->s .= $this->farbe(self::GOLD_HELL, false) . '0.5 w ' . $this->kreisPfad($cx, $cy, $r - 24) . "S\n";
        $lh = 22; $lw = $lh * $bw / $bh;
        $this->logo($cx - $lw / 2, $cy - $lh / 2 + 1, $lh);
    }

    /** @return list<string> */
    private function umbrechen(string $text, string $f, float $g, float $max): array
    {
        // Ausgewogen: so viele Zeilen wie nötig, alle etwa gleich lang (keine Witwe „test finale.“)
        $gesamt = $this->breite($this->kodieren($text), $f, $g);
        if ($gesamt > $max) { $max = min($max, $gesamt / ceil($gesamt / $max) + 40); }
        $o = []; $z = '';
        foreach (preg_split('~\s+~u', trim($text)) ?: [] as $w) {
            $v = $z === '' ? $w : "$z $w";
            if ($z !== '' && $this->breite($this->kodieren($v), $f, $g) > $max) { $o[] = $z; $z = $w; } else { $z = $v; }
        }
        if ($z !== '') { $o[] = $z; }
        return $o;
    }

    /* ---------------------------------------------------------------- PDF */

    private function dokument(string $titel): string
    {
        $obj = [];
        $obj[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $obj[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $n = 5;
        $fontRes = '';
        $fontObjs = [];
        foreach (self::SCHRIFTEN as $k => [$datei, $pdfName, $kursiv]) {
            $mt = $this->meta[$k];
            $ttf = (string) file_get_contents($mt['datei']);
            $z = gzcompress($ttf, 9);
            $fd = $n; $ff = $n + 1; $fo = $n + 2; $n += 3;
            $fontObjs[$ff] = sprintf("<< /Length %d /Length1 %d /Filter /FlateDecode >>\nstream\n%s\nendstream", strlen($z), strlen($ttf), $z);
            $fontObjs[$fd] = sprintf('<< /Type /FontDescriptor /FontName /%s /Flags %d /FontBBox [%s] /ItalicAngle %.1F /Ascent %d /Descent %d /CapHeight %d /StemV 80 /FontFile2 %d 0 R >>',
                $pdfName, 32 + ($kursiv ? 64 : 0), implode(' ', $mt['bbox']), (float) $mt['italic'], $mt['ascent'], $mt['descent'], $mt['capheight'], $ff);
            $fontObjs[$fo] = sprintf('<< /Type /Font /Subtype /TrueType /BaseFont /%s /FirstChar 32 /LastChar 255 /Widths [%s] /Encoding /WinAnsiEncoding /FontDescriptor %d 0 R >>',
                $pdfName, implode(' ', $mt['widths']), $fd);
            $fontRes .= "/$k $fo 0 R ";
        }
        $sh = $n++;
        $info = $n++;
        $inhalt = gzcompress($this->s, 9);
        $obj[3] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << %s>> /Shading << /Sh1 %d 0 R >> >> /Contents 4 0 R >>', self::B, self::H, $fontRes, $sh);
        $obj[4] = sprintf("<< /Length %d /Filter /FlateDecode >>\nstream\n%s\nendstream", strlen($inhalt), $inhalt);
        $obj += $fontObjs;
        $obj[$sh] = $this->verlauf();
        $obj[$info] = '<< /Title (' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $this->kodieren($titel)) . ') /Author (Vecom Design) /Creator (Vecom Partner Academy) >>';
        ksort($obj);
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $stellen = [];
        foreach ($obj as $i => $o) { $stellen[$i] = strlen($pdf); $pdf .= "$i 0 obj\n$o\nendobj\n"; }
        $x = strlen($pdf);
        $pdf .= "xref\n0 " . (count($obj) + 1) . "\n0000000000 65535 f \n";
        foreach ($stellen as $st) { $pdf .= sprintf("%010d 00000 n \n", $st); }
        return $pdf . "trailer\n<< /Size " . (count($obj) + 1) . " /Root 1 0 R /Info $info 0 R >>\nstartxref\n$x\n%%EOF\n";
    }
}
