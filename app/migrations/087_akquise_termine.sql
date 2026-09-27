-- ============================================================================
-- 087 — Terminbuchung (27.09.2026, Uwe: „Terminbuchung“ mit eigenem Kalender).
--
-- Freie Zeiten entstehen aus dem Wochenplan in den Einstellungen (settings:
-- akq_termin_plan …), nicht aus einer Tabelle voller leerer Termine. Hier steht
-- nur, was gebucht wurde. Doppelt vergeben kann die Datenbank selbst nicht:
-- belegt ist 1 solange der Termin gilt und NULL nach einer Absage -- der
-- eindeutige Schlüssel (beginn, belegt) lässt beliebig viele Absagen, aber nur
-- eine gültige Buchung je Uhrzeit zu.
-- Wiederholbar: IF NOT EXISTS.
-- ============================================================================

CREATE TABLE IF NOT EXISTS akq_termine (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  token         CHAR(32)     NOT NULL,              -- Adresse zum Ansehen und Absagen
  beginn        DATETIME     NOT NULL,              -- Ortszeit (Europe/Rome)
  ende          DATETIME     NOT NULL,
  belegt        TINYINT(1)   NULL DEFAULT 1,
  status        VARCHAR(12)  NOT NULL DEFAULT 'gebucht', -- gebucht | abgesagt | erledigt
  abgesagt_von  VARCHAR(12)  NULL,                  -- kunde | vecom
  name          VARCHAR(120) NULL,
  firma         VARCHAR(190) NULL,
  email         VARCHAR(190) NULL,
  telefon       VARCHAR(40)  NULL,
  sprache       CHAR(2)      NOT NULL DEFAULT 'it',
  thema         VARCHAR(20)  NOT NULL,
  art           VARCHAR(10)  NOT NULL DEFAULT 'telefon', -- telefon | video
  nachricht     VARCHAR(500) NULL,
  firma_id      INT UNSIGNED NULL,
  erinnert_am   DATETIME     NULL,
  ip_hash       CHAR(64)     NULL,
  created_at    DATETIME     NOT NULL,
  UNIQUE KEY uq_akq_termin_token (token),
  UNIQUE KEY uq_akq_termin_slot (beginn, belegt),
  KEY ix_akq_termin_zeit (status, beginn),
  KEY ix_akq_termin_firma (firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
