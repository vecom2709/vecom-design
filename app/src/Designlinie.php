<?php
declare(strict_types=1);

/**
 * Die fünf Designlinien des Marketing Centers (04.10.2026, Partner-Marketingcenter
 * Schritt 3, Uwe: „ja“).
 *
 * Eine Designlinie ist eine Familie von Vorlagen mit gemeinsamer Farbwelt. Die
 * Marke bleibt in jeder Linie gleich: echtes goldenes V, Montserrat, Gold als
 * Akzent — nur Grund, Fläche und Stimmung wechseln.
 *
 * Gespeichert wird nichts: Welche Linie ein Stil hat, steht hier (STILE). Ein
 * Stil, den es als Datei (noch) nicht gibt, erscheint nirgends — eine Linie ohne
 * Vorlage heißt im Partnerbereich ehrlich „in Vorbereitung“.
 */
final class Designlinie
{
    public const LINIEN = ['premium', 'business', 'tech', 'lifestyle', 'industrial'];

    /** Farbwelt je Linie (für Oberfläche, Vorschau-Chips und künftige Vorlagen). */
    public const FARBEN = [
        'premium'    => ['grund' => '#0b0a08', 'flaeche' => '#17130d', 'text' => '#f6f1e6', 'akzent' => '#e6b85c'],
        'business'   => ['grund' => '#f4efe6', 'flaeche' => '#ffffff', 'text' => '#1f1a13', 'akzent' => '#a57a2c'],
        'tech'       => ['grund' => '#0d0f12', 'flaeche' => '#1a1e24', 'text' => '#eef0f2', 'akzent' => '#e3c27a'],
        'lifestyle'  => ['grund' => '#21160f', 'flaeche' => '#3a281b', 'text' => '#f7ecdc', 'akzent' => '#e2b47c'],
        'industrial' => ['grund' => '#1b1c1e', 'flaeche' => '#2d2f32', 'text' => '#ecebe8', 'akzent' => '#d6ae5c'],
    ];

    /**
     * Stil → Linie je Vorlage. Die Stile a–d gibt es seit Phase 1; a, b, c sind
     * schwarz-gold (Premium), d ist hell (Business). Neue Linien bekommen eigene
     * Buchstaben, sobald ihre Vorlagen gezeichnet und von Uwe freigegeben sind.
     */
    public const STILE = [
        'visitenkarte' => ['a' => 'premium', 'b' => 'premium', 'c' => 'premium', 'd' => 'business'],
        'flyer_a6'     => ['a' => 'premium', 'b' => 'premium', 'c' => 'premium', 'd' => 'business'],
        'flyer_a5'     => ['a' => 'premium', 'b' => 'premium', 'c' => 'premium', 'd' => 'business'],
        'aufkleber_50' => ['a' => 'premium', 'd' => 'business'],
        'rollup_85'    => ['a' => 'premium', 'd' => 'business'],
    ];

    /** Linie eines Stils. Branchen-Flyer: hell (dunkle Schrift am Link) = Business, sonst Premium. */
    public static function von(string $vorlage, string $stil): string
    {
        if ($vorlage === 'flyer_branche') {
            require_once __DIR__ . '/PartnerFlyer.php';
            $farbe = (string) (PartnerFlyer::liste()[$stil]['u']['farbe'] ?? '');
            return $farbe !== '' && self::hell($farbe) < 0.5 ? 'business' : 'premium';
        }
        return self::STILE[$vorlage][$stil] ?? 'premium';
    }

    /** Relative Helligkeit 0…1 einer #rrggbb-Farbe (dunkle Schrift → heller Grund). */
    public static function hell(string $hex): float
    {
        if (!preg_match('~^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$~i', $hex, $m)) { return 1.0; }
        return (0.2126 * hexdec($m[1]) + 0.7152 * hexdec($m[2]) + 0.0722 * hexdec($m[3])) / 255;
    }

    /**
     * Stile, die es für eine Vorlage als Datei gibt (dieselbe Prüfung wie die Auswahl im
     * Marketing Center): Branchen-Flyer = die Branchen mit DE/IT/EN, sonst a, b, c …
     * @return list<string>
     */
    public static function stileDa(string $vorlage): array
    {
        require_once __DIR__ . '/Werbemittel.php';
        require_once __DIR__ . '/PartnerKarten.php';
        if ($vorlage === 'flyer_branche') {
            require_once __DIR__ . '/PartnerFlyer.php';
            return array_values(array_filter(array_keys(PartnerFlyer::liste()),
                static fn($s) => !empty(PartnerFlyer::liste()[$s]['sp']) && Werbemittel::stilDa('flyer_branche', (string) $s)));
        }
        return array_values(array_filter(array_keys(PartnerKarten::STILE), static fn($s) => Werbemittel::stilDa($vorlage, (string) $s)));
    }

    /**
     * Die vorhandenen Stile einer Vorlage nach Linie, in der Reihenfolge von LINIEN.
     * @param list<string> $stile die Stile, die es als Datei gibt
     * @return array<string, list<string>> nur Linien mit mindestens einem Stil
     */
    public static function gruppiert(string $vorlage, array $stile): array
    {
        $aus = [];
        foreach (self::LINIEN as $l) {
            $da = array_values(array_filter($stile, static fn($s) => self::von($vorlage, (string) $s) === $l));
            if ($da) { $aus[$l] = $da; }
        }
        return $aus;
    }
}
