-- ===========================================================================
-- 141_partner_automatik.sql — Automatisierungen, die jeder Partner selbst
-- an- und ausschaltet (03.10.2026, Uwe: Ja zu acht Vorschlägen).
--
-- einstellungen: JSON mit den Schaltern (PartnerAutomatik::SCHALTER), der
--   Autopilot-Anzahl und -Stunde und „ruhe_bis“ (Urlaubsmodus).
-- ics_token: eigener Schlüssel für das Kalender-Abo — nie der Login-Schlüssel
--   des Partners, damit ein Kalender auf dem Handy nicht den Zugang verrät.
-- bericht_am: wann der Wochenbericht zuletzt ging (höchstens einmal je Woche).
-- Nur hinzufügen. Rückweg: Tabelle entfernen.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS partner_automatik (
  partner_id     INT UNSIGNED  NOT NULL PRIMARY KEY,
  einstellungen  TEXT          NULL,
  ics_token      CHAR(32)      NULL,
  bericht_am     DATETIME      NULL,
  updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pa_ics (ics_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
