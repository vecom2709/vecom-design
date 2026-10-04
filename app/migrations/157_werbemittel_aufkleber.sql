-- ===========================================================================
-- 157_werbemittel_aufkleber.sql — Aufkleber rund Ø 5 cm für Partner
-- (04.10.2026, Uwe: „Ja alles“ — Vorschlag Aufkleber; „mach weiter“).
--
-- Gestaltung: tools/werbemittel/gen.py (aufkleber_50, Stile a und d), eine
-- Seite: V-Marke, „Ihre neue Website“, der Code des Partners (2 cm), „Jetzt
-- scannen“. Datenformat 54 × 54 mm laut Flyeralarm-Datenblatt (2 mm Beschnitt,
-- 4 mm Sicherheitsabstand) — die Druckdatei passt ohne Umrechnung.
-- Preise: flyeralarm.com am 04.10.2026, „Aufkleber, Rund 5 cm“ bzw. „Adesivi
-- tondi 5 cm“, 90 µm Haftfolie weiß (outdoor), Standardversand inklusive.
-- Brutto mit 19 % (DE) bzw. 22 % (IT). Aus, bis Uwe die Vorschau gesehen hat.
-- ===========================================================================

INSERT INTO wm_produkte (kategorie_id, name_it, name_de, name_en, text_it, text_de, text_en,
                         breite_zmm, hoehe_zmm, beschnitt_zmm, vorlage, aktiv, sortierung)
SELECT k.id, 'Adesivo rotondo Ø 5 cm', 'Aufkleber rund Ø 5 cm', 'Round sticker Ø 5 cm',
       'Adesivo da esterno con il suo codice QR: per vetrine, auto, laptop. Chi scansiona arriva alla sua pagina.',
       'Outdoor-Aufkleber mit Ihrem QR-Code: für Schaufenster, Auto, Laptop. Wer scannt, landet auf Ihrer Seite.',
       'Outdoor sticker with your QR code: for shop windows, cars, laptops. Whoever scans lands on your page.',
       500, 500, 20, 'aufkleber_50', 0, 10
  FROM wm_kategorien k
 WHERE k.slug = 'aufkleber'
   AND NOT EXISTS (SELECT 1 FROM wm_produkte p WHERE p.vorlage = 'aufkleber_50');

UPDATE wm_produkte SET nummer = CONCAT('VEC-', LPAD(id, 4, '0')) WHERE nummer IS NULL;

INSERT INTO wm_varianten (produkt_id, name_it, name_de, name_en, auflage, sortierung)
SELECT p.id, v.n_it, v.n_de, v.n_en, v.auflage, v.s
  FROM wm_produkte p
  JOIN (SELECT '100 pezzi' n_it, '100 Stück' n_de, '100 pieces' n_en, 100 auflage, 10 s
        UNION ALL SELECT '250 pezzi', '250 Stück', '250 pieces', 250, 20
        UNION ALL SELECT '500 pezzi', '500 Stück', '500 pieces', 500, 30) v
 WHERE p.vorlage = 'aufkleber_50'
   AND NOT EXISTS (SELECT 1 FROM wm_varianten x WHERE x.produkt_id = p.id);

INSERT INTO wm_anbieter_preise (variante_id, anbieter, land, preis_cent, netto_cent, papier, lieferung, link, geprueft_am)
SELECT v.id, 'Flyeralarm', o.land, o.preis, o.netto, '90 µm Haftfolie weiß (outdoor)', o.lief, o.link, '2026-10-04'
  FROM wm_varianten v JOIN wm_produkte p ON p.id = v.produkt_id
  JOIN (SELECT 100 a, 'IT' land, 2650 preis, 2172 netto, 'Versand inklusive, 6–8 Werktage' lief, 'https://www.flyeralarm.com/it/p/adesivi-rotondi-5-cm-10502635.html' link
        UNION ALL SELECT 250, 'IT', 2948, 2416, 'Versand inklusive, 6–8 Werktage', 'https://www.flyeralarm.com/it/p/adesivi-rotondi-5-cm-10502635.html'
        UNION ALL SELECT 500, 'IT', 3137, 2571, 'Versand inklusive, 6–8 Werktage', 'https://www.flyeralarm.com/it/p/adesivi-rotondi-5-cm-10502635.html'
        UNION ALL SELECT 100, 'DE', 2166, 1820, 'Versand inklusive, 5–6 Werktage', 'https://www.flyeralarm.com/de/p/aufkleber-rund-5-cm-10502635.html'
        UNION ALL SELECT 250, 'DE', 2436, 2047, 'Versand inklusive, 5–6 Werktage', 'https://www.flyeralarm.com/de/p/aufkleber-rund-5-cm-10502635.html'
        UNION ALL SELECT 500, 'DE', 2608, 2192, 'Versand inklusive, 5–6 Werktage', 'https://www.flyeralarm.com/de/p/aufkleber-rund-5-cm-10502635.html') o
    ON o.a = v.auflage
 WHERE p.vorlage = 'aufkleber_50'
ON DUPLICATE KEY UPDATE preis_cent = wm_anbieter_preise.preis_cent;
