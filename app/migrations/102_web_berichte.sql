-- ============================================================================
-- 102 — Ausführliche Website-Berichte (28.09.2026, Uwe: Ja zu A1–A10).
--
-- web_berichte: jeder Kurz-Check mit 12 Punkten bekommt eine feste Adresse
--   (bericht.php?b=…), damit man ihn wieder öffnen, als PDF laden und
--   weitergeben kann. Kein Name, keine E-Mail -- nur Adresse und Messwerte.
--   Nach 180 Tagen ohne Betrieb wird er gelöscht (WebBericht::aufraeumen).
-- akq_audits.marken: Stellen auf dem Handyfoto, an denen etwas nicht passt
--   (vom Worker auf deinem PC gemessen), für die Markierungen (A2).
-- ============================================================================

CREATE TABLE IF NOT EXISTS web_berichte (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token       CHAR(32)     NOT NULL,
  firma_id    INT UNSIGNED NULL,
  host        VARCHAR(190) NOT NULL,
  url         VARCHAR(500) NOT NULL,
  daten       MEDIUMTEXT   NOT NULL,
  note        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  quelle      VARCHAR(20)  NOT NULL DEFAULT 'analisi',
  aufrufe     INT UNSIGNED NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_web_bericht_token (token),
  KEY ix_web_bericht_firma (firma_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE akq_audits ADD COLUMN marken TEXT NULL;

ALTER TABLE akq_checks ADD COLUMN bericht CHAR(32) NULL;
