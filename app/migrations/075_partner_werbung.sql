-- ============================================================================
-- 075 — Partner: Werbe-Paket und persönliche Empfehlungsseite (26.09.2026,
-- Uwe: Ja zu Vorlagen je Kanal, Landingpage, Signatur, Kanal-Auswertung).
--
-- Das Foto liegt in der Datenbank, nicht als Datei: Der Deploy löscht nie,
-- und ein Foto, das ein Partner ersetzt oder entfernt, bliebe sonst als Datei
-- auf dem Webspace liegen -- unter einer Adresse, die jeder kennt, der sie
-- einmal gesehen hat. 256×256 WebP sind rund 15 KB.
-- Wiederholbar: doppelte Spalten scheitern mit 1060, Einrichtung überspringt.
-- ============================================================================

ALTER TABLE partner ADD COLUMN profil_satz VARCHAR(200) NULL;
ALTER TABLE partner ADD COLUMN foto MEDIUMBLOB NULL;
ALTER TABLE partner ADD COLUMN foto_am DATETIME NULL;
