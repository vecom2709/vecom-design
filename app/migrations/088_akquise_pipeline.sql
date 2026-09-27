-- ============================================================================
-- 088 — Pipeline an der Firma (27.09.2026, Uwe: „mach auch die Pipeline-Anzeige
-- an der Firma“).
--
-- Fast alle Stufen ergeben sich aus dem, was schon gespeichert ist (Audit,
-- Versand, Antworten, Termine). Nur drei Dinge weiß das System nicht von
-- selbst: dass ein Angebot rausging, dass verhandelt wird, und dass es
-- gewonnen oder verloren ist. Die stehen hier. Wiederholbar: ADD COLUMN
-- scheitert beim zweiten Lauf mit 1060, das die Einrichtung überspringt.
-- ============================================================================

ALTER TABLE akq_firmen ADD COLUMN pipeline VARCHAR(12) NULL;
ALTER TABLE akq_firmen ADD COLUMN pipeline_am DATETIME NULL;
