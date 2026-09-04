-- ===========================================================================
-- 034_sprache_gefragt.sql — Die Sprache ist eine Angabe, keine Vermutung.
--
-- WAS BISHER PASSIERTE
--
-- customers.sprache wurde nie gefragt. Sie kam daher, welche Sprachversion
-- der Website der Besucher gerade offen hatte — und wer nichts umstellt,
-- steht auf Italienisch. Diese eine Zeile entscheidet danach alles: jede
-- Mail, jeder Beleg, jeder Monatsname, die ganze Kundenseite.
--
-- Ein deutscher Kunde, der die Startseite nicht umgestellt hat, bekam also
-- auf Italienisch Post — und niemand konnte es sehen, weil in der Spalte
-- dasselbe stand wie bei einem echten italienischen Kunden.
--
-- WOZU DIE ZWEITE SPALTE
--
-- sprache_bestaetigt haelt fest, WANN der Kunde die Sprache selbst gewaehlt
-- hat: im Formular, im Konfigurator, oder mit dem Umschalter auf seiner
-- Seite. Bleibt sie leer, ist die Sprache geraten, und die Verwaltung sagt
-- das auch. Der Unterschied kostet eine Spalte und erspart die Frage
-- "warum schreibt der mir eigentlich auf Italienisch".
--
-- Bestandskunden bleiben bewusst auf NULL: Fuer sie wurde nie gefragt, und
-- ein Datum zu erfinden hiesse, eine Vermutung als Angabe auszugeben.
-- ===========================================================================

ALTER TABLE customers
  ADD COLUMN sprache_bestaetigt DATETIME NULL DEFAULT NULL AFTER sprache;
