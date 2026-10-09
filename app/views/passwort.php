<?php /* Passwort vergessen (09.10.2026): dieselbe Seite für Anfordern und Setzen. */ ?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Passwort — Vecom Design</title>
<link rel="stylesheet" href="<?= Fmt::h(url('assets/admin.css')) ?>">
</head>
<body>
<div class="anmeldung">
  <div class="marke" style="justify-content:center;font-size:18px"><b>VECOM</b>&nbsp;Verwaltung</div>
  <div class="block">
  <?php if ($route === 'passwort-vergessen'): ?>
    <?php if (!empty($fertig)): ?>
      <div class="hinweis">Wenn die Adresse zur Verwaltung gehört, ist eine Mail mit dem Link unterwegs. Er gilt <?= Auth::LINK_MINUTEN ?> Minuten.</div>
    <?php else: ?>
      <p style="margin:0 0 14px">Trag deine E-Mail ein. Du bekommst einen Link, mit dem du dir selbst ein neues Passwort setzt.</p>
      <form method="post">
        <?= Csrf::feld() ?>
        <div class="feld"><label>E-Mail</label><input type="email" name="email" autocomplete="username" required autofocus></div>
        <button class="knopf haupt" style="width:100%;justify-content:center">Link schicken</button>
      </form>
    <?php endif; ?>
  <?php else: ?>
    <?php if (!empty($fehler)): ?><div class="hinweis schlecht"><?= Fmt::h($fehler) ?></div><?php endif; ?>
    <?php if (!empty($linkGilt)): ?>
      <form method="post">
        <?= Csrf::feld() ?><input type="hidden" name="t" value="<?= Fmt::h($token) ?>">
        <div class="feld"><label>Neues Passwort (mindestens 10 Zeichen)</label><input type="password" name="neu1" autocomplete="new-password" minlength="10" required autofocus></div>
        <div class="feld"><label>Noch einmal</label><input type="password" name="neu2" autocomplete="new-password" minlength="10" required></div>
        <button class="knopf haupt" style="width:100%;justify-content:center">Passwort setzen</button>
      </form>
    <?php elseif (empty($fehler)): ?>
      <div class="hinweis schlecht">Der Link ist abgelaufen oder schon benutzt.</div>
    <?php endif; ?>
  <?php endif; ?>
  </div>
  <p style="margin:14px 0 0;text-align:center;font-size:14px"><a href="<?= Fmt::h(url('anmelden')) ?>">Zur Anmeldung</a><?php if ($route === 'passwort-neu'): ?> · <a href="<?= Fmt::h(url('passwort-vergessen')) ?>">Neuen Link anfordern</a><?php endif; ?></p>
</div>
</body>
</html>
