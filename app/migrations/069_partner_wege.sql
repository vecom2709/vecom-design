-- ===========================================================================
-- 069_partner_wege.sql — mehr als ein Weg zum Partner (26.09.2026, Uwe: „Ja mach“)
--
-- Der Partner wählt auf seiner Seite, wie er sein Geld bekommt: Stripe,
-- SEPA-Überweisung, PayPal, Wise oder Verrechnung (wenn er selbst Kunde
-- ist). Welche Wege es überhaupt gibt, schaltet Uwe ein.
--
-- iban_blob: versiegelt wie die Hosting-Zugänge (AES-256-GCM, Schlüssel nur
-- in config.local.php); lesbar bleiben nur die letzten vier Stellen.
--
-- partner_auszahlungen.status: 'offen' heißt „auf dem Weg, aber noch nicht
-- bestätigt“ -- eine SEPA-Datei, die bei der Bank liegt, oder eine
-- Wise-Überweisung, die in der App bestätigt werden muss. Die Provisionen
-- stehen solange auf „unterwegs“ und können nicht ein zweites Mal raus.
-- Wiederholbar: IF NOT EXISTS.
-- ===========================================================================

ALTER TABLE partner ADD COLUMN IF NOT EXISTS auszahlungsweg VARCHAR(10) NULL;
ALTER TABLE partner ADD COLUMN IF NOT EXISTS iban_blob TEXT NULL;
ALTER TABLE partner ADD COLUMN IF NOT EXISTS iban_ende CHAR(4) NULL;
ALTER TABLE partner ADD COLUMN IF NOT EXISTS kontoinhaber VARCHAR(160) NULL;
ALTER TABLE partner ADD COLUMN IF NOT EXISTS paypal_email VARCHAR(190) NULL;
ALTER TABLE partner ADD COLUMN IF NOT EXISTS wise_empfaenger VARCHAR(40) NULL;

ALTER TABLE partner_auszahlungen ADD COLUMN IF NOT EXISTS status VARCHAR(12) NOT NULL DEFAULT 'erledigt';
ALTER TABLE partner_auszahlungen ADD COLUMN IF NOT EXISTS extern_id VARCHAR(80) NULL;
-- 068 hatte 8 Zeichen fuer stripe|hand; 'gutschrift' hat 10 (die Kette fand es).
ALTER TABLE partner_auszahlungen MODIFY weg VARCHAR(12) NOT NULL;

INSERT INTO settings (skey, svalue) VALUES ('partner_wege', 'stripe,sepa,paypal,wise,gutschrift')
ON DUPLICATE KEY UPDATE skey = skey;
