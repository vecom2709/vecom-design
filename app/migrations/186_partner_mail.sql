-- ===========================================================================
-- 186_partner_mail.sql — E-Mail-Center des Partners (Phase 7a, 05.10.2026).
-- Uwe: „Weiterleitung an private Adresse“, „Email Adressen bestehen schon …
-- nur bei denen einbauen, die eine @vecom Email haben“, „20 am Tag, 5 pro
-- Stunde“, „sofort senden“.
--
-- partner.vecom_adresse: die schon bestehende @vecom-design.it-Adresse des
-- Partners. Setzt NUR die Verwaltung (Uwe), nie der Partner selbst. Ohne sie
-- gibt es für den Partner kein E-Mail-Center — dann bleibt der mailto-Link.
-- partner_mails: jede Mail aus dem Dashboard mit Inhalt (Uwe sieht, was in
-- seinem Namen hinausgeht), Stand und Abmeldeschlüssel. Das Postfach-Passwort
-- steht nirgends: Gesendet wird über Brevo, Antworten gehen an die Adresse.
-- ===========================================================================

ALTER TABLE partner ADD COLUMN IF NOT EXISTS vecom_adresse VARCHAR(120) NULL;
ALTER TABLE partner ADD UNIQUE KEY IF NOT EXISTS uq_partner_vecom_adresse (vecom_adresse);

CREATE TABLE IF NOT EXISTS partner_mails (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id     INT UNSIGNED NOT NULL,
  lead_id        INT UNSIGNED NULL,
  absender       VARCHAR(120) NOT NULL,
  an             VARCHAR(190) NOT NULL,
  betreff        VARCHAR(160) NOT NULL,
  text           MEDIUMTEXT   NOT NULL,
  sprache        CHAR(2)      NOT NULL DEFAULT 'it',
  status         ENUM('wird_gesendet','gesendet','fehler') NOT NULL DEFAULT 'wird_gesendet',
  fehler         VARCHAR(300) NULL,
  abmelde_token  CHAR(40)     NOT NULL,
  abgemeldet_am  DATETIME     NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pmail_token (abmelde_token),
  KEY ix_pmail_partner (partner_id, created_at),
  KEY ix_pmail_lead (lead_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
