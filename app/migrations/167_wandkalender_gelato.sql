-- ===========================================================================
-- 167_wandkalender_gelato.sql — Gelato-Artikel und Preise des Wandkalenders,
-- Kalender eingeschaltet (04.10.2026, Uwe auf die Frage „Artikelnummer und
-- Preise eintragen und Kalender für Partner einschalten?“: „mache alles
-- automatisch“).
--
-- Quelle: Gelato-Dashboard, Produktseite „Wall calendars 2027 (EU & Rest of
-- World)“, Auswahl Vertical · A3 (29,7 × 42 cm) · Binding with wire hanger,
-- gelesen am 04.10.2026:
--   Product UID  wall-calendars_pf_a3_pt_250-gsm-coated-silk_cl_4-4_bt_wire-with-hook-top_ver
--   Preis netto  Deutschland 9,01 €, Italien 12,09 € je Kalender
--   Versand      5,74 € erster, 2,21 € jeder weitere (DE und IT), ca. 3 Werktage
-- Brutto mit 19 % (DE) bzw. 22 % (IT), Versand eingerechnet — so, wie Vecom
-- zahlt. Gelato::preiseAktualisieren überschreibt das mit den Preisen der
-- Angebotsschnittstelle, sobald dort ein Schlüssel hinterlegt ist.
-- Aufträge gehen im Modus „entwurf“ an Gelato (Uwe bestätigt im Dashboard).
-- ===========================================================================

INSERT INTO wm_anbieter_produkte (variante_id, anbieter, artikel, menge)
SELECT v.id, 'gelato', 'wall-calendars_pf_a3_pt_250-gsm-coated-silk_cl_4-4_bt_wire-with-hook-top_ver', v.auflage
  FROM wm_varianten v JOIN wm_produkte p ON p.id = v.produkt_id
 WHERE p.vorlage = 'kalender_a3'
ON DUPLICATE KEY UPDATE artikel = VALUES(artikel), menge = VALUES(menge);

INSERT INTO wm_anbieter_preise (variante_id, anbieter, land, preis_cent, netto_cent, papier, lieferung, link, geprueft_am)
SELECT v.id, 'Gelato', o.land, o.preis, o.netto, '250 g Silk gestrichen, A3 hoch, Drahtbindung mit Aufhänger',
       'Versand inklusive, ca. 3 Werktage', 'https://dashboard.gelato.com/catalogue/categories/calendars/products', '2026-10-04'
  FROM wm_varianten v JOIN wm_produkte p ON p.id = v.produkt_id
  JOIN (SELECT 1 a, 'DE' land, 1755 preis, 1475 netto
        UNION ALL SELECT 5, 'DE', 7096, 5963
        UNION ALL SELECT 10, 'DE', 13772, 11573
        UNION ALL SELECT 1, 'IT', 2175, 1783
        UNION ALL SELECT 5, 'IT', 9154, 7503
        UNION ALL SELECT 10, 'IT', 17877, 14653) o
    ON o.a = v.auflage
 WHERE p.vorlage = 'kalender_a3'
ON DUPLICATE KEY UPDATE preis_cent = wm_anbieter_preise.preis_cent;

UPDATE wm_produkte SET aktiv = 1 WHERE vorlage = 'kalender_a3';
