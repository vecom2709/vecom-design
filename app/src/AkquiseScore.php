<?php
declare(strict_types=1);

/**
 * Opportunity Score 0–100 — nur intern, nie in einem Text an die Firma.
 *
 * WIE GERECHNET WIRD
 *
 * Sieben Toepfe mit festen Hoechstwerten (Summe 100). Jeder Befund fuellt
 * seinen Topf ein Stueck, aber nie ueber den Rand: Ein Topf mit Befunden
 * der Schwere s_1, s_2, … ist zu  1 − Π(1 − s_i/6 · g_i)  gefuellt, g = 1
 * fuer belegte und 0,4 fuer unbelegte Befunde.
 *
 * Warum nicht einfach Punkte addieren: Zwanzig kleine Alt-Texte-Befunde
 * wuerden sonst eine Seite mit einem einzigen, echten Problem ueberholen
 * -- und "viele Kleinigkeiten" ist nicht dasselbe wie "hier lohnt sich ein
 * Projekt". Die Saettigung sorgt dafuer, dass der erste schwere Befund
 * viel zaehlt und der zehnte leichte kaum noch.
 *
 * Warum unbelegte Befunde nur 40 %: Sie sind Hinweise, keine Tatsachen.
 * Ein Lead, dessen Score vor allem aus Vermutungen besteht, soll nicht
 * oben in der Liste stehen.
 */
final class AkquiseScore
{
    /** Topf => Hoechstpunkte. Vorgabe von Uwe, 24.09.2026. */
    public const GEWICHTE = [
        'technik'    => 20,   // Technik + Performance
        'mobile'     => 15,
        'ux'         => 20,   // UX + Conversion
        'design'     => 15,
        'seo'        => 10,
        'vertrauen'  => 10,
        'experience' => 10,
    ];

    /** Welche Befund-Kategorie in welchen Topf faellt. */
    public const TOPF = [
        'technik' => 'technik', 'performance' => 'technik', 'mobile' => 'mobile',
        'ux' => 'ux', 'conversion' => 'ux', 'design' => 'design', 'seo' => 'seo',
        'vertrauen' => 'vertrauen', 'experience' => 'experience',
    ];

    public const STUFEN = [
        'gering'           => 'geringes Potenzial',
        'beobachten'       => 'beobachten',
        'interessant'      => 'interessanter Lead',
        'sehr_interessant' => 'sehr interessantes Projekt',
        'top'              => 'Top Opportunity',
    ];

    public const GEWICHT_UNBELEGT = 0.4;

    /**
     * Untergrenzen fuer Seiten, die faktisch nicht da sind.
     *
     * GEFUNDEN BEIM ERSTEN ECHTEN LAUF (26.09.2026): Bistro 73 antwortet mit
     * 503 auf einer Baustellenseite -- ein einziger Befund, Score 17,
     * "geringes Potenzial". Dabei braucht genau dieser Betrieb eine ganze
     * Website. Die Toepfe messen, was an einer Seite schlecht ist; eine
     * Seite, die es nicht gibt, hat nichts, woran man messen koennte. Ein
     * belegter "gibt es nicht"-Befund hebt den Score deshalb auf eine feste
     * Mindesthoehe.
     */
    public const BODEN = [
        'domain_tot'             => 80,
        'seite_nicht_erreichbar' => 80,
        'platzhalter_text'       => 65,
    ];

    public static function stufe(int $score): string
    {
        return match (true) {
            $score >= 86 => 'top',
            $score >= 71 => 'sehr_interessant',
            $score >= 51 => 'interessant',
            $score >= 31 => 'beobachten',
            default      => 'gering',
        };
    }

    /**
     * @param list<array{kategorie:string,schwere:int,status:string}> $befunde
     * @return array{score:int,stufe:string,teile:array<string,float>}
     */
    public static function berechnen(array $befunde, string $branche = ''): array
    {
        $rest = array_fill_keys(array_keys(self::GEWICHTE), 1.0);
        foreach ($befunde as $b) {
            $topf = self::TOPF[(string) ($b['kategorie'] ?? '')] ?? null;
            if ($topf === null) { continue; }
            $status = (string) ($b['status'] ?? 'VERIFIED');
            if ($status === 'VERWORFEN') { continue; }
            $g = $status === 'VERIFIED' ? 1.0 : self::GEWICHT_UNBELEGT;
            $s = max(1, min(5, (int) ($b['schwere'] ?? 2)));
            $rest[$topf] *= (1 - ($s / 6) * $g);
        }
        $teile = [];
        $summe = 0.0;
        foreach (self::GEWICHTE as $topf => $max) {
            $wert = round($max * (1 - $rest[$topf]), 1);
            $teile[$topf] = $wert;
            $summe += $wert;
        }
        $score = (int) max(0, min(100, round($summe)));
        foreach ($befunde as $b) {
            $boden = self::BODEN[(string) ($b['code'] ?? '')] ?? null;
            if ($boden !== null && ($b['status'] ?? '') === 'VERIFIED') { $score = max($score, $boden); }
        }
        return ['score' => $score, 'stufe' => self::stufe($score), 'teile' => $teile];
    }

    /**
     * Die wichtigsten Befunde: belegt vor unbelegt, schwer vor leicht,
     * je Code nur einmal. Experience-Hinweise sind kein Problem der Firma
     * und stehen deshalb nie unter den "Top-Problemen".
     *
     * @param list<array<string,mixed>> $befunde
     * @return list<array<string,mixed>>
     */
    public static function topBefunde(array $befunde): array
    {
        $liste = array_values(array_filter($befunde, static fn($b) =>
            ($b['kategorie'] ?? '') !== 'experience' && ($b['status'] ?? '') !== 'VERWORFEN'));
        usort($liste, static function ($a, $b) {
            $va = ($a['status'] ?? '') === 'VERIFIED' ? 1 : 0;
            $vb = ($b['status'] ?? '') === 'VERIFIED' ? 1 : 0;
            if ($va !== $vb) { return $vb <=> $va; }
            return ((int) ($b['schwere'] ?? 0)) <=> ((int) ($a['schwere'] ?? 0));
        });
        $gesehen = [];
        $out = [];
        foreach ($liste as $b) {
            $c = (string) ($b['code'] ?? '');
            if (isset($gesehen[$c])) { continue; }
            $gesehen[$c] = true;
            $out[] = $b;
        }
        return $out;
    }
}
