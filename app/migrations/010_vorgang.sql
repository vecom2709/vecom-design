-- ===========================================================================
-- 010_vorgang.sql — Der Kunde bekommt seinen Zugang schon mit der Anfrage.
--
-- Bisher entstand der private Link erst mit dem Fragebogen, also nach der
-- Zahlung. Davor gab es keinen Ort, an dem Kunde und Betrieb miteinander reden
-- oder Unterlagen austauschen konnten — nur zwei Postfaecher.
--
-- Der Schluessel haengt an der Anfrage und laeuft nach 90 Tagen ab. Wird aus
-- der Anfrage ein Auftrag, fuehrt derselbe Link auf die Projektseite weiter:
-- eine Adresse, die der Kunde sich merkt, vom ersten Kontakt bis online.
--
-- Nachrichten und Dateien brauchen keine Aenderung: messages.project_id und
-- files.project_id duerfen bereits leer bleiben.
-- ===========================================================================

ALTER TABLE anfragen
  ADD COLUMN token      CHAR(48) NULL AFTER status,
  ADD COLUMN token_bis  DATETIME NULL AFTER token,
  ADD COLUMN bestaetigt_am DATETIME NULL AFTER token_bis,
  ADD UNIQUE KEY uq_anfragen_token (token);
