-- ===========================================================================
-- 071_partner_ausbau.sql — Partnerprogramm, Ausbau (26.09.2026, Uwe: „Ja alles außer 2“)
--
-- partner_kanal_klicks: Klicks je Kanal (/p/CODE/instagram) — wie
--   partner_klicks nur je Tag gezählt, ohne IP.
-- partner_zuordnungen.kanal: über welchen Kanal der Kunde kam.
-- zugaenge.partner_code: trägt jetzt „CODE:kanal“ (bis 40 Zeichen).
-- partner.sofortmail: Sofort-Nachricht bei neuem Kunden / verdienter Provision.
-- partner.erinnert_am: letzte Erinnerung an einen ruhenden Partner.
-- partner.warnung_am: letzte Missbrauchs-Warnung zu diesem Partner (an Uwe).
-- partner.jahresmail: für welches Jahr die Jahresübersicht angekündigt wurde.
-- Stufen: Einstellungen, abschaltbar.
-- Wiederholbar: IF NOT EXISTS.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS partner_kanal_klicks (
  partner_id  INT UNSIGNED NOT NULL,
  kanal       VARCHAR(20)  NOT NULL,
  tag         DATE         NOT NULL,
  anzahl      INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (partner_id, kanal, tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE partner_zuordnungen ADD COLUMN IF NOT EXISTS kanal VARCHAR(20) NULL;
ALTER TABLE zugaenge MODIFY partner_code VARCHAR(40) NULL;
ALTER TABLE partner ADD COLUMN IF NOT EXISTS sofortmail TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE partner ADD COLUMN IF NOT EXISTS erinnert_am DATETIME NULL;
ALTER TABLE partner ADD COLUMN IF NOT EXISTS warnung_am DATETIME NULL;
ALTER TABLE partner ADD COLUMN IF NOT EXISTS jahresmail SMALLINT UNSIGNED NULL;

INSERT INTO settings (skey, svalue) VALUES
  ('partner_stufen_an', '1'),
  ('partner_silber_ab', '5'), ('partner_silber_bp', '1200'),
  ('partner_gold_ab', '10'),  ('partner_gold_bp', '1500')
ON DUPLICATE KEY UPDATE skey = skey;
