<?php
declare(strict_types=1);
/* ==========================================================================
   analisi.php — Kurz-Check und Ja in einem Schritt (28.09.2026, Uwe: Ja zu
   Z2 und Z6 „Knopf ‚Analisi gratuita‘ überall“).

     Schritt 1  Adresse eingeben → sofort die Ampel der sechs Punkte
     Schritt 2  darunter: E-Mail, auf Wunsch WhatsApp, Häkchen → nur die
                Bestätigungsmail. Ab dem Klick darin läuft alles automatisch
                (Folge-Nachrichten, persönlicher Bereich).

   Kein eigenes Skript (CSP: nur sprache.js vom selben Server). Gegen Roboter: Lockfeld und der
   unterschriebene Zeitstempel wie beim Website-Check; der Kurz-Check bremst
   sich selbst (PartnerSeite::kurzcheck).
   ========================================================================== */

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; script-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
if (!is_file(__DIR__ . '/app/config.local.php')) { http_response_code(503); exit('Il servizio non è disponibile. · Der Dienst ist nicht verfügbar.'); }
foreach (['Config', 'Db', 'Status', 'Auth', 'Fmt', 'Events', 'Texte', 'Sprache', 'Akquise', 'AkquiseGate', 'AkquiseText', 'AkquiseCheck', 'AkquiseEinwilligung', 'AkquiseKurz', 'PartnerSeite', 'Partner', 'WebTipp'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
try { require_once __DIR__ . '/app/src/Einrichtung.php'; Einrichtung::selbsttaetig(false); } catch (Throwable $e) { }

$sprache = Sprache::ausAnfrage();
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }
$T = [
    'it' => ['meta' => 'Analisi gratuita del suo sito — Vecom Design', 'zeile' => 'Analisi gratuita', 'h1' => 'Com’è messo il suo sito?', 'lead' => 'Inserisca l’indirizzo: in pochi secondi vede sei punti con semaforo. Gratis, senza registrazione.',
             'feld' => 'Indirizzo del sito, per es. trattoria-rossi.it', 'pruefen' => 'Verificare', 'ergebnis' => 'Risultato per {host}', 'stand' => ['gut' => 'va bene', 'hinweis' => 'da migliorare', 'schlecht' => 'problema'],
             'jaTitel' => 'Vuole l’analisi completa e il suo spazio personale?', 'jaText' => 'Le mandiamo l’analisi dettagliata con i consigli e apriamo il suo spazio personale su Vecom Design. Prima riceve solo una e-mail di conferma.',
             'betrieb' => 'Nome dell’attività (facoltativo)', 'email' => 'La sua e-mail', 'wa' => 'Anche su WhatsApp', 'waNr' => 'Numero WhatsApp', 'knopf' => 'Ricevere l’analisi completa',
             'gesendet' => 'Fatto! Le abbiamo scritto a {email}. Apra l’e-mail e tocchi il link di conferma: poi arrivano l’analisi completa e il suo spazio personale.',
             'waLink' => 'Preferisce WhatsApp? Ci scriva e riceve l’analisi lì.', 'fehler' => ['adresse' => 'Questo indirizzo non sembra un sito raggiungibile. Lo controlli.', 'warten' => 'Un attimo: può verificare di nuovo tra pochi secondi.', 'zuviel' => 'Per oggi sono state fatte molte verifiche. Riprovi domani.',
             'email' => 'Controlli l’indirizzo e-mail e la spunta.', 'whatsapp' => 'Il numero WhatsApp non è leggibile.', 'gesperrt' => 'Per questo indirizzo non è possibile.', 'zeit' => 'Ci è sfuggito qualcosa: riprovi.'], 'waText' => 'Buongiorno, vorrei l’analisi gratuita del mio sito'],
    'de' => ['meta' => 'Kostenlose Analyse Ihrer Website — Vecom Design', 'zeile' => 'Kostenlose Analyse', 'h1' => 'Wie steht Ihre Website da?', 'lead' => 'Adresse eingeben: In wenigen Sekunden sehen Sie sechs Punkte als Ampel. Kostenlos, ohne Anmeldung.',
             'feld' => 'Adresse der Website, z. B. trattoria-rossi.it', 'pruefen' => 'Prüfen', 'ergebnis' => 'Ergebnis für {host}', 'stand' => ['gut' => 'gut', 'hinweis' => 'verbesserbar', 'schlecht' => 'Problem'],
             'jaTitel' => 'Möchten Sie die ausführliche Analyse und Ihren persönlichen Bereich?', 'jaText' => 'Wir schicken Ihnen die ausführliche Analyse mit Tipps und öffnen Ihren persönlichen Bereich bei Vecom Design. Zuerst kommt nur eine Bestätigungsmail.',
             'betrieb' => 'Name des Betriebs (freiwillig)', 'email' => 'Ihre E-Mail-Adresse', 'wa' => 'Auch per WhatsApp', 'waNr' => 'WhatsApp-Nummer', 'knopf' => 'Ausführliche Analyse erhalten',
             'gesendet' => 'Erledigt! Wir haben an {email} geschrieben. Öffnen Sie die Mail und tippen Sie auf den Bestätigungslink – dann kommen die ausführliche Analyse und Ihr persönlicher Bereich.',
             'waLink' => 'Lieber WhatsApp? Schreiben Sie uns, dann bekommen Sie die Analyse dort.', 'fehler' => ['adresse' => 'Diese Adresse sieht nicht nach einer erreichbaren Website aus. Bitte prüfen.', 'warten' => 'Einen Moment: In ein paar Sekunden können Sie wieder prüfen.', 'zuviel' => 'Für heute wurde schon oft geprüft. Bitte morgen noch einmal.',
             'email' => 'Bitte E-Mail-Adresse und Häkchen prüfen.', 'whatsapp' => 'Die WhatsApp-Nummer ist nicht lesbar.', 'gesperrt' => 'Für diese Adresse nicht möglich.', 'zeit' => 'Da ist etwas schiefgegangen: bitte noch einmal.'], 'waText' => 'Guten Tag, ich möchte die kostenlose Analyse meiner Website'],
    'en' => ['meta' => 'Free analysis of your website — Vecom Design', 'zeile' => 'Free analysis', 'h1' => 'How is your website doing?', 'lead' => 'Enter the address: in a few seconds you see six points as traffic lights. Free, no sign-up.',
             'feld' => 'Website address, e.g. trattoria-rossi.it', 'pruefen' => 'Check', 'ergebnis' => 'Result for {host}', 'stand' => ['gut' => 'good', 'hinweis' => 'could be better', 'schlecht' => 'problem'],
             'jaTitel' => 'Would you like the full analysis and your personal area?', 'jaText' => 'We send you the detailed analysis with tips and open your personal area at Vecom Design. First you only receive a confirmation email.',
             'betrieb' => 'Business name (optional)', 'email' => 'Your email address', 'wa' => 'Also on WhatsApp', 'waNr' => 'WhatsApp number', 'knopf' => 'Get the full analysis',
             'gesendet' => 'Done! We wrote to {email}. Open the email and tap the confirmation link – then the full analysis and your personal area arrive.',
             'waLink' => 'Prefer WhatsApp? Message us and get the analysis there.', 'fehler' => ['adresse' => 'This address doesn’t look like a reachable website. Please check it.', 'warten' => 'One moment: you can check again in a few seconds.', 'zuviel' => 'Many checks have been run today. Please try again tomorrow.',
             'email' => 'Please check the email address and the tick box.', 'whatsapp' => 'The WhatsApp number cannot be read.', 'gesperrt' => 'Not possible for this address.', 'zeit' => 'Something went wrong: please try again.'], 'waText' => 'Hello, I would like the free analysis of my website'],
][$sprache];
$P = Texte::PARTNER_CHECK['punkte'];

$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$url = mb_substr(trim((string) ($_POST['url'] ?? $_GET['url'] ?? '')), 0, 200);
$kc = null; $fehler = ''; $gesendet = '';
try {
    if ($post && ($_POST['tat'] ?? '') === 'pruefen') {
        $kc = PartnerSeite::kurzcheck($url);
        if (!$kc['ok']) { $fehler = (string) $kc['grund']; $kc = null; }
    } elseif ($post && ($_POST['tat'] ?? '') === 'ja') {
        $kc = json_decode((string) ($_POST['ampel'] ?? ''), true);
        $kc = is_array($kc) && isset($kc['host'], $kc['punkte']) ? $kc : null;
        if (trim((string) ($_POST['homepage'] ?? '')) !== '' || !AkquiseCheck::stempelGut((string) ($_POST['z'] ?? ''))) {
            $fehler = 'zeit';
        } else {
            $r = AkquiseKurz::einwilligen(['url' => $url, 'betrieb' => (string) ($_POST['betrieb'] ?? ''), 'email' => (string) ($_POST['email'] ?? ''),
                'whatsapp' => !empty($_POST['wa']) ? (string) ($_POST['whatsapp'] ?? '') : null, 'ja' => !empty($_POST['ja']), 'sprache' => $sprache,
                'quelle' => 'check', 'partner' => AkquiseCheck::partnerAusBesuch(), 'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '')]);
            if ($r === 'ok') { $gesendet = mb_strtolower(trim((string) $_POST['email'])); } else { $fehler = $r; }
        }
    }
} catch (Throwable $e) { $fehler = 'zeit'; }
/* Der Ampel-Stand reist unverändert mit ins zweite Formular -- nur zur Anzeige, nichts davon wird gespeichert. */
$ampelJson = $kc ? json_encode(['host' => (string) $kc['host'], 'punkte' => array_values(array_filter(array_map(static fn($p) => ['was' => (string) ($p['was'] ?? ''), 'stand' => (string) ($p['stand'] ?? '')], (array) $kc['punkte']), static fn($p) => isset($P[$p['was']])))], JSON_UNESCAPED_UNICODE) : '';
$waNummer = AkquiseGate::einstellung('wa_anzeige', '');
$waLink = $waNummer !== '' ? 'https://wa.me/' . $waNummer . '?text=' . rawurlencode($T['waText']) : '';
$wortEmail = AkquiseEinwilligung::wortlaut($sprache);
$wortWa = AkquiseEinwilligung::wortlaut($sprache, ['it' => 'indicato sopra', 'de' => 'der oben angegebenen Nummer', 'en' => 'the number given above'][$sprache]);
?><!doctype html>
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
<head>
<meta charset="utf-8">
<?= Sprache::skript() ?><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($T['meta']) ?></title>
<meta name="description" content="<?= $h($T['lead']) ?>">
<link rel="canonical" href="https://vecom-design.it/analisi.php?lang=<?= $h($sprache) ?>">
<link rel="stylesheet" href="/assets/css/fonts.css">
<style>
  :root{--g:#0a0908;--f:#14110d;--f2:#1b1712;--t:#f7f3ea;--d:#b4ada2;--l:#8b847a;--a:#f1d38b;--li:rgba(255,255,255,.09);--li2:rgba(255,255,255,.16);--gut:#3fb56b;--mittel:#e0b341;--rot:#e5534b}
  *{box-sizing:border-box}
  body{margin:0;background:radial-gradient(1200px 600px at 50% -200px,rgba(241,211,139,.09),transparent 70%),var(--g);color:var(--t);font:16px/1.6 'Inter',system-ui,sans-serif;min-height:100vh}
  main{max-width:720px;margin:0 auto;padding:28px 16px 56px}
  .kopf{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:34px;flex-wrap:wrap}
  .marke{display:flex;gap:8px;align-items:center;font:800 14px/1 'Archivo',sans-serif;letter-spacing:.08em;color:var(--t);text-decoration:none}
  .marke img{width:40px;height:auto}.marke b{color:var(--a)}
  .sprachen{display:flex;gap:4px}
  .sprachen a{min-width:44px;min-height:36px;display:inline-grid;place-items:center;border-radius:8px;color:var(--l);text-decoration:none;font-size:13px;font-weight:600}
  .sprachen a[aria-current]{color:var(--t);background:var(--f2)}
  .zeile{font-size:12.5px;text-transform:uppercase;letter-spacing:.12em;color:var(--a);font-weight:600;margin:0 0 10px}
  h1{font:800 clamp(30px,7vw,42px)/1.1 'Archivo',sans-serif;margin:0 0 12px;text-wrap:balance}
  h2{font:700 19px/1.3 'Archivo',sans-serif;margin:0 0 10px}
  .lead{color:var(--d);font-size:17px;margin:0 0 20px}
  .karte{background:var(--f);border:1px solid var(--li);border-radius:18px;padding:20px;margin:18px 0 0}
  form.pruefen{display:flex;gap:8px;flex-wrap:wrap}
  input[type=text],input[type=email],input[type=tel]{width:100%;font-size:17px;padding:14px 15px;border-radius:12px;border:1px solid var(--li2);background:var(--f2);color:var(--t)}
  form.pruefen input{flex:1 1 240px;width:auto}
  .knopf{display:inline-flex;align-items:center;justify-content:center;min-height:52px;padding:0 22px;border-radius:12px;border:0;cursor:pointer;font:700 16px/1 'Inter',sans-serif;
         background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438);color:#16120b;text-decoration:none}
  .knopf.leise{background:transparent;color:var(--t);border:1px solid var(--li2)}
  .ampel{list-style:none;margin:12px 0 0;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:8px}
  .ampel li{display:grid;grid-template-columns:auto 1fr;column-gap:10px;align-items:center;padding:11px 13px;border:1px solid var(--li);border-radius:12px;background:var(--f2)}
  .ampel i{grid-row:1/3;width:14px;height:14px;border-radius:50%;background:var(--mittel)}
  .ampel .st-gut i{background:var(--gut)}.ampel .st-schlecht i{background:var(--rot)}
  .ampel b{font-size:15px}.ampel span{font-size:13.5px;color:var(--d)}
  form.ja{display:grid;gap:12px}
  label.t{font-size:13.5px;color:var(--d)}
  .haken{display:flex;gap:11px;align-items:flex-start;font-size:14.5px;line-height:1.5;color:var(--d)}
  .haken input{width:22px;height:22px;flex:none;margin-top:2px;accent-color:#d9b25e}
  .nurwa{display:none}
  form:has(#a_wa:checked) .nurwa{display:block}
  form:has(#a_wa:checked) .nuremail{display:none}
  .lock{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
  .hinweis{border-radius:14px;padding:14px 16px;margin:16px 0 0;font-weight:600}
  .hinweis.gut{border:1px solid var(--gut);color:#8fe3a9}
  .hinweis.schlecht{border:1px solid var(--rot);color:#ffb3aa}
  .wa{display:inline-block;margin-top:18px;color:var(--a)}
  .fuss{margin-top:34px;font-size:12.5px;color:var(--l)}
</style>
</head>
<body>
<main>
  <div class="kopf">
    <a class="marke" href="/<?= $sprache === 'it' ? '' : $h($sprache) . '/' ?>"><img src="/assets/img/logo-mark.webp?v=gold2609" alt="" width="40" height="32"><span><b>VECOM</b> DESIGN</span></a>
    <nav class="sprachen" aria-label="Lingua / Sprache / Language"><?php foreach (['it' => 'IT', 'de' => 'DE', 'en' => 'EN'] as $l => $w): ?><a href="?lang=<?= $l ?>"<?= $l === $sprache ? ' aria-current="true"' : '' ?>><?= $w ?></a><?php endforeach; ?></nav>
  </div>
  <p class="zeile"><?= $h($T['zeile']) ?></p>
  <h1><?= $h($T['h1']) ?></h1>
  <p class="lead"><?= $h($T['lead']) ?></p>

  <?php if ($gesendet !== ''): ?>
    <p class="hinweis gut" role="status"><?= $h(strtr($T['gesendet'], ['{email}' => $gesendet])) ?></p>
  <?php else: ?>
  <form class="pruefen" method="post" action="analisi.php?lang=<?= $h($sprache) ?>">
    <input type="hidden" name="tat" value="pruefen">
    <label for="a_url" class="lock"><?= $h($T['feld']) ?></label>
    <input id="a_url" type="text" name="url" inputmode="url" autocomplete="url" required maxlength="200" placeholder="<?= $h($T['feld']) ?>" value="<?= $h($url) ?>">
    <button class="knopf" type="submit"><?= $h($T['pruefen']) ?></button>
  </form>
  <?php if ($fehler !== ''): ?><p class="hinweis schlecht" role="alert"><?= $h($T['fehler'][$fehler] ?? $T['fehler']['zeit']) ?></p><?php endif; ?>

  <?php if ($kc): ?>
    <div class="karte" role="status">
      <h2><?= $h(strtr($T['ergebnis'], ['{host}' => (string) $kc['host']])) ?></h2>
      <ul class="ampel"><?php foreach ((array) $kc['punkte'] as $p): if (!isset($P[$p['was'] ?? ''])) { continue; } ?>
        <li class="st-<?= $h((string) $p['stand']) ?>"><i aria-hidden="true"></i><b><?= $h(Texte::h($P[$p['was']]['titel'], $sprache)) ?></b><span><?= $h($T['stand'][$p['stand']] ?? $T['stand']['hinweis']) ?></span></li>
      <?php endforeach; ?></ul>
    </div>
    <div class="karte">
      <h2><?= $h($T['jaTitel']) ?></h2>
      <p style="margin:0 0 14px;color:var(--d)"><?= $h($T['jaText']) ?></p>
      <form class="ja" method="post" action="analisi.php?lang=<?= $h($sprache) ?>">
        <input type="hidden" name="tat" value="ja"><input type="hidden" name="url" value="<?= $h($url) ?>"><input type="hidden" name="ampel" value="<?= $h($ampelJson) ?>">
        <input type="hidden" name="z" value="<?= $h(AkquiseCheck::stempel()) ?>">
        <div class="lock" aria-hidden="true"><label>Homepage <input type="text" name="homepage" tabindex="-1" autocomplete="off"></label></div>
        <div><label class="t" for="a_betrieb"><?= $h($T['betrieb']) ?></label><input id="a_betrieb" type="text" name="betrieb" maxlength="190" autocomplete="organization"></div>
        <div><label class="t" for="a_email"><?= $h($T['email']) ?></label><input id="a_email" type="email" name="email" required autocomplete="email" inputmode="email"></div>
        <label class="haken"><input id="a_wa" type="checkbox" name="wa" value="1"><span><?= $h($T['wa']) ?></span></label>
        <div class="nurwa"><label class="t" for="a_wanr"><?= $h($T['waNr']) ?></label><input id="a_wanr" type="tel" name="whatsapp" inputmode="tel" autocomplete="tel" placeholder="+39 …"></div>
        <label class="haken"><input type="checkbox" name="ja" value="1" required><span><span class="nuremail"><?= $h($wortEmail) ?></span><span class="nurwa"><?= $h($wortWa) ?></span></span></label>
        <button class="knopf" type="submit"><?= $h($T['knopf']) ?></button>
      </form>
    </div>
  <?php endif; ?>
  <?php endif; ?>
  <?php if ($waLink !== '' && $gesendet === ''): ?><a class="wa" href="<?= $h($waLink) ?>" target="_blank" rel="noopener"><?= $h($T['waLink']) ?></a><?php endif; ?>
  <?php $TT = ['it' => ['Il consiglio della settimana', 'Una volta alla settimana un consiglio breve per il suo sito, gratis.', 'Iscrivermi'],
               'de' => ['Der Website-Tipp der Woche', 'Einmal pro Woche ein kurzer Tipp für Ihre Website, kostenlos.', 'Eintragen'],
               'en' => ['The website tip of the week', 'Once a week, one short tip for your website, free.', 'Sign up']][$sprache]; ?>
  <form class="karte ja" method="post" action="/tipp.php?lang=<?= $h($sprache) ?>">
    <h2><?= $h($TT[0]) ?></h2><p style="margin:0;color:var(--d)"><?= $h($TT[1]) ?></p>
    <input type="hidden" name="tat" value="abo"><input type="hidden" name="lang" value="<?= $h($sprache) ?>"><input type="hidden" name="quelle" value="analisi">
    <div class="lock" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
    <div><label class="t" for="tp_email"><?= $h($T['email']) ?></label><input id="tp_email" type="email" name="email" required autocomplete="email" inputmode="email"></div>
    <label class="haken"><input type="checkbox" name="ja" value="1" required><span><?= $h(WebTipp::wortlaut($sprache)) ?></span></label>
    <button class="knopf leise" type="submit"><?= $h($TT[2]) ?></button>
  </form>
  <p class="fuss">Vecom Design · Aragona (AG) · <a href="<?= $h(Sprache::legal($sprache, 'privacy')) ?>" style="color:var(--l)">Privacy</a></p>
</main>
</body>
</html>
