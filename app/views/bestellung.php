<div class="kopf"><div><div class="weg"><a href="<?= Fmt::h(url('bestellungen')) ?>">Bestellungen</a></div>
<h1><?= Fmt::h($b['order_no']) ?></h1></div></div>
<div class="zwei"><div>
  <div class="block"><h2>Zahlungen</h2><div class="tabellenrahmen"><table>
  <thead><tr><th>Anbieter</th><th class="num">Betrag</th><th>Status</th><th>Bezahlt</th><th></th></tr></thead><tbody>
  <?php foreach ($zahlungen as $z): ?><tr>
    <td><?= Fmt::h($z['provider']) ?><?= $z['provider_ref'] ? '<br><small style="color:var(--leise)">' . Fmt::h($z['provider_ref']) . '</small>' : '' ?></td>
    <td class="num"><?= Fmt::geld((int) $z['amount_cents'], $z['currency']) ?></td>
    <td><span class="marke2 <?= Status::ton($z['status']) ?>"><?= Fmt::h(Status::label(Status::ZAHLUNG, $z['status'])) ?></span></td>
    <td><?= Fmt::h(Fmt::zeit($z['paid_at'])) ?></td>
    <td style="text-align:right"><?php if ($z['status'] !== 'bezahlt'): ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="zahlung_bestaetigen">
        <input type="hidden" name="zurueck" value="bestellungen/<?= (int) $b['id'] ?>">
        <input type="hidden" name="id" value="<?= (int) $z['id'] ?>"><input type="hidden" name="order_id" value="<?= (int) $b['id'] ?>">
        <button class="knopf">Als bezahlt buchen</button></form>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="zahlung_fehler">
        <input type="hidden" name="zurueck" value="bestellungen/<?= (int) $b['id'] ?>">
        <input type="hidden" name="id" value="<?= (int) $z['id'] ?>"><input type="hidden" name="order_id" value="<?= (int) $b['id'] ?>">
        <button class="knopf stumm">Fehlgeschlagen</button></form>
    <?php endif; ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <p style="color:var(--leise);font-size:12.5px;margin-top:10px">Später übernimmt der Zahlungsanbieter diesen Schritt per Webhook —
  die Wirkung ist dieselbe, weil beide Wege dieselbe Ereignislogik aufrufen.</p></div>

  <div class="block"><h2>Verlauf</h2>
    <?php if (!$aktivitaeten): ?><div class="leer">Noch nichts.</div><?php else: ?><ul class="verlauf">
    <?php foreach ($aktivitaeten as $a): ?><li><span class="punkt"></span><span><?= Fmt::h($a['title']) ?></span>
      <span class="wann"><?= Fmt::h(Fmt::seit($a['created_at'])) ?></span></li><?php endforeach; ?></ul><?php endif; ?></div>
</div><div>
  <div class="block"><h2>Übersicht</h2><table><tbody>
    <tr><td>Kunde</td><td><a href="<?= Fmt::h(url('kunden/' . $b['customer_id'])) ?>"><?= Fmt::h($b['kunde']) ?></a></td></tr>
    <tr><td>Paket</td><td><?= Fmt::h($b['package_name']) ?></td></tr>
    <tr><td>Preis</td><td><?= Fmt::geld((int) $b['price_cents'], $b['currency']) ?><?= $b['monthly_cents'] ? ' + ' . Fmt::geld((int) $b['monthly_cents']) . '/Mon.' : '' ?></td></tr>
    <tr><td>Bestellt</td><td><?= Fmt::h(Fmt::zeit($b['ordered_at'])) ?></td></tr>
    <tr><td>Projekt</td><td><?= $projekt ? '<a href="' . Fmt::h(url('projekte/' . $projekt['id'])) . '">' . Fmt::h($projekt['name']) . '</a>' : 'entsteht bei Zahlungseingang' ?></td></tr>
  </tbody></table></div>
  <div class="block"><h2>Status ändern</h2>
    <form method="post" action="<?= Fmt::h(url('')) ?>" class="leiste">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="bestellung_status">
      <input type="hidden" name="zurueck" value="bestellungen/<?= (int) $b['id'] ?>">
      <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
      <select name="status"><?php foreach (Status::BESTELLUNG as $w => $t): ?>
        <option value="<?= $w ?>" <?= $b['status'] === $w ? 'selected' : '' ?>><?= Fmt::h($t) ?></option><?php endforeach; ?></select>
      <button class="knopf">Setzen</button></form></div>
</div></div>
