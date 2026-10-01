-- ===========================================================================
-- 123_telegram_webcheck.sql — Telegram Growth Engine, Schritt T3: Website-
-- Check im Chat (01.10.2026, Uwe: „Ja mach T3“).
--
-- telegram_chats.website: die zuletzt im Bot geprüfte Adresse. Sie fährt
-- mit, wenn derselbe Chat danach eine Anfrage schickt (dann steht sie in
-- der Anfrage), und wird geleert wie Name und E-Mail: nach dem Absenden
-- sofort, sonst nach 30 Tagen (TelegramBot::aufraeumen). Gespeichert wird
-- nur die Adresse, nicht das Ergebnis — das lässt sich jederzeit neu messen.
--
-- Nur hinzufügen. Rückweg: Spalte entfernen.
-- ===========================================================================

ALTER TABLE telegram_chats
  ADD COLUMN website VARCHAR(190) NULL AFTER email;
