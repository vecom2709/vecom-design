-- ============================================================================
-- 090 — Folge-Mails mit Anrede (27.09.2026, Uwe: „Anrede mit Namen einbauen,
-- dann freigeben“).
--
-- Die Ausgangstexte beginnen jetzt mit {anrede}: „Guten Tag Maria Rossi,“ /
-- „Buongiorno …,“ / „Hello …,“ -- ohne bekannten Namen der bloße Gruß. Schon
-- angelegte Entwürfe, die noch mit dem alten Gruß beginnen, bekommen dieselbe
-- erste Zeile (neue Fassung). Freigegebene Texte bleiben unangetastet.
-- Wiederholbar: nach dem ersten Lauf beginnt kein Entwurf mehr mit dem alten Gruß.
-- ============================================================================

UPDATE akq_folge_vorlagen SET text = CONCAT('{anrede}', SUBSTRING(text, CHAR_LENGTH('Guten Tag,') + 1)), fassung = fassung + 1
 WHERE status = 'entwurf' AND sprache = 'de' AND text LIKE 'Guten Tag,\n%';
UPDATE akq_folge_vorlagen SET text = CONCAT('{anrede}', SUBSTRING(text, CHAR_LENGTH('Buongiorno,') + 1)), fassung = fassung + 1
 WHERE status = 'entwurf' AND sprache = 'it' AND text LIKE 'Buongiorno,\n%';
UPDATE akq_folge_vorlagen SET text = CONCAT('{anrede}', SUBSTRING(text, CHAR_LENGTH('Hello,') + 1)), fassung = fassung + 1
 WHERE status = 'entwurf' AND sprache = 'en' AND text LIKE 'Hello,\n%';
