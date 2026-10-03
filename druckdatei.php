<?php
declare(strict_types=1);
/* ==========================================================================
   druckdatei.php — hier holt der Druckanbieter (Gelato) die Druckdatei ab
   (Marketing Center, Phase 4, 03.10.2026).

   Nur mit unterschriebenem, befristetem Link aus Gelato::dateiLink(). Es
   wird genau die Datei ausgeliefert, die beim Freigeben entstanden ist —
   und nur, wenn der Entwurf freigegeben ist oder zu einer Bestellung gehört.
   Ohne gültigen Link: 404, ohne Hinweis warum.
   ========================================================================== */
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

if (!is_file(__DIR__ . '/app/config.local.php')) { http_response_code(404); exit; }
foreach (['Config', 'Db', 'Status', 'Fmt', 'Events', 'Gelato'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }

$id = Gelato::linkPruefen((string) ($_GET['e'] ?? ''), (string) ($_GET['x'] ?? ''), (string) ($_GET['s'] ?? ''));
$d = $id > 0 ? Db::one("SELECT e.id, e.datei_druck FROM wm_entwuerfe e
                         WHERE e.id = ? AND e.datei_druck IS NOT NULL
                           AND (e.status IN ('freigegeben', 'ersetzt') OR EXISTS (SELECT 1 FROM wm_positionen x WHERE x.entwurf_id = e.id))", [$id]) : null;
if (!$d) { http_response_code(404); exit; }

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="vecom-druck-' . (int) $d['id'] . '.pdf"');
header('Content-Length: ' . strlen((string) $d['datei_druck']));
echo $d['datei_druck'];
