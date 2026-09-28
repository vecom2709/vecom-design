<?php
declare(strict_types=1);
/* ==========================================================================
   beitrag.php — das Bild eines Beitrags für Facebook/Instagram (28.09.2026,
   Uwe: Ja zu Z4). Meta holt es beim Posten über diese Adresse ab; deshalb
   öffentlich, aber nur mit dem langen Zufallsschlüssel des Beitrags und nie
   für verworfene Entwürfe. Nicht im Index.
   ========================================================================== */
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
if (!is_file(__DIR__ . '/app/config.local.php')) { http_response_code(503); exit; }
foreach (['Config', 'Db', 'Events', 'Akquise', 'AkquiseGate', 'MetaSeite'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
$t = (string) ($_GET['t'] ?? '');
if (!preg_match('~^[a-f0-9]{32}$~', $t)) { http_response_code(404); exit; }
try { $b = Db::one("SELECT * FROM akq_beitraege WHERE token = ? AND status <> 'verworfen'", [$t]); } catch (Throwable $e) { $b = null; }
if (!$b) { http_response_code(404); exit; }
$png = MetaSeite::bild($b);
header('Content-Type: image/png');
header('Content-Length: ' . strlen($png));
header('Cache-Control: public, max-age=86400');
echo $png;
