-- ===========================================================================
-- 133_marketing_3d_galerie.sql — 3D-Bilder und -Videos auch für Partner
-- (Marketing-Studio 11, 01.10.2026, Uwe: Ja zu P1–P3).
--
-- mk_medien kann jetzt ohne Inhalt bestehen:
--   galerie = 1   Vecoms 3D-Galerie für alle Partner (nach Uwes Ja sichtbar,
--                 status gewaehlt)
--   partner_id    3D-Bild/-Video, das ein Partner selbst bestellt hat — nur
--                 für ihn sichtbar
--   studio        welche 3D-Szene (gastro, salon, wein …)
--
-- Nur hinzufügen. Rückweg: die drei Spalten entfernen.
-- ===========================================================================

ALTER TABLE mk_medien
  ADD COLUMN IF NOT EXISTS galerie TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN IF NOT EXISTS partner_id INT UNSIGNED NULL AFTER galerie,
  ADD COLUMN IF NOT EXISTS studio VARCHAR(20) NULL AFTER partner_id,
  ADD INDEX IF NOT EXISTS ix_mk_medien_galerie (galerie, status),
  ADD INDEX IF NOT EXISTS ix_mk_medien_partner (partner_id);
