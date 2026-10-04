-- ===========================================================================
-- 161_marketingcenter_bereiche.sql — Bereiche und Favoriten im Marketing
-- Center (04.10.2026, Partner-Marketingcenter Schritt 2, Uwe: „ja“).
--
-- bereich: in welchem der Bereiche (Print, Point of Sale, Textilien,
-- Fahrzeugwerbung, Events & Messe, Premium, Starterpakete) ein Produkt
-- steht. NULL = wie seine Kategorie (Marketingcenter::KATEGORIE_BEREICH).
-- Eigene Spalte statt neuer Kategorien: Der Roll-up liegt in der Kategorie
-- „Aufsteller“, gehört im Marketing Center aber zu Events & Messe.
--
-- wm_favoriten: Merkliste je Partner. Nur eigene, nur Produkte, die es gibt.
-- ===========================================================================

ALTER TABLE wm_produkte ADD COLUMN IF NOT EXISTS bereich VARCHAR(20) NULL;

UPDATE wm_produkte SET bereich = 'event' WHERE vorlage = 'rollup_85' AND bereich IS NULL;

CREATE TABLE IF NOT EXISTS wm_favoriten (
  partner_id  INT UNSIGNED NOT NULL,
  produkt_id  INT UNSIGNED NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (partner_id, produkt_id),
  KEY ix_wm_fav_produkt (produkt_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
