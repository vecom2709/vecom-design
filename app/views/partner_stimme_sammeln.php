<?php
/* Kundenstimme sammeln (03.10.2026, PartnerStimmen — Uwe: Ja zu N4). Reiter „Werben“.
   Der Sammellink mit fertigem Text; darunter, wie viele warten und wie viele online sind.
   Gesetzt: $p, $sprache, $h. */
require_once dirname(__DIR__) . '/src/PartnerStimmen.php';
$ssW = static fn(string $k): string => Texte::h(Texte::PARTNER_STIMMEN[$k] ?? [], $sprache);
$ssLink = PartnerStimmen::link($p);
$ssMsg = strtr($ssW('p_msg'), ['{link}' => $ssLink]);
$ssZahl = ['neu' => 0, 'veroeffentlicht' => 0];
try { foreach (Db::all('SELECT status, COUNT(*) AS n FROM stimmen WHERE partner_id = ? GROUP BY status', [(int) $p['id']]) as $ssZ) { $ssZahl[(string) $ssZ['status']] = (int) $ssZ['n']; } } catch (Throwable $e) { }
?>
<div class="block pt" id="stimme-sammeln" data-reiter="werben">
  <h2><?= $h($ssW('p_titel')) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($ssW('p_text')) ?></p>
  <label for="ss_text" class="mk-sr"><?= $h($ssW('p_titel')) ?></label>
  <textarea id="ss_text" readonly rows="3" data-wachsen style="width:100%;box-sizing:border-box;font-size:14.5px;line-height:1.55;padding:10px 12px"><?= $h($ssMsg) ?></textarea>
  <div class="knoepfe">
    <button class="knopf haupt" type="button" data-kopie="ss_text"><?= $h($T('kopieren')) ?></button>
    <a class="knopf" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($ssMsg) ?>">WhatsApp</a>
    <button class="knopf" type="button" data-kopie-text="<?= $h($ssLink) ?>">Link</button>
  </div>
  <?php if ($ssZahl['neu'] + $ssZahl['veroeffentlicht'] > 0): ?>
    <p class="klein"><?= $h(implode(' · ', array_filter([$ssZahl['neu'] ? strtr($ssW('p_wartet'), ['{n}' => (string) $ssZahl['neu']]) : '', $ssZahl['veroeffentlicht'] ? strtr($ssW('p_online'), ['{n}' => (string) $ssZahl['veroeffentlicht']]) : '']))) ?></p>
  <?php endif; ?>
</div>
