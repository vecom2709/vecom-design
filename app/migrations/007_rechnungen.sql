-- Belege und Rechnungen.
--
-- Wichtig: Solange keine Partita IVA hinterlegt ist, ist das Dokument ein
-- Zahlungsbeleg und keine Rechnung im steuerlichen Sinn. Erst mit
-- eingetragener Nummer wird daraus eine Rechnung. Der Text dazu kommt vom
-- Commercialista und steht in den Einstellungen — nicht im Code.

ALTER TABLE invoices
  ADD COLUMN payment_id  INT UNSIGNED NULL       AFTER project_id,
  ADD COLUMN art         VARCHAR(20)  NOT NULL DEFAULT 'gesamt' AFTER payment_id,
  ADD COLUMN titel       VARCHAR(190) NULL       AFTER art,
  ADD COLUMN hinweis     TEXT         NULL       AFTER status,
  ADD COLUMN sent_at     DATETIME     NULL       AFTER issued_at,
  ADD KEY ix_invoices_payment (payment_id),
  ADD CONSTRAINT fk_invoices_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL;

-- Eine Zahlung bekommt hoechstens einen Beleg.
CREATE UNIQUE INDEX uq_invoices_payment ON invoices (payment_id);
