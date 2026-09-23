<?php
/* d.php — Zähler für die Demos im Erlebnisteil (23.09.2026)

   WOZU
   Welche Demo verkauft? Gezählt wird, wie oft jemand die Villa oder ein
   Produkt selbst dreht, das Auto zerlegt, die Schuh-Details öffnet und
   einen der Knöpfe „So etwas für …“ drückt. Ob daraus eine Anfrage wird,
   steht bereits in der Anfrage selbst (erste Zeile „Ausgangspunkt: …“).

   WIE z.php: KEINE IP-Adresse, KEIN Cookie, nichts von fremden Servern.
   Gespeichert werden Datum, Stunde, Ereignis und Geräteart. Nur bekannte
   Ereignisnamen kommen durch — die Adresse ist öffentlich.

   ?summe=1 liefert die Zählung der letzten 30 Tage als JSON (nur Summen). */

const EREIGNISSE = [
    'villa-drehen', 'auto-drehen', 'auto-zerlegen', 'schuh-drehen', 'schuh-details',
    'schuh-korb', 'tisch-drehen', 'cta-auto', 'cta-shop',
    'modell-kleinwagen', 'modell-mittelklasse', 'modell-auto',
];
$datei = __DIR__ . '/demo.csv';

if (isset($_GET['summe'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    header('Access-Control-Allow-Origin: *');
    $ab = date('Y-m-d', strtotime('-30 days'));
    $zahl = array_fill_keys(EREIGNISSE, 0);
    if (is_readable($datei) && ($fh = fopen($datei, 'r'))) {
        while (($z = fgets($fh)) !== false) {
            $t = explode("\t", trim($z));
            if (count($t) >= 3 && $t[0] >= $ab && isset($zahl[$t[2]])) { $zahl[$t[2]]++; }
        }
        fclose($fh);
    }
    echo json_encode(['seit' => $ab, 'zahl' => $zahl]);
    exit;
}

$e  = (string) ($_GET['e'] ?? $_POST['e'] ?? '');
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$istBot = preg_match('~bot|crawl|spider|slurp|preview|headless|lighthouse|curl|wget|python~i', $ua);
if (in_array($e, EREIGNISSE, true) && !$istBot && $ua !== '') {
    $geraet = preg_match('~Mobile|Android|iPhone|iPad|iPod~i', $ua) ? 'Handy' : 'Rechner';
    @file_put_contents($datei, date('Y-m-d') . "\t" . date('H') . "\t" . $e . "\t" . $geraet . "\n", FILE_APPEND | LOCK_EX);
}
http_response_code(204);
header('Cache-Control: no-store');
