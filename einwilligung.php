<?php
declare(strict_types=1);
/* ==========================================================================
   einwilligung.php — „Ja, Sie dürfen mir schreiben“ mit Double-Opt-in
   (26.09.2026). Das Verfahren und warum es so ist: app/src/AkquiseEinwilligung.php.

   Drei Wege hierher:
     ?t=…  Formular aus dem Einwilligungs-Link (nach einem Gespräch)
     POST a=…  Kasten auf der Analyse-Seite (Schlüssel der Analyse-Seite)
     ?b=…  Bestätigungslink aus der Mail

   WARUM DIE BESTÄTIGUNG ERST AUF EINEN KNOPF FÜHRT (wie widerspruch.php):
   Sicherheitsfilter in Firmenpostfächern rufen Links vorab auf. Bestätigte
   schon der Aufruf, stünde eine Einwilligung da, die kein Mensch gegeben hat.
   Also: Aufruf zeigt den Knopf, erst der Knopf (POST) bestätigt.
   ========================================================================== */

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');

$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$t = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$b = (string) ($_GET['b'] ?? $_POST['b'] ?? '');
$a = (string) ($_POST['a'] ?? '');
$sprache = in_array($_GET['l'] ?? $_POST['l'] ?? '', ['de', 'it', 'en'], true) ? (string) ($_GET['l'] ?? $_POST['l']) : 'it';
$zustand = 'falsch';      // falsch | formular | gesendet | email | zuviel | bestaetigen | bestaetigt | abgelaufen
$firma = '';
$wortlaut = '';

if (is_file(__DIR__ . '/app/config.local.php')) {
    foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'Sprache', 'AkquiseEinwilligung', 'AkquiseAnalyse'] as $k) {
        require_once __DIR__ . "/app/src/$k.php";
    }
    date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
    try {
        /* Vom Kasten auf der Analyse-Seite: Die Analyse muss eingeschaltet und
           gueltig sein -- sonst gaebe es hier eine Hintertuer zu Firmen, die
           nie einen Brief bekommen haben. */
        if ($post && $a !== '') {
            $an = AkquiseAnalyse::oeffentlich($a, false);
            if ($an !== null) {
                $t = (string) AkquiseEinwilligung::link((int) $an['firma']['id'], 'analyse')['link_token'];
            }
        }
        if ($b !== '') {
            $e = preg_match('~^[a-f0-9]{40}$~', $b) ? Db::one('SELECT sprache, status, wortlaut FROM akq_einwilligungen WHERE doi_token = ?', [$b]) : null;
            if ($e) {
                $sprache = (string) $e['sprache'];
                $wortlautFest = (string) ($e['wortlaut'] ?? '');   // genau der Text, der angefragt wurde (auch mit WhatsApp-Nummer)
                if ($post) {
                    $r = AkquiseEinwilligung::bestaetigen($b);
                    $zustand = $r['ok'] ? 'bestaetigt' : ($r['grund'] === 'abgelaufen' ? 'abgelaufen' : 'falsch');
                    $bereich = (string) ($r['bereich'] ?? '');
                    $firma = (string) ($r['firma']['name'] ?? '');
                } else {
                    $zustand = $e['status'] === 'bestaetigt' ? 'bestaetigt' : 'bestaetigen';
                }
            }
        } elseif ($t !== '') {
            $x = AkquiseEinwilligung::ausLink($t);
            if ($x !== null) {
                $sprache = in_array($_POST['l'] ?? '', ['de', 'it', 'en'], true) ? (string) $_POST['l'] : (string) $x['e']['sprache'];
                $firma = (string) $x['f']['name'];
                $zustand = 'formular';
                if ($post && trim((string) ($_POST['webseite'] ?? '')) === '') {
                    /* Leise Bremse je Adresse: hoechstens alle 20 Sekunden eine Anfrage. */
                    $sperre = sys_get_temp_dir() . '/vecomeinw_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
                    if (is_file($sperre) && time() - filemtime($sperre) < 20) {
                        $zustand = 'zuviel';
                    } else {
                        touch($sperre);
                        $r = AkquiseEinwilligung::anfragen($t, (string) ($_POST['email'] ?? ''), !empty($_POST['ja']), $sprache,
                            (string) ($_SERVER['REMOTE_ADDR'] ?? ''), !empty($_POST['wa']) ? (string) ($_POST['whatsapp'] ?? '') : null);
                        $zustand = ['ok' => 'gesendet', 'email' => 'email', 'zuviel' => 'zuviel', 'gesperrt' => 'falsch', 'whatsapp' => 'whatsapp'][$r] ?? 'falsch';
                    }
                } elseif ($post) {
                    $zustand = 'gesendet';   // Formular-Roboter: nichts verraten, nichts tun
                }
            }
        }
        $wortlaut = ($wortlautFest ?? '') !== '' ? $wortlautFest : AkquiseEinwilligung::wortlaut($sprache);
        $wortlautWa = AkquiseEinwilligung::wortlaut($sprache, ['de' => 'der hier angegebenen Nummer', 'it' => 'indicato qui', 'en' => 'the number given here'][$sprache] ?? 'indicato qui');
    } catch (Throwable $e) {
        $zustand = 'falsch';
    }
}

$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$T = [
    'de' => ['titel' => 'Nachrichten von Vecom Design', 'lead' => 'Tragen Sie Ihre Adresse ein, wenn wir Ihnen die Auswertung und Vorschläge für {firma} per E-Mail schicken dürfen.',
             'email' => 'Ihre E-Mail-Adresse', 'knopf' => 'Bestätigungsmail anfordern',
             'gesendet' => 'Fast geschafft: Wir haben Ihnen eine Mail geschickt. Bitte klicken Sie dort auf den Link — erst dann schreiben wir Ihnen.',
             'emailFehler' => 'Bitte eine gültige Adresse eintragen und das Kästchen setzen.', 'zuviel' => 'Bitte einen Moment warten und noch einmal versuchen.',
             'bestaetigen' => 'Bitte bestätigen Sie mit einem Klick, dass wir Ihnen schreiben dürfen.', 'bknopf' => 'Ja, ich bin einverstanden',
             'bestaetigt' => 'Danke — bestätigt. Sie hören bald von uns. Sie können jederzeit mit einer kurzen Antwort widerrufen.',
             'abgelaufen' => 'Dieser Link ist abgelaufen. Tragen Sie Ihre Adresse einfach noch einmal ein.',
             'falsch' => 'Dieser Link ist nicht (mehr) gültig.', 'privacy' => 'Datenschutz', 'wa' => 'Auch per WhatsApp — dann gilt:', 'waFehler' => 'Diese WhatsApp-Nummer ist nicht lesbar. Bitte mit Vorwahl eintragen, z. B. +49 171 1234567.', 'waNummer' => 'WhatsApp-Nummer'],
    'it' => ['titel' => 'Messaggi da Vecom Design', 'lead' => 'Inserisca il suo indirizzo se possiamo inviarle via e-mail l’analisi e le proposte per {firma}.',
             'email' => 'Il suo indirizzo e-mail', 'knopf' => 'Ricevere l’e-mail di conferma',
             'gesendet' => 'Quasi fatto: le abbiamo inviato un’e-mail. Clicchi sul link che contiene — solo allora le scriveremo.',
             'emailFehler' => 'Inserisca un indirizzo valido e spunti la casella.', 'zuviel' => 'Attenda un momento e riprovi.',
             'bestaetigen' => 'Confermi con un clic che possiamo scriverle.', 'bknopf' => 'Sì, sono d’accordo',
             'bestaetigt' => 'Grazie — confermato. A presto. Può revocare in qualsiasi momento con una breve risposta.',
             'abgelaufen' => 'Questo link è scaduto. Inserisca di nuovo il suo indirizzo.',
             'falsch' => 'Questo link non è (più) valido.', 'privacy' => 'Privacy', 'wa' => 'Anche su WhatsApp — allora vale:', 'waFehler' => 'Questo numero WhatsApp non è leggibile. Lo inserisca con prefisso, es. +39 333 1234567.', 'waNummer' => 'Numero WhatsApp'],
    'en' => ['titel' => 'Messages from Vecom Design', 'lead' => 'Enter your address if we may email you the analysis and suggestions for {firma}.',
             'email' => 'Your email address', 'knopf' => 'Request confirmation email',
             'gesendet' => 'Almost done: we have sent you an email. Please click the link in it — only then will we write to you.',
             'emailFehler' => 'Please enter a valid address and tick the box.', 'zuviel' => 'Please wait a moment and try again.',
             'bestaetigen' => 'Please confirm with one click that we may write to you.', 'bknopf' => 'Yes, I agree',
             'bestaetigt' => 'Thank you — confirmed. You’ll hear from us soon. You can withdraw at any time with a short reply.',
             'abgelaufen' => 'This link has expired. Simply enter your address again.',
             'falsch' => 'This link is not (or no longer) valid.', 'privacy' => 'Privacy', 'wa' => 'Also on WhatsApp — then this applies:', 'waFehler' => 'This WhatsApp number can’t be read. Please include the country code, e.g. +39 333 1234567.', 'waNummer' => 'WhatsApp number'],
][$sprache];
if (in_array($zustand, ['falsch', 'abgelaufen'], true)) { http_response_code($zustand === 'falsch' ? 404 : 410); }
?><!doctype html>
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
<head>
<meta charset="utf-8">
<?= Sprache::skript() ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $h($T['titel']) ?></title>
<style>
  :root { color-scheme: dark; }
  body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #0a0908; color: #f3eee4;
         font: 17px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; padding: 16px; }
  .k { max-width: 520px; width: 100%; background: #131110; border: 1px solid rgba(241,211,139,.18); border-radius: 18px; padding: 28px 24px; }
  .m { letter-spacing: .2em; font-weight: 700; font-size: 13px; color: #e6c47e; }
  h1 { font-size: 24px; line-height: 1.25; margin: 10px 0 12px; }
  p { color: #c9c1b3; margin: 0 0 14px; }
  label { display: block; font-size: 14px; color: #c9c1b3; margin: 12px 0 6px; }
  input[type=email], input[type=tel] { width: 100%; box-sizing: border-box; font: inherit; padding: 12px 14px; border-radius: 10px; border: 1px solid rgba(241,211,139,.3);
         background: #0d0c0b; color: #f3eee4; }
  .haken { display: flex; gap: 10px; align-items: flex-start; font-size: 14.5px; line-height: 1.5; color: #e9e2d5; margin: 14px 0; }
  .haken input { margin-top: 4px; width: 18px; height: 18px; flex: none; }
  button { width: 100%; min-height: 50px; border: 0; border-radius: 12px; font: 650 16px/1 system-ui, sans-serif; color: #16120b; cursor: pointer;
           background: linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438); margin-top: 6px; }
  .hinweis { border-radius: 10px; padding: 12px 14px; font-size: 15px; margin: 0 0 12px; }
  .gut { background: rgba(74,222,128,.08); border: 1px solid rgba(74,222,128,.35); color: #d6f5df; }
  .schlecht { background: rgba(255,138,138,.08); border: 1px solid rgba(255,138,138,.35); color: #ffd9d9; }
  .wabe { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
  a { color: #e6c47e; } .klein { font-size: 13px; color: #8f887c; margin-top: 16px; }
  button:focus-visible, input:focus-visible, a:focus-visible { outline: 3px solid #f1d38b; outline-offset: 2px; }
  /* Ausgewählt lesbar (04.10.2026, siehe app/assets/admin.css) */
  :root{color-scheme:dark}::selection{background:#f1d38b;color:#0a0908}select option,select optgroup{background-color:#141311;color:#f7f3ea}select option:checked{background:#c8963e linear-gradient(0deg,#c8963e,#c8963e);color:#0a0908}
</style>
</head>
<body>
<main class="k">
  <div class="m">VECOM DESIGN</div>
  <h1><?= $h($T['titel']) ?></h1>
  <?php if ($zustand === 'gesendet'): ?>
    <div class="hinweis gut" role="status"><?= $h($T['gesendet']) ?></div>
  <?php elseif ($zustand === 'bestaetigt'): ?>
    <div class="hinweis gut" role="status"><?= $h($T['bestaetigt']) ?></div>
    <?php if (($bereich ?? '') !== ''): ?>
      <p style="margin:18px 0 0"><a href="<?= $h($bereich) ?>" style="display:inline-flex;align-items:center;min-height:52px;padding:0 22px;border-radius:12px;background:#e8c874;color:#16120b;font-weight:700;text-decoration:none"><?= $h(['it' => 'Aprire il mio spazio personale →', 'de' => 'Zu meinem persönlichen Bereich →', 'en' => 'Open my personal area →'][$sprache] ?? 'Zu meinem persönlichen Bereich →') ?></a></p>
      <p style="margin:8px 0 0;font-size:14px;opacity:.75"><?= $h(['it' => 'Il link è anche nell’e-mail che le abbiamo appena mandato.', 'de' => 'Den Link haben wir Ihnen auch gerade per Mail geschickt.', 'en' => 'We also just emailed you the link.'][$sprache] ?? '') ?></p>
    <?php endif; ?>
  <?php elseif ($zustand === 'bestaetigen'): ?>
    <p><?= $h($T['bestaetigen']) ?></p>
    <p style="font-size:14.5px;color:#e9e2d5">„<?= $h($wortlaut) ?>“</p>
    <form method="post" action="/einwilligung.php"><input type="hidden" name="b" value="<?= $h($b) ?>">
      <button type="submit"><?= $h($T['bknopf']) ?></button></form>
  <?php elseif (in_array($zustand, ['formular', 'email', 'zuviel', 'whatsapp'], true)): ?>
    <?php if ($zustand !== 'formular'): ?><div class="hinweis schlecht" role="alert"><?= $h($T[['email' => 'emailFehler', 'whatsapp' => 'waFehler'][$zustand] ?? 'zuviel']) ?></div><?php endif; ?>
    <p><?= $h(strtr($T['lead'], ['{firma}' => $firma])) ?></p>
    <form method="post" action="/einwilligung.php">
      <input type="hidden" name="t" value="<?= $h($t) ?>"><input type="hidden" name="l" value="<?= $h($sprache) ?>">
      <div class="wabe" aria-hidden="true"><input type="text" name="webseite" tabindex="-1" autocomplete="off"></div>
      <label for="e_mail"><?= $h($T['email']) ?></label>
      <input id="e_mail" type="email" name="email" required autocomplete="email" inputmode="email">
      <label class="haken"><input type="checkbox" name="ja" value="1" required> <span><?= $h($wortlaut) ?></span></label>
      <?php /* WhatsApp (27.09.2026): freiwillig, eigener Wortlaut; die Nummer steht dann in der Bestätigungsmail. */ ?>
      <label class="haken"><input type="checkbox" name="wa" value="1"> <span><?= $h($T['wa']) ?> „<?= $h($wortlautWa) ?>“</span></label>
      <label for="e_wa"><?= $h($T['waNummer']) ?></label>
      <input id="e_wa" type="tel" name="whatsapp" autocomplete="tel" inputmode="tel" placeholder="+39 …">
      <button type="submit"><?= $h($T['knopf']) ?></button>
    </form>
  <?php else: ?>
    <div class="hinweis schlecht"><?= $h($T[$zustand === 'abgelaufen' ? 'abgelaufen' : 'falsch']) ?></div>
  <?php endif; ?>
  <p class="klein"><a href="<?= $h(class_exists('Sprache') ? Sprache::legal($sprache, 'privacy') : 'https://vecom-design.it/legal.html#privacy') ?>"><?= $h($T['privacy']) ?></a> · <a href="https://vecom-design.it">vecom-design.it</a></p>
</main>
</body>
</html>
