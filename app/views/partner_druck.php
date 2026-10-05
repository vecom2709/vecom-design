<?php
/* ==========================================================================
   Druck-Paket der Partner (26.09.2026). Eingebunden aus partner.php bei
   ?druck=visitenkarten|flyer|aufsteller|aufkleber. Gesetzt: $p, $sprache, $h, $T.

   Der QR-Code wird auf dem Server als SVG gerechnet (QrBild): Er ist beim
   Drucken gestochen scharf, und die Seite druckt auch ohne Skript. Jede
   Druckart trägt ihren eigenen Kanal im Link, damit „Was wirkt“ Flyer und
   Visitenkarte auseinanderhält.

   „Hell“ gibt es, weil ein schwarzer A4-Bogen eine Heimdrucker-Patrone
   leert. Die Druckerei nimmt die dunkle Fassung.
   ========================================================================== */
require_once dirname(__DIR__) . '/src/QrBild.php';
$art = (string) $_GET['druck'];
$hell = !empty($_GET['hell']);
$M = static fn(string $k): string => strtr(Texte::h(Texte::PARTNER_MEDIEN[$k] ?? [], $sprache), ['{name}' => Partner::anzeigeName($p)]);
$motiv = Texte::PARTNER_MEDIEN['motive']['allgemein'];
$kanal = ['visitenkarten' => 'karte', 'flyer' => 'flyer', 'aufsteller' => 'flyer', 'aufkleber' => 'flyer'][$art];
/* Zum Selbstdrucken aus einer Kampagne (Phase 4, 05.10.2026): &kampagne=N — der QR-Code zählt dann für diese
   Kampagne. Nur eine eigene; eine fremde Nummer gibt den gewöhnlichen Flyer. */
if ($art === 'flyer' && isset($_GET['kampagne'])) {
    require_once dirname(__DIR__) . '/src/PartnerKampagne.php';
    if (($dK = PartnerKampagne::laden((int) $p['id'], (int) $_GET['kampagne'])) !== null) { $kanal = PartnerKampagne::kanal((int) $dK['id']); }
}
$link = PartnerWerbung::link($p, $kanal);
$kurz = preg_replace('~^https?://~', '', Partner::link($p));
$qr = QrBild::svg($link, 200, 1);
$zurueck = '/partner.php?' . http_build_query(['t' => $p['token'], 'voll' => 1]) . '#medien';   // voll: der schlichte Link öffnet das Command Center
$umschalten = '/partner.php?' . http_build_query(array_filter(['t' => $p['token'], 'druck' => $art, 'hell' => $hell ? null : 1, 'kampagne' => isset($dK) ? (int) $dK['id'] : null]));
$seite = ['visitenkarten' => 'A4', 'flyer' => 'A5', 'aufsteller' => 'A4 landscape', 'aufkleber' => 'A4'][$art];
$logo = '<span class="wort"><img src="/assets/img/logo-mark.webp?v=gold2609" alt="" width="40" height="32"><b>VECOM</b> DESIGN</span>';
?><!doctype html>
<html lang="<?= $h($sprache) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Vecom Design — <?= $h($T('dr_' . $art)) ?></title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<style>
  @page{size:<?= $seite ?>;margin:0}
  :root{--g:#0a0908;--t:#f7f3ea;--l:#b4ada2;--a:#f1d38b;--q:#fff}
  <?php if ($hell): ?>:root{--g:#ffffff;--t:#14110c;--l:#5b554b;--a:#8a6a22}<?php endif; ?>
  *{box-sizing:border-box}
  body{margin:0;background:#d9d4ca;font-family:'Inter',system-ui,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .leiste{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;padding:14px}
  .leiste a,.leiste button{padding:11px 18px;font-size:15px;border-radius:10px;border:1px solid #9b927f;background:#fff;color:#16120b;text-decoration:none;cursor:pointer;font-family:inherit}
  .leiste button{border:0;background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438);font-weight:700}
  .bogen{background:#fff;margin:0 auto 14mm;position:relative;overflow:hidden;box-shadow:0 2px 14px rgba(0,0,0,.15)}
  .wort{display:inline-flex;align-items:center;gap:2mm;font-family:'Archivo',sans-serif;font-weight:800;letter-spacing:.06em;color:var(--t)}
  .wort img{height:1.3em;width:auto}
  .wort b{color:var(--a)}
  .qr svg{display:block;width:100%;height:100%}
  .qr{background:var(--q);border-radius:1.5mm;padding:1.2mm}
  .url{color:var(--a);font-weight:600;word-break:break-all}
  .werbung{position:absolute;right:3mm;top:2.5mm;font-size:5.5pt;color:var(--l);opacity:.8}
  /* Visitenkarten 85×55, 2×5 auf A4, Schnittlinien fein grau */
  .vk{width:210mm;height:297mm;display:grid;grid-template-columns:repeat(2,85mm);grid-auto-rows:55mm;justify-content:center;align-content:center}
  .vk .k{background:var(--g);color:var(--t);border:.2mm dashed #bbb;padding:5mm 5mm 4.5mm;display:flex;gap:4mm;position:relative}
  .vk .k .l{flex:1;display:flex;flex-direction:column;justify-content:space-between;min-width:0}
  .vk .k .wort{font-size:8.5pt}
  .vk .k .t{font-family:'Archivo',sans-serif;font-weight:700;font-size:9.5pt;line-height:1.2}
  .vk .k .e{font-size:6.8pt;color:var(--l);line-height:1.35}
  .vk .k .url{font-size:6.8pt}
  .vk .k .qr{width:27mm;height:27mm;align-self:center;flex:0 0 27mm}
  /* Flyer A5 */
  .fl{width:148mm;height:210mm;background:var(--g);color:var(--t);padding:13mm 12mm;display:flex;flex-direction:column}
  .fl .wort{font-size:11pt}
  .fl h1{font-family:'Archivo',sans-serif;font-size:31pt;line-height:1.06;margin:14mm 0 5mm}
  .fl .u{color:var(--l);font-size:12.5pt;line-height:1.45;margin:0 0 10mm}
  .fl ol{list-style:none;padding:0;margin:0;display:grid;gap:5mm;font-size:13pt}
  .fl li{display:flex;gap:3mm;align-items:center}
  .fl li span{flex:0 0 8.5mm;height:8.5mm;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:9pt;color:#16120b;background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438)}
  .fl .fuss{margin-top:auto;display:flex;gap:6mm;align-items:flex-end}
  .fl blockquote{margin:9mm 0 0;padding:0 0 0 4mm;border-left:.6mm solid var(--a);font-size:11pt;line-height:1.5;color:var(--t)}
  .fl blockquote cite{display:block;font-style:normal;font-size:9pt;color:var(--l);margin-top:1.5mm}
  .fl .fuss .qr{width:50mm;height:50mm;flex:0 0 50mm}
  .fl .fuss .s{font-size:12pt;font-weight:700;color:var(--a);margin:0 0 2mm}
  .fl .fuss .e{font-size:9pt;color:var(--l);margin:3mm 0 0}
  .fl .fuss .url{font-size:10pt}
  .fl .fuss .c{font-size:8.5pt;color:var(--l);margin-top:1.5mm}
  /* Tischaufsteller A4 quer: zwei Hälften, die obere steht auf dem Kopf */
  .ta{width:297mm;height:210mm}
  .ta .h{height:105mm;background:var(--g);color:var(--t);display:flex;align-items:center;gap:10mm;padding:0 16mm;position:relative}
  .ta .h.oben{transform:rotate(180deg)}
  .ta .h .l{flex:1}
  .ta .h .wort{font-size:11pt}
  .ta .h h2{font-family:'Archivo',sans-serif;font-size:30pt;line-height:1.08;margin:6mm 0 4mm}
  .ta .h .e{font-size:10pt;color:var(--l)}
  .ta .h .qr{width:62mm;height:62mm;flex:0 0 62mm}
  .ta .h .s{text-align:center;font-weight:700;color:var(--a);font-size:11pt;margin-top:2mm}
  .ta .falz{position:absolute;left:0;right:0;top:105mm;border-top:.3mm dashed #999;z-index:2}
  .ta .falz span{position:absolute;right:4mm;top:-4mm;font-size:6.5pt;color:#999;background:var(--g);padding:0 1.5mm}
  /* Aufkleber 60×60, 3×4 */
  .ak{width:210mm;height:297mm;display:grid;grid-template-columns:repeat(3,60mm);grid-auto-rows:60mm;gap:6mm;justify-content:center;align-content:center}
  .ak .s{background:var(--g);color:var(--t);border-radius:5mm;border:.2mm dashed #bbb;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2mm;text-align:center;padding:3mm}
  .ak .s .qr{width:36mm;height:36mm}
  .ak .s b{font-family:'Archivo',sans-serif;font-size:10pt}
  .ak .s small{font-size:6.5pt;color:var(--l)}
  @media print{body{background:#fff}.leiste{display:none}.bogen{margin:0;box-shadow:none}}
</style>
</head>
<body>
<div class="leiste">
  <a href="<?= $h($zurueck) ?>">← Vecom Partner</a>
  <a href="<?= $h($umschalten) ?>"><?= $hell ? '◐' : '◑' ?> <?= $h($T('dr_hell')) ?></a>
  <button type="button" onclick="window.print()"><?= $h($T('dr_drucken')) ?></button>
</div>

<?php if ($art === 'visitenkarten'): ?>
<div class="bogen vk">
  <?php for ($i = 0; $i < 10; $i++): ?>
    <div class="k"><div class="l"><?= $logo ?>
      <div class="t"><?= $h(Texte::h($motiv['titel'], $sprache)) ?></div>
      <div><div class="e"><?= $h($M('empf')) ?></div><div class="url"><?= $h($kurz) ?></div></div></div>
      <div class="qr"><?= $qr ?></div></div>
  <?php endfor; ?>
</div>

<?php elseif ($art === 'flyer'): ?>
<div class="bogen fl">
  <span class="werbung"><?= $h($M('werbung')) ?></span>
  <?= $logo ?>
  <h1><?= $h(Texte::h($motiv['titel'], $sprache)) ?></h1>
  <p class="u"><?= $h(Texte::h($motiv['unter'], $sprache)) ?></p>
  <ol><?php foreach (Texte::PARTNER_MEDIEN['punkte'] as $i => $pk): ?><li><span><?= $i + 1 ?></span><?= $h(Texte::h($pk, $sprache)) ?></li><?php endforeach; ?></ol>
  <?php if ((string) ($p['profil_satz'] ?? '') !== ''): ?><blockquote><?= $h(['it' => '«', 'en' => '“'][$sprache] ?? '„') . $h((string) $p['profil_satz']) . $h(['it' => '»', 'en' => '”'][$sprache] ?? '“') ?><cite>— <?= $h(Partner::anzeigeName($p)) ?></cite></blockquote><?php endif; ?>
  <div class="fuss"><div class="qr"><?= $qr ?></div>
    <div><p class="s"><?= $h($M('scan')) ?></p><div class="url"><?= $h($kurz) ?></div>
      <div class="c"><?= $h($M('codewort')) ?>: <b><?= $h((string) $p['code']) ?></b></div>
      <p class="e"><?= $h($M('empf')) ?></p></div></div>
</div>

<?php elseif ($art === 'aufsteller'): ?>
<div class="bogen ta">
  <?php foreach (['oben', 'unten'] as $seiteName): ?>
    <div class="h <?= $seiteName ?>"><div class="l"><?= $logo ?>
      <h2><?= $h(Texte::h($motiv['titel'], $sprache)) ?></h2>
      <div class="e"><?= $h($M('empf')) ?> · <span class="url"><?= $h($kurz) ?></span></div></div>
      <div><div class="qr"><?= $qr ?></div><div class="s"><?= $h($M('scan')) ?></div></div>
      <span class="werbung"><?= $h($M('werbung')) ?></span></div>
  <?php endforeach; ?>
  <div class="falz"><span><?= $h($T('dr_falz')) ?></span></div>
</div>

<?php else: ?>
<div class="bogen ak">
  <?php for ($i = 0; $i < 12; $i++): ?>
    <div class="s"><b><?= $h($M('sticker')) ?></b><div class="qr"><?= $qr ?></div><small><?= $h($kurz) ?></small></div>
  <?php endfor; ?>
</div>
<?php endif; ?>
</body>
</html>
