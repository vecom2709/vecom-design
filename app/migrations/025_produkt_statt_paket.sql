-- ===========================================================================
-- 025_produkt_statt_paket.sql — Ein Produkt hat einen Preis. Ein Paket hat eine
-- Schublade.
--
-- WARUM "STARTER" UMBENANNT WIRD
--
-- Der Preis war nie das Problem, der Name war es. "Starter" ist eine Stufe,
-- und eine Stufe behauptet, dass es daruber noch etwas gibt — der Leser sucht
-- nach Business und Premium und fragt sich, was ihm vorenthalten wird. Genau
-- diese Schubladen sollten verschwinden.
--
-- "Website zum Festpreis" behauptet nichts daruber. Es ist eine Aussage ueber
-- die Art, wie bezahlt wird, nicht ueber den Rang des Kunden.
--
-- WARUM VON DREI BETREUUNGSKARTEN EINE BLEIBT
--
-- Sie waren das letzte Paket-Raster auf der Seite und nahmen mehr Platz ein
-- als der Weg, ueber den das Geld hereinkommt. Plus und Premium bleiben in
-- der Datenbank und buchbar — sie stehen nur nicht mehr als Karten da,
-- sondern kommen im Gespraech vor, wenn jemand mehr braucht als die Basis.
--
-- Geloescht wird auch hier nichts: An Betreuungsvertraegen haengt Geld, das
-- jeden Monat flieszt.
-- ===========================================================================

UPDATE packages
   SET name = 'Website zum Festpreis',
       texte = JSON_SET(COALESCE(texte, '{}'),
                        '$.it.name', 'Sito a prezzo fisso',
                        '$.de.name', 'Website zum Festpreis',
                        '$.en.name', 'Website at a fixed price')
 WHERE slug = 'starter' AND art = 'website';

UPDATE packages SET oeffentlich = 0
 WHERE slug IN ('betreuung-plus', 'betreuung-premium') AND art = 'betreuung';
