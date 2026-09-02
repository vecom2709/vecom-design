-- ===========================================================================
-- 018_pakete_trennen.sql — Erstellung und Betreuung sind zwei Produkte.
--
-- Bisher hing die monatliche Betreuung als zweite Zahl am Website-Paket. Damit
-- liess sie sich nicht allein verkaufen — weder an einen Kunden, dessen Seite
-- schon steht, noch an einen mit einer Seite von jemand anderem.
--
-- Schlimmer war die Vermischung in den Leistungslisten: Im Starter-Paket fuer
-- 499 € standen "Monatliche Backups und Updates", "Kleine Aenderungen
-- inklusive" und "Direkte Betreuung ohne Ticketsystem" — alles Leistungen, die
-- zusaetzlich mit 39 € im Monat berechnet werden. Wer genau liest, fragt zu
-- Recht, wofuer er monatlich zahlt.
--
-- art trennt die Produktarten: website (einmalig), betreuung (monatlich),
-- zusatz (einmalig, z. B. die Bestandsaufnahme einer fremden Seite).
--
-- monthly_cents bleibt beim Website-Paket stehen: als empfohlene Betreuung zur
-- jeweiligen Groesse. Das Angebotsschreiben nennt sie, verkauft sie aber nicht
-- mit — der Kunde entscheidet getrennt.
-- ===========================================================================

ALTER TABLE packages
  ADD COLUMN art VARCHAR(20) NOT NULL DEFAULT 'website' AFTER slug;

UPDATE packages SET art = 'website' WHERE art = '' OR art IS NULL;
