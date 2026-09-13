-- ===========================================================================
-- 045_zahlungsabgleich.sql — Die Nummer der Bezahlseite bleibt stehen.
--
-- WARUM
--
-- Am 13.09.2026 hat ein Kunde mit Karte bezahlt und nie eine Bestaetigung
-- bekommen. Bei Stripe war das Geld da; in der Verwaltung stand die Rate
-- weiter auf offen. Dazwischen liegt genau ein Aufruf — der Webhook —, und
-- der kam nie an: Im Livemodus war kein Endpunkt eingetragen. Ohne die
-- Buchung passiert nichts weiter: kein Beleg, keine Auftragsbestaetigung,
-- kein Fragebogen, kein Projekt. Und gemerkt hat es niemand, bis der Kunde
-- sich meldete.
--
-- Ein Webhook ist ein Anruf, den der andere macht. Er kann ausfallen, falsch
-- unterschrieben sein oder ins Leere gehen, und in allen drei Faellen sieht
-- es hier genauso aus: Stille. Deshalb braucht es den Rueckweg — wir fragen
-- selbst nach. Dafuer muss nur eines gespeichert sein: WELCHE Bezahlseite zu
-- dieser Rate gehoert.
--
-- WARUM EINE EIGENE SPALTE UND NICHT provider_ref
--
-- provider_ref traegt nach der Zahlung die Nummer des Zahlungsvorgangs
-- (pi_…) und hat einen Eindeutigkeitsschluessel. Die Nummer der Bezahlseite
-- (cs_…) entsteht frueher, kann sich beim Erzeugen eines neuen Links
-- aendern und gehoert deshalb daneben, nicht hinein.
-- ===========================================================================

ALTER TABLE payments
  ADD COLUMN provider_sitzung VARCHAR(190) NULL AFTER provider_ref;

-- Gesucht wird immer nach "offen und hat eine Sitzung". Ohne den Schluessel
-- liest der Abgleich bei jedem Lauf die ganze Tabelle.
ALTER TABLE payments
  ADD KEY ix_payments_sitzung (status, provider_sitzung);
