<?php
declare(strict_types=1);
/* ==========================================================================
   Die Seite, die der Kunde schon mit der Anfrage bekommt — lange bevor Geld
   fliesst. Sie kann bewusst wenig: sehen, was man angefragt hat, schreiben,
   Unterlagen schicken. Kein Konto, kein Passwort, ein Link.

   Wird aus der Anfrage ein Auftrag, fuehrt derselbe Link auf die Projektseite
   weiter. Der Kunde merkt sich eine Adresse, vom ersten Kontakt bis online.
   ========================================================================== */
$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('Gerade nicht erreichbar.'); }
foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
require_once __DIR__ . '/app/src/Texte.php';
require_once __DIR__ . '/app/src/Anfrage.php';
require_once __DIR__ . '/app/src/Nachricht.php';
require_once __DIR__ . '/app/src/Ablage.php';
require_once __DIR__ . '/app/src/Onboarding.php';

date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
session_name('vecomvorgang');
session_start();
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

/* --------------------------------------------------------------------------
   Ab hier fuehrt ein Weg zur neuen, einen Kundenseite. Dieser Link liegt in
   Postfaechern und darf nicht sterben — er zeigt jetzt nur woanders hin.
   Findet sich zum alten Schluessel kein Kunde, laeuft unten alles wie bisher.
   -------------------------------------------------------------------------- */
require_once __DIR__ . '/app/src/Kundenzugang.php';
$altToken = trim((string) ($_REQUEST['t'] ?? ''));
if ($altToken !== '' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $kid = Kundenzugang::kundeZuAltemToken('anfrage', $altToken);
        if ($kid) {
            header('Location: ' . Kundenzugang::linkFuer($kid), true, 302);
            exit;
        }
    } catch (Throwable $e) { /* dann eben die alte Seite */ }
}

$token = trim((string) ($_REQUEST['t'] ?? ''));
$a = null; $panne = false;
try { $a = Anfrage::ausToken($token); } catch (Throwable $e) { $panne = true; }

/* Ist daraus laengst ein Projekt geworden, gehoert der Kunde dorthin. Ein
   Link, der mitwaechst — statt eines zweiten, den er suchen muss. */
if ($a && $a['order_id']) {
    $weiter = Db::one(
        'SELECT q.token FROM projects p JOIN questionnaires q ON q.project_id = p.id WHERE p.order_id = ?',
        [(int) $a['order_id']]
    );
    if ($weiter && $weiter['token']) {
        header('Location: /projekt.php?t=' . rawurlencode((string) $weiter['token']));
        exit;
    }
}

$sprache = strtolower((string) ($_REQUEST['lang'] ?? ($a['sprache'] ?? 'it')));
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

/* Waehlt der Kunde hier unten eine Sprache, gilt sie ab jetzt auch fuer
   jede Mail an ihn. Vorher aenderte der Umschalter nur diese eine Seite. */
if ($a !== null
    && isset($_REQUEST['lang'])
    && ($a['customer_id'] ?? null) !== null
    && $sprache !== strtolower((string) ($a['sprache'] ?? ''))) {
    try {
        require_once __DIR__ . '/app/src/Onboarding.php';
        Onboarding::spracheMerken((int) $a['customer_id'], $sprache);
    } catch (Throwable $e) { /* die Seite zeigt trotzdem die gewaehlte Sprache */ }
}

$T = static fn(string $s): string => Texte::h(Texte::VORGANG[$s] ?? [], $sprache);
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

/* ---------- Eine Datei herunterladen ---------- */
if ($a && isset($_GET['datei'])) {
    $d = Db::one('SELECT * FROM files WHERE id = ? AND customer_id = ?',
        [(int) $_GET['datei'], (int) $a['customer_id']]);
    if ($d) { Ablage::ausliefern($d); }
    http_response_code(404); exit('Nicht gefunden.');
}

$meldung = null; $fehler = null;
if ($a && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['csrf']) || !hash_equals((string) $_SESSION['csrf'], (string) ($_POST['_csrf'] ?? ''))) {
        $fehler = $T('panne');
    } elseif (Ablage::zuGrossFuerDenServer()) {
        $fehler = str_replace('{max}', Fmt::bytes(Ablage::grenze()), $T('dateiHinweis'));
    } elseif (($_POST['tat'] ?? '') === 'nachricht') {
        try {
            Nachricht::vorab((int) $a['customer_id'], (string) ($_POST['text'] ?? ''), 'kunde');
            Anfrage::status((int) $a['id'], 'in_arbeit');
            $meldung = $T('gesendet');
        } catch (Throwable $e) { $fehler = $e->getMessage(); }
    } elseif (($_POST['tat'] ?? '') === 'datei' && isset($_FILES['datei'])) {
        try {
            Ablage::annehmen($_FILES['datei'], null, (int) $a['customer_id'], 'kunde');
            $meldung = $T('dateiOk');
        } catch (Throwable $e) { $fehler = $e->getMessage(); }
    }
    // Nach dem Schreiben neu einlesen, sonst fehlt das gerade Gesendete.
    $a = Anfrage::ausToken($token);
}

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }

$nachrichten = $a ? Db::all(
    'SELECT * FROM messages WHERE customer_id = ? AND project_id IS NULL ORDER BY id ASC LIMIT 200',
    [(int) $a['customer_id']]) : [];
$dateien = $a ? Db::all(
    'SELECT * FROM files WHERE customer_id = ? AND project_id IS NULL ORDER BY id DESC LIMIT 60',
    [(int) $a['customer_id']]) : [];
?><!doctype html>
<html lang="<?= $h($sprache) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $h($T('titel')) ?> — Vecom Design</title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<link rel="stylesheet" href="/assets/css/kunde.css">
<style>
  .angefragt{white-space:pre-wrap; font-size:14.5px; line-height:1.65; color:var(--dim)}
  .lead{color:var(--dim); font-size:15px; line-height:1.65}
</style>
</head>
<body>
<div class="seite">
  <div class="wortmarke">
    <img src="/assets/img/logo-mark.webp" alt="" width="58" height="46" fetchpriority="high">
    <span class="wort"><b>VECOM</b> DESIGN</span>
  </div>

<?php if ($panne): ?>
  <div class="block"><div class="hinweis schlecht"><?= $h($T('panne')) ?></div></div>
<?php elseif (!$a): ?>
  <div class="block"><div class="hinweis"><?= $h($T('weg')) ?></div></div>
<?php else: ?>

  <div class="block">
    <h1 style="font-size:20px;margin:0 0 8px"><?= $h($T('titel')) ?></h1>
    <p style="color:var(--dim);font-size:14px;line-height:1.6;margin:0"><?= $h($T('lead')) ?></p>
  </div>

  <!-- Die Anleitung steht oben, nicht versteckt: Wer nach drei Wochen
       zurueckkommt, weiss sonst nicht mehr, wofuer diese Seite da war. -->
  <div class="block">
    <h2><?= $h($T('soGehts')) ?></h2>
    <p style="color:var(--dim);font-size:14px;line-height:1.6;margin:0 0 14px"><?= $h($T('g0')) ?></p>
    <ol class="schritte">
      <?php foreach ([1, 2, 3, 4] as $i): ?>
        <li><b><?= $h($T('g' . $i)) ?></b><span><?= $h($T('g' . $i . 'd')) ?></span></li>
      <?php endforeach; ?>
    </ol>
  </div>

  <?php if ($meldung): ?><div class="block"><div class="hinweis gut"><?= $h($meldung) ?></div></div><?php endif; ?>
  <?php if ($fehler):  ?><div class="block"><div class="hinweis schlecht"><?= $h($fehler) ?></div></div><?php endif; ?>

  <div class="block">
    <h2><?= $h($T('angefragt')) ?></h2>
    <?php if ($a['paket_name']): ?>
      <div class="zeile"><span><?= $h($T('paket')) ?></span><b><?= $h($a['paket_name']) ?></b></div>
    <?php endif; ?>
    <div class="zeile"><span><?= $h($T('am')) ?></span><b><?= $h(Fmt::datum($a['created_at'])) ?></b></div>
    <?php if ($a['nachricht']): ?>
      <p class="angefragt" style="margin-top:12px"><?= $h((string) $a['nachricht']) ?></p>
    <?php endif; ?>
    <p style="color:var(--leise);font-size:12.5px;margin:14px 0 0"><?= $h($T('unverbind')) ?></p>
  </div>

  <div class="block">
    <h2><?= $h($T('nachrichten')) ?></h2>
    <?php if (!$nachrichten): ?>
      <p style="color:var(--leise);font-size:14px"><?= $h($T('nochNichts')) ?></p>
    <?php else: foreach ($nachrichten as $m): ?>
      <div class="nachricht <?= $m['sender'] === 'kunde' ? '' : 'wir' ?>">
        <div class="wer"><span><?= $h($m['sender'] === 'kunde' ? $T('du') : $T('wir')) ?></span>
          <span style="color:var(--leise);font-weight:400"><?= $h(Fmt::seit($m['created_at'])) ?></span></div>
        <div class="text"><?= $h((string) $m['body']) ?></div>
      </div>
    <?php endforeach; endif; ?>

    <form method="post" style="margin-top:14px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="nachricht">
      <input type="hidden" name="t" value="<?= $h($token) ?>">
      <input type="hidden" name="lang" value="<?= $h($sprache) ?>">
      <div class="feld"><label><?= $h($T('schreiben')) ?></label><textarea name="text" required></textarea></div>
      <button class="knopf haupt"><?= $h($T('senden')) ?></button>
    </form>
  </div>

  <div class="block">
    <h2><?= $h($T('dateien')) ?></h2>
    <?php if (!$dateien): ?>
      <p style="color:var(--leise);font-size:14px"><?= $h($T('keineDateien')) ?></p>
    <?php else: foreach ($dateien as $d): ?>
      <div class="datei">
        <span><?= $h($d['orig_name']) ?><br>
          <small style="color:var(--leise)"><?= $h(Fmt::bytes((int) $d['size_bytes'])) ?></small></span>
        <a class="knopf" href="?t=<?= $h($token) ?>&amp;lang=<?= $h($sprache) ?>&amp;datei=<?= (int) $d['id'] ?>">↓</a>
      </div>
    <?php endforeach; endif; ?>

    <?php if (Ablage::bereit()): ?>
      <form method="post" enctype="multipart/form-data" style="margin-top:14px">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="datei">
        <input type="hidden" name="t" value="<?= $h($token) ?>">
        <input type="hidden" name="lang" value="<?= $h($sprache) ?>">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= Ablage::grenze() ?>">
        <div class="feld"><label><?= $h($T('hochladen')) ?></label>
          <input type="file" name="datei" required accept="<?= $h(Ablage::endungen()) ?>"></div>
        <p style="color:var(--leise);font-size:12.5px;margin:0 0 10px">
          <?= $h(str_replace('{max}', Fmt::bytes(Ablage::grenze()), $T('dateiHinweis'))) ?></p>
        <button class="knopf"><?= $h($T('senden')) ?></button>
      </form>
    <?php endif; ?>
  </div>

  <p class="sprachen">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $sl => $wort): ?>
      <a class="<?= $sl === $sprache ? 'jetzt' : '' ?>"
         href="?t=<?= $h($token) ?>&amp;lang=<?= $sl ?>"><?= $h($wort) ?></a>
    <?php endforeach; ?>
  </p>
<?php endif; ?>
</div>
</body>
</html>
