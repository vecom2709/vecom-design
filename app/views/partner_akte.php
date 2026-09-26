<?php
/* Ein Partner: Stand, Bedingungen, Kunden, Provisionen, Auszahlungen.
   Die eine blaue Tat hängt am Zustand: Bewerbung → annehmen; sonst
   auszahlen, wenn etwas bereit liegt; sonst keine. */
$pz = static fn(?int $bp): string => $bp === null ? '' : rtrim(rtrim(number_format($bp / 100, 2, ',', ''), '0'), ',');
$wahl = static fn($v): string => $v === null ? '' : ((int) $v === 1 ? '1' : '0');
$stufe = ['wartet' => ['', 'wartet (Widerrufsfrist)'], 'freigabe' => ['warnung', 'zur Freigabe'], 'bereit' => ['gut', 'bereit'],
          'unterwegs' => ['warnung', 'unterwegs'], 'ausgezahlt' => ['', 'ausgezahlt'], 'storniert' => ['', 'entfallen'], 'zurueckgeholt' => ['', 'zurückgeholt'],
          'rueckforderung' => ['schlecht', 'zurückfordern']];
$bereit = (int) Db::wert('SELECT COALESCE(SUM(provision_cents - einbehalt_cents),0) FROM partner_provisionen WHERE partner_id = ? AND status = \'bereit\'', [(int) $p['id']], 0);
$min = Partner::zahl('partner_mindest_cents');
$hin = static fn(string $tat, string $wort, bool $haupt = false, array $extra = []) =>
    '<form method="post" action="' . Fmt::h(url('')) . '" style="display:inline">' . Csrf::feld()
    . '<input type="hidden" name="tat" value="' . Fmt::h($tat) . '"><input type="hidden" name="id" value="' . (int) $p['id'] . '">'
    . implode('', array_map(static fn($k, $v) => '<input type="hidden" name="' . Fmt::h($k) . '" value="' . Fmt::h((string) $v) . '">', array_keys($extra), $extra))
    . '<button class="knopf' . ($haupt ? ' haupt' : '') . '">' . Fmt::h($wort) . '</button></form>';
?>
<div class="kopf"><h1><?= Fmt::h($p['name']) ?>
  <span class="marke2 <?= ['aktiv' => 'gut', 'pausiert' => 'warnung', 'bewerbung' => 'warnung'][$p['status']] ?? '' ?>" style="margin-left:8px;font-size:12px"><?= Fmt::h($p['status']) ?></span></h1>
  <a href="<?= Fmt::h(url('partner')) ?>" style="font-size:13px">← Alle Partner</a></div>

<div class="block">
  <div style="display:flex;gap:24px;flex-wrap:wrap;font-size:13.5px;line-height:1.7">
    <div><span style="color:var(--leise)">E-Mail</span><br><?= Fmt::h($p['email']) ?></div>
    <?php if ($p['firma'] !== ''): ?><div><span style="color:var(--leise)">Firma</span><br><?= Fmt::h($p['firma']) ?></div><?php endif; ?>
    <?php if ($p['steuer_nr'] !== ''): ?><div><span style="color:var(--leise)">P. IVA / CF</span><br><?= Fmt::h($p['steuer_nr']) ?></div><?php endif; ?>
    <div><span style="color:var(--leise)">Link</span><br><code><?= Fmt::h(Partner::link($p)) ?></code></div>
    <div><span style="color:var(--leise)">Vereinbarung</span><br><?= $p['vereinbarung_am'] ? 'bestätigt ' . Fmt::h(Fmt::datum((string) $p['vereinbarung_am'])) : '<span class="marke2 warnung">noch nicht</span>' ?></div>
    <div><span style="color:var(--leise)">Auszahlung über</span><br><?= $weg !== null ? Fmt::h(PartnerWege::WEGE[$weg]) : '—' ?>
      <?= $weg !== null ? (PartnerWege::bereit($p, $weg) ? '<span class="marke2 gut">bereit</span>' : '<span class="marke2 warnung">Angaben fehlen</span>') : '' ?>
      <?php if (in_array($weg, ['sepa', 'wise'], true) && (string) $p['iban_ende'] !== ''): ?><div style="font-size:12px;color:var(--leise)"><?= Fmt::h((string) $p['kontoinhaber']) ?> · IBAN …<?= Fmt::h((string) $p['iban_ende']) ?></div><?php endif; ?>
      <?php if ($weg === 'paypal'): ?><div style="font-size:12px;color:var(--leise)"><?= Fmt::h((string) $p['paypal_email']) ?></div><?php endif; ?></div>
  </div>
  <?php if ($p['kanal'] !== '' || (string) $p['bewerbung_text'] !== ''): ?>
    <p style="color:var(--dim);font-size:13px;line-height:1.6;margin:12px 0 0"><?= Fmt::h($p['kanal']) ?><?= (string) $p['bewerbung_text'] !== '' ? '<br>' . nl2br(Fmt::h((string) $p['bewerbung_text'])) : '' ?></p>
  <?php endif; ?>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px">
    <?php if ($p['status'] === 'bewerbung'): ?>
      <?= $hin('partner_annehmen', 'Annehmen', true) ?> <?= $hin('partner_ablehnen', 'Ablehnen') ?>
    <?php elseif ($p['status'] === 'aktiv'): ?>
      <?= $hin('partner_pausieren', 'Pausieren') ?>
    <?php elseif ($p['status'] === 'pausiert'): ?>
      <?= $hin('partner_aktivieren', 'Wieder aktivieren') ?>
    <?php endif; ?>
    <?php if (!in_array($p['status'], ['bewerbung', 'abgelehnt', 'geloescht'], true)): ?>
      <a class="knopf" href="<?= Fmt::h(Partner::portalLink($p)) ?>" target="_blank" rel="noopener">Seine Partnerseite ansehen</a>
      <?= $hin('partner_token_neu', 'Zugang neu vergeben') ?>
      <?php if (!empty($p['stripe_konto'])): ?><?= $hin('partner_konto_pruefen', 'Stripe-Konto prüfen') ?><?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<div class="block">
  <div style="display:flex;gap:18px;flex-wrap:wrap;font-size:13.5px">
    <div><b style="font-size:20px"><?= (int) $zahlen['klicks'] ?></b><br><span style="color:var(--leise)">Klicks</span></div>
    <div><b style="font-size:20px"><?= (int) $zahlen['kunden'] ?></b><br><span style="color:var(--leise)">Kunden</span></div>
    <div><b style="font-size:20px"><?= (int) $zahlen['verkaeufe'] ?></b><br><span style="color:var(--leise)">Verkäufe</span></div>
    <?php foreach (['wartet' => 'wartet', 'freigabe' => 'zur Freigabe', 'bereit' => 'bereit', 'ausgezahlt' => 'ausgezahlt', 'rueckforderung' => 'zurückfordern'] as $k => $w):
      if ($summen[$k] > 0 || in_array($k, ['bereit', 'ausgezahlt'], true)): ?>
      <div><b style="font-size:20px"><?= Fmt::h(Fmt::geld($summen[$k])) ?></b><br><span style="color:var(--leise)"><?= $w ?></span></div>
    <?php endif; endforeach; ?>
  </div>
  <?php if ($bereit > 0): ?>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-top:14px;border-top:1px solid var(--linie);padding-top:14px">
      <?php if ($weg !== null && in_array($weg, PartnerWege::AUTOMATISCH, true) && PartnerWege::bereit($p, $weg) && $p['vereinbarung_am']): ?>
        <?= $hin('partner_auszahlen', 'Jetzt ' . Fmt::geld($bereit) . ' über ' . PartnerWege::WEGE[$weg] . ' auszahlen', true) ?>
      <?php elseif ($weg === 'gutschrift' && $offeneRaten && $p['vereinbarung_am']): ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:8px;align-items:flex-end">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_verrechnen"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <div class="feld" style="margin:0"><label>Mit offener Rate verrechnen</label>
            <select name="zahlung"><?php foreach ($offeneRaten as $r): ?><option value="<?= (int) $r['id'] ?>"><?= Fmt::h($r['bezeichnung'] . ' — ' . Fmt::geld((int) $r['amount_cents'])) ?></option><?php endforeach; ?></select></div>
          <button class="knopf haupt">Verrechnen</button></form>
      <?php elseif ($weg === 'sepa'): ?>
        <span style="font-size:13px">SEPA: kommt in die nächste <a href="<?= Fmt::h(url('partner')) ?>">SEPA-Datei</a>.</span>
      <?php endif; ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:8px;align-items:flex-end">
        <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_auszahlen_hand"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <div class="feld" style="margin:0"><label>Von Hand überwiesen (Referenz)</label><input name="referenz" placeholder="z. B. Bonifico 12.10."></div>
        <button class="knopf">Als ausgezahlt buchen</button></form>
      <?php if ($bereit < $min): ?><span style="color:var(--leise);font-size:12.5px">Unter dem Mindestbetrag (<?= Fmt::h(Fmt::geld($min)) ?>) — automatisch geht es erst ab dann.</span><?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 10px">Provisionen</h2>
  <?php if (!$provisionen): ?><p style="color:var(--leise);font-size:13px">Noch keine.</p><?php else: ?>
  <div class="tabellenrahmen"><table>
    <thead><tr><th>Datum</th><th>Kunde</th><th>Art</th><th style="text-align:right">Basis</th><th>Satz</th><th style="text-align:right">Provision</th><th>Stand</th><th></th></tr></thead><tbody>
    <?php foreach ($provisionen as $z): [$m, $w] = $stufe[$z['status']] ?? ['', $z['status']]; ?>
      <tr><td style="font-size:12.5px"><?= Fmt::h(Fmt::datum((string) $z['created_at'])) ?></td>
          <td><a href="<?= Fmt::h(url('kunden/' . (int) $z['customer_id'])) ?>"><?= Fmt::h(Fmt::name($z['kunde'] ?? '')) ?></a></td>
          <td><?= Fmt::h($z['art']) ?></td>
          <td style="text-align:right"><?= Fmt::h(Fmt::geld((int) $z['basis_cents'])) ?></td>
          <td style="font-size:12.5px"><?= Fmt::h($z['satz']) ?></td>
          <td style="text-align:right"><?= Fmt::h(Fmt::geld((int) $z['provision_cents'])) ?><?= (int) $z['einbehalt_cents'] > 0 ? '<div style="font-size:11.5px;color:var(--leise)">− ' . Fmt::h(Fmt::geld((int) $z['einbehalt_cents'])) . ' Einbehalt</div>' : '' ?></td>
          <td><span class="marke2 <?= $m ?>"><?= Fmt::h($w) ?></span>
            <?php if ($z['status'] === 'wartet'): ?><div style="font-size:11.5px;color:var(--leise)">frei ab <?= Fmt::h(Fmt::datum((string) $z['frei_ab'])) ?></div><?php endif; ?>
            <?php if ($z['grund'] !== ''): ?><div style="font-size:11.5px;color:var(--leise)"><?= Fmt::h($z['grund']) ?></div><?php endif; ?></td>
          <td style="white-space:nowrap">
            <?php if ($z['status'] === 'freigabe'): ?><?= $hin('partner_provision_frei', 'Freigeben', false, ['provision' => (int) $z['id']]) ?><?php endif; ?>
            <?php if (in_array($z['status'], ['wartet', 'freigabe', 'bereit'], true)): ?>
              <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline">
                <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_provision_streichen">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="provision" value="<?= (int) $z['id'] ?>">
                <input name="grund" placeholder="Grund" style="width:110px;font-size:12px" required>
                <button class="knopf" style="font-size:12px">Streichen</button></form>
            <?php endif; ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>

<?php if ($auszahlungen): ?>
<div class="block">
  <h2 style="font-size:15px;margin:0 0 10px">Auszahlungen</h2>
  <div class="tabellenrahmen"><table><tbody>
  <?php foreach ($auszahlungen as $a): ?>
    <tr><td><?= Fmt::h($a['nummer']) ?></td><td style="font-size:12.5px"><?= Fmt::h(Fmt::datum((string) $a['created_at'])) ?></td>
        <td><?= Fmt::h(PartnerWege::WEGE[$a['weg']] ?? 'von Hand') ?><?= $a['automatisch'] ? ' (automatisch)' : '' ?><?= $a['weg'] === 'hand' ? ' · ' . Fmt::h($a['referenz']) : '' ?>
          <?= $a['status'] === 'offen' ? '<span class="marke2 warnung">offen</span>' : ($a['status'] === 'abgebrochen' ? '<span class="marke2">abgebrochen</span>' : '') ?></td>
        <td style="text-align:right"><?= Fmt::h(Fmt::geld((int) $a['betrag_cents'])) ?></td>
        <td><a href="<?= Fmt::h(url('partner/' . (int) $p['id']) . '?beleg=' . (int) $a['id']) ?>" target="_blank">Beleg</a></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 6px">Kunden über diesen Partner</h2>
  <?php if ($kunden): ?>
    <ul style="font-size:13.5px;line-height:1.8;margin:0 0 12px;padding-left:18px">
    <?php foreach ($kunden as $k): ?>
      <li><a href="<?= Fmt::h(url('kunden/' . (int) $k['customer_id'])) ?>"><?= Fmt::h(Fmt::name($k['name'], $k['company'])) ?></a>
        <span style="color:var(--leise);font-size:12px"><?= Fmt::h(['link' => 'über den Link', 'code' => 'Code eingetippt', 'hand' => 'von Hand'][$k['quelle']] ?? $k['quelle']) ?>, <?= Fmt::h(Fmt::datum((string) $k['created_at'])) ?></span></li>
    <?php endforeach; ?></ul>
  <?php endif; ?>
  <?php if ($p['status'] === 'aktiv' && $alleKunden): ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_zuordnen"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <div class="feld" style="margin:0;min-width:240px"><label>Kunde von Hand zuordnen (z. B. hat am Telefon den Partner genannt)</label>
        <select name="kunde"><?php foreach ($alleKunden as $k): ?><option value="<?= (int) $k['id'] ?>"><?= Fmt::h(Fmt::name($k['name'], $k['company']) . ($k['company'] && trim((string) $k['name']) !== '' ? ' — ' . $k['company'] : '')) ?></option><?php endforeach; ?></select></div>
      <button class="knopf">Zuordnen</button></form>
    <p style="color:var(--leise);font-size:12px;margin-top:6px">Nur Kunden ohne Partner stehen in der Liste. Eine Zuordnung wird nie umgehängt; Käufe vor der Zuordnung zählen nicht.</p>
  <?php endif; ?>
</div>

<?php if (!in_array($p['status'], ['bewerbung', 'abgelehnt', 'geloescht'], true)): ?>
<div class="block">
  <h2 style="font-size:15px;margin:0 0 6px">Eigene Bedingungen</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:0 0 12px">Leer = es gilt der Standard (derzeit <?= Fmt::h(Partner::satzWort(Partner::satzFuer([]))) ?>). Gilt für künftige Provisionen.</p>
  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_bedingungen"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div class="feld" style="flex:0 0 170px"><label>Provision</label><select name="provision_art">
        <option value="">Standard</option>
        <option value="prozent" <?= $p['provision_art'] === 'prozent' ? 'selected' : '' ?>>Prozent</option>
        <option value="fest" <?= $p['provision_art'] === 'fest' ? 'selected' : '' ?>>Fester Betrag (€)</option></select></div>
      <div class="feld" style="flex:0 0 100px"><label>Wert</label><input name="provision_wert" value="<?= Fmt::h($p['provision_art'] !== null ? $pz((int) $p['provision_wert']) : '') ?>"></div>
      <?php foreach (['gilt_website' => 'Websites', 'gilt_betreuung' => 'Betreuung', 'gilt_hosting' => 'Hosting', 'freigabe_noetig' => 'Erst freigeben'] as $k => $w): ?>
        <div class="feld" style="flex:0 0 120px"><label><?= $w ?></label><select name="<?= $k ?>">
          <option value="" <?= $wahl($p[$k]) === '' ? 'selected' : '' ?>>Standard</option>
          <option value="1" <?= $wahl($p[$k]) === '1' ? 'selected' : '' ?>>ja</option>
          <option value="0" <?= $wahl($p[$k]) === '0' ? 'selected' : '' ?>>nein</option></select></div>
      <?php endforeach; ?>
      <div class="feld" style="flex:0 0 150px"><label>Monatsverträge (Monate)</label><input name="wiederkehrend_monate" value="<?= Fmt::h($p['wiederkehrend_monate'] !== null ? (string) $p['wiederkehrend_monate'] : '') ?>"></div>
    </div>
    <label style="font-size:13.5px;display:flex;align-items:center;margin:10px 0"><input type="checkbox" style="width:auto;margin:0 6px 0 0;vertical-align:middle" name="monatsmail" value="1" <?= !empty($p['monatsmail']) ? 'checked' : '' ?>> Monatsbericht per E-Mail</label>
    <div class="feld"><label>Notiz (nur für dich)</label><textarea name="notiz" rows="2"><?= Fmt::h((string) $p['notiz']) ?></textarea></div>
    <button class="knopf">Speichern</button>
  </form>
</div>
<?php endif; ?>

<?php if ($p['status'] !== 'geloescht'): ?>
<div class="block" style="border-color:rgba(255,138,138,.28)">
  <h2 style="font-size:15px;margin:0 0 6px">Partner löschen</h2>
  <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0 0 10px">
    Geht nur, wenn kein Geld mehr offen ist. Ohne bisherige Auszahlung verschwindet er ganz; sonst bleiben Name,
    Steuernummer und Belege (Aufbewahrungspflicht) — E-Mail, IBAN, PayPal, Notizen und Zugang werden gelöscht.
    Sein Stripe-Konto gehört ihm und bleibt bei Stripe.</p>
  <?= $hin('partner_loeschen', 'Partner löschen') ?>
</div>
<?php endif; ?>
