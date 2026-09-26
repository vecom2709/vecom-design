-- ===========================================================================
-- 067_chef_gedaechtnis.sql — Manuelas Chef-Modus: Gedaechtnis und Sperre (26.09.2026)
--
-- chef_gedaechtnis: Was Uwe am Telefon festlegt, bleibt ueber das Gespraech
-- hinaus stehen -- getrennt nach Art, weil "Rossi zahlt erst im Oktober"
-- (Entscheidung) etwas anderes ist als "Rossi schickt das Logo" (wartet auf
-- den Kunden). Erledigtes wird nicht geloescht, sondern bekommt einen Status:
-- Wer spaeter fragt "was hatte ich zu Rossi gesagt?", soll es noch finden.
--
-- bedingung: Worauf ein WAITING_FOR_CUSTOMER wartet (unterlagen | zahlung |
-- fragebogen). Ist es inzwischen eingetroffen, meldet der Chef-Modus einen
-- WIDERSPRUCH statt die alte Notiz vorzulesen -- genau der Fall, in dem ein
-- Gedaechtnis sonst ueberzeugend falsch ist.
--
-- vorhaben: Bei WAITING_FOR_APPROVAL die Stufe-4-Aenderung mit altem Wert,
-- neuem Wert, Objekt und Folgen. Am Telefon wird sie nur vorbereitet; wirksam
-- wird sie erst per Klick in der Verwaltung.
--
-- chef_versuche: jeder Versuch, den Chef-Modus zu oeffnen. Daraus ergibt sich
-- die Sperre -- ohne Sitzung, weil STRATO jeden Werkzeugaufruf einzeln schickt.
-- Wiederholbar: IF NOT EXISTS.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS chef_gedaechtnis (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kategorie    VARCHAR(24)  NOT NULL,
  customer_id  INT UNSIGNED NULL,
  text         VARCHAR(800) NOT NULL,
  bedingung    VARCHAR(20)  NULL,
  vorhaben     TEXT         NULL,
  status       VARCHAR(12)  NOT NULL DEFAULT 'offen',
  quelle       VARCHAR(16)  NOT NULL DEFAULT 'telefon',
  erledigt_am  DATETIME     NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_chefg_status (status, kategorie),
  KEY ix_chefg_kunde (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chef_versuche (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  erfolg      TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_chefv_zeit (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
