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
require_once __DIR__ . '/app/src/Einrichtung.php';

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

/* --------------------------------------------------------------------------
   Zuerst: die Datenbank nachziehen.

   WARUM DAS HIER STEHT UND NICHT NUR IN DER VERWALTUNG

   Bisher hat ausschliesslich app/index.php offene Migrationen eingespielt —
   also erst dann, wenn Uwe die Verwaltung aufmachte. Nach jedem Deploy, der
   eine Spalte hinzufuegt, lief der neue Code bis dahin auf der alten
   Datenbank. Und zwar nicht nur fuer ihn: fragebogen.php, projekt.php,
   vorgang.php, buchen.php, formular.php und stripe-webhook.php gehoeren dem
   Kunden, und die fragen niemanden.

   Am 31.08. ist genau das passiert. In den Meldungen steht
   "Fragebogen nicht erreichbar — Unknown column 'c.sprache'": Ein Kunde hat
   seinen Link angeklickt und eine Fehlerseite bekommen, weil die Spalte im
   Code schon da war und in der Datenbank noch nicht.

   Der Cronjob laeuft alle zehn Minuten. Damit ist das Fenster nie groesser
   als zehn Minuten, und niemand muss daran denken. Beispieldaten legt er
   ausdruecklich nicht an — deshalb der Schalter.

   Erst nach der Schluesselpruefung: Sonst koennte ein Fremder den Aufruf
   nutzen, um Schreibvorgaenge an der Datenbank anzustossen.
   -------------------------------------------------------------------------- */
try {
    $stand = Einrichtung::selbsttaetig(false);
    if ($stand['migrationen']) {
        Events::protokoll('system_migration', 'Datenbank vom Cronjob nachgezogen: '
            . implode(', ', $stand['migrationen']));
    }
    if ($stand['fehler'] !== null) {
        Events::melden('system_migration', 'Die Datenbank liess sich nicht aktualisieren', 'schlecht',
            mb_substr((string) $stand['fehler'], 0, 400)
                . ' — solange das so bleibt, koennen Kundenseiten auf Fehler laufen.',
            '/einstellungen');
    }
} catch (Throwable $e) {
    // Das Nachziehen ist Vorsorge. Scheitert es, soll der eigentliche Lauf
    // trotzdem stattfinden — Monitoring und Erinnerungen sind wichtiger.
    $stand = ['migrationen' => [], 'fehler' => $e->getMessage()];
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
if (!empty($stand['migrationen'])) {
    echo str_pad('migrationen', 16) . implode(', ', $stand['migrationen']) . "\n";
}
foreach ($bilanz as $name => $wert) {
    if ($name === 'zeit') { continue; }
    echo str_pad((string) $name, 16) . (is_array($wert) ? json_encode($wert, JSON_UNESCAPED_UNICODE) : (string) $wert) . "\n";
}
