-- Jede Mail sagt, wodurch sie rausging (07.10.2026, Uwe: „wenn eine email versendet wurde soll dies
-- auch ganz klar markiert sein inklusive des verlaufs was geschrieben wurde“).
-- ausloeser: freigabe | knopf | automatisch | ablauf -- leer heisst: vor dieser Erfassung verschickt.
-- ref_art/ref_id: worauf sich die Mail bezieht (angebot, exit), partner_id: Mail an einen Partner.
ALTER TABLE mails ADD COLUMN IF NOT EXISTS ausloeser VARCHAR(20) NULL;
ALTER TABLE mails ADD COLUMN IF NOT EXISTS ausloeser_ref VARCHAR(80) NULL;
ALTER TABLE mails ADD COLUMN IF NOT EXISTS ausloeser_id INT UNSIGNED NULL;
ALTER TABLE mails ADD COLUMN IF NOT EXISTS ausloeser_wer VARCHAR(80) NULL;
ALTER TABLE mails ADD COLUMN IF NOT EXISTS ref_art VARCHAR(20) NULL;
ALTER TABLE mails ADD COLUMN IF NOT EXISTS ref_id INT UNSIGNED NULL;
ALTER TABLE mails ADD COLUMN IF NOT EXISTS partner_id INT UNSIGNED NULL;
ALTER TABLE mails ADD KEY IF NOT EXISTS ix_mails_ausloeser (ausloeser, ausloeser_id);
ALTER TABLE mails ADD KEY IF NOT EXISTS ix_mails_ref (ref_art, ref_id);
ALTER TABLE mails ADD KEY IF NOT EXISTS ix_mails_partner (partner_id);
