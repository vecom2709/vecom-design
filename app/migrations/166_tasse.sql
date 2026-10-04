-- ===========================================================================
-- 166_tasse.sql — Tasse 11 oz als Geschenk für Betriebe
-- (04.10.2026, Uwe: „ja“ zu den Produktvorschlägen — Vorschlag 7).
--
-- Printful „White Glossy Mug“ 11 oz (Katalogprodukt 19, Variante 1320,
-- Sublimation, hergestellt in der EU: Lettland/Spanien — GET /products/19,
-- 04.10.2026). Gestaltung: tools/werbemittel/gen.py (tasse_11, Stile a und d):
-- links das V, rechts Ansprechpartner, Link und QR-Code des Partners.
-- Preise holt Printful::preiseAktualisieren aus estimate-costs (EUR) —
-- keine geschätzten. Eingeschaltet (Uwe 04.10.2026: „mache alles automatisch“):
-- Partner sehen sie erst, wenn ein Preis da ist (katalog() zeigt nichts ohne Preis).
-- ===========================================================================

INSERT INTO wm_produkte (kategorie_id, name_it, name_de, name_en, text_it, text_de, text_en,
                         breite_zmm, hoehe_zmm, beschnitt_zmm, vorlage, aktiv, sortierung, bereich)
SELECT k.id, 'Tazza 11 oz con il suo QR', 'Tasse 11 oz mit Ihrem QR-Code', 'Mug 11 oz with your QR code',
       'Tazza in ceramica bianca lucida, lavabile in lavastoviglie: il marchio Vecom da un lato, il suo nome e codice QR dall’altro. Un regalo che si usa ogni giorno.',
       'Weiße Keramiktasse, spülmaschinenfest: auf der einen Seite das Vecom-Zeichen, auf der anderen Ihr Name und QR-Code. Ein Geschenk, das jeden Tag benutzt wird.',
       'White glossy ceramic mug, dishwasher safe: the Vecom mark on one side, your name and QR code on the other. A gift that gets used every day.',
       2286, 889, 0, 'tasse_11', 1, 20, 'geschenke'
  FROM wm_kategorien k
 WHERE k.slug = 'werbeartikel'
   AND NOT EXISTS (SELECT 1 FROM wm_produkte p WHERE p.vorlage = 'tasse_11');

UPDATE wm_produkte SET nummer = CONCAT('VEC-', LPAD(id, 4, '0')) WHERE nummer IS NULL;

INSERT INTO wm_varianten (produkt_id, name_it, name_de, name_en, auflage, sortierung)
SELECT p.id, v.n_it, v.n_de, v.n_en, v.auflage, v.s
  FROM wm_produkte p
  JOIN (SELECT '1 tazza' n_it, '1 Tasse' n_de, '1 mug' n_en, 1 auflage, 10 s
        UNION ALL SELECT '6 tazze', '6 Tassen', '6 mugs', 6, 20
        UNION ALL SELECT '12 tazze', '12 Tassen', '12 mugs', 12, 30) v
 WHERE p.vorlage = 'tasse_11'
   AND NOT EXISTS (SELECT 1 FROM wm_varianten x WHERE x.produkt_id = p.id);

-- Printful-Zuordnung: Variante 1320 (11 oz), Menge = Stückzahl der Auflage.
INSERT INTO wm_anbieter_produkte (variante_id, anbieter, artikel, menge)
SELECT v.id, 'printful', '1320', v.auflage
  FROM wm_varianten v JOIN wm_produkte p ON p.id = v.produkt_id
 WHERE p.vorlage = 'tasse_11'
ON DUPLICATE KEY UPDATE artikel = artikel;
