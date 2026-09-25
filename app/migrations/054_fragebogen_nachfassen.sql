-- ===========================================================================
-- 054_fragebogen_nachfassen.sql — Zwei Erinnerungen statt einer (C3, 25.09.2026)
--
-- Bisher: eine Erinnerung nach drei Tagen, und nur an Frageboegen, die
-- ausdruecklich verschickt wurden. Der Fragebogen VOR dem Preis (im
-- Dashboard, seit 21.09.) bekam nie eine -- wer mittendrin aufhoerte, hoerte
-- nie wieder von uns. Jetzt: nach einem Tag ohne Bewegung und, wenn danach
-- nichts passiert, nach drei Tagen noch einmal. Dann ist Ruhe.
-- Wiederholbar: IF NOT EXISTS.
-- ===========================================================================

ALTER TABLE questionnaires ADD COLUMN IF NOT EXISTS erinnert2_am DATETIME NULL AFTER erinnert_am;
