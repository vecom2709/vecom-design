<?php
declare(strict_types=1);

require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Db.php';

/* ==========================================================================
   MkKarussell.php — Karussell-Folien als Bilder, damit Karussells auf
   Instagram und Facebook von selbst rausgehen (01.10.2026, Uwe: „mach so
   weit du kannst automatisch“).

   Bisher stand bei jedem Karussell „lädst du mit dem Paket hoch“ — der
   Autopilot plant aber jede Woche eins für Instagram ein, und das wäre zur
   Sendezeit gescheitert. Die Folien entstehen jetzt hier aus dem Text
   (Titel groß, Satz darunter, Zähler, Gold wie die Marke), 1080 × 1350 wie
   Instagram-Hochformat.

   Meta holt jedes Bild über m.php ab. Diese Adressen brauchen keinen
   Datenbankeintrag: Der Schlüssel ist ein HMAC über die Inhalts-ID mit dem
   Server-Geheimnis — nicht zu erraten, und er gilt nur, solange der Inhalt
   freigegeben oder veröffentlicht ist (prüft m.php).
   ========================================================================== */
final class MkKarussell
{
    public const B = 1080;
    public const H = 1350;
    public const MAX = 10;               // Instagram nimmt höchstens zehn Elemente

    /** Folien aus den Feldern: zuerst der Aufhänger als Titelbild, dann die Folien. @return list<array{titel:string, text:string}> */
    public static function folien(array $x): array
    {
        $f = $x['f'] ?? (json_decode((string) ($x['felder'] ?? ''), true) ?: []);
        $liste = [];
        $hook = trim((string) ($f['hook'] ?? ''));
        if ($hook !== '') { $liste[] = ['titel' => $hook, 'text' => '']; }
        foreach ((array) ($f['folien'] ?? []) as $fo) {
            $t = trim((string) ($fo['titel'] ?? '')); $s = trim((string) ($fo['text'] ?? ''));
            if ($t !== '' || $s !== '') { $liste[] = ['titel' => $t, 'text' => $s]; }
        }
        return array_slice($liste, 0, self::MAX);
    }

    /** Ein Karussell braucht mindestens zwei Bilder. */
    public static function moeglich(array $x): bool
    {
        return ($x['format'] ?? '') === 'karussell' && count(self::folien($x)) >= 2 && self::schluessel((int) $x['id']) !== '';
    }

    public static function schluessel(int $id): string
    {
        $g = (string) Config::get('hosting_geheim', '');
        return $g === '' || $id <= 0 ? '' : substr(hash_hmac('sha256', 'karussell:' . $id, $g), 0, 32);
    }

    public static function url(array $x, int $n): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/m.php?k=' . self::schluessel((int) $x['id']) . '&i=' . (int) $x['id'] . '&n=' . $n;
    }

    /** Folie n als JPEG (zwischengespeichert, solange sich der Text nicht ändert). */
    public static function bild(array $x, int $n): ?string
    {
        $folien = self::folien($x);
        if (!isset($folien[$n]) || !function_exists('imagecreatetruecolor')) { return null; }
        $ordner = dirname(__DIR__) . '/uploads/marketing';
        $datei = $ordner . '/karussell-' . (int) $x['id'] . '-' . $n . '-' . substr(sha1(json_encode([$folien, $x['sprache'] ?? ''])), 0, 12) . '.jpg';
        if (is_file($datei)) { return (string) file_get_contents($datei); }
        $jpg = self::zeichnen($folien[$n], $n, count($folien), (string) ($x['sprache'] ?? 'it'));
        if (!is_dir($ordner)) { @mkdir($ordner, 0755, true); }
        /* Wie MkMedium::ordner: der Ordner ist von außen gesperrt, Meta holt nur über m.php. */
        foreach ([dirname($ordner), $ordner] as $o) { if (is_dir($o) && !is_file($o . '/.htaccess')) { @file_put_contents($o . '/.htaccess', "Require all denied\nOptions -Indexes -ExecCGI\nphp_flag engine off\n"); } }
        @file_put_contents($datei, $jpg);
        return $jpg;
    }

    private static function zeichnen(array $fo, int $n, int $von, string $sprache): string
    {
        $b = self::B; $h = self::H;
        $im = imagecreatetruecolor($b, $h);
        $grund = imagecolorallocate($im, 11, 10, 9);
        $flaeche = imagecolorallocate($im, 21, 19, 15);
        $gold = imagecolorallocate($im, 241, 211, 139);
        $hell = imagecolorallocate($im, 246, 241, 231);
        $leise = imagecolorallocate($im, 214, 207, 196);   // lesbar groß und hell (Uwe, 03.09.2026)
        imagefill($im, 0, 0, $grund);
        imagefilledrectangle($im, 60, 60, $b - 60, $h - 60, $flaeche);
        imagefilledrectangle($im, 60, 60, $b - 60, 72, $gold);
        $fett = dirname(__DIR__) . '/schrift/montserrat-700.ttf';
        $mittel = dirname(__DIR__) . '/schrift/montserrat-500.ttf';
        imagettftext($im, 24, 0, 116, 160, $gold, $fett, 'VECOM DESIGN');
        $zaehler = ($n + 1) . ' / ' . $von;
        $bx = imagettfbbox(24, 0, $mittel, $zaehler);
        imagettftext($im, 24, 0, $b - 116 - ($bx[2] - $bx[0]), 160, $leise, $mittel, $zaehler);
        $breite = $b - 232;
        $titel = (string) $fo['titel']; $text = (string) $fo['text'];
        $titelGr = $text === '' ? [84, 76, 68, 60, 54] : [74, 66, 58, 52, 46];
        foreach ($titelGr as $tg) { $tz = self::umbrechen($titel, $fett, $tg, $breite); if (count($tz) <= ($text === '' ? 6 : 4)) { break; } }
        $textGr = 34; $sz = [];
        if ($text !== '') { foreach ([42, 38, 34, 30] as $textGr) { $sz = self::umbrechen($text, $mittel, $textGr, $breite); if (count($sz) <= 9) { break; } } }
        $tzh = (int) round($tg * 1.22); $szh = (int) round($textGr * 1.5);
        $hoehe = count($tz) * $tzh + ($sz ? 50 + count($sz) * $szh : 0);
        $y = (int) max(260, ($h - $hoehe) / 2) + $tg;
        foreach ($tz as $z) { imagettftext($im, $tg, 0, 116, $y, $hell, $fett, $z); $y += $tzh; }
        if ($sz) {
            $y += 10;
            imagefilledrectangle($im, 116, $y - 30, 196, $y - 24, $gold);
            $y += 30;
            foreach ($sz as $z) { imagettftext($im, $textGr, 0, 116, $y, $leise, $mittel, $z); $y += $szh; }
        }
        $unten = $n === $von - 1
            ? ($sprache === 'de' ? 'Link in der Bio · vecom-design.it' : ($sprache === 'en' ? 'Link in bio · vecom-design.it' : 'Link in bio · vecom-design.it'))
            : ($sprache === 'de' ? 'Weiter wischen »' : ($sprache === 'en' ? 'Swipe »' : 'Scorri »'));
        imagettftext($im, 26, 0, 116, $h - 120, $n === $von - 1 ? $gold : $leise, $mittel, $unten);
        ob_start(); imagejpeg($im, null, 90); $jpg = (string) ob_get_clean();
        imagedestroy($im);
        return $jpg;
    }

    /** @return list<string> */
    private static function umbrechen(string $s, string $schrift, int $gr, int $breite): array
    {
        $zeilen = []; $z = '';
        foreach (preg_split('~\s+~u', trim($s)) ?: [] as $w) {
            $probe = $z === '' ? $w : $z . ' ' . $w;
            $box = imagettfbbox($gr, 0, $schrift, $probe);
            if ($z !== '' && ($box[2] - $box[0]) > $breite) { $zeilen[] = $z; $z = $w; } else { $z = $probe; }
        }
        if ($z !== '') { $zeilen[] = $z; }
        return $zeilen;
    }
}
