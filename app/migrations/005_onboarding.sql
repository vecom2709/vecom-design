-- Kunden-Fragebogen mit eigenem Zugangslink und automatischen E-Mails.
--
-- Der Kunde braucht kein Passwort: Er bekommt nach der Zahlung einen langen,
-- zufaelligen Link auf genau seinen Fragebogen. Das ist der Weg mit den
-- wenigsten Huerden — und der Link gilt nur fuer diesen einen Fragebogen.

ALTER TABLE questionnaires
  ADD COLUMN token         VARCHAR(64) NULL AFTER customer_id,
  ADD COLUMN eingeladen_am DATETIME    NULL AFTER submitted_at,
  ADD COLUMN erinnert_am   DATETIME    NULL AFTER eingeladen_am,
  ADD UNIQUE KEY uq_quest_token (token);

-- In welcher Sprache der Kunde angesprochen wird.
ALTER TABLE customers
  ADD COLUMN sprache CHAR(2) NOT NULL DEFAULT 'it' AFTER country;

-- Jede verschickte E-Mail wird festgehalten — damit nachvollziehbar ist, was
-- der Kunde bekommen hat, und damit nichts zweimal rausgeht.
CREATE TABLE mails (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  anlass      VARCHAR(48)  NOT NULL,
  empfaenger  VARCHAR(190) NOT NULL,
  betreff     VARCHAR(255) NOT NULL,
  customer_id INT UNSIGNED NULL,
  project_id  INT UNSIGNED NULL,
  order_id    INT UNSIGNED NULL,
  status      VARCHAR(16)  NOT NULL DEFAULT 'gesendet',
  fehler      VARCHAR(500) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_mails_anlass (anlass, created_at),
  KEY ix_mails_projekt (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
