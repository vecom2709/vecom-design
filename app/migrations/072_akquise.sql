-- ===========================================================================
-- 051 — Akquise: Lead Intelligence & Website Opportunity
--
-- Uwe am 24.09.2026: Ein System, das Betriebe in Deutschland und Italien
-- findet, deren Website nachweisbare Schwaechen hat, sie belegt, bewertet
-- und eine individuelle Kontaktvorlage vorbereitet -- mit einem
-- Compliance-Gate vor jedem Versand.
--
-- WARUM ES IN DER VERWALTUNG LIEGT UND NICHT IN EINEM ZWEITEN SYSTEM
-- Anmeldung, Pruefspur, Versand ueber Brevo, Cron und Zuruf gibt es hier
-- schon. Ein zweites System haette eine zweite Sperrliste -- und eine
-- Sperrliste, die es zweimal gibt, ist eine, die irgendwann einmal
-- uebersehen wird. Die schwere Arbeit (Playwright, Lighthouse, Overpass)
-- macht der Worker in tools/akquise/ auf Uwes Rechner und meldet ueber
-- akquise.php hierher.
--
-- Alle Tabellen mit Praefix akq_, alle wiederholbar (IF NOT EXISTS).
-- ===========================================================================

-- Die Firma. Eine Zeile je Betrieb, nie zwei.
CREATE TABLE IF NOT EXISTS akq_firmen (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kennung            CHAR(10)     NOT NULL,              -- L-XXXXXXXX, oeffentlich zitierbar
  name               VARCHAR(190) NOT NULL,
  name_norm          VARCHAR(190) NOT NULL,              -- klein, ohne Rechtsform/Satzzeichen
  domain             VARCHAR(190) NULL,                  -- ohne www., klein
  url                VARCHAR(500) NULL,
  land               CHAR(2)      NOT NULL,              -- DE | IT
  region             VARCHAR(120) NULL,                  -- Bundesland | Regione
  kreis              VARCHAR(120) NULL,                  -- Landkreis | Provincia
  stadt              VARCHAR(120) NULL,                  -- Stadt/Gemeinde | Comune
  plz                VARCHAR(10)  NULL,                  -- PLZ | CAP
  adresse            VARCHAR(255) NULL,
  adresse_norm       VARCHAR(255) NULL,
  lat                DECIMAL(9,6) NULL,
  lon                DECIMAL(9,6) NULL,
  branche            VARCHAR(40)  NULL,                  -- Schluessel aus Akquise::BRANCHEN
  unternehmensart    VARCHAR(80)  NULL,
  telefon            VARCHAR(40)  NULL,
  email              VARCHAR(190) NULL,
  ansprechpartner    VARCHAR(120) NULL,
  sprache            CHAR(2)      NULL,                  -- erkannte Sprache der Website
  tourismus          TINYINT(1)   NOT NULL DEFAULT 0,
  quelle             VARCHAR(80)  NULL,                  -- z. B. osm:node/123456
  quelle_lizenz      VARCHAR(60)  NULL,                  -- z. B. ODbL (OpenStreetMap)
  recherchiert_am    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  geprueft_am        DATETIME     NULL,                  -- letzter Audit
  audit_status       VARCHAR(20)  NOT NULL DEFAULT 'offen',     -- offen|laeuft|fertig|fehler|keine_website|uebersprungen
  kontakt_status     VARCHAR(20)  NOT NULL DEFAULT 'neu',       -- neu|qualifiziert|vorlage|freigegeben|kontaktiert|geantwortet|kunde|abgelehnt|gesperrt
  compliance_status  VARCHAR(20)  NOT NULL DEFAULT 'UNKNOWN',   -- CONTACT_ALLOWED|REVIEW_REQUIRED|DO_NOT_EMAIL|UNKNOWN
  versand_status     VARCHAR(20)  NOT NULL DEFAULT 'keiner',    -- keiner|geplant|gesendet|fehler|bounce
  antwort_status     VARCHAR(20)  NULL,                          -- Klasse der letzten Antwort
  score              TINYINT UNSIGNED NULL,
  score_stufe        VARCHAR(20)  NULL,                  -- gering|beobachten|interessant|sehr_interessant|top
  top_probleme       TEXT         NULL,                  -- JSON: die drei wichtigsten Befund-Titel
  einwilligung       VARCHAR(255) NULL,                  -- Beleg einer Einwilligung (wer, wann, wie) -- sonst leer
  bestandskunde      TINYINT(1)   NOT NULL DEFAULT 0,
  gesperrt           TINYINT(1)   NOT NULL DEFAULT 0,    -- DO_NOT_CONTACT, dauerhaft
  notiz              TEXT         NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_akq_kennung (kennung),
  UNIQUE KEY uq_akq_domain (domain),
  UNIQUE KEY uq_akq_quelle (quelle),
  KEY ix_akq_name_ort (name_norm, plz),
  KEY ix_akq_adresse (adresse_norm),
  KEY ix_akq_ort (land, region, kreis, stadt),
  KEY ix_akq_score (score),
  KEY ix_akq_status (kontakt_status, audit_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ein Audit-Lauf je Firma und Zeitpunkt. Alte bleiben stehen -- sie sind der
-- Beleg dafuer, was zum Zeitpunkt einer Ansprache galt.
CREATE TABLE IF NOT EXISTS akq_audits (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  firma_id           INT UNSIGNED NOT NULL,
  gestartet_am       DATETIME     NOT NULL,
  beendet_am         DATETIME     NULL,
  status             VARCHAR(20)  NOT NULL DEFAULT 'fertig',
  worker_version     VARCHAR(20)  NULL,
  geprueft_url       VARCHAR(500) NULL,
  seiten             SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  messwerte          JSON         NULL,                  -- Lighthouse/PSI, TTFB, Groessen ...
  teilwerte          JSON         NULL,                  -- Score je Kategorie
  score              TINYINT UNSIGNED NULL,
  ki                 JSON         NULL,                  -- Deutung durch Claude (Loesung, Experience-Idee)
  ki_modell          VARCHAR(60)  NULL,
  loesung            TEXT         NULL,
  experience         TEXT         NULL,
  screenshot_mobil   VARCHAR(120) NULL,                  -- Dateiname in uploads/akquise/
  screenshot_desktop VARCHAR(120) NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_akq_audit_firma (firma_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jeder Befund einzeln, mit Beleg. Was nicht sicher erkannt ist, heisst
-- UNVERIFIED und zaehlt im Score nicht voll.
CREATE TABLE IF NOT EXISTS akq_befunde (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  audit_id           INT UNSIGNED NOT NULL,
  firma_id           INT UNSIGNED NOT NULL,
  kategorie          VARCHAR(20)  NOT NULL,   -- technik|performance|mobile|ux|design|seo|vertrauen|conversion|experience
  code               VARCHAR(60)  NOT NULL,   -- maschinenlesbar, z. B. tel_nicht_klickbar
  schwere            TINYINT UNSIGNED NOT NULL DEFAULT 2,   -- 1 Hinweis … 5 kritisch
  titel              VARCHAR(255) NOT NULL,
  beschreibung       TEXT         NULL,
  wirkung            TEXT         NULL,       -- vorsichtig formuliert, keine erfundenen Zahlen
  url                VARCHAR(500) NULL,
  messwert           JSON         NULL,
  beleg              TEXT         NULL,       -- konkrete Beobachtung (Selektor, Textauszug, Statuscode)
  screenshot         VARCHAR(120) NULL,
  status             VARCHAR(12)  NOT NULL DEFAULT 'VERIFIED',   -- VERIFIED|UNVERIFIED|VERWORFEN
  erkannt_am         DATETIME     NOT NULL,
  KEY ix_akq_bef_audit (audit_id),
  KEY ix_akq_bef_firma (firma_id, kategorie)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kontaktvorlagen. Nie ein Standardtext: der Fingerabdruck verhindert, dass
-- derselbe Rumpf zweimal rausgeht.
CREATE TABLE IF NOT EXISTS akq_vorlagen (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  firma_id           INT UNSIGNED NOT NULL,
  audit_id           INT UNSIGNED NULL,
  sprache            CHAR(2)      NOT NULL,
  kanal              VARCHAR(20)  NOT NULL DEFAULT 'email',   -- email|brief|telefon|kontaktformular|whatsapp
  betreff            VARCHAR(255) NULL,
  text               MEDIUMTEXT   NOT NULL,
  erzeugt_von        VARCHAR(20)  NOT NULL DEFAULT 'regel',   -- regel|claude|hand
  fingerabdruck      CHAR(64)     NOT NULL,
  status             VARCHAR(20)  NOT NULL DEFAULT 'entwurf', -- entwurf|freigegeben|verworfen|gesendet
  pruefhinweise      TEXT         NULL,                       -- JSON: was die Textpruefung beanstandet hat
  freigegeben_von    VARCHAR(80)  NULL,
  freigegeben_am     DATETIME     NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_akq_vorlage_fp (fingerabdruck),
  KEY ix_akq_vorlage_firma (firma_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jeder Versuch, jemanden zu erreichen. Auch der verhinderte: Er zeigt,
-- dass das Gate gearbeitet hat.
CREATE TABLE IF NOT EXISTS akq_versand (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  firma_id           INT UNSIGNED NOT NULL,
  vorlage_id         INT UNSIGNED NULL,
  kanal              VARCHAR(20)  NOT NULL,
  an                 VARCHAR(190) NULL,
  status             VARCHAR(20)  NOT NULL,       -- gesendet|fehler|bounce|blockiert|von_hand
  compliance         VARCHAR(20)  NOT NULL,
  grund              VARCHAR(255) NULL,
  abmelde_token      CHAR(40)     NULL,
  mail_id            INT UNSIGNED NULL,
  actor              VARCHAR(80)  NOT NULL DEFAULT 'System',
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_akq_abmelde (abmelde_token),
  KEY ix_akq_versand_firma (firma_id),
  KEY ix_akq_versand_zeit (created_at, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Antworten -- vorerst von Hand eingetragen, spaeter aus einem Postfach.
CREATE TABLE IF NOT EXISTS akq_antworten (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  firma_id           INT UNSIGNED NOT NULL,
  versand_id         INT UNSIGNED NULL,
  eingang_am         DATETIME     NOT NULL,
  von                VARCHAR(190) NULL,
  betreff            VARCHAR(255) NULL,
  text               MEDIUMTEXT   NULL,
  klasse             VARCHAR(20)  NOT NULL DEFAULT 'OTHER',
  klasse_quelle      VARCHAR(20)  NOT NULL DEFAULT 'regel',    -- regel|claude|hand
  erledigt           TINYINT(1)   NOT NULL DEFAULT 0,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_akq_antw_firma (firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Die Sperrliste. Wer hier steht, wird nie wieder angesprochen -- auf
-- keinem Kanal, auch nicht nach einer neuen Recherche unter anderem Namen.
CREATE TABLE IF NOT EXISTS akq_sperrliste (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  art                VARCHAR(12)  NOT NULL,       -- domain|email|telefon|firma
  wert               VARCHAR(190) NOT NULL,       -- normalisiert
  grund              VARCHAR(255) NOT NULL,
  quelle             VARCHAR(40)  NOT NULL DEFAULT 'hand',   -- hand|abmeldung|antwort|import
  firma_id           INT UNSIGNED NULL,
  actor              VARCHAR(80)  NOT NULL DEFAULT 'System',
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_akq_sperre (art, wert)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Die Regeln des Gates. Bewusst Daten, nicht Code: Recht aendert sich, und
-- wer es aendert, soll das in der Oberflaeche tun koennen, mit Quelle und
-- Pruefdatum -- nicht per Deploy.
CREATE TABLE IF NOT EXISTS akq_regeln (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  land               CHAR(2)      NOT NULL,       -- DE | IT | *
  kanal              VARCHAR(20)  NOT NULL,       -- email|whatsapp|kontaktformular|brief|telefon|*
  bedingung          VARCHAR(20)  NOT NULL DEFAULT 'ohne',   -- ohne|einwilligung|bestandskunde
  ergebnis           VARCHAR(20)  NOT NULL,       -- CONTACT_ALLOWED|REVIEW_REQUIRED|DO_NOT_EMAIL|UNKNOWN
  begruendung        TEXT         NOT NULL,
  quelle             VARCHAR(500) NULL,
  geprueft_am        DATE         NULL,
  aktiv              TINYINT(1)   NOT NULL DEFAULT 1,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_akq_regel (land, kanal, bedingung)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rechercheauftraege: welches Gebiet, welche Branche, wie weit.
CREATE TABLE IF NOT EXISTS akq_laeufe (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  art                VARCHAR(12)  NOT NULL DEFAULT 'recherche',   -- recherche|audit
  land               CHAR(2)      NOT NULL,
  ebene              VARCHAR(12)  NOT NULL,       -- region|kreis|stadt|plz
  gebiet             VARCHAR(120) NOT NULL,
  branchen           VARCHAR(500) NULL,           -- JSON-Liste, leer = alle
  status             VARCHAR(12)  NOT NULL DEFAULT 'wartet',      -- wartet|laeuft|fertig|fehler|gestoppt
  gefunden           INT UNSIGNED NOT NULL DEFAULT 0,
  neu                INT UNSIGNED NOT NULL DEFAULT 0,
  dubletten          INT UNSIGNED NOT NULL DEFAULT 0,
  fehler             TEXT         NULL,
  angelegt_von       VARCHAR(80)  NOT NULL DEFAULT 'System',
  gestartet_am       DATETIME     NULL,
  beendet_am         DATETIME     NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_akq_lauf_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jeder automatische Schritt, je Firma nachvollziehbar.
CREATE TABLE IF NOT EXISTS akq_protokoll (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  firma_id           INT UNSIGNED NULL,
  lauf_id            INT UNSIGNED NULL,
  schritt            VARCHAR(40)  NOT NULL,
  text               VARCHAR(500) NOT NULL,
  meta               JSON         NULL,
  actor              VARCHAR(80)  NOT NULL DEFAULT 'System',
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_akq_prot_firma (firma_id, id),
  KEY ix_akq_prot_zeit (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Die persoenliche Analyse-Seite (Phase 12): nur mit Schluessel, nie im Index.
CREATE TABLE IF NOT EXISTS akq_analysen (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  firma_id           INT UNSIGNED NOT NULL,
  audit_id           INT UNSIGNED NOT NULL,
  token              CHAR(40)     NOT NULL,
  sprache            CHAR(2)      NOT NULL,
  aktiv              TINYINT(1)   NOT NULL DEFAULT 0,
  gueltig_bis        DATE         NULL,
  aufrufe            INT UNSIGNED NOT NULL DEFAULT 0,
  zuletzt_am         DATETIME     NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_akq_analyse_token (token),
  KEY ix_akq_analyse_firma (firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Die Ausgangsregeln. Vorsichtig gesetzt: Wo Zweifel ist, steht nicht
-- "erlaubt". Stand der Recherche 24.09.2026 -- KEINE Rechtsberatung, vor
-- dem ersten Versand anwaltlich pruefen lassen und hier eintragen.
INSERT IGNORE INTO akq_regeln (land, kanal, bedingung, ergebnis, begruendung, quelle, geprueft_am) VALUES
('DE','email','ohne','DO_NOT_EMAIL','Werbung per E-Mail braucht nach § 7 Abs. 2 Nr. 3 UWG eine vorherige ausdrückliche Einwilligung – auch gegenüber Unternehmen. Eine öffentlich gefundene Adresse ist keine Einwilligung.','https://www.ihk.de/nordwestfalen/recht/rechtsthemen/wettbewerbsrecht/werbung-per-telefon-telefax-oder-e-mail-3614212','2026-09-24'),
('DE','email','einwilligung','CONTACT_ALLOWED','Mit dokumentierter, ausdrücklicher Einwilligung (wer, wann, wofür) zulässig. Abmeldelink in jeder Nachricht.','https://www.ihk.de/nordwestfalen/recht/rechtsthemen/wettbewerbsrecht/werbung-per-telefon-telefax-oder-e-mail-3614212','2026-09-24'),
('DE','email','bestandskunde','REVIEW_REQUIRED','Ausnahme § 7 Abs. 3 UWG nur, wenn alle vier Voraussetzungen erfüllt sind (Adresse aus einem Verkauf, eigene ähnliche Leistung, kein Widerspruch, Hinweis bei Erhebung und jeder Verwendung). Einzeln prüfen.','https://www.ihk.de/nordwestfalen/recht/rechtsthemen/wettbewerbsrecht/werbung-per-telefon-telefax-oder-e-mail-3614212','2026-09-24'),
('DE','whatsapp','ohne','DO_NOT_EMAIL','„Elektronische Post" im Sinne des § 7 UWG umfasst auch SMS, Messenger und WhatsApp – gleiche Regel wie E-Mail.','https://www.ihk.de/nordwestfalen/recht/rechtsthemen/wettbewerbsrecht/werbung-per-telefon-telefax-oder-e-mail-3614212','2026-09-24'),
('DE','kontaktformular','ohne','DO_NOT_EMAIL','Werbung über das Kontaktformular eines Unternehmens wird wie E-Mail-Werbung behandelt.','','2026-09-24'),
('DE','brief','ohne','REVIEW_REQUIRED','Briefwerbung ist grundsätzlich zulässig (§ 7 Abs. 1 UWG), solange kein Widerspruch vorliegt; DSGVO: berechtigtes Interesse abwägen und Informationspflicht (Art. 14) im Brief erfüllen.','https://www.datenschutz.rlp.de/themen/direktwerbung-und-newsletter','2026-09-24'),
('DE','telefon','ohne','REVIEW_REQUIRED','Gegenüber Unternehmen reicht eine mutmaßliche Einwilligung (§ 7 Abs. 2 Nr. 2 UWG) – sie muss sich aus konkreten Umständen ergeben. Je Anruf begründen.','https://www.ihk.de/nordwestfalen/recht/rechtsthemen/wettbewerbsrecht/werbung-per-telefon-telefax-oder-e-mail-3614212','2026-09-24'),
('IT','email','ohne','DO_NOT_EMAIL','Art. 130 Codice Privacy: Werbung per E-Mail nur mit vorheriger Einwilligung – auch an Unternehmensadressen, auch wenn sie öffentlich (z. B. INI-PEC) stehen. Garante, Provv. n. 149 vom 21.04.2021.','https://www.cybersecurity360.it/legal/privacy-dati-personali/uso-delle-pec-pubblicate-online-per-attivita-di-marketing-cosa-devono-imparare-le-imprese/','2026-09-24'),
('IT','email','einwilligung','CONTACT_ALLOWED','Mit dokumentierter vorheriger Einwilligung zulässig. Abmeldelink in jeder Nachricht.','https://www.garanteprivacy.it/home/docweb/-/docweb-display/docweb/2542348','2026-09-24'),
('IT','email','bestandskunde','REVIEW_REQUIRED','Soft-Spam-Ausnahme (Art. 130 Abs. 4) nur für eigene Kunden und ähnliche Leistungen, mit Widerspruchsmöglichkeit. Einzeln prüfen.','https://www.garanteprivacy.it/home/docweb/-/docweb-display/docweb/2542348','2026-09-24'),
('IT','whatsapp','ohne','DO_NOT_EMAIL','Art. 130 Codice Privacy gilt auch für SMS, MMS und Messenger – gleiche Regel wie E-Mail.','https://www.garanteprivacy.it/home/docweb/-/docweb-display/docweb/2542348','2026-09-24'),
('IT','kontaktformular','ohne','DO_NOT_EMAIL','Werbung über fremde Kontaktformulare wie elektronische Werbung behandeln.','','2026-09-24'),
('IT','brief','ohne','REVIEW_REQUIRED','Papierpost mit Absender: Registro Pubblico delle Opposizioni und Informationspflicht (Art. 14 DSGVO) prüfen.','https://www.garanteprivacy.it/home/docweb/-/docweb-display/docweb/2542348','2026-09-24'),
('IT','telefon','ohne','REVIEW_REQUIRED','Telefonwerbung: Nummer gegen das Registro Pubblico delle Opposizioni prüfen; im Zweifel nicht anrufen.','https://www.garanteprivacy.it/home/docweb/-/docweb-display/docweb/2542348','2026-09-24');

-- Grenzen und Schalter. Versand ist aus, bis jemand ihn einschaltet.
INSERT IGNORE INTO settings (skey, svalue) VALUES
('akq_versand_an', '0'),
('akq_stop', '0'),
('akq_limit_tag', '10'),
('akq_limit_stunde', '3'),
('akq_limit_domain_tage', '180'),
('akq_pause_sekunden', '120'),
('akq_fehler_grenze', '3'),
('akq_bounce_grenze', '2');
