-- ============================================================================
-- 078 — Partner sehen, was aus ihren Empfehlungen wird (27.09.2026, Uwe: Ja zu
-- Erste-Schritte-Liste, Seiten-Statistik, QR mit Foto, Erfolgs-Hinweis + Post).
--
-- online_gemeldet_am: Der Partner bekam den Hinweis „Ihre Empfehlung ist
-- online“ -- genau einmal je Kunde, auch wenn das Projekt später zurück und
-- wieder auf online gesetzt wird.
--
-- zeigen_am: Der KUNDE hat zugestimmt, dass sein Empfehler Namen und neue
-- Website zeigen darf. Ohne diese Zustimmung sieht der Partner weiter keinen
-- Namen (so steht es in der Vereinbarung). Widerruf setzt zurück auf NULL.
-- Wiederholbar: doppelte Spalten scheitern mit 1060, Einrichtung überspringt.
-- ============================================================================

ALTER TABLE partner_zuordnungen ADD COLUMN online_gemeldet_am DATETIME NULL;
ALTER TABLE partner_zuordnungen ADD COLUMN zeigen_am DATETIME NULL;
