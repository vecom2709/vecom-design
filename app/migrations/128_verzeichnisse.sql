-- ===========================================================================
-- 128_verzeichnisse.sql — Telegram Growth Engine, Schritt T5: Verzeichnisse
-- und Kooperationen (01.10.2026, Uwe: „ja“).
--
-- Eine Liste der Stellen, an denen Vecom Design eingetragen werden kann:
-- Karten (Google, Apple, Bing), Branchenverzeichnisse, Telegram-Kataloge,
-- Agentur-Verzeichnisse und Kanäle/Gruppen, die Kooperationen ausdrücklich
-- erlauben. Eingereicht wird von Hand (Konten, Captchas, Bedingungen) —
-- hier steht, was wo erlaubt ist, wie weit es ist und was es bringt.
--
-- Gemessen wird über eine gewöhnliche Kampagne je Eintrag (kampagne_id):
-- /k/CODE, der Fenster-Link m_CODE und der Kanal-Einladungslink zählen dort,
-- wo sie heute schon zählen. Hier entsteht keine zweite Zählung.
--
-- schluessel: Kennung der geprüften Vorschläge (Verzeichnisse::VORSCHLAEGE),
-- leer bei eigenen Einträgen. Nur hinzufügen. Rückweg: Tabelle entfernen.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS mk_verzeichnisse (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  schluessel      VARCHAR(40)  NULL,
  art             VARCHAR(12)  NOT NULL,                 -- karte | branche | telegram | agentur | kanal
  name            VARCHAR(120) NOT NULL,
  url             VARCHAR(255) NOT NULL,                 -- wo man sich einträgt (bei Kanälen: der Kanal)
  regeln_url      VARCHAR(255) NULL,
  regeln          VARCHAR(700) NOT NULL DEFAULT '',      -- was erlaubt ist, was verlangt wird
  kosten          VARCHAR(160) NOT NULL DEFAULT 'kostenlos',
  kostenlos       TINYINT(1)   NOT NULL DEFAULT 1,
  konto           VARCHAR(80)  NOT NULL DEFAULT '',
  captcha         TINYINT(1)   NULL,                     -- 1 ja, 0 nein, NULL unbekannt
  sprache         VARCHAR(3)   NOT NULL DEFAULT 'it',    -- it | de | en
  status          VARCHAR(12)  NOT NULL DEFAULT 'offen', -- offen | eingereicht | online | abgelehnt | spaeter | nein
  kampagne_id     INT UNSIGNED NULL,
  eintrag_url     VARCHAR(255) NULL,                     -- wo der Eintrag zu sehen ist
  notiz           VARCHAR(500) NOT NULL DEFAULT '',
  reihenfolge     SMALLINT     NOT NULL DEFAULT 500,
  geprueft_am     DATE         NULL,                     -- wann die Regeln zuletzt nachgesehen wurden
  status_am       DATETIME     NULL,
  eingereicht_am  DATETIME     NULL,
  angelegt_am     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mk_verzeichnis_schluessel (schluessel),
  KEY ix_mk_verzeichnis_status (status),
  KEY ix_mk_verzeichnis_kampagne (kampagne_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
