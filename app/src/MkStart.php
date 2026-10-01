<?php
declare(strict_types=1);

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/MkLand.php';

/* ==========================================================================
   MkStart.php — Marketing geführt (01.10.2026, Uwe: Ja zu G1).

   Vier Schritte je Land, immer in derselben Reihenfolge:
     1 Zielgruppe   — wen sprechen wir an? (recherchieren, prüfen, freigeben)
     2 Werben       — Claude schreibt die Beiträge der Woche
     3 Freigeben    — Ja oder Nein je Stück
     4 Läuft        — Vecom postet, die Kanäle sind verbunden, nichts hängt
   Für jeden Schritt: erledigt ja/nein, ein Satz zum Stand und genau ein
   Knopf. Der erste nicht erledigte Schritt ist „jetzt dran“.
   ========================================================================== */
final class MkStart
{
    private static function zahl(string $sql, array $a = []): int
    {
        try { return (int) Db::wert($sql, $a, 0); } catch (Throwable $e) { return 0; }
    }

    /** @return array{schritte:list<array>, dran:int} */
    public static function schritte(string $land): array
    {
        $name = MkLand::name($land);
        $zgFrei = self::zahl("SELECT COUNT(*) FROM mk_zielgruppen WHERE land = ? AND status = 'freigegeben'", [$land]);
        $zgEntwurf = self::zahl("SELECT COUNT(*) FROM mk_zielgruppen WHERE land = ? AND status <> 'freigegeben'", [$land]);
        $recherche = self::zahl("SELECT COUNT(*) FROM mk_auftraege WHERE art = 'recherche' AND land = ? AND status IN ('wartet','laeuft')", [$land]);
        $schreibt = self::zahl("SELECT COUNT(*) FROM mk_auftraege WHERE art = 'inhalte' AND land = ? AND status IN ('wartet','laeuft')", [$land]);
        $woche = self::zahl("SELECT COUNT(*) FROM mk_inhalte WHERE land = ? AND created_at >= NOW() - INTERVAL 7 DAY", [$land]);
        $entwurf = self::zahl("SELECT COUNT(*) FROM mk_inhalte WHERE land = ? AND status = 'entwurf'", [$land]);
        $geplant = self::zahl("SELECT COUNT(*) FROM mk_inhalte WHERE land = ? AND status = 'freigegeben' AND geplant_am IS NOT NULL", [$land]);
        $handy = self::zahl("SELECT COUNT(*) FROM mk_inhalte WHERE land = ? AND status = 'freigegeben' AND geplant_am IS NULL AND (post_fehler IS NULL OR post_fehler = '')", [$land]);
        $gepostet = self::zahl("SELECT COUNT(*) FROM mk_inhalte WHERE land = ? AND status = 'veroeffentlicht' AND veroeffentlicht_am >= NOW() - INTERVAL 7 DAY", [$land]);
        $fehler = self::zahl("SELECT COUNT(*) FROM mk_inhalte WHERE land = ? AND status = 'freigegeben' AND geplant_am IS NULL AND post_fehler IS NOT NULL AND post_fehler <> ''", [$land]);
        require_once __DIR__ . '/MkKanaele.php';
        $kanaele = self::stillStand();
        $verbunden = count(array_filter($kanaele, static fn($k) => $k['bereit']));

        $s = [];
        /* 1 Zielgruppe */
        $s[] = ['nr' => 1, 'titel' => 'Zielgruppe', 'frage' => 'Wen sprechen wir an?', 'fertig' => $zgFrei > 0,
            'text' => $zgFrei > 0 ? $zgFrei . ' freigegeben' . ($zgEntwurf ? ', ' . $zgEntwurf . ($zgEntwurf === 1 ? ' Entwurf' : ' Entwürfe') . ' zum Prüfen' : '') . '.'
                : ($recherche ? 'Claude recherchiert gerade für ' . $name . '.' : ($zgEntwurf ? $zgEntwurf . ($zgEntwurf === 1 ? ' Entwurf wartet' : ' Entwürfe warten') . ' auf dein Ja.' : 'Noch keine Zielgruppe in ' . $name . '.')),
            'knopf' => $zgFrei > 0 ? ['link', 'zielgruppen', 'Zielgruppen ansehen']
                : ($zgEntwurf ? ['link', 'zielgruppen#zielgruppen', 'Entwurf prüfen und freigeben'] : ($recherche ? null : ['tat', 'recherche_starten', 'Claude recherchieren lassen']))];
        /* 2 Werben */
        $s[] = ['nr' => 2, 'titel' => 'Werben', 'frage' => 'Beiträge der Woche schreiben lassen', 'fertig' => $woche > 0 || $schreibt > 0,
            'text' => $schreibt ? 'Claude schreibt gerade — die Bilder entstehen danach.' : ($woche ? $woche . ' Beiträge in den letzten 7 Tagen geschrieben.' : 'Diese Woche noch nichts geschrieben.'),
            'knopf' => $zgFrei === 0 || $schreibt ? null : ['tat', 'woche_werben', $woche ? 'Noch eine Runde schreiben lassen' : 'Diese Woche werben']];
        /* 3 Freigeben */
        $s[] = ['nr' => 3, 'titel' => 'Freigeben', 'frage' => 'Ja oder Nein je Beitrag', 'fertig' => $entwurf === 0 && ($woche > 0 || $geplant + $gepostet > 0),
            'text' => $entwurf ? $entwurf . ($entwurf === 1 ? ' Beitrag wartet' : ' Beiträge warten') . ' auf dein Ja (auch per Telegram).' : 'Nichts offen.',
            'knopf' => $entwurf ? ['link', 'freigabe', $entwurf . ' Beiträge durchgehen'] : null];
        /* 4 Läuft */
        $s[] = ['nr' => 4, 'titel' => 'Läuft', 'frage' => 'Vecom postet zur Sendezeit', 'fertig' => $fehler === 0 && $verbunden > 0,
            'text' => $fehler ? $fehler . ' Beiträge sind nicht rausgegangen.'
                : ($verbunden === 0 ? 'Noch kein Kanal verbunden — freigegebene Beiträge können nicht automatisch rausgehen.'
                : $geplant . ' eingeplant · ' . $gepostet . ' in 7 Tagen gepostet' . ($handy ? ' · ' . $handy . ' zum Posten per Handy' : '') . ' · ' . $verbunden . ' von 3 Kanälen verbunden.'),
            'knopf' => $fehler ? ['link', 'kanaele#fehler', 'Ansehen und neu posten'] : ($verbunden < 3 ? ['link', 'kanaele', 'Kanäle verbinden'] : ['link', 'kanaele', 'Kanäle ansehen'])];

        $dran = 0;
        foreach ($s as $x) { if (!$x['fertig']) { $dran = $x['nr']; break; } }
        return ['schritte' => $s, 'dran' => $dran];
    }

    private static function stillStand(): array
    {
        try { return MkKanaele::stand(); } catch (Throwable $e) { return []; }
    }
}
