-- ===========================================================================
-- 149_werbemittel_anbieter.sql — Marketing Center, Phase 4: Druckanbieter
-- (03.10.2026, Uwe: „mache alles automatisch soweit wie es geht“).
--
-- Erster Anbieter: Gelato (öffentliche API, Schlüssel selbst erzeugt,
-- Entwurfsbestellungen). Endpunkte nur aus der offiziellen Doku
-- (dashboard.gelato.com/docs, gelesen am 03.10.2026).
--
-- wm_entwuerfe.datei_druck  Die Druckdatei für den Anbieter (Gelato: 4 mm
--                           Beschnitt, 300 dpi), erzeugt im SELBEN Moment wie
--                           die freigegebene Datei aus denselben Daten — damit
--                           auf der Karte steht, was der Partner gesehen hat.
-- wm_anbieter_produkte      Welche Anbieter-Artikelnummer (Gelato: productUid)
--                           und welche Menge zu einer Variante gehört. Trägt
--                           Uwe ein; ohne Zuordnung geht nichts zum Anbieter.
-- wm_bestellungen.anbieter_* Stand beim Anbieter und letzter Fehler. Ein
--                           Fehler wird festgehalten und NICHT wiederholt —
--                           „Wenn API-Bestellung fehlschlägt: NICHT automatisch
--                           mehrfach bestellen.“
--
-- Nur hinzufügen. Rückweg: Tabelle und Spalten entfernen.
-- ===========================================================================

ALTER TABLE wm_entwuerfe
  ADD COLUMN datei_druck MEDIUMBLOB NULL AFTER datei_bytes,
  ADD COLUMN datei_druck_hash CHAR(64) NULL AFTER datei_druck;

CREATE TABLE wm_anbieter_produkte (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  variante_id  INT UNSIGNED NOT NULL,
  anbieter     VARCHAR(20)  NOT NULL,
  artikel      VARCHAR(200) NOT NULL,
  menge        INT UNSIGNED NOT NULL DEFAULT 1,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wm_anbieter_variante (variante_id, anbieter),
  CONSTRAINT fk_wm_anbieter_variante FOREIGN KEY (variante_id)
    REFERENCES wm_varianten (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE wm_bestellungen
  ADD COLUMN anbieter_status VARCHAR(30) NULL AFTER anbieter_ref,
  ADD COLUMN anbieter_fehler VARCHAR(500) NULL AFTER anbieter_status,
  ADD COLUMN anbieter_am DATETIME NULL AFTER anbieter_fehler;
