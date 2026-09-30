-- ===========================================================================
-- 121_telegram_funnel.sql — Telegram Growth Engine, Schritt T2: Dashboard
-- (01.10.2026, Uwe: „Ja mach T2“).
--
-- Zwei Kleinigkeiten, damit der Funnel „Quelle → Telegram → Wegweiser →
-- Website-Check → Interesse → Preisrechner → Beratung → Lead → Kunde“ nur
-- aus Gemessenem besteht:
--
--   telegram_chats.stufen  welche Stufen dieser Chat schon erreicht hat
--                          (kommagetrennt). Jede Stufe zählt je Chat genau
--                          einmal in tg_tage — wer dreimal den Preisrechner
--                          öffnet, ist ein Interessent, nicht drei.
--   tg_herkunft            welcher Kunde über Telegram kam (Bot oder
--                          Mini-App) und über welche Quelle. Daran hängen
--                          „Kunden aus Telegram“ und „Umsatz aus Telegram“.
--                          Die erste Herkunft zählt, wie beim Partner.
--
-- Die schon abgeschickten Bot-Anfragen werden übernommen (ihr Chat bleibt
-- bestehen, solange er eine Anfrage hat) — so fängt die Zählung nicht bei
-- null an, wo es schon Leads gibt.
--
-- Nur hinzufügen. Rückweg: Spalte und Tabelle entfernen.
-- ===========================================================================

ALTER TABLE telegram_chats
  ADD COLUMN stufen VARCHAR(120) NOT NULL DEFAULT '' AFTER spur_besuch_id;

CREATE TABLE IF NOT EXISTS tg_herkunft (
  customer_id  INT UNSIGNED NOT NULL PRIMARY KEY,
  quelle       VARCHAR(40)  NOT NULL DEFAULT '',
  weg          VARCHAR(8)   NOT NULL DEFAULT 'bot',   -- bot | app (Mini-App im Kanal)
  anfrage_id   INT UNSIGNED NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_tg_herkunft_quelle (quelle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tg_herkunft (customer_id, quelle, weg, anfrage_id, created_at)
  SELECT customer_id, COALESCE(quelle_code, ''), 'bot', anfrage_id, created_at
    FROM telegram_chats
   WHERE customer_id IS NOT NULL AND anfrage_id IS NOT NULL;
