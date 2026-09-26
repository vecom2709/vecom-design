-- ============================================================================
-- 077 — Partner gestalten ihre Empfehlungsseite selbst (26.09.2026, Uwe:
-- „individuell gestalten, wichtig: Vecom-Logo bleibt“; Ja zu Vorlage & Farbe,
-- Titelbild, eigenen Texten, Bausteinen; Änderungen sofort live, Vecom kann
-- zurücksetzen).
--
-- seite_json: nur geprüfte Werte (Vorlage, Farbe, Bild-Schlüssel, Texte mit
-- Längengrenzen, Bausteine) -- nie HTML, nie CSS vom Partner.
-- seite_bild: eigenes Titelbild, neu gerechnet als WebP ohne EXIF.
-- Wiederholbar: doppelte Spalten scheitern mit 1060, Einrichtung überspringt.
-- ============================================================================

ALTER TABLE partner ADD COLUMN seite_json MEDIUMTEXT NULL;
ALTER TABLE partner ADD COLUMN seite_bild MEDIUMBLOB NULL;
ALTER TABLE partner ADD COLUMN seite_bild_am DATETIME NULL;
ALTER TABLE partner ADD COLUMN seite_am DATETIME NULL;
