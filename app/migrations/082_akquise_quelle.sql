-- ============================================================================
-- 082 — Suchaufträge mit Quelle (27.09.2026, Uwe: „Deutschland und ganz
-- Italien — auf Abruf“). osm = Overpass Gemeinde für Gemeinde (bisher),
-- overture = Overture Maps, ein ganzes Gebiet auf einmal. Der Partner-Finder
-- legt für jeden unbekannten Ort einen Overture-Auftrag für dessen Provinz /
-- Landkreis an; der Worker auf Uwes Rechner arbeitet ihn ab.
-- Wiederholbar: doppelte Spalte scheitert mit 1060, Einrichtung überspringt.
-- ============================================================================

ALTER TABLE akq_laeufe ADD COLUMN quelle VARCHAR(12) NOT NULL DEFAULT 'osm';
