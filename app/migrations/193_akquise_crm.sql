-- ===========================================================================
-- 193_akquise_crm.sql — Akquise-CRM Modul A (06.10.2026).
-- Uwe: „A+B+C: Profil, Score, Arbeitsplatz“, KI „weiter über den PC-Worker“,
-- Reservierung „30 Tage, einmal verlängerbar“, Partner sehen das Profil ohne
-- Vecom-Interna.
--
-- Nur HINZUFÜGEN: akq_firmen bleibt der Betrieb, alles Bestehende bleibt.
-- Was schon da ist und deshalb hier fehlt: Branche, Ort/Region/Provinz,
-- Adresse, Telefon, E-Mail, WhatsApp, Ansprechpartner, Website, Quelle,
-- recherchiert_am, Wiedervorlage, Einwilligung, gesperrt, Score (= Digital-
-- Chance), top_probleme, Pipeline.
-- ===========================================================================

ALTER TABLE akq_firmen
  ADD COLUMN IF NOT EXISTS rechtsform          VARCHAR(40)  NULL,
  ADD COLUMN IF NOT EXISTS unterbranche        VARCHAR(80)  NULL,
  ADD COLUMN IF NOT EXISTS filiale_von         INT UNSIGNED NULL,          -- Hauptsitz (akq_firmen.id), NULL = selbst Hauptsitz oder eigenständig
  ADD COLUMN IF NOT EXISTS position            VARCHAR(80)  NULL,          -- Position des Ansprechpartners
  ADD COLUMN IF NOT EXISTS mobil               VARCHAR(40)  NULL,
  ADD COLUMN IF NOT EXISTS emails_weitere      TEXT         NULL,          -- JSON-Liste
  ADD COLUMN IF NOT EXISTS google_profil       VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS facebook            VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS instagram           VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS linkedin            VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS xing                VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS profile_sonst       TEXT         NULL,          -- JSON-Liste weiterer Profile (URL)
  ADD COLUMN IF NOT EXISTS quelle_art          VARCHAR(20)  NULL,          -- google|webseite|branchenbuch|linkedin|xing|facebook|instagram|empfehlung|manuell|osm|overture|sonstige
  ADD COLUMN IF NOT EXISTS quelle_url          VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS gefunden_von        VARCHAR(80)  NULL,
  ADD COLUMN IF NOT EXISTS prio_score          TINYINT UNSIGNED NULL,      -- Akquise-Priorität 0–100 (AkquisePrio)
  ADD COLUMN IF NOT EXISTS prio_stufe          VARCHAR(10)  NULL,          -- jetzt|gut|spaeter|niedrig|nie
  ADD COLUMN IF NOT EXISTS prio_gruende        TEXT         NULL,          -- JSON: belegte Gründe, stärkste zuerst
  ADD COLUMN IF NOT EXISTS prio_am             DATETIME     NULL,
  ADD COLUMN IF NOT EXISTS letzter_kontakt_am  DATETIME     NULL,
  ADD COLUMN IF NOT EXISTS naechster_schritt   VARCHAR(160) NULL,          -- von Hand gesetzter nächster Schritt (sonst gerechnet)
  ADD COLUMN IF NOT EXISTS naechster_am        DATE         NULL,
  ADD COLUMN IF NOT EXISTS sperr_art           VARCHAR(20)  NULL,          -- AkquiseCrm::SPERR_ARTEN
  ADD COLUMN IF NOT EXISTS sperr_grund         VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS sperr_am            DATETIME     NULL,
  ADD COLUMN IF NOT EXISTS zusammenfassung     TEXT         NULL,          -- JSON: text, budget, entscheider, zeitpunkt, bedarf, angebot (Modul E)
  ADD COLUMN IF NOT EXISTS crm_stufe           VARCHAR(20)  NULL,          -- Pipeline von Hand: bedarf, angebot_erstellt, spaeter (der Rest steht in pipeline oder wird gerechnet)
  ADD COLUMN IF NOT EXISTS crm_stufe_am        DATETIME     NULL;

ALTER TABLE akq_firmen ADD INDEX IF NOT EXISTS ix_akq_prio (prio_stufe, prio_score);
ALTER TABLE akq_firmen ADD INDEX IF NOT EXISTS ix_akq_naechster (naechster_am);
ALTER TABLE akq_firmen ADD INDEX IF NOT EXISTS ix_akq_prio_am (prio_am);

-- Weitere Ansprechpartner (der erste steht weiter in akq_firmen.ansprechpartner/position).
CREATE TABLE IF NOT EXISTS akq_kontakte (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  firma_id    INT UNSIGNED NOT NULL,
  name        VARCHAR(120) NOT NULL,
  position    VARCHAR(80)  NULL,
  email       VARCHAR(190) NULL,
  telefon     VARCHAR(40)  NULL,
  notiz       VARCHAR(255) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_akqk_firma (firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notizen mit Sichtbarkeit: ich (nur Verfasser) | admin (nur Vecom) | team (Vecom + zuständiger Partner).
CREATE TABLE IF NOT EXISTS akq_notizen (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  firma_id    INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NULL,
  partner_id  INT UNSIGNED NULL,
  autor       VARCHAR(80)  NOT NULL,
  sichtbar    ENUM('ich','admin','team') NOT NULL DEFAULT 'team',
  angeheftet  TINYINT(1)   NOT NULL DEFAULT 0,
  text        VARCHAR(2000) NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_akqn_firma (firma_id, angeheftet, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kanäle je Betrieb: was von Hand gesetzt wurde (pausiert, nächster Kontakt).
-- Benutzt / Antwort / gesperrt / letzter Kontakt rechnet AkquiseCrm aus Versand, Antworten und Sperrliste.
CREATE TABLE IF NOT EXISTS akq_kanaele (
  firma_id    INT UNSIGNED NOT NULL,
  kanal       VARCHAR(12)  NOT NULL,                 -- email|whatsapp|telefon|linkedin|xing|formular|brief|besuch
  pausiert    TINYINT(1)   NOT NULL DEFAULT 0,
  benutzt_am  DATETIME     NULL,                     -- von Hand vermerkt (LinkedIn, Formular, Besuch …)
  naechster_am DATE        NULL,
  geaendert_am DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (firma_id, kanal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reservierung 30 Tage, einmal verlängerbar (Modul F nutzt es, die Spalte kommt jetzt, damit sie da ist).
ALTER TABLE partner_reservierungen ADD COLUMN IF NOT EXISTS verlaengert_am DATETIME NULL;
