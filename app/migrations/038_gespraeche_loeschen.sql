-- ===========================================================================
-- 038_gespraeche_loeschen.sql — Gelöscht muss gelöscht bleiben.
--
-- ZWEI DINGE, DIE OHNE DIESE WANDERUNG NICHT GEHEN.
--
-- 1. WER GELÖSCHT WIRD, HINTERLÄSST KEIN GESPRÄCH MEHR
--
-- Bisher stand auf telefon_gespraeche.kunde_id ein ON DELETE SET NULL. Wurde
-- ein Kunde gelöscht, blieb sein Anruf stehen — mit Rufnummer, Namen und
-- Zusammenfassung, nur ohne Verweis auf die Akte, die es nicht mehr gab. Das
-- ist das Gegenteil von dem, was Löschen heißen soll: Der Name verschwand aus
-- der Kundenliste und blieb im Telefonprotokoll stehen.
--
-- Jetzt kaskadiert es. Zusätzlich steht die Tabelle in Kunde::REIHE — der
-- Fremdschlüssel ist das Netz, die Löschreihe ist der Weg.
--
-- 2. WAS HIER WEG IST, DARF NICHT WIEDERKOMMEN
--
-- Die Gespräche werden stündlich von STRATO geholt. Ein gelöschtes Gespräch
-- stünde nach spätestens einer Stunde wieder da — und „gelöscht" wäre eine
-- Lüge, die sich selbst widerlegt, während man zusieht.
--
-- Deshalb die Sperrliste: Sie merkt sich die Nummer des Gesprächs, sonst
-- nichts. Kein Name, keine Nummer, kein Betreff — nur die Kennung, damit der
-- Abgleich sie überspringen kann. Bei STRATO selbst bleibt der Anruf liegen;
-- daran kommen wir nicht heran, und das steht auch so auf der Seite.
-- ===========================================================================

ALTER TABLE telefon_gespraeche DROP FOREIGN KEY fk_tg_kunde;
ALTER TABLE telefon_gespraeche
  ADD CONSTRAINT fk_tg_kunde FOREIGN KEY (kunde_id) REFERENCES customers(id) ON DELETE CASCADE;

CREATE TABLE telefon_gespraech_weg (
  id       CHAR(36) NOT NULL PRIMARY KEY,   -- die Gesprächsnummer bei STRATO
  weg_am   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  grund    VARCHAR(40) NOT NULL DEFAULT 'von Hand'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. ANONYMISIERT MUSS ANONYMISIERT BLEIBEN
--
-- Wird ein Kunde anonymisiert, gehen Name, Rufnummer und Zusammenfassung aus
-- seinen Gesprächen heraus -- der Mensch, nicht das Geschäft. Ohne diese
-- Spalte stünde beim nächsten stündlichen Abgleich alles wieder da: Der
-- Abgleich schreibt jede Zeile neu, und STRATO weiß nichts von einer
-- Anonymisierung. Eine Stunde später wäre die Löschung rückgängig gemacht,
-- ohne dass jemand etwas getan hätte.
--
-- Ist die Spalte gesetzt, lässt der Abgleich die Zeile in Ruhe.
-- ---------------------------------------------------------------------------

ALTER TABLE telefon_gespraeche
  ADD COLUMN anonym TINYINT(1) NOT NULL DEFAULT 0 AFTER kunde_id;
