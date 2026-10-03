<?php
/* „Wer auf Ihrer Seite war“ (03.10.2026, PartnerBesuche — Uwe: Ja zu K1, K2, K3, N1, N2).
   Eingebunden aus partner.php im Reiter „Start“. Gesetzt: $p, $sprache, $h, $selbst.
   Oben, wer sich melden lassen will (mit fertigem Text), darunter jeder Besuch der
   letzten 14 Tage — die ersten sechs offen, der Rest hinter „Weitere … zeigen“. */
require_once dirname(__DIR__) . '/src/PartnerBesuche.php';
$PB = Texte::PARTNER_BESUCHE;
$pbW = static fn(string $k): string => Texte::h($PB[$k] ?? [], $sprache);
$pbKontakte = PartnerBesuche::kontakte((int) $p['id']);
$pbListe = PartnerBesuche::liste($p, $sprache);
$pbZeit = static function (string $t) use ($pbW, $sprache): string {
    $ts = (int) strtotime($t);
    $tag = date('Y-m-d', $ts) === date('Y-m-d') ? $pbW('oggi') : (date('Y-m-d', $ts) === date('Y-m-d', strtotime('-1 day')) ? $pbW('ieri') : date($sprache === 'de' ? 'd.m.' : 'd/m', $ts));
    return $tag . ' ' . date('H:i', $ts);
};
$pbDauer = static fn(int $s): string => $s >= 60 ? strtr($pbW('min'), ['{n}' => (string) (int) round($s / 60)]) : strtr($pbW('sek'), ['{n}' => (string) max(1, $s)]);
$pbWann = static fn(array $k): string => $k['wann_von'] ? date($sprache === 'de' ? 'd.m.' : 'd/m', (int) strtotime((string) $k['wann_von'])) . ' ' . date('H', (int) strtotime((string) $k['wann_von'])) . '–' . date('H', (int) strtotime((string) $k['wann_bis'])) . ($sprache === 'de' ? ' Uhr' : '') : '';
$pbZeile = static function (array $b) use ($pbW, $pbZeit, $pbDauer, $h): string {
    $ort = $b['ort'];
    $o = '<li class="pb-b pb-' . $h($b['chance']) . '"><div class="pb-kopf"><b>' . $h($pbZeit($b['zeit'])) . ' · ' . $h($b['plattform']) . '</b>'
       . '<span class="pb-chance">' . $h($pbW('chance_' . $b['chance'])) . '</span></div>';
    if ($b['beitrag'] !== null) { $o .= '<span class="pb-beitrag">' . $h($b['beitrag']) . '</span>'; }
    $o .= '<small>' . $h(implode(' · ', array_filter([$ort !== '' ? $ort : $pbW('ohne_ort'), $pbW('g_' . $b['geraet']) !== '' ? $pbW('g_' . $b['geraet']) : $b['geraet'],
              $b['seiten'] === 1 ? $pbW('seite') : strtr($pbW('seiten'), ['{n}' => (string) $b['seiten']]), $pbDauer((int) $b['sekunden'])]))) . '</small>';
    if ($b['taten']) { $o .= '<span class="pb-taten">' . $h(implode(' · ', $b['taten'])) . '</span>'; }
    if ($b['chance'] === 'hoch' && $b['kontakt'] === null) { $o .= '<a class="pb-tipp" href="#antworten">' . $h($pbW('tipp_hoch')) . '</a>'; }
    return $o . '</li>';
};
?>
<div class="block pt" id="besuche" data-reiter="start"<?= $pbKontakte ? ' data-punkt="1"' : '' ?>>
  <h2><?= $h($pbW('titel')) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($pbW('text')) ?></p>

  <?php if ($pbKontakte): ?>
    <div class="pb-kontakte" role="region" aria-labelledby="pb_k_titel">
      <h3 class="md-h" id="pb_k_titel"><?= $h($pbW('kontakte_titel')) ?> (<?= count($pbKontakte) ?>)</h3>
      <p class="klein" style="margin:0 0 8px"><?= $h($pbW('kontakte_text')) ?></p>
      <ul class="pb-liste">
        <?php foreach ($pbKontakte as $k): $kWa = PartnerBesuche::waLink($p, $k); $kTel = (string) preg_replace('~[^\d+]~', '', (string) ($k['telefon'] ?? '')); ?>
          <li class="pb-b pb-hoch">
            <div class="pb-kopf"><b><?= $h((string) $k['name']) ?></b><span class="pb-chance"><?= $h($pbW('chance_hoch')) ?></span></div>
            <?php if ($k['wann_von']): ?><small><?= $h(strtr($pbW('kontakt_wann'), ['{wann}' => $pbWann($k)])) ?></small><?php endif; ?>
            <div class="knoepfe" style="margin-top:6px">
              <?php if ($kTel !== ''): ?><a class="knopf klein-knopf haupt" href="tel:<?= $h($kTel) ?>"><?= $h($pbW('anrufen')) ?></a><?php endif; ?>
              <?php if ($kWa): ?><a class="knopf klein-knopf" href="<?= $h($kWa) ?>" target="_blank" rel="noopener"><?= $h($pbW('whatsapp')) ?></a><?php endif; ?>
              <?php if (!empty($k['email'])): ?><a class="knopf klein-knopf" href="mailto:<?= $h((string) $k['email']) ?>?subject=<?= rawurlencode(Texte::h($PB['mail_betreff'], (string) $k['sprache'])) ?>&amp;body=<?= rawurlencode(PartnerBesuche::text($p, $k, 'mail')) ?>"><?= $h($pbW('email')) ?></a><?php endif; ?>
              <form method="post" action="<?= $h($selbst()) ?>#besuche" style="display:inline"><input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
                <input type="hidden" name="tat" value="kontakt_erledigt"><input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                <button class="knopf klein-knopf" type="submit"><?= $h($pbW('erledigt')) ?></button></form>
            </div>
            <details class="pb-text"><summary class="klein"><?= $h($pbW('whatsapp')) ?></summary><pre><?= $h(PartnerBesuche::text($p, $k, 'wa')) ?></pre></details>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (!$pbListe): ?>
    <p class="klein"><?= $h($pbW('leer')) ?></p>
  <?php else: $pbOffen = array_slice($pbListe, 0, 6); $pbRest = array_slice($pbListe, 6); ?>
    <ul class="pb-liste"><?php foreach ($pbOffen as $b) { echo $pbZeile($b); } ?></ul>
    <?php if ($pbRest): ?><details class="weitere"><summary><?= $h(strtr($T('kl_weitere'), ['{n}' => (string) count($pbRest)])) ?></summary>
      <ul class="pb-liste"><?php foreach ($pbRest as $b) { echo $pbZeile($b); } ?></ul></details><?php endif; ?>
  <?php endif; ?>
</div>
