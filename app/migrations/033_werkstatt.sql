-- ===========================================================================
-- 033_werkstatt.sql — Die Werkstatt: was gebaut wird, steht in der Akte.
--
-- Die Verwaltung weiss ueber einen Kunden schon alles, was ein guter Auftrag
-- an den Baumeister braucht: 35 Fragebogenfelder, den bezahlten Umfang aus
-- dem Angebot, Sprachen, Domain, Deadline. Nur nahm dieses Wissen bisher den
-- Umweg ueber Uwes Kopf und seine Finger in ein Chatfenster, und wurde dabei
-- jedes Mal kuerzer — und jedes Mal anders kurz.
--
-- Drei Dinge kommen deshalb an das Projekt:
--
--   briefing     Der zusammengestellte Auftrag, wie er verschickt wurde.
--                Gespeichert, nicht nur erzeugt: In Monat 14, wenn ein
--                Betreuungskunde eine Aenderung will, steht hier noch,
--                woraus die Seite gebaut ist. Ohne das faengt jede spaetere
--                Aenderung wieder bei null an.
--
--   chat_url     Wo das Gespraech dazu liegt. Eine Zeile, die den Weg
--                zurueck spart: "Wo war nochmal das Gespraech zu Boulevard."
--
--   abnahme      Was die letzte Pruefung vor dem Livegang ergeben hat,
--                als JSON. Damit das Ergebnis auch morgen noch dasteht und
--                nicht nur im Moment des Knopfdrucks.
--
-- Und eine Tabelle fuer die Bausteine: Was zum dritten Mal gebaut wird,
-- gehoert benannt und wiederverwendet. Sie heisst muster, weil bausteine
-- schon vergeben ist — das ist der Preisbaukasten, etwas ganz anderes.
-- ===========================================================================

ALTER TABLE projects
  ADD COLUMN briefing LONGTEXT NULL AFTER preview_url;

ALTER TABLE projects
  ADD COLUMN briefing_am DATETIME NULL AFTER briefing;

ALTER TABLE projects
  ADD COLUMN chat_url VARCHAR(255) NULL AFTER briefing_am;

ALTER TABLE projects
  ADD COLUMN abnahme LONGTEXT NULL AFTER chat_url;

ALTER TABLE projects
  ADD COLUMN abnahme_am DATETIME NULL AFTER abnahme;

CREATE TABLE muster (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(60)  NOT NULL,
  name        VARCHAR(160) NOT NULL,
  -- Wofuer der Baustein da ist, in einem Satz.
  zweck       VARCHAR(400) NULL,
  -- Wo er schon laeuft. Das ist der Beleg dafuer, dass er funktioniert —
  -- ein Baustein ohne diese Zeile ist eine Absicht, kein Baustein.
  laeuft_bei  VARCHAR(400) NULL,
  -- Fragebogen-Funktionen oder Branchenwoerter, zu denen er passt (CSV).
  -- Danach schlaegt das Briefing vor, was in Frage kommt.
  passt_zu    VARCHAR(200) NULL,
  -- Der Baustein selbst: Beschreibung, Markup, was auch immer traegt.
  inhalt      LONGTEXT     NULL,
  aktiv       TINYINT(1)   NOT NULL DEFAULT 1,
  sortierung  INT          NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_muster_slug (slug),
  KEY ix_muster_aktiv (aktiv, sortierung)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
