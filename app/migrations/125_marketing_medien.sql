-- ===========================================================================
-- 125_marketing_medien.sql — Bilder und Videos (Marketing-Studio Schritt 3,
-- 01.10.2026, Uwe: „Bilder und Videos über kie.ai, ansonsten Blender und
-- Unreal Engine“).
--
-- Zu jedem Inhalt kann der PC über Kie.ai ein Bild (Nano Banana Pro) oder ein
-- Kurzvideo (Veo 3.1) erzeugen — per Knopf, mit Guthaben-Prüfung vor jedem
-- Lauf. Der Kie-Schlüssel bleibt auf dem PC (Umgebungsvariable KIE_API_KEY);
-- auf den Server kommt nur die fertige Datei. Gespeichert wird wie in der
-- Ablage: app/uploads/marketing/<Zufall>.bin, ausgeliefert nur über PHP.
--
-- Nur hinzufügen. Rückweg: Tabelle mk_medien, Spalte mk_inhalte.bild_prompt
-- und den Ordner app/uploads/marketing entfernen.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS mk_medien (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inhalt_id         INT UNSIGNED NOT NULL,
  auftrag_id        INT UNSIGNED NULL,
  art               VARCHAR(10)  NOT NULL,                  -- bild | video
  datei             VARCHAR(80)  NOT NULL,                  -- Zufallsname in app/uploads/marketing
  mime              VARCHAR(40)  NOT NULL,
  bytes             INT UNSIGNED NOT NULL DEFAULT 0,
  sha256            CHAR(64)     NOT NULL,
  format            VARCHAR(8)   NOT NULL DEFAULT '',       -- Seitenverhältnis, z. B. 4:5
  modell            VARCHAR(60)  NOT NULL DEFAULT '',
  credits           DECIMAL(10,2) NULL,
  prompt            TEXT         NULL,
  quelle_url        VARCHAR(600) NULL,                      -- Kie-Adresse (läuft ab), für Bild → Video
  status            VARCHAR(12)  NOT NULL DEFAULT 'neu',    -- neu | gewaehlt | verworfen
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_mk_medium_inhalt (inhalt_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teil-Uploads (große Dateien kommen in Stücken über die Worker-Tür).
CREATE TABLE IF NOT EXISTS mk_medien_teile (
  auftrag_id   INT UNSIGNED NOT NULL,
  teil         SMALLINT UNSIGNED NOT NULL,
  bytes        INT UNSIGNED NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (auftrag_id, teil)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Claude schreibt zum Inhalt gleich einen englischen Bild-Prompt mit.
ALTER TABLE mk_inhalte
  ADD COLUMN IF NOT EXISTS bild_prompt TEXT NULL AFTER bildidee;
