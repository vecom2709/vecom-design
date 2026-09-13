<?php
declare(strict_types=1);

/**
 * Wie ausführlich die Verwaltung sich zeigt.
 *
 * WARUM ES DAS GIBT
 *
 * Gezählt am 13.09.2026: 31 Menüpunkte, 54 Seiten, 130 Handgriffe, und die
 * Vorgangsseite allein mit 13 Blöcken und 29 Knöpfen. Jeder einzelne davon
 * hat einen Grund — zusammen ergeben sie eine Oberfläche, vor der jemand,
 * der sie nicht gebaut hat, nicht weiß, wo er anfangen soll.
 *
 * Der naheliegende Ausweg wäre, etwas wegzulassen. Das geht nicht: Was
 * wegfällt, reißt irgendwo die Kette. Ein Handgriff, den es nicht mehr gibt,
 * ist nicht einfacher, sondern fehlt.
 *
 * Also wird nichts weggelassen, sondern eingeräumt. Im einfachen Modus zeigt
 * jede Seite, was für den nächsten Schritt gebraucht wird; alles Weitere
 * liegt hinter einem „Mehr" — sichtbar, dass es da ist, einen Klick entfernt.
 * Im vollen Modus steht alles offen wie bisher.
 *
 * WARUM DIE VORGABE „EINFACH" IST
 *
 * Wer den vollen Modus braucht, findet den Schalter. Wer ihn nicht braucht,
 * würde von der vollen Ansicht überfahren, bevor er den Schalter überhaupt
 * sucht. Die unangenehmere Vorgabe gehört dorthin, wo sie weniger schadet.
 *
 * WARUM EINE EINSTELLUNG UND NICHT EINE JE BENUTZER
 *
 * Die Verwaltung hat einen Arbeitsplatz. Eine Einstellung je Benutzer wäre
 * eine Tabelle mehr für einen Unterschied, den es nicht gibt — und die Frage
 * „warum sieht es bei mir anders aus" gäbe es dann zusätzlich.
 */
final class Modus
{
    public const SCHLUESSEL = 'bedienung_einfach';

    /** Einmal je Aufruf gelesen — die Frage kommt auf jeder Seite oft. */
    private static ?bool $merk = null;

    /**
     * Ist der einfache Modus an?
     *
     * Die Vorgabe gilt auch, wenn die Zeile fehlt oder die Datenbank sie
     * nicht hergibt: Eine Oberfläche, die bei einer Störung in den vollen
     * Modus fällt, tut genau das Falsche.
     */
    public static function einfach(): bool
    {
        if (self::$merk === null) {
            try {
                $w = Db::wert('SELECT svalue FROM settings WHERE skey = ?', [self::SCHLUESSEL], null);
                self::$merk = $w === null ? true : ((string) $w !== 'nein');
            } catch (Throwable $e) {
                self::$merk = true;
            }
        }
        return self::$merk;
    }

    /** Umschalten. */
    public static function setzen(bool $einfach): void
    {
        Db::run('INSERT INTO settings (skey, svalue) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
            [self::SCHLUESSEL, $einfach ? 'ja' : 'nein']);
        self::$merk = $einfach;
    }

    /** Nur für die Kettenprüfung: den gemerkten Wert vergessen. */
    public static function vergessen(): void
    {
        self::$merk = null;
    }
}
