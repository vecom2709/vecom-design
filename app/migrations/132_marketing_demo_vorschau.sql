-- ===========================================================================
-- 132_marketing_demo_vorschau.sql — kostenlose Demo-Vorschau der neuen
-- Startseite (Marketing-Studio 10, 01.10.2026, Uwe: „ja“ zu S1).
--
-- Ein Interessent (aus dem Website-Check, mit persönlichem Bereich) fordert
-- sie selbst an — das ist seine ausdrückliche Bitte (Wortlaut in
-- zustimmungen, Art demo). Claude baut auf Uwes PC eine Startseite aus den
-- öffentlichen Angaben seiner bisherigen Website; Uwe sieht sie an und gibt
-- frei. Erst dann geht der Link per Mail an den Interessenten. Der Link gilt
-- 30 Tage, ohne Skripte, nicht in Suchmaschinen.
--
-- status: wartet → fertig → freigegeben | verworfen | fehler
--
-- Nur hinzufügen. Rückweg: die Tabelle entfernen.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS mk_demos (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id      INT UNSIGNED NOT NULL,
  akq_firma_id     INT UNSIGNED NULL,
  auftrag_id       INT UNSIGNED NULL,
  token            CHAR(32)     NOT NULL,
  status           VARCHAR(12)  NOT NULL DEFAULT 'wartet',
  sprache          CHAR(2)      NOT NULL DEFAULT 'it',
  url              VARCHAR(300) NULL,
  hinweis          VARCHAR(600) NULL,
  html             MEDIUMTEXT   NULL,
  zusammenfassung  TEXT         NULL,
  quellen          TEXT         NULL,
  fehler           VARCHAR(900) NULL,
  aufrufe          INT UNSIGNED NOT NULL DEFAULT 0,
  freigegeben_am   DATETIME     NULL,
  gueltig_bis      DATE         NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mk_demo_token (token),
  KEY ix_mk_demo_kunde (customer_id),
  KEY ix_mk_demo_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
