<div class="kopf"><h1><?= $k ? 'Kunde bearbeiten' : 'Neuer Kunde' ?></h1></div>
<div class="block" style="max-width:720px"><form method="post" action="<?= Fmt::h(url('')) ?>">
<?= Csrf::feld() ?><input type="hidden" name="tat" value="kunde_speichern">
<input type="hidden" name="zurueck" value="kunden"><input type="hidden" name="id" value="<?= (int) ($k['id'] ?? 0) ?>">
<div class="reihe"><div class="feld"><label>Name *</label><input name="name" required value="<?= Fmt::h($k['name'] ?? '') ?>"></div>
<div class="feld"><label>E-Mail *</label><input type="email" name="email" required value="<?= Fmt::h($k['email'] ?? '') ?>"></div></div>
<div class="reihe"><div class="feld"><label>Telefon</label><input name="phone" value="<?= Fmt::h($k['phone'] ?? '') ?>"></div>
<div class="feld"><label>Firma</label><input name="company" value="<?= Fmt::h($k['company'] ?? '') ?>"></div></div>
<div class="reihe"><div class="feld"><label>Branche</label><input name="industry" value="<?= Fmt::h($k['industry'] ?? '') ?>"></div>
<div class="feld"><label>Straße</label><input name="street" value="<?= Fmt::h($k['street'] ?? '') ?>"></div></div>
<div class="reihe"><div class="feld"><label>PLZ</label><input name="zip" value="<?= Fmt::h($k['zip'] ?? '') ?>"></div>
<div class="feld"><label>Ort</label><input name="city" value="<?= Fmt::h($k['city'] ?? '') ?>"></div></div>
<div class="reihe"><div class="feld"><label>Land</label><input name="country" value="<?= Fmt::h($k['country'] ?? 'Italien') ?>"></div>
<div class="feld"><label>Sprache</label>
  <?php $sp = strtolower((string) ($k['sprache'] ?? 'it')); ?>
  <select name="sprache">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $wert => $wort): ?>
      <option value="<?= $wert ?>" <?= $sp === $wert ? 'selected' : '' ?>><?= $wort ?></option>
    <?php endforeach; ?>
  </select>
  <small style="color:var(--leise);display:block;margin-top:5px">In dieser Sprache gehen alle
  automatischen E-Mails an ihn — Zahlung, Vorschau, Restzahlung, „ist online".
  Sie wird beim Anfragen gesetzt und lässt sich hier ändern.</small></div></div>
<div class="feld"><label>Interne Notizen</label><textarea name="notes" rows="4"><?= Fmt::h($k['notes'] ?? '') ?></textarea></div>
<button class="knopf haupt">Speichern</button> <a class="knopf stumm" href="<?= Fmt::h(url('kunden')) ?>">Abbrechen</a>
</form></div>
