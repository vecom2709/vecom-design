-- ===========================================================================
-- 200_autobuild_bausperre.sql — AutoBuild Engine, Phase 4 (06.10.2026).
-- Uwe: Masterprompt „Vecom AutoBuild Engine“, Entscheidung 1 „ja“:
-- gebaut wird erst nach Angebotsannahme UND bezahlter Anzahlung.
--
-- bau_frei_am/_von: wann und wodurch die Bausperre fiel (automatisch, sobald
--   Annahme + Anzahlung da sind, oder von Hand durch einen Admin).
-- ki_stopp*: Not-Aus je Projekt — keine Änderungen, keine Vorschau, kein
--   Paket, keine Veröffentlichung, bis ein Admin ihn aufhebt.
-- risiko: gruen | gelb | rot (Anzeige auf der Projektkarte).
-- Alle Spalten mit Standardwert: bestehende Projekte ändern sich nicht.
-- ===========================================================================

ALTER TABLE projects
  ADD COLUMN IF NOT EXISTS projektart      VARCHAR(12)  NULL,
  ADD COLUMN IF NOT EXISTS bau_frei_am     DATETIME     NULL,
  ADD COLUMN IF NOT EXISTS bau_frei_von    VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS ki_stopp        TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS ki_stopp_grund  VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS ki_stopp_am     DATETIME     NULL,
  ADD COLUMN IF NOT EXISTS ki_stopp_von    VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS risiko          VARCHAR(8)   NULL;

-- Bestand: Projekte, die es schon gibt, entstanden alle erst nach einer Zahlung und laufen
-- bereits — sie gelten als frei, damit die laufende Werkstatt-Arbeit nicht stehen bleibt.
UPDATE projects SET bau_frei_am = COALESCE(created_at, NOW()), bau_frei_von = 'Bestand vor der Bausperre (06.10.2026)'
 WHERE bau_frei_am IS NULL;
