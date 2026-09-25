<?php
declare(strict_types=1);

/* ==========================================================================
   Zustimmung.php — Wer hat wann welchem Wortlaut zugestimmt (25.09.2026).

   WARUM EINE EIGENE TABELLE

   Die Zustimmung zum Website-Auftrag steht seit Langem in orders
   (agb_ok_am, zustimmung_text). Fuer alles andere -- Hosting bestellen,
   Domain uebertragen, spaeter eine Migration -- stand hoechstens ein
   Zeitpunkt da, nie der Satz, dem zugestimmt wurde. Ein Zeitpunkt ohne
   Wortlaut beweist nur, dass jemand auf irgendetwas geklickt hat.

   Gespeichert wird der Text, wie er vor dem Knopf stand, in der Sprache des
   Kunden, mit einer Fassung. Aendert sich der Text spaeter, bleibt der alte
   Nachweis richtig.

   Keine IP-Adresse, kein Browser: Datenminimierung. Wer zustimmen konnte,
   hatte den geheimen Link zur Kundenseite -- das ist der Nachweis der Person.
   ========================================================================== */

final class Zustimmung
{
    public const ARTEN = ['hosting', 'domain_transfer', 'domain_neu', 'mail', 'migration', 'abbuchung', 'mailumzug'];

    public static function festhalten(string $art, int $kundeId, string $text, string $sprache,
                                      string $fassung, ?int $projektId = null, ?int $bezugId = null): int
    {
        if (!in_array($art, self::ARTEN, true)) {
            throw new InvalidArgumentException('Unbekannte Art der Zustimmung: ' . $art);
        }
        if ($kundeId <= 0 || trim($text) === '') {
            throw new InvalidArgumentException('Eine Zustimmung braucht einen Kunden und einen Wortlaut.');
        }
        return (int) Db::insert('zustimmungen', [
            'customer_id' => $kundeId, 'project_id' => $projektId, 'art' => $art, 'bezug_id' => $bezugId,
            'fassung' => mb_substr($fassung, 0, 20),
            'sprache' => in_array($sprache, ['it', 'de', 'en'], true) ? $sprache : 'it',
            'text' => $text,
        ]);
    }

    /** @return list<array<string,mixed>> alle Zustimmungen eines Kunden, neueste zuerst */
    public static function fuerKunde(int $kundeId): array
    {
        return Db::all('SELECT * FROM zustimmungen WHERE customer_id = ? ORDER BY id DESC', [$kundeId]);
    }
}
