<?php
declare(strict_types=1);
/* ==========================================================================
   wa-webhook.php — Meta meldet hier Antworten und Zustellfehler von WhatsApp
   (28.09.2026, Uwe: Ja zu V3). Was damit passiert: WhatsAppCloud::verarbeiten.
   Seit Z5 auch das Werbeformular der Facebook-Seite (object „page“, Feld
   „leadgen“): MetaSeite::verarbeiten -- dieselbe App, dasselbe Geheimnis.

     GET   Anmeldung des Webhooks bei Meta (hub.challenge mit dem Prüfwort)
     POST  Nachrichten -- nur mit gültiger Signatur (X-Hub-Signature-256,
           App-Geheimnis). Ohne Geheimnis nimmt die Seite nichts an.
   ========================================================================== */

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
if (!is_file(__DIR__ . '/app/config.local.php')) { http_response_code(503); exit; }
foreach (['Config', 'Db', 'Status', 'Auth', 'Fmt', 'Events', 'Texte', 'Akquise', 'AkquiseGate', 'AkquiseText', 'WhatsAppCloud', 'MetaSeite', 'MkKommentar'] as $k) { require_once __DIR__ . "/app/src/$k.php"; }
date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $pw = (string) ($_GET['hub_verify_token'] ?? '');
    if (($_GET['hub_mode'] ?? '') === 'subscribe' && $pw !== '' && hash_equals(WhatsAppCloud::pruefwort(), $pw)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo preg_replace('~[^0-9A-Za-z_-]~', '', (string) ($_GET['hub_challenge'] ?? ''));
        exit;
    }
    http_response_code(403); exit;
}
$roh = (string) file_get_contents('php://input');
if (strlen($roh) > 500000 || !WhatsAppCloud::signaturGut($roh, (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? ''))) { http_response_code(403); exit; }
try {
    $nutzlast = (array) (json_decode($roh, true) ?: []);
    /* S1 (01.10.2026): Kommentare der Seite (Feld „feed“) und von Instagram („comments“) → MkKommentar. */
    if (($nutzlast['object'] ?? '') === 'page') { MetaSeite::verarbeiten($nutzlast); MkKommentar::verarbeiten($nutzlast); }
    elseif (($nutzlast['object'] ?? '') === 'instagram') { MkKommentar::verarbeiten($nutzlast); }
    else { WhatsAppCloud::verarbeiten($nutzlast); }
} catch (Throwable $e) {
    try { Events::melden('whatsapp_fehler', 'WhatsApp-Webhook: Verarbeitung gescheitert', 'schlecht', mb_substr($e->getMessage(), 0, 300), 'akquise/regeln'); } catch (Throwable $x) { }
}
http_response_code(200);
echo 'ok';
