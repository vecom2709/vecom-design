<?php
declare(strict_types=1);
/* ==========================================================================
   zugang.php — Adresse eintragen, Dashboard zugeschickt bekommen (24.09.2026).

   Drei Wege durch diese Datei:

   POST email   Der Knopf auf der Startseite oder hier: Zugang::anfordern().
                Danach steht fuer JEDE Adresse derselbe Satz da (E2) -- ob
                neu, schon Kunde oder erfunden. Der Link geht nur ans Postfach.
                Mit Accept: application/json antwortet die Seite dem Feld auf
                der Startseite, ohne sie zu verlassen.
   GET t=…      Der Link aus der Mail. Beim ersten Oeffnen entstehen Kunde und
                Vorhaben (E4), dann geht es ins Dashboard.
   GET          Die Seite mit dem einen Feld -- fuer alle Knoepfe, die bisher
                auf den Konfigurator zeigten (S1, S2).

   Das ist eine oeffentliche Adresse. Sie zeigt im Zweifel eine Meldung,
   niemals eine leere Seite und niemals einen Fehler aus der Datenbank.
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('Gerade nicht erreichbar.'); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Texte', 'Events', 'Zugang'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

require_once __DIR__ . '/app/src/Sprache.php';
$sprache = Sprache::ausAnfrage();
Sprache::merken($sprache);

$T = static fn(string $s): string => Texte::h(Texte::ZUGANG[$s] ?? [], $sprache);
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

/* Ein Empfehlungscode aus /e/CODE kommt ueber bedarf.php hierher und reist
   mit der Adresse in den Zugang -- beim Oeffnen landet er im Vorhaben. */
$code = strtoupper(trim((string) ($_REQUEST['e'] ?? '')));
if (!preg_match('/^[A-Z0-9]{5,16}$/', $code)) { $code = ''; }

$m = (string) ($_GET['m'] ?? '');
$panne = false;

/* ---------- Der Link aus der Mail ---------- */
$token = trim((string) ($_GET['t'] ?? ''));
if ($token !== '') {
    try {
        Einrichtung_sicher();
        $r = Zugang::oeffnen($token);
        if ($r['ok']) {
            header('Location: ' . $r['link'] . (!empty($r['neu']) ? '&willkommen=1' : ''), true, 303);
            exit;
        }
        $m = (string) ($r['grund'] ?? 'unbekannt');
    } catch (Throwable $e) {
        $panne = true;
        try { Events::melden('zugang_fehler', 'Dashboard-Link ließ sich nicht öffnen', 'schlecht', $e->getMessage(), '/kunden'); }
        catch (Throwable $e2) { /* dann eben nicht */ }
    }
}

/* ---------- Adresse eintragen ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $json = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    $email = (string) ($_POST['email'] ?? '');
    $ergebnis = 'gesendet';
    try {
        Einrichtung_sicher();
        $r = Zugang::anfordern($email, $sprache, ['quelle' => Zugang::quelle((string) ($_POST['quelle'] ?? 'seite')), 'empfehl_code' => $code]);
        if (!$r['ok']) { $ergebnis = 'ungueltig'; }
        /* Ob die Mail wirklich rausging, steht in der Verwaltung (Mails,
           Meldungen). Hier NICHT: Ein anderer Satz bei einer Bestandsadresse
           oder bei einem Versandfehler verriete, welche Adressen es gibt. */
    } catch (Throwable $e) {
        $ergebnis = 'panne';
        try { Events::melden('zugang_fehler', 'Dashboard-Anforderung gescheitert', 'schlecht', $e->getMessage(), '/kunden'); }
        catch (Throwable $e2) { /* dann eben nicht */ }
    }
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ergebnis === 'gesendet', 'meldung' => $T($ergebnis)], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Die Adresse steht nicht in der Weiterleitung: keine Daten in Adresszeilen.
    header('Location: /zugang.php?lang=' . rawurlencode($sprache) . '&m=' . $ergebnis
        . ($code !== '' && $ergebnis !== 'gesendet' ? '&e=' . rawurlencode($code) : ''), true, 303);
    exit;
}

/** Die Tabelle `zugaenge` entsteht mit Migration 050. Laeuft die Verwaltung
    nach einem Deploy erst spaeter, wuerde diese oeffentliche Seite bis dahin
    scheitern -- also die offenen Migrationen hier nachziehen. Das ist der
    Merkposten vom 03.09.2026 fuer den Tag, an dem ein Einstieg daran haengt. */
function Einrichtung_sicher(): void
{
    static $gemacht = false;
    if ($gemacht) { return; }
    $gemacht = true;
    try {
        Db::wert('SELECT 1 FROM zugaenge LIMIT 1', [], null);
    } catch (Throwable $e) {
        require_once __DIR__ . '/app/src/Einrichtung.php';
        Einrichtung::migrieren();
    }
}

$meldung = in_array($m, ['gesendet', 'ungueltig', 'abgelaufen', 'unbekannt', 'panne'], true) ? $m : '';
if ($panne) { $meldung = 'panne'; }
$gut = $meldung === 'gesendet';
?><!doctype html>
<html lang="<?= $h($sprache) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $h($T('titel')) ?> — Vecom Design</title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<link rel="stylesheet" href="/assets/css/kunde.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/kunde.css') ?>">
<style>
  .zg{max-width:520px;margin:0 auto}
  .zg h1{font-size:clamp(24px,5vw,30px);margin:0 0 10px;line-height:1.2}
  .zg .lead{color:var(--dim);font-size:15.5px;line-height:1.65;margin:0 0 18px}
  .zg form{display:flex;flex-direction:column;gap:10px}
  .zg input[type=email]{font-size:17px;padding:15px 16px;min-height:54px}
  .zg .knopf{min-height:54px;font-size:16px}
  .zg .klein{color:var(--leise);font-size:13px;line-height:1.6;margin:12px 0 0}
  .sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
  .zg .schritte{font-size:12.5px;letter-spacing:.06em;text-transform:uppercase;color:var(--leise);margin:0 0 14px}
</style>
</head>
<body>
<div class="seite">
  <div class="wortmarke">
    <img src="/assets/img/logo-mark.webp" alt="" width="58" height="46" fetchpriority="high">
    <span class="wort"><b>VECOM</b> DESIGN</span>
  </div>

  <div class="block zg">
    <p class="schritte"><?= $h($T('schritte')) ?></p>
    <h1><?= $h($T('titel')) ?></h1>
    <?php if ($meldung !== ''): ?>
      <div class="hinweis <?= $gut ? 'gut' : 'schlecht' ?>" role="status"><?= $h($T($meldung)) ?></div>
    <?php endif; ?>
    <?php if (!$gut): ?>
      <p class="lead"><?= $h($T('lead')) ?></p>
      <form method="post" action="/zugang.php?lang=<?= $h($sprache) ?>">
        <?php if ($code !== ''): ?><input type="hidden" name="e" value="<?= $h($code) ?>"><?php endif; ?>
        <label for="z_email" class="sr"><?= $h($T('feld')) ?></label>
        <input id="z_email" name="email" type="email" autocomplete="email" inputmode="email" required
               placeholder="<?= $h($T('feld')) ?>" autofocus>
        <button class="knopf haupt" type="submit"><?= $h($T('knopf')) ?></button>
      </form>
      <p class="klein"><?= $h($T('hinweis')) ?><br>
        <?= $h(strtr($T('datenschutz'), ['{tage}' => (string) Zugang::GUELTIG_TAGE])) ?>
        <a href="<?= $h(Sprache::legal($sprache, 'privacy')) ?>"><?= $h(['it' => 'Privacy', 'de' => 'Datenschutz', 'en' => 'Privacy'][$sprache]) ?></a></p>
    <?php endif; ?>
  </div>

  <div class="sprachen">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
      <a class="<?= $l === $sprache ? 'jetzt' : '' ?>" href="/zugang.php?lang=<?= $l ?><?= $code !== '' ? '&amp;e=' . $h(rawurlencode($code)) : '' ?>"><?= $h($wie) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php require_once __DIR__ . '/app/src/Fuss.php'; echo Fuss::html($sprache); ?>
</body>
</html>
