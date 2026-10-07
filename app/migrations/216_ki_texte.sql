-- 216 — Individuelle, intelligente Texte (07.10.2026, Uwe: „jede email oder whatsapp soll individuell
-- angepasst und intelligent sein“ — Ja zu allen 13 Vorschlägen).
--
-- mails.ki_teil        : der Absatz, den die KI in diese Mail geschrieben hat (Kundenakte zeigt ihn markiert)
-- customers.ki_aus     : 1 = für diesen Kunden keine KI-Sätze, nur die feste Vorlage
-- akq_folgen.ki_befunde: welche Befunde (IDs) die Folge-Mails schon genannt haben — keiner zweimal
-- ki_verbrauch         : je Monat Anfragen, Tokens und Kosten (Hundertstel-Cent) — für den Deckel
ALTER TABLE mails ADD COLUMN IF NOT EXISTS ki_teil TEXT NULL;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS ki_aus TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE akq_folgen ADD COLUMN IF NOT EXISTS ki_befunde VARCHAR(255) NULL;

CREATE TABLE IF NOT EXISTS ki_verbrauch (
  monat          CHAR(7)      NOT NULL PRIMARY KEY,
  anfragen       INT UNSIGNED NOT NULL DEFAULT 0,
  angenommen     INT UNSIGNED NOT NULL DEFAULT 0,
  abgelehnt      INT UNSIGNED NOT NULL DEFAULT 0,
  fehler         INT UNSIGNED NOT NULL DEFAULT 0,
  tokens_ein     INT UNSIGNED NOT NULL DEFAULT 0,
  tokens_aus     INT UNSIGNED NOT NULL DEFAULT 0,
  kosten_hcent   INT UNSIGNED NOT NULL DEFAULT 0,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
