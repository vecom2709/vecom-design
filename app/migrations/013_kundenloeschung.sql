-- ===========================================================================
-- 013_kundenloeschung.sql — Einen Kunden loeschen oder anonymisieren.
--
-- Zwei Spalten, die beide aus demselben Widerspruch entstehen: Ein Kunde darf
-- verlangen, dass seine Daten verschwinden (DSGVO Art. 17) — aber ausgestellte
-- Belege muessen aufbewahrt werden (Art. 2220 Codice civile, zehn Jahre).
-- Art. 17 Abs. 3 Buchst. b loest das: Wo eine gesetzliche Aufbewahrungspflicht
-- besteht, gilt das Loeschrecht nicht. Also verschwindet der Mensch aus der
-- Verwaltung, und der Beleg behaelt, was auf ihm stehen muss.
--
--   invoices.empfaenger — Der Empfaenger, wie er am Tag der Ausstellung
--     hiess. Bisher las das PDF die Anschrift jedes Mal frisch aus der
--     Kundentabelle; nach einer Anonymisierung waere jede alte Rechnung
--     ohne Empfaenger dagestanden. Jetzt friert der Beleg ihn ein.
--
--   customers.anonym_am — Wann der Datensatz geleert wurde. Solange die
--     Spalte leer ist, ist es ein normaler Kunde. Steht ein Datum drin,
--     zeigt die Akte das und sperrt, was keinen Sinn mehr ergibt.
-- ===========================================================================

ALTER TABLE invoices
  ADD COLUMN empfaenger JSON NULL AFTER customer_id;

ALTER TABLE customers
  ADD COLUMN anonym_am DATETIME NULL AFTER updated_at;
