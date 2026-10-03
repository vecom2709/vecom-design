<?php
declare(strict_types=1);

require_once __DIR__ . '/Telegram.php';
require_once __DIR__ . '/TelegramAdmin.php';
require_once __DIR__ . '/MkInhalt.php';
require_once __DIR__ . '/MkMedium.php';
require_once __DIR__ . '/MkVeroeffentlichen.php';
require_once __DIR__ . '/MkLand.php';

/* ==========================================================================
   TelegramMarketing.php — der Freigabe-Stapel im Telegram-Chat des Admins
   (Marketing-Studio 7, 01.10.2026, Uwe: „ja“ zu U4 — „du bekommst sie per
   Telegram zur Freigabe … ohne deinen Klick geht nichts raus“).

   Sind Texte und Bilder einer Kampagne fertig, bekommt jeder verbundene
   Admin-Chat (beide Schlösser, TelegramAdmin::darfChat) EINE Nachricht:
   „7 neue Entwürfe für Italien“ mit „▶ Durchgehen“. Dann ein Stück nach dem
   anderen — Bild, Text, deutsche Fassung, was „Ja“ tut — und drei Knöpfe:
   ✅ Ja (freigeben, auf den nächsten freien Abend legen), ❌ Nein, ⏭ Später.
   Dieselben Funktionen wie im Reiter „Freigeben“; nichts Eigenes daneben.

   Knöpfe: v:mg:<land> (durchgehen), v:mj:<id>, v:mn:<id>, v:ms:<id>.
   Der Admin-Riegel steht in TelegramBot (case 'v'), hier wird nur gehandelt.
   ========================================================================== */

final class TelegramMarketing
{
    private const FLAGGE = ['IT' => '🇮🇹', 'DE' => '🇩🇪'];

    /** Nur für die Kette: was verschickt wurde. */
    public static array $gesendet = [];

    private static function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    /** Die Admin-Chats, die beide Schlösser haben. @return list<int> */
    public static function adminChats(): array
    {
        $aus = [];
        try {
            if (!Telegram::bereit()) { return []; }
            foreach (Db::all('SELECT id, chat_id, admin_verbunden FROM telegram_chats WHERE admin_verbunden IS NOT NULL') as $r) {
                if (TelegramAdmin::darfChat($r)) { $aus[] = (int) $r['chat_id']; }
            }
        } catch (Throwable $e) { }
        return array_values(array_unique($aus));
    }

    /**
     * Fertig? Wenn zu einem Kampagnen-Auftrag keine Bilder mehr entstehen,
     * einmal Bescheid geben. Wirft nie.
     */
    public static function vielleichtMelden(int $auftragId): bool
    {
        try {
            $a = Db::one("SELECT * FROM mk_auftraege WHERE id = ? AND art = 'inhalte' AND status = 'fertig' AND gemeldet_am IS NULL", [$auftragId]);
            if (!$a) { return false; }
            $p = json_decode((string) $a['parameter'], true) ?: [];
            if (empty($p['paket']) && empty($p['kanalplan']) && empty($p['tiktok_takt'])) { return false; }
            $offen = (int) Db::wert("SELECT COUNT(*) FROM mk_auftraege m JOIN mk_inhalte i ON i.auftrag_id = ? AND m.parameter LIKE CONCAT('%\"inhalt_id\":', i.id, ',%')
                                      WHERE m.art = 'medien' AND m.status IN ('wartet', 'laeuft')", [$auftragId], 0);
            if ($offen > 0) { return false; }
            $n = Db::run('UPDATE mk_auftraege SET gemeldet_am = NOW() WHERE id = ? AND gemeldet_am IS NULL', [$auftragId])->rowCount();
            if ($n === 0) { return false; }
            return self::melden((string) $a['land'], (!empty($p['kanalplan']) ? 'Kanal-Plan Telegram · ' : (!empty($p['tiktok_takt']) ? 'TikTok täglich · ' : '')) . (string) ($p['zielgruppe_titel'] ?? ''), !empty($p['autopilot']));
        } catch (Throwable $e) { return false; }
    }

    /** Die Nachricht „neue Entwürfe“ an alle Admin-Chats. */
    public static function melden(string $land, string $wofuer = '', bool $autopilot = false): bool
    {
        $zahl = (int) Db::wert("SELECT COUNT(*) FROM mk_inhalte WHERE status = 'entwurf' AND land = ?", [$land], 0);
        if ($zahl === 0) { return false; }
        $chats = self::adminChats();
        if ($chats === []) { return false; }
        $basis = rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . rtrim(Config::basis(), '/');
        $text = (self::FLAGGE[$land] ?? '') . ' <b>' . ($autopilot ? 'Autopilot ' . self::h(MkLand::name($land)) : 'Neue Entwürfe für ' . self::h(MkLand::name($land))) . '</b>'
              . ($wofuer !== '' ? "\n" . self::h($wofuer) : '') . "\n\n" . $zahl . ' ' . ($zahl === 1 ? 'Entwurf wartet' : 'Entwürfe warten') . ' auf dein Ja oder Nein. Ohne deinen Klick geht nichts raus.';
        $ok = false;
        foreach ($chats as $chat) {
            $d = ['chat_id' => $chat, 'text' => $text, 'parse_mode' => 'HTML', 'link_preview_options' => ['is_disabled' => true],
                  'reply_markup' => ['inline_keyboard' => [[['text' => '▶ Durchgehen', 'callback_data' => 'v:mg:' . strtolower($land)]],
                                                           [['text' => '🛠 In der Verwaltung', 'url' => $basis . '/freigabe?land=' . $land]]]]];
            self::$gesendet[] = ['sendMessage', $d];
            $ok = Telegram::rufen('sendMessage', $d)['ok'] || $ok;
        }
        return $ok;
    }

    /** Ein Knopf aus dem Admin-Chat. */
    public static function knopf(array $c, string $tat, string $wert, ?int $msgId): string
    {
        $chat = (int) $c['chat_id'];
        if ($msgId !== null && $tat !== 'g') { self::knoepfeWeg($chat, $msgId); }
        if ($tat === 'g') {
            $land = strtoupper($wert);
            if (!isset(MkLand::NAMEN[$land])) { return 'unbekannt'; }
            return self::zeigeNaechsten($chat, $land, 0, '');
        }
        $x = MkInhalt::laden((int) $wert);
        if ($x === null) { self::text($chat, 'Dieses Stück gibt es nicht mehr.'); return 'unbekannt'; }
        $land = (string) $x['land'];
        if ($tat === 'j') {
            $r = MkVeroeffentlichen::stapelJa((int) $x['id']);
            return self::zeigeNaechsten($chat, $land, 0, ($r['ok'] ? '✅ ' : '⚠️ ') . $r['text']);
        }
        if ($tat === 'n') {
            $f = MkInhalt::verwerfen((int) $x['id']);
            return self::zeigeNaechsten($chat, $land, 0, $f === null ? '❌ „' . $x['titel'] . '“ verworfen.' : '⚠️ ' . $f);
        }
        if ($tat === 's') { return self::zeigeNaechsten($chat, $land, (int) $x['id'], '⏭ Später.'); }
        if ($tat === 'p') {   // P3: Uwe hat das Handy-Stück selbst gepostet
            $f = MkInhalt::veroeffentlicht((int) $x['id']);
            self::text($chat, $f === null ? '✅ „' . self::h((string) $x['titel']) . '“ als gepostet vermerkt — Klicks zählen über den eigenen Link.' : '⚠️ ' . self::h($f));
            return 'handy_gepostet';
        }
        return 'unbekannt';
    }

    /** Den nächsten Entwurf zeigen (nach $nachId, falls „später“), sonst „alles durch“. */
    private static function zeigeNaechsten(int $chat, string $land, int $nachId, string $vorher): string
    {
        $ids = array_map('intval', array_column(Db::all("SELECT id FROM mk_inhalte WHERE status = 'entwurf' AND land = ? ORDER BY id", [$land]), 'id'));
        if ($ids === []) {
            $andere = MkLand::andere($land);
            $dort = (int) Db::wert("SELECT COUNT(*) FROM mk_inhalte WHERE status = 'entwurf' AND land = ?", [$andere], 0);
            $knoepfe = $dort > 0 ? [[['text' => '▶ ' . $dort . ' in ' . MkLand::name($andere), 'callback_data' => 'v:mg:' . strtolower($andere)]]] : [];
            self::text($chat, trim($vorher . "\n\n" . (self::FLAGGE[$land] ?? '') . ' Alles durchgesehen in ' . MkLand::name($land) . '.'), $knoepfe);
            return 'stapel_leer';
        }
        $nach = array_values(array_filter($ids, static fn($i) => $i > $nachId));
        $id = $nachId > 0 ? ($nach[0] ?? $ids[0]) : $ids[0];
        $x = MkInhalt::laden($id);
        $rest = count($ids);
        $kopf = (self::FLAGGE[$land] ?? '') . ' ' . self::h(trim(preg_replace('/\s*\(.*\)$/u', '', (string) (MkKampagne::PLATTFORMEN[$x['plattform']] ?? $x['plattform'])) ?? ''))
              . ' · ' . self::h(MkInhalt::FORMATE[$x['format']][0] ?? $x['format']) . ' · noch ' . $rest;
        $text = MkInhalt::kopiertext($x);
        $de = $x['sprache'] !== 'de' ? trim((string) ($x['uebersetzung'] ?? '')) : '';
        $was = MkVeroeffentlichen::wasPassiert($x);
        $knoepfe = [[['text' => '✅ Ja', 'callback_data' => 'v:mj:' . $id], ['text' => '❌ Nein', 'callback_data' => 'v:mn:' . $id]],
                    [['text' => '⏭ Später', 'callback_data' => 'v:ms:' . $id]]];
        if ($vorher !== '') { self::text($chat, self::h($vorher)); }

        $bild = MkMedium::gewaehlt($id, 'bild') ?? (Db::one("SELECT * FROM mk_medien WHERE inhalt_id = ? AND art = 'bild' AND status = 'neu' ORDER BY id DESC LIMIT 1", [$id]) ?: null);
        /* Mit Bild: Bildunterschrift höchstens 1024 Zeichen — darum kürzer. */
        $kurz = static fn(string $t, int $n): string => mb_strlen($t) > $n ? mb_substr($t, 0, $n - 1) . '…' : $t;
        if ($bild) {
            $cap = $kopf . "\n<b>" . self::h($kurz((string) $x['titel'], 120)) . "</b>\n\n" . self::h($kurz($text, 420))
                 . ($de !== '' ? "\n\n🇩🇪 <i>" . self::h($kurz($de, 260)) . '</i>' : '') . "\n\n" . self::h($kurz($was, 160));
            if (self::foto($chat, $bild, $cap, $knoepfe)) { return 'stapel_stueck'; }
        }
        $voll = $kopf . "\n<b>" . self::h((string) $x['titel']) . "</b>\n\n" . self::h($kurz($text, 2400))
              . ($de !== '' ? "\n\n🇩🇪 <i>" . self::h($kurz($de, 1200)) . '</i>' : '') . "\n\n" . self::h($was);
        self::text($chat, $voll, $knoepfe);
        return 'stapel_stueck';
    }

    private static function text(int $chat, string $html, array $knoepfe = []): void
    {
        $d = ['chat_id' => $chat, 'text' => mb_substr($html, 0, 4000), 'parse_mode' => 'HTML', 'link_preview_options' => ['is_disabled' => true]];
        if ($knoepfe) { $d['reply_markup'] = ['inline_keyboard' => $knoepfe]; }
        self::$gesendet[] = ['sendMessage', $d];
        Telegram::rufen('sendMessage', $d);
    }

    private static function knoepfeWeg(int $chat, int $msgId): void
    {
        Telegram::rufen('editMessageReplyMarkup', ['chat_id' => $chat, 'message_id' => $msgId, 'reply_markup' => ['inline_keyboard' => []]]);
    }

    /**
     * Das Bild hochladen (die Datei liegt geschützt, eine Adresse gibt es für
     * Entwürfe nicht): als JPEG bis 1600 px, damit es unter Telegrams Grenze
     * bleibt. Der Bot-Schlüssel verlässt den Server nicht.
     */
    private static function foto(int $chat, array $m, string $caption, array $knoepfe): bool
    {
        $pfad = MkMedium::ordner() . '/' . basename((string) $m['datei']);
        if (!is_file($pfad)) { return false; }
        $d = ['chat_id' => $chat, 'caption' => mb_substr($caption, 0, 1024), 'parse_mode' => 'HTML', 'reply_markup' => ['inline_keyboard' => $knoepfe]];
        self::$gesendet[] = ['sendPhoto', $d + ['photo' => '(Datei)']];
        if (Telegram::$netz) { return (bool) (Telegram::rufen('sendPhoto', $d + ['photo' => '@' . $pfad])['ok'] ?? false); }
        $jpg = self::alsJpeg($pfad, (string) $m['mime']);
        if ($jpg === null) { return false; }
        try {
            $token = Telegram::token();
            if ($token === '') { return false; }
            $ch = curl_init(Telegram::API . '/bot' . $token . '/sendPhoto');
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_POSTFIELDS => ['chat_id' => (string) $chat, 'caption' => $d['caption'], 'parse_mode' => 'HTML',
                                       'reply_markup' => json_encode($d['reply_markup'], JSON_UNESCAPED_UNICODE), 'photo' => new CURLFile($jpg, 'image/jpeg', 'bild.jpg')]]);
            $roh = curl_exec($ch);
            curl_close($ch);
            $j = is_string($roh) ? json_decode($roh, true) : null;
            return is_array($j) && !empty($j['ok']);
        } catch (Throwable $e) { return false; } finally { @unlink($jpg); }
    }

    private static function alsJpeg(string $pfad, string $mime): ?string
    {
        if (!function_exists('imagecreatefromstring')) { return null; }
        $bild = @imagecreatefromstring((string) file_get_contents($pfad));
        if ($bild === false) { return null; }
        $b = imagesx($bild); $h = imagesy($bild); $f = min(1, 1600 / max($b, $h));
        $neu = imagecreatetruecolor(max(1, (int) round($b * $f)), max(1, (int) round($h * $f)));
        imagefill($neu, 0, 0, imagecolorallocate($neu, 255, 255, 255));
        imagecopyresampled($neu, $bild, 0, 0, 0, 0, imagesx($neu), imagesy($neu), $b, $h);
        $ziel = tempnam(sys_get_temp_dir(), 'tgbild');
        $ok = $ziel !== false && imagejpeg($neu, $ziel, 86);
        imagedestroy($bild); imagedestroy($neu);
        return $ok ? $ziel : null;
    }
}
