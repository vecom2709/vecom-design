-- ===========================================================================
-- 212_autobuild_betrieb.sql — AutoBuild Phase 10: Wartung, Regeln, Betrieb
-- (07.10.2026, Uwe: „mache mit den offenen Phasen weiter“ zu den Vorschlägen 1–10).
--
-- projects.betrieb_seit       : ab hier „im Betrieb“ (Übergabe freigegeben bzw. erster Livegang)
-- projects.abweichung*        : täglicher Vergleich Live-Seite ↔ freigegebene Fassung
-- projects.seitencheck*       : wöchentliche Seitenprüfung (Links, Bilder, Impressum, Jahr …)
-- projects.bau_grenze         : Kostenwächter — eigene Grenze für Bauläufe (NULL = Vorgabe)
-- projekt_wuensche.aufwand_min / kontingent_monat : Kontingent der Betreuung
-- projekt_versionen.review_maengel : Mängel des Reviewers als Liste (für „aus Fehlern lernen“)
-- bau_regeln / bau_regeln_log / bau_regel_vorschlaege : Bauregeln mit Versionen
--
-- Nur hinzufügen.
-- ===========================================================================

ALTER TABLE projects
  ADD COLUMN IF NOT EXISTS betrieb_seit    DATETIME     NULL,
  ADD COLUMN IF NOT EXISTS abweichung      MEDIUMTEXT   NULL,
  ADD COLUMN IF NOT EXISTS abweichung_am   DATETIME     NULL,
  ADD COLUMN IF NOT EXISTS seitencheck     MEDIUMTEXT   NULL,
  ADD COLUMN IF NOT EXISTS seitencheck_am  DATETIME     NULL,
  ADD COLUMN IF NOT EXISTS bau_grenze      INT UNSIGNED NULL;

ALTER TABLE projekt_wuensche
  ADD COLUMN IF NOT EXISTS aufwand_min      SMALLINT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS kontingent_monat CHAR(7)           NULL;

ALTER TABLE projekt_versionen
  ADD COLUMN IF NOT EXISTS review_maengel TEXT NULL;

CREATE TABLE IF NOT EXISTS bau_regeln (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  text        VARCHAR(500) NOT NULL,
  aktiv       TINYINT(1)   NOT NULL DEFAULT 1,
  quelle      VARCHAR(12)  NOT NULL DEFAULT 'uwe',     -- uwe | vorschlag
  von         VARCHAR(120) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  geaendert_am DATETIME    NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bau_regeln_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,   -- = Versionsnummer der Bauregeln
  regel_id    INT UNSIGNED NOT NULL,
  aktion      VARCHAR(12)  NOT NULL,                     -- neu | geaendert | aus | an
  text_vorher VARCHAR(500) NULL,
  text        VARCHAR(500) NULL,
  von         VARCHAR(120) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_regel (regel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bau_regel_vorschlaege (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  schluessel   VARCHAR(40)  NOT NULL,
  text         VARCHAR(500) NOT NULL,
  belege       TEXT         NULL,                       -- JSON: [{projekt, fassung, mangel}]
  status       VARCHAR(12)  NOT NULL DEFAULT 'offen',   -- offen | angenommen | abgelehnt
  entschieden_von VARCHAR(120) NULL,
  entschieden_am  DATETIME  NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_regel_vorschlag (schluessel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
