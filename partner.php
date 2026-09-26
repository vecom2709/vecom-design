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

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'Texte', 'Sprache', 'Partner', 'PartnerWege', 'PartnerPost'] as $k) {
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

/* ---------- Die Partnerseite als App (26.09.2026) ----------
   Das Manifest traegt die persoenliche Adresse als start_url: Wer die Seite
   auf den Startbildschirm legt, landet genau hier, ohne Anmeldung. Es wird
   nur mit gueltigem Schluessel ausgeliefert -- ein fremdes Manifest gibt es nicht. */
if ($p && isset($_GET['manifest'])) {
    header('Content-Type: application/manifest+json; charset=utf-8');
    echo json_encode([
        'name' => 'Vecom Design — Partner', 'short_name' => 'Vecom Partner',
        'start_url' => '/partner.php?t=' . $p['token'], 'scope' => '/partner.php', 'id' => '/partner.php?app=' . substr(hash('sha256', (string) $p['token']), 0, 12),
        'display' => 'standalone', 'background_color' => '#0a0908', 'theme_color' => '#0a0908', 'lang' => $sprache,
        'icons' => [
            ['src' => '/assets/img/app-icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ['src' => '/assets/img/app-icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/* Hinweise ein/aus -- vom Skript der Seite per fetch, mit demselben CSRF-Schluessel. */
if ($p && $_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['tat'] ?? '', ['push_an', 'push_aus'], true)) {
    header('Content-Type: application/json; charset=utf-8');
    if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['_csrf'] ?? ''))) { http_response_code(403); echo '{"ok":false}'; exit; }
    try {
        if ($_POST['tat'] === 'push_an') {
            PartnerPost::aboSpeichern((int) $p['id'], (string) ($_POST['endpoint'] ?? ''), (string) ($_POST['p256dh'] ?? ''), (string) ($_POST['auth'] ?? ''));
        } else {
            PartnerPost::aboLoeschen((int) $p['id'], (string) ($_POST['endpoint'] ?? ''));
        }
        echo '{"ok":true}';
    } catch (Throwable $e) { http_response_code(400); echo '{"ok":false}'; }
    exit;
}

/* ---------- Beleg herunterladen (nur der eigene) ---------- */
if ($p && isset($_GET['beleg'])) {
    $a = Db::one('SELECT id FROM partner_auszahlungen WHERE id = ? AND partner_id = ?', [(int) $_GET['beleg'], (int) $p['id']]);
    $pdf = $a ? Partner::belegPdf((int) $a['id']) : null;
    if ($pdf === null) { http_response_code(404); exit; }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="vecom-provision-' . (int) $a['id'] . '.pdf"');
    echo $pdf; exit;
}

/* ---------- Jahresübersicht (PDF) ---------- */
if ($p && isset($_GET['jahr'])) {
    $pdf = Partner::jahresPdf((int) $p['id'], (int) $_GET['jahr']);
    if ($pdf === null) { http_response_code(404); exit; }
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="vecom-provisionen-' . (int) $_GET['jahr'] . '.pdf"');
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
            } elseif ($tat === 'melden' && $p) {
                $r = Partner::kundeMelden((int) $p['id'], $_POST, $sprache);
                if ($r['ok']) { header('Location: ' . $selbst(['m' => 'm_danke']) . '#melden', true, 303); exit; }
                $meldung = (string) ($r['grund'] ?? 'panne');
            } elseif ($tat === 'sofort' && $p) {
                Db::run('UPDATE partner SET sofortmail = ? WHERE id = ?', [!empty($_POST['an']) ? 1 : 0, (int) $p['id']]);
                header('Location: ' . $selbst() . '#sofort', true, 303); exit;
            } elseif ($tat === 'weg' && $p) {
                $f = PartnerWege::setzen((int) $p['id'], $_POST);
                if ($f === null) { header('Location: ' . $selbst(['m' => 'w_gut']) . '#wege', true, 303); exit; }
                $meldung = $f;
            } elseif ($tat === 'nachricht' && $p) {
                try {
                    PartnerPost::schreiben((int) $p['id'], (string) ($_POST['text'] ?? ''), 'partner');
                    header('Location: ' . $selbst(['m' => 'nachr_danke']) . '#nachrichten', true, 303); exit;
                } catch (InvalidArgumentException $e) { $meldung = 'nachr_leer'; }
                  catch (LengthException $e) { $meldung = 'nachr_zuviel'; }
            } elseif ($tat === 'konto' && $p) {
                $r = Partner::kontoEinrichten($p, $basis . $selbst());
                if ($r['ok']) { header('Location: ' . $r['url'], true, 303); exit; }
                $meldung = 'konto_fehler';
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
/* Die Platzhalter aller erklärenden Texte — dieselben Zahlen wie in der Vereinbarung. */
$platz = [
    '{satz}' => $satz['art'] === 'fest' ? Fmt::geld($satz['wert']) . ($sprache === 'it' ? ' per vendita' : ($sprache === 'en' ? ' per sale' : ' je Verkauf'))
                                        : Partner::satzWort($satz),
    '{min}' => Fmt::geld(Partner::zahl('partner_mindest_cents')),
    '{tage}' => (string) max(14, Partner::zahl('partner_sperrtage')),
    '{zuordnung}' => (string) Partner::zahl('partner_zuordnung_monate'),
];
$Tp = static fn(string $k): string => strtr($T($k), $platz);
/* „So funktioniert's“ in drei Schritten — dieselbe Erklärung auf Bewerbung und Partnerseite. */
$so = static function () use ($Tp, $T, $h): string {
    $o = '<div class="so">';
    foreach ([1, 2, 3] as $i) {
        $o .= '<div class="so__schritt"><span class="so__nr">' . $i . '</span><div><b>' . $h($T('so_' . $i . '_t')) . '</b><br>'
            . '<span>' . $h($Tp('so_' . $i)) . '</span></div></div>';
    }
    return $o . '</div>';
};
$linkMd = static fn(string $s): string => (string) preg_replace('~\[([^\]]+)\]\((https://[^)\s]+)\)~',
    '<a href="$2" target="_blank" rel="noopener">$1</a>', htmlspecialchars($s, ENT_QUOTES, 'UTF-8'));
/* ---------- Die Karte zum Ausdrucken (QR + Link), A6 ---------- */
if ($p && isset($_GET['karte'])) {
    $kLink = Partner::link($p) . '/karte';
    ?><!doctype html><html lang="<?= $h($sprache) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex"><title>Vecom Design — <?= $h($p['code']) ?></title>
<link rel="stylesheet" href="/assets/css/fonts.css">
<style>
  @page{size:A6;margin:0}
  body{margin:0;background:#e9e5dc;font-family:'Inter',system-ui,sans-serif}
  .karte{width:105mm;height:148mm;margin:10mm auto;background:#0a0908;color:#f7f3ea;box-sizing:border-box;padding:12mm 9mm;
         display:flex;flex-direction:column;align-items:center;text-align:center;border-radius:3mm}
  .karte img.logo{width:22mm;height:auto;margin-bottom:4mm}
  .karte .wort{font-family:'Archivo',sans-serif;font-weight:800;letter-spacing:.08em;font-size:13pt;margin-bottom:6mm}
  .karte .wort b{color:#d6a849}
  .karte h1{font-family:'Archivo',sans-serif;font-size:17pt;line-height:1.15;margin:0 0 5mm}
  .karte #qr{background:#fff;padding:3mm;border-radius:2mm;width:42mm;height:42mm}
  .karte #qr svg{width:100%;height:100%;display:block}
  .karte p{font-size:10pt;color:#b4ada2;margin:5mm 0 2mm;line-height:1.4}
  .karte .url{font-size:10.5pt;color:#f1d38b;font-weight:600;word-break:break-all}
  .druck{display:block;margin:0 auto 10mm;padding:12px 22px;font-size:15px;border-radius:10px;border:0;cursor:pointer;
         background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438);color:#16120b;font-weight:700}
  @media print{body{background:#0a0908}.druck{display:none}.karte{margin:0;border-radius:0}}
</style></head><body>
<div class="karte">
  <img class="logo" src="/assets/img/logo-mark.webp?v=gold2609" alt="">
  <div class="wort"><b>VECOM</b> DESIGN</div>
  <h1><?= $h($T('karte_titel')) ?></h1>
  <div id="qr" data-link="<?= $h($kLink) ?>"></div>
  <p><?= $h(strtr($T('karte_text'), ['{name}' => Partner::anzeigeName($p)])) ?></p>
  <div class="url"><?= $h(preg_replace('~^https?://~', '', Partner::link($p))) ?></div>
</div>
<button class="druck" onclick="window.print()"><?= $h($T('karte_druck')) ?></button>
<script src="/assets/js/qrcode.js"></script>
<script>
  (function () { var el = document.getElementById('qr'); var q = qrcode(0, 'M'); q.addData(el.dataset.link); q.make();
    el.innerHTML = q.createSvgTag({ cellSize: 4, margin: 0, scalable: true }); })();
</script>
</body></html><?php
    exit;
}

?><!doctype html>
<html lang="<?= $h($sprache) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $h($p ? $T('p_titel') : $T('titel')) ?> — Vecom Design</title>
<?php if ($p): ?>
<link rel="manifest" href="<?= $h($selbst(['manifest' => 1])) ?>">
<meta name="theme-color" content="#0a0908">
<link rel="apple-touch-icon" href="/assets/img/app-icon-192.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Vecom Partner">
<?php endif; ?>
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
  .pt .zahl small{display:block;font-size:11px;color:var(--leise);margin-top:3px;line-height:1.35}
  .so{display:grid;gap:12px;margin:6px 0 4px}
  .so__schritt{display:flex;gap:12px;align-items:flex-start;font-size:14px;line-height:1.55}
  .so__schritt span{color:var(--dim)}
  .so__nr{flex:0 0 28px;height:28px;border-radius:50%;display:grid;place-items:center;font-weight:700;color:#16120b !important;
          background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438)}
  .naechst{border:1px solid var(--linie2);border-radius:12px;padding:12px 14px;margin:0 0 14px;font-size:14.5px;line-height:1.5}
  .naechst b{display:block;font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--cyan);margin-bottom:3px}
  .text-kopie{display:flex;gap:8px;align-items:flex-start;margin:8px 0}
  .text-kopie textarea{flex:1;min-height:74px;font-size:13.5px;line-height:1.5;padding:10px 12px}
  .knoepfe{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
  /* minmax(0,1fr): Ein Grid-Eintrag ist sonst mindestens so breit wie sein
     Inhalt -- die lange Adresse schob die Seite auf 519 px, das Abschneiden
     im code griff nie. */
  .kanaele{display:grid;grid-template-columns:minmax(0,1fr);gap:6px;margin-top:8px}
  .kanaele div{display:flex;gap:8px;align-items:center;font-size:13px;min-width:0}
  .kanaele b{flex:0 0 78px}
  .kanaele .knopf{min-height:34px;padding:6px 12px}
  .kanaele code{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--dim)}
  .stufe{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--linie2);border-radius:999px;padding:4px 12px;font-size:13px;margin-top:10px}
  .faq pre{white-space:pre-wrap;font-family:inherit;font-size:14px;line-height:1.65;color:var(--dim);margin:8px 0 0}
  .verlauf{display:flex;flex-direction:column;gap:8px;margin:4px 0 12px;max-height:420px;overflow-y:auto}
  .blase{max-width:86%;padding:9px 12px;border-radius:14px;font-size:14.5px;line-height:1.5;white-space:pre-wrap;word-break:break-word}
  .blase small{display:block;font-size:11.5px;color:var(--leise);margin-top:4px}
  .blase.ich{align-self:flex-end;background:rgba(241,211,139,.10);border:1px solid rgba(241,211,139,.28);border-bottom-right-radius:4px}
  .blase.wir{align-self:flex-start;background:var(--flaeche2,rgba(255,255,255,.04));border:1px solid var(--linie);border-bottom-left-radius:4px}
  .emp td small{display:block;color:var(--leise);font-size:12px}
  .emp .st{display:inline-block;padding:2px 9px;border-radius:999px;border:1px solid var(--linie);font-size:12.5px;white-space:nowrap}
  .emp .st.bezahlt,.emp .st.online{border-color:rgba(241,211,139,.5);color:var(--cyan)}
  .geld{border:1px solid rgba(241,211,139,.55);background:rgba(241,211,139,.07);border-radius:12px;padding:12px 14px;margin:0 0 14px;
        display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;font-size:14.5px}
  @media (max-width:520px){.pt .zahlen{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<div class="seite">
  <div class="wortmarke">
    <img src="/assets/img/logo-mark.webp?v=gold2609" alt="" width="58" height="46" fetchpriority="high">
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
      <h2><?= $h($T('so_titel')) ?></h2>
      <?= $so() ?>
      <details class="faq" style="margin:14px 0"><summary style="cursor:pointer;color:var(--cyan)"><?= $h($T('faq_titel')) ?></summary>
        <pre><?= $h($Tp('faq')) ?></pre></details>
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
  $liste = Db::all('SELECT created_at, art, provision_cents, einbehalt_cents, status, frei_ab FROM partner_provisionen WHERE partner_id = ? ORDER BY id DESC LIMIT 100', [(int) $p['id']]);
  $auszahl = Db::all('SELECT * FROM partner_auszahlungen WHERE partner_id = ? ORDER BY id DESC LIMIT 50', [(int) $p['id']]);
  $link = Partner::link($p);
?>
  <div class="block pt">
    <h1><?= $h($T('p_titel')) ?></h1>
    <p class="lead"><?= $h($p['name']) ?> · <?= $h($bedingungen) ?></p>
    <?php $wegFehler = in_array($meldung, ['iban_falsch', 'inhaber_fehlt', 'email_falsch', 'konto_fehler'], true); ?>
    <?php if ($meldung !== '' && !$wegFehler): ?><div class="hinweis schlecht"><?= $h($T($meldung)) ?></div><?php endif; ?>
    <?php if ($p['status'] === 'pausiert'): ?><div class="hinweis"><?= $h($T('pausiert')) ?></div><?php endif; ?>
    <?php
      /* Der eine nächste Schritt — was der Partner jetzt tun muss, nicht alles auf einmal. */
      $nWeg = PartnerWege::weg($p);
      $naechst = empty($p['vereinbarung_am']) ? 'n_vereinbarung'
               : ($nWeg === null || !PartnerWege::bereit($p, $nWeg) ? 'n_weg'
               : ((int) Db::wert('SELECT COUNT(*) FROM partner_zuordnungen WHERE partner_id = ?', [(int) $p['id']], 0) === 0 ? 'n_teilen' : 'n_laeuft'));
    ?>
    <?php /* Geld liegt bereit, der Weg fehlt -- dann ist DAS der naechste Schritt, mit Betrag (26.09.2026). */
          $bereitCents = !empty($p['vereinbarung_am']) && $naechst === 'n_weg' ? Partner::auszahlbar((int) $p['id']) : 0; ?>
    <?php if ($bereitCents <= 0): ?><div class="naechst"><b><?= $h($T('n_titel')) ?></b><?= $h($T($naechst)) ?></div><?php endif; ?>
    <?php if ($bereitCents > 0): ?>
      <div class="geld" role="status"><span><?= $h(strtr($T('geld_bereit'), ['{betrag}' => Fmt::geld($bereitCents)])) ?></span>
        <a class="knopf haupt" href="#wege"><?= $h($T('geld_knopf')) ?></a></div>
    <?php endif; ?>

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
    <a class="knopf" style="margin-top:10px;display:inline-flex" target="_blank" rel="noopener"
       href="https://wa.me/?text=<?= rawurlencode($T('teilen_text') . $link) ?>"><?= $h($T('teilen_wa')) ?></a>
    <details style="margin-top:14px"<?= $naechst === 'n_teilen' ? ' open' : '' ?>><summary style="cursor:pointer;color:var(--cyan);font-size:14px"><?= $h($T('so_titel')) ?></summary>
      <?= $so() ?>
      <details class="faq" style="margin-top:10px"><summary style="cursor:pointer;color:var(--cyan);font-size:13.5px"><?= $h($T('faq_titel')) ?></summary>
        <pre><?= $h($Tp('faq')) ?></pre></details>
    </details>
  </div>

  <div class="block pt">
    <div class="zahlen">
      <div class="zahl"><b><?= (int) $k['klicks'] ?></b><span><?= $h($T('klicks')) ?></span><small><?= $h($T('z_klicks')) ?></small></div>
      <div class="zahl"><b><?= (int) $k['kunden'] ?></b><span><?= $h($T('kunden')) ?></span><small><?= $h($T('z_kunden')) ?></small></div>
      <div class="zahl"><b><?= (int) $k['verkaeufe'] ?></b><span><?= $h($T('verkaeufe')) ?></span><small><?= $h($T('z_verkaeufe')) ?></small></div>
      <div class="zahl"><b><?= $h(Fmt::geld((int) $k['provision'])) ?></b><span><?= $h($T('provision')) ?></span><small><?= $h($T('z_provision')) ?></small></div>
    </div>
    <?php $stand = Partner::stufeStand($p); if ($stand['stufe'] !== null): ?>
      <div class="stufe">★ <?= $h(strtr($T('st_text'), ['{stufe}' => $T('st_' . $stand['stufe']), '{satz}' => Partner::satzWort(Partner::satzFuer($p), true), '{n}' => (string) $stand['verkaeufe']])) ?></div>
      <p class="klein" style="margin-top:6px"><?= $h($stand['naechste'] !== null
          ? strtr($T('st_naechst'), ['{fehlen}' => (string) $stand['fehlen'], '{naechste}' => $T('st_' . $stand['naechste'])])
          : $T('st_top')) ?></p>
    <?php endif; ?>
    <p class="klein">
      <?php foreach (['wartet', 'freigabe', 'bereit', 'unterwegs', 'ausgezahlt'] as $st): if ($sum[$st] > 0): ?>
        <?= $h($T('s_' . $st)) ?>: <b><?= $h(Fmt::geld($sum[$st])) ?></b> &nbsp;
      <?php endif; endforeach; ?>
    </p>
  </div>

  <?php $emp = PartnerPost::empfehlungen((int) $p['id']); if ($emp): ?>
  <div class="block pt" id="empfehlungen">
    <h2><?= $h($T('emp_titel')) ?></h2>
    <p class="klein" style="margin-top:0"><?= $h($T('emp_text')) ?></p>
    <table class="emp"><tbody>
    <?php foreach ($emp as $e): ?>
      <tr><td><b><?= $h(strtr($T('emp_nr'), ['{n}' => (string) $e['nr']])) ?></b>
            <small><?= $h(($e['ort'] !== '' ? $e['ort'] . ' · ' : '') . strtr($T('emp_seit'), ['{datum}' => Fmt::datum($e['seit'])])) ?></small></td>
          <td><span class="st <?= $h($e['stufe']) ?>"><?= $h($T('emp_s_' . $e['stufe'])) ?></span></td>
          <td class="r"><?php if ($e['provision'] > 0): ?><?= $h(Fmt::geld($e['provision'])) ?>
            <?php if ($e['frei_ab']): ?><small><?= $h(strtr($T('emp_frei'), ['{datum}' => Fmt::datum($e['frei_ab'])])) ?></small><?php endif; ?>
          <?php else: ?>—<?php endif; ?></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  </div>
  <?php endif; ?>

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
    <?php $anl = array_values(array_filter(['stripe', 'sepa', 'paypal'], static fn($w) => in_array($w, $wege, true))); if ($anl): ?>
      <details style="margin-top:14px"<?= $weg !== null && !PartnerWege::bereit($p, $weg) ? ' open' : '' ?>>
        <summary style="cursor:pointer;color:var(--cyan);font-size:14px"><?= $h($T('anleitung')) ?></summary>
        <?php foreach ($anl as $w): ?>
          <p style="margin:12px 0 4px;font-weight:600;font-size:14px"><?= $h($T('w_' . $w)) ?></p>
          <pre style="white-space:pre-wrap;font-family:inherit;font-size:13.5px;line-height:1.65;color:var(--dim);margin:0"><?= $h($T('anl_' . $w)) ?></pre>
        <?php endforeach; ?>
      </details>
    <?php endif; ?>

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

  <div class="block pt" id="werbung">
    <h2><?= $h($T('w_titel')) ?></h2>
    <p class="klein" style="margin-top:0"><?= $h($T('w_text')) ?></p>
    <?php foreach (['w_post1', 'w_post2', 'w_post3'] as $i => $wp): $txt = strtr($T($wp), ['{link}' => $link]); ?>
      <div class="text-kopie"><textarea id="post<?= $i ?>" readonly><?= $h($txt) ?></textarea>
        <button class="knopf" type="button" onclick="var f=document.getElementById('post<?= $i ?>');f.select();navigator.clipboard&&navigator.clipboard.writeText(f.value);this.textContent='✓'"><?= $h($T('kopieren')) ?></button></div>
    <?php endforeach; ?>
    <p class="klein"><?= $h($T('w_hinweis')) ?></p>
    <div class="knoepfe">
      <a class="knopf" href="<?= $h($selbst(['karte' => 1])) ?>" target="_blank" rel="noopener"><?= $h($T('w_karte')) ?></a>
      <button class="knopf" type="button" id="qr_laden"><?= $h($T('w_qr')) ?></button>
      <button class="knopf" type="button" id="story_laden"><?= $h($T('w_bild')) ?></button>
    </div>
    <h2 style="margin-top:20px"><?= $h($T('k_titel')) ?></h2>
    <p class="klein" style="margin-top:0"><?= $h($T('k_text')) ?></p>
    <?php $kanalName = static fn(string $k): string => ['whatsapp' => 'WhatsApp', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'karte' => $T('karte_titel_kurz')][$k] ?? ucfirst($k); ?>
    <div class="kanaele">
      <?php foreach (['whatsapp', 'instagram', 'facebook', 'karte'] as $kn): $kl = $link . '/' . $kn; ?>
        <div><b><?= $h($kanalName($kn)) ?></b><code><?= $h($kl) ?></code>
          <button class="knopf" type="button" onclick="navigator.clipboard&&navigator.clipboard.writeText('<?= $h($kl) ?>');this.textContent='✓'"><?= $h($T('kopieren')) ?></button></div>
      <?php endforeach; ?>
    </div>
    <?php $kz = Db::all('SELECT kanal, SUM(anzahl) AS n FROM partner_kanal_klicks WHERE partner_id = ? GROUP BY kanal ORDER BY n DESC', [(int) $p['id']]);
      if ($kz): ?>
      <p class="klein"><?= $h($T('k_kanal')) ?>: <?= $h(implode(' · ', array_map(static fn($z) => $kanalName((string) $z['kanal']) . ' ' . $z['n'], $kz))) ?></p>
    <?php endif; ?>
  </div>

  <?php PartnerPost::gelesen((int) $p['id'], 'partner'); $verlauf = PartnerPost::verlauf((int) $p['id']); ?>
  <div class="block pt" id="nachrichten">
    <h2><?= $h($T('nachr_titel')) ?></h2>
    <?php if (($_GET['m'] ?? '') === 'nachr_danke'): ?><div class="hinweis gut" role="status"><?= $h($T('nachr_danke')) ?></div><?php endif; ?>
    <?php if (in_array($meldung, ['nachr_leer', 'nachr_zuviel'], true)): ?><div class="hinweis schlecht"><?= $h($T($meldung)) ?></div><?php endif; ?>
    <p class="klein" style="margin-top:0"><?= $h($T('nachr_text')) ?></p>
    <?php if ($verlauf): ?>
      <div class="verlauf" id="verlauf">
        <?php foreach ($verlauf as $n): $ich = $n['von'] === 'partner'; ?>
          <div class="blase <?= $ich ? 'ich' : 'wir' ?>"><?= $h((string) $n['text']) ?><small><?= $h(($ich ? $T('nachr_sie') : $T('nachr_wir')) . ' · ' . date('d.m.Y H:i', strtotime((string) $n['created_at']))) ?></small></div>
        <?php endforeach; ?>
      </div>
      <script>(function(){var v=document.getElementById('verlauf'); if(v){v.scrollTop=v.scrollHeight;}})();</script>
    <?php endif; ?>
    <form method="post" action="<?= $h($selbst()) ?>#nachrichten">
      <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
      <input type="hidden" name="tat" value="nachricht">
      <label for="n_text"><?= $h($T('nachr_feld')) ?></label>
      <textarea id="n_text" name="text" rows="3" maxlength="<?= PartnerPost::MAX_LAENGE ?>" required></textarea>
      <button class="knopf haupt" type="submit"><?= $h($T('nachr_knopf')) ?></button>
    </form>
  </div>

  <div class="block pt" id="melden">
    <h2><?= $h($T('m_titel')) ?></h2>
    <?php if (($_GET['m'] ?? '') === 'm_danke'): ?><div class="hinweis gut"><?= $h($T('m_danke')) ?></div><?php endif; ?>
    <?php if (in_array($meldung, ['m_einverstanden', 'm_genug', 'angaben'], true)): ?><div class="hinweis schlecht"><?= $h($T($meldung)) ?></div><?php endif; ?>
    <p class="klein" style="margin-top:0"><?= $h($T('m_text')) ?></p>
    <form method="post" action="<?= $h($selbst()) ?>#melden">
      <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
      <input type="hidden" name="tat" value="melden">
      <label for="m_name"><?= $h($T('f_name')) ?></label><input id="m_name" type="text" name="name" required maxlength="120">
      <label for="m_mail"><?= $h($T('f_email')) ?></label><input id="m_mail" type="email" name="email" required>
      <label for="m_tel"><?= $h($T('m_telefon')) ?></label><input id="m_tel" type="text" name="telefon" maxlength="60">
      <label for="m_was"><?= $h($T('m_anliegen')) ?></label><textarea id="m_was" name="anliegen" rows="2" maxlength="2000"></textarea>
      <label style="display:flex;gap:8px;align-items:flex-start;color:var(--text)">
        <input type="checkbox" name="einverstanden" value="1" required style="margin-top:3px;width:auto"> <?= $h($T('m_einverstanden')) ?></label>
      <button class="knopf" type="submit"><?= $h($T('m_knopf')) ?></button>
    </form>
  </div>

  <div class="block pt">
    <h2><?= $h($T('liste')) ?></h2>
    <?php if (!$liste): ?><p class="klein"><?= $h($T('keine')) ?></p><?php else: ?>
    <table><thead><tr><th><?= $h($T('datum')) ?></th><th><?= $h($T('art')) ?></th><th class="r"><?= $h($T('betrag')) ?></th><th><?= $h($T('stand')) ?></th></tr></thead><tbody>
    <?php foreach ($liste as $z): ?>
      <tr><td><?= $h(Fmt::datum((string) $z['created_at'])) ?></td><td><?= $h($T('a_' . $z['art'])) ?></td>
          <td class="r"><?= $h(Fmt::geld((int) $z['provision_cents'])) ?></td><td><?= $h($T('s_' . $z['status'])) ?><?php if ($z['status'] === 'wartet'): ?><br><small style="color:var(--leise)"><?= $h(strtr($T('frei_ab'), ['{datum}' => Fmt::datum((string) $z['frei_ab'])])) ?></small><?php endif; ?></td></tr>
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
  <?php $jahre = Partner::jahre((int) $p['id']); ?>
  <div class="block pt" id="sofort">
    <?php if ($jahre): ?>
      <h2><?= $h($T('jahr_titel')) ?></h2>
      <p class="klein" style="margin-top:0"><?php foreach ($jahre as $j): ?><a href="<?= $h($selbst(['jahr' => $j])) ?>"><?= $h(strtr($T('jahr_link'), ['{jahr}' => (string) $j])) ?></a> &nbsp; <?php endforeach; ?></p>
    <?php endif; ?>
    <form method="post" action="<?= $h($selbst()) ?>#sofort" style="flex-direction:row;align-items:center;gap:10px">
      <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>"><input type="hidden" name="tat" value="sofort">
      <label style="display:flex;gap:8px;align-items:center;color:var(--text);font-size:14px">
        <input type="checkbox" name="an" value="1" <?= !empty($p['sofortmail']) ? 'checked' : '' ?> onchange="this.form.submit()" style="width:auto"> <?= $h($T('sofort')) ?></label>
      <noscript><button class="knopf"><?= $h($T('w_speichern')) ?></button></noscript>
    </form>
  </div>

  <div class="block pt" id="app">
    <h2><?= $h($T('app_titel')) ?></h2>
    <p class="klein" style="margin-top:0"><?= $h($T('app_text')) ?></p>
    <div class="knoepfe">
      <button class="knopf haupt" type="button" id="push_an" hidden><?= $h($T('app_an')) ?></button>
      <button class="knopf" type="button" id="installieren" hidden><?= $h($T('app_installieren')) ?></button>
    </div>
    <p class="klein" id="push_stand" role="status"></p>
  </div>
  <script>
  /* Handy-App (26.09.2026): Service Worker nur fuer Hinweise, kein Seiten-Cache. */
  (function () {
    var knopf = document.getElementById('push_an'), stand = document.getElementById('push_stand'), inst = document.getElementById('installieren');
    var W = { an: <?= json_encode($T('app_ist_an')) ?>, nein: <?= json_encode($T('app_nein')) ?>, verboten: <?= json_encode($T('app_verboten')) ?> };
    var schluessel = <?= json_encode(PartnerPost::vapid()) ?>, csrf = <?= json_encode($_SESSION['csrf']) ?>, ziel = <?= json_encode($selbst()) ?>;
    var wartend = null;
    window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); wartend = e; inst.hidden = false; });
    inst.addEventListener('click', function () { if (wartend) { wartend.prompt(); wartend = null; inst.hidden = true; } });
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !schluessel) { stand.textContent = W.nein; return; }
    function b64(s) { s = s.replace(/-/g, '+').replace(/_/g, '/'); var r = atob(s + '==='.slice((s.length + 3) % 4)); var a = new Uint8Array(r.length); for (var i = 0; i < r.length; i++) a[i] = r.charCodeAt(i); return a; }
    function melden(abo) {
      var j = abo.toJSON(), f = new FormData();
      f.append('_csrf', csrf); f.append('tat', 'push_an'); f.append('endpoint', j.endpoint); f.append('p256dh', j.keys.p256dh); f.append('auth', j.keys.auth);
      return fetch(ziel, { method: 'POST', body: f, credentials: 'same-origin' });
    }
    navigator.serviceWorker.register('/partner-sw.js', { scope: '/partner.php' }).then(function (reg) {
      return reg.pushManager.getSubscription().then(function (abo) {
        if (Notification.permission === 'denied') { stand.textContent = W.verboten; return; }
        if (abo) { stand.textContent = W.an; melden(abo); return; }
        knopf.hidden = false;
        knopf.addEventListener('click', function () {
          Notification.requestPermission().then(function (erlaubt) {
            if (erlaubt !== 'granted') { knopf.hidden = true; stand.textContent = W.verboten; return; }
            return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64(schluessel) }).then(function (neu) {
              return melden(neu).then(function () { knopf.hidden = true; stand.textContent = W.an; });
            });
          }).catch(function () { knopf.hidden = true; stand.textContent = W.nein; });
        });
      });
    }).catch(function () { stand.textContent = W.nein; });
  })();
  </script>

  <script src="/assets/js/qrcode.js"></script>
  <script>
  /* QR als PNG und ein Story-Bild (1080×1920) — im Browser gezeichnet, nichts geht an fremde Server. */
  (function () {
    var link = <?= json_encode($link . '/instagram') ?>, qrLink = <?= json_encode($link . '/karte') ?>, name = <?= json_encode(Partner::anzeigeName($p)) ?>;
    var titel = <?= json_encode($T('karte_titel')) ?>, empf = <?= json_encode(strtr($T('karte_text'), ['{name}' => Partner::anzeigeName($p)])) ?>;
    function qrMatrix(t) { var q = qrcode(0, 'M'); q.addData(t); q.make(); return q; }
    function zeichneQr(ctx, q, x, y, groesse) {
      var n = q.getModuleCount(), z = groesse / n; ctx.fillStyle = '#fff'; ctx.fillRect(x - z * 2, y - z * 2, groesse + z * 4, groesse + z * 4);
      ctx.fillStyle = '#0a0908';
      for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) if (q.isDark(r, c)) ctx.fillRect(x + c * z, y + r * z, Math.ceil(z), Math.ceil(z));
    }
    function laden(canvas, datei) { var a = document.createElement('a'); a.download = datei; a.href = canvas.toDataURL('image/png'); a.click(); }
    document.getElementById('qr_laden').addEventListener('click', function () {
      var c = document.createElement('canvas'); c.width = c.height = 1000; var x = c.getContext('2d');
      x.fillStyle = '#fff'; x.fillRect(0, 0, 1000, 1000); zeichneQr(x, qrMatrix(qrLink), 80, 80, 840); laden(c, 'vecom-qr.png');
    });
    document.getElementById('story_laden').addEventListener('click', function () {
      var c = document.createElement('canvas'); c.width = 1080; c.height = 1920; var x = c.getContext('2d');
      var g = x.createLinearGradient(0, 0, 0, 1920); g.addColorStop(0, '#15120d'); g.addColorStop(1, '#0a0908'); x.fillStyle = g; x.fillRect(0, 0, 1080, 1920);
      var gold = x.createLinearGradient(0, 0, 1080, 0); gold.addColorStop(0, '#b98a31'); gold.addColorStop(.45, '#f7e6ae'); gold.addColorStop(1, '#c49438');
      x.textAlign = 'center'; x.fillStyle = gold; x.font = '800 64px Archivo, sans-serif'; x.fillText('VECOM DESIGN', 540, 300);
      x.fillStyle = '#f7f3ea'; x.font = '700 84px Archivo, sans-serif';
      var w = titel.split(' '), zeile = '', y = 520;
      w.forEach(function (t) { var probe = zeile ? zeile + ' ' + t : t; if (x.measureText(probe).width > 900) { x.fillText(zeile, 540, y); y += 100; zeile = t; } else { zeile = probe; } });
      x.fillText(zeile, 540, y);
      zeichneQr(x, qrMatrix(link), 290, 820, 500);
      x.fillStyle = '#b4ada2'; x.font = '400 44px Inter, sans-serif'; x.fillText(empf, 540, 1470);
      x.fillStyle = gold; x.font = '600 46px Inter, sans-serif'; x.fillText(link.replace(/^https?:\/\//, ''), 540, 1560);
      laden(c, 'vecom-story.png');
    });
  })();
  </script>
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
