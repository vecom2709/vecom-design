<?php
declare(strict_types=1);
/* ==========================================================================
   p.php — der Partnerlink: /p/CODE (26.09.2026).

   Zählt den Klick (je Tag, ohne IP), legt den Code für DIESEN Besuch in
   einen Keks ohne Ablaufdatum -- er verschwindet, wenn der Browser zugeht --
   und schickt auf die Startseite. Die Website verspricht keine
   Tracking-Cookies; dieser Keks trägt nur den Code des Partners, nichts über
   den Besucher, und lebt nicht über den Besuch hinaus. Fest zugeordnet wird
   erst, wenn der Besucher Kunde wird (Partner::ausBesuch).

   Ein unbekannter oder pausierter Code führt still auf die Startseite:
   Der Besucher soll nie eine Fehlerseite sehen, nur weil ein Partner
   aufgehört hat.
   ========================================================================== */

$ziel = '/';
$konfig = __DIR__ . '/app/config.local.php';
if (is_file($konfig)) {
    try {
        foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'Sprache', 'Partner'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
        $sp = Sprache::ausAnfrage();
        $ziel = $sp === 'it' ? '/' : '/' . $sp . '/';
        $p = Partner::ausCode((string) ($_GET['c'] ?? ''));
        if ($p !== null) {
            Partner::klick((int) $p['id']);
            setcookie(Partner::KEKS, (string) $p['code'], [
                'path' => '/', 'secure' => ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off',
                'httponly' => true, 'samesite' => 'Lax',
            ]);
        }
    } catch (Throwable $e) { /* Startseite trotzdem */ }
}
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
header('Location: ' . $ziel, true, 302);
