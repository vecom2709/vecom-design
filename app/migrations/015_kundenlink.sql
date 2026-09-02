-- ===========================================================================
-- 015_kundenlink.sql — Eine Adresse für den Kunden, vom ersten Kontakt an.
--
-- Bisher gab es zwei Schluessel: einen an der Anfrage (vorgang.php) und einen
-- am Fragebogen (projekt.php, fragebogen.php). Der erste leitet auf den
-- zweiten weiter, sobald ein Auftrag da ist — gut gedacht, aber es bleiben
-- zwei Adressen, und die zweite haengt an einem Fragebogen: Gibt es keinen,
-- hat der Kunde keine Seite.
--
-- Der Schluessel gehoert an den Kunden, nicht an einen Vorgang darunter. Dann
-- ist es eine Adresse, von der Anfrage bis Jahre nach dem Onlinegang — und
-- sie bleibt dieselbe, wenn der Kunde spaeter eine zweite Seite bestellt.
--
-- token_seit haelt fest, seit wann dieser Schluessel gilt. Wird er
-- zurueckgezogen (weil der Kunde den Link weitergegeben hat), entsteht ein
-- neuer, und das Datum sagt, ab wann der alte nicht mehr galt.
-- ===========================================================================

ALTER TABLE customers
  ADD COLUMN token      CHAR(48) NULL AFTER sdi,
  ADD COLUMN token_seit DATETIME NULL AFTER token,
  ADD UNIQUE KEY uq_customers_token (token);
