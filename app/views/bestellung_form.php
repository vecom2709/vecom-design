<div class="kopf"><h1>Bestellung erfassen</h1></div>
<div class="block" style="max-width:620px">
<?php if (!$kunden || !$pakete): ?>
  <div class="hinweis schlecht">Dafür braucht es mindestens einen Kunden und ein aktives Paket.</div>
<?php endif; ?>
<form method="post" action="<?= Fmt::h(url('')) ?>">
<?= Csrf::feld() ?><input type="hidden" name="tat" value="bestellung_anlegen">
<input type="hidden" name="zurueck" value="bestellungen/neu">
<div class="feld"><label>Kunde *</label><select name="customer_id" required>
<?php foreach ($kunden as $k): ?><option value="<?= (int) $k['id'] ?>"><?= Fmt::h($k['name'] . ($k['company'] ? ' — ' . $k['company'] : '')) ?></option><?php endforeach; ?>
</select></div>
<div class="feld"><label>Paket *</label><select name="package_id" required>
<?php foreach ($pakete as $p): ?><option value="<?= (int) $p['id'] ?>"><?= Fmt::h($p['name']) ?> — <?= Fmt::geld((int) $p['price_cents']) ?></option><?php endforeach; ?>
</select></div>
<div class="feld"><label>Notiz</label><textarea name="notes" rows="3"></textarea></div>
<button class="knopf haupt" <?= (!$kunden || !$pakete) ? 'disabled' : '' ?>>Bestellung anlegen</button>
<a class="knopf stumm" href="<?= Fmt::h(url('bestellungen')) ?>">Abbrechen</a>
<p style="color:var(--leise);font-size:12.5px;margin-top:12px">Legt Bestellung und offene Zahlung an, schreibt Aktivität und Benachrichtigung.
Das Projekt entsteht, sobald die Zahlung bestätigt ist.</p>
</form></div>
