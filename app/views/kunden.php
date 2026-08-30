<div class="kopf"><h1>Kunden</h1><div class="rechts">
  <form class="leiste"><input type="search" name="q" value="<?= Fmt::h($q) ?>" placeholder="Name, E-Mail, Firma">
  <button class="knopf">Suchen</button></form>
  <a class="knopf haupt" href="<?= Fmt::h(url('kunden/neu')) ?>">Neuer Kunde</a></div></div>
<div class="block"><div class="tabellenrahmen"><table>
<thead><tr><th>Name</th><th>Firma</th><th>E-Mail</th><th class="num">Bestellungen</th><th class="num">Projekte</th><th>Seit</th></tr></thead>
<tbody>
<?php if (!$liste): ?><tr><td colspan="6"><div class="leer">Noch keine Kunden erfasst.</div></td></tr><?php endif; ?>
<?php foreach ($liste as $k): ?>
<tr><td><a href="<?= Fmt::h(url('kunden/' . $k['id'])) ?>"><strong><?= Fmt::h($k['name']) ?></strong></a></td>
<td><?= Fmt::h($k['company'] ?: '—') ?></td><td><?= Fmt::h($k['email']) ?></td>
<td class="num"><?= (int) $k['bestellungen'] ?></td><td class="num"><?= (int) $k['projekte'] ?></td>
<td><?= Fmt::h(Fmt::datum($k['created_at'])) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
