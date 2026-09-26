-- ===========================================================================
-- 070_zugang_partner.sql — der Partnercode reist mit der E-Mail (26.09.2026)
--
-- Der Hauptweg der Startseite ist „E-Mail eintragen → Link im Postfach →
-- Dashboard“. Der Kunde entsteht erst beim Öffnen des Links — oft auf einem
-- anderen Gerät, ohne den Besuchs-Keks. Uwe bat, genau das zu prüfen: Bis
-- heute ging der Partner auf diesem Weg verloren. Der Code wird deshalb
-- beim Eintragen an den Zugang geschrieben und beim Öffnen zugeordnet.
-- Wiederholbar: IF NOT EXISTS.
-- ===========================================================================

ALTER TABLE zugaenge ADD COLUMN IF NOT EXISTS partner_code VARCHAR(16) NULL;
