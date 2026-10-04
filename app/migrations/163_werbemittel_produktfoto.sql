-- ===========================================================================
-- 163_werbemittel_produktfoto.sql — Produktfoto der Druckerei je Entwurf
-- (04.10.2026, Uwe: „die Produktbilder nehme aus dem Druckanbieter, es muss
-- schon exakt das sein, was der Partner kauft“ — „ja alles“).
--
-- Printful erzeugt mit seinem offiziellen Mockup-Generator ein Foto der
-- echten Karte mit dem Design des Partners (POST /mockup-generator/create-task,
-- GET /mockup-generator/task; höchstens 2–10 Aufträge je Minute). Darum
-- einmal je Entwurf erzeugt und hier gespeichert — die Adressen bei Printful
-- verfallen nach 72 Stunden.
-- mockup_status: wartet | fertig | fehler (NULL = nie angestoßen)
-- ===========================================================================

ALTER TABLE wm_entwuerfe ADD COLUMN IF NOT EXISTS mockup MEDIUMBLOB NULL;
ALTER TABLE wm_entwuerfe ADD COLUMN IF NOT EXISTS mockup_task VARCHAR(80) NULL;
ALTER TABLE wm_entwuerfe ADD COLUMN IF NOT EXISTS mockup_status VARCHAR(12) NULL;
ALTER TABLE wm_entwuerfe ADD COLUMN IF NOT EXISTS mockup_am DATETIME NULL;
