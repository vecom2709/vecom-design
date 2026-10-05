-- Auszahlen nur per Klick (05.10.2026, Spezifikation Punkt 30/49:
-- „Automatisch berechnen erlaubt. Automatisch auszahlen verboten.“).
-- Der Code liest den Schalter nicht mehr; hier wird er nur auf den wahren
-- Stand gesetzt, damit Datenbank und Verwaltung dasselbe sagen.
-- Wiederholbar: ein UPDATE ohne Zeile ändert nichts.
UPDATE settings SET svalue = '0' WHERE skey = 'partner_auto_auszahlen';
