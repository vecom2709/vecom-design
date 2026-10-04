-- ===========================================================================
-- 171_geschenke_printful.sql — vier Geschenke für Betriebe über Printful
-- (05.10.2026, Uwe: „ja“ zu den Produktvorschlägen; Gestaltungen gezeigt,
-- dann „Fahre fort“).
--
-- Katalog (GET /products/{id}, öffentlich, 05.10.2026), Variante, Druckfläche
-- (vom Server abgefragt, Verwaltung „Printful-Druckflächen“):
--   Notizbuch    474 / 12141  Spiral Notebook, 1725 × 2625 px vorn + hinten
--   Flasche      382 / 10798  Stainless Steel Water Bottle weiß, 2557 × 1582 px
--   Untersetzer  611 / 15662  Cork-Back Coaster 95 × 95 mm, 1181 × 1181 px
--   Beutel       367 / 10457  Eco Tote schwarz (DTG), 1500 × 1500 px @150 dpi
-- Gestaltung: tools/werbemittel/gen.py (Stile a und d; Beutel nur a).
-- Preise holt Printful::preiseAktualisieren (estimate-costs, EUR).
-- AUS, bis Uwe einschaltet: verkäufliche Produkte gehen erst nach seinem Ja live.
-- ===========================================================================

INSERT INTO wm_produkte (kategorie_id, name_it, name_de, name_en, text_it, text_de, text_en,
                         breite_zmm, hoehe_zmm, beschnitt_zmm, vorlage, aktiv, sortierung, bereich)
SELECT k.id, n.n_it, n.n_de, n.n_en, n.t_it, n.t_de, n.t_en, n.b, n.h, n.bs, n.vorlage, 0, n.s, 'geschenke'
  FROM wm_kategorien k
  JOIN (SELECT 'notizbuch' vorlage, 21 s, 1397 b, 2159 h, 32 bs,
               'Quaderno A5 con il suo QR' n_it, 'Notizbuch A5 mit Ihrem QR-Code' n_de, 'A5 notebook with your QR code' n_en,
               'Quaderno a spirale 14,5 × 21 cm, 140 pagine a puntini: davanti il marchio Vecom, dietro il suo nome e il codice QR. Resta sulla scrivania del cliente.' t_it,
               'Spiralnotizbuch 14,5 × 21 cm, 140 gepunktete Seiten: vorn das Vecom-Zeichen, hinten Ihr Name und QR-Code. Bleibt beim Kunden auf dem Schreibtisch.' t_de,
               'Spiral notebook 14.5 × 21 cm, 140 dotted pages: the Vecom mark on the front, your name and QR code on the back. Stays on the client’s desk.' t_en
        UNION ALL SELECT 'flasche', 22, 2165, 1339, 0,
               'Borraccia termica 500 ml con il suo QR', 'Thermosflasche 500 ml mit Ihrem QR-Code', 'Insulated bottle 500 ml with your QR code',
               'Borraccia in acciaio inox a doppia parete, bianca lucida: il marchio Vecom, il suo nome e il codice QR tutto intorno. Mantiene le bevande calde o fredde.',
               'Doppelwandige Edelstahlflasche, weiß glänzend: rundum das Vecom-Zeichen, Ihr Name und QR-Code. Hält Getränke warm oder kalt.',
               'Double-walled stainless steel bottle, glossy white: the Vecom mark, your name and QR code all around. Keeps drinks hot or cold.'
        UNION ALL SELECT 'untersetzer', 23, 950, 950, 25,
               'Sottobicchieri in sughero con il suo QR', 'Kork-Untersetzer mit Ihrem QR-Code', 'Cork-back coasters with your QR code',
               'Sottobicchieri 95 × 95 mm, superficie lucida e retro in sughero: marchio Vecom, il suo nome e il codice QR — per banco, bar e sala d’attesa.',
               'Untersetzer 95 × 95 mm, hochglänzend mit Kork-Rücken: Vecom-Zeichen, Ihr Name und QR-Code — für Theke, Bar und Wartebereich.',
               'Coasters 95 × 95 mm, high-gloss with cork back: Vecom mark, your name and QR code — for counters, bars and waiting areas.'
        UNION ALL SELECT 'beutel', 24, 2540, 2540, 0,
               'Shopper in cotone biologico con il suo QR', 'Stofftasche aus Bio-Baumwolle mit Ihrem QR-Code', 'Organic cotton tote with your QR code',
               'Shopper nera in cotone biologico, 40,6 × 35,6 cm: davanti il marchio Vecom in oro, il suo nome e il codice QR. Un regalo che gira per la città.',
               'Schwarze Tragetasche aus Bio-Baumwolle, 40,6 × 35,6 cm: vorn das Vecom-Zeichen in Gold, Ihr Name und QR-Code. Ein Geschenk, das durch die Stadt getragen wird.',
               'Black organic cotton tote, 40.6 × 35.6 cm: the Vecom mark in gold on the front, your name and QR code. A gift that gets carried around town.') n
 WHERE k.slug = 'werbeartikel'
   AND NOT EXISTS (SELECT 1 FROM wm_produkte p WHERE p.vorlage = n.vorlage);

UPDATE wm_produkte SET nummer = CONCAT('VEC-', LPAD(id, 4, '0')) WHERE nummer IS NULL;

INSERT INTO wm_varianten (produkt_id, name_it, name_de, name_en, auflage, sortierung)
SELECT p.id, v.n_it, v.n_de, v.n_en, v.auflage, v.s
  FROM wm_produkte p
  JOIN (SELECT 'notizbuch' vorlage, '1 quaderno' n_it, '1 Notizbuch' n_de, '1 notebook' n_en, 1 auflage, 10 s
        UNION ALL SELECT 'notizbuch', '5 quaderni', '5 Notizbücher', '5 notebooks', 5, 20
        UNION ALL SELECT 'notizbuch', '10 quaderni', '10 Notizbücher', '10 notebooks', 10, 30
        UNION ALL SELECT 'flasche', '1 borraccia', '1 Flasche', '1 bottle', 1, 10
        UNION ALL SELECT 'flasche', '5 borracce', '5 Flaschen', '5 bottles', 5, 20
        UNION ALL SELECT 'flasche', '10 borracce', '10 Flaschen', '10 bottles', 10, 30
        UNION ALL SELECT 'untersetzer', '4 sottobicchieri', '4 Untersetzer', '4 coasters', 4, 10
        UNION ALL SELECT 'untersetzer', '8 sottobicchieri', '8 Untersetzer', '8 coasters', 8, 20
        UNION ALL SELECT 'untersetzer', '12 sottobicchieri', '12 Untersetzer', '12 coasters', 12, 30
        UNION ALL SELECT 'beutel', '1 shopper', '1 Tasche', '1 tote', 1, 10
        UNION ALL SELECT 'beutel', '5 shopper', '5 Taschen', '5 totes', 5, 20
        UNION ALL SELECT 'beutel', '10 shopper', '10 Taschen', '10 totes', 10, 30) v
    ON v.vorlage = p.vorlage
 WHERE NOT EXISTS (SELECT 1 FROM wm_varianten x WHERE x.produkt_id = p.id);

INSERT INTO wm_anbieter_produkte (variante_id, anbieter, artikel, menge)
SELECT v.id, 'printful', a.artikel, v.auflage
  FROM wm_varianten v JOIN wm_produkte p ON p.id = v.produkt_id
  JOIN (SELECT 'notizbuch' vorlage, '12141' artikel UNION ALL SELECT 'flasche', '10798'
        UNION ALL SELECT 'untersetzer', '15662' UNION ALL SELECT 'beutel', '10457') a ON a.vorlage = p.vorlage
ON DUPLICATE KEY UPDATE artikel = VALUES(artikel), menge = VALUES(menge);
