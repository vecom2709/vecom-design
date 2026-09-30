-- ===========================================================================
-- 119_telegram_wachstum.sql — Telegram Growth Engine, Schritt T1: Messung
-- (01.10.2026, Uwe: „Ja mach“).
--
-- KEINE ZWEITE TRACKING-WELT
--
-- Wer den Bot über einen Kampagnenlink startet (t.me/BOT?start=m_CODE),
-- bekommt einen Besuch in derselben Spur wie ein Klick auf /k/CODE
-- (spur_besuche, Quelle „telegram“). Preisrechner, Anfrage, Angebot, Auftrag
-- und Zahlung hängen sich daran wie auf der Website — die Kampagne zeigt
-- Telegram-Leads ohne eigene Rechnung. telegram_chats merkt sich dafür nur
-- die Nummer dieses Besuchs.
--
-- Was die Spur nicht kennt, steht in zwei kleinen Tabellen:
--   tg_einladungen  eigene Einladungslinks des Kanals, je Kampagne einer —
--                   Telegram meldet bei einem Beitritt, über welchen Link.
--   tg_tage         Tageszahlen ohne Personenbezug (neue Bot-Nutzer je
--                   Quelle, aktive, wiederkehrende, Kanal-Beitritte und
--                   -Austritte je Quelle, Mitgliederstand). Kein Chat, keine
--                   Kennung — sie überleben das Löschen der Chats nach 90 Tagen.
--
-- Nur hinzufügen, nichts löschen. Rückweg: die zwei Tabellen und die Spalte
-- entfernen.
-- ===========================================================================

ALTER TABLE telegram_chats
  ADD COLUMN spur_besuch_id BIGINT UNSIGNED NULL AFTER quelle_code;

CREATE TABLE IF NOT EXISTS tg_einladungen (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kampagne_id  INT UNSIGNED NULL,
  name         VARCHAR(32)  NOT NULL,                -- so heißt der Link bei Telegram (höchstens 32 Zeichen)
  link         VARCHAR(190) NOT NULL,                -- https://t.me/+…
  beitritte    INT UNSIGNED NOT NULL DEFAULT 0,
  austritte    INT UNSIGNED NOT NULL DEFAULT 0,
  aktiv        TINYINT(1)   NOT NULL DEFAULT 1,
  erstellt_von VARCHAR(80)  NOT NULL DEFAULT '',
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tg_einladung_link (link),
  KEY ix_tg_einladung_kampagne (kampagne_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tg_tage (
  tag     DATE         NOT NULL,
  art     VARCHAR(20)  NOT NULL,                     -- bot_neu | bot_aktiv | bot_wieder | bot_start | kanal_bei | kanal_aus | kanal_stand
  quelle  VARCHAR(40)  NOT NULL DEFAULT '',          -- m_CODE, p_CODE, e_CODE, kanal, web … ('' = ohne)
  zahl    INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (tag, art, quelle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
