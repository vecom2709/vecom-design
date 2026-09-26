<?php
declare(strict_types=1);
/* ==========================================================================
   analyse.php — die persoenliche Website-Analyse fuer eine Firma.

   Nur mit Schluessel, nur wenn Uwe sie eingeschaltet hat, nie im Index.
   Was hier stehen darf und was nie, steht in app/src/AkquiseAnalyse.php.

   Bildschirmfoto: ?t=…&bild=1 liefert das mobile Foto derselben Analyse aus
   dem geschuetzten Ordner -- nur mit gueltigem Schluessel, nie ein anderes.
   ========================================================================== */

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$token = (string) ($_GET['t'] ?? '');
$daten = null;
if (preg_match('~^[a-f0-9]{40}$~', $token) && is_file(__DIR__ . '/app/config.local.php')) {
    foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'AkquiseAnalyse', 'Ablage'] as $k) {
        require_once __DIR__ . "/app/src/$k.php";
    }
    date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
    try { $daten = AkquiseAnalyse::oeffentlich($token); } catch (Throwable $e) { $daten = null; }
}

if ($daten !== null && isset($_GET['bild'])) {
    $name = (string) ($daten['audit']['screenshot_mobil'] ?? '');
    $pfad = Ablage::ordner() . '/akquise/' . basename($name);
    if ($name === '' || !is_file($pfad)) { http_response_code(404); exit; }
    header('Content-Type: ' . (str_ends_with($name, '.png') ? 'image/png' : 'image/jpeg'));
    header('Content-Length: ' . filesize($pfad));
    readfile($pfad);
    exit;
}

header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
if ($daten === null) {
    http_response_code(404);
    ?><!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow"><title>Vecom Design</title>
    <style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0b0f17;color:#c3cad6;font:17px/1.5 system-ui,sans-serif;padding:16px;text-align:center}</style></head>
    <body><p>Questa pagina non è (più) disponibile. · Diese Seite ist nicht (mehr) verfügbar.<br><a style="color:#7fa7ff" href="https://vecom-design.it">vecom-design.it</a></p></body></html><?php
    exit;
}

$f = $daten['firma'];
$a = $daten['audit'];
$s = (string) $daten['analyse']['sprache'];
$h = static fn(?string $x): string => htmlspecialchars((string) $x, ENT_QUOTES);
$punkte = [];
foreach ($daten['befunde'] as $b) {
    $x = AkquiseText::saetze($b, $f, $s);
    if ($x !== null) { $punkte[] = $x; }
}
$T = [
    'de' => ['kopf' => 'Website-Analyse', 'fuer' => 'Persönlich erstellt für', 'stand' => 'Stand der Prüfung', 'gesehen' => 'Was uns aufgefallen ist',
             'warum' => 'Warum das wichtig sein kann', 'loesung' => 'Wie wir es lösen würden', 'idee' => 'Eine Idee darüber hinaus',
             'cta1' => 'Kostenlose Projektbesprechung anfragen', 'cta2' => 'In 2 Minuten den Bedarf ermitteln', 'foto' => 'So sieht Ihre Startseite auf dem Smartphone aus (zum Zeitpunkt der Prüfung).',
             'hinweis' => 'Diese Seite ist nur über Ihren persönlichen Link erreichbar und nicht in Suchmaschinen gelistet.'],
    'it' => ['kopf' => 'Analisi del sito', 'fuer' => 'Preparata per', 'stand' => 'Data della verifica', 'gesehen' => 'Cosa abbiamo notato',
             'warum' => 'Perché può essere importante', 'loesung' => 'Come lo risolveremmo', 'idee' => 'Un’idea in più',
             'cta1' => 'Richiedi una consulenza gratuita', 'cta2' => 'Calcola le tue esigenze in 2 minuti', 'foto' => 'Così appare la vostra home page su smartphone (al momento della verifica).',
             'hinweis' => 'Questa pagina è raggiungibile solo tramite il vostro link personale e non è indicizzata dai motori di ricerca.'],
    'en' => ['kopf' => 'Website analysis', 'fuer' => 'Prepared for', 'stand' => 'Checked on', 'gesehen' => 'What we noticed',
             'warum' => 'Why it can matter', 'loesung' => 'How we would solve it', 'idee' => 'One idea beyond that',
             'cta1' => 'Request a free project call', 'cta2' => 'Work out your needs in 2 minutes', 'foto' => 'This is how your home page looks on a smartphone (at the time of the check).',
             'hinweis' => 'This page is only reachable via your personal link and is not listed in search engines.'],
][$s] ?? [];
$mail = 'mailto:kontakt@vecom-design.it?subject=' . rawurlencode($T['kopf'] . ' — ' . $f['name']);
?><!doctype html>
<html lang="<?= $h($s) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow, noarchive">
<title><?= $h($T['kopf'] . ' · ' . $f['name']) ?> — Vecom Design</title>
<style>
  :root { color-scheme: dark; --g:#07090f; --f:#0f1522; --l:rgba(150,180,230,.16); --t:#eef2f9; --d:#aab5c8; --b:#2f6bff; --c:#1fe8ff; }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--g) radial-gradient(70% 45% at 50% 0%, rgba(10,120,245,.16), transparent 70%); color: var(--t);
         font: 17px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  .w { max-width: 880px; margin: 0 auto; padding: 32px 16px 64px; }
  .marke { letter-spacing: .2em; font-weight: 700; font-size: 13px; color: #7fa7ff; }
  h1 { font-size: clamp(30px, 6vw, 46px); line-height: 1.1; margin: 14px 0 8px; letter-spacing: -.02em; }
  .unter { color: var(--d); margin: 0 0 28px; }
  .raster { display: grid; grid-template-columns: 1fr 260px; gap: 28px; align-items: start; }
  .foto img { width: 100%; height: auto; border-radius: 22px; border: 1px solid var(--l); display: block; }
  .foto p { color: var(--d); font-size: 13px; margin: 8px 2px 0; }
  .punkt { background: var(--f); border: 1px solid var(--l); border-radius: 16px; padding: 20px 20px 18px; margin-bottom: 14px; }
  .punkt h3 { margin: 0 0 8px; font-size: 18px; line-height: 1.35; }
  .punkt .n { display: inline-grid; place-items: center; width: 28px; height: 28px; border-radius: 50%; background: rgba(47,107,255,.18); color: #9fbcff; font-size: 14px; margin-right: 8px; }
  .punkt dl { margin: 0; } .punkt dt { color: #7fa7ff; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; margin-top: 10px; }
  .punkt dd { margin: 2px 0 0; color: var(--d); }
  h2 { font-size: 15px; letter-spacing: .1em; text-transform: uppercase; color: var(--d); margin: 34px 0 14px; }
  .idee { border-left: 3px solid var(--c); padding: 4px 0 4px 16px; color: var(--t); }
  .cta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 30px; }
  .cta a { flex: 1 1 260px; text-align: center; padding: 16px 18px; border-radius: 12px; text-decoration: none; font-weight: 650; min-height: 52px; }
  .cta .a { background: var(--b); color: #fff; } .cta .b { border: 1px solid var(--l); color: var(--t); background: var(--f); }
  .fuss { color: #7a88a3; font-size: 13px; margin-top: 40px; }
  a:focus-visible { outline: 3px solid #9fbcff; outline-offset: 2px; }
  @media (max-width: 760px) { .raster { grid-template-columns: 1fr; } .foto { max-width: 300px; margin: 0 auto; } }
</style>
</head>
<body>
<div class="w">
  <div class="marke">VECOM DESIGN</div>
  <h1><?= $h($T['kopf']) ?>: <?= $h((string) $f['name']) ?></h1>
  <p class="unter"><?= $h($T['fuer']) ?> <?= $h((string) $f['name']) ?> · <?= $h((string) ($f['domain'] ?? '')) ?> ·
    <?= $h($T['stand']) ?> <?= $h(date('d.m.Y', strtotime((string) $a['beendet_am']))) ?></p>

  <div class="raster">
    <div>
      <h2><?= $h($T['gesehen']) ?></h2>
      <?php foreach ($punkte as $i => [$beob, $wirk, $loes]): ?>
        <div class="punkt">
          <h3><span class="n"><?= $i + 1 ?></span><?= $h($beob) ?></h3>
          <dl><dt><?= $h($T['warum']) ?></dt><dd><?= $h($wirk) ?></dd>
              <dt><?= $h($T['loesung']) ?></dt><dd><?= $h(mb_strtoupper(mb_substr($loes, 0, 1)) . mb_substr($loes, 1)) ?>.</dd></dl>
        </div>
      <?php endforeach; ?>
    </div>
    <?php if (!empty($a['screenshot_mobil'])): ?>
      <div class="foto"><img src="analyse.php?t=<?= $h($token) ?>&amp;bild=1" alt="<?= $h($T['foto']) ?>" width="390" height="844" loading="lazy">
        <p><?= $h($T['foto']) ?></p></div>
    <?php endif; ?>
  </div>

  <?php if (trim((string) ($a['experience'] ?? '')) !== ''): ?>
    <h2><?= $h($T['idee']) ?></h2>
    <p class="idee"><?= $h((string) $a['experience']) ?></p>
  <?php endif; ?>

  <div class="cta">
    <a class="a" href="<?= $h($mail) ?>"><?= $h($T['cta1']) ?></a>
    <a class="b" href="https://vecom-design.it/zugang.php?lang=<?= $h($s) ?>"><?= $h($T['cta2']) ?></a>
  </div>
  <p class="fuss"><?= $h($T['hinweis']) ?> · <a style="color:#7fa7ff" href="https://www.vecom-design.it">www.vecom-design.it</a></p>
</div>
</body>
</html>
