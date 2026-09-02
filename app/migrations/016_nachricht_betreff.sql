-- ===========================================================================
-- 016_nachricht_betreff.sql — Jede Nachricht bekommt einen eigenen Betreff.
--
-- Bisher trug jede Nachricht aus der Verwaltung denselben Betreff ("Eine
-- Nachricht zu deinem Projekt"). Zehn Nachrichten, zehn gleiche Betreffzeilen:
-- Der Kunde findet nichts wieder, und gleichlautende Serienbetreffe sind ein
-- Merkmal, auf das Spamfilter achten.
--
-- Der Betreff gehoert an die Nachricht, nicht nur an die E-Mail: Sonst stuende
-- im Verlauf — bei Uwe wie beim Kunden — ein Text ohne die Zeile, unter der er
-- verschickt wurde.
-- ===========================================================================

ALTER TABLE messages
  ADD COLUMN betreff VARCHAR(200) NULL AFTER sender;
