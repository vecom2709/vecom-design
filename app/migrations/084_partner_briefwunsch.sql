-- ============================================================================
-- 084 — Partner bittet Vecom um einen Brief an „seinen“ Betrieb (27.09.2026,
-- Uwe: Ja zu „Vecom schreibt für ihn“).
--
-- Eine Zeile je Betrieb: Solange sie offen ist, lässt AkquiseGate einen Brief
-- an diesen reservierten Betrieb zu (sonst sperrt die Reservierung Vecom aus),
-- und der Brief trägt Name, Foto und QR des Partners. Nach dem Versand
-- „verschickt“. Wiederholbar: IF NOT EXISTS.
-- ============================================================================

CREATE TABLE IF NOT EXISTS partner_briefwunsch (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  partner_id  INT UNSIGNED NOT NULL,
  firma_id    INT UNSIGNED NOT NULL,
  status      VARCHAR(12)  NOT NULL DEFAULT 'offen',   -- offen | verschickt | abgelehnt
  created_at  DATETIME     NOT NULL,
  erledigt_am DATETIME     NULL,
  UNIQUE KEY uq_briefwunsch_firma (firma_id),
  KEY k_briefwunsch_partner (partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
