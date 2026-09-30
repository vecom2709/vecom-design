<?php
declare(strict_types=1);

/**
 * Fertige Branchen-Flyer im Werbe-Paket des Partners (28.09.2026, Uwe:
 * „alle Flyer mit den entsprechenden Partner-Links, auch der QR-Code“).
 *
 * Die Vorlagen liegen in app/flyer/ (nicht öffentlich, .htaccess). Ihre
 * QR-Fläche ist weiß; hier kommt pro Partner der echte, scanbare Code auf
 * seinen Link /p/CODE/flyer hinein, darunter die kurze Adresse zum Abtippen.
 *
 *   jpg()  Bild in doppelter Auflösung (Handy, WhatsApp, Druckerei)
 *   pdf()  eine Seite, 148 mm breit; der QR-Code als Vektor, gestochen scharf
 *
 * Die Flyer selbst sind deutsch. Die Gruppen heißen in allen drei Sprachen.
 */
final class PartnerFlyer
{
    /** Reihenfolge und Namen der Gruppen im Dashboard. */
    public const GRUPPEN = [
        'allgemein'  => ['it' => 'Generali — per tutti', 'de' => 'Allgemein — für alle Betriebe', 'en' => 'General — for every business'],
        'bau'        => ['it' => 'Artigiani, edilizia ed energia', 'de' => 'Handwerk, Bau & Energie', 'en' => 'Trades, construction & energy'],
        'gesundheit' => ['it' => 'Salute, cura e bellezza', 'de' => 'Gesundheit, Pflege & Beauty', 'en' => 'Health, care & beauty'],
        'gast'       => ['it' => 'Gastronomia, turismo ed eventi', 'de' => 'Gastronomie, Tourismus & Events', 'en' => 'Hospitality, tourism & events'],
        'beratung'   => ['it' => 'Consulenza e studi professionali', 'de' => 'Beratung & Büro', 'en' => 'Advisory & office'],
        'handel'     => ['it' => 'Commercio e mobilità', 'de' => 'Handel & Mobilität', 'en' => 'Retail & mobility'],
        'kreativ'    => ['it' => 'Creativi, associazioni e formazione', 'de' => 'Kreative, Vereine & Bildung', 'en' => 'Creatives, clubs & education'],
    ];

    /** Doppelte Auflösung fürs Bild: der Hintergrund wird weich hochgerechnet, der QR-Code bleibt scharf. */
    public const FAKTOR = 2.0;

    /** @var array<string, array>|null */
    private static ?array $liste = null;

    /** @return array<string, array{g:string, n:array, b:int, h:int, q:array{int,int,int,int}}> */
    public static function liste(): array
    {
        return self::$liste ??= (array) require dirname(__DIR__) . '/flyer/liste.php';
    }

    /** Die Flyer nach Gruppen, in der Reihenfolge von GRUPPEN. */
    public static function gruppiert(): array
    {
        $aus = array_fill_keys(array_keys(self::GRUPPEN), []);
        foreach (self::liste() as $slug => $f) { $aus[$f['g']][$slug] = $f; }
        return array_filter($aus);
    }

    public static function gibt(string $slug): bool
    {
        return preg_match('~^[a-z0-9-]{2,30}$~', $slug) === 1 && isset(self::liste()[$slug])
            && is_file(self::datei($slug));
    }

    public static function name(string $slug, string $sprache): string
    {
        $n = self::liste()[$slug]['n'] ?? [];
        return (string) ($n[$sprache] ?? $n['de'] ?? $slug);
    }

    /** Welcher Flyer zu einer Branche aus der Akquise-Liste passt (W2, 28.09.2026). Unbekannt: der allgemeine. */
    public const ZU_BRANCHE = [
        'restaurant' => 'restaurant', 'bar_cafe' => 'restaurant', 'baeckerei' => 'restaurant',
        'hotel' => 'hotel', 'ferienwohnung' => 'hotel', 'agriturismo' => 'hotel', 'tourismus' => 'tourismus',
        'handwerk' => 'handwerk', 'bau' => 'handwerk', 'immobilien' => 'architektur',
        'autohaus' => 'autohaus', 'werkstatt' => 'autohaus',
        'friseur' => 'kosmetik', 'beauty' => 'kosmetik', 'fitness' => 'fitness', 'medizin' => 'arztpraxis',
        'einzelhandel' => 'shop', 'produzent' => 'shop', 'industrie' => 'logistik',
        'kanzlei' => 'kanzlei', 'beratung' => 'steuerberater', 'dienstleister' => 'allgemein',
    ];

    public static function fuerBranche(?string $akqBranche): string
    {
        $s = self::ZU_BRANCHE[(string) $akqBranche] ?? 'allgemein';
        return isset(self::liste()[$s]) ? $s : 'allgemein';
    }

    /** Der Link hinter dem QR-Code: zählt als Kanal „flyer“ in der Auswertung. */
    public static function link(array $p): string
    {
        return PartnerWerbung::link($p, 'flyer');
    }

    /** Die Adresse zum Abtippen unter dem Code, ohne https://. */
    public static function kurz(array $p): string
    {
        return (string) preg_replace('~^https?://~', '', Partner::link($p));
    }

    public static function dateiname(array $p, string $slug, string $endung): string
    {
        return 'vecom-flyer-' . $slug . '-' . strtolower((string) preg_replace('~[^A-Za-z0-9]~', '', (string) $p['code'])) . '.' . $endung;
    }

    private static function datei(string $slug): string
    {
        return dirname(__DIR__) . '/flyer/' . $slug . '.jpg';
    }

    /** @return array{0:int,1:list<list<bool>>} Modulzahl und Raster */
    private static function raster(string $inhalt): array
    {
        require_once dirname(__DIR__) . '/lib/qrcode.php';
        $qr = QRCode::getMinimumQRCode($inhalt, QR_ERROR_CORRECT_LEVEL_M);
        $n = $qr->getModuleCount();
        $r = [];
        for ($y = 0; $y < $n; $y++) { for ($x = 0; $x < $n; $x++) { $r[$y][$x] = $qr->isDark($y, $x); } }
        return [$n, $r];
    }

    /**
     * Wo der Code sitzt: ein Quadrat mittig in der weißen Fläche, mit Rand.
     * @return array{x:float,y:float,s:float}
     */
    private static function platz(array $f, float $k = 1.0): array
    {
        [$x, $y, $b, $h] = $f['q'];
        $s = min($b, $h) * 0.84;
        return ['x' => ($x + ($b - $s) / 2) * $k, 'y' => ($y + ($h - $s) / 2) * $k, 's' => $s * $k];
    }

    /** Helligkeit (0–1) des Streifens unter der QR-Fläche — dort steht die kurze Adresse. */
    private static function hellUnten(\GdImage $im, array $f): float
    {
        [$x, $y, $b, $h] = $f['q'];
        $summe = 0.0; $n = 0;
        $y0 = min($f['h'] - 2, $y + $h + 4); $y1 = min($f['h'] - 1, $y + $h + 22);
        for ($yy = $y0; $yy <= $y1; $yy += 2) {
            for ($xx = max(0, $x + $b - 170); $xx < $x + $b; $xx += 4) {
                $c = imagecolorat($im, $xx, $yy);
                $summe += (0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255)) / 255; $n++;
            }
        }
        return $n ? $summe / $n : 0.0;
    }

    /** Schriftgröße (Pixel der Vorlage) und Lage der kurzen Adresse: rechtsbündig unter der Fläche. */
    private static function adressLage(array $f): array
    {
        [$x, $y, $b, $h] = $f['q'];
        $platz = $f['h'] - ($y + $h) - 4;
        $gr = max(8.0, min(13.0, $b * 0.14, $platz * 0.55));
        return ['rechts' => (float) ($x + $b), 'grund' => (float) ($y + $h) + $gr * 1.45, 'gr' => $gr, 'passt' => $platz >= 14];
    }

    /** Der Flyer als JPEG mit dem Code des Partners. $k: Maßstab (2 = Download, klein = Vorschau im Dashboard). */
    public static function jpg(array $p, string $slug, float $k = self::FAKTOR, int $qualitaet = 90): string
    {
        if (!self::gibt($slug) || !function_exists('imagecreatefromjpeg')) { return ''; }
        $f = self::liste()[$slug];
        $roh = @imagecreatefromjpeg(self::datei($slug));
        if (!$roh) { return ''; }
        $hell = self::hellUnten($roh, $f);
        $im = imagescale($roh, (int) round($f['b'] * $k), (int) round($f['h'] * $k), $k >= 1 ? IMG_BICUBIC : IMG_BILINEAR_FIXED) ?: $roh;
        if ($im !== $roh) { imagedestroy($roh); }

        // Weiße Fläche sauber nachziehen (weiche Kanten vom Hochrechnen), dann die Module.
        [$qx, $qy, $qb, $qh] = $f['q'];
        $weiss = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, (int) ceil($qx * $k + 1), (int) ceil($qy * $k + 1), (int) floor(($qx + $qb) * $k - 2), (int) floor(($qy + $qh) * $k - 2), $weiss);
        [$n, $r] = self::raster(self::link($p));
        $pl = self::platz($f, $k);
        $m = $pl['s'] / $n;
        $schwarz = imagecolorallocate($im, 0, 0, 0);
        for ($y = 0; $y < $n; $y++) {
            $y0 = (int) round($pl['y'] + $y * $m); $y1 = max($y0, (int) round($pl['y'] + ($y + 1) * $m) - 1);
            for ($x = 0; $x < $n; $x++) {
                if (!$r[$y][$x]) { continue; }
                $x0 = (int) round($pl['x'] + $x * $m);
                imagefilledrectangle($im, $x0, $y0, max($x0, (int) round($pl['x'] + ($x + 1) * $m) - 1), $y1, $schwarz);
            }
        }

        $a = self::adressLage($f);
        $schrift = dirname(__DIR__) . '/schrift/archivo-semibold.ttf';
        if ($a['passt'] && $k >= 1 && function_exists('imagettftext') && is_file($schrift)) {
            $farbe = $hell > 0.55 ? imagecolorallocate($im, 38, 30, 18) : imagecolorallocate($im, 247, 230, 174);
            $gr = $a['gr'] * $k * 0.75; // GD rechnet in Punkt
            $text = self::kurz($p);
            $box = imagettfbbox($gr, 0, $schrift, $text);
            $breite = abs($box[2] - $box[0]);
            $xL = max(4, (int) round($a['rechts'] * $k - $breite));
            imagettftext($im, $gr, 0, $xL, (int) round($a['grund'] * $k), $farbe, $schrift, $text);
        }

        ob_start();
        imagejpeg($im, null, $qualitaet);
        imagedestroy($im);
        return (string) ob_get_clean();
    }

    /** Der Flyer als PDF: Bild als Seite, Code als Vektor, 148 mm breit. */
    public static function pdf(array $p, string $slug): string
    {
        if (!self::gibt($slug)) { return ''; }
        require_once __DIR__ . '/Pdf.php';
        $f = self::liste()[$slug];
        $breite = 148 / 25.4 * 72;
        $k = $breite / $f['b'];
        $hoehe = $f['h'] * $k;
        $pdf = new Pdf($breite, $hoehe);
        /* Unsichtbare Kennung des Partners (PartnerSchutz, 30.09.2026): Taucht ein Flyer irgendwo auf, sagt sie, aus wessen Bereich er stammt. */
        if (!empty($p['id'])) { require_once __DIR__ . '/PartnerSchutz.php'; $pdf->info(['Title' => 'Vecom Design', 'Author' => 'Vecom Design', 'Keywords' => PartnerSchutz::kennung($p)]); }
        $pdf->bild((string) file_get_contents(self::datei($slug)), 0, 0, $breite, $hoehe);

        [$qx, $qy, $qb, $qh] = $f['q'];
        $pdf->flaeche($qx * $k, $qy * $k, $qb * $k, $qh * $k, [1, 1, 1]);
        [$n, $r] = self::raster(self::link($p));
        $pl = self::platz($f, $k);
        $m = $pl['s'] / $n;
        // Läufe je Zeile zusammenfassen und minimal überlappen: keine Haarlinien im Betrachter.
        for ($y = 0; $y < $n; $y++) {
            $x = 0;
            while ($x < $n) {
                if (!$r[$y][$x]) { $x++; continue; }
                $s = $x;
                while ($x < $n && $r[$y][$x]) { $x++; }
                $pdf->flaeche($pl['x'] + $s * $m, $pl['y'] + $y * $m, ($x - $s) * $m + 0.03, $m + 0.03, [0, 0, 0]);
            }
        }

        $a = self::adressLage($f);
        if ($a['passt']) {
            $hell = 0.0;
            if (function_exists('imagecreatefromjpeg') && ($roh = @imagecreatefromjpeg(self::datei($slug)))) { $hell = self::hellUnten($roh, $f); imagedestroy($roh); }
            $farbe = $hell > 0.55 ? [0.15, 0.12, 0.07] : [0.97, 0.9, 0.68];
            $pdf->text($a['rechts'] * $k, $a['grund'] * $k, self::kurz($p), $a['gr'] * $k * 0.95, true, 'rechts', $farbe);
        }
        return $pdf->fertig();
    }
}
