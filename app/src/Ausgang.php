<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Automation.php';

/**
 * Zurückgehaltene Nachrichten während des Not-Aus (AI Office Stufe 0, 06.10.2026).
 *
 * WARUM ES DAS BRAUCHT
 *
 * Der Not-Aus versprach „nichts geht raus“ und hielt doch nur den Cron an.
 * Ein Stripe-Webhook buchte eine Zahlung und schickte im selben Zug Beleg,
 * Auftragsbestätigung und Fragebogen-Einladung — mitten im Not-Aus. Die
 * Buchung soll bleiben (sie ist eine Tatsache), die Mails nicht.
 *
 * WARUM ZURÜCKHALTEN STATT WEGWERFEN
 *
 * Ein Beleg nach einer Zahlung ist Pflicht, eine Auftragsbestätigung auch.
 * Wer sie still verwirft, hat nach dem Not-Aus einen zweiten Schaden. Deshalb
 * wartet jede Mail hier, bis Uwe sie sendet oder bewusst verwirft — beides
 * mit Prüfspur. Chat-Antworten (Telegram, WhatsApp) werden nicht gesammelt:
 * Eine Antwort, die Stunden später kommt, ist keine Antwort mehr.
 *
 * Nur Mails werden gesammelt; jede Mail genau einmal (Fingerabdruck), auch
 * wenn ein Webhook sie dreimal versucht.
 */
final class Ausgang
{
    /**
     * EINE Tür für den Not-Aus (Prüfung 07.10.2026, Punkt 48): Darf jetzt etwas über diesen
     * Kanal an diesen Empfänger? Nein nur, wenn ein automatischer Weg läuft, der Not-Aus steht
     * und der Empfänger nicht Uwe selbst ist. Neue Kanäle fragen hier, nicht selbst.
     * @param string $kanal mail | telegram | whatsapp | meta | push | sms | druck | kas
     * @param bool $anUns der Empfänger ist Uwe selbst (Meldungen an ihn gehen immer)
     */
    public const KANAELE = ['mail', 'telegram', 'whatsapp', 'meta', 'push', 'sms', 'druck', 'kas'];

    public static function darf(string $kanal, bool $anUns = false): bool
    {
        if ($anUns) { return true; }
        return !Automation::ausgangGesperrt();
    }

    /** Zurückhalten, wenn ein automatischer Weg während des Not-Aus eine Mail schicken will. */
    public static function mailHalten(string $anlass, string $an, string $betreff, string $text, array $bezug): bool
    {
        // Binäre Anhänge (PDF) überstehen JSON nur als base64.
        if (!empty($bezug['anhaenge']) && is_array($bezug['anhaenge'])) {
            foreach ($bezug['anhaenge'] as $i => $a) {
                $bezug['anhaenge'][$i]['daten'] = base64_encode((string) ($a['daten'] ?? ''));
                $bezug['anhaenge'][$i]['b64'] = true;
            }
        }
        $nutzlast = json_encode(['anlass' => $anlass, 'an' => $an, 'betreff' => $betreff, 'text' => $text, 'bezug' => $bezug],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($nutzlast === false) { return false; }
        /* Fingerabdruck ohne Ziffern im Text: Eine Mail mit „Frist: 21.10.“ war beim nächsten
           Versuch eine Woche später eine andere Mail und stand dann doppelt in der Liste. Die
           Bezugsnummern (Kunde, Zahlung, Bestellung …) halten verschiedene Fälle auseinander. */
        $ids = [];
        foreach (['customer_id', 'project_id', 'order_id', 'payment_id'] as $k) { $ids[] = (string) ($bezug[$k] ?? ''); }
        $ids[] = (string) ($bezug['nachher']['id'] ?? '');
        $fuerFinger = !empty($bezug['ki_teil']) ? str_replace((string) $bezug['ki_teil'], '', $text) : $text;   // der KI-Absatz ist bei jedem Versuch anders
        $finger = hash('sha256', $anlass . "\0" . mb_strtolower($an) . "\0" . preg_replace('~\d+~', '#', $betreff) . "\0"
            . implode(',', $ids) . "\0" . preg_replace('~\d+~', '#', $fuerFinger));
        try {
            Db::run('INSERT IGNORE INTO ausgang_gehalten
                (kanal, anlass, empfaenger, betreff, nutzlast, fingerabdruck, herkunft, customer_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                ['mail', mb_substr($anlass, 0, 64), mb_substr($an, 0, 190), mb_substr($betreff, 0, 255), $nutzlast, $finger,
                 Automation::herkunft() ?: null, isset($bezug['customer_id']) ? (int) $bezug['customer_id'] : null]);
        } catch (Throwable $e) { return false; }   // vor Migration 202: lieber nichts als ungefragt raus
        try {
            require_once __DIR__ . '/Events.php';
            Events::melden('ausgang_gehalten', 'Not-Aus hält Nachrichten zurück', 'hinweis',
                'Automationen wollten während des Not-Aus Mails verschicken. Sie warten, bis du sie sendest oder verwirfst.', '/automationen#gehalten');
        } catch (Throwable $e) { /* die Meldung ist Beiwerk */ }
        return true;
    }

    /** @return list<array<string,mixed>> offene, älteste zuerst */
    public static function offen(int $grenze = 200): array
    {
        try {
            return Db::all('SELECT id, kanal, anlass, empfaenger, betreff, herkunft, customer_id, created_at
                              FROM ausgang_gehalten WHERE entschieden_am IS NULL ORDER BY id LIMIT ' . max(1, min(500, $grenze)));
        } catch (Throwable $e) { return []; }
    }

    public static function anzahlOffen(): int
    {
        try { return (int) Db::wert('SELECT COUNT(*) FROM ausgang_gehalten WHERE entschieden_am IS NULL', [], 0); }
        catch (Throwable $e) { return 0; }
    }

    /**
     * Eine zurückgehaltene Mail jetzt schicken. Nur, wenn der Not-Aus steht nicht mehr —
     * sonst wäre „Senden“ ein Loch im Not-Aus.
     * @return array{ok:bool, text:string}
     */
    public static function senden(int $id, string $wer): array
    {
        if (Automation::notAus()) { return ['ok' => false, 'text' => 'Erst den Not-Aus lösen — dann senden.']; }
        $z = self::sperren($id, 'gesendet', $wer);
        if ($z === null) { return ['ok' => false, 'text' => 'Diese Nachricht ist schon entschieden.']; }
        $n = json_decode((string) $z['nutzlast'], true);
        if (!is_array($n)) { return ['ok' => false, 'text' => 'Die gespeicherte Nachricht ist nicht lesbar.']; }
        $bezug = (array) ($n['bezug'] ?? []);
        foreach ((array) ($bezug['anhaenge'] ?? []) as $i => $a) {
            if (!empty($a['b64'])) { $bezug['anhaenge'][$i]['daten'] = (string) base64_decode((string) $a['daten'], true); unset($bezug['anhaenge'][$i]['b64']); }
        }
        require_once __DIR__ . '/Mail.php';
        $ok = Mail::senden((string) $n['anlass'], (string) $n['an'], (string) $n['betreff'], (string) $n['text'], $bezug);
        if (!$ok) {
            // Zurück auf offen: Uwe soll es noch einmal versuchen können.
            Db::run('UPDATE ausgang_gehalten SET entschieden_am = NULL, entscheidung = NULL, entschieden_von = NULL WHERE id = ?', [$id]);
            return ['ok' => false, 'text' => 'Die Mail ging nicht raus — sie wartet weiter.'];
        }
        self::spur($id, $z, 'gesendet', $wer);
        self::nachher((array) ($bezug['nachher'] ?? []));
        return ['ok' => true, 'text' => 'Gesendet an ' . $z['empfaenger'] . '.'];
    }

    /**
     * Was der ursprüngliche Weg nach einer gesendeten Mail eingetragen hätte. Während des
     * Not-Aus kam Mail::senden mit false zurück, also blieb „Fragebogen verschickt“, „Beleg
     * gesendet“ oder „Zahlung fällig ab“ leer — auch nachdem Uwe die Mail später schickte
     * (Prüfung 07.10.2026, Punkt 25). Nur diese drei Felder, nichts Freies.
     */
    public const NACHHER = [
        'questionnaires.eingeladen_am' => 'jetzt',
        'invoices.sent_at'             => 'jetzt',
        'payments.faellig_am'          => 'frist',
    ];

    public static function nachher(array $n): void
    {
        $schluessel = (string) ($n['tabelle'] ?? '') . '.' . (string) ($n['spalte'] ?? '');
        $art = self::NACHHER[$schluessel] ?? null;
        $id = (int) ($n['id'] ?? 0);
        if ($art === null || $id <= 0) { return; }
        [$tabelle, $spalte] = explode('.', $schluessel);
        $wert = $art === 'frist'
            ? date('Y-m-d', strtotime('+' . (class_exists('Events') ? Events::ZAHLUNGSZIEL_TAGE : 14) . ' days'))
            : date('Y-m-d H:i:s');
        try { Db::run("UPDATE `$tabelle` SET `$spalte` = ? WHERE id = ? AND `$spalte` IS NULL", [$wert, $id]); }
        catch (Throwable $e) { /* dann bleibt es leer, wie vorher */ }
    }

    /** @return array{ok:bool, text:string} */
    public static function verwerfen(int $id, string $wer): array
    {
        $z = self::sperren($id, 'verworfen', $wer);
        if ($z === null) { return ['ok' => false, 'text' => 'Diese Nachricht ist schon entschieden.']; }
        self::spur($id, $z, 'verworfen', $wer);
        return ['ok' => true, 'text' => 'Verworfen — ' . $z['empfaenger'] . ' bekommt sie nicht.'];
    }

    /** @return array{gesendet:int, fehler:int} */
    public static function alleSenden(string $wer): array
    {
        $g = 0; $f = 0;
        foreach (self::offen(500) as $z) { self::senden((int) $z['id'], $wer)['ok'] ? $g++ : $f++; }
        return ['gesendet' => $g, 'fehler' => $f];
    }

    /** Atomar entscheiden: nur wer die Zeile von offen auf entschieden kippt, darf weitermachen. */
    private static function sperren(int $id, string $entscheidung, string $wer): ?array
    {
        $z = Db::one('SELECT * FROM ausgang_gehalten WHERE id = ?', [$id]);
        if (!$z || $z['entschieden_am'] !== null) { return null; }
        $st = Db::run('UPDATE ausgang_gehalten SET entschieden_am = NOW(), entscheidung = ?, entschieden_von = ?
                        WHERE id = ? AND entschieden_am IS NULL', [$entscheidung, mb_substr($wer, 0, 80), $id]);
        return $st->rowCount() > 0 ? $z : null;
    }

    private static function spur(int $id, array $z, string $entscheidung, string $wer): void
    {
        // Fingerabdruck freigeben, damit dieselbe Mail bei einem späteren Not-Aus wieder gehalten werden kann.
        Db::run('UPDATE ausgang_gehalten SET fingerabdruck = SHA2(CONCAT(fingerabdruck, id), 256) WHERE id = ?', [$id]);
        try {
            require_once __DIR__ . '/Events.php';
            Events::pruefspur('ausgang_' . $entscheidung, 'ausgang_gehalten', $id,
                ['anlass' => $z['anlass'], 'empfaenger' => $z['empfaenger'], 'betreff' => $z['betreff']], ['von' => $wer]);
        } catch (Throwable $e) { /* Spur ist Beiwerk, die Entscheidung steht in der Zeile */ }
    }
}
