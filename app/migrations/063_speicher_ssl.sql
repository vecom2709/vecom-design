-- ===========================================================================
-- 063_speicher_ssl.sql — Speicher je Vertrag, Abgleich mit dem KAS, HTTPS,
-- Datenbank/FTP auf Wunsch, Probelauf (26.09.2026, Uwes Masterprompt)
--
-- speicher_mb:     was mit DIESEM Kunden vereinbart ist, in MB. Vecom ist die
--                  Quelle; der KAS folgt. Bestehende Auftraege bekommen genau
--                  den Wert, der bis heute fuer alle galt (10 GB = 10240 MB,
--                  Hosting::SPEICHER_MB) -- geaendert wird dabei nichts.
-- kas_speicher_mb: was im KAS tatsaechlich eingerichtet ist (get_accounts,
--                  max_webspace), zuletzt gelesen am kas_gelesen_am.
-- ssl_*:           Ergebnis der HTTPS-Pruefung der Domain.
-- mit_datenbank/mit_ftp: beim Einrichten zusaetzlich anlegen (nur auf Wunsch).
-- technik_blob:    deren Zugangsdaten, verschluesselt wie zugang_blob -- fuer
--                  Uwe, nicht fuer die Kundenseite.
-- probelauf_am:    angehalten, weil der Probelauf an war; laeuft an, sobald
--                  er aus ist.
-- Wiederholbar: IF NOT EXISTS; das Nachtragen fasst nur leere Werte an.
-- ===========================================================================

ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS speicher_mb INT NULL AFTER preis_cents;
UPDATE hosting_auftraege SET speicher_mb = 10240 WHERE speicher_mb IS NULL;
ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS kas_speicher_mb INT NULL;
ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS kas_gelesen_am DATETIME NULL;
ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS ssl_status VARCHAR(16) NULL;
ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS ssl_text VARCHAR(255) NULL;
ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS ssl_geprueft_am DATETIME NULL;
ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS mit_datenbank TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS mit_ftp TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS technik_blob TEXT NULL;
ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS probelauf_am DATETIME NULL;
