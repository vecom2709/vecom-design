-- ===========================================================================
-- 140_partner_vorab.sql — Partner tragen einen vereinbarten Festpreis ein
-- (02.10.2026, Uwe: „Partner können vorab Preise, die mit dem Kunden geklärt
-- waren, eingeben und als Link schicken, wo sein persönliches Dashboard ist,
-- ohne Fragebogen … dann folgt es ganz normal der Kette“).
--
-- Eine Zeile = ein Link, den der Partner selbst verschickt. Der Kunde trägt
-- nur Name, E-Mail und Impressum-Angaben ein; daraus entstehen Kunde,
-- Partner-Zuordnung und ein Festpreis-Angebot als Entwurf. Uwe gibt es frei.
--
-- bezeichnung: was der Partner zur Wiedererkennung eintippt (z. B. „Bar Rossi“).
--   Der Partner sieht weiterhin keine Kundendaten — nur seine eigene Notiz.
-- status: offen | eingeloest | zurueckgezogen | (einloesen = in Arbeit)
-- Nur hinzufügen. Rückweg: Tabelle entfernen.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS partner_vorab (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id     INT UNSIGNED  NOT NULL,
  token          CHAR(40)      NOT NULL,
  bezeichnung    VARCHAR(120)  NOT NULL DEFAULT '',
  preis_cents    INT UNSIGNED  NOT NULL,
  leistungen     VARCHAR(600)  NOT NULL DEFAULT '',
  sprache        CHAR(2)       NOT NULL DEFAULT 'it',
  status         VARCHAR(14)   NOT NULL DEFAULT 'offen',
  customer_id    INT UNSIGNED  NULL,
  angebot_id     INT UNSIGNED  NULL,
  created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  eingeloest_at  DATETIME      NULL,
  UNIQUE KEY uq_pv_token (token),
  KEY ix_pv_partner (partner_id, status),
  KEY ix_pv_kunde (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
