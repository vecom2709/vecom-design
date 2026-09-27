-- ============================================================================
-- 080 — Partner steuern (27.09.2026, Uwe: Ja zu Rangliste, Weckruf für stille
-- Partner, Vorlagen im Admin pflegen).
--
-- partner.weckruf_am: letzter Weckruf aufs Handy (höchstens einmal im Monat).
-- partner_vorlagen_text: Uwes eigene Fassung einer Werbevorlage, der FAQ oder
-- eines Leitfaden-Abschnitts, je Sprache. Keine Zeile = der Standard aus
-- Texte.php gilt. Löschen der Zeile = zurück zum Standard.
-- Wiederholbar: doppelte Spalte scheitert mit 1060, Einrichtung überspringt.
-- ============================================================================

ALTER TABLE partner ADD COLUMN weckruf_am DATETIME NULL;

CREATE TABLE IF NOT EXISTS partner_vorlagen_text (
  schluessel VARCHAR(120) NOT NULL,
  sprache    CHAR(2)      NOT NULL,
  text       MEDIUMTEXT   NOT NULL,
  am         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (schluessel, sprache)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
