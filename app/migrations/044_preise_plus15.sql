-- ===========================================================================
-- 044_preise_plus15.sql — Die Bausteine 15 Prozent hoeher.
--
-- WARUM
--
-- Uwe, 12.09.2026: „bei den Preise von bis setze etwas hoeher." Die Spannen
-- auf der Startseite und der Preisseite rechnen sich aus diesen Zahlen — hier
-- ist die einzige Stelle, an der sie stehen.
--
-- WOHIN
--
-- +15 Prozent, jede Grenze auf volle fuenf Euro gerundet. Danach ergeben die
-- vier Beispiele:
--
--   eine Seite        325 –   400 EUR   (vorher 275 –   350)
--   fuenf Seiten      525 –   650 EUR   (vorher 450 –   575)
--   drei Sprachen     800 – 1.000 EUR   (vorher 675 –   875)
--   Onlineshop      1.200 – 1.550 EUR   (vorher 1.000 – 1.350)
--
-- Der einzige Mitbewerber in der Provinz Agrigent, der Preise nennt, beginnt
-- bei 690 (Vitrinenseite) und 1.590 (Shop). Nach der Erhoehung liegt Vecom
-- weiter knapp darunter — das Argument „offene Preise, und guenstiger als der
-- Einzige, der seine zeigt" bleibt also stehen.
--
-- WAS NICHT STEIGT
--
-- Die monatliche Betreuung (39 EUR) und Domain & Hosting (9,90 EUR). An einem
-- Monatsbetrag bleibt der Blick haengen, und an Hosting haengen echte
-- Fremdkosten, die sich nicht geaendert haben. Verdient werden soll die
-- Erhoehung beim Bauen, nicht am Abo. Deshalb steht in jeder Zeile unten
-- ausdruecklich monatlich = 0.
--
-- VERHAELTNIS ZUR EINFUEHRUNGSPHASE
--
-- Die Einfuehrungsphase (Einfuehrung::anwenden, +20 % nach den ersten zehn
-- abgeschlossenen Kunden) bleibt unberuehrt: Ihre Sperre
-- (settings.einfuehrung_erledigt) wird hier nicht gesetzt. Diese Erhoehung
-- verschiebt den Ausgangspunkt, sie nimmt den spaeteren Schritt nicht vorweg.
-- ===========================================================================

UPDATE bausteine SET preis_cents =  34500, preis_bis_cents =  40000 WHERE slug = 'basis'       AND monatlich = 0;
UPDATE bausteine SET preis_cents =   4500, preis_bis_cents =   6000 WHERE slug = 'seite'       AND monatlich = 0;
UPDATE bausteine SET preis_cents =  14000, preis_bis_cents =  18000 WHERE slug = 'sprache'     AND monatlich = 0;
UPDATE bausteine SET preis_cents =  10500, preis_bis_cents =  14000 WHERE slug = 'speisekarte' AND monatlich = 0;
UPDATE bausteine SET preis_cents =  22000, preis_bis_cents =  29000 WHERE slug = 'termine'     AND monatlich = 0;
UPDATE bausteine SET preis_cents =  45000, preis_bis_cents =  60000 WHERE slug = 'buchung'     AND monatlich = 0;
UPDATE bausteine SET preis_cents =  68000, preis_bis_cents =  91000 WHERE slug = 'shop'        AND monatlich = 0;
UPDATE bausteine SET preis_cents =  14000, preis_bis_cents =  18500 WHERE slug = 'texte'       AND monatlich = 0;
UPDATE bausteine SET preis_cents =  10500, preis_bis_cents =  14000 WHERE slug = 'fotos'       AND monatlich = 0;
UPDATE bausteine SET preis_cents =  10500, preis_bis_cents =  14000 WHERE slug = 'uebernahme'  AND monatlich = 0;
UPDATE bausteine SET preis_cents =  17000, preis_bis_cents =  23000 WHERE slug = 'express'     AND monatlich = 0;

-- Das Logo steht auf Anfrage und zeigt seinen Preis nirgends — es soll aber
-- nicht als einziger Baustein auf dem alten Stand zurueckbleiben, sonst faellt
-- es beim naechsten Angebot aus der Reihe. Gerundet auf volle fuenf Euro.
--
-- Als einzige Zeile hier rechnet sie aus dem Bestand statt feste Werte zu
-- setzen — ein zweiter Lauf wuerde also ein zweites Mal erhoehen. Alle
-- Migrationen sollen wiederholbar sein (siehe CLAUDE.md), deshalb der Riegel
-- ueber settings: Er ist gesetzt, sobald die Zeile einmal gelaufen ist.
UPDATE bausteine
   SET preis_cents     = ROUND(preis_cents     * 1.15 / 500) * 500,
       preis_bis_cents = ROUND(preis_bis_cents * 1.15 / 500) * 500
 WHERE slug = 'logo' AND monatlich = 0
   AND NOT EXISTS (SELECT 1 FROM settings WHERE skey = 'preise_plus15_logo');

INSERT INTO settings (skey, svalue) VALUES ('preise_plus15_logo', '1')
  ON DUPLICATE KEY UPDATE skey = skey;
