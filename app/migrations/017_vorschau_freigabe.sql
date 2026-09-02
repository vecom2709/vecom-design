-- ===========================================================================
-- 017_vorschau_freigabe.sql — Eintragen und Freischalten sind zweierlei.
--
-- Bisher wurde der Vorschau-Link in dem Moment sichtbar, in dem er gespeichert
-- wurde. Damit liess sich die Adresse nicht vorbereiten, und ein halb fertiger
-- Entwurf war fuer den Kunden schon offen.
--
-- Schlimmer war der umgekehrte Fall: Die Stufe "Dein Entwurf ist fertig" hing
-- am Projektstatus, der Knopf am Vorschau-Link — zwei Dinge, die nichts
-- voneinander wussten. Status auf "Vorschau" ohne eingetragene Adresse hiess:
-- Der Kunde bekam die E-Mail "Deine Vorschau steht", klickte, und fand nichts.
--
-- vorschau_frei_am ist der Zeitpunkt der Freigabe. Solange die Spalte leer ist,
-- sieht der Kunde den Bereich, aber grau: Er weiss, wo es erscheinen wird.
-- ===========================================================================

ALTER TABLE projects
  ADD COLUMN vorschau_frei_am DATETIME NULL AFTER preview_url;

-- Wer seine Vorschau nach der alten Regel schon sehen konnte, soll sie nicht
-- durch diese Aenderung verlieren. Freigegeben gilt deshalb rueckwirkend
-- alles, was eine Adresse hat und im Ablauf schon bei der Vorschau oder
-- dahinter steht. Frueher stehende Projekte bleiben gesperrt — dort war die
-- Adresse ohnehin nur ein Zwischenstand.
UPDATE projects
   SET vorschau_frei_am = COALESCE(updated_at, created_at)
 WHERE preview_url IS NOT NULL
   AND preview_url <> ''
   AND status IN ('vorschau','kundenfeedback','aenderungen','finale_freigabe','online','abgeschlossen');
