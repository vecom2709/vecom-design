<?php
declare(strict_types=1);
/* ==========================================================================
   Der Fragebogen, den der Kunde nach der Anzahlung ausfuellt.

   Kein Konto, kein Passwort: Der Link aus der E-Mail traegt einen langen
   Zufallsschluessel und oeffnet genau diesen einen Fragebogen. Wer den Link
   nicht hat, sieht nichts — wer ihn hat, muss sich nichts merken.

   Zwei Knoepfe: zwischenspeichern (der Link bleibt gueltig) und endgueltig
   absenden (dann rueckt das Projekt weiter und ich bekomme Bescheid).

   Das ist eine oeffentliche Adresse. Sie zeigt im Zweifel eine Meldung,
   niemals eine leere Seite und niemals eine Fehlermeldung aus der Datenbank.
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('Der Fragebogen ist derzeit nicht erreichbar.'); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
require_once __DIR__ . '/app/src/Onboarding.php';

date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
session_name('vecomfragebogen');
session_start();

// Der Schluessel steht in der Adresse — er soll nicht ueber den Verweis-Kopf
// an fremde Server weitergereicht werden, und in keinen Zwischenspeicher.
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');

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

/* ---------- Sprache ---------- */
$sprache = strtolower((string) ($_REQUEST['lang'] ?? ($f['kunde_sprache'] ?? 'it')));
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

$S = static fn(string $schluessel): string => Texte::h(Texte::SEITE[$schluessel] ?? [], $sprache);
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$basis   = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
$zurueck = $basis . ($sprache === 'it' ? '/' : "/$sprache/");

/* ---------- Absenden ---------- */
$zustand = null;          // 'gespeichert' oder 'danke'
$fehler  = [];

if ($f && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['csrf']) || !hash_equals((string) $_SESSION['csrf'], (string) ($_POST['_csrf'] ?? ''))) {
        $fehler[] = $S('panne');
    } elseif ($f['status'] === 'abgeschlossen') {
        $zustand = 'schon';
    } else {
        $endgueltig = ($_POST['tat'] ?? '') === 'absenden';
        try {
            if ($endgueltig && trim((string) ($_POST['firmenname'] ?? '')) === '') {
                $fehler[] = $S('pflicht');
                Onboarding::speichern((int) $f['id'], $_POST);
            } elseif ($endgueltig) {
                Onboarding::absenden((int) $f['id'], $_POST);
                $zustand = 'danke';
            } else {
                Onboarding::speichern((int) $f['id'], $_POST);
                $zustand = 'gespeichert';
            }
        } catch (Throwable $e) {
            $fehler[] = $S('panne');
            try {
                Events::melden('fragebogen_fehler', 'Fragebogen konnte nicht gespeichert werden', 'schlecht',
                    $e->getMessage(), '/projekte/' . (int) $f['project_id']);
            } catch (Throwable $e2) { /* dann eben nicht */ }
        }
        // Nach dem Schreiben frisch lesen: Das Formular zeigt, was wirklich steht.
        try { $f = Onboarding::laden($token) ?? $f; } catch (Throwable $e) { /* Anzeige reicht */ }
    }
}

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }

$daten = [];
if ($f && $f['data'] !== null && $f['data'] !== '') {
    $daten = json_decode((string) $f['data'], true) ?: [];
}
$fertig = $f && ($f['status'] === 'abgeschlossen' || $zustand === 'danke');

/* Wie viele Felder schon ausgefuellt sind — ein kleiner Anreiz, weiterzumachen. */
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
  .abschnitt + .abschnitt{margin-top:6px}
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
    <div class="hinweis gut"><?= $h($zustand === 'danke' ? $S('danke') : $S('schon')) ?></div>
    <p style="color:var(--dim);font-size:14px"><?= $h((string) $f['projekt']) ?></p>
    <a class="knopf haupt" style="margin-top:12px" href="<?= $h($zurueck) ?>">Vecom Design</a>
  </div>
  <?php foreach (Texte::FRAGEBOGEN as $abschnitt => $inhalt): ?>
    <?php $hat = array_filter($inhalt['felder'], static fn($_, $n) => trim((string) ($daten[$n] ?? '')) !== '', ARRAY_FILTER_USE_BOTH); ?>
    <?php if ($hat): ?>
      <div class="block"><h2 style="margin-bottom:12px"><?= $h(Texte::h($inhalt, $sprache)) ?></h2>
        <table><tbody>
        <?php foreach ($hat as $name => $feld): ?>
          <tr><td style="width:38%"><?= $h(Texte::h($feld, $sprache)) ?></td>
              <td><div class="antwort"><?= $h((string) $daten[$name]) ?></div></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
  <?php endforeach; ?>

<?php else: ?>
  <div class="kopfzeile">
    <h1 style="font-size:21px"><?= $h($S('titel')) ?></h1>
    <p><?= $h($S('lead')) ?></p>
    <div class="balken"><i style="width:<?= $gesamtFelder ? (int) round($gefuellt / $gesamtFelder * 100) : 0 ?>%"></i></div>
    <div class="stand"><?= (int) $gefuellt ?> / <?= (int) $gesamtFelder ?></div>
  </div>

  <?php foreach ($fehler as $x): ?><div class="hinweis schlecht"><?= $h($x) ?></div><?php endforeach; ?>
  <?php if ($zustand === 'gespeichert'): ?><div class="hinweis gut"><?= $h($S('gespeichert')) ?></div><?php endif; ?>

  <form method="post" action="fragebogen.php?t=<?= $h(rawurlencode($token)) ?>&amp;lang=<?= $h($sprache) ?>">
    <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
    <input type="hidden" name="t" value="<?= $h($token) ?>">
    <input type="hidden" name="lang" value="<?= $h($sprache) ?>">

    <?php foreach (Texte::FRAGEBOGEN as $abschnitt => $inhalt): ?>
      <div class="block">
        <h2><?= $h(Texte::h($inhalt, $sprache)) ?></h2>
        <?php foreach ($inhalt['felder'] as $name => $feld): ?>
          <div class="feld">
            <label for="f_<?= $h($name) ?>"><?= $h(Texte::h($feld, $sprache)) ?><?= $name === 'firmenname' ? ' *' : '' ?></label>
            <?php if ($feld['art'] === 'lang'): ?>
              <textarea id="f_<?= $h($name) ?>" name="<?= $h($name) ?>" rows="3"><?= $h((string) ($daten[$name] ?? '')) ?></textarea>
            <?php else: ?>
              <input id="f_<?= $h($name) ?>" name="<?= $h($name) ?>" value="<?= $h((string) ($daten[$name] ?? '')) ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

    <div class="block">
      <div class="leiste2">
        <button class="knopf" name="tat" value="speichern"><?= $h($S('speichern')) ?></button>
        <button class="knopf haupt" name="tat" value="absenden"><?= $h($S('absenden')) ?></button>
      </div>
    </div>
  </form>

  <div class="sprachen">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
      <a class="<?= $l === $sprache ? 'jetzt' : '' ?>"
         href="fragebogen.php?t=<?= $h(rawurlencode($token)) ?>&amp;lang=<?= $l ?>"><?= $h($wie) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>
</body>
</html>
