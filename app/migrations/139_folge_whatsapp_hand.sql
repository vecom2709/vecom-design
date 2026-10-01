-- ===========================================================================
-- 139_folge_whatsapp_hand.sql — Folge per WhatsApp von Hand
-- (02.10.2026, Uwe: „halbautomatisch, kostenlos“ — ohne zweite Nummer bleibt
-- die 380er in der WhatsApp-Business-App auf dem Handy).
--
-- wa_hand_schritt: dieser Folge-Schritt wartet darauf, dass Uwe ihn mit einem
--   Tipp aus seiner WhatsApp-App schickt. NULL = nichts wartet.
-- wa_hand_seit:    seit wann er wartet (nach zwei Tagen geht die Mail, wenn
--   eine Adresse da ist).
-- Nur hinzufügen. Rückweg: Spalten entfernen.
-- ===========================================================================

ALTER TABLE akq_folgen ADD COLUMN wa_hand_schritt TINYINT UNSIGNED NULL;
ALTER TABLE akq_folgen ADD COLUMN wa_hand_seit DATETIME NULL;
