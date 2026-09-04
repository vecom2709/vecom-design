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

/* Wo geht es weiter? Beim ersten Abschnitt, in dem noch nichts steht — wer
   zurueckkommt, landet dort, wo er aufgehoert hat, nicht wieder ganz vorn. */
$vorschlag = 1;
foreach ($abschnitte as $i => $name) {
    $leer = true;
    foreach (array_keys(Texte::FRAGEBOGEN[$name]['felder']) as $feldName) {
        if (trim((string) ($daten[$feldName] ?? '')) !== '') { $leer = false; break; }
    }
    if ($leer) { $vorschlag = $i + 1; break; }
    $vorschlag = min($anzahl, $i + 2);
}
$schritt = isset($_GET['schritt']) ? max(1, min($anzahl, (int) $_GET['schritt'])) : $vorschlag;
$name    = $abschnitte[$schritt - 1];
$inhalt  = Texte::FRAGEBOGEN[$name];

$gesamtFelder = count(Onboarding::felder());
$gefuellt     = count(array_filter($daten, static fn($w) => trim((string) $w) !== ''));
?><!doctype html>
<html lang="<?= $h($sprache) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $h($S('titel')) ?> — Vecom Design</title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<link rel="stylesheet" href="/assets/css/kunde.css">
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
              <td><div class="antwort"><?= $h((string) $daten[$feldName]) ?></div></td></tr>
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
        <div class="feld">
          <label for="f_<?= $h($feldName) ?>"><?= $h(Texte::h($feld, $sprache)) ?><?= $feldName === 'firmenname' ? ' *' : '' ?></label>
          <?php if ($feld['art'] === 'lang'): ?>
            <textarea id="f_<?= $h($feldName) ?>" name="<?= $h($feldName) ?>" rows="3"><?= $h((string) ($daten[$feldName] ?? '')) ?></textarea>
          <?php else: ?>
            <input id="f_<?= $h($feldName) ?>" name="<?= $h($feldName) ?>" value="<?= $h((string) ($daten[$feldName] ?? '')) ?>">
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

  <div class="sprachen">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
      <a class="<?= $l === $sprache ? 'jetzt' : '' ?>"
         href="fragebogen.php?t=<?= $h(rawurlencode($token)) ?>&amp;lang=<?= $l ?>&amp;schritt=<?= (int) $schritt ?>"><?= $h($wie) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
</body>
</html>
