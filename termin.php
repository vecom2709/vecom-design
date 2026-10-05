<?php
declare(strict_types=1);
/* ==========================================================================
   termin.php — Gesprächstermin buchen (27.09.2026). Was dahinter passiert:
   app/src/AkquiseTermin.php.

     ohne ?t     freie Zeiten + Formular
     ?t=…        der eigene Termin: ansehen, in den Kalender, absagen
     ?ics=…      Kalenderdatei

   Ohne Skript. Absagen erst per Knopf (POST) -- Sicherheitsfilter in
   Postfächern rufen Links vorab auf, ein Aufruf darf nie absagen.
   ========================================================================== */

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
if (!is_file(__DIR__ . '/app/config.local.php')) { http_response_code(503); exit('Il servizio non è disponibile. · Der Dienst ist nicht verfügbar.'); }
foreach (['Config', 'Db', 'Status', 'Fmt', 'Events', 'Texte', 'Sprache', 'AkquiseCheck', 'AkquiseTermin'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
try { require_once __DIR__ . '/app/src/Einrichtung.php'; Einrichtung::selbsttaetig(false); } catch (Throwable $e) { }

/* Kalenderdatei */
if (isset($_GET['ics'])) {
    $t = null; try { $t = AkquiseTermin::laden((string) $_GET['ics']); } catch (Throwable $e) { }
    if (!$t || $t['status'] !== 'gebucht') { http_response_code(404); exit; }
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="vecom-termin.ics"');
    echo AkquiseTermin::ics($t);
    exit;
}

$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$sprache = Sprache::ausAnfrage();
if (!in_array($sprache, ['it', 'de', 'en'], true)) { $sprache = 'it'; }
$T = static fn(string $k) => Texte::h(Texte::AKQ_TERMIN[$k] ?? [], $sprache);
$fehler = ''; $termin = null; $frei = [];
$werte = ['slot' => '', 'name' => '', 'firma' => '', 'email' => '', 'telefon' => '', 'thema' => 'neu', 'art' => 'telefon', 'antwort' => $sprache, 'nachricht' => ''];
try {
    if (isset($_GET['t']) || isset($_POST['t'])) {
        $termin = AkquiseTermin::laden((string) ($_POST['t'] ?? $_GET['t']));
        if ($termin) {
            if (!isset($_GET['lang'])) { $sprache = (string) $termin['sprache']; }
            if ($post && ($_POST['a'] ?? '') === 'absagen' && strtotime((string) $termin['beginn']) > time()) {
                AkquiseTermin::absagen((int) $termin['id'], 'kunde');
                $termin = AkquiseTermin::laden((string) $termin['token']);
            }
        } else { http_response_code(404); $fehler = 'weg'; }
    } else {
        if ($post) {
            foreach (array_keys($werte) as $f) { $werte[$f] = mb_substr(trim((string) ($_POST[$f] ?? $werte[$f])), 0, 500); }
            if (trim((string) ($_POST['homepage'] ?? '')) !== '' || !AkquiseCheck::stempelGut((string) ($_POST['z'] ?? ''))) {
                $fehler = 'formular';
            } else {
                $r = AkquiseTermin::buchen(['slot' => $werte['slot'], 'name' => $werte['name'], 'firma' => $werte['firma'], 'email' => $werte['email'],
                    'telefon' => $werte['telefon'], 'thema' => $werte['thema'], 'art' => $werte['art'], 'sprache' => $werte['antwort'], 'nachricht' => $werte['nachricht']],
                    (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
                if ($r['ok']) { header('Location: termin.php?t=' . $r['token'] . '&ok=1', true, 303); exit; }
                $fehler = (string) $r['grund'];
            }
        }
        $frei = AkquiseTermin::freie();
        /* Von der Partnerseite mit schon gewählter Zeit (28.09.2026, R2) -- nur, wenn sie noch frei ist. */
        if (!$post && preg_match('~^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})$~', (string) ($_GET['slot'] ?? ''), $gs) && in_array($gs[2], $frei[$gs[1]] ?? [], true)) {
            $werte['slot'] = $gs[0];
        }
    }
} catch (Throwable $e) { $fehler = 'formular'; }
$T = static fn(string $k) => Texte::h(Texte::AKQ_TERMIN[$k] ?? [], $sprache);
$tage = explode(',', $T('tage'));
?><!doctype html>
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
<head>
<meta charset="utf-8">
<?= Sprache::skript() ?><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $h($T('meta_titel')) ?></title>
<?php if ($termin || $fehler === 'weg'): ?><meta name="robots" content="noindex, nofollow"><?php else: ?><meta name="description" content="<?= $h($T('lead')) ?>"><?php endif; ?>
<link rel="stylesheet" href="/assets/css/fonts.css">
<style>
  :root{--g:#0a0908;--f:#14110d;--f2:#1b1712;--t:#f7f3ea;--d:#b4ada2;--l:#8b847a;--a:#f1d38b;--li:rgba(255,255,255,.09);--li2:rgba(255,255,255,.16)}
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
  h1{font:800 clamp(30px,7vw,42px)/1.1 'Archivo',sans-serif;margin:0 0 12px}
  h2{font:700 17px/1.3 'Archivo',sans-serif;margin:0 0 12px}
  .lead{color:var(--d);font-size:17px;margin:0 0 6px}
  .klein{color:var(--l);font-size:12.5px;margin:10px 0 0}
  form.karte,.karte{background:var(--f);border:1px solid var(--li);border-radius:18px;padding:20px;margin:18px 0 0}
  .tag{margin:0 0 14px}
  .tag b{display:block;font-size:14px;margin:0 0 7px;color:var(--d)}
  .slots{display:flex;flex-wrap:wrap;gap:7px}
  .slot input{position:absolute;opacity:0;pointer-events:none}
  .slot span{display:inline-flex;align-items:center;justify-content:center;min-width:74px;min-height:44px;padding:8px 12px;border:1px solid var(--li2);border-radius:11px;font-weight:600;font-size:15px;cursor:pointer}
  .slot input:checked+span{background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438);color:#16120b;border-color:transparent}
  .slot input:focus-visible+span{outline:2px solid var(--a);outline-offset:2px}
  details.mehr summary{cursor:pointer;color:var(--d);margin:4px 0 12px}
  .feld{margin:0 0 13px}
  label.t{display:block;font-size:13.5px;color:var(--d);margin:0 0 5px;font-weight:600}
  label.t small{font-weight:400;color:var(--l)}
  input[type=text],input[type=email],input[type=tel],select,textarea{width:100%;min-height:48px;padding:11px 13px;border-radius:11px;border:1px solid var(--li2);background:#080d1c;color:var(--t);font:inherit}
  textarea{min-height:80px}
  input:focus,select:focus,textarea:focus{outline:2px solid var(--a);outline-offset:1px;border-color:transparent}
  .zwei{display:grid;grid-template-columns:1fr 1fr;gap:0 12px}
  .knopf{display:inline-flex;align-items:center;justify-content:center;width:100%;min-height:54px;padding:12px 22px;border:0;border-radius:12px;font:700 16.5px/1.2 'Inter',system-ui,sans-serif;cursor:pointer;text-decoration:none;color:#16120b;background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438)}
  .knopf.leise{background:transparent;color:var(--t);border:1px solid var(--li2);width:auto}
  .knopf:focus-visible{outline:2px solid var(--a);outline-offset:3px}
  .fehler{border:1px solid rgba(239,107,91,.5);background:rgba(239,107,91,.08);color:#ffb3a8;border-radius:12px;padding:11px 14px;margin:0 0 16px;font-size:14.5px}
  .lock{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
  .gross{font:700 22px/1.3 'Archivo',sans-serif;margin:0 0 6px}
  .reihe{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
  @media (max-width:520px){ .zwei{grid-template-columns:1fr} form.karte,.karte{padding:16px 14px} }
  /* Ausgewählt lesbar (04.10.2026, siehe app/assets/admin.css) */
  :root{color-scheme:dark}::selection{background:#f1d38b;color:#0a0908}select option,select optgroup{background-color:#0b1225;color:#f7f3ea}select option:checked{background:#c8963e linear-gradient(0deg,#c8963e,#c8963e);color:#0a0908}
  @supports (appearance: base-select) {  select:not([multiple]):not([size]),select:not([multiple]):not([size])::picker(select){appearance:base-select}  select:not([multiple]):not([size]){display:flex;align-items:center;gap:8px;cursor:pointer}  select:not([multiple]):not([size])::picker-icon{color:#b4ada2;transition:rotate .15s}  select:not([multiple]):not([size]):open::picker-icon{rotate:180deg}  ::picker(select){background:#0b1225;color:#f7f3ea;border:1px solid rgba(224,206,156,.26);border-radius:12px;padding:6px;    box-shadow:0 18px 40px rgba(0,0,0,.55);margin-top:4px;max-height:min(60vh,420px)}  select:not([multiple]):not([size]) option{padding:8px 12px;border-radius:8px;background:transparent;color:#f7f3ea;gap:8px;letter-spacing:0;text-transform:none}  select:not([multiple]):not([size]) option:hover,select:not([multiple]):not([size]) option:focus-visible{background:#101933;color:#fff;outline:none}  select:not([multiple]):not([size]) option:checked{background:#c8963e;color:#0a0908;font-weight:600}  select:not([multiple]):not([size]) option:checked:hover{background:#d6a849;color:#0a0908}  select:not([multiple]):not([size]) option::checkmark{color:currentColor}}
</style>
</head>
<body>
<main>
  <div class="kopf">
    <a class="marke" href="/"><img src="/assets/img/logo-mark.webp?v=gold2609" alt="" width="40" height="32"><span><b>VECOM</b> DESIGN</span></a>
    <nav class="sprachen" aria-label="Lingua / Sprache / Language">
      <?php foreach (['it', 'de', 'en'] as $l): ?><a href="termin.php?<?= $h(http_build_query(array_filter(['t' => $termin['token'] ?? null, 'lang' => $l]))) ?>" lang="<?= $l ?>"<?= $l === $sprache ? ' aria-current="true"' : '' ?>><?= strtoupper($l) ?></a><?php endforeach; ?>
    </nav>
  </div>

<?php if ($fehler === 'weg'): ?>
  <h1><?= $h($T('weg')) ?></h1>
  <a class="knopf leise" href="termin.php?lang=<?= $h($sprache) ?>"><?= $h($T('neu_buchen')) ?></a>

<?php elseif ($termin): $b = strtotime((string) $termin['beginn']); ?>
  <p class="zeile"><?= $h($T('zeile')) ?></p>
  <?php if ($termin['status'] === 'abgesagt'): ?>
    <h1><?= $h($T('abgesagt')) ?></h1>
    <p class="lead"><?= $h(AkquiseTermin::zeitText($b, $sprache)) ?></p>
    <div class="reihe"><a class="knopf leise" href="termin.php?lang=<?= $h($sprache) ?>"><?= $h($T('neu_buchen')) ?></a></div>
  <?php else: ?>
    <h1><?= $h($T('ok_titel')) ?></h1>
    <div class="karte">
      <p class="gross"><?= $h(AkquiseTermin::zeitText($b, $sprache)) ?></p>
      <p class="lead" style="margin:0"><?= $h($T('art_' . $termin['art'])) ?> · <?= $h($T('thema_' . $termin['thema'])) ?></p>
      <p class="klein"><?= $h($T('zeitzone')) ?></p>
      <?php if (!empty($_GET['ok'])): ?><p style="margin:12px 0 0"><?= $h(strtr($T('ok_text'), ['{email}' => (string) $termin['email']])) ?></p><?php endif; ?>
      <?php if ($b > time() && $termin['status'] === 'gebucht'): ?>
        <div class="reihe">
          <a class="knopf" style="width:auto" href="termin.php?ics=<?= $h((string) $termin['token']) ?>"><?= $h($T('kalender')) ?></a>
          <form method="post" action="termin.php"><input type="hidden" name="t" value="<?= $h((string) $termin['token']) ?>"><input type="hidden" name="a" value="absagen">
            <button class="knopf leise" type="submit"><?= $h($T('absagen')) ?></button></form>
        </div>
      <?php else: ?><p class="klein"><?= $h($T('vorbei')) ?></p><?php endif; ?>
    </div>
  <?php endif; ?>

<?php else: ?>
  <p class="zeile"><?= $h($T('zeile')) ?></p>
  <h1><?= $h($T('h1')) ?></h1>
  <p class="lead"><?= $h($T('lead')) ?></p>
  <p class="klein" style="margin:0"><?= $h($T('zeitzone')) ?></p>
  <?php if (!$frei): ?>
    <div class="karte"><p style="margin:0"><?= $h($T('keine')) ?></p>
      <div class="reihe"><a class="knopf leise" href="/?lang=<?= $h($sprache) ?>#contact"><?= $h(Texte::h(['it' => 'Scriverci', 'de' => 'Uns schreiben', 'en' => 'Write to us'], $sprache)) ?></a></div></div>
  <?php else: ?>
  <form class="karte" method="post" action="termin.php?lang=<?= $h($sprache) ?>" novalidate>
    <?php if ($fehler !== ''): ?><p class="fehler" role="alert"><?= $h($T('fehler_' . $fehler) ?: $T('fehler_formular')) ?></p><?php endif; ?>
    <input type="hidden" name="z" value="<?= $h(AkquiseCheck::stempel()) ?>">
    <div class="lock" aria-hidden="true"><label>Homepage <input type="text" name="homepage" tabindex="-1" autocomplete="off"></label></div>
    <h2><?= $h($T('schritt_zeit')) ?></h2>
    <?php $i = 0; $mehrOffen = false; foreach ($frei as $datum => $zeiten): $ts = strtotime($datum);
      if ($i === 5) { echo '<details class="mehr"' . ($werte['slot'] !== '' && substr($werte['slot'], 0, 10) >= $datum ? ' open' : '') . '><summary>' . $h(Texte::h(['it' => 'Altri giorni', 'de' => 'Weitere Tage', 'en' => 'More days'], $sprache)) . '</summary>'; $mehrOffen = true; } $i++; ?>
      <fieldset class="tag" style="border:0;padding:0"><legend><b><?= $h(($tage[(int) date('N', $ts) - 1] ?? '') . ' ' . date('d.m.', $ts)) ?></b></legend>
        <div class="slots"><?php foreach ($zeiten as $z): $wert = $datum . ' ' . $z; ?>
          <label class="slot"><input type="radio" name="slot" value="<?= $h($wert) ?>"<?= $werte['slot'] === $wert ? ' checked' : '' ?> required><span><?= $h($z) ?></span></label>
        <?php endforeach; ?></div></fieldset>
    <?php endforeach; if ($mehrOffen) { echo '</details>'; } ?>

    <h2 style="margin-top:18px"><?= $h($T('schritt_daten')) ?></h2>
    <div class="zwei">
      <div class="feld"><label class="t" for="t-name"><?= $h($T('f_name')) ?></label><input id="t-name" type="text" name="name" autocomplete="name" required maxlength="120" value="<?= $h($werte['name']) ?>"></div>
      <div class="feld"><label class="t" for="t-firma"><?= $h($T('f_firma')) ?> <small>(<?= $h($T('f_optional')) ?>)</small></label><input id="t-firma" type="text" name="firma" autocomplete="organization" maxlength="190" value="<?= $h($werte['firma']) ?>"></div>
      <div class="feld"><label class="t" for="t-email"><?= $h($T('f_email')) ?></label><input id="t-email" type="email" name="email" autocomplete="email" required maxlength="190" value="<?= $h($werte['email']) ?>"></div>
      <div class="feld"><label class="t" for="t-tel"><?= $h($T('f_telefon')) ?> <small>(<?= $h($T('f_optional')) ?>)</small></label><input id="t-tel" type="tel" name="telefon" autocomplete="tel" maxlength="40" value="<?= $h($werte['telefon']) ?>"></div>
      <div class="feld"><label class="t" for="t-thema"><?= $h($T('f_thema')) ?></label><select id="t-thema" name="thema">
        <?php foreach (AkquiseTermin::THEMEN as $th): ?><option value="<?= $th ?>"<?= $werte['thema'] === $th ? ' selected' : '' ?>><?= $h($T('thema_' . $th)) ?></option><?php endforeach; ?></select></div>
      <div class="feld"><label class="t" for="t-art"><?= $h($T('f_art')) ?></label><select id="t-art" name="art">
        <?php foreach (['telefon', 'video'] as $a): ?><option value="<?= $a ?>"<?= $werte['art'] === $a ? ' selected' : '' ?>><?= $h($T('art_' . $a)) ?></option><?php endforeach; ?></select></div>
      <div class="feld"><label class="t" for="t-sp"><?= $h($T('f_sprache')) ?></label><select id="t-sp" name="antwort">
        <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $w): ?><option value="<?= $l ?>"<?= $werte['antwort'] === $l ? ' selected' : '' ?>><?= $w ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="feld"><label class="t" for="t-n"><?= $h($T('f_nachricht')) ?> <small>(<?= $h($T('f_optional')) ?>)</small></label><textarea id="t-n" name="nachricht" maxlength="500"><?= $h($werte['nachricht']) ?></textarea></div>
    <button class="knopf" type="submit"><?= $h($T('knopf')) ?> →</button>
    <p class="klein"><?= $h($T('hinweis')) ?> <a href="<?= $h(Sprache::legal($sprache, 'privacy')) ?>" style="color:var(--d)"><?= $h(Texte::h(['it' => 'Privacy', 'de' => 'Datenschutz', 'en' => 'Privacy'], $sprache)) ?></a></p>
  </form>
  <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
