-- ===========================================================================
-- 182 — Kunden & Leads des Partners (Phase 2, 05.10.2026, Uwe: „Ja, wie empfohlen“).
--
-- Bis hierher führte ein Partner seine Kontakte an fünf Stellen mit fünf
-- Statuslisten (Meine Kontakte im Browser, Reservierungen, Kontaktfreigaben,
-- Vorab, Website-Checks). Jetzt gibt es EINE Liste mit den sieben Stufen der
-- Spezifikation: neu → kontaktiert → interesse → termin → angebot → auftrag
-- → verloren. Die alten Tabellen bleiben und schreiben ab jetzt mit.
--
-- WARUM NICHT akq_firmen ERWEITERN: Dort laufen Vecoms Akquise-Automatiken
-- (Prüfung, Folge-Mails nach Einwilligung). Ein privater Kontakt eines Partners
-- darf da nie hineingeraten. partner_leads VERWEIST auf den Betrieb (firma_id),
-- es verdoppelt ihn nicht.
--
-- Kunden, die über den Link kamen (partner_zuordnungen), stehen hier NICHT:
-- Der Partner sieht sie weiter nur als Nummer, Ort und Stufe (ohne Namen).
--
-- Wiederholbar: CREATE … IF NOT EXISTS; die Übernahme läuft über INSERT IGNORE
-- und eindeutige Verweise (eine Firma, eine Freigabe, ein Vorab je Lead).
-- Rückweg: beide Tabellen entfernen — nichts anderes wurde verändert.
-- ===========================================================================

CREATE TABLE IF NOT EXISTS partner_leads (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  partner_id        INT UNSIGNED  NOT NULL,
  name              VARCHAR(120)  NOT NULL,
  name_norm         VARCHAR(120)  NOT NULL DEFAULT '',
  ansprechpartner   VARCHAR(80)   NOT NULL DEFAULT '',
  branche           VARCHAR(40)   NOT NULL DEFAULT '',          -- Schlüssel aus akquise_branchen.json
  ort               VARCHAR(80)   NOT NULL DEFAULT '',
  telefon           VARCHAR(40)   NOT NULL DEFAULT '',
  telefon_norm      VARCHAR(16)   NOT NULL DEFAULT '',          -- letzte 9 Ziffern, für die Dublettenprüfung
  email             VARCHAR(190)  NOT NULL DEFAULT '',
  website           VARCHAR(255)  NOT NULL DEFAULT '',
  domain            VARCHAR(190)  NOT NULL DEFAULT '',
  quelle            VARCHAR(16)   NOT NULL DEFAULT 'eigen',     -- PartnerLeads::QUELLEN
  stufe             VARCHAR(12)   NOT NULL DEFAULT 'neu',       -- PartnerLeads::STUFEN
  prioritaet        VARCHAR(8)    NULL,                         -- NULL = automatisch, heiss|warm|normal|spaeter = von Hand
  naechster_schritt VARCHAR(160)  NOT NULL DEFAULT '',
  naechster_am      DATE          NULL,
  firma_id          INT UNSIGNED  NULL,                         -- akq_firmen (Finder, Anrufliste)
  freigabe_id       INT UNSIGNED  NULL,                         -- partner_kontaktfreigaben
  vorab_id          INT UNSIGNED  NULL,                         -- partner_vorab
  kampagne_id       INT UNSIGNED  NULL,                         -- mk_kampagnen
  uebergeben_am     DATETIME      NULL,                         -- „An Vecom übergeben“ (Partner::kundeMelden)
  kontakt_am        DATETIME      NULL,                         -- letzter Anruf, WhatsApp, E-Mail
  stufe_am          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  archiviert_am     DATETIME      NULL,                         -- archivieren statt löschen (Spezifikation 73)
  KEY ix_pl_partner (partner_id, archiviert_am, stufe),
  KEY ix_pl_tel (partner_id, telefon_norm),
  KEY ix_pl_domain (partner_id, domain),
  KEY ix_pl_name (partner_id, name_norm),
  UNIQUE KEY uq_pl_firma (partner_id, firma_id),
  UNIQUE KEY uq_pl_freigabe (freigabe_id),
  UNIQUE KEY uq_pl_vorab (vorab_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notizen, Aufgaben und Kontaktverlauf je Lead. Notizen sind die des Partners:
-- Die Verwaltung zeigt Name, Stufe und den letzten Schritt, nie den Text.
CREATE TABLE IF NOT EXISTS partner_lead_verlauf (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_id      INT UNSIGNED  NOT NULL,
  partner_id   INT UNSIGNED  NOT NULL,
  art          VARCHAR(12)   NOT NULL,                          -- notiz|anruf|whatsapp|email|aufgabe|stufe|uebergabe|system
  text         VARCHAR(1000) NOT NULL DEFAULT '',
  faellig_am   DATE          NULL,                              -- nur Aufgaben
  erledigt_am  DATETIME      NULL,                              -- nur Aufgaben
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_plv_lead (lead_id, created_at),
  KEY ix_plv_aufgabe (partner_id, art, erledigt_am, faellig_am)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Übernahme 1: laufende Reservierungen (Firmen-Finder und Anrufliste).
INSERT IGNORE INTO partner_leads (partner_id, name, name_norm, branche, ort, telefon, telefon_norm, email, website, domain,
                                  quelle, stufe, firma_id, kontakt_am, created_at, stufe_am)
SELECT r.partner_id, LEFT(f.name, 120), LEFT(COALESCE(f.name_norm, ''), 120), LEFT(COALESCE(f.branche, ''), 40), LEFT(COALESCE(f.stadt, ''), 80),
       LEFT(COALESCE(f.telefon, ''), 40), RIGHT(REGEXP_REPLACE(COALESCE(f.telefon, ''), '[^0-9]', ''), 9),
       LEFT(COALESCE(f.email, ''), 190), LEFT(COALESCE(f.url, ''), 255), LEFT(COALESCE(f.domain, ''), 190),
       IF(r.herkunft = 'vecom', 'anrufliste', 'finder'),
       CASE COALESCE(r.anruf_status, '')
            WHEN 'zugestimmt' THEN 'interesse'
            WHEN 'kein_interesse' THEN 'verloren'
            WHEN 'nicht_erreichbar' THEN 'verloren'
            WHEN 'nicht_erreicht' THEN 'kontaktiert'
            ELSE IF(r.angeschrieben_am IS NOT NULL, 'kontaktiert', 'neu') END,
       r.firma_id, COALESCE(r.anruf_am, r.angeschrieben_am), r.created_at, COALESCE(r.anruf_am, r.angeschrieben_am, r.created_at)
  FROM partner_reservierungen r
  JOIN akq_firmen f ON f.id = r.firma_id
 WHERE r.bis >= CURDATE();

-- Übernahme 2: offene Kontaktfreigaben (der Besucher hat selbst den Haken gesetzt).
INSERT IGNORE INTO partner_leads (partner_id, name, name_norm, telefon, telefon_norm, email, quelle, stufe, freigabe_id, created_at, stufe_am)
SELECT k.partner_id, LEFT(k.name, 120), LEFT(LOWER(k.name), 120), LEFT(COALESCE(k.telefon, ''), 40),
       RIGHT(REGEXP_REPLACE(COALESCE(k.telefon, ''), '[^0-9]', ''), 9), LEFT(COALESCE(k.email, ''), 190),
       'landingpage', 'neu', k.id, k.created_at, k.created_at
  FROM partner_kontaktfreigaben k
 WHERE k.erledigt_am IS NULL;

-- Übernahme 3: Vorab-Festpreise, die noch laufen — der Preis steht, also ANGEBOT.
-- Name ist die eigene Notiz des Partners (z. B. „Bar Rossi“), nie Kundendaten.
INSERT IGNORE INTO partner_leads (partner_id, name, name_norm, quelle, stufe, vorab_id, created_at, stufe_am)
SELECT v.partner_id, LEFT(IF(TRIM(v.bezeichnung) = '', CONCAT('Festpreis ', v.id), v.bezeichnung), 120),
       LEFT(LOWER(IF(TRIM(v.bezeichnung) = '', CONCAT('festpreis ', v.id), v.bezeichnung)), 120),
       'vorab', 'angebot', v.id, v.created_at, v.created_at
  FROM partner_vorab v
 WHERE v.status IN ('offen', 'einloesen', 'eingeloest');
