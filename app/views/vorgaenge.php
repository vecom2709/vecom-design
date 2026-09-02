<?php
/**
 * Alle Vorgänge — eine Liste statt vier.
 *
 * Anfragen, Bestellungen und Projekte waren drei Listen für dieselben
 * Leute. Hier steht jeder genau einmal, mit der Stufe, auf der er gerade
 * steht.
 */
$gruppen = [];
foreach ($liste as $v) { $gruppen[$v['stufe']][] = $v; }
?>

<div class="kopf">
  <div><h1>Vorgänge</h1>
    <div class="weg"><?= count($liste) ?> insgesamt — von der Anfrage bis zur fertigen Seite</div></div>
  <div class="rechts">
    <a class="knopf" href="<?= Fmt::h(url('heute')) ?>">Heute</a>
    <a class="knopf haupt" href="<?= Fmt::h(url('bestellungen/neu')) ?>">Bestellung erfassen</a></div>
</div>

<?php if (!$liste): ?>
  <div class="block"><div class="leer">Noch kein Vorgang. Sobald jemand das Formular auf der
    Website ausfüllt, steht er hier.</div></div>
<?php endif; ?>

<?php foreach (Vorgang::STUFEN as $schl => $wort): ?>
  <?php if (empty($gruppen[$schl])) { continue; } ?>
  <div class="block">
    <h2><?= Fmt::h($wort) ?><span class="mehr"><?= count($gruppen[$schl]) ?></span></h2>
    <?php foreach ($gruppen[$schl] as $v): ?>
      <?php $tage = Vorgang::ruhtSeitTagen($v); ?>
      <div class="vg">
        <div class="vg__wer">
          <a class="vg__name" href="<?= Fmt::h(url('vorgaenge/' . $v['schluessel'])) ?>"><?= Fmt::h($v['kunde']) ?></a>
          <div class="vg__unter">
            <?= $v['bestellnr'] !== '' ? Fmt::h($v['bestellnr']) . ' · ' : '' ?>
            <?= $v['paket'] !== '' ? Fmt::h($v['paket']) : 'noch kein Paket' ?>
            <?= $v['preis'] > 0 ? ' · ' . Fmt::geld($v['preis'], $v['waehrung']) : '' ?></div>
        </div>
        <div class="vg__warum"><?= Fmt::h($v['warum']) ?></div>
        <div class="vg__tun">
          <span class="marke2 <?= $v['dran'] === Vorgang::DU ? 'warnung' : ($v['dran'] === Vorgang::NIEMAND ? 'gut' : '') ?>">
            <?= $v['dran'] === Vorgang::DU ? 'du' : ($v['dran'] === Vorgang::KUNDE ? 'Kunde' : 'fertig') ?></span>
          <span class="vg__ruht <?= $tage >= 7 ? 'lang' : '' ?>">
            <?= $tage === 0 ? 'heute' : ($tage === 1 ? '1 Tag' : "$tage Tage") ?></span>
          <a class="knopf" href="<?= Fmt::h(url('vorgaenge/' . $v['schluessel'])) ?>">Öffnen</a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
