-- ===========================================================================
-- 191_akquise_aussortiert.sql — Aussortieren ohne Kontaktweg (06.10.2026).
-- Uwe: „finde von allen die E-Mail-Adressen und zeige sie mit an, auch
-- zukünftige — und die Betriebe, die keine E-Mail haben und kein WhatsApp,
-- lösche raus.“ Gelöschte Betriebe merkt sich diese Tabelle nur über ihre
-- Schlüssel (Quelle, Domain, Name+PLZ), damit die nächtliche Suche sie nicht
-- jede Nacht neu anlegt und prüft. Taucht später eine E-Mail oder ein
-- WhatsApp auf, wird der Betrieb wieder aufgenommen und der Eintrag gelöscht.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS akq_aussortiert (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  schluessel  VARCHAR(190) NOT NULL,
  name        VARCHAR(190) NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_akq_aussortiert (schluessel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
