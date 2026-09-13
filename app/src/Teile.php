<?php
declare(strict_types=1);

/**
 * Eine Ansicht in ihre Abschnitte zerlegen.
 *
 * WARUM ES DAS GIBT
 *
 * Seit dem Umbau gibt es einen Bildschirm je Kunde: die Vorgangsseite. Was
 * es nur auf der Projektseite gab — der Auftrag an den Baumeister, die
 * naechtliche Abnahme, die Aufgaben, das Website-Paket — liegt dort in einer
 * Schublade.
 *
 * Der naheliegende Weg waere gewesen, diese Bloecke ein zweites Mal zu
 * schreiben. Dann gaebe es zwei Fassungen desselben Knopfes, und die zweite
 * liefe der ersten hinterher: Wer eine Bedingung aendert, aendert sie an
 * einer Stelle und vergisst die andere. Solche Doppelungen fallen erst auf,
 * wenn ein Kunde etwas sieht, das er nicht sehen sollte.
 *
 * Also laeuft die Projektseite ganz durch — mit allem PHP in der richtigen
 * Reihenfolge, genau wie auf ihrer eigenen Seite — und wird danach an
 * Marken zerschnitten. Die Vorgangsseite nimmt sich die Stuecke, die sie
 * braucht. Eine Fassung, ein Ort zum Aendern.
 *
 * WARUM HTML-KOMMENTARE ALS MARKE
 *
 * Sie kosten nichts, stoeren keinen Browser, ueberstehen jede Verschachtelung
 * und man sieht im Quelltext, wo ein Abschnitt anfaengt. Ein PHP-Aufruf
 * waere sauberer aussehende Technik und haette denselben Effekt — mit dem
 * Unterschied, dass man ihn beim Lesen der Ansicht fuer Logik haelt.
 */
final class Teile
{
    /** So sieht eine Marke aus: <!--teil:werkstatt--> */
    public const MUSTER = '~<!--teil:([a-z_]+)-->~';

    /**
     * Zerlegt fertiges HTML an den Marken.
     *
     * Alles vor der ersten Marke gehoert zu keinem Abschnitt und faellt weg —
     * dort steht die Kopfzeile der Seite und die oeffnenden Spalten-Kaesten,
     * die in einer Schublade nichts verloren haetten.
     *
     * @return array<string,string> Abschnittsname => HTML
     */
    public static function ausHtml(string $html): array
    {
        $stuecke = preg_split(self::MUSTER, $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($stuecke)) { return []; }

        $aus = [];
        for ($i = 1; $i < count($stuecke); $i += 2) {
            $name = (string) $stuecke[$i];
            /* Kommt derselbe Name zweimal, gilt der erste. Ein zweiter waere
               ein Versehen, und stillschweigend den zweiten zu nehmen hiesse,
               das Versehen zu verstecken. */
            if (!array_key_exists($name, $aus)) {
                $aus[$name] = (string) ($stuecke[$i + 1] ?? '');
            }
        }
        return $aus;
    }

    /**
     * Rendert eine Ansichtsdatei und zerlegt sie.
     *
     * Faellt dabei etwas um, kommt eine leere Liste zurueck: Die
     * Vorgangsseite steht dann ohne diese Schublade da, statt gar nicht.
     * Ein Fehler in einem Nebenabschnitt darf nicht die Seite kosten, auf
     * der man arbeitet.
     *
     * @param array<string,mixed> $daten Was die Ansicht als Variablen erwartet
     * @return array<string,string>
     */
    public static function ausAnsicht(string $pfad, array $daten): array
    {
        if (!is_file($pfad)) { return []; }
        $tiefe = ob_get_level();
        try {
            ob_start();
            (static function (string $__pfad, array $__daten): void {
                extract($__daten, EXTR_SKIP);
                require $__pfad;
            })($pfad, $daten);
            $html = (string) ob_get_clean();
        } catch (Throwable $e) {
            /* Bis auf die Ebene zurueck, auf der wir angefangen haben — ein
               halb gefuellter Puffer wuerde sonst in die Seite lecken. */
            while (ob_get_level() > $tiefe) { ob_end_clean(); }
            return [];
        }
        return self::ausHtml($html);
    }
}
