<?php
declare(strict_types=1);

/* ==========================================================================
   DruckereiSchnittstelle.php — was jede angebundene Druckerei können muss
   (04.10.2026, Partner-Marketingcenter Schritt 1c, Uwe: „ja“).

   Die Vorgabe nennt sechs Funktionen. So sind sie hier abgebildet — ohne
   etwas doppelt zu bauen, was es schon gibt:

     getProducts / getVariants  →  unser eigener Katalog (wm_produkte,
                                   wm_varianten) plus die Zuordnung je
                                   Druckerei (wm_anbieter_produkte,
                                   Druckerei::artikel). Der Partner wählt nie
                                   einen Druckerei-Artikel, sondern unser
                                   Produkt; welche Druckerei es herstellt,
                                   entscheidet Vecom.
     getPrice                   →  DruckereiPreise::preisJetzt() (Preis einer
                                   Bestellung jetzt) und preiseAktualisieren()
                                   (alle Angebote neu holen).
     createOrder                →  DruckereiAnbieter::auftragSenden()
     getOrderStatus             →  DruckereiAnbieter::nachsehen() (alle
                                   offenen Aufträge, Cron)
     getShippingOptions         →  bewusst NICHT vorhanden: Die geprüften
                                   Angebote enthalten den Standardversand
                                   schon. Kommt erst, wenn eine Druckerei
                                   Versandarten in ihrer offiziellen Doku
                                   anbietet — keine erfundenen Endpunkte.

   Flyeralarm hat keine öffentliche Bestell-Schnittstelle und steht deshalb
   nicht im Register: Dort bestellt Uwe von Hand mit der Druckdatei.
   ========================================================================== */

interface DruckereiAnbieter
{
    /** Kann diese Druckerei jetzt (für $land) beliefert werden? Schlüssel da, Land passt. */
    public static function bereit(?string $land = null): bool;

    /**
     * Bezahlte Bestellung als Auftrag senden — genau einmal (Druckerei::sperren),
     * ein Fehler bleibt stehen und wird nie automatisch wiederholt.
     * @return array{ok:bool, grund:string, id?:string}
     */
    public static function auftragSenden(int $bestellungId): array;

    /** Offene Aufträge nachsehen (Status, Sendung). @return int wie viele sich geändert haben */
    public static function nachsehen(): int;
}

/** Druckereien, deren Preise per Schnittstelle abrufbar sind. */
interface DruckereiPreise
{
    /** Einkaufspreis der Bestellung jetzt, in Cent, oder null (nicht abrufbar). */
    public static function preisJetzt(int $bestellungId): ?int;

    /** Alle zugeordneten Varianten neu bepreisen. @return int Anzahl eingetragener Preise */
    public static function preiseAktualisieren(): int;
}
