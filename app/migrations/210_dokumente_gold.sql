-- ===========================================================================
-- 210_dokumente_gold.sql — Dokumentstil „Vecom Gold“, echte Rechnungen,
-- E-Mails in der Kundenakte (07.10.2026, Uwe: „ok super“ zu den Vorschlägen
-- 1–13).
--
-- angebot_positionen.optional : Kasten „Optional – auf Wunsch zubuchbar“,
--                               zählt nicht zur Summe.
-- invoices.doc_typ           : beleg | rechnung | gutschrift
-- invoices.steuerfall        : beleg | forfettario | ordinario | reverse_charge
-- invoices.natura/bollo/...  : was auf Rechnung und FatturaPA-XML steht
-- invoices.storno_von        : Gutschrift → ursprüngliches Dokument
-- mails.inhalt/html/anhaenge : der genaue Inhalt jeder E-Mail (ab jetzt),
--                              damit die Kundenakte ihn zeigen kann.
--
-- Nur hinzufügen. Bestehende Belege bleiben, wie sie sind.
-- ===========================================================================

ALTER TABLE angebot_positionen
  ADD COLUMN IF NOT EXISTS optional TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE invoices
  ADD COLUMN IF NOT EXISTS doc_typ     VARCHAR(12)  NOT NULL DEFAULT 'beleg',
  ADD COLUMN IF NOT EXISTS steuerfall  VARCHAR(16)  NOT NULL DEFAULT 'beleg',
  ADD COLUMN IF NOT EXISTS natura      VARCHAR(6)   NULL,
  ADD COLUMN IF NOT EXISTS bollo_cents INT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS storno_von  INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS grund       VARCHAR(500) NULL;

-- Alte Dokumente richtig einordnen: RE-… waren Rechnungen, alles andere Belege.
UPDATE invoices SET doc_typ = 'rechnung', steuerfall = IF(tax_rate > 0, 'ordinario', 'forfettario')
 WHERE invoice_no LIKE 'RE-%' AND doc_typ = 'beleg';

ALTER TABLE mails
  ADD COLUMN IF NOT EXISTS inhalt    MEDIUMTEXT NULL,
  ADD COLUMN IF NOT EXISTS html      MEDIUMTEXT NULL,
  ADD COLUMN IF NOT EXISTS anhaenge  TEXT       NULL;
