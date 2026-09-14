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

/* Wie viele Kunden hier NICHT vorkommen.
   Siehe die Begründung im Verteiler: Diese Seite zeigt Vorgänge, und ein
   Kunde ohne Bestellung und ohne Anfrage hat keinen. Die Zahl steht hier,
   damit niemand glauben muss, es gebe ihn nicht mehr. */
$kundenGesamt = (int) ($kunden ?? 0);
$ohneVorgang  = max(0, $kundenGesamt - (int) ($gezeigt ?? 0));
?>

<div class="kopf">
  <div><h1>Kunden</h1>
    <div class="weg"><?= count($liste) ?> <?= count($liste) === 1 ? 'Vorgang' : 'Vorgänge' ?> —
      von der Anfrage bis zur fertigen Seite</div></div>
  <div class="rechts">
    <a class="knopf" href="<?= Fmt::h(url('heute')) ?>">Heute</a>
    <a class="knopf" href="<?= Fmt::h(url('kunden')) ?>">Alle Kunden<?= $kundenGesamt > 0
        ? ' (' . $kundenGesamt . ')' : '' ?></a>
    <a class="knopf haupt" href="<?= Fmt::h(url('bestellungen/neu')) ?>">Bestellung erfassen</a></div>
</div>

<?php /* ---------- Die Zeile, die den Riss nennt ----------
         Ohne sie sieht diese Seite vollständig aus, auch wenn sie es nicht
         ist: Sie heißt „Kunden" und zeigt Vorgänge. Wer keinen hat, fehlt —
         und zwar lautlos, denn eine Liste ohne Lücke sieht aus wie eine
         Liste ohne Lücke. */ ?>
<?php if ($ohneVorgang > 0): ?>
  <div class="block" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
    <div style="flex:1 1 320px;min-width:0">
      <b><?= $ohneVorgang ?> <?= $ohneVorgang === 1 ? 'Kunde hat' : 'Kunden haben' ?>
        gerade keinen laufenden Vorgang.</b>
      <div style="color:var(--leise);font-size:13px;margin-top:3px">
        Hier stehen nur Anfragen, Bestellungen und Projekte. Wer von Hand angelegt wurde oder
        fertig ist, steht in der Kundenliste.</div>
    </div>
    <a class="knopf" href="<?= Fmt::h(url('kunden')) ?>">Kundenliste öffnen</a>
  </div>
<?php endif; ?>

<?php if (!$liste): ?>
  <div class="block"><div class="leer">
    <?php if ($kundenGesamt > 0): ?>
      Gerade läuft kein Vorgang. Deine <?= $kundenGesamt ?> <?= $kundenGesamt === 1
        ? 'Kunde steht' : 'Kunden stehen' ?> weiter in der
      <a href="<?= Fmt::h(url('kunden')) ?>">Kundenliste</a> — hier erscheinen sie wieder,
      sobald es eine Anfrage oder eine Bestellung gibt.
    <?php else: ?>
      Noch kein Vorgang. Sobald jemand das Formular auf der Website ausfüllt, steht er hier.
    <?php endif; ?>
  </div></div>
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
