-- ===========================================================================
-- 151_werbemittel_preisvergleich.sql — günstigster Drucker je Auflage
-- (03.10.2026, Uwe: „Versuche immer das günstigste zu suchen … selbe
-- Qualität wie bei günstigeren, nehme günstigeren“).
--
-- Je Auflage stehen die geprüften Angebote der Druckereien. Der Einkauf der
-- Variante (wm_varianten.einkauf_cent) ist danach NICHT mehr frei, sobald es
-- Angebote gibt: Er ist das günstigste davon, und wm_varianten.anbieter_
-- guenstig sagt, bei wem. Werbemittel::angebotSpeichern setzt beides in
-- einer Transaktion — eine Stelle, eine Wahrheit.
--
-- preis_cent ist, was Vecom WIRKLICH zahlt: inklusive Versand nach Italien
-- und inklusive IVA, solange keine Partita IVA da ist (dann ist die IVA
-- Kosten, nicht durchlaufend). netto_cent hält den Nettopreis daneben fest.
--
-- Erste Angebote: HelloPrint Italien, gelesen am 03.10.2026 auf
-- helloprint.com/it-it/bigliettidavisitaclassici — Patinata Opaca 400 g,
-- 4/4, 85 × 55 mm, IVA escl. 19,29 / 20,99 / 22,99 €, Versand „Economico“
-- ohne Aufpreis. Gelato: 85 × 55 mm Silk 350 g nur „10,38 € für 50 Stück“
-- abgelesen — für 250/500/1000 kein Preis, deshalb kein Eintrag (geraten
-- wird nicht).
-- ===========================================================================

CREATE TABLE wm_anbieter_preise (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  variante_id  INT UNSIGNED NOT NULL,
  anbieter     VARCHAR(40)  NOT NULL,
  preis_cent   INT UNSIGNED NOT NULL,
  netto_cent   INT UNSIGNED NULL,
  papier       VARCHAR(120) NOT NULL DEFAULT '',
  lieferung    VARCHAR(160) NOT NULL DEFAULT '',
  link         VARCHAR(400) NOT NULL DEFAULT '',
  geprueft_am  DATE         NOT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wm_preis_anbieter (variante_id, anbieter),
  CONSTRAINT fk_wm_preis_variante FOREIGN KEY (variante_id)
    REFERENCES wm_varianten (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE wm_varianten
  ADD COLUMN anbieter_guenstig VARCHAR(40) NULL AFTER einkauf_cent;

INSERT INTO wm_anbieter_preise (variante_id, anbieter, preis_cent, netto_cent, papier, lieferung, link, geprueft_am)
SELECT v.id, 'HelloPrint', x.brutto, x.netto, 'Patinata Opaca 400 g, 4/4', 'Economico ohne Aufpreis (ca. 11 Tage), Standard +6 €',
       'https://www.helloprint.com/it-it/bigliettidavisitaclassici', '2026-10-03'
  FROM wm_varianten v JOIN wm_produkte p ON p.id = v.produkt_id
  JOIN (SELECT 250 auflage, 1929 netto, 2353 brutto
        UNION ALL SELECT 500, 2099, 2561
        UNION ALL SELECT 1000, 2299, 2805) x ON x.auflage = v.auflage
 WHERE p.vorlage = 'visitenkarte'
ON DUPLICATE KEY UPDATE anbieter = anbieter;

-- Einkauf = günstigstes Angebot (nur wo es Angebote gibt).
UPDATE wm_varianten v
  JOIN (SELECT variante_id, MIN(preis_cent) AS minp FROM wm_anbieter_preise GROUP BY variante_id) m ON m.variante_id = v.id
   SET v.einkauf_cent = m.minp,
       v.anbieter_guenstig = (SELECT a.anbieter FROM wm_anbieter_preise a WHERE a.variante_id = v.id ORDER BY a.preis_cent, a.id LIMIT 1);
