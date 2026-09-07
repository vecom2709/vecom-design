-- 040_hosting.sql — Wunschdomain und Hosting fuer Kunden ohne eigene Website.
--
-- Der Ablauf, den diese Tabelle traegt: Der Fragebogen sagt "keine Website,
-- brauche eine neue Domain" -> die erste freie Wunschdomain wird dem Kunden
-- auf seiner Seite angeboten (vorgeschlagen) -> er stimmt den monatlichen
-- Kosten ausdruecklich zu (zugestimmt) -> bei der finalen Freigabe legt die
-- Verwaltung KAS-Account, Domain und Postfach an (angelegt) -> Uwe bestellt
-- die Domain im Domainbestellsystem und der Vertrag laeuft (aktiv).
--
-- zugang_blob: die frisch erzeugten Zugangsdaten, verschluesselt (Schluessel
-- liegt in app/config.local.php, nicht in dieser Datenbank). Er existiert
-- nur bis zum einmaligen Abruf durch den Kunden oder bis zugang_bis — was
-- zuerst kommt. Danach steht hier NULL, und wer die Daten verliert, setzt
-- sie im KAS neu.

CREATE TABLE IF NOT EXISTS hosting_auftraege (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id   INT UNSIGNED NOT NULL,
  project_id    INT UNSIGNED NULL,
  domain        VARCHAR(190) NOT NULL,
  status        VARCHAR(20)  NOT NULL DEFAULT 'vorgeschlagen',
  -- vorgeschlagen | zugestimmt | abgelehnt | angelegt | aktiv
  preis_cents   INT UNSIGNED NOT NULL DEFAULT 990,
  inklusive     TINYINT(1)   NOT NULL DEFAULT 0,  -- in Betreuung Plus/Premium enthalten
  kas_login     VARCHAR(40)  NULL,
  zugang_blob   TEXT NULL,
  zugang_bis    DATETIME NULL,
  zugestimmt_am DATETIME NULL,
  angelegt_am   DATETIME NULL,
  notiz         TEXT NULL,
  demo          TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_kunde (customer_id),
  KEY idx_projekt (project_id),
  KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Das Hosting-Paket: eigener kleiner Monatsvertrag, nie oeffentlich auf der
-- Website — er entsteht nur aus dem Domain-Ablauf. art 'hosting' haelt ihn
-- von den Betreuungspaketen getrennt, damit beide nebeneinander laufen
-- koennen (die Eindeutigkeitspruefung der Abos vergleicht die Art).
-- active 0: Das Paket taucht in keiner Bestell- oder Angebotsliste auf.
-- Abo::anlegen und Hosting::preisCents finden es ueber den slug — mehr
-- braucht es nicht, und mehr soll es auch nicht koennen.
INSERT INTO packages (slug, art, name, description, price_cents, monthly_cents, currency, active, oeffentlich, direktkauf, sort)
SELECT 'hosting', 'hosting', 'Domain & Hosting',
       'Domain, Speicherplatz, SSL-Zertifikat und E-Mail-Postfach auf eigenem Account.',
       0, 990, 'EUR', 0, 0, 0, 90
WHERE NOT EXISTS (SELECT 1 FROM packages WHERE slug = 'hosting');
