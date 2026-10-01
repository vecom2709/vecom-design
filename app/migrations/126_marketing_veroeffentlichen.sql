-- ===========================================================================
-- 126_marketing_veroeffentlichen.sql — Veröffentlichen nach Freigabe
-- (Marketing-Studio Schritt 4, 01.10.2026, Uwe: „Ja, so“ — automatisch auf
-- Facebook, Instagram und Telegram, Pakete für alles andere).
--
-- Gepostet wird nur, was Uwe freigegeben hat: sofort per Knopf oder zu einem
-- geplanten Zeitpunkt (Cronlauf). Meta holt Bild/Video über eine öffentliche
-- Adresse mit langem Zufallsschlüssel (m.php?t=…), die nur für gewählte
-- Medien freigegebener Inhalte gilt.
--
-- Nur hinzufügen. Rückweg: die neuen Spalten entfernen.
-- ===========================================================================

ALTER TABLE mk_inhalte
  ADD COLUMN IF NOT EXISTS geplant_am   DATETIME NULL AFTER freigegeben_am,
  ADD COLUMN IF NOT EXISTS post_ids     VARCHAR(400) NULL AFTER veroeffentlicht_am,   -- JSON {fb, ig, ig_container, tg}
  ADD COLUMN IF NOT EXISTS post_fehler  VARCHAR(300) NULL AFTER post_ids,
  ADD INDEX IF NOT EXISTS ix_mk_inhalt_geplant (status, geplant_am);

ALTER TABLE mk_medien
  ADD COLUMN IF NOT EXISTS token CHAR(32) NULL AFTER status,
  ADD UNIQUE INDEX IF NOT EXISTS uq_mk_medium_token (token);
