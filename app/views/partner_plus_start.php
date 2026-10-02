<?php
/* Reiter „Start“ (28.09.2026, Uwe: Ja): laufende Aktion, heiße Kontakte,
   Nachhaken, 7-Tage-Kurs. Eingebunden aus partner.php.
   Gesetzt: $p, $sprache, $h, $selbst. Jeder Block erscheint nur, wenn er
   etwas zu sagen hat -- leere Kästen sind Rauschen. */
$PP = static fn(string $k): string => Texte::h(Texte::PARTNER_PLUS[$k] ?? [], $sprache);
$ppAktion = PartnerMarketing::aktion(null, $sprache);
$ppHeiss = PartnerMarketing::heisse((int) $p['id']);
$ppNach = PartnerMarketing::faellig((int) $p['id']);
$ppKurs = PartnerMarketing::kurs($p);
$ppZeit = static fn(int $min): string => $min < 60 ? strtr($PP('hk_min'), ['{n}' => (string) max(1, $min)]) : strtr($PP('hk_std'), ['{n}' => (string) intdiv($min, 60)]);
$ppHaken = static fn(bool $ja): string => '<svg class="pp-i" viewBox="0 0 20 20" aria-hidden="true">'
    . ($ja ? '<circle cx="10" cy="10" r="9"/><path d="M6 10.4l2.6 2.6L14 7.6"/>' : '<circle cx="10" cy="10" r="8.5"/>') . '</svg>';
?>
<?php if ($ppAktion): $ppAkB = PartnerMarketing::aktionBeitrag($p, $ppAktion, $sprache); ?>
<div class="block pt pp-aktion" id="aktion" data-reiter="start">
  <p class="pp-marke"><?= $h($PP('ak_titel')) ?> · <b><?= $h(PartnerMarketing::aktionRest($ppAktion, $sprache)) ?></b></p>
  <h2><?= $h(PartnerMarketing::aktionText($ppAktion, $sprache)) ?></h2>
  <p class="klein" style="margin:6px 0 4px"><?= $h($PP('ak_beitrag')) ?></p>
  <textarea id="pp_aktion" readonly rows="4"><?= $h($ppAkB) ?></textarea>
  <div class="knoepfe">
    <button class="knopf haupt" type="button" data-kopie="pp_aktion"><?= $h($PP('ak_kopieren')) ?></button>
    <a class="knopf" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($ppAkB) ?>">WhatsApp</a>
    <button class="knopf" type="button" data-teilen-text="<?= $h($ppAkB) ?>" hidden><?= $h($T('teilen_mehr')) ?></button>
  </div>
</div>
<?php endif; ?>

<?php if ($ppHeiss): ?>
<div class="block pt pp-heiss" id="heiss" data-reiter="start" data-punkt="1">
  <h2><?= $h($PP('hk_titel')) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($PP('hk_text')) ?></p>
  <ul class="pp-liste">
    <?php foreach ($ppHeiss as $hk): $hkText = PartnerMarketing::nachfassText($p, ['art' => 'check', 'titel' => $hk['host'], 'token' => $hk['token']], PartnerAnschreiben::spracheZurAdresse((string) $hk['host'], $sprache)); ?>
      <li><span class="pp-punkt" aria-hidden="true"></span>
        <span class="pp-was"><b><?= $h($hk['host']) ?></b><small><?= $h(strtr($PP('hk_vor'), ['{zeit}' => $ppZeit($hk['minuten'])])) ?> · <?= $h(strtr($PP('nf_gesehen'), ['{n}' => (string) $hk['aufrufe']])) ?></small></span>
        <a class="knopf klein-knopf haupt" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($hkText) ?>"><?= $h($PP('nf_wa')) ?></a></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if ($ppNach): ?>
<div class="block pt" id="nachhaken" data-reiter="start" data-punkt="1">
  <h2><?= $h($PP('nf_titel')) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($PP('nf_text')) ?></p>
  <?php foreach (array_slice($ppNach, 0, 8) as $i => $nf):
    $nfSp = $nf['art'] === 'firma' ? PartnerMarketing::betriebSprache($nf['land'], $sprache, $nf['sprache'] ?? '') : PartnerAnschreiben::spracheZurAdresse($nf['titel'], $sprache);
    $nfText = PartnerMarketing::nachfassText($p, $nf, $nfSp);
    $nfNr = $nf['art'] === 'firma' ? PartnerAnschreiben::waNummer($nf['telefon'], $nf['land'] ?: 'IT') : '';
    $nfWa = 'https://wa.me/' . $nfNr . '?text=' . rawurlencode($nfText); ?>
    <details class="pp-nf"<?= $i === 0 ? ' open' : '' ?>>
      <summary><b><?= $h($nf['titel']) ?></b>
        <small><?= $h(strtr($PP($nf['art'] === 'check' ? 'nf_check' : 'nf_firma'), ['{tage}' => (string) $nf['tage']])) ?><?= $nf['aufrufe'] > 0 ? ' · ' . $h(strtr($PP('nf_gesehen'), ['{n}' => (string) $nf['aufrufe']])) : '' ?></small></summary>
      <textarea id="pp_nf_<?= $i ?>" readonly rows="6"><?= $h($nfText) ?></textarea>
      <div class="knoepfe">
        <a class="knopf haupt" target="_blank" rel="noopener" href="<?= $h($nfWa) ?>"><?= $h($PP('nf_wa')) ?></a>
        <button class="knopf" type="button" data-kopie="pp_nf_<?= $i ?>"><?= $h($PP('ak_kopieren')) ?></button>
        <form method="post" action="<?= $h($selbst()) ?>#nachhaken" style="display:inline">
          <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="nachfass_ok">
          <input type="hidden" name="art" value="<?= $h($nf['art']) ?>"><input type="hidden" name="id" value="<?= (int) $nf['id'] ?>">
          <button class="knopf leise-knopf"><?= $h($PP('nf_ok')) ?></button></form>
      </div>
    </details>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$ppKurs['fertig']): ?>
<div class="block pt" id="kurs" data-reiter="start">
  <div class="pp-kopf"><h2><?= $h($PP('ku_titel')) ?></h2><span class="klein"><?= $h(strtr($PP('ku_stand'), ['{n}' => (string) $ppKurs['n']])) ?></span></div>
  <p class="klein" style="margin-top:0"><?= $h($PP('ku_text')) ?></p>
  <div class="es__balken" aria-hidden="true"><i style="width:<?= (int) round(100 * $ppKurs['n'] / PartnerMarketing::KURS_TAGE) ?>%"></i></div>
  <ol class="pp-kurs">
    <?php foreach (Texte::PARTNER_PLUS['kurs'] as $kn => $kd):
      $kOk = $ppKurs['erledigt'][$kn]; $kFrei = $kn <= $ppKurs['tag']; $kHeute = $kn === $ppKurs['tag'];
      $kAnker = [1 => 'profil', 2 => 'seite', 3 => 'werbung', 4 => 'kontakte', 5 => 'recherche', 6 => 'recherche', 7 => 'nachhaken'][$kn]; ?>
      <li class="<?= $kOk ? 'ok' : ($kHeute ? 'jetzt' : (!$kFrei ? 'zu' : '')) ?>">
        <?= $ppHaken($kOk) ?>
        <div class="pp-was"><span class="pp-tag"><?= $h(strtr($PP('ku_tag'), ['{n}' => (string) $kn])) ?></span> <b><?= $h(Texte::h($kd['titel'], $sprache)) ?></b>
          <?php if ($kFrei && !$kOk): ?><small><?= $h(Texte::h($kd['text'], $sprache)) ?></small><?php endif; ?>
          <?php if (!$kFrei && !$kOk): ?><small><?= $h(strtr($PP('ku_gesperrt'), ['{n}' => (string) $kn])) ?></small><?php endif; ?>
          <?php if ($kFrei && !$kOk): ?><div class="knoepfe" style="margin-top:6px">
            <a class="knopf klein-knopf<?= $kHeute ? ' haupt' : '' ?>" href="#<?= $kAnker ?>"><?= $h($PP('ku_los')) ?></a>
            <?php if (in_array($kn, PartnerMarketing::KURS_HAND, true)): ?>
              <form method="post" action="<?= $h($selbst()) ?>#kurs" style="display:inline"><input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
                <input type="hidden" name="tat" value="kurs_ok"><input type="hidden" name="nr" value="<?= (int) $kn ?>">
                <button class="knopf klein-knopf"><?= $h($PP('ku_ok')) ?></button></form>
            <?php endif; ?></div><?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
</div>
<?php endif; ?>
