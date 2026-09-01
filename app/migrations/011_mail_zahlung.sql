-- ===========================================================================
-- 011_mail_zahlung.sql — Der Mailspeicher merkt sich auch die Zahlung.
--
-- Eine Bestellung hat zwei Zahlungen: Anzahlung und Rest. Wuerde die Warnung
-- vor doppeltem Versand nur auf der Bestellung sitzen, hielte sie die zweite
-- Mail faelschlich fuer eine Wiederholung der ersten.
-- ===========================================================================

ALTER TABLE mails
  ADD COLUMN payment_id INT UNSIGNED NULL AFTER order_id,
  ADD KEY ix_mails_payment (payment_id);
