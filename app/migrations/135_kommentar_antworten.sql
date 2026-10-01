-- ===========================================================================
-- 135_kommentar_antworten.sql — „Kommentiere STICHWORT“ → automatische Nachricht
-- (01.10.2026, Uwe: Ja zu S1). Je Kommentar genau eine Antwort: der eindeutige
-- Schlüssel verhindert, dass derselbe Kommentar zweimal beantwortet wird.
-- ===========================================================================
CREATE TABLE IF NOT EXISTS mk_kommentare (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plattform     VARCHAR(12)  NOT NULL,                 -- instagram | facebook
  kommentar_id  VARCHAR(64)  NOT NULL,
  beitrag_id    VARCHAR(64)  NOT NULL DEFAULT '',
  stichwort     VARCHAR(20)  NOT NULL,
  land          CHAR(2)      NOT NULL DEFAULT 'IT',
  status        VARCHAR(12)  NOT NULL DEFAULT 'neu',   -- neu | beantwortet | fehler
  grund         VARCHAR(255) NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mk_kommentar (plattform, kommentar_id),
  KEY ix_mk_kommentar_zeit (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
