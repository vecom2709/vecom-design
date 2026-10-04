-- ===========================================================================
-- 159_partner_kontakt.sql — Telefon und Telegram des Partners (04.10.2026,
-- Uwe: „ja“ zu Schritt 1 des Partner-Marketingcenters, Fundament).
--
-- Werbemittel sollen die Partnerdaten automatisch tragen (Name, Firma, Code,
-- Telefon, E-Mail, Link, WhatsApp, Telegram, Ort, Foto). Bisher fehlten nur
-- Telefon und Telegram. Alles andere gibt es schon und wird NICHT doppelt
-- gespeichert: WhatsApp steht in seite_json (Partnerseite), Ort in heimatort,
-- Land in land, Foto in foto. Zusammengeführt wird in PartnerDaten::fuer().
-- Telefon in internationaler Form (+39…), Telegram als Name ohne @.
-- Erscheint nur auf Werbemitteln, die der Partner selbst freigibt.
-- ===========================================================================

ALTER TABLE partner ADD COLUMN IF NOT EXISTS telefon VARCHAR(20) NULL;
ALTER TABLE partner ADD COLUMN IF NOT EXISTS telegram VARCHAR(40) NULL;
