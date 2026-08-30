<div class="kopf"><div><div class="weg"><a href="<?= Fmt::h(url('kunden')) ?>">Kunden</a></div>
<h1><?= Fmt::h($k['name']) ?></h1></div>
<div class="rechts"><a class="knopf" href="<?= Fmt::h(url('kunden/' . $k['id'] . '/bearbeiten')) ?>">Bearbeiten</a>
<a class="knopf haupt" href="<?= Fmt::h(url('bestellungen/neu')) ?>">Bestellung erfassen</a></div></div>
<div class="zwei"><div>
  <div class="block"><h2>Bestellungen</h2><div class="tabellenrahmen"><table>
    <thead><tr><th>Nummer</th><th>Paket</th><th class="num">Preis</th><th>Status</th><th>Datum</th></tr></thead><tbody>
    <?php if (!$bestellungen): ?><tr><td colspan="5"><div class="leer">Noch keine Bestellung.</div></td></tr><?php endif; ?>
    <?php foreach ($bestellungen as $b): ?><tr>
      <td><a href="<?= Fmt::h(url('bestellungen/' . $b['id'])) ?>"><?= Fmt::h($b['order_no']) ?></a></td>
      <td><?= Fmt::h($b['package_name']) ?></td><td class="num"><?= Fmt::geld((int) $b['price_cents'], $b['currency']) ?></td>
      <td><span class="marke2 <?= Status::ton($b['status']) ?>"><?= Fmt::h(Status::label(Status::BESTELLUNG, $b['status'])) ?></span></td>
      <td><?= Fmt::h(Fmt::datum($b['ordered_at'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
  <div class="block"><h2>Projekte</h2><div class="tabellenrahmen"><table>
    <thead><tr><th>Projekt</th><th>Status</th><th class="num">Fortschritt</th><th>Deadline</th></tr></thead><tbody>
    <?php if (!$projekte): ?><tr><td colspan="4"><div class="leer">Noch kein Projekt.</div></td></tr><?php endif; ?>
    <?php foreach ($projekte as $p): ?><tr>
      <td><a href="<?= Fmt::h(url('projekte/' . $p['id'])) ?>"><?= Fmt::h($p['name']) ?></a></td>
      <td><span class="marke2 <?= Status::ton($p['status']) ?>"><?= Fmt::h(Status::label(Status::PROJEKT, $p['status'])) ?></span></td>
      <td class="num"><?= (int) $p['progress'] ?>%</td><td><?= Fmt::h(Fmt::datum($p['deadline'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
  <div class="block"><h2>Zahlungen</h2><div class="tabellenrahmen"><table>
    <thead><tr><th>Bestellung</th><th>Anbieter</th><th class="num">Betrag</th><th>Status</th><th>Bezahlt am</th></tr></thead><tbody>
    <?php if (!$zahlungen): ?><tr><td colspan="5"><div class="leer">Noch keine Zahlung.</div></td></tr><?php endif; ?>
    <?php foreach ($zahlungen as $z): ?><tr><td><?= Fmt::h($z['order_no']) ?></td><td><?= Fmt::h($z['provider']) ?></td>
      <td class="num"><?= Fmt::geld((int) $z['amount_cents'], $z['currency']) ?></td>
      <td><span class="marke2 <?= Status::ton($z['status']) ?>"><?= Fmt::h(Status::label(Status::ZAHLUNG, $z['status'])) ?></span></td>
      <td><?= Fmt::h(Fmt::zeit($z['paid_at'])) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></div>
</div><div>
  <div class="block"><h2>Kontakt</h2><table><tbody>
    <tr><td>E-Mail</td><td><?= Fmt::h($k['email']) ?></td></tr>
    <tr><td>Telefon</td><td><?= Fmt::h($k['phone'] ?: '—') ?></td></tr>
    <tr><td>Firma</td><td><?= Fmt::h($k['company'] ?: '—') ?></td></tr>
    <tr><td>Branche</td><td><?= Fmt::h($k['industry'] ?: '—') ?></td></tr>
    <tr><td>Adresse</td><td><?= Fmt::h(trim(($k['street'] ?? '') . ' ' . ($k['zip'] ?? '') . ' ' . ($k['city'] ?? '') . ' ' . ($k['country'] ?? ''))) ?: '—' ?></td></tr>
    <tr><td>Kunde seit</td><td><?= Fmt::h(Fmt::datum($k['created_at'])) ?></td></tr>
  </tbody></table></div>
  <?php if ($k['notes']): ?><div class="block"><h2>Interne Notizen</h2><p style="color:var(--dim);white-space:pre-wrap"><?= Fmt::h($k['notes']) ?></p></div><?php endif; ?>
  <div class="block"><h2>Verlauf</h2>
    <?php if (!$aktivitaeten): ?><div class="leer">Noch nichts.</div><?php else: ?><ul class="verlauf">
    <?php foreach ($aktivitaeten as $a): ?><li><span class="punkt"></span><span><?= Fmt::h($a['title']) ?></span>
      <span class="wann"><?= Fmt::h(Fmt::seit($a['created_at'])) ?></span></li><?php endforeach; ?></ul><?php endif; ?></div>
</div></div>
