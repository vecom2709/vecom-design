<?php
/* Gesperrter Partnerbereich (30.09.2026, PartnerSchutz).
   Drei Zustände: zustimmen (Vereinbarung mit zwei Haken), wartet (Uwe
   schaltet frei), gesperrt (nach einem Verstoß). Sonst nichts — keine
   Zahlen, keine Betriebe, keine Werbemittel. Erwartet $p, $sprache, $T, $h,
   $selbst, $stand, $meldung. */
$wortlaut = Partner::vereinbarungText($sprache, $p);
$klausel = PartnerSchutz::klauselText($sprache);
?><!doctype html>
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $h($T('sp_titel')) ?> — Vecom Design</title>
<meta name="theme-color" content="#060a16">
<link rel="icon" href="/assets/img/favicon-96.png" sizes="96x96">
<link rel="stylesheet" href="/assets/css/fonts.css">
<link rel="stylesheet" href="/assets/css/kunde.css?v=<?= (int) @filemtime(dirname(__DIR__, 2) . '/assets/css/kunde.css') ?>">
<style>
  .pt{max-width:680px;margin:0 auto}
  .pt h1{font-size:clamp(24px,5vw,30px);margin:0 0 10px;line-height:1.2}
  .pt .lead{color:var(--dim);font-size:15.5px;line-height:1.65;margin:0 0 14px}
  .pt .schloss{width:44px;height:44px;border-radius:50%;display:grid;place-items:center;margin:0 0 14px;
               background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438);color:#16120b}
  .pt .schloss svg{width:22px;height:22px}
  .vb{border:1px solid var(--linie2);border-radius:12px;background:rgba(255,255,255,.02);max-height:min(52vh,460px);overflow:auto;
      padding:14px 16px;margin:0 0 14px;-webkit-overflow-scrolling:touch}
  .vb:focus-visible{outline:3px solid #f1d38b;outline-offset:3px}
  .vb pre{white-space:pre-wrap;font-family:inherit;font-size:14px;line-height:1.65;color:var(--text);margin:0}
  .haken{display:flex;gap:12px;align-items:flex-start;font-size:14.5px;line-height:1.55;color:var(--text);
         border:1px solid var(--linie);border-radius:12px;padding:12px 14px;margin:0 0 10px;cursor:pointer}
  .haken input{flex:0 0 22px;width:22px;height:22px;margin:1px 0 0;accent-color:#d9b25a;cursor:pointer}
  .haken:has(input:checked){border-color:rgba(241,211,139,.55);background:rgba(241,211,139,.06)}
  .haken.stark{border-color:rgba(241,211,139,.3)}
  .pt form .knopf{width:100%;justify-content:center;min-height:50px;font-size:16px}
  .pt .klein{color:var(--leise);font-size:13px;line-height:1.6;margin:12px 0 0}
</style>
</head>
<body>
<div class="seite">
  <div class="wortmarke">
    <img src="/assets/img/logo-mark.webp?v=gold2609" alt="" width="58" height="46">
    <span class="wort"><b>VECOM</b> DESIGN</span>
  </div>
  <main class="block pt">
    <div class="schloss" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></div>
    <?php if ($stand === 'zustimmen'): ?>
      <h1><?= $h($T('sp_titel')) ?></h1>
      <p class="lead"><?= $h(strtr($T('sp_text'), ['{name}' => (string) $p['name']])) ?></p>
      <?php if ($meldung !== ''): ?><div class="hinweis schlecht" role="alert"><?= $h($T($meldung)) ?></div><?php endif; ?>
      <div class="vb" tabindex="0" role="region" aria-label="<?= $h($T('sp_titel')) ?>"><pre><?= $h($wortlaut) ?></pre></div>
      <form method="post" action="<?= $h($selbst()) ?>">
        <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
        <input type="hidden" name="tat" value="schutz_zustimmen">
        <label class="haken"><input type="checkbox" name="ganz" value="1" required> <span><?= $h($T('sp_haken1')) ?></span></label>
        <label class="haken stark"><input type="checkbox" name="klauseln" value="1" required> <span><?= $h($klausel) ?></span></label>
        <button class="knopf haupt" type="submit"><?= $h($T('sp_knopf')) ?></button>
      </form>
      <p class="klein"><?= $h($T('sp_klein')) ?></p>
    <?php elseif ($stand === 'wartet'): ?>
      <h1><?= $h($T('sp_wartet_titel')) ?></h1>
      <p class="lead"><?= $h($T('sp_wartet_text')) ?></p>
      <details style="margin-top:6px"><summary style="cursor:pointer;color:var(--cyan)"><?= $h($T('lesen')) ?></summary>
        <div class="vb" style="margin-top:10px"><pre><?= $h((string) $p['vereinbarung_text']) ?></pre></div></details>
    <?php else: ?>
      <h1><?= $h($T('sp_gesperrt_titel')) ?></h1>
      <p class="lead"><?= $h($T('sp_gesperrt_text')) ?></p>
    <?php endif; ?>
  </main>
  <div class="sprachen">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
      <a class="<?= $l === $sprache ? 'jetzt' : '' ?>" href="<?= $h('/partner.php?' . http_build_query(['t' => $p['token'], 'lang' => $l])) ?>"><?= $h($wie) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php require_once dirname(__DIR__) . '/src/Fuss.php'; echo Fuss::html($sprache); ?>
</body>
</html>
