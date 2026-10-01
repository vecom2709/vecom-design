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
    /* chat_member: Kanal-Beitritte je Einladungslink (Growth Engine T1). Seit 01.10.2026 (Kanal-Vorschläge 5 und 6):
       edited_message — nachträglich eingefügte Links in Kommentaren; my_chat_member — der Bot wurde in eine Gruppe
       geholt oder hat Rechte verloren; poll — Stimmen der eigenen Umfragen (nur Zahlen). */
    public const UPDATES = ['message', 'edited_message', 'callback_query', 'chat_member', 'my_chat_member', 'poll'];

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
        self::setzen('tg_updates', implode(',', self::UPDATES));

        self::texteSetzen();

        return ['ok' => true, 'text' => 'Angemeldet. Telegram schickt ab jetzt alles an ' . self::adresse() . '.'];
    }

    /**
     * Kommt eine neue Art von Update dazu (01.10.2026: chat_member), muss
     * Telegram das erfahren — sonst schickt es sie nie. Statt Uwe zum
     * erneuten Anmelden zu schicken, meldet der tägliche Lauf den Webhook
     * mit demselben Prüfwort nach. Kein drop_pending_updates: Es geht nichts
     * verloren, was gerade wartet.
     *
     * @return array{ok:bool, text:string}
     */
    public static function webhookNachziehen(): array
    {
        $soll = implode(',', self::UPDATES);
        if (self::einstellung('tg_updates') === $soll) { return ['ok' => true, 'text' => 'aktuell']; }
        if (!self::bereit() || self::einstellung('tg_webhook_am') === '') { return ['ok' => true, 'text' => 'nicht angemeldet']; }
        $r = self::rufen('setWebhook', [
            'url' => self::adresse(), 'secret_token' => self::pruefwort(),
            'allowed_updates' => self::UPDATES, 'max_connections' => 10,
        ]);
        if (!$r['ok']) { return ['ok' => false, 'text' => 'Telegram hat abgelehnt: ' . $r['beschreibung']]; }
        self::setzen('tg_updates', $soll);
        return ['ok' => true, 'text' => 'Webhook nachgezogen: ' . $soll];
    }

    /**
     * Befehle und Beschreibungen des Bots je Sprache. Scheitert das, läuft
     * der Bot trotzdem — es fehlt nur das Menü neben dem Eingabefeld.
     * Ist ein Kanal hinterlegt, nennt die Beschreibung (was Telegram vor
     * dem ersten „Starten“ zeigt) ihn mit — aufgerufen auch beim Speichern
     * des Kanals, damit ein neuer Link nicht erst beim nächsten Anmelden
     * ankommt.
     */
    public static function texteSetzen(): void
    {
        require_once __DIR__ . '/TelegramBot.php';
        $kanal = self::einstellung('tg_kanal_link');
        foreach (['it', 'de', 'en', ''] as $sp) {
            $T = TelegramBot::T[$sp === '' ? 'it' : $sp];
            $befehle = [];
            foreach ($T['befehle'] as $cmd => $was) { $befehle[] = ['command' => $cmd, 'description' => $was]; }
            $sprache = $sp === '' ? [] : ['language_code' => $sp];
            $beschreibung = $T['beschreibung'] . ($kanal !== '' ? "\n\n" . $T['k_kanal'] . ': ' . $kanal : '');
            self::rufen('setMyCommands', ['commands' => $befehle] + $sprache);
            self::rufen('setMyShortDescription', ['short_description' => $T['kurz']] + $sprache);
            self::rufen('setMyDescription', ['description' => mb_substr($beschreibung, 0, 512)] + $sprache);
        }
        // Verbundene Verwaltungs-Chats behalten ihre eigenen Befehle (/heute …) —
        // auch die, die schon verbunden waren, bevor es /heute gab.
        try {
            require_once __DIR__ . '/TelegramAdmin.php';
            foreach (Db::all('SELECT chat_id, admin_verbunden FROM telegram_chats WHERE admin_verbunden IS NOT NULL') as $r) {
                if (TelegramAdmin::darf((int) $r['admin_verbunden'])) { TelegramAdmin::befehleSetzen((int) $r['chat_id'], true); }
            }
        } catch (Throwable $e) { }
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
        $chats = $abgeschickt = $verbunden = 0; $letzte = '';
        try {
            $chats = (int) Db::wert('SELECT COUNT(*) FROM telegram_chats', [], 0);
            $abgeschickt = (int) Db::wert('SELECT COUNT(*) FROM telegram_chats WHERE anfrage_id IS NOT NULL', [], 0);
            $letzte = (string) Db::wert('SELECT MAX(letzte_am) FROM telegram_chats', [], '');
            $verbunden = (int) Db::wert('SELECT COUNT(*) FROM telegram_chats WHERE kunde_verbunden IS NOT NULL', [], 0);
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
            'verbunden' => $verbunden,
            'letzte' => $letzte,
            'kanal' => self::kanal(),
            'kanal_zuletzt' => self::einstellung('tg_kanal_zuletzt'),
        ];
    }

    /**
     * Darf jeder in den Bot-Chat — oder nur der Admin? (01.10.2026, Uwe:
     * „normale Nutzer außer Admin sollen nicht direkt in den Bot kommen,
     * nur über den Kanal“ → „Nur Admin“.) Ab Werk zu: Interessenten und
     * Kunden bekommen im Bot nur einen Hinweis auf den Kanal und das
     * Vecom-Fenster (Mini-App); Kunden-Hinweise per Telegram ruhen.
     * Umschaltbar unter Einstellungen → Telegram, ohne Code.
     */
    public static function botOffen(): bool { return self::einstellung('tg_bot_offen', '0') === '1'; }

    /** Der öffentliche Link zum Bot, z. B. für die Website. */
    public static function link(string $start = ''): string
    {
        $n = self::einstellung('tg_name');
        if ($n === '') { return ''; }
        return 'https://t.me/' . $n . ($start !== '' ? '?start=' . rawurlencode($start) : '');
    }

    /* ------------------------------ Kanal ------------------------------ */

    /* DER KANAL „VECOM DESIGN“ (30.09.2026, Uwe: „mach automatisch“)

       Ein Kanal ist ein Sender, keine Unterhaltung: Uwe veröffentlicht dort
       Neuigkeiten, Interessenten lesen mit und landen über den Knopf unter
       jedem Beitrag im Bot (?start=kanal → „kam über: kanal“ im Verlauf).

       Der Bot ist im Kanal Admin mit genau zwei Rechten: posten und
       bearbeiten — seit dem 01.10.2026 (Growth Engine T1) zusätzlich
       „Nutzer einladen“, damit er je Kampagne einen eigenen Einladungslink
       anlegen und Beitritte darüber zählen kann (nur auf Klick in der
       Verwaltung). Er kann dort nichts löschen, niemanden sperren und keine
       Admins ernennen — gesetzt von Hand in Telegram,
       geprüft hier bei jedem Speichern (getChatMember).

       Der Link ist ein eigenes Feld, weil ein privater Kanal keinen
       Namen hat, aus dem er sich ergäbe (t.me/+…), und der Bot die
       Einladungslinks ohne das Recht „Nutzer einladen“ nicht lesen darf. */

    /** @return array{id:string, titel:string, link:string} */
    public static function kanal(): array
    {
        return [
            'id' => self::einstellung('tg_kanal_id'),
            'titel' => self::einstellung('tg_kanal_titel'),
            'link' => self::einstellung('tg_kanal_link'),
        ];
    }

    /**
     * Kanal prüfen und merken. $wer ist die Kennung (-100…) oder @name.
     * Leeres $wer löst die Verbindung.
     *
     * @return array{ok:bool, text:string}
     */
    public static function kanalSetzen(string $wer, string $link): array
    {
        $wer = trim($wer);
        $link = trim($link);
        if ($wer === '') {
            foreach (['tg_kanal_id', 'tg_kanal_titel', 'tg_kanal_link'] as $k) { self::setzen($k, ''); }
            if (self::bereit()) { self::texteSetzen(); }
            return ['ok' => true, 'text' => 'Der Kanal ist nicht mehr hinterlegt.'];
        }
        if (preg_match('~^(?:https?://)?t\.me/([A-Za-z][A-Za-z0-9_]{4,31})/?$~', $wer, $m)) { $wer = '@' . $m[1]; }
        if (!preg_match('/^(-100\d{5,15}|@[A-Za-z][A-Za-z0-9_]{4,31})$/', $wer)) {
            return ['ok' => false, 'text' => 'Kanal bitte als Kennung (-100…) oder als @Name angeben.'];
        }
        if ($link !== '' && !preg_match('~^https://t\.me/(\+[A-Za-z0-9_-]{8,64}|[A-Za-z][A-Za-z0-9_]{4,31})$~', $link)) {
            return ['ok' => false, 'text' => 'Der Link muss mit https://t.me/ beginnen.'];
        }
        if (!self::bereit()) { return ['ok' => false, 'text' => 'Erst den Bot einrichten (Token + Webhook).']; }

        $chat = self::rufen('getChat', ['chat_id' => $wer]);
        if (!$chat['ok'] || ($chat['result']['type'] ?? '') !== 'channel') {
            return ['ok' => false, 'text' => 'Telegram kennt diesen Kanal nicht, oder der Bot ist dort nicht Mitglied'
                . ($chat['beschreibung'] !== '' ? ' (' . $chat['beschreibung'] . ')' : '') . '.'];
        }
        $me = self::rufen('getMe');
        $rolle = self::rufen('getChatMember', ['chat_id' => $wer, 'user_id' => (int) ($me['result']['id'] ?? 0)]);
        $r = (array) ($rolle['result'] ?? []);
        if (($r['status'] ?? '') !== 'administrator' || empty($r['can_post_messages'])) {
            return ['ok' => false, 'text' => 'Der Bot ist in diesem Kanal nicht Admin mit dem Recht „Beiträge veröffentlichen“.'];
        }
        $name = (string) ($chat['result']['username'] ?? '');
        if ($link === '' && $name !== '') { $link = 'https://t.me/' . $name; }
        $neueId = (string) ($chat['result']['id'] ?? $wer);
        // Ein anderer Kanal: Der gemerkte Menü-Beitrag gehört zum alten.
        if ($neueId !== self::einstellung('tg_kanal_id')) { self::setzen('tg_kanal_menue_id', ''); }
        self::setzen('tg_kanal_id', $neueId);
        self::setzen('tg_kanal_titel', mb_substr((string) ($chat['result']['title'] ?? ''), 0, 120));
        self::setzen('tg_kanal_link', $link);
        self::texteSetzen();
        // Mehr Rechte als nötig sind kein Fehler, aber ein Hinweis wert.
        $zuviel = array_keys(array_filter([
            'löschen' => !empty($r['can_delete_messages']),
            'Admins ernennen' => !empty($r['can_promote_members']),
            'Kanal ändern' => !empty($r['can_change_info']),
        ]));
        return ['ok' => true, 'text' => 'Kanal „' . ($chat['result']['title'] ?? $wer) . '“ hinterlegt.'
            . ($zuviel ? ' Hinweis: Der Bot darf dort mehr als nötig (' . implode(', ', $zuviel) . ').' : '')];
    }

    /** Die längste Nachricht, die Telegram annimmt. */
    public const KANAL_MAX = 4000;

    /**
     * Einen Beitrag im Kanal veröffentlichen. Reiner Text, keine HTML-Deutung —
     * was Uwe tippt, erscheint so. Unter dem Beitrag ein Knopf in den Bot.
     *
     * @return array{ok:bool, text:string}
     */
    public static function kanalPosten(string $text, string $knopf = ''): array
    {
        $text = trim(str_replace("\r\n", "\n", $text));
        $k = self::kanal();
        if ($k['id'] === '') { return ['ok' => false, 'text' => 'Es ist noch kein Kanal hinterlegt.']; }
        if ($text === '') { return ['ok' => false, 'text' => 'Der Beitrag ist leer.']; }
        if (mb_strlen($text) > self::KANAL_MAX) { return ['ok' => false, 'text' => 'Der Beitrag ist länger als ' . self::KANAL_MAX . ' Zeichen.']; }
        $daten = ['chat_id' => $k['id'], 'text' => $text];
        $knopf = trim($knopf);
        $bot = self::link('kanal');
        if ($knopf !== '' && $bot !== '') {
            $daten['reply_markup'] = ['inline_keyboard' => [[['text' => mb_substr($knopf, 0, 40), 'url' => $bot]]]];
        }
        $r = self::rufen('sendMessage', $daten);
        if (!$r['ok']) { return ['ok' => false, 'text' => 'Telegram hat den Beitrag nicht angenommen: ' . $r['beschreibung']]; }
        self::setzen('tg_kanal_zuletzt', date('Y-m-d H:i:s'));
        return ['ok' => true, 'text' => 'Der Beitrag steht im Kanal.'];
    }

    /**
     * Der Menü-Beitrag im Kanal: dieselben Punkte wie im Bot-Menü, als Knöpfe.
     *
     * Ein Kanal kann keine Unterhaltung führen — ein Knopf dort öffnet den
     * Bot (t.me/BOT?start=kanal-preis) und springt direkt zum Punkt. Der
     * Beitrag wird einmal gesendet und angeheftet; danach wird DERSELBE
     * Beitrag bearbeitet statt ein zweiter gesendet, damit oben im Kanal
     * immer genau ein Menü steht.
     *
     * @return array{ok:bool, text:string}
     */
    public static function kanalMenue(string $sp = 'auto'): array
    {
        require_once __DIR__ . '/Texte.php';
        $k = self::kanal();
        if ($k['id'] === '') { return ['ok' => false, 'text' => 'Es ist noch kein Kanal hinterlegt.']; }
        if (self::einstellung('tg_name') === '') { return ['ok' => false, 'text' => 'Der Bot ist nicht eingerichtet.']; }
        /* ZWEISPRACHIG (01.10.2026, Uwe: „Alles“ — Vorschlag 3): Text italienisch und deutsch,
           Knöpfe italienisch (die Kunden sind vor allem Betriebe um Agrigent), und die
           Knöpfe tragen KEINE Sprache mehr — das Fenster nimmt die, die der Nutzer in
           Telegram eingestellt hat (telegram-app.php). Mit fester Sprache wie bisher. */
        $auto = !isset(Texte::TELEGRAM[$sp]);
        $T = Texte::TELEGRAM[$auto ? 'it' : $sp];
        $l = static fn(string $wort): string => self::link('kanal-' . $wort);
        $param = static fn(string $ziel): string => 'kanal-' . ($auto ? '' : $sp . '-') . $ziel;
        // Die drei Rechner-Knöpfe öffnen die Mini-App über dem Kanal, sobald
        // sie bei @BotFather angemeldet ist — vorher den Bot wie bisher.
        require_once __DIR__ . '/TelegramApp.php';
        $r = static fn(string $wort): string => TelegramApp::link($param($wort)) ?: $l($wort);
        /* Seit 01.10.2026 (Uwe: „nur über den Kanal“) öffnet JEDER Knopf das
           Vecom-Fenster über dem Kanal (telegram-menue.php) — keiner führt mehr
           in den Bot-Chat. Ohne angemeldete Mini-App bleibt der alte Weg. */
        $f = static fn(string $ziel, string $wort): string => TelegramApp::link($param($ziel)) ?: $l($wort);
        $knoepfe = [
            [['text' => $T['k_preis'], 'url' => $r('preis')]],
            [['text' => $T['k_neu'], 'url' => $r('neu')], ['text' => $T['k_besser'], 'url' => $r('besser')]],
            [['text' => $T['k_pruefen'], 'url' => $f('pruefen', 'pruefen')], ['text' => $T['k_ki'], 'url' => $f('ki', 'ki')]],
            [['text' => $T['k_bots'], 'url' => $f('bots', 'mensch')], ['text' => $T['k_3d'], 'url' => $f('dreid', 'dreid')]],
            [['text' => $T['k_logo'], 'url' => $f('logo', 'logo')], ['text' => $T['k_hosting'], 'url' => $f('hosting', 'hosting')]],
            [['text' => $T['k_mensch'], 'url' => $f('mensch', 'mensch')], ['text' => $T['k_partner'], 'url' => $f('partner', 'partner')]],
            [['text' => $T['k_kunde'], 'url' => $f('kunde', 'kunde')]],
        ];
        $text = $auto ? self::kanalMenueText() : $T['kanalMenue'];
        $daten = ['chat_id' => $k['id'], 'text' => $text, 'reply_markup' => ['inline_keyboard' => $knoepfe]];

        $alt = (int) self::einstellung('tg_kanal_menue_id', '0');
        if ($alt > 0) {
            $r = self::rufen('editMessageText', $daten + ['message_id' => $alt]);
            // „not modified“ heißt: steht schon genau so da — auch gut.
            if ($r['ok'] || str_contains($r['beschreibung'], 'not modified')) {
                return ['ok' => true, 'text' => 'Der Menü-Beitrag im Kanal ist aktuell.'];
            }
            // Gelöscht oder zu alt zum Bearbeiten: neu senden.
        }
        $r = self::rufen('sendMessage', $daten);
        if (!$r['ok']) { return ['ok' => false, 'text' => 'Telegram hat den Menü-Beitrag nicht angenommen: ' . $r['beschreibung']]; }
        $id = (int) ($r['result']['message_id'] ?? 0);
        self::setzen('tg_kanal_menue_id', (string) $id);
        $p = self::rufen('pinChatMessage', ['chat_id' => $k['id'], 'message_id' => $id, 'disable_notification' => true]);
        return ['ok' => true, 'text' => 'Der Menü-Beitrag steht im Kanal' . ($p['ok'] ? ' und ist oben angeheftet.' : ' — anheften bitte von Hand (' . $p['beschreibung'] . ').')];
    }

    /** Der Text des Menü-Beitrags auf Italienisch und Deutsch — aus denselben Texten wie bisher, mit Fähnchen statt doppeltem Gruß. */
    public static function kanalMenueText(): string
    {
        require_once __DIR__ . '/Texte.php';
        $ohneGruss = static fn(string $t): string => trim((string) preg_replace('/^\x{1F44B}\s*/u', '', $t));
        return "\u{1F1EE}\u{1F1F9} " . $ohneGruss(Texte::TELEGRAM['it']['kanalMenue']) . "\n\n\u{1F1E9}\u{1F1EA} " . $ohneGruss(Texte::TELEGRAM['de']['kanalMenue']);
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

    /**
     * Eine Datei, die jemand dem Bot geschickt hat, auf den Server holen.
     *
     * Telegram gibt Bots Dateien bis 20 MB heraus (getFile). Größere kommen
     * gar nicht erst an — das sagt der Bot dem Kunden vorher.
     *
     * @return string Pfad der Zwischendatei (der Aufrufer legt sie ab oder löscht sie)
     */
    public static function dateiHolen(string $dateiId, int $hoechstens): string
    {
        $r = self::rufen('getFile', ['file_id' => $dateiId]);
        $pfad = (string) ($r['result']['file_path'] ?? '');
        if (!$r['ok'] || $pfad === '' || !preg_match('~^[A-Za-z0-9_./-]{1,200}$~', $pfad) || str_contains($pfad, '..')) {
            throw new RuntimeException('Telegram gibt die Datei nicht heraus.');
        }
        if ((int) ($r['result']['file_size'] ?? 0) > $hoechstens) {
            throw new RuntimeException('Die Datei ist zu groß.');
        }
        $ziel = (string) tempnam(sys_get_temp_dir(), 'tg');
        if (self::$netz) {
            $d = (array) (self::$netz)('__datei', ['file_path' => $pfad]);
            file_put_contents($ziel, (string) ($d['inhalt'] ?? ''));
            return $ziel;
        }
        $fh = fopen($ziel, 'wb');
        $ch = curl_init(self::API . '/file/bot' . self::token() . '/' . $pfad);
        $geladen = 0;
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fh, CURLOPT_TIMEOUT => 40, CURLOPT_CONNECTTIMEOUT => 5,
            // Mitten im Laden abbrechen, wenn es größer wird als erlaubt.
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => static function ($c, $gesamt, $jetzt) use ($hoechstens, &$geladen): int {
                $geladen = (int) $jetzt;
                return $jetzt > $hoechstens ? 1 : 0;
            },
        ]);
        $ok = curl_exec($ch) !== false && (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200;
        curl_close($ch);
        fclose($fh);
        if (!$ok) { @unlink($ziel); throw new RuntimeException('Die Datei ließ sich nicht von Telegram laden.'); }
        return $ziel;
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
