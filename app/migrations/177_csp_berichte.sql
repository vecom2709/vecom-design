-- 177: Meldungen der Content-Security-Policy (Etappe 0b, 05.10.2026) — zusammengefasst, ohne Abfrage und ohne IP.
CREATE TABLE IF NOT EXISTS csp_berichte (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bereich    VARCHAR(12)  NOT NULL,
  direktive  VARCHAR(40)  NOT NULL,
  quelle     VARCHAR(120) NOT NULL,
  seite      VARCHAR(120) NOT NULL,
  anzahl     INT UNSIGNED NOT NULL DEFAULT 1,
  zuerst     DATETIME     NOT NULL,
  zuletzt    DATETIME     NOT NULL,
  UNIQUE KEY uq_csp_bericht (bereich, direktive, quelle, seite)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
