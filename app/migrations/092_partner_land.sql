-- ============================================================================
-- 092 — Land des Partners für Stripe (28.09.2026, Uwe: „Die Partner können in
-- Stripe ihr Land nur Italien, obwohl sie deutsch sind“).
--
-- Stripe legt das Land eines verbundenen Kontos beim Anlegen fest -- ohne
-- Angabe gilt das Land der Plattform (Italien), und danach lässt es sich
-- nicht mehr ändern. partner.land: vom Partner gewählt, bevor das Konto
-- entsteht. partner.stripe_land: das Land, mit dem das Konto bei Stripe
-- wirklich angelegt ist (zum Vergleichen, ohne Stripe jedes Mal zu fragen).
-- Wiederholbar: ADD COLUMN → 1060 übersprungen.
-- ============================================================================

ALTER TABLE partner ADD COLUMN land CHAR(2) NULL;
ALTER TABLE partner ADD COLUMN stripe_land CHAR(2) NULL;
