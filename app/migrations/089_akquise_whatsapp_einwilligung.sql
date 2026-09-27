-- ============================================================================
-- 089 — Einwilligung für E-Mail ODER WhatsApp (27.09.2026, Uwe: „ja, erweitere
-- auf E-Mail oder WhatsApp“).
--
-- Eine Einwilligung gilt nur für die Wege, die in ihrem Wortlaut stehen. Bis
-- heute war das nur „diese E-Mail-Adresse“. Neu: optional zusätzlich WhatsApp
-- an eine genannte Nummer -- die Nummer steht in der Bestätigungsmail, der Klick
-- darin bestätigt beides. akq_firmen.einwilligung_kanaele sagt, welche Wege
-- gedeckt sind (leer = nur E-Mail, wie bei allen Einwilligungen vor heute).
--
-- Regeln: WhatsApp mit Einwilligung = erlaubt, in beiden Ländern, mit derselben
-- Begründung wie die E-Mail (Werbung über elektronische Kanäle braucht die
-- vorherige Einwilligung; sie liegt dann vor). KEINE RECHTSBERATUNG -- in der
-- Verwaltung änderbar. Wiederholbar: ADD COLUMN → 1060 übersprungen, INSERT IGNORE.
-- ============================================================================

ALTER TABLE akq_einwilligungen ADD COLUMN whatsapp VARCHAR(40) NULL;
ALTER TABLE akq_firmen ADD COLUMN einwilligung_kanaele VARCHAR(40) NULL;
ALTER TABLE akq_firmen ADD COLUMN whatsapp VARCHAR(40) NULL;

INSERT IGNORE INTO akq_regeln (land, kanal, bedingung, ergebnis, begruendung, quelle, geprueft_am) VALUES
('DE','whatsapp','einwilligung','CONTACT_ALLOWED','Mit dokumentierter, ausdrücklicher Einwilligung für WhatsApp an genau diese Nummer zulässig (§ 7 Abs. 2 Nr. 3 UWG gilt für elektronische Post einschließlich Messenger). Jede Nachricht nennt, wie man widerspricht.','https://www.ihk.de/nordwestfalen/recht/rechtsthemen/wettbewerbsrecht/werbung-per-telefon-telefax-oder-e-mail-3614212','2026-09-27'),
('IT','whatsapp','einwilligung','CONTACT_ALLOWED','Mit dokumentierter vorheriger Einwilligung für WhatsApp an genau diese Nummer zulässig (Art. 130 Codice Privacy gilt für automatisierte und elektronische Kommunikation). Jede Nachricht nennt, wie man widerspricht.','https://www.garanteprivacy.it/home/docweb/-/docweb-display/docweb/2542348','2026-09-27');
