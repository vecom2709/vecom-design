-- Passwort vergessen (09.10.2026, Uwe: „kannst du mir es zurückschalten“).
-- Ein Link je Anforderung, 30 Minuten gültig, einmal benutzbar. Gespeichert wird nur die Prüfsumme.
CREATE TABLE IF NOT EXISTS passwort_links (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  gueltig_bis DATETIME NOT NULL,
  benutzt_am DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_passwort_links_hash (token_hash),
  KEY ix_passwort_links_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
