<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Telegram.php';

/* ==========================================================================
   TelegramUmfrage.php — kurze Umfragen im Kanal
   (01.10.2026, Uwe: „Alles“ — Vorschlag 6).

   Eine Umfrage ist der leichteste Weg, aus Lesern Beteiligte zu machen —
   ein Tipp, kein Formular. Im Kanal sind Umfragen immer anonym: Telegram
   schickt dem Bot nur, wie viele Stimmen jede Antwort hat (Update „poll“),
   nie, wer abgestimmt hat. Mehr wird hier auch nicht gespeichert.

   Gesendet wird nur auf Klick in der Verwaltung, mit Rückfrage
   (Ablauf::TRAGWEITE 'telegram_umfrage') — sie erscheint sofort bei allen
   Abonnenten. Beenden ebenso ('telegram_umfrage_ende').
   ========================================================================== */

final class TelegramUmfrage
{
    public const FRAGE_MAX = 300;
    public const ANTWORT_MAX = 100;
    public const ANTWORTEN_MIN = 2;
    public const ANTWORTEN_MAX = 10;

    /** Vorschläge — zweisprachig wie der Kanal, Italienisch zuerst. Nur Vorlagen: Uwe ändert, was er will. */
    public const VORSCHLAEGE = [
        ['Il vostro sito vi porta clienti? · Bringt Ihre Website Ihnen Kunden?',
         ['Sì, regolarmente · Ja, regelmäßig', 'A volte · Manchmal', 'Quasi mai · Kaum', 'Non ho ancora un sito · Noch keine Website']],
        ['Come vi trovano i nuovi clienti? · Wie finden neue Kunden Sie?',
         ['Google', 'Instagram / Facebook', 'Passaparola · Empfehlung', 'Altro · Anderes']],
        ['Cosa vi serve di più adesso? · Was brauchen Sie gerade am meisten?',
         ['Un sito nuovo · Neue Website', 'Più visite · Mehr Besucher', 'Prenotazioni online · Online-Buchung', 'Niente, grazie · Nichts, danke']],
    ];

    /**
     * Umfrage im Kanal senden.
     * @param list<string> $antworten
     * @return array{ok:bool, text:string, id?:int}
     */
    public static function senden(string $frage, array $antworten): array
    {
        $frage = trim((string) preg_replace('/\s+/u', ' ', $frage));
        $antworten = array_values(array_filter(array_map(static fn($a) => trim((string) preg_replace('/\s+/u', ' ', (string) $a)), $antworten), static fn($a) => $a !== ''));
        if ($frage === '' || mb_strlen($frage) > self::FRAGE_MAX) { return ['ok' => false, 'text' => 'Bitte eine Frage mit höchstens ' . self::FRAGE_MAX . ' Zeichen.']; }
        if (count($antworten) < self::ANTWORTEN_MIN || count($antworten) > self::ANTWORTEN_MAX) {
            return ['ok' => false, 'text' => 'Bitte ' . self::ANTWORTEN_MIN . ' bis ' . self::ANTWORTEN_MAX . ' Antworten.'];
        }
        foreach ($antworten as $a) {
            if (mb_strlen($a) > self::ANTWORT_MAX) { return ['ok' => false, 'text' => 'Jede Antwort höchstens ' . self::ANTWORT_MAX . ' Zeichen („' . mb_substr($a, 0, 30) . '…“).']; }
        }
        if (count(array_unique(array_map('mb_strtolower', $antworten))) !== count($antworten)) { return ['ok' => false, 'text' => 'Zwei Antworten sind gleich.']; }
        $k = Telegram::kanal();
        if ($k['id'] === '' || !Telegram::bereit()) { return ['ok' => false, 'text' => 'Erst Bot und Kanal verbinden.']; }

        $r = Telegram::rufen('sendPoll', ['chat_id' => $k['id'], 'question' => $frage,
            'options' => array_map(static fn($a) => ['text' => $a], $antworten), 'is_anonymous' => true]);
        $poll = (array) ($r['result']['poll'] ?? []);
        $mid = (int) ($r['result']['message_id'] ?? 0);
        if (!$r['ok'] || $mid <= 0 || (string) ($poll['id'] ?? '') === '') {
            return ['ok' => false, 'text' => 'Telegram hat die Umfrage nicht angenommen' . ($r['beschreibung'] !== '' ? ': ' . $r['beschreibung'] : '') . '.'];
        }
        $id = (int) Db::insert('tg_umfragen', ['poll_id' => mb_substr((string) $poll['id'], 0, 64), 'chat_id' => $k['id'], 'message_id' => $mid,
            'frage' => $frage, 'optionen' => json_encode($antworten, JSON_UNESCAPED_UNICODE), 'stimmen' => json_encode(array_fill(0, count($antworten), 0)), 'gesamt' => 0]);
        Telegram::setzen('tg_kanal_zuletzt', date('Y-m-d H:i:s'));
        return ['ok' => true, 'text' => 'Die Umfrage steht im Kanal. Die Stimmen erscheinen hier, sobald jemand abstimmt.', 'id' => $id];
    }

    /** Update „poll“ von Telegram: nur die Zahlen übernehmen. @return string Vermerk */
    public static function stand(array $poll): string
    {
        $pid = (string) ($poll['id'] ?? '');
        if ($pid === '') { return 'ignoriert'; }
        $u = Db::one('SELECT id, optionen FROM tg_umfragen WHERE poll_id = ?', [$pid]);
        if (!$u) { return 'unbekannt'; }
        $n = count((array) json_decode((string) $u['optionen'], true));
        $stimmen = array_slice(array_map(static fn($o) => max(0, (int) ($o['voter_count'] ?? 0)), array_values((array) ($poll['options'] ?? []))), 0, $n);
        $stimmen = array_pad($stimmen, $n, 0);
        $daten = ['stimmen' => json_encode($stimmen), 'gesamt' => max(0, (int) ($poll['total_voter_count'] ?? array_sum($stimmen)))];
        if (!empty($poll['is_closed'])) { $daten['status'] = 'beendet'; $daten['beendet_am'] = date('Y-m-d H:i:s'); }
        Db::update('tg_umfragen', (int) $u['id'], $daten);
        return 'umfrage';
    }

    /** Umfrage beenden (stopPoll) — danach kann niemand mehr abstimmen. @return array{ok:bool, text:string} */
    public static function beenden(int $id): array
    {
        $u = Db::one('SELECT * FROM tg_umfragen WHERE id = ?', [$id]);
        if (!$u) { return ['ok' => false, 'text' => 'Diese Umfrage gibt es nicht.']; }
        if ($u['status'] === 'beendet') { return ['ok' => true, 'text' => 'Die Umfrage ist schon beendet.']; }
        $r = Telegram::rufen('stopPoll', ['chat_id' => (string) $u['chat_id'], 'message_id' => (int) $u['message_id']]);
        if (!$r['ok']) {
            // Schon in Telegram beendet oder der Beitrag ist weg: hier ebenfalls beenden, sonst bliebe sie für immer „offen“.
            if (preg_match('/already closed|message to stop not found|message can\'t be/i', $r['beschreibung'])) {
                Db::update('tg_umfragen', $id, ['status' => 'beendet', 'beendet_am' => date('Y-m-d H:i:s')]);
                return ['ok' => true, 'text' => 'Die Umfrage war in Telegram schon beendet.'];
            }
            return ['ok' => false, 'text' => 'Telegram hat das Beenden nicht angenommen: ' . $r['beschreibung']];
        }
        self::stand((array) $r['result'] + ['is_closed' => true]);
        Db::update('tg_umfragen', $id, ['status' => 'beendet', 'beendet_am' => date('Y-m-d H:i:s')]);
        return ['ok' => true, 'text' => 'Die Umfrage ist beendet — das Ergebnis bleibt im Kanal stehen.'];
    }

    /** Die letzten Umfragen mit Ergebnis. @return list<array> */
    public static function liste(int $wieviele = 5): array
    {
        try {
            $aus = [];
            foreach (Db::all('SELECT * FROM tg_umfragen ORDER BY id DESC LIMIT ' . max(1, min(50, $wieviele))) as $u) {
                $opt = (array) json_decode((string) $u['optionen'], true);
                $st = array_pad((array) json_decode((string) ($u['stimmen'] ?? '[]'), true), count($opt), 0);
                $u['antworten'] = array_map(static fn($t, $n) => ['text' => (string) $t, 'stimmen' => (int) $n], $opt, array_slice($st, 0, count($opt)));
                $aus[] = $u;
            }
            return $aus;
        } catch (Throwable $e) { return []; }   // Migration noch offen
    }
}
