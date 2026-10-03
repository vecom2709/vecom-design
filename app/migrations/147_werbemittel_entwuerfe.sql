-- ===========================================================================
-- 147_werbemittel_entwuerfe.sql — Marketing Center, Phase 2: Druckdatei und
-- Freigabe (03.10.2026, Uwe: „weiter“ nach Phase 1).
--
-- Vorgabe: „Keine Inhalte ohne Partnerfreigabe automatisch drucken.“
--
-- Der Partner wählt Stil, Sprache und Kontakt; daraus entsteht EINE
-- Druckdatei, die hier gespeichert wird — nicht nur ihre Einstellungen.
-- Gedruckt wird später genau diese Datei. Würde sie beim Bestellen neu
-- erzeugt, könnte sie anders aussehen als das, was er freigegeben hat (neue
-- Vorlage, geänderter Name, geänderte Adresse).
--
-- datei_hash (SHA-256) steht im Freigabeformular. Freigegeben wird nur, wenn
-- der Hash noch passt: Wer in zwei Fenstern zwei Entwürfe macht, kann nicht
-- versehentlich den freigeben, den er nicht gesehen hat.
--
-- status: entwurf → freigegeben → ersetzt (eine neuere Freigabe desselben
-- Produkts). Unfreigegebene Entwürfe werden beim nächsten Entwurf gelöscht;
-- freigegebene bleiben, weil spätere Bestellungen auf sie zeigen.
--
-- Nur hinzufügen. Rückweg: Tabelle entfernen.
-- ===========================================================================

CREATE TABLE wm_entwuerfe (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id      INT UNSIGNED NOT NULL,
  produkt_id      INT UNSIGNED NOT NULL,
  -- Die Wahl des Partners als JSON ({"stil":"a","sprache":"it","kontakt":"email"}).
  wahl            VARCHAR(400) NOT NULL,
  datei           MEDIUMBLOB   NOT NULL,
  datei_hash      CHAR(64)     NOT NULL,
  datei_bytes     INT UNSIGNED NOT NULL DEFAULT 0,
  status          VARCHAR(12)  NOT NULL DEFAULT 'entwurf',
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  freigegeben_am  DATETIME     NULL,
  KEY ix_wm_entwurf_partner (partner_id, produkt_id, status),
  KEY ix_wm_entwurf_frei (status, freigegeben_am),
  CONSTRAINT fk_wm_entwurf_produkt FOREIGN KEY (produkt_id)
    REFERENCES wm_produkte (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
