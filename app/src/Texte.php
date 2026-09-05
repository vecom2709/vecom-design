<?php
declare(strict_types=1);

/**
 * Alle Texte, die an Kunden gehen — in Italienisch, Deutsch und Englisch.
 * An einer Stelle, damit sich Formulierungen ändern lassen, ohne Code zu
 * durchsuchen. Platzhalter in geschweiften Klammern werden ersetzt.
 */
final class Texte
{
    /* ======================================================================
       DER FRAGEBOGEN

       Vorher: 38 Felder, davon 25 leere Textkaesten, und ein Abschnitt mit
       sechzehn Stueck. Wer das auf dem Handy oeffnet, sieht eine Wand.
       Drei Fragen standen ausserdem doppelt drin (Ziel und Handlung,
       Beispiele und Vorbilder) und eine dritte fragte, was zwei Schritte
       spaeter noch einmal gefragt wurde.

       Jetzt: sechs Abschnitte, keiner ueber neun Felder, und das meiste ist
       Anklicken statt Schreiben. Frei bleibt, was frei bleiben muss -- was
       eine Firma macht, was sie nicht will, wie ihre Texte klingen sollen.
       Eine Auswahl ist keine Bequemlichkeit, sondern eine bessere Antwort:
       "Gastronomie" ist verwertbar, "wir machen so Essen und Catering" nicht.

       Jede Auswahl hat "weiss ich nicht". Ohne das raten Kunden -- und eine
       Vermutung ist schlechter als eine Luecke, weil ich sie nicht sehe.

       ARTEN
         text   einzeilig
         lang   mehrzeilig
         zahl   Zahlenfeld
         wahl   die Baukastenliste (kommt aus dem Angebot)
         eins   genau eine Auswahl
         mehr   mehrere Auswahlen
         stand  Zeilen mit je vier Zustaenden (haben/kommt/du/nein)
       Dazu:
         frei      => true   eine freie Zeile unter der Auswahl (<name>__frei)
         wenn      => [...]  nur zeigen, wenn ein anderes Feld passt
         vorschlag => '...'  Vorbelegung aus Branche und Ort
       ====================================================================== */
    public const FRAGEBOGEN = [

        /* ---------- 1 ---------------------------------------------------- */
        'unternehmen' => [
            'it' => 'La tua azienda', 'de' => 'Dein Unternehmen', 'en' => 'Your business',
            'felder' => [
                'firmenname' => ['it' => 'Nome dell’azienda', 'de' => 'Firmenname', 'en' => 'Company name', 'art' => 'text'],

                /* Die Branche entscheidet ueber Ambitionsstufe, Seitenvorschlag
                   und Suchwoerter. Aus Freitext musste sie geraten werden. */
                'branche' => [
                    'it' => 'Settore', 'de' => 'Branche', 'en' => 'Industry',
                    'art' => 'eins', 'frei' => true,
                    'optionen' => [
                        'gastronomie' => ['it' => 'Ristorazione — ristorante, bar, pizzeria', 'de' => 'Gastronomie — Restaurant, Bar, Pizzeria', 'en' => 'Food & drink — restaurant, bar, pizzeria'],
                        'beherbergung'=> ['it' => 'Ospitalità — hotel, B&B, casa vacanze', 'de' => 'Beherbergung — Hotel, B&B, Ferienhaus', 'en' => 'Hospitality — hotel, B&B, holiday let'],
                        'handwerk'    => ['it' => 'Artigianato e servizi tecnici', 'de' => 'Handwerk und technische Dienste', 'en' => 'Trades and technical services'],
                        'schoenheit'  => ['it' => 'Bellezza e benessere — parrucchiere, estetica', 'de' => 'Schönheit und Wellness — Friseur, Kosmetik', 'en' => 'Beauty and wellbeing — hair, cosmetics'],
                        'wein'        => ['it' => 'Vino, olio, agricoltura', 'de' => 'Wein, Öl, Landwirtschaft', 'en' => 'Wine, oil, farming'],
                        'laden'       => ['it' => 'Negozio o commercio', 'de' => 'Laden oder Handel', 'en' => 'Shop or retail'],
                        'praxis'      => ['it' => 'Studio — medico, avvocato, commercialista', 'de' => 'Praxis oder Kanzlei — Arzt, Anwalt, Steuerberater', 'en' => 'Practice — doctor, lawyer, accountant'],
                        'immobilien'  => ['it' => 'Immobiliare', 'de' => 'Immobilien', 'en' => 'Property'],
                        'dienst'      => ['it' => 'Servizi alle imprese', 'de' => 'Dienstleistung für Firmen', 'en' => 'Business services'],
                        'transport'   => ['it' => 'Trasporti e logistica', 'de' => 'Transport und Logistik', 'en' => 'Transport and logistics'],
                        'anders'      => ['it' => 'Altro', 'de' => 'Etwas anderes', 'en' => 'Something else'],
                    ],
                ],

                'beschreibung' => ['it' => 'Cosa fate, in poche frasi', 'de' => 'Was ihr macht, in wenigen Sätzen', 'en' => 'What you do, in a few sentences', 'art' => 'lang'],

                'zielgruppe' => [
                    'it' => 'Chi sono i vostri clienti?', 'de' => 'Wer sind eure Kunden?', 'en' => 'Who are your customers?',
                    'art' => 'mehr', 'frei' => true,
                    'optionen' => [
                        'privat'    => ['it' => 'Privati', 'de' => 'Privatleute', 'en' => 'Private customers'],
                        'firmen'    => ['it' => 'Aziende', 'de' => 'Firmen', 'en' => 'Businesses'],
                        'einheim'   => ['it' => 'Gente del posto', 'de' => 'Leute aus der Gegend', 'en' => 'Locals'],
                        'touristen' => ['it' => 'Turisti', 'de' => 'Touristen', 'en' => 'Tourists'],
                        'stamm'     => ['it' => 'Clienti abituali', 'de' => 'Stammkunden', 'en' => 'Regulars'],
                        'familien'  => ['it' => 'Famiglie', 'de' => 'Familien', 'en' => 'Families'],
                        'jung'      => ['it' => 'Giovani', 'de' => 'Junge Leute', 'en' => 'Younger people'],
                        'behoerden' => ['it' => 'Enti pubblici', 'de' => 'Behörden und öffentliche Auftraggeber', 'en' => 'Public sector'],
                    ],
                ],

                'ort' => ['it' => 'In quale città o paese siete?', 'de' => 'In welchem Ort seid ihr?', 'en' => 'Which town are you in?', 'art' => 'text'],

                'gebiet' => [
                    'it' => 'Fin dove arrivate?', 'de' => 'Wie weit reicht euer Einzugsgebiet?', 'en' => 'How far do you reach?',
                    'art' => 'eins',
                    'optionen' => [
                        'ort'      => ['it' => 'Il paese e i dintorni', 'de' => 'Der Ort und die Umgebung', 'en' => 'The town and around it'],
                        'provinz'  => ['it' => 'Tutta la provincia', 'de' => 'Die ganze Provinz', 'en' => 'The whole province'],
                        'region'   => ['it' => 'Tutta la regione', 'de' => 'Die ganze Region', 'en' => 'The whole region'],
                        'land'     => ['it' => 'Tutto il paese', 'de' => 'Das ganze Land', 'en' => 'The whole country'],
                        'welt'     => ['it' => 'Anche all’estero', 'de' => 'Auch über die Grenze hinaus', 'en' => 'Abroad as well'],
                    ],
                ],

                'ansprech' => ['it' => 'Con chi parlo durante il lavoro? Nome e ruolo', 'de' => 'Mit wem spreche ich während der Arbeit? Name und Rolle', 'en' => 'Who do I talk to while we work? Name and role', 'art' => 'text'],

                'entscheider' => [
                    'it' => 'Chi decide alla fine?', 'de' => 'Wer entscheidet am Ende?', 'en' => 'Who decides in the end?',
                    'art' => 'eins', 'frei' => true,
                    'optionen' => [
                        'selbst'  => ['it' => 'La stessa persona', 'de' => 'Dieselbe Person', 'en' => 'The same person'],
                        'zusammen'=> ['it' => 'Decidiamo insieme, in due o tre', 'de' => 'Wir entscheiden zu zweit oder zu dritt', 'en' => 'Two or three of us decide together'],
                        'andere'  => ['it' => 'Qualcun altro — scrivo chi qui sotto', 'de' => 'Jemand anderes — schreibe ich unten dazu', 'en' => 'Someone else — I’ll write who below'],
                    ],
                ],
            ],
        ],

        /* ---------- 2 ---------------------------------------------------- */
        'ziel' => [
            'it' => 'Obiettivo e visitatori', 'de' => 'Ziel und Besucher', 'en' => 'Goal and visitors',
            'felder' => [
                /* Vorher standen hier zwei Fragen -- "Was soll die Website
                   erreichen" und "Was soll ein Besucher tun". Das ist
                   dieselbe Frage von zwei Seiten. Jetzt eine Liste und eine
                   Rangfolge: Was am meisten zaehlt, und was danach. Eine
                   Rangfolge ist die einzige Auskunft, die im Streitfall
                   hilft -- eine Wunschliste ist es nie. */
                'ziel1' => [
                    'it' => 'Che cosa conta di più?', 'de' => 'Was zählt am meisten?', 'en' => 'What matters most?',
                    'art' => 'eins',
                    'optionen' => [
                        'anrufe'    => ['it' => 'Ricevere telefonate', 'de' => 'Angerufen werden', 'en' => 'Get phone calls'],
                        'anfragen'  => ['it' => 'Ricevere richieste scritte', 'de' => 'Schriftliche Anfragen bekommen', 'en' => 'Get written enquiries'],
                        'buchungen' => ['it' => 'Prenotazioni e appuntamenti', 'de' => 'Reservierungen und Termine', 'en' => 'Bookings and appointments'],
                        'verkauf'   => ['it' => 'Vendere online', 'de' => 'Online verkaufen', 'en' => 'Sell online'],
                        'gefunden'  => ['it' => 'Farsi trovare su Google', 'de' => 'Bei Google gefunden werden', 'en' => 'Be found on Google'],
                        'serioes'    => ['it' => 'Fare bella figura — il biglietto da visita', 'de' => 'Seriös wirken — die Visitenkarte', 'en' => 'Look credible — the calling card'],
                        'besuch'    => ['it' => 'Far venire la gente da voi', 'de' => 'Leute zu euch in den Laden holen', 'en' => 'Get people to come by'],
                        'bewerber'  => ['it' => 'Trovare collaboratori', 'de' => 'Bewerber finden', 'en' => 'Find staff'],
                    ],
                ],
                'ziel2' => [
                    'it' => 'E subito dopo?', 'de' => 'Und gleich danach?', 'en' => 'And right after that?',
                    'art' => 'eins',
                    'optionen' => [
                        'anrufe'    => ['it' => 'Ricevere telefonate', 'de' => 'Angerufen werden', 'en' => 'Get phone calls'],
                        'anfragen'  => ['it' => 'Ricevere richieste scritte', 'de' => 'Schriftliche Anfragen bekommen', 'en' => 'Get written enquiries'],
                        'buchungen' => ['it' => 'Prenotazioni e appuntamenti', 'de' => 'Reservierungen und Termine', 'en' => 'Bookings and appointments'],
                        'verkauf'   => ['it' => 'Vendere online', 'de' => 'Online verkaufen', 'en' => 'Sell online'],
                        'gefunden'  => ['it' => 'Farsi trovare su Google', 'de' => 'Bei Google gefunden werden', 'en' => 'Be found on Google'],
                        'serioes'    => ['it' => 'Fare bella figura', 'de' => 'Seriös wirken', 'en' => 'Look credible'],
                        'besuch'    => ['it' => 'Far venire la gente da voi', 'de' => 'Leute zu euch holen', 'en' => 'Get people to come by'],
                        'bewerber'  => ['it' => 'Trovare collaboratori', 'de' => 'Bewerber finden', 'en' => 'Find staff'],
                        'nichts'    => ['it' => 'Nient’altro, conta solo il primo', 'de' => 'Nichts weiter, nur das erste zählt', 'en' => 'Nothing else, only the first counts'],
                    ],
                ],

                /* Die nuetzlichste Frage im ganzen Bogen. Eine Zeile, und sie
                   entscheidet, was im ersten Bildschirm steht. */
                'einesache' => ['it' => 'Se un visitatore ricorda una cosa sola di voi — quale deve essere?',
                                'de' => 'Wenn ein Besucher nur eine Sache über euch mitnimmt — welche?',
                                'en' => 'If a visitor remembers one thing about you — which one?',
                                'art' => 'text'],

                /* Sagt mir, ob ein Formular ueberhaupt Sinn hat oder ob nur
                   die Telefonnummer gross genug sein muss. */
                'heute' => ['it' => 'Oggi, quando qualcuno vi telefona o scrive: cosa succede?',
                            'de' => 'Was passiert heute, wenn jemand euch anruft oder schreibt?',
                            'en' => 'Today, when someone calls or writes: what happens?',
                            'art' => 'lang'],

                'mitbewerber' => ['it' => 'Due o tre concorrenti della zona, con il sito se ce l’hanno',
                                  'de' => 'Zwei, drei Mitbewerber aus der Gegend, mit Website falls vorhanden',
                                  'en' => 'Two or three local competitors, with their site if they have one',
                                  'art' => 'lang'],

                /* Vorbelegt aus Branche und Ort. Korrigieren koennen alle,
                   erfinden fast niemand -- vorher stand hier "gute Pizza". */
                'suchwoerter' => ['it' => 'Con quali parole dovrebbero trovarvi su Google? Correggi la proposta.',
                                  'de' => 'Mit welchen Wörtern sollen Leute euch bei Google finden? Ändere den Vorschlag.',
                                  'en' => 'Which words should people find you by on Google? Edit the suggestion.',
                                  'art' => 'lang', 'vorschlag' => 'suchwoerter'],
            ],
        ],

        /* ---------- 3 ---------------------------------------------------- */
        'website' => [
            'it' => 'Dimensione del sito', 'de' => 'Umfang der Website', 'en' => 'Size of the site',
            'felder' => [
                'seiten_zahl'   => ['it' => 'Quante pagine in tutto', 'de' => 'Wie viele Seiten insgesamt', 'en' => 'How many pages in total', 'art' => 'zahl'],
                'sprachen_zahl' => ['it' => 'In quante lingue', 'de' => 'In wie vielen Sprachen', 'en' => 'In how many languages', 'art' => 'zahl'],

                'sprachen_welche' => [
                    'it' => 'Quali lingue?', 'de' => 'Welche Sprachen?', 'en' => 'Which languages?',
                    'art' => 'mehr', 'frei' => true,
                    'optionen' => [
                        'it' => ['it' => 'Italiano', 'de' => 'Italienisch', 'en' => 'Italian'],
                        'de' => ['it' => 'Tedesco', 'de' => 'Deutsch', 'en' => 'German'],
                        'en' => ['it' => 'Inglese', 'de' => 'Englisch', 'en' => 'English'],
                        'fr' => ['it' => 'Francese', 'de' => 'Französisch', 'en' => 'French'],
                        'es' => ['it' => 'Spagnolo', 'de' => 'Spanisch', 'en' => 'Spanish'],
                    ],
                ],
                'sprache_erst' => [
                    'it' => 'Quale deve apparire per prima?', 'de' => 'Welche soll zuerst erscheinen?', 'en' => 'Which should come first?',
                    'art' => 'eins',
                    'optionen' => [
                        'it' => ['it' => 'Italiano', 'de' => 'Italienisch', 'en' => 'Italian'],
                        'de' => ['it' => 'Tedesco', 'de' => 'Deutsch', 'en' => 'German'],
                        'en' => ['it' => 'Inglese', 'de' => 'Englisch', 'en' => 'English'],
                        'fr' => ['it' => 'Francese', 'de' => 'Französisch', 'en' => 'French'],
                        'es' => ['it' => 'Spagnolo', 'de' => 'Spanisch', 'en' => 'Spanish'],
                    ],
                ],

                'funktionen_wahl' => ['it' => 'Che cosa deve avere il sito', 'de' => 'Was die Website können soll', 'en' => 'What the site should have', 'art' => 'wahl'],

                'seiten' => ['it' => 'Come si chiamano le pagine? Cambia pure la proposta.',
                             'de' => 'Wie sollen die Seiten heißen? Ändere den Vorschlag ruhig.',
                             'en' => 'What should the pages be called? Change the suggestion freely.',
                             'art' => 'lang', 'vorschlag' => 'seiten'],

                /* Die Torfrage. Vorher standen die beiden Fragen zur alten
                   Seite immer da -- auch bei Kunden, die noch nie eine
                   hatten. Zwei leere Kaesten, die sagen: Hier ist etwas,
                   das du nicht beantwortest. */
                'altseite' => [
                    'it' => 'Avete già un sito?', 'de' => 'Gibt es schon eine Website?', 'en' => 'Is there a website already?',
                    'art' => 'eins', 'frei' => true,
                    'optionen' => [
                        'nein'   => ['it' => 'No, questo è il primo', 'de' => 'Nein, das ist die erste', 'en' => 'No, this is the first'],
                        'ja'     => ['it' => 'Sì, è online — indirizzo qui sotto', 'de' => 'Ja, sie ist online — Adresse unten', 'en' => 'Yes, it is online — address below'],
                        'aufbau' => ['it' => 'C’è qualcosa, ma incompleto', 'de' => 'Es gibt etwas, aber unfertig', 'en' => 'There is something, but unfinished'],
                        'social' => ['it' => 'Solo una pagina Facebook o Instagram', 'de' => 'Nur eine Facebook- oder Instagram-Seite', 'en' => 'Only a Facebook or Instagram page'],
                    ],
                ],
                'erhalten' => ['it' => 'Del sito attuale: che cosa deve assolutamente restare?',
                               'de' => 'Von der jetzigen Seite: Was muss unbedingt erhalten bleiben?',
                               'en' => 'From the current site: what has to stay, no matter what?',
                               'art' => 'lang', 'wenn' => ['feld' => 'altseite', 'ist' => ['ja', 'aufbau']]],
                'stoert'   => ['it' => 'Del sito attuale: che cosa vi dà più fastidio?',
                               'de' => 'An der jetzigen Seite: Was stört euch am meisten?',
                               'en' => 'About the current site: what bothers you most?',
                               'art' => 'lang', 'wenn' => ['feld' => 'altseite', 'ist' => ['ja', 'aufbau']]],
            ],
        ],

        /* ---------- 4 ---------------------------------------------------- */
        'design' => [
            'it' => 'Aspetto', 'de' => 'Gestaltung', 'en' => 'Design',
            'felder' => [
                /* Benannte Richtungen statt Adjektivsuche. Jede davon ist
                   eine Design-DNA, mit der sich arbeiten laesst; "modern"
                   ist keine. */
                'stil' => [
                    'it' => 'Che direzione?', 'de' => 'Welche Richtung?', 'en' => 'Which direction?',
                    'art' => 'eins', 'frei' => true,
                    'optionen' => [
                        'ruhig'    => ['it' => 'Sobrio e chiaro — molto bianco, poco rumore', 'de' => 'Ruhig und klar — viel Weiß, wenig Lärm', 'en' => 'Calm and clear — lots of white, little noise'],
                        'warm'     => ['it' => 'Caldo e accogliente', 'de' => 'Warm und einladend', 'en' => 'Warm and welcoming'],
                        'edel'     => ['it' => 'Elegante e discreto', 'de' => 'Edel und zurückhaltend', 'en' => 'Elegant and restrained'],
                        'kraeftig' => ['it' => 'Deciso e moderno — colori forti', 'de' => 'Kräftig und modern — starke Farben', 'en' => 'Bold and modern — strong colours'],
                        'boden'    => ['it' => 'Artigianale e concreto', 'de' => 'Handwerklich und bodenständig', 'en' => 'Crafted and down to earth'],
                        'verspielt'=> ['it' => 'Vivace, con un po’ di gioco', 'de' => 'Verspielt, mit etwas Spaß', 'en' => 'Playful, with some fun'],
                        'weissnicht'=> ['it' => 'Non lo so — decidi tu', 'de' => 'Weiß ich nicht — entscheide du', 'en' => 'I don’t know — you decide'],
                    ],
                ],

                'farben' => [
                    'it' => 'Colori: cosa vi piace?', 'de' => 'Farben: Was gefällt euch?', 'en' => 'Colours: what do you like?',
                    'art' => 'mehr', 'frei' => true,
                    'optionen' => [
                        'wielogo'  => ['it' => 'Come il nostro logo', 'de' => 'Wie unser Logo', 'en' => 'Like our logo'],
                        'blau'     => ['it' => 'Blu', 'de' => 'Blau', 'en' => 'Blue'],
                        'gruen'    => ['it' => 'Verde', 'de' => 'Grün', 'en' => 'Green'],
                        'rot'      => ['it' => 'Rosso', 'de' => 'Rot', 'en' => 'Red'],
                        'orange'   => ['it' => 'Arancione', 'de' => 'Orange', 'en' => 'Orange'],
                        'gelb'     => ['it' => 'Giallo', 'de' => 'Gelb', 'en' => 'Yellow'],
                        'erde'     => ['it' => 'Terra, sabbia, beige', 'de' => 'Erdtöne, Sand, Beige', 'en' => 'Earth, sand, beige'],
                        'schwarz'  => ['it' => 'Nero e bianco', 'de' => 'Schwarz und Weiß', 'en' => 'Black and white'],
                        'weissnicht'=> ['it' => 'Non lo so — decidi tu', 'de' => 'Weiß ich nicht — entscheide du', 'en' => 'I don’t know — you decide'],
                    ],
                ],

                'wirkung' => [
                    'it' => 'Come deve sentirsi chi apre il sito? Scegli fino a tre.',
                    'de' => 'Wie soll sich anfühlen, wer die Seite öffnet? Bis zu drei.',
                    'en' => 'How should it feel to open the site? Up to three.',
                    'art' => 'mehr', 'frei' => true,
                    'optionen' => [
                        'vertrauen'  => ['it' => 'Affidabile', 'de' => 'Vertrauenswürdig', 'en' => 'Trustworthy'],
                        'hochwertig' => ['it' => 'Di qualità', 'de' => 'Hochwertig', 'en' => 'High quality'],
                        'freundlich' => ['it' => 'Accogliente', 'de' => 'Freundlich', 'en' => 'Friendly'],
                        'modern'     => ['it' => 'Moderno', 'de' => 'Modern', 'en' => 'Modern'],
                        'ruhig'      => ['it' => 'Tranquillo', 'de' => 'Ruhig', 'en' => 'Calm'],
                        'lebendig'   => ['it' => 'Vivo', 'de' => 'Lebendig', 'en' => 'Lively'],
                        'echt'       => ['it' => 'Autentico', 'de' => 'Echt', 'en' => 'Authentic'],
                        'einfach'    => ['it' => 'Semplice da usare', 'de' => 'Einfach zu benutzen', 'en' => 'Easy to use'],
                        'erfahren'   => ['it' => 'Esperto, con esperienza', 'de' => 'Erfahren', 'en' => 'Experienced'],
                    ],
                ],

                /* Die meisten Kunden haben zu Schriften keine Meinung und
                   schrieben trotzdem etwas hin. Jetzt ist die ehrliche
                   Antwort die erste. */
                'schriften' => [
                    'it' => 'Caratteri', 'de' => 'Schriften', 'en' => 'Fonts',
                    'art' => 'eins', 'frei' => true,
                    'optionen' => [
                        'egal'    => ['it' => 'Nessuna preferenza — decidi tu', 'de' => 'Keine Wünsche — entscheide du', 'en' => 'No preference — you decide'],
                        'wielogo' => ['it' => 'Come nel logo', 'de' => 'Wie im Logo', 'en' => 'Like the logo'],
                        'haus'    => ['it' => 'Abbiamo un carattere aziendale — lo scrivo qui sotto', 'de' => 'Wir haben eine Hausschrift — schreibe ich unten', 'en' => 'We have a house font — I’ll write it below'],
                    ],
                ],

                /* Vektor oder Bild entscheidet, ob das Logo neu gebaut werden
                   muss. Vorher stand hier "ja". */
                'logo' => [
                    'it' => 'Logo', 'de' => 'Logo', 'en' => 'Logo',
                    'art' => 'eins', 'frei' => true,
                    'optionen' => [
                        'vektor'   => ['it' => 'Sì, come file vettoriale (ai, eps, svg, pdf)', 'de' => 'Ja, als Vektordatei (ai, eps, svg, pdf)', 'en' => 'Yes, as a vector file (ai, eps, svg, pdf)'],
                        'bild'     => ['it' => 'Sì, ma solo come immagine (jpg, png)', 'de' => 'Ja, aber nur als Bild (jpg, png)', 'en' => 'Yes, but only as an image (jpg, png)'],
                        'neu'      => ['it' => 'No, ci serve', 'de' => 'Nein, wir brauchen eins', 'en' => 'No, we need one'],
                        'ueber'    => ['it' => 'C’è, ma andrebbe rifatto', 'de' => 'Es gibt eins, sollte aber überarbeitet werden', 'en' => 'There is one, but it should be reworked'],
                        'weissnicht'=> ['it' => 'Non so quale file abbiamo', 'de' => 'Ich weiß nicht, welche Datei wir haben', 'en' => 'I don’t know which file we have'],
                    ],
                ],

                'vorbilder' => ['it' => 'Siti che vi piacciono — anche di altri settori',
                                'de' => 'Websites, die euch gefallen — auch aus anderen Branchen',
                                'en' => 'Websites you like — from any industry',
                                'art' => 'lang'],

                /* Muss frei bleiben. Die wichtigste Frage der Gestaltung ist
                   die nach dem Nein. */
                'abneigung' => ['it' => 'Che cosa non deve esserci in nessun caso?',
                                'de' => 'Was soll auf keinen Fall vorkommen?',
                                'en' => 'What should never appear?',
                                'art' => 'lang'],
            ],
        ],

        /* ---------- 5 ---------------------------------------------------- */
        'material' => [
            'it' => 'Materiale e testi', 'de' => 'Material und Texte', 'en' => 'Material and copy',
            'felder' => [
                /* Eine Liste statt dreier Textkaesten -- und je Zeile die
                   einzige Auskunft, die zaehlt: habe ich es, kommt es noch,
                   oder muss ich es machen. Genau danach plane ich. */
                'material' => [
                    'it' => 'Che cosa avete già?', 'de' => 'Was habt ihr schon?', 'en' => 'What do you already have?',
                    'art' => 'stand',
                    'zeilen' => [
                        'logo'      => ['it' => 'Logo', 'de' => 'Logo', 'en' => 'Logo'],
                        'betrieb'   => ['it' => 'Foto dei locali', 'de' => 'Fotos vom Betrieb', 'en' => 'Photos of the premises'],
                        'produkt'   => ['it' => 'Foto di prodotti o lavori', 'de' => 'Fotos von Produkten oder Arbeiten', 'en' => 'Photos of products or work'],
                        'team'      => ['it' => 'Foto del team', 'de' => 'Team- oder Personenfotos', 'en' => 'Team or people photos'],
                        'video'     => ['it' => 'Video', 'de' => 'Video', 'en' => 'Video'],
                        'texte'     => ['it' => 'Testi su di voi', 'de' => 'Texte über euch', 'en' => 'Copy about you'],
                        'preise'    => ['it' => 'Menù o listino prezzi', 'de' => 'Speisekarte oder Preisliste', 'en' => 'Menu or price list'],
                        'zeiten'    => ['it' => 'Orari di apertura', 'de' => 'Öffnungszeiten', 'en' => 'Opening hours'],
                        'stimmen'   => ['it' => 'Recensioni di clienti', 'de' => 'Kundenstimmen', 'en' => 'Customer reviews'],
                    ],
                ],

                'texte' => [
                    'it' => 'I testi del sito', 'de' => 'Die Texte der Website', 'en' => 'The copy for the site',
                    'art' => 'eins',
                    'optionen' => [
                        'selbst' => ['it' => 'Li scriviamo noi', 'de' => 'Schreiben wir selbst', 'en' => 'We write them'],
                        'teils'  => ['it' => 'In parte noi, in parte tu', 'de' => 'Teils wir, teils du', 'en' => 'Partly us, partly you'],
                        'du'     => ['it' => 'Scrivili tu', 'de' => 'Bitte schreibst du sie', 'en' => 'Please write them'],
                    ],
                ],

                /* Bleibt drin, weil es eine echte Haftungsfrage ist. */
                'bildrechte' => [
                    'it' => 'Le foto si possono pubblicare?', 'de' => 'Dürfen die Fotos veröffentlicht werden?', 'en' => 'May the photos be published?',
                    'art' => 'eins', 'frei' => true,
                    'optionen' => [
                        'ja'      => ['it' => 'Sì, sono nostre', 'de' => 'Ja, es sind unsere', 'en' => 'Yes, they are ours'],
                        'fotograf'=> ['it' => 'Le ha fatte un fotografo — dobbiamo chiedere', 'de' => 'Ein Fotograf hat sie gemacht — wir müssen fragen', 'en' => 'A photographer took them — we need to ask'],
                        'personen'=> ['it' => 'Sì, ma ci sono persone riconoscibili', 'de' => 'Ja, aber es sind Personen erkennbar', 'en' => 'Yes, but people are recognisable'],
                        'unsicher'=> ['it' => 'Non ne siamo sicuri', 'de' => 'Sind wir uns nicht sicher', 'en' => 'We are not sure'],
                        'keine'   => ['it' => 'Non abbiamo foto', 'de' => 'Wir haben keine Fotos', 'en' => 'We have no photos'],
                    ],
                ],

                'anrede' => [
                    'it' => 'Come vi rivolgete ai clienti?', 'de' => 'Wie sprecht ihr eure Kunden an?', 'en' => 'How do you address customers?',
                    'art' => 'eins',
                    'optionen' => [
                        'sie'  => ['it' => 'Con il Lei — formale', 'de' => 'Mit Sie — förmlich', 'en' => 'Formally'],
                        'du'   => ['it' => 'Con il tu — alla mano', 'de' => 'Mit Du — locker', 'en' => 'Informally'],
                        'egal' => ['it' => 'Come è normale nel settore', 'de' => 'Wie in der Branche üblich', 'en' => 'However is usual in the trade'],
                    ],
                ],
                'klang' => [
                    'it' => 'Come devono suonare i testi?', 'de' => 'Wie sollen die Texte klingen?', 'en' => 'How should the copy sound?',
                    'art' => 'eins', 'frei' => true,
                    'optionen' => [
                        'sachlich' => ['it' => 'Sobri e precisi', 'de' => 'Sachlich und genau', 'en' => 'Matter-of-fact and precise'],
                        'herzlich' => ['it' => 'Caldi e vicini', 'de' => 'Herzlich und nah', 'en' => 'Warm and close'],
                        'sicher'   => ['it' => 'Sicuri di sé', 'de' => 'Selbstbewusst', 'en' => 'Confident'],
                        'humor'    => ['it' => 'Con un po’ di ironia', 'de' => 'Mit etwas Humor', 'en' => 'With some humour'],
                        'kurz'     => ['it' => 'Il più brevi possibile', 'de' => 'So kurz wie möglich', 'en' => 'As short as possible'],
                    ],
                ],

                'social' => ['it' => 'Profili social — Instagram, Facebook, altro',
                             'de' => 'Social-Media-Profile — Instagram, Facebook, weitere',
                             'en' => 'Social profiles — Instagram, Facebook, others',
                             'art' => 'text'],
            ],
        ],

        /* ---------- 6 ---------------------------------------------------- */
        'formales' => [
            'it' => 'Contatti, indirizzo e scadenza', 'de' => 'Kontakt, Adresse und Termin', 'en' => 'Contact, address and timing',
            'felder' => [
                'telefon' => ['it' => 'Telefono per il sito (e WhatsApp, se diverso)',
                              'de' => 'Telefon für die Website (und WhatsApp, falls anders)',
                              'en' => 'Phone for the site (and WhatsApp, if different)',
                              'art' => 'text'],
                'email_web' => ['it' => 'E-mail per il sito', 'de' => 'E-Mail für die Website', 'en' => 'Email for the site', 'art' => 'text'],

                /* Oeffnungszeiten landen auf der Seite und in Google. Als
                   Textkasten kamen sie in fuenf verschiedenen Formen. */
                'zeiten' => [
                    'it' => 'Orari', 'de' => 'Öffnungszeiten', 'en' => 'Opening hours',
                    'art' => 'eins', 'frei' => true,
                    'optionen' => [
                        'durch'   => ['it' => 'Orario continuato — scrivo gli orari qui sotto', 'de' => 'Durchgehend — Zeiten schreibe ich unten', 'en' => 'Straight through — I’ll write the hours below'],
                        'pause'   => ['it' => 'Con pausa pranzo — scrivo gli orari qui sotto', 'de' => 'Mit Mittagspause — Zeiten schreibe ich unten', 'en' => 'With a midday break — hours below'],
                        'termin'  => ['it' => 'Solo su appuntamento', 'de' => 'Nur nach Vereinbarung', 'en' => 'By appointment only'],
                        'wechsel' => ['it' => 'Cambiano con la stagione', 'de' => 'Wechseln mit der Saison', 'en' => 'They change with the season'],
                        'keine'   => ['it' => 'Non servono sul sito', 'de' => 'Brauchen wir auf der Seite nicht', 'en' => 'Not needed on the site'],
                    ],
                ],

                'domain' => [
                    'it' => 'L’indirizzo del sito (dominio)', 'de' => 'Die Adresse der Website (Domain)', 'en' => 'The website address (domain)',
                    'art' => 'eins', 'frei' => true,
                    'optionen' => [
                        'uns'      => ['it' => 'Ce l’abbiamo, è intestato a noi', 'de' => 'Haben wir, läuft auf uns', 'en' => 'We have one, registered to us'],
                        'fremd'    => ['it' => 'Ce l’abbiamo, ma è di un’agenzia o di un conoscente', 'de' => 'Haben wir, liegt aber bei einer Agentur oder einem Bekannten', 'en' => 'We have one, but an agency or acquaintance holds it'],
                        'neu'      => ['it' => 'Non ce l’abbiamo — scrivo qui sotto quale vorremmo', 'de' => 'Haben wir nicht — Wunschadresse schreibe ich unten', 'en' => 'We have none — I’ll write the one we’d like below'],
                        'weissnicht'=> ['it' => 'Non lo so', 'de' => 'Weiß ich nicht', 'en' => 'I don’t know'],
                    ],
                ],

                'karte' => [
                    'it' => 'Scheda Google dell’attività', 'de' => 'Google-Unternehmenseintrag', 'en' => 'Google Business listing',
                    'art' => 'eins', 'frei' => true,
                    'optionen' => [
                        'ja'       => ['it' => 'C’è — metto il link qui sotto', 'de' => 'Gibt es — Link schreibe ich unten', 'en' => 'There is one — link below'],
                        'nein'     => ['it' => 'Non c’è ancora', 'de' => 'Gibt es noch nicht', 'en' => 'Not yet'],
                        'nichtnoetig'=> ['it' => 'Non ci serve una mappa sul sito', 'de' => 'Wir brauchen keine Karte auf der Seite', 'en' => 'We don’t need a map on the site'],
                        'weissnicht'=> ['it' => 'Non lo so', 'de' => 'Weiß ich nicht', 'en' => 'I don’t know'],
                    ],
                ],

                /* Aendert die ganze Planung und stand bisher nirgends. */
                'termin' => [
                    'it' => 'C’è una data entro cui il sito deve essere online?',
                    'de' => 'Gibt es ein Datum, zu dem die Seite stehen muss?',
                    'en' => 'Is there a date by which the site has to be live?',
                    'art' => 'eins', 'frei' => true,
                    'optionen' => [
                        'keins'  => ['it' => 'Nessuna data fissa', 'de' => 'Kein festes Datum', 'en' => 'No fixed date'],
                        'saison' => ['it' => 'Prima della stagione — data qui sotto', 'de' => 'Vor der Saison — Datum unten', 'en' => 'Before the season — date below'],
                        'anlass' => ['it' => 'Per un’apertura, una fiera, un evento — data qui sotto', 'de' => 'Zu einer Eröffnung, Messe, Veranstaltung — Datum unten', 'en' => 'For an opening, a fair, an event — date below'],
                        'baldest'=> ['it' => 'Il prima possibile', 'de' => 'So bald wie möglich', 'en' => 'As soon as possible'],
                    ],
                ],

                /* Die Betreuungsfrage, ohne dass ich sie stellen muss. */
                'pflege' => [
                    'it' => 'Chi aggiorna il sito dopo?', 'de' => 'Wer pflegt die Seite später?', 'en' => 'Who keeps the site up to date later?',
                    'art' => 'eins',
                    'optionen' => [
                        'ich'    => ['it' => 'Lo faccio io stesso', 'de' => 'Ich selbst', 'en' => 'I do it myself'],
                        'intern' => ['it' => 'Qualcuno da noi', 'de' => 'Jemand bei uns im Betrieb', 'en' => 'Someone in the business'],
                        'du'     => ['it' => 'Preferirei affidarlo a te', 'de' => 'Am liebsten du', 'en' => 'I’d rather you did'],
                        'offen'  => ['it' => 'Ancora da decidere', 'de' => 'Noch offen', 'en' => 'Still open'],
                    ],
                ],

                'impressum' => ['it' => 'Dati per le note legali: ragione sociale esatta, indirizzo, P. IVA o codice fiscale',
                                'de' => 'Angaben fürs Impressum: genaue Firmierung, Anschrift, Steuernummer oder USt-IdNr.',
                                'en' => 'Details for the legal notice: exact company name, registered address, company and VAT number',
                                'art' => 'lang'],

                'sonstiges' => ['it' => 'Altro che dovrei sapere', 'de' => 'Sonst noch etwas, das ich wissen sollte', 'en' => 'Anything else I should know', 'art' => 'lang'],
            ],
        ],
    ];

    public const SEITE = [
        'titel'      => ['it' => 'Il tuo progetto', 'de' => 'Dein Projekt', 'en' => 'Your project'],
        'lead'       => ['it' => 'Sei passi brevi, circa dieci minuti. Quasi tutto è da spuntare — scrivi solo dove serve davvero.',
                         'de' => 'Sechs kurze Schritte, etwa zehn Minuten. Das meiste ist Anklicken — schreiben musst du nur, wo es wirklich zählt.',
                         'en' => 'Six short steps, about ten minutes. Most of it is tapping — you only write where it really counts.'],
        'freiZeile'  => ['it' => 'Vuoi aggiungere qualcosa? (facoltativo)',
                         'de' => 'Etwas dazu sagen? (freiwillig)',
                         'en' => 'Anything to add? (optional)'],
        'schonGesagt'=> ['it' => 'Alcune risposte sono già compilate: le hai date quando hai calcolato il prezzo. Correggile pure se nel frattempo è cambiato qualcosa.',
                         'de' => 'Ein paar Antworten stehen schon drin — die hast du gegeben, als du den Preis ausgerechnet hast. Ändere sie ruhig, wenn sich etwas geändert hat.',
                         'en' => 'A few answers are already filled in — you gave them when you worked out the price. Change them if anything has moved on.'],
        'speichern'  => ['it' => 'Salva e continua dopo', 'de' => 'Zwischenspeichern', 'en' => 'Save for later'],
        'absenden'   => ['it' => 'Invia definitivamente', 'de' => 'Endgültig absenden', 'en' => 'Send'],
        'gespeichert'=> ['it' => 'Salvato. Puoi tornare quando vuoi con lo stesso link.',
                         'de' => 'Gespeichert. Du kannst mit demselben Link jederzeit zurückkommen.',
                         'en' => 'Saved. Come back any time with the same link.'],
        'danke'      => ['it' => 'Grazie! Ho ricevuto tutto e mi metto al lavoro.',
                         'de' => 'Danke! Ich habe alles bekommen und lege los.',
                         'en' => 'Thank you! I have everything and I’m getting started.'],
        'weg'        => ['it' => 'Questo link non è più valido.', 'de' => 'Dieser Link gilt nicht mehr.', 'en' => 'This link is no longer valid.'],
        'schon'      => ['it' => 'Hai già inviato le informazioni. Grazie!', 'de' => 'Du hast die Angaben schon abgeschickt. Danke!', 'en' => 'You have already sent your answers. Thank you!'],
        'pflicht'    => ['it' => 'Compila almeno il nome dell’azienda.', 'de' => 'Bitte trag mindestens den Firmennamen ein.', 'en' => 'Please enter at least the company name.'],
        'panne'      => ['it' => 'Qualcosa non ha funzionato. Riprova tra poco — oppure scrivimi e me ne occupo io.',
                         'de' => 'Da hat etwas nicht geklappt. Versuch es gleich noch einmal — oder schreib mir, dann kümmere ich mich.',
                         'en' => 'Something went wrong. Please try again shortly — or write to me and I’ll sort it out.'],

        /* Der Fragebogen laeuft in Abschnitten. Vier kurze Seiten statt einer
           langen — und zwischen den Seiten wird gespeichert, ohne dass der
           Kunde daran denken muss. */
        'schritt'    => ['it' => 'Passo {n} di {g}', 'de' => 'Schritt {n} von {g}', 'en' => 'Step {n} of {g}'],
        'weiter'     => ['it' => 'Avanti', 'de' => 'Weiter', 'en' => 'Continue'],
        'zurueck'    => ['it' => 'Indietro', 'de' => 'Zurück', 'en' => 'Back'],
        'letzter'    => ['it' => 'Ultimo passo — poi hai finito.', 'de' => 'Letzter Schritt — dann bist du durch.', 'en' => 'Last step — then you’re done.'],
        'leerOk'     => ['it' => 'Quello che non sai, lascialo pure vuoto.',
                         'de' => 'Was du nicht weißt, lass einfach leer.',
                         'en' => 'Leave anything you don’t know blank.'],
        'autoOk'     => ['it' => 'Salvo automaticamente a ogni passo. Puoi chiudere e tornare quando vuoi.',
                         'de' => 'Ich speichere bei jedem Schritt automatisch. Du kannst zumachen und später zurückkommen.',
                         'en' => 'I save at every step. You can close this and come back any time.'],
        'weiterMachen' => ['it' => 'Continua il questionario', 'de' => 'Fragebogen weiter ausfüllen', 'en' => 'Continue the questionnaire'],

        /* DIE HAKENLISTE
           ------------------------------------------------------------------
           Angehakt ist, was im Angebot steht. Der Kunde darf daran ruehren --
           er soll sogar. Nur muss dabei in derselben Sekunde klar sein, was
           ein zusaetzlicher Haken bedeutet, sonst entsteht eine Erwartung,
           die spaeter teuer wird.

           Bewusst ohne Betrag: Was etwas kostet, sagt ein Mensch, nachdem er
           es gelesen hat. Eine Zahl, die hier von selbst erscheint, waere
           eine Nachforderung, der niemand zugestimmt hat. */
        'beauftragt'  => ['it' => 'Nel preventivo', 'de' => 'Im Angebot', 'en' => 'In the quote'],
        'wasDrin'     => ['it' => 'Le voci spuntate sono quelle del preventivo che hai accettato. Puoi togliere e aggiungere: quello che aggiungi lo guardo io e ti scrivo.',
                          'de' => 'Angehakt ist, was in deinem angenommenen Angebot steht. Du darfst wegnehmen und dazunehmen — was dazukommt, sehe ich mir an und melde mich dazu.',
                          'en' => 'The ticked items are the ones in the quote you accepted. Feel free to remove or add — anything you add, I’ll look at and come back to you about.'],
        'nichtDrin'   => ['it' => 'Non è ancora nel preventivo — ti scrivo prima di iniziare.',
                          'de' => 'Das ist im Angebot noch nicht enthalten — ich melde mich dazu, bevor ich anfange.',
                          'en' => 'That isn’t in the quote yet — I’ll come back to you before I start.'],
        'wenigerDrin' => ['it' => 'Questo era nel preventivo. Se non ti serve più, dimmelo pure — ne parliamo.',
                          'de' => 'Das stand im Angebot. Wenn du es nicht mehr brauchst, sag ruhig Bescheid — wir reden darüber.',
                          'en' => 'That was in the quote. If you no longer need it, do say — we’ll talk it through.'],
        'seitenHilfe' => ['it' => 'Conta anche la pagina iniziale.',
                          'de' => 'Die Startseite zählt mit.',
                          'en' => 'The home page counts too.'],
    ];

    /** Die Seite, auf der der Kunde seinem Projekt zusieht. */
    public const PROJEKT = [
        'titel'       => ['it' => 'Il tuo progetto', 'de' => 'Dein Projekt', 'en' => 'Your project'],
        'stand'       => ['it' => 'A che punto siamo', 'de' => 'Wo wir stehen', 'en' => 'Where we are'],
        'vorschau'    => ['it' => 'Guarda l’anteprima', 'de' => 'Vorschau ansehen', 'en' => 'View the preview'],
        'fragebogen'  => ['it' => 'Compila il questionario', 'de' => 'Zum Fragebogen', 'en' => 'Fill in the questionnaire'],
        'fragebogenOffen' => ['it' => 'Il questionario è ancora aperto — senza quelle informazioni non possiamo andare avanti.',
                              'de' => 'Der Fragebogen ist noch offen — ohne die Angaben kommen wir nicht weiter.',
                              'en' => 'The questionnaire is still open — we can’t move on without it.'],
        'nachrichten' => ['it' => 'Messaggi', 'de' => 'Nachrichten', 'en' => 'Messages'],
        'schreiben'   => ['it' => 'Scrivi un messaggio', 'de' => 'Nachricht schreiben', 'en' => 'Write a message'],
        'senden'      => ['it' => 'Invia', 'de' => 'Absenden', 'en' => 'Send'],
        'gesendet'    => ['it' => 'Messaggio inviato. Rispondo il prima possibile.',
                          'de' => 'Nachricht ist raus. Ich melde mich so schnell wie möglich.',
                          'en' => 'Message sent. I’ll get back to you as soon as I can.'],
        'nochNichts'  => ['it' => 'Ancora nessun messaggio.', 'de' => 'Noch keine Nachrichten.', 'en' => 'No messages yet.'],
        'du'          => ['it' => 'Tu', 'de' => 'Du', 'en' => 'You'],
        'wir'         => ['it' => 'Vecom Design', 'de' => 'Vecom Design', 'en' => 'Vecom Design'],
        'dateien'     => ['it' => 'File', 'de' => 'Dateien', 'en' => 'Files'],
        'hochladen'   => ['it' => 'Carica file', 'de' => 'Datei hochladen', 'en' => 'Upload a file'],
        'dateiHinweis'=> ['it' => 'Foto, logo, testi, PDF — al massimo {max} per file.',
                          'de' => 'Fotos, Logo, Texte, PDF — höchstens {max} je Datei.',
                          'en' => 'Photos, logo, copy, PDFs — {max} per file at most.'],
        'dateiOk'     => ['it' => 'File ricevuto. Grazie!', 'de' => 'Datei ist da. Danke!', 'en' => 'File received. Thank you!'],
        'keineDateien'=> ['it' => 'Ancora nessun file.', 'de' => 'Noch keine Dateien.', 'en' => 'No files yet.'],
        'vonUns'      => ['it' => 'da me', 'de' => 'von mir', 'en' => 'from me'],
        'vonDir'      => ['it' => 'da te', 'de' => 'von dir', 'en' => 'from you'],
        'leer'        => ['it' => 'Scrivi qualcosa prima di inviare.', 'de' => 'Bitte schreib etwas, bevor du absendest.', 'en' => 'Please write something first.'],
        'belege'      => ['it' => 'Ricevute', 'de' => 'Belege', 'en' => 'Receipts'],
        'schauen'     => ['it' => 'Dai un’occhiata', 'de' => 'Schau es dir an', 'en' => 'Take a look'],
        'schauenText' => ['it' => 'La bozza è visibile. Guardala con calma e scrivimi cosa ne pensi — non devi approvare niente adesso. Quando il sito è finito ti avviso, e solo allora potrai dare il via libera.',
                          'de' => 'Der Entwurf ist für dich freigeschaltet. Schau ihn dir in Ruhe an und schreib mir, was dir auffällt — freigeben musst du noch nichts. Wenn die Seite fertig ist, melde ich mich; erst dann kannst du sie abnehmen.',
                          'en' => 'The draft is open for you. Take your time and tell me what you notice — you don’t have to approve anything yet. When the site is finished I’ll let you know; only then can you sign it off.'],
        'kosten'      => ['it' => 'Le modifiche che rientrano in quanto concordato sono comprese. Se una richiesta va oltre, te lo dico prima e ricevi il preventivo con il prezzo — senza il tuo ok non parte niente.',
                          'de' => 'Änderungen im vereinbarten Umfang sind enthalten. Geht ein Wunsch darüber hinaus, sage ich es dir vorher und schicke dir ein Angebot mit dem Preis — ohne dein Ja passiert nichts.',
                          'en' => 'Changes within the agreed scope are included. If a request goes beyond that, I’ll say so first and send you a quote with the price — nothing happens without your go-ahead.'],
        'freigabe'    => ['it' => 'Il sito è pronto — decidi tu', 'de' => 'Die Seite ist fertig — jetzt entscheidest du', 'en' => 'The site is ready — it’s your call'],
        'freigabeText'=> ['it' => 'Se il sito va bene così, dallo pure per buono — poi lo pubblico. Se qualcosa non va, scrivimelo: lo sistemo.',
                          'de' => 'Wenn die Seite so passt, gib sie frei — dann veröffentliche ich. Wenn etwas nicht stimmt, schreib es mir: ich ändere es.',
                          'en' => 'If the site is right, approve it — then I publish. If something is off, tell me: I’ll change it.'],
        'freigeben'   => ['it' => 'Va bene così — pubblica', 'de' => 'Passt so — veröffentlichen', 'en' => 'Looks good — publish it'],
        'aendern'     => ['it' => 'Vorrei delle modifiche', 'de' => 'Ich möchte Änderungen', 'en' => 'I’d like changes'],
        'freigegeben' => ['it' => 'Grazie! Mi metto subito a pubblicare.',
                          'de' => 'Danke! Ich kümmere mich gleich um die Veröffentlichung.',
                          'en' => 'Thank you! I’ll get it published right away.'],
        'aenderungOk' => ['it' => 'Ricevuto. Ci metto mano.', 'de' => 'Angekommen. Ich mache mich dran.', 'en' => 'Got it. I’m on it.'],
        'aendernWie'  => ['it' => 'Scrivi cosa cambiare prima di inviare.',
                          'de' => 'Schreib bitte dazu, was geändert werden soll.',
                          'en' => 'Please write what should change.'],
        'keineBelege' => ['it' => 'Ancora nessuna ricevuta.', 'de' => 'Noch keine Belege.', 'en' => 'No receipts yet.'],
    ];

    /** Die Stufen des Projekts, wie der Kunde sie sieht. */
    /**
     * Die eine Kundenseite (kunde.php).
     *
     * Acht Stufen, in der Sprache des Kunden — nicht in Uwes. "In Arbeit"
     * heisst fuer ihn "Wir bauen", und was fuer Uwe "Angebot" ist, ist fuer
     * den Kunden die Anzahlung. Zu jeder Stufe genau ein Satz, was jetzt
     * dran ist, und ob er selbst etwas tun muss.
     */
    public const KUNDE = [
        'hallo'      => ['it' => 'Ciao {name}', 'de' => 'Hallo {name}', 'en' => 'Hi {name}'],
        /* Auf seiner Seite, damit er die Nummer von seinen Belegen
           wiederfindet, ohne ein PDF aufmachen zu muessen. */
        'kundennr'   => ['it' => 'N. cliente', 'de' => 'Kundennummer', 'en' => 'Customer no.'],
        'titel'      => ['it' => 'Il tuo progetto', 'de' => 'Dein Projekt', 'en' => 'Your project'],
        'duBistDran' => ['it' => 'Tocca a te', 'de' => 'Jetzt bist du dran', 'en' => 'Over to you'],
        'wirSindDran'=> ['it' => 'Ci penso io', 'de' => 'Ich bin dran', 'en' => 'I am on it'],
        'nichtsOffen'=> ['it' => 'Tutto a posto', 'de' => 'Alles erledigt', 'en' => 'All done'],
        'gespraech'  => ['it' => 'Scrivimi', 'de' => 'Schreib mir', 'en' => 'Write to me'],
        'gespraechHilfe' => [
            'it' => 'Qui rispondo io — di solito entro un giorno lavorativo.',
            'de' => 'Hier antworte ich dir — meist innerhalb eines Werktags.',
            'en' => 'I answer here — usually within one working day.'],
        /* "Unterlagen" und "Dateien" standen frueher untereinander und klangen
           gleich. Das eine sind Belege von uns, das andere sein Material. */
        'unterlagen' => ['it' => 'Ricevute e fatture', 'de' => 'Belege und Rechnungen', 'en' => 'Receipts and invoices'],
        'dateien'    => ['it' => 'Il tuo materiale', 'de' => 'Dein Material', 'en' => 'Your material'],
        'dateienHilfe' => [
            'it' => 'Logo, foto, testi — quello che serve per il sito.',
            'de' => 'Logo, Fotos, Texte — alles, was für die Seite gebraucht wird.',
            'en' => 'Logo, photos, copy — whatever the site needs.'],
        'hochladen'  => ['it' => 'Carica', 'de' => 'Hochladen', 'en' => 'Upload'],

        /* DAS MATERIAL WAR DER STILLSTE ENGPASS
           ------------------------------------------------------------------
           Der Kasten dafuer stand zugeklappt ganz unten, zwischen Belegen und
           einem Schlusssatz. Wer nicht danach suchte, fand ihn nie -- und
           schickte seine Fotos per WhatsApp, oder gar nicht. Angefangen
           werden konnte in beiden Faellen nicht, und die Wartezeit sah aus,
           als laege sie bei mir.

           Deshalb sagt die Seite jetzt in der Phase, in der es zaehlt,
           deutlich, dass Material gebraucht wird -- und wo es hingehoert. */
        'materialRuf' => [
            'it' => 'Hai già logo, foto o testi? Caricali qui — con quelli posso partire davvero.',
            'de' => 'Hast du Logo, Fotos oder Texte schon da? Lad sie hier hoch — damit kann ich wirklich anfangen.',
            'en' => 'Do you already have a logo, photos or copy? Upload them here — with those I can really start.'],
        'materialWie' => [
            'it' => 'Va bene tutto: foto dal telefono, un PDF, il vecchio volantino. Meglio troppo che troppo poco — scelgo io.',
            'de' => 'Alles ist recht: Handyfotos, ein PDF, der alte Flyer. Lieber zu viel als zu wenig — aussuchen kann ich.',
            'en' => 'Anything helps: phone photos, a PDF, the old flyer. Better too much than too little — I can pick.'],
        'materialKnopf' => [
            'it' => 'Carica il materiale', 'de' => 'Material hochladen', 'en' => 'Upload your material'],
        'materialDa' => [
            'it' => 'Ricevuto, grazie. Se arriva altro, caricalo pure — meglio adesso che dopo.',
            'de' => 'Angekommen, danke. Wenn noch etwas dazukommt, lad es ruhig hoch — jetzt ist besser als später.',
            'en' => 'Received, thank you. If more turns up, do upload it — sooner is better than later.'],

        'deineSeite' => ['it' => 'Il tuo sito', 'de' => 'Deine Website', 'en' => 'Your website'],
        /* Der Bereich steht auch dann da, wenn es noch nichts zu sehen gibt.
           Versteckt waere er eine Leerstelle, die Fragen erzeugt: Wo sehe ich
           denn nun meine Seite? So weiss der Kunde, wo sie erscheinen wird. */
        'nochNichts'  => ['it' => 'Appena la bozza è pronta, la trovi qui — ti avviso.',
                          'de' => 'Sobald dein Entwurf fertig ist, kannst du ihn hier ansehen — ich sage dir Bescheid.',
                          'en' => 'As soon as your draft is ready you can view it here — I’ll let you know.'],
        'entwurfAnsehen' => ['it' => 'Guarda l’anteprima', 'de' => 'Entwurf ansehen', 'en' => 'View the draft'],
        'seiteAnsehen'   => ['it' => 'Apri il sito', 'de' => 'Website öffnen', 'en' => 'Open the site'],

        /* ANSEHEN UND ABNEHMEN SIND ZWEIERLEI
           ------------------------------------------------------------------
           Frueher stand neben dem Entwurf sofort "Passt so — veroeffentlichen".
           Damit konnte jemand abnehmen, bevor er ueberhaupt geklickt hatte --
           und die Abnahme haengt an der Restzahlung. Jetzt sagt die Seite in
           der Schau-Phase ausdruecklich, dass noch nichts zu entscheiden ist. */
        'nurSchauen' => [
            'it' => 'Guardalo con calma. Non devi approvare niente adesso — il sito non è ancora finito. Scrivimi cosa ne pensi; ti avviso quando è pronto.',
            'de' => 'Schau ihn dir in Ruhe an. Freigeben musst du noch nichts — die Seite ist noch nicht fertig. Schreib mir, was dir auffällt; ich sage dir Bescheid, wenn sie fertig ist.',
            'en' => 'Take your time. You don’t have to approve anything yet — the site isn’t finished. Tell me what you notice; I’ll let you know when it’s ready.'],
        'fertigTitel' => [
            'it' => 'Il sito è pronto — decidi tu',
            'de' => 'Die Seite ist fertig — jetzt entscheidest du',
            'en' => 'The site is ready — it’s your call'],
        'fertigText' => [
            'it' => 'Se va bene così, dai il via libera: da lì pubblico. Se manca ancora qualcosa, scrivimelo.',
            'de' => 'Wenn sie so passt, gib sie frei — dann veröffentliche ich. Wenn noch etwas fehlt, schreib es mir.',
            'en' => 'If it’s right, sign it off — then I publish. If something is still missing, tell me.'],

        /* Der Kostensatz. Er steht bewusst DA, wo entschieden wird, und nicht
           in einer AGB-Zeile: Wer erst mit der Rechnung erfaehrt, dass ein
           Wunsch extra kostete, hat zu Recht schlechte Laune. */
        'aenderungKosten' => [
            'it' => 'Le modifiche che rientrano in quanto concordato sono comprese. Se una richiesta va oltre, te lo dico prima e ricevi il preventivo con il prezzo — senza il tuo ok non parte niente.',
            'de' => 'Änderungen im vereinbarten Umfang sind enthalten. Geht ein Wunsch darüber hinaus, sage ich es dir vorher und schicke dir ein Angebot mit dem Preis — ohne dein Ja passiert nichts.',
            'en' => 'Changes within the agreed scope are included. If a request goes beyond that, I’ll say so first and send you a quote with the price — nothing happens without your go-ahead.'],
        'aenderung'  => ['it' => 'Vorrei una modifica', 'de' => 'Ich möchte etwas ändern', 'en' => 'I’d like a change'],
        'aenderungHilfe' => [
            'it' => 'Scrivi cosa cambiare. Ti dico se rientra nella manutenzione o cosa costa.',
            'de' => 'Schreib, was anders soll. Ich sage dir, ob es zur Betreuung gehört oder was es kostet.',
            'en' => 'Tell me what should change. I’ll say whether it’s covered or what it costs.'],
        /* Die Betreuung ist ein eigener Vertrag. Der Kunde soll ihn sehen —
           und kuendigen koennen, ohne jemandem schreiben zu muessen. Ein
           Vertrag, aus dem man nur per Bittbrief herauskommt, ist keiner. */
        'betreuung'     => ['it' => 'La tua assistenza', 'de' => 'Deine Betreuung', 'en' => 'Your care plan'],
        'betreuungMtl'  => ['it' => 'al mese', 'de' => 'im Monat', 'en' => 'per month'],
        'betreuungSeit' => ['it' => 'Attiva dal {datum}', 'de' => 'Läuft seit {datum}', 'en' => 'Running since {datum}'],
        'betreuungMind' => ['it' => 'Durata minima fino al {datum}', 'de' => 'Mindestlaufzeit bis {datum}',
                            'en' => 'Minimum term until {datum}'],
        'kuendigen'     => ['it' => 'Disdici l’assistenza', 'de' => 'Betreuung kündigen', 'en' => 'Cancel the care plan'],
        'jaKuendigen'   => ['it' => 'Sì, disdico', 'de' => 'Ja, kündigen', 'en' => 'Yes, cancel'],
        'abbrechen'     => ['it' => 'Annulla', 'de' => 'Abbrechen', 'en' => 'Cancel'],
        'kuendigenWann' => ['it' => 'Se disdici adesso, l’assistenza resta attiva fino al {datum} — fino ad allora paghi, dopo no.',
                            'de' => 'Wenn du jetzt kündigst, läuft die Betreuung noch bis zum {datum} — bis dahin zahlst du, danach nicht mehr.',
                            'en' => 'If you cancel now, care runs until {datum} — you pay until then, not after.'],
        'kuendigenSicher' => ['it' => 'Vuoi davvero disdire? Ricevi subito la conferma scritta.',
                              'de' => 'Wirklich kündigen? Du bekommst sofort die schriftliche Bestätigung.',
                              'en' => 'Really cancel? You’ll get the written confirmation straight away.'],
        'gekuendigt'    => ['it' => 'Disdetta ricevuta. L’assistenza resta attiva fino al {datum}. La conferma è nella tua posta.',
                            'de' => 'Kündigung ist angekommen. Die Betreuung läuft bis zum {datum}. Die Bestätigung liegt in deinem Postfach.',
                            'en' => 'Cancellation received. Care runs until {datum}. The confirmation is in your inbox.'],
        'laeuftBis'     => ['it' => 'Disdetta — attiva fino al {datum}', 'de' => 'Gekündigt — läuft bis {datum}',
                            'en' => 'Cancelled — runs until {datum}'],
        'betreuungWeg'  => ['it' => 'L’assistenza è terminata il {datum}. Il sito resta tuo e resta online.',
                            'de' => 'Die Betreuung ist am {datum} ausgelaufen. Die Website bleibt deine und bleibt online.',
                            'en' => 'Care ended on {datum}. The site stays yours and stays online.'],
        /* Die abgerechneten Monate auf der Kundenseite. Ohne sie stand dort
           der Vertrag, aber nicht, was daraus faellig ist — und genau auf
           diese Seite fuehrt der Link in der Zahlungsaufforderung, wenn
           Stripe keinen eigenen erzeugen konnte. Wer dann hier landete, sah
           nichts, was er haette bezahlen koennen. */
        'monate'        => ['it' => 'Mesi fatturati', 'de' => 'Abgerechnete Monate', 'en' => 'Billed months'],
        'monatOffen'    => ['it' => 'Da pagare', 'de' => 'Offen', 'en' => 'Outstanding'],
        'monatBezahlt'  => ['it' => 'Pagato', 'de' => 'Bezahlt', 'en' => 'Paid'],
        'monatZahlen'   => ['it' => 'Paga adesso', 'de' => 'Jetzt bezahlen', 'en' => 'Pay now'],
        'monatFaellig'  => ['it' => 'Scadenza {datum}', 'de' => 'Fällig am {datum}', 'en' => 'Due {datum}'],
        'monatWartet'   => ['it' => 'Ti scrivo io quando è il momento di pagare.',
                            'de' => 'Ich melde mich, wenn sie zu zahlen ist.',
                            'en' => 'I’ll write to you when it’s time to pay.'],
        /* Nach dem Onlinegang: die Bitte um zwei Saetze. Sie steht auf seiner
           Seite, nicht in einer weiteren E-Mail — dort ist er ohnehin, wenn
           er zufrieden nachsieht, wie die Seite laeuft. */
        'stimme'        => ['it' => 'Com’è andata?', 'de' => 'Wie war es?', 'en' => 'How was it?'],
        'stimmeHilfe'   => ['it' => 'Se sei contento, due frasi mi aiutano molto: com’è andata a lavorare insieme e cosa è cambiato per la tua attività. Se qualcosa non è andato, quello mi interessa ancora di più — scrivilo lo stesso.',
                            'de' => 'Wenn du zufrieden bist, helfen mir zwei Sätze sehr: wie die Zusammenarbeit war und was sich für deinen Betrieb geändert hat. Wenn etwas nicht gepasst hat, interessiert mich das noch mehr — schreib es genauso.',
                            'en' => 'If you’re happy, two sentences help me a lot: what the work was like and what changed for your business. If something wasn’t right, I want to hear that even more — write it just the same.'],
        'stimmeFeld'    => ['it' => 'Due frasi bastano.', 'de' => 'Zwei Sätze reichen.', 'en' => 'Two sentences are enough.'],
        'stimmeSterne'  => ['it' => 'Come valuti il lavoro?', 'de' => 'Wie bewertest du die Arbeit?', 'en' => 'How would you rate the work?'],
        'stimmeErlaubnis' => ['it' => 'Puoi pubblicarla sul tuo sito con il mio nome e quello della mia azienda.',
                              'de' => 'Du darfst das auf deiner Website zeigen, mit meinem Namen und meiner Firma.',
                              'en' => 'You may show this on your site, with my name and my company.'],
        'stimmeErlaubnisNein' => ['it' => 'Senza la spunta la leggo solo io — e va benissimo così.',
                                  'de' => 'Ohne Häkchen lese nur ich sie — und das ist völlig in Ordnung.',
                                  'en' => 'Without the tick only I read it — and that’s perfectly fine.'],
        'stimmeSenden'  => ['it' => 'Invia', 'de' => 'Absenden', 'en' => 'Send'],
        'stimmeDanke'   => ['it' => 'Grazie davvero. La leggo con calma — se l’hai autorizzata, la metto sul sito dopo averla vista.',
                            'de' => 'Danke dir wirklich. Ich lese sie in Ruhe — wenn du es erlaubt hast, stelle ich sie danach auf die Website.',
                            'en' => 'Thank you, genuinely. I’ll read it properly — if you allowed it, it goes on the site after I’ve seen it.'],
        'stimmeSchon'   => ['it' => 'Hai già lasciato la tua opinione. Grazie!', 'de' => 'Du hast schon geschrieben. Danke dir!',
                            'en' => 'You’ve already written. Thank you!'],
        'lesenswert' => [
            'it' => 'Questa pagina resta tua. Salvala tra i preferiti — la trovi sempre qui, anche fra mesi.',
            'de' => 'Diese Seite bleibt deine. Leg sie dir als Lesezeichen an — du findest sie hier auch noch in Monaten.',
            'en' => 'This page stays yours. Bookmark it — it will still be here months from now.'],
        'nichtGefunden' => [
            'it' => 'Questo link non è valido. Scrivimi e te ne mando uno nuovo.',
            'de' => 'Dieser Link gilt nicht mehr. Schreib mir kurz, dann schicke ich dir einen neuen.',
            'en' => 'This link is no longer valid. Write to me and I’ll send a new one.'],
    ];

    /**
     * Was auf jeder Stufe dransteht — Ueberschrift, ein Satz, und wer
     * handeln muss. "kunde" heisst: Er selbst. "wir" heisst: Er wartet.
     */
    public const KUNDE_STUFEN = [
        'anfrage'  => ['wer' => 'wir',
            'kurz' => ['it' => 'Richiesta', 'de' => 'Anfrage', 'en' => 'Enquiry'],
            'it' => 'La tua richiesta è arrivata', 'de' => 'Deine Anfrage ist da', 'en' => 'I have your enquiry',
            'text' => ['it' => 'La sto guardando e ti scrivo con una proposta.',
                       'de' => 'Ich sehe sie mir an und melde mich mit einem Vorschlag.',
                       'en' => 'I’m looking at it and will come back with a proposal.']],
        'angebot'  => ['wer' => 'kunde',
            'kurz' => ['it' => 'Acconto', 'de' => 'Anzahlung', 'en' => 'Deposit'],
            'it' => 'Il tuo preventivo', 'de' => 'Dein Angebot steht', 'en' => 'Your quote is ready',
            'text' => ['it' => 'Con l’acconto iniziamo. Il pagamento avviene su una pagina di Stripe.',
                       'de' => 'Mit der Anzahlung fangen wir an. Bezahlt wird auf einer Seite von Stripe.',
                       'en' => 'The deposit gets us started. Payment happens on a Stripe page.']],
        'angaben'  => ['wer' => 'kunde',
            'kurz' => ['it' => 'Dati', 'de' => 'Angaben', 'en' => 'Details'],
            'it' => 'Adesso servono le tue informazioni', 'de' => 'Jetzt brauche ich deine Angaben',
            'en' => 'Now I need your details',
            'text' => ['it' => 'Poche domande sulla tua azienda e sul sito. Puoi interrompere e riprendere.',
                       'de' => 'Ein paar Fragen zu deinem Betrieb und zur Seite. Du kannst zwischendurch aufhören und später weitermachen.',
                       'en' => 'A few questions about your business and the site. You can stop and continue later.']],
        'arbeit'   => ['wer' => 'wir',
            'kurz' => ['it' => 'In corso', 'de' => 'Bau', 'en' => 'Build'],
            'it' => 'Sto costruendo', 'de' => 'Ich baue deine Seite', 'en' => 'I’m building your site',
            'text' => ['it' => 'Ti avviso appena c’è qualcosa da guardare.',
                       'de' => 'Ich melde mich, sobald es etwas zu sehen gibt.',
                       'en' => 'I’ll let you know as soon as there’s something to look at.']],
        'entwurf'  => ['wer' => 'kunde',
            'kurz' => ['it' => 'Anteprima', 'de' => 'Entwurf', 'en' => 'Draft'],
            'it' => 'La tua anteprima è pronta', 'de' => 'Dein Entwurf ist fertig', 'en' => 'Your draft is ready',
            'text' => ['it' => 'Guardala con calma. Va bene così? Scrivimelo. Vuoi cambiare qualcosa? Anche quello.',
                       'de' => 'Sieh sie dir in Ruhe an. Passt sie? Schreib mir. Soll etwas anders? Auch.',
                       'en' => 'Take your time. Happy with it? Tell me. Want changes? Tell me too.']],
        'freigabe' => ['wer' => 'kunde',
            'kurz' => ['it' => 'Saldo', 'de' => 'Restzahlung', 'en' => 'Balance'],
            'it' => 'Manca solo il saldo', 'de' => 'Es fehlt nur noch die Restzahlung',
            'en' => 'Only the balance is left',
            'text' => ['it' => 'Appena arriva, metto il sito online.',
                       'de' => 'Sobald sie da ist, stelle ich die Seite online.',
                       'en' => 'As soon as it arrives, I put the site live.']],
        'online'   => ['wer' => 'niemand',
            'kurz' => ['it' => 'Online', 'de' => 'Online', 'en' => 'Live'],
            'it' => 'Il tuo sito è online', 'de' => 'Deine Website ist online', 'en' => 'Your site is live',
            'text' => ['it' => 'Lo tengo d’occhio io. Se vuoi cambiare qualcosa, scrivi qui sotto.',
                       'de' => 'Ich habe ein Auge darauf. Wenn du etwas ändern willst, schreib es unten.',
                       'en' => 'I keep an eye on it. If you want a change, write below.']],
        'fertig'   => ['wer' => 'niemand',
            'kurz' => ['it' => 'Concluso', 'de' => 'Fertig', 'en' => 'Done'],
            'it' => 'Progetto concluso', 'de' => 'Projekt abgeschlossen', 'en' => 'Project completed',
            'text' => ['it' => 'Grazie. Se ti serve qualcosa, sono qui.',
                       'de' => 'Danke dir. Wenn du etwas brauchst, bin ich da.',
                       'en' => 'Thank you. If you need anything, I’m here.']],
    ];

    public const PROJEKT_STAND = [
        'bestellung_eingegangen' => ['it' => 'Ordine ricevuto', 'de' => 'Bestellung eingegangen', 'en' => 'Order received'],
        'zahlung_bestaetigt'     => ['it' => 'Pagamento confermato', 'de' => 'Zahlung bestätigt', 'en' => 'Payment confirmed'],
        'onboarding'             => ['it' => 'Raccolgo le informazioni', 'de' => 'Ich sammle die Angaben', 'en' => 'Gathering information'],
        'informationen_erhalten' => ['it' => 'Informazioni ricevute', 'de' => 'Informationen erhalten', 'en' => 'Information received'],
        'design'                 => ['it' => 'Progettazione', 'de' => 'Gestaltung', 'en' => 'Design'],
        'entwicklung'            => ['it' => 'Realizzazione', 'de' => 'Umsetzung', 'en' => 'Development'],
        'vorschau'               => ['it' => 'Anteprima pronta', 'de' => 'Vorschau steht', 'en' => 'Preview ready'],
        'kundenfeedback'         => ['it' => 'Aspetto il tuo parere', 'de' => 'Ich warte auf deine Rückmeldung', 'en' => 'Waiting for your feedback'],
        'aenderungen'            => ['it' => 'Modifiche in corso', 'de' => 'Änderungen laufen', 'en' => 'Making changes'],
        'finale_freigabe'        => ['it' => 'Ultima approvazione', 'de' => 'Letzte Freigabe', 'en' => 'Final approval'],
        'veroeffentlichung'      => ['it' => 'Pubblicazione', 'de' => 'Veröffentlichung', 'en' => 'Publishing'],
        'online'                 => ['it' => 'Online', 'de' => 'Online', 'en' => 'Live'],
        'abgeschlossen'          => ['it' => 'Concluso', 'de' => 'Abgeschlossen', 'en' => 'Completed'],
    ];

    /** Betreff und Text je Anlass. {name}, {paket}, {link}, {betrag} werden ersetzt. */
    /* Die Seite vor dem Auftrag. Bewusst knapp: Es gibt noch keinen Stand,
       keine Rechnung und keine Vorschau — nur reden und Unterlagen schicken. */
    public const VORGANG = [
        'titel'      => ['it' => 'La tua richiesta', 'de' => 'Deine Anfrage', 'en' => 'Your enquiry'],
        'lead'       => ['it' => 'Qui seguiamo la conversazione finché non decidiamo insieme. Niente di quanto vedi qui ti impegna.',
                         'de' => 'Hier läuft unser Austausch, bis wir uns einig sind. Nichts davon verpflichtet dich zu etwas.',
                         'en' => 'This is where our conversation runs until we agree. None of it commits you to anything.'],
        'angefragt'  => ['it' => 'Cosa hai chiesto', 'de' => 'Was du angefragt hast', 'en' => 'What you asked for'],
        'paket'      => ['it' => 'Pacchetto scelto', 'de' => 'Gewähltes Paket', 'en' => 'Chosen package'],
        'am'         => ['it' => 'Ricevuta il', 'de' => 'Eingegangen am', 'en' => 'Received on'],
        'unverbind'  => ['it' => 'Gratuita e senza impegno — un incarico nasce solo con il contratto firmato.',
                         'de' => 'Kostenlos und unverbindlich — ein Auftrag entsteht erst mit dem unterschriebenen Vertrag.',
                         'en' => 'Free and without obligation — a project only begins with a signed contract.'],
        'soGehts'    => ['it' => 'Come funziona', 'de' => 'So läuft es', 'en' => 'How it works'],
        'g0'         => ['it' => 'Tutto passa da questa pagina. Nessun account, nessuna password: il link è il tuo accesso, dal telefono come dal computer.',
                         'de' => 'Alles läuft über diese Seite. Kein Konto, kein Passwort: Der Link ist dein Zugang, auf dem Handy wie am Rechner.',
                         'en' => 'Everything runs through this page. No account, no password: the link is your way in, on a phone or a computer.'],
        'g1'         => ['it' => 'Mettila da parte', 'de' => 'Leg sie dir ab', 'en' => 'Keep this page'],
        'g1d'        => ['it' => 'Salva questa pagina tra i preferiti o tieni l’e-mail con il link. Se lo perdi, scrivimi: te ne mando uno nuovo.',
                         'de' => 'Setz ein Lesezeichen oder behalte die E-Mail mit dem Link. Wenn er verloren geht, schreib mir — dann kommt ein neuer.',
                         'en' => 'Bookmark it or keep the email with the link. If it gets lost, write to me and you’ll get a new one.'],
        'g2'         => ['it' => 'Scrivimi qui, non per e-mail', 'de' => 'Schreib mir hier, nicht per E-Mail', 'en' => 'Write here, not by email'],
        'g2d'        => ['it' => 'Così resta tutto in un posto solo e niente si perde. Ogni messaggio mi arriva subito.',
                         'de' => 'So steht alles an einer Stelle und nichts geht unter. Jede Nachricht erreicht mich sofort.',
                         'en' => 'That way everything stays in one place and nothing gets lost. Every message reaches me at once.'],
        'g3'         => ['it' => 'Carica quello che ho bisogno di vedere', 'de' => 'Lade hoch, was ich sehen sollte', 'en' => 'Upload what I should see'],
        'g3d'        => ['it' => 'Logo, foto, testi, il sito vecchio. Scegli il file qui sotto e invia.',
                         'de' => 'Logo, Fotos, Texte, die alte Seite. Datei unten auswählen und senden.',
                         'en' => 'Logo, photos, text, the old site. Pick the file below and send.'],
        'g4'         => ['it' => 'E poi?', 'de' => 'Und dann?', 'en' => 'And then?'],
        'g4d'        => ['it' => 'Ti mando una proposta a prezzo fisso. Se ti convince, ricevi il link per il pagamento — e questa stessa pagina cresce con noi: questionario, bozza, approvazione, messa online.',
                         'de' => 'Ich schicke dir einen Vorschlag zum Festpreis. Passt er, bekommst du den Zahlungslink — und genau diese Seite wächst mit: Fragebogen, Entwurf, Freigabe, Veröffentlichung.',
                         'en' => 'I send you a proposal at a fixed price. If it suits you, you get the payment link — and this same page grows with us: questionnaire, draft, approval, going live.'],
        'nachrichten'=> ['it' => 'Messaggi', 'de' => 'Nachrichten', 'en' => 'Messages'],
        'schreiben'  => ['it' => 'Scrivimi', 'de' => 'Schreib mir', 'en' => 'Write to me'],
        'senden'     => ['it' => 'Invia', 'de' => 'Senden', 'en' => 'Send'],
        'gesendet'   => ['it' => 'Messaggio inviato.', 'de' => 'Nachricht ist raus.', 'en' => 'Message sent.'],
        'nochNichts' => ['it' => 'Ancora nessun messaggio.', 'de' => 'Noch keine Nachricht.', 'en' => 'No messages yet.'],
        'du'         => ['it' => 'Tu', 'de' => 'Du', 'en' => 'You'],
        'wir'        => ['it' => 'Vecom Design', 'de' => 'Vecom Design', 'en' => 'Vecom Design'],
        'dateien'    => ['it' => 'I tuoi documenti', 'de' => 'Deine Unterlagen', 'en' => 'Your files'],
        'hochladen'  => ['it' => 'Carica un file', 'de' => 'Datei hochladen', 'en' => 'Upload a file'],
        'dateiHinweis'=> ['it' => 'Logo, immagini, testi — quello che dovrei vedere. Massimo {max} per file.',
                          'de' => 'Logo, Bilder, Texte — was ich sehen sollte. Höchstens {max} je Datei.',
                          'en' => 'Logo, images, text — whatever I should see. At most {max} per file.'],
        'dateiOk'    => ['it' => 'Ricevuto, grazie.', 'de' => 'Angekommen, danke.', 'en' => 'Received, thank you.'],
        'keineDateien'=> ['it' => 'Ancora nessun documento.', 'de' => 'Noch nichts hochgeladen.', 'en' => 'Nothing uploaded yet.'],
        'weg'        => ['it' => 'Questo link non è più valido. Scrivimi a kontakt@vecom-design.it e te ne mando uno nuovo.',
                         'de' => 'Dieser Link gilt nicht mehr. Schreib an kontakt@vecom-design.it, dann kommt ein neuer.',
                         'en' => 'This link is no longer valid. Write to kontakt@vecom-design.it and you will get a new one.'],
        'panne'      => ['it' => 'Al momento non raggiungibile. Riprova tra poco.',
                         'de' => 'Gerade nicht erreichbar. Versuch es gleich noch einmal.',
                         'en' => 'Not reachable right now. Please try again shortly.'],
    ];

    public const MAILS = [
        /* Die monatliche Betreuung. Kein Verkaufstext: Wer sie hat, hat sie
           bestellt — er will wissen, welcher Monat, wieviel, und wo er zahlt. */
        'betreuung_faellig' => [
            'it' => ['Assistenza {monat} — {betrag}',
                "Ciao {name},\n\nl'assistenza di {monat} è pronta: {betrag}.\n\n"
                . "Puoi pagare qui, entro il {frist}:\n{link}\n\n"
                . "Cosa è compreso: aggiornamenti, backup, controllo del sito e piccole modifiche. "
                . "Se questo mese ti serve qualcosa in particolare, scrivimi.\n\n"
                . "La ricevuta arriva subito dopo il pagamento."],
            'de' => ['Betreuung {monat} — {betrag}',
                "Hallo {name},\n\ndie Betreuung für {monat} steht an: {betrag}.\n\n"
                . "Hier kannst du zahlen, bis zum {frist}:\n{link}\n\n"
                . "Enthalten sind Aktualisierungen, Sicherungen, die Überwachung deiner Seite "
                . "und kleine Änderungen. Wenn diesen Monat etwas Bestimmtes ansteht, schreib mir.\n\n"
                . "Den Beleg bekommst du gleich nach der Zahlung."],
            'en' => ['Care for {monat} — {betrag}',
                "Hello {name},\n\nthe monthly care for {monat} is due: {betrag}.\n\n"
                . "You can pay here, by {frist}:\n{link}\n\n"
                . "It covers updates, backups, monitoring of your site and small changes. "
                . "If something particular is coming up this month, write to me.\n\n"
                . "The receipt follows right after payment."],
        ],
        /* DREI STUFEN, EIN TON, DER SICH AENDERT
           ----------------------------------------------------------------
           Bis hierher passierte bei einer unbezahlten Rate gar nichts. Der
           Zahlungslink starb nach einem Tag, und danach lag der Vorgang still
           da, bis Uwe von selbst hinsah.

           Stufe 1 ist keine Mahnung, sondern ein neuer Link: Die haeufigste
           Ursache ist nicht Unwille, sondern ein Link, der abgelaufen war,
           oder eine Mail, die unterging. Deshalb geht sie von selbst raus und
           klingt wie eine Erinnerung unter Bekannten.

           Stufe 2 nennt die Frist beim Namen und setzt eine neue. Stufe 3
           sagt, was passiert, wenn nichts kommt — und zwar genau das, was
           dann auch passiert, nicht mehr.

           Was hier NICHT steht: eine Zinsrechnung. Der Hinweis auf die
           gesetzliche Regel genuegt; wer sie anwendet, ist Uwe, nicht die
           Vorlage. */
        /* {was} IST EIN NAME, KEIN SATZTEIL
           ------------------------------------------------------------------
           Der Platzhalter traegt die Bezeichnung der Rate: "Anzahlung",
           "Gesamtbetrag", "acconto", "importo totale". Stand davor ein
           Artikel, musste er zu jeder dieser Bezeichnungen passen -- und das
           tat er nicht. Auf Deutsch kam "die Gesamtbetrag" und "die
           vereinbarter Nachtrag" heraus, auf Italienisch in JEDEM Fall ein
           fehlender Artikel ("il pagamento di acconto" statt
           "dell'acconto"), dazu eine Endung, die sich auf nichts bezog.

           Gemerkt haette man es erst an einem Kunden, der eine Mahnung
           bekommt -- also genau dort, wo eine holprige Zeile am teuersten
           ist: Wer um Geld bittet, dessen Brief muss sitzen.

           Deshalb steht die Bezeichnung jetzt hinter einem Gedankenstrich
           und traegt keinen Artikel mehr. Das haelt auch, wenn morgen eine
           neue Rate dazukommt. */
        'zahlung_erinnerung' => [
            'it' => ['Promemoria: {was} — {betrag}',
                "Ciao {name},\n\nti ricordo un pagamento ancora aperto — {was}, {betrag}. Era in scadenza il {faellig}.\n\n"
                . "Probabilmente è solo sfuggito, o il link precedente era scaduto. Eccone uno nuovo, valido due settimane:\n{link}\n\n"
                . "Se hai già pagato, ignora questo messaggio — a volte ci mettiamo un giorno a incrociarci.\n\n"
                . "Se qualcosa non torna, scrivimi e troviamo una soluzione."],
            'de' => ['Erinnerung: {was} — {betrag}',
                "Hallo {name},\n\nkurze Erinnerung an eine offene Zahlung — {was}, {betrag}. Fällig war der {faellig}.\n\n"
                . "Wahrscheinlich ist es nur untergegangen, oder der alte Link war abgelaufen. Hier ist ein neuer, zwei Wochen gültig:\n{link}\n\n"
                . "Wenn du schon bezahlt hast, ist diese Mail hinfällig — manchmal kreuzen wir uns um einen Tag.\n\n"
                . "Wenn etwas nicht passt, schreib mir, dann finden wir einen Weg."],
            'en' => ['Reminder: {was} — {betrag}',
                "Hello {name},\n\na short reminder about the {was}: {betrag}, due on {faellig}.\n\n"
                . "It has probably just slipped through, or the old link had expired. Here is a fresh one, valid for two weeks:\n{link}\n\n"
                . "If you have already paid, please ignore this — sometimes we cross by a day.\n\n"
                . "If something is not right, write to me and we will find a way."],
        ],
        'zahlung_mahnung' => [
            'it' => ['Sollecito di pagamento — {was}, {betrag}',
                "Ciao {name},\n\nresta aperto un pagamento — {was}, {betrag}. Era dovuto il {faellig} e a oggi non risulta arrivato. "
                . "Ti avevo già scritto una volta.\n\n"
                . "Ti chiedo di saldare entro il {frist}:\n{link}\n\n"
                . "Se c'è un motivo — una fattura in sospeso, un mese difficile, qualcosa che non va nel lavoro — "
                . "dimmelo e concordiamo qualcosa. Una rateizzazione è sempre meglio di un silenzio.\n\n"
                . "Riferimento: {vorgang}."],
            'de' => ['Zahlungserinnerung — {was}, {betrag}',
                "Hallo {name},\n\noffen ist noch eine Zahlung — {was}, {betrag}. Fällig war der {faellig}, eingegangen ist bis heute nichts. "
                . "Ich hatte dir dazu schon einmal geschrieben.\n\n"
                . "Ich bitte dich, den Betrag bis zum {frist} zu begleichen:\n{link}\n\n"
                . "Wenn es einen Grund gibt — eine offene Rechnung bei dir, ein schwacher Monat, etwas am Ergebnis, das nicht stimmt — "
                . "sag es mir, dann finden wir eine Lösung. Eine Ratenzahlung ist mir lieber als Schweigen.\n\n"
                . "Vorgang: {vorgang}."],
            'en' => ['Payment reminder — {was}, {betrag}',
                "Hello {name},\n\nthe {was} of {betrag} was due on {faellig} and has not arrived. "
                . "I wrote to you about it once already.\n\n"
                . "Please settle it by {frist}:\n{link}\n\n"
                . "If there is a reason — an unpaid invoice of your own, a weak month, something about the work that is not right — "
                . "tell me and we will find a solution. Paying in instalments beats silence.\n\n"
                . "Reference: {vorgang}."],
        ],
        'zahlung_letzte' => [
            'it' => ['Ultimo sollecito — {was}, {betrag}',
                "Ciao {name},\n\nun importo resta non pagato — {was}, {betrag}, scaduto il {faellig}. Questo è il mio terzo e ultimo messaggio.\n\n"
                . "Ti do tempo fino al {frist}:\n{link}\n\n"
                . "Se entro quella data non arriva nulla, sospendo il lavoro sul tuo sito, che non viene pubblicato "
                . "e i cui diritti d'uso restano miei fino al saldo completo — come previsto dalle condizioni. "
                . "Da quel momento decorrono anche gli interessi di mora di legge.\n\n"
                . "Preferirei di gran lunga sentirti. Una telefonata basta.\n\n"
                . "Riferimento: {vorgang}, cliente {kundennr}."],
            'de' => ['Letzte Mahnung — {was}, {betrag}',
                "Hallo {name},\n\neine Zahlung ist weiterhin offen — {was}, {betrag}, fällig am {faellig}. Das ist meine dritte und letzte Nachricht dazu.\n\n"
                . "Ich setze dir eine Frist bis zum {frist}:\n{link}\n\n"
                . "Kommt bis dahin nichts, ruht die Arbeit an deiner Website. Sie geht nicht online, und die Nutzungsrechte "
                . "bleiben bis zur vollständigen Zahlung bei mir — so steht es in den Bedingungen. Ab dann laufen außerdem "
                . "die gesetzlichen Verzugszinsen.\n\n"
                . "Mir wäre ein Anruf deutlich lieber. Melde dich einfach.\n\n"
                . "Vorgang: {vorgang}, Kunde {kundennr}."],
            'en' => ['Final reminder — {was}, {betrag}',
                "Hello {name},\n\nthe {was} of {betrag}, due on {faellig}, is still outstanding. This is my third and final message about it.\n\n"
                . "I am setting a deadline of {frist}:\n{link}\n\n"
                . "If nothing arrives by then, work on your website stops. It will not go live, and the rights of use stay "
                . "with me until payment in full — as set out in the terms. Statutory late-payment interest also starts from then.\n\n"
                . "I would much rather hear from you. A phone call is enough.\n\n"
                . "Reference: {vorgang}, customer {kundennr}."],
        ],

        /* DIE LETZTE STUFE BEI DER BETREUUNG
           ----------------------------------------------------------------
           Der allgemeine Text droht damit, dass die Website nicht online
           geht und die Nutzungsrechte bei Uwe bleiben. Bei einer monatlichen
           Betreuung stimmt beides nicht: Die Seite steht laengst, bezahlt
           ist sie auch. Was ausbleibt, ist die Pflege — Aktualisierungen,
           Sicherungen, Erreichbarkeit. Genau das sagt dieser Text, und sonst
           nichts. Die Stufe heisst in der Ablage weiter "zahlung_letzte",
           damit der Mahnstand einer Rate an einer Stelle gezaehlt wird. */
        'zahlung_letzte_betreuung' => [
            'it' => ['Ultimo sollecito — assistenza, {betrag}',
                "Ciao {name},\n\nun importo resta non pagato — {was}, {betrag}, scaduto il {faellig}. Questo è il mio terzo e ultimo messaggio.\n\n"
                . "Ti do tempo fino al {frist}:\n{link}\n\n"
                . "Se entro quella data non arriva nulla, sospendo l'assistenza: niente aggiornamenti, "
                . "niente copie di sicurezza, nessun controllo. Il sito resta online e resta tuo — "
                . "quello che si ferma è la manutenzione. Se la situazione non si sblocca, chiudo il "
                . "contratto di assistenza per inadempimento. Da quel momento decorrono anche gli "
                . "interessi di mora di legge.\n\n"
                . "Preferirei di gran lunga sentirti. Una telefonata basta.\n\n"
                . "Riferimento: {vorgang}, cliente {kundennr}."],
            'de' => ['Letzte Mahnung — Betreuung, {betrag}',
                "Hallo {name},\n\neine Zahlung ist weiterhin offen — {was}, {betrag}, fällig am {faellig}. Das ist meine dritte und letzte Nachricht dazu.\n\n"
                . "Ich setze dir eine Frist bis zum {frist}:\n{link}\n\n"
                . "Kommt bis dahin nichts, setze ich die Betreuung aus: keine Aktualisierungen, "
                . "keine Sicherungen, keine Kontrolle. Deine Seite bleibt online und bleibt deine — "
                . "was ruht, ist die Pflege. Bleibt es dabei, kündige ich den Betreuungsvertrag aus "
                . "wichtigem Grund. Ab dann laufen außerdem die gesetzlichen Verzugszinsen.\n\n"
                . "Mir wäre ein Anruf deutlich lieber. Melde dich einfach.\n\n"
                . "Vorgang: {vorgang}, Kunde {kundennr}."],
            'en' => ['Final reminder — care, {betrag}',
                "Hello {name},\n\nthe {was} of {betrag}, due on {faellig}, is still outstanding. This is my third and final message about it.\n\n"
                . "I am setting a deadline of {frist}:\n{link}\n\n"
                . "If nothing arrives by then, I will suspend the care: no updates, no backups, no checks. "
                . "Your site stays online and stays yours — what stops is the maintenance. If it stays that "
                . "way, I will end the care agreement for cause. Statutory late-payment interest also starts "
                . "from then.\n\n"
                . "I would much rather hear from you. A phone call is enough.\n\n"
                . "Reference: {vorgang}, customer {kundennr}."],
        ],
        /* Zu jeder bezahlten Rate ein Beleg — und zwar in der Post, nicht
           nur auf der Kundenseite. Bisher ging eine Nachricht ausschliesslich
           bei der ersten Zahlung raus (in der Auftragsbestaetigung); wer die
           Restzahlung oder einen Nachtrag beglich, hoerte nichts. Das Blatt
           haengt als PDF dran: Ein Dokument, das nur irgendwo zum Abholen
           liegt, erreicht niemanden.

           {wort} ist Beleg oder Rechnung — je nachdem, ob eine
           Umsatzsteuernummer hinterlegt ist. */
        'beleg' => [
            'it' => ['{wort} {nummer} — {betrag}',
                "Ciao {name},\n\nho ricevuto il tuo pagamento: {was}, {betrag}. Grazie.\n\n"
                . "In allegato trovi il documento {nummer} in PDF, da conservare.\n\n"
                . "Tutti i documenti restano anche sulla tua pagina:\n{seite}\n\n"
                . "Se qualcosa non torna, scrivimi e me ne occupo io."],
            'de' => ['{wort} {nummer} — {betrag}',
                "Hallo {name},\n\ndeine Zahlung ist angekommen: {was}, {betrag}. Danke dafür.\n\n"
                . "Im Anhang liegt der {wort} {nummer} als PDF, zum Aufheben.\n\n"
                . "Alle Unterlagen findest du außerdem auf deiner Seite:\n{seite}\n\n"
                . "Wenn etwas nicht stimmt, schreib mir — ich kümmere mich darum."],
            'en' => ['{wort} {nummer} — {betrag}',
                "Hello {name},\n\nyour payment has arrived: {was}, {betrag}. Thank you.\n\n"
                . "Attached is document {nummer} as a PDF, for your records.\n\n"
                . "All documents also stay on your page:\n{seite}\n\n"
                . "If anything looks wrong, write to me and I will sort it out."],
        ],
        /* Sofort nach dem Absenden. Zwei Aufgaben: der Kunde weiss, dass es
           angekommen ist — und er hat schwarz auf weiss, dass ihn nichts
           bindet. Beides fehlte bisher ganz. */
        'anfrage_eingegangen' => [
            'it' => ['Ho ricevuto la tua richiesta',
                "Ciao {name},\n\ngrazie per la tua richiesta{paketsatz}. È arrivata e la sto leggendo con calma. Ti rispondo entro un giorno lavorativo con una prima indicazione concreta.\n\nLa richiesta è gratuita e senza impegno: un incarico nasce soltanto quando ci accordiamo per iscritto.\n\nDa qui in poi passa tutto da questa pagina:\n\n{link}\n\nLì vedi sempre a che punto siamo, puoi scrivermi e caricare i tuoi documenti (fino a {maxdatei} per file). Nessun account, nessuna password. Salvala tra i preferiti: il link resta valido, dal primo contatto fino a molto dopo la messa online.\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Deine Anfrage ist angekommen',
                "Hallo {name},\n\ndanke für deine Anfrage{paketsatz}. Sie ist da und ich lese sie in Ruhe durch. Innerhalb eines Werktags hörst du von mir, mit einer ersten konkreten Einschätzung.\n\nDie Anfrage ist kostenlos und unverbindlich: Ein Auftrag entsteht erst, wenn wir uns schriftlich einig sind.\n\nAlles Weitere läuft über diese eine Seite:\n\n{link}\n\nDort siehst du jederzeit, was gerade dran ist, kannst mir schreiben und Unterlagen hochladen (bis {maxdatei} je Datei). Kein Konto, kein Passwort. Leg sie als Lesezeichen ab — der Link bleibt gültig, vom ersten Kontakt bis lange nach dem Onlinegang.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your enquiry has arrived',
                "Hello {name},\n\nthank you for your enquiry{paketsatz}. It has arrived and I am reading it properly. You will hear from me within one working day, with a first concrete assessment.\n\nThe enquiry is free and without obligation: a project only comes about once we agree in writing.\n\nEverything else runs through this one page:\n\n{link}\n\nThere you can always see what is due next, write to me and upload your material (up to {maxdatei} per file). No account, no password. Bookmark it — the link stays valid, from the first contact until long after going live.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        /* Der Zahlungslink, wenn der Kunde zugesagt hat. */
        'zahlungslink' => [
            'it' => ['Il link per il pagamento — {paket}',
                "Ciao {name},\n\ncome concordato, ecco il link per il pagamento — {was}, {betrag}:\n\n{link}\n\nIl pagamento avviene tramite un fornitore certificato; i dati della carta non passano da me. Appena arriva ti scrivo e partiamo.\n\nSe qualcosa non torna, rispondi a questa e-mail prima di pagare.\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Dein Zahlungslink — {paket}',
                "Hallo {name},\n\nwie besprochen hier der Link für die Zahlung — {was}, {betrag}:\n\n{link}\n\nBezahlt wird über einen geprüften Anbieter; deine Kartendaten sehe ich nicht. Sobald die Zahlung da ist, melde ich mich und wir legen los.\n\nWenn etwas nicht stimmt, antworte einfach auf diese E-Mail, bevor du zahlst.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your payment link — {paket}',
                "Hello {name},\n\nas agreed, here is the payment link — {was}, {betrag}:\n\n{link}\n\nPayment runs through a certified provider; I never see your card details. As soon as it arrives I will write and we start.\n\nIf anything looks wrong, just reply to this email before paying.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        'zahlung_ok' => [
            'it' => ['Pagamento ricevuto — {paket}',
                "Ciao {name},\n\nho ricevuto il tuo acconto di {betrag}. Grazie!\n\nOra iniziamo: il prossimo passo è raccontarmi il tuo progetto.\nApri questo link e compila con calma — puoi salvare e continuare più tardi:\n\n{link}\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Zahlung erhalten — {paket}',
                "Hallo {name},\n\ndeine Anzahlung über {betrag} ist angekommen. Danke!\n\nJetzt geht es los: Der nächste Schritt ist, mir dein Projekt zu beschreiben.\nÖffne diesen Link und fülle in Ruhe aus — du kannst zwischendurch speichern:\n\n{link}\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Payment received — {paket}',
                "Hello {name},\n\nyour deposit of {betrag} has arrived. Thank you!\n\nNext step: tell me about your project.\nOpen this link and take your time — you can save and come back:\n\n{link}\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        'fragebogen_erinnerung' => [
            'it' => ['Un promemoria per il tuo progetto',
                "Ciao {name},\n\nmanca ancora il questionario per il tuo progetto. Senza quelle informazioni non possiamo iniziare davvero.\n\nEccolo — dieci minuti bastano:\n\n{link}\n\nSe qualcosa non è chiaro, rispondi pure a questa e-mail.\n\nUwe Vetter · Vecom Design"],
            'de' => ['Kurze Erinnerung an deinen Fragebogen',
                "Hallo {name},\n\nfür dein Projekt fehlt noch der Fragebogen. Ohne die Angaben können wir nicht richtig loslegen.\n\nHier ist er — zehn Minuten reichen:\n\n{link}\n\nWenn etwas unklar ist, antworte einfach auf diese E-Mail.\n\nUwe Vetter · Vecom Design"],
            'en' => ['A quick reminder about your questionnaire',
                "Hello {name},\n\nthe questionnaire for your project is still open. Without it we can’t really start.\n\nHere it is — ten minutes is enough:\n\n{link}\n\nIf anything is unclear, just reply to this email.\n\nUwe Vetter · Vecom Design"],
        ],
        'vorschau' => [
            'it' => ['Puoi dare un’occhiata all’anteprima — {paket}',
                "Ciao {name},\n\nl’anteprima del tuo sito è visibile. Guardala con calma:\n\n{link}\n\nNon devi approvare niente adesso: il sito non è ancora finito. Dimmi solo cosa ne pensi — quello che non va lo sistemo. Quando è pronto davvero ti avviso, e solo allora potrai dare il via libera.\n\nUwe Vetter · Vecom Design"],
            'de' => ['Du kannst dir den Entwurf ansehen — {paket}',
                "Hallo {name},\n\nder Entwurf deiner Website ist für dich freigeschaltet. Schau ihn dir in Ruhe an:\n\n{link}\n\nFreigeben musst du noch nichts — die Seite ist noch nicht fertig. Sag mir einfach, was dir auffällt; was nicht passt, ändere ich. Wenn sie wirklich fertig ist, melde ich mich, und erst dann kannst du sie abnehmen.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['You can take a look at the draft — {paket}',
                "Hello {name},\n\nthe draft of your site is open for you. Take your time with it:\n\n{link}\n\nYou don’t have to approve anything yet — the site isn’t finished. Just tell me what you notice; whatever doesn’t fit, I’ll change. When it really is done I’ll let you know, and only then can you sign it off.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],

        /* DIE ZWEITE NACHRICHT: JETZT IST SIE FERTIG
           ------------------------------------------------------------------
           Die Vorschau-Mail sagt "schau mal". Diese sagt "sie ist fertig, jetzt
           entscheidest du". Zwei verschiedene Saetze, zwei verschiedene
           Zeitpunkte -- vorher war es einer, und deshalb hat der Kunde
           abgenommen, waehrend noch gebaut wurde.

           Der Absatz zu den Kosten steht ausdruecklich drin: Aenderungen im
           vereinbarten Umfang sind enthalten, alles darueber bekommt er
           vorher als Angebot mit Preis. Wer das erst erfaehrt, wenn die
           Rechnung kommt, hat zu Recht schlechte Laune. */
        'abnahme' => [
            'it' => ['Il tuo sito è pronto — dagli un’occhiata finale — {paket}',
                "Ciao {name},\n\nil sito è finito. Guardalo con calma:\n\n{link}\n\nSe va bene così, dai il via libera dalla tua pagina: da lì pubblico.\n\nSe invece c’è ancora qualcosa da cambiare, scrivimelo — le modifiche che rientrano in quanto concordato sono comprese. Se una richiesta va oltre, te lo dico prima e ti mando il preventivo con il prezzo: senza il tuo ok non parte niente e non ti arriva nessun costo a sorpresa.\n\nUwe Vetter · Vecom Design"],
            'de' => ['Deine Seite ist fertig — schau sie dir an — {paket}',
                "Hallo {name},\n\ndie Seite ist fertig. Schau sie dir in Ruhe an:\n\n{link}\n\nWenn sie so passt, gib sie auf deiner Seite frei — dann veröffentliche ich.\n\nWenn noch etwas anders soll, schreib es mir. Änderungen im vereinbarten Umfang sind enthalten. Geht ein Wunsch darüber hinaus, sage ich dir das vorher und schicke dir ein Angebot mit dem Preis: Ohne dein Ja passiert nichts, und es kommt nichts nachträglich dazu.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your site is ready — take a look — {paket}',
                "Hello {name},\n\nthe site is finished. Take your time with it:\n\n{link}\n\nIf it’s right, sign it off from your page — then I’ll publish it.\n\nIf something should still change, tell me. Changes within the agreed scope are included. If a request goes beyond that, I’ll say so first and send you a quote with the price: nothing happens without your go-ahead, and nothing is added afterwards.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        'online' => [
            'it' => ['Il tuo sito è online — {paket}',
                "Ciao {name},\n\nil sito è online:\n{link}\n\nGrazie per la fiducia. Se serve qualcosa, sono qui.\n\nUwe Vetter · Vecom Design"],
            'de' => ['Deine Website ist online — {paket}',
                "Hallo {name},\n\ndie Website ist online:\n{link}\n\nDanke für dein Vertrauen. Wenn etwas ist, melde dich einfach.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your site is live — {paket}',
                "Hello {name},\n\nthe site is live:\n{link}\n\nThank you for your trust. If anything comes up, just get in touch.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        'nachricht' => [
            'it' => ['Un messaggio sul tuo progetto',
                "Ciao {name},\n\nti ho scritto sul tuo progetto:\n\n{text}\n\nPuoi rispondere qui:\n{link}\n\nUwe Vetter · Vecom Design"],
            'de' => ['Eine Nachricht zu deinem Projekt',
                "Hallo {name},\n\nich habe dir zu deinem Projekt geschrieben:\n\n{text}\n\nAntworten kannst du hier:\n{link}\n\nUwe Vetter · Vecom Design"],
            'en' => ['A message about your project',
                "Hello {name},\n\nI’ve written to you about your project:\n\n{text}\n\nYou can reply here:\n{link}\n\nUwe Vetter · Vecom Design"],
        ],
        /* Die Auftragsbestaetigung. Sie ist kein Freundlichkeitsschreiben,
           sondern die Bestaetigung des Fernabsatzvertrags auf einem
           dauerhaften Datentraeger — Art. 51 Abs. 7 Codice del Consumo.
           Deshalb steht hier, was Art. 49 Abs. 1 verlangt, und deshalb
           haengen das Widerrufsformular und der Beleg daran. */
        'auftragsbestaetigung' => [
            'it' => ['Conferma d’ordine {bestellnr} — {paket}',
                "Ciao {name},\n\n"
                . "questa è la conferma del tuo ordine. Conservala: contiene tutte le informazioni sul contratto.\n\n"
                . "--------------------------------------------------\nIL TUO ORDINE\n--------------------------------------------------\n"
                . "Ordine:     {bestellnr}\nData:       {datum}\nServizio:   {paket}\n"
                . "Totale:     {gesamt}\n{raten}\n\n"
                . "--------------------------------------------------\nCHI TI FORNISCE IL SERVIZIO\n--------------------------------------------------\n"
                . "{firma}\n\n"
                . "--------------------------------------------------\nDIRITTO DI RECESSO\n--------------------------------------------------\n"
                . "{widerruf}\n\n"
                . "In allegato trovi il modulo di recesso tipo. Non devi usarlo per forza: basta una comunicazione chiara.\n\n"
                . "{zustimmung}\n\n"
                . "Condizioni generali: {agb}\nInformativa privacy: {privacy}\n\n"
                . "La tua pagina di progetto:\n\n{link}\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Auftragsbestätigung {bestellnr} — {paket}',
                "Hallo {name},\n\n"
                . "das ist die Bestätigung deines Auftrags. Heb sie auf — sie enthält alle Angaben zum Vertrag.\n\n"
                . "--------------------------------------------------\nDEIN AUFTRAG\n--------------------------------------------------\n"
                . "Bestellung: {bestellnr}\nDatum:      {datum}\nLeistung:   {paket}\n"
                . "Gesamt:     {gesamt}\n{raten}\n\n"
                . "--------------------------------------------------\nWER DIE LEISTUNG ERBRINGT\n--------------------------------------------------\n"
                . "{firma}\n\n"
                . "--------------------------------------------------\nWIDERRUFSRECHT\n--------------------------------------------------\n"
                . "{widerruf}\n\n"
                . "Im Anhang findest du das Muster-Widerrufsformular. Du musst es nicht benutzen — eine eindeutige Nachricht genügt.\n\n"
                . "{zustimmung}\n\n"
                . "AGB: {agb}\nDatenschutzerklärung: {privacy}\n\n"
                . "Deine Projektseite:\n\n{link}\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Order confirmation {bestellnr} — {paket}',
                "Hello {name},\n\n"
                . "this is the confirmation of your order. Please keep it — it holds all the contract details.\n\n"
                . "--------------------------------------------------\nYOUR ORDER\n--------------------------------------------------\n"
                . "Order:    {bestellnr}\nDate:     {datum}\nService:  {paket}\n"
                . "Total:    {gesamt}\n{raten}\n\n"
                . "--------------------------------------------------\nWHO PROVIDES THE SERVICE\n--------------------------------------------------\n"
                . "{firma}\n\n"
                . "--------------------------------------------------\nRIGHT OF WITHDRAWAL\n--------------------------------------------------\n"
                . "{widerruf}\n\n"
                . "The model withdrawal form is attached. You do not have to use it — a clear statement is enough.\n\n"
                . "{zustimmung}\n\n"
                . "Terms: {agb}\nPrivacy notice: {privacy}\n\n"
                . "Your project page:\n\n{link}\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],

        /* Die Kuendigungsbestaetigung. Sie geht von allein raus, sobald der
           Kunde auf seiner Seite kuendigt — und sie nennt genau ein Datum:
           bis wann die Betreuung laeuft und bis wann er zahlt. Beides
           dasselbe, und genau deshalb muss es dastehen. */
        'kuendigung' => [
            'it' => ['Disdetta confermata — {paket}',
                "Ciao {name},\n\nho ricevuto la tua disdetta e te la confermo per iscritto.\n\n"
                . "{paket} resta attiva fino al {ende}.\n"
                . "Fino a quella data ti viene addebitato {betrag} al mese, dopo non più — l'ultimo addebito è quello del mese in cui rientra il {ende}.\n\n"
                . "Cosa succede dopo:\n\n"
                . "· Il sito resta online e resta tuo. Non si spegne nulla.\n"
                . "· Aggiornamenti, backup e controlli si fermano. Da quel giorno il sito è nelle tue mani o in quelle di chi vorrai.\n"
                . "· Su richiesta ti do tutti gli accessi e un backup completo, così puoi spostarlo dove preferisci.\n\n"
                . "La tua pagina resta raggiungibile anche dopo: {seite}\n\n"
                . "Se hai disdetto per qualcosa che non ha funzionato, scrivimelo — mi interessa davvero, anche se non cambi idea."],
            'de' => ['Kündigung bestätigt — {paket}',
                "Hallo {name},\n\ndeine Kündigung ist angekommen, und hiermit bestätige ich sie dir schriftlich.\n\n"
                . "{paket} läuft noch bis zum {ende}.\n"
                . "Bis dahin werden {betrag} im Monat abgebucht, danach nicht mehr — die letzte Abbuchung ist die für den Monat, in den der {ende} fällt.\n\n"
                . "Was danach passiert:\n\n"
                . "· Die Website bleibt online und bleibt deine. Es wird nichts abgeschaltet.\n"
                . "· Aktualisierungen, Sicherungen und Überwachung hören auf. Ab dem Tag liegt die Seite in deiner Hand oder in der von jemandem, den du beauftragst.\n"
                . "· Auf Wunsch bekommst du alle Zugänge und eine vollständige Sicherung, damit du sie mitnehmen kannst.\n\n"
                . "Deine Seite bleibt auch danach erreichbar: {seite}\n\n"
                . "Wenn du gekündigt hast, weil etwas nicht gepasst hat, schreib es mir — das interessiert mich wirklich, auch wenn du es dir nicht anders überlegst."],
            'en' => ['Cancellation confirmed — {paket}',
                "Hello {name},\n\nyour cancellation has arrived, and this is your written confirmation.\n\n"
                . "{paket} runs until {ende}.\n"
                . "Until then {betrag} per month is charged, after that it stops — the last charge is the one for the month that {ende} falls in.\n\n"
                . "What happens afterwards:\n\n"
                . "· The site stays online and stays yours. Nothing gets switched off.\n"
                . "· Updates, backups and monitoring stop. From that day the site is in your hands, or in those of whoever you appoint.\n"
                . "· On request you get all the logins and a full backup, so you can take it anywhere.\n\n"
                . "Your page stays reachable afterwards too: {seite}\n\n"
                . "If you cancelled because something wasn't right, tell me — I genuinely want to know, even if you don't change your mind."],
        ],

        'restzahlung' => [
            'it' => ['Saldo per {paket}',
                "Ciao {name},\n\nil sito è pronto per la consegna. Resta il saldo di {betrag}:\n\n{link}\n\nGrazie!\nUwe Vetter · Vecom Design"],
            'de' => ['Restzahlung für {paket}',
                "Hallo {name},\n\ndie Website ist bereit zur Übergabe. Offen ist noch die Restzahlung über {betrag}:\n\n{link}\n\nDanke!\nUwe Vetter · Vecom Design"],
            'en' => ['Balance for {paket}',
                "Hello {name},\n\nthe site is ready for handover. The remaining balance is {betrag}:\n\n{link}\n\nThank you!\nUwe Vetter · Vecom Design"],
        ],
    ];

    /* ----------------------------------------------------------------------
       Der Bedarfs-Konfigurator auf der Website.

       Der Ton ist derselbe wie ueberall: duzen, kurze Saetze, und was der
       Kunde tun soll, steht im ersten Satz. Was hier NICHT steht, ist ein
       Preis — der entsteht erst am Ende aus seinen Antworten.
       ---------------------------------------------------------------------- */
    public const BEDARF = [
        'titel' => ['it' => 'Che cosa ti serve?', 'de' => 'Was brauchst du?', 'en' => 'What do you need?'],
        'lead'  => [
            'it' => 'Otto domande brevi, circa un minuto e mezzo. Alla fine sai in che ordine di prezzo ti muovi — senza impegno.',
            'de' => 'Acht kurze Fragen, etwa anderthalb Minuten. Am Ende weißt du, in welcher Größenordnung du liegst — unverbindlich.',
            'en' => 'Eight short questions, about ninety seconds. At the end you know the ballpark — no obligation.',
        ],
        'schritt'  => ['it' => 'Passo {n} di {g}', 'de' => 'Schritt {n} von {g}', 'en' => 'Step {n} of {g}'],
        'weiter'   => ['it' => 'Avanti', 'de' => 'Weiter', 'en' => 'Next'],
        'zurueck'  => ['it' => 'Indietro', 'de' => 'Zurück', 'en' => 'Back'],
        'absenden' => ['it' => 'Richiedi il preventivo', 'de' => 'Angebot anfordern', 'en' => 'Request a quote'],

        'ergebnisTitel' => [
            'it' => 'Per quello che hai descritto',
            'de' => 'Für das, was du beschrieben hast',
            'en' => 'For what you have described',
        ],
        'ergebnisText' => [
            'it' => 'Questa è una stima, non un preventivo. Il prezzo definitivo te lo mando entro 24 ore, con le voci una per una — e vale quello.',
            'de' => 'Das ist eine Schätzung, kein Angebot. Den verbindlichen Preis schicke ich dir binnen 24 Stunden, Position für Position — und der gilt dann.',
            'en' => 'This is an estimate, not a quote. I will send you the binding price within 24 hours, item by item — and that one holds.',
        ],
        'ergebnisMonat' => [
            'it' => 'più {betrag} al mese per l\'assistenza, se la vuoi. È un contratto a parte e puoi decidere dopo.',
            'de' => 'dazu {betrag} im Monat für die Betreuung, wenn du magst. Das ist ein eigener Vertrag, und du kannst später entscheiden.',
            'en' => 'plus {betrag} a month for care, if you want it. That is a separate contract and you can decide later.',
        ],
        'kontaktTitel' => [
            'it' => 'Dove ti mando il preventivo?',
            'de' => 'Wohin schicke ich das Angebot?',
            'en' => 'Where should I send the quote?',
        ],
        'fName'    => ['it' => 'Come ti chiami', 'de' => 'Wie heißt du', 'en' => 'Your name'],
        'fEmail'   => ['it' => 'E-mail', 'de' => 'E-Mail', 'en' => 'Email'],
        'fTelefon' => ['it' => 'Telefono (facoltativo)', 'de' => 'Telefon (freiwillig)', 'en' => 'Phone (optional)'],
        'fFirma'   => ['it' => 'Nome dell\'attività (facoltativo)', 'de' => 'Name des Betriebs (freiwillig)', 'en' => 'Business name (optional)'],

        /* DIE SPRACHE WIRD GEFRAGT, NICHT GERATEN
           ------------------------------------------------------------------
           Bisher ergab sie sich daraus, welche Fassung der Website jemand
           offen hatte -- und weil jeder Verweis auf den Konfigurator fest
           "lang=it" trug, hiess das in der Praxis: Italienisch fuer alle.
           Danach bekam ein deutscher Kunde jede Mail, jeden Beleg und seine
           ganze Seite auf Italienisch, und niemand konnte sehen, dass das
           nie jemand so gewollt hatte.

           Die Frage steht bei den Kontaktdaten und nicht am Anfang: Dort
           gehoert sie hin -- sie beantwortet nicht, was gebaut wird, sondern
           wie wir miteinander reden. */
        'fSprache' => [
            'it' => 'In che lingua vuoi che ti scriva',
            'de' => 'In welcher Sprache soll ich dir schreiben',
            'en' => 'Which language should I write to you in',
        ],
        'fSpracheHilfe' => [
            'it' => 'Vale per le e-mail, i documenti e la tua pagina. Puoi cambiarla in qualsiasi momento.',
            'de' => 'Gilt für E-Mails, Unterlagen und deine eigene Seite. Du kannst sie jederzeit ändern.',
            'en' => 'Applies to emails, documents and your own page. You can change it at any time.',
        ],

        'danke' => [
            'it' => 'Grazie! Ho ricevuto tutto. Ti scrivo entro 24 ore con il preventivo.',
            'de' => 'Danke! Alles angekommen. Ich melde mich binnen 24 Stunden mit dem Angebot.',
            'en' => 'Thank you! I have everything. I will come back to you within 24 hours with the quote.',
        ],
        'pflicht' => [
            'it' => 'Mi servono almeno il nome e un indirizzo e-mail valido.',
            'de' => 'Ich brauche mindestens deinen Namen und eine gültige E-Mail-Adresse.',
            'en' => 'I need at least your name and a valid email address.',
        ],
        'nichts' => [
            'it' => 'Scegli almeno una risposta, così posso calcolare qualcosa.',
            'de' => 'Wähle mindestens eine Antwort, damit ich etwas rechnen kann.',
            'en' => 'Pick at least one answer so I have something to work with.',
        ],
        'panne' => [
            'it' => 'Qualcosa non ha funzionato. Riprova tra poco — quello che hai già scelto è salvato.',
            'de' => 'Etwas hat nicht geklappt. Versuch es gleich noch einmal — was du gewählt hast, ist gespeichert.',
            'en' => 'Something went wrong. Try again shortly — what you picked is saved.',
        ],
        'weg' => [
            'it' => 'Questo link non è più valido. Puoi ricominciare da capo.',
            'de' => 'Dieser Link gilt nicht mehr. Du kannst neu anfangen.',
            'en' => 'This link is no longer valid. You can start again.',
        ],
        'neu' => ['it' => 'Ricomincia', 'de' => 'Neu anfangen', 'en' => 'Start again'],
        'fEmpfehlung' => [
            'it' => 'Chi ti ha consigliato noi? (facoltativo)',
            'de' => 'Wer hat uns empfohlen? (freiwillig)',
            'en' => 'Who recommended us? (optional)',
        ],
        'empfehlungHilfe' => [
            'it' => 'Il nome basta. Se diventa un lavoro, chi ti ha mandato riceve uno sconto sull\'assistenza.',
            'de' => 'Der Name genügt. Wird ein Auftrag daraus, bekommt derjenige einen Nachlass auf seine Betreuung.',
            'en' => 'A name is enough. If it turns into a job, they get a discount on their care plan.',
        ],
        'empfehlungErkannt' => [
            'it' => 'Consigliato da {name} — grazie a entrambi.',
            'de' => 'Empfohlen von {name} — danke euch beiden.',
            'en' => 'Recommended by {name} — thank you both.',
        ],
        'knappheit' => [
            'it' => 'Prezzo di lancio — restano {n} posti su {g}.',
            'de' => 'Einführungspreis — noch {n} von {g} Plätzen.',
            'en' => 'Launch pricing — {n} of {g} places left.',
        ],
        'knappheitHilfe' => [
            'it' => 'Quando i {g} progetti sono conclusi, i prezzi salgono. Chi ha già un preventivo mantiene il suo.',
            'de' => 'Sind die {g} Projekte abgeschlossen, steigen die Preise. Wer schon ein Angebot hat, behält seines.',
            'en' => 'Once those {g} projects are done, prices go up. Anyone holding a quote keeps theirs.',
        ],
        'autoOk' => [
            'it' => 'Salvo a ogni passo. Puoi chiudere e tornare con lo stesso link.',
            'de' => 'Ich speichere bei jedem Schritt. Du kannst zumachen und mit demselben Link zurückkommen.',
            'en' => 'I save at every step. You can close this and return with the same link.',
        ],

        /* Die beiden Zeilen unter der Zusammenfassung. Sie standen fest auf
           Deutsch im Code — und die Zusammenfassung liegt auf der privaten
           Seite des Kunden. Ein italienischer Kunde las dort also mitten in
           seinem Text "Errechnete Spanne". */
        'fasseSpanne' => [
            'it' => 'Fascia di prezzo calcolata: da {von} a {bis}',
            'de' => 'Errechnete Spanne: {von} bis {bis}',
            'en' => 'Calculated range: {von} to {bis}',
        ],
        'fasseBetreuung' => [
            'it' => 'Assistenza richiesta: {betrag} al mese',
            'de' => 'Betreuung gewünscht: {betrag} im Monat',
            'en' => 'Care requested: {betrag} per month',
        ],

        /* ------------------------------------------------------------------
           Die fertige Preisnachricht.

           Sie entsteht aus denselben Zahlen wie das spaetere Angebot und
           steht in der Verwaltung schon ausgefuellt im Nachrichtenfeld. Der
           Sinn ist, dass Uwe nichts abtippt und nichts nachrechnet: lesen,
           gegebenenfalls einen Satz aendern, senden.
           ------------------------------------------------------------------ */
        'preisBetreff' => [
            'it' => 'Il prezzo per il tuo sito',
            'de' => 'Der Preis für deine Website',
            'en' => 'The price for your website',
        ],
        'preisEinleitung' => [
            'it' => 'grazie per le tue indicazioni. In base a quello che mi hai descritto, il sito viene {preis}.',
            'de' => 'danke für deine Angaben. Nach dem, was du beschrieben hast, kostet die Website {preis}.',
            'en' => 'thank you for your answers. Based on what you described, the website comes to {preis}.',
        ],
        'preisInhalt' => [
            'it' => 'Che cosa comprende:',
            'de' => 'Was darin enthalten ist:',
            'en' => 'What that includes:',
        ],
        'preisBetreuung' => [
            'it' => 'In più c\'è l\'assistenza mensile, {betrag} al mese. È un contratto a parte e puoi anche farne a meno: il sito funziona lo stesso.',
            'de' => 'Dazu kommt die monatliche Betreuung, {betrag} im Monat. Das ist ein eigener Vertrag, den du auch weglassen kannst — die Website läuft genauso.',
            'en' => 'On top of that there is the monthly care, {betrag} a month. That is a separate contract and you can do without it — the site runs just the same.',
        ],
        /* KEIN "WENN DAS PASST" MEHR
           ------------------------------------------------------------------
           Der Satz machte das Angebot von einer Antwort abhaengig, die selten
           kam: Wer nur eine Zahl liest, hat nichts, wozu er Ja sagen koennte,
           und schweigt. Damit hing der Vorgang an einer Ruecknachricht, die
           gar nichts entschieden haette.

           Das Angebot kostet nichts und steht ohnehin fertig gerechnet da.
           Es kommt jetzt in jedem Fall — mit einem Knopf zum Annehmen. Wer
           etwas anders will, sagt es weiterhin. */
        'preisSchluss' => [
            'it' => 'Il preventivo dettagliato te lo mando subito dopo, voce per voce: basta un clic per accettarlo. Se c\'è qualcosa da aggiungere o da togliere, dimmelo e rifaccio il conto.',
            'de' => 'Das Angebot dazu schicke ich dir gleich hinterher — Posten für Posten, mit einem Klick zum Annehmen. Soll etwas dazu oder weg, sag Bescheid, dann rechne ich es neu.',
            'en' => 'The detailed quote follows right after — line by line, with a single click to accept. If something should be added or removed, tell me and I will redo the figures.',
        ],
    ];

    /* ----------------------------------------------------------------------
       Das Angebot, so wie der Kunde es sieht.

       Ein Angebot ist der Moment, in dem aus einem Gespraech Geld wird. Der
       Ton bleibt trotzdem derselbe: duzen, kurze Saetze, und keine Zeile,
       die man zweimal lesen muss.
       ---------------------------------------------------------------------- */
    public const ANGEBOT = [
        'titel'   => ['it' => 'La tua offerta', 'de' => 'Dein Angebot', 'en' => 'Your quote'],
        'lead'    => [
            'it' => 'Ecco che cosa costa quello che ci siamo detti. Nessuna sorpresa dopo: quello che leggi qui è il prezzo.',
            'de' => 'Das kostet, worüber wir gesprochen haben. Keine Überraschungen danach — was hier steht, ist der Preis.',
            'en' => 'Here is what we discussed, and what it costs. No surprises later — what you read here is the price.',
        ],
        'nummer'  => ['it' => 'Offerta', 'de' => 'Angebot', 'en' => 'Quote'],
        'gueltig' => ['it' => 'Valida fino al {datum}', 'de' => 'Gültig bis {datum}', 'en' => 'Valid until {datum}'],
        'posten'  => ['it' => 'Che cosa è compreso', 'de' => 'Was drin ist', 'en' => 'What is included'],
        'summe'   => ['it' => 'Totale una tantum', 'de' => 'Einmalig gesamt', 'en' => 'One-off total'],
        'monat'   => ['it' => 'Assistenza mensile', 'de' => 'Betreuung monatlich', 'en' => 'Monthly care'],
        'zahlung' => [
            'it' => 'Si paga in due volte: {anzahlung} all\'ordine, il resto alla consegna del sito.',
            'de' => 'Bezahlt wird in zwei Schritten: {anzahlung} bei Auftrag, der Rest bei Übergabe der Website.',
            'en' => 'Paid in two steps: {anzahlung} on order, the rest when the site is handed over.',
        ],
        'annehmen'  => ['it' => 'Accetto l\'offerta', 'de' => 'Angebot annehmen', 'en' => 'Accept this quote'],
        /* Die Zusage auf die Rueckfrage. "Ja, annehmen" liest man auch dann
           richtig, wenn man die Frage darueber ueberflogen hat -- "OK" nicht. */
        'jaAnnehmen' => ['it' => 'Sì, accetto', 'de' => 'Ja, annehmen', 'en' => 'Yes, accept'],
        'abbrechen'  => ['it' => 'Annulla', 'de' => 'Abbrechen', 'en' => 'Cancel'],
        /* Ueber der Zustimmung. Kein Kleingedrucktes: Wer hier klickt,
           schliesst einen Vertrag, und das darf man ihm auch sagen. */
        'zustKopf' => [
            'it' => 'Prima di accettare',
            'de' => 'Bevor du annimmst',
            'en' => 'Before you accept',
        ],
        'fehlerZust' => [
            'it' => 'Servono entrambe le conferme per accettare l\'offerta.',
            'de' => 'Beide Bestätigungen sind nötig, um das Angebot anzunehmen.',
            'en' => 'Both confirmations are needed to accept the quote.',
        ],
        'ablehnen'  => ['it' => 'Non fa per me', 'de' => 'Passt so nicht', 'en' => 'Not for me'],
        'grundFrage'=> [
            'it' => 'Che cosa non va? Basta una riga — mi aiuta a capire.',
            'de' => 'Was passt nicht? Eine Zeile genügt — sie hilft mir weiter.',
            'en' => 'What is not right? One line is enough — it helps me.',
        ],
        'pdf' => ['it' => 'Scarica in PDF', 'de' => 'Als PDF herunterladen', 'en' => 'Download as PDF'],
        'proMonat'  => ['it' => 'al mese', 'de' => 'im Monat', 'en' => 'per month'],
        'pdfAn'     => ['it' => 'A', 'de' => 'An', 'en' => 'To'],
        'pdfDatum'  => ['it' => 'Data', 'de' => 'Datum', 'en' => 'Date'],
        'pdfGueltig'=> ['it' => 'Valida fino al', 'de' => 'Gültig bis', 'en' => 'Valid until'],
        'pdfKunde'  => ['it' => 'N. cliente', 'de' => 'Kundennummer', 'en' => 'Customer no.'],
        'pdfWas'    => ['it' => 'Prestazione', 'de' => 'Leistung', 'en' => 'Item'],
        'pdfBetrag' => ['it' => 'Importo', 'de' => 'Betrag', 'en' => 'Amount'],
        'pdfFest'   => [
            'it' => 'Quello che leggi qui è il prezzo. Se durante il lavoro serve altro, te lo dico prima.',
            'de' => 'Was hier steht, ist der Preis. Kommt während der Arbeit etwas dazu, spreche ich es vorher ab.',
            'en' => 'What is written here is the price. If anything comes up during the work, I agree it with you first.',
        ],
        'dankeAn' => [
            'it' => 'Grazie! Ti scrivo subito con il link per l\'acconto — poi si comincia.',
            'de' => 'Danke! Ich melde mich gleich mit dem Link für die Anzahlung — dann geht es los.',
            'en' => 'Thank you! I will send you the deposit link shortly — then we start.',
        ],
        'dankeAb' => [
            'it' => 'Va bene, grazie per avermelo detto. Se cambi idea, sai dove trovarmi.',
            'de' => 'Alles gut, danke für die Rückmeldung. Wenn du es dir anders überlegst, weißt du, wo ich bin.',
            'en' => 'That is fine, thanks for telling me. If you change your mind, you know where I am.',
        ],
        'schonAn' => [
            'it' => 'Questa offerta è già stata accettata.',
            'de' => 'Dieses Angebot ist bereits angenommen.',
            'en' => 'This quote has already been accepted.',
        ],
        'schonAb' => [
            'it' => 'Questa offerta è stata rifiutata.',
            'de' => 'Dieses Angebot wurde abgelehnt.',
            'en' => 'This quote was declined.',
        ],
        'abgelaufen' => [
            'it' => 'Questa offerta è scaduta. Scrivimi e te ne faccio una nuova — di solito al prezzo di prima.',
            'de' => 'Dieses Angebot ist abgelaufen. Schreib mir, dann mache ich ein neues — meist zum alten Preis.',
            'en' => 'This quote has expired. Write to me and I will make a new one — usually at the old price.',
        ],
        /* ---- Gegenvorschlag ------------------------------------------
           Der Kunde stellt sich zusammen, was er will. Die Zahl, die er dabei
           sieht, ist eine Auskunft -- deshalb sagt jeder dieser Saetze, dass
           das verbindliche Angebot danach kommt. */
        'aendernKopf' => [
            'it' => 'Ti serve qualcosa in più o in meno?',
            'de' => 'Brauchst du mehr oder weniger?',
            'en' => 'Need more, or less?',
        ],
        'aendernLead' => [
            'it' => 'Togli la spunta a quello che non ti serve, cambia il numero di pagine, aggiungi quello che manca. Il totale si aggiorna subito.',
            'de' => 'Nimm das Häkchen weg, was du nicht brauchst, ändere die Zahl der Seiten, nimm dazu, was fehlt. Die Summe rechnet sich sofort mit.',
            'en' => 'Untick what you don’t need, change the number of pages, add what’s missing. The total updates as you go.',
        ],
        'aendernDazu' => [
            'it' => 'Da aggiungere',
            'de' => 'Dazunehmen',
            'en' => 'Add to it',
        ],
        'aendernNeu' => [
            'it' => 'Con queste modifiche',
            'de' => 'Mit diesen Änderungen',
            'en' => 'With these changes',
        ],
        'aendernKeinAngebot' => [
            'it' => 'Indicazione, non un’offerta. Quella vincolante te la mando io, di solito lo stesso giorno.',
            'de' => 'Auskunft, kein Angebot. Das verbindliche schicke ich dir, meist noch am selben Tag.',
            'en' => 'A guide, not a quote. The binding one comes from me, usually the same day.',
        ],
        'aendernAnfrage' => [
            'it' => 'su richiesta',
            'de' => 'auf Anfrage',
            'en' => 'on request',
        ],
        'aendernFest' => [
            'it' => 'sempre incluso',
            'de' => 'immer dabei',
            'en' => 'always included',
        ],
        'aendernSenden' => [
            'it' => 'Così mi va meglio',
            'de' => 'So passt es mir besser',
            'en' => 'This suits me better',
        ],
        'aendernDanke' => [
            'it' => 'Ricevuto. Ti mando l’offerta aggiornata, di solito lo stesso giorno.',
            'de' => 'Angekommen. Ich schicke dir das geänderte Angebot, meist noch am selben Tag.',
            'en' => 'Got it. I’ll send you the updated quote, usually the same day.',
        ],
        'aendernGenug' => [
            'it' => 'Abbiamo già fatto due giri. Se manca ancora qualcosa, chiamami: in due minuti al telefono si risolve meglio che qui.',
            'de' => 'Wir haben schon zweimal hin und her. Wenn noch etwas fehlt, ruf mich an — zwei Minuten am Telefon klären mehr als eine dritte Runde.',
            'en' => 'We’ve been back and forth twice. If something is still missing, call me — two minutes on the phone beats a third round.',
        ],
        'aendernOffen' => [
            'it' => 'Il tuo desiderio è arrivato. Ti rispondo con l’offerta aggiornata.',
            'de' => 'Dein Wunsch ist angekommen. Ich melde mich mit dem geänderten Angebot.',
            'en' => 'Your request has arrived. I’ll come back with the updated quote.',
        ],
        'ersetzt' => [
            'it' => 'Questa offerta è stata sostituita da una nuova, con le modifiche che mi hai chiesto. La trovi nell’e-mail più recente. Qui sotto resta la versione precedente, così puoi confrontarle.',
            'de' => 'Dieses Angebot wurde durch ein neues ersetzt — mit den Änderungen, um die du gebeten hast. Es steht in der jüngeren E-Mail. Hier unten bleibt die vorige Fassung stehen, damit du vergleichen kannst.',
            'en' => 'This quote has been replaced by a new one with the changes you asked for. It is in the more recent email. The previous version stays below so you can compare.',
        ],
        'weg' => [
            'it' => 'Questo link non è più valido.',
            'de' => 'Dieser Link gilt nicht mehr.',
            'en' => 'This link is no longer valid.',
        ],
        'panne' => [
            'it' => 'Qualcosa non ha funzionato. Riprova tra poco.',
            'de' => 'Etwas hat nicht geklappt. Versuch es gleich noch einmal.',
            'en' => 'Something went wrong. Please try again shortly.',
        ],
    ];

    public static function h(array $karte, string $sprache, string $ersatz = ''): string
    {
        return (string) ($karte[$sprache] ?? $karte['it'] ?? $ersatz);
    }

    public static function mail(string $anlass, string $sprache, array $werte): array
    {
        $satz = self::MAILS[$anlass][$sprache] ?? self::MAILS[$anlass]['it'] ?? ['', ''];
        $suchen  = array_map(static fn($k) => '{' . $k . '}', array_keys($werte));
        $ersetzen = array_values($werte);
        return [str_replace($suchen, $ersetzen, $satz[0]), str_replace($suchen, $ersetzen, $satz[1])];
    }
}
