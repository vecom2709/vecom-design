<?php
declare(strict_types=1);
/* zertifikat.php — Echtheit eines Zertifikats der Partner Academy prüfen
   (Etappe 3, 05.10.2026). Öffentlich, aber sparsam: Es zeigt nur, ob die
   Prüfnummer gilt, das Ausstellungsdatum und Vorname + Initial — keine
   Kontaktdaten, kein Ergebnis. Nicht für Suchmaschinen. */
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; script-src 'self'; img-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");

foreach (['Config', 'Db', 'Fmt', 'Texte', 'Sprache', 'Partner', 'Academy'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
$sprache = Sprache::ausAnfrage();
$w = static fn(string $k, array $r = []): string => strtr(Texte::h(Texte::ACADEMY[$k] ?? [], $sprache), $r);
$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$nr = strtoupper(trim((string) ($_GET['n'] ?? '')));
$erg = $nr !== '' ? Academy::zertifikatPruefen($nr) : null;
?><!doctype html>
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
<head>
<meta charset="utf-8">
<?= Sprache::skript() ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= $h($w('z_seite_titel')) ?> — Vecom Design</title>
<link rel="icon" href="/assets/img/favicon-96.png" sizes="96x96">
<link rel="stylesheet" href="/assets/css/fonts.css">
<style>
  :root{--bg:#060a16;--text:#f4efe6;--dim:#a9a197;--gold:#e7c675;--linie:rgba(255,255,255,.1)}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:radial-gradient(72vw 52vh at 50% -14%,rgba(52,98,232,.30),transparent 66%),#060a16;color:var(--text);font:400 16px/1.55 "Inter",system-ui,sans-serif;padding:24px 16px}
  main{width:100%;max-width:520px;text-align:center}
  .marke{font:800 13px/1 "Inter",sans-serif;letter-spacing:.24em;color:var(--gold);margin:0 0 6px}
  h1{font:700 clamp(26px,6vw,34px)/1.2 "Inter",sans-serif;margin:0 0 22px}
  form{display:flex;gap:8px;margin:0 0 22px}
  input{flex:1;min-width:0;padding:13px 14px;border-radius:12px;border:1px solid var(--linie);background:rgba(255,255,255,.04);color:var(--text);font:600 16px/1.2 ui-monospace,monospace;letter-spacing:.06em;text-transform:uppercase}
  input:focus{outline:2px solid var(--gold);outline-offset:1px}
  button{padding:13px 18px;border-radius:12px;border:0;background:linear-gradient(135deg,#b98a31,#f7e6ae 60%,#d6a849);color:#16120b;font:700 15px/1 "Inter",sans-serif;cursor:pointer}
  .karte{padding:22px 20px;border-radius:18px;border:1px solid var(--linie);background:rgba(255,255,255,.03)}
  .karte.gut{border-color:rgba(231,198,117,.5)}
  .karte b{display:block;font-size:21px;margin:0 0 6px}
  .karte.gut b{color:var(--gold)}
  .karte p{margin:0;color:var(--dim)}
  .klein{font-size:13px;color:var(--dim);margin:22px 0 0}
</style>
</head>
<body>
<main>
  <p class="marke">VECOM DESIGN · PARTNER ACADEMY</p>
  <h1><?= $h($w('z_seite_titel')) ?></h1>
  <form method="get" action="/zertifikat.php" role="search">
    <label class="sr" for="n" style="position:absolute;left:-9999px"><?= $h($w('z_eingabe')) ?></label>
    <input id="n" name="n" value="<?= $h($nr) ?>" placeholder="<?= $h($w('z_eingabe')) ?>" maxlength="12" autocomplete="off" required>
    <button type="submit"><?= $h($w('z_pruefen_knopf')) ?></button>
  </form>
  <?php if ($nr !== ''): ?>
    <?php if ($erg && $erg['gueltig']): ?>
      <div class="karte gut" role="status"><b>✓ <?= $h($w('z_gueltig')) ?></b>
        <p><?= $h($w('z_info', ['{name}' => $erg['name'], '{datum}' => Fmt::datum($erg['am'])])) ?></p></div>
    <?php elseif ($erg): ?>
      <div class="karte" role="status"><b><?= $h($w('z_ungueltig')) ?></b></div>
    <?php else: ?>
      <div class="karte" role="status"><b><?= $h($w('z_unbekannt')) ?></b></div>
    <?php endif; ?>
  <?php endif; ?>
  <p class="klein"><?= $h($w('intern')) ?></p>
</main>
</body>
</html>
