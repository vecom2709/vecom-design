-- AI Office Stufe 3 (07.10.2026): der Umsatz-Spürhund (V5). Jeden Tag sucht er in den eigenen Daten nach
-- Geld, das liegen bleibt: fertige Seiten ohne Betreuung, Seiten ohne Hosting bei Vecom, Angebote ohne
-- Antwort, Interessenten, die warten. Eine Zeile je Chance (Art + Bezug), damit „neu seit gestern“ zählbar
-- ist und ein Verwerfen hält. Verschickt wird hier nichts.
CREATE TABLE IF NOT EXISTS umsatz_chancen (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  art              VARCHAR(20)  NOT NULL,
  bezug            VARCHAR(60)  NOT NULL,
  kunde_id         INT UNSIGNED NULL,
  projekt_id       INT UNSIGNED NULL,
  angebot_id       INT UNSIGNED NULL,
  firma_id         INT UNSIGNED NULL,
  titel            VARCHAR(255) NOT NULL,
  grund            TEXT         NULL,
  vorschlag        VARCHAR(255) NULL,
  wert_cents       INT          NULL,
  wert_art         VARCHAR(10)  NULL,
  status           VARCHAR(16)  NOT NULL DEFAULT 'offen',
  gefunden_am      DATETIME     NOT NULL,
  zuletzt_am       DATETIME     NOT NULL,
  erledigt_am      DATETIME     NULL,
  verworfen_grund  VARCHAR(255) NULL,
  verworfen_von    VARCHAR(80)  NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_umsatz_chancen (art, bezug),
  KEY ix_umsatz_chancen_status (status, gefunden_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
