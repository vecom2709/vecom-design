-- ===========================================================================
-- 050 — Der Einstieg ist eine E-Mail-Adresse
--
-- Uwe am 24.09.2026: Statt der acht Fragen trägt der Besucher nur seine
-- Adresse ein und bekommt sein persönliches Dashboard zugeschickt; darüber
-- läuft alles bis zur Auslieferung. Entschieden: E1, E2, E4, D1–D3, S1–S4.
--
-- Eine Zeile hier ist ein ANGEFORDERTER, noch nicht bewiesener Zugang. Kunde
-- und Anfrage entstehen erst beim ersten Öffnen des Links (E4) -- das Öffnen
-- ist zugleich der Beweis, dass die Adresse dem gehört, der sie eingetippt
-- hat. Bis dahin steht in der Kundenliste niemand, der vielleicht nie
-- existiert hat, und nicht geöffnete Zeilen verfallen nach sieben Tagen.
--
-- CREATE TABLE IF NOT EXISTS: wiederholbar, wie jede neue Migration sein soll.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS zugaenge (
  id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token                  CHAR(48)     NOT NULL,
  email                  VARCHAR(190) NOT NULL,
  name                   VARCHAR(120) NULL,
  sprache                CHAR(2)      NOT NULL DEFAULT 'it',
  quelle                 VARCHAR(20)  NOT NULL DEFAULT 'seite',
  bedarf_id              INT UNSIGNED NULL,
  empfehl_code           VARCHAR(16)  NULL,
  customer_id            INT UNSIGNED NULL,
  geoeffnet_am           DATETIME     NULL,
  erinnert_am            DATETIME     NULL,
  vorhaben_erinnert1_am  DATETIME     NULL,
  vorhaben_erinnert2_am  DATETIME     NULL,
  created_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_zugaenge_token (token),
  KEY ix_zugaenge_email (email),
  KEY ix_zugaenge_kunde (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
