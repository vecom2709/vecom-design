<?php
/** @var ?array $f @var ?array $analyse @var string $ziel @var string $sp */
/* QR-KARTE ZUM HINLEGEN (28.09.2026, Uwe: Ja zu Z3).

   Eine A4-Seite mit vier Karten im Format A6 zum Ausschneiden. Zwei Arten:

     für einen Betrieb   QR führt auf SEINE Analyse-Seite (analyse.php) --
                         dort steht, was wir gefunden haben, und darunter der
                         Kasten für E-Mail/WhatsApp mit Häkchen.
     allgemein           QR führt auf analisi.php: Adresse eingeben, Ampel
                         sehen, darunter dasselbe Ja in einem Schritt.

   Die Karte schreibt niemanden an. Sie liegt auf dem Tresen, und wer
   neugierig ist, scannt selbst -- das Ja kommt vom Betrieb. */
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$abs = AkquiseText::absender();
$tel = trim(AkquiseGate::einstellung('akq_absender_telefon', ''));
$wa = preg_replace('~\D~', '', AkquiseGate::einstellung('wa_anzeige', '')) ?? '';
$waZeige = $wa !== '' ? '+' . substr($wa, 0, 2) . ' ' . trim(chunk_split(substr($wa, 2), 3, ' ')) : '';
$W = [
    'it' => ['fuer' => 'Per {name}', 'titelF' => 'Abbiamo guardato il suo sito.', 'textF' => 'Inquadri il codice con la fotocamera: vede cosa abbiamo notato e cosa proponiamo. Gratis e senza impegno.',
             'titelA' => 'Analisi gratuita del suo sito', 'textA' => 'Inquadri il codice e inserisca l’indirizzo del sito: in pochi secondi vede sei punti con semaforo. Gratis, senza registrazione.',
             'wa' => 'Oppure su WhatsApp', 'scan' => 'Inquadri'],
    'de' => ['fuer' => 'Für {name}', 'titelF' => 'Wir haben uns Ihre Website angesehen.', 'textF' => 'Halten Sie die Kamera auf den Code: Sie sehen, was uns aufgefallen ist und was wir vorschlagen. Kostenlos und unverbindlich.',
             'titelA' => 'Kostenlose Analyse Ihrer Website', 'textA' => 'Halten Sie die Kamera auf den Code und geben Sie die Adresse ein: In wenigen Sekunden sehen Sie sechs Punkte als Ampel. Kostenlos, ohne Anmeldung.',
             'wa' => 'Oder per WhatsApp', 'scan' => 'Scannen'],
    'en' => ['fuer' => 'For {name}', 'titelF' => 'We took a look at your website.', 'textF' => 'Point your camera at the code: you see what we noticed and what we suggest. Free, no obligation.',
             'titelA' => 'Free analysis of your website', 'textA' => 'Point your camera at the code and enter the address: in a few seconds you see six points as traffic lights. Free, no sign-up.',
             'wa' => 'Or on WhatsApp', 'scan' => 'Scan'],
][$sp];
$istFirma = $f !== null;
$qr = QrBild::svg($ziel, 300, 2);
$kurz = preg_replace('~^https?://(www\.)?~', '', $ziel) ?? $ziel;
$kurz = $istFirma ? preg_replace('~\?.*$~', '', $kurz) : $kurz;
$karte = static function () use ($h, $W, $istFirma, $f, $qr, $kurz, $abs, $tel, $waZeige): string {
    ob_start(); ?>
    <article class="karte">
      <div class="oben"><span class="marke"><b>VECOM</b> DESIGN</span><?php if ($istFirma): ?><span class="fuer"><?= $h(strtr($W['fuer'], ['{name}' => (string) $f['name']])) ?></span><?php endif; ?></div>
      <div class="mitte">
        <div class="wort">
          <h2><?= $h($istFirma ? $W['titelF'] : $W['titelA']) ?></h2>
          <p><?= $h($istFirma ? $W['textF'] : $W['textA']) ?></p>
        </div>
        <figure class="qr"><?= $qr ?><figcaption><?= $h($W['scan']) ?> ↑</figcaption></figure>
      </div>
      <div class="unten">
        <span><?= $h($abs['inhaber']) ?><?= $tel !== '' ? ' · ' . $h($tel) : '' ?></span>
        <?php if ($waZeige !== ''): ?><span><?= $h($W['wa']) ?>: <b><?= $h($waZeige) ?></b></span><?php else: ?><span><?= $h((string) $kurz) ?></span><?php endif; ?>
      </div>
    </article>
    <?php return (string) ob_get_clean();
};
$eine = $karte();
?><!doctype html>
<html lang="<?= $h($sp) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>QR-Karte<?= $istFirma ? ' — ' . $h((string) $f['name']) : '' ?></title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<style>
  @page{size:A4;margin:0}
  :root{--papier:#fbf8f2;--tinte:#17140f;--grau:#6d665b;--gold:#b98a2e;--linie:#e4dccd}
  *{box-sizing:border-box}
  body{margin:0;background:#2a2721;font:15px/1.5 'Inter',system-ui,sans-serif;color:var(--tinte)}
  .leiste{position:sticky;top:0;z-index:2;display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:12px 16px;background:#15130f;color:#f6f1e7}
  .leiste a,.leiste button{font:600 15px/1 'Inter',sans-serif;color:#f6f1e7;background:#2c2820;border:1px solid #4a4336;border-radius:10px;padding:11px 14px;text-decoration:none;cursor:pointer}
  .leiste button.haupt{background:#f1d38b;color:#16120b;border-color:#f1d38b}
  .leiste a[aria-current]{background:#f6f1e7;color:#16120b}
  .leiste .hinweis{font-size:13.5px;color:#b7afa2;flex-basis:100%}
  .blatt{width:210mm;height:297mm;margin:18px auto;background:#fff;display:grid;grid-template-columns:105mm 105mm;grid-template-rows:148.5mm 148.5mm;box-shadow:0 10px 40px rgba(0,0,0,.35)}
  .karte{position:relative;padding:9mm 8mm 7mm;background:var(--papier);display:flex;flex-direction:column;border:0.2mm dashed #cfc6b5}
  .karte::before{content:"";position:absolute;left:8mm;right:8mm;top:0;height:1.4mm;background:var(--gold)}
  .oben{display:flex;justify-content:space-between;align-items:baseline;gap:4mm}
  .marke{font:800 10pt/1 'Archivo',sans-serif;letter-spacing:.12em}.marke b{color:var(--gold)}
  .fuer{font-size:8.5pt;color:var(--grau);text-align:right;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:52mm}
  .mitte{flex:1;display:flex;flex-direction:column;justify-content:center;gap:5mm}
  .wort h2{font:800 17pt/1.1 'Archivo',sans-serif;margin:0 0 2.5mm;letter-spacing:-.01em}
  .wort p{margin:0;font-size:10pt;line-height:1.45;color:#3b352c}
  .qr{margin:0;align-self:center;display:flex;flex-direction:column;align-items:center;gap:1.5mm}
  .qr svg{width:44mm;height:44mm;display:block}
  .qr figcaption{font:700 8pt/1 'Inter',sans-serif;letter-spacing:.14em;text-transform:uppercase;color:var(--grau)}
  .unten{display:flex;flex-direction:column;gap:.6mm;border-top:0.3mm solid var(--linie);padding-top:2.5mm;font-size:8.5pt;color:var(--grau)}
  .unten b{color:var(--tinte)}
  @media print{body{background:#fff}.leiste{display:none}.blatt{margin:0;box-shadow:none}}
  @media (max-width:800px){.blatt{transform:scale(calc((100vw - 16px) / 794px));transform-origin:top left;margin:12px 8px;height:auto}}
</style>
</head>
<body>
  <div class="leiste">
    <button class="haupt" type="button" onclick="window.print()">Drucken</button>
    <?php foreach (['it' => 'Italienisch', 'de' => 'Deutsch', 'en' => 'Englisch'] as $l => $n): ?><a href="?sprache=<?= $l ?>"<?= $l === $sp ? ' aria-current="true"' : '' ?>><?= $n ?></a><?php endforeach; ?>
    <a href="<?= $h(url($istFirma ? 'akquise/' . (int) $f['id'] : 'akquise/regeln#wege')) ?>">← Zurück</a>
    <span class="hinweis">A4, ohne Rand drucken („Tatsächliche Größe“), an den gestrichelten Linien schneiden. Der Code führt auf <?= $h((string) $kurz) ?>.<?= $istFirma ? ' Die Analyse-Seite ist ' . AkquiseAnalyse::GUELTIG_TAGE . ' Tage gültig.' : '' ?></span>
  </div>
  <main class="blatt"><?= $eine . $eine . $eine . $eine ?></main>
</body>
</html>
