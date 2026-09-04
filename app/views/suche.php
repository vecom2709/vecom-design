<div class="kopf"><h1>Suche</h1><div class="rechts">
<form class="leiste"><input type="search" name="q" value="<?= Fmt::h($q) ?>" placeholder="Kunde, Bestellung, Projekt, Website, Rechnung" autofocus>
<button class="knopf">Suchen</button></form></div></div>
<?php if ($q === ''): ?><div class="block"><div class="leer">Suchbegriff eingeben.</div></div><?php else: ?>
<?php $wege = ['Kunden'=>'kunden','Angebote'=>'angebote','Bedarf'=>'bedarf','Bestellungen'=>'bestellungen',
               'Projekte'=>'projekte','Websites'=>'monitoring','Rechnungen'=>'rechnungen'];
$gesamt = array_sum(array_map('count', $treffer)); ?>
<?php if ($gesamt === 0): ?><div class="block"><div class="leer">Nichts gefunden zu „<?= Fmt::h($q) ?>“.</div></div><?php endif; ?>
<?php foreach ($treffer as $gruppe => $zeilen): if (!$zeilen) continue; ?>
<div class="block"><h2><?= Fmt::h($gruppe) ?></h2><table><tbody>
<?php foreach ($zeilen as $t): ?><tr>
<td><a href="<?= Fmt::h(url($wege[$gruppe] . '/' . $t['id'])) ?>"><?= Fmt::h($t['titel']) ?></a></td>
<td style="color:var(--dim)"><?= Fmt::h($t['neben']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endforeach; endif; ?>
