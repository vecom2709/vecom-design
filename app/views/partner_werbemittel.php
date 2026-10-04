<?php
/* ==========================================================================
   Marketing Center im Partnerbereich (03.10.2026, Phase 1).
   Eingebunden aus partner.php — und aus der Verwaltung („Als Partner
   ansehen“), dann mit $wmNurLesen = true und ohne Download-Links.

   Gesetzt sein müssen: $p, $sprache, $h, $wmKatalog (Werbemittel::katalog),
   $wmNurLesen, und — nur im Partnerbereich — $selbst.

   Der Katalog trägt nur Name, Format und Endpreis. Einkauf und Marge
   kommen hier gar nicht erst an (Werbemittel::katalog).
   ========================================================================== */
$W = static fn(string $k): string => Texte::h(Texte::PARTNER_WERBEMITTEL[$k] ?? [], $sprache);
$wmLink = PartnerWerbung::link($p, 'qr');
$wmName = PartnerKarten::name($p);
$wmDl = static fn(string $art): string => $wmNurLesen ? '#' : $selbst(['wmqr' => $art]);
require_once dirname(__DIR__) . '/src/WmBestellung.php';
$wmZahlweg = WmBestellung::zahlweg();
$wmAdressen = !$wmNurLesen && (int) ($p['id'] ?? 0) > 0 ? WmBestellung::adressen((int) $p['id']) : [];
$wmBestellungen = !$wmNurLesen && (int) ($p['id'] ?? 0) > 0 ? WmBestellung::fuerPartner((int) $p['id']) : [];
/* Nachbestellen (03.10.2026): ?wmnochmal=ID füllt das Formular mit Auflage und
   Adresse der alten Bestellung vor. Bestellt wird erst mit Haken und Klick —
   zum Preis von heute, nicht zu dem von damals. */
$wmNoch = null; $wmNochVar = 0; $wmNochAdr = 0; $wmNochNeu = [];
if (!$wmNurLesen && isset($_GET['wmnochmal'])) {
    foreach ($wmBestellungen as $wmX) { if ((int) $wmX['id'] === (int) $_GET['wmnochmal']) { $wmNoch = $wmX; break; } }
    if ($wmNoch) {
        $wmNochVar = (int) Db::wert('SELECT variante_id FROM wm_positionen WHERE bestellung_id = ? ORDER BY id LIMIT 1', [(int) $wmNoch['id']], 0);
        foreach ($wmAdressen as $wmX) {
            $gleich = true;
            foreach (['name', 'firma', 'strasse', 'plz', 'ort', 'land', 'telefon'] as $wmF) { if ((string) ($wmX[$wmF] ?? '') !== (string) ($wmNoch['adresse'][$wmF] ?? '')) { $gleich = false; break; } }
            if ($gleich) { $wmNochAdr = (int) $wmX['id']; break; }
        }
        if ($wmNochAdr === 0) { $wmNochNeu = $wmNoch['adresse']; }
    }
}
$wmLaender = Werbemittel::LIEFERLAENDER;   // Italien und Deutschland (04.10.2026)
/* Marketingcenter Schritt 2 (04.10.2026): Bereiche statt Kategorien, Favoriten, Designs, Erfolge. */
require_once dirname(__DIR__) . '/src/Marketingcenter.php';
require_once dirname(__DIR__) . '/src/Designlinie.php';
$mcB = Marketingcenter::nachBereich($wmKatalog);
/* Schritt 3 (04.10.2026): Designlinien je Produkt — welche Linien es mit echten Vorlagen gibt. */
$mcLinienJe = []; $mcLinienDa = [];
foreach ($mcB as $mcL) { foreach ($mcL as $mcPr) {
    if (!Werbemittel::gestaltbar((string) $mcPr['vorlage'])) { continue; }
    $mcLinienJe[(int) $mcPr['id']] = Designlinie::gruppiert((string) $mcPr['vorlage'], Designlinie::stileDa((string) $mcPr['vorlage']));
    foreach (array_keys($mcLinienJe[(int) $mcPr['id']]) as $mcLi) { $mcLinienDa[$mcLi] = true; }
} }
$mcProdukte = [];
foreach ($mcB as $mcX => $mcL) { foreach ($mcL as $mcPr) { $mcProdukte[(int) $mcPr['id']] = $mcPr + ['mc_bereich' => $mcX]; } }
$mcEigen = !$wmNurLesen && (int) ($p['id'] ?? 0) > 0;
$mcFav = $mcEigen ? Marketingcenter::favoriten((int) $p['id']) : [];
$mcDesigns = $mcEigen ? Marketingcenter::designs((int) $p['id'], $sprache) : [];
$mcErfolge = Marketingcenter::erfolge($mcDesigns);
$mcKit = ['werbung', 'beitraege', 'medien', 'branchen', 'gutschein', 'seite'];
$mcT = static fn(string $k, array $r = []): string => strtr(Texte::h(Texte::MARKETINGCENTER[$k] ?? [], $sprache), $r);
$mcZahl = ['uebersicht' => $mcT(count($mcProdukte) === 1 ? 'n_produkt' : 'n_produkte', ['{n}' => (string) count($mcProdukte)])];
$mcDa = ['uebersicht' => true];
$mcZiel = ['uebersicht' => 'mc-start'];
foreach (Marketingcenter::PRODUKT_BEREICHE as $mcX) {
    $mcN = count($mcB[$mcX]);
    $mcZahl[$mcX] = $mcN === 0 ? $mcT('bald') : $mcT($mcN === 1 ? 'n_produkt' : 'n_produkte', ['{n}' => (string) $mcN]);
    $mcDa[$mcX] = $mcN > 0; $mcZiel[$mcX] = 'mc-' . $mcX;
}
$mcZahl['digital'] = $mcT('n_werkzeuge', ['{n}' => (string) (count($mcKit) + ($mcEigen ? 1 : 0))]); $mcDa['digital'] = true; $mcZiel['digital'] = 'werbemittel-kit';   // +1: digitale Visitenkarte
if ($mcEigen) {
    $mcZahl['designs'] = (string) count($mcDesigns); $mcDa['designs'] = (bool) $mcDesigns; $mcZiel['designs'] = 'mc-designs';
    $mcZahl['bestellungen'] = (string) count($wmBestellungen); $mcDa['bestellungen'] = (bool) $wmBestellungen; $mcZiel['bestellungen'] = $wmBestellungen ? 'wm-bestellungen' : 'mc-bestellungen';
    $mcFavN = count(array_filter($mcFav, static fn($i) => isset($mcProdukte[$i])));
    $mcZahl['favoriten'] = (string) $mcFavN; $mcDa['favoriten'] = $mcFavN > 0; $mcZiel['favoriten'] = 'mc-favoriten';
    $mcZahl['erfolge'] = $mcT('n_scans', ['{n}' => (string) $mcErfolge['summe']['scans']]); $mcDa['erfolge'] = $mcErfolge['summe']['scans'] > 0; $mcZiel['erfolge'] = 'mc-erfolge';
}
?>
<style>
  .wm-kopf{display:grid;grid-template-columns:auto 1fr;gap:18px;align-items:center}
  .wm-qr{background:#fff;border-radius:12px;padding:8px;line-height:0;width:max-content}
  .wm-qr svg{width:148px;height:148px;display:block}
  .wm-daten{display:grid;grid-template-columns:auto 1fr;gap:6px 14px;margin:0;font-size:14.5px}
  .wm-daten dt{color:var(--leise)}
  .wm-daten dd{margin:0;color:var(--text);min-width:0;overflow-wrap:anywhere}
  .wm-daten code{font-size:13.5px;color:var(--dim)}
  .wm-knoepfe{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
  .wm-kat{margin:18px 0 8px;font-size:13px;letter-spacing:.08em;text-transform:uppercase;color:#f1d38b}
  .wm-produkt{border:1px solid var(--linie);border-radius:14px;padding:14px;display:grid;gap:12px;margin-bottom:12px}
  .wm-produkt img{width:100%;height:auto;border-radius:8px;display:block;background:#15130f}
  .wm-produkt h3{margin:0;font-size:17px}
  .wm-produkt .wm-text{margin:4px 0 0;color:var(--dim);font-size:14.5px;line-height:1.5}
  .wm-meta{font-size:12.5px;color:var(--leise);margin-top:6px}
  .wm-varianten{width:100%;border-collapse:collapse;font-size:14.5px}
  .wm-varianten td{padding:8px 0;border-top:1px solid var(--linie)}
  .wm-varianten td:last-child{text-align:right;font-weight:600;white-space:nowrap;color:var(--text)}
  .wm-bald{display:flex;gap:10px;align-items:flex-start;font-size:13.5px;color:var(--dim);line-height:1.5}
  .wm-bald b{display:block;color:var(--text)}
  .wm-kit{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
  .wm-meldung{margin:0;padding:10px 12px;border-radius:10px;border:1px solid var(--linie2);font-size:14px;color:var(--text)}
  .wm-meldung.gut{border-color:rgba(120,200,140,.5);background:rgba(120,200,140,.08)}
  .wm-frei,.wm-entwurf{display:grid;gap:6px;padding:12px;border-radius:12px;font-size:14px}
  .wm-frei{border:1px solid rgba(120,200,140,.45);background:rgba(120,200,140,.06)}
  .wm-frei b{color:#9fe0b0}
  .wm-entwurf{border:1px solid rgba(241,211,139,.55);background:rgba(241,211,139,.06)}
  .wm-entwurf .knopf{justify-self:start}
  .wm-pf{display:flex;gap:8px;flex-wrap:wrap}.wm-pf img{display:block;width:180px;height:auto;border-radius:6px;border:1px solid rgba(255,255,255,.18)}
  .wm-haken{display:flex;gap:10px;align-items:flex-start;font-size:13.5px;line-height:1.5;color:var(--dim);cursor:pointer}
  .wm-haken input{width:20px;height:20px;flex:none;margin-top:1px}
  .wm-gestalten summary{cursor:pointer;color:#f1d38b;font-size:14.5px;padding:4px 0}
  .wm-gestalten form{display:grid;gap:10px;margin-top:8px}
  .wm-gestalten fieldset{border:0;padding:0;margin:0;display:flex;flex-wrap:wrap;gap:6px}
  .wm-gestalten legend{font-size:12.5px;color:var(--leise);margin-bottom:4px;width:100%}
  .wm-gestalten label{display:inline-flex;gap:6px;align-items:center;min-height:38px;padding:6px 12px;border:1px solid var(--linie2);border-radius:999px;font-size:13.5px;cursor:pointer}
  .wm-gestalten label:has(input:checked){border-color:rgba(241,211,139,.7);background:rgba(241,211,139,.09);color:var(--text)}
  .wm-gestalten button{justify-self:start}
  .wm-landpreis{display:inline-block;margin-left:10px;white-space:nowrap}
  .wm-landpreis.wm-anderes{font-weight:400;color:var(--leise);font-size:13px}
  .wm-bestellen{border-top:1px solid var(--linie);padding-top:12px;display:grid;gap:10px}
  .wm-bestellen h4{margin:0;font-size:15px}
  .wm-bfeld{border:0;padding:0;margin:0;display:grid;gap:10px;min-width:0}
  .wm-wahl{display:grid;gap:6px}
  .wm-wahl label{display:flex;gap:10px;align-items:center;padding:10px 12px;border:1px solid var(--linie2);border-radius:12px;cursor:pointer;font-size:14.5px}
  .wm-wahl label span{flex:1}
  .wm-wahl label:has(input:checked),.wm-adr:has(input:checked){border-color:rgba(241,211,139,.7);background:rgba(241,211,139,.07)}
  .wm-unter{margin:4px 0 0;font-size:12.5px;color:var(--leise)}
  .wm-adr{display:flex;gap:10px;align-items:flex-start;padding:9px 12px;border:1px solid var(--linie2);border-radius:12px;font-size:13.5px;cursor:pointer;color:var(--dim)}
  .wm-neu{display:grid;grid-template-columns:1fr 1fr;gap:8px}
  .wm-neu label{display:grid;gap:4px;font-size:12.5px;color:var(--leise);min-width:0}
  .wm-neu input,.wm-neu select{width:100%;box-sizing:border-box;min-height:40px;font:inherit;font-size:15px}
  .wm-neu .wm-breit{grid-column:1 / -1}
  .wm-bfeld:has(input[name=adresse][value="neu"]:not(:checked)) .wm-neu[data-nur-neu]{display:none}
  .wm-best{border:1px solid var(--linie);border-radius:12px;padding:12px;display:grid;gap:6px;margin-bottom:10px}
  .wm-best-kopf,.wm-best-fuss{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;font-size:14px}
  .wm-status{font-size:12.5px;padding:3px 10px;border-radius:999px;border:1px solid var(--linie2);color:var(--dim)}
  .wm-s-bezahlt,.wm-s-beim_drucker{border-color:rgba(241,211,139,.6);color:#f1d38b}
  .wm-s-versendet{border-color:rgba(120,200,140,.55);color:#9fe0b0}
  .wm-s-storniert{opacity:.7}
  @media (max-width:520px){ .wm-neu{grid-template-columns:1fr} }
  @media (max-width:520px){ .wm-kopf{grid-template-columns:1fr} .wm-qr{margin:0 auto} }
  /* Produkt in drei Schritten (04.10.2026) */
  .mc-produkt{gap:14px;padding:16px;background:linear-gradient(170deg,#181512,#100f0d);border-color:rgba(227,194,122,.16)}
  .mc-produktkopf{display:flex;gap:12px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap}
  .mc-produktkopf h3{font-size:19px}
  .mc-ab{color:#f1d38b;font-weight:600}
  .mc-foto{display:grid;gap:6px}
  .mc-foto img{width:100%;max-width:520px;height:auto;border-radius:10px;border:1px solid var(--linie2)}
  .mc-dvk{display:grid;gap:14px}
  .mc-dvk > img{width:100%;max-width:380px;height:auto;justify-self:center;border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,.45)}
  .mc-dvk-kacheln{grid-template-columns:repeat(auto-fill,minmax(120px,1fr))!important;margin-bottom:10px}
  @container (min-width:640px){ .mc-dvk{grid-template-columns:minmax(0,4fr) minmax(0,6fr);align-items:start} }
  #mc-dvk{container-type:inline-size}
  .mc-fakten{display:grid;grid-template-columns:auto 1fr;gap:4px 12px;margin:8px 0 0;font-size:13.5px}
  .mc-fakten dt{color:var(--leise)}
  .mc-fakten dd{margin:0;color:var(--text)}
  .mc-raster{display:grid;gap:16px}
  /* Die Vorschau bleibt beim Scrollen durch die Schritte oben stehen — man sieht sofort, was ein Tipp ändert. */
  .mc-produkt{container-type:inline-size}
  .mc-vorschau{margin:0 -6px;padding:6px 6px 6px;display:grid;gap:4px;align-content:start;position:sticky;top:8px;z-index:3;
    background:#100f0d;box-shadow:0 14px 14px -8px #100f0d;border-radius:12px}
  .mc-vorschau img{width:100%;height:auto;max-height:38vh;object-fit:contain;border-radius:10px;background:#15130f;box-shadow:0 10px 30px rgba(0,0,0,.45)}
  .mc-vorschau figcaption{font-size:12px;color:var(--leise);text-align:center}
  .mc-schritte{display:grid;gap:12px;min-width:0}
  .mc-schritt{border:1px solid var(--linie);border-radius:14px;padding:14px;display:grid;gap:8px;background:rgba(255,255,255,.015)}
  .mc-schritt h4{display:flex;gap:10px;align-items:center;margin:0;font-size:16px;color:var(--text)}
  .mc-schritt h4 span{display:inline-grid;place-items:center;width:28px;height:28px;border-radius:50%;flex:none;font-size:14px;font-weight:800;color:#1a140b;background:linear-gradient(135deg,#f3dfa6,#d9b468 55%,#b8913f)}
  .mc-schritt.wm-bestellen{border-top:1px solid var(--linie);padding-top:14px}
  .mc-motive{border:0;padding:0;margin:0 0 6px;display:grid!important;gap:8px;min-width:0}
  .mc-motive legend{font-size:13px;color:var(--text);font-weight:600;margin-bottom:6px;padding:0}
  .mc-gruppen{display:flex;gap:6px;overflow-x:auto;padding-bottom:4px;scrollbar-width:thin}
  .mc-gruppen button{flex:none;min-height:34px;padding:4px 12px;border-radius:999px;border:1px solid var(--linie2);background:transparent;color:var(--dim);font:inherit;font-size:13px;cursor:pointer;white-space:nowrap}
  .mc-gruppen button[aria-pressed="true"]{border-color:rgba(227,194,122,.8);color:var(--text);background:rgba(227,194,122,.1)}
  .mc-kacheln{display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px}
  .mc-branchen{max-height:430px;overflow-y:auto;padding:2px 4px 2px 2px;scrollbar-width:thin}
  .wm-gestalten label.mc-kachel{display:grid;gap:6px;align-content:start;min-height:0;padding:6px;border-radius:12px;border:1px solid var(--linie2);font-size:12px;line-height:1.3;color:var(--dim);position:relative;background:#141210}
  .wm-gestalten label.mc-kachel input{position:absolute;opacity:0;pointer-events:none}
  .wm-gestalten label.mc-kachel img{width:100%;height:auto;aspect-ratio:260/368;object-fit:cover;border-radius:7px;display:block;background:#1d1a15}
  .wm-gestalten label.mc-kachel.mc-quer img{aspect-ratio:260/168}
  .wm-gestalten label.mc-kachel:has(input:checked){border-color:#e3c27a;background:rgba(227,194,122,.1);color:var(--text);box-shadow:0 0 0 1px rgba(227,194,122,.5)}
  .wm-gestalten label.mc-kachel:has(input:checked)::after{content:"✓";position:absolute;top:10px;right:10px;width:22px;height:22px;border-radius:50%;display:grid;place-items:center;background:#e3c27a;color:#1a140b;font-weight:800;font-size:13px}
  .wm-gestalten label.mc-kachel:has(input:focus-visible){outline:2px solid #e3c27a;outline-offset:2px}
  .mc-kachel[hidden]{display:none!important}
  .mc-stillinie{display:grid!important;gap:6px}
  .mc-kacheln:has(.mc-quer){grid-template-columns:repeat(auto-fill,minmax(150px,1fr))}
  .mc-kachel .mc-linienname{display:block;min-width:0;margin-top:2px;font-size:10px}
  .mc-zeile2{display:grid;gap:8px}
  .mc-titel[hidden]{display:none}
  .mc-qr-ok{font-size:13.5px;color:#9fe0b0;line-height:1.4}
  .mc-qr-fehler{font-size:14px;color:#ffb4a8;border:1px solid rgba(255,140,120,.45);border-radius:10px;padding:10px 12px;line-height:1.4}
  .mc-titelliste{display:grid;gap:6px}
  .mc-titelliste label{display:flex;gap:10px;align-items:center;padding:10px 12px;border:1px solid var(--linie);border-radius:10px;cursor:pointer;font-size:14.5px;line-height:1.3}
  .mc-titelliste label:has(input:checked){border-color:rgba(227,194,122,.7);background:rgba(227,194,122,.07)}
  .mc-titelliste b{color:#e3c27a;font-weight:600}
  .mc-titelliste input{flex:none}
  .mc-gesperrt{margin:6px 0 0;padding:10px 12px;border-radius:10px;border:1px dashed rgba(227,194,122,.35)}
  @media (min-width:700px){ .mc-vorschau{top:70px} }   /* oben steht dort die Reiterleiste */
  /* Nur wenn die Spalte wirklich breit ist: Vorschau links, Schritte rechts. */
  @container (min-width:820px){
    .mc-raster{grid-template-columns:minmax(0,5fr) minmax(0,6fr);gap:22px;align-items:start}
    .mc-vorschau{background:none;margin:0;padding:0}
    .mc-vorschau img{max-height:none}
  }
  @media (max-width:520px){ .mc-kacheln{grid-template-columns:repeat(3,minmax(0,1fr))} .mc-kacheln:has(.mc-quer){grid-template-columns:repeat(2,minmax(0,1fr))} .mc-produkt{padding:12px} }
</style>

<?php require __DIR__ . '/partner_mc_start.php'; ?>

<div class="block pt" id="werbemittel" data-reiter="werbemittel" data-mc-teil="uebersicht digital">
  <h2>Marketing Center</h2>
  <p class="lead"><?= $h($W('lead')) ?></p>

  <h3 style="font-size:15px;margin:16px 0 10px"><?= $h($W('daten')) ?></h3>
  <div class="wm-kopf">
    <div class="wm-qr"><?= QrBild::svg($wmLink, 148, 2) ?></div>
    <div>
      <dl class="wm-daten">
        <dt><?= $h($W('name')) ?></dt><dd><?= $h($wmName) ?></dd>
        <dt><?= $h($W('id')) ?></dt><dd><b><?= $h((string) $p['code']) ?></b></dd>
        <dt><?= $h($W('link')) ?></dt><dd><code><?= $h((string) preg_replace('~^https?://~', '', $wmLink)) ?></code></dd>
      </dl>
      <p class="wm-meta"><?= $h($W('qr_satz')) ?></p>
      <?php if (!$wmNurLesen): ?>
        <div class="wm-knoepfe">
          <a class="knopf" href="<?= $h($wmDl('svg')) ?>" download><?= $h($W('qr_svg')) ?></a>
          <a class="knopf" href="<?= $h($wmDl('png')) ?>" download><?= $h($W('qr_png')) ?></a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="block pt" id="werbemittel-katalog" data-reiter="werbemittel" data-mc-teil="uebersicht <?= implode(' ', Marketingcenter::PRODUKT_BEREICHE) ?>">
  <h2><?= $h($W('katalog')) ?></h2>
  <?php foreach (Marketingcenter::PRODUKT_BEREICHE as $mcX): $mcL = $mcB[$mcX]; ?>
  <section class="mc-bereich" id="mc-<?= $mcX ?>" data-mc-teil="<?= $mcX . ($mcL ? ' uebersicht' : '') ?>">
    <h3><svg viewBox="0 0 24 24" aria-hidden="true"><?= $mcIcon[$mcX] ?></svg><?= $h(Texte::h(Texte::MARKETINGCENTER['b'][$mcX], $sprache)) ?></h3>
    <?php if (!$mcL): /* Nichts erfinden: ohne freigeschaltetes Produkt nur ein ehrliches „in Vorbereitung“. */ ?>
      <div class="mc-bald"><b><?= $h($mcT('bald')) ?></b><span><?= $h($mcT('bald_satz')) ?></span>
        <?php if (!$wmNurLesen): ?><a class="knopf" style="justify-self:start" href="#nachrichten"><?= $h($mcT('bald_knopf')) ?></a><?php endif; ?></div>
    <?php endif; ?>
    <?php foreach ($mcL as $wmP): ?>
      <?php require __DIR__ . '/partner_mc_produkt.php'; /* drei Schritte, 04.10.2026 */ ?>
    <?php endforeach; ?>
  </section>
  <?php endforeach; ?>
</div>

<?php /* Phase 3: Meine Bestellungen */ $wmM = (string) ($_GET['wm'] ?? ''); $wmDarfNoch = (bool) array_filter($wmKatalog, static fn($k) => (bool) array_filter($k['produkte'], static fn($x) => Werbemittel::gestaltbar((string) $x['vorlage']))); if (!$wmNurLesen && ($wmBestellungen || in_array($wmM, ['angefragt', 'danke', 'abgebrochen', 'stripe', 'storniert', 'storno_nicht'], true))): ?>
<div class="block pt" id="wm-bestellungen" data-reiter="werbemittel" data-mc-teil="uebersicht bestellungen">
  <h2><?= $h($W('meine')) ?></h2>
  <?php if (in_array($wmM, ['angefragt', 'danke', 'abgebrochen', 'stripe', 'storniert', 'storno_nicht'], true)): ?>
    <p class="wm-meldung<?= in_array($wmM, ['angefragt', 'danke', 'storniert'], true) ? ' gut' : '' ?>" role="status"><?= $h($W('m_' . $wmM)) ?></p>
  <?php endif; ?>
  <?php foreach ($wmBestellungen as $wmB): $wmPos = $wmB['positionen'][0] ?? null; ?>
    <div class="wm-best">
      <div class="wm-best-kopf"><b><?= $h($wmB['nummer']) ?></b><span class="wm-status wm-s-<?= $h($wmB['status']) ?>"><?= $h($W('s_' . $wmB['status'])) ?></span></div>
      <div class="wm-meta" style="margin:0"><?= $h(Fmt::datum((string) $wmB['created_at'])) ?><?php if ($wmPos): ?> · <?= $h($wmPos['name'] . ' · ' . $wmPos['variante']) ?><?php endif; ?></div>
      <div class="wm-best-fuss"><span><?= $h($W('summe')) ?> <b><?= $h(Werbemittel::euro((int) $wmB['summe_cent'])) ?></b></span>
        <?php if (in_array($wmB['status'], ['bezahlt', 'beim_drucker', 'versendet', 'storniert'], true) && $wmPos && $wmDarfNoch): ?>
          <a class="knopf" href="<?= $h($selbst(['wmnochmal' => (int) $wmB['id']])) ?>#wm-bestellen"><?= $h($W('nochmal')) ?></a>
        <?php endif; ?>
        <?php if ($wmB['status'] === 'versendet' && !empty($wmB['tracking'])): ?>
          <?php if (!empty($wmB['tracking_url'])): ?><a href="<?= $h((string) $wmB['tracking_url']) ?>" target="_blank" rel="noopener"><?= $h($W('sendung')) ?> · <?= $h((string) $wmB['tracking']) ?></a>
          <?php else: ?><span><?= $h($W('sendung')) ?>: <?= $h((string) $wmB['tracking']) ?></span><?php endif; ?>
        <?php endif; ?>
        <?php if (in_array($wmB['status'], ['angefragt', 'offen'], true) && $wmZahlweg === 'stripe'): ?>
          <form method="post" action="<?= $h($selbst()) ?>" style="margin:0"><input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf'] ?? '') ?>">
            <input type="hidden" name="tat" value="wm_bezahlen"><input type="hidden" name="bestellung" value="<?= (int) $wmB['id'] ?>">
            <button class="knopf haupt"><?= $h($W('jetzt_bezahlen')) ?></button></form>
        <?php endif; ?>
        <?php if (in_array($wmB['status'], ['angefragt', 'offen'], true) && empty($wmB['bezahlt_am'])): ?>
          <form method="post" action="<?= $h($selbst()) ?>" style="margin:0" class="wm-frage" data-frage="<?= $h($W('b_abbrechen_frage')) ?>"><input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf'] ?? '') ?>">
            <input type="hidden" name="tat" value="wm_abbrechen"><input type="hidden" name="bestellung" value="<?= (int) $wmB['id'] ?>">
            <button class="knopf"><?= $h($W('b_abbrechen')) ?></button></form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($mcEigen) { require __DIR__ . '/partner_mc_listen.php'; } ?>

<?php if ($mcEigen) { require_once dirname(__DIR__) . '/src/PartnerDaten.php'; require __DIR__ . '/partner_mc_digital.php'; } ?>

<div class="block pt" id="werbemittel-kit" data-reiter="werbemittel" data-mc-teil="uebersicht digital">
  <h2><?= $h($W('kit')) ?></h2>
  <p class="lead"><?= $h($W('kit_satz')) ?></p>
  <div class="wm-kit">
    <?php foreach ($mcKit as $wmZiel): /* Digital Marketing = dieses Kit, ergänzt um Beiträge und eigene Seite (04.10.2026) */
      $mcKt = isset(Texte::PARTNER_WERBEMITTEL['kit_' . $wmZiel]) ? $W('kit_' . $wmZiel) : Texte::h(Texte::MARKETINGCENTER['dig'][$wmZiel], $sprache); ?>
      <?php if ($wmNurLesen): /* In der Verwaltung gibt es die Ziele nicht. */ ?><span class="knopf stumm"><?= $h($mcKt) ?></span>
      <?php else: ?><a class="knopf" href="#<?= $wmZiel ?>"><?= $h($mcKt) ?></a><?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
<?php if (!$wmNurLesen): ?>
<script>
/* 04.10.2026 (Uwe: „bei der Visitenkarte wird immer dieselbe in der Vorschau angezeigt“):
   Die Vorschau folgt jetzt der Auswahl — Stil, Sprache und Kontakt — sofort,
   nicht erst nach „Druckdatei erstellen“. Und: Verwerfen/Abbrechen fragen vorher. */
(function () {
  document.querySelectorAll('form.wm-gestalter').forEach(function (f) {
    var bild = document.getElementById(f.dataset.bild);
    if (!bild || !bild.dataset.muster) { return; }
    var hinweis = f.querySelector('.wm-wahl-hinweis');
    var wert = function (n) { var r = f.querySelector('input[name="' + n + '"]:checked') || f.querySelector('select[name="' + n + '"]'); return r ? r.value : ''; };
    f.addEventListener('change', function () {
      bild.src = bild.dataset.muster.replace('_S_', encodeURIComponent(wert('stil')))
        .replace('_L_', encodeURIComponent(wert('sprache'))).replace('_K_', encodeURIComponent(wert('kontakt')))
        .replace('_T_', encodeURIComponent(wert('titel')));
      if (hinweis) { hinweis.hidden = false; }
      titelZeigen();
    });
    /* Überschrift (Schritt 4) nur, wenn die gewählte Gestaltung eine hat — Branchenmotive bringen ihre eigene mit. */
    var tf = f.querySelector('.mc-titel');
    function titelZeigen() { if (tf) { tf.hidden = (' ' + tf.dataset.titelStile + ' ').indexOf(' ' + wert('stil') + ' ') < 0; } }
    titelZeigen();
  });
  /* Branchen-Gruppen (04.10.2026): ein Tipp zeigt nur die Motive dieser Gruppe; die gewählte Kachel bleibt im Blick. */
  document.querySelectorAll('.mc-motive .mc-gruppen').forEach(function (g) {
    var box = g.parentNode.querySelector('.mc-branchen');
    if (!box) { return; }
    function zeig(flg) {
      [].forEach.call(g.querySelectorAll('button[data-flg]'), function (b) { b.setAttribute('aria-pressed', b.dataset.flg === flg ? 'true' : 'false'); });
      [].forEach.call(box.querySelectorAll('.mc-kachel'), function (k) { k.hidden = flg !== '' && k.dataset.flg !== flg; });
    }
    g.addEventListener('click', function (e) { var b = e.target.closest('button[data-flg]'); if (b) { zeig(b.dataset.flg); box.scrollTop = 0; } });
    var an = g.querySelector('button[aria-pressed="true"]'); zeig(an ? an.dataset.flg : '');
    var c = box.querySelector('input:checked');
    if (c) { var k = c.closest('.mc-kachel'); box.scrollTop = Math.max(0, k.offsetTop - box.offsetTop - 8); }
  });
  document.querySelectorAll('form.wm-frage').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!window.confirm(f.dataset.frage || '?')) { e.preventDefault(); } });
  });
})();
</script>
<?php endif; ?>
