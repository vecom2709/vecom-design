<?php
/**
 * Ziel, Branche, Handlungsaufruf, Budget und Laufzeit einer Kampagne
 * (Growth Engine Phase 4) — dieselben Felder beim Anlegen und Ändern.
 * Erwartet: $kf (vorhandene Kampagne oder null), $kfId (Präfix für die IDs).
 */
$kfW = static fn(string $k, $vor = '') => $kf[$k] ?? $vor;
$kfBudget = !empty($kf['budget_cents']) ? number_format((int) $kf['budget_cents'] / 100, 2, ',', '') : '';
?>
<div class="feld"><label for="<?= $kfId ?>_ziel_art">Ziel <span class="mk-fein">(woran der Erfolg gemessen wird)</span></label>
  <select id="<?= $kfId ?>_ziel_art" name="ziel_art"><?php foreach (MkKampagne::ZIEL_ARTEN as $zk2 => [$zw2]): ?><option value="<?= $zk2 ?>"<?= $kfW('ziel_art', 'leads') === $zk2 ? ' selected' : '' ?>><?= Fmt::h($zw2) ?></option><?php endforeach; ?></select></div>
<div class="feld"><label for="<?= $kfId ?>_branche">Branche <span class="mk-fein">(wen die Kampagne anspricht)</span></label>
  <select id="<?= $kfId ?>_branche" name="branche"><option value="">— alle / keine bestimmte —</option>
    <?php foreach (MkKampagne::branchen() as $bk => $bw): ?><option value="<?= Fmt::h($bk) ?>"<?= $kfW('branche') === $bk ? ' selected' : '' ?>><?= Fmt::h($bw) ?></option><?php endforeach; ?></select></div>
<div class="feld"><label for="<?= $kfId ?>_cta">Handlungsaufruf (CTA)</label>
  <select id="<?= $kfId ?>_cta" name="cta" onchange="this.form.querySelector('[data-cta-text]').hidden = this.value !== 'eigen'"><option value="">— keiner festgelegt —</option>
    <?php foreach (MkKampagne::CTA as $ck => $cw): ?><option value="<?= $ck ?>"<?= $kfW('cta') === $ck ? ' selected' : '' ?>><?= Fmt::h($cw) ?></option><?php endforeach; ?></select></div>
<div class="feld" data-cta-text<?= $kfW('cta') === 'eigen' ? '' : ' hidden' ?>><label for="<?= $kfId ?>_cta_text">Eigener Handlungsaufruf</label>
  <input id="<?= $kfId ?>_cta_text" name="cta_text" maxlength="120" value="<?= Fmt::h((string) $kfW('cta_text')) ?>" placeholder="z. B. Jetzt Vorher/Nachher ansehen"></div>
<div class="feld"><label for="<?= $kfId ?>_budget">Budgetgrenze netto (€) <span class="mk-fein">(leer = keine)</span></label>
  <input id="<?= $kfId ?>_budget" name="budget" inputmode="decimal" value="<?= Fmt::h($kfBudget) ?>" placeholder="z. B. 150"></div>
<div class="feld"><label for="<?= $kfId ?>_budget_art">Budget gilt</label>
  <select id="<?= $kfId ?>_budget_art" name="budget_art"><?php foreach (MkKampagne::BUDGET_ARTEN as $bak => $baw): ?><option value="<?= $bak ?>"<?= $kfW('budget_art', 'gesamt') === $bak ? ' selected' : '' ?>><?= Fmt::h($baw) ?></option><?php endforeach; ?></select></div>
<div class="feld"><label for="<?= $kfId ?>_start">Start <span class="mk-fein">(optional)</span></label><input id="<?= $kfId ?>_start" type="date" name="start_am" value="<?= Fmt::h((string) $kfW('start_am')) ?>"></div>
<div class="feld"><label for="<?= $kfId ?>_ende">Ende <span class="mk-fein">(optional)</span></label><input id="<?= $kfId ?>_ende" type="date" name="ende_am" value="<?= Fmt::h((string) $kfW('ende_am')) ?>"></div>
<p class="mk-fein breit" style="margin:0">Die Budgetgrenze warnt bei <?= MkKampagne::BUDGET_WARNUNG ?> % und bei 100 % (Meldung unter „Was nicht läuft“). Sie stoppt keine Anzeige bei Meta oder Google — pausieren musst du dort.</p>
