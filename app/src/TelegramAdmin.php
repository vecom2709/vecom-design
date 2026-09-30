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
        // Zweites Schloss schon beim Verbinden, BEVOR der Code verbraucht ist:
        // Öffnet Uwe den Link versehentlich im falschen Telegram-Konto, kann er
        // ihn danach im richtigen noch benutzen.
        if (!self::imKanal((int) Db::wert('SELECT chat_id FROM telegram_chats WHERE id = ?', [$chatZeile], 0))) {
            try {
                require_once __DIR__ . '/Events.php';
                Events::melden('telegram_admin_abgelehnt', 'Telegram: Verbindung zur Verwaltung abgelehnt', 'warnung',
                    'Das Telegram-Konto, das den Link geöffnet hat, ist nicht Besitzer oder Admin des Kanals.', 'einstellungen?b=telegram');
            } catch (Throwable $e) { }
            return null;
        }
        $st = Db::run('UPDATE telegram_codes SET benutzt_am = NOW() WHERE id = ? AND benutzt_am IS NULL', [(int) $z['id']]);
        if ($st->rowCount() !== 1) { return null; }
        $uid = (int) $z['user_id'];
        if (!self::darf($uid)) { return null; }
        Db::transaktion(static function () use ($uid, $chatZeile): void {
            Db::run('UPDATE telegram_chats SET admin_verbunden = NULL WHERE admin_verbunden = ? AND id <> ?', [$uid, $chatZeile]);
            Db::run('UPDATE telegram_chats SET admin_verbunden = ? WHERE id = ?', [$uid, $chatZeile]);
        }, 3);
        self::befehleSetzen((int) Db::wert('SELECT chat_id FROM telegram_chats WHERE id = ?', [$chatZeile], 0), true);
        try {
            require_once __DIR__ . '/Events.php';
            Events::protokoll('telegram_admin', 'Telegram mit einem Zugang der Verwaltung verbunden (Zugang #' . $uid . ')');
            Events::pruefspur('telegram_admin_verbunden', 'users', $uid, [], ['telegram' => 'verbunden']);
        } catch (Throwable $e) { }
        return $uid;
    }

    public static function trennen(int $userId): bool
    {
        // Erst die Befehle /heute … aus dem Chat nehmen, solange wir ihn noch kennen.
        try {
            foreach (Db::all('SELECT chat_id FROM telegram_chats WHERE admin_verbunden = ?', [$userId]) as $r) { self::befehleSetzen((int) $r['chat_id'], false); }
        } catch (Throwable $e) { }
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

    /* ZWEI SCHLÖSSER (30.09.2026, Uwe: „kein normaler Nutzer in die
       Verwaltung, nur Kanalbesitzer und Admin“)

       Ein Chat kommt nur in die Verwaltung, wenn BEIDES stimmt:
         1. er ist mit einem aktiven Zugang der Rolle admin verbunden
            (Einmal-Code aus der angemeldeten Verwaltung), und
         2. die Telegram-Person dahinter ist Besitzer oder Admin des
            hinterlegten Kanals — gefragt bei Telegram (getChatMember),
            nicht am Namen erkannt.
       Ein weitergereichter Code oder ein übernommenes Handy mit fremdem
       Telegram-Konto scheitert am zweiten Schloss. Ist noch kein Kanal
       hinterlegt, gilt nur das erste. Antwortet Telegram nicht, bleibt die
       Tür zu (lieber ein verpasster Zuruf als ein offenes Fenster).
       Normale Nutzer kommen gar nicht bis zur Frage: Ohne Verbindung wird
       Telegram nicht einmal gefragt. */

    /** @var array<int,bool> je Anfrage gemerkt — ein Knopfdruck fragt Telegram höchstens einmal */
    private static array $kanalRolle = [];

    /** Ist diese Telegram-Person Besitzer oder Admin des hinterlegten Kanals? */
    public static function imKanal(int $telegramId): bool
    {
        if ($telegramId <= 0) { return false; }
        $kanal = Telegram::einstellung('tg_kanal_id');
        if ($kanal === '') { return true; }
        if (!array_key_exists($telegramId, self::$kanalRolle)) {
            $r = Telegram::rufen('getChatMember', ['chat_id' => $kanal, 'user_id' => $telegramId]);
            self::$kanalRolle[$telegramId] = $r['ok'] && in_array((string) ($r['result']['status'] ?? ''), ['creator', 'administrator'], true);
        }
        return self::$kanalRolle[$telegramId];
    }

    /** Beide Schlösser für einen Chat (im Privatchat ist chat_id = Telegram-Person). */
    public static function darfChat(array $c): bool
    {
        return !empty($c['admin_verbunden']) && self::darf((int) $c['admin_verbunden']) && self::imKanal((int) ($c['chat_id'] ?? 0));
    }

    /** Nur für die Kette: gemerkte Antworten vergessen. */
    public static function vergessen(): void { self::$kanalRolle = []; }

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

    /* ==================== /heute (01.10.2026, Uwe: „Ja mach“) ===================

       Die Tagesübersicht der Chef-Zentrale. Nur lesen — gezählt wird mit
       denselben Tabellen, aus denen Akquise-Liste, Auswertung und „Heute“ in
       der Verwaltung ihre Zahlen holen; hier entsteht keine zweite Rechnung.
       „Heute“ heißt: seit Mitternacht in der Zeitzone der Anwendung.

       Lässt sich eine Zahl nicht lesen (Tabelle fehlt, Datenbank hakt),
       steht dort null und im Bot „–“, nie eine erfundene Null. */

    /** Die Klassen, die bei einer Antwort „ein Mensch meldet sich“ auslösen (AkquiseVersand). */
    public const INTERESSE = ['INTERESTED', 'CALL_REQUEST', 'PRICE_REQUEST', 'MORE_INFO'];

    /** @return array{akquise: array<string,?int>, verwaltung: array<string,?int>, schalter: array<string,bool>, stand: string} */
    public static function heute(): array
    {
        $ab = date('Y-m-d 00:00:00');
        $z = static function (string $sql, array $p = []): ?int {
            try { return (int) Db::wert($sql, $p, 0); } catch (Throwable $e) { return null; }
        };
        $in = "'" . implode("','", self::INTERESSE) . "'";
        $akquise = [
            'neu'          => $z('SELECT COUNT(*) FROM akq_firmen WHERE created_at >= ?', [$ab]),
            'geprueft'     => $z('SELECT COUNT(*) FROM akq_audits WHERE created_at >= ?', [$ab]),
            'entwuerfe'    => $z("SELECT COUNT(*) FROM akq_vorlagen v JOIN akq_firmen f ON f.id = v.firma_id WHERE v.status = 'entwurf' AND f.gesperrt = 0"),
            'freigegeben'  => $z("SELECT COUNT(*) FROM akq_vorlagen v JOIN akq_firmen f ON f.id = v.firma_id WHERE v.status = 'freigegeben' AND f.gesperrt = 0"),
            'versendet'    => $z("SELECT COUNT(*) FROM akq_versand WHERE status IN ('gesendet','von_hand') AND created_at >= ?", [$ab]),
            'blockiert'    => $z("SELECT COUNT(*) FROM akq_versand WHERE status = 'blockiert' AND created_at >= ?", [$ab]),
            'antworten'    => $z('SELECT COUNT(*) FROM akq_antworten WHERE created_at >= ?', [$ab]),
            'offen'        => $z('SELECT COUNT(*) FROM akq_antworten WHERE erledigt = 0'),
            'interesse'    => $z("SELECT COUNT(*) FROM akq_antworten WHERE erledigt = 0 AND klasse IN ($in)"),
            'widerspruch'  => $z("SELECT COUNT(*) FROM akq_sperrliste WHERE quelle IN ('abmeldung','antwort') AND created_at >= ?", [$ab]),
            'wiedervorlage'=> null,
            'portal'       => $z("SELECT COUNT(*) FROM akq_plattform WHERE status = 'offen'"),
            'checks'       => $z("SELECT COUNT(*) FROM akq_checks WHERE status = 'neu'"),
        ];
        // Fällig heißt: heute oder früher und noch nicht gemeldet — plus die schon
        // gemeldeten, die noch niemand angesehen hat (AkquiseSignal::wiedervorlagen
        // leert das Datum, sobald es die Meldung schreibt).
        $wvOffen = $z('SELECT COUNT(*) FROM akq_firmen WHERE gesperrt = 0 AND wiedervorlage_am IS NOT NULL AND wiedervorlage_am <= ?', [date('Y-m-d')]);
        $wvGemeldet = $z("SELECT COUNT(*) FROM notifications WHERE type = 'akquise_wiedervorlage' AND read_at IS NULL");
        $akquise['wiedervorlage'] = $wvOffen === null || $wvGemeldet === null ? null : $wvOffen + $wvGemeldet;
        $l = self::lage();
        $schalter = ['testbetrieb' => true, 'versand_an' => false, 'stop' => false];
        try {
            require_once __DIR__ . '/Akquise.php';
            require_once __DIR__ . '/AkquiseGate.php';
            $g = AkquiseGate::grenzen();
            $schalter = ['testbetrieb' => AkquiseGate::testbetrieb(), 'versand_an' => (bool) $g['versand_an'], 'stop' => (bool) $g['stop']];
        } catch (Throwable $e) { }
        return [
            'akquise' => $akquise,
            'verwaltung' => ['du' => $l['du'], 'anfragen' => $l['anfragen'], 'nachrichten' => $l['nachrichten']],
            'schalter' => $schalter,
            'stand' => date('d.m.Y H:i'),
        ];
    }

    /**
     * Die Befehle neben dem Eingabefeld — NUR in diesem Chat. Kunden und
     * Interessenten sehen /heute nie: Telegram zeigt Befehle mit Geltung
     * „chat“ ausschließlich dort (BotCommandScopeChat).
     */
    public static function befehleSetzen(int $chatId, bool $an): void
    {
        if ($chatId <= 0) { return; }
        try {
            $scope = ['type' => 'chat', 'chat_id' => $chatId];
            if (!$an) { Telegram::rufen('deleteMyCommands', ['scope' => $scope]); return; }
            Telegram::rufen('setMyCommands', ['scope' => $scope, 'commands' => [
                ['command' => 'heute', 'description' => 'Tagesübersicht: Akquise und Verwaltung'],
                ['command' => 'menu', 'description' => 'Menü'],
            ]]);
        } catch (Throwable $e) { /* das Menü neben dem Eingabefeld ist Komfort, kein Muss */ }
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
                if (self::darfChat($r)) { self::$warteschlange[] = [(int) $r['chat_id'], mb_substr($text, 0, 900)]; }
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
