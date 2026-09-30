-- ===========================================================================
-- 111_telegram_kunden.sql — Telegram mit dem Kundenkonto verbinden (30.09.2026,
-- Uwe: „mache alle automatisch“, Stufe 2).
--
-- WARUM NICHT customer_id
--
-- telegram_chats.customer_id sagt seit Stufe 1 nur: „Aus diesem Chat wurde
-- eine Anfrage an diesen Kunden geschickt.“ Das beweist nichts — jeder kann
-- im Bot eine fremde E-Mail-Adresse eintippen. Projektstand, Nachrichten und
-- Dateien gibt es deshalb nur für einen Chat, der über einen Einmal-Link aus
-- dem persönlichen Bereich des Kunden verbunden wurde: kunde_verbunden.
--
-- Ein Kunde hat höchstens einen verbundenen Chat (eindeutiger Schlüssel).
-- Verbindet er ein neues Gerät/Konto, löst sich das alte.
-- ===========================================================================

ALTER TABLE telegram_chats
  ADD COLUMN IF NOT EXISTS kunde_verbunden INT UNSIGNED NULL AFTER customer_id,
  ADD COLUMN IF NOT EXISTS verbunden_am    DATETIME     NULL AFTER kunde_verbunden,
  -- Kurze Hinweise bei neuer Post von Vecom Design (abschaltbar im Bot).
  ADD COLUMN IF NOT EXISTS benachrichtigen TINYINT(1)   NOT NULL DEFAULT 1 AFTER verbunden_am,
  ADD COLUMN IF NOT EXISTS hinweis_am      DATETIME     NULL AFTER benachrichtigen,
  -- Eine Datei, die gerade auf „Ja, zum Projekt“ wartet (nur die Telegram-Kennung).
  ADD COLUMN IF NOT EXISTS datei_id        VARCHAR(200) NULL AFTER hinweis_am,
  ADD COLUMN IF NOT EXISTS datei_name      VARCHAR(200) NULL AFTER datei_id,
  ADD COLUMN IF NOT EXISTS datei_groesse   INT UNSIGNED NULL AFTER datei_name,
  ADD UNIQUE KEY IF NOT EXISTS uq_telegram_kunde_verbunden (kunde_verbunden);

-- Einmal-Codes für den Verbindungslink t.me/BOT?start=k_CODE.
-- Gespeichert wird nur der SHA-256 des Codes: Wer die Tabelle liest, kann
-- damit keinen Chat verbinden.
CREATE TABLE IF NOT EXISTS telegram_codes (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id  INT UNSIGNED NOT NULL,
  code_hash    CHAR(64)     NOT NULL,
  gueltig_bis  DATETIME     NOT NULL,
  benutzt_am   DATETIME     NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_telegram_code (code_hash),
  KEY ix_telegram_code_kunde (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
