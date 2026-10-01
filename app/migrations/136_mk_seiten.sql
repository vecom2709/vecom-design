-- ===========================================================================
-- 136_mk_seiten.sql — eine eigene Landingpage je freigegebener Zielgruppe
-- (01.10.2026, Uwe: Ja zu S6). Claude schreibt sie aus dem Profil (Probleme,
-- Einwände, Fragen der Zielgruppe) in der Sprache des Landes; öffentlich wird
-- sie erst nach Uwes Freigabe. „inhalt“ ist die Fassung, die live steht,
-- „entwurf“ die neue, die noch auf das Ja wartet.
-- ===========================================================================
CREATE TABLE IF NOT EXISTS mk_seiten (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  zielgruppe_id   INT UNSIGNED NOT NULL,
  auftrag_id      INT UNSIGNED NULL,
  land            CHAR(2)      NOT NULL DEFAULT 'IT',
  sprache         CHAR(2)      NOT NULL DEFAULT 'it',
  slug            VARCHAR(80)  NOT NULL,
  status          VARCHAR(12)  NOT NULL DEFAULT 'entwurf',   -- entwurf | freigegeben | aus
  inhalt          MEDIUMTEXT   NULL,
  entwurf         MEDIUMTEXT   NULL,
  lesen_de        MEDIUMTEXT   NULL,
  aufrufe         INT UNSIGNED NOT NULL DEFAULT 0,
  freigegeben_am  DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mk_seite_slug (slug),
  UNIQUE KEY uq_mk_seite_zg (zielgruppe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
