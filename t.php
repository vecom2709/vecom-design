<?php
declare(strict_types=1);
/* ==========================================================================
   t.php — Meldestelle des Partner-Trackings (30.09.2026, Uwe: „Alles“),
   seit Growth Engine Phase 3 auch für Besuche über Kampagnenlinks (/k/…).

   Das Skript auf den Seiten (assets/js/zaehlen.js) meldet hier NUR in einem
   Partner- oder Kampagnen-Besuch: Seitenwechsel, Kontaktformular geöffnet, ein Lebenszeichen
   für „Live“ und die Antwort auf die Frage „30 Tage merken?“.

   Der Browser bestimmt nichts Wichtiges: welcher Partner und welcher Besuch,
   sagt allein das Sitzungs-Cookie (Spur::KEKS), das p.php gesetzt hat. Ohne
   Besuch passiert nichts — außer bei gemerkter Einwilligung (Wiederkehr).
   Preisrechner-Ereignisse leitet der Server aus der Seite ab.

   ?widerruf=1  → Einwilligung zurücknehmen (Link in der Datenschutzerklärung).
   ========================================================================== */

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: same-origin');

$konfig = __DIR__ . '/app/config.local.php';
$antwort = static function (array $d, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
};
if (!is_file($konfig)) { $antwort(['ok' => 0], 503); }
foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'Texte', 'Sprache', 'Partner', 'Spur'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }

/* Widerruf: per Link, dann zurück zur Datenschutzerklärung. */
if (isset($_GET['widerruf'])) {
    try { Spur::widerrufen(); } catch (Throwable $e) { }
    $sp = in_array((string) ($_GET['lang'] ?? ''), ['it', 'de', 'en'], true) ? (string) $_GET['lang'] : 'it';
    $ziel = Sprache::legal($sp);
    header('Location: ' . $ziel . (str_contains($ziel, '?') ? '&' : '?') . 'widerrufen=1#privacy', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $antwort(['ok' => 0], 405); }
/* Nur von den eigenen Seiten (Origin/Referer), klein, und nicht im Sekundentakt. */
$eigene = mb_strtolower((string) (parse_url((string) Config::get('website', 'https://vecom-design.it'), PHP_URL_HOST) ?? ''));
$her = mb_strtolower((string) (parse_url((string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST) ?? ''));
$ohneWww = static fn(string $h): string => (string) preg_replace('~^www\.~', '', $h);
$lokal = in_array($her, ['127.0.0.1', 'localhost'], true) && in_array((string) ($_SERVER['SERVER_NAME'] ?? ''), ['127.0.0.1', 'localhost'], true);
if ($her === '' || ($ohneWww($her) !== $ohneWww($eigene) && !$lokal)) { $antwort(['ok' => 0], 403); }
$roh = (string) file_get_contents('php://input', false, null, 0, 4096);
$d = json_decode($roh, true);
if (!is_array($d)) { $d = $_POST; }
$e = (string) ($d['e'] ?? '');
if (Partner::istRoboter((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) { $antwort(['ok' => 0]); }

try {
    if (!Spur::an()) { $antwort(['ok' => 0, 'aus' => 1]); }
    $server = ['ua' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
               'referrer' => (string) ($d['r'] ?? ''), 'sprache' => mb_substr((string) ($d['l'] ?? ''), 0, 5), 'get' => (array) ($d['u'] ?? []),
               'einstieg' => Spur::pfad((string) ($d['p'] ?? '/'))];
    $b = Spur::aktuellerBesuch() ?? Spur::wiederkehr($server);
    if ($b === null) {
        // Kein Partner-Besuch (mehr): das Skript soll aufhören zu melden.
        if (!headers_sent()) { @setcookie(Spur::KEKS_JS, '', ['expires' => time() - 3600, 'path' => '/', 'samesite' => 'Lax']); }
        $antwort(['ok' => 0, 'aus' => 1]);
    }
    /* Bremse: höchstens 400 Meldungen je Besuch. */
    if ((int) Db::wert('SELECT COUNT(*) FROM spur_ereignisse WHERE besuch_id = ?', [(int) $b['id']], 0) > 400) { $antwort(['ok' => 0]); }

    if ($e === 'ja' || $e === 'nein') {
        Spur::einwilligen($e === 'ja');
        $antwort(['ok' => 1]);
    }
    if ($e === 'ping') {
        Spur::ping();
        /* Sofort-Hinweis (03.10.2026, N1): bleibt jemand auf der Partnerseite, ist das ein heißer Besuch. */
        if (!empty($b['partner_id'])) { try { require_once __DIR__ . '/app/src/PartnerBesuche.php'; PartnerBesuche::heissMelden((int) $b['id']); } catch (Throwable $x) { } }
        $antwort(['ok' => 1]);
    }
    if ($e === 'page_view') {
        $seite = Spur::pfad((string) ($d['p'] ?? '/'));
        Spur::ereignis('page_view', ['besuch' => $b, 'seite' => $seite]);
        /* Die Preisseiten sind der Preisrechner ohne Anmeldung (Richtpreis). */
        if (preg_match('~^/((de|en)/)?(prezzi|preise|pricing|prices)(\.html)?/?$~', $seite)) {
            Spur::ereignis('price_calculator_opened', ['besuch' => $b, 'seite' => $seite, 'meta' => ['art' => 'richtpreis']]);
        }
        $p = !empty($b['partner_id']) ? Partner::laden((int) $b['partner_id']) : null;
        /* Kampagnen-Besuch: die Frage ohne Partnernamen (das Skript wählt den Wortlaut nach „art“). */
        $antwort(['ok' => 1, 'frage' => Spur::sollFragen() ? 1 : 0, 'partner' => $p ? Partner::anzeigeName($p) : '',
                  'art' => $p ? 'partner' : 'kampagne', 'tage' => Spur::zuordnungTage()]);
    }
    if ($e === 'contact_form_opened') {
        $form = preg_replace('~[^a-z0-9_-]~', '', mb_strtolower((string) ($d['f'] ?? ''))) ?: 'formular';
        Spur::ereignis('contact_form_opened', ['besuch' => $b, 'seite' => Spur::pfad((string) ($d['p'] ?? '/')), 'meta' => ['formular' => mb_substr($form, 0, 20)]]);
        $antwort(['ok' => 1]);
    }
} catch (Throwable $ex) {
    error_log('t.php: ' . $ex->getMessage());
}
$antwort(['ok' => 0], 400);
