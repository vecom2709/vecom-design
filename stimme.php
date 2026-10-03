<?php
declare(strict_types=1);
/* ==========================================================================
   stimme.php — Kundenstimme über den Link eines Partners (03.10.2026,
   PartnerStimmen, Uwe: Ja zu N4). Nur mit unterschriebenem Link, nie im
   Index, als einziges Skript die Sprachweiche. Nach Uwes Freigabe steht die Stimme auf der Seite
   des Partners, der den Link geschickt hat.
   ========================================================================== */
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; script-src 'self'; img-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");

$p = null; $sprache = 'it';
if (is_file(__DIR__ . '/app/config.local.php')) {
    foreach (['Config', 'Db', 'Status', 'Fmt', 'Events', 'Texte', 'Sprache', 'Partner', 'PartnerRueckruf', 'PartnerStimmen'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
    date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
    try { $p = PartnerStimmen::partner((string) ($_GET['c'] ?? ''), (string) ($_GET['s'] ?? '')); } catch (Throwable $e) { $p = null; }
    $sprache = Sprache::ausAnfrage(Sprache::ausBrowser(), $p['sprache'] ?? null);
}
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
if ($p === null) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Vecom Design</title>'
       . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#0a0908;color:#b4ada2;font:17px/1.5 system-ui,sans-serif;padding:16px;text-align:center">'
       . '<p>Link non valido · Link ungültig · Invalid link</p></body>';
    exit;
}
$S = static fn(string $k): string => strtr(Texte::h(Texte::PARTNER_STIMMEN[$k] ?? [], $sprache), ['{name}' => Partner::anzeigeName($p)]);
$stand = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { $stand = PartnerStimmen::abgeben($p, $_POST, $_FILES['foto'] ?? null, $sprache); } catch (Throwable $e) { $stand = 'st_falle'; }
}
$hier = '/stimme.php?' . http_build_query(['c' => $p['code'], 's' => (string) $_GET['s'], 'lang' => $sprache]);
?><!doctype html>
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<?= Sprache::skript() ?>
<meta name="robots" content="noindex, nofollow"><meta name="referrer" content="no-referrer">
<title><?= $h($S('titel')) ?> — Vecom Design</title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<style>
  :root{--g:#0a0908;--f:#14110d;--t:#f7f3ea;--d:#b4ada2;--l:#8b847a;--a:#f1d38b;--li:rgba(255,255,255,.12)}
  *{box-sizing:border-box}
  body{margin:0;background:var(--g);color:var(--t);font:16px/1.6 'Inter',system-ui,sans-serif}
  main{max-width:560px;margin:0 auto;padding:28px 16px 48px}
  .marke{display:flex;gap:8px;align-items:center;font:800 14px/1 'Archivo',sans-serif;letter-spacing:.08em;margin-bottom:26px}
  .marke img{width:40px;height:auto}.marke b{color:var(--a)}
  h1{font:800 clamp(26px,6vw,34px)/1.15 'Archivo',sans-serif;margin:0 0 10px}
  .lead{color:var(--d);margin:0 0 22px}
  form{display:grid;gap:14px}
  label{display:grid;gap:6px;font-size:14px;color:var(--d)}
  input[type=text],textarea{width:100%;font:inherit;font-size:16px;color:var(--t);background:var(--f);border:1px solid var(--li);border-radius:11px;padding:12px 14px}
  textarea{min-height:120px;resize:vertical}
  input:focus-visible,textarea:focus-visible,select:focus-visible,button:focus-visible{outline:2px solid var(--a);outline-offset:2px}
  .sterne{display:flex;gap:6px;flex-wrap:wrap}
  .sterne label{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--li);border-radius:999px;padding:6px 12px;color:var(--t);cursor:pointer}
  .ja{display:flex;gap:10px;align-items:flex-start;color:var(--t);font-size:14.5px}
  .ja input{margin-top:4px;width:18px;height:18px}
  .falle{position:absolute;left:-9999px}
  button{min-height:50px;padding:12px 22px;border:0;border-radius:11px;font:700 16px/1 'Inter',sans-serif;color:#16120b;background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438);cursor:pointer;justify-self:start}
  .gut{border:1px solid rgba(52,211,155,.5);border-radius:12px;padding:14px;color:#34d39b}
  .schlecht{border:1px solid rgba(239,107,91,.5);border-radius:12px;padding:12px 14px;color:#ef6b5b}
</style>
</head>
<body>
<main>
  <div class="marke"><img src="/assets/img/logo-mark.webp?v=gold2609" alt="" width="40" height="32"><span><b>VECOM</b> DESIGN</span></div>
  <h1><?= $h($S('titel')) ?></h1>
  <?php if ($stand === 'ok'): ?>
    <p class="gut" role="status"><?= $h($S('danke')) ?></p>
  <?php else: ?>
    <p class="lead"><?= $h($S('lead')) ?></p>
    <?php if ($stand !== ''): ?><p class="schlecht" role="alert"><?= $h($S('f_' . $stand)) ?></p><?php endif; ?>
    <form method="post" action="<?= $h($hier) ?>" enctype="multipart/form-data">
      <input type="hidden" name="st" value="<?= $h(PartnerRueckruf::stempel((string) $p['code'])) ?>">
      <span class="falle" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></span>
      <label><?= $h($S('name')) ?><input type="text" name="name" required maxlength="80" autocomplete="name" value="<?= $h((string) ($_POST['name'] ?? '')) ?>"></label>
      <label><?= $h($S('firma')) ?><input type="text" name="firma" maxlength="120" autocomplete="organization" value="<?= $h((string) ($_POST['firma'] ?? '')) ?>"></label>
      <label><?= $h($S('ort')) ?><input type="text" name="ort" maxlength="80" autocomplete="address-level2" value="<?= $h((string) ($_POST['ort'] ?? '')) ?>"></label>
      <label><?= $h($S('text')) ?><textarea name="text" required minlength="<?= PartnerStimmen::TEXT_MIN ?>" maxlength="<?= PartnerStimmen::TEXT_MAX ?>" placeholder="<?= $h($S('text_ph')) ?>"><?= $h((string) ($_POST['text'] ?? '')) ?></textarea></label>
      <fieldset style="border:0;padding:0;margin:0"><legend style="font-size:14px;color:var(--d);margin-bottom:6px"><?= $h($S('sterne')) ?></legend>
        <div class="sterne"><?php for ($i = 5; $i >= 1; $i--): ?><label><input type="radio" name="sterne" value="<?= $i ?>"<?= $i === 5 ? ' checked' : '' ?>> <?= str_repeat('★', $i) ?></label><?php endfor; ?></div></fieldset>
      <label><?= $h($S('foto')) ?><input type="file" name="foto" accept="image/jpeg,image/png,image/webp"></label>
      <label class="ja"><input type="checkbox" name="ok" value="1" required> <span><?= $h($S('ok')) ?> <a href="<?= $h(Sprache::legal($sprache, 'privacy')) ?>" style="color:var(--d)">Privacy</a></span></label>
      <button type="submit"><?= $h($S('knopf')) ?></button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
