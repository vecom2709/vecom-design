<?php
declare(strict_types=1);
/* ==========================================================================
   Ist diese Wunschadresse frei?

   Antwortet dem Fragebogen, waehrend der Kunde tippt. Deshalb sind hier drei
   Dinge wichtig, die man einer so kleinen Datei nicht ansieht:

   1. NUR MIT SCHLUESSEL. Die Adresse haengt am Fragebogen-Token. Ohne ihn
      keine Antwort — sonst waere das eine offene Whois-Abfrage fuer jeden,
      der die Adresse kennt, auf meine Rechnung und meine IP.

   2. KEINE MELDUNG BEI FEHLERN. Faellt die Pruefung aus, steht "kann ich
      nicht sicher sagen" — und der Kunde tippt weiter. Eine Domainpruefung
      ist kein Grund, ein Formular anzuhalten.

   3. NICHTS WIRD GESPEICHERT. Was der Kunde ins Feld schreibt, speichert der
      Fragebogen beim Weiterklicken. Hier wird nur geschaut.
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('{}'); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
require_once __DIR__ . '/app/src/Onboarding.php';
require_once __DIR__ . '/app/src/Domainpruefung.php';

date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
session_name('vecomfragebogen');
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$sprache = strtolower((string) ($_GET['lang'] ?? 'it'));
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

$antwort = static function (string $stand, string $weg = '') use ($sprache): void {
    echo json_encode([
        'stand' => $stand,
        'wort'  => Domainpruefung::wort($stand, $sprache),
        'weg'   => $weg,
    ], JSON_UNESCAPED_UNICODE);
    exit;
};

/* Der Schluessel muss zu einem offenen Fragebogen gehoeren. Ein
   abgeschlossener braucht keine Domainpruefung mehr. */
try {
    $f = Onboarding::laden(trim((string) ($_GET['t'] ?? '')));
} catch (Throwable $e) {
    $antwort(Domainpruefung::UNKLAR);
}
if (!$f || ($f['status'] ?? '') === 'abgeschlossen') {
    $antwort(Domainpruefung::UNKLAR);
}

$name = (string) ($_GET['name'] ?? '');
if (trim($name) === '') { $antwort(Domainpruefung::UNGUELTIG); }

try {
    $r = Domainpruefung::pruefen($name);
    $antwort((string) $r['stand'], (string) ($r['weg'] ?? ''));
} catch (Throwable $e) {
    $antwort(Domainpruefung::UNKLAR);
}
