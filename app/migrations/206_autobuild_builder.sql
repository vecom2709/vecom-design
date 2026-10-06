-- ===========================================================================
-- 206_autobuild_builder.sql — AutoBuild Phase 7 (06.10.2026, Uwe: „fahre
-- fort“; Entscheidung 5 „Builder + Reviewer“).
--
-- bau_auftraege: neue Arten „bauen“ (Claude baut aus dem übernommenen
-- Pflichtenheft eine Fassung) und „review“ (ein zweiter Claude-Lauf prüft
-- die Fassung gegen Pflichtenheft und Tests). parameter: welche Fassung,
-- versuch: Nachbesserungsrunde (höchstens 3).
-- projekt_versionen: Ergebnis der automatischen Tests und des Reviews.
--
-- Nur hinzufügen. Rückweg: die Spalten entfernen.
-- ===========================================================================

ALTER TABLE bau_auftraege
  ADD COLUMN IF NOT EXISTS parameter  TEXT             NULL,
  ADD COLUMN IF NOT EXISTS versuch    TINYINT UNSIGNED NOT NULL DEFAULT 1;

ALTER TABLE projekt_versionen
  ADD COLUMN IF NOT EXISTS tests          MEDIUMTEXT  NULL,   -- JSON: [{name, ok, schwer, detail}]
  ADD COLUMN IF NOT EXISTS tests_ok       TINYINT(1)  NULL,
  ADD COLUMN IF NOT EXISTS review_urteil  VARCHAR(12) NULL,   -- bestanden | nachbessern
  ADD COLUMN IF NOT EXISTS review_text    MEDIUMTEXT  NULL,
  ADD COLUMN IF NOT EXISTS review_am      DATETIME    NULL,
  ADD COLUMN IF NOT EXISTS auftrag_id     INT UNSIGNED NULL;  -- bau_auftraege.id, wenn Claude sie gebaut hat
