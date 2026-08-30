<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Anmelden — Vecom Design</title>
<link rel="stylesheet" href="<?= Fmt::h(url('assets/admin.css')) ?>">
</head>
<body>
<div class="anmeldung">
  <div class="marke" style="justify-content:center;font-size:18px"><b>VECOM</b>&nbsp;Verwaltung</div>
  <div class="block">
    <?php if (!empty($fehler)): ?><div class="hinweis schlecht"><?= Fmt::h($fehler) ?></div><?php endif; ?>
    <form method="post">
      <?= Csrf::feld() ?>
      <div class="feld"><label>E-Mail</label><input type="email" name="email" autocomplete="username" required autofocus></div>
      <div class="feld"><label>Passwort</label><input type="password" name="passwort" autocomplete="current-password" required></div>
      <button class="knopf haupt" style="width:100%;justify-content:center">Anmelden</button>
    </form>
  </div>
</div>
</body>
</html>
