-- ============================================================================
-- 079 — Firmen-Finder sucht im Web nach (27.09.2026, Uwe: „Betriebe in der
-- Nähe: keine Ergebnisse — sollte entsprechend im Web suchen“).
--
-- Merkt sich je Ort und Branche, wann zuletzt bei OpenStreetMap gesucht
-- wurde. Zweck: Die offenen Dienste (Nominatim, Overpass) nicht mit jeder
-- Suche erneut abfragen -- ihre Nutzungsregeln verlangen Zurückhaltung, und
-- ein Ort ändert sich nicht in einer Woche.
-- ============================================================================

CREATE TABLE IF NOT EXISTS partner_websuche (
  schluessel CHAR(40)     NOT NULL,
  ort        VARCHAR(80)  NOT NULL,
  branche    VARCHAR(40)  NOT NULL DEFAULT '',
  gebiet     VARCHAR(120) NULL,
  gefunden   INT UNSIGNED NOT NULL DEFAULT 0,
  neu        INT UNSIGNED NOT NULL DEFAULT 0,
  fehler     VARCHAR(200) NULL,
  am         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (schluessel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
