<?php
declare(strict_types=1);
/* ==========================================================================
   vorab.php — der Link vom Partner mit dem vereinbarten Festpreis (02.10.2026).

   GET  v=…   Preis und Leistungen, dazu das kurze Formular: Name, Firma,
              E-Mail, Telefon und die Angaben für Impressum und Rechnung.
   POST       PartnerVorab::einloesen() → Kunde, Zuordnung, Festpreis-Angebot
              (Entwurf, Uwe gibt frei) → weiter ins Dashboard.

   Öffentliche Adresse: im Zweifel eine Meldung, nie eine leere Seite und nie
   ein Fehler aus der Datenbank. Keine Daten in Adresszeilen.
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('Gerade nicht erreichbar.'); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Texte', 'Events', 'Sprache', 'Kunde', 'PartnerVorab'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
session_name('vecomvorab');
session_start();
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

/* Die Tabelle entsteht mit Migration 140 — läuft die Verwaltung nach dem
   Deploy erst später, zieht diese Seite die offenen Migrationen selbst nach. */
try { require_once __DIR__ . '/app/src/Einrichtung.php'; Einrichtung::selbsttaetig(false); } catch (Throwable $e) { }

$token = (string) ($_GET['v'] ?? $_POST['v'] ?? '');
$v = null;
$meldung = '';
try { $v = PartnerVorab::ausToken($token); } catch (Throwable $e) { $meldung = 'panne'; }
if ($meldung === '' && $v === null) { $meldung = 'unbekannt'; }
elseif ($v !== null && !$v['gueltig']) {
    $meldung = match ((string) $v['status']) { 'offen' => 'abgelaufen', 'zurueckgezogen' => 'unbekannt', default => 'schon' };
}

/* Die Sprache des Links gewinnt — außer der Kunde wählt unten eine andere. */
$sprache = Sprache::gewaehlt() ? Sprache::ausAnfrage() : Sprache::waehlen((string) ($v['sprache'] ?? Sprache::ausAnfrage()));
$T = static fn(string $s): string => Texte::h(Texte::VORAB[$s] ?? [], $sprache);
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$steuer = Kunde::steuerworte($sprache);

$werte = [];
$fehlt = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $v !== null && $v['gueltig']) {
    foreach (array_keys(PartnerVorab::FELDER) as $f) { $werte[$f] = mb_substr((string) ($_POST[$f] ?? ''), 0, 200); }
    $roboter = trim((string) ($_POST['webseite'] ?? '')) !== '' || (time() - (int) ($_SESSION['vorab_seit'] ?? time())) < 3;
    if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['_csrf'] ?? ''))) {
        $meldung = 'panne';
    } elseif ($roboter) {
        $meldung = 'panne';
    } else {
        try {
            $r = PartnerVorab::einloesen($token, $_POST + ['zustimmung' => '']);
            if ($r['ok']) {
                header('Location: ' . $r['link'] . (str_contains($r['link'], '?') ? '&' : '?') . 'willkommen=1', true, 303);
                exit;
            }
            $meldung = (string) ($r['grund'] ?? 'panne');
            $fehlt = (array) ($r['fehlt'] ?? []);
        } catch (Throwable $e) {
            $meldung = 'panne';
            try { Events::melden('partner_vorab_fehler', 'Vorab-Link: Eintragen gescheitert', 'schlecht', mb_substr($e->getMessage(), 0, 300), '/partner'); }
            catch (Throwable $e2) { }
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $_SESSION['vorab_seit'] = time(); }

$zeigeFormular = $v !== null && $v['gueltig'];
$feld = static function (string $name, string $label, string $typ = 'text', string $auto = '', bool $pflicht = true) use ($h, $werte, $fehlt, $T): string {
    $id = 'vb_' . $name;
    return '<div class="vb-feld' . (in_array($name, $fehlt, true) ? ' fehlt' : '') . '"><label for="' . $id . '">' . $h($label)
        . ($pflicht ? '' : ' <span class="opt">' . $h($T('optional')) . '</span>') . '</label>'
        . '<input id="' . $id . '" name="' . $name . '" type="' . $typ . '"' . ($auto !== '' ? ' autocomplete="' . $auto . '"' : '')
        . ($pflicht ? ' required' : '') . ' maxlength="160" value="' . $h($werte[$name] ?? '') . '"></div>';
};
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
  .vb{max-width:600px;margin:0 auto}
  .vb h1{font-size:clamp(24px,5vw,30px);margin:0 0 8px;line-height:1.2}
  .vb .schritte{font-size:12.5px;letter-spacing:.06em;text-transform:uppercase;color:var(--leise);margin:0 0 14px}
  .vb .lead{color:var(--dim);font-size:15.5px;line-height:1.65;margin:0 0 18px}
  .vb-preis{border:1px solid rgba(241,211,139,.35);border-radius:14px;padding:18px 18px 16px;margin:6px 0 22px;
    background:linear-gradient(135deg,rgba(241,211,139,.08),rgba(241,211,139,.02))}
  .vb-preis .k{font-size:12px;letter-spacing:.18em;text-transform:uppercase;color:rgba(241,211,139,.85)}
  .vb-preis .b{font-size:clamp(30px,7vw,40px);line-height:1.1;margin:6px 0 2px;color:#f1d38b}
  .vb-preis .f{font-size:13px;color:var(--leise)}
  .vb-preis .l{margin:14px 0 0;font-size:15px;line-height:1.6;color:var(--text)}
  .vb-preis .w{margin:10px 0 0;font-size:13px;color:var(--leise)}
  .vb form{display:flex;flex-direction:column;gap:12px}
  .vb h2{font-size:14px;letter-spacing:.12em;text-transform:uppercase;color:var(--leise);margin:14px 0 0}
  .vb-reihe{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .vb-reihe.drei{grid-template-columns:110px 1fr 1fr}
  @media (max-width:560px){.vb-reihe,.vb-reihe.drei{grid-template-columns:1fr}}
  .vb-feld{display:flex;flex-direction:column;gap:5px}
  .vb-feld label{font-size:14px;color:var(--text)}
  .vb-feld .opt{color:var(--leise);font-size:12.5px}
  .vb-feld input,.vb-feld select{font-size:16px;padding:13px 14px;min-height:50px}
  .vb-feld.fehlt input,.vb-feld.fehlt select{border-color:#e07a5f;box-shadow:0 0 0 1px #e07a5f}
  .vb-zu{display:flex;gap:10px;align-items:flex-start;font-size:14px;line-height:1.55;color:var(--text)}
  .vb-zu input{width:auto;margin-top:4px;min-height:0}
  .vb-zu.fehlt{color:#f2a48f}
  .vb .knopf{min-height:54px;font-size:16px}
  .vb .klein{color:var(--leise);font-size:13px;line-height:1.6;margin:4px 0 0}
  .sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
</style>
</head>
<body>
<div class="seite">
  <div class="wortmarke">
    <img src="/assets/img/logo-mark.webp?v=gold2609" alt="" width="58" height="46" fetchpriority="high">
    <span class="wort"><b>VECOM</b> DESIGN</span>
  </div>

  <div class="block vb">
    <p class="schritte"><?= $h($T('schritte')) ?></p>
    <h1><?= $h($T('titel')) ?></h1>
    <?php if ($meldung !== ''): ?>
      <div class="hinweis schlecht" role="alert"><?= $h($T($meldung)) ?></div>
    <?php endif; ?>

    <?php if ($zeigeFormular): ?>
      <div class="vb-preis">
        <div class="k"><?= $h($T('preis')) ?></div>
        <div class="b"><?= Fmt::geld((int) $v['preis_cents']) ?></div>
        <div class="f"><?= $h($T('fest')) ?></div>
        <p class="l"><b><?= $h($T('leistungen')) ?>:</b> <?= $h((string) $v['leistungen']) ?></p>
        <p class="w"><?= $h(strtr($T('empfohlen'), ['{partner}' => trim((string) $v['partner_name'])])) ?></p>
      </div>
      <p class="lead"><?= $h($T('lead')) ?></p>

      <form method="post" action="/vorab.php?v=<?= $h(rawurlencode($token)) ?>&amp;lang=<?= $h($sprache) ?>" novalidate>
        <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
        <label class="sr" for="vb_webseite">Website</label><input class="sr" id="vb_webseite" name="webseite" tabindex="-1" autocomplete="off">

        <h2><?= $h($T('kontakt')) ?></h2>
        <div class="vb-reihe">
          <?= $feld('name', $T('f_name'), 'text', 'name') ?>
          <?= $feld('firma', $T('f_firma'), 'text', 'organization') ?>
        </div>
        <div class="vb-reihe">
          <?= $feld('email', $T('f_email'), 'email', 'email') ?>
          <?= $feld('telefon', $T('f_telefon'), 'tel', 'tel') ?>
        </div>

        <h2><?= $h($T('impressum')) ?></h2>
        <?= $feld('strasse', $T('f_strasse'), 'text', 'street-address') ?>
        <div class="vb-reihe drei">
          <?= $feld('plz', $T('f_plz'), 'text', 'postal-code') ?>
          <?= $feld('ort', $T('f_ort'), 'text', 'address-level2') ?>
          <div class="vb-feld<?= in_array('land', $fehlt, true) ? ' fehlt' : '' ?>"><label for="vb_land"><?= $h($T('f_land')) ?></label>
            <?php $landVor = $werte['land'] ?? ['it' => 'Italien', 'de' => 'Deutschland', 'en' => 'Italien'][$sprache]; ?>
            <select id="vb_land" name="land" required>
              <?php foreach (['Italien', 'Deutschland', 'Österreich', 'Schweiz', 'Sonstiges'] as $l): ?>
                <option value="<?= $h($l) ?>"<?= $l === $landVor ? ' selected' : '' ?>><?= $h($T('land_' . $l)) ?></option>
              <?php endforeach; ?>
            </select></div>
        </div>
        <div class="vb-reihe">
          <?= $feld('vat_id', (string) $steuer['vat_id'], 'text', '', false) ?>
          <?= $feld('tax_code', (string) $steuer['tax_code'], 'text', '', false) ?>
        </div>
        <?php if ($steuer['sdi'] !== null): ?>
          <?= $feld('sdi', ['it' => 'Codice destinatario o PEC (SDI)', 'de' => 'Empfängerkode oder PEC (SDI)', 'en' => 'Recipient code or PEC (SDI)'][$sprache], 'text', '', false) ?>
        <?php endif; ?>

        <label class="vb-zu<?= in_array('zustimmung', $fehlt, true) ? ' fehlt' : '' ?>">
          <input type="checkbox" name="zustimmung" value="1" required<?= !empty($_POST['zustimmung']) ? ' checked' : '' ?>>
          <span><?= strtr($h($T('zustimmung')), [
              '{datenschutz}' => '<a href="' . $h(Sprache::legal($sprache, 'privacy')) . '" target="_blank" rel="noopener">' . $h($T('datenschutz')) . '</a>',
              '{agb}' => '<a href="' . $h(Sprache::legal($sprache, 'agb')) . '" target="_blank" rel="noopener">' . $h($T('agb')) . '</a>']) ?></span>
        </label>
        <button class="knopf haupt" type="submit"><?= $h($T('knopf')) ?></button>
        <p class="klein"><?= $h($T('klein')) ?></p>
      </form>
    <?php endif; ?>
  </div>

  <?php if ($zeigeFormular): ?>
  <div class="sprachen">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
      <a class="<?= $l === $sprache ? 'jetzt' : '' ?>" href="/vorab.php?v=<?= $h(rawurlencode($token)) ?>&amp;lang=<?= $l ?>"><?= $h($wie) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/app/src/Fuss.php'; echo Fuss::html($sprache); ?>
</body>
</html>
