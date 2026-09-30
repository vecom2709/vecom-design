-- ===========================================================================
-- 118_marketing_kampagnen_ziele.sql — Kampagnen-Manager
-- (Growth Engine Phase 4, 30.09.2026, Uwe: „Ja“).
--
-- Jede Kampagne bekommt ein Ziel (woran ihr Erfolg gemessen wird), eine
-- Branche, einen Handlungsaufruf (CTA), eine Laufzeit und eine Budgetgrenze.
-- Die Grenze warnt (Meldung bei 80 % und 100 %) — sie stoppt keine Anzeige
-- bei Meta oder Google, dafür fehlt dort jede Anbindung.
--
-- Nur hinzufügen. Rückweg: die neuen Spalten entfernen.
-- ===========================================================================

ALTER TABLE mk_kampagnen
  ADD COLUMN IF NOT EXISTS ziel_art     VARCHAR(20)  NOT NULL DEFAULT 'leads' AFTER ziel,
  ADD COLUMN IF NOT EXISTS branche      VARCHAR(40)  NOT NULL DEFAULT '' AFTER ziel_art,
  ADD COLUMN IF NOT EXISTS cta          VARCHAR(20)  NOT NULL DEFAULT '' AFTER branche,
  ADD COLUMN IF NOT EXISTS cta_text     VARCHAR(120) NOT NULL DEFAULT '' AFTER cta,
  ADD COLUMN IF NOT EXISTS budget_cents INT UNSIGNED NULL AFTER cta_text,
  ADD COLUMN IF NOT EXISTS budget_art   VARCHAR(10)  NOT NULL DEFAULT 'gesamt' AFTER budget_cents,  -- gesamt | monat
  ADD COLUMN IF NOT EXISTS start_am     DATE NULL AFTER budget_art,
  ADD COLUMN IF NOT EXISTS ende_am      DATE NULL AFTER start_am,
  ADD INDEX IF NOT EXISTS ix_mk_kampagne_branche (branche);
