-- ============================================================================
-- 091 — Ausbau der Empfehlungsseite (27.09.2026, Uwe: Ja zu 15 Vorschlägen).
--
-- partner_ereignisse: was auf der Seite eines Partners passiert, je Tag --
--   email (Zugang angefordert), rueckruf, wa (WhatsApp-Knopf), check
--   (Website-Check geöffnet), termin, preis (Bedarfskonfigurator). Nur Zahlen,
--   nichts über den Besucher. Grundlage des Trichters im Partnerbereich.
-- partner_vormerkungen: Rückruf, Website-Check und Termin kennen noch keinen
--   Kunden. Sie merken E-Mail oder Telefon mit dem Partner vor; entsteht
--   innerhalb von 90 Tagen ein Kunde mit dieser Adresse oder Nummer, wird er
--   dem Partner zugeordnet (Partner::vormerkungEinloesen). Uwe muss nicht
--   mehr von Hand zuordnen.
-- akq_checks.partner_id / akq_termine.partner_id: über welche Partnerseite
--   der Check oder Termin kam (Anzeige in der Verwaltung).
-- Wiederholbar: CREATE IF NOT EXISTS, ADD COLUMN → 1060 übersprungen.
-- ============================================================================

CREATE TABLE IF NOT EXISTS partner_ereignisse (
  partner_id  INT UNSIGNED NOT NULL,
  art         VARCHAR(16)  NOT NULL,
  tag         DATE         NOT NULL,
  anzahl      INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (partner_id, art, tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_vormerkungen (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  partner_id   INT UNSIGNED NOT NULL,
  email        VARCHAR(190) NULL,
  telefon      VARCHAR(20)  NULL,
  quelle       VARCHAR(8)   NOT NULL DEFAULT 'link',
  art          VARCHAR(12)  NOT NULL,
  kanal        VARCHAR(20)  NULL,
  customer_id  INT UNSIGNED NULL,
  eingeloest_am DATETIME    NULL,
  created_at   DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY ix_pv_email (email),
  KEY ix_pv_telefon (telefon),
  KEY ix_pv_partner (partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE akq_checks ADD COLUMN partner_id INT UNSIGNED NULL;
ALTER TABLE akq_termine ADD COLUMN partner_id INT UNSIGNED NULL;
