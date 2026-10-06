-- ===========================================================================
-- 198_partner_admin_blick.sql — Admins schauen ins Partner-Dashboard (06.10.2026,
-- Uwe: „Admins können ins Partner-Dashboard schauen ohne Code“).
--
-- Ein Knopf in der Partnerakte erzeugt ein Einmal-Ticket (2 Minuten gültig,
-- nur der Hash steht hier). partner.php löst es ein und öffnet das Dashboard
-- des Partners NUR ZUM LESEN für 2 Stunden — ohne Partner-Link, ohne Code.
-- Jede Ansicht steht in der Prüfspur.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS partner_admin_blick (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id  INT UNSIGNED NOT NULL,
  user_id     INT UNSIGNED NOT NULL,
  admin_name  VARCHAR(120) NOT NULL,
  ticket_hash CHAR(64)     NOT NULL,
  gueltig_bis DATETIME     NOT NULL,
  benutzt_am  DATETIME     NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY ux_pab_ticket (ticket_hash),
  KEY ix_pab_partner (partner_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
