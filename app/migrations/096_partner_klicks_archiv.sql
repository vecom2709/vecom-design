-- ============================================================================
-- 096 — Klicks der Partnerseiten auf 0 setzen können (28.09.2026, Uwe:
-- „Resete alle Klicks auf 0 … dann zählen erst weitere Klicks“ — nur Besuche
-- und Kanal-Klicks; Knopf-Ereignisse und Check-Aufrufe bleiben).
--
-- Nichts wird weggeworfen: Vor dem Leeren wandern die Zeilen hierher. Die
-- Statistik zählt danach ab dem Stichtag (settings.partner_klicks_seit);
-- Meilensteine und Mini-Kurs rechnen das Archiv mit, damit niemand eine schon
-- verdiente Auszeichnung verliert.
-- ============================================================================

CREATE TABLE IF NOT EXISTS partner_klicks_archiv (
  partner_id     INT UNSIGNED NOT NULL,
  tag            DATE         NOT NULL,
  anzahl         INT UNSIGNED NOT NULL DEFAULT 0,
  archiviert_am  DATETIME     NOT NULL,
  KEY ix_pka_partner (partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS partner_kanal_klicks_archiv (
  partner_id     INT UNSIGNED NOT NULL,
  kanal          VARCHAR(20)  NOT NULL,
  tag            DATE         NOT NULL,
  anzahl         INT UNSIGNED NOT NULL DEFAULT 0,
  archiviert_am  DATETIME     NOT NULL,
  KEY ix_pkka_partner (partner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
