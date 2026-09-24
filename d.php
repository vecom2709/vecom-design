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
    'demo-villa', 'demo-auto', 'demo-shop', 'demo-tisch',
    'demo-wein', 'wein-stufe-1', 'wein-stufe-2', 'wein-stufe-3', 'wein-kiste',
    'demo-schmuck', 'schmuck-stufe-1', 'schmuck-stufe-2', 'schmuck-stufe-3', 'schmuck-stufe-4', 'schmuck-karat',
    'demo-kueche', 'kueche-stufe-1', 'kueche-stufe-2', 'kueche-planer', 'kueche-oeffnen', 'cta-kueche',
    'demo-gastro', 'gastro-stufe-1', 'gastro-stufe-2', 'gastro-stufe-3', 'logo',
    'demo-salon', 'salon-stufe-1', 'salon-stufe-2',
    // Haarfarben statt Salonstuhl (24.09.2026)
    'haar-drehen', 'tisch-wahl', 'tisch-kunde', 'haar-kunde', 'produkt-kunde', 'auto-kunde', 'kueche-kunde', 'haar-schwarz', 'haar-kastanie', 'haar-kupfer', 'haar-balayage', 'haar-aschblond', 'haar-rosegold',
    'demo-lkw', 'lkw-stufe-1', 'lkw-stufe-2', 'lkw-stufe-3', 'lkw-ladung', 'ka-cta', 'ka-gesendet',
    // Serienautos (23.09.2026): Fahrerplatz, Ausstattung, Zerlegen in Stufen
    'kleinwagen-innen', 'mittelklasse-innen',
    'kleinwagen-ausstattung-stoff-anthrazit', 'kleinwagen-ausstattung-stoff-grau-blau', 'kleinwagen-ausstattung-kunstleder-hell',
    'mittelklasse-ausstattung-stoff-anthrazit', 'mittelklasse-ausstattung-leder-cognac', 'mittelklasse-ausstattung-leder-elfenbein',
    'kleinwagen-stufe-1', 'kleinwagen-stufe-2', 'kleinwagen-stufe-3', 'kleinwagen-stufe-4',
    'mittelklasse-stufe-1', 'mittelklasse-stufe-2', 'mittelklasse-stufe-3', 'mittelklasse-stufe-4',
    'whatsapp',
    // E-Mail-Einstieg (24.09.2026): aus welchem Feld die Adresse kam
    'zugang-hero', 'zugang-kontakt', 'zugang-vorschau',
    // Ihre Seite in 30 Sekunden (N1, 24.09.2026): Vorschau gezeigt, Branche
    'vorschau-gezeigt',
    'vorschau-logo',
    // Dashboard-Einblick (V1, 24.09.2026): jemand hat sich die Ansichten angesehen
    'einblick',
    // Umgesetzte Arbeiten (24.09.2026): welche Fallstudie gewählt, welche Seite besucht
    'arbeit-cavaleri', 'arbeit-jonika', 'arbeit-mensaena', 'arbeit-trendonix',
    'arbeit-besuch-cavaleri', 'arbeit-besuch-jonika', 'arbeit-besuch-mensaena', 'arbeit-besuch-trendonix',
    'arbeit-vergleich-cavaleri', 'arbeit-vergleich-jonika', 'arbeit-vergleich-mensaena', 'arbeit-vergleich-trendonix',
    // Mein Betrieb ist … (24.09.2026): welche Branche gewählt, welche Demo geöffnet
    'betrieb-restaurant', 'betrieb-friseur', 'betrieb-autohaus', 'betrieb-kueche', 'betrieb-juwelier', 'betrieb-weingut', 'betrieb-spedition', 'betrieb-moebel', 'betrieb-mode', 'betrieb-immobilien',
    'betrieb-oeffnen-restaurant', 'betrieb-oeffnen-friseur', 'betrieb-oeffnen-autohaus', 'betrieb-oeffnen-kueche', 'betrieb-oeffnen-juwelier', 'betrieb-oeffnen-weingut', 'betrieb-oeffnen-spedition', 'betrieb-oeffnen-moebel', 'betrieb-oeffnen-mode', 'betrieb-oeffnen-immobilien',
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
