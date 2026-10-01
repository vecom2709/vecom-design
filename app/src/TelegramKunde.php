<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Texte.php';
require_once __DIR__ . '/Telegram.php';

/* ==========================================================================
   TelegramKunde.php — Telegram für Kunden, die schon Kunden sind
   (30.09.2026, Stufe 2).

   DIE VERBINDUNG

   Eine Telegram-Nummer beweist nicht, wer jemand ist. Verbunden wird
   deshalb nur aus dem persönlichen Bereich heraus — dort ist der Kunde
   über seinen geheimen Link. Der Knopf „Mit Telegram verbinden“ erzeugt
   einen Einmal-Code (128 Bit, 30 Minuten gültig, gespeichert nur als
   SHA-256) und öffnet t.me/BOT?start=k_CODE. Erst dieser Start verbindet
   den Chat mit dem Kunden (telegram_chats.kunde_verbunden).

   WAS DER VERBUNDENE KUNDE BEKOMMT — NICHTS EIGENES, NUR EIN FENSTER

     Projektstand     Kundenzugang::seite + Texte::KUNDE_STUFEN (wie im Dashboard)
     Nachricht        Nachricht::schreiben / vorab (dasselbe Postfach)
     Datei            Ablage::ausDatei (dieselbe Typprüfung, dieselbe Grenze)
     Dashboard        Kundenzugang::linkFuer
     Hinweise         aus Mail::senden: Jede Mail, die ohnehin an ihn geht,
                      bekommt einen kurzen Hinweis im Chat — Betreff, kein
                      Inhalt. Keine zusätzliche Post, kein Werbekanal.
   ========================================================================== */
final class TelegramKunde
{
    /** So lange gilt ein Verbindungslink. */
    public const CODE_MINUTEN = 30;

    /** Mindestabstand zwischen zwei Hinweisen an denselben Chat. */
    public const HINWEIS_SEKUNDEN = 60;

    /* ============================ VERBINDEN ============================ */

    /** Einen Einmal-Link erzeugen. Leer, wenn der Bot nicht eingerichtet ist. */
    public static function verbindungslink(int $kundeId): string
    {
        // Seit 01.10.2026 ab Werk zu: Der Bot-Chat ist nur für den Admin (Telegram::botOffen).
        if ($kundeId <= 0 || !Telegram::bereit() || Telegram::einstellung('tg_name') === '' || !Telegram::botOffen()) { return ''; }
        $code = bin2hex(random_bytes(16));
        Db::insert('telegram_codes', [
            'customer_id' => $kundeId,
            'code_hash' => hash('sha256', $code),
            'gueltig_bis' => date('Y-m-d H:i:s', time() + self::CODE_MINUTEN * 60),
        ]);
        // Alte, abgelaufene Codes wegräumen — sie beweisen nichts mehr.
        Db::run('DELETE FROM telegram_codes WHERE gueltig_bis < (NOW() - INTERVAL 1 DAY)');
        return Telegram::link('k_' . $code);
    }

    /**
     * Den Code aus /start einlösen.
     *
     * @return int|null Kunde, oder null, wenn der Code unbekannt, abgelaufen oder schon benutzt ist
     */
    public static function einloesen(int $chatZeile, string $code): ?int
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $code)) { return null; }
        $z = Db::one('SELECT * FROM telegram_codes WHERE code_hash = ?', [hash('sha256', $code)]);
        if (!$z || $z['benutzt_am'] !== null || strtotime((string) $z['gueltig_bis']) < time()) { return null; }
        // Einmal: Nur wer die Zeile als Erster umschaltet, verbindet.
        $st = Db::run('UPDATE telegram_codes SET benutzt_am = NOW() WHERE id = ? AND benutzt_am IS NULL', [(int) $z['id']]);
        if ($st->rowCount() !== 1) { return null; }
        $kid = (int) $z['customer_id'];
        if (!Db::one('SELECT id FROM customers WHERE id = ?', [$kid])) { return null; }

        Db::transaktion(static function () use ($kid, $chatZeile): void {
            // Ein Kunde, ein Chat: Ein früher verbundener Chat löst sich.
            Db::run('UPDATE telegram_chats SET kunde_verbunden = NULL, verbunden_am = NULL WHERE kunde_verbunden = ? AND id <> ?', [$kid, $chatZeile]);
            Db::run('UPDATE telegram_chats SET kunde_verbunden = ?, verbunden_am = NOW(), benachrichtigen = 1 WHERE id = ?', [$kid, $chatZeile]);
        }, 3);
        try {
            require_once __DIR__ . '/Events.php';
            Events::protokoll('telegram_verbunden', 'Telegram mit dem Kundenkonto verbunden', $kid);
            Events::pruefspur('telegram_verbunden', 'customers', $kid, [], ['telegram' => 'verbunden']);
        } catch (Throwable $e) { /* die Verbindung steht, das zählt */ }
        return $kid;
    }

    /** Verbindung lösen — aus dem Bot, aus dem Dashboard oder beim Zurückziehen des Kundenlinks. */
    public static function trennen(int $kundeId, string $wer = 'kunde'): bool
    {
        $n = Db::run('UPDATE telegram_chats SET kunde_verbunden = NULL, verbunden_am = NULL, datei_id = NULL, datei_name = NULL, datei_groesse = NULL
                       WHERE kunde_verbunden = ?', [$kundeId])->rowCount();
        if ($n > 0) {
            try {
                require_once __DIR__ . '/Events.php';
                Events::protokoll('telegram_getrennt', 'Telegram-Verbindung gelöst (' . $wer . ')', $kundeId);
                Events::pruefspur('telegram_getrennt', 'customers', $kundeId, ['telegram' => 'verbunden'], ['telegram' => 'getrennt']);
            } catch (Throwable $e) { }
        }
        return $n > 0;
    }

    /** Der verbundene Chat eines Kunden, oder null. */
    public static function chat(int $kundeId): ?array
    {
        try { return Db::one('SELECT * FROM telegram_chats WHERE kunde_verbunden = ?', [$kundeId]); }
        catch (Throwable $e) { return null; }
    }

    /* ============================ HINWEISE ============================= */

    /**
     * Aus Mail::senden: Eine Mail ist an den Kunden raus — ein kurzer Hinweis
     * im Chat. Nur der Betreff: Was in der Mail steht (Links, Beträge), gehört
     * nicht in einen zweiten Kanal. Wirft nie.
     */
    public static function hinweis(int $kundeId, string $betreff): bool
    {
        try {
            $c = self::chat($kundeId);
            // Ist der Bot nur für den Admin (ab Werk seit 01.10.2026), ruhen die Hinweise — die E-Mail kommt ja trotzdem.
            if (!$c || !(int) $c['benachrichtigen'] || !Telegram::bereit() || !Telegram::botOffen()) { return false; }
            if ($c['hinweis_am'] !== null && strtotime((string) $c['hinweis_am']) > time() - self::HINWEIS_SEKUNDEN) { return false; }
            $sp = in_array((string) $c['sprache'], ['it', 'de', 'en'], true) ? (string) $c['sprache'] : 'it';
            $T = Texte::TELEGRAM[$sp];
            require_once __DIR__ . '/Kundenzugang.php';
            $text = '📬 <b>' . htmlspecialchars($T['hinweisKopf'], ENT_QUOTES) . "</b>\n" . htmlspecialchars(mb_substr($betreff, 0, 200), ENT_QUOTES)
                  . "\n\n" . htmlspecialchars($T['hinweisText'], ENT_QUOTES);
            $r = Telegram::rufen('sendMessage', ['chat_id' => (int) $c['chat_id'], 'text' => $text, 'parse_mode' => 'HTML',
                'reply_markup' => ['inline_keyboard' => [[['text' => $T['k_dashboard'], 'url' => Kundenzugang::linkFuer($kundeId, $sp)]]]]]);
            Db::update('telegram_chats', (int) $c['id'], ['hinweis_am' => date('Y-m-d H:i:s')]);
            // Hat der Kunde den Bot blockiert, ist die Verbindung für uns tot.
            if (!$r['ok'] && str_contains($r['beschreibung'], 'blocked')) { self::trennen($kundeId, 'Bot blockiert'); }
            return $r['ok'];
        } catch (Throwable $e) {
            return false;
        }
    }

    /* =========================== PROJEKTSTAND ========================== */

    /** @return array{titel:string, text:string, stufe:string} */
    public static function stand(int $kundeId, string $sp): array
    {
        require_once __DIR__ . '/Kundenzugang.php';
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]);
        if (!$k) { return ['titel' => '', 'text' => '', 'stufe' => '']; }
        $s = Kundenzugang::seite($k);
        $st = (string) ($s['stufe'] ?? 'anfrage');
        $karte = Texte::KUNDE_STUFEN[$st] ?? Texte::KUNDE_STUFEN['anfrage'];
        return ['titel' => (string) ($karte[$sp] ?? $karte['it']), 'text' => Texte::h((array) ($karte['text'] ?? []), $sp), 'stufe' => $st];
    }

    /* ============================ NACHRICHT ============================ */

    /** Eine Nachricht des Kunden in dasselbe Postfach wie im Dashboard. */
    public static function nachricht(int $kundeId, string $text): int
    {
        require_once __DIR__ . '/Nachricht.php';
        $pid = self::projekt($kundeId);
        return $pid ? Nachricht::schreiben($pid, $text, 'kunde') : Nachricht::vorab($kundeId, $text, 'kunde');
    }

    /** Das jüngste Projekt des Kunden (wie das Dashboard) — oder null. */
    public static function projekt(int $kundeId): ?int
    {
        require_once __DIR__ . '/Kundenzugang.php';
        $k = Db::one('SELECT * FROM customers WHERE id = ?', [$kundeId]);
        if (!$k) { return null; }
        $pid = Kundenzugang::seite($k)['vorgang']['projekt_id'] ?? null;
        return $pid ? (int) $pid : null;
    }

    /* ============================== DATEI ============================== */

    /**
     * Die gemerkte Datei holen und ablegen — mit denselben Grenzen wie der
     * Upload im Dashboard (Größe, Stückzahl, Typ aus dem Inhalt).
     *
     * @return array{name:string, id:int}
     */
    public static function dateiAblegen(int $kundeId, string $dateiId, string $name): array
    {
        require_once __DIR__ . '/Ablage.php';
        require_once __DIR__ . '/Fmt.php';
        $pid = self::projekt($kundeId);
        $wieViele = $pid !== null
            ? (int) Db::wert("SELECT COUNT(*) FROM files WHERE project_id = ? AND rolle <> 'paket'", [$pid])
            : (int) Db::wert('SELECT COUNT(*) FROM files WHERE customer_id = ? AND project_id IS NULL', [$kundeId]);
        if ($wieViele >= Ablage::MAX_JE_PROJEKT) { throw new RuntimeException('voll'); }
        // Telegram gibt höchstens 20 MB heraus; die Grenze des Servers kann kleiner sein.
        $grenze = min(Ablage::grenze(), 20 * 1024 * 1024);
        $tmp = Telegram::dateiHolen($dateiId, $grenze);
        try {
            $id = Ablage::ausDatei($tmp, $name, $pid, $kundeId, 'kunde', $grenze);
        } finally {
            if (is_file($tmp)) { @unlink($tmp); }
        }
        try {
            require_once __DIR__ . '/Events.php';
            $k = Db::one('SELECT name, company FROM customers WHERE id = ?', [$kundeId]);
            Events::melden('datei_neu', 'Neue Datei vom Kunden (Telegram)', 'info',
                (string) (($k['company'] ?? '') ?: ($k['name'] ?? '')) . ' — ' . $name, '/kunden/' . $kundeId);
        } catch (Throwable $e) { }
        return ['name' => $name, 'id' => $id];
    }
}
