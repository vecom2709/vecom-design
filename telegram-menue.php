<?php
declare(strict_types=1);
/* ==========================================================================
   telegram-menue.php — das Vecom-Fenster im Kanal (01.10.2026, Uwe:
   „normale Nutzer außer Admin sollen nicht direkt in den Bot kommen,
   nur über den Kanal — Partner werden und Telegram-Bots erstellen auch“).

   WARUM EIN FENSTER UND KEIN BOT-GESPRÄCH

   Was im Kanal passiert, bleibt im Kanal: Jeder Knopf des angehefteten
   Menü-Beitrags öffnet diese Seite als Mini-App ÜBER dem Kanal (telegram-
   app.php leitet hierher). Schließen, und man ist wieder im Kanal. Der
   Bot-Chat bleibt dem Admin vorbehalten.

   KEIN ZWEITES SYSTEM

     Preis / neu / besser   → derselbe Konfigurator wie bisher (bedarf.php)
     Website prüfen         → derselbe Schnellcheck wie analisi.php und der
                              Bot (PartnerSeite::kurzcheck, gleiche Bremse),
                              dieselbe Auswahl der drei wichtigsten Punkte
                              (TelegramBot::checkTop)
     KI, Bots, 3D, Logo,    → dieselbe Anfrage wie aus dem Bot
     Beratung                 (Anfrage::annehmen, Herkunft telegram, Thema)
     Partner, Domain,       → die Seiten der Website, im Browser
     Kundenbereich            (Telegram.WebApp.openLink)

   Texte: TELEGRAM (Themen, Check-Sätze) und TELEGRAM_APP (Rahmen).
   Gezählt wird wie im Bot, nur je Fenster-Sitzung statt je Chat
   (TelegramWachstum, Quelle aus dem Start: kanal, m_CODE, p_CODE …).

   DATENSCHUTZ: Telegram gibt der Seite Startdaten hinter dem #; die liest
   hier niemand. Gespeichert wird nur, was jemand im Formular selbst
   einträgt — als normale Anfrage, mit Zustimmung wie im Bot.
   ========================================================================== */

if (!is_file(__DIR__ . '/app/config.local.php')) { http_response_code(503); exit('Derzeit nicht erreichbar.'); }
foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'Texte', 'Sprache', 'Telegram', 'TelegramApp', 'TelegramWachstum', 'TelegramBot'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

header('Content-Security-Policy: ' . TelegramApp::EINBETTEN);
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
// Telegram Web zeigt das Fenster in einem fremden Rahmen: ohne SameSite=None käme das Sitzungs-Cookie (CSRF) nicht mit.
session_set_cookie_params(['path' => '/', 'secure' => true, 'httponly' => true, 'samesite' => 'None']);
session_name('vecomtgapp');
session_start();
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

$sp = in_array($_GET['lang'] ?? '', ['it', 'de', 'en'], true) ? (string) $_GET['lang'] : Sprache::ausAnfrage();
if (!in_array($sp, ['it', 'de', 'en'], true)) { $sp = 'it'; }
$quelle = strtolower((string) ($_GET['s'] ?? ''));
if (!TelegramApp::quelleOk($quelle)) { $quelle = 'telegram'; }
$a = in_array($_GET['a'] ?? '', TelegramApp::ZIELE, true) ? (string) $_GET['a'] : 'menu';
if (in_array($_GET['a'] ?? '', TelegramApp::EINSTIEGE, true)) { $a = (string) $_GET['a']; }

$T = Texte::TELEGRAM[$sp];
$A = static fn(string $k): string => (string) (Texte::TELEGRAM_APP[$k][$sp] ?? Texte::TELEGRAM_APP[$k]['it'] ?? '');
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
/* Die Bot-Texte sind eigenes HTML (nur <b>, <i>, <a>) mit \n — für die Seite reicht <br>. */
$html = static fn(string $s): string => nl2br($s, false);
$web = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
$hier = static fn(string $ziel, array $mehr = []): string => '/telegram-menue.php?' . http_build_query(['a' => $ziel, 's' => $quelle, 'lang' => $sp] + $mehr, '', '&', PHP_QUERY_RFC3986);
$rechner = static fn(string $e): string => '/telegram-app.php?' . http_build_query(['neu' => 1, 's' => $quelle, 'lang' => $sp, 'e' => $e], '', '&', PHP_QUERY_RFC3986);

/* Zählen wie im Bot: je Stufe einmal — hier je Fenster-Sitzung. */
$stufe = static function (string $art) use ($quelle): void {
    $_SESSION['stufen'] ??= [];
    if (in_array($art, $_SESSION['stufen'], true)) { return; }
    $_SESSION['stufen'][] = $art;
    TelegramWachstum::zaehlen($art, $quelle);
};
$THEMEN = ['ki' => ['kiText', 'ki'], 'bots' => ['botsText', 'bots'], 'dreid' => ['dreiDText', '3d'], 'logo' => ['logoText', 'logo'], 'mensch' => ['menschText', 'allgemein']];
if ($a !== 'menu' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stufe('wegweiser');
    if ($a === 'pruefen') { $stufe('check'); }
    elseif (!in_array($a, ['kunde', 'partner'], true)) { $stufe('interesse'); }
}
/* Rechner: weiter in den Konfigurator (der zählt „Preisrechner gestartet“ selbst). */
if (in_array($a, TelegramApp::EINSTIEGE, true)) { header('Location: ' . $rechner($a), true, 303); exit; }

$fehler = ''; $danke = null; $check = null;
$tat = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($_POST['tat'] ?? '') : '';
if ($tat !== '' && !hash_equals((string) $_SESSION['csrf'], (string) ($_POST['_csrf'] ?? ''))) { $fehler = $A('fehler'); $tat = ''; }

/* ---------- Anfrage zu einem Thema ---------- */
if ($tat === 'anfrage' && isset($THEMEN[$a])) {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $text = trim((string) ($_POST['text'] ?? ''));
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $tagDatei = sys_get_temp_dir() . '/vecomtgapp_' . md5($ip . '|' . date('Ymd'));
    $alleDatei = sys_get_temp_dir() . '/vecomtgapp_alle_' . date('Ymd');
    $schonIch = is_file($tagDatei) ? (int) file_get_contents($tagDatei) : 0;
    $schonAlle = is_file($alleDatei) ? (int) file_get_contents($alleDatei) : 0;
    if ((string) ($_POST['firma_web'] ?? '') !== '' || time() - (int) ($_SESSION['formular_am'] ?? 0) < 3) {
        $fehler = $A('fehler');                     // Lockfeld ausgefüllt oder schneller als ein Mensch: still nichts
    } elseif ($schonIch >= 3 || $schonAlle >= 100) {
        $fehler = $A('zuviel');
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 120 || preg_match('~https?://|<|>~i', $name) || !filter_var($email, FILTER_VALIDATE_EMAIL)
              || empty($_POST['ds']) || mb_strlen($text) > 1000) {
        $fehler = $A('pflicht');
    } else {
        try {
            require_once __DIR__ . '/app/src/Anfrage.php';
            $thema = (string) ($T['thema'][$THEMEN[$a][1]] ?? '');
            $anfrageId = Anfrage::annehmen([
                'name' => $name, 'email' => $email, 'sprache' => $sp, 'herkunft' => 'telegram',
                'nachricht' => strtr((string) $T['beratungKopf'], ['{thema}' => $thema]) . ($text !== '' ? "\n\n" . $text : ''),
            ]);
            if (!$anfrageId) { throw new RuntimeException('Anfrage::annehmen hat abgelehnt.'); }
            @file_put_contents($tagDatei, (string) ($schonIch + 1));
            @file_put_contents($alleDatei, (string) ($schonAlle + 1));
            $kid = (int) Db::wert('SELECT customer_id FROM anfragen WHERE id = ?', [$anfrageId], 0);
            try { Events::protokoll('anfrage_quelle', 'Anfrage kam über das Vecom-Fenster in Telegram (' . $quelle . ')', $kid); } catch (Throwable $e) { }
            if ($kid > 0) {
                try {
                    require_once __DIR__ . '/app/src/Zustimmung.php';
                    Zustimmung::festhalten('datenschutz', $kid, strip_tags(strtr($A('ds'), ['{link}' => 'legal.html#privacy'])), $sp, Texte::TELEGRAM_APP_FASSUNG);
                } catch (Throwable $e) { /* nachtragbar */ }
                // Kam das Fenster über einen Partner (p_CODE), gehört der Kunde ihm — wie im Bot.
                if (str_starts_with($quelle, 'p_')) {
                    try {
                        require_once __DIR__ . '/app/src/Partner.php';
                        $p = Partner::ausCode(substr($quelle, 2));
                        if ($p) { Partner::zuordnen($kid, (int) $p['id'], 'link', null, 'telegram'); }
                    } catch (Throwable $e) { }
                }
                TelegramWachstum::herkunftMerken($kid, $quelle, 'app', (int) $anfrageId);
            }
            $stufe('beratung');
            $stufe('lead');
            $danke = strtr((string) $T['dankBeratung'], ['{name}' => $h($name), '{email}' => $h($email)]);
        } catch (Throwable $e) {
            try { Events::melden('bedarf_fehler', 'Vecom-Fenster: Anfrage nicht angenommen', 'schlecht', $e->getMessage(), 'anfragen'); } catch (Throwable $e2) { }
            $fehler = $A('fehler');
        }
    }
}

/* ---------- Website-Check ---------- */
if ($tat === 'check' && $a === 'pruefen') {
    require_once __DIR__ . '/app/src/PartnerSeite.php';
    require_once __DIR__ . '/app/src/PartnerCheck.php';
    $kc = PartnerSeite::kurzcheck(mb_substr((string) ($_POST['url'] ?? ''), 0, 200));
    if (!$kc['ok']) {
        $fehler = (string) $T[match ((string) $kc['grund']) { 'warten' => 'checkWarten', 'zuviel' => 'checkZuviel', default => 'checkAdresse' }];
    } elseif (array_filter((array) $kc['punkte'], static fn($p) => ($p['was'] ?? '') === 'erreichbar')) {
        $fehler = strtr((string) $T['checkNichtErreichbar'], ['{host}' => $h((string) $kc['host'])]);
    } else {
        $stufe('check_fertig');
        $punkte = (array) $kc['punkte'];
        $check = ['host' => (string) $kc['host'], 'gesamt' => count($punkte),
                  'gut' => count(array_filter($punkte, static fn($p) => ($p['stand'] ?? '') === 'gut')),
                  'top' => TelegramBot::checkTop($punkte, $sp)];
    }
}
$_SESSION['formular_am'] = time();

$knopf = static function (string $text, string $href, bool $haupt = false, bool $extern = false) use ($h, $A): string {
    return '<a class="k' . ($haupt ? ' haupt' : '') . '" href="' . $h($href) . '"' . ($extern ? ' data-extern target="_blank" rel="noopener"' : '') . '>' . $h($text)
        . ($extern ? ' <small>↗</small>' : '') . '</a>';
};
?><!doctype html>
<html lang="<?= $h($sp) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Vecom Design</title>
<script src="https://telegram.org/js/telegram-web-app.js?59"></script>
<style>
  :root{--grund:#0e0c09;--flaeche:#17140f;--linie:#2c261b;--text:#efe6d2;--leise:#a99a7c;--gold:#d9b46a;--gold2:#f3d79a;--rot:#ef6b5b;--gelb:#e8b84a}
  *{box-sizing:border-box}
  html,body{margin:0;background:var(--grund);color:var(--text);font:16px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
  main{max-width:560px;margin:0 auto;padding:18px 16px 40px}
  h1{font-size:21px;margin:4px 0 2px;letter-spacing:-.01em} h1 span{color:var(--gold)}
  .unter{color:var(--leise);margin:0 0 16px}
  .gitter{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .k{display:flex;align-items:center;justify-content:center;gap:6px;min-height:52px;padding:10px 12px;border-radius:14px;border:1px solid var(--linie);background:var(--flaeche);
     color:var(--text);text-decoration:none;font-weight:600;font-size:15px;text-align:center;line-height:1.3}
  .k.haupt{background:linear-gradient(135deg,var(--gold2),var(--gold));color:#16120b;border-color:transparent}
  .k.breit{grid-column:1/-1}
  .k small{opacity:.6;font-weight:400}
  .text{background:var(--flaeche);border:1px solid var(--linie);border-radius:16px;padding:16px;margin:0 0 14px}
  .text b{color:var(--gold2)}
  form{display:grid;gap:10px;margin:0 0 14px}
  label{font-size:14px;color:var(--leise)}
  input[type=text],input[type=email],textarea{width:100%;padding:12px;border-radius:12px;border:1px solid var(--linie);background:#0b0a07;color:var(--text);font:inherit}
  textarea{min-height:90px;resize:vertical}
  .ds{display:flex;gap:10px;align-items:flex-start;font-size:13.5px;color:var(--leise)} .ds input{margin-top:3px;width:20px;height:20px;flex:0 0 auto}
  .ds a{color:var(--gold)}
  button{min-height:52px;border:0;border-radius:14px;font:inherit;font-weight:700;background:linear-gradient(135deg,var(--gold2),var(--gold));color:#16120b;cursor:pointer}
  .fehler{border:1px solid var(--rot);color:#ffd2cc;border-radius:12px;padding:10px 12px;margin:0 0 12px}
  .lock{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
  ol{padding-left:20px;margin:8px 0} li{margin:0 0 8px}
  .zurueck{display:inline-block;margin:0 0 12px;color:var(--leise);text-decoration:none;font-size:14px}
  .fein{font-size:13px;color:var(--leise)} .fein a{color:var(--gold)} .fein b{color:var(--text)}
</style>
</head>
<body>
<main>
<?php if ($a !== 'menu'): ?><a class="zurueck" href="<?= $h($hier('menu')) ?>"><?= $h($A('menu')) ?></a><?php endif; ?>

<?php if ($a === 'menu'): ?>
  <h1>Vecom <span>Design</span></h1>
  <p class="unter"><?= $h($A('unter')) ?></p>
  <div class="gitter">
    <?= $knopf($T['k_preis'], $hier('preis'), true) ?>
    <?= $knopf($T['k_pruefen'], $hier('pruefen'), true) ?>
    <?= $knopf($T['k_neu'], $hier('neu')) ?>
    <?= $knopf($T['k_besser'], $hier('besser')) ?>
    <?= $knopf($T['k_ki'], $hier('ki')) ?>
    <?= $knopf($T['k_bots'], $hier('bots')) ?>
    <?= $knopf($T['k_3d'], $hier('dreid')) ?>
    <?= $knopf($T['k_logo'], $hier('logo')) ?>
    <?= $knopf($T['k_mensch'], $hier('mensch')) ?>
    <?= $knopf($T['k_partner'], $hier('partner')) ?>
    <?= $knopf($T['k_hosting'], $web . '/hosting.php?lang=' . $sp, false, true) ?>
    <?= $knopf($T['k_kunde'], $web . '/zugang.php?lang=' . $sp, false, true) ?>
  </div>

<?php elseif ($danke !== null): ?>
  <div class="text"><?= $html($danke) ?></div>
  <div class="gitter">
    <a class="k haupt breit" href="#" data-schliessen><?= $h($A('zurueck')) ?></a>
    <?= $knopf($A('menu'), $hier('menu')) ?>
  </div>

<?php elseif (isset($THEMEN[$a])): ?>
  <div class="text"><?= $html((string) $T[$THEMEN[$a][0]]) ?></div>
  <?php if ($fehler !== ''): ?><div class="fehler"><?= $h($fehler) ?></div><?php endif; ?>
  <form method="post" action="<?= $h($hier($a)) ?>">
    <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="anfrage">
    <div class="lock" aria-hidden="true"><input type="text" name="firma_web" tabindex="-1" autocomplete="off"></div>
    <label for="f_name"><?= $h($A('f_name')) ?></label><input id="f_name" type="text" name="name" required maxlength="120" autocomplete="name" value="<?= $h((string) ($_POST['name'] ?? '')) ?>">
    <label for="f_email"><?= $h($A('f_email')) ?></label><input id="f_email" type="email" name="email" required maxlength="190" autocomplete="email" value="<?= $h((string) ($_POST['email'] ?? '')) ?>">
    <label for="f_text"><?= $h($A('f_text')) ?></label><textarea id="f_text" name="text" maxlength="1000"><?= $h((string) ($_POST['text'] ?? '')) ?></textarea>
    <div class="fein"><?= $html(strtr($A('ds'), ['{link}' => '<a href="' . $h($web . '/legal.html?lang=' . $sp . '#privacy') . '" data-extern target="_blank" rel="noopener">' . $h((string) $T['dsLink']) . '</a>'])) ?></div>
    <label class="ds"><input type="checkbox" name="ds" value="1" required> <span><?= $h($A('f_ds')) ?></span></label>
    <button><?= $h($A('senden')) ?></button>
  </form>

<?php elseif ($a === 'pruefen'): ?>
  <?php if ($check === null): ?>
    <div class="text"><?= $html((string) $T['pruefenText']) ?></div>
    <?php if ($fehler !== ''): ?><div class="fehler"><?= $fehler /* Satz aus TELEGRAM, Adresse schon maskiert */ ?></div><?php endif; ?>
    <form method="post" action="<?= $h($hier('pruefen')) ?>">
      <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="check">
      <label for="f_url"><?= $h($A('f_url')) ?></label><input id="f_url" type="text" name="url" required maxlength="200" inputmode="url" autocapitalize="off" spellcheck="false" value="<?= $h((string) ($_POST['url'] ?? '')) ?>">
      <button><?= $h($A('pruefen')) ?></button>
    </form>
  <?php else: ?>
    <div class="text">
      <?= $html(strtr((string) $T['checkKopf'], ['{host}' => $h($check['host'])])) ?><br>
      <?= $h(strtr((string) $T['checkStand'], ['{gut}' => (string) $check['gut'], '{gesamt}' => (string) $check['gesamt']])) ?>
      <?php if ($check['top']): ?>
        <p style="margin:12px 0 0"><?= $html((string) $T['checkTop3']) ?></p>
        <ol><?php foreach ($check['top'] as $p): ?><li><?= $p['stand'] === 'schlecht' ? '🔴' : '🟡' ?> <b><?= $h($p['titel']) ?></b> — <?= $h($p['text']) ?></li><?php endforeach; ?></ol>
      <?php else: ?>
        <p style="margin:12px 0 0"><?= $h(strtr((string) $T['checkAlles'], ['{gesamt}' => (string) $check['gesamt']])) ?></p>
      <?php endif; ?>
      <p class="fein" style="margin:10px 0 0"><?= $html((string) $T['checkNicht']) ?></p>
    </div>
    <div class="gitter">
      <?= $knopf($T['k_verbessern'], $hier('besser'), true) ?>
      <?= $knopf($T['k_preis'], $hier('preis')) ?>
      <?= $knopf($T['k_beratung'], $hier('mensch')) ?>
      <?= $knopf($T['k_check'], $web . '/analisi.php?lang=' . $sp . '&url=' . rawurlencode($check['host']), false, true) ?>
      <?= $knopf($T['k_anderer'], $hier('pruefen'), false) ?>
    </div>
  <?php endif; ?>

<?php elseif ($a === 'partner'): ?>
  <div class="text"><?= $html((string) $T['partnerText']) ?></div>
  <div class="gitter"><?= $knopf($T['k_partner_seite'], $web . '/partner.php?lang=' . $sp, true, true) ?></div>
  <p class="fein"><?= $h($A('extern')) ?></p>

<?php elseif ($a === 'hosting' || $a === 'kunde'): ?>
  <div class="text"><?= $html((string) $T[$a === 'hosting' ? 'hostingText' : 'kundeText']) ?></div>
  <div class="gitter"><?= $knopf($T[$a === 'hosting' ? 'k_hosting_seite' : 'k_zugang'], $web . ($a === 'hosting' ? '/hosting.php' : '/zugang.php') . '?lang=' . $sp, true, true) ?></div>
  <p class="fein"><?= $h($A('extern')) ?></p>
<?php endif; ?>
</main>
<script>
(function () {
  var tg = window.Telegram && window.Telegram.WebApp;
  if (!tg) { return; }
  try { tg.ready(); tg.expand(); } catch (e) { }
  // Telegrams eigener Zurück-Pfeil: im Menü aus, sonst zurück ins Menü.
  try {
    var zurueck = document.querySelector('.zurueck');
    if (zurueck) { tg.BackButton.show(); tg.BackButton.onClick(function () { location.href = zurueck.getAttribute('href'); }); }
    else { tg.BackButton.hide(); }
  } catch (e) { }
  // Seiten der Website im Browser öffnen (in der App: Telegrams eigener Browser), das Fenster bleibt.
  document.querySelectorAll('[data-extern]').forEach(function (a) {
    a.addEventListener('click', function (ev) { try { ev.preventDefault(); tg.openLink(a.href); } catch (e) { location.href = a.href; } });
  });
  var s = document.querySelector('[data-schliessen]');
  if (s) { s.addEventListener('click', function (ev) { ev.preventDefault(); try { tg.close(); } catch (e) { } }); }
})();
</script>
</body>
</html>
