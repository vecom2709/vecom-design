<div class="kopf"><h1>Bestellung erfassen</h1></div>

<?php /* Wer ein Angebot geschrieben hat, soll es nicht hier von Hand
         nachbauen. Auf der Angebotsseite steht ein Knopf, der aus der Zusage
         die Bestellung macht -- mit Betrag, Posten und Anzahlung, so wie sie
         der Kunde gelesen hat. Dieser Hinweis ist die Abkuerzung dorthin. */ ?>
<?php if (!empty($angebote)): ?>
  <div class="block" style="max-width:680px;border-left:3px solid var(--cyan)">
    <h2 style="font-size:15px;margin:0 0 6px">Dafür gibt es schon ein Angebot</h2>
    <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0 0 10px">
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
<div class="feld"><label>Kunde *</label><select name="customer_id" required>
<?php foreach ($kunden as $k): ?><option value="<?= (int) $k['id'] ?>"><?= Fmt::h($k['name'] . ($k['company'] ? ' — ' . $k['company'] : '')) ?></option><?php endforeach; ?>
</select></div>
<div class="feld"><label>Paket *</label><select name="package_id" required>
<?php foreach ($pakete as $p): ?><option value="<?= (int) $p['id'] ?>"><?= Fmt::h($p['name']) ?> — <?= Fmt::geld((int) $p['price_cents']) ?></option><?php endforeach; ?>
</select></div>

<?php /* Der Preis ist verhandelt, bevor jemand hier klickt. Steht hier nichts,
        gilt der Paketpreis — so wie es vorher war und in den meisten Fällen
        auch bleiben wird. */ ?>
<details style="border:1px solid var(--linie);border-radius:10px;padding:11px 13px;margin:6px 0 16px">
  <summary style="cursor:pointer;font-weight:650;font-size:13.5px">Preis abweichend vereinbart</summary>
  <p style="color:var(--leise);font-size:12.5px;margin:10px 0 12px">Leer lassen heißt: es gilt der
    Paketpreis. Was du hier einträgst, steht danach auf Bestellung, Zahlungen und Beleg — also so
    eintragen, wie ihr es besprochen habt.</p>
  <div class="reihe">
    <div class="feld"><label>Vereinbarter Gesamtpreis</label>
      <input name="preis" inputmode="decimal" placeholder="z. B. 750,00"></div>
    <div class="feld"><label>Anzahlung in Prozent</label>
      <input name="prozent" inputmode="numeric" placeholder="50" maxlength="3">
      <small style="color:var(--leise);display:block;margin-top:5px">Üblich sind 50 %. Bei 100 %
        entsteht keine Restzahlung.</small></div>
  </div>
  <div class="feld"><label>Abweichende Bezeichnung</label>
    <input name="bezeichnung" maxlength="190" placeholder="Website Ristorante Da Nino">
    <small style="color:var(--leise);display:block;margin-top:5px">Nur nötig, wenn der Paketname
      nicht passt. Sonst leer lassen.</small></div>
</details>

<div class="feld"><label>Notiz</label><textarea name="notes" rows="3" placeholder="Was ihr besprochen habt — steht nur intern."></textarea></div>
<button class="knopf haupt" <?= (!$kunden || !$pakete) ? 'disabled' : '' ?>>Bestellung anlegen</button>
<a class="knopf stumm" href="<?= Fmt::h(url('bestellungen')) ?>">Abbrechen</a>
<p style="color:var(--leise);font-size:12.5px;margin-top:12px">Legt Bestellung und offene Zahlung an, schreibt Aktivität und Benachrichtigung.
Das Projekt entsteht, sobald die Zahlung bestätigt ist.</p>
</form></div>
