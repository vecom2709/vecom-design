-- ===========================================================================
-- 158_werbemittel_rollup.sql — Roll-up 85 × 200 cm für Partner (04.10.2026,
-- Uwe: „Ja alles“ — Vorschlag Roll-up; „mach weiter“).
--
-- Gestaltung: tools/werbemittel/gen.py (rollup_85, Stile a und d, DE/IT/EN):
-- Logo, Botschaft, drei Leistungen, großer Code des Partners (31 cm), sein
-- Link auf einer Platte. Datenformat 87 × 227 cm (1 cm Beschnitt, unten 25 cm
-- in der Kassette) nach den Flyeralarm-Datenblättern der 85×200-Roll-ups
-- (rollupba/rollupbl_85x200_sydr.pdf); für „Starter“ ist kein eigenes
-- Datenblatt öffentlich — Flyeralarm prüft das Format beim Hochladen.
-- Preis: flyeralarm.com am 04.10.2026, „Roll-Up Starter, System inkl. Druck“,
-- 400 g Budget-Plane, 1 Stück, Standardversand inklusive. Aus, bis Uwe sie ansieht.
-- ===========================================================================

INSERT INTO wm_produkte (kategorie_id, name_it, name_de, name_en, text_it, text_de, text_en,
                         breite_zmm, hoehe_zmm, beschnitt_zmm, vorlage, aktiv, sortierung)
SELECT k.id, 'Roll-up 85 × 200 cm', 'Roll-up 85 × 200 cm', 'Roll-up 85 × 200 cm',
       'Espositore avvolgibile con borsa: logo Vecom, i nostri servizi e il suo codice QR grande con il suo link. Per fiere, negozi, uffici.',
       'Roll-up mit Tasche: Vecom-Logo, unsere Leistungen und Ihr großer QR-Code mit Ihrem Link. Für Messen, Geschäfte, Büros.',
       'Roll-up banner with bag: Vecom logo, our services and your large QR code with your link. For fairs, shops, offices.',
       8500, 20000, 100, 'rollup_85', 0, 10
  FROM wm_kategorien k
 WHERE k.slug = 'aufsteller'
   AND NOT EXISTS (SELECT 1 FROM wm_produkte p WHERE p.vorlage = 'rollup_85');

UPDATE wm_produkte SET nummer = CONCAT('VEC-', LPAD(id, 4, '0')) WHERE nummer IS NULL;

INSERT INTO wm_varianten (produkt_id, name_it, name_de, name_en, auflage, sortierung)
SELECT p.id, '1 pezzo', '1 Stück', '1 piece', 1, 10
  FROM wm_produkte p
 WHERE p.vorlage = 'rollup_85'
   AND NOT EXISTS (SELECT 1 FROM wm_varianten x WHERE x.produkt_id = p.id);

INSERT INTO wm_anbieter_preise (variante_id, anbieter, land, preis_cent, netto_cent, papier, lieferung, link, geprueft_am)
SELECT v.id, 'Flyeralarm', o.land, o.preis, o.netto, '400 g Budget-Plane, Alu-Kassette, Tasche', o.lief, o.link, '2026-10-04'
  FROM wm_varianten v JOIN wm_produkte p ON p.id = v.produkt_id
  JOIN (SELECT 'IT' land, 4301 preis, 3525 netto, 'Versand inklusive, 4–6 Werktage' lief, 'https://www.flyeralarm.com/it/p/roll-up-starter-sistema-incl-stampa-4215596.html' link
        UNION ALL SELECT 'DE', 3664, 3079, 'Versand inklusive, 3–4 Werktage', 'https://www.flyeralarm.com/de/p/roll-up-budget-system-inkl-druck-4215596.html') o
 WHERE p.vorlage = 'rollup_85'
ON DUPLICATE KEY UPDATE preis_cent = wm_anbieter_preise.preis_cent;
