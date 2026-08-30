<div class="kopf"><h1>Zahlungen</h1></div>
<div class="block"><div class="tabellenrahmen"><table>
<thead><tr><th>Bestellung</th><th>Kunde</th><th>Rate</th><th class="num">Betrag</th><th>Status</th><th>Anbieter</th><th>Bezahlt am</th></tr></thead><tbody>
<?php if (!$liste): ?><tr><td colspan="7"><div class="leer">Noch keine Zahlungen.</div></td></tr><?php endif; ?>
<?php foreach ($liste as $z): ?><tr>
  <td><a href="<?= Fmt::h(url('bestellungen/' . $z['order_id'])) ?>"><strong><?= Fmt::h($z['order_no']) ?></strong></a></td>
  <td><a href="<?= Fmt::h(url('kunden/' . $z['kunde_id'])) ?>"><?= Fmt::h($z['kunde']) ?></a></td>
  <td><?= Fmt::h($z['bezeichnung'] ?: ucfirst((string) $z['art'])) ?></td>
  <td class="num"><?= Fmt::geld((int) $z['amount_cents'], $z['currency']) ?></td>
  <td><span class="marke2 <?= Status::ton($z['status']) ?>"><?= Fmt::h(Status::label(Status::ZAHLUNG, $z['status'])) ?></span></td>
  <td style="color:var(--dim)"><?= Fmt::h($z['provider'] === 'offen' ? '—' : $z['provider']) ?></td>
  <td><?= Fmt::h(Fmt::zeit($z['paid_at'])) ?></td>
</tr><?php endforeach; ?>
</tbody></table></div></div>
