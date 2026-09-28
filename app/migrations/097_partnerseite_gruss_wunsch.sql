-- ============================================================================
-- 097 — Partnerseite, Runde 2 (28.09.2026, Uwe: Ja zu R1–R7).
--
-- seite_gruss: persönliche Sprachnachricht des Partners (bis 30 Sekunden,
--   höchstens 1 MB, so gespeichert, wie der Browser sie aufgenommen hat).
-- zugaenge.wunsch: was der Besucher auf der Partnerseite angetippt hat
--   (neu, ueberarbeitung, shop, unsicher) -- steht später in der Kundenakte.
-- ============================================================================

ALTER TABLE partner ADD COLUMN seite_gruss MEDIUMBLOB NULL;
ALTER TABLE partner ADD COLUMN seite_gruss_typ VARCHAR(40) NULL;
ALTER TABLE partner ADD COLUMN seite_gruss_am DATETIME NULL;
ALTER TABLE zugaenge ADD COLUMN wunsch VARCHAR(20) NULL;
