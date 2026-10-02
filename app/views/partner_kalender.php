<?php
/* Posting-Kalender (27.09.2026). Eingebunden aus partner.php im Reiter „Werben“.
   Gesetzt: $p, $sprache, $h. Jeden Tag ein fertiger Beitrag; die nächsten
   Tage stehen aufklappbar darunter, damit man vorausplanen kann. */
$MK = static fn(string $k): string => Texte::h(Texte::PARTNER_MARKETING[$k] ?? [], $sprache);
$kaTage = PartnerKalender::tage($p, $sprache, time(), 7);
$kaHeute = $kaTage[0];
$kaBald = PartnerKalender::bald(time(), 21);
$kaWt = Texte::PARTNER_MARKETING['tage'][$sprache] ?? Texte::PARTNER_MARKETING['tage']['it'];
$kaDatum = static fn(string $d): string => $kaWt[(int) date('w', strtotime($d))] . ' ' . date('d.m.', strtotime($d));
?>
<div class="block pt" id="kalender" data-reiter="werben">
  <h2><?= $h($MK('ka_titel')) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($MK('ka_text')) ?></p>
  <?php /* Laufende Aktion (28.09.2026): steht über dem Tagesbeitrag, solange sie läuft. */
        $kaAktion = PartnerMarketing::aktion(null, $sprache); if ($kaAktion): $kaAkB = PartnerMarketing::aktionBeitrag($p, $kaAktion, $sprache); ?>
    <div class="ka-heute" style="margin-bottom:12px">
      <div class="ka-kopf"><span class="ka-tag"><?= $h(Texte::h(Texte::PARTNER_PLUS['ak_titel'], $sprache)) ?> · <?= $h(PartnerMarketing::aktionRest($kaAktion, $sprache)) ?></span></div>
      <textarea id="ka_aktion" readonly rows="4" data-wachsen><?= $h($kaAkB) ?></textarea>
      <div class="knoepfe"><button class="knopf haupt" type="button" data-kopie="ka_aktion"><?= $h($MK('ka_kopieren')) ?></button>
        <a class="knopf" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($kaAkB) ?>"><?= $h($MK('ka_wa')) ?></a></div>
    </div>
  <?php endif; ?>
  <div class="ka-heute">
    <div class="ka-kopf"><span class="ka-tag"><?= $h($MK('ka_heute')) ?> · <?= $h($kaDatum($kaHeute['datum'])) ?></span>
      <b><?= $h($kaHeute['titel']) ?></b><?php if ($kaHeute['anlass']): ?> <span class="ka-marke"><?= $h($MK('ka_anlass')) ?></span><?php endif; ?></div>
    <textarea id="ka_0" readonly rows="7" data-wachsen><?= $h($kaHeute['text']) ?></textarea>
    <div class="knoepfe">
      <button class="knopf haupt" type="button" data-kopie="ka_0"><?= $h($MK('ka_kopieren')) ?></button>
      <a class="knopf" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($kaHeute['text']) ?>"><?= $h($MK('ka_wa')) ?></a>
      <button class="knopf" type="button" data-teilen="ka_0" hidden><?= $h($MK('ka_teilen')) ?></button>
      <a class="knopf" href="#medien"><?= $h($MK('ka_bild')) ?></a>
    </div>
  </div>
  <?php if ($kaBald): $kaBaldT = Texte::h(Texte::PARTNER_KALENDER['anlaesse'][$kaBald['schluessel']]['titel'], $sprache); ?>
    <p class="klein ka-bald">★ <?= $h(strtr($MK('ka_bald'), ['{n}' => (string) $kaBald['in'], '{anlass}' => $kaBaldT, '{datum}' => $kaDatum($kaBald['datum'])])) ?></p>
  <?php endif; ?>
  <details class="ka-woche">
    <summary><?= $h($MK('ka_woche')) ?></summary>
    <?php foreach (array_slice($kaTage, 1) as $i => $kt): $kid = 'ka_' . ($i + 1); ?>
      <details class="ka-tagfeld">
        <summary><span class="ka-tag"><?= $h($kaDatum($kt['datum'])) ?></span> <?= $h($kt['titel']) ?><?php if ($kt['anlass']): ?> <span class="ka-marke"><?= $h($MK('ka_anlass')) ?></span><?php endif; ?></summary>
        <textarea id="<?= $kid ?>" readonly rows="6" data-wachsen><?= $h($kt['text']) ?></textarea>
        <div class="knoepfe"><button class="knopf" type="button" data-kopie="<?= $kid ?>"><?= $h($MK('ka_kopieren')) ?></button>
          <a class="knopf" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($kt['text']) ?>"><?= $h($MK('ka_wa')) ?></a></div>
      </details>
    <?php endforeach; ?>
  </details>
</div>
