<?php /** Rahmen aller Admin-Seiten. */
$navZahlen = [
  'anfragen'    => (int) sicher(fn() => Db::wert("SELECT COUNT(*) FROM anfragen WHERE status IN ('neu','in_arbeit')", [], 0), 0),
  'bedarf'      => (int) sicher(fn() => Db::wert("SELECT COUNT(*) FROM bedarf WHERE status = 'abgesendet'", [], 0), 0),
  'angebote'    => (int) sicher(fn() => Db::wert("SELECT COUNT(*) FROM angebote WHERE status = 'entwurf'", [], 0), 0),
  'empfehlungen'=> (int) sicher(fn() => Db::wert("SELECT COUNT(*) FROM empfehlungen WHERE empfehler_id IS NULL AND status = 'offen'", [], 0), 0),
  'nachrichten' => (int) Db::wert("SELECT COUNT(*) FROM messages WHERE read_at IS NULL AND sender='kunde'"),
  'onboarding'  => (int) Db::wert("SELECT COUNT(*) FROM questionnaires WHERE status='offen'"),
  /* Nur, was wirklich klemmt. Vorher zaehlte hier jede Info-Meldung mit,
     auch die, ueber die man nichts entscheiden muss -- die Zahl stand
     monatelang auf demselben Wert und wurde damit zu Tapete. Eine Zahl im
     Menue soll eine Handlung meinen. */
  'benachrichtigungen' => (int) Db::wert(
      "SELECT COUNT(*) FROM notifications WHERE read_at IS NULL AND level IN ('warnung','schlecht')"),
  'bestellungen'=> (int) Db::wert("SELECT COUNT(*) FROM orders WHERE status IN ('neu','zahlung_ausstehend')"),
];
// Wie viele Vorgaenge gerade auf Uwe warten. Das ist die einzige Zahl im
// Menue, die eine Handlung meint und nicht nur einen Bestand.
$navZahlen['stimmen'] = (int) sicher(static function (): int {
    require_once __DIR__ . '/../src/Stimme.php';
    return Stimme::offene();
}, 0);
/* Einmal rechnen, zweimal benutzt: fuer die Zahl im Menue und fuer die
   Leiste "Jetzt dran". Zweimal rechnen hiesse, jede Seite zweimal durch alle
   Vorgaenge zu schicken. */
$arbeitsliste = (array) sicher(static function (): array {
    require_once dirname(__DIR__) . '/src/Vorgang.php';
    require_once dirname(__DIR__) . '/src/Mail.php';
    return Vorgang::arbeitsliste();
}, ['du' => [], 'kunde' => [], 'ruht' => []]);
$wartetAufDich = $arbeitsliste['du'] ?? [];
$navZahlen['heute'] = count($wartetAufDich);
$aktiv = $route ?: 'heute';

/* ============================================================================
   DAS MENUE IST NACH DER ARBEIT GEORDNET, NICHT NACH TABELLEN

   Vorher standen hier fuenfundzwanzig Eintraege in fuenf Gruppen -- eine
   Zeile je Datenbanktabelle. Sechs davon (Bedarf, Anfragen, Angebote,
   Bestellungen, Projekte, Vorgaenge) waren sechs Blicke auf DENSELBEN Kunden
   zu verschiedenen Momenten. Vorgang::alle() fuehrt sie laengst zu einer
   Wahrheit zusammen; die anderen fuenf sind die rohen Tabellen darunter.

   Oben steht jetzt, womit gearbeitet wird. Die Tabellen stehen weiter unten
   unter "Listen" -- dorthin geht man, wenn man eine bestimmte Zeile sucht,
   nicht wenn man arbeitet. Keine ist verschwunden, keine Adresse hat sich
   geaendert.

   Die Reihenfolge folgt dem Ablauf: Was kommt herein, was ist besprochen,
   was wird gebaut, wer ist es. Nicht dem Alphabet und nicht der Groesse der
   Tabelle.
   ========================================================================= */
$menue = [
  ['heute', 'Heute', 'heute'],
  ['vorgaenge', 'Vorgänge', 'vorgaenge'],
  ['nachrichten', 'Nachrichten', 'nachrichten'],
  ['__gruppe', 'Was hereinkommt', null],
  ['bedarf', 'Bedarf', 'bedarf'],
  ['angebote', 'Angebote', 'angebote'],
  ['empfehlungen', 'Empfehlungen', 'empfehlungen'],
  ['__gruppe', 'Geld', null],
  ['zahlungen', 'Zahlungen', 'zahlungen'],
  ['rechnungen', 'Rechnungen', 'rechnungen'],
  ['ausgaben', 'Ausgaben', 'ausgaben'],
  ['abos', 'Betreuung', 'abos'],
  ['steuerakte', 'Fürs Finanzamt', 'steuerakte'],
  ['__gruppe', 'Was ich anbiete', null],
  ['pakete', 'Pakete', 'pakete'],
  ['baukasten', 'Baukasten', 'baukasten'],
  ['stimmen', 'Kundenstimmen', 'stimmen'],
  /* Die rohen Tabellen. Sie tragen nichts mehr allein -- alles, was auf
     ihnen steht, steht auch auf der einen Seite des Kunden. Hier sucht man
     eine Zeile, dort arbeitet man. */
  ['__gruppe', 'Listen', null],
  ['kunden', 'Kunden', 'kunden'],
  ['bestellungen', 'Bestellungen', 'bestellungen'],
  ['projekte', 'Projekte', 'projekte'],
  ['anfragen', 'Anfragen', 'anfragen'],
  ['onboarding', 'Fragebögen', 'onboarding'],
  ['dateien', 'Dateien', 'dateien'],
  ['__gruppe', 'System', null],
  ['dashboard', 'Zahlen', 'dashboard'],
  ['monitoring', 'Website-Monitoring', 'monitoring'],
  ['aktivitaeten', 'Aktivitäten', 'aktivitaeten'],
  ['benachrichtigungen', 'Benachrichtigungen', 'benachrichtigungen'],
  ['integrationen', 'Integrationen', 'integrationen'],
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
    <?php /* DIE SUCHE STAND NIE IM MENUE
             ------------------------------------------------------------
             Sie war fertig gebaut und ueber /app/suche erreichbar -- wenn
             man die Adresse kannte. Damit war sie fuer alle praktischen
             Zwecke nicht vorhanden, und man suchte weiter in Listen.

             Hier oben ist sie der kuerzeste Weg zu einem bestimmten
             Kunden: Name, Bestellnummer, E-Mail oder Angebotsnummer
             eintippen. Deshalb duerfen die Listen darunter zurueckstehen. */ ?>
    <form class="nav__suche" method="get" action="<?= Fmt::h(url('suche')) ?>" role="search">
      <input type="search" name="q" placeholder="Suchen …" aria-label="Kunde, Bestellung, Angebot"
             value="<?= Fmt::h($route === 'suche' ? (string) ($_GET['q'] ?? '') : '') ?>">
    </form>
    <?php foreach ($menue as [$ziel, $titel, $schl]): ?>
      <?php if ($ziel === '__gruppe'): ?>
        <div class="gruppe"><?= Fmt::h($titel) ?></div>
      <?php else: $n = $navZahlen[$schl] ?? 0; ?>
        <a href="<?= Fmt::h(url($ziel)) ?>" class="<?= $aktiv === ($ziel ?: 'heute') ? 'an' : '' ?>">
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
    <?php
    /* ----------------------------------------------------------------------
       "Jetzt dran" — auf jeder Seite, nicht nur auf Heute.

       Die Verwaltung wusste den naechsten Schritt schon immer: Vorgang
       rechnet Stufe, wer dran ist und welcher Knopf ihn erledigt. Nur stand
       das ausschliesslich auf zwei Seiten. Wer woanders war, musste sich
       erinnern, dass es diese Seiten gibt.

       Deshalb steht der naechste Schritt jetzt ueberall, und der Verweis
       traegt ?tun= mit: Auf der Zielseite leuchtet damit genau der Knopf,
       der gemeint ist. Ohne das findet man auf einer vollen Vorgangsseite
       den richtigen von acht Knoepfen nicht auf Anhieb.
       ---------------------------------------------------------------------- */
    $ersteAufgabe = $wartetAufDich[0] ?? null;
    ?>
    <section class="jetzt <?= $ersteAufgabe ? '' : 'jetzt--leer' ?>" aria-label="Was jetzt zu tun ist">
      <?php if (!$ersteAufgabe): ?>
        <span class="jetzt__ruhe">Nichts offen — alles liegt beim Kunden oder ist erledigt.</span>
      <?php else: ?>
        <?php
          $sch  = $ersteAufgabe['schritt'];
          /* Ein Schritt kann sagen, wohin er gehoert -- der Preis auf die
             Bedarfsseite, das Angebot auf seine eigene. Sagt er nichts, ist
             es die Vorgangsseite, und der gemeinte Knopf steht in der
             Adresse, damit ihn das Skript unten aufleuchten lassen kann. */
          $ziel = ($sch !== null && ($sch['ziel'] ?? null) !== null)
                ? url((string) $sch['ziel'])
                : url('vorgaenge/' . $ersteAufgabe['schluessel'])
                  . ($sch && $sch['tat'] ? '?tun=' . rawurlencode((string) $sch['tat']) : '');
          $rest = count($wartetAufDich) - 1;
        ?>
        <span class="jetzt__marke">Jetzt dran</span>
        <span class="jetzt__wer">
          <b><?= Fmt::h((string) $ersteAufgabe['kunde']) ?></b>
          <span class="jetzt__warum"><?= Fmt::h((string) $ersteAufgabe['warum']) ?></span>
        </span>
        <span class="jetzt__tun">
          <?php if ($sch !== null && $sch['direkt']): ?>
            <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
              <?= Csrf::feld() ?>
              <input type="hidden" name="tat" value="<?= Fmt::h((string) $sch['tat']) ?>">
              <input type="hidden" name="id" value="<?= (int) $sch['id'] ?>">
              <input type="hidden" name="zurueck" value="<?= Fmt::h($route) ?>">
              <?php foreach ($sch['felder'] as $feld => $wert): ?>
                <input type="hidden" name="<?= Fmt::h($feld) ?>" value="<?= Fmt::h((string) $wert) ?>">
              <?php endforeach; ?>
              <button class="knopf haupt"><?= Fmt::h((string) $sch['knopf']) ?></button>
            </form>
          <?php else: ?>
            <a class="knopf haupt" href="<?= Fmt::h($ziel) ?>"><?= Fmt::h($sch !== null ? (string) $sch['knopf'] : 'Öffnen') ?> &rsaquo;</a>
          <?php endif; ?>
          <?php if ($rest > 0): ?>
            <a class="jetzt__rest" href="<?= Fmt::h(url('heute')) ?>">und <?= $rest ?> weitere</a>
          <?php endif; ?>
        </span>
      <?php endif; ?>
    </section>
    <?php require $inhaltsdatei; ?>
  </main>
</div>
<script>
/* Kommt man ueber die Leiste "Jetzt dran", steht der gemeinte Knopf in der
   Adresse. Ihn hier zu suchen ist zuverlaessiger, als ihn beim Bauen jeder
   Seite einzeln zu markieren: Es gibt genau eine Stelle, an der ein Knopf
   seine Handlung nennt, naemlich das versteckte Feld "tat". */
(function () {
  var tun = new URLSearchParams(location.search).get('tun');
  if (!tun) { return; }
  /* Zwei Sorten Ziel. Ein Formular nennt seine Handlung im versteckten Feld
     "tat" -- das ist die zuverlaessigste Marke, die es gibt, weil sie ohnehin
     dastehen muss. Ein ganzer Abschnitt, der kein Formular ist (der fertige
     Preistext auf der Bedarfsseite etwa), sagt es ueber data-tun. */
  /* Die Nummer aus der Adresse trennt gleiche Knoepfe voneinander: drei
     offene Raten tragen dreimal dieselbe Tat, und ohne sie leuchtete immer
     die erste. Fehlt sie oder passt keine, bleibt es beim ersten Treffer --
     so war es vorher, und fuer alles Einmalige stimmt das. */
  var nr = new URLSearchParams(location.search).get('nr');
  var kandidaten = [].slice.call(
    document.querySelectorAll('input[name="tat"][value="' + CSS.escape(tun) + '"]'));
  var feld = null;
  if (nr) {
    for (var i = 0; i < kandidaten.length; i++) {
      var fm = kandidaten[i].closest('form');
      var kennung = fm ? fm.querySelector('[name="id"]') : null;
      if (kennung && kennung.value === nr) { feld = kandidaten[i]; break; }
    }
  }
  if (!feld) { feld = kandidaten[0] || null; }
  var ausFeld = !!(feld && feld.closest('form'));
  var ziel = ausFeld ? feld.closest('form') : document.querySelector('[data-tun="' + CSS.escape(tun) + '"]');
  if (!ziel) { return; }
  ziel.classList.add('leuchtet');
  /* Mittig ist richtig fuer einen Knopf und falsch fuer einen ganzen
     Abschnitt: Ist der hoeher als das Fenster, liegen beide Kanten -- und
     damit der leuchtende Rahmen -- ausserhalb, und man sieht nichts.
     Deshalb oben ansetzen, sobald es eng wird. */
  var hoch = ziel.getBoundingClientRect().height > innerHeight * 0.8;
  ziel.scrollIntoView({
    block: hoch ? 'start' : 'center',
    behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'
  });
  /* Den Knopf nur dann vorwaehlen, wenn er wirklich der Handgriff ist. In
     einem Abschnitt mit fertig getipptem Text waere der erste Knopf
     "Senden" -- und ein versehentliches Enter haette die Nachricht
     ungelesen verschickt. */
  if (ausFeld) {
    var knopf = ziel.querySelector('button, input[type=submit]');
    if (knopf) { knopf.focus({ preventScroll: true }); }
  }
})();
</script>
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
