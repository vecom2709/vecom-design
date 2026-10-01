-- ===========================================================================
-- 127_marketing_laender.sql — Deutschland und Italien klar getrennt
-- (Marketing-Studio 5, 01.10.2026, Uwe: „Wichtig, dass Deutsch und Italien
-- klar getrennt sind, dass man nicht den Überblick verliert“ und „Zielgruppe
-- und Recherche sollen eins werden … für die Kampagne genutzt werden können“).
--
-- Kampagnen bekommen ein Land (aus der Zielseite: /de/… = DE, sonst IT,
-- /en/… = ohne Land) und optional die Zielgruppe, aus der sie entstanden.
-- Inhalte auf Italienisch bekommen die deutsche Übersetzung zum Lesen.
--
-- Nur hinzufügen. Rückweg: die neuen Spalten entfernen.
-- ===========================================================================

ALTER TABLE mk_kampagnen
  ADD COLUMN IF NOT EXISTS land          CHAR(2)      NOT NULL DEFAULT '' AFTER branche,
  ADD COLUMN IF NOT EXISTS zielgruppe_id INT UNSIGNED NULL AFTER land,
  ADD INDEX IF NOT EXISTS ix_mk_kampagne_land (land);

UPDATE mk_kampagnen
   SET land = CASE WHEN ziel = '/de' OR ziel LIKE '/de/%' THEN 'DE' WHEN ziel = '/en' OR ziel LIKE '/en/%' THEN '' ELSE 'IT' END
 WHERE land = '';

ALTER TABLE mk_inhalte
  ADD COLUMN IF NOT EXISTS uebersetzung TEXT NULL AFTER felder;   -- deutsche Fassung eines italienischen Stücks, nur zum Lesen
