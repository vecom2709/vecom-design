-- ===========================================================================
-- 148_werbemittel_bestellungen.sql — Marketing Center, Phase 3: Partner
-- bestellen Werbemittel (03.10.2026, Uwe: „B, aber Partner kann trotzdem
-- bestellen“).
--
-- Eigene Tabellen statt orders/payments: Dort hängt die ganze Kette der
-- Website-Aufträge, und payments.order_id ist NOT NULL. Ein Umbau dort hätte
-- die Kette berührt, ohne dass das Marketing Center etwas davon hätte.
--
-- wm_adressen      Lieferadressen eines Partners (Telefon für den Kurier).
-- wm_bestellungen  Kopf: Nummer, Status, Summe, Lieferadresse EINGEFROREN
--                  (JSON) — ändert der Partner später seine Adresse, bleibt
--                  die der Bestellung, wie sie war.
-- wm_positionen    Was bestellt wurde, mit Preis UND Einkauf zum Zeitpunkt
--                  der Bestellung, und welcher freigegebene Entwurf gedruckt
--                  wird. Der Einkauf ist nur für die Verwaltung (Marge im
--                  Rückblick), nie für den Partner.
--
-- STATUS UND WER IHN SETZEN DARF
--   angefragt     Stripe nicht eingerichtet: Uwe meldet sich mit Zahlungsweg.
--   offen         Bezahlseite erstellt, Zahlung steht aus.
--   bezahlt       NUR Webhook/Abgleich (Stripe sagt paid, Betrag passt) oder
--                 Uwe von Hand in der Verwaltung. Nie aus dem Browser.
--   beim_drucker  Uwe hat den Druck beauftragt (Anbieter + Auftragsnummer).
--   versendet     mit Sendungsnummer.
--   storniert     nur vor „beim_drucker“.
--
-- steuer_cent bleibt 0, solange keine Partita IVA eingetragen ist; die
-- Spalte ist da, damit eine spätere Steuer keine Migration der Bestellungen
-- braucht.
--
-- Nur hinzufügen. Rückweg: die drei Tabellen entfernen.
-- ===========================================================================

CREATE TABLE wm_adressen (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id   INT UNSIGNED NOT NULL,
  name         VARCHAR(120) NOT NULL,
  firma        VARCHAR(160) NOT NULL DEFAULT '',
  strasse      VARCHAR(160) NOT NULL,
  plz          VARCHAR(12)  NOT NULL,
  ort          VARCHAR(120) NOT NULL,
  land         CHAR(2)      NOT NULL DEFAULT 'IT',
  telefon      VARCHAR(40)  NOT NULL DEFAULT '',
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY ix_wm_adresse_partner (partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wm_bestellungen (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nummer           VARCHAR(24)  NOT NULL,
  partner_id       INT UNSIGNED NOT NULL,
  status           VARCHAR(16)  NOT NULL DEFAULT 'angefragt',
  summe_cent       INT UNSIGNED NOT NULL,
  steuer_cent      INT UNSIGNED NOT NULL DEFAULT 0,
  waehrung         CHAR(3)      NOT NULL DEFAULT 'EUR',
  adresse          VARCHAR(1000) NOT NULL,
  sprache          CHAR(2)      NOT NULL DEFAULT 'it',
  stripe_sitzung   VARCHAR(255) NULL,
  stripe_referenz  VARCHAR(255) NULL,
  bezahlt_am       DATETIME     NULL,
  bezahlt_wie      VARCHAR(20)  NULL,
  anbieter         VARCHAR(60)  NULL,
  anbieter_ref     VARCHAR(120) NULL,
  beim_drucker_am  DATETIME     NULL,
  tracking         VARCHAR(120) NULL,
  tracking_url     VARCHAR(400) NULL,
  versendet_am     DATETIME     NULL,
  storniert_am     DATETIME     NULL,
  notiz            VARCHAR(1000) NOT NULL DEFAULT '',
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wm_bestellung_nummer (nummer),
  KEY ix_wm_bestellung_partner (partner_id, created_at),
  KEY ix_wm_bestellung_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wm_positionen (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bestellung_id  INT UNSIGNED NOT NULL,
  produkt_id     INT UNSIGNED NOT NULL,
  variante_id    INT UNSIGNED NOT NULL,
  entwurf_id     INT UNSIGNED NOT NULL,
  produkt_nummer VARCHAR(20)  NOT NULL,
  name           VARCHAR(160) NOT NULL,
  variante       VARCHAR(120) NOT NULL,
  auflage        INT UNSIGNED NOT NULL,
  menge          INT UNSIGNED NOT NULL DEFAULT 1,
  preis_cent     INT UNSIGNED NOT NULL,
  einkauf_cent   INT UNSIGNED NOT NULL,
  KEY ix_wm_position_bestellung (bestellung_id),
  CONSTRAINT fk_wm_position_bestellung FOREIGN KEY (bestellung_id)
    REFERENCES wm_bestellungen (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
