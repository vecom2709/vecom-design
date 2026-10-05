-- ===========================================================================
-- 189_automationen.sql — Automation Center (Phase 8, 06.10.2026).
-- Eine Zeile je Regel aus Automation::REGELN: Schalter und letzter Lauf.
-- Keine Zeile = an, wie vor dem Umbau. Der Not-Aus steht in settings
-- (auto_notaus, auto_notaus_am, auto_notaus_von).
-- Kein Protokoll je Lauf: Der Cron läuft jede Minute mit rund 80 Regeln —
-- das wären über 100.000 Zeilen am Tag für „lief, nichts zu tun“.
-- Fehler sieht man trotzdem: der letzte Fehler mit Datum bleibt stehen.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS automationen (
  regel         VARCHAR(40)  NOT NULL PRIMARY KEY,
  aus           TINYINT(1)   NOT NULL DEFAULT 0,
  aus_von       INT UNSIGNED NULL,
  aus_am        DATETIME     NULL,
  zuletzt_am    DATETIME     NULL,
  dauer_ms      INT UNSIGNED NULL,
  ergebnis      VARCHAR(255) NULL,
  fehler        VARCHAR(255) NULL,
  fehler_folge  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  fehler_am     DATETIME     NULL,
  laeufe        INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
