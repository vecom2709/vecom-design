<?php
declare(strict_types=1);
/* ==========================================================================
   Der Telefonassistent fragt die Verwaltung.

   Aufgerufen von STRATO AI Frontdesk waehrend eines Gespraechs. Vier
   Aktionen, ein Schluessel, JSON rein und JSON raus. Was der Endpunkt darf
   und warum er so eng gefasst ist, steht in app/src/Telefon.php.

   Absichtlich duenn: Hier stehen nur Tuer und Verteiler. Jede Regel, die man
   spaeter noch einmal lesen will, gehoert in die Klasse -- eine Datei im
   Stammverzeichnis liest niemand freiwillig zweimal.
   ========================================================================== */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

/** Antwortet und beendet. Immer JSON, immer mit einem Satz fuer Manuela. */
function antwort(array $d, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { antwort(['ok' => false, 'hinweis' => 'Noch nicht eingerichtet.'], 503); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'Kunde', 'Telefon'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

/* --------------------------------------------------------------------------
   Der Schluessel.

   Er darf im Kopf stehen (sauber) oder in der Adresse (weil manche
   Plattformen nichts anderes koennen). Verglichen wird zeitkonstant.
   -------------------------------------------------------------------------- */
$kopf = (string) ($_SERVER['HTTP_X_VECOM_TELEFON'] ?? '');
if ($kopf === '' && preg_match('/Bearer\s+(\S+)/i', (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''), $m)) {
    $kopf = $m[1];
}
$schluessel = $kopf !== '' ? $kopf : (string) ($_GET['schluessel'] ?? '');

try {
    if (!Telefon::schluesselStimmt($schluessel)) {
        /* Wortkarg wie beim Cronjob: Wer den Schluessel nicht hat, erfaehrt
           auch nicht, ob es hier etwas zu holen gibt. */
        antwort(['ok' => false, 'hinweis' => 'Nicht gefunden.'], 404);
    }
} catch (Throwable $e) {
    antwort(['ok' => false, 'hinweis' => 'Noch nicht bereit.'], 503);
}

if (!Telefon::darfNoch()) {
    antwort(['ok' => false, 'hinweis' => 'Zu viele Anfragen. Gleich noch einmal.'], 429);
}

/* --------------------------------------------------------------------------
   Die Daten. JSON im Rumpf ist der Normalfall; Formularfelder gehen auch,
   falls die Plattform sie eines Tages so schickt.
   -------------------------------------------------------------------------- */
$roh = file_get_contents('php://input') ?: '';
$d = [];
if ($roh !== '') {
    $j = json_decode($roh, true);
    if (is_array($j)) { $d = $j; }
}
if (!$d && $_POST) { $d = $_POST; }

$aktion = (string) ($d['aktion'] ?? $_GET['aktion'] ?? '');
if (!in_array($aktion, Telefon::AKTIONEN, true)) {
    antwort(['ok' => false,
             'hinweis' => 'Unbekannte Aktion.',
             'moeglich' => Telefon::AKTIONEN], 400);
}

/* --------------------------------------------------------------------------
   Ausfuehren.

   Ein Fehler hier darf nie ein Gespraech abwuergen: Manuela bekommt einen
   Satz, den sie vorlesen kann, und Uwe bekommt die Meldung. Ein Assistent,
   der mitten im Satz verstummt, ist schlimmer als einer, der sagt, dass es
   gerade nicht geht.
   -------------------------------------------------------------------------- */
try {
    $ergebnis = match ($aktion) {
        'kunde_nachschlagen' => Telefon::nachschlagen($d),
        'preis_auskunft'     => Telefon::preisAuskunft($d),
        'lage'               => Telefon::lage(),
        'angebot_link'       => Telefon::angebotLink($d),
        'melde'              => Telefon::melden($d),
        'zusammenfassung'    => Telefon::zusammenfassung($d),
        'wissensluecke'      => Telefon::wissensluecke($d),
    };
    antwort($ergebnis);
} catch (Throwable $e) {
    try {
        Events::melden('telefon_fehler', 'Telefonassistent: Aktion fehlgeschlagen', 'warnung',
                       $aktion . ' — ' . $e->getMessage(), '/heute');
    } catch (Throwable $e2) { }
    antwort(['ok' => false,
             'hinweis' => 'Das klappt gerade nicht. Anliegen aufnehmen und melden.'], 200);
}
