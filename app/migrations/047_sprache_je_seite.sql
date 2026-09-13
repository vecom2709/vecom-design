-- ===========================================================================
-- 047_sprache_je_seite.sql — Eine Sprache kostet je Seite, nicht pauschal.
--
-- WARUM
--
-- Uwe, 13.09.2026: „Die Preisspanne auf der Hauptseite ist sehr unrealistisch.
-- Wenn eine Seite 325-400 kostet, können 5 Seiten mit 3 Sprachen keine
-- 800-1000 kosten."
--
-- Er hat recht, und der Fehler saß tiefer als in der einen Zeile.
--
-- DER EIGENTLICHE FEHLER: DIE SPRACHE SKALIERTE NICHT
--
-- `sprache` war eine Pauschale von 140-180 Euro — unabhaengig davon, wie viele
-- Seiten uebersetzt werden. Gerechnet auf die uebersetzte Seite ergab das:
--
--    1 Seite  + 2 Sprachen -> 280-360 EUR Aufschlag = 140-180 EUR je Seite
--    5 Seiten + 2 Sprachen -> 280-360 EUR Aufschlag =  28- 36 EUR je Seite
--   15 Seiten + 2 Sprachen -> 280-360 EUR Aufschlag =   7-  9 EUR je Seite
--
-- Beides kann nicht stimmen. Am Ende stand der Konfigurator dafuer gerade,
-- dass 15 Seiten in drei Sprachen — 45 Seitenfassungen — 1.255 bis 1.600 Euro
-- kosten. Das sind 28 bis 36 Euro je Fassung fuer Wochenarbeit, und es war
-- kein theoretischer Fall: „viele Seiten" und „drei Sprachen" stehen beide
-- zur Auswahl.
--
-- Ab hier ist `sprache` ein Preis JE SEITE. Die Menge kommt aus der
-- Seitenzahl mal den zusaetzlichen Sprachen (Baukasten::rechnen), der Preis
-- je Einheit faellt entsprechend von 140-180 auf 40-55.
--
-- WARUM EINE SPALTE FUER DIE EINHEIT
--
-- Die Preisseite schrieb hinter jeden Baustein mit je_einheit = 1 das Wort
-- „je Stueck". Bei einer weiteren Seite stimmt das; bei einer Sprache, die
-- je Seite gerechnet wird, waere es falsch — und zwar genau an der Stelle,
-- an der der Kunde nachrechnet. Ein fest verdrahteter Sonderfall fuer diesen
-- einen Slug haette dasselbe geleistet und waere beim naechsten Baustein
-- wieder vergessen worden.
--
-- DIE SEITE STEIGT MIT
--
-- 45-60 Euro fuer Aufbau, gesetzten Text und Bilder einer weiteren Seite
-- waren so niedrig, dass die Seitenzahl den Preis kaum bewegte: Von einer auf
-- fuenfzehn Seiten stieg die Website von 345 auf 975 Euro — das Dreifache an
-- Arbeit fuer nicht ganz das Dreifache an Geld, waehrend das Grundgeruest
-- (das nur einmal anfaellt) den groessten Teil trug. Neu: 65-85.
--
-- WAS DARAUS WIRD (die vier Beispiele der Startseite)
--
--   eine Seite            325 -   400 EUR   (unveraendert)
--   fuenf Seiten          600 -   750 EUR   (vorher 525 -   650)
--   fuenf Seiten, 3 Spr.  1.000 - 1.300 EUR (vorher 800 - 1.000)
--   Onlineshop            1.250 - 1.650 EUR (vorher 1.200 - 1.550)
--
-- Der einzige Mitbewerber in der Provinz Agrigent, der Preise nennt, beginnt
-- bei 690 (Vitrinenseite) und 1.590 (Shop). Vecom bleibt bei der Vitrine
-- darunter und liegt beim Shop weiter knapp darunter.
--
-- WAS NICHT STEIGT
--
-- Grundgeruest, Shop, die Zusatzfunktionen und beide Monatsvertraege. Diese
-- Runde repariert eine Rechenregel, sie ist keine Preiserhoehung ueber die
-- Breite.
--
-- WIEDERHOLBAR
--
-- Alle UPDATEs setzen feste Werte statt zu rechnen, ein zweiter Lauf aendert
-- also nichts. Das ALTER TABLE faellt beim zweiten Mal auf „Duplicate column"
-- und wird vom Migrationslauf geschluckt (siehe Einrichtung::HARMLOS).
-- ===========================================================================

ALTER TABLE bausteine
  ADD COLUMN einheit VARCHAR(20) NOT NULL DEFAULT 'stueck' AFTER je_einheit;

-- Die weitere Seite: von 45-60 auf 65-85.
UPDATE bausteine
   SET preis_cents = 6500, preis_bis_cents = 8500, einheit = 'stueck'
 WHERE slug = 'seite' AND monatlich = 0;

-- Die weitere Sprache: aus der Pauschale wird ein Preis je Seite.
UPDATE bausteine
   SET preis_cents = 4000, preis_bis_cents = 5500, einheit = 'seite',
       name_it = 'Altra lingua, per pagina',
       name_de = 'Weitere Sprache, je Seite',
       name_en = 'Additional language, per page',
       text_it = 'Ogni pagina nella seconda o terza lingua, con il selettore.',
       text_de = 'Jede Seite in der zweiten oder dritten Sprache, mit Umschalter.',
       text_en = 'Every page in a second or third language, with the switcher.'
 WHERE slug = 'sprache' AND monatlich = 0;
