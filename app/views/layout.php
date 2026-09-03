<?php /** Rahmen aller Admin-Seiten. */
$navZahlen = [
  'anfragen'    => (int) sicher(fn() => Db::wert("SELECT COUNT(*) FROM anfragen WHERE status IN ('neu','in_arbeit')", [], 0), 0),
  'bedarf'      => (int) sicher(fn() => Db::wert("SELECT COUNT(*) FROM bedarf WHERE status = 'abgesendet'", [], 0), 0),
  'angebote'    => (int) sicher(fn() => Db::wert("SELECT COUNT(*) FROM angebote WHERE status = 'entwurf'", [], 0), 0),
  'empfehlungen'=> (int) sicher(fn() => Db::wert("SELECT COUNT(*) FROM empfehlungen WHERE empfehler_id IS NULL AND status = 'offen'", [], 0), 0),
  'nachrichten' => (int) Db::wert("SELECT COUNT(*) FROM messages WHERE read_at IS NULL AND sender='kunde'"),
  'onboarding'  => (int) Db::wert("SELECT COUNT(*) FROM questionnaires WHERE status='offen'"),
  'benachrichtigungen' => (int) Db::wert('SELECT COUNT(*) FROM notifications WHERE read_at IS NULL'),
  'bestellungen'=> (int) Db::wert("SELECT COUNT(*) FROM orders WHERE status IN ('neu','zahlung_ausstehend')"),
];
// Wie viele Vorgaenge gerade auf Uwe warten. Das ist die einzige Zahl im
// Menue, die eine Handlung meint und nicht nur einen Bestand.
$navZahlen['stimmen'] = (int) sicher(static function (): int {
    require_once __DIR__ . '/../src/Stimme.php';
    return Stimme::offene();
}, 0);
$navZahlen['heute'] = (int) sicher(static function (): int {
    require_once dirname(__DIR__) . '/src/Vorgang.php';
    require_once dirname(__DIR__) . '/src/Mail.php';
    return count(Vorgang::arbeitsliste()['du']);
}, 0);
$aktiv = $route ?: 'dashboard';
$menue = [
  ['heute', 'Heute', 'heute'],
  ['vorgaenge', 'Vorgänge', 'vorgaenge'],
  ['', 'Dashboard', 'dashboard'],
  ['__gruppe', 'Geschäft', null],
  ['pakete', 'Pakete', 'pakete'],
  ['baukasten', 'Baukasten', 'baukasten'],
  ['angebote', 'Angebote', 'angebote'],
  ['bestellungen', 'Bestellungen', 'bestellungen'],
  ['projekte', 'Projekte', 'projekte'],
  ['kunden', 'Kunden', 'kunden'],
  ['__gruppe', 'Kontakt', null],
  ['bedarf', 'Bedarf', 'bedarf'],
  ['empfehlungen', 'Empfehlungen', 'empfehlungen'],
  ['anfragen', 'Anfragen', 'anfragen'],
  ['nachrichten', 'Nachrichten', 'nachrichten'],
  ['onboarding', 'Fragebögen', 'onboarding'],
  ['dateien', 'Dateien', 'dateien'],
  ['__gruppe', 'Geld', null],
  ['zahlungen', 'Zahlungen', 'zahlungen'],
  ['rechnungen', 'Rechnungen', 'rechnungen'],
  ['ausgaben', 'Ausgaben', 'ausgaben'],
  ['abos', 'Betreuung', 'abos'],
  ['stimmen', 'Kundenstimmen', 'stimmen'],
  ['steuerakte', 'Fürs Finanzamt', 'steuerakte'],
  ['statistiken', 'Statistiken', 'statistiken'],
  ['__gruppe', 'System', null],
  ['aktivitaeten', 'Aktivitäten', 'aktivitaeten'],
  ['benachrichtigungen', 'Benachrichtigungen', 'benachrichtigungen'],
  ['integrationen', 'Integrationen', 'integrationen'],
  ['monitoring', 'Website-Monitoring', 'monitoring'],
  ['einstellungen', 'Einstellungen', 'einstellungen'],
];
$fehler = $_SESSION['fehler'] ?? null; unset($_SESSION['fehler']);
$gut    = $_SESSION['gut']    ?? null; unset($_SESSION['gut']);
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
    <?php if ($gut): ?><div class="hinweis gut"><?= Fmt::h($gut) ?></div><?php endif; ?>
    <?php
    /* Solange Beispieldaten geladen sind, muss das auf jeder Seite zu sehen
       sein — sonst haelt man erfundene Umsaetze fuer die eigenen. */
    $beispielZahl = 0;
    try { $beispielZahl = (int) Db::wert('SELECT COUNT(*) FROM customers WHERE demo = 1'); } catch (Throwable $e) { }
    ?>
    <?php
    /* Der Stand kommt aus dem regelmaessigen Lauf, nicht aus einer Anfrage
       bei jedem Seitenaufruf. Steht er auf "nein", ist das wichtig genug,
       um ueberall zu stehen. */
    $cockpitOffen = false;
    try { $cockpitOffen = (string) Db::wert("SELECT svalue FROM settings WHERE skey='cockpit_geschuetzt'", [], '') === 'nein'; }
    catch (Throwable $e) { }
    ?>
    <?php if ($cockpitOffen && $aktiv !== 'einstellungen'): ?>
      <div class="hinweis schlecht" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <span style="flex:1;min-width:240px"><b>Das Cockpit steht offen.</b> Jeder, der die Adresse kennt, sieht deine Zahlen.</span>
        <a class="knopf" href="<?= Fmt::h(url('einstellungen')) ?>">Schützen</a>
      </div>
    <?php endif; ?>
    <?php if ($beispielZahl > 0): ?>
      <div class="hinweis" style="background:rgba(251,191,36,.12);border-color:rgba(251,191,36,.35);color:var(--gelb);display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <span style="flex:1;min-width:240px">Beispieldaten geladen (<?= $beispielZahl ?> Kunden) — die Zahlen sind noch nicht deine echten.</span>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="beispiel_loeschen">
          <input type="hidden" name="zurueck" value="<?= Fmt::h($aktiv === 'dashboard' ? '' : $aktiv) ?>">
          <button class="knopf">Löschen</button></form>
      </div>
    <?php endif; ?>
    <?php
    /* Aktualisierungen laufen beim Oeffnen der Verwaltung von allein. Dieser
       Hinweis erscheint also nur noch, wenn dabei etwas schiefgegangen ist —
       dann ist der Knopf der zweite Anlauf. */
    require_once __DIR__ . '/../src/Einrichtung.php';
    $offeneMigrationen = Einrichtung::offene();
    if ($offeneMigrationen): ?>
      <div class="hinweis" style="background:rgba(251,191,36,.12);border-color:rgba(251,191,36,.35);color:var(--gelb);display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <span><?= count($offeneMigrationen) ?> Aktualisierung<?= count($offeneMigrationen) === 1 ? '' : 'en' ?> der Datenbank ist nicht durchgelaufen. Ein zweiter Versuch:</span>
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
