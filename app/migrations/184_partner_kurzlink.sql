-- Kurzlinks der Partner (Phase 4, 05.10.2026, Uwe: „Partner wählt selbst“).
-- /go/name und /go/name/branche führen zum selben Partner wie /p/CODE.
-- Ein Name bleibt für immer bei seinem Partner: Wer umbenennt, behält den alten
-- als „alt“, damit gedruckte QR-Codes weiter funktionieren. Gesperrt heißt, der
-- Name führt nirgends mehr hin und kann nicht neu vergeben werden.
CREATE TABLE IF NOT EXISTS partner_kurznamen (
  name          VARCHAR(30)  NOT NULL,
  partner_id    INT UNSIGNED NOT NULL,
  status        ENUM('aktiv','alt','gesperrt') NOT NULL DEFAULT 'aktiv',
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  geaendert_am  DATETIME     NULL,
  PRIMARY KEY (name),
  KEY idx_kurz_partner (partner_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
