-- ===========================================================================
-- 160_werbemittel_marketing_id.sql — Marketing-ID und Scans je Werbemittel
-- (04.10.2026, Partner-Marketingcenter Schritt 1b, Uwe: „ja“).
--
-- Jeder Entwurf ist ein eigenes Werbemittel mit eigener Marketing-ID
-- (VM-2026-000241 = Jahr und Nummer des Entwurfs, NICHT gespeichert, sondern
-- aus id und created_at gebildet). Sein QR-Code führt auf /p/CODE/wm-241 —
-- dieselbe Partner-Spur wie jeder andere Kanal, keine zweite Tracking-Welt.
-- Besucher und Anfragen stehen in spur_besuche und partner_zuordnungen
-- (kanal = „wm-241“). Nur die Scans bekommen hier einen Zähler, weil die
-- Rohdaten der Spur nach 90 Tagen gelöscht werden und ein gedruckter Flyer
-- länger lebt. Gezählt wird nur, wenn Code und Werbemittel zusammengehören.
-- ===========================================================================

ALTER TABLE wm_entwuerfe ADD COLUMN IF NOT EXISTS scans INT UNSIGNED NOT NULL DEFAULT 0;
