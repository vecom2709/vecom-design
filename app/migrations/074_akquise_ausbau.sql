-- ============================================================================
-- 074 — Akquise-Ausbau (26.09.2026, Uwe: Ja zu Einwilligung per Link, Brief per
-- Klick, Vorschau je Firma, Karte, Trichter & Wochenziel, Signal-Wecker,
-- Wiedervorlage, A/B-Texte).
--
-- Wiederholbar geschrieben: CREATE ... IF NOT EXISTS; ALTER TABLE ADD COLUMN
-- scheitert beim zweiten Lauf mit 1060, das Einrichtung::migrieren() als
-- "gibt es schon" ueberspringt.
-- ============================================================================

-- Einwilligungen mit Double-Opt-in. Eine Zeile je Anfrage; bestaetigt ist sie
-- erst mit dem Klick in der Bestaetigungsmail. Der Wortlaut steht in der
-- Zeile selbst -- aendert sich der Text spaeter, bleibt belegt, wozu genau
-- diese Firma ja gesagt hat.
CREATE TABLE IF NOT EXISTS akq_einwilligungen (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  firma_id         INT UNSIGNED NOT NULL,
  link_token       CHAR(40)     NOT NULL,              -- Formular (Analyse-Seite oder Link nach dem Anruf)
  doi_token        CHAR(40)     NULL,                  -- Bestaetigungslink aus der Mail
  email            VARCHAR(190) NULL,
  sprache          CHAR(2)      NOT NULL DEFAULT 'it',
  quelle           VARCHAR(20)  NOT NULL DEFAULT 'link', -- analyse | link
  wortlaut         TEXT         NULL,
  wortlaut_version VARCHAR(10)  NULL,
  status           VARCHAR(12)  NOT NULL DEFAULT 'offen', -- offen | angefragt | bestaetigt | widerrufen | abgelaufen
  angefragt_am     DATETIME     NULL,
  bestaetigt_am    DATETIME     NULL,
  ip_hash          CHAR(64)     NULL,                  -- nie die Adresse selbst
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_akq_ew_link (link_token),
  UNIQUE KEY uq_akq_ew_doi (doi_token),
  KEY ix_akq_ew_firma (firma_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Briefe ueber den Briefdienst (Poste Italiane ueber die Schnittstelle von
-- ufficiopostale.com). Erst entsteht ein Auftrag im Vorschau-Zustand mit Preis
-- und geprueftem PDF; verschickt und bezahlt wird er erst mit Uwes Klick.
CREATE TABLE IF NOT EXISTS akq_briefe (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  firma_id         INT UNSIGNED NOT NULL,
  vorlage_id       INT UNSIGNED NULL,
  dienst           VARCHAR(20)  NOT NULL DEFAULT 'ufficiopostale',
  test             TINYINT(1)   NOT NULL DEFAULT 0,
  auftrag          VARCHAR(80)  NULL,                  -- ID beim Dienst
  status           VARCHAR(12)  NOT NULL DEFAULT 'vorschau', -- vorschau | verschickt | verworfen | fehler
  kosten_cents     INT UNSIGNED NULL,
  seiten           TINYINT UNSIGNED NULL,
  pdf_url          VARCHAR(500) NULL,
  fehler           VARCHAR(255) NULL,
  actor            VARCHAR(80)  NOT NULL DEFAULT 'System',
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  verschickt_am    DATETIME     NULL,
  KEY ix_akq_brief_firma (firma_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Signale: Veraenderungen an der Website einer Firma, die einen guten Anlass
-- fuer eine Ansprache geben (offline, Zertifikat laeuft ab ...).
CREATE TABLE IF NOT EXISTS akq_signale (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  firma_id         INT UNSIGNED NOT NULL,
  art              VARCHAR(20)  NOT NULL,              -- offline | wieder_online | ssl_bald | ssl_abgelaufen
  text             VARCHAR(255) NOT NULL,
  erledigt         TINYINT(1)   NOT NULL DEFAULT 0,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_akq_sig_offen (erledigt, id),
  KEY ix_akq_sig_firma (firma_id, art, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE akq_firmen ADD COLUMN wiedervorlage_am DATE NULL;
ALTER TABLE akq_firmen ADD COLUMN signal_geprueft_am DATETIME NULL;
ALTER TABLE akq_firmen ADD COLUMN web_status SMALLINT NULL;       -- letzter HTTP-Status (0 = nicht erreichbar)
ALTER TABLE akq_firmen ADD COLUMN ssl_bis DATE NULL;
ALTER TABLE akq_vorlagen ADD COLUMN variante CHAR(1) NULL;        -- A | B, fuer den Textvergleich
