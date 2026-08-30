<?php /** Rahmen aller Admin-Seiten. */
$navZahlen = [
  'nachrichten' => (int) Db::wert("SELECT COUNT(*) FROM messages WHERE read_at IS NULL AND sender='kunde'"),
  'onboarding'  => (int) Db::wert("SELECT COUNT(*) FROM questionnaires WHERE status='offen'"),
  'benachrichtigungen' => (int) Db::wert('SELECT COUNT(*) FROM notifications WHERE read_at IS NULL'),
  'bestellungen'=> (int) Db::wert("SELECT COUNT(*) FROM orders WHERE status IN ('neu','zahlung_ausstehend')"),
];
$aktiv = $route ?: 'dashboard';
$menue = [
  ['', 'Dashboard', 'dashboard'],
  ['__gruppe', 'Geschäft', null],
  ['pakete', 'Pakete', 'pakete'],
  ['bestellungen', 'Bestellungen', 'bestellungen'],
  ['projekte', 'Projekte', 'projekte'],
  ['kunden', 'Kunden', 'kunden'],
  ['__gruppe', 'Kontakt', null],
  ['nachrichten', 'Nachrichten', 'nachrichten'],
  ['onboarding', 'Fragebögen', 'onboarding'],
  ['dateien', 'Dateien', 'dateien'],
  ['__gruppe', 'Geld', null],
  ['zahlungen', 'Zahlungen', 'zahlungen'],
  ['rechnungen', 'Rechnungen', 'rechnungen'],
  ['statistiken', 'Statistiken', 'statistiken'],
  ['__gruppe', 'System', null],
  ['aktivitaeten', 'Aktivitäten', 'aktivitaeten'],
  ['benachrichtigungen', 'Benachrichtigungen', 'benachrichtigungen'],
  ['integrationen', 'Integrationen', 'integrationen'],
  ['monitoring', 'Website-Monitoring', 'monitoring'],
  ['einstellungen', 'Einstellungen', 'einstellungen'],
];
$fehler = $_SESSION['fehler'] ?? null; unset($_SESSION['fehler']);
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Vecom Design — Verwaltung</title>
<link rel="stylesheet" href="<?= Fmt::h(url('assets/admin.css')) ?>">
</head>
<body>
<div class="huelle">
  <nav class="nav">
    <div class="marke"><b>VECOM</b> Verwaltung</div>
    <?php foreach ($menue as [$ziel, $titel, $schl]): ?>
      <?php if ($ziel === '__gruppe'): ?>
        <div class="gruppe"><?= Fmt::h($titel) ?></div>
      <?php else: $n = $navZahlen[$schl] ?? 0; ?>
        <a href="<?= Fmt::h(url($ziel)) ?>" class="<?= $aktiv === ($ziel ?: 'dashboard') ? 'an' : '' ?>">
          <span><?= Fmt::h($titel) ?></span>
          <?php if ($n > 0): ?><span class="zahl warn"><?= $n ?></span><?php endif; ?>
        </a>
      <?php endif; ?>
    <?php endforeach; ?>
    <div class="gruppe"><?= Fmt::h(Auth::name()) ?></div>
    <a href="/cockpit/">Zum Cockpit</a>
    <a href="<?= Fmt::h(url('abmelden')) ?>">Abmelden</a>
  </nav>
  <main class="inhalt">
    <?php if ($fehler): ?><div class="hinweis schlecht"><?= Fmt::h($fehler) ?></div><?php endif; ?>
    <?php
    /* Neue Tabellen oder Spalten aus einer Aktualisierung. Ohne SSH liesse sich
       das sonst nicht einspielen. Nur der angemeldete Admin sieht den Knopf,
       und er ist wie jedes Formular gegen Fremdaufrufe geschuetzt. */
    require_once __DIR__ . '/../src/Einrichtung.php';
    $offeneMigrationen = Einrichtung::offene();
    if ($offeneMigrationen): ?>
      <div class="hinweis" style="background:rgba(31,232,255,.08);border-color:rgba(31,232,255,.32);color:var(--text);display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <span>Die Datenbank ist nicht auf dem neuesten Stand — <?= count($offeneMigrationen) ?> Aktualisierung<?= count($offeneMigrationen) === 1 ? '' : 'en' ?> steht bereit.</span>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-left:auto">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="migrieren">
          <input type="hidden" name="zurueck" value="<?= Fmt::h($route) ?>">
          <button class="knopf haupt">Jetzt aktualisieren</button>
        </form>
      </div>
    <?php endif; ?>
    <?php require $inhaltsdatei; ?>
  </main>
</div>
<script>
/* Laufende Aktualisierung: fragt alle 20 Sekunden nur wenige Zahlen ab und
   laedt die Seite erst neu, wenn sich wirklich etwas geaendert hat. */
(function () {
  var stand = null, url = <?= json_encode(url('puls')) ?>;
  setInterval(function () {
    if (document.hidden) { return; }
    fetch(url, {cache: 'no-store'}).then(function (r) { return r.json(); }).then(function (d) {
      var jetzt = [d.meldungen, d.nachrichten, d.bestellungen, d.letzte].join('|');
      if (stand !== null && jetzt !== stand) { location.reload(); }
      stand = jetzt;
    }).catch(function () {});
  }, 20000);
})();
</script>
</body>
</html>
