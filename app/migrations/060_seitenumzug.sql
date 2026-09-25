-- ===========================================================================
-- 060_seitenumzug.sql — Eine bestehende Website 1:1 umziehen, begleitet
-- (Phase 6c, 25.09.2026)
--
-- Kopiert wird von Uwe, nicht automatisch: Dateien und Datenbank einer
-- fremden Seite in einem Web-Aufruf zu kopieren, laeuft in Zeitgrenzen und
-- zeigt Fehler erst spaet. Hier steht, was dafuer noetig ist und was schon
-- getan wurde -- und die Zugangsdaten zum alten Webspace, verschluesselt,
-- mit Loeschdatum.
--
-- stand:       angefragt | zugang_da | fertig | abgebrochen
-- zugang_blob: FTP (und ggf. Datenbank) beim alten Anbieter, verschluesselt
--              wie die Hosting-Zugangsdaten. Weg mit "fertig", "abgebrochen"
--              oder spaetestens am loeschen_am.
-- test_json:   Ergebnis der Verbindungspruefung (erreichbar, Login, WordPress?)
-- schritte:    JSON {schritt: Datum} -- Uwes Checkliste.
-- Wiederholbar: IF NOT EXISTS.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS seitenumzuege (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id  INT UNSIGNED NOT NULL,
  adresse      VARCHAR(190) NOT NULL,
  stand        VARCHAR(12)  NOT NULL DEFAULT 'angefragt',
  zugang_blob  TEXT         NULL,
  zugang_am    DATETIME     NULL,
  loeschen_am  DATETIME     NULL,
  test_json    TEXT         NULL,
  test_am      DATETIME     NULL,
  schritte     TEXT         NULL,
  fertig_am    DATETIME     NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_seitenumzug_stand (stand),
  CONSTRAINT fk_seitenumzug_kunde FOREIGN KEY (customer_id)
    REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
