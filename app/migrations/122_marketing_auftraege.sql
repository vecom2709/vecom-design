-- ===========================================================================
-- 122_marketing_auftraege.sql — Recherche per Knopf (01.10.2026, Uwe:
-- „Recherche soll automatisch starten, wenn in der Verwaltung … geklickt
-- wird — im Moment muss man Claude im Chat schreiben“).
--
-- Der Knopf legt hier einen Auftrag an. Der PC fragt alle fünf Minuten nach
-- (Windows-Aufgabe „VECOM Akquise Abruf“), holt ihn ab und lässt Claude über
-- Uwes Claude-Abo recherchieren — der Server ruft weiter keine KI auf.
-- Ergebnisse kommen wie bisher als Entwürfe über die Worker-Tür.
--
-- Nur hinzufügen. Rückweg: Tabelle entfernen.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS mk_auftraege (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  branche       VARCHAR(40)  NOT NULL DEFAULT '',            -- '' = alle Branchen
  land          CHAR(2)      NOT NULL DEFAULT 'IT',
  status        VARCHAR(12)  NOT NULL DEFAULT 'wartet',      -- wartet | laeuft | fertig | fehler | abgebrochen
  ergebnis      VARCHAR(1000) NULL,
  zielgruppen   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  funde         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  gestartet_am  DATETIME     NULL,
  fertig_am     DATETIME     NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_mk_auftrag_status (status, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
