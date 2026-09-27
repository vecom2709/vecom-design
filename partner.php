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

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'Texte', 'Sprache', 'Partner', 'PartnerWege', 'PartnerPost', 'PartnerWerbung', 'PartnerRecherche', 'PartnerCheck', 'PartnerSeite', 'PartnerStart', 'PartnerErfolg', 'PartnerKalender', 'PartnerWettbewerb', 'PartnerMappe', 'PartnerAnschreiben'] as $k) {
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

/* Offene Migrationen zieht diese Seite selbst nach (27.09.2026). Bis dahin
   nur, wenn die Tabelle partner ganz fehlte -- eine neue SPALTE (083) fehlte
   nach dem Deploy, bis der Cronjob oder die Verwaltung lief, und die Seite
   wäre so lange mit „Unknown column“ ausgefallen. Der Blick kostet einen
   glob und eine Abfrage; die Sperre in selbsttaetig() verhindert doppelte Läufe. */
try { require_once __DIR__ . '/app/src/Einrichtung.php'; Einrichtung::selbsttaetig(false); } catch (Throwable $e) { }

$token = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$p = $token !== '' ? Partner::ausToken($token) : null;
/* DIE SPRACHE DES PARTNERS GEWINNT (Uwe, 26.09.2026: „ständig auf Englisch“)
   Auf der Partnerseite gilt die Sprache, die am Partner steht — nicht der
   Sprach-Keks, den der Browser von der Website mitbringt (wer dort einmal
   „English“ geklickt hat, sah seine Partnerseite danach immer englisch).
   Nur ein Klick auf die Sprachwahl unten ändert sie — und dann für immer,
   auch für seine Mails. */
if ($p) {
    /* Die eigenen Aufrufe der Empfehlungsseite zählen nicht als Besuch
       (27.09.2026, Uwe: Ja zu „Echte Besucher zählen“): Ein Keks in dem
       Browser, in dem der Partner seinen Bereich öffnet. Nur der Code. */
    if (($_COOKIE[Partner::KEKS_SELBST] ?? '') !== (string) $p['code']) {
        @setcookie(Partner::KEKS_SELBST, (string) $p['code'], ['expires' => time() + 31536000, 'path' => '/',
            'secure' => ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off', 'httponly' => true, 'samesite' => 'Lax']);
    }
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
            // „any“ und „maskable“ getrennt -- zusammengelegt warnen Chrome und die WebAPK-Erzeugung.
            ['src' => '/assets/img/app-icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/assets/img/app-icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/assets/img/app-icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
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

/* Eingebettete Stripe-Einrichtung (27.09.2026): Das Skript der Seite holt
   sich hier die kurzlebige Sitzung. Jede Anfrage legt eine neue an -- Stripe
   verlangt das, und eine abgelaufene würde die Einrichtung mitten im Formular
   abbrechen. Ein bereites Konto bekommt keine Sitzung mehr. */
if ($p && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['tat'] ?? '') === 'stripe_sitzung') {
    header('Content-Type: application/json; charset=utf-8');
    if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['_csrf'] ?? ''))) { http_response_code(403); echo '{"ok":false}'; exit; }
    if (!empty($p['stripe_bereit'])) { echo '{"ok":false,"grund":"bereit"}'; exit; }
    /* Erst das Land (28.09.2026): Stripe legt es beim Anlegen fest. */
    if (isset($_POST['land'])) {
        try { if (Partner::landSetzen($p, (string) $_POST['land']) === 'bereit') { echo '{"ok":false,"grund":"bereit"}'; exit; } $p = Partner::ausToken($token); }
        catch (Throwable $e) { }
    }
    try { $r = Partner::kontoSitzung($p); } catch (Throwable $e) { $r = ['ok' => false, 'text' => $e->getMessage()]; }
    if (!$r['ok']) {
        try { Events::melden('partner_stripe_fehler', 'Eingebettete Stripe-Einrichtung nicht gestartet: ' . $p['name'], 'warnung',
                             (string) ($r['text'] ?? ''), '/partner/' . (int) $p['id']); } catch (Throwable $e) { }
        echo '{"ok":false}'; exit;
    }
    echo json_encode(['ok' => true, 'secret' => $r['secret']]); exit;
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
            } elseif ($tat === 'wettbewerb_name' && $p) {
                PartnerWettbewerb::nameErlauben((int) $p['id'], !empty($_POST['an']));
                header('Location: ' . $selbst() . '#wettbewerb', true, 303); exit;
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
            } elseif ($tat === 'profil' && $p) {
                /* Empfehlungsseite: Satz und (wenn mitgeschickt) Foto. Beides
                   steht öffentlich auf vecom-design.it -- Vecom bekommt eine
                   Meldung und kann es in der Partnerakte wieder entfernen. */
                $f = PartnerWerbung::satzSpeichern((int) $p['id'], (string) ($_POST['satz'] ?? ''));
                $datei = $_FILES['foto'] ?? null;
                if ($f === 'ok' && is_array($datei) && ($datei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $f = ($datei['error'] === UPLOAD_ERR_INI_SIZE || $datei['error'] === UPLOAD_ERR_FORM_SIZE) ? 'foto_gross'
                       : ($datei['error'] === UPLOAD_ERR_OK && is_uploaded_file((string) $datei['tmp_name'])
                          ? PartnerWerbung::fotoSpeichern((int) $p['id'], (string) $datei['tmp_name'], (int) $datei['size']) : 'foto_art');
                }
                if ($f === 'ok') {
                    Events::melden('partner_profil', 'Partner hat seine Empfehlungsseite geändert: ' . $p['name'], 'info', null, '/partner/' . (int) $p['id']);
                    header('Location: ' . $selbst(['m' => 'pf_gut']) . '#profil', true, 303); exit;
                }
                $meldung = $f;
            } elseif ($tat === 'check' && $p) {
                $r = PartnerCheck::anlegen((int) $p['id'], (string) ($_POST['url'] ?? ''));
                if ($r['ok']) { header('Location: ' . $selbst(['ck' => $r['token']]) . '#recherche', true, 303); exit; }
                $meldung = (string) $r['grund'];
            } elseif ($tat === 'fe_eintragen' && $p) {
                /* Selbst gefundener Betrieb (27.09.2026): eintragen, reservieren, Schnellcheck vorbereiten. */
                $fe = PartnerRecherche::eintragen((int) $p['id'], $_POST);
                if ($fe['ok']) {
                    header('Location: ' . $selbst(array_filter(['m' => 'fe_gut', 'ck_url' => (string) ($_POST['website'] ?? '')])) . '#recherche', true, 303); exit;
                }
                $meldung = (string) $fe['grund'];
            } elseif ($tat === 'ak_vecom' && $p) {
                /* „Vecom soll anschreiben“ (27.09.2026): nur für eigene Reservierungen. */
                $ak = PartnerAnschreiben::briefWuenschen($p, (int) ($_POST['firma'] ?? 0));
                header('Location: ' . $selbst(['m' => $ak === 'ok' ? 'ak_gut' : $ak, 'ak' => (int) ($_POST['firma'] ?? 0)]) . '#ak_' . (int) ($_POST['firma'] ?? 0), true, 303); exit;
            } elseif ($tat === 'ck_weg' && $p) {
                PartnerCheck::loeschen((int) $p['id'], (string) ($_POST['token'] ?? ''));
                header('Location: ' . $selbst(['m' => 'ck_weg_gut']) . '#recherche', true, 303); exit;
            } elseif (($tat === 'fi_reserv' || $tat === 'fi_frei') && $p) {
                $fid = (int) ($_POST['firma'] ?? 0);
                $f = 'ok';
                if ($tat === 'fi_reserv') { $f = PartnerRecherche::reservieren((int) $p['id'], $fid); } else { PartnerRecherche::freigeben((int) $p['id'], $fid); }
                if ($f === 'ok') {
                    $zurueck = array_filter(['fi_ort' => (string) ($_GET['fi_ort'] ?? ''), 'fi_branche' => (string) ($_GET['fi_branche'] ?? ''), 'fi_nz' => 1], static fn($v) => $v !== '');
                    header('Location: ' . $selbst($zurueck) . '#recherche', true, 303); exit;
                }
                $meldung = $f;
            } elseif ($tat === 'seite' && $p) {
                /* Selbst gestaltete Empfehlungsseite (26.09.2026): sofort live,
                   Vecom bekommt eine Meldung und kann in der Akte zurücksetzen. */
                $f = PartnerSeite::speichern((int) $p['id'], $_POST);
                $datei = $_FILES['titelbild'] ?? null;
                if ($f === 'ok' && is_array($datei) && ($datei['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $f = in_array($datei['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'bild_gross'
                       : ($datei['error'] === UPLOAD_ERR_OK && is_uploaded_file((string) $datei['tmp_name'])
                          ? PartnerSeite::bildSpeichern((int) $p['id'], (string) $datei['tmp_name'], (int) $datei['size']) : 'bild_art');
                }
                if ($f === 'ok') {
                    Events::melden('partner_seite', 'Partner hat seine Empfehlungsseite gestaltet: ' . $p['name'], 'info', null, '/partner/' . (int) $p['id']);
                    header('Location: ' . $selbst(['m' => 'g_gut']) . '#seite', true, 303); exit;
                }
                $meldung = $f;
            } elseif ($tat === 'seite_bild_weg' && $p) {
                PartnerSeite::bildLoeschen((int) $p['id']);
                header('Location: ' . $selbst(['m' => 'g_gut']) . '#seite', true, 303); exit;
            } elseif ($tat === 'seite_standard' && $p) {
                PartnerSeite::zuruecksetzen((int) $p['id']);
                header('Location: ' . $selbst(['m' => 'g_gut']) . '#seite', true, 303); exit;
            } elseif ($tat === 'foto_weg' && $p) {
                PartnerWerbung::fotoLoeschen((int) $p['id']);
                header('Location: ' . $selbst(['m' => 'pf_gut']) . '#profil', true, 303); exit;
            } elseif ($tat === 'konto' && $p && isset($_POST['land']) && Partner::landSetzen($p, (string) $_POST['land']) === 'bereit') {
                $meldung = 'konto_land_bereit';
            } elseif ($tat === 'konto' && $p) {
                $p = Partner::ausToken($token) ?? $p;   // Land eben gesetzt (oder altes Konto im falschen Land entfernt)
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
/* ---------- Mappe zum Vorbeibringen (27.09.2026): nur eigener Check oder eigene Reservierung ---------- */
if ($p && ($_GET['druck'] ?? '') === 'mappe') {
    $mappe = PartnerMappe::laden($p, $_GET, in_array((string) ($_GET['sp'] ?? ''), ['it', 'de', 'en'], true) ? (string) $_GET['sp'] : 'it');
    if ($mappe === null) { http_response_code(404); exit('—'); }
    header('X-Robots-Tag: noindex');
    require __DIR__ . '/app/views/partner_mappe.php';
    exit;
}
/* ---------- Druck-Paket: Visitenkarten, Flyer, Aufsteller, Aufkleber ---------- */
if ($p && in_array((string) ($_GET['druck'] ?? ''), ['visitenkarten', 'flyer', 'aufsteller', 'aufkleber'], true)) {
    header('X-Robots-Tag: noindex');
    require __DIR__ . '/app/views/partner_druck.php';
    exit;
}
/* ---------- Die Karte zum Ausdrucken (QR + Link), A6 ---------- */
if ($p && isset($_GET['karte'])) {
    $kLink = Partner::link($p) . '/karte';
    ?><!doctype html><html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>><head><meta charset="utf-8">
<?= Sprache::skript() ?><meta name="viewport" content="width=device-width, initial-scale=1">
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
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
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
  .stufe-balken{height:8px;border-radius:99px;background:rgba(255,255,255,.07);margin-top:10px;overflow:hidden;max-width:420px}
  .stufe-balken i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#b98a31,#f1d38b)}
  /* Posting-Kalender */
  .ka-heute{border:1px solid rgba(241,211,139,.35);border-radius:14px;padding:14px;background:linear-gradient(160deg,rgba(241,211,139,.07),rgba(241,211,139,0) 60%)}
  .ka-kopf{display:flex;flex-wrap:wrap;align-items:baseline;gap:6px 10px;margin-bottom:8px;font-size:15.5px}
  .ka-tag{font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--leise)}
  .ka-marke{font-size:11.5px;font-weight:700;letter-spacing:.04em;color:#16120b;background:#f1d38b;border-radius:99px;padding:2px 9px}
  .ka-heute textarea,.ka-tagfeld textarea{width:100%;box-sizing:border-box;font-size:14px;line-height:1.55;padding:10px 12px}
  .ka-bald{margin:12px 0 0;color:var(--dim)}
  .ka-woche{margin-top:12px}
  .ka-woche>summary,.ka-tagfeld>summary{cursor:pointer;color:var(--cyan);font-size:14px;padding:6px 0}
  .ka-tagfeld{border-top:1px solid var(--linie);padding:4px 0}
  .ka-tagfeld>summary{color:var(--text)}
  .ka-tagfeld .ka-tag{display:inline-block;min-width:74px}
  /* Monatsrangliste */
  .wb-liste{list-style:none;padding:0;margin:12px 0 0;display:grid;gap:6px}
  .wb-liste li{display:flex;align-items:center;gap:12px;border:1px solid var(--linie);border-radius:12px;padding:9px 12px;font-size:14.5px}
  .wb-liste li.ich{border-color:rgba(241,211,139,.55);background:rgba(241,211,139,.07)}
  .wb-liste li.wb-luecke{border:0;padding:0 12px;color:var(--leise)}
  .wb-rang{flex:0 0 28px;height:28px;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:13px;border:1px solid var(--linie2)}
  .wb-liste li:first-child .wb-rang{background:linear-gradient(115deg,#b98a31,#f7e6ae 45%,#c49438);color:#16120b;border:0}
  .wb-wer{flex:1;min-width:0;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .wb-zahl{color:var(--dim);font-size:13px;white-space:nowrap}
  .wb-leer{border:1px dashed var(--linie2);border-radius:12px;padding:12px 14px;color:var(--dim);font-size:14.5px}
  .wb-name{margin-top:14px}
  .wb-name label{display:flex;gap:10px;align-items:flex-start;color:var(--text);font-size:14px;line-height:1.45}
  .wb-name small{display:block;color:var(--leise);font-size:12.5px}
  @media (max-width:420px){.wb-liste li{flex-wrap:wrap}.wb-zahl{flex-basis:100%;padding-left:40px;margin-top:-4px}}
  .faq pre{white-space:pre-wrap;font-family:inherit;font-size:14px;line-height:1.65;color:var(--dim);margin:8px 0 0}
  .verlauf{display:flex;flex-direction:column;gap:8px;margin:4px 0 12px;max-height:420px;overflow-y:auto}
  .blase{max-width:86%;padding:9px 12px;border-radius:14px;font-size:14.5px;line-height:1.5;white-space:pre-wrap;word-break:break-word}
  .blase small{display:block;font-size:11.5px;color:var(--leise);margin-top:4px}
  .blase.ich{align-self:flex-end;background:rgba(241,211,139,.10);border:1px solid rgba(241,211,139,.28);border-bottom-right-radius:4px}
  .blase.wir{align-self:flex-start;background:var(--flaeche2,rgba(255,255,255,.04));border:1px solid var(--linie);border-bottom-left-radius:4px}
  .stripe-einrichtung{margin-top:14px;border:1px solid var(--linie);border-radius:14px;padding:18px;background:var(--flaeche);min-height:120px}
  .stripe-einrichtung .laedt{color:var(--dim);font-size:14px;margin:0}
  .konto-wie{margin:10px 0 14px;border:1px solid var(--linie);border-radius:12px;padding:10px 14px}
  .konto-wie summary{cursor:pointer;color:var(--cyan);font-size:14px}
  .konto-wie ol{margin:10px 0 0;padding-left:20px;display:grid;gap:6px;font-size:14px;color:var(--dim);line-height:1.5}
  .konto-land-l{display:block;font-size:13px;color:var(--dim);margin:0 0 6px}
  .konto-land{max-width:340px}
  .emp td small{display:block;color:var(--leise);font-size:12px}
  .emp .st{display:inline-block;padding:2px 9px;border-radius:999px;border:1px solid var(--linie);font-size:12.5px;white-space:nowrap}
  .emp .st.bezahlt,.emp .st.online{border-color:rgba(241,211,139,.5);color:var(--cyan)}
  .geld{border:1px solid rgba(241,211,139,.55);background:rgba(241,211,139,.07);border-radius:12px;padding:12px 14px;margin:0 0 14px;
        display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;font-size:14.5px}
  /* Erste Schritte (27.09.2026): alles sichtbar, nur der nächste offene hervorgehoben. */
  .es{border:1px solid var(--linie2);border-radius:14px;padding:14px;margin:0 0 14px}
  .es__kopf{display:flex;justify-content:space-between;align-items:baseline;gap:10px;margin-bottom:8px}
  .es__kopf b{font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--cyan)}
  .es__kopf span{font-size:13px;color:var(--leise)}
  .es__balken{height:6px;border-radius:99px;background:var(--linie);overflow:hidden;margin-bottom:10px}
  .es__balken i{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,#b98a31,#f7e6ae 60%,#c49438)}
  .es ol{list-style:none;margin:0;padding:0;display:grid;gap:4px}
  .es li{display:flex;gap:10px;align-items:center;padding:7px 8px;border-radius:10px;font-size:14px;line-height:1.4}
  .es li .haken{flex:0 0 22px;height:22px;border-radius:50%;border:1.5px solid var(--linie2);display:grid;place-items:center;font-size:12px}
  .es li.ok{color:var(--leise)}
  .es li.ok .haken{border-color:rgba(241,211,139,.6);color:var(--cyan)}
  .es li.ok .was{text-decoration:line-through;text-decoration-color:rgba(180,173,162,.45)}
  .es li.jetzt{background:rgba(241,211,139,.07);border:1px solid rgba(241,211,139,.35)}
  .es li .was{flex:1;min-width:0}
  .es li .was small{display:block;color:var(--leise);font-size:12.5px}
  .es li a.knopf{min-height:36px;padding:6px 14px}
  .es li a.leise{font-size:13px;color:var(--dim)}
  /* Wochenverlauf */
  .ws{margin-top:14px}
  .ws-svg{display:block;color:var(--text);max-width:480px}
  .ws-b{fill:rgba(241,211,139,.22)}
  .ws-k{fill:#e7c476}
  .ws-z{font-size:11px;fill:var(--dim)}
  .ws-v{font-size:11px;fill:#f1d38b;font-weight:700}
  .ws-d{font-size:10px;fill:var(--leise)}
  .ws-legende{display:flex;gap:14px;flex-wrap:wrap;font-size:12.5px;color:var(--leise);margin-top:4px}
  .tr-zeit{display:flex;gap:6px;flex-wrap:wrap;margin:6px 0 12px}
  .tr-zeit a{font-size:13px;padding:5px 11px;border:1px solid var(--linie2);border-radius:999px;color:var(--dim);text-decoration:none}
  .tr-zeit a[aria-current]{border-color:var(--cyan);color:var(--cyan)}
  .tr{display:grid;gap:8px}
  .tr-z{display:grid;grid-template-columns:96px 1fr auto;gap:10px;align-items:center;font-size:14px}
  .tr-z span{color:var(--dim)}
  .tr-z i{display:block;height:12px;border-radius:6px;background:linear-gradient(90deg,#b98a31,#f1d38b);min-width:0}
  .tr-z b{min-width:44px;text-align:right}
  .tr-wege{list-style:none;padding:0;margin:10px 0 0;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:6px 16px}
  .tr-wege li{display:flex;justify-content:space-between;font-size:13.5px;color:var(--dim);border-bottom:1px solid var(--linie);padding:4px 0}
  .tr-wege b{color:var(--text)}
  .ws-legende i{display:inline-block;width:10px;height:10px;border-radius:3px;margin-right:5px;vertical-align:-1px}
  /* Erfolge */
  .erfolg{border:1px solid var(--linie);border-radius:12px;padding:12px 14px;margin-top:10px}
  .erfolg.frei{border-color:rgba(241,211,139,.45)}
  .erfolg h3{font-size:15.5px;margin:0 0 4px}
  .erfolg textarea{width:100%;box-sizing:border-box;font-size:14px;line-height:1.5;padding:10px 12px;margin-top:8px}
  /* Schmale Handys: „auszahlungsbereit“ schob die Provisionstabelle 3 px über den Rand. */
  @media (max-width:520px){.pt .zahlen{grid-template-columns:repeat(2,1fr)}.pt table{font-size:13px}.pt td,.pt th{padding:8px 4px;word-break:break-word}}
  /* Reiter wie eine App (27.09.2026). Ohne Skript gibt es sie nicht -- dann steht alles untereinander. */
  .sr-nur{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
  .app-reiter{display:none}
  .mit-reitern .app-reiter{display:flex;position:sticky;top:8px;z-index:30;gap:3px;max-width:640px;margin:0 auto 18px;box-sizing:border-box;padding:5px;background:rgba(20,19,17,.88);-webkit-backdrop-filter:blur(12px);backdrop-filter:blur(12px);border:1px solid var(--linie);border-radius:16px;box-shadow:0 10px 30px -18px rgba(0,0,0,.8)}
  .app-reiter a{flex:1 1 auto;min-width:0;display:flex;align-items:center;justify-content:center;gap:7px;min-height:44px;padding:8px 6px;border-radius:11px;color:var(--dim);text-decoration:none;font-size:13.5px;font-weight:600;position:relative;transition:color .18s cubic-bezier(.16,1,.3,1),background-color .18s cubic-bezier(.16,1,.3,1);white-space:nowrap}
  .app-reiter a svg{width:19px;height:19px;flex:none;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
  .app-reiter a .k{display:none}
  .app-reiter a .l{overflow:hidden;text-overflow:ellipsis}
  .app-reiter a:hover{color:var(--text);background:rgba(255,255,255,.035)}
  .app-reiter a:focus-visible{outline:2px solid #f1d38b;outline-offset:1px}
  .app-reiter a[aria-current]{color:#f1d38b;background:rgba(241,211,139,.1);box-shadow:inset 0 0 0 1px rgba(241,211,139,.32)}
  .app-reiter a.punkt::after{content:'';position:absolute;top:7px;right:8px;width:7px;height:7px;border-radius:50%;background:#ff8a7a;box-shadow:0 0 0 2px #141311}
  .app-kopf{max-width:640px;margin:6px auto 14px;padding:0 4px;box-sizing:border-box}
  .app-kopf h2{font-size:clamp(23px,4.2vw,30px);line-height:1.15;margin:0 0 6px;letter-spacing:-.01em}
  .app-kopf h2:focus,.pt h1:focus{outline:none}
  .app-kopf p{margin:0;color:var(--dim);font-size:14.5px;line-height:1.5;max-width:56ch}
  .app-kopf[hidden],.mit-reitern [data-reiter][hidden]{display:none!important}
  @media (max-width:760px){
    .mit-reitern .app-reiter{position:fixed;top:auto;bottom:0;left:0;right:0;max-width:none;margin:0;gap:0;border-radius:18px 18px 0 0;border-width:1px 0 0;padding:5px 4px calc(5px + env(safe-area-inset-bottom));background:rgba(12,11,10,.95)}
    .app-reiter a{flex-direction:column;gap:3px;font-size:11.5px;font-weight:600;min-height:54px;padding:6px 2px;border-radius:12px}
    .app-reiter a svg{width:22px;height:22px}
    .app-reiter a .k{display:block;max-width:100%;overflow:hidden;text-overflow:ellipsis}
    .app-reiter a .l{display:none}
    .app-reiter a[aria-current]{box-shadow:none}
    .app-reiter a.punkt::after{right:calc(50% - 19px);top:6px}
    body.mit-reitern{padding-bottom:calc(78px + env(safe-area-inset-bottom))}
  }
  @media (prefers-reduced-motion:reduce){.app-reiter a{transition:none}}
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
        <pre><?= $h(strtr(PartnerVorlagen::text('faq', $sprache, $T('faq')), $platz)) ?></pre></details>
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
  <script type="application/json" id="reiter_daten"><?= json_encode([
      'aria' => Texte::h(Texte::PARTNER_REITER['aria'], $sprache),
      'neu' => ['it' => 'novità', 'de' => 'neu', 'en' => 'new'][$sprache] ?? 'new',
      'reihe' => array_keys(Texte::PARTNER_REITER['reiter']),
      'reiter' => array_map(static fn(array $r) => array_map(static fn(array $t) => Texte::h($t, $sprache), $r), Texte::PARTNER_REITER['reiter']),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <script src="/assets/js/partner-reiter.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/partner-reiter.js') ?>" defer></script>
  <div class="block pt" id="start" data-reiter="start">
    <h1><?= $h($T('p_titel')) ?></h1>
    <p class="lead"><?= $h($p['name']) ?> · <?= $h($bedingungen) ?></p>
    <?php $wegFehler = in_array($meldung, ['iban_falsch', 'inhaber_fehlt', 'email_falsch', 'konto_fehler', 'konto_land_bereit'], true); ?>
    <?php if ($meldung !== '' && !$wegFehler && !str_starts_with($meldung, 'fe_')): ?><div class="hinweis schlecht"><?= $h($T($meldung)) ?></div><?php endif; ?>
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
    <?php $es = PartnerStart::schritte($p); $ST = static fn(array $t): string => Texte::h($t, $sprache); ?>
    <?php if ($bereitCents <= 0 && $es['naechster'] === null): ?><div class="naechst"><b><?= $h($T('n_titel')) ?></b><?= $h($T($naechst)) ?></div><?php endif; ?>
    <?php if ($es['naechster'] !== null): /* Erste Schritte (27.09.2026): verschwindet, sobald alles erledigt ist. */ ?>
      <div class="es" role="region" aria-labelledby="es_titel">
        <div class="es__kopf"><b id="es_titel"><?= $h($ST(Texte::PARTNER_START['s_titel'])) ?></b>
          <span><?= $h(strtr($ST(Texte::PARTNER_START['s_stand']), ['{n}' => (string) $es['n'], '{alle}' => (string) $es['alle']])) ?></span></div>
        <div class="es__balken" aria-hidden="true"><i style="width:<?= (int) round(100 * $es['n'] / $es['alle']) ?>%"></i></div>
        <ol>
          <?php foreach (PartnerStart::SCHRITTE as $sk): $ok = $es['erledigt'][$sk]; $jetzt = $sk === $es['naechster']; $sd = Texte::PARTNER_START['schritte'][$sk]; ?>
            <li class="<?= $ok ? 'ok' : ($jetzt ? 'jetzt' : '') ?>">
              <span class="haken" aria-hidden="true"><?= $ok ? '✓' : '' ?></span>
              <span class="was"><?= $h($ST($sd[0])) ?><?php if (!$ok): ?><small><?= $h($ST($sd[1])) ?></small><?php endif; ?>
                <span class="wabe"><?= $ok ? '✓' : '' ?></span></span>
              <?php if (!$ok): ?><a class="<?= $jetzt ? ($bereitCents > 0 ? 'knopf' : 'knopf haupt') : 'leise' ?>" href="#<?= PartnerStart::ANKER[$sk] ?>"><?= $h($ST(Texte::PARTNER_START[$jetzt ? 's_jetzt' : 's_los'])) ?></a><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    <?php endif; ?>
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
    <?php /* Alles teilbar (27.09.2026): WhatsApp, E-Mail, das Teilen-Menü des Handys, ansehen. */ ?>
    <div class="knoepfe">
      <a class="knopf" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($T('teilen_text') . $link) ?>"><?= $h($T('teilen_wa')) ?></a>
      <a class="knopf" href="mailto:?subject=<?= rawurlencode($T('teilen_betreff')) ?>&amp;body=<?= rawurlencode($T('teilen_text') . $link) ?>"><?= $h($T('teilen_mail')) ?></a>
      <button class="knopf" type="button" data-teilen-text="<?= $h($T('teilen_text') . $link) ?>" hidden><?= $h($T('teilen_mehr')) ?></button>
      <a class="knopf" target="_blank" rel="noopener" href="<?= $h('/p.php?' . http_build_query(['c' => $p['code'], 'lang' => $sprache, 'n' => 1])) ?>"><?= $h($T('teilen_ansehen')) ?></a>
    </div>
    <details style="margin-top:14px"<?= $naechst === 'n_teilen' ? ' open' : '' ?>><summary style="cursor:pointer;color:var(--cyan);font-size:14px"><?= $h($T('so_titel')) ?></summary>
      <?= $so() ?>
      <details class="faq" style="margin-top:10px"><summary style="cursor:pointer;color:var(--cyan);font-size:13.5px"><?= $h($T('faq_titel')) ?></summary>
        <pre><?= $h(strtr(PartnerVorlagen::text('faq', $sprache, $T('faq')), $platz)) ?></pre></details>
    </details>
  </div>

  <div class="block pt" id="zahlen" data-reiter="start">
    <div class="zahlen">
      <div class="zahl"><b><?= (int) $k['klicks'] ?></b><span><?= $h($T('klicks')) ?></span><small><?= $h($T('z_klicks')) ?></small></div>
      <div class="zahl"><b><?= (int) $k['kunden'] ?></b><span><?= $h($T('kunden')) ?></span><small><?= $h($T('z_kunden')) ?></small></div>
      <div class="zahl"><b><?= (int) $k['verkaeufe'] ?></b><span><?= $h($T('verkaeufe')) ?></span><small><?= $h($T('z_verkaeufe')) ?></small></div>
      <div class="zahl"><b><?= $h(Fmt::geld((int) $k['provision'])) ?></b><span><?= $h($T('provision')) ?></span><small><?= $h($T('z_provision')) ?></small></div>
    </div>
    <?php $wo = PartnerStart::wochen((int) $p['id']); $PSt = Texte::PARTNER_START; ?>
    <div class="ws">
      <h2 style="margin-bottom:6px"><?= $h($ST($PSt['w_titel'])) ?></h2>
      <?php if (array_sum(array_column($wo, 'besuche')) + array_sum(array_column($wo, 'kunden')) === 0): ?>
        <p class="klein" style="margin-top:0"><?= $h($ST($PSt['w_leer'])) ?></p>
      <?php else: ?>
        <?= PartnerStart::svg($wo, $ST($PSt['w_aria'])) ?>
        <div class="ws-legende"><span><i style="background:rgba(241,211,139,.3)"></i><?= $h($ST($PSt['w_besuche'])) ?></span>
          <span><i style="background:#e7c476"></i><?= $h($ST($PSt['w_kunden'])) ?></span><span style="color:#f1d38b">★ <?= $h($ST($PSt['w_verkaeufe'])) ?></span></div>
        <details style="margin-top:8px"><summary style="cursor:pointer;color:var(--cyan);font-size:13.5px"><?= $h($ST($PSt['w_zahlen'])) ?></summary>
          <table><thead><tr><th><?= $h($ST($PSt['w_woche'])) ?></th><th class="r"><?= $h($ST($PSt['w_besuche'])) ?></th><th class="r"><?= $h($ST($PSt['w_kunden'])) ?></th><th class="r"><?= $h($ST($PSt['w_verkaeufe'])) ?></th></tr></thead><tbody>
          <?php foreach (array_reverse($wo) as $w): ?><tr><td><?= $h(Fmt::datum($w['montag'])) ?></td><td class="r"><?= $w['besuche'] ?></td><td class="r"><?= $w['kunden'] ?></td><td class="r"><?= $w['verkaeufe'] ?></td></tr><?php endforeach; ?>
          </tbody></table></details>
      <?php endif; ?>
    </div>
    <?php /* Trichter (27.09.2026, Uwe: Ja): was auf der Seite passiert, echte Besucher, mit Zeitraum. */
      $zr = in_array((int) ($_GET['zr'] ?? 30), [7, 30, 90], true) ? (int) ($_GET['zr'] ?? 30) : 30;
      $tr = Partner::trichter((int) $p['id'], $zr); $TP = Texte::PARTNER_SEITE; $TW = static fn(array $t): string => Texte::h($t, $sprache);
      $trMax = max(1, $tr['besuche'], $tr['anfragen']); ?>
    <div class="ws" id="trichter">
      <h2 style="margin-bottom:4px"><?= $h($TW($TP['t_titel'])) ?></h2>
      <p class="klein" style="margin-top:0"><?= $h($TW($TP['t_text'])) ?></p>
      <div class="tr-zeit" role="group">
        <?php foreach ([7, 30, 90] as $n): ?><a href="<?= $h($selbst(['zr' => $n]) . '#trichter') ?>"<?= $n === $zr ? ' aria-current="true"' : '' ?>><?= $h(strtr($TW($TP['t_zeit']), ['{n}' => (string) $n])) ?></a><?php endforeach; ?>
      </div>
      <div class="tr">
        <?php foreach ([['t_besuche', $tr['besuche']], ['t_anfragen', $tr['anfragen']], ['t_kunden', $tr['kunden']], ['t_verkaeufe', $tr['verkaeufe']]] as [$tk, $tn]): ?>
          <div class="tr-z"><span><?= $h($TW($TP[$tk])) ?></span><i style="width:<?= max(2, (int) round(100 * $tn / $trMax)) ?>%"></i><b><?= (int) $tn ?></b></div>
        <?php endforeach; ?>
        <div class="tr-z"><span><?= $h($TW($TP['t_provision'])) ?></span><i style="width:0"></i><b><?= $h(Fmt::geld((int) $tr['provision'])) ?></b></div>
      </div>
      <?php if ($tr['besuche'] > 0): ?><p class="klein"><?= $h(strtr($TW($TP['t_quote']), ['{p}' => (string) round(100 * $tr['anfragen'] / $tr['besuche'])])) ?></p><?php endif; ?>
      <ul class="tr-wege">
        <?php foreach ($tr['wege'] as $wk => $wn): ?><li><span><?= $h($TW($TP['t_wege'][$wk])) ?></span><b><?= (int) $wn ?></b></li><?php endforeach; ?>
      </ul>
    </div>
    <?php $stand = Partner::stufeStand($p); if ($stand['stufe'] !== null): ?>
      <?php $MKs = static fn(string $k): string => Texte::h(Texte::PARTNER_MARKETING[$k] ?? [], $sprache); ?>
      <div class="stufe">★ <?= $h(strtr($T('st_text'), ['{stufe}' => $T('st_' . $stand['stufe']), '{satz}' => Partner::satzWort(Partner::satzFuer($p), true), '{n}' => (string) $stand['verkaeufe']])) ?></div>
      <?php if ($stand['naechste'] !== null): /* Fortschritt bis zur nächsten Stufe (27.09.2026) */ ?>
        <div class="stufe-balken" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $stand['anteil'] ?>"
             aria-label="<?= $h(strtr($MKs('st_balken'), ['{naechste}' => $T('st_' . $stand['naechste'])])) ?>"><i style="width:<?= max(3, (int) $stand['anteil']) ?>%"></i></div>
      <?php endif; ?>
      <p class="klein" style="margin-top:6px"><?= $h($stand['naechste'] !== null
          ? strtr($stand['naechster_satz'] !== null ? $MKs('st_naechst2') : $T('st_naechst'), ['{fehlen}' => (string) $stand['fehlen'], '{naechste}' => $T('st_' . $stand['naechste']),
                  '{satz}' => $stand['naechster_satz'] !== null ? Partner::satzWort($stand['naechster_satz'], true) : ''])
          : $T('st_top')) ?></p>
    <?php endif; ?>
    <p class="klein">
      <?php foreach (['wartet', 'freigabe', 'bereit', 'unterwegs', 'ausgezahlt'] as $st): if ($sum[$st] > 0): ?>
        <?= $h($T('s_' . $st)) ?>: <b><?= $h(Fmt::geld($sum[$st])) ?></b> &nbsp;
      <?php endif; endforeach; ?>
    </p>
  </div>

  <?php require __DIR__ . '/app/views/partner_wettbewerb.php'; ?>

  <?php $emp = PartnerPost::empfehlungen((int) $p['id']); if ($emp): ?>
  <div class="block pt" id="empfehlungen" data-reiter="start">
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
  <div class="block pt" id="wege" data-reiter="geld"<?= $bereitCents > 0 || $weg === null || !PartnerWege::bereit($p, $weg) ? ' data-punkt="1"' : '' ?>>
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
        <?php /* Stand der Prüfung und das Land (28.09.2026, Uwe: „wie verifizieren sie, damit sie Auszahlungen bekommen“). */
          $kStand = ['stand' => 'neu', 'fehlt' => 0, 'land' => null];
          try { $kStand = Partner::kontoStand($p); if ($kStand['stand'] === 'bereit') { $p = Partner::ausToken($token) ?? $p; } } catch (Throwable $e) { }
          $kLand = Partner::landFuer($p); $TL = Texte::PARTNER['laender']; ?>
        <?php if ($kStand['stand'] === 'pruefung'): ?><div class="hinweis" role="status"><?= $h($T('konto_st_pruefung')) ?></div>
        <?php elseif ($kStand['stand'] === 'fehlt'): ?><div class="hinweis" role="status"><?= $h(strtr($T('konto_st_fehlt'), ['{n}' => (string) $kStand['fehlt']])) ?></div><?php endif; ?>
        <details class="konto-wie"<?= $kStand['stand'] === 'neu' ? ' open' : '' ?>><summary><?= $h($T('konto_wie_titel')) ?></summary>
          <ol><?php foreach (Texte::PARTNER['konto_wie'] as $kw): ?><li><?= $h(Texte::h($kw, $sprache)) ?></li><?php endforeach; ?></ol></details>
        <?php /* Mit öffentlichem Schlüssel öffnet das Skript die Einrichtung hier
                 auf der Seite, in der Sprache des Partners. Ohne Schlüssel, ohne
                 Skript oder wenn Stripe nicht lädt, schickt dasselbe Formular wie
                 bisher auf die gehostete Stripe-Seite. */
          $stripePk = Partner::stripeOeffentlich(); ?>
        <form method="post" action="<?= $h($selbst()) ?>" id="stripe-form"<?php if ($stripePk !== ''): ?>
              data-pk="<?= $h($stripePk) ?>" data-sprache="<?= $h(Partner::STRIPE_SPRACHE[$sprache] ?? 'en-GB') ?>"
              data-zurueck="<?= $h($selbst(['stripe' => 'zurueck'])) ?>#wege" data-laden="<?= $h($T('konto_laden')) ?>"<?php endif; ?>>
          <input type="hidden" name="_csrf" value="<?= $h($_SESSION['csrf']) ?>">
          <input type="hidden" name="tat" value="konto">
          <label for="konto_land" class="konto-land-l"><?= $h($T('konto_land')) ?></label>
          <select id="konto_land" name="land" class="konto-land"><?php foreach (Partner::STRIPE_LAENDER as $lc): ?><option value="<?= $h($lc) ?>"<?= $lc === ($kStand['land'] ?? $kLand) ? ' selected' : '' ?>><?= $h(Texte::h($TL[$lc], $sprache)) ?></option><?php endforeach; ?></select>
          <p class="klein" style="margin:4px 0 10px"><?= $h($T('konto_land_hilfe')) ?></p>
          <button class="knopf"><?= $h($T(empty($p['stripe_konto']) ? 'konto_knopf' : 'konto_weiter')) ?></button>
        </form>
        <div id="stripe-einrichtung" class="stripe-einrichtung" hidden></div>
        <p class="klein"><?= $linkMd($T(($kStand['land'] ?? $kLand) === Partner::STRIPE_LAND_PLATTFORM ? 'stripe_agb' : 'stripe_agb_voll')) ?></p>
        <?php if ($stripePk !== ''): ?><script src="/assets/js/partner-stripe.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/partner-stripe.js') ?>" defer></script><?php endif; ?>
      <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php require __DIR__ . '/app/views/partner_kalender.php'; ?>

  <?php require __DIR__ . '/app/views/partner_werbung.php'; ?>

  <?php $erfolge = PartnerErfolg::liste((int) $p['id']); if ($erfolge || $kacheln): $PE = Texte::PARTNER_ERFOLG; $MKe = static fn(string $k): string => Texte::h(Texte::PARTNER_MARKETING[$k] ?? [], $sprache); ?>
  <div class="block pt" id="erfolge" data-reiter="werben">
    <h2><?= $h($ST($PE['e_titel'])) ?></h2>
    <?php if ($erfolge): ?><p class="klein" style="margin-top:0"><?= $h($ST($PE['e_text'])) ?></p><?php endif; ?>
    <?php foreach ($erfolge as $i => $e): $datum = $e['seit'] !== '' ? Fmt::datum($e['seit']) : ''; ?>
      <?php if (!$e['zeigen']): ?>
        <div class="erfolg"><p class="klein" style="margin:0"><?= $h(strtr($ST($PE['e_wartet']), ['{datum}' => $datum])) ?></p></div>
      <?php else: $post = PartnerErfolg::beitrag($p, $e['firma'], $e['url'], $sprache); ?>
        <div class="erfolg frei">
          <h3><?= $h($e['firma']) ?> <small style="color:var(--leise);font-weight:400;font-size:12.5px">· <?= $h(strtr($ST($PE['e_seit']), ['{datum}' => $datum])) ?></small></h3>
          <textarea id="erfolg_<?= $i ?>" readonly rows="5" data-wachsen><?= $h($post) ?></textarea>
          <div class="knoepfe">
            <button class="knopf haupt" type="button" data-kopie="erfolg_<?= $i ?>"><?= $h($ST($PE['e_kopieren'])) ?></button>
            <a class="knopf" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($post) ?>"><?= $h($ST($PE['e_wa'])) ?></a>
            <button class="knopf" type="button" data-teilen="erfolg_<?= $i ?>" hidden><?= $h($ST($PE['e_teilen'])) ?></button>
            <?php if ($e['url'] !== ''): ?><a class="knopf" target="_blank" rel="noopener" href="<?= $h($e['url']) ?>"><?= $h($ST($PE['e_ansehen'])) ?></a><?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($kacheln): /* Erfolge als Social-Kacheln (27.09.2026) -- gezeichnet von partner-medien.js */ ?>
      <h3 class="md-h" style="margin-top:<?= $erfolge ? '22px' : '4px' ?>"><?= $h($MKe('kc_titel')) ?></h3>
      <p class="klein" style="margin-top:0"><?= $h($MKe('kc_text')) ?></p>
      <div class="chips">
        <?php foreach ($kacheln as $ki => $kk): ?>
          <button type="button" data-kachel="<?= $ki ?>" aria-pressed="<?= $ki === 0 ? 'true' : 'false' ?>"><?= $h(($kk['art'] === 'stimme' ? $kk['marke'] . ': ' : '') . $kk['name']) ?></button>
        <?php endforeach; ?>
      </div>
      <div class="md-buehne"><canvas id="kachel_vorschau" width="1080" height="1080" role="img" aria-label="<?= $h($MKe('kc_titel')) ?>"></canvas></div>
      <div class="knoepfe">
        <button class="knopf haupt" type="button" id="kachel_laden"><?= $h($MKe('kc_laden')) ?></button>
        <button class="knopf" type="button" id="kachel_teilen" hidden><?= $h($MKe('kc_teilen')) ?></button>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php require __DIR__ . '/app/views/partner_seite.php'; ?>

  <?php $checkNeu = null;
    if (preg_match('/^[0-9a-f]{32}$/', (string) ($_GET['ck'] ?? ''))) {
        $ckZ = Db::one('SELECT token, ergebnis FROM partner_checks WHERE token = ? AND partner_id = ?', [(string) $_GET['ck'], (int) $p['id']]);
        if ($ckZ) { $checkNeu = ['token' => (string) $ckZ['token'], 'ergebnis' => json_decode((string) $ckZ['ergebnis'], true) ?: ['host' => '', 'punkte' => []]]; }
    }
    require __DIR__ . '/app/views/partner_recherche.php'; ?>

  <?php $neuNachr = PartnerPost::gelesen((int) $p['id'], 'partner'); $verlauf = PartnerPost::verlauf((int) $p['id']); ?>
  <div class="block pt" id="nachrichten" data-reiter="profil"<?= $neuNachr > 0 ? ' data-punkt="1"' : '' ?>>
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

  <div class="block pt" id="melden" data-reiter="finden">
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

  <div class="block pt" id="provisionen" data-reiter="geld">
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
  <div class="block pt" id="auszahlungen" data-reiter="geld">
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
  <div class="block pt" id="sofort" data-reiter="geld">
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

  <div class="block pt" id="app" data-reiter="profil">
    <h2><?= $h($T('app_titel')) ?></h2>
    <p class="klein" style="margin-top:0"><?= $h($T('app_text')) ?></p>
    <div class="knoepfe">
      <button class="knopf haupt" type="button" id="push_an" hidden><?= $h($T('app_an')) ?></button>
      <button class="knopf" type="button" id="installieren" hidden><?= $h($T('app_installieren')) ?></button>
    </div>
    <p class="klein" id="app_hilfe" style="margin-top:10px"></p>
    <?php if ($p): /* Chrome-Absprung: dieselbe persönliche Adresse, die schon in der Adresszeile steht. */ ?>
    <p class="klein" id="app_chrome" hidden><a href="<?= $h('intent://' . preg_replace('~^https?://~', '', $basis) . $selbst() . '#Intent;scheme=https;package=com.android.chrome;end') ?>"><?= $h($T('app_chrome')) ?></a></p>
    <?php endif; ?>
    <p class="klein" id="push_stand" role="status"></p>
  </div>
  <script>
  /* Handy-App (26.09.2026): Service Worker nur fuer Hinweise, kein Seiten-Cache. */
  (function () {
    var knopf = document.getElementById('push_an'), stand = document.getElementById('push_stand'), inst = document.getElementById('installieren');
    var W = { an: <?= json_encode($T('app_ist_an')) ?>, nein: <?= json_encode($T('app_nein')) ?>, verboten: <?= json_encode($T('app_verboten')) ?> };
    var schluessel = <?= json_encode(PartnerPost::vapid()) ?>, csrf = <?= json_encode($_SESSION['csrf']) ?>, ziel = <?= json_encode($selbst()) ?>;
    /* INSTALLIEREN (26.09.2026, Uwe: „wird nicht als App auf dem Handy hinterlegt“)
       Nur Chrome/Edge/Samsung auf Android bieten den Knopf an (beforeinstallprompt).
       Safari auf dem iPhone kennt ihn nicht -- dort geht es nur über „Teilen →
       Zum Home-Bildschirm“, und genau das steht dann hier, statt eines Knopfes,
       der nie erscheint. Als App geöffnet: kurz bestätigen, nichts anbieten. */
    var hilfe = document.getElementById('app_hilfe'), wartend = null;
    var HW = { ios: <?= json_encode($T('app_ios')) ?>, android: <?= json_encode($T('app_android')) ?>, samsung: <?= json_encode($T('app_samsung')) ?>, firefox: <?= json_encode($T('app_firefox')) ?>, andere: <?= json_encode($T('app_andere')) ?>, fertig: <?= json_encode($T('app_fertig')) ?>, laeuft: <?= json_encode($T('app_laeuft')) ?> };
    /* ANDERE ANDROID-BROWSER (26.09.2026, Uwe: „Android, anderer Browser“)
       Samsung Internet und Firefox haben das Menü an anderer Stelle und nennen
       den Eintrag anders -- die Chrome-Anleitung („⋮ → App installieren“) führt
       dort ins Leere. Browser, die nur ein Lesezeichen anlegen können (Mi,
       Opera Mini, In-App-Browser von Mail/WhatsApp), bekommen zusätzlich den
       Absprung nach Chrome, das auf jedem Android-Handy vorinstalliert ist. */
    var ua = navigator.userAgent, alsApp = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    var ios = /iPhone|iPad|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var android = /Android/.test(ua), chrome = document.getElementById('app_chrome');
    if (alsApp) { hilfe.textContent = HW.laeuft; }
    else if (ios) { hilfe.textContent = HW.ios; }
    else if (/SamsungBrowser/.test(ua)) { hilfe.textContent = HW.samsung; }
    else if (android && /Firefox\//.test(ua)) { hilfe.textContent = HW.firefox; }
    else if (android && (/; wv\)|MiuiBrowser|XiaoMi|OPR\/|Opera|FBAN|FBAV|Instagram|Line\//.test(ua) || !/Chrome\//.test(ua))) {
      hilfe.textContent = HW.andere; if (chrome) { chrome.hidden = false; }
    }
    else { hilfe.textContent = HW.android; }
    window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); wartend = e; inst.hidden = false; });
    window.addEventListener('appinstalled', function () { inst.hidden = true; hilfe.textContent = HW.fertig; });
    inst.addEventListener('click', function () {
      if (!wartend) { return; }
      wartend.prompt();
      wartend.userChoice.then(function (w) { if (w && w.outcome === 'accepted') { hilfe.textContent = HW.fertig; } }).catch(function () {});
      wartend = null; inst.hidden = true;
    });
    /* Der Service Worker wird immer registriert -- auch ohne Push. Manche
       Browser machen erst damit aus „Zum Startbildschirm“ eine echte App. */
    if (!('serviceWorker' in navigator)) { stand.textContent = W.nein; return; }
    if (!('PushManager' in window) || !schluessel) {
      navigator.serviceWorker.register('/partner-sw.js', { scope: '/partner.php' }).catch(function () {});
      stand.textContent = W.nein; return;
    }
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
  <script src="/assets/js/partner-medien.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/partner-medien.js') ?>" defer></script>
<?php endif; ?>

  <div class="sprachen">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
      <a class="<?= $l === $sprache ? 'jetzt' : '' ?>" href="<?= $h('/partner.php?' . http_build_query(array_merge($p ? ['t' => $p['token']] : [], ['lang' => $l]))) ?>"><?= $h($wie) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<script>
/* Teilen-Menü des Handys für jeden Knopf mit data-teilen-text (27.09.2026).
   Über die ganze Seite delegiert -- die Knöpfe stehen in verschiedenen Blöcken.
   Ohne navigator.share bleiben sie versteckt; WhatsApp, E-Mail und Kopieren gehen immer. */
(function () {
  if (!navigator.share) { return; }
  [].forEach.call(document.querySelectorAll('[data-teilen-text]'), function (b) { b.hidden = false; });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-teilen-text]'); if (!b) { return; }
    navigator.share({ text: b.dataset.teilenText }).catch(function () {});
  });
})();
</script>
<?php require_once __DIR__ . '/app/src/Fuss.php'; echo Fuss::html($sprache); ?>
</body>
</html>
