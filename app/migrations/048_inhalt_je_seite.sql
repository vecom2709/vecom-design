-- ===========================================================================
-- 048_inhalt_je_seite.sql — Was mit dem Auftrag waechst, waechst im Preis mit.
--
-- WARUM
--
-- Uwe, 13.09.2026, nach der Sprachrunde: „Schau nochmal, ob die anderen
-- Bausteine auch realistisch sind."
--
-- Der Fehler bei `sprache` war kein Einzelfall, sondern ein Muster: eine
-- Pauschale fuer Arbeit, die mit jeder Seite mitwaechst. Vier Bausteine haben
-- ihn noch, und einer davon schlimmer als die Sprache ihn hatte.
--
--                      pauschal      1 Seite    5 Seiten   15 Seiten
--   Texte schreiben    140-185 EUR   140/Seite  28/Seite   9,30/Seite
--   Bilder             105-140 EUR   105/Seite  21/Seite   7,00/Seite
--   Inhalte uebernehm. 105-140 EUR   105/Seite  21/Seite   7,00/Seite
--
-- Schlimmer als bei der Sprache, weil Texte und Bilder AUTOMATISCH anfallen:
-- Sie werden berechnet, sobald der Kunde sie unter „Was hast du schon fertig?"
-- nicht ankreuzt — und das ist der Normalfall. Ein Hotel mit fuenfzehn Seiten
-- in drei Sprachen bekam eine Zeile ueber 140 Euro, hinter der 45
-- Seitenfassungen Text stehen.
--
-- `express` hat denselben Fehler andersherum: 170-230 Euro fest sind beim
-- Einseiter ein Aufschlag von dreissig Prozent und beim Hotel von fuenf —
-- dabei ist Vorrang bei einem grossen Auftrag viel mehr Verschiebung.
--
-- DER SATZ IST DER HEUTIGE PREIS GETEILT DURCH FUENF
--
-- „Wenige Seiten (3-5)" ist der haeufigste Fall. Wer ihn bestellt, zahlt nach
-- dieser Runde auf den Cent dasselbe wie vorher. Das ist Absicht: Es wird eine
-- Rechenregel repariert, nicht der Preis erhoeht. Bewegen tun sich nur die
-- Enden — der Einseiter wird billiger (dort war die Pauschale zu teuer, genau
-- wie die Sprache es beim Einseiter war), der grosse Auftrag ehrlich.
--
--   Handwerker  1 Seite, 1 Sprache       575- 725 ->  375- 475 EUR
--   Trattoria   5 Seiten, 3 Sprachen    1350-1800 -> 1350-1800 EUR
--   Laden       9 Seiten, Shop          2250-2950 -> 2500-3350 EUR
--   Hotel      15 Seiten, eilig         3400-4600 -> 4400-6000 EUR
--
-- WAS PAUSCHAL BLEIBT UND WARUM
--
-- Speisekarte, Termine, Buchung, Shop und Logo. Die baut man einmal,
-- unabhaengig von der Seitenzahl — eine Buchung fuer eine Ferienwohnung ist
-- nicht billiger, weil die Seite klein ist. Wer hier je Seite rechnet, macht
-- denselben Fehler in die andere Richtung.
--
-- ZWEI FUNKTIONEN STEHEN ZU NIEDRIG
--
-- Buchung 450-600 gegen Shop 680-910: Verfuegbarkeit, Zeitraeume, Preise je
-- Saison und eine Bestaetigung, die von allein rausgeht, sind mindestens so
-- viel Arbeit wie Artikel, Varianten, Warenkorb und Versandregeln. Neu
-- 600-800.
--
-- Speisekarte 105-140 gegen Termine 220-290: Nach Gruppen geordnet und spaeter
-- ohne Eingriff in den Code aenderbar heisst, dass es eine Bearbeitungsflaeche
-- gibt. Termine sind nicht doppelt so viel Arbeit. Neu 150-200.
--
-- DIE VIER BEISPIELE AUF DER STARTSEITE AENDERN SICH NICHT
--
-- Sie rechnen mit vollstaendigem Material (Texte, Fotos und Logo vorhanden)
-- und ohne Express — keiner der hier geaenderten Bausteine kommt darin vor.
-- 325-400, 600-750, 1.000-1.300 und 1.250-1.650 bleiben, wie sie sind.
--
-- WIEDERHOLBAR
--
-- Alle UPDATEs setzen feste Werte statt zu rechnen.
-- ===========================================================================

-- Die drei Inhaltsposten: aus Pauschalen werden Preise je Seite.
UPDATE bausteine
   SET preis_cents = 2800, preis_bis_cents = 3700,
       je_einheit = 1, einheit = 'seite',
       name_it = 'Scrittura dei testi, per pagina',
       name_de = 'Texte schreiben, je Seite',
       name_en = 'Copywriting, per page',
       text_it = 'Scrivo i testi di ogni pagina, tu li rileggi prima della pubblicazione.',
       text_de = 'Ich schreibe die Texte jeder Seite, du liest sie vor der Veröffentlichung gegen.',
       text_en = 'I write the text for every page; you read it before it goes live.'
 WHERE slug = 'texte' AND monatlich = 0;

UPDATE bausteine
   SET preis_cents = 2100, preis_bis_cents = 2800,
       je_einheit = 1, einheit = 'seite',
       name_it = 'Immagini, per pagina',
       name_de = 'Bilder, je Seite',
       name_en = 'Images, per page',
       text_it = 'Scelta, ritaglio e ottimizzazione per ogni pagina. Se mancano, cerco immagini con licenza.',
       text_de = 'Auswahl, Zuschnitt und Optimierung für jede Seite. Fehlen welche, suche ich lizenzierte Bilder.',
       text_en = 'Selection, cropping and optimisation for each page. If some are missing, I source licensed ones.'
 WHERE slug = 'fotos' AND monatlich = 0;

UPDATE bausteine
   SET preis_cents = 2100, preis_bis_cents = 2800,
       je_einheit = 1, einheit = 'seite',
       name_it = 'Recupero dei contenuti, per pagina',
       name_de = 'Inhalte übernehmen, je Seite',
       name_en = 'Migrating content, per page',
       text_it = 'Testi e immagini dal sito esistente, i vecchi indirizzi continuano a funzionare.',
       text_de = 'Texte und Bilder von der bestehenden Seite, alte Adressen funktionieren weiter.',
       text_en = 'Text and images from the existing site; old addresses keep working.'
 WHERE slug = 'uebernahme' AND monatlich = 0;

-- Der Eilzuschlag richtet sich nach dem Umfang, nicht nach dem Kalender.
UPDATE bausteine
   SET preis_cents = 3400, preis_bis_cents = 4600,
       je_einheit = 1, einheit = 'seite',
       name_it = 'Esecuzione accelerata, per pagina',
       name_de = 'Beschleunigte Umsetzung, je Seite',
       name_en = 'Priority delivery, per page',
       text_it = 'Il tuo progetto passa avanti. Più è grande, più lavoro sposta.',
       text_de = 'Dein Projekt geht in der Reihenfolge vor. Je größer es ist, desto mehr schiebt es.',
       text_en = 'Your project moves to the front of the queue. The bigger it is, the more it displaces.'
 WHERE slug = 'express' AND monatlich = 0;

-- Zwei Funktionen, die im Vergleich zu niedrig standen. Sie bleiben pauschal:
-- gebaut wird einmal, unabhaengig von der Seitenzahl.
UPDATE bausteine SET preis_cents = 60000, preis_bis_cents = 80000
 WHERE slug = 'buchung'     AND monatlich = 0;
UPDATE bausteine SET preis_cents = 15000, preis_bis_cents = 20000
 WHERE slug = 'speisekarte' AND monatlich = 0;
