-- ===========================================================================
-- 137_telegram_umfragen.sql — kurze Umfragen im Telegram-Kanal
-- (01.10.2026, Uwe: „Alles“ — Vorschlag 6).
--
-- Im Kanal sind Umfragen immer anonym: Telegram meldet dem Bot nur die Zahl
-- der Stimmen je Antwort (Update „poll“). Genau das steht hier — Frage,
-- Antworten, Stimmen je Antwort, gesamt. Keine Person, keine Kennung.
--
-- poll_id: Kennung der Umfrage bei Telegram (für die Updates).
-- Nur hinzufügen. Rückweg: Tabelle entfernen.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS tg_umfragen (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  poll_id     VARCHAR(64)  NOT NULL,
  chat_id     VARCHAR(32)  NOT NULL,
  message_id  INT UNSIGNED NOT NULL,
  frage       VARCHAR(320) NOT NULL,
  optionen    TEXT         NOT NULL,
  stimmen     TEXT         NULL,
  gesamt      INT UNSIGNED NOT NULL DEFAULT 0,
  status      VARCHAR(12)  NOT NULL DEFAULT 'offen',
  angelegt_am DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  beendet_am  DATETIME     NULL,
  UNIQUE KEY uq_tg_umfragen_poll (poll_id),
  KEY ix_tg_umfragen_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
