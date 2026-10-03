-- ===========================================================================
-- 142_partner_besuche.sql — wer auf der Partnerseite war, und wer sich vom
-- Partner selbst melden lassen will (03.10.2026, Uwe: Ja zu K1, K2, N1, N2).
--
-- partner_kontaktfreigaben: Nur was der Besucher selbst eingetragen UND mit
--   eigenem Häkchen für den Partner freigegeben hat („{Name} darf sich bei
--   mir melden“). Ohne Häkchen sieht der Partner wie bisher keine Daten.
--   Nach 90 Tagen gelöscht (PartnerBesuche::aufraeumen).
-- spur_besuche.heiss_am: wann der Partner zu diesem Besuch den Sofort-Hinweis
--   bekam — höchstens einmal je Besuch.
-- Nur hinzufügen. Rückweg: Tabelle und Spalte entfernen.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS partner_kontaktfreigaben (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id       INT UNSIGNED  NOT NULL,
  besuch_id        INT UNSIGNED  NULL,
  name             VARCHAR(80)   NOT NULL,
  telefon          VARCHAR(24)   NULL,
  email            VARCHAR(190)  NULL,
  wann_von         DATETIME      NULL,
  wann_bis         DATETIME      NULL,
  sprache          CHAR(2)       NOT NULL DEFAULT 'it',
  einwilligung     VARCHAR(400)  NOT NULL,
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  erledigt_am      DATETIME      NULL,
  KEY ix_pkf_partner (partner_id, erledigt_am),
  KEY ix_pkf_besuch (besuch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE spur_besuche ADD COLUMN heiss_am DATETIME NULL;
