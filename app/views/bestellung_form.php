<div class="kopf"><h1>Bestellung erfassen</h1></div>

<?php /* Wer ein Angebot geschrieben hat, soll es nicht hier von Hand
         nachbauen. Auf der Angebotsseite steht ein Knopf, der aus der Zusage
         die Bestellung macht -- mit Betrag, Posten und Anzahlung, so wie sie
         der Kunde gelesen hat. Dieser Hinweis ist die Abkuerzung dorthin. */ ?>
<?php if (!empty($angebote)): ?>
  <div class="block" style="max-width:680px;border-left:3px solid var(--cyan)">
    <h2 style="font-size:15px;margin:0 0 6px">Dafür gibt es schon ein Angebot</h2>
    <p style="color:var(--leise);font-size:var(--fs-klein);line-height:1.6;margin:0 0 10px">
      Hat der Kunde zugesagt, buche es dort — dann stimmen Betrag, Posten und Anzahlung
      mit dem überein, was er gelesen hat. Hier von Hand zu erfassen heißt, denselben
      Preis ein zweites Mal einzutippen.
    </p>
    <ul style="margin:0;padding-left:1.1rem;font-size:13.5px;line-height:1.9">
      <?php foreach ($angebote as $ang): ?>
        <li>
          <a href="<?= Fmt::h(url('angebote/' . (int) $ang['id'])) ?>"><?= Fmt::h((string) $ang['nummer']) ?></a>
          · <?= Fmt::h((string) $ang['kunde']) ?>
          · <?= Fmt::h(Fmt::geld((int) $ang['summe_cents'])) ?>
          <span style="color:var(--leise)">(<?= Fmt::h((string) $ang['status']) ?>)</span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="block" style="max-width:680px">
<?php if (!$kunden || !$pakete): ?>
  <div class="hinweis schlecht">Dafür braucht es mindestens einen Kunden und ein aktives Paket.</div>
<?php endif; ?>
<form method="post" action="<?= Fmt::h(url('')) ?>">
<?= Csrf::feld() ?><input type="hidden" name="tat" value="bestellung_anlegen">
<input type="hidden" name="zurueck" value="bestellungen/neu">
<?php /* Jede Zeile lesbar, auch ohne Namen (01.10.2026: Interessenten aus dem
         E-Mail-Einstieg haben oft nur eine Adresse — die Auswahl war leer). */
      $bfWahl = (int) ($_GET['kunde'] ?? 0); ?>
<div class="feld"><label for="f_customer_id">Kunde *</label><select id="f_customer_id" name="customer_id" required>
<option value="" disabled <?= $bfWahl === 0 ? 'selected' : '' ?>>— Kunde wählen —</option>
<?php foreach ($kunden as $k): $bfName = trim((string) $k['name']); $bfMail = trim((string) ($k['email'] ?? '')); ?>
  <option value="<?= (int) $k['id'] ?>" <?= $bfWahl === (int) $k['id'] ? 'selected' : '' ?>><?= Fmt::h(
    Fmt::name($bfName, $k['company'], $bfMail, $k['kundennr'] ?? null)
    . ($bfName !== '' && $k['company'] ? ' — ' . $k['company'] : '')
    . ($bfMail !== '' && ($bfName !== '' || $k['company']) ? ' · ' . $bfMail : '')
    . (trim((string) ($k['kundennr'] ?? '')) !== '' ? ' (' . $k['kundennr'] . ')' : '')) ?></option><?php endforeach; ?>
</select></div>
<div class="feld"><label for="f_package_id">Paket *</label><select id="f_package_id" name="package_id" required>
<?php foreach ($pakete as $p): ?><option value="<?= (int) $p['id'] ?>"><?= Fmt::h($p['name']) ?> — <?= Fmt::geld((int) $p['price_cents']) ?></option><?php endforeach; ?>
</select></div>

<?php /* Der Preis ist verhandelt, bevor jemand hier klickt. Steht hier nichts,
        gilt der Paketpreis — so wie es vorher war und in den meisten Fällen
        auch bleiben wird. */ ?>
<details style="border:1px solid var(--linie);border-radius:10px;padding:11px 13px;margin:6px 0 16px">
  <summary style="cursor:pointer;font-weight:650;font-size:13.5px">Preis abweichend vereinbart</summary>
  <p style="color:var(--leise);font-size:var(--fs-klein);margin:10px 0 12px">Leer lassen heißt: es gilt der
    Paketpreis. Was du hier einträgst, steht danach auf Bestellung, Zahlungen und Beleg — also so
    eintragen, wie ihr es besprochen habt.</p>
  <div class="reihe">
    <div class="feld"><label for="f_preis">Vereinbarter Gesamtpreis</label>
      <input id="f_preis" name="preis" inputmode="decimal" placeholder="z. B. 750,00"></div>
    <div class="feld"><label for="f_prozent">Anzahlung in Prozent</label>
      <input id="f_prozent" name="prozent" inputmode="numeric" placeholder="50" maxlength="3">
      <small style="color:var(--leise);display:block;margin-top:5px">Üblich sind 50 %. Bei 100 %
        entsteht keine Restzahlung.</small></div>
  </div>
  <div class="feld"><label for="f_bezeichnung">Abweichende Bezeichnung</label>
    <input id="f_bezeichnung" name="bezeichnung" maxlength="190" placeholder="Website Ristorante Da Nino">
    <small style="color:var(--leise);display:block;margin-top:5px">Nur nötig, wenn der Paketname
      nicht passt. Sonst leer lassen.</small></div>
</details>

<div class="feld"><label for="f_notes">Notiz</label><textarea id="f_notes" name="notes" rows="3" placeholder="Was ihr besprochen habt — steht nur intern."></textarea></div>
<button class="knopf haupt" <?= (!$kunden || !$pakete) ? 'disabled' : '' ?>>Bestellung anlegen</button>
<a class="knopf stumm" href="<?= Fmt::h(url('bestellungen')) ?>">Abbrechen</a>
<p style="color:var(--leise);font-size:var(--fs-klein);margin-top:12px">Legt Bestellung und offene Zahlung an, schreibt Aktivität und Benachrichtigung.
Das Projekt entsteht, sobald die Zahlung bestätigt ist.</p>
</form></div>
