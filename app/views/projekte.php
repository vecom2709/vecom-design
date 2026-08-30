<div class="kopf"><h1>Projekte</h1><div class="rechts">
<form class="leiste"><select name="status"><option value="">alle Status</option>
<?php foreach (Status::PROJEKT as $w => $t): ?><option value="<?= $w ?>" <?= $st === $w ? 'selected' : '' ?>><?= Fmt::h($t) ?></option><?php endforeach; ?>
</select><button class="knopf">Filtern</button></form></div></div>
<div class="block"><div class="tabellenrahmen"><table>
<thead><tr><th>Projekt</th><th>Kunde</th><th>Projektstatus</th><th>Website</th><th>Fortschritt</th><th>Deadline</th></tr></thead><tbody>
<?php if (!$liste): ?><tr><td colspan="6"><div class="leer">Noch keine Projekte. Sie entstehen automatisch, sobald eine Zahlung bestätigt wird.</div></td></tr><?php endif; ?>
<?php foreach ($liste as $p): ?><tr>
<td><a href="<?= Fmt::h(url('projekte/' . $p['id'])) ?>"><strong><?= Fmt::h($p['name']) ?></strong></a></td>
<td><?= Fmt::h($p['kunde']) ?></td>
<td><span class="marke2 <?= Status::ton($p['status']) ?>"><?= Fmt::h(Status::label(Status::PROJEKT, $p['status'])) ?></span></td>
<td><span class="marke2 <?= Status::ton((string) $p['website_status']) ?>"><?= Fmt::h($p['website_status'] ? Status::label(Status::WEBSITE, $p['website_status']) : 'keine') ?></span></td>
<td><div class="balken"><i style="width:<?= (int) $p['progress'] ?>%"></i></div></td>
<td><?= Fmt::h(Fmt::datum($p['deadline'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
