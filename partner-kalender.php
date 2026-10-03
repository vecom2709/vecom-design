<?php
declare(strict_types=1);
/* ==========================================================================
   partner-kalender.php — das Kalender-Abo eines Partners (03.10.2026,
   PartnerAutomatik, Schalter „Rückrufe in Ihren Kalender“).

   Der Schlüssel ist eigens dafür da und öffnet nichts anderes — nicht den
   Partnerbereich, keine Kundendaten. Ist der Schalter aus oder der Partner
   nicht mehr aktiv, gibt es schlicht keinen Kalender (404).
   ========================================================================== */
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$k = (string) ($_GET['k'] ?? '');
$text = null;
if (preg_match('~^[a-f0-9]{32}$~', $k) && is_file(__DIR__ . '/app/config.local.php')) {
    foreach (['Config', 'Db', 'Fmt', 'Events', 'Texte', 'Partner', 'PartnerAutomatik'] as $kl) { require_once __DIR__ . "/app/src/$kl.php"; }
    date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
    try { $text = PartnerAutomatik::ics($k); } catch (Throwable $e) { $text = null; }
}
if ($text === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    exit;
}
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="vecom.ics"');
echo $text;
