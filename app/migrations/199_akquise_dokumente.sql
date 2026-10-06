-- ============================================================================
-- 199 — Akquise-CRM: Dokumente je Betrieb (06.10.2026, Uwe: „Am Betrieb,
-- später beim Kunden“, „Nur Verwaltung“, „Art + wichtig“, „Archivieren,
-- Löschen nur Admin“).
--
-- Die Datei selbst liegt wie jede Ablage-Datei in app/uploads unter einem
-- Zufallsnamen mit .bin, außerhalb des Web-Ordners. Hier steht nur, wem sie
-- gehört und was sie ist. Archivieren blendet aus, löscht aber nichts.
-- ============================================================================

CREATE TABLE IF NOT EXISTS akq_dokumente (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  firma_id         INT UNSIGNED NOT NULL,
  stored_name      VARCHAR(64)  NOT NULL,
  orig_name        VARCHAR(200) NOT NULL,
  mime             VARCHAR(120) NOT NULL,
  size_bytes       INT UNSIGNED NOT NULL DEFAULT 0,
  art              VARCHAR(16)  NOT NULL DEFAULT 'sonstiges',
  wichtig          TINYINT(1)   NOT NULL DEFAULT 0,
  notiz            VARCHAR(255) NULL,
  hochgeladen_von  VARCHAR(80)  NOT NULL DEFAULT 'Verwaltung',
  user_id          INT UNSIGNED NULL,
  archiviert_am    DATETIME     NULL,
  archiviert_von   VARCHAR(80)  NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_akq_dok_firma (firma_id, archiviert_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
