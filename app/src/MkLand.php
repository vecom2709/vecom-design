<?php
declare(strict_types=1);

/* ==========================================================================
   MkLand.php — Deutschland und Italien im Marketing klar getrennt
   (Marketing-Studio 5, 01.10.2026, Uwe: „Wichtig, dass Deutsch und Italien
   klar getrennt sind, dass man nicht den Überblick verliert“).

   Oben auf Zielgruppen, Inhalte und Kampagnen steht ein Schalter
   Italien | Deutschland. Jede Liste zeigt dann nur dieses Land; die Wahl
   bleibt für die Sitzung stehen, damit man beim Wechsel zwischen den Reitern
   im selben Land bleibt. Die Zahl am Schalter sind die Entwürfe, die in dem
   Land auf Uwes Prüfung warten — damit das andere Land nie still liegen
   bleibt.
   ========================================================================== */

final class MkLand
{
    public const NAMEN = ['IT' => 'Italien', 'DE' => 'Deutschland'];

    /** Das gewählte Land: ?land= setzt es (und merkt es sich), sonst das gemerkte, sonst Italien. */
    public static function wahl(): string
    {
        $g = strtoupper((string) ($_GET['land'] ?? ''));
        if (isset(self::NAMEN[$g])) {
            if (session_status() === PHP_SESSION_ACTIVE) { $_SESSION['mk_land'] = $g; }
            return $g;
        }
        $s = (string) ($_SESSION['mk_land'] ?? '');
        return isset(self::NAMEN[$s]) ? $s : 'IT';
    }

    public static function andere(string $land): string
    {
        return $land === 'DE' ? 'IT' : 'DE';
    }

    public static function name(string $land): string
    {
        return self::NAMEN[$land] ?? ($land !== '' ? $land : 'beide Länder');
    }

    /** Entwürfe, die je Land auf Prüfung warten (Zielgruppen + Inhalte). @return array{IT:int, DE:int} */
    public static function offen(): array
    {
        $aus = ['IT' => 0, 'DE' => 0];
        foreach (['mk_zielgruppen', 'mk_inhalte'] as $t) {
            try {
                foreach (Db::all("SELECT land, COUNT(*) AS n FROM $t WHERE status = 'entwurf' GROUP BY land") as $r) {
                    if (isset($aus[$r['land']])) { $aus[$r['land']] += (int) $r['n']; }
                }
            } catch (Throwable $e) { }
        }
        return $aus;
    }

    /** Kleines Länderzeichen (Flagge als Farbstreifen + Name) für Kopfzeilen und Karten. */
    public static function marke(string $land, bool $mitName = true): string
    {
        if (!isset(self::NAMEN[$land])) { return $mitName ? '<span class="mk-land">beide Länder</span>' : ''; }
        return '<span class="mk-land"><i class="mk-flagge mk-flagge--' . strtolower($land) . '" aria-hidden="true"></i>'
            . ($mitName ? htmlspecialchars(self::NAMEN[$land], ENT_QUOTES, 'UTF-8') : '<span class="mk-sr">' . htmlspecialchars(self::NAMEN[$land], ENT_QUOTES, 'UTF-8') . '</span>') . '</span>';
    }

    /** Aus der Zielseite einer Kampagne: /de/… Deutschland, /en/… ohne Land, sonst Italien. */
    public static function ausZiel(string $ziel): string
    {
        if ($ziel === '/de' || str_starts_with($ziel, '/de/')) { return 'DE'; }
        if ($ziel === '/en' || str_starts_with($ziel, '/en/')) { return ''; }
        return 'IT';
    }
}
