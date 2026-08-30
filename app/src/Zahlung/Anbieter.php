<?php
declare(strict_types=1);

/**
 * Was ein Zahlungsanbieter koennen muss. Mehr nicht.
 *
 * Die Verwaltung kennt nur diese vier Methoden — deshalb laesst sich Stripe
 * spaeter gegen PayPal oder Ueberweisung tauschen, ohne dass Bestellungen,
 * Projekte oder das Dashboard etwas davon merken.
 */
interface Anbieter
{
    /** Kurzname, wie er in der Datenbank steht: 'stripe', 'manuell', … */
    public function schluessel(): string;

    /** Ist der Anbieter einsatzbereit (Zugangsdaten vorhanden)? */
    public function bereit(): bool;

    /**
     * Erzeugt eine Bezahlseite fuer eine offene Zahlung und gibt die Adresse
     * zurueck, die der Kunde bekommt.
     *
     * @param array $zahlung Zeile aus payments
     * @param array $bestellung Zeile aus orders
     * @param array $kunde Zeile aus customers
     */
    public function bezahlseite(array $zahlung, array $bestellung, array $kunde): string;

    /**
     * Prueft die Echtheit eines eingehenden Webhooks und gibt das Ereignis
     * zurueck — oder null, wenn die Unterschrift nicht stimmt.
     *
     * @return array{id:string,typ:string,daten:array}|null
     */
    public function ereignisPruefen(string $rohtext, array $kopfzeilen): ?array;
}
