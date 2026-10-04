-- ===========================================================================
-- 164_werbemittel_qr_pruefung.sql — QR-Prüfung vor der Produktion
-- (04.10.2026, Marketingcenter Schritt 8: „QR-Check vor Produktion“).
--
-- Jede Druckdatei eines Entwurfs wird nach dem Erzeugen geprüft
-- (QrPruefung::fuerEntwurf): Code vorhanden, Modul für Modul der Link mit
-- der eigenen Nummer des Werbemittels, groß genug. Ohne bestandene Prüfung
-- keine Freigabe und kein Auftrag an eine Druckerei.
-- qr_ok: 1 bestanden | 0 durchgefallen | NULL noch nicht geprüft (ältere Entwürfe)
-- ===========================================================================

ALTER TABLE wm_entwuerfe ADD COLUMN IF NOT EXISTS qr_ok TINYINT(1) NULL;
ALTER TABLE wm_entwuerfe ADD COLUMN IF NOT EXISTS qr_pruefung VARCHAR(500) NULL;
ALTER TABLE wm_entwuerfe ADD COLUMN IF NOT EXISTS qr_am DATETIME NULL;
