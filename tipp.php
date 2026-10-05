<?php
declare(strict_types=1);
/* ==========================================================================
   tipp.php — Website-Tipp der Woche (28.09.2026, Uwe: Ja zu D5).

     POST tat=abo   Anmelden (Startseite, analisi.php): nur Bestätigungsmail
     ?b=…           Bestätigungslink → Knopf → POST bestätigt (Postfach-Filter
                    rufen Links vorab auf; erst der Knopf ist ein Mensch)
     ?ab=…          Abbestellen → Knopf → POST
   Das Verfahren: app/src/WebTipp.php. Kein Skript.
   ========================================================================== */
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
if (!is_file(__DIR__ . '/app/config.local.php')) { http_response_code(503); exit('Il servizio non è disponibile. · Der Dienst ist nicht verfügbar.'); }
foreach (['Config', 'Db', 'Status', 'Auth', 'Fmt', 'Events', 'Texte', 'Akquise', 'AkquiseGate', 'AkquiseText', 'Sprache', 'WebTipp'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
try { require_once __DIR__ . '/app/src/Einrichtung.php'; Einrichtung::selbsttaetig(false); } catch (Throwable $e) { }

$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$sprache = in_array($_GET['lang'] ?? $_POST['lang'] ?? '', ['it', 'de', 'en'], true) ? (string) ($_GET['lang'] ?? $_POST['lang']) : 'it';
$b = (string) ($_GET['b'] ?? $_POST['b'] ?? '');
$ab = (string) ($_GET['ab'] ?? $_POST['ab'] ?? '');
$zustand = 'start';   // start | gesendet | fehler | bestaetigen | bestaetigt | abgelaufen | abmelden | abgemeldet
try {
    if ($post && ($_POST['tat'] ?? '') === 'abo') {
        if (trim((string) ($_POST['website'] ?? '')) !== '') { $zustand = 'gesendet'; }   // Lockfeld: freundlich nichts tun
        else {
            $r = WebTipp::anmelden((string) ($_POST['email'] ?? ''), !empty($_POST['ja']), $sprache, (string) ($_POST['quelle'] ?? 'start'), (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
            $zustand = $r === 'ok' ? 'gesendet' : 'fehler';
        }
    } elseif ($b !== '') {
        $a = preg_match('~^[a-f0-9]{40}$~', $b) ? Db::one('SELECT sprache, status FROM akq_tipp_abos WHERE doi_token = ?', [$b]) : null;
        if ($a) { $sprache = (string) $a['sprache']; }
        if ($post && $a) { $r = WebTipp::bestaetigen($b); $zustand = $r['ok'] ? 'bestaetigt' : 'abgelaufen'; }
        else { $zustand = !$a ? 'abgelaufen' : ($a['status'] === 'aktiv' ? 'bestaetigt' : 'bestaetigen'); }
    } elseif ($ab !== '') {
        $a = preg_match('~^[a-f0-9]{40}$~', $ab) ? Db::one('SELECT sprache, status FROM akq_tipp_abos WHERE token = ?', [$ab]) : null;
        if ($a) { $sprache = (string) $a['sprache']; }
        if ($post && $a) { WebTipp::abmelden($ab); $zustand = 'abgemeldet'; }
        else { $zustand = !$a ? 'abgelaufen' : ($a['status'] === 'abgemeldet' ? 'abgemeldet' : 'abmelden'); }
    }
} catch (Throwable $e) { $zustand = 'fehler'; }

$T = [
    'it' => ['titel' => 'Il consiglio della settimana per il suo sito', 'lead' => 'Una volta alla settimana, un consiglio breve e pratico per il suo sito — gratis, annullabile con un clic.', 'email' => 'La sua e-mail', 'knopf' => 'Ricevere i consigli',
             'gesendet' => 'Fatto! Le abbiamo scritto: apra l’e-mail e tocchi il link di conferma.', 'fehler' => 'Controlli l’indirizzo e la spunta, poi riprovi.', 'bestaetigen' => 'Confermi il consiglio settimanale:', 'bknopf' => 'Sì, confermo',
             'bestaetigt' => 'Grazie! È confermato: il primo consiglio arriva il prossimo martedì.', 'abgelaufen' => 'Questo link non è più valido. Può iscriversi di nuovo qui sotto.', 'abmelden' => 'Non vuole più ricevere i consigli?', 'aknopf' => 'Annullare l’iscrizione', 'abgemeldet' => 'Fatto: non riceverà più i consigli.', 'analisi' => 'Nel frattempo: analisi gratuita del suo sito →'],
    'de' => ['titel' => 'Der Website-Tipp der Woche', 'lead' => 'Einmal pro Woche ein kurzer, praktischer Tipp für Ihre Website — kostenlos, mit einem Klick abbestellbar.', 'email' => 'Ihre E-Mail-Adresse', 'knopf' => 'Tipps erhalten',
             'gesendet' => 'Erledigt! Wir haben Ihnen geschrieben: Öffnen Sie die Mail und tippen Sie auf den Bestätigungslink.', 'fehler' => 'Bitte Adresse und Häkchen prüfen und noch einmal versuchen.', 'bestaetigen' => 'Bitte bestätigen Sie den Website-Tipp der Woche:', 'bknopf' => 'Ja, bestätigen',
             'bestaetigt' => 'Danke! Bestätigt: Der erste Tipp kommt am nächsten Dienstag.', 'abgelaufen' => 'Dieser Link gilt nicht mehr. Sie können sich unten neu eintragen.', 'abmelden' => 'Keine Tipps mehr bekommen?', 'aknopf' => 'Abbestellen', 'abgemeldet' => 'Erledigt: Sie bekommen keine Tipps mehr.', 'analisi' => 'Solange: kostenlose Analyse Ihrer Website →'],
    'en' => ['titel' => 'The website tip of the week', 'lead' => 'Once a week, one short, practical tip for your website — free, unsubscribe with one click.', 'email' => 'Your email address', 'knopf' => 'Get the tips',
             'gesendet' => 'Done! We wrote to you: open the email and tap the confirmation link.', 'fehler' => 'Please check the address and the tick box, then try again.', 'bestaetigen' => 'Please confirm the weekly website tip:', 'bknopf' => 'Yes, confirm',
             'bestaetigt' => 'Thank you! Confirmed: the first tip arrives next Tuesday.', 'abgelaufen' => 'This link is no longer valid. You can sign up again below.', 'abmelden' => 'No more tips?', 'aknopf' => 'Unsubscribe', 'abgemeldet' => 'Done: you will not receive any more tips.', 'analisi' => 'Meanwhile: free analysis of your website →'],
][$sprache];
?><!doctype html>
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
<head>
<meta charset="utf-8">
<?= Sprache::skript() ?><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title><?= $h($T['titel']) ?> — Vecom Design</title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<style>
  :root{--g:#0a0908;--f:#14110d;--f2:#1b1712;--t:#f7f3ea;--d:#b4ada2;--l:#8b847a;--a:#f1d38b;--li:rgba(255,255,255,.09);--li2:rgba(255,255,255,.16);--gut:#3fb56b;--rot:#e5534b}
  *{box-sizing:border-box}
  body{margin:0;background:radial-gradient(1200px 600px at 50% -200px,rgba(241,211,139,.09),transparent 70%),var(--g);color:var(--t);font:16px/1.6 'Inter',system-ui,sans-serif;min-height:100vh}
  main{max-width:620px;margin:0 auto;padding:28px 16px 56px}
  .marke{display:inline-flex;gap:8px;align-items:center;font:800 14px/1 'Archivo',sans-serif;letter-spacing:.08em;color:var(--t);text-decoration:none;margin-bottom:34px}.marke b{color:var(--a)}
  h1{font:800 clamp(28px,6.5vw,38px)/1.12 'Archivo',sans-serif;margin:0 0 12px;text-wrap:balance}
  .lead{color:var(--d);font-size:17px;margin:0 0 20px}
  form{display:grid;gap:12px;background:var(--f);border:1px solid var(--li);border-radius:18px;padding:20px}
  input[type=email]{width:100%;font-size:17px;padding:14px 15px;border-radius:12px;border:1px solid var(--li2);background:var(--f2);color:var(--t)}
  label.t{font-size:13.5px;color:var(--d)}
  .haken{display:flex;gap:11px;align-items:flex-start;font-size:14.5px;line-height:1.5;color:var(--d)}
  .haken input{width:22px;height:22px;flex:none;margin-top:2px;accent-color:#d9b25e}
  .knopf{display:inline-flex;align-items:center;justify-content:center;min-height:52px;padding:0 22px;border-radius:12px;border:0;cursor:pointer;font:700 16px/1 'Inter',sans-serif;background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438);color:#16120b}
  .lock{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
  .hinweis{border-radius:14px;padding:14px 16px;margin:0 0 16px;font-weight:600}
  .hinweis.gut{border:1px solid var(--gut);color:#8fe3a9}.hinweis.schlecht{border:1px solid var(--rot);color:#ffb3aa}
  .weiter{display:inline-block;margin-top:20px;color:var(--a)}
  /* Ausgewählt lesbar (04.10.2026, siehe app/assets/admin.css) */
  :root{color-scheme:dark}::selection{background:#f1d38b;color:#0a0908}select option,select optgroup{background-color:#0b1225;color:#f7f3ea}select option:checked{background:#c8963e linear-gradient(0deg,#c8963e,#c8963e);color:#0a0908}
</style>
</head>
<body>
<main>
  <a class="marke" href="/<?= $sprache === 'it' ? '' : $h($sprache) . '/' ?>"><b>VECOM</b> DESIGN</a>
  <h1><?= $h($T['titel']) ?></h1>
  <?php if (in_array($zustand, ['gesendet', 'bestaetigt', 'abgemeldet'], true)): ?>
    <p class="hinweis gut" role="status"><?= $h($T[$zustand]) ?></p>
  <?php elseif (in_array($zustand, ['fehler', 'abgelaufen'], true)): ?>
    <p class="hinweis schlecht" role="alert"><?= $h($T[$zustand]) ?></p>
  <?php endif; ?>
  <?php if ($zustand === 'bestaetigen'): ?>
    <form method="post" action="/tipp.php"><input type="hidden" name="b" value="<?= $h($b) ?>">
      <p style="margin:0"><?= $h($T['bestaetigen']) ?></p><p style="margin:0;color:var(--d);font-size:14.5px">„<?= $h(WebTipp::wortlaut($sprache)) ?>“</p>
      <button class="knopf" type="submit"><?= $h($T['bknopf']) ?></button></form>
  <?php elseif ($zustand === 'abmelden'): ?>
    <form method="post" action="/tipp.php"><input type="hidden" name="ab" value="<?= $h($ab) ?>">
      <p style="margin:0"><?= $h($T['abmelden']) ?></p><button class="knopf" type="submit"><?= $h($T['aknopf']) ?></button></form>
  <?php elseif (in_array($zustand, ['start', 'fehler', 'abgelaufen'], true)): ?>
    <p class="lead"><?= $h($T['lead']) ?></p>
    <form method="post" action="/tipp.php?lang=<?= $h($sprache) ?>">
      <input type="hidden" name="tat" value="abo"><input type="hidden" name="lang" value="<?= $h($sprache) ?>"><input type="hidden" name="quelle" value="tipp">
      <div class="lock" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <div><label class="t" for="tp_email"><?= $h($T['email']) ?></label><input id="tp_email" type="email" name="email" required autocomplete="email" inputmode="email"></div>
      <label class="haken"><input type="checkbox" name="ja" value="1" required><span><?= $h(WebTipp::wortlaut($sprache)) ?></span></label>
      <button class="knopf" type="submit"><?= $h($T['knopf']) ?></button>
    </form>
  <?php endif; ?>
  <a class="weiter" href="/analisi.php?lang=<?= $h($sprache) ?>"><?= $h($T['analisi']) ?></a>
</main>
</body>
</html>
