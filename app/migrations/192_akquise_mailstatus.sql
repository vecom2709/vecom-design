-- ===========================================================================
-- 192_akquise_mailstatus.sql — Kommunikationsstatus statt starrer E-Mail-Sperre
-- (06.10.2026, Uwe: „Die E-Mail-Adresse soll grundsätzlich sichtbar, kopierbar
-- und für Entwürfe nutzbar sein. Keine pauschale Sperre mehr. Stattdessen
-- erhält jeder Betrieb einen Kommunikationsstatus.“)
--
-- Fünf Dinge, fünf Felder — nie ein einziger Schalter:
--   gefunden      email_found (abgeleitet aus email)
--   bestätigt     email_verified
--   Status        email_contact_status  keine_freigabe | pruefen | freigegeben | nicht_kontaktieren
--   Versand       email_send_allowed    (manueller Einzelversand aus der Verwaltung)
--   Werbung       email_marketing_consent
-- dazu der dokumentierte Versandgrund (email_legal_basis*), die Herkunft der
-- Adresse (email_source) und „Nicht kontaktieren“ (email_do_not_contact*).
-- Jede Dokumentation steht zusätzlich als eigene Zeile in akq_mail_grundlagen.
-- Das System dokumentiert nur, was eingetragen wurde; es bewertet nichts.
-- ===========================================================================

ALTER TABLE akq_firmen
  ADD COLUMN IF NOT EXISTS email_found              TINYINT(1) AS (IF(COALESCE(email, '') <> '', 1, 0)) VIRTUAL,
  ADD COLUMN IF NOT EXISTS email_verified           TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS email_source             VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS email_contact_status     VARCHAR(20)  NOT NULL DEFAULT 'keine_freigabe',
  ADD COLUMN IF NOT EXISTS email_send_allowed       TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS email_marketing_consent  TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS email_review_requested   TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS email_legal_basis        VARCHAR(30)  NULL,
  ADD COLUMN IF NOT EXISTS email_legal_basis_date   DATE         NULL,
  ADD COLUMN IF NOT EXISTS email_legal_basis_source VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS email_legal_basis_note   TEXT         NULL,
  ADD COLUMN IF NOT EXISTS email_legal_basis_by     VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS email_do_not_contact     TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS email_dnc_reason         VARCHAR(30)  NULL,
  ADD COLUMN IF NOT EXISTS email_dnc_note           VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS email_dnc_at             DATETIME     NULL;

CREATE TABLE IF NOT EXISTS akq_mail_grundlagen (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  firma_id      INT UNSIGNED NOT NULL,
  art           VARCHAR(20)  NOT NULL DEFAULT 'grund',   -- grund | pruefung | zurueck | dnc | dnc_aufgehoben | geprueft
  grund         VARCHAR(30)  NULL,
  datum         DATE         NULL,
  quelle        VARCHAR(255) NULL,
  notiz         TEXT         NULL,
  bearbeiter    VARCHAR(120) NOT NULL,
  user_id       INT UNSIGNED NULL,
  freigabe      TINYINT(1)   NOT NULL DEFAULT 0,
  werbung       TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_akq_mg_firma (firma_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bestand übernehmen: Herkunft unbekannt, vorhandene Einwilligungen und Sperren behalten ihre Wirkung.
UPDATE akq_firmen SET email_source = 'Vor dem 06.10.2026 gefunden — Quelle nicht erfasst'
 WHERE COALESCE(email, '') <> '' AND email_source IS NULL;
UPDATE akq_firmen SET email_legal_basis = 'einwilligung', email_legal_basis_source = einwilligung,
       email_legal_basis_by = 'Übernahme aus der Einwilligung', email_send_allowed = 1, email_marketing_consent = 1
 WHERE COALESCE(einwilligung, '') <> '' AND FIND_IN_SET('email', COALESCE(NULLIF(einwilligung_kanaele, ''), 'email')) > 0
   AND email_legal_basis IS NULL;
UPDATE akq_firmen SET email_legal_basis = 'bestandskunde', email_legal_basis_by = 'Übernahme: als Kunde markiert', email_review_requested = 1
 WHERE bestandskunde = 1 AND email_legal_basis IS NULL;
UPDATE akq_firmen SET email_do_not_contact = 1, email_dnc_reason = 'sperre', email_dnc_note = 'Übernahme: Betrieb war gesperrt oder hatte abgelehnt'
 WHERE (gesperrt = 1 OR kontakt_status IN ('abgelehnt', 'gesperrt')) AND email_do_not_contact = 0;
UPDATE akq_firmen SET email_contact_status = CASE
    WHEN email_do_not_contact = 1 THEN 'nicht_kontaktieren'
    WHEN email_send_allowed = 1 THEN 'freigegeben'
    WHEN email_review_requested = 1 OR email_legal_basis IS NOT NULL THEN 'pruefen'
    ELSE 'keine_freigabe' END;
