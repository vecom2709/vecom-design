-- ===========================================================================
-- 039_telefon_merkliste.sql — Kurz halten, nicht abweisen.
--
-- Es gibt Anrufer, die regelmäßig anrufen und nie kaufen. Jedes Gespräch
-- kostet eine Beratung, einen Seitenblick, eine Preisspanne und am Ende
-- einen Link, den niemand öffnet. Das ist kein Grund, unhöflich zu werden --
-- es ist ein Grund, kurz zu bleiben.
--
-- WAS DIESE LISTE TUT UND WAS AUSDRÜCKLICH NICHT
--
-- Sie tut: Bei einer eingetragenen Rufnummer gibt Manuela keine Beratung,
-- keinen Seitenblick, keine Preise, keinen Link und kein Angebot. Sie bleibt
-- höflich, sagt in zwei Sätzen, dass Anfragen schriftlich laufen, und
-- beendet das Gespräch. Der Anruf steht trotzdem in der Verwaltung --
-- niemand wird heimlich weggeblendet.
--
-- Sie tut NICHT: beleidigen, verhöhnen, etwas über den Anrufer behaupten
-- oder auflegen. Das wäre in Italien diffamazione, es steht aufgezeichnet
-- bei der Telefonplattform, und in einer Provinz, in der man sich kennt,
-- kostet es mehr als jeder verlorene Auftrag. Dieser Kommentar steht hier,
-- damit die Grenze nicht in einem halben Jahr aus Versehen verschoben wird.
--
-- WARUM DIE LETZTEN NEUN ZIFFERN
--
-- Dieselbe Nummer kommt mal mit +39, mal mit 0039, mal ohne Vorwahl an. Die
-- letzten neun Ziffern sind das, was zuverlässig gleich bleibt -- genau wie
-- beim Nachschlagen. Die volle Schreibweise steht daneben, damit auf der
-- Seite steht, was jemand wirklich eingetragen hat.
-- ===========================================================================

CREATE TABLE telefon_merkliste (
  nummer_ende  CHAR(9)      NOT NULL PRIMARY KEY,   -- die letzten neun Ziffern
  nummer       VARCHAR(40)  NOT NULL,               -- wie eingetragen
  notiz        VARCHAR(255) NOT NULL DEFAULT '',    -- für Uwe, nie für den Anrufer
  angelegt_am  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  getroffen    INT UNSIGNED NOT NULL DEFAULT 0,     -- wie oft sie seither angerufen hat
  zuletzt_am   DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
