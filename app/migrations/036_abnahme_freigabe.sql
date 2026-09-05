-- ===========================================================================
-- 036_abnahme_freigabe.sql — Ansehen und Abnehmen sind zweierlei.
--
-- Seit 017 gilt: Eintragen und Freischalten sind zwei Schritte. Der Kunde
-- sieht den Entwurf erst, wenn Uwe ihn freischaltet. Was dabei uebersehen
-- wurde: Sobald der Entwurf freigeschaltet war, stand daneben auch der Knopf
-- "Passt so — veroeffentlichen". Und weil der Knopf immer da war, auch wenn
-- die Adresse fehlte, konnte ein Kunde eine Seite abnehmen, die er nie
-- gesehen hatte.
--
-- Das ist der teuerste Fehler der ganzen Kette. Die Abnahme haengt an der
-- Restzahlung, an der Veroeffentlichung und an dem Satz "damit ist es
-- besprochen". Sie darf nicht aus Versehen passieren.
--
-- Also zwei Schalter statt einem:
--   vorschau_frei_am  — der Kunde darf ANSEHEN. Er schaut, er schreibt,
--                       er wuenscht Aenderungen. Abnehmen kann er nicht.
--   abnahme_frei_am   — der Kunde darf ABNEHMEN. Wird von Uwe eigens
--                       freigeschaltet, wenn die Seite wirklich fertig ist,
--                       und schickt genau dann die Nachricht "sie ist fertig".
--
-- Rueckwirkend wird nichts freigegeben: Wer im Ablauf schon hinter der
-- Abnahme steht (finale Freigabe, online, abgeschlossen), hat sie erteilt --
-- das haelt der Zeitpunkt fest. Alles davor bleibt zu, auch wenn die
-- Vorschau offen ist. Lieber schaltet Uwe einmal von Hand nach, als dass ein
-- Kunde in einer laufenden Aenderungsrunde ploetzlich abnehmen kann.
-- ===========================================================================

ALTER TABLE projects
  ADD COLUMN abnahme_frei_am DATETIME NULL AFTER vorschau_frei_am;

UPDATE projects
   SET abnahme_frei_am = COALESCE(updated_at, created_at)
 WHERE status IN ('finale_freigabe','veroeffentlichung','online','abgeschlossen');
