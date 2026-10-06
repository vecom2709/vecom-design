-- ===========================================================================
-- 208_autobuild_lieferung.sql — AutoBuild Phase 9 (06.10.2026, Uwe: „ok mach“):
-- Lieferprüfung, Livegang, Übergabe.
--
-- projekt_lieferung: die abgehakte Lieferprüfung — immer für GENAU EINE
-- Fassung. Eine andere Fassung braucht eine neue Prüfung (Zurückrollen auf
-- eine schon einmal live gewesene Fassung ausgenommen).
-- projects.livecheck*: Prüfung der Domain nach dem Livegang.
-- projects.uebergabe*: das Übergabe-Dokument für den Kunden.
--
-- Nur hinzufügen. Rückweg: Tabelle und Spalten entfernen.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS projekt_lieferung (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id  INT UNSIGNED NOT NULL,
  version_id  INT UNSIGNED NOT NULL,
  punkte      TEXT         NOT NULL,            -- JSON: abgehakte Punkte + automatischer Stand zum Zeitpunkt
  notiz       VARCHAR(500) NULL,
  von         VARCHAR(120) NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_lieferung (project_id, version_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE projects
  ADD COLUMN IF NOT EXISTS livecheck          TEXT       NULL,
  ADD COLUMN IF NOT EXISTS livecheck_am       DATETIME   NULL,
  ADD COLUMN IF NOT EXISTS uebergabe          MEDIUMTEXT NULL,
  ADD COLUMN IF NOT EXISTS uebergabe_am       DATETIME   NULL,
  ADD COLUMN IF NOT EXISTS uebergabe_frei_am  DATETIME   NULL;
