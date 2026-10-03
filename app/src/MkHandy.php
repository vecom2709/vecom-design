<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/MkInhalt.php';
require_once __DIR__ . '/MkKampagne.php';

/* ==========================================================================
   MkHandy.php — „Rest per Handy“ (01.10.2026, Uwe: Ja zu P3).

   TikTok, LinkedIn, das Google-Unternehmensprofil und YouTube lassen kleine
   Konten nicht ohne App-Prüfung automatisch posten. Damit diese Beiträge
   trotzdem pünktlich rausgehen, schickt der Vecom-Bot das freigegebene
   Stück zur Sendezeit in Uwes Admin-Chat: Bild oder Video, darunter der
   Text mit Link als eigene Nachricht (lange drücken → kopieren). Nach dem
   Posten drückt Uwe „Gepostet“ — dann zählt es wie jeder andere Beitrag.
   Dasselbe gilt für Formate, die Meta nicht per Schnittstelle annimmt
   (Story, Instagram-Karussell). Anzeigen bleiben im Werbeanzeigenmanager.
   ========================================================================== */
final class MkHandy
{
    /** Nur für die Kette: was verschickt wurde. */
    public static array $gesendet = [];

    /** Kommt dieses Stück per Handy? (organisch, aber nicht automatisch postbar) */
    public static function istHandy(array $x): bool
    {
        require_once __DIR__ . '/MkVeroeffentlichen.php';
        return ($x['art'] ?? '') === 'organisch' && !MkVeroeffentlichen::moeglich($x)['auto'];
    }

    /** @return list<int> */
    private static function chats(): array
    {
        require_once __DIR__ . '/TelegramMarketing.php';
        return TelegramMarketing::adminChats();
    }

    /** @return array{bereit:bool, text:string} */
    public static function stand(): array
    {
        require_once __DIR__ . '/Telegram.php';
        if (!Telegram::bereit()) { return ['bereit' => false, 'text' => 'Dafür braucht es den Vecom-Bot (Einstellungen › Telegram).']; }
        return self::chats() !== []
            ? ['bereit' => true, 'text' => 'Bereit: Der Bot schickt dir die Stücke in den verbundenen Admin-Chat.']
            : ['bereit' => false, 'text' => 'Der Bot läuft, aber dein Handy ist noch nicht als Admin-Chat verbunden (Einstellungen › Telegram › Verwaltung verbinden).'];
    }

    /** Auf den nächsten Sendeplatz legen. @return ?string Zeitpunkt oder null */
    public static function planen(int $id): ?string
    {
        require_once __DIR__ . '/MkVeroeffentlichen.php';
        $x = MkInhalt::laden($id);
        if ($x === null || $x['status'] !== 'freigegeben' || !self::istHandy($x) || !self::stand()['bereit']) { return null; }
        $slot = MkVeroeffentlichen::naechsterSlot((string) $x['plattform']);
        Db::update('mk_inhalte', $id, ['geplant_am' => $slot . ':00', 'post_fehler' => null]);
        return $slot;
    }

    private static function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    /** Zur Sendezeit: Stück aufs Handy. @return array{ok:bool, grund:?string} */
    public static function senden(array $x): array
    {
        require_once __DIR__ . '/Telegram.php';
        require_once __DIR__ . '/MkMedium.php';
        require_once __DIR__ . '/MkVeroeffentlichen.php';
        $chats = self::chats();
        if ($chats === []) { return ['ok' => false, 'grund' => 'Kein Admin-Chat verbunden — das Stück konnte nicht aufs Handy.']; }
        $id = (int) $x['id'];
        $pl = trim(preg_replace('/\s*\(.*\)$/u', '', (string) (MkKampagne::PLATTFORMEN[$x['plattform']] ?? $x['plattform'])) ?? '');
        $format = MkInhalt::FORMATE[$x['format']][0] ?? $x['format'];
        $video = MkMedium::gewaehlt($id, 'video');
        $bild = MkMedium::gewaehlt($id, 'bild');
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . rtrim(Config::basis(), '/');
        $kopf = '📲 <b>Jetzt posten: ' . self::h($pl) . '</b> · ' . self::h($format) . "\n" . self::h((string) $x['titel'])
              . "\n\n1. " . ($video ? 'Video' : ($bild ? 'Bild' : 'Text')) . ' teilen bzw. speichern → ' . self::h($pl) . ' öffnen'
              . "\n2. Den Text aus der nächsten Nachricht einfügen (lange drücken → Kopieren)"
              . (!in_array($x['plattform'], MkInhalt::LINK_IM_TEXT, true) ? "\n3. Den Link in Bio, Sticker oder Knopf setzen" : '')
              . "\nDanach „Gepostet“ drücken.";
        if (!$video && $x['format'] === 'reel') { $kopf .= "\n\n🎬 Für dieses Reel gibt es noch kein Video — das Skript steht im Text."; }
        $knoepfe = ['inline_keyboard' => [[['text' => '✅ Gepostet', 'callback_data' => 'v:mp:' . $id]],
                                          [['text' => '🛠 In der Verwaltung', 'url' => $basis . '/inhalte/' . $id]]]];
        /* TikTok verbunden (02.10.2026): ein Knopf zur Bestätigungsseite — dort geht es ohne Speichern, App und Einfügen raus. */
        require_once __DIR__ . '/MkPlattform.php';
        if ($x['plattform'] === 'tiktok' && $video && MkPlattform::einstellungen('tiktok')['verbunden']) {
            array_unshift($knoepfe['inline_keyboard'], [['text' => '🎵 Auf TikTok veröffentlichen', 'url' => $basis . '/tiktok/' . $id]]);
        }
        $ok = false;
        foreach ($chats as $chat) {
            $medium = $video ?? $bild;
            if ($medium) {
                $art = $medium['art'] === 'video' ? 'video' : 'photo';
                $d = ['chat_id' => $chat, $art => MkVeroeffentlichen::oeffentlich($medium) . ($art === 'photo' ? '&f=jpg' : ''), 'caption' => mb_substr($kopf, 0, 1024), 'parse_mode' => 'HTML'];
                self::$gesendet[] = [$art === 'video' ? 'sendVideo' : 'sendPhoto', $d];
                $r = Telegram::rufen($art === 'video' ? 'sendVideo' : 'sendPhoto', $d);
                if (!$r['ok']) {   // Medium nicht abrufbar: dann wenigstens der Kopf als Text
                    $d = ['chat_id' => $chat, 'text' => $kopf, 'parse_mode' => 'HTML'];
                    self::$gesendet[] = ['sendMessage', $d];
                    Telegram::rufen('sendMessage', $d);
                }
            } else {
                $d = ['chat_id' => $chat, 'text' => $kopf, 'parse_mode' => 'HTML'];
                self::$gesendet[] = ['sendMessage', $d];
                Telegram::rufen('sendMessage', $d);
            }
            $t = ['chat_id' => $chat, 'text' => mb_substr(MkInhalt::kopiertext($x), 0, 4000), 'reply_markup' => $knoepfe, 'link_preview_options' => ['is_disabled' => true]];
            self::$gesendet[] = ['sendMessage', $t];
            $ok = (bool) (Telegram::rufen('sendMessage', $t)['ok'] ?? false) || $ok;
        }
        if (!$ok) { return ['ok' => false, 'grund' => 'Telegram hat die Nachricht nicht angenommen.']; }
        $ids = json_decode((string) ($x['post_ids'] ?? ''), true) ?: [];
        $ids['handy'] = date('Y-m-d H:i');
        Db::update('mk_inhalte', $id, ['geplant_am' => null, 'post_ids' => json_encode($ids), 'post_fehler' => null]);
        return ['ok' => true, 'grund' => null];
    }

    /**
     * Story-Fassung eines eben veröffentlichten Instagram-Beitrags aufs Handy (03.10.2026, Uwe: Ja).
     * Der Link-Sticker ist der einzige klickbare Link in Instagram außer der Bio — und ihn setzt nur
     * die App. Also: Bild oder Video, darunter drei Schritte und der eigene Link zum Kopieren.
     * Kein „Gepostet“-Knopf: Die Story zählt über die Klicks auf den Link, nicht über eine Bestätigung.
     * @return array{ok:bool, grund:?string}
     */
    public static function story(array $x): array
    {
        require_once __DIR__ . '/Telegram.php';
        require_once __DIR__ . '/MkMedium.php';
        require_once __DIR__ . '/MkVeroeffentlichen.php';
        if (($x['plattform'] ?? '') !== 'instagram' || ($x['art'] ?? '') !== 'organisch') { return ['ok' => false, 'grund' => 'Nur für organische Instagram-Beiträge.']; }
        $chats = self::chats();
        if ($chats === []) { return ['ok' => false, 'grund' => 'Kein Admin-Chat verbunden.']; }
        $id = (int) $x['id'];
        $link = MkInhalt::link($x);
        if ($link === null) { return ['ok' => false, 'grund' => 'Das Stück hat noch keinen eigenen Link.']; }
        $medium = MkMedium::gewaehlt($id, 'video') ?? MkMedium::gewaehlt($id, 'bild');
        $wort = ($x['sprache'] ?? 'it') === 'de' ? 'Kostenloser Website-Check' : 'Analisi gratuita del sito';
        $kopf = '📲 <b>Story dazu (Instagram)</b>' . "
" . self::h((string) $x['titel'])
              . "

1. " . ($medium ? ($medium['art'] === 'video' ? 'Video' : 'Bild') . ' speichern' : 'Ein Bild aus dem Beitrag nehmen') . ' → Instagram → Story'
              . "
2. Sticker „Link“ → Link aus der nächsten Nachricht einfügen, Text: „" . self::h($wort) . '“'
              . "
3. Teilen — jeder Klick zählt wie beim Beitrag.";
        $ok = false;
        foreach ($chats as $chat) {
            if ($medium) {
                $art = $medium['art'] === 'video' ? 'video' : 'photo';
                $d = ['chat_id' => $chat, $art => MkVeroeffentlichen::oeffentlich($medium) . ($art === 'photo' ? '&f=jpg' : ''), 'caption' => mb_substr($kopf, 0, 1024), 'parse_mode' => 'HTML'];
                self::$gesendet[] = [$art === 'video' ? 'sendVideo' : 'sendPhoto', $d];
                if (!(Telegram::rufen($art === 'video' ? 'sendVideo' : 'sendPhoto', $d)['ok'] ?? false)) {
                    $d = ['chat_id' => $chat, 'text' => $kopf, 'parse_mode' => 'HTML'];
                    self::$gesendet[] = ['sendMessage', $d];
                    Telegram::rufen('sendMessage', $d);
                }
            } else {
                $d = ['chat_id' => $chat, 'text' => $kopf, 'parse_mode' => 'HTML'];
                self::$gesendet[] = ['sendMessage', $d];
                Telegram::rufen('sendMessage', $d);
            }
            $t = ['chat_id' => $chat, 'text' => $link, 'link_preview_options' => ['is_disabled' => true]];
            self::$gesendet[] = ['sendMessage', $t];
            $ok = (bool) (Telegram::rufen('sendMessage', $t)['ok'] ?? false) || $ok;
        }
        return $ok ? ['ok' => true, 'grund' => null] : ['ok' => false, 'grund' => 'Telegram hat die Nachricht nicht angenommen.'];
    }

    /** Wartet ein Stück auf „Gepostet“? (aufs Handy geschickt, noch nicht bestätigt) */
    public static function wartetAufBestaetigung(?string $land = null): int
    {
        try {
            $a = [];
            $sql = "SELECT COUNT(*) FROM mk_inhalte WHERE status = 'freigegeben' AND post_ids LIKE '%\"handy\"%'";
            if (in_array($land, ['IT', 'DE'], true)) { $sql .= ' AND land = ?'; $a[] = $land; }
            return (int) Db::wert($sql, $a, 0);
        } catch (Throwable $e) { return 0; }
    }
}
