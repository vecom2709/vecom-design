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
        require_once __DIR__ . '/app/src/Partner.php';
        /* WELCHER PARTNER (03.10.2026, Uwe: „auf Chiara eingetragen, Anika zugeordnet“):
           Das Formular einer Empfehlungsseite trägt seinen Partner selbst (Feld
           „partner“) -- er gilt vor dem Keks. Der Keks zeigt nur den zuletzt
           geöffneten Partnerlink dieses Browsers; ein zweiter Tab mit einer
           anderen Partnerseite hätte ihn überschrieben. Nur aktive Partner. */
        $pcFormular = (string) ($_POST['partner'] ?? '');
        [$pcfCode] = Partner::teilen($pcFormular);
        $pcGilt = ($pcfCode !== '' && Partner::ausCode($pcfCode) !== null) ? $pcFormular : (string) ($_COOKIE[Partner::KEKS] ?? '');
        /* SPRACHE GEWÄHLT (03.10.2026): IT · DE · EN neben dem Feld. Die Seite antwortet
           in ihrer Sprache; Mail, Dashboard und Fragebogen kommen in der gewählten. */
        $wahl = (string) ($_POST['sprache'] ?? '');
        $gewaehlt = in_array($wahl, Sprache::ALLE, true);
        /* Der Partnercode reist mit dem Zugang, damit er auch zählt, wenn der Link auf
           einem anderen Gerät geöffnet wird. */
        $r = Zugang::anfordern($email, $gewaehlt ? $wahl : $sprache, ['quelle' => Zugang::quelle((string) ($_POST['quelle'] ?? 'seite')), 'empfehl_code' => $code,
            'partner_code' => $pcGilt, 'wunsch' => (string) ($_POST['wunsch'] ?? ''), 'sprache_gewaehlt' => $gewaehlt]);
        if (!$r['ok']) { $ergebnis = 'ungueltig'; }
        /* Von einer Empfehlungsseite (27.09.2026): für den Trichter des
           Partners zählen, und das freiwillige Werbe-Häkchen beantworten --
           nur eine Bestätigungsmail, die Einwilligung entsteht erst mit dem
           Klick darin (wie beim Website-Check). */
        if ($r['ok'] && !empty($_POST['von_partner'])) {
            try {
                foreach (['Partner', 'PartnerSeite'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
                [$vp] = Partner::ausKeks([Partner::KEKS => $pcGilt]);
                if ($vp !== null) {
                    Partner::ereignis((int) $vp['id'], 'email');
                    if (!empty($_POST['werbung'])) {
                        PartnerSeite::werbungAnfragen($vp, $email, (string) ($_POST['betrieb'] ?? ''), (string) ($_POST['webseite'] ?? ''), $sprache,
                            (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
                    }
                }
            } catch (Throwable $e) { /* Zählen und Werbe-Häkchen sind Beiwerk des Zugangs */ }
        }
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
        Db::wert('SELECT wunsch FROM zugaenge LIMIT 1', [], null);   // Spalte aus 097 (28.09.2026): fehlt sie, jetzt nachziehen
    } catch (Throwable $e) {
        require_once __DIR__ . '/app/src/Einrichtung.php';
        Einrichtung::migrieren();
    }
}

$meldung = in_array($m, ['gesendet', 'ungueltig', 'abgelaufen', 'unbekannt', 'panne'], true) ? $m : '';
if ($panne) { $meldung = 'panne'; }
$gut = $meldung === 'gesendet';
?><!doctype html>
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
<head>
<meta charset="utf-8">
<?= Sprache::skript() ?>
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
  .zg-sprache{border:0;padding:0;margin:2px 0 4px;display:flex;flex-wrap:wrap;gap:8px;align-items:center}
  .zg-sprache legend{padding:0;margin:0 0 6px;font-size:13.5px;color:var(--dim)}
  .zg-sprache label{position:relative}
  .zg-sprache input{position:absolute;opacity:0;width:1px;height:1px}
  .zg-sprache span{display:inline-block;padding:9px 14px;border-radius:999px;border:1px solid var(--linie,rgba(0,0,0,.15));font-size:14px;cursor:pointer;min-height:40px;line-height:20px}
  .zg-sprache input:checked + span{border-color:var(--akzent,#c9a23a);background:rgba(201,162,58,.12);font-weight:600}
  .zg-sprache input:focus-visible + span{outline:2px solid var(--akzent,#c9a23a);outline-offset:2px}
  .zg .schritte{font-size:12.5px;letter-spacing:.06em;text-transform:uppercase;color:var(--leise);margin:0 0 14px}
</style>
</head>
<body>
<div class="seite">
  <div class="wortmarke">
    <img src="/assets/img/logo-mark.webp?v=gold2609" alt="" width="58" height="46" fetchpriority="high">
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
        <?php /* Sprache des Dashboards (03.10.2026): vorgewählt die dieser Seite. */ ?>
        <fieldset class="zg-sprache"><legend><?= $h($T('sprache')) ?></legend>
          <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
            <label><input type="radio" name="sprache" value="<?= $l ?>"<?= $l === $sprache ? ' checked' : '' ?>><span><?= $h($wie) ?></span></label>
          <?php endforeach; ?>
        </fieldset>
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
