<div class="kopf"><h1>Bestellungen</h1><div class="rechts">
<form class="leiste"><input type="search" name="q" value="<?= Fmt::h($q) ?>" placeholder="Nummer, Kunde, Paket">
<select name="status"><option value="">alle Status</option>
<?php foreach (Status::BESTELLUNG as $w => $t): ?><option value="<?= $w ?>" <?= $st === $w ? 'selected' : '' ?>><?= Fmt::h($t) ?></option><?php endforeach; ?>
</select>
<select name="sort"><?php foreach (['datum'=>'nach Datum','betrag'=>'nach Betrag','kunde'=>'nach Kunde'] as $w=>$t): ?>
<option value="<?= $w ?>" <?= $sort === $w ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select>
<button class="knopf">Anwenden</button></form>
<a class="knopf haupt" href="<?= Fmt::h(url('bestellungen/neu')) ?>">Bestellung erfassen</a></div></div>
<div class="block"><div class="tabellenrahmen"><table>
<thead><tr><th>Nummer</th><th>Kunde</th><th>Paket</th><th class="num">Preis</th><th>Zahlung</th><th>Status</th><th>Projekt</th><th>Datum</th></tr></thead><tbody>
<?php if (!$liste): ?><tr><td colspan="8"><div class="leer">Keine Bestellungen gefunden.</div></td></tr><?php endif; ?>
<?php foreach ($liste as $b): ?><tr>
<td><a href="<?= Fmt::h(url('bestellungen/' . $b['id'])) ?>"><strong><?= Fmt::h($b['order_no']) ?></strong></a></td>
<td><?= Fmt::h($b['kunde']) ?></td><td><?= Fmt::h($b['package_name']) ?></td>
<td class="num"><?= Fmt::geld((int) $b['price_cents'], $b['currency']) ?></td>
<td><span class="marke2 <?= Status::ton((string) $b['zahlstatus']) ?>"><?= Fmt::h(Status::label(Status::ZAHLUNG, $b['zahlstatus'])) ?></span></td>
<td><span class="marke2 <?= Status::ton($b['status']) ?>"><?= Fmt::h(Status::label(Status::BESTELLUNG, $b['status'])) ?></span></td>
<td><?= $b['projekt_id'] ? '<a href="' . Fmt::h(url('projekte/' . $b['projekt_id'])) . '">öffnen</a>' : '—' ?></td>
<td><?= Fmt::h(Fmt::datum($b['ordered_at'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
