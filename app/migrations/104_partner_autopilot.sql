-- ============================================================================
-- 104 — Partner-Autopilot (29.09.2026, Uwe: Ja).
--
-- partner.heimatort: der Ort, in dem der Partner unterwegs ist (selbst
--   eingetragen oder aus seiner ersten Suche im Firmen-Finder).
-- partner_tagesliste: jeden Morgen fünf passende Betriebe je Partner, die
--   er persönlich besuchen kann (Flyer mit QR-Code, Leitfaden, Route). Ein
--   Betrieb kommt höchstens alle 30 Tage wieder. Angeschrieben wird niemand.
-- ============================================================================

ALTER TABLE partner ADD COLUMN IF NOT EXISTS heimatort VARCHAR(80) NULL;

CREATE TABLE IF NOT EXISTS partner_tagesliste (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id  INT UNSIGNED NOT NULL,
  firma_id    INT UNSIGNED NOT NULL,
  datum       DATE         NOT NULL,
  gemeldet    TINYINT(1)   NOT NULL DEFAULT 0,
  UNIQUE KEY uq_tagesliste (partner_id, firma_id, datum),
  KEY ix_tagesliste_tag (partner_id, datum)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
