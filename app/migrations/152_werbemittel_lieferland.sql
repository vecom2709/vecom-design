-- ===========================================================================
-- 152_werbemittel_lieferland.sql — Preis und Druckerei je Lieferland
-- (04.10.2026, Uwe: „Lieferung soll Italien und Deutschland sein, also immer
-- entsprechend wo der Partner wohnt“).
--
-- Ein Angebot gilt jetzt für EIN Lieferland: Wer nach Deutschland liefert,
-- ist oft eine andere (und günstigere) Druckerei als für Italien, mit
-- anderem Versand und anderer Mehrwertsteuer. Der Einkauf einer Bestellung
-- ist das günstigste Angebot für das Land der Lieferadresse
-- (Werbemittel::einkauf). Gibt es für ein Land kein Angebot, ist dorthin
-- nicht bestellbar — geraten wird kein Preis.
--
-- Erste Angebote für Deutschland: WIRmachenDRUCK, gelesen am 03.10.2026 auf
-- wir-machen-druck.de (Visitenkarten quer 4/4, 85 × 55 mm, „350 g/m²
-- hochwertiger Qualitätsdruck matt“): 13,02 / 16,43 / 18,24 € netto,
-- kostenloser Versand in Deutschland. Vecom zahlt brutto (19 % MwSt.,
-- ohne Partita IVA keine Erstattung): 15,49 / 19,55 / 21,71 €.
--
-- wm_positionen.anbieter: bei wem diese Bestellung am günstigsten gedruckt
-- wird — festgehalten im Moment der Bestellung.
-- ===========================================================================

ALTER TABLE wm_anbieter_preise
  ADD COLUMN land CHAR(2) NOT NULL DEFAULT 'IT' AFTER anbieter;

ALTER TABLE wm_anbieter_preise
  DROP INDEX uq_wm_preis_anbieter,
  ADD UNIQUE KEY uq_wm_preis_anbieter_land (variante_id, anbieter, land);

ALTER TABLE wm_positionen
  ADD COLUMN anbieter VARCHAR(40) NULL AFTER einkauf_cent;

INSERT INTO wm_anbieter_preise (variante_id, anbieter, land, preis_cent, netto_cent, papier, lieferung, link, geprueft_am)
SELECT v.id, 'WIRmachenDRUCK', 'DE', x.brutto, x.netto, '350 g matt, 4/4', 'Versand in Deutschland kostenlos (ca. 3 Arbeitstage)',
       'https://www.wir-machen-druck.de/visitenkarten-quer-44-farbig-85-x-55-mm-(beidseitiger-druck),detail,21440.html', '2026-10-03'
  FROM wm_varianten v JOIN wm_produkte p ON p.id = v.produkt_id
  JOIN (SELECT 250 auflage, 1302 netto, 1549 brutto
        UNION ALL SELECT 500, 1643, 1955
        UNION ALL SELECT 1000, 1824, 2171) x ON x.auflage = v.auflage
 WHERE p.vorlage = 'visitenkarte'
ON DUPLICATE KEY UPDATE anbieter = anbieter;
