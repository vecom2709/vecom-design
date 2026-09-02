<?php
declare(strict_types=1);
/* ==========================================================================
   Liefert die freigegebenen Kundenstimmen an die Website.

   Genau wie pakete-daten.php: Was in der Verwaltung freigegeben wird, steht
   danach von allein auf der Startseite. Ausgegeben wird nur, was ohnehin dort
   erscheinen soll — Name, Firma, Ort, Text, Sterne. Keine E-Mail, keine
   Kundennummer, keine internen Felder.

   Ist die Verwaltung nicht eingerichtet oder die Datenbank nicht erreichbar,
   kommt eine leere Liste — die Website blendet den Bereich dann einfach aus.
   ========================================================================== */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

$leer = static function (): never {
    echo json_encode(['stimmen' => []], JSON_UNESCAPED_UNICODE);
    exit;
};

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { $leer(); }

require_once __DIR__ . '/app/src/Config.php';
require_once __DIR__ . '/app/src/Db.php';

$sprache = strtolower((string) ($_GET['lang'] ?? 'it'));
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

try {
    require_once __DIR__ . '/app/src/Stimme.php';
    $roh = Stimme::oeffentliche($sprache);
} catch (Throwable $e) {
    $leer();
}

$aus = [];
foreach ($roh as $s) {
    $aus[] = [
        'text'   => (string) $s['text'],
        'name'   => (string) $s['name'],
        'firma'  => (string) ($s['firma'] ?? ''),
        'ort'    => (string) ($s['ort'] ?? ''),
        'sterne' => $s['sterne'] !== null ? (int) $s['sterne'] : null,
    ];
}
echo json_encode(['stimmen' => $aus, 'sprache' => $sprache], JSON_UNESCAPED_UNICODE);
