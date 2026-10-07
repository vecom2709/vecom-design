-- Kunden finden (07.10.2026, Uwe: „wenn Kunden aussortiert worden sind oder schon Kunden sind, nicht nochmal aufnehmen bzw. markieren“).
-- Abgleich mit der Kundenliste, Ausschluss mit Grund, Dubletten-Verdacht, Neueröffnungen, Agentur, Tagesliste, Gebietsplan.

ALTER TABLE akq_firmen
  ADD COLUMN IF NOT EXISTS piva              VARCHAR(16)  NULL,          -- Partita IVA / USt-IdNr. von der Website (nur Ziffern)
  ADD COLUMN IF NOT EXISTS markierung        VARCHAR(20)  NULL,          -- kunde|dublette (gerechnet von KundenFinden)
  ADD COLUMN IF NOT EXISTS markierung_grund  VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS markierung_am     DATETIME     NULL,
  ADD COLUMN IF NOT EXISTS dublette_von      INT UNSIGNED NULL,          -- vermutlich derselbe Betrieb wie akq_firmen.id
  ADD COLUMN IF NOT EXISTS dublette_prozent  TINYINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS osm_neu_am        DATE         NULL,          -- erstmals auf OpenStreetMap eingetragen (Version 1)
  ADD COLUMN IF NOT EXISTS agentur           VARCHAR(120) NULL,          -- „Realizzato da …“ im Seitenfuß
  ADD COLUMN IF NOT EXISTS abgeglichen_am    DATETIME     NULL;          -- zuletzt gegen Kunden/Dubletten geprüft

ALTER TABLE akq_firmen ADD INDEX IF NOT EXISTS ix_akq_markierung (markierung);
ALTER TABLE akq_firmen ADD INDEX IF NOT EXISTS ix_akq_abgleich (abgeglichen_am);
ALTER TABLE akq_firmen ADD INDEX IF NOT EXISTS ix_akq_piva (piva);
ALTER TABLE akq_firmen ADD INDEX IF NOT EXISTS ix_akq_email (email);
ALTER TABLE akq_firmen ADD INDEX IF NOT EXISTS ix_akq_telefon (telefon);

-- Warum ein Betrieb draußen ist. Alt-Einträge waren alle „ohne Kontaktweg“.
ALTER TABLE akq_aussortiert
  ADD COLUMN IF NOT EXISTS grund       VARCHAR(20)  NOT NULL DEFAULT 'ohne_kontakt',   -- ohne_kontakt|kunde|kein_interesse|abgemeldet|gesperrt|verloren|zusammengefuehrt
  ADD COLUMN IF NOT EXISTS grund_text  VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS customer_id INT UNSIGNED NULL;
ALTER TABLE akq_aussortiert ADD INDEX IF NOT EXISTS ix_akq_aus_grund (grund);

-- „Heute ansprechen“: jeden Morgen die zehn besten — nur Vorschlag, gesendet wird nichts.
CREATE TABLE IF NOT EXISTS akq_tagesliste (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  datum     DATE         NOT NULL,
  rang      TINYINT UNSIGNED NOT NULL,
  firma_id  INT UNSIGNED NOT NULL,
  kanal     VARCHAR(20)  NOT NULL,           -- email|whatsapp|telefon|partner
  grund     VARCHAR(255) NOT NULL,
  erledigt  TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_akq_tag (datum, firma_id),
  KEY ix_akq_tag_datum (datum, rang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
