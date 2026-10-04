-- ===========================================================================
-- 155_werbemittel_branchen_flyer.sql — Branchen-Flyer A5 in DE/IT/EN im
-- Marketing Center (04.10.2026, Uwe: „die Flyer einzeln in Deutsch,
-- Italienisch und Englisch … zusätzlich ins Marketing Center“).
--
-- Vorderseite: der Branchen-Flyer (app/flyer/pro-*.jpg, Foto + unser Text +
-- der QR-Code des Partners). Rückseite: die des Flyers A5 im Stil schwarz-gold
-- mit Name, Link und Kontakt. Der Partner wählt Branche, Sprache und Kontakt.
-- Gleiches Papier und Format wie der Flyer A5 — also dieselben Angebote
-- (Flyeralarm, Recherche vom 04.10.2026, siehe 154). Aus, bis Uwe sie ansieht.
-- ===========================================================================

INSERT INTO wm_produkte (kategorie_id, name_it, name_de, name_en, text_it, text_de, text_en,
                         breite_zmm, hoehe_zmm, beschnitt_zmm, vorlage, aktiv, sortierung)
SELECT k.id, 'Volantino A5 per settore', 'Branchen-Flyer A5', 'Industry flyer A5',
       'Fronte: il settore del cliente (24 a scelta) in italiano, tedesco o inglese, con il suo QR. Retro: il suo nome, il suo contatto e il QR verso la sua pagina.',
       'Vorderseite: die Branche des Kunden (24 zur Wahl) auf Deutsch, Italienisch oder Englisch, mit Ihrem QR-Code. Rückseite: Ihr Name, Ihr Kontakt und der QR-Code zu Ihrer Seite.',
       'Front: the customer’s industry (24 to choose from) in English, German or Italian, with your QR code. Back: your name, your contact and the QR code to your page.',
       1480, 2100, 30, 'flyer_branche', 0, 30
  FROM wm_kategorien k
 WHERE k.slug = 'flyer'
   AND NOT EXISTS (SELECT 1 FROM wm_produkte p WHERE p.vorlage = 'flyer_branche');

UPDATE wm_produkte SET nummer = CONCAT('VEC-', LPAD(id, 4, '0')) WHERE nummer IS NULL;

INSERT INTO wm_varianten (produkt_id, name_it, name_de, name_en, auflage, sortierung)
SELECT p.id, v.n_it, v.n_de, v.n_en, v.auflage, v.s
  FROM wm_produkte p
  JOIN (SELECT '250 pezzi' n_it, '250 Stück' n_de, '250 pieces' n_en, 250 auflage, 10 s
        UNION ALL SELECT '500 pezzi', '500 Stück', '500 pieces', 500, 20
        UNION ALL SELECT '1000 pezzi', '1000 Stück', '1000 pieces', 1000, 30) v
 WHERE p.vorlage = 'flyer_branche'
   AND NOT EXISTS (SELECT 1 FROM wm_varianten x WHERE x.produkt_id = p.id);

-- Dieselben Angebote wie beim Flyer A5 (gleiches Produkt bei der Druckerei).
INSERT INTO wm_anbieter_preise (variante_id, anbieter, land, preis_cent, netto_cent, papier, lieferung, link, geprueft_am)
SELECT nv.id, a.anbieter, a.land, a.preis_cent, a.netto_cent, a.papier, a.lieferung, a.link, a.geprueft_am
  FROM wm_produkte np
  JOIN wm_varianten nv ON nv.produkt_id = np.id
  JOIN wm_produkte ap ON ap.vorlage = 'flyer_a5'
  JOIN wm_varianten av ON av.produkt_id = ap.id AND av.auflage = nv.auflage
  JOIN wm_anbieter_preise a ON a.variante_id = av.id
 WHERE np.vorlage = 'flyer_branche'
ON DUPLICATE KEY UPDATE preis_cent = wm_anbieter_preise.preis_cent;
