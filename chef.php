<?php
declare(strict_types=1);
/* ==========================================================================
   chef.php — Manuelas Chef-Modus für Uwe.

   Aufgerufen von STRATO wie telefon.php, aber hinter einer zweiten Tür: dem
   gesprochenen Codewort. Der STRATO-Schlüssel beweist, dass STRATO anruft;
   das Codewort beweist, dass Uwe spricht. Erst beide zusammen öffnen die
   Chef-Aktionen. Ist kein Codewort gesetzt, ist der Modus aus.

   Absichtlich getrennt von telefon.php: Kundenaktionen und Chefaktionen
   sollen sich nie versehentlich mischen. Ein Fehler hier gibt Manuela einen
   Satz zum Vorlesen, nie eine leere Seite.
   ========================================================================== */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

function antwort(array $d, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { antwort(['ok' => false, 'hinweis' => 'Noch nicht eingerichtet.'], 503); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'Kunde', 'Telefon', 'Chef'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

/* Tür 1: der STRATO-Schlüssel (derselbe wie bei telefon.php). */
$kopf = (string) ($_SERVER['HTTP_X_VECOM_TELEFON'] ?? '');
if ($kopf === '' && preg_match('/Bearer\s+(\S+)/i', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $m)) {
    $kopf = $m[1];
}
$schluessel = $kopf !== '' ? $kopf : (string) ($_GET['schluessel'] ?? '');
try {
    if (!Telefon::schluesselStimmt($schluessel)) { antwort(['ok' => false, 'hinweis' => 'Nicht gefunden.'], 404); }
} catch (Throwable $e) {
    antwort(['ok' => false, 'hinweis' => 'Noch nicht bereit.'], 503);
}
if (!Telefon::darfNoch()) {
    antwort(['ok' => false, 'hinweis' => 'Zu viele Anfragen. Gleich noch einmal.'], 429);
}

/* Daten lesen: JSON im Rumpf, sonst Formular. */
$roh = file_get_contents('php://input') ?: '';
$d = [];
if ($roh !== '') { $j = json_decode($roh, true); if (is_array($j)) { $d = $j; } }
if (!$d && $_POST) { $d = $_POST; }

/* Tür 2: das gesprochene Codewort. Ohne gesetztes Codewort ist der Modus aus. */
$codewort = (string) ($d['codewort'] ?? $_GET['codewort'] ?? '');
try {
    if (!Chef::eingerichtet()) {
        antwort(['ok' => false, 'aus' => true,
                 'hinweis' => 'Der Chef-Modus ist noch nicht eingerichtet. Uwe setzt das Codewort in der Verwaltung.']);
    }
    if (!Chef::frei($codewort)) {
        antwort(['ok' => false, 'gesperrt' => true,
                 'hinweis' => 'Das Codewort stimmt nicht — der Chef-Modus bleibt zu.']);
    }
} catch (Throwable $e) {
    antwort(['ok' => false, 'hinweis' => 'Der Chef-Modus ist gerade nicht erreichbar.']);
}

$aktion = (string) ($d['aktion'] ?? $_GET['aktion'] ?? '');
if (!in_array($aktion, Chef::AKTIONEN, true)) {
    antwort(['ok' => false, 'hinweis' => 'Unbekannte Chef-Aktion.', 'moeglich' => Chef::AKTIONEN], 400);
}

try {
    $ergebnis = match ($aktion) {
        'chef_lage'          => Chef::lage(),
        'chef_kunde'         => Chef::kunde($d),
        'chef_kunde_anlegen' => Chef::kundeAnlegen($d),
        'chef_notiz'         => Chef::notiz($d),
    };
    antwort($ergebnis);
} catch (Throwable $e) {
    try {
        Events::melden('chef_fehler', 'Chef-Modus: Aktion fehlgeschlagen', 'warnung',
                       $aktion . ' — ' . $e->getMessage(), '/heute');
    } catch (Throwable $e2) { }
    antwort(['ok' => false, 'hinweis' => 'Das klappt gerade nicht — versuch es gleich noch einmal.']);
}
