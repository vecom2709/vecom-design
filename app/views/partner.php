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

<?php /* Testpartner (Phase 9b, Uwe: „Ja, ein Testpartner“): alles ausprobieren, was ein Partner erlebt — nichts davon zählt. */
  if (Rechte::geld()): require_once dirname(__DIR__) . '/src/PartnerTest.php'; $tp = PartnerTest::laden(); ?>
<div class="block" id="testpartner" style="padding:14px 18px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
  <div style="flex:1;min-width:260px;font-size:13.5px;line-height:1.6">
    <b>Testpartner</b> — zum Ausprobieren des Partnerbereichs. Er zählt nirgends: keine Provision, keine Mail, kein Hinweis aufs Handy,
    keine Auszahlung, keine Statistik; Kontakte, die er „übergibt“, werden keine echten Anfragen.
    <?php if ($tp): ?><span style="color:var(--leise)">Den Gerätecode zum Anmelden findest du unter „Was nicht läuft“ (er geht nie per Mail raus).</span><?php endif; ?>
  </div>
  <?php if (!$tp): ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"><?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_test_anlegen">
      <button class="knopf">Testpartner anlegen</button></form>
  <?php else: ?>
    <a class="knopf" href="<?= Fmt::h(Partner::portalLink($tp)) ?>" target="_blank" rel="noopener" id="testpartner-oeffnen">Als Testpartner öffnen</a>
    <a class="knopf" href="<?= Fmt::h(url('partner/' . (int) $tp['id'])) ?>">Akte</a>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0" data-frage="Alles, was beim Ausprobieren entstand (Kontakte, Anliegen, Mails, Academy-Fortschritt, Geräte), wird gelöscht. Link und Code des Testpartners bleiben." data-ja="Ja, zurücksetzen">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_test_zuruecksetzen"><button class="knopf">Zurücksetzen</button></form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php /* Zentrale Aktion (28.09.2026, Uwe: Ja): einmal hier, dann auf allen
         Partnerseiten, im Partnerbereich und im Posting-Kalender, mit Countdown. */
  require_once dirname(__DIR__) . '/src/PartnerMarketing.php';
  $akRoh = json_decode(Partner::einstellung('partner_aktion'), true) ?: [];
  $akLaeuft = PartnerMarketing::aktion(); ?>
<details class="block" id="aktion"<?= $akLaeuft ? ' open' : '' ?>>
  <summary style="cursor:pointer;font-size:15px;font-weight:600">Aktion für alle Partner
    <?php if ($akLaeuft): ?><span class="marke2 gut" style="margin-left:6px">läuft — <?= Fmt::h(PartnerMarketing::aktionRest($akLaeuft, 'de')) ?></span>
    <?php else: ?><span class="marke2" style="margin-left:6px">keine</span><?php endif; ?></summary>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:12px"><?= Csrf::feld() ?>
    <input type="hidden" name="tat" value="partner_aktion">
    <p style="color:var(--leise);font-size:12.5px;margin:0 0 10px">Ein Satz, der auf jeder Partnerseite oben steht und den Partner als fertigen Beitrag bekommen — mit Enddatum und „Noch X Tage“. Nur versprechen, was es wirklich gibt.</p>
    <div class="reihe">
      <div class="feld"><label><input type="checkbox" name="an" value="1" style="width:auto"<?= !empty($akRoh['an']) ? ' checked' : '' ?>> Aktion zeigen</label></div>
      <div class="feld"><label>Läuft bis einschließlich</label><input type="date" name="bis" value="<?= Fmt::h((string) ($akRoh['bis'] ?? '')) ?>"></div>
    </div>
    <?php foreach (['it' => 'Italienisch', 'de' => 'Deutsch', 'en' => 'Englisch'] as $akL => $akW): ?>
      <div class="feld"><label>Text <?= $akW ?><?= trim((string) ($akRoh['texte'][$akL] ?? '')) === '' ? ' — leer: Partner auf ' . $akW . ' sehen die Aktion nicht' : '' ?></label>
        <input type="text" name="texte[<?= $akL ?>]" maxlength="240" value="<?= Fmt::h((string) ($akRoh['texte'][$akL] ?? '')) ?>"
               placeholder="<?= $akL === 'it' ? 'Autunno: verifica del sito + consulenza gratuite' : ($akL === 'de' ? 'Herbst: Website-Check + Beratung gratis' : 'Autumn: free website check + consultation') ?>"></div>
    <?php endforeach; ?>
    <button class="knopf haupt">Speichern</button>
  </form>
</details>

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
      if (Rechte::geld() && ($sepa || $verr || $offeneAuszahlungen)): /* Phase 9: Geld nur für den Admin */ ?>
<div class="block" style="border-color:rgba(255,159,90,.35)">
  <h2 style="font-size:15px;margin:0 0 8px">Auszahlungen von Hand</h2>
  <?php if ($sepa): ?>
    <p style="font-size:13.5px;margin:0 0 8px"><?= count($sepa) ?> SEPA-Überweisung<?= count($sepa) === 1 ? '' : 'en' ?> fällig:
      <?= Fmt::h(implode(', ', array_map(static fn($h) => $h['partner']['name'] . ' ' . Rechte::betrag($h['summe']), $sepa))) ?></p>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-bottom:10px">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_sepa">
      <button class="knopf haupt">SEPA-Datei herunterladen</button>
      <span style="color:var(--leise);font-size:12.5px;margin-left:8px">Im Online-Banking hochladen; wenn die Bank ausgeführt hat, unten „ausgeführt“ klicken.</span>
    </form>
  <?php endif; ?>
  <?php if ($verr): ?>
    <p style="font-size:13.5px;margin:0 0 8px">Verrechnung möglich:
      <?php foreach ($verr as $h): ?><a href="<?= Fmt::h(url('partner/' . (int) $h['partner']['id'])) ?>"><?= Fmt::h($h['partner']['name']) ?></a> <?= Fmt::h(Rechte::betrag($h['summe'])) ?> <?php endforeach; ?></p>
  <?php endif; ?>
  <?php if ($offeneAuszahlungen): ?>
    <div class="tabellenrahmen"><table><thead><tr><th>Beleg</th><th>Partner</th><th>Weg</th><th style="text-align:right">Betrag</th><th></th></tr></thead><tbody>
    <?php foreach ($offeneAuszahlungen as $a): ?>
      <tr><td><?= Fmt::h($a['nummer']) ?></td><td><?= Fmt::h($a['name']) ?></td><td><?= Fmt::h(PartnerWege::WEGE[$a['weg']] ?? $a['weg']) ?></td>
          <td style="text-align:right"><?= Fmt::h(Rechte::betrag((int) $a['betrag_cents'])) ?></td>
          <td style="white-space:nowrap">
            <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"><?= Csrf::feld() ?>
              <input type="hidden" name="tat" value="partner_auszahlung_bestaetigen"><input type="hidden" name="auszahlung" value="<?= (int) $a['id'] ?>">
              <button class="knopf">Überweisung ist raus</button></form>
            <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"><?= Csrf::feld() ?>
              <input type="hidden" name="tat" value="partner_auszahlung_abbrechen"><input type="hidden" name="auszahlung" value="<?= (int) $a['id'] ?>">
              <button class="knopf">Abbrechen</button></form></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="block">
  <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin:0 0 10px">
    <h2 style="font-size:15px;margin:0">Alle Partner<?php $klSeit = Partner::klicksSeit(); if ($klSeit): ?> <span style="font-weight:400;color:var(--leise);font-size:12.5px">Klicks gezählt seit <?= Fmt::h(Fmt::datum($klSeit)) ?></span><?php endif; ?></h2>
    <span style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if ($uebrige): ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"><?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_klicks_null">
        <button class="knopf" title="Besuche und Kanal-Klicks aller Partnerseiten auf 0 setzen; die alten Zahlen kommen ins Archiv.">Klicks auf 0 setzen</button></form>
    <?php endif; ?>
    <?php if (array_filter($uebrige, static fn($p) => !empty($p['stripe_konto']))): ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"><?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_stripe_alle_pruefen">
        <button class="knopf" title="Holt den Stand aller Partnerkonten bei Stripe ab. Nur lesen — es wird nichts geändert.">Stripe-Stand aller Konten abholen</button></form>
    <?php endif; ?>
    </span>
  </div>
  <?php if (!$uebrige): ?>
    <p style="color:var(--leise);font-size:13px">Noch keine. Bewerbungen kommen über
      <a href="<?= Fmt::h($website) ?>/partner.php" target="_blank" rel="noopener"><?= Fmt::h($website) ?>/partner.php</a>; einladen kannst du unten.</p>
  <?php else: ?>
  <div class="tabellenrahmen"><table>
    <thead><tr><th>Partner</th><th>Code</th><th style="text-align:right">Klicks</th><th style="text-align:right">Kunden</th>
               <th style="text-align:right">Offen</th><th style="text-align:right">Ausgezahlt</th><th>Land</th><th>Stripe</th></tr></thead><tbody>
    <?php foreach ($uebrige as $p): ?>
      <tr><td><a href="<?= Fmt::h(url('partner/' . (int) $p['id'])) ?>"><strong><?= Fmt::h($p['name']) ?></strong></a>
            <span class="marke2 <?= $marke[$p['status']] ?? '' ?>" style="margin-left:6px"><?= Fmt::h($p['status']) ?></span><?php if (!empty($p['test'])): ?> <span class="marke2 warnung" title="Zählt nirgends: keine Provision, keine Mail, keine Auszahlung, keine Statistik">Testpartner</span><?php endif; ?></td>
          <td><code><?= Fmt::h($p['code']) ?></code></td>
          <td style="text-align:right"><?= (int) $p['klicks'] ?></td>
          <td style="text-align:right"><?= (int) $p['kunden'] ?></td>
          <td style="text-align:right"><?= Fmt::h(Rechte::betrag((int) $p['offen'])) ?></td>
          <td style="text-align:right"><?= Fmt::h(Rechte::betrag((int) $p['ausgezahlt'])) ?></td>
          <?php /* Land und Stripe (28.09.2026): Ampel aus der Datenbank, ohne Stripe zu fragen. */ $amp = Partner::stripeAmpel($p); ?>
          <td><?= !empty($p['land']) ? Fmt::h((string) $p['land']) : '<span style="color:var(--leise)">—</span>' ?></td>
          <td><?php if (empty($p['stripe_konto'])): ?>—<?php else: ?><span class="marke2 <?= Fmt::h($amp['farbe']) ?>"><?= Fmt::h($amp['wort']) ?></span>
            <span style="color:var(--leise);font-size:12px"><?= Fmt::h((string) ($p['stripe_land'] ?: '?')) ?>-Konto</span><?php endif; ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
  <?php if (!empty($geloescht)): ?>
    <p style="color:var(--leise);font-size:12.5px;margin-top:10px"><?= count($geloescht) ?> gelöschte<?= count($geloescht) === 1 ? 'r' : '' ?> Partner, deren Belege aufbewahrt werden:
      <?php foreach ($geloescht as $g): ?><a href="<?= Fmt::h(url('partner/' . (int) $g['id'])) ?>"><?= Fmt::h($g['name']) ?></a> <?php endforeach; ?></p>
  <?php endif; ?>
  <?php if ($einbehaltMonat > 0 && Rechte::geld()): ?>
    <p style="color:var(--leise);font-size:12.5px;margin-top:10px">Steuereinbehalt im Vormonat: <b><?= Fmt::h(Rechte::betrag($einbehaltMonat)) ?></b> — per F24 abführen.</p>
  <?php endif; ?>
</div>

<?php /* Rangliste (27.09.2026): alle aktiven und pausierten, sortierbar, stille markiert. */
  $sortWort = ['umsatz' => 'Umsatz', 'kunden' => 'Kunden', 'klicks' => 'Klicks', 'letzte' => 'Letzte Aktivität', 'name' => 'Name'];
  $sortLink = static fn(string $k, string $wort) => '<a href="' . Fmt::h(url('partner') . '?sort=' . $k) . '#rangliste"' . (($sortierung ?? 'umsatz') === $k ? ' style="color:var(--text);font-weight:700" aria-current="true"' : ' style="color:inherit"') . '>' . Fmt::h($wort) . (($sortierung ?? 'umsatz') === $k ? ' ↓' : '') . '</a>';
  $stille = count(array_filter($rangliste ?? [], static fn($z) => $z['still'])); ?>
<?php if (!empty($rangliste)): ?>
<div class="block" id="rangliste">
  <h2 style="font-size:15px;margin:0 0 6px">Rangliste <span style="font-weight:400;color:var(--leise);font-size:12.5px">letzte 12 Monate<?= $stille ? ' · ' . $stille . ' still (30 Tage ohne Klick)' : '' ?><?= !empty($klSeit) ? ' · Klicks seit ' . Fmt::h(Fmt::datum($klSeit)) : '' ?></span>
    <a class="knopf" href="<?= Fmt::h(url('partner/vorlagen')) ?>" style="float:right;min-height:32px;padding:4px 12px;font-size:12.5px">Vorlagen pflegen</a>
    <a class="knopf" href="<?= Fmt::h(url('partner/mediathek')) ?>" style="float:right;min-height:32px;padding:4px 12px;font-size:12.5px;margin-right:6px">Mediathek</a>
    <?php if (Rechte::geld()): ?><a class="knopf" href="<?= Fmt::h(url('auszahlungen')) ?>" style="float:right;min-height:32px;padding:4px 12px;font-size:12.5px;margin-right:6px">Auszahlungslauf</a><?php endif; ?></h2>
  <div class="tabellenrahmen"><table>
    <thead><tr><th><?= $sortLink('name', 'Partner') ?></th><th style="text-align:right"><?= $sortLink('klicks', 'Klicks') ?></th><th style="text-align:right">30 Tage</th>
               <th style="text-align:right"><?= $sortLink('kunden', 'Kunden') ?></th><th style="text-align:right"><?= $sortLink('umsatz', 'Umsatz (netto)') ?></th>
               <th style="text-align:right">Provision</th><th><?= $sortLink('letzte', 'Letzte Aktivität') ?></th><th>Kanäle</th></tr></thead><tbody>
    <?php foreach ($rangliste as $z): ?>
      <tr<?= $z['still'] ? ' style="background:rgba(255,159,90,.05)"' : '' ?>>
          <td><a href="<?= Fmt::h(url('partner/' . (int) $z['id'])) ?>"><?= Fmt::h($z['name']) ?></a>
            <?php /* Level (Phase 5): Platin bekommt neue Anfragen aus seiner Gegend zuerst — hier sichtbar. */
              $zLv = ($zP = Partner::laden((int) $z['id'])) ? Partner::satzFuer($zP)['stufe'] : null;
              if (in_array($zLv, ['gold', 'platin'], true)): ?><span class="marke2<?= $zLv === 'platin' ? ' gut' : '' ?>" style="margin-left:4px"><?= $zLv === 'platin' ? 'Platin' : 'Gold' ?></span><?php endif; ?>
            <?php if ($z['status'] === 'pausiert'): ?><span class="marke2 warnung" style="margin-left:4px">pausiert</span><?php endif; ?>
            <?php if ($z['still']): ?><span class="marke2 warnung" style="margin-left:4px" title="<?= $z['weckruf_am'] ? 'Weckruf zuletzt ' . Fmt::h(Fmt::datum((string) $z['weckruf_am'])) : ($z['push'] ? 'Weckruf kommt automatisch' : 'Hinweise aus — kein Weckruf möglich') ?>">still</span><?php endif; ?></td>
          <td style="text-align:right"><?= (int) $z['klicks'] ?></td><td style="text-align:right"><?= (int) $z['klicks30'] ?></td><td style="text-align:right"><?= (int) $z['kunden'] ?></td>
          <td style="text-align:right"><?= Fmt::h(Rechte::betrag((int) $z['umsatz'])) ?></td><td style="text-align:right"><?= Fmt::h(Rechte::betrag((int) $z['provision'])) ?></td>
          <td style="font-size:12.5px;color:var(--dim);white-space:nowrap"><?= $z['letzte'] ? Fmt::h(Fmt::datum((string) $z['letzte'])) : '—' ?></td>
          <td style="font-size:12px;color:var(--dim)"><?= Fmt::h(implode(' · ', array_map(static fn($k) => $k['kanal'] . ' ' . $k['klicks'] . '/' . $k['kunden'], $z['kanaele']))) ?: '—' ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <p style="color:var(--leise);font-size:12px;margin-top:6px">Kanäle: Klicks/Kunden. Umsatz = bezahlte Beträge der Kunden dieses Partners (netto), ohne Erstattetes.
    „Still“: aktiv, älter als 30 Tage, kein Klick in 30 Tagen — bekommt automatisch höchstens einmal im Monat einen Weckruf aufs Handy (wenn Hinweise an).</p>
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

<?php if (Rechte::geld()): /* Phase 9: Auszahlungswege und Bedingungen (Sätze, Grenzen) nur für den Admin */ ?>
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
        <div class="feld" style="flex:0 0 150px"><label>Platin ab (Verkäufe)</label><input name="partner_platin_ab" type="number" min="3" value="<?= (int) $e('partner_platin_ab') ?>"></div>
        <p style="flex:1 1 220px;color:var(--leise);font-size:12px;margin:0 0 6px">Platin: Satz wie Gold, dazu Vorteile — Anfragen aus seiner Gegend zuerst, Abzeichen auf seiner Seite, direkter Draht zu dir.</p>
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
<?php endif; /* Rechte::geld */ ?>

<?php /* Schutz der Vecom-Unterlagen (30.09.2026, Uwe: ja) */
  require_once dirname(__DIR__) . '/src/PartnerSchutz.php';
  $sStand = ['frei' => [], 'zustimmen' => [], 'wartet' => [], 'gesperrt' => []];
  foreach ($uebrige as $sp0) { if (in_array($sp0['status'], ['aktiv', 'pausiert'], true)) { $sStand[PartnerSchutz::stand($sp0)][] = $sp0; } }
  $sPen = $e('partner_penale_cents'); $sDom = PartnerSchutz::fallenDomain(); ?>
<div class="block" id="schutz">
  <h2 style="font-size:15px;margin:0 0 6px">Schutz der Unterlagen</h2>
  <p style="color:var(--leise);font-size:12.5px;line-height:1.6;margin:0 0 12px">
    Jeder Partnerbereich ist zu, bis der Partner der Vereinbarung (Fassung <?= Fmt::h(Partner::VEREINBARUNG_VERSION) ?>) mit beiden Haken
    zugestimmt hat <b>und</b> du ihn freischaltest. Sein Link zählt die ganze Zeit weiter.</p>
  <div style="display:flex;gap:18px;flex-wrap:wrap;font-size:13.5px;margin-bottom:12px">
    <div><b style="font-size:20px"><?= count($sStand['frei']) ?></b><br><span style="color:var(--leise)">freigeschaltet</span></div>
    <div><b style="font-size:20px"><?= count($sStand['wartet']) ?></b><br><span style="color:var(--leise)">warten auf dich</span></div>
    <div><b style="font-size:20px"><?= count($sStand['zustimmen']) ?></b><br><span style="color:var(--leise)">noch nicht zugestimmt</span></div>
    <div><b style="font-size:20px"><?= count($sStand['gesperrt']) ?></b><br><span style="color:var(--leise)">gesperrt</span></div>
  </div>
  <?php if ($sStand['wartet']): ?>
    <table class="tabelle" style="margin-bottom:12px"><tbody>
    <?php foreach ($sStand['wartet'] as $sw): ?>
      <tr><td><a href="<?= Fmt::h(url('partner/' . (int) $sw['id'])) ?>"><?= Fmt::h($sw['name']) ?></a></td>
        <td style="color:var(--leise);font-size:12.5px">zugestimmt <?= Fmt::h(Fmt::datum((string) $sw['vereinbarung_klauseln_am'])) ?></td>
        <td style="text-align:right"><form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline"><?= Csrf::feld() ?>
          <input type="hidden" name="tat" value="partner_freischalten"><input type="hidden" name="id" value="<?= (int) $sw['id'] ?>"><input type="hidden" name="zurueck" value="liste">
          <button class="knopf haupt">Freischalten</button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
  <form method="post" action="<?= Fmt::h(url('')) ?>"><?= Csrf::feld() ?>
    <input type="hidden" name="tat" value="partner_schutz_einstellungen">
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
      <div class="feld" style="flex:0 0 170px"><label>Vertragsstrafe je Verstoß (€)</label><input name="partner_penale" value="<?= Fmt::h($eu((int) $sPen)) ?>"></div>
      <div class="feld" style="flex:0 0 170px"><label>Kundenschutz (Monate)</label><input name="partner_kundenschutz_monate" value="<?= Fmt::h($e('partner_kundenschutz_monate')) ?>"></div>
      <div class="feld" style="flex:1 1 240px"><label>Domain der Kontrolladressen (leer = aus)</label><input name="partner_fallen_domain" value="<?= Fmt::h($e('partner_fallen_domain')) ?>" placeholder="z. B. vecom-kontrolle.it"></div>
    </div>
    <p style="color:var(--leise);font-size:12px;line-height:1.6;margin:4px 0 10px">
      Strafe und Monate stehen in der Vereinbarung. Wer schon zugestimmt hat, behält seine Fassung, bis es eine neue gibt.
      Kontrolleinträge brauchen eine Domain, deren Sammeladresse (Catch-all) ins Akquise-Postfach läuft
      <?= $sDom !== '' ? '— <span class="marke2 gut">an: @' . Fmt::h($sDom) . '</span>' : '— <span class="marke2 warnung">aus</span>' ?>.</p>
    <button class="knopf">Speichern</button>
  </form>
</div>
