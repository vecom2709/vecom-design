-- Anrufliste: Wiedervorlage bei „Nicht erreicht“ (29.09.2026, Uwe)
-- Nach „Nicht erreicht“ kommt der Betrieb 2–3 Tage später wieder auf die
-- Liste; nach dem dritten Mal fällt er automatisch heraus.
ALTER TABLE partner_reservierungen ADD COLUMN IF NOT EXISTS naechster_versuch DATE NULL;
