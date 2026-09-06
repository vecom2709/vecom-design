-- ===========================================================================
-- 037_telefon_gespraeche.sql — Die Gespräche gehören ihm, nicht STRATO.
--
-- Bisher lag alles, was am Telefon geschah, an zwei Orten und keiner davon
-- war seiner: Was Manuela GETAN hat, steht in activities (unsere Spur, für
-- immer); was am Telefon GESPROCHEN wurde, liegt bei STRATO — hinter einer
-- Anmeldung, in einer Liste, die sich nicht durchsuchen lässt, und mit einer
-- Aufbewahrungsfrist, die wir nicht bestimmen.
--
-- Und dort liegt mehr, als die Oberfläche zeigt. Zu jedem Anruf gibt es eine
-- maschinelle Auswertung: wie er ausging, wie beteiligt der Anrufer war,
-- welche Probleme auffielen, ob der Assistent gegen seine eigenen
-- Anweisungen verstoßen hat. Genau das, was man braucht, um ihn besser zu
-- machen — und genau das, was man in einer Liste von 43 Anrufen niemals von
-- Hand zusammenzählt.
--
-- Diese Tabelle holt das herüber. Einmal die Stunde, unverändert, mit dem
-- Rohsatz daneben, damit später auch ausgewertet werden kann, was heute
-- noch niemand vermisst.
--
-- WARUM DIE ROHDATEN MITKOMMEN
-- Ein Feld, das man beim Entwurf nicht vorgesehen hat, ist rückwirkend
-- verloren — es sei denn, man hat den Satz aufgehoben. Das kostet ein paar
-- Kilobyte je Anruf und spart im Zweifel ein Jahr Datengeschichte.
-- ===========================================================================

CREATE TABLE telefon_gespraeche (
  id              CHAR(36)     NOT NULL PRIMARY KEY,   -- die Gesprächsnummer bei STRATO
  call_sid        VARCHAR(80)  NULL,
  begonnen        DATETIME     NOT NULL,
  sekunden        INT UNSIGNED NOT NULL DEFAULT 0,     -- abgerechnete Gesprächszeit
  weiter_sek      INT UNSIGNED NOT NULL DEFAULT 0,     -- abgerechnete Weiterleitung
  agent_nummer    VARCHAR(40)  NULL,
  kunde_nummer    VARCHAR(40)  NULL,                   -- "widget-call" bei einem Anruf über die Website
  name            VARCHAR(160) NULL,
  betreff         VARCHAR(255) NULL,
  zusammenfassung TEXT         NULL,

  -- Die Auswertung von STRATO. Sie ist der eigentliche Grund für diese Tabelle.
  ausgang         VARCHAR(48)  NULL,                   -- call_outcome
  engagement      VARCHAR(48)  NULL,
  tags            VARCHAR(500) NOT NULL DEFAULT '',    -- issue_tags, mit Komma getrennt
  notizen         TEXT         NULL,                   -- analysis_notes
  verstoss        TINYINT(1)   NOT NULL DEFAULT 0,     -- hat sie gegen ihre Anweisungen gehandelt?
  verstoss_text   TEXT         NULL,
  erfunden        TINYINT(1)   NOT NULL DEFAULT 0,     -- irgendeine Halluzination

  nachrichten     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  werkzeuge       SMALLINT UNSIGNED NOT NULL DEFAULT 0,

  kunde_id        INT UNSIGNED NULL,                   -- wenn die Nummer einen Kunden trifft
  roh             LONGTEXT     NULL,                   -- der ganze Satz, wie er kam
  geholt_am       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  KEY ix_tg_begonnen (begonnen),
  KEY ix_tg_ausgang  (ausgang),
  KEY ix_tg_nummer   (kunde_nummer),
  CONSTRAINT fk_tg_kunde FOREIGN KEY (kunde_id) REFERENCES customers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
