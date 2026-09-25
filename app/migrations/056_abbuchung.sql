-- ===========================================================================
-- 056_abbuchung.sql — Monatsraten automatisch abbuchen (Phase 2, 25.09.2026)
--
-- Bisher bekam der Kunde jeden Monat eine Mail mit Zahlungslink und musste
-- selbst bezahlen. Jetzt kann er einmal Karte oder SEPA-Lastschrift bei
-- Stripe hinterlegen; abgebucht wird von hier aus, Rate fuer Rate.
--
-- Bewusst KEIN Stripe-Abonnement: Laufzeit, Kuendigung und Enddatum rechnet
-- Abo.php, und so bleibt es. Stripe bucht nur ab, was hier als Rate steht.
--
-- customers.stripe_kunde: die Stripe-Kundennummer (cus_...). Noetig, weil ein
--                         hinterlegtes Zahlungsmittel an einem Stripe-Kunden
--                         haengt.
-- abos.zahlmittel_*:      das hinterlegte Zahlungsmittel (pm_...), seine Art
--                         (card | sepa_debit) und was der Kunde wiedererkennt
--                         ("Visa •••• 4242"). Leer = wie bisher per Link.
-- Wiederholbar: IF NOT EXISTS.
-- ===========================================================================

ALTER TABLE customers ADD COLUMN IF NOT EXISTS stripe_kunde VARCHAR(64) NULL;
ALTER TABLE abos ADD COLUMN IF NOT EXISTS zahlmittel_id VARCHAR(64) NULL AFTER extern_id;
ALTER TABLE abos ADD COLUMN IF NOT EXISTS zahlmittel_art VARCHAR(20) NULL AFTER zahlmittel_id;
ALTER TABLE abos ADD COLUMN IF NOT EXISTS zahlmittel_text VARCHAR(80) NULL AFTER zahlmittel_art;
ALTER TABLE abos ADD COLUMN IF NOT EXISTS zahlmittel_am DATETIME NULL AFTER zahlmittel_text;
