<div class="kopf"><div><h1><?= $istRechnung ? 'Rechnungen' : 'Zahlungsbelege' ?></h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px">
    Zu jeder bezahlten Rate entsteht ein Dokument — bei der Anzahlung eines, bei der Restzahlung eines.
    <?php if (!$istRechnung): ?><br>
      Solange in den Einstellungen keine Partita IVA steht, sind das <b>Zahlungsbelege</b>, keine Rechnungen
      im steuerlichen Sinn.
    <?php endif; ?>
  </p></div>
  <div style="text-align:right"><div style="color:var(--leise);font-size:12px">Ausgestellt <?= date('Y') ?></div>
    <div style="font-size:20px;font-weight:650"><?= Fmt::h(Fmt::geld((int) $summe)) ?></div></div>
</div>

<?php if ($ohneBeleg): ?>
  <div class="block">
    <h2>Ohne Beleg</h2>
    <p style="color:var(--dim);font-size:13.5px;margin-bottom:12px">
      Diese Zahlungen sind gebucht, haben aber noch kein Dokument. Normalerweise entsteht es von allein;
      diese hier stammen aus der Zeit davor.</p>
    <table><tbody>
    <?php foreach ($ohneBeleg as $z): ?>
      <tr>
        <td><?= Fmt::h((string) ($z['bezeichnung'] ?: ucfirst((string) $z['art']))) ?>
          <br><small style="color:var(--leise)"><?= Fmt::h($z['firma'] ?: $z['kunde']) ?> · <?= Fmt::h((string) $z['order_no']) ?></small></td>
        <td style="text-align:right;white-space:nowrap"><?= Fmt::h(Fmt::geld((int) $z['amount_cents'], (string) $z['currency'])) ?></td>
        <td style="text-align:right;width:150px">
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="rechnung_erzeugen">
            <input type="hidden" name="zurueck" value="rechnungen">
            <input type="hidden" name="id" value="<?= (int) $z['id'] ?>">
            <button class="knopf">Beleg erstellen</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
<?php endif; ?>

<div class="block">
  <?php if (!$liste): ?>
    <div class="leer">Noch keine Belege. Der erste entsteht mit der ersten bezahlten Rate.</div>
  <?php else: ?>
    <div class="tabellenrahmen"><table><thead><tr><th>Nummer</th><th>Kunde</th><th>Leistung</th><th>Datum</th><th>Betrag</th><th></th></tr></thead><tbody>
    <?php foreach ($liste as $r): ?>
      <tr>
        <td><a href="<?= Fmt::h(url('rechnungen/' . (int) $r['id'])) ?>"><?= Fmt::h($r['invoice_no']) ?></a>
          <?php if ($r['sent_at']): ?><br><small style="color:var(--gruen)">verschickt</small><?php endif; ?></td>
        <td><a href="<?= Fmt::h(url('kunden/' . (int) $r['customer_id'])) ?>"><?= Fmt::h($r['firma'] ?: $r['kunde']) ?></a></td>
        <td><?= Fmt::h(match ((string) $r['art']) {
              'anzahlung' => 'Anzahlung', 'restzahlung' => 'Restzahlung', default => 'Zahlung' }) ?>
          <?php if ($r['order_no']): ?><br><small style="color:var(--leise)"><?= Fmt::h((string) $r['order_no']) ?></small><?php endif; ?></td>
        <td style="white-space:nowrap"><?= Fmt::h(Fmt::datum((string) $r['issued_at'])) ?></td>
        <td style="white-space:nowrap;font-variant-numeric:tabular-nums"><?= Fmt::h(Fmt::geld((int) $r['total_cents'], (string) $r['currency'])) ?></td>
        <td style="text-align:right"><a class="knopf" href="<?= Fmt::h(url('rechnungen/' . (int) $r['id'] . '/pdf')) ?>">PDF</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
