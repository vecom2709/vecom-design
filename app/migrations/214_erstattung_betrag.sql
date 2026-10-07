-- 214 — Erstattungen mit Betrag (Prüfung 07.10.2026, Punkt 14/49).
-- Bisher stand bei einer Stripe-Erstattung nur der Status; wie viel und wann
-- zurückging, fehlte — die Kassenliste zählte erstattete Zahlungen gar nicht.
ALTER TABLE payments
  ADD COLUMN IF NOT EXISTS erstattet_cents INT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS erstattet_am    DATETIME NULL DEFAULT NULL;

-- Registro Pubblico delle Opposizioni je Nummer (Prüfung 07.10.2026, Punkt 21):
-- an welchem Tag Uwe bestätigt hat, dass diese italienische Nummer nicht eingetragen ist.
-- Ein Werbeanruf darf nur auf einer Prüfung beruhen, die höchstens 15 Tage alt ist.
ALTER TABLE akq_firmen
  ADD COLUMN IF NOT EXISTS rpo_frei_am DATE NULL DEFAULT NULL;
