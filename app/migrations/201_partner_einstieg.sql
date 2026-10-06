-- ============================================================================
-- 201 — Einführung für Partner (06.10.2026, Uwe: Ja zu Tour, Mini-Touren,
-- Checkliste „Deine ersten 7 Tage“ und „?“-Knopf).
--
-- Eine Zeile je Partner und Merkmal: tour:haupt = fertig oder uebersprungen,
-- ev:link_kopiert = 1 (was nur der Browser sieht: Kopieren, Teilen, eigene
-- Seite, Nachricht, App). Alles andere der Checkliste kommt aus echten Daten.
-- ============================================================================

CREATE TABLE IF NOT EXISTS partner_einstieg (
  partner_id   INT UNSIGNED NOT NULL,
  art          VARCHAR(32)  NOT NULL,
  wert         VARCHAR(16)  NOT NULL,
  am           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (partner_id, art)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
