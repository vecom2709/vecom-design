<?php
/* Gerät bestätigen bzw. ohne Link anmelden (05.10.2026, PartnerGeraet).
   Erwartet $sprache, $T, $h, $gModus ('geraet' | 'anmelden'), $gSchritt ('start' | 'code'),
   $gStand ('' | gesendet | uwe | warten | grenze | falsch | unbekannt), $gMail (maskiert), $gZiel (Formular-Adresse). */
$gHinweis = ['uwe' => 'geraet_uwe', 'warten' => 'geraet_warten', 'grenze' => 'geraet_grenze', 'falsch' => 'geraet_falsch'][$gStand] ?? '';
?><!doctype html>
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $h($T($gModus === 'anmelden' ? 'anmelden_titel' : 'geraet_titel')) ?> — Vecom Design</title>
<meta name="theme-color" content="#070e21">
<link rel="icon" href="/assets/img/favicon-96.png" sizes="96x96">
<link rel="stylesheet" href="/assets/css/fonts.css">
<link rel="stylesheet" href="/assets/css/kunde.css?v=<?= (int) @filemtime(dirname(__DIR__, 2) . '/assets/css/kunde.css') ?>">
<style>
  .pt{max-width:460px;margin:0 auto}
  .pt h1{font-size:clamp(24px,5vw,30px);margin:0 0 10px;line-height:1.2}
  .pt .lead{color:var(--dim);font-size:15.5px;line-height:1.65;margin:0 0 16px}
  .pt .schloss{width:44px;height:44px;border-radius:50%;display:grid;place-items:center;margin:0 0 14px;
               background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438);color:#16120b}
  .pt .schloss svg{width:22px;height:22px}
  .pt form .knopf{width:100%;justify-content:center;min-height:50px;font-size:16px}
  .pt .code{font-size:28px;letter-spacing:.35em;text-align:center;font-variant-numeric:tabular-nums}
  .pt .nochmal{background:none;border:0;color:var(--cyan);font:inherit;font-size:14px;cursor:pointer;padding:10px 0;text-decoration:underline}
</style>
</head>
<body>
<div class="seite">
  <div class="wortmarke">
    <img src="/assets/img/logo-mark.webp?v=gold2609" alt="" width="58" height="46">
    <span class="wort"><b>VECOM</b> DESIGN</span>
  </div>
  <main class="block pt">
    <div class="schloss" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></div>
    <h1><?= $h($T($gModus === 'anmelden' ? 'anmelden_titel' : 'geraet_titel')) ?></h1>
    <?php if ($gHinweis !== ''): ?><div class="hinweis <?= $gStand === 'warten' ? '' : 'schlecht' ?>" role="alert"><?= $h($T($gHinweis)) ?></div><?php endif; ?>
    <?php if ($gSchritt === 'start' && $gModus === 'anmelden'): ?>
      <p class="lead"><?= $h($T('anmelden_text')) ?></p>
      <form method="post" action="<?= $h($gZiel) ?>">
        <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="anmelden_mail">
        <div class="feld"><label for="g-mail"><?= $h($T('anmelden_mail')) ?></label>
          <input id="g-mail" type="email" name="email" autocomplete="email" required autofocus></div>
        <button class="knopf haupt" type="submit"><?= $h($T('anmelden_knopf')) ?></button>
      </form>
    <?php elseif ($gSchritt === 'start'): ?>
      <p class="lead"><?= $h(strtr($T('geraet_start'), ['{mail}' => $gMail])) ?></p>
      <form method="post" action="<?= $h($gZiel) ?>">
        <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="geraet_neu">
        <button class="knopf haupt" type="submit"><?= $h($T('geraet_senden')) ?></button>
      </form>
    <?php else: ?>
      <p class="lead"><?= $h($gModus === 'anmelden' && $gMail === '' ? $T('anmelden_unbekannt') : strtr($T('geraet_text'), ['{mail}' => $gMail])) ?></p>
      <form method="post" action="<?= $h($gZiel) ?>">
        <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="<?= $gModus === 'anmelden' ? 'anmelden_code' : 'geraet_code' ?>">
        <div class="feld"><label for="g-code"><?= $h($T('geraet_code')) ?></label>
          <input id="g-code" class="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus></div>
        <button class="knopf haupt" type="submit"><?= $h($T('geraet_knopf')) ?></button>
      </form>
      <form method="post" action="<?= $h($gZiel) ?>">
        <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="<?= $gModus === 'anmelden' ? 'anmelden_neu' : 'geraet_neu' ?>">
        <button class="nochmal" type="submit"><?= $h($T('geraet_neu')) ?></button>
      </form>
    <?php endif; ?>
  </main>
  <div class="sprachen">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
      <a class="<?= $l === $sprache ? 'jetzt' : '' ?>" href="<?= $h($gZiel . (str_contains($gZiel, '?') ? '&' : '?') . 'lang=' . $l) ?>"><?= $h($wie) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/src/Fuss.php'; echo Fuss::html($sprache); ?>
</body>
</html>
