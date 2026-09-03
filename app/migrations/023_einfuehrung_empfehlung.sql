-- ===========================================================================
-- 023_einfuehrung_empfehlung.sql — Zwei Dinge, die Kunden bringen sollen.
--
-- EINFUEHRUNGSPREISE
--
-- Die heutigen Preise sind bewusst niedrig und sollen es bleiben, bis zehn
-- Websites voll bezahlt sind. Danach steigen sie. Drei Zahlen genuegen dafuer:
-- ob die Phase laeuft, wie viele Abschluesse sie dauert, und um wie viel
-- danach erhoeht wird.
--
-- "Abgeschlossen" heisst voll bezahlt — nicht angezahlt, nicht online. Der
-- Zaehler steht nirgends als Spalte: Er wird jedes Mal aus den Zahlungen
-- gerechnet. Eine gespeicherte Zahl waere irgendwann falsch, und niemand
-- wuerde es merken.
--
-- EMPFEHLUNGEN
--
-- Wer jemanden bringt, der eine Website kauft, bekommt fuenfzehn Prozent auf
-- die Betreuung. Zwei Wege fuehren dahin, und beide sind noetig: ein Link mit
-- Code, und die schlichte Frage "Wer hat uns empfohlen?". Die Kunden hier
-- empfehlen am Tresen, nicht per Link — ohne die Frage bliebe die Haelfte
-- der Empfehlungen unsichtbar.
--
-- Der Rabatt haengt am KUNDEN, nicht am Betreuungsvertrag. Wer noch keinen
-- hat, verliert seinen Anspruch sonst — dabei ist er gerade der, den man
-- halten will. Er liegt als rabatt_bis am Kunden und greift, sobald ein
-- Vertrag beginnt.
--
-- Mehrere Empfehlungen verlaengern die Laufzeit, statt den Satz zu stapeln.
-- Sonst zahlt jemand mit sieben Empfehlungen irgendwann nichts mehr.
-- ===========================================================================

INSERT INTO settings (skey, svalue) VALUES ('einfuehrung_aktiv', '1')
  ON DUPLICATE KEY UPDATE skey = skey;
INSERT INTO settings (skey, svalue) VALUES ('einfuehrung_ziel', '10')
  ON DUPLICATE KEY UPDATE skey = skey;
INSERT INTO settings (skey, svalue) VALUES ('einfuehrung_erhoehung', '20')
  ON DUPLICATE KEY UPDATE skey = skey;
INSERT INTO settings (skey, svalue) VALUES ('einfuehrung_erledigt', '0')
  ON DUPLICATE KEY UPDATE skey = skey;
INSERT INTO settings (skey, svalue) VALUES ('empfehlung_prozent', '15')
  ON DUPLICATE KEY UPDATE skey = skey;
INSERT INTO settings (skey, svalue) VALUES ('empfehlung_monate', '12')
  ON DUPLICATE KEY UPDATE skey = skey;

ALTER TABLE customers
  ADD COLUMN empfehl_code   VARCHAR(16)      NULL,
  ADD COLUMN rabatt_prozent TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN rabatt_bis     DATE             NULL,
  ADD UNIQUE KEY uq_kunde_empfehlcode (empfehl_code);

ALTER TABLE bedarf
  ADD COLUMN empfehl_code VARCHAR(16)  NOT NULL DEFAULT '',
  ADD COLUMN empfehl_wer  VARCHAR(160) NOT NULL DEFAULT '';

CREATE TABLE empfehlungen (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  empfehler_id   INT UNSIGNED NULL,
  geworbener_id  INT UNSIGNED NULL,
  bedarf_id      INT UNSIGNED NULL,
  anfrage_id     INT UNSIGNED NULL,
  order_id       INT UNSIGNED NULL,
  code           VARCHAR(16)  NOT NULL DEFAULT '',
  -- link | genannt: ueber den Empfehlungslink gekommen oder von Hand genannt
  quelle         VARCHAR(10)  NOT NULL DEFAULT 'link',
  -- Was der Kunde getippt hat, wenn er einen Namen genannt statt geklickt hat.
  -- Bleibt stehen, auch wenn spaeter jemand zugeordnet wird: Wer es war, sagt
  -- der Name; wem es gutgeschrieben wurde, sagt empfehler_id.
  genannt_als    VARCHAR(160) NOT NULL DEFAULT '',
  -- offen | verdient | verfallen
  status         VARCHAR(12)  NOT NULL DEFAULT 'offen',
  verdient_am    DATETIME     NULL,
  verfallen_am   DATETIME     NULL,
  grund          VARCHAR(200) NOT NULL DEFAULT '',
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  demo           TINYINT(1)   NOT NULL DEFAULT 0,
  KEY ix_empfehlung_empfehler (empfehler_id, status),
  KEY ix_empfehlung_geworbener (geworbener_id),
  KEY ix_empfehlung_bestellung (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
