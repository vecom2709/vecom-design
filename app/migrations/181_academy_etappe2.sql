-- Vecom Partner Academy, Etappe 2 (05.10.2026, Uwe: „MACH“).
-- Eigene PDFs der Verwaltung (als BLOB wie die Werbemittel: der Deploy löscht
-- nie, eine Datei auf dem Webspace bliebe sonst ewig erreichbar), „zuletzt
-- angesehen“ je Partner und Schalter je Modul (aktiv, Pflicht, Reihenfolge).
-- Nur hinzufügen. Rückweg: die drei Tabellen löschen.

CREATE TABLE IF NOT EXISTS academy_dokumente (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  titel       VARCHAR(160) NOT NULL,
  kategorie   VARCHAR(40)  NOT NULL DEFAULT 'eigene',
  sprache     VARCHAR(5)   NOT NULL DEFAULT 'alle',   -- alle | it | de | en
  version     SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  dateiname   VARCHAR(120) NOT NULL,
  groesse     INT UNSIGNED NOT NULL,
  datei       MEDIUMBLOB   NOT NULL,
  archiviert  TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ad_aktiv (archiviert, sprache)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academy_gesehen (
  partner_id INT UNSIGNED NOT NULL,
  ziel       VARCHAR(40)  NOT NULL,   -- PDF: Slug oder „u<ID>“
  am         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (partner_id, ziel),
  KEY idx_ag_am (partner_id, am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS academy_module (
  slug    VARCHAR(40) NOT NULL,
  aktiv   TINYINT(1)  NOT NULL DEFAULT 1,
  pflicht TINYINT(1)  NULL,              -- NULL = wie in der Inhaltsdatei
  reihe   SMALLINT    NULL,              -- NULL = Nummer aus der Inhaltsdatei
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
