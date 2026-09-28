<?php
declare(strict_types=1);
/* ==========================================================================
   richtpreis.php — der Live-Richtpreis (28.09.2026, Uwe: Ja zu R1–R4).

   Nimmt die Antworten des Konfigurators entgegen (POST, JSON), rechnet sie
   durch Baukasten::live -- also durch dieselbe Formel wie Ergebnisseite und
   Angebot -- und gibt die gerundete Spanne zurück. Speichert nichts, legt
   nichts an, kennt keinen Kunden. Genutzt von bedarf.php, dem persönlichen
   Bereich und dem Preis-Baustein der Partnerseite.

   Eingabe:  {"antworten": {...}, "bis": 2, "lang": "de"}
   Ausgabe:  {"zeigen": true, "von": 45000, "bis": 60000, "monat": 0,
              "text": "450 – 600 €", "monatText": "", "ab": "ab 325 €"}
   ========================================================================== */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$aus = static function (array $d, int $status = 200): never {
    http_response_code($status);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
};
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $aus(['zeigen' => false], 405); }
if (!is_file(__DIR__ . '/app/config.local.php')) { $aus(['zeigen' => false], 503); }
foreach (['Config', 'Db', 'Texte', 'Baukasten', 'Bedarf'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }

$roh = (string) file_get_contents('php://input', false, null, 0, 8192);
$ein = json_decode($roh, true);
if (!is_array($ein)) { $aus(['zeigen' => false], 400); }
$sprache = in_array((string) ($ein['lang'] ?? ''), ['it', 'de', 'en'], true) ? (string) $ein['lang'] : 'it';
$bis = max(0, min(Baukasten::schrittZahl(), (int) ($ein['bis'] ?? 0)));

try {
    if ((string) Db::wert("SELECT svalue FROM settings WHERE skey = 'bedarf_spanne_zeigen'", [], '1') !== '1') { $aus(['zeigen' => false]); }
    $katalog = Baukasten::katalog();
    $antworten = Bedarf::bereinigen(is_array($ein['antworten'] ?? null) ? $ein['antworten'] : []);
    $r = Baukasten::live($antworten, $bis, $katalog);
    $ab = Baukasten::ab($katalog);
} catch (Throwable $e) {
    $aus(['zeigen' => false], 503);
}
$aus(Baukasten::liveAntwort($r, $ab, $sprache));
