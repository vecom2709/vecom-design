-- ===========================================================================
-- 204_autobuild_versionen.sql — AutoBuild Phase 6 (06.10.2026, Uwe: „fahre
-- fort“; Entscheidungen 2 „Netlify“ und 3 „ja“).
--
-- Jedes Website-Paket bekommt eine Nummer (V1, V2 …). Eine Fassung geht erst
-- auf die Testfassung (Netlify, eigene feste Adresse je Fassung), wird dort
-- von einem Menschen geprüft und erst dann live geschaltet. Zurückrollen =
-- eine frühere Fassung erneut veröffentlichen — mit Sicherung vorher, wie
-- jede Veröffentlichung.
--
-- Nur hinzufügen. Rückweg: Tabelle und die zwei Spalten entfernen.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS projekt_versionen (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id        INT UNSIGNED NOT NULL,
  nummer            SMALLINT UNSIGNED NOT NULL,
  file_id           INT UNSIGNED NOT NULL,              -- files.id, rolle 'paket'
  quelle            VARCHAR(20)  NOT NULL DEFAULT 'hand', -- werkstatt | hand | bestand
  notiz             VARCHAR(500) NULL,
  netlify_deploy_id VARCHAR(64)  NULL,
  staging_url       VARCHAR(255) NULL,                  -- feste Adresse dieser Fassung
  staging_am        DATETIME     NULL,
  staging_von       VARCHAR(120) NULL,
  geprueft_am       DATETIME     NULL,
  geprueft_von      VARCHAR(120) NULL,
  live_am           DATETIME     NULL,                  -- zuletzt veröffentlicht
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_version_nummer (project_id, nummer),
  UNIQUE KEY uq_version_datei (file_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE projects
  ADD COLUMN IF NOT EXISTS netlify_site_id  VARCHAR(64)  NULL,
  ADD COLUMN IF NOT EXISTS live_version_id  INT UNSIGNED NULL;
