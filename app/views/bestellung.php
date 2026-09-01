<div class="kopf"><div><div class="weg"><a href="<?= Fmt::h(url('bestellungen')) ?>">Bestellungen</a></div>
<h1><?= Fmt::h($b['order_no']) ?></h1></div></div>
<div class="zwei"><div>
  <?php
  $bezahlt = 0; $offen = 0;
  foreach ($zahlungen as $z) {
      if ($z['status'] === 'bezahlt') { $bezahlt += (int) $z['amount_cents']; }
      elseif (in_array($z['status'], ['ausstehend','in_bearbeitung','fehlgeschlagen'], true)) { $offen += (int) $z['amount_cents']; }
  }
  ?>
  <div class="block"><h2>Zahlungen
    <span class="mehr"><?= Fmt::geld($bezahlt) ?> bezahlt · <?= Fmt::geld($offen) ?> offen</span></h2>

  <?php foreach ($zahlungen as $z): ?>
    <div style="border-top:1px solid var(--linie);padding:13px 0">
      <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
        <div style="min-width:180px">
          <strong><?= Fmt::h($z['bezeichnung'] ?: ucfirst((string) $z['art'])) ?></strong><br>
          <small style="color:var(--leise)"><?= Fmt::h($z['provider'] === 'offen' ? 'noch kein Anbieter' : $z['provider']) ?>
          <?= $z['provider_ref'] ? ' · ' . Fmt::h($z['provider_ref']) : '' ?></small>
        </div>
        <div style="font-variant-numeric:tabular-nums;font-size:17px;font-weight:600"><?= Fmt::geld((int) $z['amount_cents'], $z['currency']) ?></div>
        <span class="marke2 <?= Status::ton($z['status']) ?>"><?= Fmt::h(Status::label(Status::ZAHLUNG, $z['status'])) ?></span>
        <?php if ($z['paid_at']): ?><small style="color:var(--leise)"><?= Fmt::h(Fmt::zeit($z['paid_at'])) ?></small><?php endif; ?>

        <div style="margin-left:auto;display:flex;gap:6px;flex-wrap:wrap;align-items:center">
        <?php if ($z['status'] === 'bezahlt'): ?>
          <?php $beleg = Db::one('SELECT id, invoice_no FROM invoices WHERE payment_id = ?', [(int) $z['id']]); ?>
          <?php if ($beleg): ?>
            <a class="knopf" href="<?= Fmt::h(url('rechnungen/' . (int) $beleg['id'])) ?>"><?= Fmt::h((string) $beleg['invoice_no']) ?></a>
          <?php else: ?>
            <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
              <?= Csrf::feld() ?><input type="hidden" name="tat" value="rechnung_erzeugen">
              <input type="hidden" name="zurueck" value="bestellungen/<?= (int) $b['id'] ?>">
              <input type="hidden" name="id" value="<?= (int) $z['id'] ?>">
              <button class="knopf">Beleg erstellen</button></form>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($z['status'] !== 'bezahlt'): ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="zahlungslink">
            <input type="hidden" name="zurueck" value="bestellungen/<?= (int) $b['id'] ?>">
            <input type="hidden" name="id" value="<?= (int) $z['id'] ?>">
            <button class="knopf haupt"><?= $z['link_url'] ? 'Neuen Link erzeugen' : 'Zahlungslink erzeugen' ?></button></form>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="zahlung_bestaetigen">
            <input type="hidden" name="zurueck" value="bestellungen/<?= (int) $b['id'] ?>">
            <input type="hidden" name="id" value="<?= (int) $z['id'] ?>"><input type="hidden" name="order_id" value="<?= (int) $b['id'] ?>">
            <button class="knopf">Von Hand buchen</button></form>
          <?php if ($z['art'] === 'restzahlung' && $projekt): ?>
            <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
              <?= Csrf::feld() ?><input type="hidden" name="tat" value="restzahlung_anfordern">
              <input type="hidden" name="zurueck" value="bestellungen/<?= (int) $b['id'] ?>">
              <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
              <button class="knopf" title="Schickt dem Kunden die Aufforderung samt Zahlungslink">Restzahlung anfordern</button></form>
          <?php endif; ?>
        <?php endif; ?>
        </div>
      </div>

      <?php if ($z['link_url'] && $z['status'] !== 'bezahlt'): ?>
        <div style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <input readonly value="<?= Fmt::h($z['link_url']) ?>" onclick="this.select()"
                 style="flex:1;min-width:240px;font-size:12.5px;font-family:ui-monospace,monospace">
          <a class="knopf" href="<?= Fmt::h($z['link_url']) ?>" target="_blank" rel="noopener">Öffnen</a>
          <?php $schonRaus = Mail::schonGeschickt('zahlungslink', 'payment_id', (int) $z['id']); ?>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"
                <?= $schonRaus ? 'onsubmit="return confirm(\'Der Link ging schon einmal raus. Noch einmal senden?\')"' : '' ?>>
            <?= Csrf::feld() ?><input type="hidden" name="tat" value="zahlungslink_senden">
            <input type="hidden" name="order_id" value="<?= (int) $b['id'] ?>">
            <input type="hidden" name="id" value="<?= (int) $z['id'] ?>">
            <button class="knopf <?= $schonRaus ? '' : 'haupt' ?>"
                    title="Schickt den Link direkt an <?= Fmt::h($b['kunde_email']) ?>">
              <?= $schonRaus ? 'Nochmal senden' : 'Link an den Kunden senden' ?></button>
          </form>
          <a class="knopf" href="mailto:<?= Fmt::h($b['kunde_email']) ?>?subject=<?= rawurlencode('Zahlung ' . $b['order_no'] . ' — ' . ($z['bezeichnung'] ?: '')) ?>&body=<?= rawurlencode("Hallo " . $b['kunde'] . ",\n\nhier ist der Link für die " . ($z['bezeichnung'] ?: 'Zahlung') . " über " . Fmt::geld((int) $z['amount_cents'], $z['currency']) . ":\n\n" . $z['link_url'] . "\n\nHerzliche Grüße\nUwe Vetter · Vecom Design") ?>" title="Öffnet dein Mailprogramm">im Mailprogramm</a>
          <small style="color:var(--leise)">gültig bis <?= Fmt::h(Fmt::zeit($z['link_bis'])) ?></small>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <p style="color:var(--leise);font-size:12.5px;margin-top:12px">Zahlt der Kunde über den Link, meldet Stripe das an den Server —
  und dieselbe Ereignislogik läuft, die auch „Von Hand buchen“ auslöst: Zahlung, Bestellung, Projekt, Aktivität, Dashboard.
  Kartendaten erreichen diesen Server dabei nie.</p></div>

  <div class="block"><h2>Verlauf</h2>
    <?php if (!$aktivitaeten): ?><div class="leer">Noch nichts.</div><?php else: ?><ul class="verlauf">
    <?php foreach ($aktivitaeten as $a): ?><li><span class="punkt"></span><span><?= Fmt::h($a['title']) ?></span>
      <span class="wann"><?= Fmt::h(Fmt::seit($a['created_at'])) ?></span></li><?php endforeach; ?></ul><?php endif; ?></div>
</div><div>
  <div class="block"><h2>Übersicht</h2><table><tbody>
    <tr><td>Kunde</td><td><a href="<?= Fmt::h(url('kunden/' . $b['customer_id'])) ?>"><?= Fmt::h($b['kunde']) ?></a></td></tr>
    <tr><td>Paket</td><td><?= Fmt::h($b['package_name']) ?></td></tr>
    <tr><td>Preis</td><td><?= Fmt::geld((int) $b['price_cents'], $b['currency']) ?><?= $b['monthly_cents'] ? ' + ' . Fmt::geld((int) $b['monthly_cents']) . '/Mon.' : '' ?></td></tr>
    <tr><td>Bestellt</td><td><?= Fmt::h(Fmt::zeit($b['ordered_at'])) ?></td></tr>
    <tr><td>Projekt</td><td><?= $projekt ? '<a href="' . Fmt::h(url('projekte/' . $projekt['id'])) . '">' . Fmt::h($projekt['name']) . '</a>' : 'entsteht bei Zahlungseingang' ?></td></tr>
  </tbody></table></div>
  <div class="block"><h2>Status ändern</h2>
    <form method="post" action="<?= Fmt::h(url('')) ?>" class="leiste">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="bestellung_status">
      <input type="hidden" name="zurueck" value="bestellungen/<?= (int) $b['id'] ?>">
      <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
      <select name="status"><?php foreach (Status::BESTELLUNG as $w => $t): ?>
        <option value="<?= $w ?>" <?= $b['status'] === $w ? 'selected' : '' ?>><?= Fmt::h($t) ?></option><?php endforeach; ?></select>
      <button class="knopf">Setzen</button></form></div>
</div></div>
