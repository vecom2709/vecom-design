<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/MkKampagne.php';

/* ==========================================================================
   MkKanaele.php — Kanal-Zentrale (01.10.2026, Uwe: Ja zu P1 und P2).

   Eine Stelle für die Frage „Kommt mein Beitrag an?“: Welcher Kanal ist
   verbunden, welcher geht automatisch, welcher per Handy, und welche
   Beiträge sind zuletzt nicht rausgegangen. „Verbindung prüfen“ liest nur
   (Seitenname, Instagram-Name, Telegram-Kanal) — es postet nichts
   Öffentliches.
   ========================================================================== */
final class MkKanaele
{
    /** Kanäle, die Vecom selbst postet. */
    public const AUTO = ['facebook' => 'Facebook-Seite', 'instagram' => 'Instagram', 'telegram' => 'Telegram-Kanal'];
    /** Kanäle, für die das fertige Stück zur Sendezeit aufs Handy kommt (P3). */
    public const HANDY = ['tiktok' => 'TikTok', 'linkedin' => 'LinkedIn', 'google' => 'Google-Unternehmensprofil', 'youtube' => 'YouTube Shorts'];

    /** Stand je automatischem Kanal. @return array<string, array{bereit:bool, text:string, einrichten:string}> */
    public static function stand(): array
    {
        require_once __DIR__ . '/MetaSeite.php';
        require_once __DIR__ . '/Telegram.php';
        $me = MetaSeite::einstellungen();
        $k = Telegram::kanal();
        $fb = MetaSeite::bereit();
        /* „Verbunden“ nur, wenn Meta den Schlüssel bei der letzten Prüfung auch
           angenommen hat (01.10.2026: Seite und Schlüssel standen drin, Meta
           antwortete „Invalid OAuth access token“ — die Verwaltung zeigte trotzdem
           „verbunden“ und unter Anmeldungen „postet selbst“). */
        $pr = self::letztePruefung();
        $fbNein = $fb && isset($pr['facebook']) && empty($pr['facebook']['ok']);
        $fbText = $fbNein ? 'Schlüssel abgelehnt (Prüfung ' . date('d.m. H:i', (int) strtotime((string) $pr['facebook']['am'])) . ') — neuen dauerhaften Schlüssel eintragen'
            : ($fb ? 'verbunden (Seite ' . $me['seite_id'] . ')' : 'noch nicht verbunden — Seiten-ID und Schlüssel fehlen');
        $fb = $fb && !$fbNein;
        return [
            'facebook' => ['bereit' => $fb, 'text' => $fbText, 'einrichten' => '#meta'],
            'instagram' => ['bereit' => $fb && $me['ig_id'] !== '', 'text' => !$fb ? 'braucht zuerst die Facebook-Seite' : ($me['ig_id'] !== '' ? 'verbunden (Konto ' . $me['ig_id'] . ')' : 'Instagram-Konto-ID fehlt'), 'einrichten' => '#meta'],
            'telegram' => ['bereit' => Telegram::bereit() && $k['id'] !== '', 'text' => !Telegram::bereit() ? 'Bot noch nicht eingerichtet' : ($k['id'] !== '' ? 'verbunden' . ($k['titel'] !== '' ? ' („' . $k['titel'] . '“)' : '') : 'Kanal fehlt'), 'einrichten' => 'einstellungen?b=telegram'],
        ];
    }

    /** Letzte Prüfung je Kanal: kanal => {ok, text, am}. */
    public static function letztePruefung(): array
    {
        try { $j = json_decode((string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', ['mk_kanal_pruefung'], ''), true); } catch (Throwable $e) { $j = null; }
        return is_array($j) ? $j : [];
    }

    /** Merkt das Ergebnis (null = vergessen, etwa nach einem neuen Schlüssel). Der Text enthält nie den Schlüssel. */
    public static function pruefungMerken(string $kanal, ?bool $ok, string $text = ''): void
    {
        $a = self::letztePruefung();
        if ($ok === null) { unset($a[$kanal]); } else { $a[$kanal] = ['ok' => $ok, 'text' => mb_substr($text, 0, 200), 'am' => date('Y-m-d H:i:s')]; }
        try {
            Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', ['mk_kanal_pruefung', json_encode($a, JSON_UNESCAPED_UNICODE)]);
        } catch (Throwable $e) { /* nur Anzeige */ }
    }

    /** Liest nur — postet nichts. Das Ergebnis wird gemerkt (stand() zeigt es). @return array{ok:bool, text:string} */
    public static function pruefen(string $kanal): array
    {
        $r = self::pruefenRoh($kanal);
        if (in_array($kanal, ['facebook', 'instagram', 'telegram'], true)) { self::pruefungMerken($kanal, $r['ok'], $r['text']); }
        return $r;
    }

    private static function pruefenRoh(string $kanal): array
    {
        require_once __DIR__ . '/MetaSeite.php';
        require_once __DIR__ . '/Telegram.php';
        if ($kanal === 'telegram') {
            $k = Telegram::kanal();
            if (!Telegram::bereit() || $k['id'] === '') { return ['ok' => false, 'text' => 'Telegram ist noch nicht eingerichtet.']; }
            $r = Telegram::rufen('getChat', ['chat_id' => $k['id']]);
            return $r['ok'] ? ['ok' => true, 'text' => 'Telegram antwortet: Kanal „' . (string) ($r['result']['title'] ?? $k['id']) . '“ erreichbar.']
                            : ['ok' => false, 'text' => 'Telegram: ' . ($r['beschreibung'] ?: 'Kanal nicht erreichbar') . ' — ist der Bot Admin im Kanal?'];
        }
        if (!MetaSeite::bereit()) { return ['ok' => false, 'text' => 'Die Facebook-Seite ist noch nicht verbunden.']; }
        $me = MetaSeite::einstellungen();
        if ($kanal === 'instagram') {
            if ($me['ig_id'] === '') { return ['ok' => false, 'text' => 'Instagram-Konto-ID fehlt.']; }
            $r = MetaSeite::graph('GET', $me['ig_id'] . '?fields=username');
            return ($r['status'] ?? 0) === 200 && !empty($r['json']['username'])
                ? ['ok' => true, 'text' => 'Instagram antwortet: @' . (string) $r['json']['username'] . ' ist verbunden.']
                : ['ok' => false, 'text' => 'Instagram: ' . MetaSeite::fehler($r)];
        }
        $r = MetaSeite::graph('GET', $me['seite_id'] . '?fields=name');
        return ($r['status'] ?? 0) === 200 && !empty($r['json']['name'])
            ? ['ok' => true, 'text' => 'Facebook antwortet: Seite „' . (string) $r['json']['name'] . '“ ist verbunden.']
            : ['ok' => false, 'text' => 'Facebook: ' . MetaSeite::fehler($r)];
    }

    /** Beiträge, die zuletzt nicht rausgegangen sind (P2). */
    public static function fehlgeschlagen(?string $land = null): array
    {
        try {
            $a = [];
            $sql = "SELECT id, titel, plattform, post_fehler, updated_at FROM mk_inhalte
                     WHERE status = 'freigegeben' AND post_fehler IS NOT NULL AND post_fehler <> '' AND geplant_am IS NULL";
            if (in_array($land, ['IT', 'DE'], true)) { $sql .= ' AND land = ?'; $a[] = $land; }
            return Db::all($sql . ' ORDER BY id DESC LIMIT 20', $a);
        } catch (Throwable $e) { return []; }
    }

    /** Was in den nächsten Tagen rausgeht — je Kanal die Zahl. */
    public static function geplant(): array
    {
        try {
            $aus = [];
            foreach (Db::all("SELECT plattform, COUNT(*) AS n, MIN(geplant_am) AS naechst FROM mk_inhalte WHERE status = 'freigegeben' AND geplant_am IS NOT NULL GROUP BY plattform") as $r) {
                $aus[(string) $r['plattform']] = ['n' => (int) $r['n'], 'naechst' => (string) $r['naechst']];
            }
            return $aus;
        } catch (Throwable $e) { return []; }
    }
}
