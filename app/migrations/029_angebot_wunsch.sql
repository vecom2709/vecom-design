-- ===========================================================================
-- 029_angebot_wunsch.sql — Der Kunde darf gegenrechnen.
--
-- WAS DER KUNDE JETZT KANN
--
-- Auf seinem Angebot kann er Posten abwaehlen, Mengen aendern und aus einer
-- kurzen Liste etwas dazunehmen. Die Summe rechnet sich dabei vor seinen
-- Augen neu. Was er abschickt, ist ein WUNSCH -- keine neue Zusage und kein
-- neues Angebot. Verbindlich wird erst, was danach als neue Fassung von hier
-- rausgeht.
--
-- WARUM DAS NICHT DIE POSITIONEN AENDERT
--
-- Die Zeilen des Angebots sind das, was der Kunde gelesen hat. Liesse man
-- ihn daran ruehren, wuesste hinterher niemand, worauf sich eine Zusage
-- bezog. Der Wunsch liegt deshalb daneben, als eigenes Feld, und bleibt
-- Vorschlag, bis Uwe daraus eine Fassung macht.
--
-- WOZU DIE RUNDEN
--
-- Zweimal hin und her ist Verhandlung, dreimal ist ein Telefonat, das
-- niemand fuehrt. Ab der dritten Runde steht auf der Seite ein Satz, der zum
-- Anruf raet, statt eine vierte anzubieten.
-- ===========================================================================

ALTER TABLE angebote
  ADD COLUMN wunsch        LONGTEXT NULL AFTER fassung,
  ADD COLUMN wunsch_am     DATETIME NULL AFTER wunsch,
  ADD COLUMN wunsch_runden TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER wunsch_am;
