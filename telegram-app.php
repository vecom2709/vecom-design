<?php
declare(strict_types=1);
/* ==========================================================================
   telegram-app.php — Einstieg der Mini-App „Preis-Rechner“ (30.09.2026).

   Telegram öffnet diese Adresse, wenn jemand im Kanal auf „Preis berechnen“
   tippt (t.me/BOT/KURZNAME?startapp=kanal-de-preis). Sie tut genau zwei
   Dinge und zeigt selbst nichts:

     1. ohne ?neu: kurz im Gerät nachsehen, ob es schon einen offenen
        Rechner gibt (localStorage) — wer das Fenster schließt und wieder
        öffnet, macht dort weiter, statt jedes Mal neu anzufangen;
     2. mit ?neu: einen Bedarf anlegen und nach bedarf.php weiterleiten.

   Alles Weitere ist der Konfigurator der Website (siehe TelegramApp.php).
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('Der Rechner ist derzeit nicht erreichbar.'); }
foreach (['Config', 'Db', 'Events', 'TelegramApp'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }

header('Content-Security-Policy: ' . TelegramApp::EINBETTEN);
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$param = strtolower((string) ($_GET['tgWebAppStartParam'] ?? $_GET['s'] ?? ''));
$start = TelegramApp::lesen($param, strtolower((string) ($_GET['lang'] ?? '')));
$q = static fn(array $p): string => http_build_query($p, '', '&', PHP_QUERY_RFC3986);

if (isset($_GET['neu'])) {
    try {
        $token = TelegramApp::neuerBedarf($start);
    } catch (Throwable $e) {
        try { Events::melden('bedarf_fehler', 'Telegram-Rechner: Bedarf nicht angelegt', 'schlecht', $e->getMessage(), 'anfragen'); } catch (Throwable $e2) { }
        http_response_code(503);
        exit('Der Rechner ist gerade nicht erreichbar. Bitte später noch einmal.');
    }
    header('Location: /bedarf.php?' . $q(['t' => $token, 'lang' => $start['sprache'], 'tg' => $start['quelle']]), true, 303);
    exit;
}

$weiter = '/bedarf.php?' . $q(['lang' => $start['sprache'], 'tg' => $start['quelle']]) . '&t=';
$neu = '/telegram-app.php?' . $q(['neu' => 1, 's' => $param, 'lang' => $start['sprache']]);
?><!doctype html>
<html lang="<?= htmlspecialchars($start['sprache'], ENT_QUOTES) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Vecom Design</title>
<?php /* Das Telegram-Skript schon HIER: Es merkt sich beim ersten Laden die
         Startdaten im Sitzungsspeicher des Fensters. Fehlen sie auf den
         folgenden Seiten, kennt Telegram Web das Fenster nicht als fertig
         und zeigt ewig den Ladekreis (gesehen am 30.09.2026: Inhalt geladen,
         Rahmen blieb auf opacity 0). */ ?>
<script src="https://telegram.org/js/telegram-web-app.js?59"></script>
<style>html,body{margin:0;height:100%;background:#0e0c09;color:#c9b27a;font:15px system-ui,sans-serif}
body{display:grid;place-items:center}</style>
</head>
<body>
<p>…</p>
<script>
(function () {
  var t = null;
  try { t = localStorage.getItem('vd_tg_bedarf'); } catch (e) { }
  var ok = typeof t === 'string' && /^[0-9a-f]{48}$/.test(t);
  // Telegram Web zeigt das Fenster erst nach „fertig“ vom ERSTEN Dokument im Rahmen
  // (gesehen am 30.09.2026: kam das „fertig“ erst von bedarf.php, blieb der Ladekreis).
  try { window.Telegram.WebApp.ready(); } catch (e) { }
  // Der Teil hinter dem # geht mit — er bleibt im Gerät, der Server sieht ihn nie.
  location.replace((ok ? <?= json_encode($weiter, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?> + t : <?= json_encode($neu, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>) + location.hash);
})();
</script>
<noscript><a href="<?= htmlspecialchars($neu, ENT_QUOTES) ?>">→ Vecom Design</a></noscript>
</body>
</html>
