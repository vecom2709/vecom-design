<?php
declare(strict_types=1);
/* ==========================================================================
   widerspruch.php — "Keine weiteren Nachrichten".

   Der Link steht unter jeder Akquise-Mail und im List-Unsubscribe-Kopf.

   WARUM DER KLICK ERST AUF EINEN KNOPF FUEHRT
   Sicherheitsfilter in Firmenpostfaechern rufen Links in eingehenden Mails
   vorab auf. Wuerde schon der Aufruf sperren, waere das zwar kein Schaden
   (gesperrt ist die sichere Seite), aber die Bestaetigung "Sie wurden
   ausgetragen" saehe dann nie ein Mensch. Deshalb: Aufruf zeigt den Knopf,
   der Knopf (POST) traegt aus. Mailprogramme mit One-Click (RFC 8058)
   schicken selbst ein POST und landen direkt beim Austragen.

   Der Schluessel in der Adresse ist die einzige Berechtigung -- er oeffnet
   nichts, er kann nur sperren.
   ========================================================================== */

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');

$token = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$sprache = in_array($_GET['l'] ?? '', ['de', 'it', 'en'], true) ? (string) $_GET['l'] : 'it';
$erledigt = false;
$ungueltig = !preg_match('~^[a-f0-9]{40}$~', $token);

if (!$ungueltig && is_file(__DIR__ . '/app/config.local.php')) {
    foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'AkquiseVersand'] as $k) {
        require_once __DIR__ . "/app/src/$k.php";
    }
    date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
    try {
        $s = Db::wert('SELECT t.sprache FROM akq_versand v LEFT JOIN akq_vorlagen t ON t.id = v.vorlage_id WHERE v.abmelde_token = ?', [$token], null);
        if ($s === null) {
            $ungueltig = true;
        } else {
            $sprache = in_array($s, ['de', 'it', 'en'], true) ? (string) $s : $sprache;
            if ($post) { $erledigt = AkquiseVersand::widerspruch($token) !== null; }
        }
    } catch (Throwable $e) {
        $ungueltig = true;
    }
} elseif (!$ungueltig) {
    $ungueltig = true;
}

$T = [
    'de' => ['titel' => 'Keine weiteren Nachrichten', 'frage' => 'Möchten Sie keine weiteren Nachrichten von Vecom Design erhalten?',
             'knopf' => 'Ja, keine Nachrichten mehr', 'fertig' => 'Erledigt. Sie erhalten von uns keine weiteren Nachrichten — auf keinem Weg. Entschuldigen Sie die Störung.',
             'falsch' => 'Dieser Link ist nicht gültig. Schreiben Sie uns einfach eine kurze Antwort an kontakt@vecom-design.it — wir tragen Sie sofort aus.'],
    'it' => ['titel' => 'Nessun altro messaggio', 'frage' => 'Non desiderate ricevere altri messaggi da Vecom Design?',
             'knopf' => 'Sì, nessun altro messaggio', 'fertig' => 'Fatto. Non riceverete più messaggi da noi, su nessun canale. Ci scusiamo per il disturbo.',
             'falsch' => 'Questo link non è valido. Basta una breve risposta a kontakt@vecom-design.it e vi cancelliamo subito.'],
    'en' => ['titel' => 'No further messages', 'frage' => 'Would you rather not receive further messages from Vecom Design?',
             'knopf' => 'Yes, no more messages', 'fertig' => 'Done. You will not hear from us again, on any channel. Sorry for the interruption.',
             'falsch' => 'This link is not valid. Just send a short reply to kontakt@vecom-design.it and we will remove you straight away.'],
][$sprache];
if ($ungueltig) { http_response_code(404); }
?><!doctype html>
<html lang="<?= htmlspecialchars($sprache) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= htmlspecialchars($T['titel']) ?> — Vecom Design</title>
<style>
  :root { color-scheme: dark; }
  body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #0b0f17; color: #e8ecf3;
         font: 17px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; padding: 16px; box-sizing: border-box; }
  main { max-width: 520px; width: 100%; background: #121826; border: 1px solid #1f2a3d; border-radius: 16px; padding: 32px 28px; }
  .marke { letter-spacing: .18em; font-weight: 700; font-size: 13px; color: #7fa7ff; margin-bottom: 18px; }
  h1 { font-size: 24px; margin: 0 0 12px; }
  p { margin: 0 0 22px; color: #c3cad6; }
  button { font: inherit; font-weight: 600; background: #2f6bff; color: #fff; border: 0; border-radius: 10px; padding: 14px 20px;
           width: 100%; cursor: pointer; min-height: 48px; }
  button:focus-visible { outline: 3px solid #9fbcff; outline-offset: 2px; }
</style>
</head>
<body>
<main>
  <div class="marke">VECOM DESIGN</div>
  <h1><?= htmlspecialchars($T['titel']) ?></h1>
  <?php if ($ungueltig): ?>
    <p><?= htmlspecialchars($T['falsch']) ?></p>
  <?php elseif ($erledigt): ?>
    <p><?= htmlspecialchars($T['fertig']) ?></p>
  <?php else: ?>
    <p><?= htmlspecialchars($T['frage']) ?></p>
    <form method="post" action="widerspruch.php?t=<?= htmlspecialchars($token) ?>">
      <input type="hidden" name="t" value="<?= htmlspecialchars($token) ?>">
      <button type="submit"><?= htmlspecialchars($T['knopf']) ?></button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
