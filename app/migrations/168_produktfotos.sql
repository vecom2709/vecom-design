-- ===========================================================================
-- 168_produktfotos.sql — weitere Ansichten des Produktfotos je Entwurf
-- (04.10.2026, Uwe: „wo die Mockups vom Druckanbieter downloadbar bzw.
-- nutzbar sind, inklusive Druck, setze dieses mit rein“).
--
-- Printfuls Mockup-Generator liefert je Auftrag ein Hauptfoto (bleibt in
-- wm_entwuerfe.mockup) und weitere Ansichten (result.mockups[].extra[] —
-- z. B. Tasse von links/rechts). Bis zu drei davon stehen hier, nr 1–3.
-- Der Partner kann jedes Foto herunterladen und für seine Werbung nutzen.
-- Gelato und Flyeralarm haben keine solche Schnittstelle (Gelato nur im
-- Dashboard mit Gelato+, geprüft 04.10.2026).
-- ===========================================================================

CREATE TABLE IF NOT EXISTS wm_produktfotos (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  entwurf_id  INT UNSIGNED NOT NULL,
  nr          TINYINT UNSIGNED NOT NULL,
  titel       VARCHAR(80) NOT NULL DEFAULT '',
  bild        MEDIUMBLOB NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wm_produktfoto (entwurf_id, nr),
  CONSTRAINT fk_wm_produktfoto_entwurf FOREIGN KEY (entwurf_id) REFERENCES wm_entwuerfe (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
