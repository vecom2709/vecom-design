<?php
declare(strict_types=1);

/**
 * Meldungen, die sich selbst erledigt haben (26.09.2026, Uwe: „ja“ zu
 * Vorschlag 5).
 *
 * „Stripe Connect ist nicht aktiviert“ stand weiter da, als Connect längst
 * lief; „Neue Anfrage“ stand da, als die Anfrage längst beantwortet war. Wer
 * solche Zeilen dreimal wegklickt, liest die vierte nicht mehr -- und die
 * vierte ist dann die echte Störung.
 *
 * DIE REGEL: Eine Meldung wird nur dann als gelesen markiert, wenn die
 * Datenbank belegt, dass ihr Anlass vorbei ist. Nie auf Verdacht, nie nach
 * Zeit. Worum es geht, steht im Link der Meldung (/anfragen/7, /partner/3);
 * ohne erkennbaren Gegenstand bleibt sie stehen. Gelöscht wird nichts: Die
 * Meldung ist danach gelesen, nicht weg.
 */
final class Meldungen
{
    /**
     * Je Art: Frage an die Datenbank „ist das vorbei?“ -- bekommt die Meldung
     * (mit id, link, created_at) und die Nummer aus ihrem Link.
     *
     * @return array<string, callable(array, ?int): bool>
     */
    public static function regeln(): array
    {
        return [
            // Connect läuft wieder, sobald „Stripe Connect prüfen“ ok gesagt hat.
            'partner_stripe_connect' => static fn(array $m, ?int $id): bool =>
                (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'partner_stripe_connect'", [], '') === 'ok',
            // Partner-Nachricht gelesen (Partnerakte geöffnet) -- keine ungelesene dieses Partners mehr.
            'partner_nachricht' => static fn(array $m, ?int $id): bool => $id !== null
                && (int) Db::wert("SELECT COUNT(*) FROM partner_nachrichten WHERE partner_id = ? AND von = 'partner' AND gelesen_am IS NULL", [$id], 1) === 0,
            // Neue STRATO-Sitzung hinterlegt, NACH dem Alarm.
            'strato_zugang' => static function (array $m, ?int $id): bool {
                $seit = (string) Db::wert("SELECT svalue FROM settings WHERE skey = 'strato_sitzung_seit'", [], '');
                return $seit !== '' && strtotime($seit) >= strtotime((string) $m['created_at']);
            },
            // Anfrage ist nicht mehr neu (in Arbeit, erledigt) -- oder gelöscht.
            'anfrage_neu' => static fn(array $m, ?int $id): bool => $id !== null
                && (string) Db::wert('SELECT COALESCE(MAX(status), ?) FROM anfragen WHERE id = ?', ['weg', $id], 'weg') !== 'neu',
            // Bewerbung angenommen oder abgelehnt.
            'partner_bewerbung' => static fn(array $m, ?int $id): bool => $id !== null
                && (string) Db::wert('SELECT COALESCE(MAX(status), ?) FROM partner WHERE id = ?', ['weg', $id], 'weg') !== 'bewerbung',
            // Nichts mehr freizugeben.
            'partner_freigabe' => static fn(array $m, ?int $id): bool =>
                (int) Db::wert("SELECT COUNT(*) FROM partner_provisionen WHERE status = 'freigabe'", [], 1) === 0,
            // Die Wise-Überweisung ist bestätigt oder abgebrochen.
            'partner_wise' => static fn(array $m, ?int $id): bool => $id !== null
                && (int) Db::wert("SELECT COUNT(*) FROM partner_auszahlungen WHERE partner_id = ? AND weg = 'wise' AND status = 'offen'", [$id], 1) === 0,
            // Alle Nachrichten dieses Kunden sind gelesen.
            'nachricht_rein' => static fn(array $m, ?int $id): bool => $id !== null && str_starts_with((string) $m['link'], '/kunden/')
                && (int) Db::wert("SELECT COUNT(*) FROM messages WHERE customer_id = ? AND sender = 'kunde' AND read_at IS NULL", [$id], 1) === 0,
        ];
    }

    /** Die Nummer aus dem Link („/anfragen/7“ → 7), sonst null. */
    public static function nummer(?string $link): ?int
    {
        return preg_match('~^/[a-z_]+/(\d+)(?:[/?#]|$)~', (string) $link, $t) ? (int) $t[1] : null;
    }

    /** Markiert als gelesen, was sich erledigt hat. @return int wie viele */
    public static function aufraeumen(): int
    {
        $regeln = self::regeln();
        $arten = array_keys($regeln);
        $ph = implode(',', array_fill(0, count($arten), '?'));
        $offen = Db::all("SELECT id, type, link, created_at FROM notifications WHERE read_at IS NULL AND type IN ($ph)", $arten);
        $n = 0;
        foreach ($offen as $m) {
            try {
                if (($regeln[$m['type']])($m, self::nummer($m['link']))) {
                    Db::run('UPDATE notifications SET read_at = NOW() WHERE id = ? AND read_at IS NULL', [(int) $m['id']]);
                    $n++;
                }
            } catch (Throwable $e) {
                // Eine Regel, die nicht antworten kann, erledigt nichts.
            }
        }
        return $n;
    }
}
