-- ===========================================================================
-- 058_domain_umzug.sql — Den Domain-Umzug begleiten (Phase 5, 25.09.2026)
--
-- Bisher: eine Textaufgabe an Uwe ("Auth-Code anfordern, DNS vorher
-- ablesen ..."). Der Code kam per Mail oder Telefon, die DNS-Eintraege las
-- Uwe von Hand ab, und ob der Umzug durch war, sah man nur beim Nachsehen.
--
-- stand:     code_fehlt | code_da | beantragt | fertig
-- code_blob: der Auth-Code, verschluesselt wie die Zugangsdaten (Schluessel
--            in config.local.php). Weg, sobald der KK-Antrag gestellt ist.
-- dns_json:  die oeffentlichen DNS-Eintraege VOR dem Umzug -- das, was im
--            KAS-DNS stehen muss, damit Mail und Seite nicht ausfallen.
-- sperre:    Transfersperre laut Registry (RDAP): gesperrt | frei | unklar.
-- Einmal je Hosting-Auftrag. Wiederholbar: IF NOT EXISTS.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS domain_umzuege (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  auftrag_id   INT UNSIGNED NOT NULL,
  customer_id  INT UNSIGNED NOT NULL,
  domain       VARCHAR(190) NOT NULL,
  stand        VARCHAR(20)  NOT NULL DEFAULT 'code_fehlt',
  code_blob    TEXT         NULL,
  code_am      DATETIME     NULL,
  dns_json     MEDIUMTEXT   NULL,
  dns_am       DATETIME     NULL,
  sperre       VARCHAR(12)  NULL,
  sperre_am    DATETIME     NULL,
  beantragt_am DATETIME     NULL,
  fertig_am    DATETIME     NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_domain_umzug_auftrag (auftrag_id),
  KEY idx_domain_umzug_stand (stand),
  CONSTRAINT fk_domain_umzug_auftrag FOREIGN KEY (auftrag_id)
    REFERENCES hosting_auftraege (id) ON DELETE CASCADE,
  CONSTRAINT fk_domain_umzug_kunde FOREIGN KEY (customer_id)
    REFERENCES customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
