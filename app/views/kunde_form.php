<div class="kopf"><h1><?= $k ? 'Kunde bearbeiten' : 'Neuer Kunde' ?></h1></div>
<div class="block" style="max-width:720px"><form method="post" action="<?= Fmt::h(url('')) ?>">
<?= Csrf::feld() ?><input type="hidden" name="tat" value="kunde_speichern">
<input type="hidden" name="zurueck" value="kunden"><input type="hidden" name="id" value="<?= (int) ($k['id'] ?? 0) ?>">
<div class="reihe"><div class="feld"><label for="f_name">Name *</label><input id="f_name" name="name" required value="<?= Fmt::h($k['name'] ?? '') ?>"></div>
<div class="feld"><label for="f_email">E-Mail *</label><input id="f_email" type="email" name="email" required value="<?= Fmt::h($k['email'] ?? '') ?>"></div></div>
<div class="reihe"><div class="feld"><label for="f_phone">Telefon</label><input id="f_phone" type="tel" autocomplete="tel" name="phone" value="<?= Fmt::h($k['phone'] ?? '') ?>"></div>
<div class="feld"><label for="f_company">Firma</label><input id="f_company" name="company" value="<?= Fmt::h($k['company'] ?? '') ?>"></div></div>
<div class="reihe"><div class="feld"><label for="f_industry">Branche</label><input id="f_industry" name="industry" value="<?= Fmt::h($k['industry'] ?? '') ?>"></div>
<div class="feld"><label for="f_street">Straße</label><input id="f_street" name="street" value="<?= Fmt::h($k['street'] ?? '') ?>"></div></div>
<div class="reihe"><div class="feld"><label for="f_zip">PLZ</label><input id="f_zip" name="zip" value="<?= Fmt::h($k['zip'] ?? '') ?>"></div>
<div class="feld"><label for="f_city">Ort</label><input id="f_city" name="city" value="<?= Fmt::h($k['city'] ?? '') ?>"></div></div>
<div class="reihe"><div class="feld"><label for="f_country">Land</label><input id="f_country" name="country" value="<?= Fmt::h($k['country'] ?? 'Italien') ?>"></div>
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
<?php /* Die Beschriftung folgt der Sprache des Kunden: Ein deutscher Kunde
         hat eine Steuernummer, keine Partita IVA, und der italienische
         Empfaengerkode existiert bei ihm gar nicht. Umgestellt wird sie beim
         Speichern -- wer die Sprache oben aendert, sieht die neuen Worte
         nach dem Sichern. */
  $sw = Kunde::steuerworte($k['sprache'] ?? 'it'); ?>
<div class="reihe"><div class="feld"><label for="f_tax_code"><?= Fmt::h($sw['tax_code']) ?></label><input id="f_tax_code" name="tax_code" value="<?= Fmt::h($k['tax_code'] ?? '') ?>"></div>
<div class="feld"><label for="f_vat_id"><?= Fmt::h($sw['vat_id']) ?></label><input id="f_vat_id" name="vat_id" value="<?= Fmt::h($k['vat_id'] ?? '') ?>"></div></div>
<?php /* Das Feld steht immer im Formular, auch wenn es gerade nicht gilt --
         sonst koennte das Skript unten es beim Umschalten auf Italienisch
         nicht wieder hervorholen. Versteckt bleibt der Wert erhalten. */ ?>
<div class="feld"<?= $sw['sdi'] === null ? ' hidden' : '' ?>>
  <label for="f_sdi"><?= Fmt::h($sw['sdi'] ?? 'Empfängerkode oder PEC (SDI)') ?></label>
  <input id="f_sdi" name="sdi" value="<?= Fmt::h($k['sdi'] ?? '') ?>" placeholder="M5UXCR1"></div>
<p style="color:var(--leise);font-size:var(--fs-klein);line-height:1.6;margin:-4px 0 14px" id="steuerhinweis"><?= Fmt::h($sw['hinweis']) ?></p>

<?php /* Die Worte wechseln mit der Auswahl, nicht erst nach dem Speichern.
         Wer oben Deutsch einstellt und darunter weiter "Partita IVA" liest,
         traegt im Zweifel etwas Falsches ein. */ ?>
<script>
(function () {
  var worte = <?= json_encode([
      'it' => Kunde::steuerworte('it'),
      'de' => Kunde::steuerworte('de'),
      'en' => Kunde::steuerworte('en'),
  ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var wahl = document.querySelector('select[name=sprache]');
  if (!wahl) { return; }
  function beschriften() {
    var w = worte[wahl.value] || worte.it;
    ['tax_code', 'vat_id', 'sdi'].forEach(function (feld) {
      var eingabe = document.querySelector('[name=' + feld + ']');
      if (!eingabe) { return; }
      var kasten = eingabe.closest('.feld');
      if (!kasten) { return; }               // sdi liegt versteckt, wenn es das Feld nicht gibt
      var schild = kasten.querySelector('label');
      if (w[feld] === null) { kasten.hidden = true; return; }
      kasten.hidden = false;
      if (schild) { schild.textContent = w[feld]; }
    });
    var hinweis = document.getElementById('steuerhinweis');
    if (hinweis) { hinweis.textContent = w.hinweis; }
  }
  wahl.addEventListener('change', beschriften);
  beschriften();
})();
</script>
<div class="feld"><label for="f_notes">Interne Notizen</label><textarea id="f_notes" name="notes" rows="4"><?= Fmt::h($k['notes'] ?? '') ?></textarea></div>
<button class="knopf haupt">Speichern</button> <a class="knopf stumm" href="<?= Fmt::h(url('kunden')) ?>">Abbrechen</a>
</form></div>
