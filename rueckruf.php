<?php
declare(strict_types=1);
/* ==========================================================================
   rueckruf.php — „Rufen Sie mich zurück“ von der Website (26.09.2026,
   Uwe: „ja“ zu Vorschlag 7).

   Kein eigenes Postfach: Der Wunsch landet genau dort, wo Manuelas
   Rückrufwünsche liegen (Telefon::melden) -- in der Liste „Heute anrufen“
   unter Telefon, mit Nummer und Zeitfenster. Zwei Listen für dieselbe Sache
   wären zwei Stellen, an denen man vergessen kann nachzusehen.

   Nie über das Nachschlagen des Telefons zugeordnet (quelle=website), siehe
   Telefon::kundeImGespraech. Antwortet immer mit JSON, nie mit einer
   Datenbankmeldung.
   ========================================================================== */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');

/* Ohne JavaScript schickt das Formular klassisch: dann zurück zur Seite,
   nicht auf eine nackte JSON-Antwort. */
$json = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
$antwort = static function (int $code, array $d) use ($json): never {
    if (!$json && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        header('Location: /?rueckruf=' . (!empty($d['ok']) ? 'ok' : 'fehler') . '#contact', true, 303);
        exit;
    }
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $antwort(405, ['ok' => false]); }
$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { $antwort(503, ['ok' => false, 'grund' => 'panne']); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events', 'Kunde', 'Telefon', 'Texte'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

$name    = trim((string) ($_POST['name'] ?? ''));
$telefon = trim((string) ($_POST['telefon'] ?? ''));
$wann    = (string) ($_POST['wann'] ?? '');
$anliegen = trim((string) ($_POST['anliegen'] ?? ''));

/* Eine Nummer, die man wählen kann: mindestens sechs Ziffern. Mehr prüfen
   wir nicht -- „0922 …“, „+39 …“, „340-…“ schreiben Menschen alle. */
if ($name === '' || preg_match_all('/\d/', $telefon) < 6 || mb_strlen($telefon) > 40) {
    $antwort(422, ['ok' => false, 'grund' => 'angaben']);
}
$fenster = ['heute_nachmittag' => 'heute Nachmittag', 'morgen_vormittag' => 'morgen Vormittag',
            'morgen_nachmittag' => 'morgen Nachmittag', 'egal' => 'jederzeit'];
$erreichbar = $fenster[$wann] ?? 'jederzeit';

try {
    /* Bremse gegen Massenversand: mehr als zehn Website-Rückrufe in einer
       Stunde sind kein Andrang, sondern ein Skript. Dann freundlich ablehnen
       -- Uwe soll nicht hundert Zettel abarbeiten. */
    $letzteStunde = (int) Db::wert("SELECT COUNT(*) FROM activities WHERE type = 'telefon_melde' AND created_at >= NOW() - INTERVAL 1 HOUR
                                      AND meta LIKE '%\"quelle\":\"website\"%'", [], 0);
    if ($letzteStunde >= 10) { $antwort(429, ['ok' => false, 'grund' => 'viel']); }

    $r = Telefon::melden([
        'art' => 'rueckruf', 'quelle' => 'website', 'name' => mb_substr($name, 0, 120), 'telefon' => $telefon,
        'erreichbar' => $erreichbar,
        'text' => 'Rückruf-Wunsch über die Website' . ($anliegen !== '' ? ': ' . mb_substr($anliegen, 0, 1000) : '.'),
    ]);
    $antwort($r['ok'] ? 200 : 422, ['ok' => (bool) $r['ok']]);
} catch (Throwable $e) {
    try { Events::melden('rueckruf_fehler', 'Rückruf-Wunsch von der Website ging nicht durch', 'schlecht', $e->getMessage(), '/telefon'); }
    catch (Throwable $e2) { /* dann eben nicht */ }
    $antwort(500, ['ok' => false, 'grund' => 'panne']);
}
