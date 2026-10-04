<?php
/* Marketing Center Verwaltung (03.10.2026, Phase 1).
   Hier steht, was Partner nie sehen: Einkauf, Marge, Regel. Der Partner
   bekommt nur Werbemittel::katalog() — Name, Format, Endpreis.
   Ein Formular je Variante über das form-Attribut, weil ein <form> keine
   Tabellenzeile umschließen darf. */
$st = $wm['standard'];
$zk = $wm['zahlkosten'] ?? Werbemittel::zahlkosten();
$kats = array_column($wm['kategorien'], null, 'id');
$jeKat = [];
foreach ($wm['produkte'] as $p) { $jeKat[(int) $p['kategorie_id']][] = $p; }
$eur = static fn(?int $c): string => $c === null ? '' : number_format($c / 100, 2, ',', '');
$mm = static fn(int $z): string => $z % 10 === 0 ? (string) intdiv($z, 10) : str_replace('.', ',', (string) ($z / 10));
$produktFelder = static function (?array $p) use ($kats, $eur, $mm): void { ?>
  <div class="reihe">
    <div class="feld"><label>Kategorie</label><select name="kategorie_id">
      <?php foreach ($kats as $k): ?><option value="<?= (int) $k['id'] ?>" <?= (int) ($p['kategorie_id'] ?? 0) === (int) $k['id'] ? 'selected' : '' ?>><?= Fmt::h($k['name_de'] ?: $k['name_it']) ?></option><?php endforeach; ?>
    </select></div>
    <div class="feld"><label>Bereich im Marketing Center</label><select name="bereich">
      <option value="">— wie die Kategorie</option>
      <?php foreach (['print' => 'Print', 'pos' => 'Point of Sale', 'textil' => 'Textilien', 'fahrzeug' => 'Fahrzeugwerbung', 'event' => 'Events & Messe', 'premium' => 'Premium', 'geschenke' => 'Geschenke für Betriebe', 'starter' => 'Starterpakete'] as $b => $bn): ?><option value="<?= $b ?>" <?= ($p['bereich'] ?? '') === $b ? 'selected' : '' ?>><?= $bn ?></option><?php endforeach; ?>
    </select></div>
    <div class="feld"><label>Vorlage für die Druckdatei</label><select name="vorlage">
      <option value="">— noch keine (nicht bestellbar)</option>
      <?php foreach (Werbemittel::VORLAGEN as $v => $n): ?><option value="<?= Fmt::h($v) ?>" <?= ($p['vorlage'] ?? '') === $v ? 'selected' : '' ?>><?= Fmt::h($n) ?></option><?php endforeach; ?>
    </select></div>
  </div>
  <div class="reihe">
    <div class="feld"><label>Name Italienisch *</label><input name="name_it" required value="<?= Fmt::h($p['name_it'] ?? '') ?>"></div>
    <div class="feld"><label>Name Deutsch</label><input name="name_de" value="<?= Fmt::h($p['name_de'] ?? '') ?>"></div>
    <div class="feld"><label>Name Englisch</label><input name="name_en" value="<?= Fmt::h($p['name_en'] ?? '') ?>"></div>
  </div>
  <div class="reihe">
    <div class="feld"><label>Text Italienisch</label><input name="text_it" value="<?= Fmt::h($p['text_it'] ?? '') ?>"></div>
    <div class="feld"><label>Text Deutsch</label><input name="text_de" value="<?= Fmt::h($p['text_de'] ?? '') ?>"></div>
    <div class="feld"><label>Text Englisch</label><input name="text_en" value="<?= Fmt::h($p['text_en'] ?? '') ?>"></div>
  </div>
  <div class="reihe">
    <div class="feld"><label>Breite (mm)</label><input name="breite_mm" inputmode="decimal" value="<?= $p ? $mm((int) $p['breite_zmm']) : '' ?>"></div>
    <div class="feld"><label>Höhe (mm)</label><input name="hoehe_mm" inputmode="decimal" value="<?= $p ? $mm((int) $p['hoehe_zmm']) : '' ?>"></div>
    <div class="feld"><label>Beschnitt (mm)</label><input name="beschnitt_mm" inputmode="decimal" value="<?= $p ? $mm((int) $p['beschnitt_zmm']) : '3' ?>"></div>
  </div>
  <div class="reihe">
    <div class="feld"><label>Eigene Marge (%) — leer = Standard</label><input name="marge_prozent" inputmode="numeric" value="<?= isset($p['marge_prozent']) ? (int) $p['marge_prozent'] : '' ?>"></div>
    <div class="feld"><label>Eigene Mindestmarge (€) — leer = Standard</label><input name="mindestmarge_eur" inputmode="decimal" value="<?= isset($p['mindestmarge_cent']) ? $eur((int) $p['mindestmarge_cent']) : '' ?>"></div>
    <div class="feld"><label>Reihenfolge</label><input name="sortierung" type="number" value="<?= (int) ($p['sortierung'] ?? 0) ?>"></div>
  </div>
  <label style="color:var(--text);display:block;margin:4px 0 10px"><input type="checkbox" name="aktiv" style="width:auto" <?= !empty($p['aktiv']) ? 'checked' : '' ?>> für Partner sichtbar (nur Varianten mit Einkaufspreis erscheinen)</label>
<?php };
?>
<div class="kopf"><div><h1>Marketing Center</h1>
  <p style="color:var(--leise);font-size:13px;margin-top:6px;max-width:760px">
    Werbemittel, die Partner mit ihrem Namen, ihrer ID und ihrem QR-Code bestellen können.
    Hier trägst du den Einkauf ein; der Preis für den Partner entsteht aus Einkauf und Marge.
    Partner sehen nur den Endpreis — nie Einkauf, Marge oder Anbieter.
    Partner bestellen mit ihrer freigegebenen Druckdatei; den Druck beauftragst du unter „Bestellungen“.</p></div>
  <div class="rechts"><a class="knopf <?= ($offeneBestellungen ?? 0) > 0 ? 'haupt' : 'stumm' ?>" href="<?= Fmt::h(url('werbemittel/bestellungen')) ?>">Bestellungen<?= ($offeneBestellungen ?? 0) > 0 ? ' (' . (int) $offeneBestellungen . ' in Arbeit)' : '' ?></a>
    <a class="knopf stumm" href="<?= Fmt::h(url('werbemittel/vorschau')) ?>">Als Partner ansehen</a></div>
</div>

<?php require_once dirname(__DIR__) . '/src/Gelato.php'; require_once dirname(__DIR__) . '/src/Partner.php'; require_once dirname(__DIR__) . '/src/WmBestellung.php'; ?>
<?php require_once dirname(__DIR__) . '/src/HelloPrint.php'; require_once dirname(__DIR__) . '/src/Printful.php'; require_once dirname(__DIR__) . '/src/Druckerei.php'; ?>
<div class="block" style="max-width:820px"><h2>Angebundene Druckereien</h2>
  <p style="font-size:14px;margin:0 0 6px"><strong>HelloPrint (Connect):</strong>
    <?php if (HelloPrint::bereit()): ?><span class="marke2 gut">Schlüssel eingetragen</span> liefert nach <?= Partner::flagge(HelloPrint::land()) ?> <?= Fmt::h(HelloPrint::land()) ?> · Modus <strong><?= HelloPrint::modus() === 'prod' ? 'echt' : 'Test (es wird nichts gedruckt)' ?></strong>
    <?php else: ?><span class="marke2">nicht angebunden</span> Zugang über api@helloprint.com; danach in <code>app/config.local.php</code>: <code>'helloprint' => ['api' => '…', 'land' => 'IT', 'modus' => 'test']</code>.<?php endif; ?></p>
  <p style="font-size:14px;margin:0 0 6px"><strong>Printful:</strong>
    <?php if (Printful::bereit()): ?><span class="marke2 gut">Schlüssel eingetragen</span> liefert nach 🇮🇹 IT und 🇩🇪 DE · Format 90 × 50 mm (eingepasst, der Partner sieht es vor der Freigabe) · Modus <strong><?= Printful::modus() === 'auftrag' ? 'echt (sofort in die Fertigung)' : 'Entwurf (du bestätigst im Printful-Dashboard)' ?></strong>
    <?php else: ?><span class="marke2">nicht angebunden</span> Schlüssel im Printful-Konto unter Developers → Tokens erzeugen; danach in <code>app/config.local.php</code>: <code>'printful' => ['api' => '…']</code> (bei einem Konto-Schlüssel zusätzlich <code>'store' => 'Store-ID'</code>). Währung im Konto auf EUR stellen.<?php endif; ?></p>
  <p style="font-size:14px;margin:0 0 10px"><strong>Gelato:</strong></p>

  <p style="font-size:14px;margin:0"><?php if (Gelato::bereit()): ?><span class="marke2 gut">Schlüssel eingetragen</span> Bezahlte Bestellungen gehen per Klick als Entwurf an Gelato; gedruckt wird erst nach deiner Bestätigung im Gelato-Dashboard.
    <?php else: ?><span class="marke2 warnung">Kein Schlüssel</span> In <code>app/config.local.php</code> eintragen: <code>'gelato' => ['api' => '…']</code>. Bis dahin beauftragst du den Druck von Hand.<?php endif; ?></p>
  <?php $wmPfF = Printful::druckflaechen(); if ($wmPfF['flaechen']): ?>
    <details style="margin:8px 0 0"><summary style="font-size:13px;color:var(--leise);cursor:pointer">Printful-Druckflächen (abgefragt <?= Fmt::h($wmPfF['am']) ?>)</summary>
      <div class="tabellenrahmen"><table id="pf-druckflaechen"><thead><tr><th>Produkt</th><th>Druckstelle</th><th class="num">Breite px</th><th class="num">Höhe px</th><th class="num">dpi</th><th>Füllung</th><th>unser Bild</th></tr></thead><tbody>
      <?php foreach ($wmPfF['flaechen'] as $wmFn => $wmFp): foreach ($wmFp as $wmPl => $wmFf): $wmSoll = Printful::ARTEN[$wmFn]['px'] ?? null; ?>
        <tr><td><?= Fmt::h($wmFn) ?></td><td><?= Fmt::h($wmPl) ?></td><td class="num"><?= (int) $wmFf['b'] ?></td><td class="num"><?= (int) $wmFf['h'] ?></td><td class="num"><?= (int) $wmFf['dpi'] ?></td><td><?= Fmt::h($wmFf['fill']) ?><?= $wmFf['drehen'] ? ' · drehbar' : '' ?></td>
          <td><?= $wmSoll === null ? '—' : ($wmSoll[1] > 0 && $wmFf['h'] > 0 && abs($wmSoll[0] / $wmSoll[1] - $wmFf['b'] / $wmFf['h']) / ($wmFf['b'] / $wmFf['h']) <= 0.01 ? '<span class="marke2 gut">' . $wmSoll[0] . ' × ' . $wmSoll[1] . ' passt</span>' : '<span class="marke2 schlecht">' . $wmSoll[0] . ' × ' . $wmSoll[1] . ' passt nicht</span>') ?></td></tr>
      <?php endforeach; endforeach; ?></tbody></table></div></details>
  <?php endif; ?>
  <p style="color:var(--leise);font-size:12.5px;margin:8px 0 0">Je Auflage unten die Gelato-Artikelnummer (productUid) und die Menge eintragen — ohne Zuordnung geht keine Bestellung an Gelato.</p>
  <?php $auto = WmBestellung::automatik(); ?>
  <p style="font-size:14px;margin:12px 0 6px"><strong>Automatik:</strong> <?= $auto ? '<span class="marke2 gut">an</span> Nach der Zahlung geht der Auftrag von selbst an die Druckerei der Bestellung (wenn angebunden); die Sendungsnummer kommt von dort, der Partner bekommt die Mail. Je Land gewinnt dann die günstigste <em>angebundene</em> Druckerei.' : '<span class="marke2">aus</span> Du gibst jeden Auftrag selbst frei.' ?></p>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"><?= Csrf::feld() ?><input type="hidden" name="zurueck" value="werbemittel">
      <input type="hidden" name="tat" value="<?= $auto ? 'wm_automatik_aus' : 'wm_automatik_an' ?>"><button class="knopf"><?= $auto ? 'Automatik ausschalten' : 'Automatik einschalten' ?></button></form>
    <?php if (Gelato::bereit() || Printful::bereit()): ?><form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"><?= Csrf::feld() ?><input type="hidden" name="zurueck" value="werbemittel">
      <input type="hidden" name="tat" value="wm_gelato_preise"><button class="knopf stumm">Preise der Druckereien jetzt holen</button></form><?php endif; ?>
    <?php foreach (['Gelato', 'Printful'] as $wmPk): if (!$wmPk::bereit()) { continue; } ?><form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"><?= Csrf::feld() ?><input type="hidden" name="zurueck" value="werbemittel">
      <input type="hidden" name="tat" value="wm_probe"><input type="hidden" name="anbieter" value="<?= $wmPk ?>"><button class="knopf stumm" title="Musterkarte als Entwurf — nichts wird gedruckt">Probe-Entwurf an <?= $wmPk ?></button></form><?php endforeach; ?>
  </div>
</div>

<div class="block" style="max-width:820px"><h2>Standardmarge</h2>
  <form method="post" action="<?= Fmt::h(url('')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="wm_standard"><input type="hidden" name="zurueck" value="werbemittel">
    <div class="reihe">
      <div class="feld"><label>Marge auf den Einkauf (%)</label><input name="marge_prozent" inputmode="numeric" value="<?= (int) $st['marge_prozent'] ?>"></div>
      <div class="feld"><label>Mindestens je Position (€)</label><input name="mindestmarge_eur" inputmode="decimal" value="<?= $eur((int) $st['mindestmarge_cent']) ?>"></div>
    </div>
    <div class="reihe">
      <div class="feld"><label>Zahlungskosten Stripe (%)</label><input name="zahlkosten_prozent" inputmode="decimal" value="<?= number_format($zk['zehntel'] / 10, 1, ',', '') ?>"></div>
      <div class="feld"><label>+ fest je Zahlung (€)</label><input name="zahlkosten_fix_eur" inputmode="decimal" value="<?= $eur((int) $zk['fix_cent']) ?>"></div>
    </div>
    <p style="color:var(--leise);font-size:12.5px;margin:0 0 6px">Preis = Einkauf (inkl. Versand und MwSt) + Marge — nie weniger als die Mindestmarge — und darauf die Stripe-Gebühr, damit dir die Marge ganz bleibt; aufgerundet auf 10 Cent. Bleibt nach allem weniger als <?= Werbemittel::euro(Werbemittel::MIN_GEWINN_CENT) ?> Gewinn, ist die Auflage gesperrt. Preise der Druckereien gelten <?= Werbemittel::FRISCH_TAGE ?> Tage.</p>
    <p id="wm-beispiel" style="font-size:13px;margin:0 0 10px"></p>
    <button class="knopf haupt">Speichern</button>
  </form>
</div>

<?php /* Phase 3: wie Partner bezahlen. Voreinstellung „Anfrage“ — Stripe erst auf ausdrücklichen Klick. */ ?>
<div class="block" style="max-width:820px"><h2>Zahlweg für Partner-Bestellungen</h2>
  <p style="font-size:14px;margin:0 0 10px">Gerade: <strong><?= ($zahlweg ?? 'anfrage') === 'stripe' ? 'Stripe — Partner zahlen beim Bestellen direkt' : 'Anfrage — Bestellung wird gespeichert, du klärst die Zahlung' ?></strong>
    <?php if (($zahlwegGewollt ?? 'anfrage') === 'stripe' && ($zahlweg ?? 'anfrage') !== 'stripe'): ?><br><span class="marke2 warnung">Stripe gewählt, aber kein Stripe-Schlüssel eingetragen — es bleibt bei „Anfrage“.</span><?php endif; ?></p>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
    <?= Csrf::feld() ?><input type="hidden" name="zurueck" value="werbemittel">
    <?php if (($zahlwegGewollt ?? 'anfrage') === 'stripe'): ?>
      <input type="hidden" name="tat" value="wm_zahlweg_anfrage"><button class="knopf">Zurück auf „Anfrage“</button>
    <?php else: ?>
      <input type="hidden" name="tat" value="wm_zahlweg_stripe"><button class="knopf">Stripe einschalten</button>
    <?php endif; ?>
  </form>
</div>

<?php foreach ($kats as $kid => $k): if (empty($jeKat[$kid])) { continue; } ?>
  <h2 style="margin:22px 0 8px"><?= Fmt::h($k['name_de'] ?: $k['name_it']) ?></h2>
  <?php foreach ($jeKat[$kid] as $p): $pid = (int) $p['id']; ?>
    <div class="block" id="wm-<?= $pid ?>">
      <h2><?= Fmt::h($p['name_de'] ?: $p['name_it']) ?>
        <span class="mehr">
          <span class="marke2"><?= Fmt::h((string) $p['nummer']) ?></span>
          <?php if ($p['format']): ?><span class="marke2"><?= Fmt::h($p['format']) ?> + <?= $mm((int) $p['beschnitt_zmm']) ?> mm Beschnitt</span><?php endif; ?>
          <?php if (!$p['aktiv']): ?><span class="marke2">aus</span>
          <?php elseif ($p['bestellbar']): ?><span class="marke2 gut">für Partner sichtbar</span>
          <?php else: ?><span class="marke2 warnung">an, aber ohne Einkaufspreis — unsichtbar</span><?php endif; ?>
          <?php if ($p['vorlage'] === ''): ?><span class="marke2 schlecht">keine Vorlage</span><?php endif; ?>
        </span></h2>
      <?php /* Produktfotos der Druckerei je Gestaltung (04.10.2026): genau das, was der Partner beim Auswählen sieht. */
        if (isset(Printful::ARTEN[$p['vorlage']])):
          $wmVfDa = Werbemittel::vorlagenfotosDa((string) $p['vorlage']);
          $wmVfSoll = count(array_filter(Printful::vorlagenKombis(), static fn($k) => $k[0] === $p['vorlage'])); ?>
        <details class="wm-vf"<?= $wmVfDa ? ' open' : '' ?>><summary style="font-size:13px;color:var(--leise);cursor:pointer">Produktfotos von Printful (wie der Partner sie sieht): <?= count($wmVfDa) ?> von <?= $wmVfSoll ?> da<?= count($wmVfDa) < $wmVfSoll ? ' — der Rest kommt von selbst, 2 je Cron-Lauf' : '' ?></summary>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin:8px 0">
            <?php foreach ($wmVfDa as $wmVfK): [$wmVfS, $wmVfL] = explode('|', $wmVfK); $wmVfU = url('werbemittel/vorlagenfoto') . '?' . http_build_query(['v' => $p['vorlage'], 'st' => $wmVfS, 'l' => $wmVfL]); ?>
              <a href="<?= Fmt::h($wmVfU) ?>" target="_blank" rel="noopener" style="display:grid;gap:2px;text-align:center;font-size:11.5px;color:var(--leise)">
                <img src="<?= Fmt::h($wmVfU) ?>" alt="" width="110" height="110" loading="lazy" style="width:110px;height:110px;object-fit:cover;border-radius:8px;background:#f3f2f0">
                <?= Fmt::h(strtoupper($wmVfS) . ' · ' . strtoupper($wmVfL)) ?></a>
            <?php endforeach; ?>
          </div></details>
      <?php endif; ?>
      <p style="color:var(--leise);font-size:12.5px;margin:0 0 8px">Regel: <?= (int) $p['regel']['marge_prozent'] ?> %, mindestens <?= Fmt::h(Werbemittel::euro((int) $p['regel']['mindestmarge_cent'])) ?>
        <?= $p['marge_prozent'] === null && $p['mindestmarge_cent'] === null ? '(Standard)' : '(eigene Regel)' ?></p>

      <div class="tabellenrahmen"><table>
        <thead><tr><th>Variante (IT / DE / EN)</th><th class="num">Auflage</th><th class="num">Einkauf €</th><th class="num">Partnerpreis</th><th class="num" title="Was nach Einkauf (inkl. Versand, MwSt) und Stripe-Gebühr übrig bleibt">Gewinn nach Stripe</th><th title="Gelato productUid und Menge je Auflage">Gelato-Artikel · Menge</th><th>an</th><th></th></tr></thead>
        <tbody>
        <?php foreach (array_merge($p['varianten'], [null]) as $v): $fid = 'wmv-' . $pid . '-' . (int) ($v['id'] ?? 0); ?>
          <tr>
            <td><form id="<?= $fid ?>" method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0">
                <?= Csrf::feld() ?><input type="hidden" name="tat" value="wm_variante"><input type="hidden" name="zurueck" value="werbemittel#wm-<?= $pid ?>">
                <input type="hidden" name="produkt_id" value="<?= $pid ?>"><input type="hidden" name="id" value="<?= (int) ($v['id'] ?? 0) ?>"></form>
              <div style="display:flex;gap:4px">
                <input form="<?= $fid ?>" name="name_it" required placeholder="<?= $v ? '' : 'Neue Variante, z. B. 250 pezzi' ?>" value="<?= Fmt::h($v['name_it'] ?? '') ?>" style="min-width:110px">
                <input form="<?= $fid ?>" name="name_de" value="<?= Fmt::h($v['name_de'] ?? '') ?>" style="min-width:90px">
                <input form="<?= $fid ?>" name="name_en" value="<?= Fmt::h($v['name_en'] ?? '') ?>" style="min-width:90px"></div></td>
            <td class="num"><input form="<?= $fid ?>" name="auflage" type="number" min="1" value="<?= (int) ($v['auflage'] ?? 1) ?>" style="width:80px"></td>
            <?php /* Je Lieferland (04.10.2026): Einkauf = günstigste Druckerei für das Land. */
              $zelle = static function (?array $v, string $was) use ($eur): string {
                  if (!$v) { return ''; }
                  $o = [];
                  foreach ($v['laender'] as $l => $x) {
                      if ($x[$was] <= 0) { $o[] = Partner::flagge($l) . ' <span style="color:var(--leise)">—</span>'; continue; }
                      $t = Partner::flagge($l) . ' ' . Fmt::h($was === 'einkauf_cent' ? $eur((int) $x[$was]) : Werbemittel::euro((int) $x[$was]));
                      if ($was === 'einkauf_cent' && $x['anbieter']) { $t .= '<br><span style="font-size:11px;color:var(--leise)">' . Fmt::h((string) $x['anbieter']) . '</span>'; }
                      $o[] = $t;
                  }
                  return implode('<br>', $o);
              }; ?>
            <td class="num"><?php if ($v && $v['hat_angebote']): ?><?= $zelle($v, 'einkauf_cent') ?>
            <?php else: ?><input form="<?= $fid ?>" name="einkauf_eur" inputmode="decimal" value="<?= $v ? $eur((int) $v['einkauf_cent']) : '' ?>" style="width:90px" placeholder="0,00"><?php endif; ?></td>
            <td class="num" style="white-space:nowrap"><?php if ($v): foreach ($v['laender'] as $l => $x): ?>
              <div class="wm-live" data-ek="<?= (int) $x['einkauf_cent'] ?>" data-standard="<?= $p['marge_prozent'] === null && $p['mindestmarge_cent'] === null ? 1 : 0 ?>"><?= Partner::flagge($l) ?> <span class="wm-p"><?= $x['preis_cent'] > 0 ? Fmt::h(Werbemittel::euro((int) $x['preis_cent'])) : '—' ?></span></div>
            <?php endforeach; else: ?><span style="color:var(--leise)">—</span><?php endif; ?></td>
            <td class="num" style="white-space:nowrap"><?php if ($v): foreach ($v['laender'] as $l => $x): ?>
              <div class="wm-live-g" data-ek="<?= (int) $x['einkauf_cent'] ?>" data-standard="<?= $p['marge_prozent'] === null && $p['mindestmarge_cent'] === null ? 1 : 0 ?>"><?= Partner::flagge($l) ?>
                <?php if (!empty($x['veraltet'])): ?><span class="marke2 warnung" title="Preis älter als <?= Werbemittel::FRISCH_TAGE ?> Tage">Preis alt · gesperrt</span>
                <?php elseif ((int) $x['einkauf_cent'] <= 0): ?><span style="color:var(--leise)">—</span>
                <?php else: ?><span class="wm-g"<?= !empty($x['gesperrt']) ? ' style="color:var(--rot,#e5484d)"' : '' ?>><?= Fmt::h(Werbemittel::euro((int) $x['gewinn_cent'])) ?></span>
                  <span class="wm-h" style="font-size:11px;color:var(--leise)"><?= !empty($x['gesperrt']) ? 'gesperrt' : (!empty($x['mindest_greift']) ? 'Mindestmarge greift' : '') ?></span><?php endif; ?></div>
            <?php endforeach; endif; ?></td>
            <?php $ga = $v ? Gelato::artikel((int) $v['id']) : null; ?>
            <td><div style="display:flex;gap:4px"><input form="<?= $fid ?>" name="gelato_artikel" value="<?= Fmt::h((string) ($ga['artikel'] ?? '')) ?>" placeholder="productUid" style="min-width:150px">
              <input form="<?= $fid ?>" name="gelato_menge" type="number" min="1" value="<?= (int) ($ga['menge'] ?? ($v['auflage'] ?? 1)) ?>" style="width:80px"></div></td>
            <td><input form="<?= $fid ?>" type="checkbox" name="aktiv" style="width:auto" <?= !$v || $v['aktiv'] ? 'checked' : '' ?>></td>
            <td style="text-align:right"><button form="<?= $fid ?>" class="knopf <?= $v ? 'stumm' : '' ?>"><?= $v ? 'Speichern' : 'Hinzufügen' ?></button></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>

      <?php /* Preisvergleich (04.10.2026): je Auflage die geprüften Angebote; der Einkauf ist das günstigste. */ ?>
      <div style="margin-top:12px"><strong style="font-size:13.5px">Druckereien im Vergleich</strong>
        <span style="color:var(--leise);font-size:12.5px"> — Preis so, wie Vecom zahlt: inkl. Versand ins Lieferland und inkl. Mehrwertsteuer (ohne Partita IVA ist sie Kosten). Je Land gewinnt das günstigste. Gleiche Qualität vorausgesetzt — das Papier steht daneben.</span>
        <div class="tabellenrahmen" style="margin-top:6px"><table>
          <thead><tr><th>Auflage</th><th>Land</th><th>Druckerei</th><th class="num">Vecom zahlt</th><th class="num">netto</th><th>Papier</th><th>Lieferung</th><th>geprüft</th><th></th></tr></thead><tbody>
          <?php $keinAngebot = true; foreach ($p['varianten'] as $v): $vorLand = ''; foreach (Werbemittel::angebote((int) $v['id']) as $an): $keinAngebot = false;
            $ai = $an['land'] === $vorLand ? 1 : 0; $vorLand = $an['land']; /* je Land ist das erste das günstigste */ ?>
            <tr><td><?= Fmt::h($v['name_de'] ?: $v['name_it']) ?></td><td><?= Partner::flagge((string) $an['land']) ?> <?= Fmt::h((string) $an['land']) ?></td>
              <td><?= $an['link'] !== '' ? '<a href="' . Fmt::h($an['link']) . '" target="_blank" rel="noopener">' . Fmt::h($an['anbieter']) . '</a>' : Fmt::h($an['anbieter']) ?>
                <?= $ai === 0 ? ' <span class="marke2 gut">günstigster</span>' : '' ?></td>
              <td class="num"><?= Fmt::h(Werbemittel::euro((int) $an['preis_cent'])) ?></td>
              <td class="num"><?= $an['netto_cent'] !== null ? Fmt::h(Werbemittel::euro((int) $an['netto_cent'])) : '—' ?></td>
              <td><?= Fmt::h($an['papier']) ?></td><td style="font-size:12.5px"><?= Fmt::h($an['lieferung']) ?></td>
              <td><?= Fmt::h(Fmt::datum((string) $an['geprueft_am'])) ?><?= Werbemittel::veraltet($an) ? ' <span class="marke2 warnung">neu prüfen</span>' : '' ?></td>
              <td style="text-align:right"><form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0"><?= Csrf::feld() ?>
                <input type="hidden" name="tat" value="wm_angebot_weg"><input type="hidden" name="zurueck" value="werbemittel#wm-<?= $pid ?>">
                <input type="hidden" name="variante_id" value="<?= (int) $v['id'] ?>"><input type="hidden" name="anbieter" value="<?= Fmt::h($an['anbieter']) ?>"><input type="hidden" name="land" value="<?= Fmt::h((string) $an['land']) ?>">
                <button class="knopf stumm">Entfernen</button></form></td></tr>
          <?php endforeach; endforeach; ?>
          <?php if ($keinAngebot): ?><tr><td colspan="9"><div class="leer">Noch kein Angebot — dann gilt der Einkauf oben.</div></td></tr><?php endif; ?>
          </tbody></table></div>
        <?php foreach (['helloprint' => ['HelloPrint (variantKey)', 'productKey~sku', 'Menge'], 'printful' => ['Printful (variant_id; Menge = Zahl der Packs zu 50/100)', 'z. B. 18555', 'Packs']] as $dKey => [$dTitel, $dMuster, $dMenge]): ?>
        <details style="margin-top:6px"><summary style="cursor:pointer;color:var(--leise);font-size:13px">Artikelnummern bei <?= Fmt::h($dTitel) ?></summary>
          <div style="display:grid;gap:6px;margin-top:8px">
          <?php foreach ($p['varianten'] as $v): $hp = Druckerei::artikel((int) $v['id'], $dKey); ?>
            <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin:0;display:flex;gap:6px;align-items:center;flex-wrap:wrap"><?= Csrf::feld() ?>
              <input type="hidden" name="tat" value="wm_artikel"><input type="hidden" name="zurueck" value="werbemittel#wm-<?= $pid ?>">
              <input type="hidden" name="anbieter" value="<?= $dKey ?>"><input type="hidden" name="variante_id" value="<?= (int) $v['id'] ?>">
              <span style="min-width:90px"><?= Fmt::h($v['name_de'] ?: $v['name_it']) ?></span>
              <input name="artikel" value="<?= Fmt::h((string) ($hp['artikel'] ?? '')) ?>" placeholder="<?= Fmt::h($dMuster) ?>" style="min-width:260px">
              <input name="menge" type="number" min="1" value="<?= (int) ($hp['menge'] ?? ($dKey === 'printful' ? 1 : $v['auflage'])) ?>" style="width:90px" title="<?= Fmt::h($dMenge) ?>">
              <button class="knopf stumm">Speichern</button></form>
          <?php endforeach; ?></div></details>
        <?php endforeach; ?>
        <details style="margin-top:6px"><summary style="cursor:pointer;color:var(--leise);font-size:13px">Angebot eintragen oder aktualisieren</summary>
          <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:8px"><?= Csrf::feld() ?>
            <input type="hidden" name="tat" value="wm_angebot"><input type="hidden" name="zurueck" value="werbemittel#wm-<?= $pid ?>">
            <div class="reihe">
              <div class="feld"><label>Auflage</label><select name="variante_id"><?php foreach ($p['varianten'] as $v): ?><option value="<?= (int) $v['id'] ?>"><?= Fmt::h($v['name_de'] ?: $v['name_it']) ?></option><?php endforeach; ?></select></div>
              <div class="feld"><label>Lieferland</label><select name="land"><?php foreach (Werbemittel::LIEFERLAENDER as $lc => $ln): ?><option value="<?= $lc ?>"><?= Fmt::h($ln) ?></option><?php endforeach; ?></select></div>
              <div class="feld"><label>Druckerei</label><input name="anbieter" required placeholder="z. B. HelloPrint"></div>
              <div class="feld"><label>Vecom zahlt (€, inkl. Versand + IVA)</label><input name="preis_eur" inputmode="decimal" required></div>
              <div class="feld"><label>davon netto (€)</label><input name="netto_eur" inputmode="decimal"></div>
            </div>
            <div class="reihe">
              <div class="feld"><label>Papier / Qualität</label><input name="papier" placeholder="z. B. 400 g matt, 4/4"></div>
              <div class="feld"><label>Lieferung</label><input name="lieferung" placeholder="z. B. gratis, 5 Werktage"></div>
              <div class="feld"><label>Link (https://…)</label><input name="link" type="url"></div>
              <div class="feld"><label>geprüft am</label><input name="geprueft_am" type="date" value="<?= date('Y-m-d') ?>"></div>
            </div>
            <button class="knopf haupt">Speichern</button>
          </form></details>
      </div>

      <details style="margin-top:10px"><summary style="cursor:pointer;color:var(--leise);font-size:13px">Produkt bearbeiten</summary>
        <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:10px">
          <?= Csrf::feld() ?><input type="hidden" name="tat" value="wm_produkt"><input type="hidden" name="zurueck" value="werbemittel#wm-<?= $pid ?>">
          <input type="hidden" name="id" value="<?= $pid ?>">
          <?php $produktFelder($p); ?>
          <button class="knopf haupt">Produkt speichern</button>
        </form></details>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>

<?php /* Phase 2: was Partner freigegeben haben. Gedruckt wird nur, was hier steht. */ $freigaben = $freigaben ?? []; ?>
<div class="block"><h2>Freigegebene Druckdateien</h2>
  <p style="color:var(--leise);font-size:12.5px;margin:0 0 8px">Jede Datei hat der Partner selbst geprüft und freigegeben. „ersetzt“ heißt: Er hat danach eine neuere freigegeben.</p>
  <?php if (!$freigaben): ?><div class="leer">Noch keine Freigabe.</div>
  <?php else: ?><div class="tabellenrahmen"><table>
    <thead><tr><th>Freigegeben</th><th>Partner</th><th>Produkt</th><th>Wahl</th><th>Zustand</th><th></th></tr></thead><tbody>
    <?php foreach ($freigaben as $f): $fw = (array) json_decode((string) $f['wahl'], true); ?>
      <tr><td><?= Fmt::h(Fmt::datum((string) $f['freigegeben_am'])) ?></td>
        <td><?= Fmt::h($f['partner']) ?> · <?= Fmt::h($f['code']) ?></td>
        <td><?= Fmt::h($f['nummer']) ?> <?= Fmt::h($f['name_de'] ?: $f['name_it']) ?></td>
        <td><?= Fmt::h(strtoupper((string) ($fw['stil'] ?? '')) . ' · ' . strtoupper((string) ($fw['sprache'] ?? '')) . ' · ' . ($fw['kontakt'] ?? '')) ?></td>
        <td><span class="marke2 <?= $f['status'] === 'freigegeben' ? 'gut' : '' ?>"><?= Fmt::h($f['status']) ?></span></td>
        <td style="text-align:right"><a class="knopf stumm" href="<?= Fmt::h(url('werbemittel/pdf/' . (int) $f['id'])) ?>" target="_blank" rel="noopener">PDF</a></td></tr>
    <?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</div>

<div class="block" style="max-width:820px"><details><summary style="cursor:pointer"><strong>Neues Produkt anlegen</strong></summary>
  <form method="post" action="<?= Fmt::h(url('')) ?>" style="margin-top:10px">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="wm_produkt"><input type="hidden" name="zurueck" value="werbemittel"><input type="hidden" name="id" value="0">
    <?php $produktFelder(null); ?>
    <p style="color:var(--leise);font-size:12.5px;margin:0 0 10px">Die VEC-Nummer wird beim Anlegen vergeben. Danach Varianten mit Einkaufspreis eintragen.</p>
    <button class="knopf haupt">Anlegen</button>
  </form></details></div>

<script>
/* 04.10.2026 (Uwe: „egal wie die Marge eingestellt ist, wird immer derselbe Betrag angezeigt“):
   Beim Tippen rechnet die Tabelle mit — gleiche Formel wie Werbemittel::preis()
   — und sagt, wann die Mindestmarge greift. Gespeichert wird erst mit „Speichern“. */
(function () {
  var f = document.querySelector('input[name="marge_prozent"]'); if (!f) { return; }
  var form = f.form, zahl = function (n) { var x = form.querySelector('[name="' + n + '"]'); return x ? parseFloat(String(x.value).replace(',', '.')) || 0 : 0; };
  var euro = function (c) { return (c / 100).toFixed(2).replace('.', ',') + '\u00a0€'; };
  var preis = function (ek, pz, min, zt, fix) {
    if (ek <= 0) { return 0; }
    var roh = Math.max(Math.ceil(ek * (100 + pz) / 100), ek + min);
    var p = Math.ceil((roh + fix) * 1000 / (1000 - zt)); p = Math.ceil(p / 10) * 10;
    while (p - Math.ceil(p * zt / 1000) - fix < roh) { p += 10; }
    return p;
  };
  var rechne = function () {
    var pz = Math.round(zahl('marge_prozent')), min = Math.round(zahl('mindestmarge_eur') * 100), zt = Math.round(zahl('zahlkosten_prozent') * 10), fix = Math.round(zahl('zahlkosten_fix_eur') * 100);
    document.querySelectorAll('.wm-live[data-standard="1"]').forEach(function (d) {
      var ek = +d.dataset.ek, p = preis(ek, pz, min, zt, fix), s = d.querySelector('.wm-p'); if (s && ek > 0) { s.textContent = euro(p); }
    });
    document.querySelectorAll('.wm-live-g[data-standard="1"]').forEach(function (d) {
      var ek = +d.dataset.ek; if (ek <= 0) { return; }
      var p = preis(ek, pz, min, zt, fix), g = p - Math.ceil(p * zt / 1000) - fix - ek, s = d.querySelector('.wm-g'), h = d.querySelector('.wm-h');
      if (s) { s.textContent = euro(g); s.style.color = g < 100 ? '#e5484d' : ''; }
      if (h) { h.textContent = g < 100 ? 'gesperrt' : (ek * pz < min * 100 ? 'Mindestmarge greift' : ''); }
    });
    var b = document.getElementById('wm-beispiel');
    if (b) { var ek = 2353, p = preis(ek, pz, min, zt, fix), g = p - Math.ceil(p * zt / 1000) - fix - ek;
      b.textContent = 'Beispiel: Einkauf 23,53 € → Partnerpreis ' + euro(p) + ', davon Stripe ' + euro(Math.ceil(p * zt / 1000) + fix) + ', dein Gewinn ' + euro(g)
        + (ek * pz < min * 100 ? ' — die Mindestmarge greift (der Prozentwert wäre nur ' + euro(Math.ceil(ek * pz / 100)) + ').' : '.'); }
  };
  form.addEventListener('input', rechne); rechne();
})();
</script>
