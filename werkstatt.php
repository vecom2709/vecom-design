<?php
declare(strict_types=1);
/* ==========================================================================
   werkstatt.php — die Tuer zwischen Verwaltung und Baumeister.

   Aufgerufen von Claude Code auf Uwes Rechner, nicht von einem Browser.
   Ein Schluessel im Kopf, JSON im Rumpf, JSON zurueck — mehr braucht es
   nicht, und mehr soll hier auch nicht stehen.

   Absichtlich getrennt von telefon.php und chef.php: Der Telefonschluessel
   liegt bei einem fremden Anbieter im Klartext und darf nur lesen, was ein
   Kunde am Telefon hoeren darf. Dieser hier darf am Projekt schreiben. Zwei
   Tueren, zwei Schluessel, getrennt zu tauschen.

   Ein Fehler gibt hier immer einen Satz zurueck, nie eine leere Seite: Am
   anderen Ende sitzt ein Werkzeug, das mit einem Satz weiterarbeiten kann
   und mit einer leeren Antwort nicht.
   ========================================================================== */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

function antwort(array $d, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { antwort(['ok' => false, 'hinweis' => 'Noch nicht eingerichtet.'], 503); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'Werkstatt'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

/* Der Schluessel: im Kopf, als Bearer, sonst gar nicht. Nicht in der
   Adresszeile -- die steht in jedem Serverprotokoll. */
$kopf = (string) ($_SERVER['HTTP_X_VECOM_WERKSTATT'] ?? '');
if ($kopf === '' && preg_match('/Bearer\s+(\S+)/i', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $m)) {
    $kopf = $m[1];
}
try {
    if (!Werkstatt::schluesselStimmt($kopf)) { antwort(['ok' => false, 'hinweis' => 'Nicht gefunden.'], 404); }
} catch (Throwable $e) {
    antwort(['ok' => false, 'hinweis' => 'Noch nicht bereit.'], 503);
}
if (!Werkstatt::darfNoch()) {
    antwort(['ok' => false, 'hinweis' => 'Zu viele Anfragen. Gleich noch einmal.'], 429);
}

/* Daten lesen: JSON im Rumpf, sonst Formular, sonst Adresszeile. */
$roh = file_get_contents('php://input') ?: '';
$d = [];
if ($roh !== '') { $j = json_decode($roh, true); if (is_array($j)) { $d = $j; } }
if (!$d && $_POST) { $d = $_POST; }
if (!$d && $_GET)  { $d = $_GET; }

$aktion = (string) ($d['aktion'] ?? '');
if (!in_array($aktion, Werkstatt::AKTIONEN, true)) {
    antwort(['ok' => false, 'hinweis' => 'Unbekannte Aktion.', 'moeglich' => Werkstatt::AKTIONEN], 400);
}

try {
    antwort(match ($aktion) {
        'liste'     => Werkstatt::liste($d),
        'auftrag'   => Werkstatt::auftrag($d),
        'weiter'    => Werkstatt::weiter($d),
        'vorschau'  => Werkstatt::vorschau($d),
        'stand'     => Werkstatt::stand($d),
        'notiz'     => Werkstatt::notiz($d),
        'freigeben' => Werkstatt::freigeben($d),
    });
} catch (RuntimeException $e) {
    /* Erwartbares: falsche Nummer, fehlende Adresse. Das ist kein Fehler des
       Servers, sondern eine Auskunft — und gehoert deshalb lesbar zurueck
       und nicht in die Meldungen. */
    antwort(['ok' => false, 'hinweis' => $e->getMessage()], 400);
} catch (Throwable $e) {
    try {
        Events::melden('werkstatt_fehler', 'Werkstatt: Aufruf fehlgeschlagen', 'warnung',
                       $aktion . ' — ' . $e->getMessage(), '/werkstatt');
    } catch (Throwable $e2) { }
    antwort(['ok' => false, 'hinweis' => 'Das klappt gerade nicht — gleich noch einmal.'], 500);
}
