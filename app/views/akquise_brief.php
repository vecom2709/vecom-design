<?php
/** @var array $f @var ?array $audit @var array $befunde */
/* DER BRIEF (26.09.2026) — Druckblatt ohne Menue, A4.

   Warum Brief und nicht Mail: In Deutschland und Italien ist Werbung per
   E-Mail an Firmen ohne Einwilligung nicht erlaubt; per Post schon, solange
   kein Widerspruch vorliegt und der Brief sagt, wie man keine weitere Post
   bekommt. Ein Brief mit dem eigenen Bildschirmfoto faellt ausserdem auf —
   er liegt auf dem Tisch, statt im Spamordner.

   Seite 1: der freigegebene Text auf dem Briefbogen, unten der QR-Code zur
   persoenlichen Analyse-Seite. Seite 2 (Anlage): die Startseite heute und
   die drei Beobachtungen in der Sprache des Betriebs.

   Gedruckt wird nur ein FREIGEGEBENER Text. Ein Entwurf bekommt quer ueber
   die Seite „ENTWURF“ — damit kein ungelesener Text in einen Umschlag
   rutscht. */
require_once __DIR__ . '/../src/Firma.php';

$fid = (int) $f['id'];
$v = Db::one("SELECT * FROM akq_vorlagen WHERE firma_id = ? AND kanal = 'brief' AND status IN ('freigegeben','gesendet','entwurf')
              ORDER BY FIELD(status,'freigegeben','gesendet','entwurf'), id DESC LIMIT 1", [$fid]);
$analyse = Db::one('SELECT * FROM akq_analysen WHERE firma_id = ? AND aktiv = 1 ORDER BY id DESC LIMIT 1', [$fid]);
$qr = $analyse ? AkquiseAnalyse::adresse($analyse) : '';
$sprache = $v ? (string) $v['sprache'] : AkquiseText::spracheFuer($f);
$entwurf = !$v || $v['status'] === 'entwurf';
$abs = AkquiseText::absender();

$W = [
    'de' => ['monate' => ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
             'datum' => '%d. %s %d', 'anlage' => 'Anlage', 'heute' => 'Ihre Startseite heute', 'handy' => 'auf dem Smartphone', 'pc' => 'am Computer',
             'aufgenommen' => 'aufgenommen am', 'drei' => 'Was uns aufgefallen ist', 'qr' => 'Ihre persönliche Auswertung',
             'qr2' => 'Mit dem Handy scannen — ohne Anmeldung, nur für Sie.', 'land' => ['DE' => 'DEUTSCHLAND', 'IT' => 'ITALIEN']],
    'it' => ['monate' => ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'],
             'datum' => '%d %s %d', 'anlage' => 'Allegato', 'heute' => 'La vostra home page oggi', 'handy' => 'da smartphone', 'pc' => 'da computer',
             'aufgenommen' => 'rilevata il', 'drei' => 'Cosa abbiamo notato', 'qr' => 'La vostra analisi personale',
             'qr2' => 'Inquadrate il codice con lo smartphone: senza registrazione, solo per voi.', 'land' => ['DE' => 'GERMANIA', 'IT' => 'ITALIA']],
    'en' => ['monate' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
             'datum' => '%d %s %d', 'anlage' => 'Enclosure', 'heute' => 'Your home page today', 'handy' => 'on a smartphone', 'pc' => 'on a computer',
             'aufgenommen' => 'captured on', 'drei' => 'What we noticed', 'qr' => 'Your personal analysis',
             'qr2' => 'Scan with your phone — no sign-up, just for you.', 'land' => ['DE' => 'GERMANY', 'IT' => 'ITALY']],
][$sprache] ?? null;
$W ??= [];
$datum = static fn(string $wann) => sprintf($W['datum'], (int) date('j', strtotime($wann)), $W['monate'][(int) date('n', strtotime($wann)) - 1], (int) date('Y', strtotime($wann)));

/* Absender und Empfaenger. Die Laenderzeile nur bei Post ins Ausland --
   in Grossbuchstaben, wie es die Post will. */
$absLand = strtoupper(Firma::get('land', 'Italia')) === 'DEUTSCHLAND' ? 'DE' : 'IT';
$ruecksende = implode(' · ', array_filter([$abs['firma'], Firma::get('strasse'), trim(Firma::get('plz') . ' ' . Firma::get('ort', 'Aragona (AG)'))]));
$an = array_values(array_filter([
    (string) $f['name'],
    trim((string) $f['ansprechpartner']),
    (string) $f['adresse'],
    trim(($f['plz'] ?? '') . ' ' . ($f['stadt'] ?? '')),
    strtoupper((string) $f['land']) !== $absLand ? ($W['land'][strtoupper((string) $f['land'])] ?? '') : '',
], static fn($z) => trim((string) $z) !== ''));
$ortDatum = preg_replace('~\s*\(.*\)$~', '', $abs['ort']) . ', ' . $datum('now');

/* Die drei Beobachtungen fuer die Anlage: nur belegte, nur mit gepflegtem Satz. */
$drei = [];
foreach (AkquiseScore::topBefunde(array_values(array_filter($befunde, static fn($b) => $b['status'] === 'VERIFIED'))) as $b) {
    $s = AkquiseText::saetze($b, $f, $sprache);
    if ($s !== null) { $drei[] = $s[0]; }
    if (count($drei) === 3) { break; }
}
$h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="<?= $h($sprache) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Brief — <?= $h($f['name']) ?></title>
<style>
  @page { size: A4; margin: 0; }
  * { box-sizing: border-box; }
  html { background: #57534c; }
  body { margin: 0; font: 10pt/1.45 "Helvetica Neue", Helvetica, Arial, sans-serif; color: #1a1712; }
  .leiste { position: sticky; top: 0; z-index: 5; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
            padding: 10px 16px; background: #0a0908; color: #f7f3ea; font: 14px/1.4 system-ui, sans-serif; }
  .leiste a, .leiste button { font: inherit; color: #f7f3ea; background: #1f1c18; border: 1px solid rgba(224,206,156,.26);
            border-radius: 10px; padding: 8px 14px; text-decoration: none; cursor: pointer; }
  .leiste .haupt { background: linear-gradient(115deg,#b98a31,#f7e6ae 30%,#d6a849 60%,#c49438); color: #0a0908; border: 0; font-weight: 650; }
  .leiste .warn { color: #ffb37a; }
  .blatt { width: 210mm; min-height: 297mm; margin: 16px auto; background: #fff; position: relative; padding: 0 20mm 22mm 25mm;
           box-shadow: 0 10px 40px rgba(0,0,0,.35); page-break-after: always; overflow: hidden; }
  .blatt:last-child { page-break-after: auto; }
  .kopf { height: 45mm; display: flex; justify-content: space-between; align-items: flex-start; padding-top: 14mm; }
  .marke { display: flex; gap: 3.5mm; align-items: center; }
  .marke img { width: 13mm; height: auto; }
  .marke b { font-size: 15pt; letter-spacing: .32em; font-weight: 700; }
  .marke span { display: block; font-size: 7.5pt; letter-spacing: .38em; color: #8a6a2a; margin-top: .6mm; }
  .absender { text-align: right; font-size: 8pt; line-height: 1.45; color: #5d574c; }
  .goldlinie { position: absolute; left: 0; right: 0; top: 40mm; height: .6mm;
               background: linear-gradient(90deg,#b98a31,#f3dc98 30%,#c49438 60%,rgba(196,148,56,0)); }
  .fenster { height: 45mm; width: 85mm; margin-left: -5mm; padding: 5mm 5mm 0; }   /* DIN-Fenster links: 20 mm vom Rand */
  .ruecksende { font-size: 7pt; color: #6d665a; border-bottom: .2mm solid #b9b1a2; padding-bottom: .8mm; margin-bottom: 3mm; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .an { font-size: 10.5pt; line-height: 1.4; }
  .datum { text-align: right; margin: 4mm 0 8mm; }
  .betreff { font-weight: 700; margin: 0 0 6mm; }
  .text { white-space: pre-wrap; hyphens: auto; }
  .qr { display: flex; gap: 5mm; align-items: center; margin-top: 7mm; padding: 4mm; border: .3mm solid #d9cfb4; border-radius: 3mm; break-inside: avoid; }
  .qr svg, .qr img { width: 30mm; height: 30mm; display: block; flex: none; }
  .qr b { display: block; font-size: 11pt; }
  .qr small { display: block; color: #5d574c; font-size: 8.5pt; word-break: break-all; margin-top: 1mm; }
  .fuss { position: absolute; left: 25mm; right: 20mm; bottom: 9mm; font-size: 7pt; color: #6d665a; border-top: .2mm solid #d9cfb4; padding-top: 2mm;
          display: flex; justify-content: space-between; gap: 6mm; }
  .anlage h2 { font-size: 15pt; margin: 0 0 1mm; }
  .anlage .unter { color: #6d665a; font-size: 9pt; margin: 0 0 7mm; }
  .bilder { display: flex; gap: 7mm; align-items: flex-start; }
  .bilder figure { margin: 0; }
  .bilder img { display: block; border: .3mm solid #cfc6b3; border-radius: 3mm; max-width: 100%; height: auto; }
  .bilder figcaption { font-size: 8pt; color: #6d665a; margin-top: 1.5mm; }
  .drei { margin: 9mm 0 0; padding: 0; list-style: none; counter-reset: n; }
  .drei li { counter-increment: n; display: flex; gap: 4mm; margin: 0 0 4mm; }
  .drei li::before { content: counter(n); flex: none; width: 7mm; height: 7mm; border-radius: 50%; display: grid; place-items: center;
                     background: #1a1712; color: #f3dc98; font-weight: 700; font-size: 9pt; }
  .stempel { position: absolute; inset: 0; display: grid; place-items: center; pointer-events: none; }
  .stempel span { transform: rotate(-28deg); font-size: 64pt; font-weight: 800; letter-spacing: .12em; color: rgba(200,40,40,.16); border: 2mm solid rgba(200,40,40,.16); padding: 3mm 10mm; }
  /* Druck: Der Text ist laenger als eine Seite. Ohne Seitenraender stuende
     die Fortsetzung auf Seite 2 am oberen Papierrand -- deshalb Raender ueber
     @page und der Briefkopf um genau diesen Rand nach oben geschoben, damit
     das Adressfeld weiter bei 45 mm sitzt (DIN-Fenster). */
  @media print {
    @page { size: A4; margin: 12mm 0 14mm 0; }
    html, body { background: #fff; }
    .leiste { display: none; }
    .blatt { margin: 0; box-shadow: none; min-height: 0; padding-top: 0; padding-bottom: 0; overflow: visible; }
    .kopf { height: 33mm; padding-top: 2mm; }
    .goldlinie { top: 28mm; }
    .fuss { position: static; margin-top: 8mm; }
    .stempel { position: fixed; }
  }
  @media screen and (max-width: 820px) { .blatt { transform-origin: top left; zoom: .5; } }
</style>
</head>
<body>
<div class="leiste">
  <a href="<?= $h(url('akquise/' . $fid)) ?>#kontakt">← Zurück</a>
  <?php if ($entwurf): ?>
    <span class="warn"><?= $v ? 'Noch nicht freigegeben — erst lesen und freigeben, dann drucken.' : 'Es gibt noch keinen Brief-Text für diesen Betrieb.' ?></span>
  <?php else: ?>
    <button class="haupt" type="button" onclick="window.print()">Drucken oder als PDF speichern</button>
    <span>Danach auf der Firmenseite „Als verschickt vermerken“.</span>
  <?php endif; ?>
  <?php if (!$qr && !$entwurf): ?><span class="warn">Analyse-Seite ist aus — der Brief hat dann keinen QR-Code.</span><?php endif; ?>
</div>

<?php if ($v): ?>
<section class="blatt">
  <div class="goldlinie"></div>
  <header class="kopf">
    <div class="marke"><img src="/assets/img/logo-mark.webp" alt="" width="120" height="94"><div><b>VECOM</b><span>DESIGN</span></div></div>
    <div class="absender"><?= $h($abs['inhaber']) ?><br><?= nl2br($h(implode("\n", array_filter([Firma::get('strasse'), trim(Firma::get('plz') . ' ' . Firma::get('ort', 'Aragona (AG)'))])))) ?><br>
      <?= $h($abs['email']) ?><?= $abs['telefon'] !== '' ? '<br>' . $h($abs['telefon']) : '' ?><br>www.vecom-design.it</div>
  </header>
  <div class="fenster">
    <div class="ruecksende"><?= $h($ruecksende) ?></div>
    <div class="an"><?= implode('<br>', array_map($h, $an)) ?></div>
  </div>
  <p class="datum"><?= $h($ortDatum) ?></p>
  <?php if (trim((string) $v['betreff']) !== ''): ?><p class="betreff"><?= $h($v['betreff']) ?></p><?php endif; ?>
  <div class="text"><?= $h(rtrim((string) $v['text'])) ?></div>
  <?php if ($qr): ?>
    <div class="qr"><div id="qr" data-inhalt="<?= $h($qr) ?>"></div>
      <div><b><?= $h($W['qr']) ?></b><?= $h($W['qr2']) ?><small><?= $h($qr) ?></small></div></div>
  <?php endif; ?>
  <?php $fuss = Firma::fusszeilen(); /* Kontakt und Steuernummer; die Bankzeile gehoert nicht in einen Werbebrief. */ ?>
  <footer class="fuss"><span><?= $h($fuss[0] ?? $abs['email']) ?></span><span><?= $h($fuss[1] ?? '') ?></span></footer>
  <?php if ($entwurf): ?><div class="stempel"><span><?= $sprache === 'it' ? 'BOZZA' : ($sprache === 'en' ? 'DRAFT' : 'ENTWURF') ?></span></div><?php endif; ?>
</section>

<?php if ($audit && ($audit['screenshot_mobil'] || $audit['screenshot_desktop'] || $drei)): ?>
<section class="blatt anlage">
  <div class="goldlinie"></div>
  <header class="kopf">
    <div class="marke"><img src="/assets/img/logo-mark.webp" alt="" width="120" height="94"><div><b>VECOM</b><span>DESIGN</span></div></div>
    <div class="absender"><?= $h($W['anlage']) ?><br><?= $h($f['name']) ?></div>
  </header>
  <h2><?= $h($W['heute']) ?><?= $f['domain'] ? ': ' . $h($f['domain']) : '' ?></h2>
  <p class="unter"><?= $h($W['aufgenommen']) ?> <?= $h($datum((string) ($audit['beendet_am'] ?: 'now'))) ?></p>
  <div class="bilder">
    <?php if ($audit['screenshot_mobil']): ?><figure style="width:52mm"><img src="<?= $h(url('akquise/bild/' . (int) $audit['id'] . '/mobil')) ?>" width="390" height="844" alt="">
      <figcaption><?= $h($W['handy']) ?></figcaption></figure><?php endif; ?>
    <?php if ($audit['screenshot_desktop']): ?><figure style="flex:1"><img src="<?= $h(url('akquise/bild/' . (int) $audit['id'] . '/desktop')) ?>" width="1366" height="768" alt="">
      <figcaption><?= $h($W['pc']) ?></figcaption></figure><?php endif; ?>
  </div>
  <?php if ($drei): ?>
    <h2 style="margin-top:10mm;font-size:12pt"><?= $h($W['drei']) ?></h2>
    <ol class="drei"><?php foreach ($drei as $s): ?><li><span><?= $h($s) ?></span></li><?php endforeach; ?></ol>
  <?php endif; ?>
  <?php if ($entwurf): ?><div class="stempel"><span><?= $sprache === 'it' ? 'BOZZA' : ($sprache === 'en' ? 'DRAFT' : 'ENTWURF') ?></span></div><?php endif; ?>
</section>
<?php endif; ?>
<?php endif; ?>

<?php if ($qr): ?>
<script src="/assets/js/qrcode.js"></script>
<script>
/* QR im Browser gezeichnet -- die Adresse geht an keinen fremden Dienst. */
(function () {
  var ziel = document.getElementById('qr'); if (!ziel || typeof qrcode !== 'function') { return; }
  var q = qrcode(0, 'M'); q.addData(ziel.getAttribute('data-inhalt')); q.make();
  ziel.innerHTML = q.createSvgTag(4, 0);
})();
</script>
<?php endif; ?>
</body>
</html>
