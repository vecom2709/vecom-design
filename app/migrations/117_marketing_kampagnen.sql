-- ===========================================================================
-- 117_marketing_kampagnen.sql — Kampagnen-Links und Zuordnung
-- (Growth Engine Phase 3, 30.09.2026, Uwe: „ja“).
--
-- Eine Kampagne ist ein eigener Link /k/CODE (optional /k/CODE/WERBEMITTEL)
-- für Beiträge, Anzeigen, Newsletter und Flyer. Ein Klick darauf legt einen
-- Besuch in derselben Spur an wie ein Partnerlink (keine zweite Tracking-
-- Welt): spur_besuche bekommt kampagne_id/creative_id, partner_id darf leer
-- sein. Dieselben Regeln: keine IP im Klartext, kein Fingerprint, merken über
-- den Besuch hinaus nur mit Einwilligung.
--
-- Nur hinzufügen, nichts löschen. Rückweg: die drei neuen Tabellen und die
-- neuen Spalten entfernen; partner_id wieder NOT NULL, sobald keine Zeile
-- ohne Partner mehr existiert (DELETE … WHERE partner_id IS NULL).
-- ===========================================================================

CREATE TABLE IF NOT EXISTS mk_kampagnen (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(24)  NOT NULL,                 -- /k/CODE, klein, a-z 0-9 -
  name        VARCHAR(120) NOT NULL,
  plattform   VARCHAR(20)  NOT NULL DEFAULT 'sonstige',
  ziel        VARCHAR(190) NOT NULL DEFAULT '/',     -- eigene Seite, nur Pfad
  status      VARCHAR(12)  NOT NULL DEFAULT 'aktiv', -- aktiv | pausiert | beendet
  notiz       VARCHAR(500) NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mk_kampagne_code (code),
  KEY ix_mk_kampagne_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Werbemittel einer Kampagne (Reel 3, Story, Flyer Sciacca …): eigener Link /k/CODE/C.
CREATE TABLE IF NOT EXISTS mk_creatives (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kampagne_id  INT UNSIGNED NOT NULL,
  code         VARCHAR(12)  NOT NULL,
  name         VARCHAR(120) NOT NULL,
  art          VARCHAR(20)  NOT NULL DEFAULT 'beitrag',
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mk_creative (kampagne_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kosten je Kampagne. ausgabe_id verbindet mit einem Beleg (Ausgaben „Werbung“),
-- damit derselbe Betrag im Überblick nicht doppelt zählt.
CREATE TABLE IF NOT EXISTS mk_kosten (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kampagne_id   INT UNSIGNED NOT NULL,
  datum         DATE         NOT NULL,
  betrag_cents  INT UNSIGNED NOT NULL DEFAULT 0,
  notiz         VARCHAR(200) NOT NULL DEFAULT '',
  ausgabe_id    INT UNSIGNED NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_mk_kosten_kampagne (kampagne_id, datum),
  UNIQUE KEY uq_mk_kosten_ausgabe (ausgabe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tageszahlen je Kampagne und Werbemittel, wenn die Einzeldaten gelöscht sind (wie spur_tage je Partner).
CREATE TABLE IF NOT EXISTS mk_tage (
  kampagne_id   INT UNSIGNED NOT NULL,
  creative_id   INT UNSIGNED NOT NULL DEFAULT 0,
  tag           DATE         NOT NULL,
  event_type    VARCHAR(40)  NOT NULL,
  anzahl        INT UNSIGNED NOT NULL DEFAULT 0,
  besucher      INT UNSIGNED NOT NULL DEFAULT 0,
  betrag_cents  BIGINT       NOT NULL DEFAULT 0,
  PRIMARY KEY (kampagne_id, creative_id, tag, event_type),
  KEY ix_mk_tage_tag (tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE spur_besuche
  MODIFY COLUMN partner_id INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS kampagne_id INT UNSIGNED NULL AFTER partner_id,
  ADD COLUMN IF NOT EXISTS creative_id INT UNSIGNED NULL AFTER kampagne_id,
  ADD INDEX IF NOT EXISTS ix_spur_kampagne (kampagne_id, start_am);

ALTER TABLE spur_ereignisse
  MODIFY COLUMN partner_id INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS kampagne_id INT UNSIGNED NULL AFTER partner_id,
  ADD COLUMN IF NOT EXISTS creative_id INT UNSIGNED NULL AFTER kampagne_id,
  ADD INDEX IF NOT EXISTS ix_spe_kampagne (kampagne_id, created_at);
