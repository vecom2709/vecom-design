-- Plattform-Anfragen (30.09.2026, Uwe: Ja zum Kundenfinder, Eingang 3).
-- Betriebe, die auf Portalen wie ProntoPro oder Instapro selbst einen
-- Webdesigner suchen. Die Benachrichtigung des Portals kommt ins Akquise-
-- Postfach; hier liegt sie mit einer vorbereiteten Antwort, bis Uwe im Portal
-- geantwortet hat. Der Text wird nach 90 Tagen geleert (AkquisePlattform::aufraeumen).
CREATE TABLE IF NOT EXISTS akq_plattform (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plattform     VARCHAR(40)  NOT NULL,
  von           VARCHAR(190) NOT NULL DEFAULT '',
  betreff       VARCHAR(255) NOT NULL DEFAULT '',
  text          TEXT         NULL,
  sprache       CHAR(2)      NOT NULL DEFAULT 'it',
  nachricht_id  VARCHAR(190) NOT NULL,
  eingang_am    DATETIME     NULL,
  status        VARCHAR(12)  NOT NULL DEFAULT 'offen',  -- offen | erledigt
  erledigt_am   DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_akq_pf_nachricht (nachricht_id),
  KEY ix_akq_pf_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
