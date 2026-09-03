<?php
declare(strict_types=1);
/* ==========================================================================
   Nimmt die Meldungen von Stripe entgegen.

   Diese Adresse wird bei Stripe unter "Entwickler → Webhooks" eingetragen:
       https://vecom-design.it/stripe-webhook.php

   Drei Dinge macht die Datei, mehr nicht:
   1. Unterschrift pruefen — ohne sie koennte jeder eine bezahlte Zahlung
      vortaeuschen. Nicht unterschriebene Aufrufe werden abgewiesen.
   2. Ereignis festhalten. Die Tabelle hat einen Eindeutigkeitsschluessel auf
      Anbieter + Ereignis-ID; dasselbe Ereignis kann deshalb nie zweimal
      verarbeitet werden, auch wenn Stripe es mehrfach schickt.
   3. Die Ereignislogik anstossen — genau dieselbe, die auch das Buchen von
      Hand in der Verwaltung verwendet.

   Das Dashboard fragt nie bei Stripe nach. Es liest nur, was hier ankam.
   ========================================================================== */

$konfig = __DIR__ . '/app/config.local.php';
if (!is_file($konfig)) { http_response_code(503); exit('nicht eingerichtet'); }

foreach (['Config', 'Db', 'Status', 'Csrf', 'Auth', 'Fmt', 'Events'] as $k) {
    require_once __DIR__ . "/app/src/$k.php";
}
require_once __DIR__ . '/app/src/Zahlung/Anbieter.php';
require_once __DIR__ . '/app/src/Zahlung/Stripe.php';

date_default_timezone_set((string) Config::get('zeitzone', 'Europe/Rome'));

$rohtext = (string) file_get_contents('php://input');
$kopf    = function_exists('getallheaders') ? (array) getallheaders() : [];
if (!$kopf && isset($_SERVER['HTTP_STRIPE_SIGNATURE'])) {
    $kopf = ['Stripe-Signature' => $_SERVER['HTTP_STRIPE_SIGNATURE']];
}

$stripe   = new StripeAnbieter();
$ereignis = $stripe->ereignisPruefen($rohtext, $kopf);

if ($ereignis === null) {
    // Bewusst wortkarg nach aussen, aber im Protokoll nachvollziehbar.
    try {
        Db::insert('webhook_events', [
            'provider' => 'stripe', 'event_id' => 'ungueltig-' . bin2hex(random_bytes(8)),
            'event_type' => 'signatur_ungueltig', 'signature_ok' => 0, 'status' => 'fehler',
            'payload' => mb_substr($rohtext, 0, 4000), 'error' => 'Unterschrift nicht gültig oder zu alt',
        ]);
        Db::run("UPDATE integrations SET status='fehler', last_error=? WHERE ikey='stripe'",
            ['Ein Webhook kam mit ungültiger Unterschrift an (' . date('d.m.Y H:i') . ')']);
    } catch (Throwable $e) { /* nicht weiter stoeren */ }
    http_response_code(400);
    exit('ungueltig');
}

/* Festhalten. Ist das Ereignis schon da, war es schon dran — dann nur 200. */
try {
    $webhookId = Db::insert('webhook_events', [
        'provider' => 'stripe', 'event_id' => $ereignis['id'], 'event_type' => $ereignis['typ'],
        'signature_ok' => 1, 'status' => 'empfangen', 'payload' => mb_substr($rohtext, 0, 60000),
    ]);
} catch (Throwable $e) {
    http_response_code(200);
    exit('bereits verarbeitet');
}

/* Verarbeiten. */
try {
    $o = $ereignis['daten']['data']['object'] ?? [];

    switch ($ereignis['typ']) {
        case 'checkout.session.completed':
        case 'checkout.session.async_payment_succeeded':
            $zahlungId = (int) ($o['metadata']['zahlung_id'] ?? $o['client_reference_id'] ?? 0);
            if ($zahlungId <= 0) { throw new RuntimeException('Keine Zahlungsnummer im Ereignis.'); }
            if (($o['payment_status'] ?? '') !== 'paid') { throw new RuntimeException('Sitzung ist nicht bezahlt.'); }
            Events::zahlungBestaetigen($zahlungId, (string) ($o['payment_intent'] ?? $o['id']), 'stripe');
            break;

        case 'checkout.session.async_payment_failed':
        case 'payment_intent.payment_failed':
            $zahlungId = (int) ($o['metadata']['zahlung_id'] ?? 0);
            if ($zahlungId > 0) {
                Events::zahlungFehlgeschlagen($zahlungId,
                    (string) ($o['last_payment_error']['message'] ?? 'von Stripe abgelehnt'));
            }
            break;

        case 'charge.refunded':
            $ref = (string) ($o['payment_intent'] ?? '');
            $z = $ref !== '' ? Db::one('SELECT * FROM payments WHERE provider_ref = ?', [$ref]) : null;
            if ($z) {
                $voll = (int) ($o['amount_refunded'] ?? 0) >= (int) ($o['amount'] ?? 0);
                Db::update('payments', (int) $z['id'], ['status' => $voll ? 'rueckerstattet' : 'teilweise_erstattet']);

                // Eine erstattete Zahlung nimmt die Empfehlung mit, die an ihr
                // haengt. Nur bei voller Erstattung: Wer die Haelfte zurueck
                // bekommt, hat die Website trotzdem gekauft.
                if ($voll) {
                    try {
                        require_once __DIR__ . '/app/src/Empfehlung.php';
                        Empfehlung::beiRueckerstattung((int) $z['order_id'], 'Zahlung erstattet');
                    } catch (Throwable $e) { /* dann eben von Hand */ }
                }
                Events::protokoll('zahlung_erstattet',
                    ($voll ? 'Rückerstattung' : 'Teilerstattung') . ': ' . Fmt::geld((int) ($o['amount_refunded'] ?? 0)),
                    null, (int) $z['order_id']);
                Events::melden('zahlung_erstattet', $voll ? 'Zahlung zurückerstattet' : 'Teilweise erstattet',
                    'warnung', Fmt::geld((int) ($o['amount_refunded'] ?? 0)), '/bestellungen/' . (int) $z['order_id']);
            }
            break;

        case 'charge.dispute.created':
            Events::melden('zahlung_streit', 'Rückbuchung eröffnet', 'schlecht',
                'Ein Kunde hat eine Zahlung bei seiner Bank angefochten. Bei Stripe ansehen.', '/zahlungen');
            break;

        default:
            // Alles Übrige wird festgehalten, aber nicht verarbeitet.
            break;
    }

    Db::update('webhook_events', $webhookId, ['status' => 'verarbeitet', 'processed_at' => date('Y-m-d H:i:s')]);
    Db::run("UPDATE integrations SET status='verbunden', last_sync_at=NOW(), last_error=NULL WHERE ikey='stripe'");
    http_response_code(200);
    echo 'ok';
} catch (Throwable $e) {
    Db::update('webhook_events', $webhookId, ['status' => 'fehler', 'error' => mb_substr($e->getMessage(), 0, 480)]);
    Db::run("UPDATE integrations SET status='fehler', last_error=? WHERE ikey='stripe'", [mb_substr($e->getMessage(), 0, 480)]);
    Events::melden('integration_fehler', 'Stripe: ein Ereignis konnte nicht verarbeitet werden', 'schlecht',
        mb_substr($e->getMessage(), 0, 200), '/integrationen');
    // 200 zurueckgeben waere bequem, verschleiert aber Fehler. 500 sorgt
    // dafuer, dass Stripe es spaeter noch einmal versucht.
    http_response_code(500);
    echo 'fehler';
}
