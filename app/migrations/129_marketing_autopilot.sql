-- ===========================================================================
-- 129_marketing_autopilot.sql — Wochen-Autopilot mit Freigabe per Telegram
-- (Marketing-Studio 7, 01.10.2026, Uwe: „ja“ zu U4 — „so gut wie
-- automatisiert … nichts geht ohne deinen Klick raus“).
--
-- Ein Kampagnen-Auftrag meldet sich per Telegram, sobald Texte UND Bilder
-- fertig sind — einmal. gemeldet_am merkt sich das.
--
-- Nur hinzufügen. Rückweg: die Spalte entfernen.
-- ===========================================================================

ALTER TABLE mk_auftraege
  ADD COLUMN IF NOT EXISTS gemeldet_am DATETIME NULL AFTER fertig_am;
