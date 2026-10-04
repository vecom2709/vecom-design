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
require_once __DIR__ . '/PartnerFlyer.php';
require_once __DIR__ . '/Texte.php';

final class WmDruck
{
    /** Formate mit Vorlage. Schlüssel = wm_produkte.vorlage. */
    public const FORMATE = [
        'flyer_a6' => 'Flyer A6 (Vorlage)',
        'flyer_a5' => 'Flyer A5 (Vorlage)',
        'flyer_branche' => 'Branchen-Flyer A5 (Vorlage)',
        'aufkleber_50' => 'Aufkleber rund Ø 5 cm (Vorlage)',
        'rollup_85' => 'Roll-up 85 × 200 cm (Vorlage)',
        'tasse_11' => 'Tasse 11 oz rundum (Vorlage)',
    ];

    /**
     * Großformat (Roll-up, 04.10.2026): Die Vorlage hat 3425 × 8937 Pixel — in GD wären das über
     * 120 MB Speicher je Bild. Darum wird sie nie geladen: Das JPEG geht unverändert ins PDF, der Code
     * als Vektor darüber, der Link des Partners als kleines eigenes Bild auf seiner Platte. Die
     * Vorschau nimmt eine kleine Fassung derselben Vorlage (-klein.jpg).
     */
    public static function gross(string $fmt): bool
    {
        return !empty(self::layout($fmt)['gross']);
    }

    /** Nur eine Seite (Aufkleber): der Code steht vorn, die Druckdatei hat eine Seite. */
    public static function einseitig(string $fmt): bool
    {
        return !empty(self::layout($fmt)['einseitig']);
    }

    /**
     * Branchen-Flyer (04.10.2026, Uwe: „die Flyer einzeln in DE/IT/EN … zusätzlich ins
     * Marketing Center“): Vorderseite = der Branchen-Flyer aus app/flyer (PartnerFlyer,
     * gleiche Datei wie im Dashboard, 300 dpi mit 3 mm Beschnitt), Rückseite = die
     * Rückseite des Flyers A5 im schwarz-goldenen Stil — sie trägt Name, Link, Kontakt.
     * Der „Stil“ ist hier die Branche (z. B. pro-restaurant).
     */
    public const RUECKSEITE = ['flyer_branche' => ['flyer_a5', 'a']];

    /**
     * Branchenmotiv für jeden Flyer (04.10.2026, Uwe: „bei den A5-Flyern sind viele Branchen
     * zur Auswahl, so soll es auch bei den anderen Flyern sein“): Flyer A5 und A6 nehmen als
     * „Stil“ auch eine Branche. Vorderseite = das Branchenmotiv (für A6 verkleinert), Rückseite
     * = die eigene des Formats im Stil a — mit Name, Link, Kontakt und Code.
     */
    public const BRANCHE_MOEGLICH = ['flyer_a5', 'flyer_a6'];

    /** Ist $stil hier ein Branchenmotiv (und nicht a, b, c …)? */
    public static function branche(string $fmt, string $stil): bool
    {
        return (isset(self::RUECKSEITE[$fmt]) || in_array($fmt, self::BRANCHE_MOEGLICH, true))
            && PartnerFlyer::gibt($stil) && PartnerFlyer::sprachen($stil) !== [];
    }

    /** Rückseite zu einem Branchenmotiv: [Format, Stil]. */
    private static function rueckseite(string $fmt): array
    {
        return self::RUECKSEITE[$fmt] ?? [$fmt, 'a'];
    }

    /** Skalierung „füllen“ von Quelle auf Ziel (Pixel): [Faktor, Versatz x, Versatz y]. Gleich groß: genau 1. */
    private static function deckung(float $sw, float $sh, float $tw, float $th): array
    {
        $f = max($tw / $sw, $th / $sh);
        if (abs($f - 1) < 0.002) { return [1.0, 0.0, 0.0]; }
        return [$f, ($sw * $f - $tw) / 2, ($sh * $f - $th) / 2];
    }

    /** @var array<string, array> */
    private static array $layouts = [];

    // ---- Überschrift (04.10.2026, Marketingcenter Schritt 4) ------------------------------
    /*  Flyer A5/A6 in allgemeiner Gestaltung und Roll-up: Die Vorlage hat keine Überschrift mehr.
        Der Partner wählt eine aus Texte::WM_TITEL; hier wird sie gesetzt — Lage, Schrift, Größe
        und Farben aus layout.php (gen.py), also gesperrt. Ein Entwurf bringt seine Wahl als
        $p['_wm_titel'] mit (wie den Kanal, Werbemittel::mitKanal). Branchenmotive haben ihre
        eigene Überschrift im Bild; dort gibt es keine Wahl. */

    /** Hat diese Gestaltung eine wählbare Überschrift? */
    public static function hatTitel(string $fmt, string $stil): bool
    {
        return !isset(self::RUECKSEITE[$fmt]) && !self::branche($fmt, $stil) && isset(self::layout($fmt)['stile'][$stil]['titel']);
    }

    /** Schlüssel der Überschrift: der gewählte, wenn freigegeben — sonst der erste (die bisherige). */
    public static function titel(string $schluessel): string
    {
        return isset(Texte::WM_TITEL[$schluessel]) ? $schluessel : (string) array_key_first(Texte::WM_TITEL);
    }

    /** Die zwei Zeilen der Überschrift in der Sprache des Werbemittels. @return array{string,string} */
    public static function titelZeilen(string $schluessel, string $sprache): array
    {
        $t = Texte::WM_TITEL[self::titel($schluessel)];
        return $t[$sprache] ?? $t['it'];
    }

    /**
     * Überschrift auf ein Bild der Vorderseite (volle Vorlage oder kleine Fassung — $k sagt, wie
     * viele Pixel eine Layout-Einheit hat). Beide Zeilen gleich groß: Ist eine zu breit, werden
     * beide kleiner, nie abgeschnitten. $oy: Versatz in Layout-Einheiten (Streifen des Roll-ups).
     */
    private static function titelMalen(\GdImage $im, array $T, array $zeilen, float $k, float $oy = 0.0): void
    {
        $datei = PartnerKarten::schrift((int) $T['font']);
        if (!is_file($datei)) { return; }
        $pt = $T['size'] * $k * 72 / 96;
        $max = $T['max'] * $k;
        $breite = static function (string $z, float $pt) use ($datei): float { $bb = imagettfbbox($pt, 0, $datei, $z); return (float) abs($bb[2] - $bb[0]); };
        for ($i = 0; $i < 20 && max($breite($zeilen[0], $pt), $breite($zeilen[1], $pt)) > $max; $i++) { $pt *= 0.95; }
        imagealphablending($im, true);
        foreach ([[$zeilen[0], $T['y1'], $T['farbe1']], [$zeilen[1], $T['y2'], $T['farbe2']]] as [$z, $y, $farbe]) {
            [$r, $g, $b] = sscanf((string) $farbe, '#%02x%02x%02x');
            $bb = imagettfbbox($pt, 0, $datei, $z);
            $x = $T['x'] * $k - ($bb[2] + $bb[0]) / 2;               // mittig wie text-anchor="middle"
            imagettftext($im, $pt, 0, (int) round($x), (int) round(($y - $oy) * $k), imagecolorallocate($im, $r, $g, $b), $datei, $z);
        }
    }

    /** Überschrift auf ein Vorderseitenbild dieses Formats (Kachel, Vorschau): Maßstab aus der Bildbreite. */
    public static function titelAuf(\GdImage $im, string $fmt, string $stil, string $sprache, string $schluessel): void
    {
        if (!self::hatTitel($fmt, $stil)) { return; }
        $lay = self::layout($fmt);
        self::titelMalen($im, $lay['stile'][$stil]['titel'], self::titelZeilen($schluessel, $sprache), imagesx($im) / (($lay['b'] + 2 * $lay['beschnitt']) * 10));
    }

    /** Hinter dem QR-Code: der Partnerlink mit Kanal (Flyer zählen als „flyer“ in der Auswertung).
        Ein Entwurf aus dem Marketing Center bringt seinen eigenen Kanal mit (wm-241, Werbemittel::mitKanal). */
    public static function qrLink(array $p, string $fmt): string
    {
        return PartnerWerbung::link($p, (string) ($p['_wm_kanal'] ?? (str_starts_with($fmt, 'flyer') ? 'flyer' : 'qr')));
    }

    public static function layout(string $fmt): array
    {
        if (!isset(self::FORMATE[$fmt])) { return []; }
        if (isset(self::RUECKSEITE[$fmt])) { return self::layout(self::RUECKSEITE[$fmt][0]); }
        return self::$layouts[$fmt] ??= (array) require dirname(__DIR__) . '/druckvorlagen/' . $fmt . '/layout.php';
    }

    public static function gibt(string $fmt, string $stil): bool
    {
        if (isset(self::RUECKSEITE[$fmt]) || self::branche($fmt, $stil)) {
            [$rf, $rs] = self::rueckseite($fmt);
            return self::branche($fmt, $stil) && self::gibt($rf, $rs);
        }
        return isset(self::FORMATE[$fmt], self::layout($fmt)['stile'][$stil]) && is_file(self::datei($fmt, $stil, 'vorn', 'de'));
    }

    /** Vorderseite als Datei (Kachelbild der Auswahl): Branchenmotiv oder Vorlage; Großformat in der kleinen Fassung. */
    public static function vornDatei(string $fmt, string $stil, string $sprache): string
    {
        if (!self::gibt($fmt, $stil)) { return ''; }
        if (self::branche($fmt, $stil)) { return PartnerFlyer::datei($stil, $sprache); }
        $d = self::datei($fmt, $stil, 'vorn', $sprache);
        return self::gross($fmt) ? (string) preg_replace('~\.jpg$~', '-klein.jpg', $d) : $d;
    }

    private static function datei(string $fmt, string $stil, string $seite, string $sprache): string
    {
        $sprache = in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it';
        return dirname(__DIR__) . '/druckvorlagen/' . $fmt . '/' . $stil . '-' . ($seite === 'hinten' ? 'hinten' : 'vorn') . '-' . $sprache . '.jpg';
    }

    /** Eine Seite als GD-Bild in voller Größe (mit Beschnitt). $mitQr: false, wenn der Code im PDF als Vektor kommt. */
    private static function leinwand(array $p, string $fmt, string $stil, string $seite, string $sprache, string $kontakt, bool $mitQr = true): ?\GdImage
    {
        if (self::branche($fmt, $stil)) {
            if ($seite === 'hinten') { [$rf, $rs] = self::rueckseite($fmt); return self::leinwand($p, $rf, $rs, 'hinten', $sprache, $kontakt, $mitQr); }
            $im = @imagecreatefromjpeg(PartnerFlyer::datei($stil, $sprache));
            if (!$im) { return null; }
            // Flyer im Originalstil: der Link des Partners steht dort, wo im Original www.vecom-design.it stand.
            $fu = PartnerFlyer::liste()[$stil]['u'] ?? null;
            $schrift = dirname(__DIR__) . '/schrift/archivo-semibold.ttf';
            if ($fu && is_file($schrift)) { PartnerFlyer::linkMalen($im, $fu, PartnerFlyer::kurz($p), imagesx($im) / PartnerFlyer::liste()[$stil]['b'], $schrift); }
            // Anderes Format (A6): auf dessen Fläche mit Beschnitt füllen — gleiche Pixeldichte, Mitte bleibt Mitte.
            $lay = self::layout($fmt); $a5 = self::layout('flyer_a5');
            $k = imagesx($im) / (($a5['b'] + 2 * $a5['beschnitt']) * 10);
            $tw = (int) round(($lay['b'] + 2 * $lay['beschnitt']) * 10 * $k); $th = (int) round(($lay['h'] + 2 * $lay['beschnitt']) * 10 * $k);
            [$f, $ox, $oy] = self::deckung(imagesx($im), imagesy($im), $tw, $th);
            if ($f !== 1.0) {
                $neu = imagecreatetruecolor($tw, $th);
                imagecopyresampled($neu, $im, 0, 0, (int) round($ox / $f), (int) round($oy / $f), $tw, $th, (int) round($tw / $f), (int) round($th / $f));
                imagedestroy($im); $im = $neu;
            }
            if ($mitQr) { self::qrMalen($im, self::qrVorn($stil, $im, $fmt), $p, $fmt); }
            return $im;
        }
        $im = @imagecreatefromjpeg(self::datei($fmt, $stil, $seite, $sprache));
        if (!$im) { return null; }
        $lay = self::layout($fmt);
        if ($seite === 'vorn') { self::titelAuf($im, $fmt, $stil, $sprache, (string) ($p['_wm_titel'] ?? '')); }
        if ($seite !== (empty($lay['einseitig']) ? 'hinten' : 'vorn')) { return $im; }
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
        if ($mitQr) { self::qrMalen($im, ['qr' => $L['qr'], 'k' => $k], $p, $fmt); }
        return $im;
    }

    /**
     * QR-Fläche der Branchen-Vorderseite in 1/10 mm (wie layout.php): mittig in der weißen
     * Fläche, 84 % ihrer Breite — dasselbe Maß wie im Dashboard (PartnerFlyer::platz).
     * @return array{qr:array{float,float,float},k:float}
     */
    public static function qrVorn(string $slug, ?\GdImage $im = null, string $fmt = 'flyer_a5'): array
    {
        $f = PartnerFlyer::liste()[$slug];
        $a5 = self::layout('flyer_a5');
        $lay = self::layout($fmt) ?: $a5;
        $k = $f['b'] / (($a5['b'] + 2 * $a5['beschnitt']) * 10);          // Pixel je 1/10 mm (Branchenbild)
        $tw = ($lay['b'] + 2 * $lay['beschnitt']) * 10 * $k; $th = ($lay['h'] + 2 * $lay['beschnitt']) * 10 * $k;
        [$sk, $ox, $oy] = self::deckung((float) $f['b'], (float) $f['h'], $tw, $th);   // A6: dieselbe Verkleinerung wie das Bild
        [$x, $y, $b, $h] = $f['q'];
        $s = min($b, $h) * 0.84;
        return ['qr' => [(($x + ($b - $s) / 2) * $sk - $ox) / $k, (($y + ($h - $s) / 2) * $sk - $oy) / $k, $s * $sk / $k],
                'k' => $im ? imagesx($im) / (($lay['b'] + 2 * $lay['beschnitt']) * 10) : $k];
    }

    /** Den echten Code ins Bild (Vorschau, Fassungen ohne Vektor). $L: ['qr' => [x, y, s] in 1/10 mm, 'k' => Pixel je 1/10 mm]. */
    private static function qrMalen(\GdImage $im, array $L, array $p, string $fmt): void
    {
        $k = $L['k'];
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

    private static function jpeg(\GdImage $im, int $q = 90): string
    {
        ob_start(); imagejpeg($im, null, $q); return (string) ob_get_clean();
    }

    /** Druckdatei: Vorder- und Rückseite mit Beschnitt (TrimBox/BleedBox), QR-Code als Vektor. */
    /**
     * $beschnitt (mm): null = wie die Vorlage (3 mm). Kleiner = Fassung für eine Druckerei, die weniger
     * verlangt (Flyeralarm: 1 mm je Seite, Datenblatt flyer_a6_mass_uvl.pdf, geprüft 04.10.2026) — der
     * Überschuss wird abgeschnitten, nicht gestaucht; Code und Text bleiben, wo sie waren.
     */
    public static function pdf(array $p, string $fmt, string $stil, string $sprache, string $kontakt = 'email', ?float $beschnitt = null): string
    {
        if (!self::gibt($fmt, $stil)) { return ''; }
        if (self::gross($fmt)) { return self::grossPdf($p, $fmt, $stil, $sprache); }
        $lay = self::layout($fmt);
        $weg = $beschnitt === null ? 0.0 : max(0.0, (float) $lay['beschnitt'] - $beschnitt);   // mm je Seite
        $ein = self::einseitig($fmt);
        $br = self::branche($fmt, $stil);
        $v = self::leinwand($p, $fmt, $stil, 'vorn', $sprache, $kontakt, !$br && !$ein);
        $h = $ein ? $v : self::leinwand($p, $fmt, $stil, 'hinten', $sprache, $kontakt, false);
        if (!$v || !$h) { return ''; }
        $bw = $lay['b'] + 2 * $lay['beschnitt']; $bh = $lay['h'] + 2 * $lay['beschnitt'];
        if ($weg > 0) {
            foreach ([&$v, &$h] as &$bild) {
                $px = (int) round(imagesx($bild) * $weg / $bw);
                $bild = imagecrop($bild, ['x' => $px, 'y' => $px, 'width' => imagesx($bild) - 2 * $px, 'height' => imagesy($bild) - 2 * $px]) ?: $bild;
            }
            unset($bild);
        }
        $sb = (float) $lay['beschnitt'] - $weg;          // Beschnitt dieser Fassung
        $bw0 = $bw; $bh0 = $bh;                           // Maße der Vorlage (für die Code-Lage)
        $bw -= 2 * $weg; $bh -= 2 * $weg;
        $mm = 72 / 25.4;
        $pdf = new KartenPdf();
        if (!empty($p['id'])) { require_once __DIR__ . '/PartnerSchutz.php'; $pdf->kennung = PartnerSchutz::kennung($p); }
        $iv = $pdf->bild(self::jpeg($v, 92), imagesx($v), imagesy($v));
        $ih = $ein ? $iv : $pdf->bild(self::jpeg($h, 92), imagesx($h), imagesy($h));
        [$n, $raster] = PartnerKarten::raster(self::qrLink($p, $fmt));
        // Branchen-Flyer: Code auch vorn — dort im PDF als Vektor, nicht als Pixel.
        // Lage der Codes: in Koordinaten der Vorlage, um den abgeschnittenen Rand verschoben.
        $vornQr = $br ? PartnerKarten::qrVektor(self::qrVorn($stil, null, $fmt), $n, $raster, -$weg, (float) $bh0 - $weg)
            : ($ein ? PartnerKarten::qrVektor($lay['stile'][$stil], $n, $raster, -$weg, (float) $bh0 - $weg) : '');
        $pdf->seite($bw * $mm, $bh * $mm, sprintf("q %.3F 0 0 %.3F 0 0 cm /%s Do Q\n", $bw * $mm, $bh * $mm, $iv) . $vornQr, $sb * $mm);
        if ($ein) { return $pdf->fertig(); }
        $pdf->seite($bw * $mm, $bh * $mm, sprintf("q %.3F 0 0 %.3F 0 0 cm /%s Do Q\n", $bw * $mm, $bh * $mm, $ih)
            . PartnerKarten::qrVektor($lay['stile'][$br ? self::rueckseite($fmt)[1] : $stil], $n, $raster, -$weg, (float) $bh0 - $weg), $sb * $mm);
        return $pdf->fertig();
    }

    /** Link des Partners als kleines Bild in der Größe seiner Platte (Pixel der Vorlage × $k). */
    private static function linkBild(array $p, array $L, float $k): ?\GdImage
    {
        $schrift = PartnerKarten::schrift(700);
        [$x, $y, $b, $h] = $L['link']['platte'];
        $w = max(1, (int) round($b * $k)); $hh = max(1, (int) round($h * $k));
        $im = imagecreatetruecolor($w, $hh);
        [$r, $g, $bl] = sscanf((string) $L['link']['grund'], '#%02x%02x%02x');
        imagefill($im, 0, 0, imagecolorallocate($im, $r, $g, $bl));
        if (!is_file($schrift)) { return $im; }
        $text = PartnerFlyer::kurz($p);
        $pt = $L['link']['size'] * $k * 0.75;
        for ($i = 0; $i < 16; $i++) {
            $bb = imagettfbbox($pt, 0, $schrift, $text);
            if (abs($bb[2] - $bb[0]) <= $L['link']['max'] * $k) { break; }
            $pt *= 0.93;
        }
        $bb = imagettfbbox($pt, 0, $schrift, $text);
        [$r, $g, $bl] = sscanf((string) $L['link']['farbe'], '#%02x%02x%02x');
        imagettftext($im, $pt, 0, (int) round(($w - abs($bb[2] - $bb[0])) / 2), (int) round($hh / 2 + abs($bb[7] - $bb[1]) / 2), imagecolorallocate($im, $r, $g, $bl), $schrift, $text);
        return $im;
    }

    /** Druck-PDF Großformat: Vorlage unverändert, Code als Vektor, Link-Platte als kleines Bild. */
    private static function grossPdf(array $p, string $fmt, string $stil, string $sprache): string
    {
        $lay = self::layout($fmt);
        $L = $lay['stile'][$stil];
        $jpeg = (string) @file_get_contents(self::datei($fmt, $stil, 'vorn', $sprache));
        $mass = $jpeg !== '' ? @getimagesizefromstring($jpeg) : false;
        if (!$mass) { return ''; }
        $bw = $lay['b'] + 2 * $lay['beschnitt']; $bh = $lay['h'] + 2 * $lay['beschnitt'];
        $mm = 72 / 25.4;
        $k = $mass[0] / ($bw * 10);                                  // Pixel je 1/10 mm
        $link = self::linkBild($p, $L, $k);
        if (!$link) { return ''; }
        $pdf = new KartenPdf();
        if (!empty($p['id'])) { require_once __DIR__ . '/PartnerSchutz.php'; $pdf->kennung = PartnerSchutz::kennung($p); }
        $iv = $pdf->bild($jpeg, $mass[0], $mass[1]);
        $il = $pdf->bild(self::jpeg($link, 95), imagesx($link), imagesy($link));
        [$x, $y, $b, $h] = $L['link']['platte'];
        [$n, $raster] = PartnerKarten::raster(self::qrLink($p, $fmt));
        $inhalt = sprintf("q %.3F 0 0 %.3F 0 0 cm /%s Do Q\n", $bw * $mm, $bh * $mm, $iv);
        if (isset($L['titel']['grund'])) {
            // Überschrift: Streifen des Hintergrunds (aus derselben Darstellung geschnitten), darauf die Wahl.
            $streifen = @imagecreatefromjpeg(dirname(self::datei($fmt, $stil, 'vorn', $sprache)) . '/' . $stil . '-titelgrund.jpg');
            if (!$streifen) { return ''; }
            [$gx, $gy, $gb, $gh] = $L['titel']['grund'];
            self::titelMalen($streifen, $L['titel'], self::titelZeilen((string) ($p['_wm_titel'] ?? ''), $sprache), imagesx($streifen) / $gb, (float) $gy);
            $it = $pdf->bild(self::jpeg($streifen, 92), imagesx($streifen), imagesy($streifen));
            $inhalt .= sprintf("q %.3F 0 0 %.3F %.3F %.3F cm /%s Do Q\n", $gb / 10 * $mm, $gh / 10 * $mm, $gx / 10 * $mm, ($bh - ($gy + $gh) / 10) * $mm, $it);
        }
        $inhalt .= sprintf("q %.3F 0 0 %.3F %.3F %.3F cm /%s Do Q\n", $b / 10 * $mm, $h / 10 * $mm, $x / 10 * $mm, ($bh - ($y + $h) / 10) * $mm, $il)
            . PartnerKarten::qrVektor($L, $n, $raster, 0.0, (float) $bh);
        $pdf->seite($bw * $mm, $bh * $mm, $inhalt, $lay['beschnitt'] * $mm);
        return $pdf->fertig();
    }

    /** Vorschau Großformat: kleine Vorlage, Code und Link darauf, nur der sichtbare Teil (ohne Kassette). */
    private static function grossVorschau(array $p, string $fmt, string $stil, string $sprache, int $hoehe): string
    {
        $lay = self::layout($fmt);
        $L = $lay['stile'][$stil];
        $klein = preg_replace('~\.jpg$~', '-klein.jpg', self::datei($fmt, $stil, 'vorn', $sprache));
        $im = @imagecreatefromjpeg((string) $klein);
        if (!$im) { return ''; }
        $bw = $lay['b'] + 2 * $lay['beschnitt'];
        $k = imagesx($im) / ($bw * 10);
        self::titelAuf($im, $fmt, $stil, $sprache, (string) ($p['_wm_titel'] ?? ''));
        self::qrMalen($im, ['qr' => $L['qr'], 'k' => $k], $p, $fmt);
        $link = self::linkBild($p, $L, $k);
        [$x, $y] = $L['link']['platte'];
        if ($link) { imagecopy($im, $link, (int) round($x * $k), (int) round($y * $k), 0, 0, imagesx($link), imagesy($link)); }
        // sichtbar: unter dem Beschnitt oben bis 200 cm darunter
        $r = (int) round($lay['beschnitt'] * 10 * $k);
        $sh = (int) round(($lay['sichtbar'] ?? 2000) * 10 * $k);
        $zu = imagecrop($im, ['x' => $r, 'y' => $r, 'width' => imagesx($im) - 2 * $r, 'height' => min($sh, imagesy($im) - $r)]) ?: $im;
        $aus = imagescale($zu, (int) round(imagesx($zu) * $hoehe / imagesy($zu)), $hoehe, IMG_BICUBIC) ?: $zu;
        return self::jpeg($aus, 86);
    }

    /**
     * Das ganze Bild einer einseitigen Vorlage mit Partnerdaten und Code (Raster) als JPEG — die Datei, die eine
     * Druckerei ohne PDF bekommt (Printful-Tasse: genau die Druckfläche, kein Beschnitt).
     */
    public static function bild(array $p, string $fmt, string $stil, string $sprache, string $kontakt = 'email'): string
    {
        if (!self::gibt($fmt, $stil) || !self::einseitig($fmt) || self::gross($fmt)) { return ''; }
        $im = self::leinwand($p, $fmt, $stil, 'vorn', $sprache, $kontakt);
        return $im ? self::jpeg($im, 95) : '';
    }

    /** Vorder- und Rückseite beschnitten nebeneinander, klein (Partnerbereich). */
    public static function vorschau(array $p, string $fmt, string $stil, string $sprache, string $kontakt = 'email', int $hoehe = 360): string
    {
        if (!self::gibt($fmt, $stil)) { return ''; }
        if (self::gross($fmt)) { return self::grossVorschau($p, $fmt, $stil, $sprache, max($hoehe, 420)); }
        $lay = self::layout($fmt);
        $teile = [];
        foreach (self::einseitig($fmt) ? ['vorn'] : ['vorn', 'hinten'] as $s) {
            $im = self::leinwand($p, $fmt, $stil, $s, $sprache, $kontakt);
            if (!$im) { return ''; }
            $b = (int) round(imagesx($im) * $lay['beschnitt'] / ($lay['b'] + 2 * $lay['beschnitt']));
            $zu = imagecrop($im, ['x' => $b, 'y' => $b, 'width' => imagesx($im) - 2 * $b, 'height' => imagesy($im) - 2 * $b]) ?: $im;
            $teile[] = imagescale($zu, (int) round(imagesx($zu) * $hoehe / imagesy($zu)), $hoehe, IMG_BICUBIC) ?: $zu;
        }
        if (count($teile) === 1 && !str_starts_with($fmt, 'aufkleber')) { return self::jpeg($teile[0], 86); }   // Tasse: das Rundum-Bild flach
        if (count($teile) === 1) {
            // Aufkleber: rund zeigen, wie er geschnitten wird — außerhalb des Kreises weiß.
            $t = $teile[0]; $d = imagesx($t);
            $weiss = imagecolorallocate($t, 255, 255, 255);
            for ($y = 0; $y < imagesy($t); $y++) {
                for ($x = 0; $x < $d; $x++) {
                    if ((($x - $d / 2 + 0.5) ** 2 + ($y - $hoehe / 2 + 0.5) ** 2) > ($d / 2) ** 2) { imagesetpixel($t, $x, $y, $weiss); }
                }
            }
            return self::jpeg($t, 86);
        }
        $abstand = (int) round($hoehe * 0.05);
        $aus = imagecreatetruecolor(imagesx($teile[0]) + imagesx($teile[1]) + $abstand, $hoehe);
        imagefill($aus, 0, 0, imagecolorallocate($aus, 255, 255, 255));
        imagecopy($aus, $teile[0], 0, 0, 0, 0, imagesx($teile[0]), $hoehe);
        imagecopy($aus, $teile[1], imagesx($teile[0]) + $abstand, 0, 0, 0, imagesx($teile[1]), $hoehe);
        return self::jpeg($aus, 86);
    }
}
