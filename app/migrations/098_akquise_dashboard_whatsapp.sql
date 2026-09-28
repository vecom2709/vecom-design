-- ============================================================================
-- 098 — Italienische Betriebe mit Einwilligung (28.09.2026, Uwe: Ja zu V1–V4).
--
-- V2 Dashboard vorbereitet: Der Zugang weiß, aus welchem Betrieb der Akquise
--    er kommt; der Betrieb weiß, wann sein Dashboard zum ersten Mal offen war.
-- V3 WhatsApp nach Ja: Welcher Folge-Schritt per WhatsApp raus ist, und die
--    WhatsApp-Vorlagen samt Freigabestand bei Meta.
-- ============================================================================

ALTER TABLE zugaenge ADD COLUMN akq_firma_id INT UNSIGNED NULL;
ALTER TABLE akq_firmen ADD COLUMN customer_id INT UNSIGNED NULL;
ALTER TABLE akq_firmen ADD COLUMN dashboard_am DATETIME NULL;
ALTER TABLE akq_folgen ADD COLUMN wa_schritt TINYINT UNSIGNED NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS akq_wa_vorlagen (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  schritt       TINYINT UNSIGNED NOT NULL,
  sprache       VARCHAR(2)   NOT NULL,
  name          VARCHAR(80)  NOT NULL,
  text          TEXT         NOT NULL,
  meta_status   VARCHAR(20)  NOT NULL DEFAULT 'neu',    -- neu|PENDING|APPROVED|REJECTED|PAUSED|DISABLED|fehler
  meta_id       VARCHAR(40)  NULL,
  meta_grund    VARCHAR(255) NULL,
  geaendert_am  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_akq_wa_vorlage (schritt, sprache)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
