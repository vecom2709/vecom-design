-- ===========================================================================
-- 124_marketing_inhalte.sql — Content-Studio (Marketing-Studio Schritt 2,
-- 01.10.2026, Uwe: „organisch und bezahlt, nicht nur Texte … Zielgruppe
-- perfekt, automatisch“; Reihenfolge „Ja, so bauen“).
--
-- Claude schreibt über Uwes Claude-Abo (Auftrag per Knopf, PC holt ab) aus
-- einer FREIGEGEBENEN Zielgruppe Beiträge, Karussells, Reel-Skripte,
-- Telegram- und Profil-Beiträge sowie Meta- und Google-Anzeigen — als
-- Entwurf. Bei der Freigabe bekommt jedes Stück ein eigenes Werbemittel in
-- einer Kampagne und damit einen eigenen Link (/k/kampagne/werbemittel), so
-- dass jeder Klick bis zum Kunden zählt.
--
-- Nur hinzufügen. Rückweg: Tabelle mk_inhalte und die drei neuen Spalten
-- von mk_auftraege entfernen.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS mk_inhalte (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  auftrag_id         INT UNSIGNED NULL,
  zielgruppe_id      INT UNSIGNED NULL,
  branche            VARCHAR(40)  NOT NULL DEFAULT '',
  land               CHAR(2)      NOT NULL DEFAULT 'IT',
  sprache            CHAR(2)      NOT NULL DEFAULT 'it',
  art                VARCHAR(10)  NOT NULL DEFAULT 'organisch',   -- organisch | bezahlt
  format             VARCHAR(20)  NOT NULL,                       -- MkInhalt::FORMATE
  plattform          VARCHAR(20)  NOT NULL,                       -- MkKampagne::PLATTFORMEN
  titel              VARCHAR(160) NOT NULL,
  felder             MEDIUMTEXT   NOT NULL,                       -- JSON, je Format
  bildidee           TEXT         NULL,                           -- für Bilder und Videos (Schritt 3)
  begruendung        TEXT         NULL,                           -- warum es bei dieser Zielgruppe trägt
  fund_ids           VARCHAR(255) NULL,                           -- benutzte Recherche-Funde, Komma
  kampagne_id        INT UNSIGNED NULL,
  creative_id        INT UNSIGNED NULL,                           -- mk_creatives, ab Freigabe
  status             VARCHAR(16)  NOT NULL DEFAULT 'entwurf',     -- entwurf | freigegeben | veroeffentlicht | verworfen
  freigegeben_am     DATETIME     NULL,
  veroeffentlicht_am DATETIME     NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_mk_inhalt_status (status, id),
  KEY ix_mk_inhalt_zielgruppe (zielgruppe_id),
  KEY ix_mk_inhalt_kampagne (kampagne_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aufträge können jetzt auch „Inhalte schreiben“ sein.
ALTER TABLE mk_auftraege
  ADD COLUMN IF NOT EXISTS art        VARCHAR(12) NOT NULL DEFAULT 'recherche' AFTER id,   -- recherche | inhalte
  ADD COLUMN IF NOT EXISTS parameter  TEXT NULL AFTER status,
  ADD COLUMN IF NOT EXISTS inhalte    SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER funde;
