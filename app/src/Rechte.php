<?php
declare(strict_types=1);

/* ==========================================================================
   Rechte.php — Rollen in der Verwaltung (05.10.2026, Uwe: „Ja“ zu den Rollen;
   den zweiten Faktor hat er danach abgelehnt: „Verwaltung ohne 2 Faktor“).

   Bis heute war jeder Zugang voller Admin („Ein Zugang sieht alles, was du
   siehst“). Für Mitarbeit an Kunden, Akquise und Marketing braucht es keinen
   Blick in Zahlungen, Steuern, Preise, Hosting-Zugänge oder Partner-Auszahlungen.

     admin      alles (wie bisher)
     mitarbeit  Kunden, Akquise, Marketing, Bauen ansehen und bearbeiten —
                nichts mit Geld, Preisen, Zugängen, Hosting, Einstellungen
     lesen      dieselben Seiten nur ansehen, nichts ändern

   WARUM ERLAUBNISLISTEN STATT SPERRLISTEN: Die Verwaltung hat mehrere hundert
   Taten. Eine vergessene Zeile in einer Sperrliste hieße „darf“, eine
   vergessene Zeile in einer Erlaubnisliste heißt nur „darf noch nicht“. Neue
   Seiten und Taten sind für Nicht-Admins also gesperrt, bis sie hier stehen.
   ========================================================================== */
final class Rechte
{
    public const ROLLEN = ['admin' => 'Admin — alles', 'mitarbeit' => 'Mitarbeit — ohne Geld und Einstellungen', 'lesen' => 'Nur lesen'];

    /** Seiten (erster Teil der Route), die Mitarbeit und Lesen sehen. */
    public const SEITEN = [
        '', 'heute', 'puls', 'suche', 'aktivitaeten', 'benachrichtigungen',
        'vorgaenge', 'kunden', 'projekte', 'anfragen', 'nachrichten', 'bedarf', 'akquise',
        'empfehlungen', 'partner', 'tracking', 'stimmen', 'werbemittel',
        'marketing', 'zahlen', 'zielgruppen', 'recherche', 'freigabe', 'inhalte', 'kampagnen', 'kanaele', 'kanal-karte',
        'medien', 'demo', 'tiktok', 'verzeichnisse', 'ausfuellen', 'statistiken', 'seite-vorschau', 'bewertung-karte',
        'werkstatt', 'onboarding', 'standard', 'muster', 'dateien', 'monitoring', 'automationen',
        // Phase 9: Tür „Partner“ — Support und Meldungen ja, „auszahlungen“ bewusst nicht (Geld nur Admin).
        'partner-support', 'partner-meldungen',
    ];

    /** Taten (Anfang des Namens), die Mitarbeit ausführen darf. Alles mit Geld, Preisen, Zugängen, Hosting fehlt bewusst. */
    public const TATEN_MITARBEIT = [
        'anfrage_', 'bedarf_', 'nachricht', 'kommentar_', 'aufgabe_', 'meldung', 'merkliste_', 'notiz',
        'akq_', 'recherche_', 'zielgruppe_', 'inhalt_', 'inhalte_', 'medium_', 'demo_', 'stapel_', 'verzeichnis_',
        'fragebogen_', 'stimme_', 'werkstatt_', 'muster_', 'datei_', 'kunde_notiz', 'vorher_', 'woche_',
        // Phase 8: den Not-Aus ziehen darf jede Mitarbeit — lösen nur der Admin (automation_weiter steht hier bewusst nicht).
        'automation_notaus',
    ];

    /** Taten, die nur die eigene Person betreffen und jede Rolle braucht (05.10.2026):
        Ohne sie liess sich die Einführung als Mitarbeit oder Nur lesen nicht schließen --
        das Merken wurde abgewiesen, und sie begann bei jedem Laden wieder von vorn. */
    public const TATEN_PERSOENLICH = ['einfuehrung_gesehen'];

    public static function rolle(): string
    {
        $r = (string) (Auth::rolle() ?? '');
        return isset(self::ROLLEN[$r]) ? $r : '';
    }

    /** Darf die angemeldete Person in die Verwaltung? (Kunden-Zugänge nicht.) */
    public static function verwaltung(): bool { return self::rolle() !== ''; }

    public static function darfSeite(string $route): bool
    {
        $r = self::rolle();
        if ($r === 'admin') { return true; }
        return $r !== '' && in_array($route, self::SEITEN, true);
    }

    /** Teamrollen (Phase 9, Uwe: „Partner ja, Geld nein“): Beträge, Provisionen und Auszahlungen sieht nur der Admin. */
    public static function geld(): bool { return self::rolle() === 'admin'; }

    /** Ein Betrag für die Anzeige — für alle außer dem Admin verborgen, nicht nur ausgegraut. */
    public static function betrag(int $cents): string
    {
        require_once __DIR__ . '/Fmt.php';
        return self::geld() ? Fmt::geld($cents) : '•••';
    }

    public static function darfTat(string $tat): bool
    {
        $r = self::rolle();
        if ($r === 'admin') { return true; }
        if ($r !== '' && in_array($tat, self::TATEN_PERSOENLICH, true)) { return true; }
        if ($r !== 'mitarbeit' || $tat === '') { return false; }
        // Was den Kunden schwer trifft (Rechnung, Abnahme, Betreuung …), bleibt beim Admin.
        require_once __DIR__ . '/Ablauf.php';
        if ((Ablauf::TRAGWEITE[$tat][0] ?? null) === Ablauf::SCHWER) { return false; }
        foreach (self::TATEN_MITARBEIT as $anfang) { if (str_starts_with($tat, $anfang)) { return true; } }
        return false;
    }
}
