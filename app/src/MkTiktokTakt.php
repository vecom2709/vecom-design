<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/MkAuftrag.php';
require_once __DIR__ . '/MkAutopilot.php';

/* ==========================================================================
   MkTiktokTakt.php — jeden Tag TikTok (03.10.2026, Uwe: „Bei TikTok soll
   jeden Tag gepostet werden … kinoreif wie eine Art Trailer … alle Videos
   deutsch, nur wenige italienisch zwischendurch“).

   Entschieden und begründet:
   - Zwei Videos am Tag, 15:30 und 20:00. Buffer hat 11,4 Mio. TikTok-
     Beiträge ausgewertet: Mehr Beiträge heben vor allem die Spitze (die
     besten 10 % je Konto) — mehr Versuche, mehr Chancen auf den Ausreißer.
     Metricool (2,3 Mio. Beiträge): die meisten Aufrufe 18–21 Uhr, Spitze
     20 Uhr. 15:30 liegt in der Pause zwischen Mittag- und Abendservice —
     dann haben die Inhaber, die wir erreichen wollen, das Handy in der Hand.
   - Eines der beiden mit Stimme über Kie.ai (Veo 3.1 Quality, spricht den
     Einstieg auf Deutsch), das andere als Blender-Werbespot auf Uwes PC
     (fünf Einstellungen, ohne Credits) — dort legt Uwe in der App einen
     passenden TikTok-Sound darüber. Gibt es für die Branche keine 3D-Szene,
     wird auch das zweite ein Kie-Video.
   - Sprache: jeder fünfte Schreibauftrag italienisch, sonst deutsch.
   - Vorrat: Liegen weniger als sechs TikTok-Stücke (drei Tage) bereit, gibt
     der Cronlauf einen Schreibauftrag über vier Stücke an den PC. Höchstens
     einer am Tag, und nie, solange einer läuft.

   Nichts geht ohne Uwes Ja raus: Die Stücke kommen als Stapel per Telegram,
   freigegebene landen auf den beiden Sendeplätzen und von dort aufs Handy
   (vor der App-Prüfung als Entwurf in die TikTok-App).

   Ab Werk aus; Schalter unter Kanäle › TikTok.
   ========================================================================== */

final class MkTiktokTakt
{
    /** Sendeplätze für TikTok — MkVeroeffentlichen::naechsterSlot liest sie. */
    public const ZEITEN = ['15:30', '20:00'];
    public const VORRAT = 6;
    public const JE_AUFTRAG = 4;
    /** Jeder wievielte Auftrag italienisch ist. */
    public const ITALIENISCH_JEDER = 5;
    /** Stimme (Kie.ai) und Werbespot (Blender) wechseln sich ab. */
    public const MOTOREN = ['veo3', 'spot'];

    private static function lesen(string $k): string
    {
        try { return (string) Db::wert('SELECT svalue FROM settings WHERE skey = ?', [$k], ''); } catch (Throwable $e) { return ''; }
    }

    public static function an(): bool { return self::lesen('mk_tiktok_takt') === '1'; }

    public static function schalten(bool $an): void
    {
        $alt = self::an();
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', ['mk_tiktok_takt', $an ? '1' : '0']);
        try { Events::pruefspur('tiktok_takt', 'settings', 0, ['an' => $alt], ['an' => $an]); } catch (Throwable $e) { }
    }

    /** TikTok-Stücke, die noch rausgehen (Entwurf oder freigegeben) — der Vorrat. */
    public static function vorrat(): int
    {
        return (int) Db::wert("SELECT COUNT(*) FROM mk_inhalte WHERE plattform = 'tiktok' AND art = 'organisch' AND status IN ('entwurf', 'freigegeben')
                               AND created_at >= NOW() - INTERVAL 10 DAY", [], 0);
    }

    /** @return list<array<string,mixed>> eigene Schreibaufträge, neueste zuerst */
    private static function auftraege(int $max = 20): array
    {
        return Db::all("SELECT * FROM mk_auftraege WHERE art = 'inhalte' AND parameter LIKE '%\"tiktok_takt\":true%' ORDER BY id DESC LIMIT " . max(1, min(100, $max)));
    }

    /** Welche Sprache der nächste Auftrag bekommt: jeder fünfte italienisch. */
    public static function naechstesLand(): string
    {
        $bisher = array_values(array_filter(self::auftraege(), static fn($a) => $a['status'] !== 'abgebrochen'));
        $seitIt = 0;
        foreach ($bisher as $a) { if ($a['land'] === 'IT') { break; } $seitIt++; }
        return $seitIt >= self::ITALIENISCH_JEDER - 1 ? 'IT' : 'DE';
    }

    /** Cronlauf. @return array<string,mixed> */
    public static function lauf(): array
    {
        if (!self::an()) { return ['an' => false]; }
        $v = self::vorrat();
        if ($v >= self::VORRAT) { return ['vorrat' => $v]; }
        foreach (self::auftraege(5) as $a) {
            if (in_array($a['status'], ['wartet', 'laeuft'], true)) { return ['vorrat' => $v, 'laeuft' => (int) $a['id']]; }
            if (substr((string) $a['created_at'], 0, 10) === date('Y-m-d')) { return ['vorrat' => $v, 'heute_schon' => (int) $a['id']]; }
        }
        $land = self::naechstesLand();
        $z = MkAutopilot::naechsteZielgruppe($land);
        if ($z === null && $land === 'IT') { $land = 'DE'; $z = MkAutopilot::naechsteZielgruppe('DE'); }   // ohne italienische Zielgruppe bleibt es deutsch
        if ($z === null) { return ['vorrat' => $v, 'hinweis' => 'keine freigegebene Zielgruppe']; }
        $z['land'] = $land;
        $r = MkAuftrag::anlegenInhalte(['zielgruppe' => (int) $z['id'], 'plattformen' => ['tiktok'], 'umfang' => 'organisch', 'anzahl' => self::JE_AUFTRAG,
                                        'mit_bildern' => true, 'tiktok_takt' => true,
                                        'thema' => 'Kurzvideos wie ein Kinotrailer: ein starker Einstieg in den ersten zwei Sekunden, eine Zahl oder Szene aus dem Alltag des Betriebs, Auflösung, Ziel vecom-design.it']);
        if (!is_int($r)) { return ['vorrat' => $v, 'hinweis' => $r]; }
        try { Events::protokoll('tiktok_takt', 'TikTok täglich: Schreibauftrag für „' . $z['titel'] . '“ (' . $z['land'] . ')', null, null, null, ['auftrag_id' => $r]); } catch (Throwable $e) { }
        return ['vorrat' => $v, 'auftrag' => $r, 'land' => (string) $z['land']];
    }

    /** Der Motor für das n-te TikTok-Stück eines Takt-Auftrags (0 = Stimme, 1 = Werbespot, …). */
    public static function motor(int $n): string
    {
        return self::MOTOREN[$n % count(self::MOTOREN)];
    }
}
