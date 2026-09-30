<?php
declare(strict_types=1);
/* ==========================================================================
   telegram-webhook.php — hier ruft Telegram an (30.09.2026).

   Absichtlich dünn: Tür und Verteiler. Was der Bot sagt, steht in
   app/src/TelegramBot.php, die Leitung in app/src/Telegram.php.

   DIE TÜR

     * nur POST
     * nur mit dem Prüfwort im Kopf X-Telegram-Bot-Api-Secret-Token, das wir
       beim Anmelden festgelegt haben. Wer es nicht hat, bekommt 404 — wie
       beim Telefon- und Cron-Schlüssel erfährt er nicht einmal, ob es hier
       etwas gibt.
     * höchstens 256 KB. Ein Update mit Text und Knopf ist ein paar hundert
       Byte groß; alles darüber ist kein Gespräch.

   JEDES UPDATE GENAU EINMAL

   Telegram stellt ein Update erneut zu, wenn die Antwort ausbleibt oder
   kein 2xx ist. Webhook::annehmen (derselbe Mechanismus wie bei Stripe)
   merkt sich die update_id: schon verarbeitet → 200 und nichts tun, läuft
   gerade → 409 (später wiederkommen), Datenbank weg → 500 (auch später).
   Gespeichert wird dort nur die Art des Updates, nie der Text — Nachrichten
   von Interessenten gehören nicht in ein technisches Protokoll.

   NIE LEER AUSGEHEN

   Scheitert die Verarbeitung, bekommt der Mensch im Chat einen ruhigen Satz
   („Ihre bisherigen Angaben sind nicht verloren“), Uwe eine Meldung — und
   Telegram trotzdem 200. Eine 500 hieße: Telegram wiederholt dasselbe Update
   immer wieder und hält alle folgenden dieses Chats so lange zurück.
   ========================================================================== */

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

$aus = static function (int $code, string $text = ''): never {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $text;
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { $aus(404); }
if (!is_file(__DIR__ . '/app/config.local.php')) { $aus(404); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Texte', 'Events', 'Webhook', 'Telegram', 'TelegramBot'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

try {
    $soll = Telegram::pruefwort();
} catch (Throwable $e) {
    $aus(500);                                   // Datenbank weg: Telegram versucht es später
}
$ist = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
if ($soll === '' || $ist === '' || !hash_equals($soll, $ist)) { $aus(404); }

$laenge = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($laenge > 262144) { $aus(413); }
$roh = (string) file_get_contents('php://input', false, null, 0, 262145);
if (strlen($roh) > 262144) { $aus(413); }

$u = json_decode($roh, true);
if (!is_array($u) || !isset($u['update_id']) || !is_int($u['update_id'])) { $aus(400); }

$typ = isset($u['callback_query']) ? 'callback_query' : (isset($u['message']) ? 'message' : 'anderes');
$annahme = Webhook::annehmen('telegram', (string) $u['update_id'], $typ, json_encode(['typ' => $typ]));
if (!$annahme['weiter']) { $aus($annahme['code'], $annahme['text']); }
$eid = (int) $annahme['id'];

try {
    $vermerk = TelegramBot::verarbeiten($u);
    Db::update('webhook_events', $eid, ['status' => 'verarbeitet', 'event_type' => mb_substr($typ . ':' . $vermerk, 0, 80),
        'processed_at' => date('Y-m-d H:i:s')]);
    try { Telegram::setzen('tg_zuletzt', date('Y-m-d H:i:s')); } catch (Throwable $e) { }
} catch (Throwable $e) {
    try { Db::update('webhook_events', $eid, ['status' => 'fehler', 'error' => mb_substr($e->getMessage(), 0, 480)]); } catch (Throwable $x) { }
    TelegramBot::panne($u, $e);
}
$aus(200, 'ok');
