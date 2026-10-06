<?php
declare(strict_types=1);
/* ==========================================================================
   google-lead.php — Google Ads meldet hier ausgefüllte Lead-Formulare
   (28.09.2026, Uwe: Ja zu D3). Nur mit dem Schlüssel aus der Verwaltung.
   Was damit passiert: GoogleLead::verarbeiten.
   ========================================================================== */
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Content-Type: application/json; charset=utf-8');
if (!is_file(__DIR__ . '/app/config.local.php')) { http_response_code(503); exit('{}'); }
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { http_response_code(405); exit('{}'); }
foreach (['Config', 'Db', 'Status', 'Auth', 'Fmt', 'Events', 'Texte', 'Akquise', 'AkquiseGate', 'AkquiseText', 'GoogleLead'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
// Ein Eingang ohne Menschen: Während des Not-Aus geht von hier nichts an Kunden,
// Partner oder die Öffentlichkeit (AI Office Stufe 0, 06.10.2026, Automation::ausgangGesperrt).
require_once __DIR__ . '/app/src/Automation.php';
Automation::automatischAb('google-lead');
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
$roh = (string) file_get_contents('php://input');
if (strlen($roh) > 100000) { http_response_code(413); exit('{}'); }
try {
    $r = GoogleLead::verarbeiten((array) (json_decode($roh, true) ?: []));
} catch (Throwable $e) {
    $r = 'fehler';
    try { Events::melden('akquise_formular', 'Google-Formular: Verarbeitung gescheitert', 'schlecht', mb_substr($e->getMessage(), 0, 300), 'akquise/beitraege#formular'); } catch (Throwable $x) { }
}
http_response_code($r === 'schluessel' ? 403 : 200);
echo '{}';
