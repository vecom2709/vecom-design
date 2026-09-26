<?php
declare(strict_types=1);
/* ==========================================================================
   p.php — der Partnerlink: /p/CODE und /p/CODE/kanal (26.09.2026).

   Zählt den Klick (je Tag, ohne IP; beim Kanal-Link auch je Kanal) und legt
   den Code für DIESEN Besuch in einen Keks ohne Ablaufdatum — er verschwindet,
   wenn der Browser zugeht. Die Website verspricht keine Tracking-Cookies;
   dieser Keks trägt nur den Code des Partners, nichts über den Besucher.
   Fest zugeordnet wird erst, wenn der Besucher Kunde wird.

   DIE LANDESEITE (Uwe: „ja“ zu Vorschlag 1): Statt auf die nackte
   Startseite kommt der Besucher auf eine Seite „Empfohlen von …“ mit dem
   Einstieg (E-Mail → persönlicher Bereich) gleich oben. Wer erst schauen
   will, kommt mit einem Klick auf die normale Website — der Keks reist mit.

   Ein unbekannter oder pausierter Code führt still auf die Startseite:
   Der Besucher soll nie eine Fehlerseite sehen, nur weil ein Partner
   aufgehört hat.
   ========================================================================== */

$ziel = '/';
$konfig = __DIR__ . '/app/config.local.php';
/* MULTIVIEWS (26.09.2026, gemessen): Auf dem Webspace liefert Apache /p/CODE
   per Inhaltsaushandlung direkt an p.php aus -- mit CODE als PATH_INFO, OHNE
   ?c=. Die RewriteRule in .htaccess kommt dann nie zum Zug. /p/ULLI10 führte
   so auf die Startseite, ohne Klick und ohne Zuordnung, während
   /p.php?c=ULLI10 ging. „Options -MultiViews“ wäre der saubere Weg, kann
   auf diesem Tarif aber einen 500er auslösen -- also liest p.php den Code
   selbst aus der Adresse, wenn ?c fehlt. */
if (!isset($_GET['c']) && preg_match('~^/p/([A-Za-z0-9]{5,16})(?:/([A-Za-z0-9-]{1,20}))?/?(?:[?#]|$)~', (string) ($_SERVER['REQUEST_URI'] ?? ''), $pfad)) {
    $_GET['c'] = $pfad[1];
    if (isset($pfad[2]) && $pfad[2] !== '' && !isset($_GET['k'])) { $_GET['k'] = $pfad[2]; }
}
$p = null; $sprache = 'it';
if (is_file($konfig)) {
    try {
        foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'Texte', 'Sprache', 'Partner', 'PartnerWerbung', 'PartnerSeite'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
        /* Das Foto der Empfehlungsseite (siehe PartnerWerbung). Nur aktive
           Partner; die Adresse trägt einen Versionsanhang, also darf lange
           zwischengespeichert werden. */
        if (isset($_GET['foto'])) {
            $f = Db::wert("SELECT foto FROM partner WHERE code = ? AND status = 'aktiv' AND foto IS NOT NULL", [strtoupper((string) $_GET['foto'])], null);
            if (!is_string($f) || $f === '') { http_response_code(404); exit; }
            header('Content-Type: image/webp');
            header('Cache-Control: public, max-age=31536000, immutable');
            header('X-Content-Type-Options: nosniff');
            echo $f; exit;
        }
        /* Das eigene Titelbild der gestalteten Seite (PartnerSeite). */
        if (isset($_GET['titel'])) {
            $f = Db::wert("SELECT seite_bild FROM partner WHERE code = ? AND status = 'aktiv' AND seite_bild IS NOT NULL", [strtoupper((string) $_GET['titel'])], null);
            if (!is_string($f) || $f === '') { http_response_code(404); exit; }
            header('Content-Type: image/webp');
            header('Cache-Control: public, max-age=31536000, immutable');
            header('X-Content-Type-Options: nosniff');
            echo $f; exit;
        }
        $sprache = Sprache::ausAnfrage();
        $ziel = $sprache === 'it' ? '/' : '/' . $sprache . '/';
        $p = Partner::ausCode((string) ($_GET['c'] ?? ''));
        if ($p !== null) {
            $kanal = Partner::kanal((string) ($_GET['k'] ?? ''));
            // Der Sprachwechsel auf der Landeseite ist kein neuer Klick (n=1).
            if (!isset($_GET['n'])) { Partner::klick((int) $p['id'], $kanal); }
            setcookie(Partner::KEKS, (string) $p['code'] . ($kanal !== null ? ':' . $kanal : ''), [
                'path' => '/', 'secure' => ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off',
                'httponly' => true, 'samesite' => 'Lax',
            ]);
        }
    } catch (Throwable $e) { $p = null; }
}
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
if ($p === null) { header('Location: ' . $ziel, true, 302); exit; }

$g = PartnerSeite::gestaltung($p);
/* Eigene Texte des Partners gehen vor, sonst der Standard (Texte::PARTNER_LANDE). */
$L = static fn(string $k): string => in_array($k, ['titel', 'lead', 'p1', 'p2', 'p3'], true)
    ? PartnerSeite::text($g, $sprache, $k, strtr(Texte::h(Texte::PARTNER_LANDE[$k] ?? [], $sprache), ['{name}' => Partner::anzeigeName($p)]))
    : strtr(Texte::h(Texte::PARTNER_LANDE[$k] ?? [], $sprache), ['{name}' => Partner::anzeigeName($p)]);
$S = static fn(array $t): string => strtr(Texte::h($t, $sprache), ['{name}' => Partner::anzeigeName($p)]);
$vorlageHell = PartnerSeite::VORLAGEN[$g['vorlage']]['hell'];
$metall = $g['vorlage'] === 'gold' && $g['akzent'] === 'gold';
$titelbild = PartnerSeite::bildAdresse($p, $g);
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="<?= $h($sprache) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $h($L('titel')) ?> — Vecom Design</title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<link rel="stylesheet" href="/assets/css/kunde.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/kunde.css') ?>">
<style>
  .ld{max-width:560px;margin:0 auto}
  .ld .marke{display:inline-flex;gap:8px;align-items:center;border:1px solid var(--linie2);border-radius:999px;padding:6px 14px;
             font-size:13px;color:var(--cyan);margin:0 0 16px}
  .ld h1{font-family:var(--f-display);font-size:clamp(28px,7vw,40px);line-height:1.12;margin:0 0 14px}
  .ld .lead{color:var(--dim);font-size:16.5px;line-height:1.65;margin:0 0 20px}
  .ld ul{list-style:none;padding:0;margin:0 0 22px;display:grid;gap:10px}
  .ld li{display:flex;gap:10px;font-size:15px;line-height:1.55}
  .ld li::before{content:"";flex:0 0 8px;height:8px;margin-top:8px;border-radius:50%;background:var(--metall)}
  .ld form{display:flex;flex-direction:column;gap:10px}
  .ld input[type=email]{font-size:17px;padding:15px 16px;min-height:54px}
  .ld .knopf{min-height:54px;font-size:16px}
  .ld .klein{color:var(--leise);font-size:13px;margin:10px 0 0}
  .ld .weiter{display:inline-block;margin-top:18px;color:var(--cyan);font-size:14.5px}
  .ld .empf{display:flex;gap:14px;align-items:center;margin:0 0 18px}
  .ld .empf img{width:64px;height:64px;border-radius:50%;object-fit:cover;border:1px solid var(--linie2);flex:0 0 64px}
  .ld blockquote{margin:0 0 20px;padding:12px 16px;border-left:2px solid rgba(241,211,139,.6);font-size:16px;line-height:1.6;color:var(--text)}
  .ld blockquote cite{display:block;margin-top:6px;font-style:normal;font-size:13.5px;color:var(--dim)}
  .sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
  /* Gestaltung des Partners (26.09.2026): nur Werte aus PartnerSeite -- nie CSS vom Partner. */
  <?= PartnerSeite::css($g) ?>
  <?php if (!$metall): ?>
  .ld .knopf.haupt,.lp-wa{background:var(--akzent);color:var(--knopftext);border-color:transparent}
  .ld li::before{background:var(--akzent)}
  .ld blockquote{border-left-color:var(--akzent)}
  <?php endif; ?>
  <?php if ($vorlageHell): ?>
  body::before{display:none}
  /* Die Wortmarke bleibt Vecom -- auf hellem Grund in dunklem Gold statt des hellen Metallverlaufs. */
  .wortmarke .wort b{background:none;-webkit-text-fill-color:#8a6322;color:#8a6322}
  .block{background:var(--flaeche);border-color:var(--linie);box-shadow:0 18px 50px -34px rgba(40,30,15,.35)}
  .ld input[type=email]{background:#fff;color:var(--text);border-color:var(--linie2)}
  <?php endif; ?>
  .lp-held{position:relative;margin:0 0 18px;border-radius:18px;overflow:hidden;aspect-ratio:16/9;background:var(--flaeche2)}
  .lp-held img{width:100%;height:100%;object-fit:cover;display:block}
  .lp-held::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 55%,rgba(0,0,0,.28))}
  .lp h2{font-family:var(--f-display);font-size:20px;margin:0 0 14px}
  .lp-arbeiten{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
  .lp-arbeiten figure{margin:0;border:1px solid var(--linie);border-radius:14px;overflow:hidden;background:var(--flaeche2)}
  .lp-arbeiten img{width:100%;height:auto;aspect-ratio:16/9;object-fit:cover;display:block}
  .lp-arbeiten figcaption{padding:10px 12px;font-size:13.5px;line-height:1.45}
  .lp-arbeiten figcaption b{display:block;font-size:14.5px}
  .lp-arbeiten figcaption span{color:var(--dim)}
  .lp-schritte{list-style:none;padding:0;margin:0;display:grid;gap:14px;counter-reset:s}
  .lp-schritte li{display:flex;gap:14px;align-items:flex-start;counter-increment:s}
  .lp-schritte li::before{content:counter(s);flex:0 0 32px;height:32px;border-radius:50%;display:grid;place-items:center;font-weight:700;background:var(--akzent);color:var(--knopftext)}
  .lp-schritte b{display:block}
  .lp-schritte span{color:var(--dim);font-size:15px}
  .lp details{border-top:1px solid var(--linie);padding:12px 0}
  .lp details:last-child{border-bottom:1px solid var(--linie)}
  .lp summary{cursor:pointer;font-weight:600}
  .lp details p{color:var(--dim);margin:8px 0 0}
  .lp-wa{display:flex;align-items:center;justify-content:center;gap:10px;min-height:52px;border-radius:12px;font-weight:650;text-decoration:none;padding:12px 18px;
         border:1px solid var(--linie2);color:var(--text)}
  .lp .weiter2{display:inline-block;margin-top:12px;color:var(--cyan);font-size:14.5px}
</style>
</head>
<body>
<div class="seite">
  <div class="wortmarke">
    <img src="/assets/img/logo-mark.webp?v=gold2609" alt="" width="58" height="46" fetchpriority="high">
    <span class="wort"><b>VECOM</b> DESIGN</span>
  </div>
  <div class="block ld">
    <?php if ($titelbild): ?><div class="lp-held"><img src="<?= $h($titelbild) ?>" alt="" width="1600" height="900" fetchpriority="high"></div><?php endif; ?>
    <?php $foto = PartnerWerbung::fotoAdresse($p); $satz = trim((string) ($p['profil_satz'] ?? '')); ?>
    <?php if ($foto): ?>
      <div class="empf"><img src="<?= $h($foto) ?>" alt="<?= $h($L('foto_alt')) ?>" width="64" height="64"><span class="marke" style="margin:0">★ <?= $h($L('marke')) ?></span></div>
    <?php else: ?>
      <span class="marke">★ <?= $h($L('marke')) ?></span>
    <?php endif; ?>
    <h1><?= $h($L('titel')) ?></h1>
    <p class="lead"><?= $h($L('lead')) ?></p>
    <?php if ($satz !== ''): ?>
      <blockquote><?= $h(['it' => '«', 'en' => '“'][$sprache] ?? '„') . $h($satz) . $h(['it' => '»', 'en' => '”'][$sprache] ?? '“') ?><cite>— <?= $h(Partner::anzeigeName($p)) ?></cite></blockquote>
    <?php endif; ?>
    <ul><li><?= $h($L('p1')) ?></li><li><?= $h($L('p2')) ?></li><li><?= $h($L('p3')) ?></li></ul>
    <form method="post" action="/zugang.php?lang=<?= $h($sprache) ?>">
      <input type="hidden" name="quelle" value="seite">
      <label for="ld_email" class="sr"><?= $h($L('feld')) ?></label>
      <input id="ld_email" name="email" type="email" required autocomplete="email" inputmode="email" placeholder="<?= $h($L('feld')) ?>">
      <button class="knopf haupt" type="submit"><?= $h($L('knopf')) ?></button>
    </form>
    <p class="klein"><?= $h($L('klein')) ?></p>
    <a class="weiter" href="<?= $h($ziel) ?>"><?= $h($L('weiter')) ?></a>
  </div>

  <?php $PS = Texte::PARTNER_SEITE; ?>
  <?php if ($g['bausteine']['ablauf']): ?>
    <section class="block ld lp"><h2><?= $h($S($PS['ablauf_titel'])) ?></h2>
      <ol class="lp-schritte"><?php foreach ($PS['ablauf'] as [$t, $u]): ?><li><div><b><?= $h($S($t)) ?></b><span><?= $h($S($u)) ?></span></div></li><?php endforeach; ?></ol>
    </section>
  <?php endif; ?>
  <?php if ($g['bausteine']['arbeiten']): ?>
    <section class="block ld lp"><h2><?= $h($S($PS['arbeiten_titel'])) ?></h2>
      <div class="lp-arbeiten"><?php foreach ($PS['arbeiten'] as $aid => $a): ?>
        <figure><img src="/assets/img/arbeiten/<?= $h($aid) ?>/an.webp" alt="<?= $h($a['name']) ?>" width="2400" height="1350" loading="lazy" decoding="async">
          <figcaption><b><?= $h($a['name']) ?></b><span><?= $h(Texte::h($a, $sprache)) ?></span></figcaption></figure>
      <?php endforeach; ?></div>
      <a class="weiter2" href="<?= $h($ziel . '#work') ?>"><?= $h($S($PS['arbeiten_mehr'])) ?></a>
    </section>
  <?php endif; ?>
  <?php if ($g['bausteine']['faq']): ?>
    <section class="block ld lp"><h2><?= $h($S($PS['faq_titel'])) ?></h2>
      <?php foreach ($PS['faq'] as [$q, $a]): ?><details><summary><?= $h($S($q)) ?></summary><p><?= $h($S($a)) ?></p></details><?php endforeach; ?>
    </section>
  <?php endif; ?>
  <?php if ($g['bausteine']['whatsapp'] && $g['whatsapp'] !== ''): ?>
    <section class="block ld lp">
      <a class="lp-wa" href="https://wa.me/<?= $h(ltrim($g['whatsapp'], '+')) ?>?text=<?= rawurlencode($S($PS['wa_text'])) ?>" target="_blank" rel="noopener"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8 8 0 1 1 12 20z"/></svg><?= $h($S($PS['wa_knopf'])) ?></a>
    </section>
  <?php endif; ?>
  <div class="sprachen">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
      <a class="<?= $l === $sprache ? 'jetzt' : '' ?>" href="<?= $h('/p.php?' . http_build_query(array_filter(['c' => $p['code'], 'k' => $_GET['k'] ?? null, 'lang' => $l, 'n' => 1]))) ?>"><?= $h($wie) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php require_once __DIR__ . '/app/src/Fuss.php'; echo Fuss::html($sprache); ?>
</body>
</html>
