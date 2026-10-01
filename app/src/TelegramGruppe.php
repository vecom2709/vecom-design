<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Telegram.php';

/* ==========================================================================
   TelegramGruppe.php — Kommentare unter den Kanal-Beiträgen, mit Schutz
   (01.10.2026, Uwe: „Alles“ — Vorschlag 5).

   In Telegram kommentiert man einen Kanal-Beitrag in einer verknüpften
   Diskussionsgruppe. Dort schreiben Fremde — und wo Fremde schreiben, kommt
   Werbung: Links, @Erwähnungen anderer Kanäle, weitergeleitete Beiträge,
   Nachrichten „als Kanal“. Der Bot ist in der Gruppe Admin mit genau einem
   Recht, das er dafür braucht: Nachrichten löschen.

   WAS ER TUT
     * Ein Kommentar mit Link, @Erwähnung, E-Mail-Adresse, Telefonnummer,
       Weiterleitung, Knöpfen oder „als fremder Kanal“ wird gelöscht — außer
       er kommt vom Team (Admin der Gruppe) oder zeigt auf Vecom selbst.
       Uwe bekommt einen Zuruf (höchstens einer je Stunde), ohne Inhalt und
       ohne Absender.
     * Eine Frage (Fragezeichen) von außen meldet er Uwe mit dem Link zum
       Kommentar — höchstens einmal je halbe Stunde.
     * In jeder anderen Gruppe, in die ihn jemand holt, verabschiedet er
       sich sofort (leaveChat). Der Bot spricht nur dort, wo Vecom spricht.

   WAS ER NICHT TUT
     * Er sperrt niemanden und schränkt niemanden ein — das bleibt Uwes
       Entscheidung in Telegram.
     * Er speichert keinen Kommentar, keinen Namen, keine Kennung. Gezählt
       wird je Tag: Kommentare, gelöschte Kommentare (tg_tage). Im
       Webhook-Protokoll steht nur der Vermerk (z. B. „geloescht:Link“).
     * Er antwortet nicht in der Gruppe — der Bot-Chat bleibt der Ort für
       Gespräche.

   Erkannt wird die Gruppe über getChat(Kanal).linked_chat_id: per Knopf in
   den Einstellungen, oder von selbst, sobald der Bot dort hinzugefügt wird
   (my_chat_member) oder die erste Nachricht kommt.
   ========================================================================== */

final class TelegramGruppe
{
    /** Was in einem Kommentar Inhalt ist — alles andere sind Dienstmeldungen (Beitritt, Angeheftet …). */
    private const INHALT = ['text', 'caption', 'photo', 'video', 'document', 'sticker', 'animation', 'voice', 'video_note', 'audio',
                            'poll', 'contact', 'location', 'venue', 'dice', 'story'];

    public static function id(): string { return Telegram::einstellung('tg_gruppe_id'); }

    /** @return array{id:string, titel:string, name:string, schutz:bool} */
    public static function stand(): array
    {
        return ['id' => self::id(), 'titel' => Telegram::einstellung('tg_gruppe_titel'), 'name' => Telegram::einstellung('tg_gruppe_name'),
                'schutz' => Telegram::einstellung('tg_gruppe_schutz') === '1'];
    }

    /**
     * Diskussionsgruppe des Kanals finden und prüfen, ob der Bot dort löschen darf.
     * @return array{ok:bool, text:string}
     */
    public static function pruefen(): array
    {
        $k = Telegram::kanal();
        if ($k['id'] === '' || !Telegram::bereit()) { return ['ok' => false, 'text' => 'Erst Bot und Kanal verbinden.']; }
        $c = Telegram::rufen('getChat', ['chat_id' => $k['id']]);
        if (!$c['ok']) { return ['ok' => false, 'text' => 'Telegram hat den Kanal nicht gezeigt' . ($c['beschreibung'] !== '' ? ' (' . $c['beschreibung'] . ')' : '') . '.']; }
        $gid = (string) ($c['result']['linked_chat_id'] ?? '');
        if (!preg_match('/^-100\d{5,15}$/', $gid)) {
            foreach (['tg_gruppe_id', 'tg_gruppe_titel', 'tg_gruppe_name', 'tg_gruppe_schutz'] as $s) { Telegram::setzen($s, ''); }
            return ['ok' => false, 'text' => 'Am Kanal hängt noch keine Diskussionsgruppe. In Telegram: Kanal › Bearbeiten › Diskussion › Gruppe hinzufügen, '
                . 'dann den Bot dort als Admin mit „Nachrichten löschen“ eintragen und hier noch einmal prüfen.'];
        }
        $g = Telegram::rufen('getChat', ['chat_id' => $gid]);
        $me = Telegram::rufen('getMe');
        $rolle = (array) (Telegram::rufen('getChatMember', ['chat_id' => $gid, 'user_id' => (int) ($me['result']['id'] ?? 0)])['result'] ?? []);
        $titel = mb_substr((string) ($g['result']['title'] ?? ''), 0, 120);
        $name = (string) ($g['result']['username'] ?? '');
        $darf = ($rolle['status'] ?? '') === 'administrator' && !empty($rolle['can_delete_messages']);
        Telegram::setzen('tg_gruppe_id', $gid);
        Telegram::setzen('tg_gruppe_titel', $titel);
        Telegram::setzen('tg_gruppe_name', preg_match('/^[A-Za-z][A-Za-z0-9_]{3,63}$/', $name) ? $name : '');
        Telegram::setzen('tg_gruppe_schutz', $darf ? '1' : '0');
        // Ohne my_chat_member und edited_message sähe der Bot Rechte-Änderungen und nachträglich eingefügte Links nicht.
        Telegram::webhookNachziehen();
        $wer = $titel !== '' ? '„' . $titel . '“' : 'die Diskussionsgruppe';
        if (!$darf) {
            return ['ok' => false, 'text' => 'Gefunden: ' . $wer . '. Der Bot ist dort aber noch nicht Admin mit dem Recht „Nachrichten löschen“ — '
                . 'ohne das kann er Werbung nicht entfernen. In der Gruppe: Verwalten › Administratoren › Bot hinzufügen, nur „Nachrichten löschen“.'];
        }
        return ['ok' => true, 'text' => 'Kommentare geschützt: Der Bot prüft neue Kommentare in ' . $wer . ' und löscht Links und Fremdwerbung von Absendern außerhalb des Teams.'];
    }

    /**
     * Eine Nachricht aus einer Gruppe (Webhook: message oder edited_message).
     * @return string Vermerk fürs Protokoll — nie Inhalt oder Absender
     */
    public static function nachricht(array $m): string
    {
        $chat = (string) ($m['chat']['id'] ?? '');
        if ($chat === '') { return 'ignoriert'; }
        if ($chat !== self::id()) {
            $f = self::fremd($chat);
            if ($f !== 'unsere_gruppe') { return $f; }
        }
        // Was nicht von Menschen außerhalb kommt: der Kanal selbst (automatische Weiterleitung), anonyme Admins, Dienstmeldungen.
        if (!empty($m['is_automatic_forward'])) { return 'kanal'; }
        $sc = (string) ($m['sender_chat']['id'] ?? '');
        if ($sc !== '' && ($sc === $chat || $sc === Telegram::kanal()['id'])) { return 'team'; }
        if (!array_intersect(self::INHALT, array_keys($m))) { return 'dienst'; }
        if (!empty($m['from']['is_bot']) && $sc === '') { return 'bot'; }

        require_once __DIR__ . '/TelegramWachstum.php';
        if (!isset($m['edit_date'])) { TelegramWachstum::zaehlen('kommentar'); }
        $grund = self::verdacht($m);
        if ($grund !== '') {
            if (self::team($chat, (int) ($m['from']['id'] ?? 0), $sc)) { return 'team'; }
            $r = Telegram::rufen('deleteMessage', ['chat_id' => $chat, 'message_id' => (int) ($m['message_id'] ?? 0)]);
            if ($r['ok']) {
                TelegramWachstum::zaehlen('kommentar_weg');
                self::zuruf('tg_kommentar_weg', '🧹 Telegram: Ein Kommentar unter dem Kanal wurde gelöscht (' . $grund . ', Absender nicht im Team). '
                    . 'Die Zahlen stehen im Wochenbericht.', 60);
                return 'geloescht:' . $grund;
            }
            self::zuruf('tg_kommentar_recht', '⚠️ Telegram: Ein Kommentar mit ' . $grund . ' ließ sich nicht löschen — hat der Bot in der Diskussionsgruppe noch das Recht „Nachrichten löschen“?', 24 * 60);
            return 'nicht_geloescht';
        }
        if (!isset($m['edit_date']) && str_contains((string) ($m['text'] ?? $m['caption'] ?? ''), '?') && !self::team($chat, (int) ($m['from']['id'] ?? 0), $sc)) {
            self::zuruf('tg_kommentar_frage', '💬 Telegram: Neue Frage unter einem Kanal-Beitrag — ' . self::link($chat, (int) ($m['message_id'] ?? 0)), 30);
            return 'frage';
        }
        return 'ok';
    }

    /**
     * Was an einem Kommentar nach Werbung aussieht. Leer = nichts.
     * Eigene Adressen (vecom-design.it, der eigene Kanal, der eigene Bot) sind erlaubt.
     */
    public static function verdacht(array $m): string
    {
        if (isset($m['forward_origin']) || isset($m['forward_from']) || isset($m['forward_from_chat']) || isset($m['forward_sender_name'])) { return 'Weiterleitung'; }
        $sc = (string) ($m['sender_chat']['id'] ?? '');
        if ($sc !== '' && $sc !== (string) ($m['chat']['id'] ?? '') && $sc !== Telegram::kanal()['id']) { return 'fremder Kanal'; }
        if (isset($m['via_bot'])) { return 'Inline-Bot'; }
        if (isset($m['reply_markup'])) { return 'Knöpfe'; }
        if (isset($m['contact'])) { return 'Kontaktkarte'; }
        if (isset($m['story'])) { return 'Story'; }
        $text = (string) ($m['text'] ?? $m['caption'] ?? '');
        foreach (array_merge((array) ($m['entities'] ?? []), (array) ($m['caption_entities'] ?? [])) as $e) {
            $typ = (string) ($e['type'] ?? '');
            $teil = self::teil($text, (int) ($e['offset'] ?? 0), (int) ($e['length'] ?? 0));
            if ($typ === 'text_link' && !self::eigen((string) ($e['url'] ?? ''))) { return 'Link'; }
            if ($typ === 'url' && !self::eigen($teil)) { return 'Link'; }
            if ($typ === 'mention' && !self::eigen('t.me/' . ltrim($teil, '@'))) { return '@-Erwähnung'; }
            if ($typ === 'email') { return 'E-Mail-Adresse'; }
            if ($typ === 'phone_number') { return 'Telefonnummer'; }
        }
        // Auch ohne Markierung von Telegram (z. B. „t . me/…“ zusammengezogen, oder Bildunterschriften älterer Apps).
        $flach = (string) preg_replace('/\s*([.\/])\s*/u', '$1', $text);
        if (preg_match_all('~(?:https?://|www\.)\S+|\b(?:t\.me|telegram\.me|telegram\.dog|wa\.me|chat\.whatsapp\.com|bit\.ly|tinyurl\.com)/\S+~iu', $flach, $treffer)) {
            foreach ($treffer[0] as $u) { if (!self::eigen($u)) { return 'Link'; } }
        }
        return '';
    }

    /** Zeigt die Adresse auf Vecom selbst? */
    public static function eigen(string $url): bool
    {
        $u = strtolower(trim($url));
        $u = (string) preg_replace('~^(?:https?://)?(?:www\.)?~', '', $u);
        $host = (string) strtok($u, '/?#');
        if (in_array($host, ['vecom-design.it', 'vecom-design.com'], true)) { return true; }
        if (!in_array($host, ['t.me', 'telegram.me', 'telegram.dog'], true)) { return false; }
        $pfad = substr($u, strlen($host) + 1);
        $erster = strtolower((string) strtok($pfad, '/?#'));
        if ($erster === '') { return false; }
        $eigene = array_filter([
            preg_match('~^https://t\.me/([A-Za-z0-9_]{4,64})/?$~', Telegram::kanal()['link'], $km) ? strtolower($km[1]) : '',
            strtolower(Telegram::einstellung('tg_name')),
            strtolower(Telegram::einstellung('tg_gruppe_name')),
        ]);
        return in_array($erster, $eigene, true);
    }

    /** Teil eines Telegram-Textes: Telegram zählt Versatz und Länge in UTF-16-Einheiten. */
    private static function teil(string $text, int $offset, int $laenge): string
    {
        if ($laenge <= 0) { return ''; }
        $u = (string) mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
        return (string) mb_convert_encoding(substr($u, $offset * 2, $laenge * 2), 'UTF-8', 'UTF-16LE');
    }

    /** Gehört der Absender zum Team? Nur bei Verdacht gefragt — ein Aufruf bei Telegram. */
    private static function team(string $chat, int $von, string $senderChat): bool
    {
        if ($senderChat !== '' || $von <= 0) { return false; }
        $r = Telegram::rufen('getChatMember', ['chat_id' => $chat, 'user_id' => $von]);
        return $r['ok'] && in_array((string) ($r['result']['status'] ?? ''), ['creator', 'administrator'], true);
    }

    /** Der Link zu einem Kommentar — für Mitglieder der Gruppe. */
    public static function link(string $chat, int $msg): string
    {
        $name = Telegram::einstellung('tg_gruppe_name');
        if ($name !== '') { return 'https://t.me/' . $name . '/' . $msg; }
        return 'https://t.me/c/' . preg_replace('/^-100/', '', $chat) . '/' . $msg;
    }

    /**
     * Eine Gruppe, die nicht als unsere bekannt ist. Hängt sie am Kanal, wird
     * sie übernommen; sonst verlässt der Bot sie.
     * @return string 'unsere_gruppe' oder Vermerk
     */
    public static function fremd(string $chat): string
    {
        $k = Telegram::kanal();
        if ($k['id'] !== '') {
            $c = Telegram::rufen('getChat', ['chat_id' => $k['id']]);
            if ($c['ok'] && (string) ($c['result']['linked_chat_id'] ?? '') === $chat) {
                self::pruefen();
                return 'unsere_gruppe';
            }
        }
        Telegram::rufen('leaveChat', ['chat_id' => $chat]);
        return 'fremde_gruppe_verlassen';
    }

    /** my_chat_member: Der Bot wurde irgendwo hinzugefügt, entfernt oder hat neue Rechte. */
    public static function meinStatus(array $mcm): string
    {
        $typ = (string) ($mcm['chat']['type'] ?? '');
        $chat = (string) ($mcm['chat']['id'] ?? '');
        $neu = (string) ($mcm['new_chat_member']['status'] ?? '');
        if (in_array($typ, ['group', 'supergroup'], true)) {
            if (in_array($neu, ['member', 'administrator', 'restricted'], true)) {
                if ($chat === self::id()) { self::pruefen(); return 'gruppe_rechte'; }
                return self::fremd($chat) === 'unsere_gruppe' ? 'gruppe_erkannt' : 'fremde_gruppe_verlassen';
            }
            if ($chat === self::id()) {
                Telegram::setzen('tg_gruppe_schutz', '0');
                self::zuruf('tg_gruppe_raus', '⚠️ Telegram: Der Bot ist nicht mehr in der Diskussionsgruppe — Kommentare sind ungeschützt.', 24 * 60);
                return 'gruppe_raus';
            }
            return 'gruppe';
        }
        if ($typ === 'channel' && $chat !== '' && $chat === Telegram::kanal()['id']) {
            if ($neu !== 'administrator') {
                self::zuruf('tg_kanal_raus', '⚠️ Telegram: Der Bot ist im Kanal nicht mehr Admin — Beiträge, Menü und Einladungslinks gehen nicht mehr.', 24 * 60);
            }
            return 'kanal:' . preg_replace('/[^a-z]/', '', $neu);
        }
        return 'privat';
    }

    private static function zuruf(string $anlass, string $text, int $sperre): void
    {
        try { require_once __DIR__ . '/Zuruf.php'; Zuruf::vormerken($anlass, $text, $sperre); } catch (Throwable $e) { /* Beiwerk */ }
    }
}
