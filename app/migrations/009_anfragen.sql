-- ===========================================================================
-- 009_anfragen.sql — Anfragen aus dem Formular landen in der Verwaltung.
--
-- Bisher ging eine Anfrage nur als E-Mail raus. Wer daraus einen Auftrag machen
-- wollte, tippte Name, E-Mail und Paket von Hand ab — obwohl der Kunde sie
-- gerade erst eingegeben hatte. Jetzt entsteht der Kunde sofort, und die
-- Anfrage haengt an ihm.
--
-- Warum eine eigene Tabelle und nicht ein orders-Eintrag mit Status "Anfrage":
-- Eine Anfrage ist kein Auftrag. Stuende sie in orders, verfaelschte sie jede
-- Umsatzzahl und jede Abschlussquote — und die Verwaltung soll ehrlich bleiben.
-- ===========================================================================

CREATE TABLE anfragen (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id  INT UNSIGNED NULL,
  package_id   INT UNSIGNED NULL,
  paket_slug   VARCHAR(60)  NULL,
  paket_name   VARCHAR(120) NULL,
  name         VARCHAR(120) NOT NULL,
  email        VARCHAR(190) NOT NULL,
  telefon      VARCHAR(60)  NULL,
  website      VARCHAR(190) NULL,
  sprache      CHAR(2)      NOT NULL DEFAULT 'it',
  nachricht    MEDIUMTEXT   NULL,
  status       VARCHAR(20)  NOT NULL DEFAULT 'neu',
  order_id     INT UNSIGNED NULL,
  demo         TINYINT(1)   NOT NULL DEFAULT 0,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_anfragen_status (status),
  KEY ix_anfragen_kunde (customer_id),
  KEY ix_anfragen_erstellt (created_at),
  CONSTRAINT fk_anfragen_kunde   FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_anfragen_paket   FOREIGN KEY (package_id)  REFERENCES packages(id)  ON DELETE SET NULL,
  CONSTRAINT fk_anfragen_order   FOREIGN KEY (order_id)    REFERENCES orders(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
