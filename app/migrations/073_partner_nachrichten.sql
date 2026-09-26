-- ============================================================================
-- 073 — Partner: Nachrichten, Erinnerung an den Auszahlungsweg, Handy-App mit
-- Hinweisen (26.09.2026, Uwe: Ja zu allen dreien).
-- Wiederholbar: CREATE ... IF NOT EXISTS; doppelte Spalten scheitern mit 1060,
-- das Einrichtung::migrieren() ueberspringt.
-- ============================================================================

CREATE TABLE IF NOT EXISTS partner_nachrichten (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  partner_id   INT UNSIGNED NOT NULL,
  von          VARCHAR(8)   NOT NULL,             -- partner | vecom
  text         TEXT         NOT NULL,
  user_id      INT UNSIGNED NULL,                 -- wer bei Vecom geantwortet hat
  gelesen_am   DATETIME     NULL,                 -- von der Gegenseite gelesen
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_pn_partner (partner_id, id),
  KEY ix_pn_offen (von, gelesen_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Web-Push: eine Zeile je Geraet. Endpoint, Schluessel und Geheimnis kommen
-- vom Browser des Partners; ohne sie laesst sich nichts zustellen.
CREATE TABLE IF NOT EXISTS partner_push (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  partner_id   INT UNSIGNED NOT NULL,
  endpoint     VARCHAR(700) NOT NULL,
  endpoint_hash CHAR(64)    NOT NULL,
  p256dh       VARCHAR(120) NOT NULL,
  auth         VARCHAR(40)  NOT NULL,
  fehler       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  zuletzt_am   DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pp_endpoint (endpoint_hash),
  KEY ix_ppush_partner (partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE partner ADD COLUMN weg_erinnert_am DATETIME NULL;
