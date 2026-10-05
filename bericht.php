<?php
declare(strict_types=1);
/* ==========================================================================
   bericht.php — der ausführliche Website-Bericht mit fester Adresse
   (28.09.2026, Uwe: Ja zu A1–A10).

     ?b=…            der Bericht (zum Wiederöffnen und Weitergeben)
     ?b=…&pdf=1      derselbe Bericht als PDF (A9)
     ?b=…&bild=1     Handyfoto des Betriebs (nur, wenn der Bericht zu einem
                     Betrieb mit Prüfung vom PC gehört)

   Nur mit dem langen Schlüssel, nie im Index. Kein Name, keine E-Mail.
   ========================================================================== */
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
if (!is_file(__DIR__ . '/app/config.local.php')) { http_response_code(503); exit('Il servizio non è disponibile. · Der Dienst ist nicht verfügbar.'); }
foreach (['Config', 'Db', 'Status', 'Auth', 'Fmt', 'Events', 'Texte', 'Sprache', 'Akquise', 'WebBericht'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
try { require_once __DIR__ . '/app/src/Einrichtung.php'; Einrichtung::selbsttaetig(false); } catch (Throwable $e) { }

$token = (string) ($_GET['b'] ?? '');
$b = null;
try { $b = WebBericht::laden($token, !isset($_GET['pdf']) && !isset($_GET['bild'])); } catch (Throwable $e) { $b = null; }
$sprache = Sprache::ausAnfrage();
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

if ($b === null) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Vecom Design</title>'
       . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#060a16;color:#c9c1b3;font:17px/1.5 system-ui,sans-serif;padding:16px;text-align:center">'
       . '<p>Questo rapporto non è (più) disponibile. · Dieser Bericht ist nicht (mehr) verfügbar.<br><a style="color:#f1d38b" href="/analisi.php">vecom-design.it/analisi.php</a></p>';
    exit;
}
$firma = $b['firma_id'] ? Db::one('SELECT id, name, branche, stadt, kreis FROM akq_firmen WHERE id = ? AND gesperrt = 0', [$b['firma_id']]) : null;
$audit = $firma ? Akquise::letzterAudit((int) $firma['id']) : null;
if ($audit !== null && ($audit['status'] ?? '') !== 'fertig') { $audit = null; }

/* ---------- Handyfoto ---------- */
if (isset($_GET['bild'])) {
    require_once __DIR__ . '/app/src/Ablage.php';
    $name = (string) ($audit['screenshot_mobil'] ?? '');
    $pfad = Ablage::ordner() . '/akquise/' . basename($name);
    if ($name === '' || !is_file($pfad)) { http_response_code(404); exit; }
    header('Content-Type: ' . (str_ends_with($name, '.png') ? 'image/png' : 'image/jpeg'));
    header('Content-Length: ' . (string) filesize($pfad));
    readfile($pfad);
    exit;
}

/* ---------- PDF (A9) ---------- */
if (isset($_GET['pdf'])) {
    $kc = $b['kc'];
    $datei = 'bericht-' . preg_replace('~[^a-z0-9.-]~', '', strtolower((string) ($kc['host'] ?? 'website'))) . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $datei . '"');
    echo WebBericht::pdf($b, $sprache);
    exit;
}

/* ---------- Seite ---------- */
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; script-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$T = ['it' => ['titel' => 'Rapporto sul sito', 'selbst' => 'Vuole la stessa analisi per il suo sito?', 'knopf' => 'Analisi gratuita'],
      'de' => ['titel' => 'Website-Bericht', 'selbst' => 'Dieselbe Analyse für Ihre eigene Website?', 'knopf' => 'Kostenlose Analyse'],
      'en' => ['titel' => 'Website report', 'selbst' => 'Want the same analysis for your own website?', 'knopf' => 'Free analysis']][$sprache];
$marken = $audit ? WebBericht::marken($audit) : [];
$wb = ['kc' => $b['kc'], 'sprache' => $sprache, 'token' => $b['token'], 'firma' => $firma,
       'bild' => $audit && !empty($audit['screenshot_mobil']) ? '/bericht.php?b=' . $b['token'] . '&bild=1' : null, 'marken' => $marken,
       'vergleich' => $firma ? WebBericht::vergleich((int) $firma['id'], $sprache) : null, 'preise' => false,
       'seite' => '/bericht.php?b=' . $b['token'] . '&lang=' . $sprache];
?><!doctype html>
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
<head>
<meta charset="utf-8">
<?= Sprache::skript() ?><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title><?= $h($T['titel'] . ': ' . (string) ($b['kc']['host'] ?? '')) ?> — Vecom Design</title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<style>
  body{margin:0;background:radial-gradient(1200px 600px at 50% -200px,rgba(241,211,139,.09),transparent 70%),#060a16;color:#f7f3ea;font:16px/1.6 'Inter',system-ui,sans-serif}
  main{max-width:760px;margin:0 auto;padding:28px 16px 56px}
  .marke{display:inline-flex;font:800 14px/1 'Archivo',sans-serif;letter-spacing:.08em;color:#f7f3ea;text-decoration:none;margin-bottom:26px}.marke b{color:#f1d38b}
  h1{font:800 clamp(26px,6vw,38px)/1.12 'Archivo',sans-serif;margin:0 0 6px;overflow-wrap:anywhere}
  .selbst{margin-top:28px;padding:20px;border:1px solid rgba(241,211,139,.35);border-radius:18px;display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap}
  .selbst a{display:inline-flex;align-items:center;min-height:48px;padding:0 20px;border-radius:12px;background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438);color:#16120b;font-weight:700;text-decoration:none}
</style>
</head>
<body>
<main>
  <a class="marke" href="/<?= $sprache === 'it' ? '' : $h($sprache) . '/' ?>"><b>VECOM</b>&nbsp;DESIGN</a>
  <h1><?= $h($T['titel']) ?>: <?= $h((string) ($b['kc']['host'] ?? '')) ?></h1>
  <?php require __DIR__ . '/app/views/web_bericht.php'; ?>
  <div class="selbst"><b><?= $h($T['selbst']) ?></b><a href="/analisi.php?lang=<?= $h($sprache) ?>"><?= $h($T['knopf']) ?></a></div>
</main>
</body>
</html>
