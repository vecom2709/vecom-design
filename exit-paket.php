<?php
declare(strict_types=1);
/* ==========================================================================
   exit-paket.php — der Download des Exit-Pakets für den Kunden
   (AI Office Stufe 5, 07.10.2026).

   Öffentlich, aber nur mit dem 256-Bit-Schlüssel aus der Mail, die Uwe in
   AI Freigaben genehmigt hat, und nur 7 Tage. In der Datenbank steht vom
   Schlüssel nur der SHA-256. Keine Seite, kein Formular — nur die Datei.
   Abgelaufen oder unbekannt: 410 in der Sprache aus ?lang (sonst Italienisch).
   ========================================================================== */
if (!is_file(__DIR__ . '/app/config.local.php') && !class_exists('Config', false)) { http_response_code(503); exit; }
foreach (['Config', 'Db', 'Events', 'ExitPaket'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { http_response_code(405); header('Allow: GET'); exit; }
$exitDatei = null;
try { $exitDatei = ExitPaket::abruf((string) ($_GET['t'] ?? '')); } catch (Throwable $e) { $exitDatei = null; }
if ($exitDatei === null) {
    http_response_code(410);
    $sp = in_array($_GET['lang'] ?? '', ['it', 'de', 'en'], true) ? (string) $_GET['lang'] : 'it';
    [$titel, $satz] = Texte::EXIT['weg'][$sp];
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="' . $sp . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'
        . htmlspecialchars($titel, ENT_QUOTES, 'UTF-8') . ' · Vecom Design</title></head>'
        . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#0d0c0a;color:#f3ece0;font:16px/1.6 system-ui,sans-serif;text-align:center;padding:24px">'
        . '<main style="max-width:520px"><h1 style="font-size:22px;margin:0 0 10px">' . htmlspecialchars($titel, ENT_QUOTES, 'UTF-8') . '</h1><p>'
        . htmlspecialchars($satz, ENT_QUOTES, 'UTF-8') . '</p><p><a style="color:#f5e2a6" href="https://vecom-design.it/?lang=' . $sp . '">vecom-design.it</a></p></main></body></html>';
    exit;
}
header('Content-Type: application/zip');
header('Content-Length: ' . $exitDatei['groesse']);
header('Content-Disposition: attachment; filename="' . preg_replace('~[^A-Za-z0-9._-]~', '_', $exitDatei['name']) . '"');
readfile($exitDatei['pfad']);
