-- ===========================================================================
-- 051_keine_pakete.sql — Die drei alten Website-Pakete sind aus. Ganz. (25.09.2026)
--
-- WARUM oeffentlich = 0 NICHT GENUEGTE
--
-- Migrationen 025 und 043 haben Starter (499), Business (899) und Premium
-- (1.499) von der Website genommen — nur oeffentlich = 0, active blieb 1.
-- Alles, was nach "active = 1" fragt, sah sie deshalb weiter:
--   * Telefon::preisAuskunft gab der Telefonassistentin "Starter, 499 Euro"
--     als Festpreis mit,
--   * Telefon::wissen reichte ihr alle drei Pakete samt Preis,
--   * Vorlage::allePakete und die Vorlagen "angebot_starter"/"angebot_klein"
--     setzten den Starter-Preis in Nachrichten an Kunden ein.
-- Auf der Website stand seit dem 12.09. kein Paket mehr — am Telefon und in
-- den Vorlagen schon.
--
-- Es gibt fuer Websites keine Pakete. Jeder Preis entsteht im Konfigurator
-- (Tabelle bausteine) und wird im individuellen Angebot verbindlich.
--
-- WARUM NICHT GELOESCHT
--
-- An den Zeilen koennen Bestellungen, Projekte und Belege haengen
-- (orders.package_id, ON DELETE RESTRICT). Eine geloeschte Zeile machte aus
-- einem bezahlten Auftrag rueckwirkend einen Auftrag ueber nichts.
-- Wiederholbar: ein zweiter Lauf aendert nichts mehr.
-- ===========================================================================

UPDATE packages SET active = 0, oeffentlich = 0, direktkauf = 0, popular = 0
 WHERE slug IN ('starter', 'business', 'premium') AND art = 'website';
