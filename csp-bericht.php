<?php
declare(strict_types=1);
/* ==========================================================================
   csp-bericht.php — nimmt Meldungen der Content-Security-Policy entgegen
   (Etappe 0b, 05.10.2026). Verwaltung und Partnerbereich schicken ihre
   Regel vorerst nur „zum Melden“ (Csp::melden); der Browser meldet hierher,
   was er blockiert HÄTTE. Gespeichert wird zusammengefasst und ohne Abfrage,
   ohne IP (Csp::bericht). Antwort immer 204 — der Endpunkt verrät nichts.
   ========================================================================== */
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && is_file(__DIR__ . '/app/config.local.php')) {
    try {
        foreach (['Config', 'Db', 'Csp'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
        $roh = (string) file_get_contents('php://input', false, null, 0, Csp::GROESSE + 1);
        Csp::bericht($roh, (string) ($_GET['b'] ?? ''));
    } catch (Throwable $e) { }
}
http_response_code(204);
