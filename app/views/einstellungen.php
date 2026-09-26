<?php
/**
 * EINSTELLUNGEN — NACH FUNKTION, NICHT NACH TABELLE
 * ===========================================================================
 *
 * Vorher lagen die Einstellungen an sechs Stellen: Firmendaten und Zugänge
 * hier, Stripe unter „Integrationen", der Cronjob unten beim Monitoring, der
 * Telefonschlüssel und fünfzehn Konfigurationsblöcke mitten in der
 * Auswertung des Telefonassistenten. Jede für sich war begründbar, und
 * zusammen ergaben sie die Frage, die man sich nie stellen will: Wo war das
 * noch mal?
 *
 * Jetzt eine Seite, sieben Bereiche, geordnet danach, WOFÜR man kommt:
 * die Firma, wer hereindarf, wie etwas hinausgeht, wie Geld hereinkommt,
 * die Telefonassistentin, was von allein läuft, und die Daten selbst.
 *
 * WARUM EIN BEREICH NACH DEM ANDEREN UND NICHT ALLES UNTEREINANDER
 * Alles auf einer Seite wären gut zweitausend Zeilen, darunter fünfzehn
 * Textfelder mit je acht Zeilen JSON. Das lädt lange, scrollt schlecht, und
 * die Suche des Browsers findet in jedem zweiten Feld etwas. Ein Bereich ist
 * kurz genug, um ihn ganz zu sehen.
 */
$bereiche = [
    'firma'        => ['Firma & Steuern',        'Was oben auf jedem Beleg steht'],
    'zugaenge'     => ['Zugänge & Schutz', 'Wer in die Verwaltung darf, KAS-Reseller'],
    'email'        => ['E-Mail & Zuruf',         'Wie etwas hinausgeht'],
    'bezahlung'    => ['Bezahlung',              'Stripe und die Dienste von außen'],
    'telefon'      => ['Telefonassistentin',     'Schlüssel, Zugang, die 15 Konfigurationen'],
    'ueberwachung' => ['Was von allein läuft',   'Der Cronjob, der alles andere anstößt'],
    'reseller'     => ['Reseller (KAS)',         'Kunden, Domains, Postfächer, Speicher — und ob es zu Vecom passt'],
    'daten'        => ['Daten',                  'Beispieldaten und was noch kommt'],
];

$b = (string) ($_GET['b'] ?? 'firma');
if (!isset($bereiche[$b])) { $b = 'firma'; }
?>

<div class="kopf"><div><h1>Einstellungen</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px"><?= Fmt::h($bereiche[$b][1]) ?></p>
</div></div>

<?php
/* ---------- WIE AUSFÜHRLICH SICH DIE VERWALTUNG ZEIGT ----------
   Steht über den Bereichen und nicht in einem davon: Dieser Schalter
   betrifft jede Seite, nicht die Firma oder das Geld. Wer zum ersten Mal in
   die Einstellungen kommt, soll ihn sehen, ohne ihn zu suchen. */
require_once dirname(__DIR__) . '/src/Modus.php';
$einfach = Modus::einfach();
?>
<div class="bedienung">
  <div class="bedienung__text">
    <b><?= $einfach ? 'Einfache Ansicht' : 'Volle Ansicht' ?></b>
    <span><?= $einfach
      ? 'Jede Seite zeigt, was für den nächsten Schritt gebraucht wird. Alles Weitere liegt hinter „Mehr" — einen Klick entfernt, nichts ist entfernt.'
      : 'Alles steht offen da: jeder Block, jeder Knopf, auf jeder Seite.' ?></span>
  </div>
  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="bedienung">
    <input type="hidden" name="einfach" value="<?= $einfach ? 'nein' : 'ja' ?>">
    <input type="hidden" name="zurueck" value="einstellungen?b=<?= Fmt::h($b) ?>">
    <button class="knopf"><?= $einfach ? 'Alles anzeigen' : 'Einfach anzeigen' ?></button>
  </form>
</div>

<nav style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:22px">
  <?php foreach ($bereiche as $schl => [$titel, $unter]): ?>
    <a href="<?= Fmt::h(url('einstellungen?b=' . $schl)) ?>"
       class="knopf<?= $b === $schl ? ' haupt' : '' ?>"
       style="text-decoration:none"><?= Fmt::h($titel) ?></a>
  <?php endforeach; ?>
</nav>

<?php require __DIR__ . '/einstellungen/' . $b . '.php'; ?>
