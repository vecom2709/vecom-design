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
    <div><span style="color:var(--leise)">Vereinbarung</span><br><a href="#schutz"><?= $p['vereinbarung_am'] ? 'Fassung ' . Fmt::h((string) $p['vereinbarung_version']) . ' · ' . Fmt::h(Fmt::datum((string) $p['vereinbarung_am'])) : 'noch nicht' ?></a></div>
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
  <?php if (!empty($p['stripe_konto']) || !empty($p['land'])): /* Land und Stripe-Verifizierung (28.09.2026) */
    $amp = Partner::stripeAmpel($p);
    $kst = Partner::kontoStand($p, false);
    $lname = static fn(?string $c): string => $c ? Partner::flagge($c) . ' ' . Texte::h(Texte::STRIPE_LAENDER[$c] ?? [], 'de', $c) . ' (' . $c . ')' : '—';
    $ja = static fn(bool $b, string $w1, string $w0): string => '<span class="marke2 ' . ($b ? 'gut' : 'warnung') . '">' . Fmt::h($b ? $w1 : $w0) . '</span>'; ?>
    <div class="stripe-akte" style="margin-top:14px;padding-top:12px;border-top:1px solid var(--linie)">
      <h3 style="font-size:13.5px;margin:0 0 8px">Stripe-Auszahlungskonto <?php if (!empty($p['stripe_konto'])): ?><span class="marke2 <?= Fmt::h($amp['farbe']) ?>" style="margin-left:6px"><?= Fmt::h($amp['wort']) ?></span><?php endif; ?></h3>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px 20px;font-size:13px;line-height:1.55">
        <div><span style="color:var(--leise)">Land (vom Partner)</span><br><?= Fmt::h($lname($p['land'] ?? null)) ?></div>
        <div><span style="color:var(--leise)">Stripe-Land</span><br><?= !empty($p['stripe_konto']) ? Fmt::h($p['stripe_land'] ? $lname((string) $p['stripe_land']) : 'noch nicht abgefragt') : '—' ?></div>
        <div><span style="color:var(--leise)">Account-ID</span><br><?php if (!empty($p['stripe_konto'])): ?><a href="https://dashboard.stripe.com/connect/accounts/<?= Fmt::h(rawurlencode((string) $p['stripe_konto'])) ?>" target="_blank" rel="noopener"><code><?= Fmt::h((string) $p['stripe_konto']) ?></code></a><?php else: ?>—<?php endif; ?></div>
        <?php if (!empty($p['stripe_konto'])): ?>
        <div><span style="color:var(--leise)">Verifizierung</span><br><?= $ja($kst['identitaet'], 'Identität bestätigt', $kst['stand'] === 'abgelehnt' ? 'abgelehnt' : 'nicht abgeschlossen') ?><?= $kst['fehlt'] > 0 ? ' <span style="color:var(--leise)">' . (int) $kst['fehlt'] . ' Angabe' . ($kst['fehlt'] === 1 ? '' : 'n') . ' fehlen</span>' : '' ?></div>
        <div><span style="color:var(--leise)">Auszahlungen</span><br><?= $ja($kst['auszahlung'], 'aktiviert', 'noch nicht') ?></div>
        <?php if ((string) ($p['stripe_faellig'] ?? '') !== ''): ?>
        <div style="grid-column:1/-1"><span style="color:var(--leise)">Stripe verlangt noch<?= $p['stripe_frist'] ? ' — bis ' . Fmt::h(date('d.m.Y', strtotime((string) $p['stripe_frist']))) . ', sonst setzt Stripe die Auszahlungen aus' : '' ?></span><br>
          <code style="font-size:12px;white-space:normal;word-break:break-word"><?= Fmt::h(str_replace(',', ', ', (string) $p['stripe_faellig'])) ?></code></div>
        <?php endif; ?>
        <div><span style="color:var(--leise)">Letzter Stripe-Status</span><br><?= $p['stripe_status_am'] ? Fmt::h(Fmt::datum((string) $p['stripe_status_am'])) . ' · ' . Fmt::h(['vollstaendig' => 'vollständig', 'pruefung' => 'in Prüfung', 'offen' => 'Angaben fehlen', 'abgelehnt' => 'abgelehnt', 'angelegt' => 'angelegt', 'unbekannt' => 'unbekannt'][$kst['stand']] ?? $kst['stand']) : 'noch nie abgefragt' ?>
          <?php if ((string) ($p['stripe_status_fehler'] ?? '') !== ''): ?><br><span style="color:var(--rot);font-size:12px">Stripe: <?= Fmt::h((string) $p['stripe_status_fehler']) ?></span><?php endif; ?></div>
        <?php endif; ?>
        <?php if (!empty($p['stripe_konto_alt'])): ?><div><span style="color:var(--leise)">Früheres Konto</span><br><code><?= Fmt::h((string) $p['stripe_konto_alt']) ?></code></div><?php endif; ?>
      </div>
      <?php if (Partner::landAbweichend($p)): ?>
        <div class="hinweis" role="status" style="margin-top:12px;background:rgba(255,159,90,.12);border-color:rgba(255,159,90,.35);color:var(--gelb)"><?= Fmt::h(Partner::landHinweis($p)) ?></div>
      <?php endif; ?>
      <?php if (!empty($p['stripe_konto'])): /* bewusst: Land umstellen bzw. neu einrichten (28.09.2026) */
        $slVor = (string) ($p['land'] ?: $p['stripe_land']); ?>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center"><?= Csrf::feld() ?>
          <input type="hidden" name="tat" value="partner_stripe_neu"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <label for="sl_land" style="margin:0;font-size:12.5px;color:var(--leise)">Neues Konto im Land</label>
          <select id="sl_land" name="land" style="width:auto;min-width:200px">
            <?php foreach (Partner::getSupportedStripeCountries('de') as $slC => $slN): ?>
              <option value="<?= Fmt::h($slC) ?>"<?= $slC === $slVor ? ' selected' : '' ?>><?= Fmt::h($slN . ' (' . $slC . ')') ?></option>
            <?php endforeach; ?>
          </select>
          <button class="knopf">Stripe-Verifizierung neu einrichten</button>
        </form>
        <p style="color:var(--leise);font-size:12px;margin:6px 0 0">Nur bewusst: löst die Verknüpfung (das alte Konto wird bei Stripe nur gelöscht, wenn es nie Geld empfangen konnte) und legt gleich ein neues Konto im gewählten Land an. Den Ausweis und die IBAN gibt der Partner danach selbst bei Stripe an.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php /* Schutz der Vecom-Unterlagen (30.09.2026, Uwe: ja) */
  require_once dirname(__DIR__) . '/src/PartnerSchutz.php';
  $scStand = PartnerSchutz::stand($p);
  $scWort = ['frei' => ['gut', 'freigeschaltet'], 'wartet' => ['warnung', 'wartet auf deine Freischaltung'], 'zustimmen' => ['warnung', 'noch nicht zugestimmt — Bereich gesperrt'], 'gesperrt' => ['schlecht', 'gesperrt']][$scStand];
  $scVs = PartnerSchutz::verstoesse((int) $p['id']);
  $scTr = PartnerSchutz::fallenTreffer((int) $p['id']);
  $scZ = PartnerSchutz::zugriffZahlen((int) $p['id']);
  $scZu = PartnerSchutz::zugriffe((int) $p['id'], 40); ?>
<?php if (!in_array($p['status'], ['bewerbung', 'abgelehnt', 'geloescht'], true)): ?>
<div class="block" id="schutz"<?= $scTr || $scStand === 'gesperrt' ? ' style="border-color:rgba(255,138,138,.35)"' : '' ?>>
  <h2 style="font-size:15px;margin:0 0 8px">Vereinbarung und Schutz <span class="marke2 <?= $scWort[0] ?>" style="margin-left:6px"><?= Fmt::h($scWort[1]) ?></span></h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px 20px;font-size:13px;line-height:1.55">
    <div><span style="color:var(--leise)">Fassung</span><br><?= Fmt::h((string) ($p['vereinbarung_version'] ?? '—')) ?><?= (string) ($p['vereinbarung_version'] ?? '') !== Partner::VEREINBARUNG_VERSION ? ' <span class="marke2 warnung">alt</span>' : '' ?></div>
    <div><span style="color:var(--leise)">Zugestimmt (beide Haken)</span><br><?= !empty($p['vereinbarung_klauseln_am']) ? Fmt::h(date('d.m.Y H:i', strtotime((string) $p['vereinbarung_klauseln_am']))) . ' · ' . Fmt::h(strtoupper((string) $p['vereinbarung_sprache'])) : '—' ?></div>
    <div><span style="color:var(--leise)">Freigeschaltet</span><br><?= !empty($p['freigeschaltet_am']) ? Fmt::h(date('d.m.Y H:i', strtotime((string) $p['freigeschaltet_am']))) . ' · ' . Fmt::h((string) $p['freigeschaltet_von']) : '—' ?></div>
    <div><span style="color:var(--leise)">Kennung in seinen PDFs</span><br><code><?= Fmt::h(PartnerSchutz::kennung($p)) ?></code></div>
    <?php if (!empty($p['vereinbarung_hash'])): ?><div style="grid-column:1/-1"><span style="color:var(--leise)">Prüfsumme des Wortlauts (SHA-256)</span><br><code style="font-size:11.5px;word-break:break-all"><?= Fmt::h((string) $p['vereinbarung_hash']) ?></code></div><?php endif; ?>
    <?php if (!empty($p['gesperrt_am'])): ?><div style="grid-column:1/-1;color:var(--rot)">Gesperrt am <?= Fmt::h(date('d.m.Y H:i', strtotime((string) $p['gesperrt_am']))) ?>: <?= Fmt::h((string) $p['gesperrt_grund']) ?></div><?php endif; ?>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;align-items:center">
    <?php if ($scStand === 'wartet' || $scStand === 'gesperrt'): ?>
      <?= $scStand === 'gesperrt' && ((string) $p['vereinbarung_version'] !== Partner::VEREINBARUNG_VERSION || empty($p['vereinbarung_klauseln_am'])) ? '' : $hin('partner_freischalten', $scStand === 'gesperrt' ? 'Sperre aufheben' : 'Freischalten', true) ?>
    <?php endif; ?>
    <?php if ($scStand !== 'gesperrt'): ?>
      <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:6px;align-items:center"><?= Csrf::feld() ?>
        <input type="hidden" name="tat" value="partner_sperren"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
        <input name="grund" placeholder="Grund der Sperre" maxlength="255" style="width:220px"><button class="knopf">Sperren</button></form>
    <?php endif; ?>
    <a class="knopf" href="<?= Fmt::h(url('partner/' . (int) $p['id']) . '?akte=it') ?>" target="_blank" rel="noopener">Akte für den Anwalt (IT)</a>
    <a class="knopf" href="<?= Fmt::h(url('partner/' . (int) $p['id']) . '?akte=de') ?>" target="_blank" rel="noopener">Akte (deutsch)</a>
  </div>

  <?php if ($scTr): ?>
    <h3 style="font-size:13.5px;margin:16px 0 6px;color:var(--rot)">Kontrolleinträge angeschrieben (<?= count($scTr) ?>)</h3>
    <ul style="margin:0;padding-left:18px;font-size:13px;line-height:1.6">
      <?php foreach ($scTr as $t): ?><li><?= Fmt::h(date('d.m.Y H:i', strtotime((string) ($t['eingang_am'] ?: $t['created_at'])))) ?> · an <code><?= Fmt::h($t['email']) ?></code> (<?= Fmt::h($t['name'] . ', ' . $t['ort']) ?>) von <?= Fmt::h($t['von']) ?> — <?= Fmt::h($t['betreff']) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <h3 style="font-size:13.5px;margin:16px 0 6px">Verstöße (<?= count($scVs) ?>)</h3>
  <?php foreach ($scVs as $v): ?>
    <div style="border-top:1px solid var(--linie);padding:8px 0;font-size:13px;line-height:1.55">
      <b><?= Fmt::h(date('d.m.Y', strtotime((string) $v['festgestellt_am']))) ?> · <?= Fmt::h(PartnerSchutz::VERSTOSS_ARTEN[$v['art']] ?? $v['art']) ?></b>
      <span style="color:var(--leise)"> · <?= Fmt::h((string) $v['erfasst_von']) ?></span><br><?= nl2br(Fmt::h((string) $v['beschreibung'])) ?>
      <?php if ((string) $v['beleg'] !== ''): ?><br><span style="color:var(--leise)">Beleg: <?= nl2br(Fmt::h((string) $v['beleg'])) ?></span><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <details style="margin-top:8px"><summary style="cursor:pointer;color:var(--cyan);font-size:13.5px">Verstoß festhalten</summary>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:10px"><?= Csrf::feld() ?>
      <input type="hidden" name="tat" value="partner_verstoss"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <div style="display:flex;gap:12px;flex-wrap:wrap">
        <div class="feld" style="flex:1 1 260px"><label>Art</label><select name="art" required><option value="">— wählen —</option>
          <?php foreach (PartnerSchutz::VERSTOSS_ARTEN as $vk => $vw): ?><option value="<?= Fmt::h($vk) ?>"><?= Fmt::h($vw) ?></option><?php endforeach; ?></select></div>
        <div class="feld" style="flex:0 0 170px"><label>Festgestellt am</label><input type="date" name="festgestellt_am" value="<?= date('Y-m-d') ?>"></div>
      </div>
      <div class="feld"><label>Was ist passiert?</label><textarea name="beschreibung" rows="3" required minlength="10" placeholder="z. B. Betrieb X aus seiner Reservierung hat jetzt eine Website vom Partner selbst (gefunden am …)"></textarea></div>
      <div class="feld"><label>Beleg (Links, Screenshots-Ablage, Zeugen)</label><textarea name="beleg" rows="2"></textarea></div>
      <button class="knopf">Festhalten</button>
    </form>
  </details>

  <details style="margin-top:10px"><summary style="cursor:pointer;color:var(--cyan);font-size:13.5px">Zugriffe (30 Tage: <?= Fmt::h(implode(', ', array_map(static fn($k, $n) => $n . '× ' . $k, array_keys($scZ), $scZ)) ?: 'keine') ?>)</summary>
    <table class="tabelle" style="margin-top:8px;font-size:12.5px"><tbody>
      <?php foreach ($scZu as $z): ?>
        <tr><td style="white-space:nowrap"><?= Fmt::h(date('d.m. H:i', strtotime((string) $z['created_at']))) ?></td><td><?= Fmt::h((string) $z['art']) ?></td>
          <td><?= (int) $z['firma_id'] !== 0 ? Fmt::h(((int) $z['firma_id'] < 0 ? 'Kontrolleintrag #' . -(int) $z['firma_id'] : ($z['firma'] ? $z['firma'] . ' (' . $z['stadt'] . ')' : '#' . $z['firma_id']))) : '' ?> <?= Fmt::h((string) $z['info']) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </details>
</div>
<?php endif; ?>

<?php /* Partner-Tracking (30.09.2026): die letzten 30 Tage in einer Zeile, Einzelheiten im eigenen Bereich */
  require_once dirname(__DIR__) . '/src/Spur.php';
  $trZ = Spur::zeitraum('30'); $trK = Spur::kennzahlen($trZ[0], $trZ[1], ['partner' => (int) $p['id']]); ?>
<div class="block" id="tracking">
  <h2 style="font-size:15px;margin:0 0 8px">Tracking · 30 Tage <a class="mehr" href="<?= Fmt::h(url('tracking') . '?partner=' . (int) $p['id']) ?>#partner" style="margin-left:auto;font-size:12.5px;font-weight:400">Partner-Tracking öffnen →</a></h2>
  <div style="display:flex;gap:18px;flex-wrap:wrap;font-size:13.5px">
    <?php foreach (['klicks' => 'Klicks', 'sitzungen' => 'Besucher', 'rechner_gestartet' => 'Preisrechner', 'fragebogen' => 'Fragebögen', 'anfragen' => 'Anfragen', 'kunden' => 'Kunden'] as $trS => $trW): ?>
      <div><b style="font-size:20px"><?= (int) $trK[$trS] ?></b><br><span style="color:var(--leise)"><?= $trW ?></span></div>
    <?php endforeach; ?>
    <div><b style="font-size:20px"><?= Fmt::h(Fmt::geld((int) $trK['umsatz'])) ?></b><br><span style="color:var(--leise)">Umsatz</span></div>
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

<div class="block" id="nachrichten">
  <h2 style="font-size:15px;margin:0 0 10px">Nachrichten<?php $offenN = count(array_filter($nachrichten ?? [], static fn($n) => $n['von'] === 'partner' && $n['gelesen_am'] === null)); ?></h2>
  <?php if (empty($nachrichten)): ?>
    <p style="color:var(--leise);font-size:13px;margin:0 0 10px">Noch keine. Der Partner schreibt über seine Seite; deine Antwort bekommt er per Mail und, wenn er die App hat, aufs Handy.</p>
  <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:8px;max-height:380px;overflow-y:auto;margin-bottom:12px">
      <?php foreach ($nachrichten as $n): $vp = $n['von'] === 'partner'; ?>
        <div style="max-width:80%;<?= $vp ? 'align-self:flex-start' : 'align-self:flex-end' ?>;padding:9px 12px;border-radius:12px;border:1px solid var(--linie);
                    background:<?= $vp ? 'var(--flaeche2)' : 'rgba(241,211,139,.08)' ?>;white-space:pre-wrap;font-size:14px;line-height:1.5"><?= Fmt::h((string) $n['text']) ?>
          <div style="font-size:11.5px;color:var(--leise);margin-top:4px"><?= $vp ? Fmt::h($p['name']) : 'Vecom' ?> · <?= Fmt::h(date('d.m.Y H:i', strtotime((string) $n['created_at']))) ?>
            <?= !$vp && $n['gelesen_am'] ? ' · gelesen' : '' ?></div></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;flex-direction:column;gap:8px">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_nachricht"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
    <textarea name="text" rows="3" required maxlength="4000" placeholder="Antwort an <?= Fmt::h($p['name']) ?>"></textarea>
    <div><button class="knopf">Antworten</button></div>
  </form>
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
                <button class="knopf" style="font-size:12px">Provision streichen</button></form>
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
        <span style="color:var(--leise);font-size:12px"><?= Fmt::h(['link' => 'über den Link', 'code' => 'Code eingetippt', 'hand' => 'von Hand', 'partner' => 'vom Partner gemeldet',
                 'telefon' => 'am Telefon genannt'][$k['quelle']] ?? $k['quelle']) ?><?= !empty($k['kanal']) ? ' · Kanal ' . Fmt::h($k['kanal']) : '' ?>, <?= Fmt::h(Fmt::datum((string) $k['created_at'])) ?></span></li>
    <?php endforeach; ?></ul>
  <?php endif; ?>
  <?php if ($p['status'] === 'aktiv' && $alleKunden): ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_zuordnen"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <div class="feld" style="margin:0;min-width:240px"><label>Kunde von Hand zuordnen (z. B. hat am Telefon den Partner genannt)</label>
        <select name="kunde"><?php foreach ($alleKunden as $k): ?><option value="<?= (int) $k['id'] ?>"><?= Fmt::h(Fmt::name($k['name'], $k['company']) . ($k['company'] && trim((string) $k['name']) !== '' ? ' — ' . $k['company'] : '')) ?></option><?php endforeach; ?></select></div>
      <button class="knopf">Kunden zuordnen</button></form>
    <p style="color:var(--leise);font-size:12px;margin-top:6px">Nur Kunden ohne Partner stehen in der Liste. Eine Zuordnung wird nie umgehängt; Käufe vor der Zuordnung zählen nicht.</p>
  <?php endif; ?>
</div>

<?php /* Kunden & Leads des Partners (Phase 2, 05.10.2026, Uwe: „Ja, wie empfohlen“): Name, Stufe und die ART des
         letzten Schritts — nie der Text seiner Notizen oder Aufgaben. Nur lesen: Die Liste gehört dem Partner. */
  require_once dirname(__DIR__) . '/src/PartnerLeads.php';
  $pLeads = [];
  try { $pLeads = PartnerLeads::fuerVerwaltung((int) $p['id']); } catch (Throwable $e) { $pLeads = []; }
  $plStufe = static fn(string $s): string => Texte::h(Texte::PARTNER_LEADS['stufen'][$s] ?? [], 'de', $s);
  $plQuelle = static fn(string $q): string => Texte::h(Texte::PARTNER_LEADS['quellen'][$q] ?? [], 'de', $q);
  $plArt = static fn(string $a): string => $a === '' ? '—' : Texte::h(Texte::PARTNER_LEADS['arten'][$a] ?? [], 'de', $a); ?>
<div class="block" id="leads">
  <h2 style="font-size:15px;margin:0 0 6px">Kunden &amp; Leads des Partners<?php if ($pLeads): ?> <span style="color:var(--leise);font-weight:400">(<?= count(array_filter($pLeads, static fn($l) => !$l['archiviert'])) ?> aktiv)</span><?php endif; ?></h2>
  <p style="color:var(--leise);font-size:12px;margin:0 0 10px">Seine eigene Liste im Command Center. Notizen und Aufgaben sieht nur er — hier steht nur, was zuletzt passiert ist.</p>
  <?php if (!$pLeads): ?><p style="color:var(--leise);font-size:13px">Noch keine.</p><?php else: ?>
  <div class="tabellenrahmen"><table>
    <thead><tr><th>Betrieb</th><th>Stufe</th><th>Quelle</th><th>Letzter Schritt</th><th>Angelegt</th></tr></thead><tbody>
    <?php foreach ($pLeads as $l): ?>
      <tr<?= $l['archiviert'] ? ' style="opacity:.5"' : '' ?>><td><?= Fmt::h($l['name']) ?><?= $l['uebergeben_am'] ? ' <span class="marke2 gut">übergeben ' . Fmt::h(Fmt::datum((string) $l['uebergeben_am'])) . '</span>' : '' ?><?= $l['archiviert'] ? ' <span style="font-size:11.5px;color:var(--leise)">archiviert</span>' : '' ?></td>
          <td><?= Fmt::h($plStufe($l['stufe'])) ?></td>
          <td style="font-size:12.5px"><?= Fmt::h($plQuelle($l['quelle'])) ?></td>
          <td style="font-size:12.5px"><?= Fmt::h($plArt($l['letzter'])) ?><?= $l['letzter_am'] ? ' · ' . Fmt::h(Fmt::datum((string) $l['letzter_am'])) : '' ?></td>
          <td style="font-size:12.5px"><?= Fmt::h(Fmt::datum($l['created_at'])) ?></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>

<?php if ($p['status'] !== 'geloescht' && (!empty($p['foto_am']) || (string) ($p['profil_satz'] ?? '') !== '')): require_once dirname(__DIR__) . '/src/PartnerWerbung.php'; $pf = PartnerWerbung::fotoAdresse($p); ?>
<div class="block" id="profil">
  <h2 style="font-size:15px;margin:0 0 6px">Empfehlungsseite</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:0 0 10px">Steht öffentlich auf <code><?= Fmt::h(Partner::link($p)) ?></code>. Unpassendes hier entfernen — Besucher sehen danach die Seite ohne Foto und Satz.</p>
  <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap">
    <?php if ($pf): ?><img src="<?= Fmt::h($pf) ?>" alt="" width="64" height="64" style="border-radius:50%;object-fit:cover"><?php endif; ?>
    <?php if ((string) ($p['profil_satz'] ?? '') !== ''): ?><blockquote style="margin:0;flex:1;min-width:200px">„<?= Fmt::h((string) $p['profil_satz']) ?>“</blockquote><?php endif; ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_profil_weg"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
      <button class="knopf">Foto und Satz entfernen</button></form>
  </div>
</div>
<?php endif; ?>

<?php if ($p['status'] !== 'geloescht' && (!empty($p['seite_json']) || !empty($p['seite_bild_am']))): require_once dirname(__DIR__) . '/src/PartnerSeite.php'; $psg = PartnerSeite::gestaltung($p); ?>
<div class="block" id="seite">
  <h2 style="font-size:15px;margin:0 0 6px">Selbst gestaltete Empfehlungsseite</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:0 0 10px">Vorlage <?= Fmt::h($psg['vorlage']) ?> · Farbe <?= Fmt::h($psg['akzent']) ?> · Bild <?= Fmt::h($psg['bild'] !== '' ? $psg['bild'] : 'keins') ?>
    · <?= count($psg['texte']) ?> Sprache(n) mit eigenem Text · zuletzt geändert <?= Fmt::h(Fmt::zeit((string) ($p['seite_am'] ?? ''))) ?>.
    <a href="/p.php?<?= Fmt::h(http_build_query(['c' => $p['code'], 'n' => 1])) ?>" target="_blank" rel="noopener" style="text-decoration:underline">Seite ansehen</a></p>
  <?php foreach ($psg['texte'] as $pl => $pt): ?>
    <p class="akq-klein" style="margin:0 0 6px;color:var(--dim)"><b><?= Fmt::h(strtoupper($pl)) ?>:</b> <?= Fmt::h(implode(' · ', $pt)) ?></p>
  <?php endforeach; ?>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:8px">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_seite_zurueck"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
    <button class="knopf">Auf Standard zurücksetzen</button></form>
</div>
<?php endif; ?>

<?php if ($p['status'] !== 'geloescht'): ?>
<div class="block">
  <h2 style="font-size:15px;margin:0 0 6px">Code und Link</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:0 0 10px">Jetzt: <code><?= Fmt::h(Partner::link($p)) ?></code>.
    5–16 Buchstaben oder Ziffern. Ändern heißt: Der alte Link funktioniert nicht mehr.</p>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_code"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
    <div class="feld" style="margin:0"><label>Neuer Code</label>
      <input name="code" value="<?= Fmt::h($p['code']) ?>" maxlength="16" required style="text-transform:uppercase"></div>
    <button class="knopf">Code ändern</button></form>
</div>
<?php /* Kurzlink (Phase 4, 05.10.2026): der Partner wählt selbst, Uwe kann ändern (ohne Obergrenze) und sperren. */
  require_once dirname(__DIR__) . '/src/PartnerKurzlink.php';
  $pkl = []; try { $pkl = PartnerKurzlink::liste((int) $p['id']); } catch (Throwable $e) { $pkl = []; } ?>
<div class="block">
  <h2 style="font-size:15px;margin:0 0 6px">Kurzlink</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:0 0 10px">
    <?php if ($pkl): ?>Namen: <?php foreach ($pkl as $i => $kn): ?><?= $i ? ' · ' : '' ?><code>/go/<?= Fmt::h((string) $kn['name']) ?></code> (<?= Fmt::h((string) $kn['status']) ?>)<?php endforeach; ?>.
      Alte Namen führen weiter zum Partner; gesperrte nirgends hin.
    <?php else: ?>Noch keiner. Der Partner wählt ihn unter MARKETING › Kampagnen &amp; QR.<?php endif; ?></p>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_kurzlink"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
    <div class="feld" style="margin:0"><label>Name setzen</label>
      <input name="name" value="<?= Fmt::h((string) (PartnerKurzlink::name((int) $p['id']) ?? '')) ?>" maxlength="30" required></div>
    <button class="knopf">Speichern</button></form>
  <?php foreach ($pkl as $kn): if ($kn['status'] === 'gesperrt') { continue; } ?>
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:inline-block;margin:8px 8px 0 0">
      <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_kurzlink_sperren"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="name" value="<?= Fmt::h((string) $kn['name']) ?>">
      <button class="knopf" style="min-height:32px;padding:5px 10px;font-size:12.5px">/go/<?= Fmt::h((string) $kn['name']) ?> sperren</button></form>
  <?php endforeach; ?>
</div>
<?php /* E-Mail-Center (Phase 7a, 05.10.2026, Uwe: „nur bei denen einbauen, die eine @vecom Email haben“):
         die bestehende Adresse zuordnen — angelegt, geändert oder gelesen wird im KAS dabei nichts außer der Adressliste. */
  require_once dirname(__DIR__) . '/src/PartnerMail.php';
  $pmKas = PartnerMail::kasAdressen(); $pmVergeben = array_column(Db::all('SELECT vecom_adresse FROM partner WHERE vecom_adresse IS NOT NULL AND id <> ?', [(int) $p['id']]), 'vecom_adresse');
  $pmMails = []; try { $pmMails = PartnerMail::fuerVerwaltung((int) $p['id'], 30); } catch (Throwable $e) { $pmMails = []; } ?>
<div class="block" id="vecom-adresse">
  <h2 style="font-size:15px;margin:0 0 6px">@vecom-Adresse und E-Mails</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:0 0 10px">
    <?php if (!empty($p['vecom_adresse'])): ?>Sendet aus dem Dashboard als <code><?= Fmt::h((string) $p['vecom_adresse']) ?></code> — Antworten gehen an diese Adresse. Höchstens <?= PartnerMail::TAG_MAX ?> am Tag, <?= PartnerMail::STUNDE_MAX ?> pro Stunde, Betreff Pflicht, Abmeldelink unter jeder Mail.
    <?php else: ?>Keine Adresse zugeordnet: Der Partner hat kein E-Mail-Center, nur den Link ins eigene Mailprogramm.<?php endif; ?></p>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="partner_vecom_adresse"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
    <div class="feld" style="margin:0;min-width:260px"><label for="pm-adr">Adresse (leer = keine)</label>
      <input id="pm-adr" name="adresse" value="<?= Fmt::h((string) ($p['vecom_adresse'] ?? '')) ?>" maxlength="120" list="pm-kas" placeholder="name@<?= PartnerMail::DOMAIN ?>" autocomplete="off">
      <datalist id="pm-kas"><?php foreach ($pmKas['adressen'] as $pmA => $pmArt): if (in_array($pmA, $pmVergeben, true)) { continue; } ?><option value="<?= Fmt::h((string) $pmA) ?>"><?= Fmt::h((string) $pmArt) ?></option><?php endforeach; ?></datalist></div>
    <button class="knopf">Speichern</button></form>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:8px 0 0">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="vecom_adressen_lesen"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
    <button class="knopf stumm" style="min-height:32px;padding:5px 10px;font-size:12.5px">Bestehende Adressen aus dem KAS lesen</button>
    <small style="color:var(--leise)"><?= $pmKas['am'] !== '' ? count($pmKas['adressen']) . ' Adressen, gelesen ' . Fmt::h($pmKas['am']) : 'noch nicht gelesen' ?> · nur lesen, kein Passwort</small></form>
  <?php if ($pmMails): ?>
    <details style="margin:12px 0 0"><summary style="font-size:13px;cursor:pointer">Gesendete E-Mails (<?= count($pmMails) ?>, mit Inhalt)</summary>
      <div class="tabellenrahmen"><table id="partner-mails"><thead><tr><th>Wann</th><th>An</th><th>Betreff</th><th>Stand</th></tr></thead><tbody>
      <?php foreach ($pmMails as $pmX): ?>
        <tr><td><?= Fmt::h(Fmt::datum((string) $pmX['created_at'])) ?></td><td><?= Fmt::h((string) $pmX['an']) ?></td>
          <td><details><summary><?= Fmt::h((string) $pmX['betreff']) ?></summary><pre style="white-space:pre-wrap;font:13px/1.5 inherit;margin:6px 0 0"><?= Fmt::h((string) $pmX['text']) ?></pre></details></td>
          <td><?= Fmt::h($pmX['abgemeldet_am'] !== null ? 'abgemeldet' : (string) $pmX['status']) ?><?= $pmX['fehler'] ? ' · ' . Fmt::h((string) $pmX['fehler']) : '' ?></td></tr>
      <?php endforeach; ?></tbody></table></div></details>
  <?php endif; ?>
</div>
<?php endif; ?>

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
      <div class="feld" style="flex:0 0 130px"><label>Sprache</label><select name="sprache">
        <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $w): ?>
          <option value="<?= $l ?>" <?= $p['sprache'] === $l ? 'selected' : '' ?>><?= $w ?></option><?php endforeach; ?></select></div>
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
