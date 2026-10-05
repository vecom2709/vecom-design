-- ===========================================================================
-- 187_partner_tickets.sql — Support-Tickets für Partner (Phase 7b, 05.10.2026).
-- Uwe: „5 Themen“, „Offen → In Arbeit → Erledigt“ (schreibt der Partner nach
-- Erledigt noch einmal, ist das Ticket wieder offen; nichts schließt von selbst).
--
-- Die Nachrichten bleiben in partner_nachrichten — ein Ticket ist nur der
-- Rahmen darum (Thema, Betreff, Stand, Bezug). Alte Nachrichten ohne Ticket
-- bleiben, wie sie sind, und stehen als „Frühere Nachrichten“ da.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS partner_tickets (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id     INT UNSIGNED NOT NULL,
  thema          ENUM('geld','kunde','werbemittel','technik','sonstiges') NOT NULL DEFAULT 'sonstiges',
  betreff        VARCHAR(120) NOT NULL,
  stand          ENUM('offen','in_arbeit','erledigt') NOT NULL DEFAULT 'offen',
  bezug_art      ENUM('lead','bestellung') NULL,
  bezug_id       INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  geaendert_am   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  erledigt_am    DATETIME NULL,
  KEY ix_pticket_partner (partner_id, geaendert_am),
  KEY ix_pticket_stand (stand, geaendert_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE partner_nachrichten ADD COLUMN IF NOT EXISTS ticket_id INT UNSIGNED NULL;
ALTER TABLE partner_nachrichten ADD INDEX IF NOT EXISTS ix_pn_ticket (ticket_id, id);
