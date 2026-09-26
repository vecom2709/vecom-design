<?php
/* Partnerprogramm: wer empfiehlt, was es gebracht hat, was offen ist — und
   die Bedingungen, die für alle gelten, solange ein Partner keine eigenen hat.

   Oben die Bewerbungen: Sie sind das Einzige hier, das auf Uwe wartet. */
$e = static fn(string $k): string => Partner::einstellung($k);
$pz = static fn(int $bp): string => rtrim(rtrim(number_format($bp / 100, 2, ',', ''), '0'), ',');
$eu = static fn(int $c): string => rtrim(rtrim(number_format($c / 100, 2, ',', ''), '0'), ',');
$bewerbungen = array_values(array_filter($liste, static fn($p) => $p['status'] === 'bewerbung'));
$uebrige = array_values(array_filter($liste, static fn($p) => $p['status'] !== 'bewerbung'));
$marke = ['aktiv' => 'gut', 'pausiert' => 'warnung', 'abgelehnt' => '', 'bewerbung' => 'warnung'];
$website = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
?>
<div class="kopf"><h1>Partner</h1></div>

<?php if ($bewerbungen): ?>
<div class="block">
  <h2 style="font-size:15px;margin:0 0 10px">Bewerbungen<span class="mehr"><?= count($bewerbungen) ?></span></h2>
  <div class="tabellenrahmen"><table>
    <thead><tr><th>Wer</th><th>Wo er empfehlen will</th><th>Seit</th><th></th></tr></thead><tbody>
    <?php foreach ($bewerbungen as $p): ?>
      <tr><td><strong><?= Fmt::h($p['name']) ?></strong><div style="color:var(--leise);font-size:12px"><?= Fmt::h($p['email']) ?><?= $p['firma'] !== '' ? ' · ' . Fmt::h($p['firma']) : '' ?></div></td>
          <td style="font-size:13px"><?= Fmt::h($p['kanal']) ?></td>
          <td style="font-size:12.5px;color:var(--leise)"><?= Fmt::h(Fmt::datum((string) $p['created_at'])) ?></td>
          <td><a class="knopf" href="<?= Fmt::h(url('partner/' . (int) $p['id'])) ?>">Ansehen</a></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
</div>
<?php endif; ?>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 10px">Alle Partner</h2>
  <?php if (!$uebrige): ?>
    <p style="color:var(--leise);font-size:13px">Noch keine. Bewerbungen kommen über
      <a href="<?= Fmt::h($website) ?>/partner.php" target="_blank" rel="noopener"><?= Fmt::h($website) ?>/partner.php</a>; einladen kannst du unten.</p>
  <?php else: ?>
  <div class="tabellenrahmen"><table>
    <thead><tr><th>Partner</th><th>Code</th><th style="text-align:right">Klicks</th><th style="text-align:right">Kunden</th>
               <th style="text-align:right">Offen</th><th style="text-align:right">Ausgezahlt</th><th>Stripe</th></tr></thead><tbody>
    <?php foreach ($uebrige as $p): ?>
      <tr><td><a href="<?= Fmt::h(url('partner/' . (int) $p['id'])) ?>"><strong><?= Fmt::h($p['name']) ?></strong></a>
            <span class="marke2 <?= $marke[$p['status']] ?? '' ?>" style="margin-left:6px"><?= Fmt::h($p['status']) ?></span></td>
          <td><code><?= Fmt::h($p['code']) ?></code></td>
          <td style="text-align:right"><?= (int) $p['klicks'] ?></td>
          <td style="text-align:right"><?= (int) $p['kunden'] ?></td>
          <td style="text-align:right"><?= Fmt::h(Fmt::geld((int) $p['offen'])) ?></td>
          <td style="text-align:right"><?= Fmt::h(Fmt::geld((int) $p['ausgezahlt'])) ?></td>
          <td><?= !empty($p['stripe_bereit']) ? '<span class="marke2 gut">bereit</span>' : (empty($p['stripe_konto']) ? '—' : '<span class="marke2">angefangen</span>') ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
  <?php if ($einbehaltMonat > 0): ?>
    <p style="color:var(--leise);font-size:12.5px;margin-top:10px">Steuereinbehalt im Vormonat: <b><?= Fmt::h(Fmt::geld($einbehaltMonat)) ?></b> — per F24 abführen.</p>
  <?php endif; ?>
</div>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 6px">Partner einladen</h2>
  <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0 0 12px">
    Er bekommt sofort eine E-Mail mit Link, Code und seiner Partnerseite. Die Vereinbarung bestätigt er dort —
    vorher wird nichts ausgezahlt.</p>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_anlegen">
    <div class="feld" style="flex:1 1 180px"><label>Name</label><input name="name" required></div>
    <div class="feld" style="flex:1 1 200px"><label>E-Mail</label><input name="email" type="email" required></div>
    <div class="feld" style="flex:1 1 160px"><label>Firma</label><input name="firma"></div>
    <div class="feld" style="flex:0 1 140px"><label>P. IVA / CF</label><input name="steuer_nr"></div>
    <div class="feld" style="flex:0 0 110px"><label>Sprache</label>
      <select name="sprache"><option value="it">Italiano</option><option value="de">Deutsch</option><option value="en">English</option></select></div>
    <button class="knopf">Anlegen und einladen</button>
  </form>
</div>

<div class="block" id="bedingungen">
  <h2 style="font-size:15px;margin:0 0 6px">Bedingungen für alle</h2>
  <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0 0 12px">
    Gilt für jeden Partner, der keine eigenen Bedingungen hat — und nur für künftige Provisionen. Basis ist immer der
    <b>tatsächlich bezahlte Nettobetrag</b>. Eine Provision wartet die Widerrufsfrist ab (mindestens 14 Tage); wird vorher
    erstattet, entfällt sie. Wird danach erstattet, holt Stripe sie zurück, solange sie beim Partner noch liegt — sonst
    bekommst du eine Aufgabe „Rückforderung“.</p>
  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_einstellungen">
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div class="feld" style="flex:0 0 150px"><label>Provision</label>
        <select name="partner_standard_art">
          <option value="prozent" <?= $e('partner_standard_art') === 'prozent' ? 'selected' : '' ?>>Prozent</option>
          <option value="fest" <?= $e('partner_standard_art') === 'fest' ? 'selected' : '' ?>>Fester Betrag (€)</option></select></div>
      <div class="feld" style="flex:0 0 110px"><label>Wert</label>
        <input name="partner_standard_wert" value="<?= Fmt::h($e('partner_standard_art') === 'fest' ? $eu((int) $e('partner_standard_wert')) : $pz((int) $e('partner_standard_wert'))) ?>"></div>
      <div class="feld" style="flex:0 0 150px"><label>Auszahlen ab (€)</label><input name="partner_mindest_cents" value="<?= Fmt::h($eu((int) $e('partner_mindest_cents'))) ?>"></div>
      <div class="feld" style="flex:0 0 150px"><label>Wartezeit (Tage, ≥ 14)</label><input name="partner_sperrtage" type="number" min="14" max="90" value="<?= (int) $e('partner_sperrtage') ?>"></div>
      <div class="feld" style="flex:0 0 190px"><label>Zuordnung gilt (Monate)</label><input name="partner_zuordnung_monate" type="number" min="1" max="60" value="<?= (int) $e('partner_zuordnung_monate') ?>"></div>
      <div class="feld" style="flex:0 0 220px"><label>Monatsverträge: Provision für (Monate)</label><input name="partner_wiederkehrend_monate" type="number" min="0" max="60" value="<?= (int) $e('partner_wiederkehrend_monate') ?>"></div>
      <div class="feld" style="flex:0 0 190px"><label>Steuereinbehalt (%)</label><input name="partner_einbehalt_bp" value="<?= Fmt::h($pz((int) $e('partner_einbehalt_bp'))) ?>"></div>
    </div>
    <div style="display:flex;gap:18px;flex-wrap:wrap;margin:12px 0;font-size:13.5px">
      <label style="display:inline-flex;align-items:center"><input type="checkbox" style="width:auto;margin:0 6px 0 0;vertical-align:middle" name="partner_gilt_website" value="1" <?= $e('partner_gilt_website') === '1' ? 'checked' : '' ?>> Websites</label>
      <label style="display:inline-flex;align-items:center"><input type="checkbox" style="width:auto;margin:0 6px 0 0;vertical-align:middle" name="partner_gilt_betreuung" value="1" <?= $e('partner_gilt_betreuung') === '1' ? 'checked' : '' ?>> Betreuung</label>
      <label style="display:inline-flex;align-items:center"><input type="checkbox" style="width:auto;margin:0 6px 0 0;vertical-align:middle" name="partner_gilt_hosting" value="1" <?= $e('partner_gilt_hosting') === '1' ? 'checked' : '' ?>> Hosting</label>
      <label style="display:inline-flex;align-items:center"><input type="checkbox" style="width:auto;margin:0 6px 0 0;vertical-align:middle" name="partner_freigabe_noetig" value="1" <?= $e('partner_freigabe_noetig') === '1' ? 'checked' : '' ?>> Jede Provision erst von mir freigeben</label>
      <label style="display:inline-flex;align-items:center"><input type="checkbox" style="width:auto;margin:0 6px 0 0;vertical-align:middle" name="partner_bewerbung_offen" value="1" <?= $e('partner_bewerbung_offen') === '1' ? 'checked' : '' ?>> Bewerbungsformular offen</label>
    </div>
    <div style="border:1px solid var(--linie);border-radius:10px;padding:12px 14px;margin-bottom:12px">
      <label style="font-size:13.5px;display:inline-flex;align-items:center"><input type="checkbox" style="width:auto;margin:0 6px 0 0;vertical-align:middle" name="partner_auto_auszahlen" value="1" <?= $e('partner_auto_auszahlen') === '1' ? 'checked' : '' ?>>
        <b>Automatisch über Stripe auszahlen</b></label>
      <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:6px 0 8px">
        Nach der Wartezeit geht die Provision ohne Klick raus — nur an Partner mit bestätigter Vereinbarung und von Stripe
        geprüftem Konto, nur wenn die Kundenzahlung in dem Moment noch bezahlt ist, nur ab dem Mindestbetrag.
        Über dem Tageslimit wartet sie auf deinen Klick.</p>
      <div class="feld" style="max-width:220px"><label>Tageslimit automatisch (€)</label>
        <input name="partner_auto_tageslimit_cents" value="<?= Fmt::h($eu((int) $e('partner_auto_tageslimit_cents'))) ?>"></div>
    </div>
    <button class="knopf haupt">Bedingungen speichern</button>
  </form>
  <p style="color:var(--leise);font-size:12px;line-height:1.6;margin-top:12px">
    Voraussetzung für Stripe: In deinem Stripe-Konto unter <b>Connect</b> einmal das Plattform-Profil ausfüllen und
    „Überweisungen (Recipient)“ für Italien/EU freischalten. Ob ein Steuereinbehalt (Ritenuta) nötig ist, sagt dein
    Commercialista — Standard ist 0 %. Die Vereinbarung ist ein Entwurf: bitte einmal rechtlich lesen lassen.</p>
</div>
