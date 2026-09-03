-- ===========================================================================
-- 026_betreuung_seite.sql — Plus und Premium duerfen wieder oeffentlich sein.
--
-- WARUM DAS KEINE RUECKNAHME VON 025 IST
--
-- Migration 025 hat sie auf oeffentlich = 0 gesetzt, weil drei Karten auf der
-- Startseite mehr Platz einnahmen als der Weg, ueber den das Geld hereinkommt.
-- Das Ziel war die Startseite, nicht die Verfuegbarkeit — verkauft wurden sie
-- weiter, nur im Gespraech.
--
-- Jetzt gibt es eine eigene Seite fuer die Betreuung, auf der alle drei Stufen
-- mit ihren Leistungen stehen. Damit sind sie oeffentlich im Wortsinn, und der
-- Schalter soll wieder sagen, was er bedeutet.
--
-- Dass die Startseite trotzdem nur eine Karte zeigt, entscheidet dort die
-- Seite selbst: pakete-live.js nimmt ohne data-betreuung="alle" nur die erste.
-- Das ist der richtige Ort dafuer — es ist eine Frage der Darstellung, keine
-- Frage des Produkts.
-- ===========================================================================

UPDATE packages SET oeffentlich = 1
 WHERE slug IN ('betreuung-plus', 'betreuung-premium') AND art = 'betreuung';
