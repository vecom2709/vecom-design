<?php
declare(strict_types=1);
/* ==========================================================================
   check.php — der Schnellcheck-Bericht, den ein Partner weiterschickt
   (26.09.2026). Nur mit Schlüssel, nie im Index, kein Skript.

   Unten steht die Empfehlung des Partners mit seinem Link (/p/CODE/check):
   Wer vom Bericht aus anfragt, zählt für den, der ihn geschickt hat.
   Ist der Partner nicht mehr aktiv, bleibt der Bericht lesbar, aber ohne
   seinen Namen -- der Knopf führt dann auf die Startseite.
   ========================================================================== */
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");

$token = (string) ($_GET['t'] ?? '');
$z = null; $p = null; $sprache = 'it';
if (preg_match('~^[a-f0-9]{32}$~', $token) && is_file(__DIR__ . '/app/config.local.php')) {
    foreach (['Config', 'Db', 'Status', 'Fmt', 'Events', 'Texte', 'Sprache', 'Partner', 'PartnerWerbung', 'PartnerCheck', 'PartnerMarketing', 'PartnerSeite'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
    date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
    try {
        $z = Db::one('SELECT * FROM partner_checks WHERE token = ?', [$token]) ?: null;
        if ($z) {
            $p = Db::one("SELECT * FROM partner WHERE id = ? AND status = 'aktiv'", [(int) $z['partner_id']]) ?: null;
            /* Heißer Kontakt (28.09.2026): Zählt nur, wenn ein Mensch den Bericht
               öffnet, der nicht der Partner selbst ist (Keks aus seinem
               Partnerbereich) -- dann Hinweis aufs Handy des Partners.
               Vorschau-Abrufe von WhatsApp & Co. sind Programme und zählen nicht. */
            $selbstKeks = strtoupper((string) ($_COOKIE[Partner::KEKS_SELBST] ?? ''));
            if (!Partner::istRoboter((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')) && !($p && $selbstKeks === strtoupper((string) $p['code']))) {
                try { PartnerMarketing::checkAufruf($z); } catch (Throwable $e) { error_log('check heiss: ' . $e->getMessage()); }
            }
            // Sprache: ausdrücklich gewählt, sonst die des Partners (er schreibt seinen Bekannten in seiner Sprache).
            $sprache = isset($_GET['lang']) ? Sprache::ausAnfrage() : (in_array((string) ($p['sprache'] ?? ''), ['it', 'de', 'en'], true) ? (string) $p['sprache'] : Sprache::ausAnfrage());
        }
    } catch (Throwable $e) { $z = null; }
}
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
if ($z === null) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1"><title>Vecom Design</title>'
       . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#060a16;color:#b4ada2;font:17px/1.5 system-ui,sans-serif;padding:16px;text-align:center">'
       . '<p>Questo rapporto non è più disponibile. · Dieser Bericht ist nicht mehr verfügbar.<br><a style="color:#f1d38b" href="https://vecom-design.it">vecom-design.it</a></p>';
    exit;
}
$e = json_decode((string) $z['ergebnis'], true) ?: ['punkte' => []];
$C = static fn(string $k): string => Texte::h(Texte::PARTNER_CHECK[$k] ?? [], $sprache);
$schlecht = count(array_filter($e['punkte'], static fn($x) => $x['stand'] === 'schlecht'));
$fazit = $schlecht === 0 ? $C('fazit0') : ($schlecht < 3 ? $C('fazit1') : $C('fazit3'));
$name = $p ? Partner::anzeigeName($p) : '';
$ziel = $p ? PartnerWerbung::link($p, 'check') : 'https://vecom-design.it/';
$foto = $p ? PartnerWerbung::fotoAdresse($p) : null;
?><!doctype html>
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
<head>
<meta charset="utf-8">
<?= Sprache::skript() ?><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
<title><?= $h(strtr($C('titel'), ['{host}' => (string) $z['host']])) ?></title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<style>
  :root{--g:#0a0908;--f:#14110d;--t:#f7f3ea;--d:#b4ada2;--l:#8b847a;--a:#f1d38b;--li:rgba(255,255,255,.09)}
  *{box-sizing:border-box}
  body{margin:0;background:var(--g);color:var(--t);font:16px/1.6 'Inter',system-ui,sans-serif}
  main{max-width:640px;margin:0 auto;padding:28px 16px 48px}
  .marke{display:flex;gap:8px;align-items:center;font:800 14px/1 'Archivo',sans-serif;letter-spacing:.08em;margin-bottom:26px}
  .marke img{width:40px;height:auto}.marke b{color:var(--a)}
  h1{font:800 clamp(26px,6vw,34px)/1.15 'Archivo',sans-serif;margin:0 0 8px;word-break:break-word}
  .lead{color:var(--d);margin:0 0 18px}
  .fazit{border:1px solid rgba(241,211,139,.45);border-radius:12px;padding:12px 14px;font-weight:600;margin:0 0 18px}
  ul{list-style:none;padding:0;margin:0 0 26px;display:grid;gap:10px}
  li{background:var(--f);border:1px solid var(--li);border-radius:12px;padding:12px 14px;display:flex;gap:12px;align-items:flex-start}
  li b{display:block;font-size:15px}
  li span.t{color:var(--d);font-size:14.5px}
  .ampel{flex:0 0 12px;height:12px;border-radius:50%;margin-top:6px}
  .gut{background:#34d39b}.hinweis{background:#e8b64c}.schlecht{background:#ef6b5b}
  .empf{border:1px solid var(--li);background:linear-gradient(160deg,rgba(241,211,139,.10),rgba(241,211,139,.02));border-radius:14px;padding:18px}
  .empf .kopf{display:flex;gap:12px;align-items:center;margin-bottom:8px}
  .empf img{width:52px;height:52px;border-radius:50%;object-fit:cover;border:1px solid rgba(241,211,139,.5)}
  .empf h2{font:700 18px/1.3 'Archivo',sans-serif;margin:0}
  .empf p{color:var(--d);margin:0 0 14px}
  .knopf{display:inline-flex;align-items:center;justify-content:center;min-height:50px;padding:12px 22px;border-radius:11px;font-weight:700;text-decoration:none;
         color:#16120b;background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438)}
  .knopf:focus-visible{outline:2px solid var(--a);outline-offset:3px}
  .klein{color:var(--l);font-size:12.5px;margin:22px 0 0}
  .wl{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-top:18px;font-size:14.5px}
  .wl a{display:inline-flex;align-items:center;min-height:44px;padding:8px 16px;border:1px solid var(--li);border-radius:10px;color:var(--t);text-decoration:none}
  .wl a:focus-visible{outline:2px solid var(--a);outline-offset:2px}
  .stimme{margin:16px 0 0;border:1px solid var(--li);border-radius:14px;padding:14px 16px;background:var(--f)}
  .stimme .st-kopf{font-size:12.5px;color:var(--l);letter-spacing:.04em;text-transform:uppercase;margin-bottom:6px}
  .stimme .sterne{color:var(--a);letter-spacing:2px}
  .stimme blockquote{margin:6px 0;font-size:15.5px;line-height:1.6}
  .stimme figcaption{color:var(--d);font-size:13.5px}
</style>
</head>
<body>
<main>
  <div class="marke"><img src="/assets/img/logo-mark.webp?v=gold2609" alt="" width="40" height="32"><span><b>VECOM</b> DESIGN</span></div>
  <h1><?= $h(strtr($C('titel'), ['{host}' => (string) $z['host']])) ?></h1>
  <p class="lead"><?= $h(strtr($C('lead'), ['{datum}' => date('d.m.Y', strtotime((string) $z['created_at']))])) ?></p>
  <p class="fazit"><?= $h($fazit) ?></p>
  <ul>
    <?php foreach ($e['punkte'] as $pk):
      $K = Texte::PARTNER_CHECK['punkte'][$pk['was']] ?? null; if (!$K) { continue; }
      $satzK = ($pk['k'] ?? '') !== '' ? (string) $pk['k'] : ($pk['was'] === 'aktuell' && $pk['stand'] === 'hinweis' && $pk['wert'] === '' ? 'hinweis_leer' : $pk['stand']);
      $satz = strtr(Texte::h($K[$satzK] ?? $K[$pk['stand']] ?? [], $sprache), ['{wert}' => (string) $pk['wert']]); ?>
      <li><span class="ampel <?= $h($pk['stand']) ?>" aria-hidden="true"></span><div><b><?= $h(Texte::h($K['titel'], $sprache)) ?></b><span class="t"><?= $h($satz) ?></span></div></li>
    <?php endforeach; ?>
  </ul>
  <div class="empf">
    <div class="kopf"><?php if ($foto): ?><img src="<?= $h($foto) ?>" alt=""><?php endif; ?>
      <h2><?= $h($name !== '' ? strtr($C('empf'), ['{name}' => $name]) : 'Vecom Design') ?></h2></div>
    <p><?= $h($C('empf_text')) ?></p>
    <a class="knopf" href="<?= $h($ziel) ?>"><?= $h($C('knopf')) ?> →</a>
  </div>
  <?php /* Eine echte Kundenstimme unter der Empfehlung (28.09.2026, Uwe: Ja zu Kundenstimmen). */
        $stimme = $p ? (PartnerSeite::stimmen($p, $sprache, 1)[0] ?? null) : null; if ($stimme): ?>
  <figure class="stimme"><figcaption class="st-kopf"><?= $h(Texte::h(Texte::PARTNER_PLUS['st_check'], $sprache)) ?></figcaption>
    <?php if ($stimme['sterne']): ?><div class="sterne" aria-label="<?= (int) $stimme['sterne'] ?>/5"><?= str_repeat('★', (int) $stimme['sterne']) ?></div><?php endif; ?>
    <blockquote>„<?= $h($stimme['text']) ?>“</blockquote>
    <figcaption>— <?= $h($stimme['name']) ?><?= $stimme['firma'] !== '' ? ', ' . $h($stimme['firma']) : '' ?><?= $stimme['ort'] !== '' ? ' · ' . $h($stimme['ort']) : '' ?></figcaption>
  </figure>
  <?php endif; ?>
  <?php /* Weiterleiten ohne Skript (CSP default-src 'none'): nur Links. */
        $wlBericht = PartnerCheck::link((string) $z['token']); $wlT = strtr($C('wl_nachricht'), ['{host}' => (string) $z['host']]) . $wlBericht; ?>
  <div class="wl"><b><?= $h($C('wl_titel')) ?></b>
    <a href="https://wa.me/?text=<?= rawurlencode($wlT) ?>" rel="noopener"><?= $h($C('wl_wa')) ?></a>
    <a href="mailto:?subject=<?= rawurlencode(strtr($C('titel'), ['{host}' => (string) $z['host']])) ?>&amp;body=<?= rawurlencode($wlT) ?>"><?= $h($C('wl_mail')) ?></a></div>
  <p class="klein"><?= $h($C('klein')) ?></p>
</main>
</body>
</html>
