<?php
declare(strict_types=1);
/* ==========================================================================
   sicherung.php — Uwes Rechner holt die Sicherung ab (AI Office Stufe 0, 06.10.2026).

   Kein Browser, kein Passwort: Jede Anfrage trägt die Unterschrift des
   Rechners (RSA, SHA-256), geprüft gegen den öffentlichen Schlüssel, den Uwe
   in der Verwaltung eingetragen hat. Alles, was hier hinausgeht, ist mit
   demselben Schlüssel verschlüsselt — lesen kann es nur der Rechner.
   Warum so und nicht anders: app/src/SicherungAussen.php.

   aktion=liste            JSON: Auszüge und Kundendateien
   aktion=holen&name=…     verschlüsselter Datenbankauszug
   aktion=datei&name=…     verschlüsselte Kundendatei aus der Ablage
   aktion=probe (POST)     Ergebnis der Wiederherstellungsprobe

   Ohne eingetragenen Schlüssel oder mit falscher Unterschrift: 404, wortkarg.
   ========================================================================== */

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

function sicherungJson(array $d, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!is_file(__DIR__ . '/app/config.local.php')) { sicherungJson(['ok' => false], 404); }
foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'SicherungAussen'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

$aktion = (string) ($_GET['aktion'] ?? '');
$name   = (string) ($_GET['name'] ?? '');
$rumpf  = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) file_get_contents('php://input', false, null, 0, 65536) : '';
try {
    if (!in_array($aktion, ['liste', 'holen', 'datei', 'probe'], true)
        || !SicherungAussen::anfrageStimmt($aktion, (string) ($_SERVER['HTTP_X_VECOM_ZEIT'] ?? ''), $name, $rumpf,
            (string) ($_SERVER['HTTP_X_VECOM_SIGNATUR'] ?? ''))) {
        sicherungJson(['ok' => false, 'hinweis' => 'Nicht gefunden.'], 404);
    }
} catch (Throwable $e) {
    sicherungJson(['ok' => false, 'hinweis' => 'Noch nicht bereit.'], 503);
}

if ($aktion === 'liste') { sicherungJson(['ok' => true] + SicherungAussen::liste()); }

if ($aktion === 'probe') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sicherungJson(['ok' => false], 405); }
    $d = json_decode($rumpf, true);
    sicherungJson(SicherungAussen::probeMelden(is_array($d) ? $d : []));
}

// holen / datei: verschlüsselt streamen
$pfad = SicherungAussen::pfad($aktion, $name);
if ($pfad === null) { sicherungJson(['ok' => false, 'hinweis' => 'Nicht gefunden.'], 404); }
@set_time_limit(300);
while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $name . '.vcs"');
SicherungAussen::verschluesseln($pfad, static function (string $teil): void { echo $teil; flush(); });
if ($aktion === 'holen') { SicherungAussen::abgeholt($name); }
exit;
