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
        foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'Texte', 'Sprache', 'Partner', 'PartnerWerbung'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
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

$L = static fn(string $k): string => strtr(Texte::h(Texte::PARTNER_LANDE[$k] ?? [], $sprache), ['{name}' => Partner::anzeigeName($p)]);
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
</style>
</head>
<body>
<div class="seite">
  <div class="wortmarke">
    <img src="/assets/img/logo-mark.webp?v=gold2609" alt="" width="58" height="46" fetchpriority="high">
    <span class="wort"><b>VECOM</b> DESIGN</span>
  </div>
  <div class="block ld">
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
  <div class="sprachen">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
      <a class="<?= $l === $sprache ? 'jetzt' : '' ?>" href="<?= $h('/p.php?' . http_build_query(array_filter(['c' => $p['code'], 'k' => $_GET['k'] ?? null, 'lang' => $l, 'n' => 1]))) ?>"><?= $h($wie) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php require_once __DIR__ . '/app/src/Fuss.php'; echo Fuss::html($sprache); ?>
</body>
</html>
