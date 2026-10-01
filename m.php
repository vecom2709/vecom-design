<?php
declare(strict_types=1);
/* ==========================================================================
   m.php — Bild oder Video eines freigegebenen Inhalts für Meta und Telegram
   (Marketing-Studio Schritt 4, 01.10.2026). Facebook, Instagram und Telegram
   holen die Datei beim Posten über diese Adresse ab; deshalb öffentlich, aber
   nur mit dem langen Zufallsschlüssel des Mediums, nur für GEWÄHLTE Medien
   und nur, solange der Inhalt freigegeben oder veröffentlicht ist. Nicht im
   Index. „&f=jpg“ liefert Bilder als JPEG (Instagram nimmt nur JPEG).
   ========================================================================== */
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
// Die Kette (app/pruefung/kette.php) bindet diese Seite mit ihrer eigenen Konfiguration ein — dort steht Config schon.
if (!is_file(__DIR__ . '/app/config.local.php') && !class_exists('Config', false)) { http_response_code(503); exit; }
foreach (['Config', 'Db', 'Events'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
$t = (string) ($_GET['t'] ?? '');
if (!preg_match('~^[a-f0-9]{32}$~', $t)) { http_response_code(404); exit; }
try {
    $m = Db::one("SELECT m.* FROM mk_medien m JOIN mk_inhalte i ON i.id = m.inhalt_id
                   WHERE m.token = ? AND m.status = 'gewaehlt' AND i.status IN ('freigegeben', 'veroeffentlicht')", [$t]);
} catch (Throwable $e) { $m = null; }
if (!$m) { http_response_code(404); exit; }
$ordner = __DIR__ . '/app/uploads/marketing';
$pfad = $ordner . '/' . basename((string) $m['datei']);
if (!is_file($pfad)) { http_response_code(404); exit; }
$mime = (string) $m['mime'];
if (($_GET['f'] ?? '') === 'jpg' && in_array($mime, ['image/png', 'image/webp'], true) && function_exists('imagejpeg')) {
    $jpg = $ordner . '/' . basename((string) $m['datei'], '.bin') . '-jpg.bin';
    if (!is_file($jpg)) {
        $bild = $mime === 'image/png' ? @imagecreatefrompng($pfad) : @imagecreatefromwebp($pfad);
        if ($bild !== false) {
            $weiss = imagecreatetruecolor(imagesx($bild), imagesy($bild));
            imagefill($weiss, 0, 0, imagecolorallocate($weiss, 255, 255, 255));
            imagecopy($weiss, $bild, 0, 0, 0, 0, imagesx($bild), imagesy($bild));
            imagejpeg($weiss, $jpg, 90);
            imagedestroy($bild); imagedestroy($weiss);
        }
    }
    if (is_file($jpg)) { $pfad = $jpg; $mime = 'image/jpeg'; }
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($pfad));
header('Cache-Control: public, max-age=86400');
readfile($pfad);
