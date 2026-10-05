-- ===========================================================================
-- 174_partner_kampagnen.sql — Kampagnen der Partner (Etappe 0c des Marketing
-- Command Center, 05.10.2026; Uwe: „Ja“ zu „Kampagnen von Partnern und
-- Verwaltung in einer Tabelle (mk_kampagnen), keine neuen marketing_*-Tabellen“).
--
-- mk_kampagnen.partner_id NULL = Kampagne der Verwaltung (wie bisher, /k/CODE);
-- gesetzt = Kampagne eines Partners (nie über /k/ erreichbar — gezählt wird
-- über seine Kanäle /p/CODE/kampagne-N und /p/CODE/wm-M, siehe PartnerKampagne.php).
-- vorlage_id = die Vecom-Kampagne, aus der der Partner seine gezogen hat.
-- ziel_weg = wohin Link und QR führen ('' Partnerseite | preis | check | termin | wa),
-- änderbar ohne Neudruck — je Kampagne und je Material (wm_entwuerfe.ziel_weg).
-- wm_entwuerfe.version: V1, V2, … je Partner und Produkt (bisher überschrieb ein
-- neuer Entwurf den alten ohne Spur).
-- ===========================================================================

ALTER TABLE mk_kampagnen
  ADD COLUMN IF NOT EXISTS partner_id  INT UNSIGNED NULL AFTER id,
  ADD COLUMN IF NOT EXISTS vorlage_id  INT UNSIGNED NULL AFTER partner_id,
  ADD COLUMN IF NOT EXISTS region      VARCHAR(80)  NOT NULL DEFAULT '' AFTER land,
  ADD COLUMN IF NOT EXISTS designlinie VARCHAR(20)  NOT NULL DEFAULT '' AFTER region,
  ADD COLUMN IF NOT EXISTS sprache     CHAR(2)      NOT NULL DEFAULT '' AFTER designlinie,
  ADD COLUMN IF NOT EXISTS ziel_weg    VARCHAR(12)  NOT NULL DEFAULT '' AFTER ziel,
  ADD INDEX IF NOT EXISTS ix_mk_kampagne_partner (partner_id, status);

ALTER TABLE wm_entwuerfe
  ADD COLUMN IF NOT EXISTS kampagne_id INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS version     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS ziel_weg    VARCHAR(12) NOT NULL DEFAULT '',
  ADD INDEX IF NOT EXISTS ix_wm_entwurf_kampagne (kampagne_id);
