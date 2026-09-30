-- ===========================================================================
-- 112_telegram_verwaltung.sql — Uwes eigenes Telegram als Fenster zur
-- Verwaltung (30.09.2026, Stufe 3).
--
-- Dieselben Einmal-Codes wie bei Kunden, nur für einen Zugang der Verwaltung
-- (users.id). Rechte werden bei JEDEM Aufruf neu geprüft (aktiv + Rolle
-- admin) — die gespeicherte Verbindung allein öffnet nichts.
-- ===========================================================================

ALTER TABLE telegram_codes
  MODIFY customer_id INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS user_id INT UNSIGNED NULL AFTER customer_id;

ALTER TABLE telegram_chats
  ADD COLUMN IF NOT EXISTS admin_verbunden INT UNSIGNED NULL AFTER verbunden_am,
  ADD UNIQUE KEY IF NOT EXISTS uq_telegram_admin_verbunden (admin_verbunden);
