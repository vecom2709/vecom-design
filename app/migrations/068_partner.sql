-- ===========================================================================
-- 068_partner.sql — Das Partnerprogramm (26.09.2026, Uwe: „Alles und b“,
-- Auszahlung voll automatisch über Stripe, 10 % / ab 50 €)
--
-- GETRENNT VON DEN EMPFEHLUNGEN. Kunden werben Kunden und bekommen Rabatt
-- auf die Betreuung (empfehlungen, Migration 023) -- das bleibt. Partner
-- sind nicht zwingend Kunden und bekommen Geld. Zwei Tabellen, zwei Wege:
-- Ein Rabatt und eine Auszahlung haben verschiedene Buchungen, verschiedene
-- Steuerfolgen und verschiedene Menschen, die fragen.
--
-- partner_klicks zählt nur je Tag -- keine IP, kein Browser, kein Cookie.
-- Die Website verspricht „keine Tracking-Cookies, kein Cookie-Banner“, und
-- dabei bleibt es: Zugeordnet wird beim ersten Kontakt am KUNDEN
-- (partner_zuordnungen), nicht im Browser.
--
-- partner_provisionen: je Zahlung höchstens eine Provision (uq_pp_zahlung),
-- auch wenn Webhook, Abgleich und Klick gleichzeitig kommen.
-- Beträge in ganzen Cent, Prozentsätze in Basispunkten (1000 = 10,00 %).
-- Wiederholbar: IF NOT EXISTS.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS partner (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code               VARCHAR(16)  NOT NULL,
  token              CHAR(48)     NOT NULL,
  name               VARCHAR(160) NOT NULL,
  email              VARCHAR(190) NOT NULL,
  firma              VARCHAR(160) NOT NULL DEFAULT '',
  steuer_nr          VARCHAR(40)  NOT NULL DEFAULT '',
  kanal              VARCHAR(300) NOT NULL DEFAULT '',
  bewerbung_text     TEXT         NULL,
  sprache            CHAR(2)      NOT NULL DEFAULT 'it',
  -- bewerbung | aktiv | pausiert | abgelehnt
  status             VARCHAR(12)  NOT NULL DEFAULT 'bewerbung',
  customer_id        INT UNSIGNED NULL,
  -- NULL heißt: der Standard aus den Einstellungen gilt
  provision_art      VARCHAR(8)   NULL,
  provision_wert     INT UNSIGNED NULL,
  gilt_website       TINYINT(1)   NULL,
  gilt_betreuung     TINYINT(1)   NULL,
  gilt_hosting       TINYINT(1)   NULL,
  wiederkehrend_monate SMALLINT UNSIGNED NULL,
  freigabe_noetig    TINYINT(1)   NULL,
  monatsmail         TINYINT(1)   NOT NULL DEFAULT 1,
  vereinbarung_am    DATETIME     NULL,
  vereinbarung_text  TEXT         NULL,
  vereinbarung_version VARCHAR(20) NULL,
  stripe_konto       VARCHAR(40)  NULL,
  stripe_bereit      TINYINT(1)   NOT NULL DEFAULT 0,
  stripe_geprueft_am DATETIME     NULL,
  notiz              TEXT         NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_partner_code (code),
  UNIQUE KEY uq_partner_token (token),
  KEY ix_partner_email (email),
  KEY ix_partner_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_klicks (
  partner_id  INT UNSIGNED NOT NULL,
  tag         DATE         NOT NULL,
  anzahl      INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (partner_id, tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_zuordnungen (
  customer_id  INT UNSIGNED NOT NULL,
  partner_id   INT UNSIGNED NOT NULL,
  -- link | code | hand
  quelle       VARCHAR(8)   NOT NULL DEFAULT 'link',
  bedarf_id    INT UNSIGNED NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (customer_id),
  KEY ix_pz_partner (partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_provisionen (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  partner_id       INT UNSIGNED NOT NULL,
  customer_id      INT UNSIGNED NOT NULL,
  payment_id       INT UNSIGNED NOT NULL,
  order_id         INT UNSIGNED NULL,
  -- website | betreuung | hosting
  art              VARCHAR(10)  NOT NULL,
  basis_cents      INT UNSIGNED NOT NULL,
  provision_cents  INT UNSIGNED NOT NULL,
  -- Steuereinbehalt (Ritenuta), falls eingestellt: bleibt bei Vecom und
  -- geht per F24 ans Finanzamt; ausgezahlt wird provision - einbehalt.
  einbehalt_cents  INT UNSIGNED NOT NULL DEFAULT 0,
  satz             VARCHAR(30)  NOT NULL DEFAULT '',
  -- wartet (Widerrufsfrist) | freigabe (Uwe prüft) | bereit | ausgezahlt |
  -- storniert (vor Auszahlung) | zurueckgeholt | rueckforderung (von Hand offen)
  status           VARCHAR(14)  NOT NULL DEFAULT 'wartet',
  frei_ab          DATETIME     NOT NULL,
  stripe_transfer  VARCHAR(40)  NULL,
  auszahlung_id    INT UNSIGNED NULL,
  ausgezahlt_am    DATETIME     NULL,
  grund            VARCHAR(255) NOT NULL DEFAULT '',
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pp_zahlung (payment_id),
  KEY ix_pp_partner (partner_id, status),
  KEY ix_pp_status (status, frei_ab)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_auszahlungen (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nummer        VARCHAR(20)  NOT NULL,
  partner_id    INT UNSIGNED NOT NULL,
  betrag_cents  INT UNSIGNED NOT NULL,
  -- stripe | hand
  weg           VARCHAR(8)   NOT NULL,
  referenz      VARCHAR(120) NOT NULL DEFAULT '',
  automatisch   TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pa_nummer (nummer),
  KEY ix_pa_partner (partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (skey, svalue) VALUES
  ('partner_standard_art', 'prozent'),
  ('partner_standard_wert', '1000'),
  ('partner_mindest_cents', '5000'),
  ('partner_sperrtage', '14'),
  ('partner_zuordnung_monate', '12'),
  ('partner_gilt_website', '1'),
  ('partner_gilt_betreuung', '1'),
  ('partner_gilt_hosting', '1'),
  ('partner_wiederkehrend_monate', '12'),
  ('partner_freigabe_noetig', '0'),
  ('partner_auto_auszahlen', '1'),
  ('partner_auto_tageslimit_cents', '100000'),
  ('partner_bewerbung_offen', '1'),
  ('partner_einbehalt_bp', '0')
ON DUPLICATE KEY UPDATE skey = skey;
