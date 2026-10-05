-- Partner-Provisionen wieder automatisch auszahlen (05.10.2026, Uwe: „Beides“ —
-- ausdrückliche Ausnahme von Punkt 30 der Partner-Spezifikation).
-- Migration 179 hatte den Schalter auf 0 gesetzt; hier steht er wieder wie vorher.
-- Abschaltbar bleibt er in der Verwaltung unter Partner › „Bedingungen für alle“.
-- Wiederholbar: ein UPDATE ohne Zeile ändert nichts (dann gilt der Standard 1).
UPDATE settings SET svalue = '1' WHERE skey = 'partner_auto_auszahlen';
