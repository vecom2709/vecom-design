-- ===========================================================================
-- 162_branchen_flyer_aus.sql — „Branchen-Flyer A5“ abschalten (04.10.2026,
-- Uwe: „ja“ zur Frage, ob das doppelte Produkt aus soll).
--
-- Seit dem Branchenmotiv für alle Flyer (WmDruck::branche) ist „Flyer A5“ mit
-- Branche dieselbe Datei wie der Branchen-Flyer A5. Abgeschaltet wird nur,
-- wenn „Flyer A5“ selbst eingeschaltet ist — sonst gäbe es für Partner gar
-- keinen A5-Flyer mit Branche mehr. Nichts wird gelöscht: Entwürfe,
-- Freigaben und Bestellungen des Branchen-Flyers bleiben, wie sie sind.
-- ===========================================================================

UPDATE wm_produkte b
  JOIN wm_produkte a ON a.vorlage = 'flyer_a5' AND a.aktiv = 1
   SET b.aktiv = 0
 WHERE b.vorlage = 'flyer_branche' AND b.aktiv = 1;
