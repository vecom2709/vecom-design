-- ============================================================================
-- 085 — Öffentlicher Website-Check auf vecom-design.it (27.09.2026, Auftrag
-- „Lead-Magnet + Double-Opt-in“).
--
-- Eine Zeile je Check. Name, E-Mail und Telefon stehen nur hier, nicht in
-- akq_firmen: Die Firma bekommt ihre E-Mail erst mit einer bestätigten
-- Einwilligung (AkquiseEinwilligung). Nach der Frist (AkquiseCheck::FRIST_TAGE)
-- leert der Cron die persönlichen Felder, wenn weder Einwilligung noch Auftrag
-- daraus wurde -- das Ergebnis der Prüfung bleibt, es ist nichts Persönliches.
-- Wiederholbar: IF NOT EXISTS.
-- ============================================================================

CREATE TABLE IF NOT EXISTS akq_checks (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  token               CHAR(32)     NOT NULL,              -- Ergebnis-Adresse website-check.php?t=…
  firma_id            INT UNSIGNED NULL,
  name                VARCHAR(120) NULL,
  firma               VARCHAR(190) NOT NULL,
  url                 VARCHAR(500) NOT NULL,
  host                VARCHAR(190) NOT NULL,
  email               VARCHAR(190) NULL,
  telefon             VARCHAR(40)  NULL,
  sprache             CHAR(2)      NOT NULL DEFAULT 'it',
  land                CHAR(2)      NOT NULL DEFAULT 'IT',
  ausfuehrlich        TINYINT(1)   NOT NULL DEFAULT 0,    -- „Bitte melden Sie sich mit der ausführlichen Analyse“ (Anfrage, keine Werbung)
  marketing           TINYINT(1)   NOT NULL DEFAULT 0,    -- Häkchen gesetzt -- erst der Double-Opt-in macht daraus eine Einwilligung
  einwilligung_id     INT UNSIGNED NULL,
  einwilligung_stand  VARCHAR(12)  NULL,                  -- ok | zuviel | gesperrt | email
  ergebnis            TEXT         NOT NULL,              -- JSON aus PartnerCheck::pruefen
  schlecht            TINYINT UNSIGNED NOT NULL DEFAULT 0,
  aufrufe             INT UNSIGNED NOT NULL DEFAULT 0,
  ip_hash             CHAR(64)     NULL,                  -- nie die Adresse selbst
  status              VARCHAR(12)  NOT NULL DEFAULT 'neu', -- neu | erledigt | anonymisiert
  erledigt_am         DATETIME     NULL,
  created_at          DATETIME     NOT NULL,
  UNIQUE KEY uq_akq_check_token (token),
  KEY ix_akq_check_firma (firma_id),
  KEY ix_akq_check_zeit (created_at),
  KEY ix_akq_check_ip (ip_hash, created_at),
  KEY ix_akq_check_host (host, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
