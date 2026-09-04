<?php
declare(strict_types=1);
/* ==========================================================================
   bedarf.php — Der Konfigurator auf der Website.

   WAS HIER AN DIE STELLE VON DREI PREISKARTEN GETRETEN IST

   Feste Pakete zwingen den Kunden, sich selbst einzusortieren. Wer mehr
   braucht als das teuerste, zahlt trotzdem nur das teuerste; wer weniger
   braucht, nimmt das billigste und ist unzufrieden. Hier beschreibt er
   stattdessen, was er braucht — und der Preis entsteht daraus.

   WARUM AM ENDE TROTZDEM EINE ZAHL STEHT

   Die Zielgruppe sind kleine Betriebe in und um Agrigent. Wer dort gar
   keine Zahl sieht, denkt "das kann ich mir nicht leisten" und fragt erst
   gar nicht. Deshalb steht am Ende eine Spanne — nicht auf der Startseite,
   sondern erst, wenn der Kunde gesagt hat, was er will. Dann ist sie keine
   Werbung mehr, sondern eine Auskunft. Abschaltbar ueber die Einstellung
   bedarf_spanne_zeigen, falls sich das als falsch erweist.

   OHNE KONTO, MIT SCHLUESSEL IN DER ADRESSE

   Wie beim Fragebogen und bei der Projektseite. Wer zumacht, kommt mit
   demselben Link an dieselbe Stelle zurueck. Der Schluessel steht in der
   Adresse des Formulars, nicht in seinem Rumpf: Verwirft der Server eine zu
   grosse Eingabe, sind $_POST und $_FILES leer — dann waere der Schluessel
   weg und der Kunde ausgesperrt. Dieser Fehler ist hier schon einmal
   passiert und soll sich nicht wiederholen.

   Das ist eine oeffentliche Adresse. Sie zeigt im Zweifel eine Meldung,
   niemals eine leere Seite und niemals einen Fehler aus der Datenbank.
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('Der Konfigurator ist derzeit nicht erreichbar.'); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Texte', 'Events'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
require_once __DIR__ . '/app/src/Baukasten.php';
require_once __DIR__ . '/app/src/Bedarf.php';
require_once __DIR__ . '/app/src/Einfuehrung.php';
require_once __DIR__ . '/app/src/Empfehlung.php';

date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
session_name('vecombedarf');
session_start();
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

/* ---------- Sprache ---------- */
$sprache = strtolower((string) ($_REQUEST['lang'] ?? 'it'));
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }

$T = static fn(string $s): string => Texte::h(Texte::BEDARF[$s] ?? [], $sprache);
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

$basis   = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
$zurueck = $basis . ($sprache === 'it' ? '/' : "/$sprache/");

/* Welche Frage auf welcher Seite steht, entscheidet Baukasten::SCHRITTE —
   dort, weil der Datensatz dieselbe Zahl zum Begrenzen braucht. Der letzte
   Schritt traegt keine Fragen mehr, sondern das Ergebnis. */
$anzahl = Baukasten::schrittZahl();

/* Absolut, nicht relativ — und das ist kein Schoenheitsfehler.
   Der Empfehlungslink /e/ANNA3CU wird serverseitig auf diese Datei
   umgeschrieben, die Adresse im Browser bleibt aber /e/ANNA3CU. Eine
   relative Weiterleitung landet dann unter /e/bedarf.php, und der Kunde
   steht vor einer toten Seite — ausgerechnet der, den jemand empfohlen hat. */
$adresse = static function (int $schritt, string $token, string $meldung = '') use ($sprache): string {
    $u = '/bedarf.php?t=' . rawurlencode($token) . '&lang=' . rawurlencode($sprache) . '&schritt=' . $schritt;
    return $meldung !== '' ? $u . '&m=' . rawurlencode($meldung) : $u;
};

/* ---------- Empfehlungscode aus der Adresse ----------
   Er kommt am Anfang herein (/e/CODE) und wird erst ganz am Ende gebraucht.
   Deshalb in die Sitzung: Vier Schritte spaeter steht er sonst nicht mehr in
   der Adresse, und die Empfehlung waere verloren. */
$codeRoh = strtoupper(trim((string) ($_REQUEST['e'] ?? '')));
if ($codeRoh !== '' && preg_match('/^[A-Z0-9]{5,16}$/', $codeRoh)) {
    $_SESSION['empfehl_code'] = $codeRoh;
}
$empfehlCode = (string) ($_SESSION['empfehl_code'] ?? '');
$empfehlName = '';
if ($empfehlCode !== '') {
    try {
        $eid = Empfehlung::kundeZuCode($empfehlCode);
        if ($eid) { $empfehlName = (string) Db::wert('SELECT name FROM customers WHERE id = ?', [$eid], ''); }
        else { $empfehlCode = ''; unset($_SESSION['empfehl_code']); }
    } catch (Throwable $e) { $empfehlCode = ''; }
}

/* ---------- Laden oder anfangen ---------- */
$token = trim((string) ($_REQUEST['t'] ?? ''));
$b = null;
$panne = false;
$tor = false;   // Sprachtor statt Fragen zeigen

try {
    Baukasten::sicherstellen();
    if ($token !== '') {
        $b = Bedarf::laden($token);
    }
    if (!$b && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        /* DAS SPRACHTOR — DIE ERSTE FRAGE, UND SIE IST PFLICHT
           ------------------------------------------------------------------
           An dieser einen Angabe haengt alles Spaetere: jede Mail, jeder
           Beleg, seine ganze Kundenseite. Sie ergab sich bisher aus der
           Fassung der Website, auf der jemand landete -- und wer die
           italienische Startseite nicht umstellt, bekam von da an alles auf
           Italienisch, ohne je gefragt worden zu sein.

           Deshalb steht sie jetzt vor allem anderen und laesst sich nicht
           uebergehen: Ohne Klick entsteht kein Bedarf, und es geht nicht
           weiter. Ein Bildschirm, ein Klick -- und dafuer stimmt danach
           jedes Wort, das dieser Mensch von uns liest.

           Nebenbei entsteht dadurch auch kein Datensatz mehr, nur weil ein
           Suchprogramm die Adresse aufgerufen hat. */
        if (($_GET['start'] ?? '') !== '1') {
            $tor = true;
        } else {
            $b = Bedarf::starten($sprache);
            header('Location: ' . $adresse(1, (string) $b['token'])); exit;
        }
    }
} catch (Throwable $e) {
    $panne = true;
    try {
        Events::melden('bedarf_fehler', 'Konfigurator nicht erreichbar', 'schlecht', $e->getMessage(), '/anfragen');
    } catch (Throwable $e2) { /* dann eben nicht */ }
}

/* ---------- Schreiben, dann umleiten ---------- */
if ($b && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $jetzt = max(1, min($anzahl, (int) ($_POST['schritt'] ?? 1)));

    if (empty($_SESSION['csrf']) || !hash_equals((string) $_SESSION['csrf'], (string) ($_POST['_csrf'] ?? ''))) {
        header('Location: ' . $adresse($jetzt, (string) $b['token'], 'panne')); exit;
    }

    $tat = (string) ($_POST['tat'] ?? 'weiter');
    try {
        if ($tat === 'absenden') {
            $ok = Bedarf::absenden((int) $b['id'], [
                'name'    => (string) ($_POST['name'] ?? ''),
                'email'   => (string) ($_POST['email'] ?? ''),
                'telefon' => (string) ($_POST['telefon'] ?? ''),
                'firma'   => (string) ($_POST['firma'] ?? ''),
                'empfehl_code' => $empfehlCode,
                'empfehl_wer'  => (string) ($_POST['empfehl_wer'] ?? ''),
                // Seine Antwort auf die Sprachfrage — nicht die Fassung, in
                // der er gerade zufaellig liest.
                'sprache'      => (string) ($_POST['sprache_wahl'] ?? ''),
            ]);
            /* Die Dankeseite in SEINER Sprache, nicht in der, in der er
               gelesen hat. Wer gerade "Deutsch" angegeben hat und dann eine
               italienische Bestaetigung sieht, glaubt zu Recht, die Angabe
               sei untergegangen — und die Mail, die gleich kommt, ist ja
               schon deutsch. */
            $zielSprache = strtolower(trim((string) ($_POST['sprache_wahl'] ?? '')));
            if (!in_array($zielSprache, ['it', 'de', 'en'], true)) { $zielSprache = $sprache; }
            header('Location: /bedarf.php?t=' . rawurlencode((string) $b['token'])
                . '&lang=' . rawurlencode($zielSprache)
                . '&schritt=' . $anzahl
                . '&m=' . ($ok ? 'danke' : 'pflicht')); exit;
        }

        // Die Antworten dieses Schritts einsammeln. Ein nicht angekreuztes
        // Mehrfachfeld schickt gar nichts mit — deshalb wird es ausdruecklich
        // als leer uebergeben, sonst bliebe die alte Antwort stehen.
        $neu = [];
        foreach (Baukasten::SCHRITTE[$jetzt - 1] ?? [] as $frage) {
            $art = Baukasten::FRAGEN[$frage]['art'] ?? 'einfach';
            $neu[$frage] = $art === 'mehrfach' ? (array) ($_POST[$frage] ?? []) : (string) ($_POST[$frage] ?? '');
        }
        Bedarf::speichern((int) $b['id'], $neu, $jetzt);

        if ($tat === 'zurueck') { header('Location: ' . $adresse(max(1, $jetzt - 1), (string) $b['token'])); exit; }
        header('Location: ' . $adresse(min($anzahl, $jetzt + 1), (string) $b['token'])); exit;

    } catch (Throwable $e) {
        try {
            Events::melden('bedarf_fehler', 'Bedarf konnte nicht gespeichert werden', 'schlecht', $e->getMessage(), '/anfragen');
        } catch (Throwable $e2) { /* dann eben nicht */ }
        header('Location: ' . $adresse($jetzt, (string) $b['token'], 'panne')); exit;
    }
}

/* ---------- Anzeigen ---------- */
$schritt   = max(1, min($anzahl, (int) ($_GET['schritt'] ?? 1)));
$m         = (string) ($_GET['m'] ?? '');
$antworten = $b ? Bedarf::antworten($b) : [];
$fertig    = $b && $b['status'] !== 'offen';

/* Die Spanne wird erst im letzten Schritt gerechnet — vorher waere sie eine
   Zahl, die sich bei jeder Antwort aendert, und das verunsichert mehr als
   es hilft. */
$spanne = null; $monatlich = 0; $zeigen = true; $genug = true;
if ($b && $schritt === $anzahl) {
    try {
        $genug  = Baukasten::genugGesagt($antworten);
        $zeigen = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'bedarf_spanne_zeigen'", [], '1') === '1'
                  && $genug;
        $r = Baukasten::rechnen($antworten);
        $spanne = Baukasten::spanne((int) $r['von_cents'], (int) $r['bis_cents']);
        $monatlich = (int) $r['monatlich_cents'];
    } catch (Throwable $e) { $spanne = null; }
}

/* Wie viele Plaetze zum Einfuehrungspreis noch offen sind. Echte Zahl aus
   voll bezahlten Bestellungen — kein erfundener Countdown. Faellt sie aus,
   steht dort einfach nichts. */
$rest = null; $ziel = 0;
if ($b && $schritt === $anzahl) {
    try {
        if (Einfuehrung::laeuft()) { $rest = Einfuehrung::restplaetze(); $ziel = Einfuehrung::ziel(); }
    } catch (Throwable $e) { $rest = null; }
}

$geld = static function (int $cents) use ($sprache): string {
    $z = number_format($cents / 100, 0, ',', '.');
    return $sprache === 'en' ? '€' . $z : $z . ' €';
};
?><!doctype html>
<html lang="<?= $h($sprache) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $h($T('titel')) ?> — Vecom Design</title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<link rel="stylesheet" href="/assets/css/kunde.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/kunde.css') ?>">
<style>
  .lead{color:var(--dim);font-size:15px;line-height:1.65}
  .bkopf{margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid var(--linie)}
  .punkte{display:flex;gap:6px;margin:14px 0 4px;list-style:none;padding:0}
  .punkte li{flex:1 1 0;height:4px;border-radius:2px;background:var(--linie)}
  .punkte li.durch{background:var(--blau)}
  .punkte li.jetzt{background:var(--cyan)}
  .zaehler{font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--leise)}
  .beiseite{color:var(--leise);font-size:12.5px;line-height:1.6;margin-top:10px}

  .frage{margin-bottom:22px}
  .frage:last-child{margin-bottom:0}
  .frage h2{font-size:18px;margin:0 0 4px}
  .frage .hilfe{color:var(--leise);font-size:13px;line-height:1.55;margin:0 0 12px}

  /* Ganze Flaechen statt kleiner Kreise: Auf dem Handy trifft der Daumen
     einen 20-Pixel-Radiobutton nicht zuverlaessig. Hier ist die ganze Zeile
     das Ziel, mindestens 52 Pixel hoch. */
  .wahl{display:grid;gap:8px}
  /* position:relative ist kein Beiwerk: Das Eingabefeld darunter liegt
     absolut. Ohne einen positionierten Elternteil verankert es sich am
     Seitenanfang — und der Browser springt beim Tabben nach oben, statt
     die gerade gewaehlte Zeile zu zeigen. */
  .wahl label{position:relative;display:flex;align-items:center;gap:12px;min-height:52px;
    padding:12px 14px;border:1px solid var(--linie);border-radius:10px;
    cursor:pointer;transition:border-color .15s,background .15s}
  .wahl label:hover{border-color:var(--blau)}
  .wahl input{position:absolute;opacity:0;width:0;height:0}
  .wahl .kaestchen{flex:0 0 20px;width:20px;height:20px;border:2px solid var(--linie);
    border-radius:50%;position:relative;transition:border-color .15s}
  .wahl .kaestchen.eckig{border-radius:5px}
  .wahl input:checked + .kaestchen{border-color:var(--cyan)}
  .wahl input:checked + .kaestchen::after{content:"";position:absolute;inset:3px;
    border-radius:50%;background:var(--cyan)}
  .wahl .kaestchen.eckig::after{border-radius:2px}
  .wahl label:has(input:checked){border-color:var(--cyan);background:rgba(0,180,216,.07)}
  .wahl input:focus-visible + .kaestchen{outline:2px solid var(--cyan);outline-offset:3px}
  .wahl .wort{font-size:15px;line-height:1.4}

  .ergebnis{text-align:center;padding:26px 18px}
  .ergebnis .klein{font-size:13px;letter-spacing:.06em;text-transform:uppercase;color:var(--leise)}
  .ergebnis .zahl{font-size:clamp(28px,7vw,40px);font-weight:600;margin:10px 0 6px;line-height:1.15}
  .ergebnis .monat{color:var(--dim);font-size:14px;line-height:1.6;margin:0}
  .ergebnis .erklaerung{color:var(--leise);font-size:13px;line-height:1.65;margin:14px auto 0;max-width:44ch}

  .knapp{margin:16px auto 0;max-width:44ch;padding:10px 14px;border-radius:10px;
    background:rgba(0,180,216,.09);border:1px solid rgba(0,180,216,.28);
    font-size:13.5px;line-height:1.55;color:var(--dim)}
  .knapp span{display:block;margin-top:4px;color:var(--leise);font-size:12.5px}
  .erkannt{margin:4px 0 0;padding:10px 14px;border-radius:10px;
    background:rgba(0,180,216,.09);border:1px solid rgba(0,180,216,.28);
    font-size:13.5px;color:var(--dim)}
  .leiste2{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  .leiste2 .rechts{margin-left:auto}
  .leiste2 .knopf{flex:0 1 auto;padding-left:26px;padding-right:26px}
  @media (max-width:520px){ .leiste2 .knopf{flex:1 1 auto} .leiste2 .rechts{display:none} }

  /* Das Sprachtor. Drei gleich grosse Ziele, untereinander — auf dem Telefon
     ist das die verlaesslichste Form, und nichts davon ist vorausgewaehlt:
     Es soll ein Klick sein, keine Bestaetigung einer Vermutung. */
  .sprachtor{display:flex;flex-direction:column;gap:10px}
  /* Bewusst KEIN Hauptknopf: Drei gleich laute Farbflaechen sagen "alles ist
     wichtig", und das heisst nichts. Hier ist die Wahl die Betonung, nicht
     die Flaeche — ruhige Kacheln, die erst unter dem Zeiger aufleuchten. */
  .sprachtor .knopf{display:flex;flex-direction:column;align-items:center;gap:3px;
    padding:17px 20px;text-align:center;line-height:1.35;
    background:rgba(255,255,255,.035);border:1px solid var(--linie);
    transition:border-color .18s ease, background .18s ease}
  .sprachtor .knopf:hover,.sprachtor .knopf:focus-visible{
    background:rgba(0,180,216,.10);border-color:var(--cyan)}
  .sprachtor .knopf b{font-size:16.5px;font-weight:600;color:#fff}
  .sprachtor .knopf span{font-size:12.5px;font-weight:400;color:var(--leise)}
</style>
</head>
<body>
<div class="seite">
  <div class="wortmarke">
    <img src="/assets/img/logo-mark.webp" alt="" width="58" height="46" fetchpriority="high">
    <span class="wort"><b>VECOM</b> DESIGN</span>
  </div>

<?php if ($tor && !$panne): ?>
  <?php /* Dreisprachig, und das ist keine Spielerei: Wer nach der Sprache
           fragt, darf die Frage nicht in einer Sprache stellen, die der
           Leser vielleicht nicht kann. Jeder Knopf spricht fuer sich
           selbst. */ ?>
  <div class="bkopf" style="text-align:center">
    <h1 style="font-size:20px;margin:0 0 8px">Lingua · Sprache · Language</h1>
    <p class="lead" style="margin:0 auto;max-width:40ch">
      Vale per le e-mail, i documenti e la tua pagina.<br>
      Gilt für E-Mails, Unterlagen und deine Seite.<br>
      Applies to emails, documents and your page.
    </p>
  </div>
  <div class="block">
    <div class="sprachtor">
      <?php foreach ([
        'it' => ['Italiano', 'Continua in italiano'],
        'de' => ['Deutsch',  'Auf Deutsch weiter'],
        'en' => ['English',  'Continue in English'],
      ] as $sl => [$wort, $satz]): ?>
        <a class="knopf" href="/bedarf.php?lang=<?= $sl ?>&amp;start=1<?= $empfehlCode !== '' ? '&amp;e=' . $h(rawurlencode($empfehlCode)) : '' ?>">
          <b><?= $h($wort) ?></b><span><?= $h($satz) ?></span></a>
      <?php endforeach; ?>
    </div>
  </div>

<?php elseif ($panne || !$b): ?>
  <div class="block">
    <div class="hinweis schlecht"><?= $h($T($panne ? 'panne' : 'weg')) ?></div>
    <a class="knopf haupt" href="/bedarf.php?lang=<?= $h($sprache) ?>"><?= $h($T('neu')) ?></a>
  </div>

<?php elseif ($m === 'danke' || ($fertig && $schritt === $anzahl)): ?>
  <div class="block">
    <div class="hinweis gut"><?= $h($T('danke')) ?></div>
    <a class="knopf haupt" style="margin-top:12px" href="<?= $h($zurueck) ?>">Vecom Design</a>
  </div>

<?php else: ?>
  <div class="bkopf">
    <h1 style="font-size:21px;margin:0 0 6px"><?= $h($T('titel')) ?></h1>
    <p class="lead" style="margin:0"><?= $h($T('lead')) ?></p>
    <ul class="punkte">
      <?php for ($i = 1; $i <= $anzahl; $i++): ?>
        <li class="<?= $i < $schritt ? 'durch' : ($i === $schritt ? 'jetzt' : '') ?>"></li>
      <?php endfor; ?>
    </ul>
    <div class="zaehler"><?= $h(strtr($T('schritt'), ['{n}' => (string) $schritt, '{g}' => (string) $anzahl])) ?></div>
  </div>

  <?php if ($m === 'panne'): ?><div class="hinweis schlecht"><?= $h($T('panne')) ?></div><?php endif; ?>
  <?php if ($m === 'pflicht'): ?><div class="hinweis schlecht"><?= $h($T('pflicht')) ?></div><?php endif; ?>

  <form method="post" action="/bedarf.php?t=<?= $h(rawurlencode((string) $b['token'])) ?>&amp;lang=<?= $h($sprache) ?>">
    <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
    <input type="hidden" name="lang" value="<?= $h($sprache) ?>">
    <input type="hidden" name="schritt" value="<?= (int) $schritt ?>">

    <?php if ($schritt < $anzahl): ?>
      <div class="block">
        <?php foreach (Baukasten::SCHRITTE[$schritt - 1] as $name): ?>
          <?php $f = Baukasten::FRAGEN[$name]; $mehrfach = ($f['art'] ?? 'einfach') === 'mehrfach'; ?>
          <?php $gewaehlt = $antworten[$name] ?? ($mehrfach ? [] : ''); ?>
          <fieldset class="frage" style="border:0;padding:0;margin-inline:0">
            <legend style="padding:0"><h2><?= $h(Texte::h($f['frage'], $sprache)) ?></h2></legend>
            <?php if (!empty($f['hilfe'])): ?>
              <p class="hilfe"><?= $h(Texte::h($f['hilfe'], $sprache)) ?></p>
            <?php endif; ?>
            <div class="wahl">
              <?php foreach ($f['optionen'] as $wert => $text): ?>
                <?php
                  $wert = (string) $wert;
                  $an = $mehrfach ? in_array($wert, (array) $gewaehlt, true) : ((string) $gewaehlt === $wert);
                  $id = 'o_' . $h($name) . '_' . $h($wert);
                ?>
                <label for="<?= $id ?>">
                  <input type="<?= $mehrfach ? 'checkbox' : 'radio' ?>" id="<?= $id ?>"
                         name="<?= $h($name) ?><?= $mehrfach ? '[]' : '' ?>"
                         value="<?= $h($wert) ?>"<?= $an ? ' checked' : '' ?>>
                  <span class="kaestchen<?= $mehrfach ? ' eckig' : '' ?>"></span>
                  <span class="wort"><?= $h(Texte::h($text, $sprache)) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </fieldset>
        <?php endforeach; ?>
      </div>

    <?php else: ?>
      <?php if (!$genug): ?>
        <div class="block">
          <div class="hinweis warnung"><?= $h($T('nichts')) ?></div>
        </div>
      <?php endif; ?>
      <?php if ($spanne && $zeigen): ?>
        <div class="block ergebnis">
          <div class="klein"><?= $h($T('ergebnisTitel')) ?></div>
          <div class="zahl"><?= $h($geld($spanne['von_cents'])) ?> – <?= $h($geld($spanne['bis_cents'])) ?></div>
          <?php if ($monatlich > 0): ?>
            <p class="monat"><?= $h(strtr($T('ergebnisMonat'), ['{betrag}' => $geld($monatlich)])) ?></p>
          <?php endif; ?>
          <p class="erklaerung"><?= $h($T('ergebnisText')) ?></p>
          <?php if ($rest !== null && $rest > 0): ?>
            <p class="knapp">
              <?= $h(strtr($T('knappheit'), ['{n}' => (string) $rest, '{g}' => (string) $ziel])) ?>
              <span><?= $h(strtr($T('knappheitHilfe'), ['{g}' => (string) $ziel])) ?></span>
            </p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="block">
        <h2 style="font-size:18px;margin:0 0 14px"><?= $h($T('kontaktTitel')) ?></h2>
        <div class="feld">
          <label for="f_name"><?= $h($T('fName')) ?> *</label>
          <input id="f_name" name="name" autocomplete="name" required
                 value="<?= $h((string) ($b['name'] ?? '')) ?>">
        </div>
        <div class="feld">
          <label for="f_email"><?= $h($T('fEmail')) ?> *</label>
          <input id="f_email" name="email" type="email" autocomplete="email" required
                 value="<?= $h((string) ($b['email'] ?? '')) ?>">
        </div>
        <div class="feld">
          <label for="f_telefon"><?= $h($T('fTelefon')) ?></label>
          <input id="f_telefon" name="telefon" type="tel" autocomplete="tel"
                 value="<?= $h((string) ($b['telefon'] ?? '')) ?>">
        </div>
        <div class="feld">
          <label for="f_firma"><?= $h($T('fFirma')) ?></label>
          <input id="f_firma" name="firma" autocomplete="organization"
                 value="<?= $h((string) ($b['firma'] ?? '')) ?>">
        </div>
        <?php /* ----------------------------------------------------------
             Die Sprache als Antwort, nicht als Nebenwirkung.

             Unten steht ein Umschalter, aber der aendert nur die Ansicht --
             und weil jeder Verweis auf diese Seite fest "lang=it" trug, hat
             ihn kaum jemand je gebraucht. Was dabei herauskam, entschied
             danach ueber jede Mail, jeden Beleg und die ganze Kundenseite.

             Hier steht die Frage jetzt da, wo die Kontaktdaten stehen, mit
             der aktuellen Fassung als Vorauswahl. Wer sie stehen laesst, hat
             sie trotzdem gesehen -- und das ist der Unterschied zu vorher.
             ---------------------------------------------------------- */ ?>
        <div class="feld">
          <label for="f_sprache"><?= $h($T('fSprache')) ?> *</label>
          <select id="f_sprache" name="sprache_wahl" required>
            <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $sl => $wort): ?>
              <option value="<?= $sl ?>" <?= $sprache === $sl ? 'selected' : '' ?>><?= $h($wort) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="beiseite" style="margin-top:6px"><?= $h($T('fSpracheHilfe')) ?></p>
        </div>
        <?php if ($empfehlName !== ''): ?>
          <p class="erkannt"><?= $h(strtr($T('empfehlungErkannt'), ['{name}' => $empfehlName])) ?></p>
        <?php else: ?>
          <div class="feld">
            <label for="f_empf"><?= $h($T('fEmpfehlung')) ?></label>
            <input id="f_empf" name="empfehl_wer"
                   value="<?= $h((string) ($b['empfehl_wer'] ?? '')) ?>">
            <p class="beiseite" style="margin-top:6px"><?= $h($T('empfehlungHilfe')) ?></p>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="block">
      <div class="leiste2">
        <?php if ($schritt > 1): ?>
          <button class="knopf" name="tat" value="zurueck"><?= $h($T('zurueck')) ?></button>
        <?php endif; ?>
        <span class="rechts"></span>
        <?php if ($schritt < $anzahl): ?>
          <button class="knopf haupt" name="tat" value="weiter"><?= $h($T('weiter')) ?></button>
        <?php else: ?>
          <button class="knopf haupt" name="tat" value="absenden"><?= $h($T('absenden')) ?></button>
        <?php endif; ?>
      </div>
      <p class="beiseite"><?= $h($T('autoOk')) ?></p>
    </div>
  </form>

  <div class="sprachen">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
      <a class="<?= $l === $sprache ? 'jetzt' : '' ?>"
         href="/bedarf.php?t=<?= $h(rawurlencode((string) $b['token'])) ?>&amp;lang=<?= $l ?>&amp;schritt=<?= (int) $schritt ?>"><?= $h($wie) ?></a>
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
