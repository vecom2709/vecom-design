-- ===========================================================================
-- 130_marketing_partner_googleprofil.sql — Empfehlen leicht gemacht und ein
-- kleiner Einstieg (Marketing-Studio 8, 01.10.2026, Uwe: „ja“ zu S3 und S6).
--
-- 1. mk_inhalte.partner: Ein freigegebener Beitrag kann Partnern zum Teilen
--    gegeben werden — sie bekommen ihn mit IHREM Link (Provision bleibt bei
--    ihnen), Bild über m.php wie bei Meta.
-- 2. Paket „google-profil“: Google-Unternehmensprofil einrichten, Festpreis
--    89 €, direkt buchbar (Zusatz, keine Website). Die Startdatei
--    standardpakete.json trägt dasselbe für neue Einrichtungen.
--
-- Nur hinzufügen. Rückweg: Spalte entfernen, Paket auf active = 0.
-- ===========================================================================

ALTER TABLE mk_inhalte
  ADD COLUMN IF NOT EXISTS partner TINYINT(1) NOT NULL DEFAULT 0 AFTER status;

INSERT IGNORE INTO packages (slug, art, name, description, sub, ideal, price_cents, monthly_cents, currency, features, texte, active, oeffentlich, direktkauf, popular, sort)
VALUES ('google-profil', 'zusatz', 'Google-Unternehmensprofil', 'Bei Google Maps gefunden werden — auch ohne Website',
  'Bei Google Maps gefunden werden — auch ohne Website', 'Der einfachste erste Schritt: dass Kunden Sie auf Google Maps finden, anrufen und bewerten.',
  8900, 0, 'EUR',
  '["Enthalten:","Profil neu anlegen oder ein vorhandenes übernehmen","Kategorie, Öffnungszeiten, Einzugsgebiet, Leistungen und Beschreibung sauber formuliert","Fotos ausgewählt und in sinnvoller Reihenfolge hochgeladen","Link für Bewertungen mit QR-Code zum Ausdrucken","Telefon, WhatsApp und — falls vorhanden — Website verknüpft","Die Bestätigung macht Google (Postkarte, Video oder Telefon): ich begleite Sie Schritt für Schritt","Keine Versprechen zur Platzierung — die Reihenfolge entscheidet Google"]',
  '{"it":{"name":"Profilo Google dell’attività","sub":"Farsi trovare su Google Maps, anche senza sito","ideal":"Il primo passo più semplice: che i clienti la trovino su Google Maps, la chiamino e lascino una recensione.","features":["Cosa comprende:","Creazione del profilo o presa in carico di quello esistente","Categoria, orari, zona servita, servizi e descrizione scritti bene","Foto scelte e caricate nell’ordine giusto","Link per le recensioni con QR da stampare","Telefono, WhatsApp e — se c’è — sito collegati","La verifica la fa Google (cartolina, video o telefono): la accompagno passo per passo","Nessuna promessa di posizione: l’ordine lo decide Google"]},"de":{"name":"Google-Unternehmensprofil","sub":"Bei Google Maps gefunden werden — auch ohne Website","ideal":"Der einfachste erste Schritt: dass Kunden Sie auf Google Maps finden, anrufen und bewerten.","features":["Enthalten:","Profil neu anlegen oder ein vorhandenes übernehmen","Kategorie, Öffnungszeiten, Einzugsgebiet, Leistungen und Beschreibung sauber formuliert","Fotos ausgewählt und in sinnvoller Reihenfolge hochgeladen","Link für Bewertungen mit QR-Code zum Ausdrucken","Telefon, WhatsApp und — falls vorhanden — Website verknüpft","Die Bestätigung macht Google (Postkarte, Video oder Telefon): ich begleite Sie Schritt für Schritt","Keine Versprechen zur Platzierung — die Reihenfolge entscheidet Google"]},"en":{"name":"Google Business Profile","sub":"Get found on Google Maps — even without a website","ideal":"The simplest first step: customers find you on Google Maps, call you and leave a review.","features":["Included:","New profile or takeover of an existing one","Category, opening hours, service area, services and description well written","Photos chosen and uploaded in a sensible order","Review link with a printable QR code","Phone, WhatsApp and — if there is one — website linked","Google does the verification (postcard, video or phone): I guide you step by step","No ranking promises — Google decides the order"]}}',
  1, 1, 1, 0, 21);
