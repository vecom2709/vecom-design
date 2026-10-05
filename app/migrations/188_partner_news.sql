-- ===========================================================================
-- 188_partner_news.sql — News an Partner mit Zielgruppe (Phase 7b-2, 05.10.2026).
-- Uwe: „Dashboard + Handy-Hinweis“ — keine Mail. Eine Meldung steht auf der
-- Startseite des Command Centers, bis der Partner sie wegklickt (oder sie
-- abläuft), und geht einmal als Push an alle passenden Partner mit App.
-- Zielgruppe: alle · ein Level · ein Land · neue Partner (Vereinbarung < 30 Tage).
-- ===========================================================================

CREATE TABLE IF NOT EXISTS partner_news (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  titel_it      VARCHAR(120) NOT NULL,
  titel_de      VARCHAR(120) NOT NULL DEFAULT '',
  titel_en      VARCHAR(120) NOT NULL DEFAULT '',
  text_it       VARCHAR(600) NOT NULL,
  text_de       VARCHAR(600) NOT NULL DEFAULT '',
  text_en       VARCHAR(600) NOT NULL DEFAULT '',
  link          VARCHAR(300) NULL,
  ziel          ENUM('alle','level','land','neu') NOT NULL DEFAULT 'alle',
  ziel_wert     VARCHAR(20)  NOT NULL DEFAULT '',
  bis           DATE NULL,
  push_an       INT UNSIGNED NOT NULL DEFAULT 0,
  zurueck_am    DATETIME NULL,
  user_id       INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_pnews_zeit (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Wer eine Meldung bekommen hat und ob er sie weggeklickt hat. Die Empfänger
-- werden beim Senden festgehalten: Wer später Gold wird, bekommt keine alte
-- Gold-Meldung nachgereicht, und die Zahlen in der Verwaltung bleiben stehen.
CREATE TABLE IF NOT EXISTS partner_news_an (
  news_id       INT UNSIGNED NOT NULL,
  partner_id    INT UNSIGNED NOT NULL,
  gelesen_am    DATETIME NULL,
  PRIMARY KEY (news_id, partner_id),
  KEY ix_pnewsan_partner (partner_id, gelesen_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
