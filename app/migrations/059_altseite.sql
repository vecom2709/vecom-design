-- ===========================================================================
-- 059_altseite.sql — Die alte Website als Vorlage sichern (Phase 6a, 25.09.2026)
--
-- Texte, Bilder und PDFs (Speisekarte, Preisliste) der bisherigen Seite
-- werden eingesammelt und als EINE ZIP-Datei in die Ablage des Kunden
-- gelegt -- Material fuer die neue Seite. Die alte Seite wird dabei nicht
-- veraendert; gelesen wird nur, was oeffentlich ist.
--
-- Gesammelt wird im Cron, portionsweise: Eine Seite mit dreissig
-- Unterseiten und achtzig Bildern passt in keinen einzelnen Aufruf.
-- warteschlange/besucht/seiten/bilder sind JSON und tragen den Stand von
-- einem Lauf zum naechsten.
-- Wiederholbar: IF NOT EXISTS.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS altseiten (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  customer_id   INT UNSIGNED NOT NULL,
  project_id    INT UNSIGNED NULL,
  adresse       VARCHAR(255) NOT NULL,
  host          VARCHAR(190) NOT NULL,
  stand         VARCHAR(12)  NOT NULL DEFAULT 'offen',
  warteschlange MEDIUMTEXT   NULL,
  besucht       MEDIUMTEXT   NULL,
  seiten        MEDIUMTEXT   NULL,
  bilder        MEDIUMTEXT   NULL,
  datei_id      INT UNSIGNED NULL,
  fehler        VARCHAR(500) NULL,
  fertig_am     DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_altseiten_stand (stand),
  CONSTRAINT fk_altseiten_kunde FOREIGN KEY (customer_id)
    REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
