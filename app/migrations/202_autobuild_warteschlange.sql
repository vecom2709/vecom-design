-- ===========================================================================
-- 202_autobuild_warteschlange.sql — AutoBuild Phase 5 (06.10.2026, Uwe: „ja“
-- zur Bau-Warteschlange).
--
-- Knopf an der Projektkarte „Bauen“ → Auftrag „wartet“. Der PC fragt alle
-- fünf Minuten nach (steuern → bau_wartet), holt ihn ab und lässt Claude
-- Code über Uwes Abo arbeiten. Der Server ruft weiter keine KI auf.
--
-- Zuerst nur zwei Arten, beide ändern an keiner Website etwas:
--   analyse        Machbarkeit, Risiken, Aufwand, offene Fragen (intern)
--   pflichtenheft  aus Briefing, Angebot und Fragebogen: Ziel, Seiten,
--                  Funktionen, Abnahmekriterien — Grundlage für den Bau
-- Bauen/Ändern/Testen kommen später dazu und brauchen dann die Bausperre.
--
-- Nur hinzufügen. Rückweg: Tabelle und die vier Spalten entfernen.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS bau_auftraege (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id    INT UNSIGNED NOT NULL,
  art           VARCHAR(20)  NOT NULL,                       -- analyse | pflichtenheft
  status        VARCHAR(12)  NOT NULL DEFAULT 'wartet',      -- wartet | laeuft | fertig | fehler | abgebrochen
  hinweis       VARCHAR(500) NULL,                           -- Zusatzwunsch beim Anstoßen
  ergebnis      MEDIUMTEXT   NULL,                           -- Markdown von Claude
  fehler        VARCHAR(1000) NULL,
  von           VARCHAR(120) NOT NULL DEFAULT '',
  gestartet_am  DATETIME     NULL,
  fertig_am     DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_bau_auftrag_status (status, id),
  KEY ix_bau_auftrag_projekt (project_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE projects
  ADD COLUMN IF NOT EXISTS analyse          MEDIUMTEXT NULL,
  ADD COLUMN IF NOT EXISTS analyse_am       DATETIME   NULL,
  ADD COLUMN IF NOT EXISTS pflichtenheft    MEDIUMTEXT NULL,
  ADD COLUMN IF NOT EXISTS pflichtenheft_am DATETIME   NULL;
