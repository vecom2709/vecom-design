<?php
declare(strict_types=1);
/* ==========================================================================
   demo.php — die kostenlose Demo-Vorschau einer neuen Startseite
   (Marketing-Studio 10, 01.10.2026, Uwe: „ja“ zu S1).

   Öffentlich, aber nur mit dem langen Zufallsschlüssel, nur nach Uwes
   Freigabe und nur 30 Tage. Die Seite stammt von Claude (aus der bisherigen
   Website des Betriebs) und läuft deshalb in einer Sandbox: keine Skripte,
   keine Formulare, nicht einbettbar außer bei uns, nicht in Suchmaschinen.
   ========================================================================== */
// Die Kette (app/pruefung/kette.php) bindet diese Seite mit ihrer eigenen Konfiguration ein — dort steht Config schon.
if (!is_file(__DIR__ . '/app/config.local.php') && !class_exists('Config', false)) { http_response_code(503); exit; }
foreach (['Config', 'Db', 'Events', 'MkDemo'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
MkDemo::kopfzeilen();
$demo = null;
try { $demo = MkDemo::zeigen((string) ($_GET['t'] ?? '')); } catch (Throwable $e) { $demo = null; }
if ($demo === null) {
    http_response_code(410);
    $sp = in_array($_GET['lang'] ?? '', ['it', 'de', 'en'], true) ? (string) $_GET['lang'] : 'it';
    echo '<!doctype html><html lang="' . $sp . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Vecom Design</title></head>'
        . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#0d0c0a;color:#f3ece0;font:16px/1.6 system-ui,sans-serif;text-align:center;padding:24px">'
        . '<main><p>' . htmlspecialchars(MkDemo::t('weg', $sp), ENT_QUOTES, 'UTF-8') . '</p><p><a style="color:#f5e2a6" href="https://vecom-design.it/?lang=' . $sp . '">vecom-design.it</a></p></main></body></html>';
    exit;
}
echo MkDemo::seite($demo);
