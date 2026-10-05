<?php
declare(strict_types=1);

require_once __DIR__ . '/Texte.php';

/* ==========================================================================
   PartnerBranche.php — die EINE Branchenliste des Partnerbereichs
   (Phase 3, 05.10.2026, Uwe: „Diese 12 nehmen“).

   Bis hierher gab es sieben Listen mit 3 bis 80 Einträgen: fünf Branchen-
   Pakete (gastro, unterkunft, handwerk, laden, praxis), acht im Kampagnen-
   Assistenten, 22 in Finder und Kundenliste, acht Looks der Partnerseite,
   sieben Flyer-Gruppen … und „laden“ hieß an einer Stelle Laden & Beauty,
   an der anderen Mode & Schmuck.

   WAS DIESE KLASSE TUT: Sie hält die 12 (+ „andere“), die der Partner sieht,
   und bildet jeden alten Schlüssel darauf ab. Gespeicherte alte Werte bleiben
   stehen und werden beim Lesen übersetzt (von()) — keine Datenwanderung, kein
   Verlust. Die feineren Listen (22 der Akquise, 80 Flyer-Motive, 3D-Szenen)
   bleiben, wo sie fachlich gebraucht werden; nach außen zählt diese Liste.
   ========================================================================== */
final class PartnerBranche
{
    /** Die zwölf Branchen und „andere“ — in dieser Reihenfolge überall. */
    public const ALLE = ['gastronomie', 'unterkunft', 'handwerk', 'einzelhandel', 'beauty', 'gesundheit', 'fitness', 'auto',
        'immobilien', 'beratung', 'tourismus', 'lebensmittel', 'andere'];

    /** Alter Schlüssel (aus irgendeiner der früheren Listen) → einer der zwölf. */
    public const ALT = [
        // Branchen-Pakete (PartnerMarketing::BRANCHEN, PARTNER_BRANCHEN, PARTNER_MEDIEN['motive'])
        'gastro' => 'gastronomie', 'laden' => 'einzelhandel', 'praxis' => 'gesundheit',
        // Kampagnen-Assistent bis 05.10.2026
        'automotive' => 'auto', 'sonstige' => 'andere',
        // Akquise und Kundenliste (akquise_branchen.json)
        'restaurant' => 'gastronomie', 'bar_cafe' => 'gastronomie', 'hotel' => 'unterkunft', 'ferienwohnung' => 'unterkunft',
        'agriturismo' => 'unterkunft', 'baeckerei' => 'lebensmittel', 'bau' => 'handwerk', 'autohaus' => 'auto', 'werkstatt' => 'auto',
        'friseur' => 'beauty', 'produzent' => 'lebensmittel', 'industrie' => 'andere', 'kanzlei' => 'beratung', 'medizin' => 'gesundheit',
        'dienstleister' => 'andere',
        // Look der Partnerseite (SEITE_BRANCHEN) und Flyer-Gruppen (PartnerFlyer::GRUPPEN)
        'produkte' => 'lebensmittel', 'transport' => 'andere', 'gast' => 'gastronomie', 'handel' => 'einzelhandel', 'kreativ' => 'andere',
        'allgemein' => 'andere',
    ];

    /** Für die fertigen Branchen-Texte (PARTNER_BRANCHEN gibt es für fünf): welche passt; ohne Eintrag die allgemeinen. */
    public const PAKET = ['gastronomie' => 'gastro', 'lebensmittel' => 'gastro', 'unterkunft' => 'unterkunft', 'tourismus' => 'unterkunft',
        'handwerk' => 'handwerk', 'einzelhandel' => 'laden', 'beauty' => 'laden', 'gesundheit' => 'praxis', 'fitness' => 'praxis'];

    /** Für die Branchen-Flyer: welche Gruppe (PartnerFlyer::GRUPPEN) vorgewählt wird. */
    public const FLYER = ['gastronomie' => 'gast', 'unterkunft' => 'gast', 'tourismus' => 'gast', 'lebensmittel' => 'handel',
        'handwerk' => 'bau', 'einzelhandel' => 'handel', 'auto' => 'handel', 'beauty' => 'gesundheit', 'gesundheit' => 'gesundheit',
        'fitness' => 'gesundheit', 'immobilien' => 'beratung', 'beratung' => 'beratung', 'andere' => 'allgemein'];

    /** Irgendein Schlüssel → einer der zwölf; '' bei leer oder unbekannt (unbekannt ist ein Fehler, keine Branche). */
    public static function von(?string $k): string
    {
        $k = strtolower(trim((string) $k));
        if ($k === '') { return ''; }
        if (in_array($k, self::ALLE, true)) { return $k; }
        return self::ALT[$k] ?? '';
    }

    /**
     * Name für die Anzeige. Ein feinerer Akquise-Schlüssel (bar_cafe) behält seinen genaueren Namen
     * („Bar / Café“) — die Zuordnung zu den zwölf gilt fürs Filtern, nicht fürs Umbenennen.
     */
    public static function name(?string $k, string $sprache): string
    {
        $k = strtolower(trim((string) $k));
        if ($k === '') { return ''; }
        if (isset(Texte::BRANCHEN_LISTE[$k])) { return Texte::h(Texte::BRANCHEN_LISTE[$k], $sprache); }
        require_once __DIR__ . '/Akquise.php';
        if (isset(Akquise::branchen()[$k])) { return Akquise::branchenName($k, $sprache); }
        $neu = self::von($k);
        return $neu !== '' ? Texte::h(Texte::BRANCHEN_LISTE[$neu], $sprache) : '';
    }

    /** Alle zwölf (+ andere) mit Namen, für Auswahllisten. @return array<string,string> */
    public static function auswahl(string $sprache): array
    {
        $aus = [];
        foreach (self::ALLE as $k) { $aus[$k] = Texte::h(Texte::BRANCHEN_LISTE[$k], $sprache); }
        return $aus;
    }

    /** Die feineren Akquise-Schlüssel, die zu einer der zwölf gehören (für Filter über Finder und Kundenliste). @return list<string> */
    public static function akquise(string $k12): array
    {
        $aus = [];
        foreach (self::ALT as $alt => $neu) { if ($neu === $k12) { $aus[] = $alt; } }
        if (in_array($k12, self::ALLE, true)) { $aus[] = $k12; }
        return array_values(array_unique($aus));
    }
}
