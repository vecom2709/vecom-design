-- ===========================================================================
-- 153_werbemittel_printful.sql — dritte angebundene Druckerei: Printful
-- (04.10.2026, Uwe: „bringe trotzdem Printful zusätzlich mit rein“).
--
-- Printful druckt Visitenkarten nur im Format 3,5 × 2 Zoll (ca. 90 × 50 mm),
-- in Packs zu 50 oder 100 Stück. Uwe hat entschieden: „Eingepasst“ — die
-- freigegebene Karte wird unverändert auf die Höhe verkleinert, der
-- Hintergrund links und rechts verlängert. Der Partner sieht diese Fassung
-- VOR der Freigabe als zweite Vorschau; ohne sie geht nichts an Printful.
--
-- wm_entwuerfe.datei_pf_vorn/_hinten  Vorder- und Rückseite in dieser
--   Fassung (JPEG), erzeugt im selben Moment wie die freigegebene Datei.
--
-- Artikel aus dem öffentlichen Printful-Katalog (GET /products/724, abgerufen
-- am 04.10.2026): „Set of Business Cards“, Munken Lynx 300 g (EU-Druck in
-- Lettland), Variante 18554 = 50 Stück, 18555 = 100 Stück. Menge = Zahl der
-- Packs: 250 → 5 × 50, 500 → 5 × 100, 1000 → 10 × 100. Nur einfügen, wo
-- noch nichts steht.
--
-- Nur hinzufügen. Rückweg: Spalten entfernen, Zeilen mit anbieter='printful'.
-- ===========================================================================

ALTER TABLE wm_entwuerfe
  ADD COLUMN datei_pf_vorn MEDIUMBLOB NULL AFTER datei_druck_hash,
  ADD COLUMN datei_pf_hinten MEDIUMBLOB NULL AFTER datei_pf_vorn;

INSERT INTO wm_anbieter_produkte (variante_id, anbieter, artikel, menge)
SELECT v.id, 'printful', IF(v.auflage % 100 = 0, '18555', '18554'), IF(v.auflage % 100 = 0, v.auflage DIV 100, v.auflage DIV 50)
  FROM wm_varianten v JOIN wm_produkte p ON p.id = v.produkt_id
 WHERE p.vorlage = 'visitenkarte' AND v.auflage >= 50 AND v.auflage % 50 = 0
ON DUPLICATE KEY UPDATE artikel = artikel;
