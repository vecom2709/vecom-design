-- ===========================================================================
-- 169_vorlagenfotos.sql — Produktfoto der Druckerei je Gestaltung
-- (04.10.2026, Uwe: „nicht zum Download, sondern statt nur das Gedruckte zu
-- zeigen … für die Tasse das Original-Mockup inklusive des Bedruckten, dass
-- der Partner auch weiß, was er bestellt — bei allen Produkten, wo es geht“).
--
-- Je Vorlage × Stil × Sprache ein Foto aus Printfuls Mockup-Generator mit den
-- Musterdaten (Druckerei::MUSTER). Der Partner sieht es beim Auswählen als
-- Hauptbild; nach „Druckdatei erstellen“ kommt das Foto mit SEINEN Daten
-- dazu (wm_entwuerfe.mockup). Der Cron holt fehlende Fotos nach und nach
-- (Printful: 2–10 Aufträge je Minute).
-- status: wartet | fertig | fehler
-- ===========================================================================

CREATE TABLE IF NOT EXISTS wm_vorlagenfotos (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  vorlage   VARCHAR(40) NOT NULL,
  stil      VARCHAR(10) NOT NULL,
  sprache   CHAR(2) NOT NULL,
  task      VARCHAR(80) NULL,
  status    VARCHAR(12) NOT NULL DEFAULT 'wartet',
  bild      MEDIUMBLOB NULL,
  am        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wm_vorlagenfoto (vorlage, stil, sprache)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
