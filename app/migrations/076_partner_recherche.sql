-- ============================================================================
-- 076 — Partner: Firmen-Finder, Website-Schnellcheck, Wochen-Impuls
-- (26.09.2026, Uwe: Ja zu allen vier Recherche-Vorschlägen).
-- Wiederholbar: CREATE ... IF NOT EXISTS; doppelte Spalten scheitern mit 1060.
-- ============================================================================

-- Eine Firma gehört höchstens EINEM Partner zur Zeit -- deshalb firma_id als
-- Schlüssel. Abgelaufene Reservierungen werden überschrieben, nicht gestapelt.
-- Solange eine gilt, spricht auch Vecoms eigene Akquise die Firma nicht an
-- (AkquiseGate::pruefen): Zwei Anfragen an denselben Betrieb aus demselben
-- Haus sind die schnellste Art, beide zu verlieren.
CREATE TABLE IF NOT EXISTS partner_reservierungen (
  firma_id     INT UNSIGNED NOT NULL,
  partner_id   INT UNSIGNED NOT NULL,
  bis          DATE         NOT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (firma_id),
  KEY ix_pr_partner (partner_id, bis)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wie oft ein Partner am Tag sucht oder prüft: gegen das Abgrasen der
-- Firmenliste und gegen den Schnellcheck als Gratis-Crawler.
CREATE TABLE IF NOT EXISTS partner_zaehler (
  partner_id   INT UNSIGNED NOT NULL,
  art          VARCHAR(12)  NOT NULL,              -- suche | check
  tag          DATE         NOT NULL,
  anzahl       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (partner_id, art, tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Der Schnellcheck als Bericht zum Weiterschicken (check.php?t=…).
CREATE TABLE IF NOT EXISTS partner_checks (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  partner_id   INT UNSIGNED NOT NULL,
  token        CHAR(32)     NOT NULL,
  url          VARCHAR(500) NOT NULL,
  host         VARCHAR(190) NOT NULL,
  ergebnis     MEDIUMTEXT   NOT NULL,              -- JSON: punkte, ms, geprüft
  aufrufe      INT UNSIGNED NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pc_token (token),
  KEY ix_pc_partner (partner_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE partner ADD COLUMN impuls_am DATETIME NULL;
