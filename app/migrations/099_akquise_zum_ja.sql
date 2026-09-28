-- ============================================================================
-- 099 — Wege zum Ja (28.09.2026, Uwe: Ja zu Z1–Z6).
--
-- akq_wa_gespraeche: der WhatsApp-Assistent (Z1). Ein Betrieb schreibt zuerst,
--   der Assistent fragt nach der Website, schickt die Ampel, holt per Knopf
--   die Einwilligung und am Ende die E-Mail für den persönlichen Bereich.
-- akq_beitraege: vorbereitete Beiträge für Facebook und Instagram (Z4).
-- akq_meta_leads: Meldungen aus dem Werbeformular (Z5), jede genau einmal.
-- ============================================================================

CREATE TABLE IF NOT EXISTS akq_wa_gespraeche (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nummer       VARCHAR(20)  NOT NULL,
  sprache      VARCHAR(2)   NOT NULL DEFAULT 'it',
  stand        VARCHAR(12)  NOT NULL DEFAULT 'neu',     -- neu|url|ja|email|fertig|beendet
  url          VARCHAR(255) NULL,
  ampel        TEXT         NULL,
  firma_id     INT UNSIGNED NULL,
  letzte_am    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_akq_wa_gespraech (nummer)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS akq_beitraege (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code         VARCHAR(60)  NOT NULL,
  sprache      VARCHAR(2)   NOT NULL DEFAULT 'it',
  text         TEXT         NOT NULL,
  titel        VARCHAR(160) NOT NULL,
  token        VARCHAR(40)  NOT NULL,
  status       VARCHAR(12)  NOT NULL DEFAULT 'entwurf', -- entwurf|gepostet|fehler|verworfen
  fb_id        VARCHAR(80)  NULL,
  ig_id        VARCHAR(80)  NULL,
  grund        VARCHAR(255) NULL,
  gepostet_am  DATETIME     NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_akq_beitrag_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS akq_meta_leads (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id      VARCHAR(40)  NOT NULL,
  status       VARCHAR(16)  NOT NULL DEFAULT 'neu',     -- neu|ok|ohne_haken|fehler|adresse|email|...
  grund        VARCHAR(255) NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_akq_meta_lead (lead_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
