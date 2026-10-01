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
/* Karussell-Folien (01.10.2026, MkKarussell): Schlüssel = HMAC über die Inhalts-ID, gilt nur für freigegebene/veröffentlichte Karussells. */
if (isset($_GET['k'])) {
    require_once __DIR__ . '/app/src/MkKarussell.php';
    $kk = (string) $_GET['k']; $ki = (int) ($_GET['i'] ?? 0); $kn = (int) ($_GET['n'] ?? -1);
    if (!preg_match('~^[a-f0-9]{32}$~', $kk) || $ki <= 0 || $kn < 0 || !hash_equals(MkKarussell::schluessel($ki), $kk)) { http_response_code(404); return; }
    try { $kx = Db::one("SELECT * FROM mk_inhalte WHERE id = ? AND format = 'karussell' AND status IN ('freigegeben', 'veroeffentlicht')", [$ki]); } catch (Throwable $e) { $kx = null; }
    $kb = $kx ? MkKarussell::bild($kx, $kn) : null;
    if ($kb === null) { http_response_code(404); return; }
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . strlen($kb));
    echo $kb;
    return;   // return statt exit: die Kette bindet m.php per require ein
}
$t = (string) ($_GET['t'] ?? '');
if (!preg_match('~^[a-f0-9]{32}$~', $t)) { http_response_code(404); exit; }
try {
    $m = Db::one("SELECT m.* FROM mk_medien m JOIN mk_inhalte i ON i.id = m.inhalt_id
                   WHERE m.token = ? AND m.status = 'gewaehlt' AND i.status IN ('freigegeben', 'veroeffentlicht')", [$t]);
    /* Marketing-Studio 11: 3D-Galerie für Partner (nach Uwes Ja) und die eigenen 3D-Bestellungen eines Partners. */
    if (!$m) {
        $m = Db::one("SELECT * FROM mk_medien WHERE token = ? AND inhalt_id = 0 AND ((galerie = 1 AND status = 'gewaehlt') OR (partner_id IS NOT NULL AND status <> 'verworfen'))", [$t]);
    }
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
header('Cache-Control: public, max-age=86400');
header('Accept-Ranges: bytes');
/* Marketing-Studio 11: Videos im Partnerportal — Safari spielt MP4 nur mit Byte-Bereichen (206). */
$groesse = (int) filesize($pfad);
if (preg_match('~^bytes=(\d*)-(\d*)$~', (string) ($_SERVER['HTTP_RANGE'] ?? ''), $br) && ($br[1] !== '' || $br[2] !== '')) {
    $von = $br[1] === '' ? max(0, $groesse - (int) $br[2]) : (int) $br[1];
    $bis = ($br[1] !== '' && $br[2] !== '') ? min((int) $br[2], $groesse - 1) : $groesse - 1;
    if ($von > $bis || $von >= $groesse) { http_response_code(416); header('Content-Range: bytes */' . $groesse); exit; }
    http_response_code(206);
    header('Content-Range: bytes ' . $von . '-' . $bis . '/' . $groesse);
    header('Content-Length: ' . ($bis - $von + 1));
    $fh = fopen($pfad, 'rb'); fseek($fh, $von); $rest = $bis - $von + 1;
    while ($rest > 0 && !feof($fh)) { $stueck = fread($fh, min(65536, $rest)); if ($stueck === false) { break; } echo $stueck; $rest -= strlen($stueck); }
    fclose($fh);
    exit;
}
header('Content-Length: ' . $groesse);
readfile($pfad);
