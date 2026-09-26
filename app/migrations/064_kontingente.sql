-- ===========================================================================
-- 064_kontingente.sql — die Kontingente je Kunde (26.09.2026)
--
-- kontingente: was dieser Kunden-Account im KAS haben darf, als JSON
--              ({"max_domain":4,"max_mail_account":…}) -- gerecht aus dem
--              Reseller-Vertrag geteilt (Hosting::kontingentAus) und beim
--              Anlegen des Auftrags festgehalten. Der Speicher steht weiter
--              in speicher_mb. Anlass: add_account setzt ALLES, was nicht
--              uebergeben wird, auf 0 -- ohne diese Werte haette ein neuer
--              Kunde keine Domain und kein Postfach anlegen koennen.
-- Wiederholbar: IF NOT EXISTS.
-- ===========================================================================

ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS kontingente TEXT NULL AFTER speicher_mb;
