-- ===========================================================================
-- 120_marketing_zielgruppen.sql — Zielgruppen und Recherche
-- (Growth Engine, Marketing-Studio Schritt 1, 01.10.2026, Uwe: „Ja, so bauen“).
--
-- Recherche und Texte schreibt Claude über Uwes Claude-Abo (Uwe: „soll hier
-- rüber“) — der Server ruft keine KI auf. Claude liefert über die Worker-Tür
-- (akquise.php, Aktionen marketing_*) Entwürfe ab; freigegeben wird nur in
-- der Verwaltung.
--
-- Nur hinzufügen. Rückweg: beide Tabellen entfernen.
-- ===========================================================================

-- Eine Zielgruppe je Branche und Land. profil = das gültige bzw. neue Profil (JSON),
-- vorher = das zuletzt freigegebene, solange eine Überarbeitung auf Freigabe wartet.
CREATE TABLE IF NOT EXISTS mk_zielgruppen (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branche         VARCHAR(40)  NOT NULL,
  land            CHAR(2)      NOT NULL DEFAULT 'IT',
  titel           VARCHAR(160) NOT NULL,
  profil          MEDIUMTEXT   NOT NULL,
  vorher          MEDIUMTEXT   NULL,
  status          VARCHAR(12)  NOT NULL DEFAULT 'entwurf',   -- entwurf | freigegeben
  quelle          VARCHAR(20)  NOT NULL DEFAULT 'claude',
  freigegeben_am  DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mk_zielgruppe (branche, land),
  KEY ix_mk_zielgruppe_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recherche-Funde: Themen, Trends, Fragen, Wettbewerb, Plattformen — jeweils mit Quellen.
CREATE TABLE IF NOT EXISTS mk_recherche (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  art         VARCHAR(20)  NOT NULL,                   -- thema | trend | frage | wettbewerb | plattform
  titel       VARCHAR(200) NOT NULL,
  text        TEXT         NOT NULL,
  branche     VARCHAR(40)  NOT NULL DEFAULT '',
  land        CHAR(2)      NOT NULL DEFAULT '',
  relevanz    TINYINT UNSIGNED NOT NULL DEFAULT 3,     -- 1 … 5
  quellen     TEXT         NULL,                       -- JSON [{titel, url, datum}]
  fingerabdruck CHAR(40)   NOT NULL,                   -- gegen doppelte Funde
  status      VARCHAR(12)  NOT NULL DEFAULT 'neu',     -- neu | gemerkt | verwendet | verworfen
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mk_recherche_fp (fingerabdruck),
  KEY ix_mk_recherche_status (status, created_at),
  KEY ix_mk_recherche_branche (branche, land)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
