<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Telegram.php';

/* ==========================================================================
   TelegramAdmin.php — Uwes Telegram als Fenster zur Verwaltung
   (30.09.2026, Stufe 3).

   KEINE ZWEITE VERWALTUNG

   Der Bot zeigt Uwe nur, WAS ansteht, und führt mit einem Knopf an die
   richtige Stelle in /app. Gearbeitet wird dort — mit Anmeldung, Rückfragen
   und Prüfspur. Schreibende Taten im Chat gibt es in dieser Stufe bewusst
   nicht: Ein weitergeleitetes Handy oder ein fremder Blick auf den
   Bildschirm soll nie eine Zahlung buchen oder einen Status ändern können.

   WIE BEIM ZURUF: KEINE KUNDENDATEN

   Telegram ist ein fremder Dienst. Deshalb gilt dieselbe Regel wie beim
   WhatsApp-Zuruf (Zuruf.php): Zahlen und Verweise ja, Namen, Adressen und
   Nachrichtentexte nein. Wer Einzelheiten will, tippt auf den Knopf und ist
   in der Verwaltung.

   RECHTE

   Verbunden wird über einen Einmal-Code aus der angemeldeten Verwaltung.
   Bei jedem Aufruf wird neu geprüft, ob der Zugang noch aktiv ist und die
   Rolle admin hat — wer in der Verwaltung abgeschaltet wird, verliert im
   selben Moment auch den Bot.
   ========================================================================== */
final class TelegramAdmin
{
    public const CODE_MINUTEN = 30;

    /** Was in dieser Anfrage noch an Uwe gehen soll (nach der Antwort verschickt). */
    private static array $warteschlange = [];
    private static bool $angemeldet = false;

    public static function verbindungslink(int $userId): string
    {
        if ($userId <= 0 || !Telegram::bereit() || Telegram::einstellung('tg_name') === '') { return ''; }
        if (!self::darf($userId)) { return ''; }
        $code = bin2hex(random_bytes(16));
        Db::insert('telegram_codes', [
            'customer_id' => null, 'user_id' => $userId,
            'code_hash' => hash('sha256', $code),
            'gueltig_bis' => date('Y-m-d H:i:s', time() + self::CODE_MINUTEN * 60),
        ]);
        return Telegram::link('a_' . $code);
    }

    /** @return int|null Zugang, oder null bei unbekanntem, abgelaufenem, benutztem Code */
    public static function einloesen(int $chatZeile, string $code): ?int
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $code)) { return null; }
        $z = Db::one('SELECT * FROM telegram_codes WHERE code_hash = ? AND user_id IS NOT NULL', [hash('sha256', $code)]);
        if (!$z || $z['benutzt_am'] !== null || strtotime((string) $z['gueltig_bis']) < time()) { return null; }
        $st = Db::run('UPDATE telegram_codes SET benutzt_am = NOW() WHERE id = ? AND benutzt_am IS NULL', [(int) $z['id']]);
        if ($st->rowCount() !== 1) { return null; }
        $uid = (int) $z['user_id'];
        if (!self::darf($uid)) { return null; }
        Db::transaktion(static function () use ($uid, $chatZeile): void {
            Db::run('UPDATE telegram_chats SET admin_verbunden = NULL WHERE admin_verbunden = ? AND id <> ?', [$uid, $chatZeile]);
            Db::run('UPDATE telegram_chats SET admin_verbunden = ? WHERE id = ?', [$uid, $chatZeile]);
        }, 3);
        try {
            require_once __DIR__ . '/Events.php';
            Events::protokoll('telegram_admin', 'Telegram mit einem Zugang der Verwaltung verbunden (Zugang #' . $uid . ')');
            Events::pruefspur('telegram_admin_verbunden', 'users', $uid, [], ['telegram' => 'verbunden']);
        } catch (Throwable $e) { }
        return $uid;
    }

    public static function trennen(int $userId): bool
    {
        $n = Db::run('UPDATE telegram_chats SET admin_verbunden = NULL WHERE admin_verbunden = ?', [$userId])->rowCount();
        if ($n > 0) {
            try {
                require_once __DIR__ . '/Events.php';
                Events::pruefspur('telegram_admin_getrennt', 'users', $userId, ['telegram' => 'verbunden'], ['telegram' => 'getrennt']);
            } catch (Throwable $e) { }
        }
        return $n > 0;
    }

    public static function chat(int $userId): ?array
    {
        try { return Db::one('SELECT * FROM telegram_chats WHERE admin_verbunden = ?', [$userId]); }
        catch (Throwable $e) { return null; }
    }

    /** Aktiver Zugang mit Rolle admin? Wird bei jedem Aufruf neu gefragt. */
    public static function darf(int $userId): bool
    {
        if ($userId <= 0) { return false; }
        try {
            return (bool) Db::wert("SELECT COUNT(*) FROM users WHERE id = ? AND active = 1 AND role = 'admin'", [$userId], 0);
        } catch (Throwable $e) { return false; }
    }

    /**
     * Die Lage in Zahlen — dieselben Abfragen wie Menüzähler und „Heute“ in
     * der Verwaltung, keine eigene Rechnung.
     *
     * @return array<string,int>
     */
    public static function lage(): array
    {
        $z = static function (string $sql): int { try { return (int) Db::wert($sql, [], 0); } catch (Throwable $e) { return 0; } };
        $du = 0; $kunde = 0;
        try {
            require_once __DIR__ . '/Vorgang.php';
            $a = Vorgang::arbeitsliste();
            $du = count($a['du'] ?? []); $kunde = count($a['kunde'] ?? []);
        } catch (Throwable $e) { }
        return [
            'anfragen'    => $z("SELECT COUNT(*) FROM anfragen WHERE status IN ('neu','in_arbeit') AND demo = 0"),
            'nachrichten' => $z("SELECT COUNT(*) FROM messages WHERE read_at IS NULL AND sender = 'kunde'"),
            'meldungen'   => $z('SELECT COUNT(*) FROM notifications WHERE read_at IS NULL'),
            'dateien'     => $z("SELECT COUNT(*) FROM files WHERE uploaded_by = 'kunde' AND created_at >= (NOW() - INTERVAL 1 DAY)"),
            'du'          => $du,
            'kunde'       => $kunde,
        ];
    }

    /**
     * Ein Zuruf auch an Uwes Telegram (aus Zuruf::vormerken). Verschickt wird
     * erst nach der Antwort an den Besucher — ein langsamer Dienst darf nie
     * das Kontaktformular aufhalten. Wirft nie.
     */
    /** @return bool ob mindestens ein Chat vorgemerkt wurde */
    public static function zuruf(string $text): bool
    {
        $vorher = count(self::$warteschlange);
        try {
            if (!Telegram::bereit()) { return false; }
            $ids = Db::all('SELECT id, chat_id, admin_verbunden FROM telegram_chats WHERE admin_verbunden IS NOT NULL');
            foreach ($ids as $r) {
                if (self::darf((int) $r['admin_verbunden'])) { self::$warteschlange[] = [(int) $r['chat_id'], mb_substr($text, 0, 900)]; }
            }
            $neu = count(self::$warteschlange) > $vorher;
            if (!self::$warteschlange || self::$angemeldet) { return $neu; }
            self::$angemeldet = true;
            if (PHP_SAPI === 'cli') { self::abschicken(); return $neu; }
            register_shutdown_function([self::class, 'nachDerAntwort']);
            return $neu;
        } catch (Throwable $e) { return false; }
    }

    public static function nachDerAntwort(): void
    {
        if (function_exists('fastcgi_finish_request')) { @fastcgi_finish_request(); }
        self::abschicken();
    }

    private static function abschicken(): void
    {
        $liste = self::$warteschlange; self::$warteschlange = []; self::$angemeldet = false;
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . rtrim(Config::basis(), '/');
        foreach ($liste as [$chat, $text]) {
            try {
                Telegram::rufen('sendMessage', ['chat_id' => $chat, 'text' => '🔔 ' . $text,
                    'link_preview_options' => ['is_disabled' => true],
                    'reply_markup' => ['inline_keyboard' => [[['text' => '🛠 Verwaltung öffnen', 'url' => $basis . '/heute']], [['text' => '📊 Lage', 'callback_data' => 'v:lage']]]]]);
            } catch (Throwable $e) { }
        }
    }
}
