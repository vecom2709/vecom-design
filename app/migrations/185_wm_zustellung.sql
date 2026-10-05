-- Partner-Shop Phase 6a (05.10.2026, Uwe: „Partner bestätigt, sonst nach 14 Tagen“).
-- Zwei Stände nach „versendet“: zugestellt (Partner tippt, sonst automatisch nach 14 Tagen)
-- und reklamation (Partner meldet binnen 14 Tagen nach Zustellung, Uwe entscheidet:
-- neudruck, gutschrift oder abgelehnt — danach wieder „zugestellt“ mit Entscheid).
-- Wiederholbar: IF NOT EXISTS.
ALTER TABLE wm_bestellungen ADD COLUMN IF NOT EXISTS zugestellt_am DATETIME NULL;
ALTER TABLE wm_bestellungen ADD COLUMN IF NOT EXISTS zugestellt_wie VARCHAR(12) NULL;
ALTER TABLE wm_bestellungen ADD COLUMN IF NOT EXISTS reklamation_am DATETIME NULL;
ALTER TABLE wm_bestellungen ADD COLUMN IF NOT EXISTS reklamation_grund VARCHAR(600) NULL;
ALTER TABLE wm_bestellungen ADD COLUMN IF NOT EXISTS reklamation_foto MEDIUMBLOB NULL;
ALTER TABLE wm_bestellungen ADD COLUMN IF NOT EXISTS reklamation_entscheid VARCHAR(12) NULL;
ALTER TABLE wm_bestellungen ADD COLUMN IF NOT EXISTS reklamation_antwort VARCHAR(600) NULL;
ALTER TABLE wm_bestellungen ADD COLUMN IF NOT EXISTS reklamation_entschieden_am DATETIME NULL;
