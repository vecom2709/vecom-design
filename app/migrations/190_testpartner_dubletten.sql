-- ===========================================================================
-- 190_testpartner_dubletten.sql — Phase 9b (06.10.2026).
-- Uwe: „Ja, ein Testpartner“ — zählt nirgends: keine Provision, keine Mail,
-- keine Auszahlung, keine Statistik. Und „Vorschlag + Zusammenführen per
-- Klick“ für doppelte Kunden — „Sind verschieden“ blendet den Vorschlag für
-- immer aus (kunden_verschieden), zusammengeführt wird nur nach Klick.
-- ===========================================================================

ALTER TABLE partner ADD COLUMN IF NOT EXISTS test TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS kunden_verschieden (
  a_id        INT UNSIGNED NOT NULL,
  b_id        INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (a_id, b_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
