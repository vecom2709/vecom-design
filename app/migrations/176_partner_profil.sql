-- 176: Marketingprofil des Partners (Command Center, Etappe 1b, 05.10.2026).
-- JSON {branchen:[…], wege:[…], ziel:"…", am:"…"} — klein und nur für ihn selbst.
-- Der Ort steht schon in partner.heimatort (Firmen-Finder, Autopilot) und wird nicht doppelt gespeichert.
ALTER TABLE partner ADD COLUMN IF NOT EXISTS mk_profil VARCHAR(600) NULL AFTER heimatort;
