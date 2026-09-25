-- ===========================================================================
-- 061_mailumzug.sql — E-Mails aus dem alten Postfach umziehen (Phase 6b, 25.09.2026)
--
-- Automatisch, ohne PHP-Erweiterung "imap" (app/src/Imap.php): Der Cron
-- kopiert portionsweise alle Ordner des alten Postfachs ins neue -- mit
-- Gelesen-Markierung und Datum. Beim alten Anbieter wird nichts geaendert.
-- Nach dem ersten Durchlauf holt er noch zwei Wochen lang nach, was neu
-- ankommt (bis die MX-Eintraege umgestellt sind), dann sind die Zugaenge weg.
--
-- zugang_blob:  alter und neuer Zugang, verschluesselt (config.local.php).
-- fortschritt:  JSON je Ordner {quelle, ziel, uidvalidity, letzte, anzahl}.
-- Wiederholbar: IF NOT EXISTS.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS mailumzuege (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id   INT UNSIGNED NOT NULL,
  adresse       VARCHAR(190) NOT NULL,
  ziel_adresse  VARCHAR(190) NULL,
  stand         VARCHAR(12)  NOT NULL DEFAULT 'angefragt',
  zugang_blob   TEXT         NULL,
  fortschritt   MEDIUMTEXT   NULL,
  kopiert       INT UNSIGNED NOT NULL DEFAULT 0,
  gesamt        INT UNSIGNED NOT NULL DEFAULT 0,
  zu_gross      INT UNSIGNED NOT NULL DEFAULT 0,
  fehler        VARCHAR(500) NULL,
  letzter_lauf  DATETIME     NULL,
  fertig_am     DATETIME     NULL,
  loeschen_am   DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mailumzug_stand (stand),
  CONSTRAINT fk_mailumzug_kunde FOREIGN KEY (customer_id)
    REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
