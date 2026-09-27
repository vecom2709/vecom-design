-- ============================================================================
-- 094 — Fällige Stripe-Angaben am Partner (28.09.2026, Uwe mit Bildschirmfoto:
-- Stripe meldet „Geben Sie ein Ausweisdokument an … Bald fällig —
-- Auszahlungen werden in Kürze ausgesetzt“, die Partnerseite zeigte aber
-- alles grün).
--
-- Ein Konto kann Überweisungen empfangen UND trotzdem Angaben schulden, die
-- bis zu einer Frist nachzureichen sind. Bisher kannte die Datenbank nur die
-- Zahl. Jetzt auch:
--   stripe_faellig  welche Angaben (Stripes Schlüssel, z. B.
--                   individual.verification.document), damit „Identität
--                   bestätigt“ nicht grün ist, solange ein Ausweis fehlt
--   stripe_frist    bis wann (requirements.current_deadline)
-- Nur neue Spalten, NULL erlaubt. Wiederholbar: ADD COLUMN → 1060 übersprungen.
-- ============================================================================

ALTER TABLE partner ADD COLUMN stripe_faellig VARCHAR(600) NULL;
ALTER TABLE partner ADD COLUMN stripe_frist DATETIME NULL;
