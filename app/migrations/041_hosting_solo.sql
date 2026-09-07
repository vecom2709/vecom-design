-- 041_hosting_solo.sql — Domain & Hosting auch als eigenes, oeffentliches Paket.
--
-- Bisher entstand das Hosting nur als Anhaengsel eines Website-Projekts.
-- Jetzt darf es allein stehen: Kunden, die keine Website von uns wollen,
-- bekommen Domain, Webspace, SSL und Postfach als eigenen Monatsvertrag.
--
-- active = 1 macht das Paket fuer die Website sichtbar (pakete-daten.php
-- liefert es unter dem eigenen Schluessel 'hosting' aus, nie zwischen den
-- Website-Preiskarten). In Bestell- und Angebotslisten taucht es weiterhin
-- nicht auf — dort wird die Art ausgefiltert, denn eine "Bestellung" ueber
-- 0 € einmalig waere Unsinn: Der Vertrag entsteht aus dem Hosting-Ablauf.

UPDATE packages SET
  active = 1,
  oeffentlich = 1,
  description = 'Wunschdomain, 10 GB Webspace, SSL-Zertifikat und E-Mail-Postfach auf eigenem Account — mit eigenen Zugangsdaten.',
  sub = 'Dominio, spazio web, SSL e casella e-mail',
  ideal = 'Per chi vuole solo un dominio con e-mail professionale — senza sito.',
  features = '["Il tuo dominio (.it, .de, .com, .eu …) — registrato e gestito da noi","10 GB di spazio web con accesso FTP","Certificato SSL incluso — si rinnova da solo","Casella e-mail info@tuodominio — altre le crei tu","Account proprio con i TUOI dati di accesso","Contratto mensile, 12 mesi di durata minima"]',
  texte = '{"it":{"name":"Dominio & Hosting","sub":"Dominio, spazio web, SSL e casella e-mail","ideal":"Per chi vuole solo un dominio con e-mail professionale — senza sito.","features":["Il tuo dominio (.it, .de, .com, .eu …) — registrato e gestito da noi","10 GB di spazio web con accesso FTP","Certificato SSL incluso — si rinnova da solo","Casella e-mail info@tuodominio — altre le crei tu","Account proprio con i TUOI dati di accesso","Contratto mensile, 12 mesi di durata minima"]},"de":{"name":"Domain & Hosting","sub":"Domain, Speicherplatz, SSL und E-Mail-Postfach","ideal":"Für alle, die nur eine Domain mit professioneller E-Mail wollen — ohne Website.","features":["Deine Wunschdomain (.it, .de, .com, .eu …) — von uns registriert und betreut","10 GB Speicherplatz mit FTP-Zugang","SSL-Zertifikat inklusive — verlängert sich von selbst","E-Mail-Postfach info@deine-domain — weitere legst du selbst an","Eigener Account mit DEINEN Zugangsdaten","Monatsvertrag, 12 Monate Mindestlaufzeit"]},"en":{"name":"Domain & hosting","sub":"Domain, web space, SSL and email mailbox","ideal":"For anyone who just wants a domain with professional email — no website.","features":["Your domain (.it, .de, .com, .eu …) — registered and managed by us","10 GB of web space with FTP access","SSL certificate included — renews itself","Email mailbox info@yourdomain — create more yourself","Your own account with YOUR access details","Monthly contract, 12-month minimum term"]}}'
WHERE slug = 'hosting';
