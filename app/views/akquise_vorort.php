<?php
/** @var array $f @var array $befunde */
/* VOR-ORT-MODUS (28.09.2026, Uwe: Ja zu V1) — für den Besuch im Laden, auf
   Uwes Handy, in der Sprache des Betriebs.

   Oben: was wir auf seiner Website gefunden haben und was wir vorschlagen
   (dieselben Sätze wie in den Texten, aus den belegten Befunden). Darunter
   gibt der Inhaber selbst seine E-Mail ein, auf Wunsch seine WhatsApp-Nummer,
   und hakt den Wortlaut an. Danach geht NUR die Bestätigungsmail an seine
   Adresse; erst sein Klick darin macht E-Mail (und WhatsApp) erlaubt -- mit
   demselben Beleg wie jede andere Einwilligung (Quelle „vorort“).

   Eigene Seite ohne Menü: Der Bildschirm gehört in dem Moment dem Kunden. */
$fid = (int) $f['id'];
$sp = in_array($_GET['sprache'] ?? '', ['it', 'de', 'en'], true) ? (string) $_GET['sprache'] : AkquiseText::spracheFuer($f);
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$W = [
    'it' => ['titel' => 'Il vostro sito, visto da vicino', 'gefunden' => 'Cosa abbiamo notato', 'loesung' => 'La nostra proposta', 'ohne' => 'Non abbiamo ancora un’analisi completa del vostro sito.',
             'analyse' => 'Vedere l’analisi completa', 'form' => 'Riceverla per e-mail', 'formText' => 'Inserisca il suo indirizzo: le inviamo l’analisi e il suo spazio personale su Vecom Design. Prima riceve solo una e-mail di conferma.',
             'email' => 'La sua e-mail', 'wa' => 'Anche su WhatsApp', 'waNr' => 'Numero WhatsApp', 'knopf' => 'Inviare', 'angefragt' => 'Fatto: abbiamo inviato una e-mail a {email}. La apra ora e tocchi il link di conferma.',
             'bestaetigt' => 'Grazie! È confermato. L’analisi e il suo spazio personale arrivano a breve.', 'neu' => 'Aggiorna', 'fehler' => ['email' => 'Controlli l’indirizzo e la spunta.', 'whatsapp' => 'Il numero WhatsApp non è leggibile.', 'zuviel' => 'Troppe richieste oggi: riprovi domani.', 'gesperrt' => 'Per questo indirizzo non è possibile.']],
    'de' => ['titel' => 'Ihre Website, genauer angesehen', 'gefunden' => 'Was uns aufgefallen ist', 'loesung' => 'Unser Vorschlag', 'ohne' => 'Für Ihre Website gibt es noch keine vollständige Analyse.',
             'analyse' => 'Vollständige Analyse ansehen', 'form' => 'Per E-Mail bekommen', 'formText' => 'Tragen Sie Ihre Adresse ein: Wir schicken Ihnen die Analyse und Ihren persönlichen Bereich bei Vecom Design. Zuerst kommt nur eine Bestätigungsmail.',
             'email' => 'Ihre E-Mail-Adresse', 'wa' => 'Auch per WhatsApp', 'waNr' => 'WhatsApp-Nummer', 'knopf' => 'Absenden', 'angefragt' => 'Erledigt: Wir haben eine Mail an {email} geschickt. Öffnen Sie sie jetzt und tippen Sie auf den Bestätigungslink.',
             'bestaetigt' => 'Danke! Bestätigt. Die Analyse und Ihr persönlicher Bereich kommen gleich.', 'neu' => 'Aktualisieren', 'fehler' => ['email' => 'Bitte Adresse und Häkchen prüfen.', 'whatsapp' => 'Die WhatsApp-Nummer ist nicht lesbar.', 'zuviel' => 'Heute zu viele Versuche: bitte morgen noch einmal.', 'gesperrt' => 'Für diese Adresse nicht möglich.']],
    'en' => ['titel' => 'Your website, up close', 'gefunden' => 'What we noticed', 'loesung' => 'Our proposal', 'ohne' => 'There is no full analysis of your website yet.',
             'analyse' => 'See the full analysis', 'form' => 'Get it by email', 'formText' => 'Enter your address: we will send you the analysis and your personal area at Vecom Design. First you only receive a confirmation email.',
             'email' => 'Your email address', 'wa' => 'Also on WhatsApp', 'waNr' => 'WhatsApp number', 'knopf' => 'Send', 'angefragt' => 'Done: we sent an email to {email}. Open it now and tap the confirmation link.',
             'bestaetigt' => 'Thank you! Confirmed. The analysis and your personal area are on their way.', 'neu' => 'Refresh', 'fehler' => ['email' => 'Please check the address and the tick box.', 'whatsapp' => 'The WhatsApp number cannot be read.', 'zuviel' => 'Too many attempts today: please try again tomorrow.', 'gesperrt' => 'Not possible for this address.']],
][$sp];
$zeilen = [];
foreach (array_slice(AkquiseScore::topBefunde($befunde), 0, 3) as $b) {
    $x = AkquiseText::saetze($b, $f, $sp);
    if ($x !== null) { $zeilen[] = $x; }
}
$loesungen = array_values(array_unique(array_map(static fn($z) => $z[2], $zeilen)));
$analyse = sicher(static fn() => Db::one('SELECT * FROM akq_analysen WHERE firma_id = ? AND aktiv = 1 AND (gueltig_bis IS NULL OR gueltig_bis >= CURDATE()) ORDER BY id DESC LIMIT 1', [$fid]), null);
$letzte = sicher(static fn() => Db::one("SELECT * FROM akq_einwilligungen WHERE firma_id = ? AND quelle = 'vorort' AND status IN ('angefragt','bestaetigt') ORDER BY id DESC LIMIT 1", [$fid]), null);
$fehler = (string) ($_GET['f'] ?? '');
$waVor = (string) ($f['whatsapp'] ?? '') ?: (preg_match('~^\+?39\s?3~', (string) ($f['telefon'] ?? '')) ? (string) $f['telefon'] : '');
$wortEmail = AkquiseEinwilligung::wortlaut($sp);
$wortWa = AkquiseEinwilligung::wortlaut($sp, ['it' => 'indicato sopra', 'de' => 'der oben angegebenen Nummer', 'en' => 'the number given above'][$sp]);
?><!doctype html>
<html lang="<?= $h($sp) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= $h((string) $f['name']) ?> — Vecom Design</title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<style>
  :root{--g:#0b0a09;--f:#15130f;--f2:#1d1a15;--t:#f6f1e7;--d:#b7afa2;--a:#f1d38b;--li:rgba(241,211,139,.18);--gut:#6fcf8f;--rot:#ef8a7a}
  *{box-sizing:border-box}
  body{margin:0;background:var(--g);color:var(--t);font:17px/1.6 'Inter',system-ui,sans-serif}
  main{max-width:640px;margin:0 auto;padding:22px 16px 60px}
  .kopf{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:18px}
  .marke{font:800 14px/1 'Archivo',sans-serif;letter-spacing:.08em}.marke b{color:var(--a)}
  .sprachen a{color:var(--d);text-decoration:none;font-weight:600;padding:8px 10px;border-radius:8px;font-size:14px}
  .sprachen a[aria-current]{color:var(--t);background:var(--f2)}
  h1{font:800 clamp(26px,7vw,34px)/1.15 'Archivo',sans-serif;margin:0 0 6px}
  .ort{color:var(--d);margin:0 0 20px}
  h2{font:700 19px/1.3 'Archivo',sans-serif;margin:24px 0 10px;color:var(--a)}
  .punkt{background:var(--f);border:1px solid var(--li);border-radius:14px;padding:14px 16px;margin-bottom:10px}
  .punkt b{display:block;margin-bottom:4px}
  .punkt span{color:var(--d);font-size:15.5px}
  ul.loes{margin:0;padding-left:20px}ul.loes li{margin:6px 0}
  .knopf{display:inline-flex;align-items:center;justify-content:center;min-height:52px;padding:0 20px;border-radius:12px;font-weight:700;font-size:16.5px;text-decoration:none;border:0;cursor:pointer;background:var(--a);color:#16120b;width:100%}
  .knopf.leise{background:transparent;color:var(--t);border:1px solid var(--li)}
  form{display:grid;gap:12px;background:var(--f);border:1px solid var(--li);border-radius:16px;padding:18px}
  label.t{font-size:14px;color:var(--d)}
  input[type=email],input[type=tel]{width:100%;font-size:18px;padding:14px;border-radius:12px;border:1px solid var(--li);background:var(--f2);color:var(--t)}
  .haken{display:flex;gap:12px;align-items:flex-start;font-size:15px;line-height:1.5}
  .haken input{width:24px;height:24px;flex:none;margin-top:2px;accent-color:var(--a)}
  .nurwa{display:none}
  form:has(#vo_wa:checked) .nurwa{display:block}
  form:has(#vo_wa:checked) .nuremail{display:none}
  .stand{border-radius:14px;padding:16px;margin:0 0 16px;font-weight:600}
  .stand.gut{border:1px solid var(--gut);color:var(--gut)}
  .stand.warte{border:1px solid var(--a);color:var(--a)}
  .stand.fehler{border:1px solid var(--rot);color:var(--rot)}
  .zurueck{display:block;margin-top:26px;color:var(--d);font-size:14px}
</style>
</head>
<body>
<main>
  <div class="kopf">
    <span class="marke"><b>VECOM</b> DESIGN</span>
    <nav class="sprachen"><?php foreach (['it' => 'IT', 'de' => 'DE', 'en' => 'EN'] as $l => $k): ?><a href="?sprache=<?= $l ?>"<?= $l === $sp ? ' aria-current="true"' : '' ?>><?= $k ?></a><?php endforeach; ?></nav>
  </div>
  <h1><?= $h((string) $f['name']) ?></h1>
  <p class="ort"><?= $h(trim((string) ($f['domain'] ?: '') . ((string) ($f['stadt'] ?? '') !== '' ? ' · ' . $f['stadt'] : ''), ' ·')) ?></p>

  <?php if ($letzte && $letzte['status'] === 'bestaetigt'): ?>
    <p class="stand gut" role="status"><?= $h($W['bestaetigt']) ?></p>
  <?php elseif ($letzte && $letzte['status'] === 'angefragt' && strtotime((string) $letzte['angefragt_am']) > time() - 3600): ?>
    <p class="stand warte" role="status"><?= $h(strtr($W['angefragt'], ['{email}' => (string) $letzte['email']])) ?></p>
    <a class="knopf leise" href="?sprache=<?= $h($sp) ?>"><?= $h($W['neu']) ?></a>
  <?php endif; ?>
  <?php if (isset($W['fehler'][$fehler])): ?><p class="stand fehler" role="alert"><?= $h($W['fehler'][$fehler]) ?></p><?php endif; ?>

  <h2><?= $h($W['gefunden']) ?></h2>
  <?php if ($zeilen): foreach ($zeilen as [$beob, $wirk]): ?>
    <div class="punkt"><b><?= $h($beob) ?></b><span><?= $h($wirk) ?></span></div>
  <?php endforeach; else: ?><p><?= $h($W['ohne']) ?></p><?php endif; ?>
  <?php if ($loesungen): ?>
    <h2><?= $h($W['loesung']) ?></h2>
    <ul class="loes"><?php foreach ($loesungen as $l): ?><li><?= $h($l) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>
  <?php if ($analyse): ?><p style="margin-top:16px"><a class="knopf leise" href="<?= $h(AkquiseAnalyse::adresse($analyse)) ?>" target="_blank" rel="noopener"><?= $h($W['analyse']) ?></a></p><?php endif; ?>

  <?php if (!$letzte || $letzte['status'] !== 'bestaetigt'): ?>
  <h2><?= $h($W['form']) ?></h2>
  <form method="post" action="<?= $h(url('akquise')) ?>">
    <?= Csrf::feld() ?><input type="hidden" name="tat" value="akq_vorort"><input type="hidden" name="firma" value="<?= $fid ?>"><input type="hidden" name="sprache" value="<?= $h($sp) ?>">
    <p style="margin:0;color:var(--d);font-size:15px"><?= $h($W['formText']) ?></p>
    <div><label class="t" for="vo_email"><?= $h($W['email']) ?></label>
      <input id="vo_email" type="email" name="email" required autocomplete="off" inputmode="email"></div>
    <label class="haken"><input id="vo_wa" type="checkbox" name="wa" value="1"><span><?= $h($W['wa']) ?></span></label>
    <div class="nurwa"><label class="t" for="vo_wanr"><?= $h($W['waNr']) ?></label>
      <input id="vo_wanr" type="tel" name="whatsapp" inputmode="tel" autocomplete="off" value="<?= $h($waVor) ?>" placeholder="+39 …"></div>
    <label class="haken"><input type="checkbox" name="ja" value="1" required><span><span class="nuremail"><?= $h($wortEmail) ?></span><span class="nurwa"><?= $h($wortWa) ?></span></span></label>
    <button class="knopf" type="submit"><?= $h($W['knopf']) ?></button>
  </form>
  <?php endif; ?>
  <a class="zurueck" href="<?= $h(url('akquise/' . $fid)) ?>">← Verwaltung</a>
</main>
</body>
</html>
