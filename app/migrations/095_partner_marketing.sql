-- ============================================================================
-- 095 — Marketing-Ausbau der Partnerseite (28.09.2026, Uwe: Ja zu Heißer
-- Kontakt, Nachfass-Erinnerung, Mini-Kurs und Meilensteinen).
--
-- partner_checks
--   zuletzt_am  letzter Aufruf durch einen Fremden (nicht der Partner selbst,
--               kein Roboter) -- daraus „Heiße Kontakte“
--   heiss_am    wann der Partner zuletzt deswegen einen Hinweis bekam
--               (höchstens alle 6 Stunden je Bericht)
--   nachfass    höchste Nachfass-Stufe, zu der er erinnert wurde (0/1/2);
--               9 = vom Partner als erledigt markiert
-- partner_reservierungen
--   angeschrieben_am  wann der Partner den Betrieb per WhatsApp/E-Mail
--                     angeschrieben hat (Klick in seiner Liste)
--   nachfass          wie oben
-- partner
--   kurs_start     erster Tag des 7-Tage-Kurses
--   kurs_erledigt  von Hand abgehakte Tage, z. B. „4,7“
--   kurs_push_am   letzter Kurs-Hinweis (einer am Tag)
--   meilensteine   erreichte Meilensteine (für den Hinweis beim ersten Mal)
-- Nur neue Spalten. Wiederholbar: ADD COLUMN → 1060 übersprungen.
-- ============================================================================

ALTER TABLE partner_checks ADD COLUMN zuletzt_am DATETIME NULL;
ALTER TABLE partner_checks ADD COLUMN heiss_am DATETIME NULL;
ALTER TABLE partner_checks ADD COLUMN nachfass TINYINT NOT NULL DEFAULT 0;
ALTER TABLE partner_reservierungen ADD COLUMN angeschrieben_am DATETIME NULL;
ALTER TABLE partner_reservierungen ADD COLUMN nachfass TINYINT NOT NULL DEFAULT 0;
ALTER TABLE partner ADD COLUMN kurs_start DATE NULL;
ALTER TABLE partner ADD COLUMN kurs_erledigt VARCHAR(40) NULL;
ALTER TABLE partner ADD COLUMN kurs_push_am DATE NULL;
ALTER TABLE partner ADD COLUMN meilensteine VARCHAR(400) NULL;
