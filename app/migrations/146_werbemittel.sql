-- ===========================================================================
-- 146_werbemittel.sql — Marketing Center, Phase 1: der Katalog
-- (03.10.2026, Uwe: Ja zur Bestandsaufnahme und zu Phase 1).
--
-- Partner können hier später personalisierte Werbemittel kaufen. Phase 1
-- legt nur den Katalog an: was es gibt, was es im Einkauf kostet und mit
-- welcher Marge es verkauft wird. Bezahlung, Druckanbieter und Bestellungen
-- kommen in eigenen Migrationen, wenn die Phase dran ist.
--
-- Präfix wm_ (Werbemittel), weil mk_ dem Marketing-Studio gehört.
--
-- DREI TABELLEN, WEIL ES DREI DINGE SIND
--
-- wm_kategorien   Wie der Katalog gegliedert ist.
-- wm_produkte     Was es gibt: Format, Beschnitt, Vorlage, Margenregel.
-- wm_varianten    Was man davon wählen kann (Auflage, Papier) und was es
--                 im Einkauf kostet. Der Verkaufspreis wird NICHT gespeichert,
--                 sondern aus Einkauf und Marge gerechnet (Werbemittel::preis),
--                 damit eine geänderte Marge sofort überall gilt und es keine
--                 zwei Wahrheiten über denselben Preis gibt.
--
-- Partner sehen nie einkauf_cent und nie die Marge — die Klasse gibt ihnen
-- nur den Endpreis heraus.
--
-- Beträge als ganze Cent. Nur hinzufügen. Rückweg: die drei Tabellen und die
-- zwei wm_-Einstellungen entfernen.
-- ===========================================================================

CREATE TABLE wm_kategorien (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(40)  NOT NULL,
  name_it       VARCHAR(120) NOT NULL,
  name_de       VARCHAR(120) NOT NULL DEFAULT '',
  name_en       VARCHAR(120) NOT NULL DEFAULT '',
  sortierung    INT          NOT NULL DEFAULT 0,
  aktiv         TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wm_kategorie_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wm_produkte (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  -- VEC-0001 … — die Nummer, unter der ein Produkt überall heißt, auch
  -- gegenüber Druckanbietern. Wird nach dem Anlegen aus der id gesetzt.
  nummer          VARCHAR(20)  NULL,
  kategorie_id    INT UNSIGNED NOT NULL,
  name_it         VARCHAR(160) NOT NULL,
  name_de         VARCHAR(160) NOT NULL DEFAULT '',
  name_en         VARCHAR(160) NOT NULL DEFAULT '',
  text_it         VARCHAR(400) NOT NULL DEFAULT '',
  text_de         VARCHAR(400) NOT NULL DEFAULT '',
  text_en         VARCHAR(400) NOT NULL DEFAULT '',
  -- Endformat und Beschnitt in Zehntelmillimetern (850 = 85,0 mm), damit
  -- auch 3,5 mm Beschnitt ohne Fließkomma geht.
  breite_zmm      INT UNSIGNED NOT NULL DEFAULT 0,
  hoehe_zmm       INT UNSIGNED NOT NULL DEFAULT 0,
  beschnitt_zmm   INT UNSIGNED NOT NULL DEFAULT 30,
  -- Welche Vorlage die Druckdatei erzeugt ('visitenkarte' = PartnerKarten).
  -- Leer = noch keine Vorlage; das Produkt kann dann nicht bestellt werden.
  vorlage         VARCHAR(40)  NOT NULL DEFAULT '',
  -- NULL = Standard aus den Einstellungen (wm_marge_prozent / wm_mindestmarge_cent).
  marge_prozent   SMALLINT UNSIGNED NULL,
  mindestmarge_cent INT UNSIGNED NULL,
  -- Neu angelegte Produkte sind aus, bis Uwe Einkaufspreise eingetragen hat.
  aktiv           TINYINT(1)   NOT NULL DEFAULT 0,
  sortierung      INT          NOT NULL DEFAULT 0,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wm_produkt_nummer (nummer),
  KEY ix_wm_produkt_kategorie (kategorie_id, sortierung),
  CONSTRAINT fk_wm_produkt_kategorie FOREIGN KEY (kategorie_id)
    REFERENCES wm_kategorien (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wm_varianten (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  produkt_id    INT UNSIGNED NOT NULL,
  -- Was der Partner wählt, z. B. „250 Stück · 400 g matt“.
  name_it       VARCHAR(120) NOT NULL,
  name_de       VARCHAR(120) NOT NULL DEFAULT '',
  name_en       VARCHAR(120) NOT NULL DEFAULT '',
  auflage       INT UNSIGNED NOT NULL DEFAULT 1,
  -- Einkaufspreis netto inkl. Versand zu Vecom, von Hand gepflegt, bis ein
  -- Druckanbieter angebunden ist. 0 = noch kein Preis → nicht bestellbar.
  einkauf_cent  INT UNSIGNED NOT NULL DEFAULT 0,
  aktiv         TINYINT(1)   NOT NULL DEFAULT 1,
  sortierung    INT          NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_wm_variante_produkt (produkt_id, sortierung),
  CONSTRAINT fk_wm_variante_produkt FOREIGN KEY (produkt_id)
    REFERENCES wm_produkte (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Standardmarge auf den Einkauf in Prozent, und was je Position mindestens
-- bleiben muss. Gilt, wo ein Produkt keine eigene Regel hat.
INSERT INTO settings (skey, svalue) VALUES ('wm_marge_prozent', '35')
  ON DUPLICATE KEY UPDATE skey = skey;
INSERT INTO settings (skey, svalue) VALUES ('wm_mindestmarge_cent', '500')
  ON DUPLICATE KEY UPDATE skey = skey;

-- Die Gliederung aus Uwes Vorgabe. Ändern und ergänzen in der Verwaltung.
INSERT INTO wm_kategorien (slug, name_it, name_de, name_en, sortierung) VALUES
  ('visitenkarten', 'Biglietti da visita',   'Visitenkarten',          'Business cards',      10),
  ('flyer',         'Volantini e pieghevoli','Flyer & Folder',         'Flyers & leaflets',   20),
  ('aufkleber',     'Adesivi',               'Aufkleber',              'Stickers',            30),
  ('aufsteller',    'Espositori e roll-up',  'Aufsteller & Roll-ups',  'Displays & roll-ups', 40),
  ('textil',        'Abbigliamento',         'Textilien',              'Apparel',             50),
  ('werbeartikel',  'Gadget promozionali',   'Werbeartikel',           'Promotional items',   60)
  ON DUPLICATE KEY UPDATE slug = slug;

-- Das erste Produkt: die Visitenkarte, die es heute schon druckfertig gibt
-- (PartnerKarten, 85 × 55 mm + 3 mm Beschnitt). Aus, bis Preise da sind.
INSERT INTO wm_produkte (kategorie_id, name_it, name_de, name_en, text_it, text_de, text_en,
                         breite_zmm, hoehe_zmm, beschnitt_zmm, vorlage, aktiv, sortierung)
SELECT k.id, 'Biglietto da visita Vecom Partner', 'Visitenkarte Vecom-Partner', 'Vecom partner business card',
       'Con il suo nome, il suo codice partner e il QR verso la sua pagina.',
       'Mit Ihrem Namen, Ihrer Partner-ID und dem QR-Code zu Ihrer Seite.',
       'With your name, your partner ID and the QR code to your page.',
       850, 550, 30, 'visitenkarte', 0, 10
  FROM wm_kategorien k
 WHERE k.slug = 'visitenkarten'
   AND NOT EXISTS (SELECT 1 FROM wm_produkte WHERE vorlage = 'visitenkarte');

UPDATE wm_produkte SET nummer = CONCAT('VEC-', LPAD(id, 4, '0')) WHERE nummer IS NULL;

INSERT INTO wm_varianten (produkt_id, name_it, name_de, name_en, auflage, sortierung)
SELECT p.id, v.n_it, v.n_de, v.n_en, v.auflage, v.s
  FROM wm_produkte p
  JOIN (SELECT '250 pezzi'  n_it, '250 Stück'  n_de, '250 pieces'  n_en, 250  auflage, 10 s
        UNION ALL SELECT '500 pezzi',  '500 Stück',  '500 pieces',  500,  20
        UNION ALL SELECT '1000 pezzi', '1000 Stück', '1000 pieces', 1000, 30) v
 WHERE p.vorlage = 'visitenkarte'
   AND NOT EXISTS (SELECT 1 FROM wm_varianten x WHERE x.produkt_id = p.id);
