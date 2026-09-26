-- ===========================================================================
-- 066_veroeffentlichung.sql — die fertige Seite auf die Kundendomain (26.09.2026)
--
-- veroeffentlicht_am/_domain: wann und wohin das Paket aus der Werkstatt per
-- FTPS in den Web-Ordner des Kunden-Accounts ging (Knopf, nie von allein).
-- files.rolle bekommt eine vierte Sorte: 'sicherung' -- was vorher auf dem
-- Webspace lag. Nur fuer die Verwaltung, nie in der Liste des Kunden.
-- Wiederholbar: IF NOT EXISTS.
-- ===========================================================================

ALTER TABLE projects ADD COLUMN IF NOT EXISTS veroeffentlicht_am DATETIME NULL;
ALTER TABLE projects ADD COLUMN IF NOT EXISTS veroeffentlicht_domain VARCHAR(190) NULL;
