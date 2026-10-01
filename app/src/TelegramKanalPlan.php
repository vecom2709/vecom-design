<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/MkAuftrag.php';
require_once __DIR__ . '/MkAutopilot.php';
require_once __DIR__ . '/Telegram.php';

/* ==========================================================================
   TelegramKanalPlan.php — Redaktionsplan für den Telegram-Kanal
   (01.10.2026, Uwe: „Alles“ — Vorschlag 2).

   Ein Kanal lebt davon, dass regelmäßig etwas kommt. Einmal je Woche, am
   gewählten Tag zur gewählten Stunde, stößt der Cronlauf im Content-Studio
   drei Telegram-Beiträge an — für die freigegebene italienische Zielgruppe,
   die am längsten nichts bekommen hat (dieselbe Wahl wie der Autopilot),
   mit einem Thema, das jede Woche wechselt, und zweisprachig: erst
   Italienisch, dann Deutsch.

   Es entsteht nur ein Schreibauftrag. Claude schreibt über Uwes Abo (die
   Tagesgrenze des Content-Studios gilt), ohne Bilder (kein Kie.ai-Guthaben).
   Sind die Entwürfe da, kommt der Stapel per Telegram: Ja / Nein / Später.
   Was Uwe freigibt, geht zur nächsten freien Zeit in den Kanal (einer je Tag,
   18:30 — MkVeroeffentlichen). Ohne Uwes Klick geht nichts raus.

   Messbar: Jeder Beitrag trägt seinen eigenen Link (MkInhalt::link), die
   besten stehen im Telegram-Reiter („Beste Beiträge im Kanal“).
   ========================================================================== */

final class TelegramKanalPlan
{
    public const STANDARD = ['an' => true, 'tag' => 3, 'stunde' => 8];
    public const LAND = 'IT';
    public const ANZAHL = 3;

    /** Wechselt jede Woche (ISO-Woche modulo Anzahl). Die Anweisung zur Zweisprachigkeit hängt immer dran. */
    public const THEMEN = [
        'Tipp der Woche: ein konkreter, sofort umsetzbarer Tipp für die eigene Website',
        'Vorher/Nachher: was eine moderne Website für einen kleinen Betrieb ändert',
        'Aus dem Website-Check: drei typische Schwachstellen und wie man sie behebt',
        'Frage aus der Praxis: eine häufige Kundenfrage kurz und ehrlich beantwortet',
    ];
    public const ZWEISPRACHIG = ' · Text zweisprachig: erst Italienisch, dann 🇩🇪 dasselbe auf Deutsch, zusammen max. 1024 Zeichen';

    private static function lesen(string $k): string
    {
        try { return (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], ''); } catch (Throwable $e) { return ''; }
    }

    private static function schreiben(string $k, string $v): void
    {
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $v]);
    }

    /** @return array{an:bool, tag:int, stunde:int} */
    public static function einstellung(): array
    {
        $j = json_decode(self::lesen('tg_plan'), true);
        $e = (is_array($j) ? $j : []) + self::STANDARD;
        return ['an' => (bool) $e['an'], 'tag' => max(1, min(7, (int) $e['tag'])), 'stunde' => max(5, min(22, (int) $e['stunde']))];
    }

    public static function speichern(array $d): ?string
    {
        $alt = self::einstellung();
        $neu = ['an' => !empty($d['an']), 'tag' => max(1, min(7, (int) ($d['tag'] ?? 3))), 'stunde' => max(5, min(22, (int) ($d['stunde'] ?? 8)))];
        self::schreiben('tg_plan', (string) json_encode($neu));
        try { Events::pruefspur('telegram_plan', 'settings', 0, $alt, $neu); } catch (Throwable $e) { }
        return null;
    }

    /** Das Thema einer Woche — mit der Anweisung zur Zweisprachigkeit (passt in die 200 Zeichen des Auftrags). */
    public static function thema(?int $jetzt = null): string
    {
        $w = (int) date('W', $jetzt ?? time());
        return self::THEMEN[$w % count(self::THEMEN)] . self::ZWEISPRACHIG;
    }

    /** Wann der nächste Lauf ist (null = aus). */
    public static function naechsterLauf(?int $jetzt = null): ?int
    {
        $e = self::einstellung();
        if (!$e['an']) { return null; }
        $jetzt ??= time();
        $montag = $jetzt - ((int) date('N', $jetzt) - 1) * 86400;
        $termin = (int) strtotime(date('Y-m-d', $montag + ($e['tag'] - 1) * 86400) . sprintf(' %02d:00', $e['stunde']));
        if (self::lesen('tg_plan_woche') === date('o-\WW', $jetzt)) {
            return (int) strtotime(date('Y-m-d', $termin + 7 * 86400) . sprintf(' %02d:00', $e['stunde']));
        }
        return max($termin, $jetzt);
    }

    /**
     * Cronlauf: höchstens einmal je Woche, am Tag zur Stunde oder später in
     * derselben Woche. Scheitert das Anlegen (Tagesgrenze, es schreibt schon
     * jemand für diese Zielgruppe), bleibt die Woche offen — der nächste Lauf
     * versucht es wieder.
     * @return array{gestartet:int, hinweis:string}
     */
    public static function lauf(?int $jetzt = null): array
    {
        $jetzt ??= time();
        $e = self::einstellung();
        if (!$e['an']) { return ['gestartet' => 0, 'hinweis' => 'aus']; }
        if (Telegram::kanal()['id'] === '') { return ['gestartet' => 0, 'hinweis' => 'kein Kanal verbunden']; }
        $woche = date('o-\WW', $jetzt);
        if (self::lesen('tg_plan_woche') === $woche) { return ['gestartet' => 0, 'hinweis' => 'diese Woche schon']; }
        if ((int) date('N', $jetzt) < $e['tag'] || ((int) date('N', $jetzt) === $e['tag'] && (int) date('G', $jetzt) < $e['stunde'])) {
            return ['gestartet' => 0, 'hinweis' => 'noch nicht dran'];
        }
        [$r, $z] = self::anstossen($jetzt);
        if (is_int($r)) { return ['gestartet' => 1, 'hinweis' => '']; }
        if ($z === null) {
            // Ohne Zielgruppe hilft kein zweiter Versuch in dieser Woche — einmal melden, dann Ruhe.
            self::schreiben('tg_plan_woche', $woche);
            try { Events::melden('telegram_plan', 'Kanal-Plan Telegram: keine freigegebene Zielgruppe in Italien', 'info', 'Erst unter „Zielgruppen & Recherche“ eine recherchieren lassen und freigeben.', 'zielgruppen?land=IT'); } catch (Throwable $x) { }
        }
        return ['gestartet' => 0, 'hinweis' => (string) $r];
    }

    /**
     * Jetzt anstoßen (Knopf „Diese Woche jetzt schreiben lassen“ oder Cron).
     * @return array{0:int|string, 1:?array} [Auftrag oder Hinweis, Zielgruppe]
     */
    public static function anstossen(?int $jetzt = null): array
    {
        $jetzt ??= time();
        $z = MkAutopilot::naechsteZielgruppe(self::LAND);
        if ($z === null) { return ['In Italien ist noch keine Zielgruppe freigegeben — erst unter „Zielgruppen“ recherchieren lassen und freigeben.', null]; }
        $r = MkAuftrag::anlegenInhalte(['zielgruppe' => (int) $z['id'], 'plattformen' => ['telegram'], 'umfang' => 'organisch',
            'anzahl' => self::ANZAHL, 'thema' => self::thema($jetzt), 'kanalplan' => true]);
        if (is_int($r)) {
            self::schreiben('tg_plan_woche', date('o-\WW', $jetzt));
            try { Events::protokoll('telegram_plan', 'Kanal-Plan Telegram: ' . self::ANZAHL . ' Beiträge für „' . $z['titel'] . '“ angestoßen', null, null, null, ['auftrag_id' => $r]); } catch (Throwable $e) { }
        }
        return [$r, $z];
    }

    /**
     * Die Kanal-Beiträge mit den meisten Klicks auf ihren Link (letzte 60 Tage) —
     * gezählt über das Werbemittel, das jeder Beitrag ab Freigabe trägt (/k/CODE).
     * @return list<array{id:int, titel:string, am:string, klicks:int}>
     */
    public static function beste(int $wieviele = 3): array
    {
        try {
            return array_map(static fn($r) => ['id' => (int) $r['id'], 'titel' => (string) $r['titel'], 'am' => (string) $r['veroeffentlicht_am'], 'klicks' => (int) $r['klicks']],
                Db::all("SELECT i.id, i.titel, i.veroeffentlicht_am, COUNT(b.id) AS klicks
                           FROM mk_inhalte i LEFT JOIN spur_besuche b ON b.creative_id = i.creative_id AND i.creative_id IS NOT NULL
                          WHERE i.plattform = 'telegram' AND i.status = 'veroeffentlicht' AND i.veroeffentlicht_am >= NOW() - INTERVAL 60 DAY
                          GROUP BY i.id ORDER BY klicks DESC, i.veroeffentlicht_am DESC LIMIT " . max(1, min(10, $wieviele))));
        } catch (Throwable $e) { return []; }
    }
}
