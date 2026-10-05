-- Vecom Partner Academy & Verkaufstraining, Etappe 1 (05.10.2026, Uwe: „Ja, Etappe 1 bauen“).
-- Die Inhalte (Module, Einwände, Leistungen) liegen als Dateien in app/data/academy/
-- (dreisprachig, versioniert im Repository). Die Datenbank hält nur, was je Partner
-- entsteht: Fortschritt, Merkliste, eigene Notizen — und anonyme Zähler für die
-- Statistik der Verwaltung. Keine Kundendaten, keine Gesprächsinhalte.
-- Nur hinzufügen. Rückweg: die vier Tabellen löschen.

CREATE TABLE IF NOT EXISTS academy_fortschritt (
  partner_id    INT UNSIGNED NOT NULL,
  modul         VARCHAR(40)  NOT NULL,
  lektionen     VARCHAR(255) NOT NULL DEFAULT '',   -- gelesene Lektionen als Liste „0,1,3“
  begonnen_am   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  zuletzt_am    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fertig_am     DATETIME     NULL,
  test_richtig  TINYINT UNSIGNED NULL,
  test_fragen   TINYINT UNSIGNED NULL,
  PRIMARY KEY (partner_id, modul),
  KEY idx_af_zuletzt (partner_id, zuletzt_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Merkliste: art = modul | einwand | leistung | kontakt, ziel = slug
CREATE TABLE IF NOT EXISTS academy_merkliste (
  partner_id INT UNSIGNED NOT NULL,
  art        VARCHAR(12)  NOT NULL,
  ziel       VARCHAR(40)  NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (partner_id, art, ziel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eigene Lernnotizen: nur der Partner selbst sieht sie (die Verwaltung zeigt sie nicht).
CREATE TABLE IF NOT EXISTS academy_notizen (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  partner_id INT UNSIGNED NOT NULL,
  modul      VARCHAR(40)  NOT NULL DEFAULT '',
  text       VARCHAR(2000) NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_an_partner (partner_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anonyme Zähler je Tag (was wird geöffnet/gesucht) — ohne Partner, ohne Freitext.
CREATE TABLE IF NOT EXISTS academy_zaehler (
  tag   DATE        NOT NULL,
  art   VARCHAR(12) NOT NULL,
  ziel  VARCHAR(40) NOT NULL,
  n     INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (tag, art, ziel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
