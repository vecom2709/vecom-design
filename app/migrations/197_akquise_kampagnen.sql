-- ============================================================================
-- 197 — Akquise-CRM Modul H: Kampagnen (06.10.2026, Uwe: „Feste Gruppe aus Filter“).
--
-- Eine Kampagne ist eine benannte, feste Gruppe von Betrieben. Beim Anlegen
-- werden die Betriebe übernommen, die zum Filter passen (Branche, Ort,
-- Priorität, Stufe). Danach ändert sich die Gruppe nur von Hand, damit die
-- Zahlen vergleichbar bleiben. Gezählt wird ab der Aufnahme (hinzu_am).
-- Nichts wird gesammelt verschickt.
-- ============================================================================

CREATE TABLE IF NOT EXISTS akq_kampagnen (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120)  NOT NULL,
  filter_json   VARCHAR(1000) NULL,
  notiz         VARCHAR(255)  NULL,
  status        VARCHAR(12)   NOT NULL DEFAULT 'aktiv',
  angelegt_von  VARCHAR(80)   NOT NULL DEFAULT 'Verwaltung',
  created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  beendet_am    DATETIME      NULL,
  KEY ix_akq_kampagne_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS akq_kampagne_firmen (
  kampagne_id   INT UNSIGNED NOT NULL,
  firma_id      INT UNSIGNED NOT NULL,
  hinzu_am      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (kampagne_id, firma_id),
  KEY ix_akq_kf_firma (firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
