-- AI Office Stufe 5 (07.10.2026): Migration Center, DNS-Schutz, Exit-Paket, Domain-Bestellcheckliste.
-- Uwe: Migration Center als eigene Seite mit Pre-Flight, DNS vorher und taeglich mit MX/NS-Alarm,
-- Exit-Paket mit Website-Dateien, DNS-Doku, Postfaechern; Link an den Kunden ueber AI Freigaben.
-- Bestehende Tabellen bleiben, wie sie sind. Hier entsteht nur, was fehlte.

-- Ein Umzug als EIN Vorgang je Kunde -- ueber Domain-, Seiten- und Mailumzug.
CREATE TABLE IF NOT EXISTS migrationen (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id     INT UNSIGNED NOT NULL,
  domain          VARCHAR(190) NULL,
  stand           VARCHAR(20)  NOT NULL DEFAULT 'neu',
  preflight_json  MEDIUMTEXT   NULL,
  preflight_am    DATETIME     NULL,
  preflight_ampel VARCHAR(10)  NULL,
  abgeschlossen_am DATETIME    NULL,
  notiz           VARCHAR(500) NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_migration_kunde (customer_id),
  KEY idx_migration_stand (stand),
  CONSTRAINT fk_migration_kunde FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Welche bestehenden Umzuege zu einem Vorgang gehoeren. Jeder Teil nur einmal.
CREATE TABLE IF NOT EXISTS migration_teile (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  migration_id  INT UNSIGNED NOT NULL,
  art           VARCHAR(10)  NOT NULL,
  ref_id        INT UNSIGNED NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_migration_teil (art, ref_id),
  KEY idx_migration_teil_vorgang (migration_id),
  CONSTRAINT fk_migration_teil_vorgang FOREIGN KEY (migration_id) REFERENCES migrationen (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jeder Wechsel des Stands, mit Grund und wer.
CREATE TABLE IF NOT EXISTS migration_verlauf (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  migration_id  INT UNSIGNED NOT NULL,
  von           VARCHAR(20)  NULL,
  nach          VARCHAR(20)  NOT NULL,
  grund         VARCHAR(500) NULL,
  wer           VARCHAR(80)  NOT NULL DEFAULT 'System',
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_migration_verlauf_vorgang (migration_id),
  CONSTRAINT fk_migration_verlauf_vorgang FOREIGN KEY (migration_id) REFERENCES migrationen (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- DNS-Schnappschuss: oeffentliches DNS und (wenn bei uns) die KAS-Zone.
-- anlass: vorher | nachher | taeglich | hand | exit
-- aenderungen_json (nur bei nachher): was umgeschrieben (mit altem Wert) und was hinzugefuegt wurde.
CREATE TABLE IF NOT EXISTS dns_schnappschuesse (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  domain           VARCHAR(190) NOT NULL,
  customer_id      INT UNSIGNED NULL,
  anlass           VARCHAR(12)  NOT NULL,
  bezug            VARCHAR(60)  NULL,
  oeffentlich_json MEDIUMTEXT   NULL,
  kas_json         MEDIUMTEXT   NULL,
  kas_ok           TINYINT(1)   NOT NULL DEFAULT 0,
  fingerabdruck    CHAR(64)     NOT NULL,
  vorher_id        INT UNSIGNED NULL,
  aenderungen_json MEDIUMTEXT   NULL,
  zurueck_am       DATETIME     NULL,
  zurueck_von      VARCHAR(80)  NULL,
  zurueck_text     VARCHAR(500) NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_dns_domain (domain, created_at),
  KEY idx_dns_kunde (customer_id),
  KEY idx_dns_anlass (anlass)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exit-Paket: entsteht auf Uwes Klick; der Link geht nur nach seinem Ja (AI Freigaben).
-- Vom Schluessel steht nur der SHA-256 hier.
CREATE TABLE IF NOT EXISTS exit_pakete (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id   INT UNSIGNED NOT NULL,
  stand         VARCHAR(12)  NOT NULL DEFAULT 'baut',
  inhalt        VARCHAR(120) NOT NULL,
  file_id       INT UNSIGNED NULL,
  groesse       BIGINT UNSIGNED NOT NULL DEFAULT 0,
  protokoll     MEDIUMTEXT   NULL,
  fehler        VARCHAR(500) NULL,
  schluessel_hash CHAR(64)   NULL,
  gueltig_bis   DATETIME     NULL,
  gesendet_am   DATETIME     NULL,
  abrufe        INT UNSIGNED NOT NULL DEFAULT 0,
  zuletzt_abgerufen DATETIME NULL,
  erstellt_von  VARCHAR(80)  NOT NULL DEFAULT 'Verwaltung',
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_exit_schluessel (schluessel_hash),
  KEY idx_exit_kunde (customer_id),
  CONSTRAINT fk_exit_kunde FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Domain-Bestellcheckliste: Uwes Freigabe vor der Bestellung und sein Haken danach.
ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS bestell_freigabe_am DATETIME NULL;
ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS bestell_freigabe_von VARCHAR(80) NULL;
ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS domain_bestellt_am DATETIME NULL;
