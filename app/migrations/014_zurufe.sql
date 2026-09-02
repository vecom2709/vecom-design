-- ===========================================================================
-- 014_zurufe.sql — Die Warteschlange fuer den Zuruf aufs Handy.
--
-- Warum eine Tabelle und nicht einfach ein HTTP-Aufruf an Ort und Stelle:
-- Der Zuruf geht an einen fremden Dienst. Antwortet der langsam, wartet sonst
-- der Besucher des Kontaktformulars mit — beim Durchtesten waren das fuenf
-- Sekunden. Auf FastCGI liesse sich die Antwort vorher abschliessen, aber
-- darauf soll sich nichts verlassen muessen.
--
-- Also: Der Zuruf wird hier abgelegt und danach verschickt — sofort, wenn der
-- Server die Antwort schon losgeworden ist, sonst beim naechsten Cronlauf.
-- Verloren geht dabei nichts, auch wenn der Vorgang mittendrin abbricht.
-- ===========================================================================

CREATE TABLE zurufe (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  anlass       VARCHAR(64)  NOT NULL,
  text         VARCHAR(900) NOT NULL,
  status       VARCHAR(16)  NOT NULL DEFAULT 'offen',
  versuche     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  fehler       VARCHAR(255) NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  gesendet_am  DATETIME     NULL,
  KEY ix_zurufe_offen (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
