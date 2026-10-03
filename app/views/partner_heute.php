<?php
/* „Heute zu tun“ und Fortschritt zur Provision (03.10.2026, PartnerHeute).
   Eingebunden aus partner.php oben im Reiter „Start“. Gesetzt: $p, $sprache, $h, $T.
   Jeder Punkt ist ein ganzer Tipp-Bereich und springt dorthin, wo die Arbeit liegt —
   partner-reiter.js öffnet dazu den richtigen Reiter. */
require_once dirname(__DIR__) . '/src/PartnerHeute.php';
$HT = Texte::PARTNER_HEUTE;
$htW = static fn(array $t): string => Texte::h($t, $sprache);
$htPunkte = PartnerHeute::punkte($p, $sprache);
$htF = PartnerHeute::fortschritt($p);
?>
<section class="ht" aria-labelledby="ht_titel">
  <div class="ht-geld">
    <div><span class="ht-l"><?= $h($htW($HT['verdient'])) ?></span>
      <b class="ht-betrag"><?= $h(Fmt::geld($htF['verdient'])) ?></b>
      <?php if ($htF['wartet'] > 0): ?><small><?= $h(strtr($htW($HT['wartet']), ['{betrag}' => Fmt::geld($htF['wartet'])])) ?></small><?php endif; ?></div>
    <?php if ($htF['stufe'] !== null): ?>
      <div class="ht-stufe">
        <span class="ht-l"><?= $h($htF['naechste'] === null ? strtr($htW($HT['oben']), ['{stufe}' => $T('st_' . $htF['stufe'])])
              : strtr($htW($HT[$htF['fehlen'] === 1 ? 'bis_eins' : 'bis']), ['{n}' => (string) $htF['fehlen'], '{stufe}' => $T('st_' . $htF['naechste'])])) ?></span>
        <span class="ht-balken" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $htF['anteil'] ?>"><i style="width:<?= $htF['naechste'] === null ? 100 : max(3, (int) $htF['anteil']) ?>%"></i></span>
      </div>
    <?php endif; ?>
  </div>
  <h2 id="ht_titel"><?= $h($htW($HT['titel'])) ?></h2>
  <?php require_once dirname(__DIR__) . '/src/PartnerAutomatik.php';
        if (PartnerAutomatik::ruhig((int) $p['id'])): /* Urlaubsmodus sichtbar machen, sonst wundert man sich über die Stille */ ?>
    <p class="klein" style="margin:0 0 8px"><a href="#automatik" style="color:var(--cyan)"><?= $h(strtr(Texte::h(Texte::PARTNER_AUTOMATIK['ruhig'], $sprache), ['{datum}' => date($sprache === 'de' ? 'd.m.Y' : 'd/m/Y', (int) strtotime((string) PartnerAutomatik::einstellungen((int) $p['id'])['ruhe_bis']))])) ?></a></p>
  <?php endif; ?>
  <?php if (!$htPunkte): ?>
    <p class="klein" style="margin:0"><?= $h($htW($HT['leer'])) ?></p>
  <?php else: ?>
    <ul class="ht-liste">
      <?php foreach ($htPunkte as $hp): $htText = strtr($htW($HT['punkte'][$hp['k']][$hp['n'] === 1 ? 0 : 1]), ['{n}' => (string) $hp['n'], '{titel}' => $hp['titel']]); ?>
        <li><a class="ht-p<?= in_array($hp['k'], ['kontakte', 'heiss', 'nachhaken', 'anrufen'], true) ? ' warm' : '' ?>" href="#<?= $h($hp['anker']) ?>" data-heute="<?= $h($hp['k']) ?>">
          <span><?= $h(rtrim($htText, ' :')) ?></span><span class="ht-los" aria-hidden="true"><?= $h($htW($HT['los'])) ?></span></a></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
