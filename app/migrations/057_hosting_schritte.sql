-- ===========================================================================
-- 057_hosting_schritte.sql — Hosting einrichten in einzelnen Schritten
-- (Phase 3, 25.09.2026)
--
-- Bisher lief das Anlegen in einem Zug. Brach es nach dem KAS-Account ab,
-- blieb der Auftrag auf "zugestimmt" -- und die naechste bezahlte Rate legte
-- einen ZWEITEN Account an. Eine gescheiterte Domain liess sich nicht
-- nachholen, und was geklappt hatte, stand nur in einem Freitext.
--
-- Jetzt hat jeder Schritt eine Zeile: offen | laeuft | fertig | fehler |
-- hand (Uwe macht es im KAS) | entfaellt. "laeuft" beim naechsten Blick
-- heisst: abgebrochen mitten im Aufruf -- beim Account wird dann NICHT
-- wiederholt, sondern Uwe gefragt, denn ob er entstand, weiss nur der KAS.
-- Wiederholbar: IF NOT EXISTS.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS hosting_schritte (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  auftrag_id  INT UNSIGNED NOT NULL,
  schritt     VARCHAR(24)  NOT NULL,
  status      VARCHAR(12)  NOT NULL DEFAULT 'offen',
  versuche    INT UNSIGNED NOT NULL DEFAULT 0,
  text        VARCHAR(500) NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hosting_schritt (auftrag_id, schritt),
  CONSTRAINT fk_hosting_schritte_auftrag FOREIGN KEY (auftrag_id)
    REFERENCES hosting_auftraege (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
