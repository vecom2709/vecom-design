-- ===========================================================================
-- 116_partner_tracking.sql — Partner-Tracking (30.09.2026, Uwe: „Alles“).
--
-- Ein Besuch = eine Sitzung, die über einen Partnerlink begann (oder, mit
-- Einwilligung, später mit dem gemerkten Partner wiederkam). Aufgezeichnet
-- werden NUR Partner-Besuche; alle anderen bleiben in der anonymen Zählung
-- (z.php). Keine IP im Klartext (nur ein täglich wechselnder Hash gegen
-- Mehrfachklicks), kein Fingerprint, keine Stadt.
-- Rohdaten werden nach spur_rohdaten_tage zu Tageszahlen (spur_tage)
-- zusammengefasst und gelöscht (Spur::aufraeumen, Cron).
-- ===========================================================================

CREATE TABLE IF NOT EXISTS spur_besuche (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visitor_id    CHAR(12)     NOT NULL,              -- VIS-XXXXXXXX
  session_id    CHAR(32)     NOT NULL,
  partner_id    INT UNSIGNED NOT NULL,
  kanal         VARCHAR(20)  NULL,
  neu           TINYINT(1)   NOT NULL DEFAULT 1,     -- 0 = wiederkehrend (nur mit Einwilligung erkennbar)
  einwilligung  TINYINT(1)   NOT NULL DEFAULT 0,
  einwilligung_am DATETIME   NULL,
  einstieg      VARCHAR(190) NOT NULL DEFAULT '',
  aktuell       VARCHAR(190) NOT NULL DEFAULT '',
  seiten        INT UNSIGNED NOT NULL DEFAULT 0,
  ref_link      VARCHAR(190) NOT NULL DEFAULT '',
  referrer      VARCHAR(120) NOT NULL DEFAULT '',     -- nur die Domain
  quelle        VARCHAR(30)  NOT NULL DEFAULT 'direkt',
  utm_source    VARCHAR(60)  NOT NULL DEFAULT '',
  utm_medium    VARCHAR(60)  NOT NULL DEFAULT '',
  utm_campaign  VARCHAR(80)  NOT NULL DEFAULT '',
  utm_content   VARCHAR(80)  NOT NULL DEFAULT '',
  geraet        VARCHAR(12)  NOT NULL DEFAULT '',     -- smartphone | tablet | desktop
  browser       VARCHAR(24)  NOT NULL DEFAULT '',
  system        VARCHAR(16)  NOT NULL DEFAULT '',
  sprache       VARCHAR(5)   NOT NULL DEFAULT '',
  land          CHAR(2)      NOT NULL DEFAULT '',
  region        VARCHAR(80)  NOT NULL DEFAULT '',
  status        VARCHAR(20)  NOT NULL DEFAULT 'besucher', -- besucher | interessent | rechner | anfrage | angebot | kunde | abgeschlossen
  verdacht      TINYINT(1)   NOT NULL DEFAULT 0,     -- auffällige Mehrfachklicks: zählt nicht als Besucher
  ip_hash       CHAR(16)     NOT NULL DEFAULT '',     -- sha256(ip|Tag|Geheimnis), täglich neu
  customer_id   INT UNSIGNED NULL,
  anfrage_id    INT UNSIGNED NULL,
  start_am      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  zuletzt_am    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_spur_session (session_id),
  KEY ix_spur_partner (partner_id, start_am),
  KEY ix_spur_visitor (visitor_id),
  KEY ix_spur_zuletzt (zuletzt_am),
  KEY ix_spur_kunde (customer_id),
  KEY ix_spur_ip (ip_hash, partner_id, start_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spur_ereignisse (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  besuch_id     BIGINT UNSIGNED NULL,               -- NULL: Ereignis aus der Verwaltung zu einem zugeordneten Kunden ohne aufgezeichneten Besuch
  visitor_id    CHAR(12)     NOT NULL DEFAULT '',
  session_id    CHAR(32)     NOT NULL DEFAULT '',
  partner_id    INT UNSIGNED NOT NULL,
  event_type    VARCHAR(40)  NOT NULL,
  seite         VARCHAR(190) NOT NULL DEFAULT '',
  meta          VARCHAR(500) NOT NULL DEFAULT '',
  customer_id   INT UNSIGNED NULL,
  betrag_cents  INT          NULL,                 -- nur bei payment_completed / order_created / offer_created
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_spe_partner (partner_id, created_at),
  KEY ix_spe_typ (event_type, created_at),
  KEY ix_spe_session (session_id),
  KEY ix_spe_visitor (visitor_id),
  KEY ix_spe_besuch (besuch_id, id),
  KEY ix_spe_kunde (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tageszahlen, die bleiben, wenn die Rohdaten gelöscht sind.
CREATE TABLE IF NOT EXISTS spur_tage (
  partner_id    INT UNSIGNED NOT NULL,
  tag           DATE         NOT NULL,
  event_type    VARCHAR(40)  NOT NULL,
  anzahl        INT UNSIGNED NOT NULL DEFAULT 0,
  besucher      INT UNSIGNED NOT NULL DEFAULT 0,
  betrag_cents  BIGINT       NOT NULL DEFAULT 0,
  PRIMARY KEY (partner_id, tag, event_type),
  KEY ix_spt_tag (tag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Der Besuch, aus dem ein E-Mail-Einstieg kam: So hängt die Journey am Kunden,
-- auch wenn er den Link aus der Mail auf einem anderen Gerät öffnet.
ALTER TABLE zugaenge
  ADD COLUMN IF NOT EXISTS spur_besuch_id BIGINT UNSIGNED NULL AFTER partner_code;
