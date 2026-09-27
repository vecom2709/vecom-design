-- ============================================================================
-- 081 — Antworten aus dem Postfach (27.09.2026, Uwe: Ja zu „Antworten
-- automatisch“). nachricht_id = Message-ID der Mail: dieselbe Antwort wird
-- nie zweimal eingetragen, auch wenn der Stand des Postfachs verloren geht.
-- Wiederholbar: doppelte Spalte/Index scheitern mit 1060/1061, Einrichtung überspringt.
-- ============================================================================

ALTER TABLE akq_antworten ADD COLUMN nachricht_id VARCHAR(190) NULL;
ALTER TABLE akq_antworten ADD KEY ix_akq_antw_nachricht (nachricht_id);
