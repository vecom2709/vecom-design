-- ===========================================================================
-- 062_weiterleitung_sperre.sql — Weiterleitungen und Sperre nach Vertragsende
-- (25.09.2026, aus der KAS-Schnittstelle)
--
-- weiterleitungen: weitere Adressen, die bei kontakt@ ankommen sollen
--                  ("info, prenotazioni") -- aus dem Fragebogen, beim
--                  Einrichten per add_mailforward angelegt.
-- gesperrt_am:     wann der KAS-Zugang des Kunden nach Vertragsende gesperrt
--                  wurde (update_account, kas_access_forbidden). Gesperrt,
--                  nicht geloescht -- und nur auf Uwes Klick.
-- Wiederholbar: IF NOT EXISTS.
-- ===========================================================================

ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS weiterleitungen VARCHAR(300) NULL AFTER mail;
ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS gesperrt_am DATETIME NULL;
