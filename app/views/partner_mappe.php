<?php
/* ==========================================================================
   Mappe zum Vorbeibringen (27.09.2026, Uwe: Ja). Eingebunden aus partner.php
   bei ?druck=mappe&ck=… (ein eigener Schnellcheck) oder &firma=… (ein
   reservierter Betrieb). Gesetzt: $p, $h, $mappe (check|firma + Daten).

   Ein A4-Blatt, das der Partner ausgedruckt vorbeibringt: was uns an der
   Seite aufgefallen ist, wie Vecom arbeitet, wer er ist, und der QR-Code.
   Hell, weil es auf dem Heimdrucker entsteht. Die Sprache ist die des
   Betriebs, nicht die des Partners -- aus Land/Sprache des Betriebs bzw. der
   Endung der Adresse (PartnerMappe::laden), umschaltbar. Die Bedienleiste
   oben spricht die Sprache des Partners (02.10.2026).

   Keine erfundenen Aussagen: Mit Schnellcheck stehen dessen Ergebnisse da.
   Ohne Website die drei Sätze, die für jeden Betrieb ohne Website stimmen.
   Mit Website, aber ohne Check nur eine Liste zum Selbstprüfen -- und das
   Angebot, den Check zu machen. Nie „Ihre Seite ist langsam“ ohne Messung.
   ========================================================================== */
require_once dirname(__DIR__) . '/src/QrBild.php';
$ms = in_array((string) ($mappe['sprache'] ?? ''), ['it', 'de', 'en'], true) ? (string) $mappe['sprache'] : 'it';
$MM = static fn(string $k) => Texte::PARTNER_MARKETING[$k][$ms] ?? Texte::PARTNER_MARKETING[$k]['it'];
$mpBed = in_array((string) ($sprache ?? $p['sprache'] ?? ''), ['it', 'de', 'en'], true) ? (string) ($sprache ?? $p['sprache']) : $ms;
$MB = static fn(string $k) => Texte::PARTNER_MARKETING[$k][$mpBed] ?? $MM($k);
$name = Partner::anzeigeName($p);
$link = PartnerWerbung::link($p, 'mappe');
$kurz = preg_replace('~^https?://~', '', Partner::link($p));
$qr = QrBild::svg($link, 220, 1);
$foto = PartnerWerbung::fotoAdresse($p);
$satz = trim((string) ($p['profil_satz'] ?? ''));
$stimme = PartnerSeite::stimmen($p, $ms, 1)[0] ?? null;
$zurueck = '/partner.php?' . http_build_query(['t' => $p['token']]) . '#recherche';
$hier = static fn(string $l): string => '/partner.php?' . http_build_query(array_filter(['t' => $p['token'], 'druck' => 'mappe',
    'ck' => $mappe['art'] === 'check' ? $mappe['token'] : null, 'firma' => $mappe['art'] === 'firma' ? $mappe['id'] : null, 'sp' => $l]));
$ersetze = static fn(string $t): string => strtr($t, ['{name}' => $name]);
?><!doctype html>
<html lang="<?= $h($ms) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Vecom Design — <?= $h(strtr($MM('mp_fuer'), ['{firma}' => $mappe['titel']])) ?></title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<style>
  @page{size:A4;margin:0}
  *{box-sizing:border-box}
  body{margin:0;background:#d9d4ca;font-family:'Inter',system-ui,sans-serif;color:#16120b;-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .leiste{display:flex;gap:10px;justify-content:center;align-items:center;flex-wrap:wrap;padding:14px}
  .leiste a,.leiste button{padding:11px 18px;font-size:15px;border-radius:10px;border:1px solid #9b927f;background:#fff;color:#16120b;text-decoration:none;cursor:pointer;font-family:inherit}
  .leiste a.jetzt{border-color:#8a6a22;font-weight:700}
  .leiste button{border:0;background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438);font-weight:700}
  .leiste span{font-size:13px;color:#4b453b}
  .blatt{width:210mm;min-height:297mm;background:#fff;margin:0 auto 14mm;padding:13mm 16mm 11mm;box-shadow:0 2px 14px rgba(0,0,0,.15);display:flex;flex-direction:column}
  .kopf{display:flex;justify-content:space-between;align-items:center;border-bottom:.3mm solid #e6dfd0;padding-bottom:5mm}
  .wort{display:inline-flex;align-items:center;gap:2mm;font-family:'Archivo',sans-serif;font-weight:800;letter-spacing:.06em;font-size:11pt}
  .wort img{height:1.35em;width:auto}.wort b{color:#8a6a22}
  .datum{font-size:9pt;color:#6b6457}
  h1{font-family:'Archivo',sans-serif;font-size:26pt;line-height:1.08;margin:8mm 0 2mm;word-break:break-word}
  .lead{margin:0 0 6mm;color:#4b453b;font-size:10.5pt}
  h2{font-family:'Archivo',sans-serif;font-size:13pt;margin:0 0 3mm}
  .teil{margin-bottom:5.5mm}
  ul{list-style:none;padding:0;margin:0;display:grid;gap:2.2mm}
  ul.zwei{grid-template-columns:1fr 1fr;gap:3mm 7mm}
  li{display:flex;gap:3mm;align-items:flex-start;font-size:10.5pt;line-height:1.45}
  li b{display:block;font-size:10.5pt}
  .ampel{flex:0 0 3.2mm;height:3.2mm;border-radius:50%;margin-top:1.3mm}
  .gut{background:#2fae7f}.hinweis{background:#d9a531}.schlecht{background:#d65a4a}
  .kasten{flex:0 0 3.6mm;height:3.6mm;border:.35mm solid #8a6a22;border-radius:.8mm;margin-top:.9mm}
  .punkt{flex:0 0 6mm;height:6mm;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:8.5pt;background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438)}
  .fazit{border:.35mm solid #d9c38b;background:#fbf6ea;border-radius:2.5mm;padding:3mm 4mm;font-weight:600;font-size:10.5pt;margin:0 0 4mm}
  .klein{font-size:9pt;color:#6b6457;margin:2.5mm 0 0}
  blockquote{margin:0;padding:0 0 0 4mm;border-left:.6mm solid #c49438;font-size:10.5pt;line-height:1.5}
  blockquote cite{display:block;font-style:normal;font-size:9pt;color:#6b6457;margin-top:1.5mm}
  .fuss{margin-top:auto;display:flex;gap:7mm;align-items:stretch;border-top:.3mm solid #e6dfd0;padding-top:5mm}
  .person{flex:1;display:flex;gap:4mm;align-items:flex-start}
  .person img{width:22mm;height:22mm;border-radius:50%;object-fit:cover;border:.4mm solid #c49438;flex:none}
  .person .n{font-family:'Archivo',sans-serif;font-weight:800;font-size:13pt;margin:1mm 0 1.5mm}
  .person .s{font-size:10pt;line-height:1.45;color:#2c271f;margin:0}
  .qrteil{flex:0 0 52mm;text-align:center}
  .qr{width:44mm;height:44mm;margin:0 auto}
  .qr svg{display:block;width:100%;height:100%}
  .scan{font-weight:700;font-size:10pt;color:#8a6a22;margin:2mm 0 1mm}
  .code{font-size:8.5pt;color:#4b453b;overflow-wrap:anywhere}
  .unten{font-size:7.5pt;color:#8b847a;margin:5mm 0 0}
  @media print{body{background:#fff}.leiste{display:none}.blatt{margin:0;box-shadow:none;width:210mm;height:297mm;min-height:0;overflow:hidden;page-break-after:avoid;break-after:avoid}}
  @media screen and (max-width:820px){.blatt{width:auto;min-height:0;margin:0 8px 16px;padding:22px 18px}.fuss{flex-direction:column}.qrteil{flex-basis:auto}ul.zwei{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="leiste">
  <a href="<?= $h($zurueck) ?>"><?= $h($MB('mp_zurueck')) ?></a>
  <span><?= $h($MB('mp_sprache')) ?>:</span>
  <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
    <a class="<?= $l === $ms ? 'jetzt' : '' ?>" href="<?= $h($hier($l)) ?>"><?= $h($wie) ?></a>
  <?php endforeach; ?>
  <button type="button" onclick="window.print()"><?= $h($MB('mp_drucken')) ?></button>
</div>

<div class="blatt">
  <div class="kopf"><span class="wort"><img src="/assets/img/logo-mark.webp?v=gold2609" alt="" width="40" height="32"><b>VECOM</b>&nbsp;DESIGN</span>
    <span class="datum"><?= $h(date('d.m.Y')) ?></span></div>

  <h1><?= $h(strtr($MM('mp_fuer'), ['{firma}' => $mappe['titel']])) ?></h1>
  <p class="lead"><?= $h(strtr($MM('mp_lead'), ['{datum}' => date('d.m.Y'), '{name}' => $name])) ?><?= $mappe['ort'] !== '' ? ' · ' . $h($mappe['ort']) : '' ?></p>

  <?php if ($mappe['art'] === 'check'):
      $C = static fn(string $k): string => Texte::h(Texte::PARTNER_CHECK[$k] ?? [], $ms);
      $schlecht = count(array_filter($mappe['punkte'], static fn($x) => $x['stand'] === 'schlecht')); ?>
    <div class="teil">
      <h2><?= $h(strtr($C('titel'), ['{host}' => $mappe['host']])) ?></h2>
      <p class="fazit"><?= $h($schlecht === 0 ? $C('fazit0') : ($schlecht < 3 ? $C('fazit1') : $C('fazit3'))) ?></p>
      <ul class="zwei">
        <?php foreach ($mappe['punkte'] as $pk):
          $K = Texte::PARTNER_CHECK['punkte'][$pk['was']] ?? null; if (!$K) { continue; }
          $satzK = ($pk['k'] ?? '') !== '' ? (string) $pk['k'] : ($pk['was'] === 'aktuell' && $pk['stand'] === 'hinweis' && $pk['wert'] === '' ? 'hinweis_leer' : $pk['stand']); ?>
          <li><span class="ampel <?= $h($pk['stand']) ?>" aria-hidden="true"></span><div><b><?= $h(Texte::h($K['titel'], $ms)) ?></b>
            <?= $h(strtr(Texte::h($K[$satzK] ?? $K[$pk['stand']] ?? [], $ms), ['{wert}' => (string) $pk['wert']])) ?></div></li>
        <?php endforeach; ?>
      </ul>
      <p class="klein"><?= $h(strtr($C('lead'), ['{datum}' => $mappe['datum']])) ?> <?= $h($C('klein')) ?></p>
    </div>
  <?php elseif ($mappe['ohne_website']):
      $was = $mappe['branche'] !== '' && $mappe['stadt'] !== '' ? strtr($MM('mp_was'), ['{branche}' => $mappe['branche'], '{ort}' => $mappe['stadt']]) : $MM('mp_was_leer'); ?>
    <div class="teil">
      <h2><?= $h($MM('mp_ohne_titel')) ?></h2>
      <ul><?php foreach ($MM('mp_ohne') as $t): ?><li><span class="ampel hinweis" aria-hidden="true"></span><div><?= $h(strtr($t, ['{was}' => $was])) ?></div></li><?php endforeach; ?></ul>
    </div>
  <?php else: ?>
    <div class="teil">
      <h2><?= $h($MM('mp_liste_titel')) ?></h2>
      <ul><?php foreach ($MM('mp_liste') as $t): ?><li><span class="kasten" aria-hidden="true"></span><div><?= $h($t) ?></div></li><?php endforeach; ?></ul>
      <p class="klein"><?= $h($ersetze($MM('mp_liste_klein'))) ?></p>
    </div>
  <?php endif; ?>

  <div class="teil">
    <h2><?= $h($MM('mp_vecom_titel')) ?></h2>
    <ul><?php foreach ($MM('mp_vecom') as $i => $t): ?><li><span class="punkt"><?= $i + 1 ?></span><div><?= $h($t) ?></div></li><?php endforeach; ?></ul>
  </div>

  <?php if ($stimme): ?>
  <div class="teil">
    <h2><?= $h($MM('mp_stimme_titel')) ?></h2>
    <blockquote>„<?= $h(mb_strimwidth($stimme['text'], 0, 200, '…')) ?>“
      <cite><?= $h(trim($stimme['name'] . ($stimme['firma'] !== '' ? ', ' . $stimme['firma'] : '') . ($stimme['ort'] !== '' ? ' · ' . $stimme['ort'] : ''))) ?></cite></blockquote>
  </div>
  <?php endif; ?>

  <div class="fuss">
    <div class="person">
      <?php if ($foto): ?><img src="<?= $h($foto) ?>" alt=""><?php endif; ?>
      <div><p class="klein" style="margin:0"><?= $h($MM('mp_kontakt_titel')) ?></p>
        <p class="n"><?= $h($name) ?></p>
        <?php if ($satz !== ''): ?><p class="s">„<?= $h($satz) ?>“</p><?php endif; ?></div>
    </div>
    <div class="qrteil">
      <div class="qr"><?= $qr ?></div>
      <p class="scan"><?= $h($MM('mp_scan')) ?></p>
      <p class="code"><?= $h(strtr($MM('mp_code'), ['{kurz}' => $kurz, '{code}' => (string) $p['code']])) ?></p>
    </div>
  </div>
  <p class="unten"><?= $h($ersetze($MM('mp_klein'))) ?></p>
</div>
</body>
</html>
