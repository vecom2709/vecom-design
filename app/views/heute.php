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
          <span class="marke2"><?= Fmt::h($v['stufe_wort']) ?></span>
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
    <span class="marke2"><?= count($liste['du']) ?> bei dir</span>
    <span class="marke2"><?= count($liste['kunde']) ?> beim Kunden</span>
    <?php if ($offenGeld > 0): ?><span class="marke2 warnung"><?= Fmt::geld($offenGeld) ?> offen</span><?php endif; ?>
    <a class="knopf" href="<?= Fmt::h(url('vorgaenge')) ?>">Alle Vorgänge</a>
  </div>
</div>

<?php if ($stoerungen): ?>
  <div class="block" style="border-color:rgba(255,138,138,.32)">
    <h2 style="color:var(--rot)">Das läuft nicht<span class="mehr"><?= count($stoerungen) ?></span></h2>
    <?php foreach ($stoerungen as $m): ?>
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
    <p style="color:var(--leise);font-size:12.5px;margin-top:12px">
      Alle Meldungen stehen unter <a href="<?= Fmt::h(url('benachrichtigungen')) ?>">Benachrichtigungen</a>.</p>
  </div>
<?php endif; ?>

<div class="block">
  <h2>Du bist dran<span class="mehr"><?= count($liste['du']) ?></span></h2>
  <?php if (!$liste['du']): ?>
    <div class="leer">Nichts offen. Alles, was läuft, wartet gerade auf jemand anderen.</div>
  <?php else: foreach ($liste['du'] as $v) { $zeile($v); } endif; ?>
</div>

<div class="block">
  <h2>Der Kunde ist dran<span class="mehr"><?= count($liste['kunde']) ?></span></h2>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 10px">
    Hier musst du nichts tun — außer nachfassen, wenn es zu lange still ist.</p>
  <?php if (!$liste['kunde']): ?>
    <div class="leer">Niemand lässt dich warten.</div>
  <?php else: foreach ($liste['kunde'] as $v) { $zeile($v); } endif; ?>
</div>

<?php if ($liste['ruht']): ?>
  <div class="block">
    <h2>Läuft<span class="mehr"><?= count($liste['ruht']) ?></span></h2>
    <?php foreach ($liste['ruht'] as $v) { $zeile($v); } ?>
  </div>
<?php endif; ?>
