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
$zeile = static function (array $v) {
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
            <button class="knopf haupt"><?= Fmt::h($s['knopf']) ?></button>
          </form>
        <?php else: ?>
          <?php /* Fuehrt auf die Vorgangsseite statt sofort zu handeln — der
                   Pfeil sagt das, damit niemand einen Klick erwartet, der
                   nicht kommt. Was den Projektstand verschiebt, will vorher
                   im Zusammenhang gesehen werden. */ ?>
          <a class="knopf haupt" href="<?= Fmt::h($tunZiel) ?>"><?= Fmt::h($s['knopf']) ?> &rsaquo;</a>
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
    <a class="knopf" href="<?= Fmt::h(url('vorgaenge')) ?>">Alle Vorgänge</a>
  </div>
</div>

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

<?php /* ---------- Was nicht läuft ----------
         Der Kasten bleibt offen: Eine Störung soll rufen, nicht warten, bis
         jemand sie aufklappt. Aber er bleibt auch kurz. Am 13.09.2026
         standen hier acht Meldungen, darunter dieselbe dreimal — sie füllten
         den ganzen ersten Bildschirm, und „Du bist dran" begann erst
         darunter. Eine Störungsliste, die die Arbeit verdeckt, richtet
         denselben Schaden an wie eine, die man übersieht.

         Drei stehen da, der Rest klappt auf. Die Zahl oben nennt weiterhin
         alle — verschwunden ist nichts. */ ?>
<?php $stMax = 3; ?>
<?php if ($stoerungen): ?>
  <div class="block" style="border-color:rgba(255,138,138,.32)">
    <h2 style="color:var(--rot)">Das läuft nicht<span class="mehr"><?= count($stoerungen) ?></span></h2>
    <?php foreach (array_slice($stoerungen, 0, $stMax) as $m): ?>
      <div class="vg">
        <div class="vg__wer"><span class="vg__name"><?= Fmt::h($m['title']) ?></span>
          <div class="vg__unter"><?= Fmt::h(Fmt::seit($m['created_at'])) ?></div></div>
        <div class="vg__warum"><?= Fmt::h(mb_substr((string) ($m['body'] ?? ''), 0, 220)) ?></div>
        <div class="vg__tun">
          <?php if ($m['link']): ?>
            <a class="knopf" href="<?= Fmt::h(url(ltrim((string) $m['link'], '/'))) ?>">Ansehen</a>
          <?php endif; ?>
          <?php /* Erledigt heisst gelesen, nicht geloescht: Die Meldung
                   verschwindet von dieser Liste, bleibt aber unter
                   Benachrichtigungen stehen, bis sie dort wegfliegt. */ ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="meldung_gelesen">
            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <input type="hidden" name="zurueck" value="heute">
            <button class="knopf">Erledigt</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (count($stoerungen) > $stMax): ?>
      <details class="stmehr">
        <summary>und <?= count($stoerungen) - $stMax ?> weitere</summary>
        <?php foreach (array_slice($stoerungen, $stMax) as $m): ?>
          <div class="vg">
            <div class="vg__wer"><span class="vg__name"><?= Fmt::h($m['title']) ?></span>
              <div class="vg__unter"><?= Fmt::h(Fmt::seit($m['created_at'])) ?></div></div>
            <div class="vg__warum"><?= Fmt::h(mb_substr((string) ($m['body'] ?? ''), 0, 220)) ?></div>
            <div class="vg__tun">
              <?php if ($m['link']): ?>
                <a class="knopf" href="<?= Fmt::h(url(ltrim((string) $m['link'], '/'))) ?>">Ansehen</a>
              <?php endif; ?>
              <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
                <?= Csrf::feld() ?><input type="hidden" name="tat" value="meldung_gelesen">
                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                <input type="hidden" name="zurueck" value="heute">
                <button class="knopf">Erledigt</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </details>
    <?php endif; ?>
    <p style="color:var(--leise);font-size:12.5px;margin-top:12px">
      Alle Meldungen stehen unter <a href="<?= Fmt::h(url('benachrichtigungen')) ?>">Benachrichtigungen</a>.</p>
  </div>
<?php endif; ?>

<?php /* ---------- Was demnächst fällig wird ----------
         Die Verwaltung konnte gut sagen, was gerade dran ist, und sehr gut,
         was gewesen ist. Was auf einen zukommt, stand nirgends -- und genau
         da gehen Dinge verloren: Ein Angebot läuft ab, ohne dass jemand
         nachgefragt hat. Ein Fragebogen liegt seit einer Woche. Nichts davon
         löst eine Meldung aus, weil nichts passiert; Stille löst nun einmal
         nichts aus.

         Steht nichts an, steht hier nichts. */ ?>
<?php if (!empty($faellig)): ?>
  <?php /* Zu: Es ist noch nichts faellig, sondern wird es. Wer morgens auf
           die Seite kommt, soll zuerst sehen, was heute dran ist -- das
           Kommende steht darunter und laesst sich aufziehen. Eilt etwas,
           steht die Schublade offen; dann ist es kein Ausblick mehr. */ ?>
  <?php $faelligEilt = false; foreach ($faellig as $f) { if (!empty($f['eilig'])) { $faelligEilt = true; break; } } ?>
  <details class="block klapp" <?= $faelligEilt ? 'open' : '' ?>>
    <summary><h2>Demnächst fällig<span class="mehr"><?= count($faellig) ?></span></h2></summary>
    <p style="color:var(--leise);font-size:12.5px;margin:2px 0 10px">
      Nichts davon ist ein Fehler — es passiert nur gerade nichts, und das fällt sonst niemandem auf.</p>
    <?php foreach ($faellig as $f): ?>
      <div class="vg">
        <div class="vg__wer">
          <a class="vg__name" href="<?= Fmt::h(url((string) $f['ziel'])) ?>"><?= Fmt::h((string) $f['wer']) ?></a>
          <div class="vg__unter"><?= Fmt::h((string) $f['was']) ?></div>
        </div>
        <div class="vg__warum"><?= Fmt::h((string) $f['warum']) ?></div>
        <div class="vg__tun">
          <?php if (!empty($f['eilig'])): ?>
            <span class="vg__ruht lang">eilt</span>
          <?php endif; ?>
          <a class="knopf" href="<?= Fmt::h(url((string) $f['ziel'])) ?>">Ansehen</a>
        </div>
      </div>
    <?php endforeach; ?>
  </details>
<?php endif; ?>

<div class="block">
  <h2>Du bist dran<span class="mehr"><?= count($liste['du']) ?></span></h2>
  <?php if (!$liste['du']): ?>
    <div class="leer">Nichts offen. Alles, was läuft, wartet gerade auf jemand anderen.</div>
  <?php else: foreach ($liste['du'] as $v) { $zeile($v); } endif; ?>
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
