-- ===========================================================================
-- 053_leistungen_getrennt.sql — Domain, Hosting und E-Mail sind drei
-- Entscheidungen, und jede Zustimmung steht mit ihrem Wortlaut da. (25.09.2026)
--
-- hosting_auftraege kannte nur einen Fall: neue Domain + Hosting + Postfach.
--   domain_aktion  neu | transfer | behalten | offen
--                  (transfer nur nach ausdruecklichem Ja, nie vorgewaehlt)
--   mail           vecom | bisher | keine | offen
--                  (ein Postfach entsteht nur bei 'vecom')
-- Bestehende Zeilen behalten ihre Bedeutung: neu + vecom, genau das, was
-- sie bisher waren.
--
-- zustimmungen: Wer hat wann welchem Wortlaut zugestimmt. Keine IP, kein
-- Browser -- Datenminimierung; Zeitpunkt, Fassung und Text genuegen als
-- Nachweis, dass der Kunde genau diesen Satz vor dem Knopf gesehen hat.
-- Wiederholbar: IF NOT EXISTS.
-- ===========================================================================

ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS domain_aktion VARCHAR(16) NOT NULL DEFAULT 'neu' AFTER domain;
ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS mail VARCHAR(16) NOT NULL DEFAULT 'vecom' AFTER domain_aktion;

CREATE TABLE IF NOT EXISTS zustimmungen (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id  INT UNSIGNED NOT NULL,
  project_id   INT UNSIGNED NULL,
  art          VARCHAR(32)  NOT NULL,
  bezug_id     INT UNSIGNED NULL,
  fassung      VARCHAR(20)  NOT NULL,
  sprache      CHAR(2)      NOT NULL DEFAULT 'it',
  text         TEXT         NOT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_zustimmung_kunde (customer_id),
  KEY ix_zustimmung_bezug (art, bezug_id),
  CONSTRAINT fk_zustimmung_kunde FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
