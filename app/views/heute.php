<?php
/**
 * Heute — die Arbeitsliste.
 *
 * Das alte Dashboard zeigte Zahlen. Zahlen sind schoen, aber sie sagen nicht,
 * was zu tun ist. Diese Seite beantwortet genau eine Frage: Was wartet auf
 * mich, und welcher Knopf erledigt es? Die Zahlen stehen klein oben rechts,
 * wo sie hingehoeren.
 */

/** Eine Zeile: wer, warum, seit wann, und der Knopf. */
$zeile = static function (array $v, bool $vorne = false) {
    $tage = Vorgang::ruhtSeitTagen($v);
    $s    = $v['schritt'];
    $ziel = url('vorgaenge/' . $v['schluessel']);
    // Wohin der Knopf fuehrt: was der Schritt nennt, sonst die Vorgangsseite.
    $tunZiel = ($s !== null && ($s['ziel'] ?? null) !== null) ? url((string) $s['ziel']) : $ziel;
    ?>
    <div class="vg">
      <div class="vg__wer">
        <a class="vg__name" href="<?= Fmt::h($ziel) ?>"><?= Fmt::h($v['kunde']) ?></a>
        <div class="vg__unter">
          <?php /* Wo im Ablauf. In der Liste hilft die Zahl mehr als eine
                   Leiste aus neun Punkten je Zeile: Man sieht auf einen
                   Blick, wer kurz vor dem Abschluss steht und wer gerade
                   erst anfängt, ohne dass die Zeile zur Grafik wird.
                   Die Leiste selbst steht auf der Vorgangsseite. */ ?>
          <span class="marke2" title="Stufe <?= (int) $v['stufe_nr'] + 1 ?> von <?= count(Vorgang::STUFEN) ?>">
            <?= Fmt::h($v['stufe_wort']) ?>
            <i class="vg__stufe"><?= (int) $v['stufe_nr'] + 1 ?>/<?= count(Vorgang::STUFEN) ?></i>
          </span>
          <?php if ($v['paket'] !== ''): ?> <?= Fmt::h($v['paket']) ?><?php endif; ?>
          <?php if ($v['preis'] > 0): ?> · <?= Fmt::geld($v['preis'], $v['waehrung']) ?><?php endif; ?>
        </div>
      </div>
      <div class="vg__warum"><?= Fmt::h($v['warum']) ?></div>
      <div class="vg__tun">
        <span class="vg__ruht <?= $tage >= 7 ? 'lang' : '' ?>">
          <?= $tage === 0 ? 'heute' : ($tage === 1 ? 'seit gestern' : "seit $tage Tagen") ?></span>
        <?php if ($s === null): ?>
          <a class="knopf" href="<?= Fmt::h($ziel) ?>">Öffnen</a>
        <?php elseif ($s['direkt']): ?>
          <a class="knopf" href="<?= Fmt::h($ziel) ?>">Öffnen</a>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
            <?= Csrf::feld() ?>
            <input type="hidden" name="tat" value="<?= Fmt::h((string) $s['tat']) ?>">
            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <input type="hidden" name="zurueck" value="heute">
            <?php foreach ($s['felder'] as $feld => $wert): ?>
              <input type="hidden" name="<?= Fmt::h($feld) ?>" value="<?= Fmt::h((string) $wert) ?>">
            <?php endforeach; ?>
            <button class="knopf<?= $vorne ? ' haupt' : '' ?>"><?= Fmt::h($s['knopf']) ?></button>
          </form>
        <?php else: ?>
          <?php /* Fuehrt auf die Vorgangsseite statt sofort zu handeln — der
                   Pfeil sagt das, damit niemand einen Klick erwartet, der
                   nicht kommt. Was den Projektstand verschiebt, will vorher
                   im Zusammenhang gesehen werden. */ ?>
          <a class="knopf<?= $vorne ? ' haupt' : '' ?>" href="<?= Fmt::h($tunZiel) ?>"><?= Fmt::h($s['knopf']) ?> &rsaquo;</a>
        <?php endif; ?>
      </div>
    </div>
    <?php
};
?>

<div class="kopf">
  <div><h1>Heute</h1>
    <div class="weg"><?= Fmt::h(Fmt::langesDatum()) ?></div></div>
  <div class="rechts">
    <?php /* Im Menue steht die Suche auf breiten Schirmen. Auf dem Handy ist
             das Menue eine Zeile ohne Platz dafuer -- deshalb steht sie hier
             noch einmal, auf der Seite, mit der man ohnehin anfaengt. */ ?>
    <form class="leiste nurschmal" method="get" action="<?= Fmt::h(url('suche')) ?>" role="search">
      <input type="search" name="q" placeholder="Kunde, Bestellung, Angebot …" aria-label="Suchen">
    </form>
    <span class="marke2"><?= count($liste['du']) ?> bei dir</span>
    <span class="marke2"><?= count($liste['kunde']) ?> beim Kunden</span>
    <?php if ($offenGeld > 0): ?><span class="marke2 warnung"><?= Fmt::geld($offenGeld) ?> offen</span><?php endif; ?>
    <a class="knopf" href="<?= Fmt::h(url('vorgaenge')) ?>">Alle Kunden</a>
  </div>
</div>

<?php /* STEHT DER TAKTGEBER? (26.09.2026)
   Alles, was von allein passiert -- Hosting einrichten, Abbuchungen,
   Mahnungen, HTTPS, Speicher, Mail-Umzug --, haengt am Cronjob im KAS. Ob er
   lief, stand bisher nur unter Monitoring; wer dort nicht hinsah, merkte
   tagelang nicht, dass nichts mehr von allein geschah. Er laeuft alle zehn
   Minuten; nach 30 ohne Lauf ist etwas faul. */
require_once __DIR__ . '/../src/Cron.php';
$cronZuletzt = sicher(static fn() => Cron::zuletzt(), null);
$cronSteht = $cronZuletzt === null || strtotime((string) $cronZuletzt) < time() - 30 * 60; ?>
<?php if ($cronSteht): ?>
  <div class="hinweis schlecht" style="margin-bottom:16px">
    <b>Der Cronjob <?= $cronZuletzt === null ? 'ist noch nie gelaufen' : 'läuft nicht mehr — zuletzt ' . Fmt::h(Fmt::seit((string) $cronZuletzt)) ?>.</b>
    Solange er steht, passiert nichts von allein: kein Einrichten, keine Abbuchung, keine Mahnung, keine Prüfung.
    <a href="<?= Fmt::h(url('einstellungen?b=ueberwachung')) ?>" style="color:inherit;text-decoration:underline;font-weight:600">Im KAS eintragen</a>
  </div>
<?php endif; ?>

<?php /* MEHRERE, DIE AUF DIE ERSTE ANTWORT WARTEN
         ------------------------------------------------------------------
         Kommen an einem Tag fuenf Anfragen, sagt die Liste das nicht von
         selbst: Sie zeigt fuenfzehn Zeilen, und wer nicht zaehlt, sieht
         nicht, dass fuenf davon Menschen sind, die noch kein Wort gehoert
         haben. Auf der Website stehen 24 Stunden.

         Die Zeile steht nur da, wenn es mehr als einer ist -- bei einem
         genuegt die Liste selbst. */ ?>
<?php
$erst = 0;
foreach ($liste['du'] as $eins) { if (!empty($eins['erstantwort'])) { $erst++; } }
?>
<?php if ($erst > 1): ?>
  <p class="erstantwort"><b><?= (int) $erst ?> Anfragen</b> warten auf die erste
    Antwort — sie stehen oben. Auf der Website stehen 24 Stunden.</p>
<?php endif; ?>

<?php /* Was von selbst nachgerueckt ist. Steht nur da, wenn wirklich etwas
         passiert ist -- eine Meldung, die immer da ist, liest niemand.
         Sie meldet keine Handlung, sondern eine Buchhaltung: Der Kunde sieht
         seinen Fortschritt jetzt richtig, ohne dass jemand geklickt hat. */ ?>
<?php $nachgezogen = $nachgezogen ?? 0; ?>
<?php if ($nachgezogen > 0): ?>
  <p class="nachgezogen" style="margin:0 0 14px"><b><?= (int) $nachgezogen ?></b>
    <?= $nachgezogen === 1 ? 'Vorgang ist' : 'Vorgänge sind' ?> im Stand nachgerückt —
    die Tatsachen dazu standen schon da. Nichts ist rausgegangen; im Verlauf des
    Kunden steht, was sich geändert hat.</p>
<?php endif; ?>

<?php /* ---------- WAS GERADE HÄNGT ----------
         Hier standen zwei Kästen: „Das läuft nicht" (was gemeldet wurde) und
         „Demnächst fällig" (was eine Frist hat). Dazwischen gab es einen
         dritten Fall ohne Kasten — die Vorgänge, bei denen einfach nichts
         passiert. Der ist der gefährlichste: Stille löst nichts aus.

         Jetzt eine Liste. Jede Zeile sagt, was hängt, seit wann, und hat
         einen Knopf. Hängt nichts, steht hier nichts — und das ist dann
         eine Auskunft und kein leerer Kasten. */ ?>
<?php $haengt = $haengt ?? []; ?>
<?php if ($haengt): ?>
  <?php
    $hEilt = 0;
    foreach ($haengt as $h) { if (!empty($h['eilig'])) { $hEilt++; } }
    $hWort = ['stoerung' => 'Gemeldet', 'frist' => 'Frist', 'stille' => 'Still'];
  ?>
  <div class="block" style="border-color:rgba(255,138,138,.32)">
    <h2 style="color:var(--rot)">Was gerade hängt<span class="mehr"><?= count($haengt) ?><?php
      if (($hGesamt ?? 0) > count($haengt)): ?> von <?= (int) $hGesamt ?><?php endif; ?></span></h2>
    <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 12px">
      Nicht alles davon ist ein Fehler. Manches hat nur eine Frist, und manches
      liegt einfach seit Wochen still — das meldet sonst niemand.</p>

    <?php foreach ($haengt as $h): ?>
      <div class="vg">
        <div class="vg__wer">
          <span class="vg__name"><?= Fmt::h((string) $h['titel']) ?></span>
          <div class="vg__unter">
            <span class="marke2 <?= $h['art'] === 'stoerung' ? 'schlecht'
                  : ($h['art'] === 'stille' ? 'warnung' : '') ?>"><?=
              Fmt::h($hWort[$h['art']] ?? '') ?></span>
            <?php if (trim((string) $h['wer']) !== ''): ?> <?= Fmt::h((string) $h['wer']) ?><?php endif; ?>
          </div>
        </div>
        <div class="vg__warum"><?= Fmt::h((string) $h['warum']) ?></div>
        <div class="vg__tun">
          <?php if (!empty($h['eilig'])): ?><span class="vg__ruht lang">eilt</span><?php endif; ?>
          <a class="knopf" href="<?= Fmt::h(url((string) $h['ziel'])) ?>"><?=
            Fmt::h((string) ($h['wohin'] ?: 'Ansehen')) ?></a>
          <?php if (!empty($h['tat'])): ?>
            <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
              <?= Csrf::feld() ?>
              <input type="hidden" name="tat" value="<?= Fmt::h((string) $h['tat']) ?>">
              <input type="hidden" name="id" value="<?= (int) $h['tatId'] ?>">
              <input type="hidden" name="zurueck" value="heute">
              <button class="knopf"><?= Fmt::h((string) ($h['tatWort'] ?: 'Erledigt')) ?></button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <p style="color:var(--leise);font-size:12.5px;margin-top:12px">
      <?php if (($hGesamt ?? 0) > count($haengt)): ?>
        <?= (int) $hGesamt - count($haengt) ?> weitere stehen unter
        <a href="<?= Fmt::h(url('benachrichtigungen')) ?>">Meldungen</a>.
      <?php else: ?>
        Alle Meldungen stehen unter
        <a href="<?= Fmt::h(url('benachrichtigungen')) ?>">Meldungen</a>.
      <?php endif; ?>
    </p>
  </div>
<?php endif; ?>

<?php /* HÖCHSTENS FÜNF, UND NUR EINER GOLDEN (26.09.2026, Uwe: „ja“)
         Bei fünfzig Zeilen mit fünfzig goldenen Knöpfen vergleicht man, statt
         zu handeln (Regel 3). Die Reihenfolge der Liste ist schon die
         Dringlichkeit -- die ersten fünf sind die, um die es heute geht; der
         Rest steht eingeklappt darunter, mit seiner Zahl. Zugeklappt ist
         nicht verschwunden. */
$duVorne = array_slice($liste['du'], 0, 5);
$duSpaeter = array_slice($liste['du'], 5); ?>
<div class="block">
  <h2>Du bist dran<span class="mehr"><?= count($liste['du']) ?></span></h2>
  <?php if (!$liste['du']): ?>
    <div class="leer">Nichts offen. Alles, was läuft, wartet gerade auf jemand anderen.</div>
  <?php else: foreach ($duVorne as $i => $v) { $zeile($v, $i === 0); } endif; ?>
  <?php if ($duSpaeter): ?>
    <details class="klapp spaeter">
      <summary>Später<span class="mehr"><?= count($duSpaeter) ?></span></summary>
      <?php foreach ($duSpaeter as $v) { $zeile($v); } ?>
    </details>
  <?php endif; ?>
</div>

<?php /* ---------- Was nicht bei dir liegt ----------
         Beides stand vorher offen und in voller Laenge da. Bei zwoelf
         Vorgaengen hiess das: Man scrollte an zwanzig Zeilen vorbei, in
         denen nichts zu tun war, um an die zu kommen, in denen etwas zu tun
         war. Die Zahl neben der Ueberschrift sagt weiter, wie viele es sind
         -- zugeklappt ist nicht verschwunden.

         "Der Kunde ist dran" oeffnet sich trotzdem, wenn oben nichts steht:
         Wer nichts zu tun hat, sucht als Naechstes, wo es hakt. */ ?>
<details class="block klapp" <?= !$liste['du'] ? 'open' : '' ?>>
  <summary><h2>Der Kunde ist dran<span class="mehr"><?= count($liste['kunde']) ?></span></h2></summary>
  <p style="color:var(--leise);font-size:12.5px;margin:2px 0 10px">
    Hier musst du nichts tun — außer nachfassen, wenn es zu lange still ist.</p>
  <?php if (!$liste['kunde']): ?>
    <div class="leer">Niemand lässt dich warten.</div>
  <?php else: foreach ($liste['kunde'] as $v) { $zeile($v); } endif; ?>
</details>

<?php if ($liste['ruht']): ?>
  <details class="block klapp">
    <summary><h2>Läuft<span class="mehr"><?= count($liste['ruht']) ?></span></h2></summary>
    <?php foreach ($liste['ruht'] as $v) { $zeile($v); } ?>
  </details>
<?php endif; ?>
