<div class="kopf"><h1>Pakete</h1><div class="rechts">
<a class="knopf haupt" href="<?= Fmt::h(url('pakete/neu')) ?>">Neues Paket</a></div></div>
<div class="block"><div class="tabellenrahmen"><table>
<thead><tr><th>Paket</th><th class="num">Einmalig</th><th class="num">Monatlich</th><th class="num">Bestellungen</th><th>Zustand</th><th></th></tr></thead><tbody>
<?php if (!$liste): ?><tr><td colspan="6"><div class="leer">Noch keine Pakete angelegt.</div></td></tr><?php endif; ?>
<?php foreach ($liste as $p): ?><tr>
<td><a href="<?= Fmt::h(url('pakete/' . $p['id'])) ?>"><strong><?= Fmt::h($p['name']) ?></strong></a>
    <?php if ($p['popular']): ?> <span class="marke2 warnung">beliebt</span><?php endif; ?></td>
<td class="num"><?= Fmt::geld((int) $p['price_cents'], $p['currency']) ?></td>
<td class="num"><?= $p['monthly_cents'] ? Fmt::geld((int) $p['monthly_cents'], $p['currency']) . '/Mon.' : '—' ?></td>
<td class="num"><?= (int) $p['bestellungen'] ?></td>
<td><span class="marke2 <?= $p['active'] ? 'gut' : '' ?>"><?= $p['active'] ? 'aktiv' : 'inaktiv' ?></span></td>
<td style="text-align:right">
<?php if ((int) $p['bestellungen'] === 0): ?>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"
        onsubmit="return confirm('Paket wirklich löschen?')">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="paket_loeschen">
    <input type="hidden" name="zurueck" value="pakete"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
    <button class="knopf stumm">Löschen</button></form>
<?php else: ?><span style="color:var(--leise);font-size:12px">in Verwendung</span><?php endif; ?>
</td></tr><?php endforeach; ?>
</tbody></table></div></div>
