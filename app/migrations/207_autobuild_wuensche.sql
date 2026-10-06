-- ===========================================================================
-- 207_autobuild_wuensche.sql — AutoBuild Phase 8 (06.10.2026, Uwe: „fahre
-- fort“): Kundenwünsche zur Vorschau, eingeordnet gegen das Angebot.
--
-- Jeder Änderungswunsch wird ein Eintrag. Ein Mensch ordnet ihn ein:
-- im Umfang (wird umgesetzt), Zusatz (erst Angebot, dann bauen), abgelehnt.
-- Claude darf nur VORSCHLAGEN (vorschlag*), nie selbst einordnen — an der
-- Einordnung hängt Geld.
--
-- Nur hinzufügen. Rückweg: Tabelle entfernen.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS projekt_wuensche (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id        INT UNSIGNED NOT NULL,
  customer_id       INT UNSIGNED NULL,
  text              VARCHAR(2000) NOT NULL,
  quelle            VARCHAR(12)  NOT NULL DEFAULT 'kunde',     -- kunde | vecom
  status            VARCHAR(20)  NOT NULL DEFAULT 'neu',       -- neu | im_umfang | zusatz | zusatz_angenommen | in_arbeit | umgesetzt | abgelehnt
  grund             VARCHAR(500) NULL,                         -- Begründung der Einordnung (sieht der Kunde bei Zusatz/abgelehnt)
  eingeordnet_von   VARCHAR(120) NULL,
  eingeordnet_am    DATETIME     NULL,
  vorschlag         VARCHAR(12)  NULL,                         -- Claude: im_umfang | zusatz | unklar
  vorschlag_grund   VARCHAR(500) NULL,
  vorschlag_aufwand VARCHAR(120) NULL,
  bau_auftrag_id    INT UNSIGNED NULL,
  version_id        INT UNSIGNED NULL,                         -- umgesetzt in dieser Fassung
  umgesetzt_am      DATETIME     NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_wunsch_projekt (project_id, status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
