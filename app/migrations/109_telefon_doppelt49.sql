-- Doppelte 49 vor deutschen Handynummern (29.09.2026): „+49491723890040“ → „+491723890040“.
-- Nur Handynummern (15x/16x/17x, 10–11 Ziffern) -- echte Festnetznummern aus 049… (Ostfriesland) sind kürzer.
UPDATE akq_firmen SET telefon = CONCAT('+49', SUBSTRING(telefon, 6)) WHERE telefon REGEXP '^\\+49491[5-7][0-9]{8,9}$';
