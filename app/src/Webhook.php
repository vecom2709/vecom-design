<?php
declare(strict_types=1);

/**
 * Nimmt ein Ereignis eines Zahlungsanbieters an -- oder entscheidet, warum nicht.
 *
 * DER FEHLER, DEN DIESE KLASSE BEHEBT (gefunden am 21.09.2026)
 *
 * stripe-webhook.php hielt jedes Ereignis zuerst in einer Tabelle fest, mit
 * einem eindeutigen Schluessel auf Anbieter + Ereignis. Scheiterte das
 * Festhalten, hiess es "bereits verarbeitet", Antwort 200. Scheiterte danach
 * die Verarbeitung, stand das Ereignis mit Status "fehler" in der Tabelle, und
 * die Antwort war 500 -- ausdruecklich, "damit Stripe es spaeter noch einmal
 * versucht".
 *
 * Stripe hat es auch versucht. Mit derselben Ereignis-ID. Das Festhalten
 * scheiterte am eindeutigen Schluessel, die Antwort war 200, und Stripe hielt
 * die Sache fuer erledigt. Der Wiederholungsversuch, fuer den die 500 gedacht
 * war, landete jedes Mal im Papierkorb.
 *
 * Und umgekehrt: JEDER Fehler beim Festhalten galt als "schon da" -- auch eine
 * Datenbank, die gerade nicht erreichbar war. Dann antwortete die Seite 200 auf
 * ein Ereignis, das sie nie gesehen hatte.
 *
 * JETZT
 *
 *   neu                         -> verarbeiten
 *   schon da, verarbeitet       -> 200, nichts tun
 *   schon da, gescheitert       -> noch einmal verarbeiten
 *   schon da, laeuft gerade     -> 409: Stripe soll spaeter wiederkommen.
 *                                  Eine 200 hier hiesse "erledigt", auch wenn
 *                                  der andere Lauf gleich scheitert.
 *   schon da, haengt seit Minuten -> noch einmal verarbeiten
 *   Datenbank weg               -> 500
 */
final class Webhook
{
    /** Wie lange ein "empfangen" als laufend gilt, bevor es als haengend zaehlt. */
    public const LAEUFT_SEKUNDEN = 300;

    /**
     * @return array{weiter:bool, id:?int, code:int, text:string}
     */
    public static function annehmen(string $anbieter, string $ereignisId, string $typ, string $rohtext): array
    {
        try {
            $id = Db::insert('webhook_events', [
                'provider' => $anbieter, 'event_id' => $ereignisId, 'event_type' => $typ,
                'signature_ok' => 1, 'status' => 'empfangen', 'payload' => mb_substr($rohtext, 0, 60000),
            ]);
            return ['weiter' => true, 'id' => (int) $id, 'code' => 200, 'text' => 'ok'];
        } catch (Throwable $e) {
            if (!Db::doppelt($e, 'uq_webhook_provider_event')) {
                return ['weiter' => false, 'id' => null, 'code' => 500, 'text' => 'fehler'];
            }
        }

        $alt = Db::one('SELECT id, status, received_at FROM webhook_events WHERE provider = ? AND event_id = ?',
            [$anbieter, $ereignisId]);
        if (!$alt) {
            return ['weiter' => false, 'id' => null, 'code' => 500, 'text' => 'fehler'];
        }
        $status = (string) $alt['status'];
        if ($status === 'verarbeitet') {
            return ['weiter' => false, 'id' => (int) $alt['id'], 'code' => 200, 'text' => 'bereits verarbeitet'];
        }

        $alter = time() - (int) strtotime((string) $alt['received_at']);
        if ($status === 'empfangen' && $alter < self::LAEUFT_SEKUNDEN) {
            return ['weiter' => false, 'id' => (int) $alt['id'], 'code' => 409, 'text' => 'in arbeit'];
        }

        /* Noch einmal -- aber nur einer. Kommen zwei Wiederholungen
           gleichzeitig, schaltet nur die erste den Status um; die zweite
           findet nichts mehr umzuschalten und bekommt 409. */
        $st = Db::run(
            "UPDATE webhook_events SET status = 'empfangen', error = NULL, received_at = NOW()
              WHERE id = ? AND status = ?", [(int) $alt['id'], $status]);
        if ($st->rowCount() !== 1) {
            return ['weiter' => false, 'id' => (int) $alt['id'], 'code' => 409, 'text' => 'in arbeit'];
        }
        return ['weiter' => true, 'id' => (int) $alt['id'], 'code' => 200, 'text' => 'ok'];
    }
}
