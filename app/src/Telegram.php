<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';

/* ==========================================================================
   Telegram.php — die Leitung zu Telegram (30.09.2026).

   Hier steht nur Technik: Schlüssel ablegen, die Bot-API aufrufen, den
   Webhook an- und abmelden. Was der Bot SAGT, steht in TelegramBot.php —
   und was er WEISS (Fragen, Preise, Kunden), kommt aus den Klassen, die
   auch die Website benutzt. Telegram ist ein Kanal, keine zweite Welt.

   WO DER SCHLÜSSEL LIEGT

   Nicht im Code und nicht in einer .env — das Repository ist öffentlich,
   und auf dem Webspace gibt es keine Umgebungsvariablen. Der Bot-Token
   wird in der Verwaltung eingetragen und landet verschlüsselt in
   `settings` (derselbe Weg wie der WhatsApp-Schlüssel: Hosting::versiegeln
   mit `hosting_geheim` aus config.local.php). Angezeigt werden nur die
   letzten vier Zeichen. Er steht nie in einem Protokoll und nie in einer
   Fehlermeldung — die Adresse der API enthält ihn, deshalb wird sie nirgends
   ausgegeben.

   DAS PRÜFWORT

   Telegram schickt bei jedem Aufruf des Webhooks einen Kopf
   X-Telegram-Bot-Api-Secret-Token mit, den wir beim Anmelden festlegen.
   Ohne dieses Wort nimmt telegram-webhook.php nichts an. Es entsteht beim
   Anmelden neu; wer es tauschen will, meldet den Webhook neu an.
   ========================================================================== */
final class Telegram
{
    public const API = 'https://api.telegram.org';

    /** Nur diese Arten von Updates bestellen wir — alles andere schickt Telegram gar nicht erst. */
    public const UPDATES = ['message', 'callback_query'];

    /** Für die Kette: ersetzt das Netz. fn(string $methode, array $daten): array{ok:bool,...} */
    public static $netz = null;

    /* ---------------------------- Schlüssel ---------------------------- */

    /** Der Bot-Token, entsiegelt. Leer, wenn keiner hinterlegt ist. */
    public static function token(): string { return self::geheim('tg_token'); }

    /** Das Prüfwort für den Webhook-Kopf. Leer, solange der Webhook nie angemeldet wurde. */
    public static function pruefwort(): string { return self::geheim('tg_pruefwort'); }

    public static function bereit(): bool { return self::token() !== '' && self::pruefwort() !== ''; }

    /** Die letzten vier Zeichen — zum Wiedererkennen, nicht zum Benutzen. */
    public static function tokenEnde(): string
    {
        $t = self::token();
        return $t === '' ? '' : substr($t, -4);
    }

    /** Sieht es aus wie ein Bot-Token? 123456789:AA… */
    public static function tokenGueltig(string $t): bool
    {
        return (bool) preg_match('/^\d{5,15}:[A-Za-z0-9_-]{30,60}$/', $t);
    }

    /**
     * Neuen Token prüfen und ablegen. Erst fragt getMe bei Telegram nach, ob
     * es ihn gibt — ein Tippfehler soll nicht gespeichert werden und dann
     * tagelang still ins Leere funken.
     *
     * @return array{ok:bool, text:string}
     */
    public static function tokenSpeichern(string $t): array
    {
        $t = trim($t);
        if (!self::tokenGueltig($t)) {
            return ['ok' => false, 'text' => 'Das sieht nicht wie ein Bot-Token aus. Er hat die Form 123456789:AA… und kommt von @BotFather.'];
        }
        $r = self::rufen('getMe', [], $t);
        if (!$r['ok'] || empty($r['result']['is_bot'])) {
            return ['ok' => false, 'text' => 'Telegram kennt diesen Token nicht (' . $r['beschreibung'] . '). Nichts gespeichert.'];
        }
        self::geheimSetzen('tg_token', $t);
        self::setzen('tg_name', (string) ($r['result']['username'] ?? ''));
        // Ein neuer Token gehört zu einem (vielleicht anderen) Bot: Das alte
        // Prüfwort und die Anmeldung gelten nicht mehr.
        self::setzen('tg_pruefwort', '');
        self::setzen('tg_webhook_am', '');
        return ['ok' => true, 'text' => 'Gespeichert: @' . (string) ($r['result']['username'] ?? '') . '. Jetzt „Webhook anmelden“ drücken.'];
    }

    /** Alles vergessen: abmelden, Token und Prüfwort löschen. */
    public static function entfernen(): void
    {
        if (self::token() !== '') { self::rufen('deleteWebhook', ['drop_pending_updates' => true]); }
        foreach (['tg_token', 'tg_pruefwort', 'tg_name', 'tg_webhook_am'] as $k) { self::setzen($k, ''); }
    }

    /* ----------------------------- Webhook ----------------------------- */

    /** Die Adresse, die Telegram aufruft. Ohne www: Telegram folgt keiner Weiterleitung. */
    public static function adresse(): string
    {
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/');
        $basis = (string) preg_replace('~^https?://www\.~i', 'https://', $basis);
        return $basis . '/telegram-webhook.php';
    }

    /**
     * Webhook anmelden, Befehle und Beschreibung setzen.
     *
     * drop_pending_updates: Was sich während einer Pause angestaut hat, wird
     * verworfen. Ein Interessent, der vor drei Tagen /start geschrieben hat,
     * soll nicht jetzt plötzlich ein Menü bekommen.
     *
     * @return array{ok:bool, text:string}
     */
    public static function anmelden(): array
    {
        if (self::token() === '') { return ['ok' => false, 'text' => 'Erst den Bot-Token eintragen.']; }
        $wort = bin2hex(random_bytes(32));
        $r = self::rufen('setWebhook', [
            'url' => self::adresse(),
            'secret_token' => $wort,
            'allowed_updates' => self::UPDATES,
            'max_connections' => 10,
            'drop_pending_updates' => true,
        ]);
        if (!$r['ok']) { return ['ok' => false, 'text' => 'Telegram hat die Anmeldung abgelehnt: ' . $r['beschreibung']]; }
        self::geheimSetzen('tg_pruefwort', $wort);
        self::setzen('tg_webhook_am', date('Y-m-d H:i:s'));

        // Befehle und Kurzbeschreibung je Sprache. Scheitert das, läuft der
        // Bot trotzdem — es fehlt nur das Menü neben dem Eingabefeld.
        require_once __DIR__ . '/TelegramBot.php';
        foreach (['it', 'de', 'en'] as $sp) {
            $T = TelegramBot::T[$sp];
            $befehle = [];
            foreach ($T['befehle'] as $cmd => $was) { $befehle[] = ['command' => $cmd, 'description' => $was]; }
            self::rufen('setMyCommands', ['commands' => $befehle, 'language_code' => $sp]);
            self::rufen('setMyShortDescription', ['short_description' => $T['kurz'], 'language_code' => $sp]);
            self::rufen('setMyDescription', ['description' => $T['beschreibung'], 'language_code' => $sp]);
        }
        $T = TelegramBot::T['it'];
        $befehle = [];
        foreach ($T['befehle'] as $cmd => $was) { $befehle[] = ['command' => $cmd, 'description' => $was]; }
        self::rufen('setMyCommands', ['commands' => $befehle]);
        self::rufen('setMyShortDescription', ['short_description' => $T['kurz']]);
        self::rufen('setMyDescription', ['description' => $T['beschreibung']]);

        return ['ok' => true, 'text' => 'Angemeldet. Telegram schickt ab jetzt alles an ' . self::adresse() . '.'];
    }

    /** @return array{ok:bool, text:string} */
    public static function abmelden(): array
    {
        if (self::token() === '') { return ['ok' => false, 'text' => 'Kein Token hinterlegt.']; }
        $r = self::rufen('deleteWebhook', ['drop_pending_updates' => false]);
        if ($r['ok']) { self::setzen('tg_webhook_am', ''); self::setzen('tg_pruefwort', ''); }
        return ['ok' => $r['ok'], 'text' => $r['ok'] ? 'Abgemeldet. Der Bot antwortet nicht mehr, bis er neu angemeldet wird.' : 'Abmelden ging nicht: ' . $r['beschreibung']];
    }

    /**
     * Wie steht es — aus Sicht von Telegram. Für den Knopf „Verbindung prüfen“.
     *
     * @return array{ok:bool, text:string, zeilen:list<string>}
     */
    public static function pruefen(): array
    {
        if (self::token() === '') { return ['ok' => false, 'text' => 'Kein Token hinterlegt.', 'zeilen' => []]; }
        $me = self::rufen('getMe');
        if (!$me['ok']) { return ['ok' => false, 'text' => 'Telegram erkennt den Token nicht mehr: ' . $me['beschreibung'], 'zeilen' => []]; }
        $info = self::rufen('getWebhookInfo');
        $w = (array) ($info['result'] ?? []);
        $zeilen = ['Bot: @' . (string) ($me['result']['username'] ?? '?')];
        $url = (string) ($w['url'] ?? '');
        $richtig = $url === self::adresse();
        $zeilen[] = 'Webhook: ' . ($url === '' ? 'nicht angemeldet' : ($richtig ? 'richtig angemeldet' : 'zeigt auf eine andere Adresse'));
        $zeilen[] = 'Wartende Updates: ' . (int) ($w['pending_update_count'] ?? 0);
        if (!empty($w['last_error_date'])) {
            $zeilen[] = 'Letzter Fehler: ' . date('d.m.Y H:i', (int) $w['last_error_date']) . ' — ' . mb_substr((string) ($w['last_error_message'] ?? ''), 0, 160);
        }
        // Ein Fehler, der über eine Stunde her ist, gilt als überstanden.
        $ok = $richtig && (int) ($w['last_error_date'] ?? 0) < time() - 3600;
        return ['ok' => $ok, 'text' => $ok ? 'Die Verbindung steht.' : 'Die Verbindung hat ein Problem — Einzelheiten unten.', 'zeilen' => $zeilen];
    }

    /**
     * Stand für die Einstellungsseite. Liest nur die eigene Datenbank —
     * die Seite soll nicht bei jedem Aufruf Telegram fragen müssen.
     */
    public static function stand(): array
    {
        $chats = $abgeschickt = 0; $letzte = '';
        try {
            $chats = (int) Db::wert('SELECT COUNT(*) FROM telegram_chats', [], 0);
            $abgeschickt = (int) Db::wert('SELECT COUNT(*) FROM telegram_chats WHERE anfrage_id IS NOT NULL', [], 0);
            $letzte = (string) Db::wert('SELECT MAX(letzte_am) FROM telegram_chats', [], '');
        } catch (Throwable $e) { /* Migration noch offen */ }
        return [
            'token' => self::token() !== '',
            'ende' => self::tokenEnde(),
            'name' => self::einstellung('tg_name'),
            'angemeldet' => self::einstellung('tg_webhook_am'),
            'bereit' => self::bereit(),
            'adresse' => self::adresse(),
            'chats' => $chats,
            'abgeschickt' => $abgeschickt,
            'letzte' => $letzte,
        ];
    }

    /** Der öffentliche Link zum Bot, z. B. für die Website. */
    public static function link(string $start = ''): string
    {
        $n = self::einstellung('tg_name');
        if ($n === '') { return ''; }
        return 'https://t.me/' . $n . ($start !== '' ? '?start=' . rawurlencode($start) : '');
    }

    /* ------------------------------ Senden ----------------------------- */

    /**
     * Eine Methode der Bot-API aufrufen.
     *
     * @return array{ok:bool, result:mixed, beschreibung:string, status:int}
     */
    public static function rufen(string $methode, array $daten = [], ?string $token = null): array
    {
        if (!preg_match('/^[A-Za-z]{3,40}$/', $methode)) {
            return ['ok' => false, 'result' => null, 'beschreibung' => 'unbekannte Methode', 'status' => 0];
        }
        if (self::$netz) {
            $r = (array) (self::$netz)($methode, $daten);
            return $r + ['ok' => false, 'result' => null, 'beschreibung' => '', 'status' => 200];
        }
        $token ??= self::token();
        if ($token === '') { return ['ok' => false, 'result' => null, 'beschreibung' => 'kein Token', 'status' => 0]; }

        $ch = curl_init(self::API . '/bot' . $token . '/' . $methode);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $roh = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $netzfehler = $roh === false ? 'keine Verbindung zu Telegram' : '';
        curl_close($ch);
        $j = is_string($roh) ? json_decode($roh, true) : null;
        $ok = is_array($j) && !empty($j['ok']);
        return [
            'ok' => $ok,
            'result' => $j['result'] ?? null,
            // Nur die Beschreibung von Telegram — nie die Adresse, in der der Token steht.
            'beschreibung' => $ok ? '' : mb_substr((string) ($j['description'] ?? $netzfehler ?: ('HTTP ' . $status)), 0, 200),
            'status' => $status,
        ];
    }

    /* --------------------------- Einstellungen ------------------------- */

    public static function einstellung(string $k, string $ersatz = ''): string
    {
        try { return (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], $ersatz); }
        catch (Throwable $e) { return $ersatz; }
    }

    public static function setzen(string $k, string $wert): void
    {
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $wert]);
    }

    private static function geheim(string $k): string
    {
        $blob = self::einstellung($k);
        if ($blob === '') { return ''; }
        require_once __DIR__ . '/Hosting.php';
        return (string) (Hosting::entsiegeln($blob)['wert'] ?? '');
    }

    private static function geheimSetzen(string $k, string $wert): void
    {
        require_once __DIR__ . '/Hosting.php';
        $blob = (string) Hosting::versiegeln(['wert' => $wert]);
        if ($blob === '') {
            throw new RuntimeException('Der Schlüssel ließ sich nicht verschlüsselt ablegen (hosting_geheim fehlt in der Konfiguration).');
        }
        self::setzen($k, $blob);
    }
}
