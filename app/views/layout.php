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
   SIEBEN EINTRAEGE, DER REST EINEN KLICK ENTFERNT

   Hier standen achtundzwanzig Eintraege in sechs Gruppen. Das war schon der
   zweite Anlauf -- davor waren es fuenfundzwanzig flache, eine Zeile je
   Datenbanktabelle. Besser geordnet, aber immer noch eine Wand, in der die
   vier Seiten, auf denen wirklich gearbeitet wird, untergehen.

   Oben stehen jetzt sieben. Alles andere steht darunter unter "Alles
   andere", aufklappbar, in denselben Gruppen und derselben Reihenfolge wie
   vorher. Keine Seite ist verschwunden, keine Adresse hat sich geaendert --
   wer /app/steuerakte im Lesezeichen hat, kommt weiter dorthin.

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
/* Was jeden Tag gebraucht wird. Alles Weitere steht unten aufgeklappt --
   erreichbar in einem Klick, aber nicht im Weg. */
$menue = [
  ['heute', 'Heute', 'heute'],
  ['vorgaenge', 'Vorgänge', 'vorgaenge'],
  ['nachrichten', 'Nachrichten', 'nachrichten'],
  ['werkstatt', 'Werkstatt', 'werkstatt'],
  ['bedarf', 'Bedarf', 'bedarf'],
  ['angebote', 'Angebote', 'angebote'],
  ['rechnungen', 'Rechnungen', 'rechnungen'],
];

/* Der Rest. Die Gruppen sind dieselben wie vorher, in derselben Reihenfolge
   -- wer sie kennt, findet sie wieder. */
$menueMehr = [
  ['__gruppe', 'Was hereinkommt', null],
  ['empfehlungen', 'Empfehlungen', 'empfehlungen'],
  ['anfragen', 'Anfragen', 'anfragen'],
  ['__gruppe', 'Was gebaut wird', null],
  ['standard', 'Vecom-Standard', 'standard'],
  ['muster', 'Bausteine', 'muster'],
  ['onboarding', 'Fragebögen', 'onboarding'],
  ['__gruppe', 'Geld', null],
  ['zahlungen', 'Zahlungen', 'zahlungen'],
  ['ausgaben', 'Ausgaben', 'ausgaben'],
  ['abos', 'Betreuung', 'abos'],
  ['steuerakte', 'Fürs Finanzamt', 'steuerakte'],
  ['__gruppe', 'Was ich anbiete', null],
  ['pakete', 'Pakete', 'pakete'],
  ['baukasten', 'Baukasten', 'baukasten'],
  ['stimmen', 'Kundenstimmen', 'stimmen'],
  ['__gruppe', 'Listen', null],
  ['kunden', 'Kunden', 'kunden'],
  ['bestellungen', 'Bestellungen', 'bestellungen'],
  ['projekte', 'Projekte', 'projekte'],
  ['dateien', 'Dateien', 'dateien'],
  ['__gruppe', 'System', null],
  ['dashboard', 'Zahlen', 'dashboard'],
  ['monitoring', 'Website-Monitoring', 'monitoring'],
  ['aktivitaeten', 'Aktivitäten', 'aktivitaeten'],
  ['benachrichtigungen', 'Benachrichtigungen', 'benachrichtigungen'],
  ['integrationen', 'Integrationen', 'integrationen'],
  ['einstellungen', 'Einstellungen', 'einstellungen'],
];

/* NICHTS DARF STILL VERSCHWINDEN
   --------------------------------------------------------------------------
   Ein eingeklappter Eintrag mit offenen Posten waere schlimmer als ein langes
   Menue: Man sieht die Zahl nicht mehr und haelt die Null fuer die Wahrheit.
   Deshalb traegt "Alles andere" die Summe dessen, was darunter offen ist --
   und klappt von selbst auf, wenn man gerade darin arbeitet. */
$mehrZahl = 0;
$mehrAktiv = false;
foreach ($menueMehr as [$ziel, $titel, $schl]) {
    if ($ziel === '__gruppe') { continue; }
    $mehrZahl += (int) ($navZahlen[$schl] ?? 0);
    if ($aktiv === $ziel) { $mehrAktiv = true; }
}
$fehler = $_SESSION['fehler'] ?? null; unset($_SESSION['fehler']);
$gut    = $_SESSION['gut']    ?? null; unset($_SESSION['gut']);
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Vecom Design — Verwaltung</title>
<?php
/* EIN STRICH HINTER DER ADRESSE, DAMIT NEUE GESTALTUNG AUCH ANKOMMT
   ----------------------------------------------------------------------
   Der Server schickt die Stildatei mit langer Haltbarkeit -- richtig so,
   sie aendert sich selten. Nur heisst das: Wenn sie sich doch aendert,
   sieht man es nicht. Der Browser nimmt weiter die alte aus seinem Speicher,
   und zwar auch nach einem gewoehnlichen Neuladen. Das ist genau eben
   passiert: Die neue Regel lag auf dem Server, im Browser fehlte sie.

   Die Aenderungszeit der Datei in der Adresse macht daraus eine andere
   Adresse, sobald sich wirklich etwas geaendert hat -- und nur dann. Kein
   Hartes-Neuladen-Erklaeren mehr, und der Speicher bleibt bis zur naechsten
   Aenderung nuetzlich. */
$stilStand = (int) @filemtime(dirname(__DIR__) . '/assets/admin.css');
?>
<link rel="stylesheet" href="<?= Fmt::h(url('assets/admin.css') . '?v=' . ($stilStand ?: 1)) ?>">
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
    <?php
    $punkt = static function (array $eintrag) use ($navZahlen, $aktiv) {
        [$ziel, $titel, $schl] = $eintrag;
        if ($ziel === '__gruppe') {
            echo '<div class="gruppe">' . Fmt::h($titel) . '</div>';
            return;
        }
        $n = (int) ($navZahlen[$schl] ?? 0);
        printf('<a href="%s" class="%s"><span>%s</span>%s</a>',
            Fmt::h(url($ziel)),
            $aktiv === ($ziel ?: 'heute') ? 'an' : '',
            Fmt::h($titel),
            $n > 0 ? '<span class="zahl warn">' . $n . '</span>' : '');
    };
    foreach ($menue as $eintrag) { $punkt($eintrag); }
    ?>

    <?php /* Aufgeklappt, sobald man darin arbeitet — sonst muesste man sich
             beim Zurueckkommen jedes Mal neu hineinklicken. */ ?>
    <details class="mehr"<?= $mehrAktiv ? ' open' : '' ?>>
      <summary>
        <span>Alles andere</span>
        <?php if ($mehrZahl > 0): ?><span class="zahl warn"><?= $mehrZahl ?></span><?php endif; ?>
      </summary>
      <?php foreach ($menueMehr as $eintrag) { $punkt($eintrag); } ?>
    </details>
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
/* ============================================================================
   RUECKFRAGEN, DIE DIE SEITE NICHT ANHALTEN

   Vorher stand an jedem heiklen Knopf ein onsubmit="return confirm(...)".
   Das tut, was es soll, hat aber zwei Nachteile: Es friert das ganze Fenster
   ein, bis jemand klickt -- und es sieht aus wie eine Warnung des Browsers,
   nicht wie eine Frage aus dieser Verwaltung. Die Frage steht jetzt dort, wo
   der Knopf steht, in derselben Gestaltung, und daneben die Antwort.

   Ein Formular sagt ueber data-frage, was gefragt werden soll, und optional
   ueber data-ja, wie die Zusage heisst. "Ja, verschicken" ist eine bessere
   Antwort als "OK": Man liest sie auch, wenn man die Frage ueberflogen hat.

   Wer welchen Knopf gedrueckt hat, merkt sich das Skript -- auf einer Seite
   mit mehreren Knoepfen im selben Formular waere sonst hinterher der erste
   gemeint und nicht der gedrueckte.
   ========================================================================= */
(function () {
  var offen = null;

  function schliessen() {
    if (offen) { offen.remove(); offen = null; }
  }

  /** Fragt am Element, gibt den Streifen zurueck. */
  function fragen(anker, frage, jaText, aufJa) {
    schliessen();
    var kasten = document.createElement('div');
    kasten.className = 'frage';
    kasten.setAttribute('role', 'group');

    var text = document.createElement('span');
    text.textContent = frage;
    kasten.appendChild(text);

    var ja = document.createElement('button');
    ja.type = 'button';
    ja.className = 'knopf haupt';
    ja.textContent = jaText || 'Ja, weiter';
    ja.addEventListener('click', function () { schliessen(); aufJa(); });

    var nein = document.createElement('button');
    nein.type = 'button';
    nein.className = 'knopf';
    nein.textContent = 'Abbrechen';
    nein.addEventListener('click', schliessen);

    kasten.appendChild(ja);
    kasten.appendChild(nein);

    /* IN EINER TABELLE GEHOERT SIE IN EINE EIGENE ZEILE
       ----------------------------------------------------------------
       Steht der Knopf in einer Zelle, waere der Streifen dort auch --
       und legte sich quer ueber die Spalten, mit den Antworten
       untereinander in einer Zelle, die dafuer zu schmal ist. Eine
       eigene Zeile ueber die volle Breite ist das, was eine Tabelle
       dafuer vorsieht. */
    var zeile = anker.closest ? anker.closest('tr') : null;
    if (zeile && zeile.parentNode) {
      var neueZeile = document.createElement('tr');
      var zelle = document.createElement('td');
      zelle.colSpan = zeile.children.length || 1;
      zelle.style.padding = '0';
      zelle.appendChild(kasten);
      neueZeile.appendChild(zelle);
      neueZeile.className = 'fragezeile';
      zeile.insertAdjacentElement('afterend', neueZeile);
      offen = neueZeile;
    } else {
      anker.insertAdjacentElement('afterend', kasten);
      offen = kasten;
    }
    ja.focus({ preventScroll: true });
    kasten.scrollIntoView({ block: 'nearest' });
    return kasten;
  }

  /* Nach aussen, damit auch etwas anderes als ein Formular fragen kann. */
  window.vecomFrage = fragen;

  /* Auf dem Dokument und in der EINFANGENDEN Phase: So wird gefragt, bevor
     irgendein anderer Zuhoerer am Formular reagiert. Haengte die Frage
     hinten dran, haette ein Skript am Formular schon gehandelt, waehrend
     die Frage noch offen ist. */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f || !f.getAttribute) { return; }
    var frage = f.getAttribute('data-frage');
    if (!frage || f.dataset.beantwortet === 'ja') { return; }

    e.preventDefault();
    var knopf = e.submitter || f.querySelector('button, input[type=submit]');
    fragen(f, frage, f.getAttribute('data-ja'), function () {
      f.dataset.beantwortet = 'ja';
      if (f.requestSubmit) { f.requestSubmit(knopf); } else { f.submit(); }
    });
  }, true);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { schliessen(); }
  });
})();
</script>

<script>
/* KOPIEREN, UND ERST DANN OEFFNEN
   -------------------------------------------------------------------------
   Ein Knopf mit data-kopieren="<id>" legt den Inhalt dieses Feldes in die
   Zwischenablage. Steht zusaetzlich data-oeffnen="<adresse>" dabei, geht
   danach ein neuer Tab dorthin auf.

   Die Reihenfolge ist der ganze Punkt. Das Briefing ueber die Adresse
   vorbefuellen zu wollen waere bequemer, aber es haengt daran, dass
   claude.ai einen bestimmten Parameter versteht -- tut es das eines Tages
   nicht mehr, steht man vor einem leeren Chat und weiss nicht, warum. So
   liegt der Text immer in der Zwischenablage, und Einfuegen ist ein Griff.

   Faellt das Kopieren aus (aeltere Browser, kein sicherer Kontext), wird der
   Text markiert und der Knopf sagt es. Dann kopiert man von Hand -- aber man
   steht nicht ohne Rueckmeldung da. */
(function () {
  function markieren(feld) {
    try { feld.focus(); feld.select(); feld.setSelectionRange(0, feld.value.length); }
    catch (e) { /* dann eben nicht */ }
  }
  document.addEventListener('click', function (e) {
    var knopf = e.target.closest('[data-kopieren]');
    if (!knopf) { return; }
    e.preventDefault();
    var feld = document.getElementById(knopf.getAttribute('data-kopieren'));
    if (!feld) { return; }
    var wohin = knopf.getAttribute('data-oeffnen') || '';
    var wort  = knopf.textContent;

    function fertig(geklappt) {
      knopf.textContent = geklappt ? 'Kopiert' : 'Bitte von Hand kopieren';
      if (!geklappt) { markieren(feld); }
      setTimeout(function () { knopf.textContent = wort; }, 2500);
      /* Der neue Tab geht nur auf, wenn der Text auch wirklich liegt --
         sonst landet man bei Claude mit leeren Haenden. */
      if (geklappt && wohin) { window.open(wohin, '_blank', 'noopener'); }
    }

    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(feld.value).then(function () { fertig(true); },
                                                     function () { fertig(false); });
      return;
    }
    markieren(feld);
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (err) { ok = false; }
    fertig(ok);
  });
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
