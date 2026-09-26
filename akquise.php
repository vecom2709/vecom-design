<?php
declare(strict_types=1);
/* ==========================================================================
   akquise.php — die Tuer zwischen Verwaltung und Lead-Worker.

   Aufgerufen vom Worker in tools/akquise auf Uwes Rechner, nie von einem
   Browser. Ein Schluessel im Kopf, JSON im Rumpf, JSON zurueck.

   Eigener Schluessel, getrennt von Werkstatt und Telefon: Der Worker darf
   Firmen und Befunde melden, aber nichts freigeben und nichts versenden.
   Eine Sende-Aktion gibt es an dieser Tuer nicht.
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { antwort(['ok' => false, 'hinweis' => 'Nicht gefunden.'], 404); }

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { antwort(['ok' => false, 'hinweis' => 'Noch nicht eingerichtet.'], 503); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'AkquiseWorker'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

$kopf = (string) ($_SERVER['HTTP_X_VECOM_AKQUISE'] ?? '');
if ($kopf === '' && preg_match('/Bearer\s+(\S+)/i', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $m)) {
    $kopf = $m[1];
}
try {
    if (!AkquiseWorker::schluesselStimmt($kopf)) { antwort(['ok' => false, 'hinweis' => 'Nicht gefunden.'], 404); }
} catch (Throwable $e) {
    antwort(['ok' => false, 'hinweis' => 'Noch nicht bereit.'], 503);
}
if (!AkquiseWorker::darfNoch()) { antwort(['ok' => false, 'hinweis' => 'Zu viele Anfragen. Gleich noch einmal.'], 429); }

/* Nur JSON. Ein Audit mit zwei Bildschirmfotos ist schnell ein paar MB --
   darueber ist etwas schiefgelaufen. */
$roh = file_get_contents('php://input', false, null, 0, 6_000_000) ?: '';
$d = json_decode($roh, true);
if (!is_array($d)) { antwort(['ok' => false, 'hinweis' => 'Erwartet wird JSON.'], 400); }

$aktion = (string) ($d['aktion'] ?? '');
if (!in_array($aktion, AkquiseWorker::AKTIONEN, true)) {
    antwort(['ok' => false, 'hinweis' => 'Unbekannte Aktion.', 'moeglich' => AkquiseWorker::AKTIONEN], 400);
}

try {
    antwort(AkquiseWorker::ausfuehren($aktion, $d));
} catch (RuntimeException | InvalidArgumentException $e) {
    antwort(['ok' => false, 'hinweis' => $e->getMessage()], 400);
} catch (Throwable $e) {
    try {
        Events::melden('akquise_fehler', 'Akquise: Worker-Aufruf fehlgeschlagen', 'warnung',
                       $aktion . ' — ' . $e->getMessage(), 'akquise');
    } catch (Throwable $e2) { }
    antwort(['ok' => false, 'hinweis' => 'Das klappt gerade nicht — gleich noch einmal.'], 500);
}
