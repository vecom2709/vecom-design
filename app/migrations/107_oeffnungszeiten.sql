-- Öffnungszeiten laut eigener Website des Betriebs (29.09.2026, Uwe: D3)
-- Der Prüflauf liest sie aus Schema.org-Daten oder dem Text hinter „Orari“ /
-- „Öffnungszeiten“. Nie aus Google Maps. JSON: {"q":"daten|text","z":[{"t":[1..7],"v":"HH:MM","b":"HH:MM"}],"am":"YYYY-MM-DD"}
ALTER TABLE akq_firmen ADD COLUMN IF NOT EXISTS oeffnungszeiten TEXT NULL;
