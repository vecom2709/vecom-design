-- ===========================================================================
-- 194_partner_mailto.sql — E-Mail über das eigene Mailprogramm (06.10.2026).
-- Uwe: „Das System soll die E-Mails NICHT selbst über einen eigenen Server oder
-- eine API versenden. Es soll stattdessen aus dem generierten Text einen
-- mailto:-Link erzeugen … Das System speichert nur den Zeitstempel der
-- Generierung.“ — für Partner und Verwaltung.
-- Neue Zeilen in partner_mails haben den Stand „mailto“ und keinen Betreff und
-- keinen Text mehr; es bleiben Zeitpunkt, Empfänger (für den Abmeldelink)
-- und der Abmeldeschlüssel.
-- ===========================================================================

ALTER TABLE partner_mails
  MODIFY status  ENUM('wird_gesendet','gesendet','fehler','mailto') NOT NULL DEFAULT 'mailto',
  MODIFY betreff VARCHAR(160) NULL,
  MODIFY text    MEDIUMTEXT   NULL,
  MODIFY absender VARCHAR(120) NULL;
