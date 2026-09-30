-- ===========================================================================
-- 110_telegram.sql — Telegram als weiterer Eingang (30.09.2026, Uwe: „ja“ zur
-- ersten Stufe: Interessenten — Start, Sprache, Fragebogen, Preis, Anfrage).
--
-- WARUM NUR EINE TABELLE
--
-- Telegram ist ein Kanal, keine zweite Verwaltung. Was ein Interessent im Bot
-- beantwortet, steht in `bedarf` (dieselbe Tabelle wie der Konfigurator auf
-- der Website), was er absendet, wird eine ganz normale Anfrage mit Kunde,
-- Eingangsmail und Meldung. Hier steht nur, WO im Gespräch der Chat gerade
-- ist — und das so knapp wie möglich:
--
--   * kein Telegram-Benutzername, kein Anzeigename, kein Profilbild
--   * Name und E-Mail nur, solange die Anfrage noch nicht abgeschickt ist
--     (danach stehen sie in der Kundenakte, hier werden sie geleert)
--   * wer 90 Tage nichts schreibt und nie etwas abgeschickt hat, wird
--     vom täglichen Lauf gelöscht (TelegramBot::aufraeumen)
--
-- Doppelt zugestellte Updates fängt webhook_events ab (provider 'telegram',
-- event_id = update_id) — derselbe Mechanismus wie bei Stripe.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS telegram_chats (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  -- Bei privaten Chats ist die Chat-ID die Telegram-Nutzer-ID.
  chat_id             BIGINT       NOT NULL,
  -- NULL, bis er selbst eine Sprache gewählt hat. Danach wechselt sie nur
  -- über den Sprachknopf oder /sprache — nie anhand einzelner Wörter.
  sprache             CHAR(2)      NULL,
  -- Aus der Oberflächensprache von Telegram, nur als Vorschlag.
  sprache_vorschlag   CHAR(2)      NULL,
  -- neu | sprache | menu | frage | ergebnis | ds | name | email | nachricht | pruefen | loeschen
  stand               VARCHAR(16)  NOT NULL DEFAULT 'neu',
  -- Welche Frage des Baukastens gerade dran ist (Index in TelegramBot::fragen()).
  frage               TINYINT UNSIGNED NOT NULL DEFAULT 0,
  -- Der Faden in den Konfigurator: derselbe Datensatz wie auf der Website.
  bedarf_id           INT UNSIGNED NULL,
  -- Mit welchem Menüpunkt der Fragebogen begann (neu | besser | preis).
  einstieg            VARCHAR(12)  NULL,
  -- Wofür gerade Kontaktdaten gesammelt werden: anfrage | beratung
  ziel                VARCHAR(12)  NULL,
  -- Bei einer Beratungsanfrage: logo | 3d | allgemein
  thema               VARCHAR(12)  NULL,
  name                VARCHAR(120) NULL,
  email               VARCHAR(190) NULL,
  nachricht           TEXT         NULL,
  -- Datenschutzhinweis: welche Fassung, wann bestätigt.
  datenschutz_fassung VARCHAR(20)  NULL,
  datenschutz_am      DATETIME     NULL,
  -- Aus dem Start-Link: p_CODE (Partner) oder e_CODE (Empfehlung eines Kunden).
  quelle_code         VARCHAR(40)  NULL,
  customer_id         INT UNSIGNED NULL,
  anfrage_id          INT UNSIGNED NULL,
  -- Die Nachricht mit den Knöpfen, die beim nächsten Klick ersetzt wird.
  nachricht_id        INT UNSIGNED NULL,
  -- Flutbremse je Chat: Minute und Zähler.
  takt_minute         CHAR(12)     NULL,
  takt_zahl           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  letzte_am           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_telegram_chat (chat_id),
  KEY ix_telegram_letzte (letzte_am),
  KEY ix_telegram_kunde (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
