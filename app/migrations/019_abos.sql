-- ===========================================================================
-- 019_abos.sql — Die Betreuung ist ein eigener Vertrag.
--
-- Bisher gab es nur Bestellungen: einmalig, mit Anzahlung und Restzahlung. Ein
-- Vertrag, der jeden Monat weiterlaeuft, passt da nicht hinein — er hat keinen
-- Endpreis, sondern einen Monatspreis, eine Mindestlaufzeit und ein Datum, an
-- dem er endet.
--
-- Website und Betreuung sind zwei Vertraege. Deshalb eine eigene Tabelle und
-- nicht eine weitere Spalte an orders: Wer beides hat, hat zwei Zeilen, zwei
-- Laufzeiten und zwei Kuendigungen.
--
-- laeuft_bis ist der Kern. Es wird beim Kuendigen ausgerechnet, nicht vom
-- Kunden gewaehlt: fruehestens zum Ende der Mindestlaufzeit, sonst zum Ende des
-- laufenden Monats. Danach zahlt niemand mehr — und das System weiss, ab wann.
-- ===========================================================================

CREATE TABLE abos (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id         INT UNSIGNED NOT NULL,
  project_id          INT UNSIGNED NULL,
  package_id          INT UNSIGNED NULL,
  paket_slug          VARCHAR(80)  NULL,
  paket_name          VARCHAR(120) NOT NULL,
  betrag_cents        INT UNSIGNED NOT NULL DEFAULT 0,
  currency            CHAR(3)      NOT NULL DEFAULT 'EUR',
  -- karte | sepa | manuell (manuell nur, solange Stripe nicht bereit ist)
  zahlart             VARCHAR(20)  NOT NULL DEFAULT 'karte',
  -- angelegt | aktiv | gekuendigt | beendet
  status              VARCHAR(20)  NOT NULL DEFAULT 'angelegt',
  beginn              DATE         NOT NULL,
  mindestlaufzeit_bis DATE         NOT NULL,
  naechste_abrechnung DATE         NULL,
  gekuendigt_am       DATETIME     NULL,
  gekuendigt_von      VARCHAR(16)  NULL,
  laeuft_bis          DATE         NULL,
  -- Die Kennung beim Zahlungsanbieter. Bleibt leer, solange von Hand abgerechnet wird.
  extern_id           VARCHAR(190) NULL,
  notiz               TEXT         NULL,
  demo                TINYINT(1)   NOT NULL DEFAULT 0,
  created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_abos_extern (extern_id),
  KEY ix_abos_kunde (customer_id),
  KEY ix_abos_status (status),
  KEY ix_abos_ende (laeuft_bis),
  CONSTRAINT fk_abos_kunde   FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
  CONSTRAINT fk_abos_projekt FOREIGN KEY (project_id)  REFERENCES projects(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ein Beleg gehoert entweder zur Website-Bestellung oder zum Betreuungsvertrag.
-- Die Nummernreihe bleibt eine einzige — im Jahr lueckenlos ist so am sichersten.
ALTER TABLE invoices
  ADD COLUMN abo_id INT UNSIGNED NULL AFTER order_id,
  ADD KEY ix_invoices_abo (abo_id);
