<?php
/* Partner › Meldungen (Phase 9, 06.10.2026): „Neu von Vecom“ hat eine eigene Seite unter der Tür
   „Partner“ — vorher ein zugeklappter Kasten mitten in der Partnerliste. */
?>
<div class="kopf"><div><h1>Meldungen an Partner</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">„Neu von Vecom“ — steht oben auf der Startseite der Partner und geht einmal als Hinweis aufs Handy. Keine Mail.</p></div></div>
<?php /* Neu von Vecom (Phase 7b-2, 05.10.2026, Uwe: „Dashboard + Handy-Hinweis“): Meldung an eine Zielgruppe —
         steht auf der Startseite des Command Centers und geht einmal als Push. Keine Mail. */
  require_once dirname(__DIR__) . '/src/PartnerNews.php';
  $nwListe = []; try { $nwListe = PartnerNews::liste(10); } catch (Throwable $ex) { $nwListe = []; }
  $nwZiel = static fn(array $n): string => match ($n['ziel']) { 'level' => 'Level ' . ucfirst((string) $n['ziel_wert']), 'land' => 'Land ' . $n['ziel_wert'], 'neu' => 'neue Partner (' . PartnerNews::NEU_TAGE . ' Tage)', default => 'alle' }; ?>
<div class="block" id="news">
  <h2 style="font-size:15px;margin:0">Neue Meldung an Partner</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:8px 0 12px">Steht oben auf der Startseite der Partner, bis sie sie ausblenden oder das Datum vorbei ist, und geht einmal als Hinweis aufs Handy (wer die App hat). Keine Mail. Italienisch ist Pflicht; ohne Deutsch oder Englisch sehen diese Partner den italienischen Text.</p>
  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_news_senden">
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div class="feld" style="flex:0 0 220px"><label for="nw-ziel">Zielgruppe</label><select id="nw-ziel" name="ziel_kombi">
        <option value="alle:">Alle aktiven Partner</option>
        <?php foreach (PartnerNews::LEVEL as $nwL): ?><option value="level:<?= $nwL ?>">Level <?= ucfirst($nwL) ?></option><?php endforeach; ?>
        <option value="land:IT">Land Italien</option><option value="land:DE">Land Deutschland</option>
        <option value="neu:">Neue Partner (letzte <?= PartnerNews::NEU_TAGE ?> Tage)</option></select></div>
      <div class="feld" style="flex:0 0 170px"><label for="nw-bis">Sichtbar bis</label><input id="nw-bis" type="date" name="bis" value="<?= date('Y-m-d', strtotime('+' . PartnerNews::STANDARD_TAGE . ' days')) ?>"></div>
      <div class="feld" style="flex:1 1 260px"><label for="nw-link">Link (freiwillig, https:// oder /…)</label><input id="nw-link" name="link" maxlength="300" placeholder="https://… oder /partner.php?…"></div>
    </div>
    <?php foreach (['it' => 'Italienisch (Pflicht)', 'de' => 'Deutsch', 'en' => 'Englisch'] as $nwS => $nwW): ?>
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <div class="feld" style="flex:1 1 260px"><label for="nw-t-<?= $nwS ?>">Titel <?= $nwW ?></label><input id="nw-t-<?= $nwS ?>" name="titel_<?= $nwS ?>" maxlength="<?= PartnerNews::TITEL_MAX ?>"<?= $nwS === 'it' ? ' required minlength="3"' : '' ?>></div>
        <div class="feld" style="flex:2 1 380px"><label for="nw-x-<?= $nwS ?>">Text <?= $nwW ?></label><textarea id="nw-x-<?= $nwS ?>" name="text_<?= $nwS ?>" rows="2" maxlength="<?= PartnerNews::TEXT_MAX ?>"<?= $nwS === 'it' ? ' required minlength="10"' : '' ?>></textarea></div>
      </div>
    <?php endforeach; ?>
    <button class="knopf haupt">Meldung senden</button>
  </form>
  <?php if ($nwListe): ?>
    <div class="tabellenrahmen" style="margin-top:14px"><table id="news-liste"><thead><tr><th>Wann</th><th>Titel</th><th>Zielgruppe</th><th class="num">An</th><th class="num">Handy</th><th class="num">Ausgeblendet</th><th></th></tr></thead><tbody>
    <?php foreach ($nwListe as $nw): ?>
      <tr><td><?= Fmt::h(Fmt::datum((string) $nw['created_at'])) ?></td><td><?= Fmt::h((string) $nw['titel_it']) ?><?= $nw['zurueck_am'] ? ' <span class="marke2">zurückgezogen</span>' : '' ?></td>
        <td><?= Fmt::h($nwZiel($nw)) ?></td><td class="num"><?= (int) $nw['an'] ?></td><td class="num"><?= (int) $nw['push_an'] ?></td><td class="num"><?= (int) $nw['gelesen'] ?></td>
        <td><?php if (!$nw['zurueck_am']): ?><form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"><?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_news_zurueck"><input type="hidden" name="id" value="<?= (int) $nw['id'] ?>"><button class="knopf" style="min-height:30px;padding:4px 10px;font-size:12.5px">Zurückziehen</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>
