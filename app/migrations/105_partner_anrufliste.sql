-- Anrufliste (29.09.2026, Uwe: Ja zu T1–T4)
-- Uwe übergibt Betriebe aus „Neue Kunden finden“ an einen Partner zum
-- Abtelefonieren. Es bleibt eine Reservierung wie beim Firmen-Finder, nur
-- mit Herkunft „vecom“, dem Prüfvermerk von Uwe und dem Stand des Anrufs.
ALTER TABLE partner_reservierungen ADD COLUMN IF NOT EXISTS herkunft VARCHAR(12) NULL;
ALTER TABLE partner_reservierungen ADD COLUMN IF NOT EXISTS anruf_status VARCHAR(16) NULL;
ALTER TABLE partner_reservierungen ADD COLUMN IF NOT EXISTS anruf_am DATETIME NULL;
ALTER TABLE partner_reservierungen ADD COLUMN IF NOT EXISTS versuche TINYINT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE partner_reservierungen ADD COLUMN IF NOT EXISTS vermerk VARCHAR(255) NULL;
