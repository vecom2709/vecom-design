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

   SEIT DEM 01.10.2026 (Uwe: „normale Nutzer nur über den Kanal“): Ohne
   Rechner-Einstieg (preis/neu/besser) geht es ins Vecom-Fenster
   (telegram-menue.php) — Menü, Website-Check, Anfrage, Partner. Kommt der
   Start über eine Kampagne (m_CODE) oder einen Partner (p_CODE), legt diese
   Seite den Besuch in der Spur an und merkt den Partner, wie p.php und
   k.php es auf der Website tun; gezählt wird jedes Öffnen (app_start).
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('Der Rechner ist derzeit nicht erreichbar.'); }
foreach (['Config', 'Db', 'Events', 'TelegramApp'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

header('Content-Security-Policy: ' . TelegramApp::EINBETTEN);
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$param = strtolower((string) ($_GET['tgWebAppStartParam'] ?? $_GET['s'] ?? ''));
$start = TelegramApp::lesen($param, strtolower((string) ($_GET['lang'] ?? '')));
/* SPRACHE DES NUTZERS (01.10.2026, Uwe: „Alles“ — Kanal zweisprachig): Trägt der Start keine
   Sprache (die Kanal-Knöpfe seitdem nicht mehr, Kampagnen- und Partner-Links nie), nimmt der
   Server die Sprache aus Keks und Browser — in Telegram ist das die Sprache des Geräts.
   Die Startdaten von Telegram (mit Name und Kennung) bleiben weiter ungelesen:
   Für eine Sprache ist das zu viel. */
$festeSprache = in_array(strtolower((string) ($_GET['lang'] ?? '')), ['it', 'de', 'en'], true) || preg_match('/(?:^|-)(?:it|de|en)(?:-|$)/', $param) === 1;
if (!$festeSprache) {
    require_once __DIR__ . '/app/src/Sprache.php';
    $geraten = Sprache::ausAnfrage();
    if (in_array($geraten, ['it', 'de', 'en'], true)) { $start['sprache'] = $geraten; }
}
// Aus dem Vecom-Fenster: der Rechner mit eigenem Einstieg, die Quelle bleibt die des Starts.
if (isset($_GET['e']) && in_array((string) $_GET['e'], TelegramApp::EINSTIEGE, true)) { $start['einstieg'] = (string) $_GET['e']; $start['rechner'] = true; }
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

/* Das erste Laden eines Starts: zählen, und Kampagne/Partner merken. Wirft nie. */
if (!isset($_GET['e'])) {
    try {
        require_once __DIR__ . '/app/src/TelegramWachstum.php';
        TelegramWachstum::zaehlen('app_start', $start['quelle']);
        TelegramApp::quelleMerken($start['quelle'], $start['sprache']);
    } catch (Throwable $e) { error_log('telegram-app: ' . $e->getMessage()); }
}

if (!$start['rechner']) {
    // Ins Vecom-Fenster — mit demselben Laden wie unten (erst „fertig“, dann weiter), nur ein anderes Ziel.
    $weiter = '/telegram-menue.php?' . $q(['a' => $start['ziel'], 's' => $start['quelle'], 'lang' => $start['sprache']]);
    $neu = $weiter;
} else {
$weiter = '/bedarf.php?' . $q(['lang' => $start['sprache'], 'tg' => $start['quelle']]) . '&t=';
$neu = '/telegram-app.php?' . $q(['neu' => 1, 's' => $param, 'lang' => $start['sprache']] + (isset($_GET['e']) ? ['e' => $start['einstieg']] : []));
}
$imMenu = !$start['rechner'];
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
  // Ins Menü immer frisch; nur der Rechner macht dort weiter, wo man war.
  var ok = <?= $imMenu ? 'false' : 'true' ?> && typeof t === 'string' && /^[0-9a-f]{48}$/.test(t);
  // Telegram Web zeigt das Fenster erst nach „fertig“ vom ERSTEN Dokument im Rahmen
  // (gesehen am 30.09.2026: kam das „fertig“ erst von bedarf.php, blieb der Ladekreis).
  try { window.Telegram.WebApp.ready(); } catch (e) { }
  // Der Teil hinter dem # geht mit — er bleibt im Gerät, der Server sieht ihn nie.
  var ziel = (ok ? <?= json_encode($weiter, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?> + t : <?= json_encode($neu, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?>) + location.hash;
  // Erst weiter, wenn dieses Dokument fertig geladen ist: Telegram Web richtet
  // sich am „load“ des Rahmens ein — sprang die Seite schon während des
  // Aufbaus weiter, kamen dort keine Nachrichten an (gemessen 30.09.2026).
  var los = function () { setTimeout(function () { location.replace(ziel); }, 30); };
  if (document.readyState === 'complete') { los(); } else { window.addEventListener('load', los); }
})();
</script>
<noscript><a href="<?= htmlspecialchars($neu, ENT_QUOTES) ?>">→ Vecom Design</a></noscript>
</body>
</html>
