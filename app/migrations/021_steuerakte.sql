-- Alles, was ausser den eigenen Belegen noch in die Steuerakte gehoert.
--
-- WARUM AUSGABEN, WO SIE IM FORFETTARIO GAR NICHT ABZIEHBAR SIND
--
-- Weil die Aufbewahrungspflicht davon unberuehrt bleibt. Art. 1 comma 59
-- L. 190/2014 nimmt den Forfettario von fast allem aus — ausdruecklich NICHT
-- von der "numerazione e conservazione delle fatture di acquisto". Die
-- Eingangsrechnungen muessen also nummeriert und aufbewahrt werden, auch wenn
-- sie steuerlich nichts bringen.
--
-- Und dann ist da der Punkt, an dem eine selbstgebaute Verwaltung wirklich
-- hilft: auslaendische Dienstleister. Stripe (Irland), Google, Meta, Hosting.
-- Fuer die gilt im Forfettario das Reverse-Charge-Verfahren — der Betrag wird
-- mit 22 % italienischer IVA belegt, die tatsaechlich zu zahlen ist und die
-- niemand zurueckbekommt. Wer das nicht mitschreibt, merkt es erst, wenn der
-- Commercialista im Maerz danach fragt. Deshalb steht das Merkmal hier als
-- eigene Spalte und nicht als Notiz.

CREATE TABLE ausgaben (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  beleg_nr       VARCHAR(24)  NOT NULL,
  datum          DATE         NOT NULL,
  bezahlt_am     DATE         NULL,
  lieferant      VARCHAR(190) NOT NULL,
  land           CHAR(2)      NOT NULL DEFAULT 'IT',
  ust_id         VARCHAR(40)  NULL,
  kategorie      VARCHAR(40)  NOT NULL DEFAULT 'sonstiges',
  titel          VARCHAR(190) NULL,
  netto_cents    INT UNSIGNED NOT NULL DEFAULT 0,
  steuer_cents   INT UNSIGNED NOT NULL DEFAULT 0,
  brutto_cents   INT UNSIGNED NOT NULL DEFAULT 0,
  waehrung       CHAR(3)      NOT NULL DEFAULT 'EUR',
  reverse_charge TINYINT(1)   NOT NULL DEFAULT 0,
  zahlweg        VARCHAR(40)  NULL,
  stored_name    VARCHAR(190) NULL,
  orig_name      VARCHAR(190) NULL,
  mime           VARCHAR(100) NULL,
  size_bytes     INT UNSIGNED NOT NULL DEFAULT 0,
  notiz          TEXT         NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ausgaben_nr (beleg_nr),
  KEY ix_ausgaben_datum (datum),
  KEY ix_ausgaben_rc (reverse_charge)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Was der Zahlungsdienst einbehaelt.
--
-- Stripe zahlt netto aus: Rechnung 499,00 EUR, auf dem Konto stehen 484,52.
-- Steuerlich vereinnahmt sind trotzdem die vollen 499,00 — die Gebuehr ist
-- eine Ausgabe, kein geringerer Umsatz. Wer den Kontoeingang als Einnahme
-- bucht, weist zu wenig aus. Deshalb wird die Differenz getrennt festgehalten
-- und nicht vom Betrag abgezogen.
ALTER TABLE payments
  ADD COLUMN gebuehr_cents INT UNSIGNED NOT NULL DEFAULT 0 AFTER amount_cents;
