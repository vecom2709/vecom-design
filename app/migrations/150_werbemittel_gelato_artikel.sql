-- ===========================================================================
-- 150_werbemittel_gelato_artikel.sql — Gelato-Artikel für die Visitenkarte
-- (03.10.2026, Uwe im Gelato-Dashboard eingeloggt: „mache weiter automatisch“).
--
-- Aus dem Gelato-Katalog abgelesen (Business cards › Premium silk paper,
-- Horizontal, 5.5x8.5 cm = 85 × 55 mm): Product UID
--   cards_pf_bd_pt_350-gsm-coated-silk_cl_4-4_hor
-- 350 g Silk, beidseitig farbig — am nächsten an der Empfehlung „350 g matt“
-- aus dem Druckhinweis der Visitenkarten. Laut Katalog ist die Menge die
-- Zahl der Karten (Mindestmenge 50); also Menge = Auflage.
--
-- Nur einfügen, wo noch nichts steht — eine Zuordnung, die Uwe in der
-- Verwaltung geändert hat, bleibt.
-- ===========================================================================

INSERT INTO wm_anbieter_produkte (variante_id, anbieter, artikel, menge)
SELECT v.id, 'gelato', 'cards_pf_bd_pt_350-gsm-coated-silk_cl_4-4_hor', v.auflage
  FROM wm_varianten v JOIN wm_produkte p ON p.id = v.produkt_id
 WHERE p.vorlage = 'visitenkarte' AND v.auflage >= 50
ON DUPLICATE KEY UPDATE artikel = artikel;
