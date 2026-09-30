-- ===========================================================================
-- 115_partner_schutz.sql — Schutz der Vecom-Unterlagen im Partnerbereich
-- (30.09.2026, Uwe: „ja perfekt, aber statt Unterschrift ein Haken“).
--
-- Neue Partnervereinbarung (Fassung 2026-10-01) mit zwei Haken: dem ganzen
-- Text und ausdrücklich den belastenden Klauseln (Art. 1341/1342 c.c.).
-- Der Partnerbereich ist gesperrt, bis der Partner zugestimmt UND Uwe ihn
-- freigeschaltet hat. Dazu: Zugriffsprotokoll, erfasste Verstöße,
-- Kontrolleinträge (Fallen) und deren Treffer — Beweise für den Anwalt.
-- ===========================================================================

ALTER TABLE partner
  ADD COLUMN IF NOT EXISTS vereinbarung_klauseln_am DATETIME    NULL AFTER vereinbarung_version,
  ADD COLUMN IF NOT EXISTS vereinbarung_hash        CHAR(64)    NULL AFTER vereinbarung_klauseln_am,
  ADD COLUMN IF NOT EXISTS vereinbarung_ip_hash     CHAR(64)    NULL AFTER vereinbarung_hash,
  ADD COLUMN IF NOT EXISTS vereinbarung_sprache     CHAR(2)     NULL AFTER vereinbarung_ip_hash,
  ADD COLUMN IF NOT EXISTS freigeschaltet_am        DATETIME    NULL AFTER vereinbarung_sprache,
  ADD COLUMN IF NOT EXISTS freigeschaltet_von       VARCHAR(80) NULL AFTER freigeschaltet_am,
  ADD COLUMN IF NOT EXISTS gesperrt_am              DATETIME    NULL AFTER freigeschaltet_von,
  ADD COLUMN IF NOT EXISTS gesperrt_grund           VARCHAR(255) NULL AFTER gesperrt_am,
  ADD COLUMN IF NOT EXISTS neufassung_hinweis_am    DATETIME    NULL AFTER gesperrt_grund;

-- Wer hat wann was im Partnerbereich angesehen oder genommen.
CREATE TABLE IF NOT EXISTS partner_zugriffe (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id  INT UNSIGNED NOT NULL,
  art         VARCHAR(30)  NOT NULL,            -- seite | suche | reserviert | freigegeben | anruf | download | falle_reserviert
  firma_id    INT          NULL,                -- negativ = Kontrolleintrag
  info        VARCHAR(255) NOT NULL DEFAULT '',
  ip_hash     CHAR(16)     NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_pz_partner (partner_id, created_at),
  KEY ix_pz_firma (firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Von Uwe festgestellte Verstöße (mit Beleg), für die Akte.
CREATE TABLE IF NOT EXISTS partner_verstoesse (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id      INT UNSIGNED NOT NULL,
  art             VARCHAR(30)  NOT NULL,        -- daten | kundenschutz | logo | datenschutz | werbung | sonstiges
  festgestellt_am DATE         NOT NULL,
  beschreibung    TEXT         NOT NULL,
  beleg           TEXT         NULL,            -- Links, Zeugen, Fundstellen
  erfasst_von     VARCHAR(80)  NOT NULL DEFAULT '',
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_pv_partner (partner_id, festgestellt_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kontrolleinträge: je Partner eigene, erfundene Betriebe mit einer eigenen
-- E-Mail-Adresse. Schreibt jemand anderes dorthin, ist die Liste weitergegeben.
CREATE TABLE IF NOT EXISTS partner_fallen (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id      INT UNSIGNED NOT NULL,
  name            VARCHAR(190) NOT NULL,
  branche         VARCHAR(40)  NOT NULL DEFAULT '',
  ort             VARCHAR(80)  NOT NULL DEFAULT '',
  ort_schluessel  VARCHAR(80)  NOT NULL DEFAULT '',
  land            CHAR(2)      NOT NULL DEFAULT 'IT',
  email           VARCHAR(190) NOT NULL,
  reserviert_bis  DATE         NULL,
  treffer         INT UNSIGNED NOT NULL DEFAULT 0,
  letzter_treffer DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pf_email (email),
  UNIQUE KEY uq_pf_partner_ort (partner_id, ort_schluessel, branche)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_fallen_treffer (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  falle_id      INT UNSIGNED NOT NULL,
  partner_id    INT UNSIGNED NOT NULL,
  von           VARCHAR(190) NOT NULL DEFAULT '',
  betreff       VARCHAR(255) NOT NULL DEFAULT '',
  auszug        TEXT         NULL,
  nachricht_id  VARCHAR(190) NOT NULL,
  eingang_am    DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pft_nachricht (nachricht_id),
  KEY ix_pft_partner (partner_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
