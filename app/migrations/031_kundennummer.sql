-- Jeder Kunde bekommt eine eigene Nummer.
--
-- Bis hierher wurde sie zweimal aus der laufenden Kennung gerechnet, in zwei
-- verschiedenen Formaten: "VD-K-0008" im Mailbetreff (und auch nur solange es
-- keine Bestellung gab) und ein nacktes "0008" auf dem Beleg. In der
-- Verwaltung stand sie nirgends. Der Kunde sah eine Zahl, die er sonst nicht
-- wiederfand, und Uwe konnte sie weder sehen noch danach suchen.
--
-- Eine eigene Spalte statt der laufenden Kennung: Die Reihe soll lueckenlos
-- sein und jahresweise zaehlen, wie bei den Belegen. Aus der Kennung
-- gerechnet haette ein geloeschter Kunde eine Luecke hinterlassen.
--
-- Die Spalte darf leer sein: Eine Wanderung soll nichts erfinden. Die
-- Nummern traegt Kunde::nummernNachtragen() nach, in der Reihenfolge der
-- Anlage, und neue Kunden bekommen ihre bei der Anlage.
ALTER TABLE customers
  ADD COLUMN kundennr VARCHAR(20) NULL AFTER id;

-- Zweimal dieselbe Nummer waere schlimmer als gar keine. NULL bleibt
-- mehrfach erlaubt, das ist bei einem eindeutigen Schluessel so.
ALTER TABLE customers
  ADD UNIQUE KEY uq_customers_kundennr (kundennr);
