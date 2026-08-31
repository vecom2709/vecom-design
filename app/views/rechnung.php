<div class="kopf"><div><div class="weg"><a href="<?= Fmt::h(url('rechnungen')) ?>">Belege</a></div>
  <h1><?= Fmt::h((string) ($r['titel'] ?: 'Beleg')) ?> <?= Fmt::h($r['invoice_no']) ?></h1></div>
  <div style="display:flex;gap:8px">
    <a class="knopf haupt" href="<?= Fmt::h(url('rechnungen/' . (int) $r['id'] . '/pdf')) ?>">PDF herunterladen</a>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="rechnung_schicken">
      <input type="hidden" name="zurueck" value="rechnungen/<?= (int) $r['id'] ?>">
      <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
      <button class="knopf"><?= $r['sent_at'] ? 'Noch einmal schicken' : 'An den Kunden schicken' ?></button></form>
  </div>
</div>

<div class="zwei"><div>
  <div class="block"><h2>Positionen</h2>
    <table><thead><tr><th>Leistung</th>
      <?php if ((float) $r['tax_rate'] > 0): ?><th style="text-align:right">Netto</th><th style="text-align:right">MwSt.</th><?php endif; ?>
      <th style="text-align:right">Betrag</th></tr></thead><tbody>
    <?php foreach ($posten as $p): ?>
      <tr><td><?= Fmt::h($p['text']) ?></td>
        <?php if ((float) $r['tax_rate'] > 0): ?>
          <td style="text-align:right"><?= Fmt::h(Fmt::geld((int) $p['netto'], (string) $r['currency'])) ?></td>
          <td style="text-align:right"><?= Fmt::h(Fmt::geld((int) $p['steuer'], (string) $r['currency'])) ?></td>
        <?php endif; ?>
        <td style="text-align:right"><?= Fmt::h(Fmt::geld((int) $p['brutto'], (string) $r['currency'])) ?></td></tr>
    <?php endforeach; ?>
      <tr><td colspan="<?= (float) $r['tax_rate'] > 0 ? 3 : 1 ?>" style="text-align:right;font-weight:650">Gesamt</td>
        <td style="text-align:right;font-weight:650;font-size:16px"><?= Fmt::h(Fmt::geld((int) $r['total_cents'], (string) $r['currency'])) ?></td></tr>
    </tbody></table>
    <?php if ((string) ($r['titel'] ?? '') === 'Zahlungsbeleg'): ?>
      <p style="color:var(--leise);font-size:12.5px;margin-top:14px">
        Dies ist ein Zahlungsbeleg, keine Rechnung im steuerlichen Sinn. Sobald in den
        <a href="<?= Fmt::h(url('einstellungen')) ?>">Einstellungen</a> eine Partita IVA steht,
        stellt die Verwaltung Rechnungen aus — mit eigenem Nummernkreis, beginnend bei 1.</p>
    <?php endif; ?>
    <?php if ($r['hinweis']): ?>
      <p style="color:var(--dim);font-size:13px;margin-top:10px;white-space:pre-wrap"><?= Fmt::h((string) $r['hinweis']) ?></p>
    <?php endif; ?>
  </div>
</div><div>
  <div class="block"><h2>Übersicht</h2><table><tbody>
    <tr><td>Kunde</td><td><a href="<?= Fmt::h(url('kunden/' . (int) $r['customer_id'])) ?>"><?= Fmt::h($r['firma'] ?: $r['kunde']) ?></a></td></tr>
    <tr><td>Bestellung</td><td><?= $r['order_id'] ? '<a href="' . Fmt::h(url('bestellungen/' . (int) $r['order_id'])) . '">' . Fmt::h((string) $r['order_no']) . '</a>' : '—' ?></td></tr>
    <tr><td>Projekt</td><td><?= $r['project_id'] ? '<a href="' . Fmt::h(url('projekte/' . (int) $r['project_id'])) . '">ansehen</a>' : '—' ?></td></tr>
    <tr><td>Ausgestellt</td><td><?= Fmt::h(Fmt::datum((string) $r['issued_at'])) ?></td></tr>
    <tr><td>Verschickt</td><td><?= Fmt::h($r['sent_at'] ? Fmt::zeit((string) $r['sent_at']) : 'noch nicht') ?></td></tr>
    <tr><td>Stand</td><td><span class="marke2 <?= Status::ton((string) $r['status']) ?>"><?= Fmt::h(ucfirst((string) $r['status'])) ?></span></td></tr>
  </tbody></table></div>
</div></div>
