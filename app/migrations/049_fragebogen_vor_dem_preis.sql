-- ===========================================================================
-- 049 — Der grosse Fragebogen kommt vor dem Preis
--
-- Bis hierher hing ein Fragebogen fest an einem Projekt (project_id NOT
-- NULL), und ein Projekt entsteht erst mit der bezahlten Anzahlung. Die
-- Reihenfolge war damit in die Tabelle gegossen: Preis, Zahlung, dann erst
-- die Fragen. Uwe am 21.09.2026: "Wir koennen nicht vorher den Preis nennen,
-- bevor der Fragebogen ausgefuellt ist."
--
-- Jetzt darf ein Fragebogen ohne Projekt dastehen. Er gehoert dann dem
-- Kunden, und wenn nach der Zahlung das Projekt entsteht, wandert genau
-- dieser Fragebogen hinein -- kein zweiter, keine zweite Einladung.
--
-- Der eindeutige Schluessel auf project_id bleibt: NULL zaehlt dort nicht
-- als Wert, beliebig viele Fragebogen ohne Projekt stoeren sich nicht.
-- MODIFY ist wiederholbar.
-- ===========================================================================

ALTER TABLE questionnaires MODIFY project_id INT UNSIGNED NULL;
