-- ===========================================================================
-- 022_baukasten.sql — Der Preis kommt aus dem Bedarf, nicht aus einem Paket.
--
-- WARUM DIESE UMSTELLUNG
--
-- Drei feste Pakete auf der Website haben zwei Nachteile: Sie deckeln den
-- Wert nach oben, und sie laden zum Vergleichen ein. Wer mehr braucht als
-- Premium, zahlt trotzdem nur Premium; wer weniger braucht, sucht sich das
-- billigste. Ab hier beschreibt der Kunde seinen Bedarf, und der Preis
-- entsteht aus dem, was er wirklich braucht.
--
-- VIER TABELLEN, WEIL ES VIER DINGE SIND
--
-- bausteine          Der Katalog. Was eine Leistung kostet. Gehoert mir.
-- bedarf             Was ein Kunde angegeben hat. Gehoert ihm.
-- angebote           Was ich ihm daraufhin vorschlage. Verbindlich, befristet.
-- angebot_positionen Die Zeilen des Angebots.
--
-- Der Bedarf wird NICHT zum Angebot umgeschrieben. Beide bleiben stehen:
-- Der Bedarf ist, was der Kunde wollte; das Angebot ist, was ich daraus
-- gemacht habe. Wenn spaeter jemand fragt, warum der Preis so ist, steht
-- beides nebeneinander.
--
-- SPANNE STATT EINER ZAHL
--
-- Jeder Baustein hat preis_cents und preis_bis_cents. Am Ende des
-- Konfigurators sieht der Kunde daraus eine Spanne ("700-1.100 EUR"), nie
-- eine Zahl, die spaeter nicht haelt. Ist preis_bis_cents 0, gilt
-- preis_cents fuer beide Enden — dann ist die Position sicher kalkulierbar.
--
-- Betraege ueberall als ganze Cent. Nie Fliesskomma fuer Geld.
-- ===========================================================================

CREATE TABLE bausteine (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug              VARCHAR(60)  NOT NULL,
  -- basis | seite | sprache | funktion | inhalt | betreuung
  gruppe            VARCHAR(20)  NOT NULL DEFAULT 'funktion',
  name_it           VARCHAR(160) NOT NULL,
  name_de           VARCHAR(160) NOT NULL DEFAULT '',
  name_en           VARCHAR(160) NOT NULL DEFAULT '',
  -- Was auf dem Angebot unter der Zeile steht. Darf leer bleiben.
  text_it           VARCHAR(400) NOT NULL DEFAULT '',
  text_de           VARCHAR(400) NOT NULL DEFAULT '',
  text_en           VARCHAR(400) NOT NULL DEFAULT '',
  preis_cents       INT UNSIGNED NOT NULL DEFAULT 0,
  -- Obere Grenze fuer die Spanne. 0 heisst: so sicher wie preis_cents.
  preis_bis_cents   INT UNSIGNED NOT NULL DEFAULT 0,
  -- Monatlich statt einmalig (Betreuung). Beides zugleich gibt es nicht.
  monatlich         TINYINT(1)   NOT NULL DEFAULT 0,
  -- Zaehlt die Position mal Menge? (Seiten, Sprachen) Sonst einmal.
  je_einheit        TINYINT(1)   NOT NULL DEFAULT 0,
  aktiv             TINYINT(1)   NOT NULL DEFAULT 1,
  sortierung        INT          NOT NULL DEFAULT 0,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  demo              TINYINT(1)   NOT NULL DEFAULT 0,
  UNIQUE KEY uq_baustein_slug (slug),
  KEY ix_baustein_gruppe (gruppe, sortierung)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bedarf (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  -- Der Kunde entsteht erst beim Absenden. Solange er nur klickt, ist er niemand.
  customer_id       INT UNSIGNED NULL,
  anfrage_id        INT UNSIGNED NULL,
  -- Derselbe Gedanke wie beim Fragebogen: ein langer Schluessel statt eines Kontos.
  token             CHAR(48)     NOT NULL,
  sprache           CHAR(2)      NOT NULL DEFAULT 'it',
  -- Die Antworten als JSON. Die Fragen aendern sich schneller als eine Tabelle.
  antworten         LONGTEXT     NULL,
  name              VARCHAR(120) NOT NULL DEFAULT '',
  email             VARCHAR(190) NOT NULL DEFAULT '',
  telefon           VARCHAR(60)  NOT NULL DEFAULT '',
  firma             VARCHAR(160) NOT NULL DEFAULT '',
  -- Die Spanne, die dem Kunden am Ende gezeigt wurde. Festgehalten, damit
  -- spaeter nachvollziehbar ist, womit er gerechnet hat.
  von_cents         INT UNSIGNED NOT NULL DEFAULT 0,
  bis_cents         INT UNSIGNED NOT NULL DEFAULT 0,
  monatlich_cents   INT UNSIGNED NOT NULL DEFAULT 0,
  -- offen | abgesendet | angebot | verworfen
  status            VARCHAR(20)  NOT NULL DEFAULT 'offen',
  schritt           TINYINT      NOT NULL DEFAULT 1,
  abgesendet_am     DATETIME     NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  demo              TINYINT(1)   NOT NULL DEFAULT 0,
  UNIQUE KEY uq_bedarf_token (token),
  KEY ix_bedarf_status (status, created_at),
  KEY ix_bedarf_kunde (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE angebote (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nummer            VARCHAR(20)  NOT NULL,
  customer_id       INT UNSIGNED NOT NULL,
  bedarf_id         INT UNSIGNED NULL,
  project_id        INT UNSIGNED NULL,
  order_id          INT UNSIGNED NULL,
  sprache           CHAR(2)      NOT NULL DEFAULT 'it',
  -- entwurf | gesendet | angenommen | abgelehnt | abgelaufen
  status            VARCHAR(20)  NOT NULL DEFAULT 'entwurf',
  titel             VARCHAR(200) NOT NULL DEFAULT '',
  -- Freier Text ueber den Positionen. Der Satz, der das Angebot menschlich macht.
  einleitung        TEXT         NULL,
  summe_cents       INT UNSIGNED NOT NULL DEFAULT 0,
  monatlich_cents   INT UNSIGNED NOT NULL DEFAULT 0,
  currency          CHAR(3)      NOT NULL DEFAULT 'EUR',
  anzahlung_prozent TINYINT UNSIGNED NOT NULL DEFAULT 50,
  gueltig_bis       DATE         NULL,
  -- Der Kunde nimmt ueber diesen Schluessel an. Kein Konto, wie ueberall sonst.
  token             CHAR(48)     NOT NULL,
  gesendet_am       DATETIME     NULL,
  angenommen_am     DATETIME     NULL,
  abgelehnt_am      DATETIME     NULL,
  abgelehnt_grund   VARCHAR(400) NOT NULL DEFAULT '',
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  demo              TINYINT(1)   NOT NULL DEFAULT 0,
  UNIQUE KEY uq_angebot_nummer (nummer),
  UNIQUE KEY uq_angebot_token (token),
  KEY ix_angebot_status (status, created_at),
  KEY ix_angebot_kunde (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE angebot_positionen (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  angebot_id        INT UNSIGNED NOT NULL,
  -- Nur zur Herkunft. Die Zeile lebt weiter, auch wenn der Baustein verschwindet.
  baustein_slug     VARCHAR(60)  NOT NULL DEFAULT '',
  bezeichnung       VARCHAR(200) NOT NULL,
  beschreibung      VARCHAR(400) NOT NULL DEFAULT '',
  menge             INT UNSIGNED NOT NULL DEFAULT 1,
  einzel_cents      INT UNSIGNED NOT NULL DEFAULT 0,
  summe_cents       INT UNSIGNED NOT NULL DEFAULT 0,
  monatlich         TINYINT(1)   NOT NULL DEFAULT 0,
  sortierung        INT          NOT NULL DEFAULT 0,
  KEY ix_position_angebot (angebot_id, sortierung),
  CONSTRAINT fk_position_angebot FOREIGN KEY (angebot_id)
    REFERENCES angebote (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Der Nummernkreis fuer Angebote, wie bei Rechnungen und Belegen schon ueblich.
INSERT INTO settings (skey, svalue) VALUES ('angebot_praefix', 'AN')
  ON DUPLICATE KEY UPDATE skey = skey;
INSERT INTO settings (skey, svalue) VALUES ('angebot_gueltig_tage', '14')
  ON DUPLICATE KEY UPDATE skey = skey;
-- Solange 1: Der Konfigurator zeigt am Ende eine Richtwert-Spanne.
INSERT INTO settings (skey, svalue) VALUES ('bedarf_spanne_zeigen', '1')
  ON DUPLICATE KEY UPDATE skey = skey;
