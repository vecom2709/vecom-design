<?php
/* „Automatisch für Sie“ (03.10.2026, PartnerAutomatik). Eingebunden aus partner.php
   im Reiter „Profil“. Gesetzt: $p, $sprache, $h, $selbst. Ein Formular, ein Knopf —
   jeder Schalter sagt in einem Satz, was er tut. */
require_once dirname(__DIR__) . '/src/PartnerAutomatik.php';
$PA = Texte::PARTNER_AUTOMATIK;
$paW = static fn(array $t): string => Texte::h($t, $sprache);
$paE = PartnerAutomatik::einstellungen((int) $p['id']);
$paPush = (int) Db::wert('SELECT COUNT(*) FROM partner_push WHERE partner_id = ?', [(int) $p['id']], 0) > 0;
$paDatum = static fn(string $d): string => date($sprache === 'de' ? 'd.m.Y' : 'd/m/Y', (int) strtotime($d));
?>
<div class="block pt" id="automatik" data-reiter="profil">
  <h2><?= $h($paW($PA['titel'])) ?></h2>
  <p class="klein" style="margin-top:0"><?= $h($paW($PA['text'])) ?></p>
  <?php if (($_GET['pa'] ?? '') === '1'): ?><div class="hinweis gut" role="status"><?= $h($paW($PA['gespeichert'])) ?></div><?php endif; ?>
  <?php if (PartnerAutomatik::ruhig((int) $p['id'])): ?><div class="hinweis" role="status"><?= $h(strtr($paW($PA['ruhig']), ['{datum}' => $paDatum((string) $paE['ruhe_bis'])])) ?></div><?php endif; ?>
  <?php if (!$paPush): ?><p class="klein pa-ohne"><a href="#app"><?= $h($paW($PA['ohne_push'])) ?></a></p><?php endif; ?>
  <form method="post" action="<?= $h($selbst()) ?>#automatik" class="pa">
    <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="automatik">
    <?php foreach (PartnerAutomatik::SCHALTER as $paK => $_): [$paT, $paX] = $PA['schalter'][$paK]; ?>
      <div class="pa-zeile">
        <label class="pa-schalter"><input type="checkbox" name="<?= $h($paK) ?>" value="1" role="switch"<?= $paE[$paK] ? ' checked' : '' ?>>
          <span class="pa-was"><b><?= $h($paW($paT)) ?></b><small><?= $h($paW($paX)) ?></small></span></label>
        <?php if ($paK === 'autopilot'): ?>
          <div class="pa-wahl">
            <label><span><?= $h($paW($PA['anzahl'])) ?></span><select name="anzahl"><?php foreach (PartnerAutomatik::ANZAHL as $paN): ?><option value="<?= $paN ?>"<?= $paN === $paE['anzahl'] ? ' selected' : '' ?>><?= $paN ?></option><?php endforeach; ?></select></label>
            <label><span><?= $h($paW($PA['stunde'])) ?></span><select name="stunde"><?php foreach (PartnerAutomatik::STUNDEN as $paS): ?><option value="<?= $paS ?>"<?= $paS === $paE['stunde'] ? ' selected' : '' ?>><?= $h(strtr($paW($PA['uhr']), ['{h}' => (string) $paS])) ?></option><?php endforeach; ?></select></label>
          </div>
        <?php endif; ?>
        <?php if ($paK === 'kalender' && $paE['kalender']): $paIcs = PartnerAutomatik::icsLink((int) $p['id']); ?>
          <div class="pa-kal">
            <a class="knopf klein-knopf" href="<?= $h(preg_replace('~^https?://~', 'webcal://', $paIcs)) ?>"><?= $h($paW($PA['kal_abo'])) ?></a>
            <button class="knopf klein-knopf" type="button" data-kopie-text="<?= $h($paIcs) ?>"><?= $h($paW($PA['kal_link'])) ?></button>
            <small><?= $h($paW($PA['kal_hinweis'])) ?></small>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <div class="pa-zeile">
      <label class="pa-schalter"><input type="checkbox" name="ruhe" value="1" role="switch"<?= $paE['ruhe_bis'] !== null ? ' checked' : '' ?>>
        <span class="pa-was"><b><?= $h($paW($PA['ruhe'])) ?></b><small><?= $h($paW($PA['ruhe_text'])) ?></small></span></label>
      <div class="pa-wahl"><label><span><?= $h($paW($PA['ruhe_bis'])) ?></span>
        <input type="date" name="ruhe_bis" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+' . PartnerAutomatik::RUHE_MAX_TAGE . ' days')) ?>" value="<?= $h((string) ($paE['ruhe_bis'] ?? date('Y-m-d', strtotime('+7 days')))) ?>"></label></div>
    </div>
    <button class="knopf haupt" type="submit"><?= $h($paW($PA['speichern'])) ?></button>
  </form>
</div>
