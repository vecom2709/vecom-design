-- ===========================================================================
-- 030_umfang_geklaert.sql — Ein Haken dafuer, dass der Mehrbedarf besprochen
-- ist.
--
-- WAS DAZUGEKOMMEN IST
--
-- Der Fragebogen fragt jetzt nicht mehr im Freitext, welche Seiten und
-- Funktionen es sein sollen, sondern zeigt dieselbe Liste, aus der auch der
-- Preis entstanden ist -- angehakt, was beauftragt ist. Hakt der Kunde etwas
-- dazu oder weg, ist das ein exaktes Signal statt eines Satzes, den jemand
-- auslegen muss.
--
-- WARUM DAS EINE SPALTE BRAUCHT
--
-- Ein Unterschied zwischen Beauftragtem und Angekreuztem leuchtet in der
-- Verwaltung auf, bis er geklaert ist. Geklaert heisst aber nicht immer
-- "berechnet": Manchmal wird ein Nachtrag daraus, manchmal sagt Uwe "das
-- nehme ich mit rein", und manchmal hat sich der Kunde schlicht verklickt.
-- In allen drei Faellen soll die Zeile aufhoeren zu leuchten -- und wieder
-- anfangen, wenn der Kunde spaeter etwas Weiteres ankreuzt.
--
-- Deshalb steht hier keine Ja-Nein-Spalte, sondern der Fingerabdruck genau
-- des Unterschieds, der abgehakt wurde. Kommt ein neuer dazu, passt der
-- Fingerabdruck nicht mehr, und die Fuehrung meldet sich von selbst wieder.
-- ===========================================================================

ALTER TABLE questionnaires
  ADD COLUMN umfang_geklaert VARCHAR(32) NULL AFTER data;
