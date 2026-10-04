<?php
declare(strict_types=1);

/**
 * Druckdateien der weiteren Werbemittel (Marketing Center, 04.10.2026, Uwe:
 * „zu jedem Produkt intelligent, passend zu Produkt und Partner: Logo,
 * Farbe, Schrift, Name, QR-Code, E-Mail oder vecom-design.it“).
 *
 * Wie die Visitenkarten (PartnerKarten): Die Hintergründe — Logo als Vektor,
 * Gold, die vier Stile A–D, feste Texte je Sprache — erzeugt
 * tools/werbemittel/gen.py einmal im Format der Druckerei (mit Beschnitt,
 * 300 dpi). Hier kommt pro Partner dazu: Name, sein Link, sein Kontakt
 * (seine E-Mail oder kontakt@vecom-design.it) und der echte QR-Code — im PDF
 * als Vektor. Texte, die zu lang sind, werden schrittweise kleiner, nie
 * abgeschnitten; der QR-Code ist nie kleiner als 2 cm (Prüfung in der Kette).
 */
require_once __DIR__ . '/Partner.php';
require_once __DIR__ . '/PartnerWerbung.php';
require_once __DIR__ . '/PartnerKarten.php';

final class WmDruck
{
    /** Formate mit Vorlage. Schlüssel = wm_produkte.vorlage. */
    public const FORMATE = [
        'flyer_a6' => 'Flyer A6 (Vorlage)',
        'flyer_a5' => 'Flyer A5 (Vorlage)',
    ];

    /** @var array<string, array> */
    private static array $layouts = [];

    /** Hinter dem QR-Code: der Partnerlink mit Kanal (Flyer zählen als „flyer“ in der Auswertung). */
    public static function qrLink(array $p, string $fmt): string
    {
        return PartnerWerbung::link($p, str_starts_with($fmt, 'flyer') ? 'flyer' : 'qr');
    }

    public static function layout(string $fmt): array
    {
        if (!isset(self::FORMATE[$fmt])) { return []; }
        return self::$layouts[$fmt] ??= (array) require dirname(__DIR__) . '/werbemittel/' . $fmt . '/layout.php';
    }

    public static function gibt(string $fmt, string $stil): bool
    {
        return isset(self::FORMATE[$fmt], self::layout($fmt)['stile'][$stil]) && is_file(self::datei($fmt, $stil, 'vorn', 'de'));
    }

    private static function datei(string $fmt, string $stil, string $seite, string $sprache): string
    {
        $sprache = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it';
        return dirname(__DIR__) . '/werbemittel/' . $fmt . '/' . $stil . '-' . ($seite === 'hinten' ? 'hinten' : 'vorn') . '-' . $sprache . '.jpg';
    }

    /** Eine Seite als GD-Bild in voller Größe (mit Beschnitt). $mitQr: false, wenn der Code im PDF als Vektor kommt. */
    private static function leinwand(array $p, string $fmt, string $stil, string $seite, string $sprache, string $kontakt, bool $mitQr = true): ?\GdImage
    {
        $im = @imagecreatefromjpeg(self::datei($fmt, $stil, $seite, $sprache));
        if (!$im) { return null; }
        if ($seite !== 'hinten') { return $im; }
        $lay = self::layout($fmt);
        $L = $lay['stile'][$stil];
        $k = imagesx($im) / (($lay['b'] + 2 * $lay['beschnitt']) * 10);          // Pixel je 1/10 mm
        imagealphablending($im, true);
        foreach (PartnerKarten::zeilen($p, $kontakt) as $feld => $text) {
            $f = $L[$feld] ?? null;
            $datei = PartnerKarten::schrift((int) ($f['font'] ?? 500));
            if (!$f || !is_file($datei) || $text === '') { continue; }
            $pt = $f['size'] * $k * 72 / 96;
            $max = $f['max'] * $k;
            for ($i = 0; $i < 14; $i++) {
                $bb = imagettfbbox($pt, 0, $datei, $text);
                if (abs($bb[2] - $bb[0]) <= $max) { break; }
                $pt *= 0.93;
            }
            [$r, $g, $b] = sscanf((string) $f['farbe'], '#%02x%02x%02x');
            imagettftext($im, $pt, 0, (int) round($f['x'] * $k), (int) round($f['y'] * $k), imagecolorallocate($im, $r, $g, $b), $datei, $text);
        }
        if ($mitQr) {
            [$n, $raster] = PartnerKarten::raster(self::qrLink($p, $fmt));
            [$qx, $qy, $qs] = $L['qr'];
            $m = $qs * $k / $n; $x0 = $qx * $k; $y0 = $qy * $k;
            $weiss = imagecolorallocate($im, 255, 255, 255); $schwarz = imagecolorallocate($im, 0, 0, 0);
            imagefilledrectangle($im, (int) floor($x0), (int) floor($y0), (int) ceil($x0 + $qs * $k), (int) ceil($y0 + $qs * $k), $weiss);
            for ($y = 0; $y < $n; $y++) {
                for ($x = 0; $x < $n; $x++) {
                    if ($raster[$y][$x]) {
                        imagefilledrectangle($im, (int) round($x0 + $x * $m), (int) round($y0 + $y * $m), (int) round($x0 + ($x + 1) * $m) - 1, (int) round($y0 + ($y + 1) * $m) - 1, $schwarz);
                    }
                }
            }
        }
        return $im;
    }

    private static function jpeg(\GdImage $im, int $q = 90): string
    {
        ob_start(); imagejpeg($im, null, $q); return (string) ob_get_clean();
    }

    /** Druckdatei: Vorder- und Rückseite mit Beschnitt (TrimBox/BleedBox), QR-Code als Vektor. */
    public static function pdf(array $p, string $fmt, string $stil, string $sprache, string $kontakt = 'email'): string
    {
        if (!self::gibt($fmt, $stil)) { return ''; }
        $lay = self::layout($fmt);
        $v = self::leinwand($p, $fmt, $stil, 'vorn', $sprache, $kontakt);
        $h = self::leinwand($p, $fmt, $stil, 'hinten', $sprache, $kontakt, false);
        if (!$v || !$h) { return ''; }
        $bw = $lay['b'] + 2 * $lay['beschnitt']; $bh = $lay['h'] + 2 * $lay['beschnitt'];
        $mm = 72 / 25.4;
        $pdf = new KartenPdf();
        if (!empty($p['id'])) { require_once __DIR__ . '/PartnerSchutz.php'; $pdf->kennung = PartnerSchutz::kennung($p); }
        $iv = $pdf->bild(self::jpeg($v, 92), imagesx($v), imagesy($v));
        $ih = $pdf->bild(self::jpeg($h, 92), imagesx($h), imagesy($h));
        [$n, $raster] = PartnerKarten::raster(self::qrLink($p, $fmt));
        $pdf->seite($bw * $mm, $bh * $mm, sprintf("q %.3F 0 0 %.3F 0 0 cm /%s Do Q\n", $bw * $mm, $bh * $mm, $iv), $lay['beschnitt'] * $mm);
        $pdf->seite($bw * $mm, $bh * $mm, sprintf("q %.3F 0 0 %.3F 0 0 cm /%s Do Q\n", $bw * $mm, $bh * $mm, $ih)
            . PartnerKarten::qrVektor($lay['stile'][$stil], $n, $raster, 0.0, (float) $bh), $lay['beschnitt'] * $mm);
        return $pdf->fertig();
    }

    /** Vorder- und Rückseite beschnitten nebeneinander, klein (Partnerbereich). */
    public static function vorschau(array $p, string $fmt, string $stil, string $sprache, string $kontakt = 'email', int $hoehe = 360): string
    {
        if (!self::gibt($fmt, $stil)) { return ''; }
        $lay = self::layout($fmt);
        $teile = [];
        foreach (['vorn', 'hinten'] as $s) {
            $im = self::leinwand($p, $fmt, $stil, $s, $sprache, $kontakt);
            if (!$im) { return ''; }
            $b = (int) round(imagesx($im) * $lay['beschnitt'] / ($lay['b'] + 2 * $lay['beschnitt']));
            $zu = imagecrop($im, ['x' => $b, 'y' => $b, 'width' => imagesx($im) - 2 * $b, 'height' => imagesy($im) - 2 * $b]) ?: $im;
            $teile[] = imagescale($zu, (int) round(imagesx($zu) * $hoehe / imagesy($zu)), $hoehe, IMG_BICUBIC) ?: $zu;
        }
        $abstand = (int) round($hoehe * 0.05);
        $aus = imagecreatetruecolor(imagesx($teile[0]) + imagesx($teile[1]) + $abstand, $hoehe);
        imagefill($aus, 0, 0, imagecolorallocate($aus, 255, 255, 255));
        imagecopy($aus, $teile[0], 0, 0, 0, 0, imagesx($teile[0]), $hoehe);
        imagecopy($aus, $teile[1], imagesx($teile[0]) + $abstand, 0, 0, 0, imagesx($teile[1]), $hoehe);
        return self::jpeg($aus, 86);
    }
}
