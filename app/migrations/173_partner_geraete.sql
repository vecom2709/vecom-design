-- ===========================================================================
-- 173_partner_geraete.sql — bestätigte Geräte je Partner (05.10.2026, Uwe:
-- „Ja“ zu „Partner-Link bleibt, auf einem neuen Gerät kommt einmal ein Code
-- per E-Mail“). Siehe PartnerGeraet.php. geraet = SHA-256 des Keks-Geheimnisses.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS partner_geraete (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  partner_id    INT UNSIGNED NOT NULL,
  geraet        CHAR(64)     NOT NULL,
  bezeichnung   VARCHAR(120) NOT NULL DEFAULT '',
  code_hash     VARCHAR(255) NULL,
  code_bis      DATETIME     NULL,
  code_gesendet DATETIME     NULL,
  sendungen     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  sendungen_seit DATETIME    NULL,
  versuche      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bestaetigt_am DATETIME     NULL,
  zuletzt       DATETIME     NULL,
  erstellt      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_partner_geraet (partner_id, geraet),
  KEY ix_geraet (geraet),
  CONSTRAINT fk_partner_geraete_partner FOREIGN KEY (partner_id) REFERENCES partner(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
