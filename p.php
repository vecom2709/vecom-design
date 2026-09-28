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
$p = null; $sprache = 'it'; $zaehlen = false;
if (is_file($konfig)) {
    try {
        foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'Texte', 'Sprache', 'Partner', 'PartnerWerbung', 'PartnerSeite', 'PartnerMarketing'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
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
        /* Die Sprachnachricht des Partners (28.09.2026, Uwe: Ja zu R6). Mit
           Bereichsabfragen (206): Safari spielt Ton sonst gar nicht ab. */
        if (isset($_GET['gruss'])) {
            $gr = Db::one("SELECT seite_gruss, seite_gruss_typ FROM partner WHERE code = ? AND status = 'aktiv' AND seite_gruss IS NOT NULL", [strtoupper((string) $_GET['gruss'])]);
            $daten = is_array($gr) ? (string) $gr['seite_gruss'] : '';
            if ($daten === '' || !in_array((string) $gr['seite_gruss_typ'], PartnerSeite::GRUSS_TYPEN, true)) { http_response_code(404); exit; }
            $n = strlen($daten); $von = 0; $bis = $n - 1;
            header('Content-Type: ' . $gr['seite_gruss_typ']);
            header('Accept-Ranges: bytes');
            header('Cache-Control: public, max-age=31536000, immutable');
            header('X-Content-Type-Options: nosniff');
            if (preg_match('~^bytes=(\d*)-(\d*)$~', (string) ($_SERVER['HTTP_RANGE'] ?? ''), $rg) && ($rg[1] !== '' || $rg[2] !== '')) {
                if ($rg[1] === '') { $von = max(0, $n - (int) $rg[2]); } else { $von = (int) $rg[1]; $bis = $rg[2] !== '' ? min((int) $rg[2], $n - 1) : $n - 1; }
                if ($von > $bis || $von >= $n) { http_response_code(416); header('Content-Range: bytes */' . $n); exit; }
                http_response_code(206);
                header('Content-Range: bytes ' . $von . '-' . $bis . '/' . $n);
            }
            header('Content-Length: ' . ($bis - $von + 1));
            echo substr($daten, $von, $bis - $von + 1); exit;
        }
        /* Das Vorschaubild für geteilte Links (27.09.2026, Uwe: Ja). Kein
           Klick, kein Keks -- WhatsApp & Co. holen es beim Einfügen des Links. */
        if (isset($_GET['og'])) {
            $op = Partner::ausCode((string) $_GET['og']);
            $ol = in_array($_GET['lang'] ?? '', Sprache::ALLE, true) ? (string) $_GET['lang'] : 'it';
            $og = $op !== null ? PartnerSeite::gestaltung($op) : null;
            $jpg = $op !== null ? PartnerSeite::ogBild($op, $og, $ol,
                PartnerSeite::text($og, $ol, 'titel', strtr(Texte::h(Texte::PARTNER_LANDE['titel'], $ol), ['{name}' => Partner::anzeigeName($op)])),
                strtr(Texte::h(Texte::PARTNER_LANDE['marke'], $ol), ['{name}' => Partner::anzeigeName($op)])) : null;
            if ($jpg === null) { http_response_code(404); exit; }
            header('Content-Type: image/jpeg');
            header('Cache-Control: public, max-age=604800');
            header('X-Content-Type-Options: nosniff');
            echo $jpg; exit;
        }
        $p = Partner::ausCode((string) ($_GET['c'] ?? ''));
        /* SPRACHE (27.09.2026, Uwe: Ja zu „Sprache automatisch“): gewählt
           (?lang, Keks) geht vor; sonst die Sprache des Browsers; sonst die
           des Partners -- statt immer Italienisch. */
        $sprache = Sprache::ausAnfrage(Sprache::ausBrowser(), $p['sprache'] ?? null);
        $ziel = $sprache === 'it' ? '/' : '/' . $sprache . '/';
        if ($p !== null) {
            $kanal = Partner::kanal((string) ($_GET['k'] ?? ''));
            /* ECHTE BESUCHER (27.09.2026): Vorschau-Programme, der Partner
               selbst und derselbe Browser im selben Besuch zählen nicht.
               Der Sprachwechsel und die Vorschau im Gestalter (n=1) nie. */
            /* Der Check-Knopf auf fremden Websites (W2) ist ein echter Besuch: gezählt, dann weiter zur Analyse. */
            $zaehlen = !isset($_GET['n']) && (!isset($_GET['weg']) || $_GET['weg'] === 'analisi')
                && Partner::echterBesuch($p, (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), $_COOKIE);
            if ($zaehlen) { Partner::klick((int) $p['id'], $kanal); }
            setcookie(Partner::KEKS, (string) $p['code'] . ($kanal !== null ? ':' . $kanal : ''), [
                'path' => '/', 'secure' => ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off',
                'httponly' => true, 'samesite' => 'Lax',
            ]);
            $_COOKIE[Partner::KEKS] = (string) $p['code'] . ($kanal !== null ? ':' . $kanal : '');
            /* DIE WEGE (27.09.2026): Website-Check, Preis, Termin und der
               WhatsApp-Knopf laufen über diese Adresse -- gezählt wird der
               Preis-Aufruf und das Öffnen von WhatsApp hier, Check und
               Termin erst, wenn sie wirklich gemacht sind (dort). */
            $weg = (string) ($_GET['weg'] ?? '');
            if ($weg !== '') {
                if ($weg === 'wa') {
                    $gw = PartnerSeite::gestaltung($p);
                    if ($gw['bausteine']['whatsapp'] && $gw['whatsapp'] !== '') {
                        if (!Partner::istRoboter((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) { Partner::ereignis((int) $p['id'], 'wa'); }
                        header('Cache-Control: no-store');
                        header('Location: https://wa.me/' . ltrim($gw['whatsapp'], '+') . '?text='
                            . rawurlencode(strtr(Texte::h(Texte::PARTNER_SEITE['wa_text'], $sprache), ['{name}' => Partner::anzeigeName($p)])), true, 302);
                        exit;
                    }
                } elseif (($wz = PartnerSeite::wegZiel($weg, $sprache, ['slot' => (string) ($_GET['slot'] ?? ''), 'url' => (string) ($_GET['url'] ?? '')])) !== null) {
                    if ($weg === 'preis' && !Partner::istRoboter((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) { Partner::ereignis((int) $p['id'], 'preis'); }
                    header('Cache-Control: no-store');
                    header('Location: ' . $wz, true, 302); exit;
                }
            }
        }
    } catch (Throwable $e) { $p = null; }
}
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
/* Sicherheit (27.09.2026, Uwe: Ja): wie Website-Check und Termin. Skript nur
   aus eigener Datei; eingebettet werden darf die Seite nur bei uns selbst
   (Vorschau im Partnerbereich). */
header("Content-Security-Policy: default-src 'none'; img-src 'self'; media-src 'self'; style-src 'self' 'unsafe-inline'; font-src 'self'; script-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'self'");
if ($p === null) { header('Location: ' . $ziel, true, 302); exit; }

$g = PartnerSeite::gestaltung($p);
$hier = static fn(array $extra = []): string => '/p.php?' . http_build_query(array_filter(['c' => $p['code'], 'k' => $_GET['k'] ?? null, 'lang' => $sprache, 'n' => 1] + $extra));
/* Rückrufwunsch (27.09.2026): danach zurück auf dieselbe Seite (n=1: kein neuer Klick). */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['tat'] ?? '') === 'rueckruf' && $g['bausteine']['rueckruf']) {
    $rr = 'rr_falle';
    try {
        foreach (['Akquise', 'PartnerRecherche', 'PartnerPost', 'WebPush', 'PartnerRueckruf'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
        $rr = PartnerRueckruf::anlegen($p, $_POST, $sprache);
    } catch (Throwable $e) { $rr = 'rr_falle'; }
    header('Location: ' . $hier(['rr' => $rr]) . '#rueckruf', true, 303); exit;
}
/* Kurz-Check (28.09.2026, Uwe: Ja zu R7): Ergebnis gleich auf derselben Seite, nichts gespeichert. */
$kc = null; $kcUrl = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['tat'] ?? '') === 'kurzcheck' && $g['bausteine']['kurzcheck']) {
    $kcUrl = mb_substr(trim((string) ($_POST['url'] ?? '')), 0, 200);
    try { $kc = PartnerSeite::kurzcheck($kcUrl); } catch (Throwable $e) { $kc = ['ok' => false, 'grund' => 'adresse']; }
}
/* Ja in einem Schritt (28.09.2026, Uwe: Ja zu Z2): unter der Ampel E-Mail + Häkchen → nur die Bestätigungsmail. */
$kcJa = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['tat'] ?? '') === 'kurzcheck_ja' && $g['bausteine']['kurzcheck']) {
    $kcUrl = mb_substr(trim((string) ($_POST['url'] ?? '')), 0, 200);
    try {
        foreach (['Akquise', 'AkquiseGate', 'AkquiseText', 'AkquiseCheck', 'AkquiseEinwilligung', 'AkquiseKurz'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
        if (trim((string) ($_POST['homepage'] ?? '')) !== '' || !AkquiseCheck::stempelGut((string) ($_POST['z'] ?? ''))) {
            $kcJa = 'zeit';
        } else {
            $kcJa = AkquiseKurz::einwilligen(['url' => $kcUrl, 'email' => (string) ($_POST['email'] ?? ''), 'whatsapp' => !empty($_POST['wa']) ? (string) ($_POST['whatsapp'] ?? '') : null,
                'ja' => !empty($_POST['ja']), 'sprache' => $sprache, 'quelle' => 'partner', 'partner' => [$p, Partner::kanal((string) ($_GET['k'] ?? ''))]]);
        }
    } catch (Throwable $e) { $kcJa = 'zeit'; }
    $kcJaMail = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
}
/* Eigene Texte des Partners gehen vor, sonst der Standard (Texte::PARTNER_LANDE). */
$L = static fn(string $k): string => in_array($k, ['titel', 'lead', 'p1', 'p2', 'p3'], true)
    ? PartnerSeite::text($g, $sprache, $k, strtr(Texte::h(Texte::PARTNER_LANDE[$k] ?? [], $sprache), ['{name}' => Partner::anzeigeName($p)]))
    : strtr(Texte::h(Texte::PARTNER_LANDE[$k] ?? [], $sprache), ['{name}' => Partner::anzeigeName($p)]);
$S = static fn(array $t): string => strtr(Texte::h($t, $sprache), ['{name}' => Partner::anzeigeName($p)]);
$vorlageHell = PartnerSeite::VORLAGEN[$g['vorlage']]['hell'];
$metall = $g['vorlage'] === 'gold' && $g['akzent'] === 'gold';
$titelbild = PartnerSeite::bildAdresse($p, $g);
$h = static fn(?string $s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$PS = Texte::PARTNER_SEITE;
$name = Partner::anzeigeName($p);
$foto = PartnerWerbung::fotoAdresse($p);
$satz = trim((string) ($p['profil_satz'] ?? ''));
/* Kleines Titelbild fürs Handy, wo es eins gibt (Auswahl hat -800-Fassungen). */
$titelKlein = ($titelbild !== null && isset(PartnerSeite::BILDER[$g['bild']]) && !str_contains($titelbild, 'haar/'))
    ? preg_replace('~\.webp$~', '-800.webp', $titelbild) : null;
$wegAdresse = static fn(string $weg, array $extra = []): string => '/p.php?' . http_build_query(array_filter(['c' => $p['code'], 'k' => $_GET['k'] ?? null, 'lang' => $sprache, 'weg' => $weg] + $extra));
$wege = $g['bausteine']['wege'] ? PartnerSeite::wege() : [];
/* Der Kurz-Check steht als eigener Abschnitt da -- dann nicht noch einmal als Weg. */
if ($g['bausteine']['kurzcheck']) { $wege = array_values(array_diff($wege, ['check'])); }
$termine = PartnerSeite::naechsteTermine(3);
$tageKurz = explode(',', Texte::h(Texte::AKQ_TERMIN['tage'], $sprache));
$gruss = PartnerSeite::grussAdresse($p);
$datenschutz = Sprache::legal($sprache, 'privacy');
$ogBild = PartnerSeite::ogAdresse($p, $g, $sprache);
$ogText = $S($PS['og_text']);
$initialen = mb_strtoupper(implode('', array_map(static fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/u', trim($name)) ?: [], 0, 2))));
$zit = static fn(string $t): string => (['it' => '«', 'en' => '“'][$sprache] ?? '„') . $t . (['it' => '»', 'en' => '”'][$sprache] ?? '“');
$waAn = $g['bausteine']['whatsapp'] && $g['whatsapp'] !== '';
/* Kleine Linien-Symbole statt Emoji (feste SVG, nichts vom Partner). */
$svg = static fn(string $d): string => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">' . $d . '</svg>';
$wegIcon = [
    'check' => $svg('<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/><path d="m8.5 11 2 2 3.5-3.5"/>'),
    'preis' => $svg('<path d="M17 6.5A7 7 0 1 0 17 17.5"/><path d="M4 10h9M4 14h9"/>'),
    'termin' => $svg('<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>'),
];
?><!doctype html>
<html lang="<?= $h($sprache) ?>" <?= Sprache::marken($sprache) ?>>
<head>
<meta charset="utf-8">
<?= Sprache::skript() ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title><?= $h($L('titel')) ?> — Vecom Design</title>
<meta name="description" content="<?= $h($ogText) ?>">
<?php /* Link-Vorschau beim Teilen (27.09.2026): noindex bleibt -- Vorschau ist keine Suchmaschine. */ ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="Vecom Design">
<meta property="og:title" content="<?= $h($L('titel') . ' — ' . $L('marke')) ?>">
<meta property="og:description" content="<?= $h($ogText) ?>">
<meta property="og:url" content="<?= $h(Partner::link($p)) ?>">
<meta property="og:image" content="<?= $h($ogBild) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="<?= $h(['it' => 'it_IT', 'de' => 'de_DE', 'en' => 'en_GB'][$sprache]) ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="stylesheet" href="/assets/css/fonts.css">
<link rel="stylesheet" href="/assets/css/kunde.css?v=<?= (int) @filemtime(__DIR__ . '/assets/css/kunde.css') ?>">
<script src="/assets/js/partnerseite.js?v=<?= (int) @filemtime(__DIR__ . '/assets/js/partnerseite.js') ?>" defer></script>
<style>
  .ld{max-width:560px;margin:0 auto}
  .ld .marke{display:inline-flex;gap:8px;align-items:center;border:1px solid var(--linie2);border-radius:999px;padding:6px 14px;
             font-size:13px;color:var(--cyan);margin:0 0 16px}
  .ld h1{font-family:var(--f-titel,var(--f-display));font-weight:var(--f-titel-w,800);font-size:calc(clamp(28px,7vw,40px) * var(--f-titel-s,1));line-height:1.12;margin:0 0 14px}
  .ld .lead{color:var(--dim);font-size:16.5px;line-height:1.65;margin:0 0 20px}
  .ld ul{list-style:none;padding:0;margin:0 0 22px;display:grid;gap:10px}
  .ld li{display:flex;gap:10px;font-size:15px;line-height:1.55}
  .ld li::before{content:"";flex:0 0 8px;height:8px;margin-top:8px;border-radius:50%;background:var(--metall)}
  .ld form{display:flex;flex-direction:column;gap:10px}
  .ld input[type=email],.ld .zusatz input{font-size:17px;padding:15px 16px;min-height:54px}
  .ld .knopf{min-height:54px;font-size:16px}
  .ld .klein{color:var(--leise);font-size:13px;margin:10px 0 0}
  .ld .klein a{color:var(--dim);text-decoration:underline}
  .ld .weiter{display:inline-block;margin-top:18px;color:var(--cyan);font-size:14.5px}
  /* Der Kopf mit dem Partner (27.09.2026, Uwe: Ja zu „Profilfoto größer“) */
  .lp-kopf{display:flex;gap:16px;align-items:center;margin:0 0 18px}
  .lp-kopf .bild{flex:0 0 96px;width:96px;height:96px;border-radius:50%;object-fit:cover;border:2px solid var(--akzent);display:grid;place-items:center;
                 font-family:var(--f-display);font-size:34px;background:var(--flaeche2);color:var(--akzent)}
  .lp-kopf b{display:block;font-size:19px;line-height:1.25}
  .lp-aktion{margin:0 0 16px;padding:12px 14px;border-radius:12px;border:1px solid var(--akzent);background:color-mix(in oklab, var(--akzent) 12%, transparent);display:flex;flex-direction:column;gap:3px}
  .lp-aktion b{font-size:16px;line-height:1.4}
  .lp-aktion span{font-size:13.5px;color:var(--dim)}
  .lp-kopf span{display:block;color:var(--dim);font-size:14.5px;margin-top:3px}
  .ld blockquote{margin:0 0 20px;padding:12px 16px;border-left:2px solid rgba(241,211,139,.6);font-size:16px;line-height:1.6;color:var(--text)}
  .sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
  .lp-werbung{display:flex;gap:10px;align-items:flex-start;font-size:13.5px;color:var(--dim);line-height:1.5;margin-top:4px}
  .lp-werbung input{width:18px;height:18px;margin-top:2px;flex:none;accent-color:var(--akzent)}
  .lp-werbung small{display:block;color:var(--leise);font-size:12.5px;margin-top:2px}
  .ld .zusatz{display:grid;gap:8px;margin:2px 0 4px}
  .lp-werbung:has(input:not(:checked)) + .zusatz{display:none}
  /* Gestaltung des Partners (26.09.2026): nur Werte aus PartnerSeite -- nie CSS vom Partner. */
  <?= PartnerSeite::css($g) ?>
  <?php if (!$metall): ?>
  .ld .knopf.haupt,.lp-wa.haupt,.lp-leiste a.haupt{background:var(--akzent);color:var(--knopftext);border-color:transparent}
  .ld li::before{background:var(--akzent)}
  .ld blockquote{border-left-color:var(--akzent)}
  <?php endif; ?>
  <?php if ($vorlageHell): ?>
  body::before{display:none}
  /* Die Wortmarke bleibt Vecom -- auf hellem Grund in dunklem Gold statt des hellen Metallverlaufs. */
  .wortmarke .wort b{background:none;-webkit-text-fill-color:#8a6322;color:#8a6322}
  .block{background:var(--flaeche);border-color:var(--linie);box-shadow:0 18px 50px -34px rgba(40,30,15,.35)}
  .ld input[type=email],.ld .zusatz input,.lp-rr input[type=text],.lp-rr input[type=tel],.lp-rr select,.lp-kc-form input{background:#fff;color:var(--text);border-color:var(--linie2)}
  <?php endif; ?>
  .lp-held{position:relative;margin:0 0 18px;border-radius:18px;overflow:hidden;aspect-ratio:16/9;background:var(--flaeche2)}
  .lp-held img{width:100%;height:100%;object-fit:cover;display:block}
  .lp-held::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 55%,rgba(0,0,0,.28))}
  .lp h2{font-family:var(--f-titel,var(--f-display));font-weight:var(--f-titel-w2,700);font-size:calc(20px * var(--f-titel-s,1));margin:0 0 14px}
  /* Drei Wege (27.09.2026) */
  .lp-wege{display:grid;gap:10px}
  .lp-wege a{display:flex;gap:14px;align-items:center;padding:14px 16px;border:1px solid var(--linie2);border-radius:14px;text-decoration:none;color:var(--text);background:var(--flaeche2)}
  .lp-wege a:hover,.lp-wege a:focus-visible{border-color:var(--akzent)}
  .lp-wege i{flex:0 0 38px;height:38px;border-radius:50%;display:grid;place-items:center;background:var(--akzent);color:var(--knopftext);font-style:normal}
  .lp-wege b{display:block;font-size:15.5px}
  .lp-wege span{display:block;color:var(--dim);font-size:13.5px;margin-top:2px}
  .lp-wege a::after{content:"→";margin-left:auto;color:var(--akzent);font-size:18px}
  .lp-arbeiten{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px}
  .lp-arbeiten figure{margin:0;border:1px solid var(--linie);border-radius:14px;overflow:hidden;background:var(--flaeche2)}
  .lp-arbeiten img{width:100%;height:auto;aspect-ratio:16/9;object-fit:cover;display:block}
  .lp-arbeiten figcaption{padding:10px 12px;font-size:13.5px;line-height:1.45}
  .lp-arbeiten figcaption b{display:block;font-size:14.5px}
  .lp-arbeiten figcaption span{color:var(--dim)}
  /* Projekt-Beispiele anklickbar (28.09.2026): Der Link liegt am Namen und
     deckt per ::after die ganze Karte ab -- ein Ziel, ein Link für Vorleser. */
  .lp-film{position:relative;border-radius:14px;overflow:hidden;background:#000;border:1px solid var(--linie)}
  .lp-film video{display:block;width:100%;height:auto;aspect-ratio:16/9;background:#000}
  .lp-film.hoch video{aspect-ratio:9/16;max-height:78vh;width:auto;max-width:100%;margin:0 auto}
  .lp-ton{position:absolute;right:12px;top:12px;min-height:40px;padding:6px 14px;border-radius:999px;border:1px solid rgba(255,255,255,.35);
          background:rgba(0,0,0,.55);color:#fff;font:600 14px/1 var(--f-text,inherit);cursor:pointer}
  .lp-ton:focus-visible{outline:2px solid var(--akzent);outline-offset:2px}
  .lp-arbeiten figure{position:relative;transition:border-color .18s cubic-bezier(.16,1,.3,1)}
  .lp-arbeiten figure:has(.lp-ar-a):hover,.lp-arbeiten figure:focus-within{border-color:var(--akzent)}
  .lp-arbeiten figure img{transition:transform .52s cubic-bezier(.16,1,.3,1)}
  .lp-arbeiten figure:has(.lp-ar-a):hover img{transform:scale(1.025)}
  .lp-arbeiten figure:last-child:nth-child(odd):not(:first-child){grid-column:1/-1}   /* drei Arbeiten: die dritte breit statt Lücke */
  .lp-ar-a{color:inherit;text-decoration:none}
  .lp-ar-a::after{content:"";position:absolute;inset:0;border-radius:inherit}
  .lp-ar-a:focus-visible{outline:none}
  .lp-arbeiten figure:has(.lp-ar-a:focus-visible){outline:2px solid var(--akzent);outline-offset:2px}
  .lp-arbeiten figcaption .lp-ar-los{display:flex;width:fit-content;align-items:center;gap:5px;margin-top:6px;color:var(--akzent);font-weight:600;font-size:13px}
  .lp-ar-los svg{width:12px;height:12px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
  @media (prefers-reduced-motion:reduce){.lp-arbeiten figure img{transition:none}.lp-arbeiten figure:has(.lp-ar-a):hover img{transform:none}}
  .lp-schritte{list-style:none;padding:0;margin:0;display:grid;gap:14px;counter-reset:s}
  .lp-schritte li{display:flex;gap:14px;align-items:flex-start;counter-increment:s}
  .lp-schritte li::before{content:counter(s);flex:0 0 32px;height:32px;border-radius:50%;display:grid;place-items:center;font-weight:700;background:var(--akzent);color:var(--knopftext);margin-top:0}
  .lp-schritte b{display:block}
  .lp-schritte span{color:var(--dim);font-size:15px}
  .lp details{border-top:1px solid var(--linie);padding:12px 0}
  .lp details:last-child{border-bottom:1px solid var(--linie)}
  .lp summary{cursor:pointer;font-weight:600}
  .lp details p{color:var(--dim);margin:8px 0 0}
  .lp-wa{display:flex;align-items:center;justify-content:center;gap:10px;min-height:52px;border-radius:12px;font-weight:650;text-decoration:none;padding:12px 18px;
         border:1px solid var(--linie2);color:var(--text)}
  .lp .weiter2{display:inline-block;margin-top:12px;color:var(--cyan);font-size:14.5px}
  .lp-stimmen{display:grid;gap:12px}
  .lp-stimmen figure{margin:0;padding:14px 16px;border:1px solid var(--linie);border-radius:14px;background:var(--flaeche2)}
  .lp-stimmen blockquote{margin:0 0 8px;padding:0;border:0;font-size:15.5px;line-height:1.6;color:var(--text)}
  .lp-stimmen figcaption{font-size:13.5px;color:var(--dim)}
  .lp-stimmen .sterne{color:var(--akzent);letter-spacing:2px;font-size:14px;margin-bottom:6px}
  .lp-rr form{display:grid;gap:10px}
  .lp-rr label{font-size:13px;color:var(--dim)}
  .lp-rr input[type=text],.lp-rr input[type=tel],.lp-rr select{font-size:16px;padding:13px 14px;border-radius:12px;width:100%;box-sizing:border-box}
  .lp-rr .zwei{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .lp-rr .ja{display:flex;gap:10px;align-items:flex-start;font-size:13.5px;color:var(--dim);line-height:1.5}
  .lp-rr .ja input{width:auto;margin-top:3px}
  .lp-rr .falle{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
  .lp-rr .gut{border:1px solid var(--akzent);border-radius:12px;padding:12px 14px;font-weight:600}
  .lp-rr .schlecht{border:1px solid #ef6b5b;border-radius:12px;padding:10px 14px;color:var(--text)}
  .vn{position:relative;overflow:hidden;aspect-ratio:16/9;--pos:50%}
  .vn img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
  .vn .vn-vor{clip-path:inset(0 calc(100% - var(--pos)) 0 0)}
  .vn .vn-linie{position:absolute;top:0;bottom:0;left:var(--pos);width:2px;background:#fff;box-shadow:0 0 0 1px rgba(0,0,0,.25);pointer-events:none}
  .vn input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:ew-resize;margin:0}
  .vn .vn-tag{position:absolute;top:8px;font-size:11.5px;padding:2px 8px;border-radius:999px;background:rgba(0,0,0,.55);color:#fff;pointer-events:none}
  .vn .vn-tag.l{left:8px}.vn .vn-tag.r{right:8px}
  .vn:focus-within{outline:2px solid var(--akzent);outline-offset:2px}
  .wl-knoepfe{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px}
  .wl-knoepfe .lp-wa{background:transparent;color:var(--text);border:1px solid var(--linie2);font:inherit;font-weight:650;cursor:pointer}
  /* Mitlaufender Knopf auf dem Handy (27.09.2026, Uwe: Ja). Ohne Skript immer da;
     mit Skript weg, solange das Formular oben ohnehin zu sehen ist. */
  .lp-leiste{display:none}
  @media (max-width:720px){
    .lp-leiste{display:flex;gap:8px;position:fixed;left:0;right:0;bottom:0;z-index:50;padding:10px 12px calc(10px + env(safe-area-inset-bottom));
               background:var(--flaeche);border-top:1px solid var(--linie2);box-shadow:0 -10px 30px -18px rgba(0,0,0,.5);transition:transform .24s cubic-bezier(.16,1,.3,1)}
    .lp-leiste.weg{transform:translateY(110%)}
    .lp-leiste a{flex:1 1 0;display:flex;align-items:center;justify-content:center;gap:6px;min-height:46px;border-radius:12px;font-weight:650;font-size:14.5px;
                 text-decoration:none;color:var(--text);border:1px solid var(--linie2)}
    .lp-leiste a.haupt{flex:1.4 1 0}
    body{padding-bottom:84px}
  }
  <?php if ($metall): ?>.lp-leiste a.haupt{background:var(--metall);color:#16120b;border-color:transparent}<?php endif; ?>
  @media (prefers-reduced-motion:reduce){.lp-leiste{transition:none}}
  /* Runde 2 (28.09.2026, Uwe: Ja zu R2–R7) */
  .lp-gruss{display:grid;gap:6px;margin:-4px 0 18px;padding:10px 12px;border:1px solid var(--linie2);border-radius:14px;background:var(--flaeche2)}
  .lp-gruss span{font-size:13.5px;color:var(--dim)}
  .lp-gruss audio{width:100%;height:40px}
  .lp-wunsch{border:0;padding:0;margin:0 0 4px;min-width:0}
  .lp-wunsch legend{font-weight:650;font-size:15.5px;margin:0 0 10px;padding:0}
  .lp-chips{display:grid;grid-template-columns:1fr 1fr;gap:8px}
  .lp-chips input{position:absolute;opacity:0;width:1px;height:1px}
  .lp-chips span{display:flex;align-items:center;justify-content:center;text-align:center;min-height:48px;padding:8px 10px;border:1px solid var(--linie2);border-radius:12px;background:var(--flaeche2);font-size:14.5px;font-weight:600;cursor:pointer;line-height:1.25}
  .lp-chips input:checked + span{border-color:var(--akzent);background:color-mix(in oklab, var(--akzent) 18%, var(--flaeche2));box-shadow:0 0 0 1px var(--akzent)}
  .lp-chips input:focus-visible + span{outline:2px solid var(--akzent);outline-offset:2px}
  .lp-wunsch-weiter{margin:4px 0 0;font-weight:600;color:var(--akzent)}
  .lp-email-teil{display:flex;flex-direction:column;gap:10px}
  form.zweistufig:not(.gewaehlt) .lp-email-teil{display:none}
  .lp-termine{margin:18px 0 0;padding:12px 0 0;border-top:1px solid var(--linie)}
  .lp-termine p{margin:0 0 8px;font-size:14px;color:var(--dim)}
  .lp-termine div{display:flex;flex-wrap:wrap;gap:8px}
  .lp-termine a{display:inline-flex;align-items:center;min-height:42px;padding:6px 12px;border:1px solid var(--linie2);border-radius:10px;color:var(--text);text-decoration:none;font-size:14px;font-weight:600;font-variant-numeric:tabular-nums}
  .lp-termine a:hover,.lp-termine a:focus-visible{border-color:var(--akzent);color:var(--akzent)}
  .ld .lp-kc-form{display:flex;flex-direction:row;gap:8px;flex-wrap:wrap}
  .lp-kc-form input{flex:1 1 220px;font-size:16px;padding:13px 14px;min-height:50px;border-radius:12px}
  .lp-kc-form .knopf{flex:0 0 auto;min-height:50px}
  .lp-kc-hinweis{margin:12px 0 0;padding:10px 14px;border:1px solid #ef6b5b;border-radius:12px}
  .lp-kc-erg{margin-top:16px;display:grid;gap:12px}
  .lp-kc-kopf{margin:0;font-weight:650}
  .lp-ampel{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px}
  .lp-ampel li{display:grid;grid-template-columns:auto 1fr;grid-template-rows:auto auto;column-gap:10px;align-items:center;padding:10px 12px;border:1px solid var(--linie);border-radius:12px;background:var(--flaeche2)}
  .lp-ampel li::before{display:none}
  .lp-ampel i{grid-row:1/3;width:14px;height:14px;border-radius:50%;background:#e0b341;box-shadow:0 0 0 3px color-mix(in oklab,#e0b341 25%,transparent)}
  .lp-ampel .st-gut i{background:#3fb56b;box-shadow:0 0 0 3px color-mix(in oklab,#3fb56b 25%,transparent)}
  .lp-ampel .st-schlecht i{background:#e5534b;box-shadow:0 0 0 3px color-mix(in oklab,#e5534b 25%,transparent)}
  .lp-ampel b{font-size:14.5px}
  .lp-ampel span{font-size:13px;color:var(--dim)}
  .ld .lp-kc-ja{display:grid;gap:10px;margin-top:16px;padding-top:14px;border-top:1px solid var(--linie)}
  .lp-kc-ja input[type=email],.lp-kc-ja input[type=tel]{font-size:16px;padding:13px 14px;min-height:50px;border-radius:12px;width:100%}
  .kc-nurwa{display:none}
  .lp-kc-ja:has(#kc_wa:checked) .kc-nurwa{display:block}
  .lp-kc-ja:has(#kc_wa:checked) .kc-nuremail{display:none}
  .lp-kc-ja .falle{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
  .lp-preise{margin:0;display:grid;gap:0}
  .lp-preise div{display:flex;justify-content:space-between;align-items:baseline;gap:14px;padding:11px 0;border-bottom:1px solid var(--linie)}
  .lp-preise div:first-child{border-top:1px solid var(--linie)}
  .lp-preise dt{font-size:15px}
  .lp-preise dd{margin:0;font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap;color:var(--text)}
  .lp-leiste-name{display:none}
  /* Titelbild als Bühne (28.09.2026, Uwe: Ja zu L3): Überschrift auf dem Bild, Abdunklung für die Lesbarkeit. */
  .lp-buehne{aspect-ratio:auto;min-height:min(118vw,540px);display:flex;align-items:flex-end}
  .lp-buehne img{position:absolute;inset:0}
  .lp-buehne::after{background:linear-gradient(180deg,rgba(0,0,0,.05) 20%,rgba(0,0,0,.55) 55%,rgba(0,0,0,.82))}
  .lp-buehne-text{position:relative;z-index:1;padding:22px 20px 20px}
  .lp-buehne-text h1{color:#fff;margin:0 0 10px;text-shadow:0 2px 18px rgba(0,0,0,.35)}
  .lp-buehne-text .lead{color:rgba(255,255,255,.88);margin:0}
  /* Breites Layout am Computer (28.09.2026, Uwe: Ja zu L1). Auf dem Handy bleibt alles wie bisher. */
  @media (min-width:980px){
    .seite{max-width:1160px}
    .ld{max-width:1080px}
    .ld .lead{max-width:62ch}
    .lp-start.mit-bild:not(.buehne){display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.05fr);gap:44px;align-items:start;padding:34px}
    .lp-start.mit-bild:not(.buehne) .lp-held{order:2;margin:0;aspect-ratio:4/3;position:sticky;top:24px}
    .lp-start:not(.mit-bild) .lp-inhalt,.lp-start.buehne .lp-inhalt{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);gap:44px;align-items:start}
    .lp-start.buehne{padding:18px 18px 30px}
    .lp-start.buehne .lp-inhalt{padding:10px 16px 0}
    .lp-buehne{min-height:0;aspect-ratio:21/9;margin-bottom:26px}
    .lp-buehne-text{padding:34px 38px 32px;max-width:760px}
    .lp-start:not(.mit-bild) .lp-b,.lp-start.buehne .lp-b{padding:22px;border:1px solid var(--linie2);border-radius:16px;background:var(--flaeche2)}
    .lp-wege{grid-template-columns:repeat(3,minmax(0,1fr))}
    .lp-wege a{flex-direction:column;align-items:flex-start;text-align:left}
    .lp-wege i{flex:none;width:38px;height:38px}
    .lp-wege a::after{margin-left:0;margin-top:auto}
    .lp-stimmen{grid-template-columns:repeat(auto-fit,minmax(280px,1fr))}
    .lp-arbeiten{grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}
    .lp-arbeiten figure:last-child:nth-child(odd):not(:first-child){grid-column:auto}
    .lp details,.lp-rr form,.lp-rr > p,.lp-schritte,.wl > p{max-width:720px}
    #whatsapp .lp-wa{max-width:420px}
    .lp-preise{max-width:720px}
    .lp-chips{grid-template-columns:repeat(4,minmax(0,1fr))}
    .lp-start.mit-bild:not(.buehne) .lp-chips{grid-template-columns:1fr 1fr}
  }
  /* Mitlaufende Leiste am Computer (28.09.2026, Uwe: Ja zu R5): oben, erst wenn das Formular aus dem Bild ist (nur mit Skript). */
  @media (min-width:721px){
    .lp-leiste.bereit{display:flex;align-items:center;gap:10px;position:fixed;left:0;right:0;top:0;z-index:50;padding:10px max(20px,calc((100vw - 1080px)/2)) 10px;
                      background:color-mix(in oklab, var(--flaeche) 92%, transparent);backdrop-filter:blur(10px);border-bottom:1px solid var(--linie2);
                      box-shadow:0 10px 30px -22px rgba(0,0,0,.5);transition:transform .24s cubic-bezier(.16,1,.3,1)}
    .lp-leiste.bereit.weg{transform:translateY(-110%)}
    .lp-leiste.bereit .lp-leiste-name{display:block;margin-right:auto;font-size:14px;color:var(--dim);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .lp-leiste.bereit .lp-leiste-name b{color:var(--text);font-family:var(--f-display);letter-spacing:.04em}
    .lp-leiste.bereit a{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 18px;border-radius:12px;font-weight:650;font-size:14.5px;text-decoration:none;color:var(--text);border:1px solid var(--linie2)}
    .lp-leiste.bereit a.haupt{color:var(--knopftext);border-color:transparent}
    .lp,.lp-start{scroll-margin-top:84px}
  }
  /* Sanftes Einblenden beim Scrollen (28.09.2026, Uwe: Ja zu L6): nur CSS, ohne
     Skript; wo der Browser es nicht kann oder „weniger Bewegung“ gilt, steht alles still da. */
  @keyframes lp-auf{from{opacity:0;transform:translateY(22px)}to{opacity:1;transform:none}}
  @supports (animation-timeline:view()){
    @media (prefers-reduced-motion:no-preference){
      .lp{animation:lp-auf linear both;animation-timeline:view();animation-range:entry 0% entry 38%}
    }
  }
</style>
</head>
<body>
<div class="seite">
  <div class="wortmarke">
    <img src="/assets/img/logo-mark.webp?v=gold2609" alt="" width="58" height="46">
    <span class="wort"><b>VECOM</b> DESIGN</span>
  </div>
  <?php $buehne = $titelbild && $g['kopf'] === 'buehne'; ?>
  <div class="block ld lp-start<?= $titelbild ? ' mit-bild' : '' ?><?= $buehne ? ' buehne' : '' ?>" id="start">
    <?php if ($titelbild): ?><div class="lp-held<?= $buehne ? ' lp-buehne' : '' ?>"><img src="<?= $h($titelbild) ?>"<?php if ($titelKlein): ?> srcset="<?= $h($titelKlein) ?> 800w, <?= $h($titelbild) ?> 1600w" sizes="<?= $buehne ? '(min-width:980px) 1080px, 100vw' : '(max-width:600px) 100vw, (min-width:980px) 540px, 560px' ?>"<?php endif; ?> alt="" width="1600" height="900" fetchpriority="high">
      <?php if ($buehne): ?><div class="lp-buehne-text"><h1><?= $h($L('titel')) ?></h1><p class="lead"><?= $h($L('lead')) ?></p></div><?php endif; ?></div><?php endif; ?>
    <div class="lp-inhalt"><div class="lp-a">
    <div class="lp-kopf">
      <?php if ($foto): ?><img class="bild" src="<?= $h($foto) ?>" alt="<?= $h($L('foto_alt')) ?>" width="96" height="96">
      <?php else: ?><span class="bild" aria-hidden="true"><?= $h($initialen) ?></span><?php endif; ?>
      <div><b><?= $h($name) ?></b><span>★ <?= $h($S($PS['empfiehlt'])) ?></span></div>
    </div>
    <?php if ($gruss): ?><div class="lp-gruss"><span><?= $h($S($PS['gruss_titel'])) ?></span><audio controls preload="none" src="<?= $h($gruss) ?>"></audio></div><?php endif; ?>
    <?php if ($satz !== ''): ?><blockquote><?= $h($zit($satz)) ?></blockquote><?php endif; ?>
    <?php /* Zentrale Aktion (28.09.2026, Uwe: Ja): einmal in der Verwaltung angelegt, auf allen Partnerseiten. */
          $aktion = PartnerMarketing::aktion(); if ($aktion): ?>
      <p class="lp-aktion" role="note"><b><?= $h(PartnerMarketing::aktionText($aktion, $sprache)) ?></b><span><?= $h(PartnerMarketing::aktionRest($aktion, $sprache)) ?></span></p>
    <?php endif; ?>
    <?php if (!$buehne): ?><h1><?= $h($L('titel')) ?></h1>
    <p class="lead"><?= $h($L('lead')) ?></p><?php endif; ?>
    <ul><li><?= $h($L('p1')) ?></li><li><?= $h($L('p2')) ?></li><li><?= $h($L('p3')) ?></li></ul>
    </div><div class="lp-b">
    <form method="post" action="/zugang.php?lang=<?= $h($sprache) ?>" id="lp_form">
      <input type="hidden" name="quelle" value="seite"><input type="hidden" name="von_partner" value="1">
      <?php /* Zwei Schritte (28.09.2026, Uwe: Ja zu R3): erst antippen, dann E-Mail. Ohne Skript steht beides da. */ ?>
      <fieldset class="lp-wunsch"><legend><?= $h($S($PS['wunsch_frage'])) ?></legend>
        <div class="lp-chips"><?php foreach ($PS['wuensche'] as $wk => $wt): ?><label><input type="radio" name="wunsch" value="<?= $h($wk) ?>"><span><?= $h($S($wt)) ?></span></label><?php endforeach; ?></div>
      </fieldset>
      <p class="lp-wunsch-weiter" hidden><?= $h($S($PS['wunsch_weiter'])) ?></p>
      <div class="lp-email-teil">
      <label for="ld_email" class="sr"><?= $h($L('feld')) ?></label>
      <input id="ld_email" name="email" type="email" required autocomplete="email" inputmode="email" placeholder="<?= $h($L('feld')) ?>">
      <?php /* Freiwillige Werbe-Einwilligung (27.09.2026, Uwe: Ja): nur Bestätigungsmail, erlaubt erst nach dem Klick. */ ?>
      <label class="lp-werbung"><input type="checkbox" name="werbung" value="1"><span><?= $h($S($PS['werbung'])) ?><small><?= $h($S($PS['werbung_hilfe'])) ?></small></span></label>
      <div class="zusatz">
        <label for="ld_betrieb" class="sr"><?= $h($S($PS['betrieb'])) ?></label>
        <input id="ld_betrieb" name="betrieb" type="text" maxlength="190" autocomplete="organization" placeholder="<?= $h($S($PS['betrieb'])) ?>">
        <label for="ld_web" class="sr"><?= $h($S($PS['webseite'])) ?></label>
        <input id="ld_web" name="webseite" type="text" maxlength="200" inputmode="url" autocomplete="url" placeholder="<?= $h($S($PS['webseite'])) ?>">
      </div>
      <button class="knopf haupt" type="submit"><?= $h($S($PS['knoepfe'][$g['knopf']])) ?></button>
      </div>
    </form>
    <p class="klein"><?= $h($L('klein')) ?> <?= $h($S($PS['ds'])) ?> <a href="<?= $h($datenschutz) ?>"><?= $h($S($PS['ds_link'])) ?></a></p>
    <?php /* Freie Termine gleich hier (28.09.2026, Uwe: Ja zu R2): ein Klick, und die Buchung hat die Zeit schon gewählt. */
          if ($termine): ?>
      <div class="lp-termine"><p><?= $h($S($PS['termine_frage'])) ?></p>
        <div><?php foreach ($termine as $tm): $tts = strtotime($tm['datum']); ?><a href="<?= $h($wegAdresse('termin', ['slot' => $tm['slot']])) ?>"><?= $h(($tageKurz[(int) date('N', $tts) - 1] ?? '') . ' ' . date('d.m.', $tts) . ' · ' . $tm['zeit']) ?></a><?php endforeach; ?></div>
      </div>
    <?php endif; ?>
    <a class="weiter" href="<?= $h($ziel) ?>"><?= $h($L('weiter')) ?></a>
    </div></div>
  </div>

<?php
/* Die Abschnitte in der Reihenfolge des Partners (27.09.2026, Uwe: Ja zu „Bausteine umsortieren“). */
foreach ($g['reihenfolge'] as $baustein):
  if (empty($g['bausteine'][$baustein])) { continue; }
  switch ($baustein):
    case 'wege':
      if ($wege === []) { break; } ?>
  <section class="block ld lp" id="wege"><h2><?= $h($S($PS['wege_titel'])) ?></h2>
    <div class="lp-wege">
      <?php foreach ($wege as $w): [$wt, $wx] = $PS['wege'][$w]; ?>
        <a href="<?= $h($wegAdresse($w)) ?>"><i aria-hidden="true"><?= $wegIcon[$w] ?></i><div><b><?= $h($S($wt)) ?></b><span><?= $h($S($wx)) ?></span></div></a>
      <?php endforeach; ?>
    </div>
  </section>
<?php break;
    case 'kurzcheck':
      $kcP = Texte::PARTNER_CHECK['punkte']; ?>
  <section class="block ld lp lp-kc" id="check"><h2><?= $h($S($PS['kc_titel'])) ?></h2>
    <p class="klein" style="margin:0 0 12px"><?= $h($S($PS['kc_text'])) ?></p>
    <form method="post" action="<?= $h($hier()) ?>#check" class="lp-kc-form">
      <input type="hidden" name="tat" value="kurzcheck">
      <label for="kc_url" class="sr"><?= $h($S($PS['kc_feld'])) ?></label>
      <input id="kc_url" name="url" type="text" inputmode="url" autocomplete="url" required maxlength="200" placeholder="<?= $h($S($PS['kc_feld'])) ?>" value="<?= $h($kcUrl) ?>">
      <button class="knopf haupt" type="submit"><?= $h($S($PS['kc_knopf'])) ?></button>
    </form>
    <?php if ($kc !== null && !$kc['ok']): ?>
      <p class="schlecht lp-kc-hinweis" role="alert"><?= $h($S($PS['kc_fehler'][$kc['grund']] ?? $PS['kc_fehler']['adresse'])) ?></p>
      <?php if (($kc['grund'] ?? '') === 'zuviel'): ?><a class="lp-wa" href="<?= $h($wegAdresse('check', ['url' => $kcUrl])) ?>"><?= $h($S($PS['kc_mehr'])) ?></a><?php endif; ?>
    <?php elseif ($kc !== null): ?>
      <div class="lp-kc-erg" role="status">
        <p class="lp-kc-kopf"><?= $h(strtr($S($PS['kc_ergebnis']), ['{host}' => $kc['host']])) ?></p>
        <ul class="lp-ampel"><?php foreach ($kc['punkte'] as $kp): if (!isset($kcP[$kp['was']])) { continue; } ?>
          <li class="st-<?= $h($kp['stand']) ?>"><i aria-hidden="true"></i><b><?= $h($S($kcP[$kp['was']]['titel'])) ?></b><span><?= $h($S($PS['kc_stand'][$kp['stand']] ?? $PS['kc_stand']['hinweis'])) ?></span></li>
        <?php endforeach; ?></ul>
      </div>
      <?php foreach (['Akquise', 'AkquiseGate', 'AkquiseText', 'AkquiseCheck', 'AkquiseEinwilligung'] as $k) { require_once __DIR__ . "/app/src/$k.php"; } ?>
      <form method="post" action="<?= $h($hier()) ?>#check" class="lp-kc-ja">
        <input type="hidden" name="tat" value="kurzcheck_ja"><input type="hidden" name="url" value="<?= $h($kc['url']) ?>"><input type="hidden" name="z" value="<?= $h(AkquiseCheck::stempel()) ?>">
        <span class="falle" aria-hidden="true"><label>Homepage <input type="text" name="homepage" tabindex="-1" autocomplete="off"></label></span>
        <p class="lp-kc-kopf"><?= $h($S($PS['kc_ja_titel'])) ?></p>
        <p class="klein" style="margin:0"><?= $h($S($PS['kc_ja_text'])) ?></p>
        <label for="kc_email" class="sr"><?= $h($S($PS['kc_ja_email'])) ?></label>
        <input id="kc_email" type="email" name="email" required autocomplete="email" inputmode="email" placeholder="<?= $h($S($PS['kc_ja_email'])) ?>">
        <label class="lp-werbung"><input id="kc_wa" type="checkbox" name="wa" value="1"><span><?= $h($S($PS['kc_ja_wa'])) ?></span></label>
        <div class="kc-nurwa"><label for="kc_wanr" class="sr">WhatsApp</label><input id="kc_wanr" type="tel" name="whatsapp" inputmode="tel" autocomplete="tel" placeholder="+39 …"></div>
        <label class="lp-werbung"><input type="checkbox" name="ja" value="1" required><span><span class="kc-nuremail"><?= $h(AkquiseEinwilligung::wortlaut($sprache)) ?></span><span class="kc-nurwa"><?= $h(AkquiseEinwilligung::wortlaut($sprache, ['it' => 'indicato sopra', 'de' => 'der oben angegebenen Nummer', 'en' => 'the number given above'][$sprache] ?? 'indicato sopra')) ?></span></span></label>
        <button class="knopf haupt" type="submit"><?= $h($S($PS['kc_ja_knopf'])) ?></button>
      </form>
    <?php endif; ?>
    <?php if ($kcJa === 'ok'): ?>
      <p class="gut lp-kc-hinweis" role="status" style="border-color:var(--akzent)"><?= $h(strtr($S($PS['kc_ja_ok']), ['{email}' => $kcJaMail])) ?></p>
    <?php elseif ($kcJa !== ''): ?>
      <p class="schlecht lp-kc-hinweis" role="alert"><?= $h($S($PS['kc_ja_fehler'][$kcJa] ?? $PS['kc_ja_fehler']['zeit'])) ?></p>
    <?php endif; ?>
  </section>
<?php break;
    case 'preise':
      $pr = PartnerSeite::preise($sprache);
      if ($pr['faelle'] === []) { break; } ?>
  <section class="block ld lp" id="preise"><h2><?= $h($S($PS['preise_titel'])) ?></h2>
    <dl class="lp-preise"><?php foreach ($pr['faelle'] as $fk2 => $fp): ?><div><dt><?= $h($S($PS['preise_faelle'][$fk2])) ?></dt><dd><?= $h($fp) ?></dd></div><?php endforeach; ?></dl>
    <p class="klein" style="margin:12px 0 0"><?= $h($S($PS['preise_hinweis'])) ?><?php if ($pr['betreuung'] !== ''): ?> <?= $h(strtr($S($PS['preise_betreuung']), ['{preis}' => $pr['betreuung']])) ?><?php endif; ?></p>
    <a class="lp-wa" style="margin-top:14px" href="<?= $h($wegAdresse('preis')) ?>"><?= $h($S($PS['preise_knopf'])) ?> →</a>
  </section>
<?php break;
    case 'film':
      /* Werbefilm (28.09.2026): stumm, spielt, sobald er sichtbar ist, Ton auf
         Knopfdruck. Hochformat auf schmalen Bildschirmen (Skript wählt). Ohne
         Skript: Standbild mit Bedienelementen. */
      $film = PartnerSeite::film($g['film'] ?: null, $sprache);
      if ($film === null) { break; }
      $fq = $film['quer'] ?? $film['hoch']; $fh = $film['hoch'] ?? null; ?>
  <?php $fk = $film['id'] === 'showreel' ? 'showreel_' : 'film_'; ?>
  <section class="block ld lp lp-filmblock" id="film"><h2><?= $h($S($PS[$fk . 'titel'])) ?></h2>
    <p class="klein" style="margin:0 0 12px"><?= $h($S($PS[$fk . 'text'])) ?></p>
    <div class="lp-film<?= isset($film['quer']) ? '' : ' hoch' ?>" data-film>
      <video controls playsinline muted loop preload="none" poster="<?= $h($fq['poster']) ?>" aria-label="<?= $h($S($PS['film_alt_' . str_replace('-', '_', $film['id'])] ?? $PS['film_alt'])) ?>"
             <?php if ($fh && isset($film['quer'])): ?>data-hoch-mp4="<?= $h($fh['mp4']) ?>" data-hoch-webm="<?= $h((string) $fh['webm']) ?>" data-hoch-poster="<?= $h($fh['poster']) ?>"<?php endif; ?>>
        <?php if ($fq['webm']): ?><source src="<?= $h($fq['webm']) ?>" type="video/webm"><?php endif; ?>
        <source src="<?= $h($fq['mp4']) ?>" type="video/mp4">
      </video>
      <button type="button" class="lp-ton" hidden data-an="<?= $h($S($PS['film_ton_an'])) ?>" data-aus="<?= $h($S($PS['film_ton_aus'])) ?>"><?= $h($S($PS['film_ton_an'])) ?></button>
    </div>
  </section>
<?php break;
    case 'stimmen':
      $stimmen = PartnerSeite::stimmen($p, $sprache, 3);
      if (!$stimmen) { break; } ?>
  <section class="block ld lp"><h2><?= $h($S($PS['stimmen_titel'])) ?></h2>
    <div class="lp-stimmen"><?php foreach ($stimmen as $st): ?>
      <figure><?php if ($st['sterne']): ?><div class="sterne" aria-label="<?= (int) $st['sterne'] ?>/5"><?= str_repeat('★', (int) $st['sterne']) ?></div><?php endif; ?>
        <blockquote><?= $h($st['text']) ?></blockquote>
        <figcaption>— <?= $h($st['name']) ?><?= $st['firma'] !== '' ? ', ' . $h($st['firma']) : '' ?><?= $st['ort'] !== '' ? ' · ' . $h($st['ort']) : '' ?></figcaption></figure>
    <?php endforeach; ?></div>
  </section>
<?php break;
    case 'ablauf': ?>
  <section class="block ld lp"><h2><?= $h($S($PS['ablauf_titel'])) ?></h2>
    <ol class="lp-schritte"><?php foreach ($PS['ablauf'] as [$t, $u]): ?><li><div><b><?= $h($S($t)) ?></b><span><?= $h($S($u)) ?></span></div></li><?php endforeach; ?></ol>
  </section>
<?php break;
    case 'arbeiten': ?>
  <section class="block ld lp"><h2><?= $h($S($PS['arbeiten_titel'])) ?></h2>
    <div class="lp-arbeiten"><?php foreach ($g['arbeiten'] as $aid): $a = $PS['arbeiten'][$aid] ?? null; if (!$a || !is_file(__DIR__ . '/assets/img/arbeiten/' . $aid . '/an.webp')) { continue; } ?>
      <?php $vor = PartnerSeite::vorher($aid); ?>
      <figure><?php if ($vor): ?>
        <div class="vn"><img src="/assets/img/arbeiten/<?= $h($aid) ?>/an.webp" alt="<?= $h($a['name'] . ' — ' . $S($PS['vn_nachher'])) ?>" width="2400" height="1350" loading="lazy" decoding="async">
          <img class="vn-vor" src="<?= $h($vor) ?>" alt="<?= $h($a['name'] . ' — ' . $S($PS['vn_vorher'])) ?>" loading="lazy" decoding="async">
          <span class="vn-linie" aria-hidden="true"></span><span class="vn-tag l"><?= $h($S($PS['vn_vorher'])) ?></span><span class="vn-tag r"><?= $h($S($PS['vn_nachher'])) ?></span>
          <input type="range" min="0" max="100" value="50" aria-label="<?= $h($S($PS['vn_regler']) . ' — ' . $a['name']) ?>" data-vn></div>
      <?php else: ?><img src="/assets/img/arbeiten/<?= $h($aid) ?>/an.webp" alt="<?= $h($a['name']) ?>" width="2400" height="1350" loading="lazy" decoding="async"><?php endif; ?>
        <?php $aUrl = PartnerSeite::arbeitUrl($aid); ?>
        <figcaption><b><?php if ($aUrl): ?><a class="<?= $vor ? '' : 'lp-ar-a' ?>" href="<?= $h($aUrl) ?>" target="_blank" rel="noopener"><?= $h($a['name']) ?><span class="sr"> <?= $h($S($PS['neuer_tab'])) ?></span></a><?php else: ?><?= $h($a['name']) ?><?php endif; ?></b><span><?= $h(Texte::h($a, $sprache)) ?></span>
          <?php if ($aUrl): ?><span class="lp-ar-los" aria-hidden="true"><?= $h($S($PS['arbeiten_ansehen'])) ?> <svg viewBox="0 0 12 12"><path d="M4 2h6v6M10 2L2.5 9.5"/></svg></span><?php endif; ?></figcaption></figure>
    <?php endforeach; ?></div>
    <a class="weiter2" href="<?= $h($ziel . '#work') ?>"><?= $h($S($PS['arbeiten_mehr'])) ?></a>
  </section>
<?php break;
    case 'faq': ?>
  <section class="block ld lp"><h2><?= $h($S($PS['faq_titel'])) ?></h2>
    <?php foreach ($PS['faq'] as [$q, $a]): ?><details><summary><?= $h($S($q)) ?></summary><p><?= $h($S($a)) ?></p></details><?php endforeach; ?>
  </section>
<?php break;
    case 'rueckruf':
      require_once __DIR__ . '/app/src/PartnerRueckruf.php';
      $rrStand = (string) ($_GET['rr'] ?? ''); $rrFehler = $PS['rr_fehler'][$rrStand] ?? null;
      $rrTage = PartnerRueckruf::tage(); $rrHeute = date('Y-m-d'); $rrOffen = PartnerRueckruf::offeneFenster(); ?>
  <section class="block ld lp lp-rr" id="rueckruf"><h2><?= $h($S($PS['rr_titel'])) ?></h2>
    <?php if ($rrStand === 'ok'): ?><p class="gut" role="status"><?= $h($S($PS['rr_danke'])) ?></p>
    <?php else: ?>
      <p class="klein" style="margin:0 0 12px"><?= $h($S($PS['rr_text'])) ?></p>
      <?php if ($rrFehler): ?><p class="schlecht" role="alert"><?= $h($S($rrFehler)) ?></p><?php endif; ?>
      <form method="post" action="<?= $h($hier()) ?>#rueckruf">
        <input type="hidden" name="tat" value="rueckruf"><input type="hidden" name="st" value="<?= $h(PartnerRueckruf::stempel((string) $p['code'])) ?>">
        <span class="falle" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></span>
        <div><label for="rr_name"><?= $h($S($PS['rr_name'])) ?></label><input id="rr_name" type="text" name="name" required maxlength="80" autocomplete="name"></div>
        <div><label for="rr_tel"><?= $h($S($PS['rr_telefon'])) ?></label><input id="rr_tel" type="tel" name="telefon" required maxlength="24" autocomplete="tel" inputmode="tel" placeholder="+39 …"></div>
        <div class="zwei">
          <div><label for="rr_tag"><?= $h($S($PS['rr_tag'])) ?></label><select id="rr_tag" name="tag" data-heute="<?= $h($rrHeute) ?>"><?php foreach ($rrTage as $dt => $wk): ?><option value="<?= $h($dt) ?>"><?= $h($S($PS['rr_tage'][$wk])) ?></option><?php endforeach; ?></select></div>
          <div><label for="rr_fenster"><?= $h($S($PS['rr_fenster'])) ?></label><select id="rr_fenster" name="fenster"><?php foreach (PartnerRueckruf::FENSTER as $fk => $fw): ?><option value="<?= $h($fk) ?>"<?= in_array($fk, $rrOffen, true) ? '' : ' data-vorbei' ?>><?= $h($fw) ?></option><?php endforeach; ?></select></div>
        </div>
        <label class="ja"><input type="checkbox" name="ok" value="1" required> <span><?= $h($S($PS['rr_ok'])) ?> <a href="<?= $h($datenschutz) ?>" style="color:var(--dim);text-decoration:underline"><?= $h($S($PS['ds_link'])) ?></a></span></label>
        <button class="knopf" type="submit"><?= $h($S($PS['rr_knopf'])) ?></button>
      </form>
    <?php endif; ?>
  </section>
<?php break;
    case 'whatsapp':
      if (!$waAn) { break; } ?>
  <section class="block ld lp" id="whatsapp">
    <a class="lp-wa" href="<?= $h($wegAdresse('wa')) ?>" target="_blank" rel="noopener"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8 8 0 1 1 12 20z"/></svg><?= $h($S($PS['wa_knopf'])) ?></a>
  </section>
<?php break;
  endswitch;
endforeach; ?>
  <?php /* Weiterleiten (27.09.2026, Uwe: „alles muss für Kunden mit Kunden teilbar sein“):
           Wer die Seite gut findet, gibt sie weiter. Der Link trägt den Kanal
           „weiter“ -- so sieht der Partner, dass seine Kunden für ihn werben. */
        $wlLink = PartnerWerbung::link($p, 'weiter'); $wlText = $L('wl_nachricht') . $wlLink; ?>
  <section class="block ld lp wl">
    <h2><?= $h($L('wl_titel')) ?></h2>
    <p class="klein" style="margin:0 0 12px"><?= $h($L('wl_text')) ?></p>
    <div class="wl-knoepfe">
      <a class="lp-wa" href="https://wa.me/?text=<?= rawurlencode($wlText) ?>" target="_blank" rel="noopener"><svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18a8 8 0 0 1-4.1-1.1l-.3-.2-3 .8.8-2.9-.2-.3A8 8 0 1 1 12 20z"/></svg><?= $h($L('wl_wa')) ?></a>
      <a class="lp-wa" href="mailto:?subject=<?= rawurlencode($L('wl_betreff')) ?>&amp;body=<?= rawurlencode($wlText) ?>"><?= $h($L('wl_mail')) ?></a>
      <button class="lp-wa" type="button" id="wl_kopieren" data-link="<?= $h($wlLink) ?>" data-fertig="<?= $h($L('wl_kopiert')) ?>" hidden><?= $h($L('wl_kopieren')) ?></button>
      <button class="lp-wa" type="button" id="wl_teilen" data-text="<?= $h($wlText) ?>" hidden><?= $h($L('wl_teilen')) ?></button>
    </div>
  </section>
  <div class="sprachen">
    <?php foreach (['it' => 'Italiano', 'de' => 'Deutsch', 'en' => 'English'] as $l => $wie): ?>
      <a class="<?= $l === $sprache ? 'jetzt' : '' ?>" href="<?= $h('/p.php?' . http_build_query(array_filter(['c' => $p['code'], 'k' => $_GET['k'] ?? null, 'lang' => $l, 'n' => 1]))) ?>"><?= $h($wie) ?></a>
    <?php endforeach; ?>
  </div>
</div>
<nav class="lp-leiste" id="lp_leiste" aria-label="<?= $h($L('knopf')) ?>">
  <span class="lp-leiste-name"><b>VECOM</b> DESIGN · <?= $h(strtr(Texte::h(Texte::PARTNER_LANDE['marke'], $sprache), ['{name}' => $name])) ?></span>
  <a class="haupt" href="#start"><?= $h($S($PS['st_start'])) ?></a>
  <?php if ($g['bausteine']['rueckruf']): ?><a href="#rueckruf"><?= $h($S($PS['st_rr'])) ?></a><?php endif; ?>
  <?php if ($waAn): ?><a href="<?= $h($wegAdresse('wa')) ?>" target="_blank" rel="noopener"><?= $h($S($PS['st_wa'])) ?></a><?php endif; ?>
</nav>
<?php require_once __DIR__ . '/app/src/Fuss.php'; echo Fuss::html($sprache); ?>
</body>
</html>
