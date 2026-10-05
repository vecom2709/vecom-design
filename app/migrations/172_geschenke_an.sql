-- ===========================================================================
-- 172_geschenke_an.sql — Notizbuch, Flasche, Untersetzer, Stofftasche für
-- Partner einschalten (05.10.2026, Uwe auf die Frage „sichtbar, sobald
-- Printful die Preise geliefert hat?“: „Ja, einschalten“).
-- Printful-Preise auf live geholt am 05.10.2026 (estimate-costs, EUR), z. B.
-- Notizbuch 1 Stück: Partnerpreis IT 25,60 € / DE 25,00 €. Ohne Preis zeigt
-- der Katalog ein Produkt ohnehin nicht (Werbemittel::katalog).
-- ===========================================================================

UPDATE wm_produkte SET aktiv = 1 WHERE vorlage IN ('notizbuch', 'flasche', 'untersetzer', 'beutel');
