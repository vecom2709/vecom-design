<div class="kopf"><div><div class="weg"><a href="<?= Fmt::h(url('projekte')) ?>">Projekte</a></div>
<h1><?= Fmt::h($p['name']) ?></h1></div></div>
<div class="zwei"><div>
  <div class="block"><h2>Ablauf</h2>
    <div class="balken" style="height:8px;margin-bottom:14px"><i style="width:<?= (int) $p['progress'] ?>%"></i></div>
    <form method="post" action="<?= Fmt::h(url('')) ?>" class="leiste">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="projekt_status">
      <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <select name="status"><?php foreach (Status::PROJEKT as $w => $t): ?>
        <option value="<?= $w ?>" <?= $p['status'] === $w ? 'selected' : '' ?>><?= Fmt::h($t) ?></option><?php endforeach; ?></select>
      <button class="knopf haupt">Status setzen</button></form>
    <p style="color:var(--leise);font-size:12.5px;margin-top:10px">Der Projektstatus zieht die Bestellung sinngemäß mit.
    Der technische Website-Status bleibt davon unberührt — er wird nur vom Monitoring gesetzt.</p></div>

  <div class="block"><h2>Verlauf</h2>
    <?php if (!$aktivitaeten): ?><div class="leer">Noch nichts.</div><?php else: ?><ul class="verlauf">
    <?php foreach ($aktivitaeten as $a): ?><li><span class="punkt"></span><span><?= Fmt::h($a['title']) ?><br><small><?= Fmt::h($a['actor']) ?></small></span>
      <span class="wann"><?= Fmt::h(Fmt::seit($a['created_at'])) ?></span></li><?php endforeach; ?></ul><?php endif; ?></div>
</div><div>
  <div class="block"><h2>Übersicht</h2><table><tbody>
    <tr><td>Kunde</td><td><a href="<?= Fmt::h(url('kunden/' . $p['customer_id'])) ?>"><?= Fmt::h($p['kunde']) ?></a></td></tr>
    <tr><td>Bestellung</td><td><?= $p['order_id'] ? '<a href="' . Fmt::h(url('bestellungen/' . $p['order_id'])) . '">' . Fmt::h((string) $p['order_no']) . '</a>' : '—' ?></td></tr>
    <tr><td>Projektstatus</td><td><span class="marke2 <?= Status::ton($p['status']) ?>"><?= Fmt::h(Status::label(Status::PROJEKT, $p['status'])) ?></span></td></tr>
    <tr><td>Website-Status</td><td><span class="marke2 <?= Status::ton((string) ($website['status'] ?? '')) ?>"><?= Fmt::h($website ? Status::label(Status::WEBSITE, $website['status']) : 'keine hinterlegt') ?></span></td></tr>
    <tr><td>Fragebogen</td><td><?= Fmt::h($fragebogen ? ucfirst((string) $fragebogen['status']) : '—') ?></td></tr>
    <tr><td>Start</td><td><?= Fmt::h(Fmt::datum($p['start_date'])) ?></td></tr>
  </tbody></table></div>
  <div class="block"><h2>Eckdaten</h2>
    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="projekt_felder">
      <input type="hidden" name="zurueck" value="projekte/<?= (int) $p['id'] ?>">
      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <div class="feld"><label>Deadline</label><input type="date" name="deadline" value="<?= Fmt::h($p['deadline'] ?? '') ?>"></div>
      <div class="feld"><label>Priorität</label><select name="priority">
        <?php foreach (['niedrig','normal','hoch'] as $pr): ?><option <?= $p['priority'] === $pr ? 'selected' : '' ?>><?= $pr ?></option><?php endforeach; ?></select></div>
      <div class="feld"><label>Vorschau-Link</label><input name="preview_url" value="<?= Fmt::h($p['preview_url'] ?? '') ?>"></div>
      <button class="knopf">Speichern</button></form></div>
</div></div>
