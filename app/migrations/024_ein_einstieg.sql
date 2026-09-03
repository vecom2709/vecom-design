-- ===========================================================================
-- 024_ein_einstieg.sql — Von drei Preiskarten bleibt eine.
--
-- WARUM NICHT ALLE DREI VERSCHWINDEN
--
-- Ohne jede Zahl auf der Seite verliert die Direktbuchung ihren Boden: Sie
-- braucht zwingend einen festen Preis, sonst gibt es nichts zu bezahlen.
-- Damit faellt der einzige Weg weg, auf dem Geld ohne Zutun hereinkommt.
-- Deshalb bleibt genau ein Einstiegsangebot stehen, und alles Groessere
-- laeuft ueber den Bedarfsweg.
--
-- WARUM STARTER UND NICHTS NEUES
--
-- Starter hat dreisprachige Texte, eine Leistungsliste und einen Preis, der
-- sich im Markt bewaehrt hat. Ein frisch erfundenes Einstiegspaket haette
-- nichts davon. Der Baukasten rechnet denselben Umfang auf 450 bis 575 Euro
-- — die 499 liegen also genau da, wo sie hingehoeren.
--
-- BUSINESS UND PREMIUM BLEIBEN, SIE ZEIGEN SICH NUR NICHT MEHR
--
-- Geloescht wird nichts: An ihnen haengen laufende Bestellungen, und sie
-- dienen weiter als interne Rechenvorlage. Sie stehen nur nicht mehr auf der
-- Website. Das ist Vorschlag acht, und es ist der Grund, warum hier ein
-- UPDATE steht und kein DELETE.
-- ===========================================================================

UPDATE packages SET oeffentlich = 0
 WHERE slug IN ('business', 'premium') AND art = 'website';

UPDATE packages SET oeffentlich = 1, direktkauf = 1, sort = 1
 WHERE slug = 'starter' AND art = 'website';

-- Der Sammelposten fuer Angebots-Bestellungen darf unter keinen Umstaenden
-- auf der Website erscheinen. Falls er vor dieser Migration schon entstanden
-- ist, bekommt er hier nachtraeglich die richtigen Schalter.
UPDATE packages SET oeffentlich = 0, active = 0, art = 'zusatz'
 WHERE slug = 'individuelles-angebot';

INSERT INTO settings (skey, svalue) VALUES ('einstieg_slug', 'starter')
  ON DUPLICATE KEY UPDATE skey = skey;
