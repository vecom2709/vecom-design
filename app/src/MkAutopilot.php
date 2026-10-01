<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/MkAuftrag.php';
require_once __DIR__ . '/MkLand.php';

/* ==========================================================================
   MkAutopilot.php — Wochen-Autopilot je Land
   (Marketing-Studio 7, 01.10.2026, Uwe: „ja“ zu U4 — „Mach gesamtes
   Marketing so gut wie automatisiert … ich sage ja oder nein“).

   Einmal je Woche, am gewählten Tag und zur gewählten Stunde, startet der
   Cronlauf für jedes eingeschaltete Land eine Ein-Klick-Kampagne — für die
   freigegebene Zielgruppe, die am längsten keine Inhalte mehr bekommen hat.
   Claude schreibt über Uwes Abo, Kie.ai bebildert (Guthaben vorher geprüft).
   Sind Texte und Bilder da, kommt der Stapel per Telegram: Ja / Nein /
   Später je Stück. Ohne Uwes Klick geht nichts raus.

   Ab Werk aus. Einstellungen je Land in settings (mk_autopilot_IT / _DE),
   der letzte Lauf als ISO-Woche (mk_autopilot_woche_IT / _DE).
   ========================================================================== */

final class MkAutopilot
{
    public const TAGE = [1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag'];
    public const STANDARD = ['an' => false, 'tag' => 1, 'stunde' => 7, 'anzeigen' => false, 'bilder' => true];

    private static function lesen(string $k): string
    {
        try { return (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], ''); } catch (Throwable $e) { return ''; }
    }

    private static function schreiben(string $k, string $v): void
    {
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$k, $v]);
    }

    /** @return array{an:bool, tag:int, stunde:int, anzeigen:bool, bilder:bool} */
    public static function einstellung(string $land): array
    {
        $j = json_decode(self::lesen('mk_autopilot_' . $land), true);
        $e = (is_array($j) ? $j : []) + self::STANDARD;
        return ['an' => (bool) $e['an'], 'tag' => max(1, min(7, (int) $e['tag'])), 'stunde' => max(5, min(22, (int) $e['stunde'])),
                'anzeigen' => (bool) $e['anzeigen'], 'bilder' => (bool) $e['bilder']];
    }

    public static function speichern(string $land, array $d): ?string
    {
        if (!isset(MkLand::NAMEN[$land])) { return 'Unbekanntes Land.'; }
        $alt = self::einstellung($land);
        $neu = ['an' => !empty($d['an']), 'tag' => max(1, min(7, (int) ($d['tag'] ?? 1))), 'stunde' => max(5, min(22, (int) ($d['stunde'] ?? 7))),
                'anzeigen' => !empty($d['anzeigen']), 'bilder' => !empty($d['bilder'])];
        self::schreiben('mk_autopilot_' . $land, (string) json_encode($neu));
        Events::pruefspur('autopilot_' . strtolower($land), 'settings', 0, $alt, $neu);
        return null;
    }

    /**
     * Die nächste freigegebene Zielgruppe des Landes: die, deren letzte Inhalte
     * am längsten her sind (nie bediente zuerst, dann die ältesten).
     */
    public static function naechsteZielgruppe(string $land): ?array
    {
        $beste = null; $besteZeit = null;
        foreach (Db::all("SELECT id, titel, branche FROM mk_zielgruppen WHERE land = ? AND (status = 'freigegeben' OR vorher IS NOT NULL) ORDER BY id", [$land]) as $z) {
            $zuletzt = (string) Db::wert("SELECT MAX(created_at) FROM mk_auftraege WHERE art = 'inhalte' AND status <> 'abgebrochen' AND parameter LIKE ?", ['%"zielgruppe_id":' . (int) $z['id'] . ',%'], '');
            if ($zuletzt === '') { return $z; }
            if ($besteZeit === null || $zuletzt < $besteZeit) { $beste = $z; $besteZeit = $zuletzt; }
        }
        return $beste;
    }

    /** Wann der Autopilot dieses Land das nächste Mal anstößt (null = aus; liegt der Termin dieser Woche schon zurück, läuft er beim nächsten Cronlauf). */
    public static function naechsterLauf(string $land, ?int $jetzt = null): ?int
    {
        $e = self::einstellung($land);
        if (!$e['an']) { return null; }
        $jetzt ??= time();
        $montag = $jetzt - ((int) date('N', $jetzt) - 1) * 86400;
        $termin = (int) strtotime(date('Y-m-d', $montag + ($e['tag'] - 1) * 86400) . sprintf(' %02d:00', $e['stunde']));
        if (self::lesen('mk_autopilot_woche_' . $land) === date('o-\WW', $jetzt)) {
            return (int) strtotime(date('Y-m-d', $termin + 7 * 86400) . sprintf(' %02d:00', $e['stunde']));
        }
        return max($termin, $jetzt);
    }

    /**
     * Cronlauf: je eingeschaltetem Land höchstens einmal je Woche — am Tag zur
     * Stunde oder später in derselben Woche (falls der Server da schlief).
     * @return array{gestartet:int, hinweise:list<string>}
     */
    public static function lauf(?int $jetzt = null): array
    {
        $jetzt ??= time();
        $aus = ['gestartet' => 0, 'hinweise' => []];
        foreach (array_keys(MkLand::NAMEN) as $land) {
            $e = self::einstellung($land);
            if (!$e['an']) { continue; }
            $woche = date('o-\WW', $jetzt);
            if (self::lesen('mk_autopilot_woche_' . $land) === $woche) { continue; }
            if ((int) date('N', $jetzt) < $e['tag'] || ((int) date('N', $jetzt) === $e['tag'] && (int) date('G', $jetzt) < $e['stunde'])) { continue; }
            $z = self::naechsteZielgruppe($land);
            if ($z === null) {
                self::schreiben('mk_autopilot_woche_' . $land, $woche);
                $aus['hinweise'][] = MkLand::name($land) . ': keine freigegebene Zielgruppe';
                try { Events::melden('autopilot', 'Autopilot ' . MkLand::name($land) . ': keine freigegebene Zielgruppe', 'info', 'Erst unter „Zielgruppen & Recherche“ eine recherchieren lassen und freigeben.', 'zielgruppen?land=' . $land); } catch (Throwable $x) { }
                continue;
            }
            $r = MkAuftrag::anlegenKampagne((int) $z['id'], ['organisch' => '1', 'anzeigen' => $e['anzeigen'] ? '1' : '', 'bilder' => $e['bilder'] ? '1' : '', 'autopilot' => '1']);
            if (is_int($r)) {
                self::schreiben('mk_autopilot_woche_' . $land, $woche);
                $aus['gestartet']++;
                Events::protokoll('autopilot', 'Autopilot ' . MkLand::name($land) . ': Kampagne für „' . $z['titel'] . '“ angestoßen', null, null, null, ['auftrag_id' => $r]);
            } else {
                /* z. B. Tageslimit oder es läuft schon etwas — nächster Cronlauf versucht es wieder (die Woche bleibt offen). */
                $aus['hinweise'][] = MkLand::name($land) . ': ' . $r;
            }
        }
        return $aus;
    }
}
