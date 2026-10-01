-- ===========================================================================
-- 131_marketing_vorher_nachher.sql — Vorher/Nachher aus fertigen Projekten
-- (Marketing-Studio 9, 01.10.2026, Uwe: „ja“ zu S4).
--
-- Gezeigt wird nur, wer im Kundenbereich ausdrücklich zugestimmt hat
-- (customers.referenz_am; der Wortlaut steht in zustimmungen, Art referenz).
-- Zieht der Kunde zurück, wird referenz_am leer — vorhandene Entwürfe bleiben
-- Entwürfe, neue entstehen nicht.
--
-- mk_inhalte.kunde_id merkt, zu welchem Kunden ein Vorher/Nachher-Beitrag
-- gehört (einmal je Kunde).
--
-- Nur hinzufügen. Rückweg: die Spalten entfernen.
-- ===========================================================================

ALTER TABLE customers
  ADD COLUMN IF NOT EXISTS referenz_am DATETIME NULL AFTER rabatt_bis;

ALTER TABLE mk_inhalte
  ADD COLUMN IF NOT EXISTS kunde_id INT UNSIGNED NULL AFTER zielgruppe_id,
  ADD INDEX IF NOT EXISTS ix_mk_inhalt_kunde (kunde_id);
