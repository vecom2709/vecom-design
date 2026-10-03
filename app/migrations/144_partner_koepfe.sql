-- ===========================================================================
-- 144_partner_koepfe.sql — Titelbilder der Partnerseiten aus Blender und
-- Unreal (03.10.2026, Uwe: Ja zu B1 „Kino-Kopf je Branche“, B3 „Unreal-Intro
-- Sizilien / Stadt“, B4 „Saisonale 3D-Motive“).
--
-- Je Szene (studio) und Jahreszeit ein Standbild und eine Schleife. Der PC
-- rechnet sie nachts; sie stehen erst nach Uwes Ja auf den Partnerseiten.
-- saison '' = ganzjährig (das Piazza-Intro). datei_* nur bei freigegebenen
-- Standbildern: die WebP-Fassungen, die p.php ausliefert.
-- Nur hinzufügen. Rückweg: Tabelle entfernen.
-- ===========================================================================

CREATE TABLE partner_koepfe (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  studio VARCHAR(20) NOT NULL,
  saison VARCHAR(10) NOT NULL DEFAULT '',
  art VARCHAR(5) NOT NULL,
  medium_id INT UNSIGNED NOT NULL,
  auftrag_id INT UNSIGNED NULL,
  seed INT UNSIGNED NOT NULL DEFAULT 0,
  status VARCHAR(10) NOT NULL DEFAULT 'wartet',
  datei_gross VARCHAR(80) NULL,
  datei_klein VARCHAR(80) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  freigegeben_am DATETIME NULL,
  KEY idx_kopf (studio, saison, art, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
