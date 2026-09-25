-- ===========================================================================
-- 055_vorwissen.sql — Die alte Website und die P. IVA lesen, bevor der Kunde
-- tippt (A1 + A3, 25.09.2026)
--
-- seite_gelesen_am: einmal nachgesehen -- auch wenn nichts gefunden wurde,
--                   damit nicht jeder Cronlauf denselben fremden Server fragt.
-- seite_adresse:    wo nachgesehen wurde (fuer die Verwaltung).
-- seite_felder:     JSON {feld: wert} dessen, was WIR eingetragen haben. Der
--                   Fragebogen zeigt daran "von Ihrer Website uebernommen",
--                   solange der Wert noch derselbe ist.
-- Wiederholbar: IF NOT EXISTS.
-- ===========================================================================

ALTER TABLE questionnaires ADD COLUMN IF NOT EXISTS seite_gelesen_am DATETIME NULL AFTER erinnert2_am;
ALTER TABLE questionnaires ADD COLUMN IF NOT EXISTS seite_adresse VARCHAR(190) NULL AFTER seite_gelesen_am;
ALTER TABLE questionnaires ADD COLUMN IF NOT EXISTS seite_felder TEXT NULL AFTER seite_adresse;
-- seite_befunde:    JSON [[art, gewicht], ...] aus Seitenblick -- was an der
--                   alten Seite gemessen wurde (C4: "Ihre Seite heute").
ALTER TABLE questionnaires ADD COLUMN IF NOT EXISTS seite_befunde TEXT NULL AFTER seite_felder;
