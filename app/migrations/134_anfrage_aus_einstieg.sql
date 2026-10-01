-- ===========================================================================
-- 134_anfrage_aus_einstieg.sql — Interessenten aus dem E-Mail-Einstieg stehen
-- unter „Heute“ (01.10.2026, Uwe: Ja zu K1).
--
-- Wer über den E-Mail-Einstieg Kunde wurde, hatte weder Bestellung noch
-- Anfrage und tauchte deshalb in keiner Arbeitsliste auf (Anfrage über
-- Partner Anika, 01.10.2026). Für die schon vorhandenen Fälle wird die
-- Anfrage hier nachgetragen; neue bekommen sie in Zugang::annehmen().
-- ===========================================================================
INSERT INTO anfragen (customer_id, name, email, sprache, nachricht, status, created_at)
SELECT c.id,
       LEFT(COALESCE(NULLIF(TRIM(c.name), ''), z.email), 120),
       LEFT(z.email, 190),
       IF(c.sprache IN ('it', 'de', 'en'), c.sprache, IF(z.sprache IN ('it', 'de', 'en'), z.sprache, 'it')),
       CONCAT('Hat auf der Website seine E-Mail eingetragen und den Zugangslink bekommen.',
              IF(z.partner_code IS NOT NULL AND z.partner_code <> '', CONCAT(' Über Partner ', z.partner_code, '.'), ''),
              ' Noch kein Fragebogen ausgefüllt.'),
       'neu',
       COALESCE(z.geoeffnet_am, z.created_at)
  FROM zugaenge z
  JOIN customers c ON c.id = z.customer_id
 WHERE z.created_at >= NOW() - INTERVAL 30 DAY
   AND c.anonym_am IS NULL
   AND NOT EXISTS (SELECT 1 FROM anfragen a WHERE a.customer_id = c.id)
   AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.customer_id = c.id)
   AND z.id = (SELECT MAX(z2.id) FROM zugaenge z2 WHERE z2.customer_id = c.id);
