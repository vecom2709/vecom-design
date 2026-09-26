<?php
/* Zählpixel für vecom-design.it
   Speichert KEINE IP-Adressen, setzt KEINE Cookies, lädt nichts von fremden
   Servern. Erfasst nur: Datum, Stunde, Herkunfts-Domain, Geräteart, Seite,
   Kampagne (utm_source).

   DIE HERKUNFT KAM NIE AN (gemessen 26.09.2026): Als <img> eingebunden, trägt
   die Anfrage als Referer die EIGENE Seite, nie Google oder Instagram -- und
   die eigene Domain wird unten verworfen. Jede Zeile hatte eine leere Herkunft.
   Jetzt schickt die Seite document.referrer als ?r= mit; das <img> bleibt nur
   für Besucher ohne JavaScript (dann eben ohne Herkunft). */

$datei = __DIR__ . '/besuche.csv';
$ua    = $_SERVER['HTTP_USER_AGENT'] ?? '';

$istBot = preg_match('~bot|crawl|spider|slurp|bing|yandex|baidu|duckduck|'
        . 'facebookexternal|preview|monitor|curl|wget|python|java/|'
        . 'headless|lighthouse|pingdom|uptime|semrush|ahrefs|mj12|dotbot~i', $ua);

if (!$istBot && $ua !== '') {
    $ref  = isset($_GET['r']) ? mb_substr((string) $_GET['r'], 0, 300) : ($_SERVER['HTTP_REFERER'] ?? '');
    $host = $ref ? (string) parse_url($ref, PHP_URL_HOST) : '';
    $host = preg_replace('~^www\.~', '', strtolower($host));
    if ($host === 'vecom-design.it') { $host = ''; }   // eigene Klicks sind keine Quelle
    $host = preg_replace('~[^a-z0-9.\-]~', '', $host);
    $geraet = preg_match('~Mobile|Android|iPhone|iPad|iPod~i', $ua) ? 'Handy' : 'Rechner';

    // Seite und Kampagne: nur harmlose Zeichen, kurz -- die Adresse ist öffentlich.
    $seite  = substr((string) preg_replace('~[^a-z0-9/_.\-]~', '', strtolower((string) ($_GET['p'] ?? ''))), 0, 60);
    $quelle = substr((string) preg_replace('~[^a-z0-9_\-]~', '', strtolower((string) ($_GET['s'] ?? ''))), 0, 30);

    $zeile = date('Y-m-d') . "\t" . date('H') . "\t" . $host . "\t" . $geraet . "\t" . $seite . "\t" . $quelle . "\n";
    @file_put_contents($datei, $zeile, FILE_APPEND | LOCK_EX);
}

header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
