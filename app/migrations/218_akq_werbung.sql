-- Werbe-Mail mit Branchen-Flyer an Betriebe mit Zustimmung (07.10.2026, Uwe: „schlage vor ich sage ja oder nein“).
-- Eine Zeile je Betrieb nach Uwes Ja in AI Freigaben. Der Cron schickt sie innerhalb der Grenzen.
-- status: geplant | gesendet | simuliert | blockiert | fehler -- uq_werbung_firma haelt das „einmalig“.
CREATE TABLE IF NOT EXISTS akq_werbung_plan (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  firma_id INT UNSIGNED NOT NULL,
  vorlage_id INT UNSIGNED NOT NULL,
  freigabe_id INT UNSIGNED NULL,
  flyer VARCHAR(40) NULL,
  sprache VARCHAR(5) NOT NULL DEFAULT 'it',
  wer VARCHAR(80) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'geplant',
  grund VARCHAR(255) NULL,
  versand_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  erledigt_am DATETIME NULL,
  UNIQUE KEY uq_werbung_firma (firma_id),
  KEY ix_werbung_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
