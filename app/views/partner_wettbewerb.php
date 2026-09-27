<?php
/* Monatsrangliste (27.09.2026). Eingebunden aus partner.php im Reiter „Start“.
   Gesetzt: $p, $sprache, $h, $selbst. Namen anderer Partner nur mit deren
   Zustimmung; ohne sie steht dort „Partner“. */
$MK = static fn(string $k): string => Texte::h(Texte::PARTNER_MARKETING[$k] ?? [], $sprache);
$wb = PartnerWettbewerb::monat((int) $p['id']);
$wbMonat = (Texte::PARTNER_MARKETING['monate'][$sprache] ?? Texte::PARTNER_MARKETING['monate']['it'])[(int) substr($wb['monat'], 5, 2) - 1];
$wbZeile = static function (array $z) use ($h, $MK): string {
    $wer = $z['ich'] ? $MK('wb_sie') : ($z['name'] ?? $MK('wb_partner'));
    return '<li class="' . ($z['ich'] ? 'ich' : '') . '"><span class="wb-rang">' . (int) $z['rang'] . '</span><span class="wb-wer">' . $h($wer) . '</span>'
         . '<span class="wb-zahl">' . $h(strtr($MK('wb_zeile'), ['{v}' => (string) $z['verkaeufe'], '{k}' => (string) $z['kunden']])) . '</span></li>';
};
$wbIchDrin = $wb['ich'] !== null && array_filter($wb['liste'], static fn($z) => $z['ich']);
?>
<div class="block pt" id="wettbewerb" data-reiter="start">
  <h2><?= $h(strtr($MK('wb_titel'), ['{monat}' => $wbMonat])) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($MK('wb_text')) ?></p>
  <?php if ($wb['teilnehmer'] === 0): ?>
    <p class="wb-leer"><?= $h($MK('wb_leer')) ?></p>
  <?php else: ?>
    <ol class="wb-liste">
      <?php foreach ($wb['liste'] as $z): echo $wbZeile($z); endforeach; ?>
      <?php if (!$wbIchDrin && $wb['ich'] !== null && $wb['ich']['rang'] > 0): ?><li class="wb-luecke" aria-hidden="true">…</li><?= $wbZeile($wb['ich']) ?><?php endif; ?>
    </ol>
    <?php if ($wb['ich'] === null || $wb['ich']['rang'] === 0): ?><p class="klein"><?= $h($MK('wb_nicht')) ?></p><?php endif; ?>
  <?php endif; ?>
  <form method="post" action="<?= $h($selbst()) ?>#wettbewerb" class="wb-name">
    <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="wettbewerb_name">
    <label><input type="checkbox" name="an" value="1" <?= !empty($p['wettbewerb_name']) ? 'checked' : '' ?> onchange="this.form.submit()" style="width:auto">
      <span><?= $h(strtr($MK('wb_name'), ['{name}' => PartnerWettbewerb::vorname((string) $p['name'])])) ?><small><?= $h($MK('wb_name_klein')) ?></small></span></label>
    <noscript><button class="knopf"><?= $h($MK('wb_speichern')) ?></button></noscript>
  </form>
</div>
