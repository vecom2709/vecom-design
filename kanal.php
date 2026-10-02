<?php
declare(strict_types=1);
/* ==========================================================================
   kanal.php — der Weg in den Telegram-Kanal, mit Zählung je Ort
   (01.10.2026, Uwe: „Alles“ — Vorschlag 1 und 7).

   /kanal.php?w=fuss | check | mail | kunde | qr | profil

   Leitet auf den eigenen Einladungslink des Orts (TelegramWachstum::
   KANAL_ORTE) — Telegram meldet dann, über welchen Link jemand beitrat,
   und der Beitritt zählt für diesen Ort. Diese Seite ruft selbst NIE bei
   Telegram an: Angelegt werden die Links in der Verwaltung und im
   Cronlauf. Fehlt einer, geht es zum öffentlichen Kanal; ein Ziel außer
   t.me oder der eigenen Startseite gibt es nicht (keine offene Weiterleitung).
   Gespeichert wird hier nichts.
   ========================================================================== */
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');

$ziel = '/';
if (is_file(__DIR__ . '/app/config.local.php')) {
    try {
        foreach (['Config', 'Db', 'Telegram', 'TelegramWachstum'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
        /* Ohne w, aber über eine Kampagne (/k/… hängt utm_ an): ein Beitrag, der den Kanal bekannt macht (02.10.2026). */
        $ziel = TelegramWachstum::kanalZiel(strtolower((string) ($_GET['w'] ?? (isset($_GET['utm_source']) ? 'social' : ''))));
    } catch (Throwable $e) { $ziel = '/'; }
}
header('Location: ' . $ziel, true, 302);
exit;
