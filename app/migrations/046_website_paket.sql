-- ===========================================================================
-- 046_website_paket.sql — Das Material des Kunden und die fertige Seite.
--
-- WARUM
--
-- Uwe, 13.09.2026: Was der Kunde hochgeladen hat — Logo, Schriften, Bilder —
-- soll im Briefing stehen, damit der Baumeister es sich selbst holen kann.
-- Und die fertige Seite soll als Paket in der Verwaltung liegen: zum
-- Herunterladen, zum Weitergeben per E-Mail oder im Kundendashboard.
--
-- ZWEI SORTEN DATEI IN EINER TABELLE
--
-- `files` traegt bisher nur eines: Material. Das fertige Website-Paket ist
-- aber etwas anderes — es geht in die Gegenrichtung, es ist gross, und es
-- darf dem Kunden erst gezeigt werden, wenn Uwe es freigibt. Ohne
-- Unterscheidung stuende es sofort in der Dateiliste seiner Projektseite,
-- zwischen seinen eigenen Uploads.
--
-- Deshalb `rolle`: 'material' ist alles Bisherige (daher der Vorgabewert,
-- der bestehende Zeilen unangetastet laesst), 'paket' ist die fertige Seite.
--
-- WARUM DIE FREIGABE AM PROJEKT HAENGT UND NICHT AN DER DATEI
--
-- Freigegeben wird nicht eine Datei, sondern ein Zustand: "Der Kunde darf
-- seine Seite mitnehmen." Kommt ein neues Paket dazu, bleibt die Freigabe
-- bestehen und der Kunde bekommt die neue Fassung — genau das ist gemeint.
-- Haenge sie an der Datei, muesste sie bei jedem Nachliefern neu gesetzt
-- werden, und irgendwann steht beim Kunden ein Paket von vorletzter Woche.
-- ===========================================================================

ALTER TABLE files
  ADD COLUMN rolle VARCHAR(20) NOT NULL DEFAULT 'material' AFTER uploaded_by;

ALTER TABLE files
  ADD KEY ix_files_rolle (project_id, rolle);

ALTER TABLE projects
  ADD COLUMN paket_frei_am DATETIME NULL AFTER vorschau_frei_am;
