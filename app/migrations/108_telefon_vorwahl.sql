-- Telefonnummern mit Ländervorwahl ohne „+“ reparieren (29.09.2026, Uwe: Ja)
-- Deutschland: „4940619121“ → „+4940619121“ (sonst wählt das Handy eine Ortsnummer).
UPDATE akq_firmen SET telefon = CONCAT('+', telefon) WHERE land = 'DE' AND telefon REGEXP '^49[1-9][0-9]{6,11}$';
-- Italien: Overture-Nummer „39 0922…“ bekam doppelt +39 („+39390922…“). Nach +39 hat eine
-- italienische Nummer höchstens 10 Ziffern; 11 und mehr, beginnend mit 39, sind doppelt.
UPDATE akq_firmen SET telefon = CONCAT('+', SUBSTRING(telefon, 4)) WHERE land = 'IT' AND telefon REGEXP '^\\+3939[0-9]{9,}$';
