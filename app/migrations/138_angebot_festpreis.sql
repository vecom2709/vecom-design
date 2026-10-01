-- ===========================================================================
-- 138_angebot_festpreis.sql — individuelles Angebot zum Festpreis
-- (01.10.2026, Uwe: „wir schreiben das Angebot z. B. 950 € und klicken
-- Bausteine rein; die Preise der Bausteine werden anhand des Betrages
-- berechnet und stehen auf Rechnung oder Beleg“).
--
-- festpreis_cents: gesetzt = Festpreis-Angebot. Die einmaligen Zeilen werden
--   so verteilt, dass sie genau diesen Betrag ergeben. NULL = wie bisher.
-- gewicht_cents:   woran sich der Anteil einer Zeile bemisst (Mitte der
--   Baustein-Spanne je Stück, bei freien Zeilen der eingetragene Preis).
-- von_hand:        Uwe hat den Preis der Zeile selbst gesetzt — sie bleibt,
--   die übrigen gleichen aus.
-- Nur hinzufügen. Rückweg: Spalten entfernen.
-- ===========================================================================

ALTER TABLE angebote
  ADD COLUMN festpreis_cents INT UNSIGNED NULL AFTER monatlich_cents;

ALTER TABLE angebot_positionen
  ADD COLUMN gewicht_cents INT UNSIGNED NULL AFTER summe_cents,
  ADD COLUMN von_hand TINYINT(1) NOT NULL DEFAULT 0 AFTER gewicht_cents;
