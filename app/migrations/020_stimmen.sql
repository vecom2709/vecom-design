-- ===========================================================================
-- 020_stimmen.sql — Was Kunden sagen, direkt von ihnen.
--
-- Auf der Website standen sieben Projekte und kein einziges Wort von einem
-- Kunden. Arbeiten zeigen, WAS jemand kann; ein Satz eines Kunden zeigt, WIE
-- es war, mit ihm zu arbeiten. Bei einem Ein-Mann-Betrieb ueberzeugt das mehr
-- als jede Gestaltung.
--
-- Der Weg soll kurz sein: Der Kunde schreibt zwei Saetze auf seiner eigenen
-- Seite, sie landen hier, und mit einem Klick stehen sie auf der Website.
--
-- WARUM DER KLICK DAZWISCHEN BLEIBT
--
-- Was ungeprueft auf einer Verkaufsseite erscheint, hat dort irgendwann
-- gestanden, was niemand wollte — ein wuetender Satz nach einem schlechten
-- Tag, ein Missverstaendnis, ein Tippfehler im Firmennamen. Ein Klick kostet
-- fuenf Sekunden, das Zurueckholen kostet einen Ruf.
--
-- erlaubnis ist nicht Hoeflichkeit, sondern Voraussetzung: Namen und Firma
-- eines Kunden zu veroeffentlichen braucht seine ausdrueckliche Zustimmung.
-- Ohne Haekchen kein Name.
-- ===========================================================================

CREATE TABLE stimmen (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id      INT UNSIGNED NULL,
  name             VARCHAR(120) NOT NULL,
  firma            VARCHAR(160) NULL,
  ort              VARCHAR(120) NULL,
  text             TEXT         NOT NULL,
  sterne           TINYINT UNSIGNED NULL,
  sprache          CHAR(2)      NOT NULL DEFAULT 'it',
  -- Darf der Name mit dem Zitat erscheinen? Ohne das: gar nicht.
  erlaubnis        TINYINT(1)   NOT NULL DEFAULT 0,
  -- neu | veroeffentlicht | versteckt
  status           VARCHAR(16)  NOT NULL DEFAULT 'neu',
  sort             INT          NOT NULL DEFAULT 0,
  veroeffentlicht_am DATETIME   NULL,
  demo             TINYINT(1)   NOT NULL DEFAULT 0,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_stimmen_status (status),
  KEY ix_stimmen_kunde (customer_id),
  CONSTRAINT fk_stimmen_kunde FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
