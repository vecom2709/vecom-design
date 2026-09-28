-- ============================================================================
-- 100 — Website-Tipp der Woche (28.09.2026, Uwe: Ja zu D5).
--
-- Wer auf der Analyse-Seite oder der Startseite den Tipp abonniert und den
-- Link in der Bestätigungsmail anklickt, bekommt einmal pro Woche einen
-- kurzen Tipp für seine Website, mit Link zur kostenlosen Analyse und zu
-- seinem persönlichen Bereich. Abbestellen mit einem Klick.
-- ============================================================================

CREATE TABLE IF NOT EXISTS akq_tipp_abos (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email          VARCHAR(190) NOT NULL,
  sprache        CHAR(2)      NOT NULL DEFAULT 'it',
  status         VARCHAR(12)  NOT NULL DEFAULT 'angefragt', -- angefragt|aktiv|abgemeldet|abgelaufen
  token          CHAR(40)     NOT NULL,                     -- Abbestellen
  doi_token      CHAR(40)     NULL,                         -- Bestätigungslink
  wortlaut       TEXT         NULL,
  quelle         VARCHAR(20)  NOT NULL DEFAULT 'seite',     -- analisi|start
  ip_hash        CHAR(64)     NULL,                         -- nie die Adresse selbst
  angefragt_am   DATETIME     NULL,
  bestaetigt_am  DATETIME     NULL,
  abgemeldet_am  DATETIME     NULL,
  letzte_nr      INT UNSIGNED NOT NULL DEFAULT 0,
  letzter_am     DATETIME     NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_akq_tipp_email (email),
  UNIQUE KEY uq_akq_tipp_token (token),
  UNIQUE KEY uq_akq_tipp_doi (doi_token),
  KEY ix_akq_tipp_status (status, letzter_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
