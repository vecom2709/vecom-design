-- ===========================================================================
-- 035_ambition.sql — Wie hoch gebaut wird, steht am Projekt.
--
-- WARUM DAS EIN FELD BRAUCHT
--
-- Der Bau-Prompt sagt Claude bisher nur "bau die Seite nach dem
-- Vecom-Standard". Was fehlt, ist die Fallhoehe: Eine Pizzeria fuer 499 Euro
-- und ein Hotel mit Markengeschichte fuer 3.000 brauchen dieselbe Sorgfalt,
-- aber nicht dieselbe Inszenierung. Ohne Angabe kommt beides Mal dasselbe
-- heraus — ordentlich und austauschbar.
--
-- Die Stufe wird aus Preis und Branche gerechnet, und das reicht in den
-- meisten Faellen. Dieses Feld ist fuer die anderen: den Kunden, der mehr
-- will als sein Preis vermuten laesst, und den, bei dem Ruhe richtig ist,
-- obwohl das Budget hoch ist. Leer heisst "rechne es aus".
--
-- A = klar und schnell, B = Premium-UI, C = editorial mit Bewegung,
-- D = immersiv und 3D. Hoeher als noetig ist ein Fehler, kein Ehrgeiz —
-- eine saubere Stufe B schlaegt eine wacklige Stufe D immer.
-- ===========================================================================

ALTER TABLE projects
  ADD COLUMN ambition CHAR(1) NULL DEFAULT NULL AFTER briefing_am;
