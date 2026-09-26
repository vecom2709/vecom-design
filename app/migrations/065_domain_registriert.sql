-- ===========================================================================
-- 065_domain_registriert.sql — wann eine neue Domain registriert war (26.09.2026)
--
-- Uwe bestellt neue Domains weiter selbst im Domainbestellsystem (kostenlos,
-- ein Klick; eine Schnittstelle dafuer gibt es nicht). Das System erkennt
-- danach von allein, dass die Nameserver auf All-Inkl zeigen, traegt es hier
-- ein und macht weiter: HTTPS pruefen, Kunde benachrichtigen.
-- Wiederholbar: IF NOT EXISTS.
-- ===========================================================================

ALTER TABLE hosting_auftraege ADD COLUMN IF NOT EXISTS domain_registriert_am DATETIME NULL;
