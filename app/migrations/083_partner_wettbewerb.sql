-- ============================================================================
-- 083 — Monatswettbewerb der Partner (27.09.2026, Uwe: Ja zu „Stufen &
-- Monatswettbewerb“).
--
-- partner.wettbewerb_name: 1 = der Vorname darf in der Monatsrangliste der
-- anderen Partner stehen. Standard 0 -- ohne Zustimmung steht dort nur
-- „Partner“. Name anderer Leute zeigen wir nicht ungefragt.
-- Wiederholbar: doppelte Spalte scheitert mit 1060, Einrichtung überspringt.
-- ============================================================================

ALTER TABLE partner ADD COLUMN wettbewerb_name TINYINT(1) NOT NULL DEFAULT 0;
