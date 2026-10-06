<?php
declare(strict_types=1);

/* ==========================================================================
   KundeTour — die Einführung hinter dem „?“ auf der Kundenseite (06.10.2026).

   Dieselbe Mechanik wie bei den Partnern: assets/js/partner-tour.js liest
   ein JSON aus <script id="tour-daten"> und zeigt Lichtkegel und
   Sprechblase. Hier entsteht nur dieses JSON -- nichts Zweites, das man
   pflegen müsste. Die Texte stehen in Texte::KUNDE_TOUR, wie alle
   Kundentexte.

   Bewusst anders als bei den Partnern:
   - Es wird nichts gespeichert und nichts gemeldet ('melden' => null). Ob
     ein Kunde die Tour gesehen hat, weiß nur sein Browser (localStorage).
     Uwe braucht dafür keine Zahl, und ein Formular mehr hieße ein Angriffs-
     weg mehr auf einer öffentlichen Seite.
   - Die Tour heißt „kunde“, nicht „haupt“. Sonst gälte eine im selben
     Browser gesehene Partnertour hier als erledigt (gleiche Domain).
   ========================================================================== */
final class KundeTour
{
    public const NAME = 'kunde';

    private static function t(array $x, string $sp): string
    {
        return (string) ($x[$sp] ?? $x['it'] ?? reset($x));
    }

    /**
     * Alles, was partner-tour.js braucht.
     *
     * @param string $url  die Adresse dieser Kundenseite (mit Schlüssel)
     * @return array<string,mixed>
     */
    public static function daten(string $sp, string $url): array
    {
        require_once __DIR__ . '/Texte.php';
        $q = Texte::KUNDE_TOUR;
        $schritte = [];
        foreach ($q['schritte'] as $s) {
            $schritte[] = ['k' => $s['k'], 'seite' => 'start', 'ziel' => $s['ziel'],
                'titel' => self::t($s['titel'], $sp), 'text' => self::t($s['text'], $sp)];
        }
        $texte = [];
        foreach ($q['knoepfe'] as $k => $x) { $texte[$k] = self::t($x, $sp); }

        /* ?tour=kunde startet sie von außen, etwa aus einer Mail
           („So funktioniert Ihre Seite“). Sonst startet sie beim ersten
           Besuch von selbst -- partner-tour.js prüft localStorage. */
        $start = ((string) ($_GET['tour'] ?? '')) === self::NAME ? ['tour' => self::NAME, 'ab' => ''] : null;

        return [
            'seite' => 'start', 'ganz' => self::NAME, 'tour_hier' => self::NAME,
            'auto' => $start === null ? self::NAME : null, 'start' => $start,
            'touren' => [self::NAME => $schritte],
            'urls' => ['start' => $url],
            'texte' => $texte, 'hilfe' => self::t($q['hilfe'], $sp),
            'melden' => null, 'csrf' => '', 'code' => '',
        ];
    }
}
