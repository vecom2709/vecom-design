<?php
declare(strict_types=1);
/* ==========================================================================
   Der regelmaessige Lauf, angestossen vom Cronjob im KAS.

   Er prueft die ueberwachten Websites, erinnert an offene Fragebogen,
   warnt vor ablaufenden Zertifikaten und raeumt abgelaufene Zahlungslinks
   weg. Alles Weitere steht in app/src/Cron.php.

   Aufgerufen wird die Adresse aus der Verwaltung unter Website-Monitoring —
   sie traegt einen Schluessel. Ohne den passiert hier nichts.
   ========================================================================== */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit("Noch nicht eingerichtet.\n"); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
require_once __DIR__ . '/app/src/Cron.php';

date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

// Der Schluessel darf in der Adresse stehen (so kann der KAS ihn aufrufen)
// oder im Kopf mitkommen. Verglichen wird zeitkonstant.
$schluessel = (string) ($_GET['schluessel'] ?? $_SERVER['HTTP_X_VECOM_CRON'] ?? '');

try {
    if (!Cron::schluesselStimmt($schluessel)) {
        // Absichtlich wortkarg: Wer den Schluessel nicht hat, erfaehrt auch
        // nicht, ob es hier ueberhaupt etwas zu holen gibt.
        http_response_code(404);
        exit("Nicht gefunden.\n");
    }
} catch (Throwable $e) {
    http_response_code(503);
    exit("Noch nicht bereit.\n");
}

try {
    $bilanz = Cron::laufen(isset($_GET['sofort']));
} catch (Throwable $e) {
    http_response_code(500);
    // In der Antwort steht nur, dass es schiefging. Der Grund landet dort,
    // wo Uwe ihn sieht — nicht in einer oeffentlich abrufbaren Zeile.
    try {
        Events::melden('cron_fehler', 'Der regelmäßige Lauf ist gescheitert', 'schlecht',
            mb_substr($e->getMessage(), 0, 400), '/monitoring');
    } catch (Throwable $e2) { /* dann eben nicht */ }
    exit("Fehler.\n");
}

if (!empty($bilanz['uebersprungen'])) {
    echo "uebersprungen: " . $bilanz['grund'] . "\n";
    exit;
}

echo "ok " . date('d.m.Y H:i:s') . "\n";
foreach ($bilanz as $name => $wert) {
    if ($name === 'zeit') { continue; }
    echo str_pad((string) $name, 16) . (is_array($wert) ? json_encode($wert, JSON_UNESCAPED_UNICODE) : (string) $wert) . "\n";
}
