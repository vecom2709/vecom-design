-- Not-Aus hält auch die Wege an, die nicht im Cron laufen (AI Office Stufe 0, 06.10.2026).
-- Was eine Automation während des Not-Aus an Kunden oder Partner schicken wollte,
-- wartet hier, bis Uwe es sendet oder verwirft. Nichts geht verloren, nichts geht ungefragt raus.
CREATE TABLE IF NOT EXISTS ausgang_gehalten (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kanal           VARCHAR(16)  NOT NULL DEFAULT 'mail',
  anlass          VARCHAR(64)  NOT NULL,
  empfaenger      VARCHAR(190) NOT NULL,
  betreff         VARCHAR(255) NOT NULL,
  nutzlast        LONGTEXT     NOT NULL,
  fingerabdruck   CHAR(64)     NOT NULL,
  herkunft        VARCHAR(40)  NULL,
  customer_id     INT UNSIGNED NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  entschieden_am  DATETIME     NULL,
  entscheidung    VARCHAR(16)  NULL,
  entschieden_von VARCHAR(80)  NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ausgang_fingerabdruck (fingerabdruck),
  KEY ix_ausgang_offen (entschieden_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
