-- ============================================================================
-- 103 — Branchen-Stadt-Seiten und Anzeigen-Entwürfe (28.09.2026, Uwe: Ja zu W1 und W3).
--
-- akq_statistik: anonyme Durchschnittswerte aus den Website-Prüfungen je
--   Branche und Ort (Stadt, sonst Kreis). Nur Gruppen mit genug geprüften
--   Betrieben werden gespeichert -- kein Betrieb ist erkennbar, kein Name
--   steht irgendwo. Nachts neu gerechnet (BranchenStatistik::rechnen).
-- akq_anzeigen: wöchentliche Entwürfe für Facebook-/Instagram- und
--   Google-Anzeigen mit diesen Zahlen. Geschaltet wird nichts -- Uwe
--   kopiert, was er nehmen will, und entscheidet Budget und Start selbst.
-- ============================================================================

CREATE TABLE IF NOT EXISTS akq_statistik (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug         VARCHAR(190) NOT NULL,
  land         CHAR(2)      NOT NULL DEFAULT 'IT',
  branche      VARCHAR(40)  NOT NULL,
  ort          VARCHAR(120) NOT NULL,
  ort_art      VARCHAR(8)   NOT NULL DEFAULT 'stadt',
  n            INT UNSIGNED NOT NULL DEFAULT 0,
  geprueft     INT UNSIGNED NOT NULL DEFAULT 0,
  werte        TEXT         NOT NULL,
  aktualisiert DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_akq_statistik_slug (slug),
  KEY ix_akq_statistik_branche (branche, land)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS akq_anzeigen (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  woche       CHAR(8)      NOT NULL,
  slug        VARCHAR(190) NOT NULL,
  kanal       VARCHAR(10)  NOT NULL,
  sprache     CHAR(2)      NOT NULL DEFAULT 'it',
  texte       MEDIUMTEXT   NOT NULL,
  status      VARCHAR(10)  NOT NULL DEFAULT 'entwurf',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_akq_anzeige (woche, slug, kanal, sprache),
  KEY ix_akq_anzeige_status (status, woche)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
