-- ===========================================================================
-- 154_werbemittel_flyer.sql — Flyer A6 und A5 für Partner (04.10.2026, Uwe:
-- „Ja alles“ zu den Vorschlägen 6 und 7: die günstigste Druckerei je Land).
--
-- Gestaltung: tools/werbemittel/gen.py (Vorlagen in app/werbemittel/), Partner
-- wählt Stil, Sprache und Kontakt, sieht die Vorschau und gibt frei.
-- Preise: Recherche vom 04.10.2026 auf flyeralarm.com (Produktseite „Flyer
-- Klassiker“, Preisabfrage je Auflage), 4/4-farbig, 130 g Bilderdruck
-- glänzend „Budget“ — in Italien und Deutschland die günstigste belegte
-- Druckerei inkl. Versand (Versand dort kostenlos). Brutto mit 22 % (IT)
-- bzw. 19 % (DE) MwSt, so wie Vecom ohne Partita IVA zahlt.
-- Aus, bis Uwe die Vorschau gesehen hat.
-- ===========================================================================

INSERT INTO wm_produkte (kategorie_id, name_it, name_de, name_en, text_it, text_de, text_en,
                         breite_zmm, hoehe_zmm, beschnitt_zmm, vorlage, aktiv, sortierung)
SELECT k.id, x.n_it, x.n_de, x.n_en,
       'Fronte: Vecom Design. Retro: il suo nome, il suo contatto e il QR verso la sua pagina.',
       'Vorderseite: Vecom Design. Rückseite: Ihr Name, Ihr Kontakt und der QR-Code zu Ihrer Seite.',
       'Front: Vecom Design. Back: your name, your contact and the QR code to your page.',
       x.b, x.h, 30, x.v, 0, x.s
  FROM wm_kategorien k
  JOIN (SELECT 'Volantino A6 Vecom Partner' n_it, 'Flyer A6 Vecom-Partner' n_de, 'Vecom partner flyer A6' n_en, 1050 b, 1480 h, 'flyer_a6' v, 10 s
        UNION ALL SELECT 'Volantino A5 Vecom Partner', 'Flyer A5 Vecom-Partner', 'Vecom partner flyer A5', 1480, 2100, 'flyer_a5', 20) x
 WHERE k.slug = 'flyer'
   AND NOT EXISTS (SELECT 1 FROM wm_produkte p WHERE p.vorlage = x.v);

UPDATE wm_produkte SET nummer = CONCAT('VEC-', LPAD(id, 4, '0')) WHERE nummer IS NULL;

INSERT INTO wm_varianten (produkt_id, name_it, name_de, name_en, auflage, sortierung)
SELECT p.id, v.n_it, v.n_de, v.n_en, v.auflage, v.s
  FROM wm_produkte p
  JOIN (SELECT '250 pezzi' n_it, '250 Stück' n_de, '250 pieces' n_en, 250 auflage, 10 s
        UNION ALL SELECT '500 pezzi', '500 Stück', '500 pieces', 500, 20
        UNION ALL SELECT '1000 pezzi', '1000 Stück', '1000 pieces', 1000, 30) v
 WHERE p.vorlage IN ('flyer_a6', 'flyer_a5')
   AND NOT EXISTS (SELECT 1 FROM wm_varianten x WHERE x.produkt_id = p.id);

INSERT INTO wm_anbieter_preise (variante_id, anbieter, land, preis_cent, netto_cent, papier, lieferung, link, geprueft_am)
SELECT v.id, 'Flyeralarm', o.land, o.preis, o.netto, '130 g Bilderdruck glänzend (Budget), 4/4', o.lief, o.link, '2026-10-04'
  FROM wm_varianten v JOIN wm_produkte p ON p.id = v.produkt_id
  JOIN (SELECT 'flyer_a6' v, 250 a, 'IT' land, 1852 preis, 1518 netto, 'Versand gratis, 5–7 Werktage' lief, 'https://www.flyeralarm.com/it/p/volantini-classici-4191540.html' link
        UNION ALL SELECT 'flyer_a6', 500, 'IT', 1972, 1616, 'Versand gratis, 5–7 Werktage', 'https://www.flyeralarm.com/it/p/volantini-classici-4191540.html'
        UNION ALL SELECT 'flyer_a6', 1000, 'IT', 2396, 1964, 'Versand gratis, 5–7 Werktage', 'https://www.flyeralarm.com/it/p/volantini-classici-4191540.html'
        UNION ALL SELECT 'flyer_a6', 250, 'DE', 1442, 1212, 'Versand kostenlos, 4–5 Werktage', 'https://www.flyeralarm.com/de/p/flyer-klassiker-4191540.html'
        UNION ALL SELECT 'flyer_a6', 500, 'DE', 1551, 1303, 'Versand kostenlos, 4–5 Werktage', 'https://www.flyeralarm.com/de/p/flyer-klassiker-4191540.html'
        UNION ALL SELECT 'flyer_a6', 1000, 'DE', 1936, 1627, 'Versand kostenlos, 4–5 Werktage', 'https://www.flyeralarm.com/de/p/flyer-klassiker-4191540.html'
        UNION ALL SELECT 'flyer_a5', 250, 'IT', 2601, 2132, 'Versand gratis, 5–7 Werktage', 'https://www.flyeralarm.com/it/p/volantini-classici-4191540.html'
        UNION ALL SELECT 'flyer_a5', 500, 'IT', 3105, 2545, 'Versand gratis, 5–7 Werktage', 'https://www.flyeralarm.com/it/p/volantini-classici-4191540.html'
        UNION ALL SELECT 'flyer_a5', 1000, 'IT', 3819, 3130, 'Versand gratis, 5–7 Werktage', 'https://www.flyeralarm.com/it/p/volantini-classici-4191540.html'
        UNION ALL SELECT 'flyer_a5', 250, 'DE', 2122, 1783, 'Versand kostenlos, 4–5 Werktage', 'https://www.flyeralarm.com/de/p/flyer-klassiker-4191540.html'
        UNION ALL SELECT 'flyer_a5', 500, 'DE', 2579, 2167, 'Versand kostenlos, 4–5 Werktage', 'https://www.flyeralarm.com/de/p/flyer-klassiker-4191540.html'
        UNION ALL SELECT 'flyer_a5', 1000, 'DE', 3227, 2712, 'Versand kostenlos, 4–5 Werktage', 'https://www.flyeralarm.com/de/p/flyer-klassiker-4191540.html') o
    ON o.v = p.vorlage AND o.a = v.auflage
ON DUPLICATE KEY UPDATE preis_cent = preis_cent;
