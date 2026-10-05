-- 178: Produktnamen im Marketing Center duzen (05.10.2026, Nachtrag zu 175 — dort standen nur die Texte).
-- Ersetzt wird nur der wörtliche alte Name; ein von Uwe umbenanntes Produkt bleibt.
UPDATE wm_produkte SET name_de = 'Tasse 11 oz mit deinem QR-Code' WHERE vorlage = 'tasse_11' AND name_de = 'Tasse 11 oz mit Ihrem QR-Code';
UPDATE wm_produkte SET name_de = 'Notizbuch A5 mit deinem QR-Code' WHERE vorlage = 'notizbuch' AND name_de = 'Notizbuch A5 mit Ihrem QR-Code';
UPDATE wm_produkte SET name_de = 'Thermosflasche 500 ml mit deinem QR-Code' WHERE vorlage = 'flasche' AND name_de = 'Thermosflasche 500 ml mit Ihrem QR-Code';
UPDATE wm_produkte SET name_de = 'Kork-Untersetzer mit deinem QR-Code' WHERE vorlage = 'untersetzer' AND name_de = 'Kork-Untersetzer mit Ihrem QR-Code';
UPDATE wm_produkte SET name_de = 'Stofftasche aus Bio-Baumwolle mit deinem QR-Code' WHERE vorlage = 'beutel' AND name_de = 'Stofftasche aus Bio-Baumwolle mit Ihrem QR-Code';
