<?php
declare(strict_types=1);

/**
 * Wandkalender A3 hoch für Partner (Marketingcenter, 04.10.2026, Uwe: „ja“
 * zu den Geschenken für Betriebe — Vorschlag 6).
 *
 * „12 Monate, 12 Ideen“: Der Partner schenkt einem Betrieb den Kalender; jeden
 * Monat steht dort eine praktische Idee für dessen digitalen Auftritt, unten
 * auf jeder Seite Name, Link und QR-Code des Partners. Ein Jahr lang an der Wand.
 *
 * Gedruckt bei Gelato (Vorgaben geprüft 04.10.2026, support.gelato.com 8996274:
 * 297 × 420 mm, 4 mm Beschnitt, ~300 dpi, PDF mit 14 Seiten = Titel, 12 Monate,
 * eine leere Seite; Wire-O oben). Die Seiten ohne Partnerdaten zeichnet
 * tools/werbemittel/kalender.py; hier kommen dazu:
 *   - die Fußplatte des Partners (ein Bild, auf allen 13 Seiten dasselbe Objekt),
 *   - der QR-Code als Vektor (Kanal = Nummer des Werbemittels),
 *   - die gesetzlichen Feiertage seines Lieferlandes als kleine Zellenbilder
 *     über dem Kalendarium (Italien und Deutschland bundesweit; sonst keine).
 * Die großen Seitenbilder lädt PHP nie in GD (je 73 MB): sie gehen unverändert
 * ins PDF. Die Vorschau nimmt die kleinen Fassungen (-klein.jpg).
 */
require_once __DIR__ . '/PartnerKarten.php';
require_once __DIR__ . '/PartnerWerbung.php';
require_once __DIR__ . '/PartnerKalender.php';

final class WmKalender
{
    public const VORLAGE = 'kalender_a3';
    /** Gelato: Titel + 12 Monate + leere Seite. Geht als pageCount mit dem Auftrag. */
    public const SEITEN = 14;
    public const STILE = ['a'];

    private static ?array $layout = null;

    public static function layout(): array
    {
        $d = self::ordner() . '/layout.php';
        return self::$layout ??= is_file($d) ? (array) require $d : [];
    }

    private static function ordner(): string
    {
        return dirname(__DIR__) . '/druckvorlagen/' . self::VORLAGE;
    }

    public static function gibt(string $stil): bool
    {
        return in_array($stil, self::STILE, true) && is_file(self::datei('de', 0)) && self::layout() !== [];
    }

    /** Seite $nr (0 = Titel, 1–12 = Monate) als Datei; klein = Fassung für die Vorschau. */
    public static function datei(string $sprache, int $nr, bool $klein = false): string
    {
        $sprache = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it';
        return self::ordner() . '/' . $sprache . '-' . sprintf('%02d', $nr) . ($klein ? '-klein' : '') . '.jpg';
    }

    /**
     * Gesetzliche Feiertage, landesweit (regionale fehlen bewusst — lieber keiner als ein falscher).
     * Italien seit 2026 auch San Francesco am 4. Oktober (Gesetz 2025, „festa nazionale“).
     * @return array<int, array<int, array{it:string,de:string,en:string}>> Monat => Tag => Name
     */
    public static function feiertage(int $jahr, string $land): array
    {
        $o = PartnerKalender::ostern($jahr);
        $tag = static fn(int $ts): array => [(int) date('n', $ts), (int) date('j', $ts)];
        $liste = [];
        if ($land === 'IT') {
            $liste = [
                [[1, 1], 'Capodanno', 'Neujahr', 'New Year’s Day'], [[1, 6], 'Epifania', 'Heilige Drei Könige', 'Epiphany'],
                [$tag($o), 'Pasqua', 'Ostersonntag', 'Easter Sunday'], [$tag($o + 86400), 'Pasquetta', 'Ostermontag', 'Easter Monday'],
                [[4, 25], 'Liberazione', 'Tag der Befreiung', 'Liberation Day'], [[5, 1], 'Festa del Lavoro', 'Tag der Arbeit', 'Labour Day'],
                [[6, 2], 'Festa della Repubblica', 'Tag der Republik', 'Republic Day'], [[8, 15], 'Ferragosto', 'Mariä Himmelfahrt', 'Assumption Day'],
                [[10, 4], 'San Francesco', 'Hl. Franziskus', 'St Francis of Assisi'],
                [[11, 1], 'Ognissanti', 'Allerheiligen', 'All Saints’ Day'], [[12, 8], 'Immacolata', 'Mariä Empfängnis', 'Immaculate Conception'],
                [[12, 25], 'Natale', '1. Weihnachtstag', 'Christmas Day'], [[12, 26], 'Santo Stefano', '2. Weihnachtstag', 'St Stephen’s Day'],
            ];
        } elseif ($land === 'DE') {
            $liste = [
                [[1, 1], 'Capodanno', 'Neujahr', 'New Year’s Day'], [$tag($o - 2 * 86400), 'Venerdì Santo', 'Karfreitag', 'Good Friday'],
                [$tag($o + 86400), 'Lunedì di Pasqua', 'Ostermontag', 'Easter Monday'], [[5, 1], 'Festa del Lavoro', 'Tag der Arbeit', 'Labour Day'],
                [$tag($o + 39 * 86400), 'Ascensione', 'Christi Himmelfahrt', 'Ascension Day'], [$tag($o + 50 * 86400), 'Lunedì di Pentecoste', 'Pfingstmontag', 'Whit Monday'],
                [[10, 3], 'Unità tedesca', 'Tag der Deutschen Einheit', 'German Unity Day'],
                [[12, 25], 'Natale', '1. Weihnachtstag', 'Christmas Day'], [[12, 26], 'Santo Stefano', '2. Weihnachtstag', 'Boxing Day'],
            ];
        }
        $aus = [];
        foreach ($liste as [[$m, $d], $it, $de, $en]) { $aus[$m][$d] = ['it' => $it, 'de' => $de, 'en' => $en]; }
        return $aus;
    }

    /** Fußplatte des Partners: Name, Link, Kontakt auf dem Grund der Platte. $k = Pixel je Layout-Einheit. */
    private static function fussBild(array $p, string $kontakt, float $k): ?\GdImage
    {
        [, , $b, $h] = self::layout()['titel']['platte'];
        $im = imagecreatetruecolor(max(1, (int) round($b * $k)), max(1, (int) round($h * $k)));
        imagefill($im, 0, 0, imagecolorallocate($im, 0x0d, 0x0c, 0x0a));
        $zeilen = [[PartnerKarten::name($p), 700, 74, [0xf6, 0xf1, 0xe6], 70],
                   [PartnerKarten::kurz($p), 600, 50, [0xe6, 0xb8, 0x5c], 128],
                   [PartnerKarten::kontakt($p, $kontakt), 500, 46, [0xf6, 0xf1, 0xe6], 178]];
        foreach ($zeilen as [$text, $gew, $gr, [$r, $g, $bl], $y]) {
            $datei = PartnerKarten::schrift($gew);
            if ($text === '' || !is_file($datei)) { continue; }
            $pt = $gr * $k * 72 / 96;
            for ($i = 0; $i < 14 && abs(imagettfbbox($pt, 0, $datei, $text)[2] - imagettfbbox($pt, 0, $datei, $text)[0]) > $b * $k; $i++) { $pt *= 0.93; }
            imagettftext($im, $pt, 0, 0, (int) round($y * $k), imagecolorallocate($im, $r, $g, $bl), $datei, $text);
        }
        return $im;
    }

    /** Feiertagszelle: gleicher Papiergrund, Tag in Gold, Name klein darunter. */
    private static function zellBild(int $tag, string $name, float $w, float $h, float $k): \GdImage
    {
        $im = imagecreatetruecolor(max(1, (int) round($w * $k)), max(1, (int) round($h * $k)));
        imagefill($im, 0, 0, imagecolorallocate($im, 0xf7, 0xf2, 0xe8));
        $gold = imagecolorallocate($im, 0x9a, 0x6f, 0x25);
        $fett = PartnerKarten::schrift(700); $mittel = PartnerKarten::schrift(600);
        if (is_file($fett)) { imagettftext($im, 72 * $k * 72 / 96, 0, (int) round(22 * $k), (int) round(88 * $k), $gold, $fett, (string) $tag); }
        if (is_file($mittel)) {
            $pt = 38 * $k * 72 / 96;                      // ~3,8 mm: auf A3 aus Armlänge lesbar
            for ($i = 0; $i < 12 && abs(imagettfbbox($pt, 0, $mittel, $name)[2] - imagettfbbox($pt, 0, $mittel, $name)[0]) > ($w - 40) * $k; $i++) { $pt *= 0.93; }
            imagettftext($im, $pt, 0, (int) round(22 * $k), (int) round(($h - 22) * $k), $gold, $mittel, $name);
        }
        return $im;
    }

    private static function jpeg(\GdImage $im, int $q = 92): string
    {
        ob_start(); imagejpeg($im, null, $q); return (string) ob_get_clean();
    }

    /** Druckdatei: 14 Seiten mit 4 mm Beschnitt (TrimBox/BleedBox), Code als Vektor auf Titel und jedem Monat. */
    public static function pdf(array $p, string $stil, string $sprache, string $kontakt = 'email'): string
    {
        if (!self::gibt($stil)) { return ''; }
        $L = self::layout();
        $mm = 72 / 25.4;
        $bw = $L['b'] + 2 * $L['beschnitt']; $bh = $L['h'] + 2 * $L['beschnitt'];
        $erste = @getimagesize(self::datei($sprache, 0));
        if (!$erste) { return ''; }
        $k = $erste[0] / ($bw * 10);                                     // Pixel je 1/10 mm
        $pdf = new KartenPdf();
        if (!empty($p['id'])) { require_once __DIR__ . '/PartnerSchutz.php'; $pdf->kennung = PartnerSchutz::kennung($p); }
        $fuss = self::fussBild($p, $kontakt, $k);
        if (!$fuss) { return ''; }
        $if = $pdf->bild(self::jpeg($fuss, 95), imagesx($fuss), imagesy($fuss));
        require_once __DIR__ . '/WmDruck.php';
        [$n, $raster] = PartnerKarten::raster(WmDruck::qrLink($p, self::VORLAGE));
        require_once __DIR__ . '/Werbemittel.php';
        $feiertage = self::feiertage((int) $L['jahr'], Werbemittel::anzeigeLand($p));
        $legen = static fn(string $name, float $x, float $y, float $w, float $h): string =>
            sprintf("q %.3F 0 0 %.3F %.3F %.3F cm /%s Do Q\n", $w / 10 * $mm, $h / 10 * $mm, $x / 10 * $mm, ($bh - ($y + $h) / 10) * $mm, $name);
        for ($nr = 0; $nr <= 12; $nr++) {
            $jpeg = (string) @file_get_contents(self::datei($sprache, $nr));
            $mass = $jpeg !== '' ? @getimagesizefromstring($jpeg) : false;
            if (!$mass) { return ''; }
            $S = $nr === 0 ? $L['titel'] : $L['monate'][(string) $nr];
            $inhalt = sprintf("q %.3F 0 0 %.3F 0 0 cm /%s Do Q\n", $bw * $mm, $bh * $mm, $pdf->bild($jpeg, $mass[0], $mass[1]));
            foreach ($nr > 0 ? ($feiertage[$nr] ?? []) : [] as $tag => $namen) {
                if (!isset($S['zellen'][(string) $tag])) { continue; }
                [$x, $y, $w, $h] = $S['zellen'][(string) $tag];
                $z = self::zellBild((int) $tag, $namen[$sprache] ?? $namen['it'], $w, $h, $k);
                $inhalt .= $legen($pdf->bild(self::jpeg($z, 92), imagesx($z), imagesy($z)), $x, $y, $w, $h);
            }
            [$px, $py, $pb, $ph] = $S['platte'];
            $inhalt .= $legen($if, $px, $py, $pb, $ph);
            $inhalt .= PartnerKarten::qrVektor($S, $n, $raster, 0.0, (float) $bh);
            $pdf->seite($bw * $mm, $bh * $mm, $inhalt, $L['beschnitt'] * $mm);
        }
        // Seite 14: leer (Rückseite des letzten Blatts, Gelato-Vorgabe).
        $pdf->seite($bw * $mm, $bh * $mm, sprintf("1 1 1 rg 0 0 %.3F %.3F re f\n", $bw * $mm, $bh * $mm), $L['beschnitt'] * $mm);
        return $pdf->fertig();
    }

    /** Kleine Seite mit Partnerdaten (Vorschau), ohne Beschnitt. */
    private static function kleineSeite(array $p, string $sprache, string $kontakt, int $nr): ?\GdImage
    {
        $L = self::layout();
        $im = @imagecreatefromjpeg(self::datei($sprache, $nr, true));
        if (!$im) { return null; }
        $bw = $L['b'] + 2 * $L['beschnitt'];
        $k = imagesx($im) / ($bw * 10);
        $S = $nr === 0 ? $L['titel'] : $L['monate'][(string) $nr];
        require_once __DIR__ . '/Werbemittel.php';
        foreach ($nr > 0 ? (self::feiertage((int) $L['jahr'], Werbemittel::anzeigeLand($p))[$nr] ?? []) : [] as $tag => $namen) {
            if (!isset($S['zellen'][(string) $tag])) { continue; }
            [$x, $y, $w, $h] = $S['zellen'][(string) $tag];
            $z = self::zellBild((int) $tag, $namen[$sprache] ?? $namen['it'], $w, $h, $k);
            imagecopy($im, $z, (int) round($x * $k), (int) round($y * $k), 0, 0, imagesx($z), imagesy($z));
        }
        $fuss = self::fussBild($p, $kontakt, $k);
        [$px, $py] = $S['platte'];
        if ($fuss) { imagecopy($im, $fuss, (int) round($px * $k), (int) round($py * $k), 0, 0, imagesx($fuss), imagesy($fuss)); }
        require_once __DIR__ . '/WmDruck.php';
        [$n, $raster] = PartnerKarten::raster(WmDruck::qrLink($p, self::VORLAGE));
        [$qx, $qy, $qs] = $S['qr'];
        $m = $qs * $k / $n;
        $weiss = imagecolorallocate($im, 255, 255, 255); $schwarz = imagecolorallocate($im, 0, 0, 0);
        imagefilledrectangle($im, (int) floor($qx * $k), (int) floor($qy * $k), (int) ceil(($qx + $qs) * $k), (int) ceil(($qy + $qs) * $k), $weiss);
        for ($y = 0; $y < $n; $y++) { for ($x = 0; $x < $n; $x++) { if ($raster[$y][$x]) {
            imagefilledrectangle($im, (int) round($qx * $k + $x * $m), (int) round($qy * $k + $y * $m), (int) round($qx * $k + ($x + 1) * $m) - 1, (int) round($qy * $k + ($y + 1) * $m) - 1, $schwarz);
        } } }
        $r = (int) round($L['beschnitt'] * 10 * $k);
        return imagecrop($im, ['x' => $r, 'y' => $r, 'width' => imagesx($im) - 2 * $r, 'height' => imagesy($im) - 2 * $r]) ?: $im;
    }

    /** Vorschau: Titel und Januar (mit Feiertagen seines Landes) nebeneinander. */
    public static function vorschau(array $p, string $stil, string $sprache, string $kontakt = 'email', int $hoehe = 760): string
    {
        if (!self::gibt($stil)) { return ''; }
        $teile = [];
        foreach ([0, 1] as $nr) {
            $s = self::kleineSeite($p, $sprache, $kontakt, $nr);
            if (!$s) { return ''; }
            $teile[] = imagescale($s, (int) round(imagesx($s) * $hoehe / imagesy($s)), $hoehe, IMG_BICUBIC) ?: $s;
        }
        $abstand = (int) round($hoehe * 0.04);
        $aus = imagecreatetruecolor(imagesx($teile[0]) + imagesx($teile[1]) + $abstand, $hoehe);
        imagefill($aus, 0, 0, imagecolorallocate($aus, 255, 255, 255));
        imagecopy($aus, $teile[0], 0, 0, 0, 0, imagesx($teile[0]), $hoehe);
        imagecopy($aus, $teile[1], imagesx($teile[0]) + $abstand, 0, 0, 0, imagesx($teile[1]), $hoehe);
        return self::jpeg($aus, 86);
    }
}
