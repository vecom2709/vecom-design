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
<div class="block" style="padding:14px 18px">
  <p style="font-size:13.5px;line-height:1.7;margin:0;color:var(--dim)">
    <b style="color:var(--text)">So läuft es:</b>
    ① Partner teilen ihren Link <code>/p/CODE</code> →
    ② wer darüber kommt, gehört ab dem ersten Kontakt <?= (int) Partner::zahl('partner_zuordnung_monate') ?> Monate diesem Partner →
    ③ bezahlt der Kunde, entsteht die Provision (<?= Fmt::h(Partner::satzWort(Partner::satzFuer([]))) ?>) →
    ④ nach <?= max(14, Partner::zahl('partner_sperrtage')) ?> Tagen wird sie ausgezahlt — automatisch (Stripe/PayPal/Wise) oder von Hand (SEPA/Verrechnung).
    Erstattet der Kunde, entfällt sie.</p>
</div>

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

<?php $sepa = array_values(array_filter($handarbeit, static fn($h) => $h['weg'] === 'sepa'));
      $verr = array_values(array_filter($handarbeit, static fn($h) => $h['weg'] === 'gutschrift'));
      if ($sepa || $verr || $offeneAuszahlungen): ?>
<div class="block" style="border-color:rgba(255,159,90,.35)">
  <h2 style="font-size:15px;margin:0 0 8px">Auszahlungen von Hand</h2>
  <?php if ($sepa): ?>
    <p style="font-size:13.5px;margin:0 0 8px"><?= count($sepa) ?> SEPA-Überweisung<?= count($sepa) === 1 ? '' : 'en' ?> fällig:
      <?= Fmt::h(implode(', ', array_map(static fn($h) => $h['partner']['name'] . ' ' . Fmt::geld($h['summe']), $sepa))) ?></p>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-bottom:10px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_sepa">
      <button class="knopf haupt">SEPA-Datei herunterladen</button>
      <span style="color:var(--leise);font-size:12.5px;margin-left:8px">Im Online-Banking hochladen; wenn die Bank ausgeführt hat, unten „ausgeführt“ klicken.</span>
    </form>
  <?php endif; ?>
  <?php if ($verr): ?>
    <p style="font-size:13.5px;margin:0 0 8px">Verrechnung möglich:
      <?php foreach ($verr as $h): ?><a href="<?= Fmt::h(url('partner/' . (int) $h['partner']['id'])) ?>"><?= Fmt::h($h['partner']['name']) ?></a> <?= Fmt::h(Fmt::geld($h['summe'])) ?> <?php endforeach; ?></p>
  <?php endif; ?>
  <?php if ($offeneAuszahlungen): ?>
    <div class="tabellenrahmen"><table><thead><tr><th>Beleg</th><th>Partner</th><th>Weg</th><th style="text-align:right">Betrag</th><th></th></tr></thead><tbody>
    <?php foreach ($offeneAuszahlungen as $a): ?>
      <tr><td><?= Fmt::h($a['nummer']) ?></td><td><?= Fmt::h($a['name']) ?></td><td><?= Fmt::h(PartnerWege::WEGE[$a['weg']] ?? $a['weg']) ?></td>
          <td style="text-align:right"><?= Fmt::h(Fmt::geld((int) $a['betrag_cents'])) ?></td>
          <td style="white-space:nowrap">
            <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"><?= Csrf::feld() ?>
              <input type="hidden" name="tat" value="partner_auszahlung_bestaetigen"><input type="hidden" name="auszahlung" value="<?= (int) $a['id'] ?>">
              <button class="knopf">Ausgeführt</button></form>
            <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"><?= Csrf::feld() ?>
              <input type="hidden" name="tat" value="partner_auszahlung_abbrechen"><input type="hidden" name="auszahlung" value="<?= (int) $a['id'] ?>">
              <button class="knopf">Abbrechen</button></form></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
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
  <?php if (!empty($geloescht)): ?>
    <p style="color:var(--leise);font-size:12.5px;margin-top:10px"><?= count($geloescht) ?> gelöschte<?= count($geloescht) === 1 ? 'r' : '' ?> Partner, deren Belege aufbewahrt werden:
      <?php foreach ($geloescht as $g): ?><a href="<?= Fmt::h(url('partner/' . (int) $g['id'])) ?>"><?= Fmt::h($g['name']) ?></a> <?php endforeach; ?></p>
  <?php endif; ?>
  <?php if ($einbehaltMonat > 0): ?>
    <p style="color:var(--leise);font-size:12.5px;margin-top:10px">Steuereinbehalt im Vormonat: <b><?= Fmt::h(Fmt::geld($einbehaltMonat)) ?></b> — per F24 abführen.</p>
  <?php endif; ?>
</div>

<?php $ausw = array_values(array_filter($auswertung ?? [], static fn($z) => (int) $z['klicks'] + (int) $z['kunden'] > 0)); if ($ausw): ?>
<div class="block">
  <h2 style="font-size:15px;margin:0 0 6px">Lohnt es sich? <span style="font-weight:400;color:var(--leise);font-size:12.5px">letzte 12 Monate, beste zuerst</span></h2>
  <div class="tabellenrahmen"><table>
    <thead><tr><th>Partner</th><th style="text-align:right">Klicks</th><th style="text-align:right">Kunden</th><th style="text-align:right">Umsatz (netto)</th>
               <th style="text-align:right">Provision</th><th style="text-align:right">je Kunde</th><th>Kanäle</th></tr></thead><tbody>
    <?php foreach ($ausw as $z): ?>
      <tr><td><a href="<?= Fmt::h(url('partner/' . (int) $z['id'])) ?>"><?= Fmt::h($z['name']) ?></a></td>
          <td style="text-align:right"><?= (int) $z['klicks'] ?></td><td style="text-align:right"><?= (int) $z['kunden'] ?></td>
          <td style="text-align:right"><?= Fmt::h(Fmt::geld((int) $z['umsatz'])) ?></td><td style="text-align:right"><?= Fmt::h(Fmt::geld((int) $z['provision'])) ?></td>
          <td style="text-align:right"><?= (int) $z['kunden'] > 0 ? Fmt::h(Fmt::geld((int) $z['je_kunde'])) : '—' ?></td>
          <td style="font-size:12px;color:var(--dim)"><?= Fmt::h(implode(' · ', array_map(static fn($k) => $k['kanal'] . ' ' . $k['klicks'] . '/' . $k['kunden'], $z['kanaele']))) ?: '—' ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <p style="color:var(--leise);font-size:12px;margin-top:6px">Kanäle: Klicks/Kunden. Umsatz = bezahlte Beträge der Kunden dieses Partners (netto), ohne Erstattetes.</p>
</div>
<?php endif; ?>

<div class="block">
  <h2 style="font-size:15px;margin:0 0 6px">Partner einladen</h2>
  <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0 0 12px">
    Er bekommt sofort eine E-Mail mit Link, Code und seiner Partnerseite. Den Code (und damit den Link
    <code>/p/CODE</code>) kannst du selbst wählen oder vergeben lassen. Die Vereinbarung bestätigt er dort —
    vorher wird nichts ausgezahlt.</p>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_anlegen">
    <div class="feld" style="flex:1 1 180px"><label>Name</label><input name="name" required></div>
    <div class="feld" style="flex:1 1 200px"><label>E-Mail</label><input name="email" type="email" required></div>
    <div class="feld" style="flex:1 1 160px"><label>Firma</label><input name="firma"></div>
    <div class="feld" style="flex:0 1 140px"><label>P. IVA / CF</label><input name="steuer_nr"></div>
    <div class="feld" style="flex:0 1 170px"><label>Code (leer = vergeben lassen)</label>
      <input name="code" maxlength="16" pattern="[A-Za-z0-9 \-]{5,20}" placeholder="z. B. ROSSI2026" style="text-transform:uppercase"></div>
    <div class="feld" style="flex:0 0 110px"><label>Sprache</label>
      <select name="sprache"><option value="it">Italiano</option><option value="de">Deutsch</option><option value="en">English</option></select></div>
    <button class="knopf">Anlegen und einladen</button>
  </form>
</div>

<?php $an = array_map('trim', explode(',', Partner::einstellung('partner_wege'))); ?>
<div class="block" id="wege">
  <h2 style="font-size:15px;margin:0 0 6px">Auszahlungswege</h2>
  <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0 0 10px">Der Partner wählt auf seiner Seite unter den eingeschalteten.
    Ein Weg erscheint dort nur, wenn er auch technisch bereit ist.</p>
  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_wege">
    <?php foreach (PartnerWege::WEGE as $w => $wort):
      $tech = PartnerWege::technisch($w); ?>
      <label style="display:flex;align-items:flex-start;gap:6px;font-size:13.5px;margin:6px 0">
        <input type="checkbox" name="wege[]" value="<?= Fmt::h($w) ?>" <?= in_array($w, $an, true) ? 'checked' : '' ?> style="width:auto;margin:3px 0 0">
        <span><b><?= Fmt::h($wort) ?></b>
          <?= in_array($w, PartnerWege::AUTOMATISCH, true) ? '<span class="marke2" style="margin-left:4px">automatisch</span>' : '<span class="marke2" style="margin-left:4px">von Hand</span>' ?>
          <?= $tech ? '' : '<span class="marke2 warnung" style="margin-left:4px">noch nicht eingerichtet</span>' ?>
          <br><span style="color:var(--leise);font-size:12px"><?= Fmt::h([
            'stripe' => 'Stripe Connect: im Stripe-Dashboard einmal „Connect“ aktivieren.',
            'sepa' => 'Die Verwaltung baut eine SEPA-Datei für dein Online-Banking. Braucht deine IBAN unter Einstellungen → Firma.',
            'paypal' => 'Braucht ein PayPal-Geschäftskonto mit freigeschalteten „Payouts“; client_id und secret in config.local.php (paypal).',
            'wise' => 'Braucht ein Wise-Geschäftskonto; API-Token und Profil-ID in config.local.php (wise). Wise kann eine Bestätigung in der App verlangen.',
            'gutschrift' => 'Nur für Partner, die selbst Kunde sind: Provision wird mit einer offenen Rate verrechnet.',
          ][$w]) ?></span></span></label>
    <?php endforeach; ?>
    <button class="knopf">Wege speichern</button>
  </form>
  <?php $connect = Partner::einstellung('partner_stripe_connect'); ?>
  <div style="border-top:1px solid var(--linie);margin-top:14px;padding-top:12px">
    <p style="font-size:13.5px;margin:0 0 6px"><b>Stripe Connect</b>
      <?= $connect === 'ok' ? '<span class="marke2 gut">aktiv</span>' : ($connect === 'fehlt' ? '<span class="marke2 warnung">nicht bereit — Stripe wird Partnern nicht angeboten</span>' : '<span class="marke2">noch nicht geprüft</span>') ?></p>
    <?php /* Der Grund steht HIER, nicht nur als Meldung oben: Nach dem Klick
             springt die Seite zu #wege, die Meldung lag außerhalb des Bildes,
             und Uwe sah nur das Schild (26.09.2026). */
      $cGrund = Partner::einstellung('partner_stripe_connect_grund'); if ($cGrund !== ''): ?>
      <p class="<?= $connect === 'ok' ? '' : 'hinweis schlecht' ?>" style="font-size:13px;line-height:1.6;margin:6px 0 8px"><?= Fmt::h($cGrund) ?>
        <span style="color:var(--leise)"> · geprüft <?= Fmt::h(Partner::einstellung('partner_stripe_connect_am')) ?></span></p>
    <?php endif; ?>
    <details <?= $connect !== 'ok' ? 'open' : '' ?>><summary style="cursor:pointer;font-size:13px;color:var(--cyan)">So schaltest du Connect frei (einmalig, ca. 10 Minuten)</summary>
      <ol style="color:var(--dim);font-size:13px;line-height:1.8;padding-left:20px;margin:8px 0">
        <li>Im Stripe-Dashboard links auf <b>Connect</b> (bzw. „Verbundene Konten“) → <b>Loslegen</b>.</li>
        <li>Als Modell <b>Plattform / Marktplatz</b> wählen; Konten verwaltet <b>Stripe</b> (Express), Verluste trägt die Plattform.</li>
        <li><b>Plattform-Profil</b> ausfüllen: Was du tust („Provisionen an Empfehlungspartner für Webdesign-Aufträge“), Website vecom-design.it.</li>
        <li>Unter <b>Einstellungen → Connect → Onboarding-Optionen</b>: Italien (und andere EU-Länder, falls Partner dort wohnen) freigeben.</li>
        <li>Unter <b>Branding</b>: Name „Vecom Design“, Logo, Farbe Gold (#c8963e) — das sieht der Partner beim Einrichten.</li>
        <li>Hier unten auf <b>„Stripe Connect prüfen“</b> klicken. Steht dort „aktiv“, sehen Partner den Weg „Stripe“ wieder.</li>
      </ol></details>
    <form method="post" action="<?= Fmt::h(url('')) ?>"><?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_connect_pruefen">
      <button class="knopf">Stripe Connect prüfen</button></form>
  </div>
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
      <label style="font-size:13.5px;display:inline-flex;align-items:center"><input type="checkbox" name="partner_stufen_an" value="1" <?= $e('partner_stufen_an') === '1' ? 'checked' : '' ?> style="width:auto;margin:0 6px 0 0">
        <b>Stufen</b>&nbsp;— wer im letzten Jahr viel gebracht hat, bekommt automatisch mehr (nur bei Prozent und ohne eigene Bedingungen)</label>
      <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;margin-top:10px">
        <div class="feld" style="flex:0 0 150px"><label>Silber ab (Verkäufe)</label><input name="partner_silber_ab" type="number" min="1" value="<?= (int) $e('partner_silber_ab') ?>"></div>
        <div class="feld" style="flex:0 0 120px"><label>Silber (%)</label><input name="partner_silber_bp" value="<?= Fmt::h($pz((int) $e('partner_silber_bp'))) ?>"></div>
        <div class="feld" style="flex:0 0 150px"><label>Gold ab (Verkäufe)</label><input name="partner_gold_ab" type="number" min="2" value="<?= (int) $e('partner_gold_ab') ?>"></div>
        <div class="feld" style="flex:0 0 120px"><label>Gold (%)</label><input name="partner_gold_bp" value="<?= Fmt::h($pz((int) $e('partner_gold_bp'))) ?>"></div>
      </div>
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
