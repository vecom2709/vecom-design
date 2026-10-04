-- ===========================================================================
-- 165_wandkalender.sql — Wandkalender 2027 A3 als Geschenk für Betriebe
-- (04.10.2026, Uwe: „ja“ zu den Geschenken — Vorschlag 6, „fahre fort“).
--
-- Gestaltung: tools/werbemittel/kalender.py („12 Monate, 12 Ideen“), Partner-
-- daten, QR-Code und Feiertage seines Lieferlandes: WmKalender. Gedruckt bei
-- Gelato (A3 hoch, 14 Seiten, Wire-O). Preise kommen aus Gelatos Angebots-
-- schnittstelle, sobald der Gelato-Artikel (productUid) eingetragen ist —
-- keine geschätzten Preise. Aus, bis Uwe Vorschau, Artikel und Preis gesehen hat.
-- ===========================================================================

INSERT INTO wm_produkte (kategorie_id, name_it, name_de, name_en, text_it, text_de, text_en,
                         breite_zmm, hoehe_zmm, beschnitt_zmm, vorlage, aktiv, sortierung, bereich)
SELECT k.id, 'Calendario da parete 2027 · A3', 'Wandkalender 2027 · A3', 'Wall calendar 2027 · A3',
       '12 mesi, 12 idee per la presenza digitale — con il suo nome e codice QR su ogni pagina. Un regalo che resta appeso un anno intero.',
       '12 Monate, 12 Ideen für den digitalen Auftritt — mit Ihrem Namen und QR-Code auf jeder Seite. Ein Geschenk, das ein Jahr lang im Betrieb hängt.',
       '12 months, 12 ideas for a digital presence — with your name and QR code on every page. A gift that hangs in the business all year.',
       2970, 4200, 40, 'kalender_a3', 0, 10, 'geschenke'
  FROM wm_kategorien k
 WHERE k.slug = 'werbeartikel'
   AND NOT EXISTS (SELECT 1 FROM wm_produkte p WHERE p.vorlage = 'kalender_a3');

UPDATE wm_produkte SET nummer = CONCAT('VEC-', LPAD(id, 4, '0')) WHERE nummer IS NULL;

INSERT INTO wm_varianten (produkt_id, name_it, name_de, name_en, auflage, sortierung)
SELECT p.id, v.n_it, v.n_de, v.n_en, v.auflage, v.s
  FROM wm_produkte p
  JOIN (SELECT '1 pezzo' n_it, '1 Stück' n_de, '1 piece' n_en, 1 auflage, 10 s
        UNION ALL SELECT '5 pezzi', '5 Stück', '5 pieces', 5, 20
        UNION ALL SELECT '10 pezzi', '10 Stück', '10 pieces', 10, 30) v
 WHERE p.vorlage = 'kalender_a3'
   AND NOT EXISTS (SELECT 1 FROM wm_varianten x WHERE x.produkt_id = p.id);
