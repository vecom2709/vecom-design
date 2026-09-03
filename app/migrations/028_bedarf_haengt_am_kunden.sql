-- ===========================================================================
-- 028_bedarf_haengt_am_kunden.sql — Wird der Kunde geloescht, geht der Bedarf
-- mit. Und das Angebot auch.
--
-- WAS SCHIEFLIEF
--
-- bedarf und angebote sind die beiden einzigen Tabellen mit einer
-- Kundennummer, die nie einen Fremdschluessel bekommen haben — und sie
-- standen auch nicht in der Loeschreihe von Kunde::loeschen. Wer einen
-- Kunden loeschte, liess damit einen Bedarf zurueck, der auf eine Akte
-- zeigte, die es nicht mehr gab: In der Liste stand ein Name, hinter dem
-- niemand mehr war, und die Verwaltung bot Knoepfe an, die ins Leere
-- gefuehrt haetten.
--
-- ZWEI SCHLOESSER FUER DIESELBE TUER
--
-- Die Loeschreihe in Kunde::loeschen raeumt jetzt beides mit ab — dort steht
-- auch die Zaehlung, die dem Benutzer sagt, wie viel weg ist. Der
-- Fremdschluessel hier ist die zweite Sicherung: Wer je auf einem anderen
-- Weg einen Kunden entfernt, laesst trotzdem nichts Verwaistes zurueck.
--
-- ERST AUFRAEUMEN, DANN ZUSPERREN
--
-- Ein Fremdschluessel laesst sich nicht anlegen, solange auch nur eine Zeile
-- ihn verletzt. Deshalb raeumt diese Wanderung zuerst weg, was aus der Zeit
-- ohne Schloss noch herumliegt.
-- ===========================================================================

-- Empfehlungen zeigen auf einen Bedarf, der gleich verschwindet. Die
-- Empfehlung selbst bleibt: Sie gehoert dem, der empfohlen hat.
UPDATE empfehlungen e
   LEFT JOIN bedarf b ON b.id = e.bedarf_id
   SET e.bedarf_id = NULL
 WHERE e.bedarf_id IS NOT NULL AND b.id IS NULL;

UPDATE empfehlungen e
   LEFT JOIN customers c ON c.id = (SELECT b.customer_id FROM bedarf b WHERE b.id = e.bedarf_id)
   SET e.bedarf_id = NULL
 WHERE e.bedarf_id IS NOT NULL AND c.id IS NULL;

DELETE p FROM angebot_positionen p
  LEFT JOIN angebote a ON a.id = p.angebot_id
  LEFT JOIN customers c ON c.id = a.customer_id
 WHERE a.id IS NULL OR c.id IS NULL;

DELETE a FROM angebote a
  LEFT JOIN customers c ON c.id = a.customer_id
 WHERE c.id IS NULL;

DELETE b FROM bedarf b
  LEFT JOIN customers c ON c.id = b.customer_id
 WHERE b.customer_id IS NOT NULL AND c.id IS NULL;

ALTER TABLE bedarf
  ADD CONSTRAINT fk_bedarf_kunde FOREIGN KEY (customer_id)
      REFERENCES customers (id) ON DELETE CASCADE;

ALTER TABLE angebote
  ADD CONSTRAINT fk_angebote_kunde FOREIGN KEY (customer_id)
      REFERENCES customers (id) ON DELETE CASCADE;
