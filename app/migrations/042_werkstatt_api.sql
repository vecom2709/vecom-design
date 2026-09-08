-- ===========================================================================
-- 042_werkstatt_api.sql — Die Werkstatt bekommt eine Tuer nach aussen.
--
-- Bisher lief der Weg zum Baumeister ueber die Zwischenablage: Briefing
-- erzeugen, kopieren, Claude oeffnen, einfuegen -- und am Ende die
-- Vorschau-Adresse von Hand zurueck in die Verwaltung tippen. Das
-- funktioniert, solange ein Mensch dazwischensitzt.
--
-- Claude Code sitzt nicht in einem Chatfenster, sondern auf einem Rechner mit
-- einem Ordner. Damit es sich den Auftrag selbst holen und das Ergebnis selbst
-- zurueckmelden kann, braucht es einen Endpunkt -- und eine Spalte, in der
-- steht, wo der Quelltext der Kundenseite liegt.
--
-- WARUM DAS REPO ANS PROJEKT GEHOERT
--
-- Die Vorschau-Adresse sagt, wo die Seite zu sehen ist. Sie sagt nicht, woraus
-- sie gebaut ist. In Monat 14, wenn ein Betreuungskunde eine Aenderung will,
-- ist genau das die Frage -- und die Antwort lag bisher nur im Kopf oder in
-- einer Notiz. Neben dem Briefing (033) ist das der zweite Teil derselben
-- Sache: Was gebaut wurde, steht in der Akte.
-- ===========================================================================

ALTER TABLE projects
  ADD COLUMN repo_url VARCHAR(255) NULL AFTER preview_url;
