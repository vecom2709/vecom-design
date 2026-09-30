<?php
declare(strict_types=1);
/* ==========================================================================
   k.php — der Kampagnenlink: /k/CODE und /k/CODE/WERBEMITTEL
   (Growth Engine Phase 3, 30.09.2026, Uwe: „ja“).

   Für Beiträge, Anzeigen, Newsletter und Flyer. Zählt den Klick, legt einen
   anonymen Besuch in der Spur an (wie ein Partnerlink, siehe Spur.php) und
   leitet mit utm-Angaben auf die eigene Zielseite weiter.

   Ein unbekannter, pausierter oder beendeter Code führt still auf die
   Startseite — ein alter Flyer soll nie auf einer Fehlerseite enden.
   Das Ziel ist immer eine Seite dieser Website (MkKampagne::zielOk), nie
   eine fremde: Der Link darf keine offene Weiterleitung sein.

   Nicht gezählt: Vorschau (?n=1), Programme, und Uwes eigener Browser
   (angemeldet in der Verwaltung) — sonst zählte jeder Test als Klick.
   ========================================================================== */

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: strict-origin-when-cross-origin');

$ziel = '/';
/* Wie bei p.php: MultiViews kann /k/CODE ohne ?c= an dieses Skript geben. */
if (!isset($_GET['c']) && preg_match('~^/k/([A-Za-z0-9-]{3,24})(?:/([A-Za-z0-9-]{1,12}))?/?(?:[?#]|$)~', (string) ($_SERVER['REQUEST_URI'] ?? ''), $pfad)) {
    $_GET['c'] = $pfad[1];
    if (isset($pfad[2]) && $pfad[2] !== '' && !isset($_GET['w'])) { $_GET['w'] = $pfad[2]; }
}
if (is_file(__DIR__ . '/app/config.local.php')) {
    try {
        foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'Texte', 'Sprache', 'Partner', 'Spur', 'MkKampagne'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
        [$kamp, $wm] = MkKampagne::ausCode((string) ($_GET['c'] ?? ''), (string) ($_GET['w'] ?? ''));
        if ($kamp !== null) {
            $ziel = MkKampagne::zielAdresse($kamp, $wm);
            $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
            if (!isset($_GET['n']) && $ua !== '' && !Partner::istRoboter($ua) && !isset($_COOKIE['vecomadmin'])) {
                $sprache = preg_match('~^/(de|en)/~', (string) $kamp['ziel'], $m) ? $m[1] : 'it';
                $pfadK = '/k/' . $kamp['code'] . ($wm !== null ? '/' . $wm['code'] : '');
                Spur::kampagnenBesuch($kamp, $wm, ['ua' => $ua, 'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                    'referrer' => (string) ($_SERVER['HTTP_REFERER'] ?? ''), 'sprache' => $sprache, 'get' => MkKampagne::utm($kamp, $wm),
                    'einstieg' => $pfadK, 'ref_link' => $pfadK]);
            }
        }
    } catch (Throwable $e) {
        error_log('k.php: ' . $e->getMessage());
    }
}
header('Location: ' . $ziel, true, 302);
exit;
