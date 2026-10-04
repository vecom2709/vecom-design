<?php
/* Kontaktdaten für Werbemittel (04.10.2026, Partner-Marketingcenter, Fundament).
   Name, E-Mail und Code stehen schon fest (aus der Bewerbung); hier kommen die
   Wege dazu, auf denen Interessenten den Partner direkt erreichen. WhatsApp ist
   dieselbe Nummer wie auf der Partnerseite — ein Feld, eine Stelle. */
$kdD = PartnerDaten::fuer($p);
$kdFehler = in_array($meldung, ['kd_f_telefon', 'kd_f_whatsapp', 'kd_f_telegram'], true);
$kdW = static fn(string $k, string $wert): string => $kdFehler ? (string) ($_POST[$k] ?? '') : $wert;
?>
<div class="block pt" id="kontaktdaten" data-reiter="profil">
  <h2><?= $h($T('kd_titel')) ?></h2>
  <?php if (($_GET['m'] ?? '') === 'kd_gut'): ?><div class="hinweis gut" role="status"><?= $h($T('kd_gut')) ?></div><?php endif; ?>
  <?php if ($kdFehler): ?><div class="hinweis schlecht" role="alert"><?= $h($T($meldung)) ?></div><?php endif; ?>
  <p class="klein" style="margin-top:0"><?= $h($T('kd_text')) ?></p>
  <p class="klein" style="margin:0 0 12px"><?= $h($kdD['name']) ?><?= $kdD['firma'] !== '' ? ' · ' . $h($kdD['firma']) : '' ?> · <?= $h($kdD['email']) ?> · <?= $h($T('kd_code')) ?> <b><?= $h($kdD['code']) ?></b></p>
  <form method="post" action="<?= $h($selbst()) ?>#kontaktdaten">
    <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
    <input type="hidden" name="tat" value="kontakt">
    <label for="kd_tel"><?= $h($T('kd_telefon')) ?></label>
    <input id="kd_tel" type="tel" name="telefon" maxlength="20" autocomplete="tel" inputmode="tel" placeholder="+39 333 123 4567"
           value="<?= $h($kdW('telefon', $kdD['telefon'])) ?>"<?= $meldung === 'kd_f_telefon' ? ' aria-invalid="true"' : '' ?>>
    <label for="kd_wa"><?= $h($T('kd_whatsapp')) ?></label>
    <input id="kd_wa" type="tel" name="whatsapp" maxlength="20" inputmode="tel" placeholder="+39 333 123 4567"
           value="<?= $h($kdW('whatsapp', $kdD['whatsapp'])) ?>"<?= $meldung === 'kd_f_whatsapp' ? ' aria-invalid="true"' : '' ?>>
    <label for="kd_tg"><?= $h($T('kd_telegram')) ?></label>
    <input id="kd_tg" type="text" name="telegram" maxlength="40" autocapitalize="off" spellcheck="false" placeholder="@name"
           value="<?= $h($kdW('telegram', $kdD['telegram'] !== '' ? '@' . $kdD['telegram'] : '')) ?>"<?= $meldung === 'kd_f_telegram' ? ' aria-invalid="true"' : '' ?>>
    <p class="klein" style="margin:2px 0 0"><?= $h($T('kd_hilfe')) ?></p>
    <button class="knopf haupt" type="submit"><?= $h($T('kd_speichern')) ?></button>
  </form>
</div>
