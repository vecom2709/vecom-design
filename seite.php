<?php
declare(strict_types=1);
/* ==========================================================================
   seite.php — Landingpages der Zielgruppen (01.10.2026, Uwe: Ja zu S6).

   /seite.php?s=sito-ristorante zeigt die freigegebene Seite der Zielgruppe
   im Gerüst der Landeseiten (gleicher Kopf, gleiche Navigation, gleicher
   Fuß). Entwürfe sieht nur Uwe, in der Verwaltung (Vorschau).
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('Derzeit nicht erreichbar.'); }
foreach (['Config', 'Db', 'Events', 'MkSeite'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

$s = MkSeite::zumAnzeigen((string) ($_GET['s'] ?? ''));
$geruest = $s ? MkSeite::geruest((string) $s['sprache'], __DIR__) : null;
if (!$s || $geruest === null) {
    http_response_code(404);
    $nf = @file_get_contents(__DIR__ . '/404.html');
    echo $nf !== false ? $nf : 'Seite nicht gefunden.';
    exit;
}
MkSeite::zaehlen((int) $s['id']);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');
echo MkSeite::html($s, $geruest, (string) Config::get('website', 'https://vecom-design.it'));
