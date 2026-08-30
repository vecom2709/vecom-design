-- Pakete bekommen Texte je Sprache, damit sie auf der dreisprachigen Website
-- richtig erscheinen. Fehlt eine Sprache, greift der Haupttext des Pakets.
--
-- Aufbau von `texte`:
--   {"it": {"name": "...", "sub": "...", "ideal": "...", "features": ["...", "..."]},
--    "de": {...}, "en": {...}}

ALTER TABLE packages
  ADD COLUMN texte      JSON         NULL AFTER features,
  ADD COLUMN sub        VARCHAR(190) NULL AFTER description,
  ADD COLUMN ideal      VARCHAR(500) NULL AFTER extras,
  ADD COLUMN detail_url VARCHAR(190) NULL AFTER image,
  ADD COLUMN oeffentlich TINYINT(1)  NOT NULL DEFAULT 1 AFTER active;
