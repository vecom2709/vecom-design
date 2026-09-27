-- ============================================================================
-- 086 — Folge-Mails an Betriebe mit bestätigter Einwilligung (27.09.2026,
-- Uwe: „Texte freigeben, dann automatisch“).
--
-- akq_folge_vorlagen: fünf Schritte je Sprache. Jede Änderung setzt den Text
-- zurück auf „entwurf“ -- verschickt wird nur, was Uwe in genau dieser Fassung
-- freigegeben hat.
-- akq_folgen: eine Zeile je Betrieb. schritt = zuletzt WIRKLICH verschickter
-- Schritt; simuliert = zuletzt im Testbetrieb durchgespielter Schritt (der
-- Testbetrieb schiebt die echte Folge nie weiter).
-- Wiederholbar: IF NOT EXISTS.
-- ============================================================================

CREATE TABLE IF NOT EXISTS akq_folge_vorlagen (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  schritt          TINYINT UNSIGNED NOT NULL,          -- 1..5
  sprache          CHAR(2)      NOT NULL,
  betreff          VARCHAR(190) NOT NULL,
  text             TEXT         NOT NULL,
  status           VARCHAR(12)  NOT NULL DEFAULT 'entwurf', -- entwurf | freigegeben
  fassung          INT UNSIGNED NOT NULL DEFAULT 1,
  freigegeben_von  VARCHAR(80)  NULL,
  freigegeben_am   DATETIME     NULL,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_akq_folge_vorlage (schritt, sprache)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS akq_folgen (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  firma_id         INT UNSIGNED NOT NULL,
  sprache          CHAR(2)      NOT NULL DEFAULT 'it',
  status           VARCHAR(12)  NOT NULL DEFAULT 'laeuft', -- laeuft | pausiert | beendet
  schritt          TINYINT UNSIGNED NOT NULL DEFAULT 0,
  simuliert        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  naechst_am       DATETIME     NULL,
  letzte_am        DATETIME     NULL,
  grund            VARCHAR(255) NULL,
  gestartet_am     DATETIME     NOT NULL,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_akq_folge_firma (firma_id),
  KEY ix_akq_folge_faellig (status, naechst_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
