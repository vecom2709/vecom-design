<?php
declare(strict_types=1);

/**
 * Visitenkarten des Partners in vier Stilen (28.09.2026, Uwe: „exakt diese
 * Styles, nur die Visitenkarten ohne den Tisch, mit Partner-QR-Code,
 * Partner-Link und Name“).
 *
 * Die Hintergründe (Gold, Bürstung, Glanzlinien, Logo, feste Texte je
 * Sprache) liegen fertig in app/karten/ -- 91 × 61 mm, also 85 × 55 mm
 * Endformat plus 3 mm Beschnitt, 450 dpi (erzeugt aus vk/gen.py mit dem
 * echten Logo). Hier kommt pro Partner dazu: Name, sein Link, sein Kontakt
 * und der echte QR-Code auf /p/CODE/karte.
 *
 *   bild()      eine Seite als JPEG (Vorschau, WhatsApp, Druckerei)
 *   vorschau()  beide Seiten beschnitten nebeneinander, klein
 *   pdf()       Druckerei: Vorder- und Rückseite mit Beschnitt;
 *               Bogen: 10 Karten auf A4 mit Schnittmarken, Rückseite
 *               gespiegelt für beidseitigen Druck. QR-Code immer als Vektor.
 */
final class PartnerKarten
{
    public const STILE = [
        'a' => ['it' => 'Nero e oro, diagonale', 'de' => 'Schwarz-Gold diagonal', 'en' => 'Black & gold, diagonal'],
        'b' => ['it' => 'Nero e oro, angoli', 'de' => 'Schwarz-Gold Ecken', 'en' => 'Black & gold, corners'],
        'c' => ['it' => 'Nero con bordo oro', 'de' => 'Schwarz mit Goldkante', 'en' => 'Black with gold edge'],
        'd' => ['it' => 'Bianco e oro', 'de' => 'Weiß-Gold', 'en' => 'White & gold'],
    ];
    public const KONTAKTE = ['email', 'vecom'];
    public const VECOM_MAIL = 'kontakt@vecom-design.it';

    /** Leinwand in 1/10 mm (Layout-Einheit) und Beschnitt. */
    private const LW = 910, LH = 610, BESCHNITT = 30;

    private static ?array $layout = null;

    public static function gibt(string $stil): bool
    {
        return isset(self::STILE[$stil]) && is_file(self::datei($stil, 'vorn', 'de'));
    }

    public static function link(array $p): string
    {
        return PartnerWerbung::link($p, 'karte');
    }

    public static function kurz(array $p): string
    {
        return (string) preg_replace('~^https?://~', '', Partner::link($p));
    }

    public static function kontakt(array $p, string $art): string
    {
        $m = trim((string) ($p['email'] ?? ''));
        return $art === 'vecom' || $m === '' ? self::VECOM_MAIL : $m;
    }

    public static function name(array $p): string
    {
        $n = trim((string) preg_replace('~\s+~u', ' ', (string) ($p['name'] ?? '')));
        return $n !== '' ? $n : Partner::anzeigeName($p);
    }

    public static function dateiname(array $p, string $stil, string $was, string $endung): string
    {
        return 'vecom-visitenkarte-' . $stil . '-' . $was . '-' . strtolower((string) preg_replace('~[^A-Za-z0-9]~', '', (string) $p['code'])) . '.' . $endung;
    }

    private static function datei(string $stil, string $seite, string $sprache): string
    {
        $s = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'de';
        return dirname(__DIR__) . '/karten/' . $stil . '-' . ($seite === 'vorn' ? 'vorn' : 'hinten-' . $s) . '.jpg';
    }

    private static function layout(string $stil): array
    {
        self::$layout ??= (array) require dirname(__DIR__) . '/karten/layout.php';
        return self::$layout[$stil];
    }

    private static function schrift(int $gewicht): string
    {
        $g = $gewicht >= 700 ? 700 : ($gewicht >= 600 ? 600 : 500);
        return dirname(__DIR__) . '/schrift/montserrat-' . $g . '.ttf';
    }

    /** @return array{0:int,1:list<list<bool>>} */
    private static function raster(string $inhalt): array
    {
        require_once dirname(__DIR__) . '/lib/qrcode.php';
        $qr = QRCode::getMinimumQRCode($inhalt, QR_ERROR_CORRECT_LEVEL_M);
        $n = $qr->getModuleCount();
        $r = [];
        for ($y = 0; $y < $n; $y++) { for ($x = 0; $x < $n; $x++) { $r[$y][$x] = $qr->isDark($y, $x); } }
        return [$n, $r];
    }

    /** Die drei Zeilen der Rückseite. */
    private static function zeilen(array $p, string $kontakt): array
    {
        return ['name' => self::name($p), 'link' => self::kurz($p), 'kontakt' => self::kontakt($p, $kontakt)];
    }

    /**
     * Eine Seite als GD-Bild in voller Größe (mit Beschnitt). $mitQr: false,
     * wenn der Code im PDF als Vektor darübergelegt wird.
     */
    private static function leinwand(array $p, string $stil, string $seite, string $sprache, string $kontakt, bool $mitQr = true): ?\GdImage
    {
        $im = @imagecreatefromjpeg(self::datei($stil, $seite, $sprache));
        if (!$im) { return null; }
        if ($seite === 'vorn') { return $im; }
        $L = self::layout($stil);
        $k = imagesx($im) / self::LW;                    // Pixel je Layout-Einheit
        imagealphablending($im, true);
        foreach (self::zeilen($p, $kontakt) as $feld => $text) {
            $f = $L[$feld];
            $datei = self::schrift((int) $f['font']);
            if (!is_file($datei) || $text === '') { continue; }
            // Schriftgröße: em in Layout-Einheiten → Pixel → Punkt (GD rechnet mit 96 dpi)
            $pt = $f['size'] * $k * 72 / 96;
            $max = $f['max'] * $k;
            for ($i = 0; $i < 12; $i++) {
                $bb = imagettfbbox($pt, 0, $datei, $text);
                if (abs($bb[2] - $bb[0]) <= $max) { break; }
                $pt *= 0.93;
            }
            [$r, $g, $b] = sscanf((string) $f['farbe'], '#%02x%02x%02x');
            imagettftext($im, $pt, 0, (int) round($f['x'] * $k), (int) round($f['y'] * $k), imagecolorallocate($im, $r, $g, $b), $datei, $text);
        }
        if ($mitQr) {
            [$n, $raster] = self::raster(self::link($p));
            [$qx, $qy, $qs] = $L['qr'];
            $m = $qs * $k / $n;
            $x0 = $qx * $k; $y0 = $qy * $k;
            $weiss = imagecolorallocate($im, 255, 255, 255);
            imagefilledrectangle($im, (int) floor($x0), (int) floor($y0), (int) ceil($x0 + $qs * $k), (int) ceil($y0 + $qs * $k), $weiss);
            $schwarz = imagecolorallocate($im, 0, 0, 0);
            for ($y = 0; $y < $n; $y++) {
                for ($x = 0; $x < $n; $x++) {
                    if (!$raster[$y][$x]) { continue; }
                    imagefilledrectangle($im, (int) round($x0 + $x * $m), (int) round($y0 + $y * $m),
                        (int) round($x0 + ($x + 1) * $m) - 1, (int) round($y0 + ($y + 1) * $m) - 1, $schwarz);
                }
            }
        }
        return $im;
    }

    private static function jpeg(\GdImage $im, int $q = 92): string
    {
        ob_start();
        imagejpeg($im, null, $q);
        return (string) ob_get_clean();
    }

    /** Eine Seite als JPEG, mit Beschnitt (für die Druckerei) oder beschnitten ($beschnitten). */
    public static function bild(array $p, string $stil, string $seite, string $sprache, string $kontakt = 'email', bool $beschnitten = true): string
    {
        if (!self::gibt($stil)) { return ''; }
        $im = self::leinwand($p, $stil, $seite, $sprache, $kontakt);
        if (!$im) { return ''; }
        if ($beschnitten) {
            $b = (int) round(imagesx($im) * self::BESCHNITT / self::LW);
            $zu = imagecrop($im, ['x' => $b, 'y' => $b, 'width' => imagesx($im) - 2 * $b, 'height' => imagesy($im) - 2 * $b]);
            if ($zu) { $im = $zu; }
        }
        return self::jpeg($im);
    }

    /** Vorder- und Rückseite beschnitten nebeneinander (Dashboard). */
    public static function vorschau(array $p, string $stil, string $sprache, string $kontakt = 'email', int $breite = 720): string
    {
        if (!self::gibt($stil)) { return ''; }
        $v = self::leinwand($p, $stil, 'vorn', $sprache, $kontakt);
        $h = self::leinwand($p, $stil, 'hinten', $sprache, $kontakt);
        if (!$v || !$h) { return ''; }
        $b = (int) round(imagesx($v) * self::BESCHNITT / self::LW);
        $kw = imagesx($v) - 2 * $b; $kh = imagesy($v) - 2 * $b;
        $zw = (int) round(($breite - 12) / 2); $zh = (int) round($zw * $kh / $kw);
        $aus = imagecreatetruecolor($breite, $zh);
        imagefill($aus, 0, 0, imagecolorallocate($aus, 13, 11, 8));
        imagecopyresampled($aus, $v, 0, 0, $b, $b, $zw, $zh, $kw, $kh);
        imagecopyresampled($aus, $h, $breite - $zw, 0, $b, $b, $zw, $zh, $kw, $kh);
        return self::jpeg($aus, 84);
    }

    /**
     * Druck-PDF. $art: 'einzeln' (2 Seiten 91 × 61 mm mit Beschnitt) oder
     * 'bogen' (A4, 10 Karten, Schnittmarken, Rückseite gespiegelt).
     */
    public static function pdf(array $p, string $stil, string $sprache, string $kontakt = 'email', string $art = 'einzeln'): string
    {
        if (!self::gibt($stil)) { return ''; }
        $v = self::leinwand($p, $stil, 'vorn', $sprache, $kontakt);
        $h = self::leinwand($p, $stil, 'hinten', $sprache, $kontakt, false);
        if (!$v || !$h) { return ''; }
        $pdf = new KartenPdf();
        if (!empty($p['id'])) { require_once __DIR__ . '/PartnerSchutz.php'; $pdf->kennung = PartnerSchutz::kennung($p); }   // unsichtbar im PDF (30.09.2026)
        $iv = $pdf->bild(self::jpeg($v, 93), imagesx($v), imagesy($v));
        $ih = $pdf->bild(self::jpeg($h, 93), imagesx($h), imagesy($h));
        $mm = 72 / 25.4;
        $L = self::layout($stil);
        [$n, $raster] = self::raster(self::link($p));
        // QR in mm relativ zur Leinwand (Layout-Einheit = 0,1 mm)
        $qr = static function (float $ox, float $oy) use ($L, $n, $raster, $mm): string {
            [$qx, $qy, $qs] = $L['qr'];
            $m = $qs / 10 / $n;
            $o = sprintf("1 1 1 rg %.3F %.3F %.3F %.3F re f\n0 0 0 rg\n", ($ox + $qx / 10) * $mm, ($oy - $qy / 10 - $qs / 10) * $mm, $qs / 10 * $mm, $qs / 10 * $mm);
            for ($y = 0; $y < $n; $y++) {
                $x = 0;
                while ($x < $n) {
                    if (!$raster[$y][$x]) { $x++; continue; }
                    $s = $x;
                    while ($x < $n && $raster[$y][$x]) { $x++; }
                    $o .= sprintf("%.3F %.3F %.3F %.3F re f\n", ($ox + $qx / 10 + $s * $m) * $mm, ($oy - $qy / 10 - ($y + 1) * $m) * $mm - 0.02, ($x - $s) * $m * $mm + 0.03, $m * $mm + 0.03);
                }
            }
            return $o;
        };

        if ($art !== 'bogen') {
            $bw = 91 * $mm; $bh = 61 * $mm;
            $pdf->seite($bw, $bh, sprintf("q %.3F 0 0 %.3F 0 0 cm /%s Do Q\n", $bw, $bh, $iv), 3 * $mm);
            $pdf->seite($bw, $bh, sprintf("q %.3F 0 0 %.3F 0 0 cm /%s Do Q\n", $bw, $bh, $ih) . $qr(0, 61), 3 * $mm);
            return $pdf->fertig();
        }

        // Bogen: A4 hoch, 2 × 5 Karten zu 85 × 55 mm, ohne Abstand (ein Schnitt je Linie)
        $aw = 210; $ah = 297; $kw = 85; $kh = 55;
        $lx = ($aw - 2 * $kw) / 2; $ty = ($ah - 5 * $kh) / 2;
        foreach (['vorn', 'hinten'] as $s) {
            $o = '';
            for ($r = 0; $r < 5; $r++) {
                for ($c = 0; $c < 2; $c++) {
                    $spalte = $s === 'hinten' ? 1 - $c : $c;          // gespiegelt für beidseitigen Druck
                    $x = $lx + $spalte * $kw; $yOben = $ty + $r * $kh;
                    $ox = $x - 3; $oy = $ah - $yOben + 3;          // Leinwand-Ursprung (oben links) in PDF-mm
                    $o .= sprintf("q %.3F %.3F %.3F %.3F re W n\n", $x * $mm, ($ah - $yOben - $kh) * $mm, $kw * $mm, $kh * $mm);
                    $o .= sprintf("q %.3F 0 0 %.3F %.3F %.3F cm /%s Do Q\n", 91 * $mm, 61 * $mm, $ox * $mm, ($oy - 61) * $mm, $s === 'vorn' ? $iv : $ih);
                    if ($s === 'hinten') { $o .= $qr($ox, $oy); }
                    $o .= "Q\n";
                }
            }
            // Schnittmarken außerhalb des Rasters
            $o .= "0.2 w 0 0 0 RG\n";
            foreach ([0, 1, 2] as $c) {
                $x = ($lx + $c * $kw) * $mm;
                $o .= sprintf("%.3F %.3F m %.3F %.3F l S %.3F %.3F m %.3F %.3F l S\n", $x, ($ah - $ty + 2) * $mm, $x, ($ah - $ty + 7) * $mm, $x, ($ty - 2) * $mm, $x, ($ty - 7) * $mm);
            }
            for ($r = 0; $r <= 5; $r++) {
                $y = ($ah - $ty - $r * $kh) * $mm;
                $o .= sprintf("%.3F %.3F m %.3F %.3F l S %.3F %.3F m %.3F %.3F l S\n", ($lx - 2) * $mm, $y, ($lx - 7) * $mm, $y, ($aw - $lx + 2) * $mm, $y, ($aw - $lx + 7) * $mm, $y);
            }
            $pdf->seite($aw * $mm, $ah * $mm, $o);
        }
        return $pdf->fertig();
    }
}

/**
 * Kleinster mehrseitiger PDF-Schreiber für Bilder und Flächen. Die Klasse Pdf
 * bleibt einseitig und unberührt (Belege, Rechnungen); hier braucht es zwei
 * Seiten und ein Bild, das auf einer Seite zehnmal steht, aber nur einmal in
 * der Datei liegt.
 */
final class KartenPdf
{
    /** @var list<array{0:string,1:int,2:int}> */
    private array $bilder = [];
    /** @var list<array{0:float,1:float,2:string,3:float}> */
    private array $seiten = [];

    /** Kennung des Partners als Dokumenteigenschaft (PartnerSchutz). Leer = keine. */
    public string $kennung = '';

    /** Nimmt ein JPEG auf und gibt seinen Namen zurück (/Im1 …). */
    public function bild(string $jpeg, int $breite, int $hoehe): string
    {
        $this->bilder[] = [$jpeg, $breite, $hoehe];
        return 'Im' . count($this->bilder);
    }

    /**
     * $beschnitt (in pt) > 0: die Seite ist das Format MIT Beschnitt. Dann
     * stehen TrimBox (Endformat) und BleedBox (mit Beschnitt) im PDF — daran
     * erkennen Druckereien und ihre Prüfprogramme das Endformat, ohne zu raten
     * (Marketing Center, 03.10.2026).
     */
    public function seite(float $breite, float $hoehe, string $inhalt, float $beschnitt = 0.0): void
    {
        $this->seiten[] = [$breite, $hoehe, $inhalt, $beschnitt];
    }

    public function fertig(): string
    {
        $nb = count($this->bilder);
        $ns = count($this->seiten);
        // 1 Katalog, 2 Seiten-Baum, dann die Bilder, dann je Seite: Seite + Inhalt
        $xo = '';
        foreach ($this->bilder as $i => $_) { $xo .= sprintf('/Im%d %d 0 R ', $i + 1, 3 + $i); }
        $kids = [];
        $obj = ["<< /Type /Catalog /Pages 2 0 R >>", ''];
        foreach ($this->bilder as [$d, $w, $h]) {
            $obj[] = sprintf("<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream", $w, $h, strlen($d), $d);
        }
        foreach ($this->seiten as $i => [$w, $h, $inhalt, $b]) {
            $seiteNr = 3 + $nb + 2 * $i;
            $kids[] = "$seiteNr 0 R";
            $boxen = $b > 0 ? sprintf(' /BleedBox [0 0 %.3F %.3F] /TrimBox [%.3F %.3F %.3F %.3F]', $w, $h, $b, $b, $w - $b, $h - $b) : '';
            $obj[] = sprintf("<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.3F %.3F]%s /Resources << /XObject << %s>> >> /Contents %d 0 R >>", $w, $h, $boxen, $xo, $seiteNr + 1);
            $obj[] = sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($inhalt), $inhalt);
        }
        $obj[1] = sprintf("<< /Type /Pages /Kids [%s] /Count %d >>", implode(' ', $kids), $ns);
        $info = '';
        if ($this->kennung !== '') {
            $obj[] = '<< /Author (Vecom Design) /Keywords (' . preg_replace('~[^A-Za-z0-9-]~', '', $this->kennung) . ') >>';
            $info = ' /Info ' . count($obj) . ' 0 R';
        }
        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $stellen = [];
        foreach ($obj as $i => $o) { $stellen[] = strlen($pdf); $pdf .= ($i + 1) . " 0 obj\n$o\nendobj\n"; }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($obj) + 1) . "\n0000000000 65535 f \n";
        foreach ($stellen as $s) { $pdf .= sprintf("%010d 00000 n \n", $s); }
        return $pdf . "trailer\n<< /Size " . (count($obj) + 1) . " /Root 1 0 R$info >>\nstartxref\n$xref\n%%EOF\n";
    }
}
