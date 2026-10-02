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
    foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'Sprache', 'AkquiseAnalyse', 'AkquiseEinwilligung', 'Ablage', 'Texte', 'WebBericht'] as $k) {
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

/* Skizze (26.09.2026): vorschau.js ist ein Modul von hier, holt ecken.json
   und die Branchenbilder von hier und setzt Stile per Attribut -- deshalb
   script/connect/font 'self' und style 'unsafe-inline'. Formulare nur an
   uns selbst (einwilligung.php). Nichts von fremden Servern. */
header("Content-Security-Policy: default-src 'none'; img-src 'self' blob:; style-src 'self' 'unsafe-inline'; font-src 'self'; script-src 'self'; connect-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
if ($daten === null) {
    http_response_code(404);
    ?><!doctype html><html lang="it"><head><meta charset="utf-8">
<?= Sprache::skript() ?><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow"><title>Vecom Design</title>
    <style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#0a0908;color:#c9c1b3;font:17px/1.5 system-ui,sans-serif;padding:16px;text-align:center}</style></head>
    <body><p>Questa pagina non è (più) disponibile. · Diese Seite ist nicht (mehr) verfügbar.<br><a style="color:#f1d38b" href="https://vecom-design.it">vecom-design.it</a></p></body></html><?php
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
    'de' => ['skizze' => 'So könnte Ihre neue Startseite aussehen', 'skizzeText' => 'Eine automatisch gesetzte Skizze mit Ihrem Namen — kein fertiger Entwurf. Den machen wir mit Ihnen gemeinsam.', 'einwTitel' => 'Lieber per E-Mail?', 'einwText' => 'Wenn Sie möchten, schicken wir Ihnen die Auswertung und unsere Vorschläge per E-Mail. Sie bekommen zuerst eine Bestätigungsmail — erst nach Ihrem Klick schreiben wir Ihnen.', 'einwEmail' => 'Ihre E-Mail-Adresse', 'einwKnopf' => 'Bestätigungsmail anfordern', 'einwDa' => 'Sie haben bereits zugestimmt — danke. Wir melden uns per E-Mail.', 'kopf' => 'Website-Analyse', 'fuer' => 'Persönlich erstellt für', 'stand' => 'Stand der Prüfung', 'gesehen' => 'Was uns aufgefallen ist',
             'warum' => 'Warum das wichtig sein kann', 'loesung' => 'Wie wir es lösen würden', 'idee' => 'Eine Idee darüber hinaus',
             'cta1' => 'Kostenlose Projektbesprechung anfragen', 'cta2' => 'In 2 Minuten den Bedarf ermitteln', 'foto' => 'So sieht Ihre Startseite auf dem Smartphone aus (zum Zeitpunkt der Prüfung).',
             'hinweis' => 'Diese Seite ist nur über Ihren persönlichen Link erreichbar und nicht in Suchmaschinen gelistet.'],
    'it' => ['skizze' => 'Ecco come potrebbe apparire la vostra nuova home page', 'skizzeText' => 'Una bozza impostata automaticamente con il vostro nome — non un progetto finito. Quello lo facciamo insieme a voi.', 'einwTitel' => 'Preferisce via e-mail?', 'einwText' => 'Se vuole, le inviamo l’analisi e le nostre proposte via e-mail. Prima riceve un’e-mail di conferma — le scriviamo solo dopo il suo clic.', 'einwEmail' => 'Il suo indirizzo e-mail', 'einwKnopf' => 'Ricevere l’e-mail di conferma', 'einwDa' => 'Ha già dato il consenso — grazie. La contatteremo via e-mail.', 'kopf' => 'Analisi del sito', 'fuer' => 'Preparata per', 'stand' => 'Data della verifica', 'gesehen' => 'Cosa abbiamo notato',
             'warum' => 'Perché può essere importante', 'loesung' => 'Come lo risolveremmo', 'idee' => 'Un’idea in più',
             'cta1' => 'Richiedi una consulenza gratuita', 'cta2' => 'Calcola le tue esigenze in 2 minuti', 'foto' => 'Così appare la vostra home page su smartphone (al momento della verifica).',
             'hinweis' => 'Questa pagina è raggiungibile solo tramite il vostro link personale e non è indicizzata dai motori di ricerca.'],
    'en' => ['skizze' => 'This is how your new home page could look', 'skizzeText' => 'An automatically set sketch with your name — not a finished design. We create that together with you.', 'einwTitel' => 'Prefer email?', 'einwText' => 'If you like, we will email you the analysis and our suggestions. You first receive a confirmation email — we only write after your click.', 'einwEmail' => 'Your email address', 'einwKnopf' => 'Request confirmation email', 'einwDa' => 'You have already agreed — thank you. We will be in touch by email.', 'kopf' => 'Website analysis', 'fuer' => 'Prepared for', 'stand' => 'Checked on', 'gesehen' => 'What we noticed',
             'warum' => 'Why it can matter', 'loesung' => 'How we would solve it', 'idee' => 'One idea beyond that',
             'cta1' => 'Request a free project call', 'cta2' => 'Work out your needs in 2 minutes', 'foto' => 'This is how your home page looks on a smartphone (at the time of the check).',
             'hinweis' => 'This page is only reachable via your personal link and is not listed in search engines.'],
][$s] ?? [];
$skizzeBranche = AkquiseAnalyse::skizze((string) ($f['branche'] ?? ''));
$einwilligungDa = trim((string) ($f['einwilligung'] ?? '')) !== '';
$einwWortlaut = AkquiseEinwilligung::wortlaut($s);
$mail = 'mailto:kontakt@vecom-design.it?subject=' . rawurlencode($T['kopf'] . ' — ' . $f['name']);
?><!doctype html>
<html lang="<?= $h($s) ?>" <?= Sprache::marken($s) ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow, noarchive">
<title><?= $h($T['kopf'] . ' · ' . $f['name']) ?> — Vecom Design</title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<?php if ($skizzeBranche): ?><link rel="stylesheet" href="/assets/css/vorschau.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/vorschau.css') ?>"><?php endif; ?>
<style>
  :root { color-scheme: dark; --g:#0a0908; --f:#14110d; --l:rgba(241,211,139,.16); --t:#f7f3ea; --d:#b4ada2; --b:#f1d38b; --c:#f1d38b; }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--g) radial-gradient(70% 45% at 70% 0%, rgba(241,211,139,.10), transparent 70%); color: var(--t);
         font: 17px/1.6 'Inter', system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
  .w { max-width: 880px; margin: 0 auto; padding: 32px 16px 64px; }
  .marke { display: flex; gap: 8px; align-items: center; letter-spacing: .12em; font-weight: 800; font-size: 13px; font-family: 'Archivo', sans-serif; }
  .marke img { width: 40px; height: auto; } .marke b { color: var(--b); }
  h1 { font-family: 'Archivo', sans-serif; font-size: clamp(30px, 6vw, 46px); line-height: 1.1; margin: 14px 0 8px; letter-spacing: -.02em; }
  .unter { color: var(--d); margin: 0 0 28px; }
  .raster { display: grid; grid-template-columns: 1fr 260px; gap: 28px; align-items: start; }
  .foto img { width: 100%; height: auto; border-radius: 22px; border: 1px solid var(--l); display: block; }
  .foto p { color: var(--d); font-size: 13px; margin: 8px 2px 0; }
  .punkt { background: var(--f); border: 1px solid var(--l); border-radius: 16px; padding: 20px 20px 18px; margin-bottom: 14px; }
  .punkt h3 { margin: 0 0 8px; font-size: 18px; line-height: 1.35; }
  .punkt .n { display: inline-grid; place-items: center; width: 28px; height: 28px; border-radius: 50%; background: linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438); color: #16120b; font-weight: 700; font-size: 14px; margin-right: 8px; }
  .punkt dl { margin: 0; } .punkt dt { color: var(--b); font-size: 12px; letter-spacing: .08em; text-transform: uppercase; margin-top: 10px; }
  .punkt dd { margin: 2px 0 0; color: var(--d); }
  h2 { font-size: 15px; letter-spacing: .1em; text-transform: uppercase; color: var(--d); margin: 34px 0 14px; }
  .idee { border-left: 3px solid var(--c); padding: 4px 0 4px 16px; color: var(--t); }
  .cta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 30px; }
  .cta a { flex: 1 1 260px; text-align: center; padding: 16px 18px; border-radius: 12px; text-decoration: none; font-weight: 650; min-height: 52px; }
  .cta .a { background: linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438); color: #16120b; } .cta .b { border: 1px solid var(--l); color: var(--t); background: var(--f); }
  .fuss { color: #8b847a; font-size: 13px; margin-top: 40px; }
  a:focus-visible, button:focus-visible, input:focus-visible { outline: 3px solid #f1d38b; outline-offset: 2px; }
  .skizze p { color: var(--d); font-size: 14px; margin: 10px 2px 0; }
  .skizze .vorschau__buehne { border-radius: 16px; border-color: var(--l); }
  .einw { background: var(--f); border: 1px solid var(--l); border-radius: 16px; padding: 20px; margin-top: 30px; }
  .einw h2 { margin-top: 0; }
  .einw p { color: var(--d); margin: 0 0 12px; }
  .einw label { display: block; font-size: 14px; color: var(--d); margin: 8px 0 6px; }
  .einw input[type=email] { width: 100%; font: inherit; padding: 12px 14px; border-radius: 10px; border: 1px solid rgba(241,211,139,.3); background: #0d0c0b; color: var(--t); }
  .einw .haken { display: flex; gap: 10px; align-items: flex-start; font-size: 14.5px; line-height: 1.5; color: var(--t); margin: 14px 0; }
  .einw .haken input { margin-top: 4px; width: 18px; height: 18px; flex: none; }
  .einw button { width: 100%; min-height: 50px; border: 0; border-radius: 12px; font: 650 16px/1 'Inter', system-ui, sans-serif; color: var(--t); cursor: pointer; background: transparent; border: 1px solid rgba(241,211,139,.55); }
  .einw button:hover { background: rgba(241,211,139,.08); }
  .einw .wabe { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
  @media (max-width: 760px) { .raster { grid-template-columns: 1fr; } .foto { max-width: 300px; margin: 0 auto; } }
</style>
</head>
<body>
<div class="w">
  <div class="marke"><img src="/assets/img/logo-mark.webp?v=gold2609" alt="" width="40" height="32"><span><b>VECOM</b> DESIGN</span></div>
  <h1><?= $h($T['kopf']) ?>: <?= $h((string) $f['name']) ?></h1>
  <p class="unter"><?= $h($T['fuer']) ?> <?= $h((string) $f['name']) ?> · <?= $h((string) ($f['domain'] ?? '')) ?> ·
    <?= $h($T['stand']) ?> <?= $h(date('d.m.Y', strtotime((string) $a['beendet_am']))) ?></p>

  <?php /* Der ausführliche Bericht (28.09.2026, A1–A10): Note, Ladezeit, zwölf Punkte, Google, Vergleich, Rechner.
           Das Handyfoto mit Markierungen nur, wenn der PC Stellen gemessen hat -- sonst steht es rechts wie bisher. */
        $wbB = WebBericht::fuerFirma((int) $f['id'], 60);
        if ($wbB !== null):
          $wbMarken = WebBericht::marken($a);
          $wb = ['kc' => $wbB['kc'], 'sprache' => $s, 'token' => $wbB['token'], 'firma' => $f,
                 'bild' => $wbMarken && !empty($a['screenshot_mobil']) ? 'analyse.php?t=' . $token . '&bild=1' : null, 'marken' => $wbMarken,
                 'vergleich' => WebBericht::vergleich((int) $f['id'], $s), 'seite' => 'analyse.php?t=' . $token];
          require __DIR__ . '/app/views/web_bericht.php'; ?>
  <?php endif; ?>

  <div class="raster">
    <div>
      <?php if ($punkte): ?><h2><?= $h($T['gesehen']) ?></h2><?php endif; ?>
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

  <?php if ($skizzeBranche): ?>
    <h2><?= $h($T['skizze']) ?></h2>
    <div class="skizze">
      <div data-vorschau-fest data-name="<?= $h((string) $f['name']) ?>" data-branche="<?= $h($skizzeBranche) ?>" data-ort="<?= $h((string) ($f['stadt'] ?? '')) ?>">
        <div class="vorschau__buehne" data-vorschau-buehne aria-hidden="true" hidden>
          <div class="vorschau__innen" data-vorschau-innen>
            <img class="vorschau__foto" src="/assets/img/arbeiten/cavaleri/an.webp?v=<?= (int) @filemtime(__DIR__ . '/assets/img/arbeiten/cavaleri/an.webp') ?>" alt="" width="2400" height="1350" loading="lazy" decoding="async">
            <div class="vorschau__schirm" data-schirm="laptop"></div>
            <div class="vorschau__schirm vorschau__schirm--telefon" data-schirm="telefon"></div>
            <img class="vorschau__glanz" src="/assets/img/arbeiten/cavaleri/aus.webp?v=<?= (int) @filemtime(__DIR__ . '/assets/img/arbeiten/cavaleri/aus.webp') ?>" alt="" width="2400" height="1350" loading="lazy" decoding="async">
          </div>
        </div>
      </div>
      <p><?= $h($T['skizzeText']) ?></p>
    </div>
  <?php endif; ?>

  <div class="cta">
    <a class="a" href="<?= $h($mail) ?>"><?= $h($T['cta1']) ?></a>
    <a class="b" href="https://vecom-design.it/zugang.php?lang=<?= $h($s) ?>"><?= $h($T['cta2']) ?></a>
  </div>
  <div class="einw" id="email">
    <h2><?= $h($T['einwTitel']) ?></h2>
    <?php if ($einwilligungDa): ?>
      <p><?= $h($T['einwDa']) ?></p>
    <?php else: ?>
      <p><?= $h($T['einwText']) ?></p>
      <form method="post" action="/einwilligung.php">
        <input type="hidden" name="a" value="<?= $h($token) ?>"><input type="hidden" name="l" value="<?= $h($s) ?>">
        <div class="wabe" aria-hidden="true"><input type="text" name="webseite" tabindex="-1" autocomplete="off"></div>
        <label for="e_mail"><?= $h($T['einwEmail']) ?></label>
        <input id="e_mail" type="email" name="email" required autocomplete="email" inputmode="email">
        <label class="haken"><input type="checkbox" name="ja" value="1" required> <span><?= $h($einwWortlaut) ?></span></label>
        <button type="submit"><?= $h($T['einwKnopf']) ?></button>
      </form>
    <?php endif; ?>
  </div>

  <p class="fuss"><?= $h($T['hinweis']) ?> · <a style="color:#f1d38b" href="https://vecom-design.it">vecom-design.it</a></p>
</div>
<?php if ($skizzeBranche): ?><script type="module" src="/assets/js/vorschau.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/vorschau.js') ?>"></script><?php endif; ?>
</body>
</html>
