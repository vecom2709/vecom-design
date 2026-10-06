-- AI Office Stufe 2 (07.10.2026): Claude liest die Verwaltung über einen eigenen Zugang (MCP mit OAuth).
-- Uwe erlaubt einmal in der Verwaltung, die Verbindung hält 30 Tage und lässt sich jederzeit entziehen.
-- Codes und Schlüssel stehen hier nur als SHA-256, nie im Klartext: Wer die Datenbank liest, kann damit nichts anfangen.

-- Programme, die sich angemeldet haben (Dynamic Client Registration, RFC 7591)
CREATE TABLE IF NOT EXISTS claude_clients (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id       VARCHAR(64)  NOT NULL,
  geheim_hash     CHAR(64)     NULL,
  name            VARCHAR(120) NOT NULL,
  redirect_uris   TEXT         NOT NULL,
  auth_methode    VARCHAR(30)  NOT NULL DEFAULT 'none',
  ip              VARCHAR(45)  NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_claude_clients_id (client_id),
  KEY ix_claude_clients_zeit (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eine Anfrage auf Erlaubnis, bis Uwe Ja oder Nein sagt (zehn Minuten)
CREATE TABLE IF NOT EXISTS claude_anfragen (
  id              CHAR(32)     NOT NULL,
  client_id       VARCHAR(64)  NOT NULL,
  redirect_uri    VARCHAR(500) NOT NULL,
  state           VARCHAR(500) NULL,
  code_challenge  VARCHAR(128) NOT NULL,
  scope           VARCHAR(200) NOT NULL,
  resource        VARCHAR(300) NOT NULL,
  bis             DATETIME     NOT NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_claude_anfragen_bis (bis)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eine erteilte Erlaubnis: Uwe hat Ja gesagt, sie gilt bis „bis“ oder bis zum Entziehen
CREATE TABLE IF NOT EXISTS claude_verbindungen (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id       VARCHAR(64)  NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  scope           VARCHAR(200) NOT NULL,
  erlaubt_am      DATETIME     NOT NULL,
  bis             DATETIME     NOT NULL,
  entzogen_am     DATETIME     NULL,
  entzogen_grund  VARCHAR(200) NULL,
  zuletzt_am      DATETIME     NULL,
  aufrufe         INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_claude_verbindungen_offen (entzogen_am, bis)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Einmal-Codes (fünf Minuten) und Schlüssel (Zugang eine Stunde, Erneuern bis zum Ende der Verbindung)
CREATE TABLE IF NOT EXISTS claude_schluessel (
  hash            CHAR(64)     NOT NULL,
  art             VARCHAR(10)  NOT NULL,
  verbindung_id   INT UNSIGNED NULL,
  client_id       VARCHAR(64)  NOT NULL,
  redirect_uri    VARCHAR(500) NULL,
  code_challenge  VARCHAR(128) NULL,
  bis             DATETIME     NOT NULL,
  benutzt_am      DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (hash),
  KEY ix_claude_schluessel_verbindung (verbindung_id),
  KEY ix_claude_schluessel_bis (bis)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jeder Griff von Claude, mit Werkzeug und Kürzel der Frage
CREATE TABLE IF NOT EXISTS claude_spur (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  verbindung_id   INT UNSIGNED NULL,
  werkzeug        VARCHAR(60)  NOT NULL,
  argumente       VARCHAR(500) NULL,
  ok              TINYINT(1)   NOT NULL DEFAULT 1,
  zeichen         INT UNSIGNED NOT NULL DEFAULT 0,
  ms              INT UNSIGNED NOT NULL DEFAULT 0,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_claude_spur_zeit (created_at),
  KEY ix_claude_spur_verbindung (verbindung_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
