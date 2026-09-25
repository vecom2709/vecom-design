-- ===========================================================================
-- 052_vertragsregeln.sql — Laufzeit, Frist und Inklusivzeit gehoeren in die
-- Verwaltung, nicht in den Code. (25.09.2026)
--
-- Bisher: Abo::MINDESTMONATE = 12 fest im Code, keine Kuendigungsfrist als
-- Feld, die Aenderungszeit der Betreuung nur als Werbetext ("60 Minuten").
-- Uwe, 25.09.2026: 12 Monate Mindestlaufzeit, danach monatlich zum
-- Monatsende. Diese Werte stehen jetzt am Produkt und werden beim
-- Vertragsschluss in den Vertrag kopiert -- eine spaetere Aenderung am
-- Produkt aendert keinen laufenden Vertrag.
--
-- kuendigung_tage: Frist vor dem Monatsende. 0 = bis zum letzten Tag des
-- Monats kuendbar (so steht es heute in den Vertragstexten).
-- Wiederholbar: IF NOT EXISTS, und die UPDATEs setzen nur leere Felder.
-- ===========================================================================

ALTER TABLE packages ADD COLUMN IF NOT EXISTS mindest_monate   TINYINT UNSIGNED  NULL AFTER monthly_cents;
ALTER TABLE packages ADD COLUMN IF NOT EXISTS kuendigung_tage  SMALLINT UNSIGNED NULL AFTER mindest_monate;
ALTER TABLE packages ADD COLUMN IF NOT EXISTS inklusiv_minuten SMALLINT UNSIGNED NULL AFTER kuendigung_tage;

ALTER TABLE abos ADD COLUMN IF NOT EXISTS kuendigung_tage SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER mindestlaufzeit_bis;

UPDATE packages SET mindest_monate = 12
 WHERE art IN ('betreuung', 'hosting') AND mindest_monate IS NULL;
UPDATE packages SET kuendigung_tage = 0
 WHERE art IN ('betreuung', 'hosting') AND kuendigung_tage IS NULL;
UPDATE packages SET inklusiv_minuten = 0   WHERE slug = 'betreuung-basis'   AND inklusiv_minuten IS NULL;
UPDATE packages SET inklusiv_minuten = 60  WHERE slug = 'betreuung-plus'    AND inklusiv_minuten IS NULL;
UPDATE packages SET inklusiv_minuten = 120 WHERE slug = 'betreuung-premium' AND inklusiv_minuten IS NULL;
