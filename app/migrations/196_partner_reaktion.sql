-- ===========================================================================
-- 196_partner_reaktion.sql — Akquise-CRM Modul F (06.10.2026).
-- Uwe: Reservierung „Neue 30 Tage, alte bleiben“ (einmal verlängerbar),
-- Warnungen „24h/48h Partner, 72h Sie“, Provision bei Übernahme „Sie
-- entscheiden je Fall“, Auswertung „Funnel + Reaktionszeit“.
--
-- partner_warnungen merkt sich nur, welcher Hinweis zu welchem Signal schon
-- verschickt wurde — die Signale selbst rechnet AkquisePartner aus Antworten
-- und Website-Checks, damit es keine zweite Wahrheit gibt.
-- partner_entscheide hält fest, was Vecom mit einer Reservierung getan hat
-- und was mit der Provision gilt.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS partner_warnungen (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id   INT UNSIGNED NOT NULL,
  firma_id     INT UNSIGNED NOT NULL,
  art          VARCHAR(12)  NOT NULL,                  -- antwort oder check
  signal_am    DATETIME     NOT NULL,
  push_am      DATETIME     NULL,                      -- Hinweis an den Partner (ab 48 h)
  uwe_am       DATETIME     NULL,                      -- Meldung an Uwe (ab 72 h)
  erinnert_am  DATETIME     NULL,                      -- von Hand erinnert
  UNIQUE KEY uq_pw (partner_id, firma_id, art, signal_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_entscheide (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  firma_id       INT UNSIGNED NOT NULL,
  partner_id     INT UNSIGNED NOT NULL,
  aktion         VARCHAR(16)  NOT NULL,                -- uebernommen, neu_zugewiesen, geloest
  provision      VARCHAR(10)  NOT NULL,                -- bleibt oder entfaellt
  neu_partner_id INT UNSIGNED NULL,
  grund          VARCHAR(255) NULL,
  von            VARCHAR(80)  NOT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_pe_firma (firma_id),
  KEY ix_pe_partner (partner_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
