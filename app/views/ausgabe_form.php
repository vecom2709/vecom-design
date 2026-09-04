<div class="kopf"><h1><?= $a ? 'Ausgabe ändern' : 'Ausgabe erfassen' ?></h1></div>
<div class="block" style="max-width:760px">
<form method="post" action="<?= Fmt::h(url('')) ?>" enctype="multipart/form-data">
<?= Csrf::feld() ?><input type="hidden" name="tat" value="ausgabe_speichern">
<input type="hidden" name="id" value="<?= (int) ($a['id'] ?? 0) ?>">

<?php if ($a): ?>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 14px">Nummer
    <b><?= Fmt::h((string) $a['beleg_nr']) ?></b> — sie bleibt, was sie ist. Eine Nummer, die einmal
    vergeben war, wird nicht neu verteilt: die Reihe muss lückenlos sein.</p>
<?php else: ?>
  <p style="color:var(--leise);font-size:12.5px;margin:-4px 0 14px">Bekommt beim Speichern die
    Nummer <b><?= Fmt::h($naechste) ?></b>.</p>
<?php endif; ?>

<div class="reihe">
  <div class="feld"><label>Datum der Rechnung *</label>
    <input type="date" name="datum" required value="<?= Fmt::h((string) ($a['datum'] ?? date('Y-m-d'))) ?>"></div>
  <div class="feld"><label>Bezahlt am</label>
    <input type="date" name="bezahlt_am" value="<?= Fmt::h((string) ($a['bezahlt_am'] ?? '')) ?>">
    <small style="color:var(--leise);display:block;margin-top:5px">Wann das Geld weg war. Nach diesem
      Datum wird in Italien gerechnet, nicht nach dem Rechnungsdatum.</small></div>
</div>

<div class="reihe">
  <div class="feld"><label>Lieferant *</label>
    <input name="lieferant" required value="<?= Fmt::h((string) ($a['lieferant'] ?? '')) ?>" placeholder="All-Inkl, Stripe, Adobe …"></div>
  <div class="feld"><label>Land</label>
    <input name="land" maxlength="2" style="text-transform:uppercase"
           value="<?= Fmt::h((string) ($a['land'] ?? 'IT')) ?>" placeholder="IT">
    <small style="color:var(--leise);display:block;margin-top:5px">Zwei Buchstaben. Alles außer IT ist
      ein Kandidat für Reverse Charge.</small></div>
</div>

<div class="reihe">
  <div class="feld"><label>Kategorie</label>
    <select name="kategorie">
      <?php foreach (Ausgabe::KATEGORIEN as $wert => $wort): ?>
        <option value="<?= Fmt::h($wert) ?>" <?= ($a['kategorie'] ?? 'sonstiges') === $wert ? 'selected' : '' ?>><?= Fmt::h($wort) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="feld"><label>Wofür</label>
    <input name="titel" value="<?= Fmt::h((string) ($a['titel'] ?? '')) ?>" placeholder="Hosting Januar bis Dezember"></div>
</div>

<div class="reihe">
  <div class="feld"><label>Netto</label>
    <input name="netto" inputmode="decimal" value="<?= $a ? Fmt::h(number_format((int) $a['netto_cents'] / 100, 2, ',', '')) : '' ?>" placeholder="49,00"></div>
  <div class="feld"><label>Steuer</label>
    <input name="steuer" inputmode="decimal" value="<?= $a ? Fmt::h(number_format((int) $a['steuer_cents'] / 100, 2, ',', '')) : '' ?>" placeholder="10,78"></div>
  <div class="feld"><label>Brutto</label>
    <input name="brutto" inputmode="decimal" value="<?= $a ? Fmt::h(number_format((int) $a['brutto_cents'] / 100, 2, ',', '')) : '' ?>" placeholder="59,78"></div>
</div>
<p style="color:var(--leise);font-size:12.5px;margin:-6px 0 14px">Es genügt, was auf dem Beleg steht.
  Fehlt eins der drei Felder, wird es ausgerechnet.</p>

<div class="feld">
  <label style="display:flex;align-items:center;gap:9px;cursor:pointer">
    <input type="checkbox" name="reverse_charge" value="1" style="width:auto;margin:0"
           <?= (int) ($a['reverse_charge'] ?? 0) === 1 ? 'checked' : '' ?>>
    <span>Reverse Charge — Leistung aus dem Ausland</span></label>
  <small style="color:var(--leise);display:block;margin-top:5px">Setz das bei Stripe, Google, Meta,
    ausländischem Hosting und ausländischer Software. Darauf fällt italienische IVA an, die du zahlst
    und nicht zurückbekommst. Diese Zeilen landen in einer eigenen Liste fürs Finanzamt — sonst
    kommt die Frage erst im März.</small>
</div>

<div class="reihe">
  <div class="feld"><label>USt-IdNr. des Lieferanten</label>
    <input name="ust_id" value="<?= Fmt::h((string) ($a['ust_id'] ?? '')) ?>" placeholder="IE3206488LH"></div>
  <div class="feld"><label>Bezahlt womit</label>
    <input name="zahlweg" value="<?= Fmt::h((string) ($a['zahlweg'] ?? '')) ?>" placeholder="Bank, Karte, PayPal"></div>
</div>

<div class="feld"><label>Beleg als Datei</label>
  <input type="file" name="beleg" accept="application/pdf,image/jpeg,image/png,image/webp">
  <small style="color:var(--leise);display:block;margin-top:5px">PDF, JPG, PNG oder WebP, bis 15 MB.
    <?php if ($a && ($a['stored_name'] ?? '') !== ''): ?>
      Hinterlegt ist <a href="<?= Fmt::h(url('ausgaben/' . (int) $a['id'] . '/datei')) ?>"><?= Fmt::h((string) ($a['orig_name'] ?: 'Beleg')) ?></a>
      — eine neue Datei ersetzt sie.
    <?php else: ?>
      Ohne Datei zählt die Zeile, aber im Jahrespaket steht sie als fehlend.
    <?php endif; ?></small></div>

<div class="feld"><label>Notiz</label><textarea name="notiz" rows="3"><?= Fmt::h((string) ($a['notiz'] ?? '')) ?></textarea></div>

<button class="knopf haupt">Speichern</button>
<a class="knopf stumm" href="<?= Fmt::h(url('ausgaben')) ?>">Abbrechen</a>
</form>
</div>

<?php if ($a): ?>
<div class="block" style="max-width:760px">
  <details style="border:1px solid rgba(255,138,138,.3);border-radius:10px;padding:10px 12px">
    <summary style="cursor:pointer;font-weight:650;font-size:13.5px;color:var(--rot)">Diesen Eintrag löschen</summary>
    <p style="color:var(--dim);font-size:13px;margin:10px 0">Nur für Tippfehler. Ein echter Beleg
      bleibt stehen, auch wenn er ärgert — sonst hat die Nummernreihe eine Lücke.</p>
    <form method="post" action="<?= Fmt::h(url('')) ?>" data-frage="Wirklich löschen? Die Nummer bleibt danach frei." data-ja="Ja, löschen">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="ausgabe_loeschen">
      <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
      <button class="knopf">Löschen</button></form>
  </details>
</div>
<?php endif; ?>
