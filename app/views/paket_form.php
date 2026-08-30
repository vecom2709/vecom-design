<?php $feat = $p && $p['features'] ? implode("\n", (array) json_decode((string) $p['features'], true)) : ''; ?>
<div class="kopf"><h1><?= $p ? 'Paket bearbeiten' : 'Neues Paket' ?></h1></div>
<div class="block" style="max-width:720px"><form method="post" action="<?= Fmt::h(url('')) ?>">
<?= Csrf::feld() ?><input type="hidden" name="tat" value="paket_speichern">
<input type="hidden" name="zurueck" value="pakete"><input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
<div class="reihe"><div class="feld"><label>Name *</label><input name="name" required value="<?= Fmt::h($p['name'] ?? '') ?>"></div>
<div class="feld"><label>Kürzel (URL)</label><input name="slug" value="<?= Fmt::h($p['slug'] ?? '') ?>"></div></div>
<div class="feld"><label>Beschreibung</label><textarea name="description" rows="2"><?= Fmt::h($p['description'] ?? '') ?></textarea></div>
<div class="reihe"><div class="feld"><label>Preis einmalig (€)</label><input name="preis" value="<?= $p ? number_format($p['price_cents']/100, 2, ',', '') : '' ?>"></div>
<div class="feld"><label>Monatlich (€)</label><input name="monat" value="<?= $p ? number_format($p['monthly_cents']/100, 2, ',', '') : '0' ?>"></div></div>
<div class="reihe"><div class="feld"><label>Anzahl Seiten</label><input name="pages_count" value="<?= Fmt::h($p['pages_count'] ?? '') ?>"></div>
<div class="feld"><label>Bearbeitungszeit</label><input name="delivery_days" value="<?= Fmt::h($p['delivery_days'] ?? '') ?>"></div></div>
<div class="reihe"><div class="feld"><label>SEO</label><input name="seo" value="<?= Fmt::h($p['seo'] ?? '') ?>"></div>
<div class="feld"><label>Hosting</label><input name="hosting" value="<?= Fmt::h($p['hosting'] ?? '') ?>"></div></div>
<div class="feld"><label>Leistungen (eine je Zeile)</label><textarea name="features" rows="6"><?= Fmt::h($feat) ?></textarea></div>
<div class="feld"><label>Zusätzliche Funktionen</label><textarea name="extras" rows="2"><?= Fmt::h($p['extras'] ?? '') ?></textarea></div>
<div class="reihe"><div class="feld"><label>Reihenfolge</label><input name="sort" type="number" value="<?= (int) ($p['sort'] ?? 0) ?>"></div>
<div class="feld"><label>&nbsp;</label>
  <label style="color:var(--text)"><input type="checkbox" name="active" style="width:auto" <?= (!$p || $p['active']) ? 'checked' : '' ?>> aktiv</label>
  <label style="color:var(--text)"><input type="checkbox" name="popular" style="width:auto" <?= ($p && $p['popular']) ? 'checked' : '' ?>> als beliebt zeigen</label>
</div></div>
<button class="knopf haupt">Speichern</button> <a class="knopf stumm" href="<?= Fmt::h(url('pakete')) ?>">Abbrechen</a>
</form></div>
