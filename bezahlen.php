<?php
declare(strict_types=1);
/* ==========================================================================
   bezahlen.php — die Adresse, die in jeder Zahlungsmail steht.

   Eine Bezahlseite bei Stripe lebt hoechstens 24 Stunden. Diese Adresse
   haelt, solange die Rate offen ist, und besorgt beim Klick die passende
   Bezahlseite: die laufende, oder eine frische. Die ganze Ueberlegung steht
   in app/src/Bezahllink.php.

   Oeffentlich wie kunde.php: nie eine leere Seite, nie ein Datenbankfehler.
   Im Zweifel geht es auf die Kundenseite, wo der Stand steht.
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('Gerade nicht erreichbar.'); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'Kundenzugang', 'Bezahllink'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

// Der Schluessel steht in der Adresse — nicht weiterreichen, nicht zwischenspeichern.
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');

$token = trim((string) ($_GET['t'] ?? ''));
$rate  = (int) ($_GET['z'] ?? 0);
$basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');

try {
    $weg = Bezahllink::oeffnen($token, $rate);
    $ziel = (string) $weg['ziel'];
} catch (Throwable $e) {
    try {
        Events::melden('integration_fehler', 'Bezahllink konnte keine Bezahlseite öffnen', 'schlecht',
            mb_substr($e->getMessage(), 0, 200) . ' — Rate #' . $rate . '. Der Kunde landete auf seiner Seite.',
            '/zahlungen');
    } catch (Throwable $e2) { /* nicht weiter stoeren */ }
    $kunde = null;
    try { $kunde = Kundenzugang::ausToken($token); } catch (Throwable $e3) { }
    $ziel = $kunde ? Kundenzugang::link($token) : $basis . '/';
}

// Nur zu uns oder zu Stripe — nie irgendwohin, was in der Datenbank stuende.
if (!preg_match('~^https://(checkout\.stripe\.com/|' . preg_quote(parse_url($basis, PHP_URL_HOST) ?: 'vecom-design.it', '~') . '/)~i', $ziel)) {
    $ziel = $basis . '/';
}
header('Location: ' . $ziel, true, 303);
exit;
