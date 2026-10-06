-- ===========================================================================
-- 195_akquise_toene.sql — Akquise-CRM Modul D-2: Töne über den PC (06.10.2026).
-- Uwe: KI „weiter über den PC-Worker“. Ein Klick auf „Kürzer“, „Lockerer“
-- oder „Professioneller“ legt einen Auftrag in mk_auftraege an (art = ton).
-- Der PC holt ihn alle fünf Minuten ab, Claude Code schreibt um, ohne
-- Werkzeuge, und liefert hierher. Hier steht nur der Vorschlag — übernommen
-- wird er von Hand, gesendet wird nichts.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS akq_textvorschlaege (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  auftrag_id     INT UNSIGNED NOT NULL,                  -- mk_auftraege.id
  firma_id       INT UNSIGNED NOT NULL,
  kanal          VARCHAR(12)  NOT NULL,                  -- email oder whatsapp
  ton            VARCHAR(16)  NOT NULL,                  -- kuerzer, lockerer, professioneller
  betreff_vorher VARCHAR(300) NULL,
  text_vorher    TEXT         NOT NULL,
  betreff        VARCHAR(300) NULL,
  text           TEXT         NULL,
  status         VARCHAR(12)  NOT NULL DEFAULT 'wartet', -- wartet, fertig, abgelehnt, uebernommen, verworfen
  grund          VARCHAR(500) NULL,                      -- warum abgelehnt
  erstellt_von   VARCHAR(80)  NOT NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fertig_am      DATETIME     NULL,
  UNIQUE KEY uq_akq_tv_auftrag (auftrag_id),
  KEY ix_akq_tv_firma (firma_id, kanal, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
