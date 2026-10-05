-- Vecom Partner Academy, Etappe 3 (05.10.2026, Uwe: „Etappe 3 bauen“).
-- Abschlusstest (Versuche je Partner), Zertifikate mit öffentlicher Prüfnummer,
-- eigene Medien je Lektion (Video/Audio der Verwaltung, als BLOB wie die PDFs).
-- Der Gesprächssimulator speichert keine Gespräche — nur die Tageszahl je
-- Partner in partner_zaehler (art „sim“). Nur hinzufügen. Rückweg: Tabellen löschen.

CREATE TABLE IF NOT EXISTS academy_abschluss (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  partner_id  INT UNSIGNED NOT NULL,
  richtig     TINYINT UNSIGNED NOT NULL,
  gesamt      TINYINT UNSIGNED NOT NULL,
  bestanden   TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_aa_partner (partner_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academy_zertifikate (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  partner_id    INT UNSIGNED NOT NULL,
  nummer        VARCHAR(20)  NOT NULL,          -- VA-XXXX-XXXX, öffentlich prüfbar
  name          VARCHAR(160) NOT NULL,          -- so steht es auf dem Zertifikat
  ergebnis      TINYINT UNSIGNED NOT NULL,      -- Prozent im Abschlusstest
  ausgestellt_am DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  widerrufen_am DATETIME     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_az_nummer (nummer),
  KEY idx_az_partner (partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academy_medien (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  modul       VARCHAR(40)  NOT NULL,
  lektion     TINYINT UNSIGNED NOT NULL,
  sprache     VARCHAR(5)   NOT NULL DEFAULT 'alle',   -- alle | it | de | en
  art         VARCHAR(10)  NOT NULL,                  -- video | audio
  mime        VARCHAR(40)  NOT NULL,
  titel       VARCHAR(160) NOT NULL DEFAULT '',
  groesse     INT UNSIGNED NOT NULL,
  datei       MEDIUMBLOB   NOT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_am_lektion (modul, lektion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
