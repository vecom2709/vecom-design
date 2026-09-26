<?php
declare(strict_types=1);
/* ==========================================================================
   partner.php — Bewerbung und Partnerseite (26.09.2026).

   Ohne Schlüssel: das Bewerbungsformular (Uwe: „b“ — öffentlich, er nimmt
   jede Bewerbung an oder lehnt sie ab). Mit Schlüssel (?t=…): die Seite des
   Partners — Link, Code, Zahlen, Provisionen, Auszahlungen, Belege und das
   Auszahlungskonto bei Stripe.

   KEINE KUNDENNAMEN. Der Partner sieht, DASS jemand gekauft hat und was es
   ihm bringt, nicht WER. Das steht so auch in der Vereinbarung.

   Der Stripe-Einrichtungslink entsteht erst beim Klick auf dieser Seite und
   wird sofort geöffnet — nie per E-Mail verschickt (Stripe-Vorgabe: der Link
   öffnet persönliche Daten).
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('Derzeit nicht erreichbar.'); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'Texte', 'Sprache', 'Partner', 'PartnerWege'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));
session_name('vecompartnerseite');
session_start();
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

/* Die Tabellen entstehen mit Migration 068. Läuft die Verwaltung nach dem
   Deploy erst später, zieht diese Seite sie nach (wie zugang.php). */
try { Db::wert('SELECT 1 FROM partner LIMIT 1', [], null); }
catch (Throwable $e) {
    try { require_once __DIR__ . '/app/src/Einrichtung.php'; Einrichtung::migrieren(); } catch (Throwable $e2) { }
}

$token = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$p = $token !== '' ? Partner::ausToken($token) : null;
/* DIE SPRACHE DES PARTNERS GEWINNT (Uwe, 26.09.2026: „ständig auf Englisch“)
   Auf der Partnerseite gilt die Sprache, die am Partner steht — nicht der
   Sprach-Keks, den der Browser von der Website mitbringt (wer dort einmal
   „English“ geklickt hat, sah seine Partnerseite danach immer englisch).
   Nur ein Klick auf die Sprachwahl unten ändert sie — und dann für immer,
   auch für seine Mails. */
if ($p) {
    $sprache = Sprache::gewaehlt() ? Sprache::ausAnfrage() : Sprache::waehlen((string) $p['sprache']);
    if (Sprache::gewaehlt() && $sprache !== (string) $p['sprache']) {
        Db::run('UPDATE partner SET sprache = ? WHERE id = ?', [$sprache, (int) $p['id']]);
        $p['sprache'] = $sprache;
    }
} else {
    $sprache = Sprache::ausAnfrage();
    Sprache::merken($sprache);
}
$T = static fn(string $k): string => Texte::h(Texte::PARTNER[$k] ?? [], $sprache);
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
/* Mit Schlüssel ohne lang: Sonst hielte jede Formularadresse die Sprache
   dieser Seite fest und zählte als „gewählt“. */
$selbst = static fn(array $extra = []) => '/partner.php?' . http_build_query(array_merge(
    $p ? ['t' => $p['token']] : ['lang' => $sprache], $extra));

$meldung = ''; $gut = false;

/* ---------- Beleg herunterladen (nur der eigene) ---------- */
if ($p && isset($_GET['beleg'])) {
    $a = Db::one('SELECT id FROM partner_auszahlungen WHERE id = ? AND partner_id = ?', [(int) $_GET['beleg'], (int) $p['id']]);
    $pdf = $a ? Partner::belegPdf((int) $a['id']) : null;
    if ($pdf === null) { http_response_code(404); exit; }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="vecom-provision-' . (int) $a['id'] . '.pdf"');
    echo $pdf; exit;
}

/* ---------- Zurück von Stripe: nachsehen, ob das Konto bereit ist ---------- */
if ($p && isset($_GET['stripe'])) {
    try { Partner::kontoPruefen($p); $p = Partner::ausToken($token); } catch (Throwable $e) { }
}

/* ---------- Formulare ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['_csrf'] ?? ''))) {
        $meldung = 'panne';
    } else {
        $tat = (string) ($_POST['tat'] ?? '');
        try {
            if ($tat === 'bewerben' && !$p) {
                /* Zwei leise Bremsen gegen Formular-Roboter: ein Feld, das
                   Menschen nicht sehen, und eine Mindestzeit auf der Seite. */
                $roboter = trim((string) ($_POST['webseite'] ?? '')) !== ''
                        || (time() - (int) ($_SESSION['partner_seit'] ?? time())) < 3;
                $sperre = sys_get_temp_dir() . '/vecompartner_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
                if ($roboter || (is_file($sperre) && time() - filemtime($sperre) < 30)) {
                    $meldung = 'danke'; $gut = true;          // nichts verraten
                } else {
                    touch($sperre);
                    $r = Partner::bewerben($_POST, $sprache, Partner::vereinbarungText($sprache));
                    $meldung = $r['ok'] ? 'danke' : ($r['grund'] ?? 'panne');
                    $gut = $r['ok'];
                }
            } elseif ($tat === 'vereinbarung' && $p) {
                if (!empty($_POST['ok'])) {
                    Partner::vereinbarungMerken((int) $p['id'], Partner::vereinbarungText($sprache, $p));
                }
                header('Location: ' . $selbst(), true, 303); exit;
            } elseif ($tat === 'weg' && $p) {
                $f = PartnerWege::setzen((int) $p['id'], $_POST);
                if ($f === null) { header('Location: ' . $selbst(['m' => 'w_gut']) . '#wege', true, 303); exit; }
                $meldung = $f;
            } elseif ($tat === 'konto' && $p) {
                $r = Partner::kontoEinrichten($p, $basis . $selbst());
                if ($r['ok']) { header('Location: ' . $r['url'], true, 303); exit; }
                $meldung = 'panne';
                Events::melden('partner_stripe_fehler', 'Partner-Konto bei Stripe nicht eingerichtet: ' . $p['name'], 'warnung',
                               (string) ($r['text'] ?? ''), '/partner/' . (int) $p['id']);
            }
        } catch (Throwable $e) {
            $meldung = 'panne';
            try { Events::melden('partner_fehler', 'Partnerseite: Fehler', 'warnung', mb_substr($e->getMessage(), 0, 200), '/partner'); } catch (Throwable $e2) { }
        }
    }
}
if (!$p && $_SERVER['REQUEST_METHOD'] !== 'POST') { $_SESSION['partner_seit'] = time(); }

$offen = Partner::einstellung('partner_bewerbung_offen') === '1';
$satz = Partner::satzFuer($p ?? []);
$bedingungen = strtr($T('bedingungen'), [
    '{satz}' => $satz['art'] === 'fest' ? Fmt::geld($satz['wert']) . ($sprache === 'it' ? ' per vendita' : ($sprache === 'en' ? ' per sale' : ' je Verkauf'))
                                        : Partner::satzWort($satz),
    '{min}' => Fmt::geld(Partner::zahl('partner_mindest_cents')),
    '{tage}' => (string) max(14, Partner::zahl('partner_sperrtage')),
]);
$linkMd = static fn(string $s): string => (string) preg_replace('~\[([^\]]+)\]\((https://[^)\s]+)\)~',
    '<a href="$2" target="_blank" rel="noopener">$1</a>', htmlspecialchars($s, ENT_QUOTES, 'UTF-8'));
?><!doctype html>
<html lang="<?= $h($sprache) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $h($p ? $T('p_titel') : $T('titel')) ?> — Vecom Design</title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<link rel="stylesheet" href="/assets/css/kunde.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/kunde.css') ?>">
<style>
  .pt{max-width:640px;margin:0 auto}
  .pt h1{font-size:clamp(24px,5vw,30px);margin:0 0 10px;line-height:1.2}
  .pt h2{font-size:17px;margin:0 0 10px}
  .pt .lead{color:var(--dim);font-size:15.5px;line-height:1.65;margin:0 0 14px}
  .pt form{display:flex;flex-direction:column;gap:10px}
  .pt label{font-size:13px;color:var(--dim)}
  .pt input[type=text],.pt input[type=email],.pt textarea{font-size:16px;padding:12px 14px;width:100%;box-sizing:border-box}
  .pt .klein{color:var(--leise);font-size:13px;line-height:1.6;margin:10px 0 0}
  .pt .zahlen{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin:6px 0 4px}
  .pt .zahl{border:1px solid var(--linie);border-radius:10px;padding:10px;text-align:center}
  .pt .zahl b{display:block;font-size:20px}
  .pt .zahl span{font-size:12px;color:var(--leise)}
  .pt .kopie{display:flex;gap:8px}
  .pt .kopie input{flex:1;min-width:0}
  .pt table{width:100%;border-collapse:collapse;font-size:14px}
  .pt td,.pt th{padding:8px 6px;border-bottom:1px solid var(--linie);text-align:left}
  .pt td.r,.pt th.r{text-align:right;white-space:nowrap}
  .pt pre{white-space:pre-wrap;font-family:inherit;font-size:13.5px;line-height:1.6;color:var(--dim);margin:8px 0 0}
  .pt .wabe{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
  @media (max-width:520px){.pt .zahlen{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<div class="seite">
  <div class="wortmarke">
    <img src="/assets/img/logo-mark.webp" alt="" width="58" height="46" fetchpriority="high">
    <span class="wort"><b>VECOM</b> DESIGN</span>
  </div>

<?php if (!$p): ?>
  <div class="block pt">
    <h1><?= $h($T('titel')) ?></h1>
    <?php if ($meldung !== ''): ?>
      <div class="hinweis <?= $gut ? 'gut' : 'schlecht' ?>" role="status"><?= $h($T($meldung)) ?></div>
    <?php endif; ?>
    <?php if (!$gut): ?>
      <p class="lead"><?= $h($T('lead')) ?></p>
      <p class="lead"><?= $h($bedingungen) ?></p>
      <?php if (!$offen): ?>
        <div class="hinweis"><?= $h($T('zu')) ?></div>
      <?php else: ?>
      <form method="post" action="<?= $h($selbst()) ?>">
        <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
        <input type="hidden" name="tat" value="bewerben">
        <div class="wabe" aria-hidden="true"><input type="text" name="webseite" tabindex="-1" autocomplete="off"></div>
        <label for="pb_name"><?= $h($T('f_name')) ?></label>
        <input id="pb_name" type="text" name="name" required maxlength="160" autocomplete="name">
        <label for="pb_email"><?= $h($T('f_email')) ?></label>
        <input id="pb_email" type="email" name="email" required autocomplete="email">
        <label for="pb_firma"><?= $h($T('f_firma')) ?></label>
        <input id="pb_firma" type="text" name="firma" maxlength="160" autocomplete="organization">
        <label for="pb_st"><?= $h($T('f_steuer')) ?></label>
        <input id="pb_st" type="text" name="steuer_nr" maxlength="40">
        <label for="pb_kanal"><?= $h($T('f_kanal')) ?></label>
        <input id="pb_kanal" type="text" name="kanal" maxlength="300">
        <label for="pb_text"><?= $h($T('f_text')) ?></label>
        <textarea id="pb_text" name="text" rows="3" maxlength="2000"></textarea>
        <details><summary><?= $h($T('lesen')) ?></summary><pre><?= $h(Partner::vereinbarungText($sprache)) ?></pre></details>
        <label style="display:flex;gap:8px;align-items:flex-start;color:var(--text)">
          <input type="checkbox" name="vereinbarung" value="1" required style="margin-top:3px"> <?= $h($T('ok')) ?></label>
        <button class="knopf haupt" type="submit"><?= $h($T('knopf')) ?></button>
      </form>
      <?php endif; ?>
    <?php endif; ?>
    <p class="klein"><a href="<?= $h(Sprache::legal($sprache, 'privacy')) ?>"><?= $h(['it' => 'Privacy', 'de' => 'Datenschutz', 'en' => 'Privacy'][$sprache]) ?></a></p>
  </div>

<?php else:
  $k = Partner::kennzahlen((int) $p['id']);
  $sum = Partner::summen((int) $p['id']);
  $liste = Db::all('SELECT created_at, art, provision_cents, einbehalt_cents, status FROM partner_provisionen WHERE partner_id = ? ORDER BY id DESC LIMIT 100', [(int) $p['id']]);
  $auszahl = Db::all('SELECT * FROM partner_auszahlungen WHERE partner_id = ? ORDER BY id DESC LIMIT 50', [(int) $p['id']]);
  $link = Partner::link($p);
?>
  <div class="block pt">
    <h1><?= $h($T('p_titel')) ?></h1>
    <p class="lead"><?= $h($p['name']) ?> · <?= $h($bedingungen) ?></p>
    <?php $wegFehler = in_array($meldung, ['iban_falsch', 'inhaber_fehlt', 'email_falsch'], true); ?>
    <?php if ($meldung !== '' && !$wegFehler): ?><div class="hinweis schlecht"><?= $h($T($meldung)) ?></div><?php endif; ?>
    <?php if ($p['status'] === 'pausiert'): ?><div class="hinweis"><?= $h($T('pausiert')) ?></div><?php endif; ?>

    <?php if (empty($p['vereinbarung_am'])): ?>
      <div class="hinweis" style="margin-bottom:14px">
        <?= $h($T('v_fehlt')) ?>
        <details style="margin:8px 0"><summary><?= $h($T('lesen')) ?></summary><pre><?= $h(Partner::vereinbarungText($sprache, $p)) ?></pre></details>
        <form method="post" action="<?= $h($selbst()) ?>" style="flex-direction:row;align-items:center;gap:10px;flex-wrap:wrap">
          <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
          <input type="hidden" name="tat" value="vereinbarung">
          <label style="display:flex;gap:8px;color:var(--text)"><input type="checkbox" name="ok" value="1" required> <?= $h($T('ok')) ?></label>
          <button class="knopf haupt"><?= $h($T('v_knopf')) ?></button>
        </form>
      </div>
    <?php endif; ?>

    <label for="p_link"><?= $h($T('p_link')) ?></label>
    <div class="kopie"><input id="p_link" type="text" readonly value="<?= $h($link) ?>">
      <button class="knopf" type="button" onclick="var f=document.getElementById('p_link');f.select();navigator.clipboard&&navigator.clipboard.writeText(f.value);this.textContent='✓'"><?= $h($T('kopieren')) ?></button></div>
    <p class="klein" style="margin-top:6px"><?= $h($T('p_code')) ?>: <b><?= $h($p['code']) ?></b></p>
  </div>

  <div class="block pt">
    <div class="zahlen">
      <div class="zahl"><b><?= (int) $k['klicks'] ?></b><span><?= $h($T('klicks')) ?></span></div>
      <div class="zahl"><b><?= (int) $k['kunden'] ?></b><span><?= $h($T('kunden')) ?></span></div>
      <div class="zahl"><b><?= (int) $k['verkaeufe'] ?></b><span><?= $h($T('verkaeufe')) ?></span></div>
      <div class="zahl"><b><?= $h(Fmt::geld((int) $k['provision'])) ?></b><span><?= $h($T('provision')) ?></span></div>
    </div>
    <p class="klein">
      <?php foreach (['wartet', 'freigabe', 'bereit', 'unterwegs', 'ausgezahlt'] as $st): if ($sum[$st] > 0): ?>
        <?= $h($T('s_' . $st)) ?>: <b><?= $h(Fmt::geld($sum[$st])) ?></b> &nbsp;
      <?php endif; endforeach; ?>
    </p>
  </div>

  <?php $wege = PartnerWege::fuerPartner($p); $weg = PartnerWege::weg($p); ?>
  <div class="block pt" id="wege">
    <h2><?= $h($T('wege')) ?></h2>
    <?php if (($_GET['m'] ?? '') === 'w_gut'): ?><div class="hinweis gut"><?= $h($T('w_gut')) ?></div><?php endif; ?>
    <?php if ($wegFehler): ?><div class="hinweis schlecht" role="alert"><?= $h($T($meldung)) ?></div>
    <?php elseif ($weg !== null && !PartnerWege::bereit($p, $weg)): ?><div class="hinweis"><?= $h($T('w_fehlt')) ?></div><?php endif; ?>
    <form method="post" action="<?= $h($selbst()) ?>#wege">
      <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
      <input type="hidden" name="tat" value="weg">
      <?php foreach ($wege as $w): ?>
        <label style="display:flex;gap:10px;align-items:flex-start;color:var(--text);font-size:15px">
          <input type="radio" name="weg" value="<?= $h($w) ?>" <?= $w === $weg ? 'checked' : '' ?> style="margin-top:4px;width:auto">
          <span><b><?= $h($T('w_' . $w)) ?></b><br><span style="color:var(--dim);font-size:13px"><?= $h($T('wd_' . $w)) ?></span></span></label>
      <?php endforeach; ?>
      <?php if (array_intersect($wege, ['sepa', 'wise'])): ?>
        <label for="w_inh"><?= $h($T('inhaber')) ?></label>
        <input id="w_inh" type="text" name="kontoinhaber" maxlength="160" value="<?= $h((string) ($_POST['kontoinhaber'] ?? $p['kontoinhaber'] ?? '')) ?>" autocomplete="name">
        <label for="w_iban"><?= $h($T('iban')) ?><?= (string) ($p['iban_ende'] ?? '') !== '' ? ' — ' . $h($T('iban_da')) . ' …' . $h((string) $p['iban_ende']) : '' ?></label>
        <input id="w_iban" type="text" name="iban" maxlength="42" autocomplete="off" inputmode="text" value="<?= $h((string) ($_POST['iban'] ?? '')) ?>" placeholder="IT60 X054 2811 1010 0000 0123 456">
      <?php endif; ?>
      <?php if (in_array('paypal', $wege, true)): ?>
        <label for="w_pp"><?= $h($T('paypal_email')) ?></label>
        <input id="w_pp" type="email" name="paypal_email" value="<?= $h((string) ($_POST['paypal_email'] ?? $p['paypal_email'] ?? '')) ?>" autocomplete="email">
      <?php endif; ?>
      <button class="knopf<?= empty($p['vereinbarung_am']) ? '' : ' haupt' ?>" type="submit"><?= $h($T('w_speichern')) ?></button>
    </form>

    <?php if ($weg === 'stripe'): ?>
      <div style="border-top:1px solid var(--linie);margin-top:16px;padding-top:14px">
      <h2><?= $h($T('konto')) ?></h2>
      <?php if (!empty($p['stripe_bereit'])): ?>
        <div class="hinweis gut"><?= $h($T('konto_bereit')) ?></div>
      <?php else: ?>
        <p class="lead" style="font-size:14.5px"><?= $h($T('konto_text')) ?></p>
        <form method="post" action="<?= $h($selbst()) ?>">
          <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
          <input type="hidden" name="tat" value="konto">
          <button class="knopf"><?= $h($T(empty($p['stripe_konto']) ? 'konto_knopf' : 'konto_weiter')) ?></button>
        </form>
        <p class="klein"><?= $linkMd($T('stripe_agb')) ?></p>
      <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="block pt">
    <h2><?= $h($T('liste')) ?></h2>
    <?php if (!$liste): ?><p class="klein"><?= $h($T('keine')) ?></p><?php else: ?>
    <table><thead><tr><th><?= $h($T('datum')) ?></th><th><?= $h($T('art')) ?></th><th class="r"><?= $h($T('betrag')) ?></th><th><?= $h($T('stand')) ?></th></tr></thead><tbody>
    <?php foreach ($liste as $z): ?>
      <tr><td><?= $h(Fmt::datum((string) $z['created_at'])) ?></td><td><?= $h($T('a_' . $z['art'])) ?></td>
          <td class="r"><?= $h(Fmt::geld((int) $z['provision_cents'])) ?></td><td><?= $h($T('s_' . $z['status'])) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
    <p class="klein"><?= $h($T('privat')) ?></p>
  </div>

  <?php if ($auszahl): ?>
  <div class="block pt">
    <h2><?= $h($T('auszahlungen')) ?></h2>
    <table><tbody>
    <?php foreach ($auszahl as $a): ?>
      <tr><td><?= $h(Fmt::datum((string) $a['created_at'])) ?></td><td><?= $h($a['nummer']) ?></td>
          <td class="r"><?= $h(Fmt::geld((int) $a['betrag_cents'])) ?></td>
          <td class="r"><a href="<?= $h($selbst(['beleg' => (int) $a['id']])) ?>"><?= $h($T('beleg')) ?></a></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
  <?php endif; ?>
<?php endif; ?>

  <div class="sprachen">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
      <a class="<?= $l === $sprache ? 'jetzt' : '' ?>" href="<?= $h('/partner.php?' . http_build_query(array_merge($p ? ['t' => $p['token']] : [], ['lang' => $l]))) ?>"><?= $h($wie) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<?php require_once __DIR__ . '/app/src/Fuss.php'; echo Fuss::html($sprache); ?>
</body>
</html>
