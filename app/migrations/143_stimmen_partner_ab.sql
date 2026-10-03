-- ===========================================================================
-- 143_stimmen_partner_ab.sql — Kundenstimmen über den Link eines Partners und
-- der Test zweier Überschriften (03.10.2026, Uwe: Ja zu N3 und N4).
--
-- stimmen.partner_id: Die Stimme kam über den Sammellink dieses Partners und
--   steht nach Uwes Freigabe auf SEINER Seite (nicht auf vecom-design.it).
-- stimmen.foto: kleines Quadrat (WebP, 320 px), nur mit Erlaubnis gezeigt.
-- spur_besuche.ab_variante: welche Überschrift dieser Besuch sah (A oder B).
-- Nur hinzufügen. Rückweg: Spalten entfernen.
-- ===========================================================================

ALTER TABLE stimmen ADD COLUMN partner_id INT UNSIGNED NULL;
ALTER TABLE stimmen ADD COLUMN foto MEDIUMBLOB NULL;
ALTER TABLE spur_besuche ADD COLUMN ab_variante CHAR(1) NULL;
