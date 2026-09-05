<?php
declare(strict_types=1);
/* ==========================================================================
   Der Fragebogen, den der Kunde nach der Anzahlung ausfuellt.

   Kein Konto, kein Passwort: Der Link aus der E-Mail traegt einen langen
   Zufallsschluessel und oeffnet genau diesen einen Fragebogen. Wer den Link
   nicht hat, sieht nichts — wer ihn hat, muss sich nichts merken.

   WARUM IN ABSCHNITTEN

   Vorher standen einundzwanzig Felder auf einer Seite. Wer das auf dem Handy
   oeffnet, sieht eine Wand und macht sie zu. Jetzt sind es vier kurze
   Schritte mit fuenf bis sechs Feldern, und zwischen den Schritten wird
   gespeichert — ohne dass der Kunde an einen Knopf denken muss. Er kann
   jederzeit zumachen und mit demselben Link an derselben Stelle weiter.

   Nach jedem Schreiben wird umgeleitet (POST → Redirect → GET). Dann laesst
   sich die Seite neu laden, ohne dass etwas doppelt passiert, und der Zurueck-
   Knopf des Browsers tut, was er soll.

   Das ist eine oeffentliche Adresse. Sie zeigt im Zweifel eine Meldung,
   niemals eine leere Seite und niemals eine Fehlermeldung aus der Datenbank.
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('Der Fragebogen ist derzeit nicht erreichbar.'); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
require_once __DIR__ . '/app/src/Onboarding.php';
require_once __DIR__ . '/app/src/Kundenzugang.php';
require_once __DIR__ . '/app/src/Umfang.php';
require_once __DIR__ . '/app/src/Baukasten.php';
require_once __DIR__ . '/app/src/Fragen.php';

date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
session_name('vecomfragebogen');
session_start();

// Der Schluessel steht in der Adresse — er soll nicht ueber den Verweis-Kopf
// an fremde Server weitergereicht werden, und in keinen Zwischenspeicher.
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

$token = trim((string) ($_REQUEST['t'] ?? ''));
$f = null;
$panne = false;
try {
    $f = Onboarding::laden($token);
} catch (Throwable $e) {
    // Fehlt eine Aktualisierung der Datenbank, ist das kein Grund fuer eine
    // weisse Seite — der Kunde bekommt eine Erklaerung und ich eine Meldung.
    $panne = true;
    try {
        Events::melden('fragebogen_fehler', 'Fragebogen nicht erreichbar', 'schlecht', $e->getMessage(), '/projekte');
    } catch (Throwable $e2) { /* dann eben nicht */ }
}

/* ---------- Sprache ----------------------------------------------------
   Ohne Angabe gilt, was beim Kunden steht -- das ist die Sprache, in der er
   die Website benutzt hat, als er anfragte oder buchte.

   Waehlt er hier unten eine andere, ist das die staerkere Auskunft: Er sitzt
   gerade davor und sagt es selbst. Also wird sie beim Kunden vermerkt, und
   jede spaetere Mail -- Vorschau, Restzahlung, "deine Seite ist online" --
   kommt von da an in derselben Sprache. Vorher aenderte der Umschalter nur
   diese eine Seite, und die naechste Mail fiel wieder zurueck. */
$sprache = strtolower((string) ($_REQUEST['lang'] ?? ($f['kunde_sprache'] ?? 'it')));
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

if ($f !== null
    && isset($_REQUEST['lang'])
    && $sprache !== strtolower((string) ($f['kunde_sprache'] ?? ''))) {
    try {
        Onboarding::spracheMerken((int) $f['customer_id'], $sprache);
        $f['kunde_sprache'] = $sprache;
    } catch (Throwable $e) { /* die Seite zeigt trotzdem die gewaehlte Sprache */ }
}

$S = static fn(string $schluessel): string => Texte::h(Texte::SEITE[$schluessel] ?? [], $sprache);
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$basis   = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
$zurueck = $basis . ($sprache === 'it' ? '/' : "/$sprache/");

/* Zurueck zur einen Kundenseite — dorthin, wo der Kunde hergekommen ist. */
$heim = $zurueck;
if ($f) {
    try { $heim = Kundenzugang::linkFuer((int) $f['customer_id']); } catch (Throwable $e) { /* dann die Startseite */ }
}

/* ---------- Die vier Abschnitte ---------- */
$abschnitte = array_keys(Texte::FRAGEBOGEN);
$anzahl     = count($abschnitte);

$adresse = static function (int $schritt, string $meldung = '') use ($token, $sprache): string {
    $u = 'fragebogen.php?t=' . rawurlencode($token) . '&lang=' . rawurlencode($sprache) . '&schritt=' . $schritt;
    return $meldung !== '' ? $u . '&m=' . rawurlencode($meldung) : $u;
};

/* ---------- Schreiben, dann umleiten ---------- */
if ($f && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $jetzt = max(1, min($anzahl, (int) ($_POST['schritt'] ?? 1)));

    if (empty($_SESSION['csrf']) || !hash_equals((string) $_SESSION['csrf'], (string) ($_POST['_csrf'] ?? ''))) {
        header('Location: ' . $adresse($jetzt, 'panne')); exit;
    }
    if ($f['status'] === 'abgeschlossen') {
        header('Location: ' . $adresse(1, 'schon')); exit;
    }

    $tat = (string) ($_POST['tat'] ?? 'weiter');
    try {
        if ($tat === 'absenden') {
            // Der Firmenname steht im ersten Abschnitt. Beim Absenden kommt er
            // nicht mehr mit — also gegen das pruefen, was gespeichert ist.
            $bisher = [];
            if ($f['data'] !== null && $f['data'] !== '') { $bisher = json_decode((string) $f['data'], true) ?: []; }
            $name = trim((string) ($_POST['firmenname'] ?? ($bisher['firmenname'] ?? '')));
            if ($name === '') {
                Onboarding::speichern((int) $f['id'], $_POST);
                header('Location: ' . $adresse(1, 'pflicht')); exit;
            }
            Onboarding::absenden((int) $f['id'], $_POST);
            header('Location: ' . $adresse(1, 'danke')); exit;
        }

        Onboarding::speichern((int) $f['id'], $_POST);

        if ($tat === 'zurueck')      { header('Location: ' . $adresse(max(1, $jetzt - 1))); exit; }
        if ($tat === 'pause')        { header('Location: ' . $adresse($jetzt, 'gespeichert')); exit; }
        header('Location: ' . $adresse(min($anzahl, $jetzt + 1))); exit;

    } catch (Throwable $e) {
        try {
            Events::melden('fragebogen_fehler', 'Fragebogen konnte nicht gespeichert werden', 'schlecht',
                $e->getMessage(), '/projekte/' . (int) $f['project_id']);
        } catch (Throwable $e2) { /* dann eben nicht */ }
        header('Location: ' . $adresse($jetzt, 'panne')); exit;
    }
}

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }

$daten = [];
if ($f && $f['data'] !== null && $f['data'] !== '') {
    $daten = json_decode((string) $f['data'], true) ?: [];
}

$m      = (string) ($_GET['m'] ?? '');
$fertig = $f && ($f['status'] === 'abgeschlossen' || $m === 'danke');

/* WO GEHT ES WEITER?
   ----------------------------------------------------------------------
   Beim ersten Abschnitt, in dem noch eine Luecke ist. Wer zurueckkommt,
   landet damit dort, wo er aufgehoert hat, statt wieder ganz vorn.

   Frueher galt "der erste Abschnitt, in dem gar nichts steht". Das war
   dasselbe, solange der Fragebogen leer begann. Seit ein paar Felder aus
   dem Konfigurator vorbelegt sind, ist es das nicht mehr: In jedem
   Abschnitt steht dann schon etwas, und der Kunde landete im letzten --
   ohne je die leeren Pflichtfelder im ersten gesehen zu haben. */
$vorschlag = 1;
foreach ($abschnitte as $i => $name) {
    $luecke = false;
    foreach (Texte::FRAGEBOGEN[$name]['felder'] as $feldName => $feld) {
        /* Was gar nicht gezeigt wird, kann auch nicht fehlen. Sonst schickte
           der Fragebogen jemanden ohne alte Website ewig auf denselben
           Schritt zurueck, wo zwei unsichtbare Felder auf ihn warten. */
        if (!Fragen::zeigen($feld, $daten)) { continue; }
        /* Eine Hakenliste, in der nichts angehakt ist, ist beantwortet --
           der Kunde hat sie gesehen und alles weggenommen. Wuerde sie als
           Luecke zaehlen, landete er bis in alle Ewigkeit wieder auf
           diesem Schritt, egal wie oft er weiterklickt. Dasselbe gilt fuer
           die Auswahllisten und die Materialliste. */
        if (in_array(($feld['art'] ?? ''), ['wahl', 'mehr', 'stand'], true)) {
            if (!array_key_exists($feldName, $daten)) { $luecke = true; break; }
            continue;
        }
        if (trim((string) ($daten[$feldName] ?? '')) === '') { $luecke = true; break; }
    }
    if ($luecke) { $vorschlag = $i + 1; break; }
    $vorschlag = min($anzahl, $i + 2);
}
$schritt = isset($_GET['schritt']) ? max(1, min($anzahl, (int) $_GET['schritt'])) : $vorschlag;
$name    = $abschnitte[$schritt - 1];
$inhalt  = Texte::FRAGEBOGEN[$name];

$gesamtFelder = count(Onboarding::felder());
$gefuellt     = count(array_filter($daten, static fn($w) => trim((string) $w) !== ''));

/* ---------- Was beauftragt ist -----------------------------------------
   Die Hakenliste zeigt an, was im angenommenen Angebot steht. Gibt es
   keines -- eine Bestellung von Hand, ein Paket von der Website --, bleibt
   die Liste eine gewoehnliche Auswahl ohne Vergleich: Dann ist nichts
   "nicht enthalten", weil es nichts gibt, worin es enthalten sein koennte. */
$bezahlt  = $f ? Umfang::bezahlt((int) $f['project_id']) : null;
$wahl     = Umfang::gewaehlt($daten, $bezahlt);
$katWahl  = Umfang::katalogWahl();
$gruppenWort = [
    'funktion'  => ['it' => 'Funzioni', 'de' => 'Funktionen', 'en' => 'Features'],
    'inhalt'    => ['it' => 'Contenuti e materiale', 'de' => 'Inhalte und Material', 'en' => 'Content and material'],
    'betreuung' => ['it' => 'Assistenza', 'de' => 'Betreuung', 'en' => 'Care plan'],
];
?><!doctype html>
<html lang="<?= $h($sprache) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $h($S('titel')) ?> — Vecom Design</title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<link rel="stylesheet" href="/assets/css/kunde.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/kunde.css') ?>">
<style>
  .lead{color:var(--dim); font-size:15px; line-height:1.65}
  /* Ein eigener Kopf statt der Kopfzeile aus der gemeinsamen Datei: Die
     stellt alles nebeneinander, und nebeneinander ist auf dem Handy
     untereinander mit Gewalt. */
  .fbkopf{margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--linie)}
  /* Vier Punkte statt eines Prozentbalkens: Man sieht auf einen Blick,
     wie viel noch kommt — und dass es wenig ist. */
  .punkte{display:flex;gap:6px;margin:14px 0 4px;list-style:none;padding:0}
  .punkte li{flex:1 1 0;height:4px;border-radius:2px;background:var(--linie)}
  .punkte li.durch{background:var(--blau)}
  .punkte li.jetzt{background:var(--cyan)}
  .zaehler{font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--leise)}
  .beiseite{color:var(--leise);font-size:12.5px;line-height:1.6;margin-top:10px}
  .leiste2{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .leiste2 .rechts{margin-left:auto}
  /* Auf dem Rechner haben Knoepfe ihre eigene Breite, auf dem Handy die
     volle — dort trifft der Daumen sonst daneben. */
  .leiste2 .knopf{flex:0 1 auto;min-width:0;padding-left:26px;padding-right:26px}
  @media (max-width:520px){ .leiste2 .knopf{flex:1 1 auto} .leiste2 .rechts{display:none} }

  /* Die Hakenliste. Sie ersetzt zwei Freitextfelder, in denen frueher der
     halbe Auftragsumfang stand, ohne dass jemand ihn vergleichen konnte. */
  .zahlfeld{max-width:110px}
  .hakengruppe{margin:14px 0 0}
  .hakengruppe h4{font-size:11.5px;letter-spacing:.08em;text-transform:uppercase;
                  color:var(--leise);margin:0 0 8px;font-weight:600}
  .haken{display:flex;gap:11px;align-items:flex-start;padding:11px 12px;margin-bottom:7px;
         border:1px solid var(--linie);border-radius:10px;cursor:pointer;
         transition:border-color .14s ease, background .14s ease}
  .haken:hover{border-color:var(--cyan)}
  /* Angehakt sieht anders aus als nicht angehakt — sonst muss man das
     Kaestchen suchen, um zu wissen, was gilt. */
  .haken:has(input:checked){border-color:var(--blau);background:rgba(6,72,232,.045)}
  .haken input{margin:2px 0 0;width:17px;height:17px;flex:0 0 auto;accent-color:var(--blau)}
  .haken span{flex:1 1 auto;min-width:0;display:block;font-size:14px;line-height:1.45}
  .haken span b{display:block;font-weight:600}
  .haken span i{display:block;font-style:normal;font-weight:400;font-size:12.5px;
                color:var(--leise);margin-top:2px}
  /* "Im Angebot" ist die wichtigste Auskunft auf dieser Seite: Sie sagt dem
     Kunden, was er schon hat, und uns, woran wir seine Kreuze messen. */
  .haken em.drin{flex:0 0 auto;align-self:center;font-style:normal;font-size:11px;
                 letter-spacing:.05em;text-transform:uppercase;color:var(--blau);
                 border:1px solid currentColor;border-radius:999px;padding:3px 9px;white-space:nowrap}
  .feld p.mehr,.feld p.weniger{margin:12px 0 0;font-size:13px;line-height:1.55;
         border-radius:9px;padding:10px 12px}
  .feld p.mehr{color:var(--cyan);background:rgba(31,232,255,.07);border:1px solid rgba(31,232,255,.3)}
  .feld p.weniger{color:var(--dim);background:rgba(127,127,127,.07);border:1px solid var(--linie)}

  /* ---- Auswahl statt Schreiben -------------------------------------------
     Knoepfe, keine Klapplisten. Eine Klappliste ist auf dem Handy ein
     zweiter Bildschirm, den man oeffnen, scrollen und schliessen muss;
     sieben Knoepfe untereinander sind ein Blick und ein Tippen. Das
     eigentliche Kaestchen bleibt sichtbar — verschwindet es, weiss bei
     einer Mehrfachauswahl niemand mehr, ob eins oder mehrere gehen. */
  .auswahl{display:flex;flex-direction:column;gap:7px}
  .option{display:flex;gap:13px;align-items:center;padding:11px 14px;
          border:1px solid var(--linie);border-radius:10px;cursor:pointer;
          font-size:14px;line-height:1.4;
          transition:border-color .14s ease, background .14s ease}
  .option:hover{border-color:var(--cyan)}
  .option:has(input:checked){border-color:var(--blau);background:rgba(6,72,232,.055);font-weight:500}
  .option input{margin:0;width:17px;height:17px;flex:0 0 auto;accent-color:var(--blau)}
  .option span{flex:1 1 auto;min-width:0}
  /* Die freie Zeile gehoert optisch unter die Auswahl, nicht daneben —
     sonst liest sie sich wie eine eigene Frage. */
  .freizeile{margin-top:9px}
  .freizeile::placeholder{color:var(--leise)}

  /* ---- Die Materialliste --------------------------------------------------
     Sie ersetzt drei Textkaesten ("Texte", "Bilder", "Videos"), in denen
     bisher Saetze standen, aus denen sich nicht ablesen liess, was ich
     bekomme und was ich machen muss. Genau das steht jetzt je Zeile. */
  .standliste{display:flex;flex-direction:column;gap:7px}
  .standzeile{border:1px solid var(--linie);border-radius:10px;padding:10px 12px}
  .standzeile>b{display:block;font-size:14px;font-weight:600;margin-bottom:8px}
  /* Raster statt Umbruch: Mit flex-wrap standen auf dem Handy drei Knoepfe
     nebeneinander und der vierte allein ueber die volle Breite -- als waere
     er etwas anderes als die drei davor. Vier gleiche Zustaende muessen auch
     gleich aussehen, also zwei mal zwei. */
  .standwahl{display:grid;grid-template-columns:repeat(4,1fr);gap:6px}
  @media (max-width:620px){ .standwahl{grid-template-columns:repeat(2,1fr)} }
  .standwahl label{display:flex;align-items:center;justify-content:center;
                   gap:6px;padding:8px 8px;border:1px solid var(--linie);border-radius:8px;
                   font-size:12.5px;cursor:pointer;white-space:nowrap;
                   transition:border-color .14s ease, background .14s ease, color .14s ease}
  .standwahl label:hover{border-color:var(--cyan)}
  .standwahl label.an,
  .standwahl label:has(input:checked){border-color:var(--blau);background:rgba(6,72,232,.09);
                      color:var(--blau);font-weight:600}
  /* Bei vier Zustaenden nebeneinander waere jeder Knopf so schmal, dass die
     Beschriftung umbricht. Also bekommt das Kaestchen die Arbeit und die
     Schrift den Platz. */
  .standwahl input{margin:0;width:14px;height:14px;flex:0 0 auto;accent-color:var(--blau)}

</style>
</head>
<body>
<div class="seite">
  <div class="wortmarke">
    <img src="/assets/img/logo-mark.webp" alt="" width="58" height="46" fetchpriority="high">
    <span class="wort"><b>VECOM</b> DESIGN</span>
  </div>

<?php if ($panne): ?>
  <div class="block">
    <div class="hinweis schlecht"><?= $h($S('panne')) ?></div>
    <a class="knopf haupt" href="<?= $h($zurueck) ?>">Vecom Design</a>
  </div>

<?php elseif (!$f): ?>
  <div class="block">
    <div class="hinweis schlecht"><?= $h($S('weg')) ?></div>
    <a class="knopf haupt" href="<?= $h($zurueck) ?>">Vecom Design</a>
  </div>

<?php elseif ($fertig): ?>
  <div class="block">
    <div class="hinweis gut"><?= $h($m === 'danke' ? $S('danke') : $S('schon')) ?></div>
    <p style="color:var(--dim);font-size:14px"><?= $h((string) $f['projekt']) ?></p>
    <a class="knopf haupt" style="margin-top:12px" href="<?= $h($heim) ?>"><?= $h(Texte::h(Texte::PROJEKT['titel'] ?? [], $sprache, 'Dein Projekt')) ?></a>
  </div>
  <?php foreach (Texte::FRAGEBOGEN as $abschnitt => $teil): ?>
    <?php $hat = array_filter($teil['felder'], static fn($_, $n) => trim((string) ($daten[$n] ?? '')) !== '', ARRAY_FILTER_USE_BOTH); ?>
    <?php if ($hat): ?>
      <div class="block"><h2 style="margin-bottom:12px"><?= $h(Texte::h($teil, $sprache)) ?></h2>
        <table><tbody>
        <?php foreach ($hat as $feldName => $feld): ?>
          <tr><td style="width:38%"><?= $h(Texte::h($feld, $sprache)) ?></td>
              <td><div class="antwort"><?= $h(($feld['art'] ?? '') === 'wahl'
                    ? Umfang::worte((string) $daten[$feldName], $sprache)
                    : Fragen::worte($feldName, (string) $daten[$feldName], $sprache)) ?><?php
                    /* Die freie Zeile gehoert zur Auswahl, nicht daneben. */
                    $frei = trim((string) ($daten[$feldName . '__frei'] ?? ''));
                    if ($frei !== '') { echo ' — ' . $h($frei); }
                  ?></div></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
  <?php endforeach; ?>

<?php else: ?>
  <div class="fbkopf">
    <h1 style="font-size:21px;margin:0 0 6px"><?= $h($S('titel')) ?></h1>
    <p class="lead" style="margin:0"><?= $h($S('lead')) ?></p>
    <?php /* Ein paar Felder sind schon gefuellt, weil der Kunde sie im
             Konfigurator beantwortet hat. Ohne diesen Satz fragt er sich, wer
             das getippt hat -- und traut sich womoeglich nicht, es zu
             aendern. Mit dem ersten eigenen Speichern verschwindet er. */ ?>
    <?php if ($schritt === 1 && trim((string) ($f['data'] ?? '')) !== '' && ($f['status'] ?? '') === 'offen'): ?>
      <p class="lead" style="margin:8px 0 0;color:var(--cyan)"><?= $h($S('schonGesagt')) ?></p>
    <?php endif; ?>
    <ul class="punkte">
      <?php foreach ($abschnitte as $i => $_): ?>
        <li class="<?= $i + 1 < $schritt ? 'durch' : ($i + 1 === $schritt ? 'jetzt' : '') ?>"></li>
      <?php endforeach; ?>
    </ul>
    <div class="zaehler"><?= $h(strtr($S('schritt'), ['{n}' => (string) $schritt, '{g}' => (string) $anzahl])) ?></div>
  </div>

  <?php if ($m === 'panne'): ?><div class="hinweis schlecht"><?= $h($S('panne')) ?></div><?php endif; ?>
  <?php if ($m === 'pflicht'): ?><div class="hinweis schlecht"><?= $h($S('pflicht')) ?></div><?php endif; ?>
  <?php if ($m === 'gespeichert'): ?><div class="hinweis gut"><?= $h($S('gespeichert')) ?></div><?php endif; ?>

  <form method="post" action="fragebogen.php?t=<?= $h(rawurlencode($token)) ?>&amp;lang=<?= $h($sprache) ?>">
    <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
    <input type="hidden" name="t" value="<?= $h($token) ?>">
    <input type="hidden" name="lang" value="<?= $h($sprache) ?>">
    <input type="hidden" name="schritt" value="<?= (int) $schritt ?>">

    <div class="block">
      <h2><?= $h(Texte::h($inhalt, $sprache)) ?></h2>
      <p class="beiseite" style="margin-top:0"><?= $h($S('leerOk')) ?></p>
      <?php foreach ($inhalt['felder'] as $feldName => $feld): ?>
        <?php
          /* Bedingte Fragen. Sie stehen im Aufbau immer, aber gezeigt werden
             sie nur, wenn die Antwort davor sie verlangt. Wer nie eine
             Website hatte, sieht keine Frage zur alten. */
          if (!Fragen::zeigen($feld, $daten)) { continue; }
          $ohneLabelFuer = in_array($feld['art'], ['wahl', 'eins', 'mehr', 'stand'], true);
        ?>
        <div class="feld">
          <label <?= $ohneLabelFuer ? '' : 'for="f_' . $h($feldName) . '"' ?>><?= $h(Texte::h($feld, $sprache)) ?><?= $feldName === 'firmenname' ? ' *' : '' ?></label>

          <?php if ($feld['art'] === 'zahl'): ?>
            <?php
              $istZahl  = $feldName === 'seiten_zahl' ? $wahl['seiten']   : $wahl['sprachen'];
              $sollZahl = $feldName === 'seiten_zahl'
                          ? ($bezahlt['seiten'] ?? null) : ($bezahlt['sprachen'] ?? null);
              $obergrenze = $feldName === 'seiten_zahl' ? 60 : 6;
            ?>
            <input type="number" inputmode="numeric" min="1" max="<?= (int) $obergrenze ?>"
                   id="f_<?= $h($feldName) ?>" name="<?= $h($feldName) ?>" class="zahlfeld"
                   value="<?= (int) $istZahl ?>"
                   <?= $sollZahl !== null ? 'data-soll="' . (int) $sollZahl . '"' : '' ?>>
            <p class="beiseite" style="margin-top:6px">
              <?php if ($feldName === 'seiten_zahl'): ?><?= $h($S('seitenHilfe')) ?><?php endif; ?>
              <?php if ($sollZahl !== null): ?>
                <?= $h($S('beauftragt')) ?>: <?= (int) $sollZahl ?>
              <?php endif; ?></p>
            <?php if ($sollZahl !== null): ?>
              <p class="mehr" hidden><?= $h($S('nichtDrin')) ?></p>
              <p class="weniger" hidden><?= $h($S('wenigerDrin')) ?></p>
            <?php endif; ?>

          <?php elseif ($feld['art'] === 'wahl'): ?>
            <?php if ($bezahlt !== null): ?>
              <p class="beiseite" style="margin:0 0 10px"><?= $h($S('wasDrin')) ?></p>
            <?php endif; ?>
            <?php /* Ein leerer erster Eintrag, damit das Feld auch dann
                     mitkommt, wenn kein einziger Haken gesetzt ist. Sonst
                     liesse sich nichts abwaehlen: Ein Formular schickt
                     leere Kaestchen nicht mit, und was nicht mitkommt,
                     zaehlt beim Speichern als "nichts gesagt". */ ?>
            <input type="hidden" name="<?= $h($feldName) ?>[]" value="">
            <?php foreach ($katWahl as $gruppe => $bausteine): ?>
              <div class="hakengruppe">
                <h4><?= $h(Texte::h($gruppenWort[$gruppe] ?? [], $sprache, $gruppe)) ?></h4>
                <?php foreach ($bausteine as $slug => $b): ?>
                  <?php
                    $drin = $bezahlt !== null && isset($bezahlt['slugs'][$slug]);
                    $an   = isset($wahl['slugs'][$slug]);
                  ?>
                  <label class="haken">
                    <input type="checkbox" name="<?= $h($feldName) ?>[]" value="<?= $h($slug) ?>"
                           <?= $an ? 'checked' : '' ?> <?= $drin ? 'data-drin="1"' : '' ?>>
                    <span>
                      <b><?= $h(Baukasten::name($b, $sprache)) ?></b>
                      <?php $wozu = Baukasten::text($b, $sprache); ?>
                      <?php if ($wozu !== ''): ?><i><?= $h($wozu) ?></i><?php endif; ?>
                    </span>
                    <?php if ($drin): ?><em class="drin"><?= $h($S('beauftragt')) ?></em><?php endif; ?>
                  </label>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
            <?php if ($bezahlt !== null): ?>
              <p class="mehr" hidden><?= $h($S('nichtDrin')) ?></p>
              <p class="weniger" hidden><?= $h($S('wenigerDrin')) ?></p>
            <?php endif; ?>

          <?php elseif ($feld['art'] === 'eins'): ?>
            <?php /* Knoepfe statt Klappliste: Auf dem Handy ist eine
                     Klappliste ein zweiter Bildschirm, den man oeffnen,
                     scrollen und schliessen muss. Sieben Knoepfe
                     untereinander sind ein Blick und ein Tippen. */ ?>
            <div class="auswahl">
              <?php foreach ($feld['optionen'] as $schluessel => $wort): ?>
                <label class="option<?= ($daten[$feldName] ?? '') === (string) $schluessel ? ' an' : '' ?>">
                  <input type="radio" name="<?= $h($feldName) ?>" value="<?= $h((string) $schluessel) ?>"
                         <?= ($daten[$feldName] ?? '') === (string) $schluessel ? 'checked' : '' ?>>
                  <span><?= $h(Texte::h($wort, $sprache)) ?></span>
                </label>
              <?php endforeach; ?>
            </div>

          <?php elseif ($feld['art'] === 'mehr'): ?>
            <?php /* Leerer erster Eintrag, damit sich alles abwaehlen laesst
                     — ein Formular schickt leere Kaestchen nicht mit, und was
                     nicht mitkommt, zaehlt beim Speichern als "nichts gesagt". */ ?>
            <input type="hidden" name="<?= $h($feldName) ?>[]" value="">
            <?php $an = array_filter(explode(',', (string) ($daten[$feldName] ?? ''))); ?>
            <div class="auswahl mehrfach">
              <?php foreach ($feld['optionen'] as $schluessel => $wort): ?>
                <label class="option<?= in_array((string) $schluessel, $an, true) ? ' an' : '' ?>">
                  <input type="checkbox" name="<?= $h($feldName) ?>[]" value="<?= $h((string) $schluessel) ?>"
                         <?= in_array((string) $schluessel, $an, true) ? 'checked' : '' ?>>
                  <span><?= $h(Texte::h($wort, $sprache)) ?></span>
                </label>
              <?php endforeach; ?>
            </div>

          <?php elseif ($feld['art'] === 'stand'): ?>
            <?php $stand = Onboarding::standWerte((string) ($daten[$feldName] ?? '')); ?>
            <div class="standliste">
              <?php foreach ($feld['zeilen'] as $zeile => $wort): ?>
                <div class="standzeile">
                  <b><?= $h(Texte::h($wort, $sprache)) ?></b>
                  <div class="standwahl">
                    <?php foreach (Onboarding::ZUSTAENDE as $zustand): ?>
                      <label class="<?= ($stand[$zeile] ?? '') === $zustand ? 'an' : '' ?>">
                        <input type="radio" name="<?= $h($feldName) ?>[<?= $h((string) $zeile) ?>]"
                               value="<?= $h($zustand) ?>" <?= ($stand[$zeile] ?? '') === $zustand ? 'checked' : '' ?>>
                        <span><?= $h(Texte::h(Fragen::ZUSTANDWORT[$zustand], $sprache)) ?></span>
                      </label>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

          <?php elseif ($feld['art'] === 'lang'): ?>
            <?php
              /* Vorbelegt aus Branche und Ort — aber nur, solange der Kunde
                 nichts eigenes geschrieben hat. Ein Vorschlag, der eine
                 Antwort ueberschreibt, ist ein Datenverlust. */
              $wert = (string) ($daten[$feldName] ?? '');
              if ($wert === '' && isset($feld['vorschlag'])) {
                  $wert = Fragen::vorschlag((string) $feld['vorschlag'], $daten, $sprache);
              }
            ?>
            <textarea id="f_<?= $h($feldName) ?>" name="<?= $h($feldName) ?>" rows="3"><?= $h($wert) ?></textarea>
          <?php else: ?>
            <input id="f_<?= $h($feldName) ?>" name="<?= $h($feldName) ?>" value="<?= $h((string) ($daten[$feldName] ?? '')) ?>">
          <?php endif; ?>

          <?php if (!empty($feld['frei'])): ?>
            <input class="freizeile" id="f_<?= $h($feldName) ?>__frei" name="<?= $h($feldName) ?>__frei"
                   placeholder="<?= $h($S('freiZeile')) ?>"
                   value="<?= $h((string) ($daten[$feldName . '__frei'] ?? '')) ?>">
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="block">
      <?php if ($schritt === $anzahl): ?><p class="beiseite" style="margin-top:0"><?= $h($S('letzter')) ?></p><?php endif; ?>
      <div class="leiste2">
        <?php if ($schritt > 1): ?>
          <button class="knopf" name="tat" value="zurueck"><?= $h($S('zurueck')) ?></button>
        <?php endif; ?>
        <span class="rechts"></span>
        <?php if ($schritt < $anzahl): ?>
          <button class="knopf haupt" name="tat" value="weiter"><?= $h($S('weiter')) ?></button>
        <?php else: ?>
          <button class="knopf" name="tat" value="pause"><?= $h($S('speichern')) ?></button>
          <button class="knopf haupt" name="tat" value="absenden"><?= $h($S('absenden')) ?></button>
        <?php endif; ?>
      </div>
      <p class="beiseite"><?= $h($S('autoOk')) ?>
        <?php if ($gefuellt > 0 && $gesamtFelder > 0): ?>
          · <?= (int) $gefuellt ?>/<?= (int) $gesamtFelder ?>
        <?php endif; ?></p>
    </div>
  </form>

  <?php if ($bezahlt !== null): ?>
  <script>
  /* Der Hinweis erscheint in derselben Sekunde, in der der Haken gesetzt
     wird -- nicht erst nach dem Abschicken. Wer erst hinterher erfaehrt,
     dass etwas nicht im Angebot war, hat es in der Zwischenzeit fuer
     selbstverstaendlich gehalten.

     Bewusst ohne Betrag: Was es kostet, sagt ein Mensch, nachdem er es
     gelesen hat. Eine Zahl, die hier von selbst erscheint, waere eine
     Nachforderung, der niemand zugestimmt hat. */
  (function () {
    document.querySelectorAll('.feld').forEach(function (feld) {
      var mehr    = feld.querySelector('p.mehr');
      var weniger = feld.querySelector('p.weniger');
      if (!mehr && !weniger) { return; }

      var haken = feld.querySelectorAll('input[type=checkbox]');
      var zahl  = feld.querySelector('input[type=number][data-soll]');

      function pruefen() {
        var plus = false, minus = false;
        haken.forEach(function (k) {
          if (k.checked && !k.dataset.drin) { plus = true; }
          if (!k.checked && k.dataset.drin) { minus = true; }
        });
        if (zahl) {
          var soll = parseInt(zahl.dataset.soll, 10);
          var ist  = parseInt(zahl.value, 10);
          if (!isNaN(soll) && !isNaN(ist)) {
            if (ist > soll) { plus = true; }
            if (ist < soll) { minus = true; }
          }
        }
        if (mehr)    { mehr.hidden = !plus; }
        if (weniger) { weniger.hidden = !minus; }
      }

      haken.forEach(function (k) { k.addEventListener('change', pruefen); });
      if (zahl) { zahl.addEventListener('input', pruefen); }
      pruefen();
    });
  })();
  </script>
  <?php endif; ?>

  <div class="sprachen">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
      <a class="<?= $l === $sprache ? 'jetzt' : '' ?>"
         href="fragebogen.php?t=<?= $h(rawurlencode($token)) ?>&amp;lang=<?= $l ?>&amp;schritt=<?= (int) $schritt ?>"><?= $h($wie) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
<?php /* Impressum, Datenschutz und AGB — auch unter den Seiten, die man nur
         mit Schluessel erreicht. Sie waren bisher nur auf den oeffentlichen
         Seiten zu finden, obwohl der Kunde hier entscheidet. */ ?>
<?php require_once __DIR__ . '/app/src/Fuss.php'; echo Fuss::html($sprache); ?>
</body>
</html>
