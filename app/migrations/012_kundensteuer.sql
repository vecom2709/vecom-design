-- Steuerangaben des Kunden.
--
-- Auf einem Zahlungsbeleg braucht es sie nicht. Auf einer italienischen
-- Rechnung schon: Wer an ein Unternehmen oder an einen Freiberufler
-- fakturiert, muss dessen Steuernummer nennen, und fuer die elektronische
-- Rechnung ueber das SDI kommt der Empfaengerkode oder eine PEC-Adresse
-- dazu. Die Felder stehen jetzt bereit, damit sie am Tag der Partita IVA
-- nicht nachtraeglich bei jedem Kunden erfragt werden muessen.
ALTER TABLE customers
  ADD COLUMN tax_code VARCHAR(32)  NULL AFTER country,
  ADD COLUMN vat_id   VARCHAR(32)  NULL AFTER tax_code,
  ADD COLUMN sdi      VARCHAR(120) NULL AFTER vat_id;
