-- ============================================================================
-- 093 — Stand der Stripe-Verifizierung am Partner (28.09.2026, Uwes Vorgabe
-- „Stripe-Connect-Partner-Verifizierung für mehrere Länder“).
--
-- Bisher kannte die Datenbank nur stripe_bereit (Überweisungen aktiv). Jetzt
-- merkt sie sich, was Stripe beim Abruf oder per Webhook account.updated
-- meldet -- damit Partnerbereich und Akte den Stand zeigen, ohne Stripe bei
-- jedem Seitenaufruf zu fragen:
--   stripe_details_submitted  Angaben vollständig abgeschickt (details_submitted)
--   stripe_charges_enabled    charges_enabled
--   stripe_payouts_enabled    payouts_enabled
--   stripe_onboarding_status  angelegt | offen | pruefung | vollstaendig | abgelehnt
--   stripe_fehlt              Zahl der fehlenden Angaben (currently_due + past_due)
--   stripe_status_am          letzter Abruf/Webhook
--   stripe_status_fehler      letzte Absage von Stripe beim Abruf (nur für Vecom)
--   stripe_konto_alt          nach „Stripe-Verifizierung neu einrichten“: das
--                             abgehängte Konto (nichts wird still vergessen)
--
-- Nur neue Spalten, alle NULL erlaubt: Bestehende Partner und ihre Konten
-- bleiben unverändert. Wiederholbar: ADD COLUMN → 1060 übersprungen.
-- ============================================================================

ALTER TABLE partner ADD COLUMN stripe_details_submitted TINYINT(1) NULL;
ALTER TABLE partner ADD COLUMN stripe_charges_enabled TINYINT(1) NULL;
ALTER TABLE partner ADD COLUMN stripe_payouts_enabled TINYINT(1) NULL;
ALTER TABLE partner ADD COLUMN stripe_onboarding_status VARCHAR(16) NULL;
ALTER TABLE partner ADD COLUMN stripe_fehlt SMALLINT NULL;
ALTER TABLE partner ADD COLUMN stripe_status_am DATETIME NULL;
ALTER TABLE partner ADD COLUMN stripe_status_fehler VARCHAR(255) NULL;
ALTER TABLE partner ADD COLUMN stripe_konto_alt VARCHAR(40) NULL;
