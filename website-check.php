<?php
declare(strict_types=1);
/* ==========================================================================
   website-check.php — der kostenlose Website-Check auf vecom-design.it
   (27.09.2026). Was er tut und was nicht: app/src/AkquiseCheck.php.

   Zwei Zustände:
     ohne ?t   Formular (auch nach einem Fehler, mit den Eingaben)
     ?t=…      Ergebnis. Nach dem Absenden mit &n=1 -- nur dann stehen die
               Hinweise zur Anfrage und zur Bestätigungsmail darunter. Wer
               den Link weitergibt, zeigt nur die sechs Punkte.

   Ohne Skript (CSP default-src 'none'). Gegen Roboter: ein leeres Lockfeld,
   ein unterschriebener Zeitstempel (zu schnell oder zu alt = kein Mensch)
   und die Mengen je Absender und Domain in AkquiseCheck.
   ========================================================================== */

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
if (!is_file(__DIR__ . '/app/config.local.php')) {
    http_response_code(503);
    exit('Il servizio non è disponibile. · Der Dienst ist nicht verfügbar.');
}
foreach (['Config', 'Db', 'Status', 'Fmt', 'Events', 'Texte', 'Sprache', 'AkquiseCheck', 'AkquiseEinwilligung'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
try { require_once __DIR__ . '/app/src/Einrichtung.php'; Einrichtung::selbsttaetig(false); } catch (Throwable $e) { }

$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$sprache = Sprache::ausAnfrage();
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }
$T = static fn(string $k) => Texte::h(Texte::AKQ_CHECK[$k] ?? [], $sprache);
$C = static fn(string $k) => Texte::h(Texte::PARTNER_CHECK[$k] ?? [], $sprache);
$zitat = static fn(string $t) => ['it' => '«', 'de' => '„', 'en' => '“'][$sprache] . $t . ['it' => '»', 'de' => '“', 'en' => '”'][$sprache];

$fehler = '';
$werte = ['url' => '', 'firma' => '', 'name' => '', 'email' => '', 'telefon' => '', 'land' => $sprache === 'de' ? 'DE' : 'IT',
          'antwort' => $sprache, 'ausfuehrlich' => false, 'marketing' => false, 'wa' => false, 'whatsapp' => ''];
$check = null;
$an = true;
try {
    $an = AkquiseCheck::an();
    if ($post) {
        foreach (['url', 'firma', 'name', 'email', 'telefon', 'whatsapp'] as $f) { $werte[$f] = mb_substr(trim((string) ($_POST[$f] ?? '')), 0, 500); }
        $werte['land'] = in_array($_POST['land'] ?? '', ['IT', 'DE'], true) ? (string) $_POST['land'] : $werte['land'];
        $werte['antwort'] = in_array($_POST['antwort'] ?? '', ['it', 'de', 'en'], true) ? (string) $_POST['antwort'] : $sprache;
        $werte['ausfuehrlich'] = !empty($_POST['ausfuehrlich']);
        /* WhatsApp (27.09.2026): Der WA-Wortlaut schließt die E-Mail ein -- wer nur
           dieses Häkchen setzt, hat beidem zugestimmt. Leere Nummer: die Telefonnummer. */
        $werte['wa'] = !empty($_POST['wa']);
        $werte['marketing'] = !empty($_POST['marketing']) || $werte['wa'];
        if (trim((string) ($_POST['homepage'] ?? '')) !== '') {
            $fehler = 'zeit';           // Lockfeld ausgefüllt: nichts verraten, nichts tun
        } elseif (!AkquiseCheck::stempelGut((string) ($_POST['z'] ?? ''))) {
            $fehler = 'zeit';
        } else {
            /* Leise Bremse je Adresse: höchstens alle 20 Sekunden ein Check. */
            $sperre = sys_get_temp_dir() . '/vecomcheck_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
            if (is_file($sperre) && time() - filemtime($sperre) < 20) {
                $fehler = 'warten';
            } else {
                @touch($sperre);
                $r = AkquiseCheck::anlegen([
                    'url' => $werte['url'], 'firma' => $werte['firma'], 'name' => $werte['name'], 'email' => $werte['email'],
                    'telefon' => $werte['telefon'], 'land' => $werte['land'], 'sprache' => $werte['antwort'], 'sprache_seite' => $sprache,
                    'ausfuehrlich' => $werte['ausfuehrlich'], 'marketing' => $werte['marketing'],
                    'whatsapp' => $werte['wa'] ? ($werte['whatsapp'] !== '' ? $werte['whatsapp'] : $werte['telefon']) : null,
                ], (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
                if ($r['ok']) {
                    header('Location: website-check.php?t=' . $r['token'] . '&n=1&lang=' . $sprache, true, 303);
                    exit;
                }
                $fehler = (string) $r['grund'];
            }
        }
    } elseif (isset($_GET['url']) && !isset($_GET['t'])) {
        /* Aus dem Kurz-Check der Partnerseite (28.09.2026, R7): die Adresse steht schon im Feld. */
        $werte['url'] = mb_substr(trim((string) $_GET['url']), 0, 200);
    } elseif (isset($_GET['t'])) {
        $check = AkquiseCheck::laden((string) $_GET['t']);
        if ($check === null) {
            http_response_code(404);
            $fehler = 'weg';
        }
    }
} catch (Throwable $e) {
    $fehler = 'zeit';
}
$neu = !empty($_GET['n']);
$titel = $check ? strtr($C('titel'), ['{host}' => (string) $check['host']]) : $T('meta_titel');
$sprachLinks = array_map(static fn($l) => ['l' => $l, 'href' => 'website-check.php?' . http_build_query(array_filter(['t' => $check['token'] ?? null, 'lang' => $l]))], ['it', 'de', 'en']);
?><!doctype html>
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
<head>
<meta charset="utf-8">
<?= Sprache::skript() ?><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($titel) ?></title>
<?php if ($check || $fehler === 'weg'): ?>
<meta name="robots" content="noindex, nofollow">
<?php else: ?>
<meta name="description" content="<?= $h($T('meta_beschr')) ?>">
<link rel="canonical" href="https://vecom-design.it/website-check.php<?= $sprache !== 'it' ? '?lang=' . $h($sprache) : '' ?>">
<?php foreach (['it', 'de', 'en'] as $l): ?><link rel="alternate" hreflang="<?= $l ?>" href="https://vecom-design.it/website-check.php<?= $l !== 'it' ? '?lang=' . $l : '' ?>">
<?php endforeach; ?><link rel="alternate" hreflang="x-default" href="https://vecom-design.it/website-check.php">
<?php endif; ?>
<link rel="stylesheet" href="/assets/css/fonts.css">
<style>
  :root{--g:#0a0908;--f:#14110d;--f2:#1b1712;--t:#f7f3ea;--d:#b4ada2;--l:#8b847a;--a:#f1d38b;--li:rgba(255,255,255,.09);--li2:rgba(255,255,255,.16);--rot:#ef6b5b}
  *{box-sizing:border-box}
  body{margin:0;background:radial-gradient(1200px 600px at 50% -200px,rgba(241,211,139,.09),transparent 70%),var(--g);color:var(--t);font:16px/1.6 'Inter',system-ui,sans-serif;min-height:100vh}
  main{max-width:680px;margin:0 auto;padding:28px 16px 56px}
  .kopf{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:34px;flex-wrap:wrap}
  .marke{display:flex;gap:8px;align-items:center;font:800 14px/1 'Archivo',sans-serif;letter-spacing:.08em;color:var(--t);text-decoration:none}
  .marke img{width:40px;height:auto}.marke b{color:var(--a)}
  .sprachen{display:flex;gap:4px}
  .sprachen a{min-width:44px;min-height:36px;display:inline-grid;place-items:center;border-radius:8px;color:var(--l);text-decoration:none;font-size:13px;font-weight:600;letter-spacing:.06em}
  .sprachen a[aria-current]{color:var(--t);background:var(--f2)}
  .zeile{font-size:12.5px;text-transform:uppercase;letter-spacing:.12em;color:var(--a);font-weight:600;margin:0 0 10px}
  h1{font:800 clamp(30px,7vw,44px)/1.08 'Archivo',sans-serif;margin:0 0 14px;letter-spacing:-.01em;word-break:break-word}
  .lead{color:var(--d);font-size:17px;margin:0 0 8px;max-width:60ch}
  .was{color:var(--l);font-size:13.5px;margin:0 0 26px}
  form{background:var(--f);border:1px solid var(--li);border-radius:18px;padding:22px 20px}
  .feld{margin:0 0 14px}
  label.t{display:block;font-size:13.5px;color:var(--d);margin:0 0 5px;font-weight:600}
  label.t small{font-weight:400;color:var(--l)}
  input[type=text],input[type=email],input[type=tel],input[type=url],select{width:100%;min-height:48px;padding:11px 13px;border-radius:11px;border:1px solid var(--li2);background:#0f0d0a;color:var(--t);font:inherit}
  input:focus,select:focus{outline:2px solid var(--a);outline-offset:1px;border-color:transparent}
  .gross input{font-size:18px;min-height:56px}
  .zwei{display:grid;grid-template-columns:1fr 1fr;gap:0 12px}
  .haken{display:flex;gap:11px;align-items:flex-start;padding:12px 13px;border:1px solid var(--li);border-radius:12px;margin:0 0 10px;cursor:pointer;background:rgba(255,255,255,.015)}
  .haken input{width:20px;height:20px;margin:2px 0 0;flex:none;accent-color:#d9b25e}
  .haken span{font-size:14.5px}.haken small{display:block;color:var(--l);font-size:12.5px;margin-top:3px}
  .wa-nr{margin:-4px 0 12px 44px}
  .haken:has(input[name=wa]:not(:checked)) + .wa-nr{display:none}
  .knopf{display:inline-flex;align-items:center;justify-content:center;width:100%;min-height:54px;padding:12px 22px;border:0;border-radius:12px;font:700 16.5px/1.2 'Inter',system-ui,sans-serif;cursor:pointer;text-decoration:none;
         color:#16120b;background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438);margin-top:6px}
  .knopf:focus-visible,.leise:focus-visible{outline:2px solid var(--a);outline-offset:3px}
  .fehler{border:1px solid rgba(239,107,91,.5);background:rgba(239,107,91,.08);color:#ffb3a8;border-radius:12px;padding:11px 14px;margin:0 0 16px;font-size:14.5px}
  .klein{color:var(--l);font-size:12.5px;margin:14px 0 0}
  .klein a{color:var(--d)}
  .lock{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
  .fazit{border:1px solid rgba(241,211,139,.45);border-radius:12px;padding:12px 14px;font-weight:600;margin:0 0 18px}
  ul.punkte{list-style:none;padding:0;margin:0 0 22px;display:grid;gap:10px}
  ul.punkte li{background:var(--f);border:1px solid var(--li);border-radius:12px;padding:12px 14px;display:flex;gap:12px;align-items:flex-start}
  ul.punkte b{display:block;font-size:15px}
  ul.punkte span.t{color:var(--d);font-size:14.5px}
  .ampel{flex:0 0 12px;height:12px;border-radius:50%;margin-top:6px}
  .gut{background:#34d39b}.hinweis{background:#e8b64c}.schlecht{background:var(--rot)}
  .notiz{border-left:3px solid var(--a);padding:6px 0 6px 14px;margin:0 0 12px;color:var(--t)}
  .weiter{border:1px solid var(--li);background:linear-gradient(160deg,rgba(241,211,139,.10),rgba(241,211,139,.02));border-radius:16px;padding:20px;margin:22px 0 0}
  .weiter h2{font:700 20px/1.25 'Archivo',sans-serif;margin:0 0 6px}
  .weiter p{color:var(--d);margin:0 0 14px}
  .leise{display:inline-flex;align-items:center;min-height:44px;margin-top:16px;color:var(--d);text-decoration:underline;text-underline-offset:3px}
  @media (max-width:520px){ .zwei{grid-template-columns:1fr} form{padding:18px 14px} }
</style>
</head>
<body>
<main>
  <div class="kopf">
    <a class="marke" href="/"><img src="/assets/img/logo-mark.webp?v=gold2609" alt="" width="40" height="32"><span><b>VECOM</b> DESIGN</span></a>
    <nav class="sprachen" aria-label="Lingua / Sprache / Language">
      <?php foreach ($sprachLinks as $s): ?><a href="<?= $h($s['href']) ?>" lang="<?= $s['l'] ?>"<?= $s['l'] === $sprache ? ' aria-current="true"' : '' ?>><?= strtoupper($s['l']) ?></a><?php endforeach; ?>
    </nav>
  </div>

<?php if ($fehler === 'weg'): ?>
  <h1><?= $h($C('weg')) ?></h1>
  <a class="knopf" href="website-check.php?lang=<?= $h($sprache) ?>" style="width:auto"><?= $h($T('nochmal')) ?></a>

<?php elseif ($check):
  $e = $check['ergebnis'];
  $schlecht = (int) $check['schlecht'];
  $fazit = $schlecht === 0 ? $C('fazit0') : ($schlecht < 3 ? $C('fazit1') : $C('fazit3')); ?>
  <p class="zeile"><?= $h($T('marke_zeile')) ?></p>
  <h1><?= $h($titel) ?></h1>
  <p class="lead"><?= $h(strtr($T('r_lead'), ['{datum}' => date('d.m.Y', strtotime((string) $check['created_at']))])) ?></p>
  <p class="fazit"><?= $h($fazit) ?></p>
  <ul class="punkte">
    <?php foreach ($e['punkte'] ?? [] as $pk):
      $K = Texte::PARTNER_CHECK['punkte'][$pk['was']] ?? null; if (!$K) { continue; }
      $satzK = $pk['was'] === 'aktuell' && $pk['stand'] === 'hinweis' && $pk['wert'] === '' ? 'hinweis_leer' : $pk['stand'];
      $satz = strtr(Texte::h($K[$satzK] ?? $K[$pk['stand']] ?? [], $sprache), ['{wert}' => (string) $pk['wert']]); ?>
      <li><span class="ampel <?= $h($pk['stand']) ?>" aria-hidden="true"></span><div><b><?= $h(Texte::h($K['titel'], $sprache)) ?></b><span class="t"><?= $h($satz) ?></span></div></li>
    <?php endforeach; ?>
  </ul>
  <?php if ($neu): ?>
    <?php if ((int) $check['ausfuehrlich'] === 1): ?><p class="notiz"><?= $h($T('n_ausf')) ?></p><?php endif; ?>
    <?php if ((int) $check['marketing'] === 1 && $check['einwilligung_stand'] === 'ok'): ?><p class="notiz"><?= $h($T('n_mkt')) ?></p>
    <?php elseif ((int) $check['marketing'] === 1 && $check['einwilligung_stand'] === 'zuviel'): ?><p class="notiz"><?= $h($T('n_mkt_zuviel')) ?></p><?php endif; ?>
  <?php endif; ?>
  <div class="weiter">
    <h2><?= $h($T('weiter_titel')) ?></h2>
    <p><?= $h($T('weiter_text')) ?></p>
    <a class="knopf" href="/bedarf.php?lang=<?= $h($sprache) ?>"><?= $h($T('weiter_knopf')) ?> →</a>
  </div>
  <a class="leise" href="website-check.php?lang=<?= $h($sprache) ?>"><?= $h($T('nochmal')) ?></a>
  <p class="klein"><?= $h($C('klein')) ?></p>

<?php else: ?>
  <p class="zeile"><?= $h($T('marke_zeile')) ?></p>
  <h1><?= $h($T('h1')) ?></h1>
  <p class="lead"><?= $h($T('lead')) ?></p>
  <p class="was"><?= $h($T('was')) ?></p>
  <?php if (!$an): ?>
    <p class="fehler" role="alert"><?= $h($T('fehler_aus')) ?></p>
  <?php else: ?>
  <form method="post" action="website-check.php?lang=<?= $h($sprache) ?>" novalidate>
    <?php if ($fehler !== ''): ?><p class="fehler" role="alert"><?= $h($T('fehler_' . $fehler) ?: $T('fehler_zeit')) ?></p><?php endif; ?>
    <input type="hidden" name="z" value="<?= $h(AkquiseCheck::stempel()) ?>">
    <div class="lock" aria-hidden="true"><label>Homepage <input type="text" name="homepage" tabindex="-1" autocomplete="off"></label></div>
    <div class="feld gross"><label class="t" for="c-url"><?= $h($T('f_url')) ?></label>
      <input id="c-url" type="text" name="url" inputmode="url" autocomplete="url" required value="<?= $h($werte['url']) ?>" placeholder="<?= $h($T('f_url_bsp')) ?>"<?= $fehler === 'adresse' ? ' aria-invalid="true" autofocus' : '' ?>></div>
    <div class="zwei">
      <div class="feld"><label class="t" for="c-firma"><?= $h($T('f_firma')) ?></label>
        <input id="c-firma" type="text" name="firma" autocomplete="organization" required maxlength="190" value="<?= $h($werte['firma']) ?>"></div>
      <div class="feld"><label class="t" for="c-name"><?= $h($T('f_name')) ?></label>
        <input id="c-name" type="text" name="name" autocomplete="name" required maxlength="120" value="<?= $h($werte['name']) ?>"></div>
      <div class="feld"><label class="t" for="c-email"><?= $h($T('f_email')) ?></label>
        <input id="c-email" type="email" name="email" autocomplete="email" required maxlength="190" value="<?= $h($werte['email']) ?>"<?= $fehler === 'email' ? ' aria-invalid="true" autofocus' : '' ?>></div>
      <div class="feld"><label class="t" for="c-tel"><?= $h($T('f_telefon')) ?> <small>(<?= $h($T('f_optional')) ?>)</small></label>
        <input id="c-tel" type="tel" name="telefon" autocomplete="tel" maxlength="40" value="<?= $h($werte['telefon']) ?>"></div>
      <div class="feld"><label class="t" for="c-land"><?= $h($T('f_land')) ?></label>
        <select id="c-land" name="land"><?php foreach (['IT', 'DE'] as $l): ?><option value="<?= $l ?>"<?= $werte['land'] === $l ? ' selected' : '' ?>><?= $h($T('land_' . $l)) ?></option><?php endforeach; ?></select></div>
      <div class="feld"><label class="t" for="c-sp"><?= $h($T('f_sprache')) ?></label>
        <select id="c-sp" name="antwort"><?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $w): ?><option value="<?= $l ?>"<?= $werte['antwort'] === $l ? ' selected' : '' ?>><?= $w ?></option><?php endforeach; ?></select></div>
    </div>
    <label class="haken"><input type="checkbox" name="ausfuehrlich" value="1"<?= $werte['ausfuehrlich'] ? ' checked' : '' ?>>
      <span><?= $h($T('ausf')) ?><small><?= $h($T('ausf_hilfe')) ?></small></span></label>
    <label class="haken"><input type="checkbox" name="marketing" value="1"<?= $werte['marketing'] ? ' checked' : '' ?>>
      <span><?= $h($T('mkt_vor')) ?> <?= $h($zitat(AkquiseEinwilligung::wortlaut($sprache))) ?><small><?= $h($T('mkt_hilfe')) ?></small></span></label>
    <label class="haken"><input type="checkbox" name="wa" value="1"<?= $werte['wa'] ? ' checked' : '' ?>>
      <span><?= $h($T('wa_vor')) ?> <?= $h($zitat(AkquiseEinwilligung::wortlaut($sprache, $T('wa_nummer')))) ?><small><?= $h($T('wa_hilfe')) ?></small></span></label>
    <div class="feld wa-nr"><label class="t" for="c-wa"><?= $h($T('f_whatsapp')) ?> <small>(<?= $h($T('wa_leer')) ?>)</small></label>
      <input id="c-wa" type="tel" name="whatsapp" autocomplete="tel" inputmode="tel" maxlength="40" value="<?= $h($werte['whatsapp']) ?>" placeholder="<?= $werte['land'] === 'DE' ? '+49 171 1234567' : '+39 333 1234567' ?>"<?= $fehler === 'whatsapp' ? ' aria-invalid="true" autofocus' : '' ?>></div>
    <button class="knopf" type="submit"><?= $h($T('knopf')) ?> →</button>
    <p class="klein"><?= $h($T('datenschutz')) ?> <a href="<?= $h(Sprache::legal($sprache, 'privacy')) ?>"><?= $h($T('datenschutz_link')) ?></a></p>
  </form>
  <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
