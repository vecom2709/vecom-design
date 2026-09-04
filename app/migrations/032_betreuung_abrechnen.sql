-- Die monatliche Betreuung wird abrechenbar.
--
-- Bisher erzeugte ein Betreuungsvertrag keine einzige Zahlung: Abo::taeglich()
-- beendete nur gekuendigte Vertraege, invoices.abo_id wurde nirgends
-- beschrieben, und payments.order_id war NOT NULL — eine Betreuungszahlung
-- konnte also gar nicht existieren. Bei fuenf Kunden zu 39 Euro sind das
-- 2.340 Euro im Jahr, die im Paket fuers Finanzamt nicht auftauchten.
--
-- Zwei Aenderungen, mehr braucht es nicht: Eine Zahlung darf ohne Bestellung
-- dastehen, und sie darf an einem Abo haengen.
ALTER TABLE payments
  MODIFY order_id INT UNSIGNED NULL;

ALTER TABLE payments
  ADD COLUMN abo_id INT UNSIGNED NULL AFTER order_id;

ALTER TABLE payments
  ADD KEY ix_payments_abo (abo_id);

-- Zweimal derselbe Monat waere doppelt abgerechnet. Der Monat steht als
-- erster Tag in faellig_bis... nein: als eigene Spalte, damit die Sperre
-- unabhaengig vom Zahlungsziel greift.
ALTER TABLE payments
  ADD COLUMN abrechnungsmonat CHAR(7) NULL AFTER abo_id;

ALTER TABLE payments
  ADD UNIQUE KEY uq_payments_abo_monat (abo_id, abrechnungsmonat);
