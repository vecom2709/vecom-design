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
    /* ANKLICKEN STATT SCHREIBEN (B2, 25.09.2026)
       Satzbausteine unter den langen Textfeldern, die sich wiederholen. Ein
       Klick setzt den Baustein ins Feld; der Kunde laesst ihn stehen oder
       schreibt weiter. Bewusst KEIN neues Datenformat: Gespeichert wird
       weiter Text, Briefing, Angebot und Telefon lesen wie bisher. */
    public const CHIPS = [
        'heute' => [
            ['it' => 'Rispondo io al telefono', 'de' => 'Ich gehe selbst ans Telefon', 'en' => 'I answer the phone myself'],
            ['it' => 'Spesso non riesco a rispondere', 'de' => 'Oft komme ich nicht ans Telefon', 'en' => 'I often can’t pick up'],
            ['it' => 'Molti scrivono su WhatsApp', 'de' => 'Viele schreiben per WhatsApp', 'en' => 'Many write on WhatsApp'],
            ['it' => 'Rispondo alle e-mail in giornata', 'de' => 'E-Mails beantworte ich am selben Tag', 'en' => 'I answer emails the same day'],
            ['it' => 'Le richieste arrivano dai social', 'de' => 'Anfragen kommen über Social Media', 'en' => 'Enquiries come via social media'],
        ],
        'einesache' => [
            ['it' => 'Qualità artigianale', 'de' => 'Handwerkliche Qualität', 'en' => 'Craft quality'],
            ['it' => 'Azienda di famiglia da anni', 'de' => 'Familienbetrieb seit Jahren', 'en' => 'Family business for years'],
            ['it' => 'Veloci e affidabili', 'de' => 'Schnell und zuverlässig', 'en' => 'Fast and reliable'],
            ['it' => 'Consulenza personale', 'de' => 'Persönliche Beratung', 'en' => 'Personal advice'],
            ['it' => 'Prodotti locali', 'de' => 'Aus der Region', 'en' => 'Local products'],
        ],
        'erhalten' => [
            ['it' => 'Il logo', 'de' => 'Das Logo', 'en' => 'The logo'],
            ['it' => 'I colori', 'de' => 'Die Farben', 'en' => 'The colours'],
            ['it' => 'Le foto', 'de' => 'Die Fotos', 'en' => 'The photos'],
            ['it' => 'I testi', 'de' => 'Die Texte', 'en' => 'The texts'],
            ['it' => 'L’indirizzo del sito', 'de' => 'Die Adresse der Seite', 'en' => 'The site address'],
        ],
        'stoert' => [
            ['it' => 'È superato', 'de' => 'Wirkt veraltet', 'en' => 'Looks dated'],
            ['it' => 'Sul telefono si vede male', 'de' => 'Auf dem Handy schlecht', 'en' => 'Poor on mobile'],
            ['it' => 'Non ci trovano su Google', 'de' => 'Bei Google nicht zu finden', 'en' => 'Not found on Google'],
            ['it' => 'Non porta richieste', 'de' => 'Bringt keine Anfragen', 'en' => 'Brings no enquiries'],
            ['it' => 'Non riesco a modificarlo da solo', 'de' => 'Ich kann nichts selbst ändern', 'en' => 'I can’t change anything myself'],
            ['it' => 'È lento', 'de' => 'Lädt langsam', 'en' => 'Loads slowly'],
        ],
        'abneigung' => [
            ['it' => 'Musica o video che partono da soli', 'de' => 'Musik oder Videos mit Selbststart', 'en' => 'Music or videos that autoplay'],
            ['it' => 'Foto di repertorio', 'de' => 'Beliebige Stockfotos', 'en' => 'Generic stock photos'],
            ['it' => 'Finestre pop-up', 'de' => 'Aufpoppende Fenster', 'en' => 'Pop-ups'],
            ['it' => 'Troppo testo', 'de' => 'Zu viel Text', 'en' => 'Too much text'],
            ['it' => 'Colori troppo accesi', 'de' => 'Knallige Farben', 'en' => 'Loud colours'],
        ],
        'beschreibung' => [
            ['it' => 'Siamo un’azienda di famiglia', 'de' => 'Wir sind ein Familienbetrieb', 'en' => 'We’re a family business'],
            ['it' => 'Lavoriamo su appuntamento', 'de' => 'Wir arbeiten nach Termin', 'en' => 'We work by appointment'],
            ['it' => 'Clienti privati e aziende', 'de' => 'Privat- und Firmenkunden', 'en' => 'Private and business clients'],
            ['it' => 'Consegniamo anche a domicilio', 'de' => 'Wir liefern auch nach Hause', 'en' => 'We also deliver'],
        ],
    ];

    public const FRAGEBOGEN = [

        /* ---------- 1 ---------------------------------------------------- */
        'unternehmen' => [
            'it' => 'La sua azienda', 'de' => 'Ihr Unternehmen', 'en' => 'Your business',
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

                'beschreibung' => ['it' => 'Cosa fate, in poche frasi', 'de' => 'Was Sie machen, in wenigen Sätzen', 'en' => 'What you do, in a few sentences', 'art' => 'lang'],

                'zielgruppe' => [
                    'it' => 'Chi sono i vostri clienti?', 'de' => 'Wer sind Ihre Kunden?', 'en' => 'Who are your customers?',
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

                'ort' => ['it' => 'In quale città o paese siete?', 'de' => 'In welchem Ort sind Sie?', 'en' => 'Which town are you in?', 'art' => 'text'],

                'gebiet' => [
                    'it' => 'Fin dove arrivate?', 'de' => 'Wie weit reicht Ihr Einzugsgebiet?', 'en' => 'How far do you reach?',
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
                        'besuch'    => ['it' => 'Far venire la gente da voi', 'de' => 'Leute zu Ihnen in den Laden holen', 'en' => 'Get people to come by'],
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
                        'besuch'    => ['it' => 'Far venire la gente da voi', 'de' => 'Leute zu Ihnen holen', 'en' => 'Get people to come by'],
                        'bewerber'  => ['it' => 'Trovare collaboratori', 'de' => 'Bewerber finden', 'en' => 'Find staff'],
                        'nichts'    => ['it' => 'Nient’altro, conta solo il primo', 'de' => 'Nichts weiter, nur das erste zählt', 'en' => 'Nothing else, only the first counts'],
                    ],
                ],

                /* Die nuetzlichste Frage im ganzen Bogen. Eine Zeile, und sie
                   entscheidet, was im ersten Bildschirm steht. */
                'einesache' => ['it' => 'Se un visitatore ricorda una cosa sola di voi — quale deve essere?',
                                'de' => 'Wenn ein Besucher nur eine Sache über Sie mitnimmt — welche?',
                                'en' => 'If a visitor remembers one thing about you — which one?',
                                'art' => 'text'],

                /* Sagt mir, ob ein Formular ueberhaupt Sinn hat oder ob nur
                   die Telefonnummer gross genug sein muss. */
                'heute' => ['it' => 'Oggi, quando qualcuno vi telefona o scrive: cosa succede?',
                            'de' => 'Was passiert heute, wenn jemand Sie anruft oder Ihnen schreibt?',
                            'en' => 'Today, when someone calls or writes: what happens?',
                            'art' => 'lang'],

                'mitbewerber' => ['it' => 'Due o tre concorrenti della zona, con il sito se ce l’hanno',
                                  'de' => 'Zwei, drei Mitbewerber aus der Gegend, mit Website falls vorhanden',
                                  'en' => 'Two or three local competitors, with their site if they have one',
                                  'art' => 'lang'],

                /* Vorbelegt aus Branche und Ort. Korrigieren koennen alle,
                   erfinden fast niemand -- vorher stand hier "gute Pizza". */
                'suchwoerter' => ['it' => 'Con quali parole dovrebbero trovarvi su Google? Corregga pure la proposta.',
                                  'de' => 'Mit welchen Wörtern sollen Leute Sie bei Google finden? Passen Sie den Vorschlag an.',
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

                'seiten' => ['it' => 'Come si chiamano le pagine? Cambi pure la proposta.',
                             'de' => 'Wie sollen die Seiten heißen? Ändern Sie den Vorschlag ruhig.',
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
                               'de' => 'An der jetzigen Seite: Was stört Sie am meisten?',
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
                        'weissnicht'=> ['it' => 'Non lo so — decida Lei', 'de' => 'Weiß ich nicht — entscheiden Sie', 'en' => 'I don’t know — you decide'],
                    ],
                ],

                'farben' => [
                    'it' => 'Colori: cosa vi piace?', 'de' => 'Farben: Was gefällt Ihnen?', 'en' => 'Colours: what do you like?',
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
                        'weissnicht'=> ['it' => 'Non lo so — decida Lei', 'de' => 'Weiß ich nicht — entscheiden Sie', 'en' => 'I don’t know — you decide'],
                    ],
                ],

                'wirkung' => [
                    'it' => 'Come deve sentirsi chi apre il sito? Scelga fino a tre.',
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
                        'egal'    => ['it' => 'Nessuna preferenza — decida Lei', 'de' => 'Keine Wünsche — entscheiden Sie', 'en' => 'No preference — you decide'],
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
                                'de' => 'Websites, die Ihnen gefallen — auch aus anderen Branchen',
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
                    'it' => 'Che cosa avete già?', 'de' => 'Was haben Sie schon?', 'en' => 'What do you already have?',
                    'art' => 'stand',
                    'zeilen' => [
                        'logo'      => ['it' => 'Logo', 'de' => 'Logo', 'en' => 'Logo'],
                        'betrieb'   => ['it' => 'Foto dei locali', 'de' => 'Fotos vom Betrieb', 'en' => 'Photos of the premises'],
                        'produkt'   => ['it' => 'Foto di prodotti o lavori', 'de' => 'Fotos von Produkten oder Arbeiten', 'en' => 'Photos of products or work'],
                        'team'      => ['it' => 'Foto del team', 'de' => 'Team- oder Personenfotos', 'en' => 'Team or people photos'],
                        'video'     => ['it' => 'Video', 'de' => 'Video', 'en' => 'Video'],
                        'texte'     => ['it' => 'Testi su di voi', 'de' => 'Texte über Ihr Unternehmen', 'en' => 'Copy about you'],
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
                        'teils'  => ['it' => 'In parte noi, in parte Lei', 'de' => 'Teils wir, teils Sie', 'en' => 'Partly us, partly you'],
                        'du'     => ['it' => 'Li scriva Lei', 'de' => 'Bitte schreiben Sie sie', 'en' => 'Please write them'],
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
                    'it' => 'Come vi rivolgete ai clienti?', 'de' => 'Wie sprechen Sie Ihre Kunden an?', 'en' => 'How do you address customers?',
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

                /* DIE HILFE STEHT UEBER DER AUSWAHL, NICHT DARUNTER
                   ----------------------------------------------------------
                   Die drei Wunschzeilen erscheinen erst, wenn "Haben wir
                   nicht" angeklickt ist. Das ist richtig -- wer schon eine
                   Adresse hat, braucht keine drei leeren Felder. Aber es
                   heisst auch: Wer nicht klickt, sieht nie, dass es die
                   Pruefung gibt. Uwe hat sie selbst nicht gefunden; ein
                   Kunde findet sie dann erst recht nicht.

                   Also sagt eine Zeile ueber der Auswahl, was hinter dem
                   dritten Punkt liegt. Eine Funktion, die man erst durch
                   Ausprobieren entdeckt, gibt es fuer die meisten nicht. */
                'domain' => [
                    'it' => 'L’indirizzo del sito (dominio)', 'de' => 'Die Adresse der Website (Domain)', 'en' => 'The website address (domain)',
                    'art' => 'eins', 'frei' => true, 'hilfe' => 'domainHilfe',
                    'optionen' => [
                        'uns'      => ['it' => 'Ce l’abbiamo, è intestato a noi', 'de' => 'Haben wir, läuft auf uns', 'en' => 'We have one, registered to us'],
                        'fremd'    => ['it' => 'Ce l’abbiamo, ma è di un’agenzia o di un conoscente', 'de' => 'Haben wir, liegt aber bei einer Agentur oder einem Bekannten', 'en' => 'We have one, but an agency or acquaintance holds it'],
                        'neu'      => ['it' => 'Non ce l’abbiamo — propongo tre indirizzi qui sotto',
                                       'de' => 'Haben wir nicht — ich schlage unten drei vor',
                                       'en' => 'We have none — I’ll suggest three below'],
                        'weissnicht'=> ['it' => 'Non lo so', 'de' => 'Weiß ich nicht', 'en' => 'I don’t know'],
                    ],
                ],

                /* DREI WUENSCHE, NICHT EINER
                   ----------------------------------------------------------
                   Mit einem Feld lief es so: Der Kunde schreibt seinen
                   Wunsch, die Adresse ist vergeben -- was sie bei kurzen,
                   naheliegenden Namen fast immer ist --, ich schreibe ihm,
                   er antwortet in zwei Tagen mit dem naechsten, der auch weg
                   ist. Eine Woche fuer eine Auskunft, die eine Sekunde
                   dauert.

                   Drei Zeilen in Rangfolge, und daneben steht sofort, was
                   frei ist. Damit ist die Frage erledigt, bevor ich davon
                   erfahre. */
                'wunsch1' => ['it' => 'Primo desiderio', 'de' => 'Erster Wunsch', 'en' => 'First choice',
                              'art' => 'text', 'pruefen' => true, 'hilfe' => 'wunschHilfe',
                              'wenn' => ['feld' => 'domain', 'ist' => ['neu']]],
                'wunsch2' => ['it' => 'Secondo desiderio', 'de' => 'Zweiter Wunsch', 'en' => 'Second choice',
                              'art' => 'text', 'pruefen' => true,
                              'wenn' => ['feld' => 'domain', 'ist' => ['neu']]],
                'wunsch3' => ['it' => 'Terzo desiderio', 'de' => 'Dritter Wunsch', 'en' => 'Third choice',
                              'art' => 'text', 'pruefen' => true,
                              'wenn' => ['feld' => 'domain', 'ist' => ['neu']]],

                /* DOMAIN, HOSTING UND E-MAIL SIND DREI ENTSCHEIDUNGEN (25.09.2026)
                   ----------------------------------------------------------
                   Bis heute kannte der Ablauf nur einen Fall: keine Domain,
                   also neue Domain, Hosting und Postfach in einem Paket. Wer
                   seine Domain behalten, sie umziehen lassen oder seine
                   E-Mail bei Microsoft 365 lassen wollte, kam nicht vor.
                   Jetzt entscheidet der Kunde jedes davon selbst, und keine
                   Antwort ist vorgewaehlt -- eine Vorauswahl "zu uns
                   uebertragen" waere ein Schubs, kein Angebot.

                   Verbindlich ist keine dieser Antworten. Bestellt wird erst
                   mit dem Knopf "zahlungspflichtig bestellen" auf der
                   Kundenseite, und dort steht dann genau das, was hier
                   gewaehlt wurde. */
                'domain_name' => ['it' => 'Qual è il dominio?', 'de' => 'Welche Domain ist es?', 'en' => 'Which domain is it?',
                                  'art' => 'text', 'wenn' => ['feld' => 'domain', 'ist' => ['uns', 'fremd']]],
                'domain_wahl' => [
                    'it' => 'Che cosa deve succedere con il dominio?', 'de' => 'Was soll mit der Domain passieren?', 'en' => 'What should happen with the domain?',
                    'art' => 'eins', 'wenn' => ['feld' => 'domain', 'ist' => ['uns', 'fremd']],
                    'optionen' => [
                        'behalten'    => ['it' => 'Resta dal fornitore attuale', 'de' => 'Sie bleibt bei unserem bisherigen Anbieter', 'en' => 'It stays with our current provider'],
                        'uebertragen' => ['it' => 'Deve passare a Vecom Design', 'de' => 'Sie soll zu Vecom Design umziehen', 'en' => 'It should move to Vecom Design'],
                        'offen'       => ['it' => 'Non lo so ancora — mi consigli', 'de' => 'Weiß ich noch nicht — bitte beraten', 'en' => 'Not sure yet — please advise'],
                    ],
                ],
                'hosting_wahl' => [
                    'it' => 'Dove deve essere ospitato il nuovo sito?', 'de' => 'Wo soll die neue Website laufen?', 'en' => 'Where should the new website be hosted?',
                    'art' => 'eins',
                    'optionen' => [
                        'vecom'  => ['it' => 'Da Vecom Design — prezzo e condizioni li vedo prima di ordinare', 'de' => 'Bei Vecom Design — Preis und Bedingungen sehe ich vor der Bestellung', 'en' => 'With Vecom Design — I’ll see price and terms before ordering'],
                        'bisher' => ['it' => 'Dal nostro fornitore attuale o da uno scelto da noi', 'de' => 'Bei unserem bisherigen Anbieter oder einem, den wir wählen', 'en' => 'With our current provider or one we choose'],
                        'offen'  => ['it' => 'Non lo so ancora — mi consigli', 'de' => 'Noch offen — bitte beraten', 'en' => 'Still open — please advise'],
                    ],
                ],
                'mail_wahl' => [
                    'it' => 'E l’e-mail con il vostro dominio?', 'de' => 'Und die E-Mail mit Ihrer Domain?', 'en' => 'And email with your domain?',
                    'art' => 'eins',
                    'optionen' => [
                        'bisher' => ['it' => 'Resta com’è (per es. dal fornitore, Microsoft 365, Gmail)', 'de' => 'Bleibt, wie sie ist (z. B. beim Anbieter, Microsoft 365, Gmail)', 'en' => 'Stays as it is (e.g. provider, Microsoft 365, Gmail)'],
                        'vecom'  => ['it' => 'Una casella da Vecom Design (info@il-vostro-dominio)', 'de' => 'Ein Postfach über Vecom Design (info@ihre-domain)', 'en' => 'A mailbox through Vecom Design (info@your-domain)'],
                        'keine'  => ['it' => 'Non ci serve', 'de' => 'Brauchen wir nicht', 'en' => 'We don’t need it'],
                    ],
                ],

                /* Weitere Adressen, die bei info@ ankommen (25.09.2026; bis 26.09. kontakt@) --
                   beim Einrichten per KAS-Schnittstelle als Weiterleitung angelegt. */
                'mail_weiter' => [
                    'it' => 'Altri indirizzi che devono arrivare a info@ (per es. contatto, prenotazioni) — facoltativo',
                    'de' => 'Weitere Adressen, die bei info@ ankommen sollen (z. B. kontakt, buchung) — freiwillig',
                    'en' => 'Other addresses that should arrive at info@ (e.g. contact, bookings) — optional',
                    'art' => 'text', 'wenn' => ['feld' => 'mail_wahl', 'ist' => ['vecom']],
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
                        'du'     => ['it' => 'Preferirei affidarlo a Lei', 'de' => 'Am liebsten Sie', 'en' => 'I’d rather you did'],
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
        /* Der Knopf zum Vorhaben im Dashboard (24.09.2026) */
        'vorhabenKnopf'  => ['it' => 'Descrivere il progetto', 'de' => 'Vorhaben beschreiben', 'en' => 'Describe your project'],
        'vorhabenWeiter' => ['it' => 'Continuare il progetto', 'de' => 'Vorhaben weiter ausfüllen', 'en' => 'Continue your project'],
        'titel'      => ['it' => 'Il suo progetto', 'de' => 'Ihr Projekt', 'en' => 'Your project'],
        'lead'       => ['it' => 'Solo l’essenziale, quasi tutto da spuntare — in pochi minuti. Il resto è facoltativo e si può aggiungere anche dopo.',
                         'de' => 'Nur das Nötigste, fast alles zum Anklicken — in wenigen Minuten. Der Rest ist freiwillig und geht auch später.',
                         'en' => 'Just the essentials, mostly tapping — in a few minutes. The rest is optional and can be added later.'],
        'freiZeile'  => ['it' => 'Vuole aggiungere qualcosa? (facoltativo)',
                         'de' => 'Etwas dazu sagen? (freiwillig)',
                         'en' => 'Anything to add? (optional)'],
        'domainHilfe'=> ['it' => 'Non avete ancora un indirizzo? Scegliete il terzo punto: proponete tre nomi e vi dico subito quali sono liberi.',
                         'de' => 'Noch keine Adresse? Wählen Sie den dritten Punkt — dann schlagen Sie drei Namen vor, und ich sage Ihnen sofort, welche frei sind.',
                         'en' => 'No address yet? Pick the third option — suggest three names and I’ll tell you right away which are free.'],
        'wunschHilfe'=> ['it' => 'Scriva tre indirizzi, il preferito per primo. Le dico subito quali sono liberi. Esempio: lasuaazienda.it',
                         'de' => 'Schreiben Sie drei Adressen, die liebste zuerst. Ich sage Ihnen sofort, welche frei sind. Beispiel: ihrefirma.it',
                         'en' => 'Write three addresses, your favourite first. I’ll tell you right away which are free. Example: yourcompany.com'],
        'pruefeLaeuft'=> ['it' => 'Controllo…', 'de' => 'Sehe nach…', 'en' => 'Checking…'],
        'schonGesagt'=> ['it' => 'Alcune risposte sono già compilate: le ha date quando ha calcolato il prezzo. Le corregga pure se nel frattempo è cambiato qualcosa.',
                         'de' => 'Ein paar Antworten stehen schon drin — die haben Sie gegeben, als Sie den Preis ausgerechnet haben. Ändern Sie sie ruhig, wenn sich etwas geändert hat.',
                         'en' => 'A few answers are already filled in — you gave them when you worked out the price. Change them if anything has moved on.'],
        'speichern'  => ['it' => 'Salvare e continuare dopo', 'de' => 'Zwischenspeichern', 'en' => 'Save for later'],
        'absenden'   => ['it' => 'Inviare definitivamente', 'de' => 'Endgültig absenden', 'en' => 'Send'],
        'gespeichert'=> ['it' => 'Salvato. Può tornare quando vuole con lo stesso link.',
                         'de' => 'Gespeichert. Sie können mit demselben Link jederzeit zurückkommen.',
                         'en' => 'Saved. Come back any time with the same link.'],
        'danke'      => ['it' => 'Grazie! Ho ricevuto tutto e mi metto al lavoro.',
                         'de' => 'Danke! Ich habe alles bekommen und lege los.',
                         'en' => 'Thank you! I have everything and I’m getting started.'],
        'weg'        => ['it' => 'Questo link non è più valido.', 'de' => 'Dieser Link gilt nicht mehr.', 'en' => 'This link is no longer valid.'],
        'schon'      => ['it' => 'Ha già inviato le informazioni. Grazie!', 'de' => 'Sie haben die Angaben schon abgeschickt. Vielen Dank!', 'en' => 'You have already sent your answers. Thank you!'],
        'pflicht'    => ['it' => 'Manca ancora una risposta necessaria per il preventivo — è evidenziata qui sotto.', 'de' => 'Für das Angebot fehlt noch eine Antwort — sie ist unten markiert.', 'en' => 'One answer needed for the quote is still missing — it’s highlighted below.'],
        'panne'      => ['it' => 'Qualcosa non ha funzionato. Riprovi tra poco — oppure mi scriva e me ne occupo io.',
                         'de' => 'Da hat etwas nicht geklappt. Versuchen Sie es gleich noch einmal — oder schreiben Sie mir, dann kümmere ich mich darum.',
                         'en' => 'Something went wrong. Please try again shortly — or write to me and I’ll sort it out.'],

        /* Der Fragebogen laeuft in Abschnitten. Vier kurze Seiten statt einer
           langen — und zwischen den Seiten wird gespeichert, ohne dass der
           Kunde daran denken muss. */
        'schritt'    => ['it' => 'Passo {n} di {g}', 'de' => 'Schritt {n} von {g}', 'en' => 'Step {n} of {g}'],
        /* Kern und Kuer, Zeit statt Schritt, Ergaenzen nach dem Absenden
           (B1, B6, C1 -- 25.09.2026). */
        'lieberReden' => ['it' => 'Preferisce parlare invece di cliccare? Manuela, l’assistente vocale di Vecom Design, compila il questionario con Lei — tocchi il pulsante in basso a destra e dica il suo numero cliente {nr}.',
                          'de' => 'Lieber sprechen als klicken? Manuela, die Sprachassistentin von Vecom Design, geht den Fragebogen mit Ihnen durch — tippen Sie unten rechts auf den Knopf und nennen Sie Ihre Kundennummer {nr}.',
                          'en' => 'Rather talk than click? Manuela, Vecom Design’s voice assistant, goes through the questionnaire with you — tap the button at the bottom right and say your customer number {nr}.'],
        'diktieren'   => ['it' => 'Dettare', 'de' => 'Diktieren', 'en' => 'Dictate'],
        'diktierenAus'=> ['it' => 'Stop', 'de' => 'Stopp', 'en' => 'Stop'],
        'diktierenHinweis' => ['it' => 'Il riconoscimento vocale è del suo browser (per es. Google o Apple).',
                               'de' => 'Die Spracherkennung übernimmt Ihr Browser (z. B. Google oder Apple).',
                               'en' => 'Speech recognition is done by your browser (e.g. Google or Apple).'],
        'hochladenHier' => ['it' => 'Può caricarli subito qui — foto, logo, menù (anche come foto dal telefono):',
                            'de' => 'Gleich hier hochladen — Fotos, Logo, Speisekarte (auch als Handyfoto):',
                            'en' => 'Upload them right here — photos, logo, menu (a phone photo is fine):'],
        'hochgeladen'   => ['it' => '{n} file già caricati', 'de' => '{n} Dateien sind schon da', 'en' => '{n} files already uploaded'],
        'ausSeite'      => ['it' => 'Preso dal suo sito attuale — controlli, per favore.',
                            'de' => 'Von Ihrer bisherigen Website übernommen — bitte kurz prüfen.',
                            'en' => 'Taken from your current website — please check.'],
        'hochladenOk'   => ['it' => 'Grazie, i file sono arrivati.', 'de' => 'Danke, die Dateien sind angekommen.', 'en' => 'Thank you, the files arrived.'],
        'nochMin'     => ['it' => 'Ancora circa {m} minuti', 'de' => 'Noch etwa {m} Minuten', 'en' => 'About {m} minutes to go'],
        'nochEineMin' => ['it' => 'Ancora circa un minuto', 'de' => 'Noch etwa eine Minute', 'en' => 'About one minute to go'],
        'fastFertig'  => ['it' => 'Quasi fatto', 'de' => 'Fast geschafft', 'en' => 'Almost done'],
        'kuer'        => ['it' => 'Se le va, mi aiuta anche questo (facoltativo)', 'de' => 'Wenn Sie mögen, hilft mir auch das (freiwillig)', 'en' => 'If you like, this helps me too (optional)'],
        'kuerGanz'    => ['it' => 'Questo passo è facoltativo — apra se le va', 'de' => 'Dieser Schritt ist freiwillig — aufklappen, wenn Sie mögen', 'en' => 'This step is optional — open it if you like'],
        'ergaenzenTitel'   => ['it' => 'Aggiungere dettagli facoltativi', 'de' => 'Freiwillige Angaben ergänzen', 'en' => 'Add optional details'],
        'ergaenzenFertig'  => ['it' => 'Salvare e chiudere', 'de' => 'Speichern und fertig', 'en' => 'Save and finish'],
        'ergaenzenHinweis' => ['it' => 'Ha già dato tutto ciò che serve per il preventivo. Se vuole raccontarmi di più su stile, testi o concorrenti, può farlo quando vuole.',
                               'de' => 'Sie haben alles gegeben, was ich für das Angebot brauche. Wenn Sie mir mehr über Stil, Texte oder Mitbewerber erzählen möchten, geht das jederzeit.',
                               'en' => 'You’ve given me everything I need for the quote. If you’d like to tell me more about style, texts or competitors, you can do so any time.'],
        'ergaenzenKnopf'   => ['it' => 'Aggiungere dettagli (facoltativo)', 'de' => 'Angaben ergänzen (freiwillig)', 'en' => 'Add details (optional)'],
        'ergaenzt'         => ['it' => 'Grazie, ho salvato le sue aggiunte.', 'de' => 'Danke, Ihre Ergänzungen sind gespeichert.', 'en' => 'Thank you, your additions are saved.'],
        'weiter'     => ['it' => 'Avanti', 'de' => 'Weiter', 'en' => 'Continue'],
        'zurueck'    => ['it' => 'Indietro', 'de' => 'Zurück', 'en' => 'Back'],
        'letzter'    => ['it' => 'Ultimo passo — poi ha finito.', 'de' => 'Letzter Schritt — dann haben Sie es geschafft.', 'en' => 'Last step — then you’re done.'],
        'leerOk'     => ['it' => 'Quello che non sa, lo lasci pure vuoto.',
                         'de' => 'Was Sie nicht wissen, lassen Sie einfach leer.',
                         'en' => 'Leave anything you don’t know blank.'],
        'autoOk'     => ['it' => 'Salvo automaticamente a ogni passo. Può chiudere e tornare quando vuole.',
                         'de' => 'Ich speichere bei jedem Schritt automatisch. Sie können die Seite schließen und später zurückkommen.',
                         'en' => 'I save at every step. You can close this and come back any time.'],
        'weiterMachen' => ['it' => 'Continuare il questionario', 'de' => 'Fragebogen weiter ausfüllen', 'en' => 'Continue the questionnaire'],
        /* Der Weg ohne Tastatur. Er steht direkt unter dem Fragebogen-Knopf:
           Genau in dem Moment, in dem jemand vor 48 Feldern steht und sie auf
           morgen verschieben will, ist der Satz „das geht auch am Telefon"
           die einzige Werbung, die etwas nuetzt. */
        /* Das Angebot auf der Kundenseite (22.09.2026). Bis dahin fuehrte der
           einzige Weg dorthin ueber die E-Mail -- wer sie nicht mehr fand,
           stand auf seiner Seite vor einem Satz ohne Knopf. */
        'angebotAnsehen' => ['it' => 'Vedere il preventivo',
                             'de' => 'Angebot ansehen',
                             'en' => 'View your quote'],
        'angebotText' => [
            'it' => 'Lo legga con calma. Se va bene, lo accetta lì; se qualcosa non torna, me lo scriva.',
            'de' => 'Sehen Sie es sich in Ruhe an. Passt es, nehmen Sie es dort an; passt etwas nicht, schreiben Sie mir.',
            'en' => 'Take your time. If it works, you accept it there; if something doesn’t fit, tell me.',
        ],
        'angebotGilt' => ['it' => 'Valido fino al', 'de' => 'Gültig bis', 'en' => 'Valid until'],
        'fragebogenTelefon' => [
            'it' => 'Oppure senza tastiera: clicchi sulla finestra vocale qui in basso a destra — Manuela, la nostra assistente, compila il questionario insieme a Lei. Manuela chiede, Lei racconta; ogni risposta viene salvata subito.',
            'de' => 'Oder ganz ohne Tippen: Klicken Sie auf das Sprachfenster unten rechts — Manuela, unsere Assistentin, füllt den Fragebogen gemeinsam mit Ihnen aus. Manuela fragt, Sie erzählen; jede Antwort wird sofort gespeichert.',
            'en' => 'Or skip the typing: click the voice window in the bottom right — Manuela, our assistant, fills in the questionnaire together with you. She asks, you talk; every answer is saved right away.',
        ],

        /* DIE WUNSCHDOMAIN
           ------------------------------------------------------------------
           Wer im Fragebogen sagt "keine Website, keine Domain", bekommt hier
           die erste freie Wunschdomain angeboten. Der Preis steht im Satz,
           BEVOR der Knopf kommt — die Zustimmung gilt den Kosten, nicht nur
           der Domain. Angelegt wird erst nach der finalen Freigabe; auch das
           steht ausdruecklich da, damit niemand am naechsten Tag nach seinen
           Zugangsdaten fragt. */
        'hostingTitel' => ['it' => 'Il suo dominio', 'de' => 'Ihre Wunschdomain', 'en' => 'Your domain'],
        'hostingTitelHosting' => ['it' => 'Il suo hosting', 'de' => 'Ihr Hosting', 'en' => 'Your hosting'],
        'hostingAngebot' => [
            'it' => 'Nel questionario ha indicato che non ha ancora un sito né un dominio. Registriamo e gestiamo noi {domain} per Lei: dominio, spazio web, certificato SSL e una casella e-mail. Costa {preis} al mese in più (12 mesi di durata minima, poi può disdire a fine mese). Attiviamo tutto quando il suo sito è pronto — e riceverà i suoi dati di accesso qui su questa pagina.',
            'de' => 'Im Fragebogen haben Sie angegeben, dass Sie noch keine Website und keine Domain haben. Wir schalten und betreuen {domain} für Sie: Domain, Speicherplatz, SSL-Zertifikat und ein E-Mail-Postfach. Das kostet zusätzlich {preis} im Monat (12 Monate Mindestlaufzeit, danach zum Monatsende kündbar). Angelegt wird alles, sobald Ihre Website fertig ist — Ihre Zugangsdaten bekommen Sie dann hier auf dieser Seite.',
            'en' => 'In the questionnaire you said you don’t have a website or a domain yet. We’ll register and run {domain} for you: domain, web space, SSL certificate and an email mailbox. It costs an extra {preis} per month (12-month minimum term, then cancel at month’s end). Everything is set up once your website is finished — you’ll receive your access details right here on this page.',
        ],
        /* Der Ja-Knopf ist der Vertragsschluss — also sagt er es auch:
           "mit Zahlungspflicht", wie es das Fernabsatzrecht verlangt
           (Button-Loesung; it: obbligo di pagare). */
        /* DER KASTEN AUS DEN DREI ENTSCHEIDUNGEN (25.09.2026)
           Hosting.php::angebotText setzt ihn aus dem zusammen, was der Kunde
           im Fragebogen gewaehlt hat. Genau dieser Text wird bei "Ja" als
           Zustimmung gespeichert -- er muss deshalb vollstaendig sagen, was
           passiert und was es kostet. */
        'hostingWahlEinleitung' => [
            'it' => 'Nel questionario ha scelto di far ospitare il sito da Vecom Design.',
            'de' => 'Sie haben im Fragebogen gewählt, dass die Website bei Vecom Design laufen soll.',
            'en' => 'In the questionnaire you chose to have the website hosted by Vecom Design.',
        ],
        'hostingDomain_neu' => [
            'it' => 'Registriamo {domain} a nome Suo e lo gestiamo per Lei.',
            'de' => 'Wir registrieren {domain} auf Ihren Namen und betreuen sie für Sie.',
            'en' => 'We register {domain} in your name and manage it for you.',
        ],
        'hostingDomain_transfer' => [
            'it' => 'Il suo dominio {domain} passa a noi — il titolare resta Lei. Per il trasferimento ci serve poi il codice Auth dal suo fornitore attuale; le impostazioni della sua e-mail le riprendiamo invariate.',
            'de' => 'Ihre Domain {domain} zieht zu uns um — Inhaber bleiben Sie. Für den Umzug brauchen wir später den Auth-Code von Ihrem bisherigen Anbieter; die Einträge Ihrer E-Mail übernehmen wir unverändert.',
            'en' => 'Your domain {domain} moves to us — you stay the owner. For the transfer we will need the Auth code from your current provider; your email settings are carried over unchanged.',
        ],
        'hostingDomain_behalten' => [
            'it' => 'Il suo dominio {domain} resta dal fornitore attuale. Lì si cambia solo la voce che punta al sito — la sua e-mail non viene toccata.',
            'de' => 'Ihre Domain {domain} bleibt bei Ihrem bisherigen Anbieter. Dort wird nur der Eintrag geändert, der auf die Website zeigt — Ihre E-Mail bleibt davon unberührt.',
            'en' => 'Your domain {domain} stays with your current provider. Only the record that points to the website is changed there — your email is not touched.',
        ],
        'hostingDomain_offen' => [
            'it' => 'Che cosa succede con il suo dominio {domain} lo decidiamo prima insieme — senza il suo sì esplicito non trasferiamo nulla.',
            'de' => 'Was mit Ihrer Domain {domain} geschieht, besprechen wir vorher mit Ihnen — ohne Ihr ausdrückliches Ja wird nichts übertragen.',
            'en' => 'What happens with your domain {domain} we agree with you first — nothing is transferred without your explicit yes.',
        ],
        'hostingUmfang' => [
            'it' => 'Incluso: {gb} di spazio web e il certificato SSL{mail}.',
            'de' => 'Enthalten: {gb} Speicherplatz und SSL-Zertifikat{mail}.',
            'en' => 'Included: {gb} of web space and an SSL certificate{mail}.',
        ],
        'hostingUmfangMail' => [
            'it' => ', più una casella e-mail info@{domain}',
            'de' => ', dazu ein E-Mail-Postfach info@{domain}',
            'en' => ', plus an email mailbox info@{domain}',
        ],
        'hostingPreisSatz' => [
            'it' => 'Costa {preis} al mese in più ({monate} mesi di durata minima, poi disdetta a fine mese).',
            'de' => 'Das kostet zusätzlich {preis} im Monat ({monate} Monate Mindestlaufzeit, danach zum Monatsende kündbar).',
            'en' => 'It costs an extra {preis} per month ({monate}-month minimum term, then cancel at month’s end).',
        ],
        'hostingWann' => [
            'it' => 'Attiviamo tutto quando il suo sito è pronto e la prima rata mensile è pagata — i dati di accesso li riceve qui su questa pagina.',
            'de' => 'Angelegt wird alles, sobald Ihre Website fertig und die erste Monatsrate bezahlt ist — Ihre Zugangsdaten bekommen Sie dann hier auf dieser Seite.',
            'en' => 'Everything is set up once your website is finished and the first monthly instalment is paid — you’ll receive your access details right here on this page.',
        ],
        'hostingUmfangMailFertig' => [
            'it' => 'spazio web, casella e-mail e il suo account personale',
            'de' => 'Speicherplatz, E-Mail-Postfach und Ihrem eigenen Account',
            'en' => 'web space, an email mailbox and your own account',
        ],
        'hostingUmfangFertig' => [
            'it' => 'spazio web e il suo account personale',
            'de' => 'Speicherplatz und Ihrem eigenen Account',
            'en' => 'web space and your own account',
        ],
        'hostingJa'   => ['it' => 'Sì, ordino con obbligo di pagare — {preis} al mese',
                          'de' => 'Ja, zahlungspflichtig bestellen — {preis} im Monat',
                          'en' => 'Yes, order with obligation to pay — {preis} per month'],
        'vertragsblatt' => ['it' => 'Foglio del contratto (PDF)',
                            'de' => 'Vertragsblatt (PDF)',
                            'en' => 'Contract sheet (PDF)'],
        'hostingNein' => ['it' => 'No, grazie', 'de' => 'Nein, danke', 'en' => 'No, thanks'],
        'hostingDanke' => [
            'it' => 'Perfetto — appena il suo sito è pronto le mandiamo la prima rata mensile; quando è pagata attiviamo tutto e le mettiamo qui i dati di accesso.',
            'de' => 'Sehr gern — sobald Ihre Website fertig ist, kommt die erste Monatsrate zu Ihnen; ist sie bezahlt, schalten wir alles und legen Ihnen hier die Zugangsdaten bereit.',
            'en' => 'Great — once your website is finished, the first monthly instalment comes to you; when it is paid, we set everything up and put your access details here.',
        ],
        'hostingDankeSolo' => [
            'it' => 'Perfetto — le abbiamo mandato la prima rata mensile per e-mail. Appena il pagamento arriva, attiviamo tutto e i suoi dati di accesso compaiono qui.',
            'de' => 'Sehr gern — die erste Monatsrate kommt per E-Mail zu Ihnen. Sobald die Zahlung da ist, schalten wir alles, und Ihre Zugangsdaten erscheinen hier.',
            'en' => 'Great — the first monthly instalment is on its way to you by email. As soon as the payment arrives, we set everything up and your access details appear here.',
        ],
        'hostingAbgelehnt' => [
            'it' => 'Va bene, senza. Se cambia idea, ce lo scriva qui nella pagina.',
            'de' => 'In Ordnung, dann ohne. Falls Sie es sich anders überlegen, schreiben Sie uns einfach hier auf der Seite.',
            'en' => 'All right, we’ll skip it. If you change your mind, just write to us here on this page.',
        ],
        'hostingWartet' => [
            'it' => '{domain} è previsto per Lei — lo attiviamo quando il sito è pronto e la prima rata mensile è pagata.',
            'de' => '{domain} ist für Sie vorgemerkt — wir schalten sie, sobald Ihre Website fertig und die erste Monatsrate bezahlt ist.',
            'en' => '{domain} is reserved for you — we’ll set it up once your website is finished and the first monthly instalment is paid.',
        ],
        /* Die Solo-Fassungen: kein Fragebogen, keine Website — hier kommt
           jemand NUR fuer Domain und Hosting. Der Satz zum Angebot nennt
           deshalb nicht den Fragebogen, und nach der Zustimmung wartet
           nichts auf eine fertige Seite, sondern auf die erste Zahlung. */
        'hostingAngebotSolo' => [
            'it' => 'Il dominio {domain} è libero — glielo registriamo e gestiamo noi: dominio, {gb} di spazio web, certificato SSL e una casella e-mail, con i suoi dati di accesso. Costa {preis} al mese (12 mesi di durata minima, poi può disdire a fine mese). Appena arriva il primo pagamento mensile, attiviamo tutto — e i suoi dati di accesso compaiono qui su questa pagina.',
            'de' => 'Die Domain {domain} ist frei — wir registrieren und betreuen sie für Sie: Domain, {gb} Speicherplatz, SSL-Zertifikat und ein E-Mail-Postfach, mit Ihren eigenen Zugangsdaten. Das kostet {preis} im Monat (12 Monate Mindestlaufzeit, danach zum Monatsende kündbar). Sobald Ihre erste Monatszahlung da ist, schalten wir alles — Ihre Zugangsdaten erscheinen dann hier auf dieser Seite.',
            'en' => 'The domain {domain} is available — we’ll register and manage it for you: domain, {gb} of web space, SSL certificate and an email mailbox, with your own access details. It costs {preis} per month (12-month minimum term, then cancel at month’s end). As soon as your first monthly payment arrives, we set everything up — your access details will then appear right here on this page.',
        ],
        /* Phase 3: bezahlt, und es wird gerade eingerichtet -- "wartet auf
           die Zahlung" waere hier falsch. */
        'hostingInArbeit' => [
            'it' => '{domain}: il pagamento è arrivato, stiamo attivando tutto. Appena è pronto, i suoi dati di accesso compaiono qui.',
            'de' => '{domain}: Die Zahlung ist da, wir richten gerade alles ein. Sobald es steht, erscheinen Ihre Zugangsdaten hier.',
            'en' => '{domain}: your payment has arrived and we are setting everything up. As soon as it is ready, your access details appear here.',
        ],
        'hostingWartetZahlung' => [
            'it' => '{domain} è riservato per Lei. Le abbiamo mandato la prima rata mensile — appena il pagamento arriva, attiviamo tutto e i dati di accesso compaiono qui.',
            'de' => '{domain} ist für Sie vorgemerkt. Die erste Monatsrate ist unterwegs zu Ihnen — sobald die Zahlung da ist, schalten wir alles, und die Zugangsdaten erscheinen hier.',
            'en' => '{domain} is reserved for you. The first monthly instalment is on its way to you — as soon as the payment arrives, we set everything up and your access details appear here.',
        ],
        'hostingFertig' => [
            'it' => '{domain} è attivo. Se le servono di nuovo i dati di accesso, ce lo scriva — ne impostiamo di nuovi.',
            'de' => '{domain} ist geschaltet. Brauchen Sie die Zugangsdaten noch einmal, geben Sie uns Bescheid — wir setzen neue.',
            'en' => '{domain} is up and running. If you need your access details again, let us know — we’ll set new ones.',
        ],
        /* Der einmalige Abruf: Die Daten liegen verschluesselt und werden mit
           dem Anzeigen geloescht. Deshalb die Rueckfrage vor dem Klick und
           der deutliche Satz danach. */
        'hostingZugangHilfe' => [
            'it' => 'I suoi dati di accesso sono pronti. Vengono mostrati una sola volta — poi li cancelliamo da qui.',
            'de' => 'Ihre Zugangsdaten liegen bereit. Sie werden genau einmal angezeigt — danach löschen wir sie hier.',
            'en' => 'Your access details are ready. They are shown exactly once — after that we delete them from here.',
        ],
        'hostingZugangKnopf' => ['it' => 'Mostrare i dati di accesso (una volta sola)',
                                 'de' => 'Zugangsdaten einmalig anzeigen',
                                 'en' => 'Show access details (one time only)'],
        'hostingZugangSicher' => [
            'it' => 'Mostrare adesso? Funziona una sola volta — tenga pronto dove salvarli.',
            'de' => 'Jetzt anzeigen? Das geht nur ein einziges Mal — halten Sie bereit, wo Sie sie speichern.',
            'en' => 'Show them now? This works only once — have somewhere ready to save them.',
        ],
        'hostingZugangAendern' => [
            'it' => 'Consiglio: dopo aver salvato i dati, cambi le password nel pannello KAS (kas.all-inkl.com) — così le conosce solo Lei.',
            'de' => 'Tipp: Ändern Sie die Passwörter nach dem Speichern im KAS-Kundenmenü (kas.all-inkl.com) — dann kennen nur noch Sie sie.',
            'en' => 'Tip: after saving, change the passwords in the KAS panel (kas.all-inkl.com) — then only you know them.',
        ],
        'hostingZugangJetzt' => [
            'it' => 'Salvi questi dati ADESSO — è l’unica volta che vengono mostrati.',
            'de' => 'Speichern Sie diese Daten JETZT — sie werden nur dieses eine Mal angezeigt.',
            'en' => 'Save these details NOW — this is the only time they are shown.',
        ],
        'hostingZugangWeg' => [
            'it' => 'I dati di accesso non sono più disponibili qui. Ce lo scriva e ne impostiamo di nuovi.',
            'de' => 'Die Zugangsdaten sind hier nicht mehr hinterlegt. Geben Sie uns Bescheid, dann setzen wir neue.',
            'en' => 'The access details are no longer stored here. Let us know and we’ll set new ones.',
        ],

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
        'wasDrin'     => ['it' => 'Le voci spuntate sono quelle del preventivo che ha accettato. Può togliere e aggiungere: quello che aggiunge lo guardo io e le scrivo.',
                          'de' => 'Angehakt ist, was in Ihrem angenommenen Angebot steht. Sie dürfen wegnehmen und dazunehmen — was dazukommt, sehe ich mir an und melde mich dazu.',
                          'en' => 'The ticked items are the ones in the quote you accepted. Feel free to remove or add — anything you add, I’ll look at and come back to you about.'],
        'nichtDrin'   => ['it' => 'Non è ancora nel preventivo — le scrivo prima di iniziare.',
                          'de' => 'Das ist im Angebot noch nicht enthalten — ich melde mich dazu, bevor ich anfange.',
                          'en' => 'That isn’t in the quote yet — I’ll come back to you before I start.'],
        'wenigerDrin' => ['it' => 'Questo era nel preventivo. Se non le serve più, me lo dica pure — ne parliamo.',
                          'de' => 'Das stand im Angebot. Wenn Sie es nicht mehr brauchen, sagen Sie ruhig Bescheid — wir sprechen darüber.',
                          'en' => 'That was in the quote. If you no longer need it, do say — we’ll talk it through.'],
        'seitenHilfe' => ['it' => 'Conta anche la pagina iniziale.',
                          'de' => 'Die Startseite zählt mit.',
                          'en' => 'The home page counts too.'],
    ];

    /** Die Seite, auf der der Kunde seinem Projekt zusieht. */
    public const PROJEKT = [
        'titel'       => ['it' => 'Il suo progetto', 'de' => 'Ihr Projekt', 'en' => 'Your project'],
        'stand'       => ['it' => 'A che punto siamo', 'de' => 'Wo wir stehen', 'en' => 'Where we are'],
        'vorschau'    => ['it' => 'Vedere l’anteprima', 'de' => 'Vorschau ansehen', 'en' => 'View the preview'],
        'fragebogen'  => ['it' => 'Compilare il questionario', 'de' => 'Zum Fragebogen', 'en' => 'Fill in the questionnaire'],
        'fragebogenOffen' => ['it' => 'Il questionario è ancora aperto — senza quelle informazioni non possiamo andare avanti.',
                              'de' => 'Der Fragebogen ist noch offen — ohne die Angaben kommen wir nicht weiter.',
                              'en' => 'The questionnaire is still open — we can’t move on without it.'],
        'nachrichten' => ['it' => 'Messaggi', 'de' => 'Nachrichten', 'en' => 'Messages'],
        'schreiben'   => ['it' => 'Scrivere un messaggio', 'de' => 'Nachricht schreiben', 'en' => 'Write a message'],
        'senden'      => ['it' => 'Invia', 'de' => 'Absenden', 'en' => 'Send'],
        'gesendet'    => ['it' => 'Messaggio inviato. Rispondo il prima possibile.',
                          'de' => 'Nachricht ist raus. Ich melde mich so schnell wie möglich.',
                          'en' => 'Message sent. I’ll get back to you as soon as I can.'],
        'nochNichts'  => ['it' => 'Ancora nessun messaggio.', 'de' => 'Noch keine Nachrichten.', 'en' => 'No messages yet.'],
        'du'          => ['it' => 'Lei', 'de' => 'Sie', 'en' => 'You'],
        'wir'         => ['it' => 'Vecom Design', 'de' => 'Vecom Design', 'en' => 'Vecom Design'],
        'dateien'     => ['it' => 'File', 'de' => 'Dateien', 'en' => 'Files'],
        'hochladen'   => ['it' => 'Caricare file', 'de' => 'Datei hochladen', 'en' => 'Upload a file'],
        'dateiHinweis'=> ['it' => 'Foto, logo, testi, PDF — al massimo {max} per file.',
                          'de' => 'Fotos, Logo, Texte, PDF — höchstens {max} je Datei.',
                          'en' => 'Photos, logo, copy, PDFs — {max} per file at most.'],
        'dateiOk'     => ['it' => 'File ricevuto. Grazie!', 'de' => 'Datei ist da. Danke!', 'en' => 'File received. Thank you!'],
        'keineDateien'=> ['it' => 'Ancora nessun file.', 'de' => 'Noch keine Dateien.', 'en' => 'No files yet.'],
        'vonUns'      => ['it' => 'da me', 'de' => 'von mir', 'en' => 'from me'],
        'vonDir'      => ['it' => 'da Lei', 'de' => 'von Ihnen', 'en' => 'from you'],
        'leer'        => ['it' => 'Scriva qualcosa prima di inviare.', 'de' => 'Bitte schreiben Sie etwas, bevor Sie absenden.', 'en' => 'Please write something first.'],
        'belege'      => ['it' => 'Ricevute', 'de' => 'Belege', 'en' => 'Receipts'],
        'schauen'     => ['it' => 'Dia un’occhiata', 'de' => 'Sehen Sie es sich an', 'en' => 'Take a look'],
        'schauenText' => ['it' => 'La bozza è visibile. La guardi con calma e mi scriva cosa ne pensa — non deve approvare niente adesso. Quando il sito è finito la avviso, e solo allora potrà dare il via libera.',
                          'de' => 'Der Entwurf ist für Sie freigeschaltet. Sehen Sie ihn sich in Ruhe an und schreiben Sie mir, was Ihnen auffällt — freigeben müssen Sie noch nichts. Wenn die Seite fertig ist, melde ich mich; erst dann können Sie sie abnehmen.',
                          'en' => 'The draft is open for you. Take your time and tell me what you notice — you don’t have to approve anything yet. When the site is finished I’ll let you know; only then can you sign it off.'],
        'kosten'      => ['it' => 'Le modifiche che rientrano in quanto concordato sono comprese. Se una richiesta va oltre, glielo dico prima e riceve il preventivo con il prezzo — senza il suo ok non parte niente.',
                          'de' => 'Änderungen im vereinbarten Umfang sind enthalten. Geht ein Wunsch darüber hinaus, sage ich es Ihnen vorher und schicke Ihnen ein Angebot mit dem Preis — ohne Ihr Ja passiert nichts.',
                          'en' => 'Changes within the agreed scope are included. If a request goes beyond that, I’ll say so first and send you a quote with the price — nothing happens without your go-ahead.'],
        'freigabe'    => ['it' => 'Il sito è pronto — decida Lei', 'de' => 'Die Seite ist fertig — jetzt entscheiden Sie', 'en' => 'The site is ready — it’s your call'],
        'freigabeText'=> ['it' => 'Se il sito va bene così, lo approvi pure — poi lo pubblico. Se qualcosa non va, me lo scriva: lo sistemo.',
                          'de' => 'Wenn die Seite so passt, geben Sie sie frei — dann veröffentliche ich. Wenn etwas nicht stimmt, schreiben Sie es mir: Ich ändere es.',
                          'en' => 'If the site is right, approve it — then I publish. If something is off, tell me: I’ll change it.'],
        'freigeben'   => ['it' => 'Va bene così — si può pubblicare', 'de' => 'Passt so — veröffentlichen', 'en' => 'Looks good — publish it'],
        'aendern'     => ['it' => 'Vorrei delle modifiche', 'de' => 'Ich möchte Änderungen', 'en' => 'I’d like changes'],
        'freigegeben' => ['it' => 'Grazie! Mi metto subito a pubblicare.',
                          'de' => 'Danke! Ich kümmere mich gleich um die Veröffentlichung.',
                          'en' => 'Thank you! I’ll get it published right away.'],
        'aenderungOk' => ['it' => 'Ricevuto. Ci metto mano.', 'de' => 'Angekommen. Ich mache mich dran.', 'en' => 'Got it. I’m on it.'],
        'aendernWie'  => ['it' => 'Scriva cosa cambiare prima di inviare.',
                          'de' => 'Schreiben Sie bitte dazu, was geändert werden soll.',
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
        'hallo'      => ['it' => 'Buongiorno {name}', 'de' => 'Guten Tag {name}', 'en' => 'Hi {name}'],
        /* Wer ueber den E-Mail-Einstieg kommt, hat noch keinen Namen genannt
           (D2: gefragt wird erst im Vorhaben). „Guten Tag ,“ waere die Folge. */
        'halloOhne'  => ['it' => 'Buongiorno', 'de' => 'Guten Tag', 'en' => 'Hello'],
        'willkommen' => [
            'it' => 'Benvenuto nella sua dashboard personale. Da qui passa tutto, fino alla consegna del sito. La salvi tra i preferiti: il link resta valido.',
            'de' => 'Willkommen in Ihrem persönlichen Dashboard. Hier läuft alles bis zur Übergabe Ihrer Website. Legen Sie die Seite als Lesezeichen ab, der Link bleibt gültig.',
            'en' => 'Welcome to your personal dashboard. Everything runs through here until your website is handed over. Bookmark this page, the link stays valid.'],
        'vorhabenDanke' => [
            'it' => 'Grazie, il suo progetto è arrivato. Le ho appena inviato una conferma via e-mail.',
            'de' => 'Danke, Ihr Vorhaben ist angekommen. Eine Bestätigung ist gerade per E-Mail unterwegs.',
            'en' => 'Thank you, your project has arrived. A confirmation is on its way by email.'],
        /* Auf seiner Seite, damit er die Nummer von seinen Belegen
           wiederfindet, ohne ein PDF aufmachen zu muessen. */
        'kundennr'   => ['it' => 'N. cliente', 'de' => 'Kundennummer', 'en' => 'Customer no.'],
        'titel'      => ['it' => 'Il suo progetto', 'de' => 'Ihr Projekt', 'en' => 'Your project'],
        'duBistDran' => ['it' => 'Tocca a Lei', 'de' => 'Jetzt sind Sie gefragt', 'en' => 'Over to you'],
        'wirSindDran'=> ['it' => 'Ci penso io', 'de' => 'Ich bin dran', 'en' => 'I am on it'],
        'nichtsOffen'=> ['it' => 'Tutto a posto', 'de' => 'Alles erledigt', 'en' => 'All done'],
        'gespraech'  => ['it' => 'Mi scriva', 'de' => 'Schreiben Sie mir', 'en' => 'Write to me'],
        'gespraechHilfe' => [
            'it' => 'Qui rispondo io — di solito entro un giorno lavorativo.',
            'de' => 'Hier antworte ich Ihnen — meist innerhalb eines Werktags.',
            'en' => 'I answer here — usually within one working day.'],
        /* "Unterlagen" und "Dateien" standen frueher untereinander und klangen
           gleich. Das eine sind Belege von uns, das andere sein Material. */
        /* DIE SEITE ZUM MITNEHMEN
           ------------------------------------------------------------------
           Kein Fachwort, keine Drohung. Der Kasten sagt, wofuer das Paket gut
           ist — umziehen, sichern, weitergeben —, und dass niemand etwas
           kuendigen muss, um es zu bekommen. Wer ein ZIP ohne diesen Satz
           bekommt, legt es weg und fragt sich, ob das ein Abschied war. */
        'paket'      => ['it' => 'Il suo sito da portare con sé',
                         'de' => 'Ihre Website zum Mitnehmen',
                         'en' => 'Your website to take with you'],
        'paketHilfe' => [
            'it' => 'Tutti i file del suo sito in un unico pacchetto. È suo: le serve per cambiare hosting, per una copia di sicurezza, o se un giorno ci lavora qualcun altro. Il sito resta online come prima.',
            'de' => 'Alle Dateien Ihrer Website in einem Paket. Es gehört Ihnen: für einen Anbieterwechsel, als Sicherung, oder falls einmal jemand anderes daran arbeitet. Die Seite bleibt online wie bisher.',
            'en' => 'Every file of your site in one package. It’s yours: for moving to another host, as a backup, or if someone else works on it one day. The site stays online as before.'],
        'paketHolen' => ['it' => 'Scaricare il pacchetto', 'de' => 'Paket herunterladen', 'en' => 'Download the package'],
        'unterlagen' => ['it' => 'Ricevute e fatture', 'de' => 'Belege und Rechnungen', 'en' => 'Receipts and invoices'],
        'dateien'    => ['it' => 'Il suo materiale', 'de' => 'Ihr Material', 'en' => 'Your material'],
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
            'it' => 'Ha già logo, foto o testi? Li carichi qui — con quelli posso partire davvero.',
            'de' => 'Haben Sie Logo, Fotos oder Texte schon da? Laden Sie sie hier hoch — damit kann ich wirklich anfangen.',
            'en' => 'Do you already have a logo, photos or copy? Upload them here — with those I can really start.'],
        'materialWie' => [
            'it' => 'Va bene tutto: foto dal telefono, un PDF, il vecchio volantino. Meglio troppo che troppo poco — scelgo io.',
            'de' => 'Alles ist recht: Handyfotos, ein PDF, der alte Flyer. Lieber zu viel als zu wenig — aussuchen kann ich.',
            'en' => 'Anything helps: phone photos, a PDF, the old flyer. Better too much than too little — I can pick.'],
        'materialKnopf' => [
            'it' => 'Caricare il materiale', 'de' => 'Material hochladen', 'en' => 'Upload your material'],
        'materialDa' => [
            'it' => 'Ricevuto, grazie. Se arriva altro, lo carichi pure — meglio adesso che dopo.',
            'de' => 'Angekommen, danke. Wenn noch etwas dazukommt, laden Sie es ruhig hoch — jetzt ist besser als später.',
            'en' => 'Received, thank you. If more turns up, do upload it — sooner is better than later.'],

        'deineSeite' => ['it' => 'Il suo sito', 'de' => 'Ihre Website', 'en' => 'Your website'],
        /* Phase 2: automatisch abbuchen. abbuchungZustimmung ist der Wortlaut,
           der als Zustimmung gespeichert wird -- aendern heisst: FASSUNG in
           Abbuchung.php hochzaehlen. */
        /* Phase 6b: E-Mail-Umzug. mailumzugZustimmung ist der gespeicherte Wortlaut. */
        'mailumzugTitel' => ['it' => 'Trasferimento delle e-mail', 'de' => 'Umzug Ihrer E-Mails', 'en' => 'Moving your emails'],
        'mailumzugZustimmung' => [
            'it' => 'Incarico Vecom Design di copiare tutte le e-mail e cartelle da {alt} a {neu}. Presso il fornitore attuale non viene cancellato né modificato nulla (le e-mail restano anche non lette). Per {tage} giorni dopo la prima copia vengono riprese anche le e-mail nuove; poi le password qui inserite vengono cancellate.',
            'de' => 'Ich beauftrage Vecom Design, alle E-Mails und Ordner von {alt} nach {neu} zu kopieren. Beim bisherigen Anbieter wird nichts gelöscht oder verändert (auch ungelesene Mails bleiben ungelesen). {tage} Tage nach der ersten Kopie werden neu eingehende Mails noch nachgeholt; danach werden die hier eingegebenen Passwörter gelöscht.',
            'en' => 'I engage Vecom Design to copy all emails and folders from {alt} to {neu}. Nothing is deleted or changed at the current provider (unread mails stay unread). For {tage} days after the first copy, newly arriving mails are picked up too; then the passwords entered here are deleted.'],
        'mailumzugHilfe' => ['it' => 'Gmail: serve una „password per le app“. Microsoft 365/Outlook spesso non permette l’accesso IMAP con password — in quel caso ci pensiamo noi.', 'de' => 'Gmail: Sie brauchen ein „App-Passwort“. Microsoft 365/Outlook erlaubt IMAP oft nicht mit Passwort — dann übernehmen wir das von Hand.', 'en' => 'Gmail: you need an “app password”. Microsoft 365/Outlook often does not allow IMAP with a password — then we take care of it by hand.'],
        'mailumzugAltPass' => ['it' => 'Password della casella attuale', 'de' => 'Passwort des bisherigen Postfachs', 'en' => 'Password of the current mailbox'],
        'mailumzugNeuPass' => ['it' => 'Password della nuova casella', 'de' => 'Passwort des neuen Postfachs', 'en' => 'Password of the new mailbox'],
        'mailumzugServer'  => ['it' => 'Server (lo troviamo noi, se lo lascia vuoto)', 'de' => 'Server (leer lassen — wir finden ihn)', 'en' => 'Server (leave empty — we find it)'],
        'mailumzugKnopf'   => ['it' => 'Acconsento e avvio il trasferimento', 'de' => 'Zustimmen und Umzug starten', 'en' => 'Agree and start the move'],
        'mailumzugLaeuft'  => ['it' => 'In corso: {kopiert} di {gesamt} e-mail copiate. Continua da sé, può chiudere la pagina.', 'de' => 'Läuft: {kopiert} von {gesamt} E-Mails kopiert. Das geht von selbst weiter — Sie können die Seite schließen.', 'en' => 'Running: {kopiert} of {gesamt} emails copied. It continues on its own — you can close the page.'],
        'mailumzugFertig'  => ['it' => 'Fatto: {kopiert} e-mail sono nella nuova casella. Fino al {datum} riprendiamo anche quelle nuove.', 'de' => 'Fertig: {kopiert} E-Mails sind im neuen Postfach. Bis zum {datum} holen wir auch neu eingehende nach.', 'en' => 'Done: {kopiert} emails are in the new mailbox. Until {datum} we also pick up newly arriving ones.'],
        'mailumzugFehler'  => ['it' => 'Non ha funzionato: {fehler} Controlli i dati e riprovi.', 'de' => 'Das hat nicht geklappt: {fehler} Bitte die Angaben prüfen und noch einmal.', 'en' => 'That did not work: {fehler} Please check the details and try again.'],
        'mailumzugFehlt'   => ['it' => 'Servono le password di entrambe le caselle.', 'de' => 'Es braucht die Passwörter beider Postfächer.', 'en' => 'Both mailbox passwords are needed.'],
        /* 26.09.2026: Das neue Postfach gibt es noch nicht -- dann keine Passwortabfrage. */
        'mailumzugWartet'  => ['it' => 'La nuova casella non esiste ancora — il trasferimento parte appena è attiva.', 'de' => 'Das neue Postfach gibt es noch nicht — der Umzug startet, sobald es eingerichtet ist.', 'en' => 'The new mailbox doesn’t exist yet — the move starts as soon as it is set up.'],
        'mailumzugWartetText' => ['it' => 'Appena la casella {neu} è pronta, qui potrà avviare il trasferimento. Le scriviamo noi.', 'de' => 'Sobald das Postfach {neu} eingerichtet ist, können Sie hier den Umzug starten. Wir sagen Ihnen Bescheid.', 'en' => 'As soon as the mailbox {neu} is set up, you can start the move here. We’ll let you know.'],
        'mailumzugDa'      => ['it' => 'Grazie — il trasferimento parte a minuti.', 'de' => 'Danke — der Umzug startet in den nächsten Minuten.', 'en' => 'Thank you — the move starts within minutes.'],
        /* Phase 6c: 1:1-Umzug der Website auf der Kundenseite. seitenumzugZustimmung
           ist der Wortlaut, der gespeichert wird -- aendern heisst FASSUNG hochzaehlen. */
        'seitenumzugTitel' => ['it' => 'Trasferimento del suo sito', 'de' => 'Umzug Ihrer Website', 'en' => 'Moving your website'],
        'seitenumzugZustimmung' => [
            'it' => 'Incarico Vecom Design di trasferire da loro il mio sito {adresse} così com’è. A questo scopo fornisco l’accesso al mio spazio web attuale. Lì non viene modificato né cancellato nulla; prima si fa una copia di sicurezza. I dati di accesso sono conservati cifrati e cancellati a trasferimento concluso, al più tardi dopo {tage} giorni. Dopo conviene cambiare la password.',
            'de' => 'Ich beauftrage Vecom Design, meine Website {adresse} so, wie sie ist, zu Vecom umzuziehen. Dafür gebe ich den Zugang zu meinem bisherigen Webspace. Dort wird nichts verändert oder gelöscht; zuerst wird eine Sicherung angelegt. Die Zugangsdaten werden verschlüsselt aufbewahrt und nach dem Umzug gelöscht, spätestens nach {tage} Tagen. Danach ändere ich das Passwort am besten.',
            'en' => 'I engage Vecom Design to move my website {adresse} to them exactly as it is. For this I provide access to my current web space. Nothing there is changed or deleted; a backup is made first. The access details are stored encrypted and deleted once the move is done, at the latest after {tage} days. Afterwards I should change the password.'],
        'seitenumzugHilfe' => ['it' => 'Li trova nel pannello del suo fornitore attuale, alla voce FTP. Il database serve solo se il sito ne usa uno (per es. WordPress).', 'de' => 'Sie finden sie im Kundenbereich Ihres bisherigen Anbieters unter FTP. Die Datenbank nur, wenn die Seite eine hat (z. B. WordPress).', 'en' => 'You find them in your current provider’s customer area under FTP. The database only if the site uses one (e.g. WordPress).'],
        'seitenumzugFtpHost' => ['it' => 'Server FTP', 'de' => 'FTP-Server', 'en' => 'FTP server'],
        'seitenumzugFtpUser' => ['it' => 'Utente FTP', 'de' => 'FTP-Benutzer', 'en' => 'FTP user'],
        'seitenumzugFtpPass' => ['it' => 'Password FTP', 'de' => 'FTP-Passwort', 'en' => 'FTP password'],
        'seitenumzugDb'      => ['it' => 'Database (se c’è)', 'de' => 'Datenbank (falls vorhanden)', 'en' => 'Database (if any)'],
        'seitenumzugKnopf'   => ['it' => 'Acconsento e invio i dati', 'de' => 'Zustimmen und Zugang übermitteln', 'en' => 'Agree and send access'],
        'seitenumzugDa'      => ['it' => 'Grazie — i dati sono arrivati. Ora prepariamo il trasferimento; il suo sito resta online come prima.', 'de' => 'Danke — der Zugang ist angekommen. Wir bereiten den Umzug vor; Ihre Seite bleibt bis dahin, wie sie ist.', 'en' => 'Thank you — the access has arrived. We are preparing the move; your site stays as it is until then.'],
        'seitenumzugFehlt'   => ['it' => 'Servono almeno server, utente e password FTP.', 'de' => 'Es braucht mindestens FTP-Server, Benutzer und Passwort.', 'en' => 'At least FTP server, user and password are needed.'],
        'seitenumzugHost'    => ['it' => 'Questo server non è raggiungibile da Internet. Controlli il nome.', 'de' => 'Dieser Server ist aus dem Internet nicht erreichbar. Bitte den Namen prüfen.', 'en' => 'This server cannot be reached from the internet. Please check the name.'],
        'seitenumzugFertig'  => ['it' => 'Trasferimento concluso. I dati di accesso sono stati cancellati.', 'de' => 'Umzug abgeschlossen. Die Zugangsdaten sind gelöscht.', 'en' => 'Move complete. The access details have been deleted.'],
        /* Phase 5: Domain-Umzug auf der Kundenseite. */
        'umzugTitel'    => ['it' => 'Trasferimento del dominio', 'de' => 'Umzug Ihrer Domain', 'en' => 'Moving your domain'],
        /* Mein Hosting (26.09.2026) -- der gebuchte Speicher ist der mit
           diesem Kunden vereinbarte, nie eine Paketgroesse. */
        'meinHosting'     => ['it' => 'Il suo hosting', 'de' => 'Ihr Hosting', 'en' => 'Your hosting'],
        'mhSpeicher'      => ['it' => 'Spazio web', 'de' => 'Speicherplatz', 'en' => 'Web space'],
        'mhSpeicherGebucht' => ['it' => 'prenotati', 'de' => 'gebucht', 'en' => 'booked'],
        'mhHttps'         => ['it' => 'Connessione sicura (HTTPS)', 'de' => 'Sichere Verbindung (HTTPS)', 'en' => 'Secure connection (HTTPS)'],
        'mhHttpsOk'       => ['it' => 'attiva', 'de' => 'aktiv', 'en' => 'active'],
        'mhHttpsNoch'     => ['it' => 'viene verificata a breve', 'de' => 'wird in Kürze geprüft', 'en' => 'will be checked shortly'],
        'mhHttpsArbeit'   => ['it' => 'ci stiamo lavorando', 'de' => 'wir kümmern uns darum', 'en' => 'we are working on it'],
        'mhMail'          => ['it' => 'E-mail', 'de' => 'E-Mail', 'en' => 'Email'],
        'mhMailWoanders'  => ['it' => 'resta presso il suo fornitore attuale', 'de' => 'bleibt bei Ihrem bisherigen Anbieter', 'en' => 'stays with your current provider'],
        'mhVertrag'       => ['it' => 'Contratto', 'de' => 'Vertrag', 'en' => 'Contract'],
        'mhNaechste'      => ['it' => 'prossimo addebito il {datum}', 'de' => 'nächste Abbuchung am {datum}', 'en' => 'next charge on {datum}'],
        'mhLaeuftBis'     => ['it' => 'disdetto, attivo fino al {datum}', 'de' => 'gekündigt, läuft bis {datum}', 'en' => 'cancelled, runs until {datum}'],
        'umzugSperre'   => ['it' => 'Il dominio è ancora bloccato presso il suo fornitore attuale. Nel suo pannello cerchi „blocco trasferimento“ (o „transfer lock“) e lo disattivi — altrimenti il trasferimento non parte.',
                            'de' => 'Die Domain ist bei Ihrem bisherigen Anbieter noch gesperrt. Suchen Sie dort im Kundenbereich nach „Transfersperre“ (oder „Transfer Lock“) und heben Sie sie auf — sonst kann der Umzug nicht starten.',
                            'en' => 'The domain is still locked at your current provider. In their customer area look for “transfer lock” and switch it off — otherwise the transfer cannot start.'],
        'umzugCodeHilfe'=> ['it' => 'Ci serve il codice di trasferimento (Auth-Code / AuthInfo). Lo trova nel pannello del suo fornitore attuale o lo chiede a loro. Lo inserisca qui — non per e-mail.',
                            'de' => 'Wir brauchen den Umzugscode (Auth-Code / AuthInfo). Sie finden ihn im Kundenbereich Ihres bisherigen Anbieters oder fragen ihn dort an. Bitte hier eingeben — nicht per E-Mail.',
                            'en' => 'We need the transfer code (auth code / AuthInfo). You find it in your current provider’s customer area or ask them for it. Please enter it here — not by email.'],
        'umzugCodeFeld' => ['it' => 'Codice di trasferimento', 'de' => 'Umzugscode', 'en' => 'Transfer code'],
        'umzugCodeKnopf'=> ['it' => 'Inviare il codice', 'de' => 'Code übermitteln', 'en' => 'Send code'],
        'umzugCodeOk'   => ['it' => 'Grazie, il codice è arrivato. Ora prepariamo il trasferimento — non deve fare altro.', 'de' => 'Danke, der Code ist angekommen. Wir bereiten jetzt den Umzug vor — Sie müssen nichts weiter tun.', 'en' => 'Thank you, the code has arrived. We are now preparing the transfer — nothing else to do on your side.'],
        'umzugCodeFalsch'=>['it' => 'Questo non sembra un codice di trasferimento (6–64 caratteri, senza spazi).', 'de' => 'Das sieht nicht wie ein Umzugscode aus (6–64 Zeichen, ohne Leerzeichen).', 'en' => 'That does not look like a transfer code (6–64 characters, no spaces).'],
        'umzugBeantragt'=> ['it' => 'Il trasferimento è richiesto. Di solito dura da qualche ora a cinque giorni; il suo fornitore attuale potrebbe chiederle una conferma per e-mail — la confermi, per favore.', 'de' => 'Der Umzug ist beantragt. Das dauert meist ein paar Stunden bis fünf Tage; Ihr bisheriger Anbieter fragt eventuell per E-Mail nach einer Bestätigung — bitte bestätigen.', 'en' => 'The transfer has been requested. It usually takes a few hours to five days; your current provider may ask you to confirm by email — please do.'],
        'umzugFertig'   => ['it' => 'Trasferimento concluso: il dominio ora è da noi.', 'de' => 'Umzug abgeschlossen: Die Domain liegt jetzt bei uns.', 'en' => 'Transfer complete: the domain is now with us.'],
        'abbuchungTitel'   => ['it' => 'Pagare in automatico', 'de' => 'Automatisch bezahlen', 'en' => 'Pay automatically'],
        'abbuchungZustimmung' => [
            'it' => 'Autorizzo Vecom Design ad addebitare ogni mese {betrag} per {paket} sulla carta o sul conto che inserisco ora su Stripe, per tutta la durata del contratto. Ogni addebito mi viene annunciato per e-mail {tage} giorni prima. Posso revocare qui in qualsiasi momento; la durata e la disdetta del contratto restano invariate.',
            'de' => 'Ich erlaube Vecom Design, für {paket} jeden Monat {betrag} von der Karte oder dem Konto abzubuchen, das ich jetzt bei Stripe hinterlege — solange der Vertrag läuft. Jede Abbuchung wird mir {tage} Tage vorher per E-Mail angekündigt. Ich kann das hier jederzeit beenden; Laufzeit und Kündigung des Vertrags ändern sich dadurch nicht.',
            'en' => 'I authorise Vecom Design to charge {betrag} each month for {paket} to the card or account I now add at Stripe, for as long as the contract runs. Each charge is announced to me by email {tage} days in advance. I can end this here at any time; the contract term and notice stay the same.'],
        'abbuchungKnopf'   => ['it' => 'Inserire carta o conto', 'de' => 'Karte oder Konto hinterlegen', 'en' => 'Add card or account'],
        'abbuchungAktiv'   => ['it' => 'Pagamento automatico attivo', 'de' => 'Automatische Abbuchung aktiv', 'en' => 'Automatic payment on'],
        'abbuchungMit'     => ['it' => 'Addebito su {zahlmittel}. Ogni addebito viene annunciato per e-mail prima.', 'de' => 'Abgebucht wird von {zahlmittel}. Jede Abbuchung kündigen wir vorher per E-Mail an.', 'en' => 'Charged to {zahlmittel}. Every charge is announced by email first.'],
        'abbuchungAendern' => ['it' => 'Cambiare carta o conto', 'de' => 'Karte oder Konto ändern', 'en' => 'Change card or account'],
        'abbuchungBeenden' => ['it' => 'Pagare di nuovo con link', 'de' => 'Wieder per Link zahlen', 'en' => 'Pay by link again'],
        'abbuchungAus'     => ['it' => 'Fatto: d’ora in poi riceve di nuovo un link di pagamento ogni mese.', 'de' => 'Erledigt: Ab jetzt bekommen Sie wieder jeden Monat einen Zahlungslink.', 'en' => 'Done: from now on you get a payment link each month again.'],
        'abbuchungEin'     => ['it' => 'Grazie — da ora l’addebito è automatico. Ogni volta la avviso prima per e-mail.', 'de' => 'Danke — ab jetzt wird automatisch abgebucht. Sie bekommen vorher jedes Mal eine E-Mail.', 'en' => 'Thank you — from now on payment is automatic. You get an email before each charge.'],
        'abbuchungNicht'   => ['it' => 'Non è stato salvato nulla. Se vuole, riprovi.', 'de' => 'Es wurde nichts hinterlegt. Versuchen Sie es gern noch einmal.', 'en' => 'Nothing was saved. Feel free to try again.'],
        'monatAbbuchung'   => ['it' => 'addebito il {datum}', 'de' => 'wird am {datum} abgebucht', 'en' => 'charged on {datum}'],
        'skizzeTitel'  => ['it' => 'Com’è oggi — e come potrebbe essere', 'de' => 'Wie es heute ist — und wie es werden könnte', 'en' => 'How it is today — and how it could be'],
        'skizzeHeute'  => ['it' => 'Il suo sito attuale ({adresse}), misurato il {datum}:', 'de' => 'Ihre bisherige Seite ({adresse}), gemessen am {datum}:', 'en' => 'Your current site ({adresse}), measured on {datum}:'],
        'skizzeHinweis'=> ['it' => 'Una bozza composta in automatico dai suoi dati — non ancora il progetto. Quello lo facciamo insieme.',
                           'de' => 'Eine automatisch gesetzte Skizze aus Ihren Angaben — noch nicht der Entwurf. Den machen wir zusammen.',
                           'en' => 'A sketch set automatically from your details — not the design yet. That we do together.'],
        /* Der Bereich steht auch dann da, wenn es noch nichts zu sehen gibt.
           Versteckt waere er eine Leerstelle, die Fragen erzeugt: Wo sehe ich
           denn nun meine Seite? So weiss der Kunde, wo sie erscheinen wird. */
        'nochNichts'  => ['it' => 'Appena la bozza è pronta, la trova qui — la avviso.',
                          'de' => 'Sobald Ihr Entwurf fertig ist, können Sie ihn hier ansehen — ich gebe Ihnen Bescheid.',
                          'en' => 'As soon as your draft is ready you can view it here — I’ll let you know.'],
        'entwurfAnsehen' => ['it' => 'Vedere l’anteprima', 'de' => 'Entwurf ansehen', 'en' => 'View the draft'],
        'seiteAnsehen'   => ['it' => 'Aprire il sito', 'de' => 'Website öffnen', 'en' => 'Open the site'],

        /* ANSEHEN UND ABNEHMEN SIND ZWEIERLEI
           ------------------------------------------------------------------
           Frueher stand neben dem Entwurf sofort "Passt so — veroeffentlichen".
           Damit konnte jemand abnehmen, bevor er ueberhaupt geklickt hatte --
           und die Abnahme haengt an der Restzahlung. Jetzt sagt die Seite in
           der Schau-Phase ausdruecklich, dass noch nichts zu entscheiden ist. */
        'nurSchauen' => [
            'it' => 'Lo guardi con calma. Non deve approvare niente adesso — il sito non è ancora finito. Mi scriva cosa ne pensa; la avviso quando è pronto.',
            'de' => 'Sehen Sie ihn sich in Ruhe an. Freigeben müssen Sie noch nichts — die Seite ist noch nicht fertig. Schreiben Sie mir, was Ihnen auffällt; ich gebe Ihnen Bescheid, wenn sie fertig ist.',
            'en' => 'Take your time. You don’t have to approve anything yet — the site isn’t finished. Tell me what you notice; I’ll let you know when it’s ready.'],
        'fertigTitel' => [
            'it' => 'Il sito è pronto — decida Lei',
            'de' => 'Die Seite ist fertig — jetzt entscheiden Sie',
            'en' => 'The site is ready — it’s your call'],
        'fertigText' => [
            'it' => 'Se va bene così, dia il via libera: da lì pubblico. Se manca ancora qualcosa, me lo scriva.',
            'de' => 'Wenn sie so passt, geben Sie sie frei — dann veröffentliche ich. Wenn noch etwas fehlt, schreiben Sie es mir.',
            'en' => 'If it’s right, sign it off — then I publish. If something is still missing, tell me.'],

        /* Der Kostensatz. Er steht bewusst DA, wo entschieden wird, und nicht
           in einer AGB-Zeile: Wer erst mit der Rechnung erfaehrt, dass ein
           Wunsch extra kostete, hat zu Recht schlechte Laune. */
        'aenderungKosten' => [
            'it' => 'Le modifiche che rientrano in quanto concordato sono comprese. Se una richiesta va oltre, glielo dico prima e riceve il preventivo con il prezzo — senza il suo ok non parte niente.',
            'de' => 'Änderungen im vereinbarten Umfang sind enthalten. Geht ein Wunsch darüber hinaus, sage ich es Ihnen vorher und schicke Ihnen ein Angebot mit dem Preis — ohne Ihr Ja passiert nichts.',
            'en' => 'Changes within the agreed scope are included. If a request goes beyond that, I’ll say so first and send you a quote with the price — nothing happens without your go-ahead.'],
        'aenderung'  => ['it' => 'Vorrei una modifica', 'de' => 'Ich möchte etwas ändern', 'en' => 'I’d like a change'],
        'aenderungHilfe' => [
            'it' => 'Scriva cosa cambiare. Le dico se rientra nella manutenzione o cosa costa.',
            'de' => 'Schreiben Sie, was anders sein soll. Ich sage Ihnen, ob es zur Betreuung gehört oder was es kostet.',
            'en' => 'Tell me what should change. I’ll say whether it’s covered or what it costs.'],
        /* Die Betreuung ist ein eigener Vertrag. Der Kunde soll ihn sehen —
           und kuendigen koennen, ohne jemandem schreiben zu muessen. Ein
           Vertrag, aus dem man nur per Bittbrief herauskommt, ist keiner. */
        'betreuung'     => ['it' => 'La sua assistenza', 'de' => 'Ihre Betreuung', 'en' => 'Your care plan'],
        'betreuungMtl'  => ['it' => 'al mese', 'de' => 'im Monat', 'en' => 'per month'],
        'betreuungSeit' => ['it' => 'Attiva dal {datum}', 'de' => 'Läuft seit {datum}', 'en' => 'Running since {datum}'],
        'betreuungMind' => ['it' => 'Durata minima fino al {datum}', 'de' => 'Mindestlaufzeit bis {datum}',
                            'en' => 'Minimum term until {datum}'],
        'kuendigen'     => ['it' => 'Disdire l’assistenza', 'de' => 'Betreuung kündigen', 'en' => 'Cancel the care plan'],
        'jaKuendigen'   => ['it' => 'Sì, disdico', 'de' => 'Ja, kündigen', 'en' => 'Yes, cancel'],
        'abbrechen'     => ['it' => 'Annulla', 'de' => 'Abbrechen', 'en' => 'Cancel'],
        'kuendigenWann' => ['it' => 'Se disdice adesso, l’assistenza resta attiva fino al {datum} — fino ad allora paga, dopo no.',
                            'de' => 'Wenn Sie jetzt kündigen, läuft die Betreuung noch bis zum {datum} — bis dahin zahlen Sie, danach nicht mehr.',
                            'en' => 'If you cancel now, care runs until {datum} — you pay until then, not after.'],
        'kuendigenSicher' => ['it' => 'Vuole davvero disdire? Riceve subito la conferma scritta.',
                              'de' => 'Wirklich kündigen? Sie bekommen sofort die schriftliche Bestätigung.',
                              'en' => 'Really cancel? You’ll get the written confirmation straight away.'],
        'gekuendigt'    => ['it' => 'Disdetta ricevuta. L’assistenza resta attiva fino al {datum}. La conferma è nella sua posta.',
                            'de' => 'Kündigung ist angekommen. Die Betreuung läuft bis zum {datum}. Die Bestätigung liegt in Ihrem Postfach.',
                            'en' => 'Cancellation received. Care runs until {datum}. The confirmation is in your inbox.'],
        'laeuftBis'     => ['it' => 'Disdetta — attiva fino al {datum}', 'de' => 'Gekündigt — läuft bis {datum}',
                            'en' => 'Cancelled — runs until {datum}'],
        'betreuungWeg'  => ['it' => 'L’assistenza è terminata il {datum}. Il sito resta suo e resta online.',
                            'de' => 'Die Betreuung ist am {datum} ausgelaufen. Die Website gehört weiter Ihnen und bleibt online.',
                            'en' => 'Care ended on {datum}. The site stays yours and stays online.'],
        /* Die abgerechneten Monate auf der Kundenseite. Ohne sie stand dort
           der Vertrag, aber nicht, was daraus faellig ist — und genau auf
           diese Seite fuehrt der Link in der Zahlungsaufforderung, wenn
           Stripe keinen eigenen erzeugen konnte. Wer dann hier landete, sah
           nichts, was er haette bezahlen koennen. */
        'monate'        => ['it' => 'Mesi fatturati', 'de' => 'Abgerechnete Monate', 'en' => 'Billed months'],
        'monatOffen'    => ['it' => 'Da pagare', 'de' => 'Offen', 'en' => 'Outstanding'],
        'monatBezahlt'  => ['it' => 'Pagato', 'de' => 'Bezahlt', 'en' => 'Paid'],
        'monatZahlen'   => ['it' => 'Pagare adesso', 'de' => 'Jetzt bezahlen', 'en' => 'Pay now'],
        'monatFaellig'  => ['it' => 'Scadenza {datum}', 'de' => 'Fällig am {datum}', 'en' => 'Due {datum}'],
        'monatWartet'   => ['it' => 'Le scrivo io quando è il momento di pagare.',
                            'de' => 'Ich melde mich, wenn sie zu zahlen ist.',
                            'en' => 'I’ll write to you when it’s time to pay.'],
        /* Der Weg per Ueberweisung — solange es keinen Zahlungslink gibt
           (Karte kommt, sobald der Zahlungsanbieter freigeschaltet ist). */
        'ueberweisung'     => ['it' => 'Pagamento con bonifico',
                               'de' => 'Zahlung per Überweisung',
                               'en' => 'Payment by bank transfer'],
        'ueberweisungHilfe'=> ['it' => 'Può pagare comodamente con bonifico bancario. Indichi la causale qui sotto così riconosco subito il pagamento.',
                               'de' => 'Sie können bequem per Überweisung zahlen. Geben Sie den Verwendungszweck unten an, dann erkenne ich die Zahlung sofort.',
                               'en' => 'You can pay conveniently by bank transfer. Please add the reference below so I recognise the payment right away.'],
        'ueEmpf'  => ['it' => 'Beneficiario', 'de' => 'Empfänger', 'en' => 'Recipient'],
        'ueBank'  => ['it' => 'Banca',       'de' => 'Bank',      'en' => 'Bank'],
        'ueZweck' => ['it' => 'Causale',     'de' => 'Verwendungszweck', 'en' => 'Reference'],
        /* Nach dem Onlinegang: die Bitte um zwei Saetze. Sie steht auf seiner
           Seite, nicht in einer weiteren E-Mail — dort ist er ohnehin, wenn
           er zufrieden nachsieht, wie die Seite laeuft. */
        'stimme'        => ['it' => 'Com’è andata?', 'de' => 'Wie war es?', 'en' => 'How was it?'],
        'stimmeHilfe'   => ['it' => 'Se il risultato la soddisfa, due frasi mi aiutano molto: com’è andata a lavorare insieme e cosa è cambiato per la sua attività. Se qualcosa non è andato, quello mi interessa ancora di più — lo scriva lo stesso.',
                            'de' => 'Wenn Sie zufrieden sind, helfen mir zwei Sätze sehr: wie die Zusammenarbeit war und was sich für Ihren Betrieb geändert hat. Wenn etwas nicht gepasst hat, interessiert mich das noch mehr — schreiben Sie es genauso.',
                            'en' => 'If you’re happy, two sentences help me a lot: what the work was like and what changed for your business. If something wasn’t right, I want to hear that even more — write it just the same.'],
        'stimmeFeld'    => ['it' => 'Due frasi bastano.', 'de' => 'Zwei Sätze reichen.', 'en' => 'Two sentences are enough.'],
        'stimmeSterne'  => ['it' => 'Come valuta il lavoro?', 'de' => 'Wie bewerten Sie die Arbeit?', 'en' => 'How would you rate the work?'],
        'stimmeErlaubnis' => ['it' => 'Può pubblicarla sul suo sito con il mio nome e quello della mia azienda.',
                              'de' => 'Sie dürfen das auf Ihrer Website zeigen, mit meinem Namen und meiner Firma.',
                              'en' => 'You may show this on your site, with my name and my company.'],
        'stimmeErlaubnisNein' => ['it' => 'Senza la spunta la leggo solo io — e va benissimo così.',
                                  'de' => 'Ohne Häkchen lese nur ich sie — und das ist völlig in Ordnung.',
                                  'en' => 'Without the tick only I read it — and that’s perfectly fine.'],
        'stimmeSenden'  => ['it' => 'Invia', 'de' => 'Absenden', 'en' => 'Send'],
        'stimmeDanke'   => ['it' => 'Grazie davvero. La leggo con calma — se l’ha autorizzata, la metto sul sito dopo averla vista.',
                            'de' => 'Herzlichen Dank. Ich lese sie in Ruhe — wenn Sie es erlaubt haben, stelle ich sie danach auf die Website.',
                            'en' => 'Thank you, genuinely. I’ll read it properly — if you allowed it, it goes on the site after I’ve seen it.'],
        'stimmeSchon'   => ['it' => 'Ha già lasciato la sua opinione. Grazie!', 'de' => 'Sie haben schon geschrieben. Vielen Dank!',
                            'en' => 'You’ve already written. Thank you!'],
        'lesenswert' => [
            'it' => 'Questa pagina resta sua. La salvi tra i preferiti — la trova sempre qui, anche fra mesi.',
            'de' => 'Diese Seite gehört Ihnen. Legen Sie sie als Lesezeichen an — Sie finden sie hier auch noch in Monaten.',
            'en' => 'This page stays yours. Bookmark it — it will still be here months from now.'],
        'nichtGefunden' => [
            'it' => 'Questo link non è valido. Mi scriva e gliene mando uno nuovo.',
            'de' => 'Dieser Link gilt nicht mehr. Schreiben Sie mir kurz, dann schicke ich Ihnen einen neuen.',
            'en' => 'This link is no longer valid. Write to me and I’ll send a new one.'],
    ];

    /**
     * Was auf jeder Stufe dransteht — Ueberschrift, ein Satz, und wer
     * handeln muss. "kunde" heisst: Er selbst. "wir" heisst: Er wartet.
     */
    public const KUNDE_STUFEN = [
        /* Der erste Schritt im Dashboard (24.09.2026, D1): die acht Fragen.
           Er teilt sich den Platz auf der Fortschrittsleiste mit „anfrage“ --
           vorher beschreibt er sein Vorhaben, danach liegt es bei mir. */
        'vorhaben' => ['wer' => 'kunde',
            'kurz' => ['it' => 'Progetto', 'de' => 'Vorhaben', 'en' => 'Project'],
            'it' => 'Mi racconti il suo progetto', 'de' => 'Erzählen Sie mir von Ihrem Vorhaben',
            'en' => 'Tell me about your project',
            'text' => ['it' => 'Otto domande brevi, circa un minuto e mezzo. Subito dopo vede un prezzo indicativo, senza impegno.',
                       'de' => 'Acht kurze Fragen, etwa anderthalb Minuten. Gleich danach sehen Sie einen Richtpreis, unverbindlich.',
                       'en' => 'Eight short questions, about ninety seconds. Right after, you see a guide price, no obligation.']],
        'anfrage'  => ['wer' => 'wir',
            'kurz' => ['it' => 'Progetto', 'de' => 'Vorhaben', 'en' => 'Project'],
            'it' => 'La sua richiesta è arrivata', 'de' => 'Ihre Anfrage ist da', 'en' => 'I have your enquiry',
            'text' => ['it' => 'La sto guardando e le scrivo con una proposta.',
                       'de' => 'Ich sehe sie mir an und melde mich mit einem Vorschlag.',
                       'en' => 'I’m looking at it and will come back with a proposal.']],
        'angebot'  => ['wer' => 'kunde',
            /* Die Stufe traegt zwei Schritte: das Angebot lesen und annehmen,
               danach die Anzahlung. "Anzahlung" als Aufschrift widersprach
               deshalb der Ueberschrift darunter, solange das Angebot noch
               offen war (22.09.2026). */
            'kurz' => ['it' => 'Preventivo', 'de' => 'Angebot', 'en' => 'Quote'],
            'it' => 'Il suo preventivo', 'de' => 'Ihr Angebot steht', 'en' => 'Your quote is ready',
            'text' => ['it' => 'Con l’acconto iniziamo. Il pagamento avviene su una pagina di Stripe.',
                       'de' => 'Mit der Anzahlung fangen wir an. Bezahlt wird auf einer Seite von Stripe.',
                       'en' => 'The deposit gets us started. Payment happens on a Stripe page.']],
        'angaben'  => ['wer' => 'kunde',
            'kurz' => ['it' => 'Dati', 'de' => 'Angaben', 'en' => 'Details'],
            'it' => 'Adesso servono le sue informazioni', 'de' => 'Jetzt brauche ich Ihre Angaben',
            'en' => 'Now I need your details',
            'text' => ['it' => 'Poche domande sulla sua azienda e sul sito. Può interrompere e riprendere.',
                       'de' => 'Ein paar Fragen zu Ihrem Betrieb und zur Seite. Sie können zwischendurch aufhören und später weitermachen.',
                       'en' => 'A few questions about your business and the site. You can stop and continue later.']],
        'arbeit'   => ['wer' => 'wir',
            'kurz' => ['it' => 'In corso', 'de' => 'Bau', 'en' => 'Build'],
            'it' => 'Sto costruendo', 'de' => 'Ich baue Ihre Seite', 'en' => 'I’m building your site',
            'text' => ['it' => 'La avviso appena c’è qualcosa da guardare.',
                       'de' => 'Ich melde mich, sobald es etwas zu sehen gibt.',
                       'en' => 'I’ll let you know as soon as there’s something to look at.']],
        'entwurf'  => ['wer' => 'kunde',
            'kurz' => ['it' => 'Anteprima', 'de' => 'Entwurf', 'en' => 'Draft'],
            'it' => 'La sua anteprima è pronta', 'de' => 'Ihr Entwurf ist fertig', 'en' => 'Your draft is ready',
            'text' => ['it' => 'La guardi con calma. Va bene così? Me lo scriva. Vuole cambiare qualcosa? Anche quello.',
                       'de' => 'Sehen Sie ihn sich in Ruhe an. Passt er? Schreiben Sie mir. Soll etwas anders werden? Auch das.',
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
            'it' => 'Il suo sito è online', 'de' => 'Ihre Website ist online', 'en' => 'Your site is live',
            'text' => ['it' => 'Lo tengo d’occhio io. Se vuole cambiare qualcosa, scriva qui sotto.',
                       'de' => 'Ich habe ein Auge darauf. Wenn Sie etwas ändern möchten, schreiben Sie es unten.',
                       'en' => 'I keep an eye on it. If you want a change, write below.']],
        'fertig'   => ['wer' => 'niemand',
            'kurz' => ['it' => 'Concluso', 'de' => 'Fertig', 'en' => 'Done'],
            'it' => 'Progetto concluso', 'de' => 'Projekt abgeschlossen', 'en' => 'Project completed',
            'text' => ['it' => 'Grazie. Se le serve qualcosa, sono qui.',
                       'de' => 'Vielen Dank. Wenn Sie etwas brauchen, bin ich da.',
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
        'kundenfeedback'         => ['it' => 'Aspetto il suo parere', 'de' => 'Ich warte auf Ihre Rückmeldung', 'en' => 'Waiting for your feedback'],
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
        'titel'      => ['it' => 'La sua richiesta', 'de' => 'Ihre Anfrage', 'en' => 'Your enquiry'],
        'lead'       => ['it' => 'Qui seguiamo la conversazione finché non decidiamo insieme. Niente di quanto vede qui la impegna.',
                         'de' => 'Hier läuft unser Austausch, bis wir uns einig sind. Nichts davon verpflichtet Sie zu etwas.',
                         'en' => 'This is where our conversation runs until we agree. None of it commits you to anything.'],
        'angefragt'  => ['it' => 'Cosa ha chiesto', 'de' => 'Was Sie angefragt haben', 'en' => 'What you asked for'],
        'paket'      => ['it' => 'Pacchetto scelto', 'de' => 'Gewähltes Paket', 'en' => 'Chosen package'],
        'am'         => ['it' => 'Ricevuta il', 'de' => 'Eingegangen am', 'en' => 'Received on'],
        'unverbind'  => ['it' => 'Gratuita e senza impegno — un incarico nasce solo con il contratto firmato.',
                         'de' => 'Kostenlos und unverbindlich — ein Auftrag entsteht erst mit dem unterschriebenen Vertrag.',
                         'en' => 'Free and without obligation — a project only begins with a signed contract.'],
        'soGehts'    => ['it' => 'Come funziona', 'de' => 'So läuft es', 'en' => 'How it works'],
        'g0'         => ['it' => 'Tutto passa da questa pagina. Nessun account, nessuna password: il link è il suo accesso, dal telefono come dal computer.',
                         'de' => 'Alles läuft über diese Seite. Kein Konto, kein Passwort: Der Link ist Ihr Zugang, auf dem Handy wie am Rechner.',
                         'en' => 'Everything runs through this page. No account, no password: the link is your way in, on a phone or a computer.'],
        'g1'         => ['it' => 'La metta da parte', 'de' => 'Bewahren Sie sie auf', 'en' => 'Keep this page'],
        'g1d'        => ['it' => 'Salvi questa pagina tra i preferiti o tenga l’e-mail con il link. Se lo perde, mi scriva: gliene mando uno nuovo.',
                         'de' => 'Setzen Sie ein Lesezeichen oder behalten Sie die E-Mail mit dem Link. Wenn er verloren geht, schreiben Sie mir — dann kommt ein neuer.',
                         'en' => 'Bookmark it or keep the email with the link. If it gets lost, write to me and you’ll get a new one.'],
        'g2'         => ['it' => 'Mi scriva qui, non per e-mail', 'de' => 'Schreiben Sie mir hier, nicht per E-Mail', 'en' => 'Write here, not by email'],
        'g2d'        => ['it' => 'Così resta tutto in un posto solo e niente si perde. Ogni messaggio mi arriva subito.',
                         'de' => 'So steht alles an einer Stelle und nichts geht unter. Jede Nachricht erreicht mich sofort.',
                         'en' => 'That way everything stays in one place and nothing gets lost. Every message reaches me at once.'],
        'g3'         => ['it' => 'Carichi quello che ho bisogno di vedere', 'de' => 'Laden Sie hoch, was ich sehen sollte', 'en' => 'Upload what I should see'],
        'g3d'        => ['it' => 'Logo, foto, testi, il sito vecchio. Scelga il file qui sotto e invii.',
                         'de' => 'Logo, Fotos, Texte, die alte Seite. Datei unten auswählen und senden.',
                         'en' => 'Logo, photos, text, the old site. Pick the file below and send.'],
        'g4'         => ['it' => 'E poi?', 'de' => 'Und dann?', 'en' => 'And then?'],
        'g4d'        => ['it' => 'Le mando una proposta a prezzo fisso. Se la convince, riceve il link per il pagamento — e questa stessa pagina cresce con noi: questionario, bozza, approvazione, messa online.',
                         'de' => 'Ich schicke Ihnen einen Vorschlag zum Festpreis. Passt er, bekommen Sie den Zahlungslink — und genau diese Seite wächst mit: Fragebogen, Entwurf, Freigabe, Veröffentlichung.',
                         'en' => 'I send you a proposal at a fixed price. If it suits you, you get the payment link — and this same page grows with us: questionnaire, draft, approval, going live.'],
        'nachrichten'=> ['it' => 'Messaggi', 'de' => 'Nachrichten', 'en' => 'Messages'],
        'schreiben'  => ['it' => 'Mi scriva', 'de' => 'Schreiben Sie mir', 'en' => 'Write to me'],
        'senden'     => ['it' => 'Invia', 'de' => 'Senden', 'en' => 'Send'],
        'gesendet'   => ['it' => 'Messaggio inviato.', 'de' => 'Nachricht ist raus.', 'en' => 'Message sent.'],
        'nochNichts' => ['it' => 'Ancora nessun messaggio.', 'de' => 'Noch keine Nachricht.', 'en' => 'No messages yet.'],
        'du'         => ['it' => 'Lei', 'de' => 'Sie', 'en' => 'You'],
        'wir'        => ['it' => 'Vecom Design', 'de' => 'Vecom Design', 'en' => 'Vecom Design'],
        'dateien'    => ['it' => 'I suoi documenti', 'de' => 'Ihre Unterlagen', 'en' => 'Your files'],
        'hochladen'  => ['it' => 'Caricare un file', 'de' => 'Datei hochladen', 'en' => 'Upload a file'],
        'dateiHinweis'=> ['it' => 'Logo, immagini, testi — quello che dovrei vedere. Massimo {max} per file.',
                          'de' => 'Logo, Bilder, Texte — was ich sehen sollte. Höchstens {max} je Datei.',
                          'en' => 'Logo, images, text — whatever I should see. At most {max} per file.'],
        'dateiOk'    => ['it' => 'Ricevuto, grazie.', 'de' => 'Angekommen, danke.', 'en' => 'Received, thank you.'],
        'keineDateien'=> ['it' => 'Ancora nessun documento.', 'de' => 'Noch nichts hochgeladen.', 'en' => 'Nothing uploaded yet.'],
        'weg'        => ['it' => 'Questo link non è più valido. Mi scriva a kontakt@vecom-design.it e gliene mando uno nuovo.',
                         'de' => 'Dieser Link gilt nicht mehr. Schreiben Sie an kontakt@vecom-design.it, dann kommt ein neuer.',
                         'en' => 'This link is no longer valid. Write to kontakt@vecom-design.it and you will get a new one.'],
        'panne'      => ['it' => 'Al momento non raggiungibile. Riprovi tra poco.',
                         'de' => 'Gerade nicht erreichbar. Versuchen Sie es gleich noch einmal.',
                         'en' => 'Not reachable right now. Please try again shortly.'],
    ];

    /* Die Zeilen des Monatsberichts (26.09.2026) -- jede nur, wenn ihr Wert
       gemessen ist. Ein Bericht, der etwas behauptet, das niemand geprueft
       hat, waere schlimmer als keiner. */
    public const BERICHT = [
        'online'   => ['it' => '✓ Il suo sito è raggiungibile — ultimo controllo il {datum}.',
                       'de' => '✓ Ihre Website ist erreichbar — zuletzt geprüft am {datum}.',
                       'en' => '✓ Your website is reachable — last checked on {datum}.'],
        'stoerung' => ['it' => '⚠ All’ultimo controllo il sito non rispondeva correttamente. Ce ne stiamo occupando.',
                       'de' => '⚠ Bei der letzten Prüfung antwortete die Website nicht richtig. Wir kümmern uns darum.',
                       'en' => '⚠ At the last check your website did not respond properly. We are on it.'],
        'https_bis'=> ['it' => '✓ Connessione sicura (HTTPS) attiva, certificato valido fino al {datum} — si rinnova da solo.',
                       'de' => '✓ Sichere Verbindung (HTTPS) aktiv, Zertifikat gültig bis {datum} — es verlängert sich von selbst.',
                       'en' => '✓ Secure connection (HTTPS) active, certificate valid until {datum} — it renews itself.'],
        'https'    => ['it' => '✓ Connessione sicura (HTTPS) attiva — verificata il {datum}.',
                       'de' => '✓ Sichere Verbindung (HTTPS) aktiv — geprüft am {datum}.',
                       'en' => '✓ Secure connection (HTTPS) active — checked on {datum}.'],
        'speicher' => ['it' => '✓ Spazio web: {belegt} di {gebucht} occupati.',
                       'de' => '✓ Speicherplatz: {belegt} von {gebucht} belegt.',
                       'en' => '✓ Web space: {belegt} of {gebucht} used.'],
    ];

    public const MAILS = [
        /* Die neue Domain ist registriert (26.09.2026) */
        'domain_aktiv' => [
            'it' => ['{domain} è registrato',
                "Buongiorno {name},\n\nbuone notizie: il dominio {domain} è registrato a suo nome e punta già al suo spazio web.\n\n"
                . "Nelle prossime ore attiviamo il certificato di sicurezza (HTTPS); le scriveremo appena il sito è online.\n\nLa sua pagina: {seite}"],
            'de' => ['{domain} ist registriert',
                "Guten Tag {name},\n\ngute Nachricht: Die Domain {domain} ist auf Sie registriert und zeigt bereits auf Ihren Webspace.\n\n"
                . "In den nächsten Stunden schalten wir das Sicherheitszertifikat (HTTPS) ein; wir melden uns, sobald die Seite online ist.\n\nIhre Seite: {seite}"],
            'en' => ['{domain} is registered',
                "Hello {name},\n\ngood news: the domain {domain} is registered in your name and already points to your web space.\n\n"
                . "Over the next hours we will switch on the security certificate (HTTPS); we’ll let you know as soon as the site is online.\n\nYour page: {seite}"],
        ],
        /* Monatsbericht an Hosting-Kunden (26.09.2026, Uwe: ja) */
        'hosting_bericht' => [
            'it' => ['{domain}: il suo mese di {monat}',
                "Buongiorno {name},\n\necco come sta {domain} a {monat}:\n\n{zeilen}\n\n"
                . "Non deve fare nulla. Se le serve qualcosa, mi risponda pure a questa e-mail.\n\nLa sua pagina: {seite}"],
            'de' => ['{domain}: Ihr {monat} im Überblick',
                "Guten Tag {name},\n\nso steht {domain} im {monat}:\n\n{zeilen}\n\n"
                . "Sie müssen nichts tun. Wenn Sie etwas brauchen, antworten Sie einfach auf diese Mail.\n\nIhre Seite: {seite}"],
            'en' => ['{domain}: your {monat} at a glance',
                "Hello {name},\n\nhere is how {domain} is doing in {monat}:\n\n{zeilen}\n\n"
                . "You don’t need to do anything. If you need something, just reply to this email.\n\nYour page: {seite}"],
        ],
        /* Speicher fast voll -- an den Kunden, mit einfachen Tipps (26.09.2026, Uwe: ja) */
        'hosting_speicher_voll' => [
            'it' => ['{domain}: lo spazio web è quasi pieno',
                "Buongiorno {name},\n\n{domain} occupa {belegt} dei {gebucht} previsti.\n\n"
                . "Cosa aiuta di solito:\n– svuotare il cestino e le e-mail vecchie con allegati grandi\n– cancellare copie e backup vecchi dallo spazio web\n\n"
                . "Se le serve più spazio, mi risponda: troviamo una soluzione.\n\nLa sua pagina: {seite}"],
            'de' => ['{domain}: Der Speicherplatz ist fast voll',
                "Guten Tag {name},\n\n{domain} belegt {belegt} der vereinbarten {gebucht}.\n\n"
                . "Was meistens hilft:\n– Papierkorb und alte Mails mit großen Anhängen leeren\n– alte Kopien und Sicherungen vom Webspace löschen\n\n"
                . "Brauchen Sie mehr Platz, antworten Sie einfach — wir finden eine Lösung.\n\nIhre Seite: {seite}"],
            'en' => ['{domain}: your web space is almost full',
                "Hello {name},\n\n{domain} uses {belegt} of the agreed {gebucht}.\n\n"
                . "What usually helps:\n– empty the trash and old emails with large attachments\n– delete old copies and backups from the web space\n\n"
                . "If you need more space, just reply — we’ll find a solution.\n\nYour page: {seite}"],
        ],
        /* Die monatliche Betreuung. Kein Verkaufstext: Wer sie hat, hat sie
           bestellt — er will wissen, welcher Monat, wieviel, und wo er zahlt. */
        /* Phase 2: die Vorabinformation vor jeder Abbuchung. Bei SEPA ist
           sie Pflicht -- und bei der Karte ehrlich. */
        /* Phase 6b: E-Mail-Umzug -- Anfrage und Abschluss. Nie ein Passwort in der Mail. */
        'mailumzug_anfrage' => [
            'it' => ['Trasferire le sue e-mail da {alt}',
                "Buongiorno {name},\n\ncopiamo le sue e-mail (con tutte le cartelle) da {alt} nella nuova casella {neu}. Presso il fornitore attuale non si cancella nulla.\n\n"
                . "Sulla sua pagina dà il consenso e inserisce le due password — per favore non per e-mail:\n{seite}"],
            'de' => ['Ihre E-Mails von {alt} umziehen',
                "Guten Tag {name},\n\nwir kopieren Ihre E-Mails (mit allen Ordnern) von {alt} in das neue Postfach {neu}. Beim bisherigen Anbieter wird nichts gelöscht.\n\n"
                . "Auf Ihrer Seite stimmen Sie zu und geben die beiden Passwörter ein — bitte nicht per E-Mail:\n{seite}"],
            'en' => ['Moving your emails from {alt}',
                "Hello {name},\n\nwe copy your emails (with all folders) from {alt} into the new mailbox {neu}. Nothing is deleted at the current provider.\n\n"
                . "On your page you give your consent and enter both passwords — please not by email:\n{seite}"],
        ],
        'mailumzug_fertig' => [
            'it' => ['Le sue e-mail sono nella nuova casella',
                "Buongiorno {name},\n\n{anzahl} e-mail da {alt} ora sono anche in {neu}. Per {tage} giorni riprendiamo automaticamente quelle che arrivano ancora nella vecchia casella; poi cancelliamo le password.\n\n{seite}"],
            'de' => ['Ihre E-Mails sind im neuen Postfach',
                "Guten Tag {name},\n\n{anzahl} E-Mails aus {alt} liegen jetzt auch in {neu}. {tage} Tage lang holen wir automatisch nach, was im alten Postfach noch ankommt; danach löschen wir die Passwörter.\n\n{seite}"],
            'en' => ['Your emails are in the new mailbox',
                "Hello {name},\n\n{anzahl} emails from {alt} are now also in {neu}. For {tage} days we automatically pick up whatever still arrives in the old mailbox; then we delete the passwords.\n\n{seite}"],
        ],
        /* Phase 6c: Uwe fragt den 1:1-Umzug an. Kein Passwort in der Mail --
           nur der Weg zur Seite, auf der zugestimmt und eingegeben wird. */
        'seitenumzug_anfrage' => [
            'it' => ['Trasferire il suo sito {adresse}',
                "Buongiorno {name},\n\ncome d’accordo trasferiamo il suo sito {adresse} da noi, così com’è.\n\n"
                . "Sulla sua pagina trova cosa serve (i dati di accesso al suo spazio web attuale) e il testo a cui dà il consenso. "
                . "Per favore non mandi password per e-mail — le inserisca lì:\n{seite}"],
            'de' => ['Umzug Ihrer Website {adresse}',
                "Guten Tag {name},\n\nwie besprochen ziehen wir Ihre Website {adresse} so, wie sie ist, zu uns um.\n\n"
                . "Auf Ihrer Seite steht, was wir dafür brauchen (den Zugang zu Ihrem bisherigen Webspace), und der Text, dem Sie zustimmen. "
                . "Bitte keine Passwörter per E-Mail — geben Sie sie dort ein:\n{seite}"],
            'en' => ['Moving your website {adresse}',
                "Hello {name},\n\nas agreed we are moving your website {adresse} to us exactly as it is.\n\n"
                . "Your page shows what we need (access to your current web space) and the text you agree to. "
                . "Please don’t send passwords by email — enter them there:\n{seite}"],
        ],
        /* Phase 5: der Umzug ist durch. */
        'domain_umgezogen' => [
            'it' => ['{domain} è arrivato da noi',
                "Buongiorno {name},\n\nil trasferimento di {domain} è concluso: il dominio ora è gestito da noi. "
                . "Sito ed e-mail funzionano come prima — se nei prossimi giorni qualcosa non dovesse arrivare, ci scriva subito.\n\n{seite}"],
            'de' => ['{domain} ist umgezogen',
                "Guten Tag {name},\n\nder Umzug von {domain} ist abgeschlossen: Die Domain liegt jetzt bei uns. "
                . "Website und E-Mail laufen weiter wie bisher — fällt Ihnen in den nächsten Tagen trotzdem etwas auf, schreiben Sie uns gleich.\n\n{seite}"],
            'en' => ['{domain} has moved',
                "Hello {name},\n\nthe transfer of {domain} is complete: the domain is now with us. "
                . "Website and email keep working as before — if anything seems off in the next few days, write to us right away.\n\n{seite}"],
        ],
        'abbuchung_angekuendigt' => [
            'it' => ['{monat}: addebito di {betrag} il {datum}',
                "Buongiorno {name},\n\nil {datum} addebiteremo {betrag} per {monat} su {zahlmittel}.\n\n"
                . "Non deve fare nulla. La ricevuta arriva dopo l’addebito.\n\n"
                . "Se vuole cambiare carta o conto, o tornare al link di pagamento:\n{seite}"],
            'de' => ['{monat}: Abbuchung von {betrag} am {datum}',
                "Guten Tag {name},\n\nam {datum} buchen wir {betrag} für {monat} von {zahlmittel} ab.\n\n"
                . "Sie müssen nichts tun. Der Beleg kommt nach der Abbuchung.\n\n"
                . "Karte oder Konto ändern oder wieder per Link zahlen:\n{seite}"],
            'en' => ['{monat}: {betrag} will be charged on {datum}',
                "Hello {name},\n\non {datum} we will charge {betrag} for {monat} to {zahlmittel}.\n\n"
                . "You don’t need to do anything. The receipt follows the charge.\n\n"
                . "To change card or account, or to pay by link again:\n{seite}"],
        ],
        'betreuung_faellig' => [
            'it' => ['Assistenza {monat} — {betrag}',
                "Buongiorno {name},\n\nl’assistenza di {monat} è pronta: {betrag}.\n\n"
                . "Può pagare qui, entro il {frist}:\n{link}\n\n"
                . "Cosa è compreso: aggiornamenti, backup, controllo del sito e piccole modifiche. "
                . "Se questo mese le serve qualcosa in particolare, mi scriva.\n\n"
                . "La ricevuta arriva subito dopo il pagamento."],
            'de' => ['Betreuung {monat} — {betrag}',
                "Guten Tag {name},\n\ndie Betreuung für {monat} steht an: {betrag}.\n\n"
                . "Hier können Sie zahlen, bis zum {frist}:\n{link}\n\n"
                . "Enthalten sind Aktualisierungen, Sicherungen, die Überwachung Ihrer Seite "
                . "und kleine Änderungen. Wenn diesen Monat etwas Bestimmtes ansteht, schreiben Sie mir.\n\n"
                . "Den Beleg bekommen Sie gleich nach der Zahlung."],
            'en' => ['Care for {monat} — {betrag}',
                "Hello {name},\n\nthe monthly care for {monat} is due: {betrag}.\n\n"
                . "You can pay here, by {frist}:\n{link}\n\n"
                . "It covers updates, backups, monitoring of your site and small changes. "
                . "If something particular is coming up this month, write to me.\n\n"
                . "The receipt follows right after payment."],
        ],
        /* Die Hosting-Rate: derselbe Rhythmus, aber ohne die Betreuungs-
           Versprechen (Aktualisierungen, Sicherungen) — die gibt es in
           diesem Vertrag nicht, und eine Mail verspricht nichts, was der
           Vertrag nicht haelt. Bei der ERSTEN Rate haengt zudem noch mehr
           dran: Erst mit ihr wird angelegt. Das sagt der zweite Absatz. */
        'hosting_faellig' => [
            'it' => ['Dominio & hosting {monat} — {betrag}',
                "Buongiorno {name},\n\nla rata di dominio & hosting per {monat} è pronta: {betrag}.\n\n"
                . "Può pagare qui, entro il {frist}:\n{link}\n\n"
                . "Se è la sua prima rata: appena arriva il pagamento attiviamo dominio, "
                . "spazio web, SSL e casella e-mail — e i suoi dati di accesso compaiono "
                . "sulla sua pagina personale.\n\n"
                . "La ricevuta arriva subito dopo il pagamento."],
            'de' => ['Domain & Hosting {monat} — {betrag}',
                "Guten Tag {name},\n\ndie Rate für Domain & Hosting im {monat} steht an: {betrag}.\n\n"
                . "Hier können Sie zahlen, bis zum {frist}:\n{link}\n\n"
                . "Falls das Ihre erste Rate ist: Sobald die Zahlung da ist, schalten wir "
                . "Domain, Speicherplatz, SSL und E-Mail-Postfach — und Ihre Zugangsdaten "
                . "erscheinen auf Ihrer persönlichen Seite.\n\n"
                . "Den Beleg bekommen Sie gleich nach der Zahlung."],
            'en' => ['Domain & hosting {monat} — {betrag}',
                "Hello {name},\n\nthe domain & hosting instalment for {monat} is due: {betrag}.\n\n"
                . "You can pay here, by {frist}:\n{link}\n\n"
                . "If this is your first instalment: as soon as the payment arrives we set up "
                . "your domain, web space, SSL and email mailbox — and your access details "
                . "appear on your personal page.\n\n"
                . "The receipt follows right after payment."],
        ],
        /* Die Bestaetigung zum Monatsvertrag — mit dem Vertragsblatt im
           Anhang. Sie geht bei JEDEM Abschluss raus (Betreuung wie Hosting):
           Beim Solo-Hosting kommt der Vertrag online zustande, und ein
           Fernabsatzvertrag verlangt die Bestaetigung auf dauerhaftem
           Datentraeger. Bei den anderen ist sie schlicht guter Stil. */
        'vertrag_monat' => [
            'it' => ['Il suo contratto: {paket} — {betrag} al mese',
                "Buongiorno {name},\n\necco la conferma del suo contratto mensile, nero su bianco:\n\n"
                . "{paket} — {betrag} al mese\nInizio: {beginn}\nDurata minima fino al: {mindest}\n\n"
                . "In allegato trova il foglio del contratto con tutte le condizioni, il diritto "
                . "di recesso compreso — lo conservi pure. Lo trova anche sulla sua pagina:\n{link}\n\n"
                . "Dopo la durata minima può disdire quando vuole a fine mese, dalla sua pagina o "
                . "rispondendo a questa e-mail."],
            'de' => ['Ihr Vertrag: {paket} — {betrag} im Monat',
                "Guten Tag {name},\n\nhier die Bestätigung Ihres Monatsvertrags, schwarz auf weiß:\n\n"
                . "{paket} — {betrag} im Monat\nBeginn: {beginn}\nMindestlaufzeit bis: {mindest}\n\n"
                . "Im Anhang liegt Ihr Vertragsblatt mit allen Bedingungen samt Widerrufsrecht — "
                . "zum Aufheben. Sie finden es auch jederzeit auf Ihrer Seite:\n{link}\n\n"
                . "Nach der Mindestlaufzeit kündigen Sie jederzeit zum Monatsende — auf Ihrer "
                . "Seite oder einfach als Antwort auf diese E-Mail."],
            'en' => ['Your contract: {paket} — {betrag} per month',
                "Hello {name},\n\nhere is the confirmation of your monthly contract, in black and white:\n\n"
                . "{paket} — {betrag} per month\nStart: {beginn}\nMinimum term until: {mindest}\n\n"
                . "Attached you’ll find your contract sheet with all terms including the right of "
                . "withdrawal — keep it somewhere safe. It’s also available on your page any time:\n{link}\n\n"
                . "After the minimum term you can cancel at any month’s end — from your page or "
                . "simply by replying to this email."],
        ],
        /* Alles geschaltet: Der Kunde erfaehrt es per Mail — aber die
           Zugangsdaten stehen NICHT darin. Mails laufen unverschluesselt
           und liegen ewig im Postfach; die Daten liegen stattdessen
           verschluesselt bereit und werden genau einmal auf seiner Seite
           gezeigt. Die Mail sagt, wo, wie lange — und dass er die
           Passwoerter danach im KAS selbst aendern soll. */
        'hosting_fertig' => [
            'it' => ['Il suo dominio {domain} è attivo — i dati di accesso la aspettano',
                "Buongiorno {name},\n\nfatto: {domain} è attivo, con {umfang}.\n\nI suoi dati di accesso sono pronti sulla sua pagina — per "
                . "sicurezza vengono mostrati UNA SOLA volta, quindi tenga pronto dove salvarli:\n{link}\n\n"
                . "Importante: dopo averli salvati, cambi le password nel pannello KAS "
                . "(kas.all-inkl.com) — così le conosce solo Lei. Se non ritira i dati entro "
                . "{tage} giorni, li cancelliamo e su richiesta ne impostiamo di nuovi.\n\n"
                . "Per qualsiasi cosa, risponda pure a questa e-mail."],
            'de' => ['Ihre Domain {domain} ist geschaltet — die Zugangsdaten warten auf Sie',
                "Guten Tag {name},\n\ngeschafft: {domain} ist geschaltet, mit {umfang}.\n\nIhre Zugangsdaten liegen auf "
                . "Ihrer Seite bereit — aus Sicherheitsgründen werden sie nur EIN einziges Mal "
                . "angezeigt, halten Sie also bereit, wo Sie sie speichern:\n{link}\n\n"
                . "Wichtig: Ändern Sie die Passwörter nach dem Speichern im KAS-Kundenmenü "
                . "(kas.all-inkl.com) — dann kennen nur noch Sie sie. Rufen Sie die Daten nicht "
                . "innerhalb von {tage} Tagen ab, löschen wir sie und setzen Ihnen auf Zuruf neue.\n\n"
                . "Bei allem anderen: einfach auf diese E-Mail antworten."],
            'en' => ['Your domain {domain} is live — your access details are waiting',
                "Hello {name},\n\ndone: {domain} is live, with {umfang}.\n\nYour access details are ready on your page — for security "
                . "they are shown only ONCE, so have somewhere ready to save them:\n{link}\n\n"
                . "Important: after saving them, change the passwords in the KAS panel "
                . "(kas.all-inkl.com) — then only you know them. If you don’t collect the details "
                . "within {tage} days, we delete them and set new ones on request.\n\n"
                . "For anything else, just reply to this email."],
        ],
        /* Das Angebot: Uwe hat die Wunschdomain geprueft und vorgeschlagen.
           Die Mail bringt den Kunden auf seine Seite, wo Preis und Ja-Knopf
           stehen — die ZUSTIMMUNG passiert dort, nie in der Mail. */
        'hosting_angebot' => [
            'it' => ['Il suo dominio {domain} è libero',
                "Buongiorno {name},\n\nbuone notizie: il dominio {domain} è libero.\n\n"
                . "Sulla sua pagina personale trova l’offerta con il prezzo mensile e "
                . "tutto quello che è compreso — dominio, spazio web, certificato SSL e "
                . "casella e-mail, con i suoi dati di accesso:\n{link}\n\n"
                . "Decide lì con un clic. Se ha domande, risponda pure a questa e-mail."],
            'de' => ['Ihre Wunschdomain {domain} ist frei',
                "Guten Tag {name},\n\ngute Nachricht: Die Domain {domain} ist frei.\n\n"
                . "Auf Ihrer persönlichen Seite steht das Angebot mit dem Monatspreis und "
                . "allem, was drinsteckt — Domain, Speicherplatz, SSL-Zertifikat und "
                . "E-Mail-Postfach, mit Ihren eigenen Zugangsdaten:\n{link}\n\n"
                . "Dort entscheiden Sie mit einem Klick. Bei Fragen antworten Sie einfach auf diese E-Mail."],
            'en' => ['Your domain {domain} is available',
                "Hello {name},\n\ngood news: the domain {domain} is available.\n\n"
                . "Your personal page has the offer with the monthly price and everything "
                . "included — domain, web space, SSL certificate and email mailbox, with "
                . "your own access details:\n{link}\n\n"
                . "You decide there with one click. Any questions — just reply to this email."],
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
           "dell’acconto"), dazu eine Endung, die sich auf nichts bezog.

           Gemerkt haette man es erst an einem Kunden, der eine Mahnung
           bekommt -- also genau dort, wo eine holprige Zeile am teuersten
           ist: Wer um Geld bittet, dessen Brief muss sitzen.

           Deshalb steht die Bezeichnung jetzt hinter einem Gedankenstrich
           und traegt keinen Artikel mehr. Das haelt auch, wenn morgen eine
           neue Rate dazukommt. */
        'zahlung_erinnerung' => [
            'it' => ['Promemoria: {was} — {betrag}',
                "Buongiorno {name},\n\nle ricordo un pagamento ancora aperto — {was}, {betrag}. Era in scadenza il {faellig}.\n\n"
                . "Probabilmente è solo sfuggito, o il link precedente era scaduto. Eccone uno nuovo, valido due settimane:\n{link}\n\n"
                . "Se ha già pagato, ignori questo messaggio — a volte ci mettiamo un giorno a incrociarci.\n\n"
                . "Se qualcosa non torna, mi scriva e troviamo una soluzione."],
            'de' => ['Erinnerung: {was} — {betrag}',
                "Guten Tag {name},\n\nkurze Erinnerung an eine offene Zahlung — {was}, {betrag}. Fällig war der {faellig}.\n\n"
                . "Wahrscheinlich ist es nur untergegangen, oder der alte Link war abgelaufen. Hier ist ein neuer, zwei Wochen gültig:\n{link}\n\n"
                . "Wenn Sie schon bezahlt haben, ist diese Mail hinfällig — manchmal kreuzen wir uns um einen Tag.\n\n"
                . "Wenn etwas nicht passt, schreiben Sie mir, dann finden wir einen Weg."],
            'en' => ['Reminder: {was} — {betrag}',
                "Hello {name},\n\na short reminder about the {was}: {betrag}, due on {faellig}.\n\n"
                . "It has probably just slipped through, or the old link had expired. Here is a fresh one, valid for two weeks:\n{link}\n\n"
                . "If you have already paid, please ignore this — sometimes we cross by a day.\n\n"
                . "If something is not right, write to me and we will find a way."],
        ],
        'zahlung_mahnung' => [
            'it' => ['Sollecito di pagamento — {was}, {betrag}',
                "Buongiorno {name},\n\nresta aperto un pagamento — {was}, {betrag}. Era dovuto il {faellig} e a oggi non risulta arrivato. "
                . "Le avevo già scritto una volta.\n\n"
                . "Le chiedo di saldare entro il {frist}:\n{link}\n\n"
                . "Se c’è un motivo — una fattura in sospeso, un mese difficile, qualcosa che non va nel lavoro — "
                . "me lo dica e concordiamo qualcosa. Una rateizzazione è sempre meglio di un silenzio.\n\n"
                . "Riferimento: {vorgang}."],
            'de' => ['Zahlungserinnerung — {was}, {betrag}',
                "Guten Tag {name},\n\noffen ist noch eine Zahlung — {was}, {betrag}. Fällig war der {faellig}, eingegangen ist bis heute nichts. "
                . "Ich hatte Ihnen dazu schon einmal geschrieben.\n\n"
                . "Ich bitte Sie, den Betrag bis zum {frist} zu begleichen:\n{link}\n\n"
                . "Wenn es einen Grund gibt — eine offene Rechnung bei Ihnen, ein schwacher Monat, etwas am Ergebnis, das nicht stimmt — "
                . "sagen Sie es mir, dann finden wir eine Lösung. Eine Ratenzahlung ist mir lieber als Schweigen.\n\n"
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
                "Buongiorno {name},\n\nun importo resta non pagato — {was}, {betrag}, scaduto il {faellig}. Questo è il mio terzo e ultimo messaggio.\n\n"
                . "Le do tempo fino al {frist}:\n{link}\n\n"
                . "Se entro quella data non arriva nulla, sospendo il lavoro sul suo sito, che non viene pubblicato "
                . "e i cui diritti d’uso restano miei fino al saldo completo — come previsto dalle condizioni. "
                . "Da quel momento decorrono anche gli interessi di mora di legge.\n\n"
                . "Preferirei di gran lunga sentirla. Una telefonata basta.\n\n"
                . "Riferimento: {vorgang}, cliente {kundennr}."],
            'de' => ['Letzte Mahnung — {was}, {betrag}',
                "Guten Tag {name},\n\neine Zahlung ist weiterhin offen — {was}, {betrag}, fällig am {faellig}. Das ist meine dritte und letzte Nachricht dazu.\n\n"
                . "Ich setze Ihnen eine Frist bis zum {frist}:\n{link}\n\n"
                . "Kommt bis dahin nichts, ruht die Arbeit an Ihrer Website. Sie geht nicht online, und die Nutzungsrechte "
                . "bleiben bis zur vollständigen Zahlung bei mir — so steht es in den Bedingungen. Ab dann laufen außerdem "
                . "die gesetzlichen Verzugszinsen.\n\n"
                . "Mir wäre ein Anruf deutlich lieber. Melden Sie sich einfach.\n\n"
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
                "Buongiorno {name},\n\nun importo resta non pagato — {was}, {betrag}, scaduto il {faellig}. Questo è il mio terzo e ultimo messaggio.\n\n"
                . "Le do tempo fino al {frist}:\n{link}\n\n"
                . "Se entro quella data non arriva nulla, sospendo l’assistenza: niente aggiornamenti, "
                . "niente copie di sicurezza, nessun controllo. Il sito resta online e resta suo — "
                . "quello che si ferma è la manutenzione. Se la situazione non si sblocca, chiudo il "
                . "contratto di assistenza per inadempimento. Da quel momento decorrono anche gli "
                . "interessi di mora di legge.\n\n"
                . "Preferirei di gran lunga sentirla. Una telefonata basta.\n\n"
                . "Riferimento: {vorgang}, cliente {kundennr}."],
            'de' => ['Letzte Mahnung — Betreuung, {betrag}',
                "Guten Tag {name},\n\neine Zahlung ist weiterhin offen — {was}, {betrag}, fällig am {faellig}. Das ist meine dritte und letzte Nachricht dazu.\n\n"
                . "Ich setze Ihnen eine Frist bis zum {frist}:\n{link}\n\n"
                . "Kommt bis dahin nichts, setze ich die Betreuung aus: keine Aktualisierungen, "
                . "keine Sicherungen, keine Kontrolle. Ihre Seite bleibt online und bleibt Ihr Eigentum — "
                . "was ruht, ist die Pflege. Bleibt es dabei, kündige ich den Betreuungsvertrag aus "
                . "wichtigem Grund. Ab dann laufen außerdem die gesetzlichen Verzugszinsen.\n\n"
                . "Mir wäre ein Anruf deutlich lieber. Melden Sie sich einfach.\n\n"
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
                "Buongiorno {name},\n\nho ricevuto il suo pagamento: {was}, {betrag}. Grazie.\n\n"
                . "In allegato trova il documento {nummer} in PDF, da conservare.\n\n"
                . "Tutti i documenti restano anche sulla sua pagina:\n{seite}\n\n"
                . "Se qualcosa non torna, mi scriva e me ne occupo io."],
            'de' => ['{wort} {nummer} — {betrag}',
                "Guten Tag {name},\n\nIhre Zahlung ist angekommen: {was}, {betrag}. Vielen Dank dafür.\n\n"
                . "Im Anhang liegt der {wort} {nummer} als PDF, zum Aufheben.\n\n"
                . "Alle Unterlagen finden Sie außerdem auf Ihrer Seite:\n{seite}\n\n"
                . "Wenn etwas nicht stimmt, schreiben Sie mir — ich kümmere mich darum."],
            'en' => ['{wort} {nummer} — {betrag}',
                "Hello {name},\n\nyour payment has arrived: {was}, {betrag}. Thank you.\n\n"
                . "Attached is document {nummer} as a PDF, for your records.\n\n"
                . "All documents also stay on your page:\n{seite}\n\n"
                . "If anything looks wrong, write to me and I will sort it out."],
        ],
        /* Sofort nach dem Absenden. Zwei Aufgaben: der Kunde weiss, dass es
           angekommen ist — und er hat schwarz auf weiss, dass ihn nichts
           bindet. Beides fehlte bisher ganz. */
        /* ---------- Der E-Mail-Einstieg (24.09.2026, S3) ----------
           Kein Konto, kein Passwort: der Link IST der Zugang. Die vier
           Schritte stehen in der Mail, damit niemand denkt, er habe sich
           bloss fuer einen Newsletter eingetragen. */
        'zugang' => [
            'it' => ['Il suo accesso personale — Vecom Design',
                "Buongiorno{name},\n\necco la sua dashboard personale di Vecom Design:\n\n{link}\n\nDa lì passa tutto, fino alla consegna del sito. I prossimi passi:\n\n1. Il suo progetto: otto domande brevi, poi vede subito un prezzo indicativo.\n2. I suoi dati: qualche domanda sulla sua attività, perché il preventivo sia preciso.\n3. Preventivo e acconto: legge il preventivo con calma e decide lei.\n4. Anteprima e approvazione: vede il suo sito prima che vada online.\n\nNessun account, nessuna password. Apra il link entro {tage} giorni; dopo il primo clic resta valido e può salvarlo tra i preferiti.\n\nNon ha richiesto lei questa e-mail? Allora la ignori: senza un clic sul link non viene salvato nulla.\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Ihr persönlicher Zugang – Vecom Design',
                "Guten Tag{name},\n\nhier ist Ihr persönliches Dashboard bei Vecom Design:\n\n{link}\n\nDarüber läuft alles bis zur Übergabe Ihrer Website. Die nächsten Schritte:\n\n1. Ihr Vorhaben: acht kurze Fragen, danach sehen Sie sofort einen Richtpreis.\n2. Ihre Angaben: ein paar Fragen zu Ihrem Betrieb, damit das Angebot genau passt.\n3. Angebot und Anzahlung: Sie lesen das Angebot in Ruhe und entscheiden.\n4. Entwurf und Freigabe: Sie sehen Ihre Seite, bevor sie online geht.\n\nKein Konto, kein Passwort. Öffnen Sie den Link innerhalb von {tage} Tagen; nach dem ersten Klick bleibt er gültig, und Sie können ihn als Lesezeichen ablegen.\n\nSie haben diese Mail nicht angefordert? Dann ignorieren Sie sie einfach: Ohne einen Klick auf den Link wird nichts gespeichert.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your personal access – Vecom Design',
                "Hello{name},\n\nhere is your personal dashboard at Vecom Design:\n\n{link}\n\nEverything runs through it until your website is handed over. The next steps:\n\n1. Your project: eight short questions, then you see a guide price straight away.\n2. Your details: a few questions about your business, so the quote fits exactly.\n3. Quote and deposit: you read the quote in your own time and decide.\n4. Draft and approval: you see your site before it goes live.\n\nNo account, no password. Open the link within {tage} days; after the first click it stays valid and you can bookmark it.\n\nDidn’t request this email? Then just ignore it: nothing is stored unless the link is clicked.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        /* Schon Kunde: derselbe Link noch einmal (E2). */
        'zugang_bestand' => [
            'it' => ['Il link alla sua dashboard — Vecom Design',
                "Buongiorno{name},\n\nha chiesto di nuovo il link alla sua dashboard. Eccolo:\n\n{link}\n\nÈ lo stesso di sempre: lì trova tutto quello che abbiamo fatto finora.\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Der Link zu Ihrem Dashboard – Vecom Design',
                "Guten Tag{name},\n\nSie haben den Link zu Ihrem Dashboard noch einmal angefordert. Hier ist er:\n\n{link}\n\nEs ist derselbe wie bisher: Dort finden Sie alles, was wir bisher gemacht haben.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['The link to your dashboard – Vecom Design',
                "Hello{name},\n\nyou asked for the link to your dashboard again. Here it is:\n\n{link}\n\nIt is the same one as before: everything we have done so far is there.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        /* D3: ungeoeffnet nach einem Tag -- genau einmal. */
        'zugang_erinnerung' => [
            'it' => ['La sua dashboard la aspetta — Vecom Design',
                "Buongiorno{name},\n\nieri ha chiesto il suo accesso personale, ma il link non è ancora stato aperto. Eccolo di nuovo:\n\n{link}\n\nUn clic basta, e si parte dal suo progetto. Se ha cambiato idea, non deve fare nulla: non le scriverò più per questo.\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Ihr Dashboard wartet – Vecom Design',
                "Guten Tag{name},\n\nSie haben gestern Ihren persönlichen Zugang angefordert, der Link ist aber noch nicht geöffnet. Hier ist er noch einmal:\n\n{link}\n\nEin Klick genügt, dann geht es mit Ihrem Vorhaben los. Haben Sie es sich anders überlegt, müssen Sie nichts tun: Deswegen schreibe ich Ihnen nicht noch einmal.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your dashboard is waiting – Vecom Design',
                "Hello{name},\n\nyesterday you asked for your personal access, but the link has not been opened yet. Here it is again:\n\n{link}\n\nOne click is enough and we start with your project. If you have changed your mind, you don’t need to do anything: I won’t write about this again.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        /* D3: geoeffnet, Vorhaben offen -- nach zwei und nach sieben Tagen. */
        'vorhaben_erinnerung' => [
            'it' => ['Il suo progetto è a un minuto e mezzo — Vecom Design',
                "Buongiorno{name},\n\nnella sua dashboard manca ancora un passo: otto domande brevi sul suo progetto. Subito dopo vede un prezzo indicativo, senza impegno.\n\n{link}\n\nSe preferisce parlarne a voce, risponda semplicemente a questa e-mail.\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Ihr Vorhaben ist anderthalb Minuten entfernt – Vecom Design',
                "Guten Tag{name},\n\nin Ihrem Dashboard fehlt noch ein Schritt: acht kurze Fragen zu Ihrem Vorhaben. Gleich danach sehen Sie einen Richtpreis, unverbindlich.\n\n{link}\n\nWenn Sie lieber darüber sprechen möchten, antworten Sie einfach auf diese Mail.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your project is ninety seconds away – Vecom Design',
                "Hello{name},\n\none step is still open in your dashboard: eight short questions about your project. Right after, you see a guide price, no obligation.\n\n{link}\n\nIf you would rather talk it through, just reply to this email.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        'anfrage_eingegangen' => [
            'it' => ['Ho ricevuto la sua richiesta',
                "Buongiorno {name},\n\ngrazie per la sua richiesta{paketsatz}. È arrivata e la sto leggendo con calma. Le rispondo entro un giorno lavorativo con una prima indicazione concreta.\n\nLa richiesta è gratuita e senza impegno: un incarico nasce soltanto quando ci accordiamo per iscritto.\n\nDa qui in poi passa tutto da questa pagina:\n\n{link}\n\nLì vede sempre a che punto siamo, può scrivermi e caricare i suoi documenti (fino a {maxdatei} per file). Nessun account, nessuna password. La salvi tra i preferiti: il link resta valido, dal primo contatto fino a molto dopo la messa online.\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Ihre Anfrage ist angekommen',
                "Guten Tag {name},\n\nvielen Dank für Ihre Anfrage{paketsatz}. Sie ist da, und ich lese sie in Ruhe durch. Innerhalb eines Werktags hören Sie von mir, mit einer ersten konkreten Einschätzung.\n\nDie Anfrage ist kostenlos und unverbindlich: Ein Auftrag entsteht erst, wenn wir uns schriftlich einig sind.\n\nAlles Weitere läuft über diese eine Seite:\n\n{link}\n\nDort sehen Sie jederzeit, was gerade dran ist, können mir schreiben und Unterlagen hochladen (bis {maxdatei} je Datei). Kein Konto, kein Passwort. Legen Sie sie als Lesezeichen ab — der Link bleibt gültig, vom ersten Kontakt bis lange nach dem Onlinegang.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your enquiry has arrived',
                "Hello {name},\n\nthank you for your enquiry{paketsatz}. It has arrived and I am reading it properly. You will hear from me within one working day, with a first concrete assessment.\n\nThe enquiry is free and without obligation: a project only comes about once we agree in writing.\n\nEverything else runs through this one page:\n\n{link}\n\nThere you can always see what is due next, write to me and upload your material (up to {maxdatei} per file). No account, no password. Bookmark it — the link stays valid, from the first contact until long after going live.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        /* Der Zahlungslink, wenn der Kunde zugesagt hat. */
        'zahlungslink' => [
            'it' => ['Il link per il pagamento — {paket}',
                "Buongiorno {name},\n\ncome concordato, ecco il link per il pagamento — {was}, {betrag}:\n\n{link}\n\nIl pagamento avviene tramite un fornitore certificato; i dati della carta non passano da me. Appena arriva le scrivo e partiamo.\n\nSe qualcosa non torna, risponda a questa e-mail prima di pagare.\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Ihr Zahlungslink — {paket}',
                "Guten Tag {name},\n\nwie besprochen hier der Link für die Zahlung — {was}, {betrag}:\n\n{link}\n\nBezahlt wird über einen geprüften Anbieter; Ihre Kartendaten sehe ich nicht. Sobald die Zahlung da ist, melde ich mich und wir legen los.\n\nWenn etwas nicht stimmt, antworten Sie einfach auf diese E-Mail, bevor Sie zahlen.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your payment link — {paket}',
                "Hello {name},\n\nas agreed, here is the payment link — {was}, {betrag}:\n\n{link}\n\nPayment runs through a certified provider; I never see your card details. As soon as it arrives I will write and we start.\n\nIf anything looks wrong, just reply to this email before paying.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        'zahlung_ok' => [
            'it' => ['Pagamento ricevuto — {paket}',
                "Buongiorno {name},\n\nho ricevuto il suo acconto di {betrag}. Grazie!\n\nOra iniziamo: il prossimo passo è raccontarmi il suo progetto.\nApra questo link e compili con calma — può salvare e continuare più tardi:\n\n{link}\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Zahlung erhalten — {paket}',
                "Guten Tag {name},\n\nIhre Anzahlung über {betrag} ist angekommen. Vielen Dank!\n\nJetzt geht es los: Der nächste Schritt ist, mir Ihr Projekt zu beschreiben.\nÖffnen Sie diesen Link und füllen Sie ihn in Ruhe aus — Sie können zwischendurch speichern:\n\n{link}\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Payment received — {paket}',
                "Hello {name},\n\nyour deposit of {betrag} has arrived. Thank you!\n\nNext step: tell me about your project.\nOpen this link and take your time — you can save and come back:\n\n{link}\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        /* NACH EINEM ANRUF: DER WEG ZURUECK ZUR EIGENEN SEITE
           ------------------------------------------------------------------
           Am Telefon faellt kein Betrag und kein Stand. Wer nicht
           weiterkommt, bekommt stattdessen diese Mail -- an die HINTERLEGTE
           Adresse, nie an eine, die am Telefon genannt wurde. Auf der Seite
           steht dann alles, was er wissen darf, weil dort der Link der
           Ausweis ist und nicht die Stimme. */
        'kundenseite' => [
            'it' => ['La sua pagina — Vecom Design',
                "Buongiorno {name},\n\ncome detto al telefono, ecco la sua pagina:\n\n{link}\n\nLì trova sempre a che punto siamo e cosa può fare adesso. Il link è personale — non serve password.\n\nSe qualcosa non torna, risponda a questa e-mail.\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Ihre Seite — Vecom Design',
                "Guten Tag {name},\n\nwie am Telefon besprochen, hier Ihre Seite:\n\n{link}\n\nDort steht immer, wo wir stehen und was Sie gerade tun können. Der Link gehört Ihnen persönlich — ein Passwort brauchen Sie nicht.\n\nWenn etwas nicht stimmt, antworten Sie einfach auf diese E-Mail.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your page — Vecom Design',
                "Hello {name},\n\nas discussed on the phone, here is your page:\n\n{link}\n\nIt always shows where we stand and what you can do right now. The link is personal — no password needed.\n\nIf anything looks wrong, just reply to this email.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        /* NACH DEM ANRUF
           ------------------------------------------------------------------
           Die Mail, die aus einem Gespraech einen Auftrag macht -- oder eben
           nicht. Deshalb steht hier kein Rabatt, keine Frist und kein
           „melden Sie sich bald": Wer nach einem Telefonat gedraengt wird,
           antwortet nicht mehr. Es steht nur, worueber gesprochen wurde,
           damit er es morgen noch weiss, und der Fragebogen, in dem seine
           Antworten schon drinstehen.

           Die Platzhalter duerfen leer bleiben. Ist keine Spanne genannt
           worden, faellt die Zeile weg -- Telefon::uebergabe raeumt die
           entstehenden Leerzeilen weg. */
        'uebergabe' => [
            'it' => ['Come promesso al telefono',
                "Buongiorno{name},\n\ncome promesso, ecco tutto per iscritto — così lo ha anche domani.\n\n{block}\n\nQui trova il questionario con dentro già le sue risposte — bastano pochi minuti per completarlo, e da lì esce il preventivo:\n\n{link}\n\nNessuna fretta e nessun impegno. Se preferisce parlarne, risponda a questa e-mail.\n\nUwe Vetter · Vecom Design"],
            'de' => ['Wie am Telefon besprochen',
                "Guten Tag{name},\n\nwie versprochen alles noch einmal schriftlich — damit Sie es morgen auch noch haben.\n\n{block}\n\nHier ist der Fragebogen, in dem Ihre Antworten schon stehen — die letzten Angaben dauern ein paar Minuten, und daraus entsteht das Angebot:\n\n{link}\n\nKeine Eile und keine Verpflichtung. Wenn Sie lieber sprechen möchten, antworten Sie einfach auf diese E-Mail.\n\nUwe Vetter · Vecom Design"],
            'en' => ['As promised on the phone',
                "Hello{name},\n\nas promised, here it all is in writing — so you still have it tomorrow.\n\n{block}\n\nHere is the questionnaire with your answers already filled in — the rest takes a few minutes, and the quote comes out of it:\n\n{link}\n\nNo hurry and no obligation. If you would rather talk it through, just reply to this email.\n\nUwe Vetter · Vecom Design"],
        ],
        /* Die Einladung zum grossen Fragebogen VOR dem Preis (21.09.2026).
           Nicht 'zahlung_ok' -- die sagt "deine Anzahlung ist angekommen",
           und vor dem Preis ist nichts angekommen. */
        'fragebogen_vorab' => [
            'it' => ['Prima di darle un prezzo',
                "Buongiorno {name},\n\ngrazie per le sue indicazioni. Prima di darle un prezzo voglio capire bene di cosa ha bisogno — altrimenti tirerei a indovinare, e alla fine lo pagherebbe Lei.\n\nPer questo c’è un questionario. Lo trova sulla sua pagina; può salvare e continuare più tardi:\n\n{link}\n\nAppena lo ricevo, le mando un preventivo a prezzo fisso. Fino ad allora nulla è vincolante.\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Bevor ich Ihnen einen Preis nenne',
                "Guten Tag {name},\n\nvielen Dank für Ihre Angaben. Bevor ich Ihnen einen Preis nenne, möchte ich genau verstehen, was Sie brauchen — sonst rate ich, und das zahlen am Ende Sie.\n\nDafür gibt es einen Fragebogen. Er steht auf Ihrer Seite; Sie können zwischendurch speichern und später weitermachen:\n\n{link}\n\nSobald er da ist, bekommen Sie von mir ein Angebot mit festem Preis. Bis dahin ist nichts verbindlich.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Before I give you a price',
                "Hello {name},\n\nthank you for the details. Before I give you a price, I want to understand exactly what you need — otherwise I would be guessing, and in the end you would pay for it.\n\nThat is what the questionnaire is for. It is on your page; you can save and continue later:\n\n{link}\n\nAs soon as it is in, you will get a fixed-price quote from me. Until then nothing is binding.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        'fragebogen_erinnerung' => [
            'it' => ['Un promemoria per il suo progetto',
                "Buongiorno {name},\n\nmanca ancora il questionario per il suo progetto. Senza quelle informazioni non possiamo iniziare davvero.\n\nEccolo — mancano circa {minuten} minuti:\n\n{link}\n\nSe qualcosa non è chiaro, risponda pure a questa e-mail.\n\nUwe Vetter · Vecom Design"],
            'de' => ['Kurze Erinnerung an Ihren Fragebogen',
                "Guten Tag {name},\n\nfür Ihr Projekt fehlt noch der Fragebogen. Ohne die Angaben können wir nicht richtig loslegen.\n\nHier ist er — es sind noch etwa {minuten} Minuten:\n\n{link}\n\nWenn etwas unklar ist, antworten Sie einfach auf diese E-Mail.\n\nUwe Vetter · Vecom Design"],
            'en' => ['A quick reminder about your questionnaire',
                "Hello {name},\n\nthe questionnaire for your project is still open. Without it we can’t really start.\n\nHere it is — about {minuten} minutes to go:\n\n{link}\n\nIf anything is unclear, just reply to this email.\n\nUwe Vetter · Vecom Design"],
        ],
        'vorschau' => [
            'it' => ['Può dare un’occhiata all’anteprima — {paket}',
                "Buongiorno {name},\n\nl’anteprima del suo sito è visibile. La guardi con calma:\n\n{link}\n\nNon deve approvare niente adesso: il sito non è ancora finito. Mi dica solo cosa ne pensa — quello che non va lo sistemo. Quando è pronto davvero la avviso, e solo allora potrà dare il via libera.\n\nUwe Vetter · Vecom Design"],
            'de' => ['Sie können sich den Entwurf ansehen — {paket}',
                "Guten Tag {name},\n\nder Entwurf Ihrer Website ist für Sie freigeschaltet. Sehen Sie ihn sich in Ruhe an:\n\n{link}\n\nFreigeben müssen Sie noch nichts — die Seite ist noch nicht fertig. Sagen Sie mir einfach, was Ihnen auffällt; was nicht passt, ändere ich. Wenn sie wirklich fertig ist, melde ich mich, und erst dann können Sie sie abnehmen.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
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
            'it' => ['Il suo sito è pronto — gli dia un’occhiata finale — {paket}',
                "Buongiorno {name},\n\nil sito è finito. Lo guardi con calma:\n\n{link}\n\nSe va bene così, dia il via libera dalla sua pagina: da lì pubblico.\n\nSe invece c’è ancora qualcosa da cambiare, me lo scriva — le modifiche che rientrano in quanto concordato sono comprese. Se una richiesta va oltre, glielo dico prima e le mando il preventivo con il prezzo: senza il suo ok non parte niente e non le arriva nessun costo a sorpresa.\n\nUwe Vetter · Vecom Design"],
            'de' => ['Ihre Seite ist fertig — sehen Sie sie sich an — {paket}',
                "Guten Tag {name},\n\ndie Seite ist fertig. Sehen Sie sie sich in Ruhe an:\n\n{link}\n\nWenn sie so passt, geben Sie sie auf Ihrer Seite frei — dann veröffentliche ich.\n\nWenn noch etwas anders sein soll, schreiben Sie es mir. Änderungen im vereinbarten Umfang sind enthalten. Geht ein Wunsch darüber hinaus, sage ich Ihnen das vorher und schicke Ihnen ein Angebot mit dem Preis: Ohne Ihr Ja passiert nichts, und es kommt nichts nachträglich dazu.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your site is ready — take a look — {paket}',
                "Hello {name},\n\nthe site is finished. Take your time with it:\n\n{link}\n\nIf it’s right, sign it off from your page — then I’ll publish it.\n\nIf something should still change, tell me. Changes within the agreed scope are included. If a request goes beyond that, I’ll say so first and send you a quote with the price: nothing happens without your go-ahead, and nothing is added afterwards.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        /* DIE WEBSITE ZUM MITNEHMEN
           ------------------------------------------------------------------
           Nicht "hier ist deine Rechnung", sondern "das gehoert dir". Der
           Satz dazu ist wichtiger als die Datei: Wer ein ZIP bekommt und
           nicht weiss, was er damit soll, legt es weg. Also steht drin,
           WOFUER es gut ist — umziehen, sichern, jemand anderem geben —
           und ausdruecklich, dass er dafuer nicht kuendigen muss.

           Der Anhang ist bewusst keiner: Dreissig Megabyte ZIP kommen bei
           den meisten Postfaechern gar nicht an und landen sonst im Spam.
           Der Link fuehrt auf seine Projektseite, die er kennt. */
        'paket' => [
            'it' => ['Il suo sito da portare con sé — {paket}',
                "Buongiorno {name},\n\nil suo sito è pronto anche da scaricare: tutti i file, in un unico pacchetto ({datei}).\n\nLo trova qui:\n{link}\n\nÈ suo. Le serve se un giorno vuole cambiare hosting, se vuole una copia di sicurezza, o se qualcun altro ci deve lavorare. Non deve disdire niente per averlo — il sito resta online come prima.\n\nSe ha bisogno di una mano per usarlo, mi scriva.\n\nUwe Vetter · Vecom Design"],
            'de' => ['Ihre Website zum Mitnehmen — {paket}',
                "Guten Tag {name},\n\nIhre Website liegt jetzt auch zum Herunterladen bereit: alle Dateien in einem Paket ({datei}).\n\nSie finden es hier:\n{link}\n\nEs gehört Ihnen. Sie brauchen es, wenn Sie irgendwann den Anbieter wechseln wollen, wenn Sie eine Sicherung haben möchten, oder wenn jemand anderes daran arbeiten soll. Kündigen müssen Sie dafür nichts — die Seite bleibt online wie bisher.\n\nWenn Sie Hilfe brauchen, melden Sie sich einfach.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your website to take with you — {paket}',
                "Hello {name},\n\nyour website is now also ready to download: every file, in one package ({datei}).\n\nYou’ll find it here:\n{link}\n\nIt’s yours. You’ll want it if you ever move to another host, if you’d like a backup, or if someone else is to work on it. You don’t have to cancel anything for this — the site stays online as before.\n\nIf you need a hand with it, just get in touch.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        'online' => [
            'it' => ['Il suo sito è online — {paket}',
                "Buongiorno {name},\n\nil sito è online:\n{link}\n\nGrazie per la fiducia. Se serve qualcosa, sono qui.\n\nUwe Vetter · Vecom Design"],
            'de' => ['Ihre Website ist online — {paket}',
                "Guten Tag {name},\n\ndie Website ist online:\n{link}\n\nDanke für Ihr Vertrauen. Wenn etwas ist, melden Sie sich einfach.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your site is live — {paket}',
                "Hello {name},\n\nthe site is live:\n{link}\n\nThank you for your trust. If anything comes up, just get in touch.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        'nachricht' => [
            'it' => ['Un messaggio sul suo progetto',
                "Buongiorno {name},\n\nle ho scritto sul suo progetto:\n\n{text}\n\nPuò rispondere qui:\n{link}\n\nUwe Vetter · Vecom Design"],
            'de' => ['Eine Nachricht zu Ihrem Projekt',
                "Guten Tag {name},\n\nich habe Ihnen zu Ihrem Projekt geschrieben:\n\n{text}\n\nAntworten können Sie hier:\n{link}\n\nUwe Vetter · Vecom Design"],
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
                "Buongiorno {name},\n\n"
                . "questa è la conferma del suo ordine. La conservi: contiene tutte le informazioni sul contratto.\n\n"
                . "--------------------------------------------------\nIL SUO ORDINE\n--------------------------------------------------\n"
                . "Ordine:     {bestellnr}\nData:       {datum}\nServizio:   {paket}\n"
                . "Totale:     {gesamt}\n{raten}\n\n"
                . "--------------------------------------------------\nCHI LE FORNISCE IL SERVIZIO\n--------------------------------------------------\n"
                . "{firma}\n\n"
                . "--------------------------------------------------\nDIRITTO DI RECESSO\n--------------------------------------------------\n"
                . "{widerruf}\n\n"
                . "In allegato trova il modulo di recesso tipo. Non deve usarlo per forza: basta una comunicazione chiara.\n\n"
                . "{zustimmung}\n\n"
                . "Condizioni generali: {agb}\nInformativa privacy: {privacy}\n\n"
                . "La sua pagina di progetto:\n\n{link}\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Auftragsbestätigung {bestellnr} — {paket}',
                "Guten Tag {name},\n\n"
                . "das ist die Bestätigung Ihres Auftrags. Bewahren Sie sie auf — sie enthält alle Angaben zum Vertrag.\n\n"
                . "--------------------------------------------------\nIHR AUFTRAG\n--------------------------------------------------\n"
                . "Bestellung: {bestellnr}\nDatum:      {datum}\nLeistung:   {paket}\n"
                . "Gesamt:     {gesamt}\n{raten}\n\n"
                . "--------------------------------------------------\nWER DIE LEISTUNG ERBRINGT\n--------------------------------------------------\n"
                . "{firma}\n\n"
                . "--------------------------------------------------\nWIDERRUFSRECHT\n--------------------------------------------------\n"
                . "{widerruf}\n\n"
                . "Im Anhang finden Sie das Muster-Widerrufsformular. Sie müssen es nicht benutzen — eine eindeutige Nachricht genügt.\n\n"
                . "{zustimmung}\n\n"
                . "AGB: {agb}\nDatenschutzerklärung: {privacy}\n\n"
                . "Ihre Projektseite:\n\n{link}\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
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
                "Buongiorno {name},\n\nho ricevuto la sua disdetta e gliela confermo per iscritto.\n\n"
                . "{paket} resta attiva fino al {ende}.\n"
                . "Fino a quella data le viene addebitato {betrag} al mese, dopo non più — l’ultimo addebito è quello del mese in cui rientra il {ende}.\n\n"
                . "Cosa succede dopo:\n\n"
                . "· Il sito resta online e resta suo. Non si spegne nulla.\n"
                . "· Aggiornamenti, backup e controlli si fermano. Da quel giorno il sito è nelle sue mani o in quelle di chi vorrà.\n"
                . "· Su richiesta le do tutti gli accessi e un backup completo, così può spostarlo dove preferisce.\n\n"
                . "La sua pagina resta raggiungibile anche dopo: {seite}\n\n"
                . "Se ha disdetto per qualcosa che non ha funzionato, me lo scriva — mi interessa davvero, anche se non cambia idea."],
            'de' => ['Kündigung bestätigt — {paket}',
                "Guten Tag {name},\n\nIhre Kündigung ist angekommen, und hiermit bestätige ich sie Ihnen schriftlich.\n\n"
                . "{paket} läuft noch bis zum {ende}.\n"
                . "Bis dahin werden {betrag} im Monat abgebucht, danach nicht mehr — die letzte Abbuchung ist die für den Monat, in den der {ende} fällt.\n\n"
                . "Was danach passiert:\n\n"
                . "· Die Website bleibt online und gehört weiter Ihnen. Es wird nichts abgeschaltet.\n"
                . "· Aktualisierungen, Sicherungen und Überwachung hören auf. Ab dem Tag liegt die Seite in Ihrer Hand oder in der von jemandem, den Sie beauftragen.\n"
                . "· Auf Wunsch bekommen Sie alle Zugänge und eine vollständige Sicherung, damit Sie sie mitnehmen können.\n\n"
                . "Ihre Seite bleibt auch danach erreichbar: {seite}\n\n"
                . "Wenn Sie gekündigt haben, weil etwas nicht gepasst hat, schreiben Sie es mir — das interessiert mich wirklich, auch wenn Sie es sich nicht anders überlegen."],
            'en' => ['Cancellation confirmed — {paket}',
                "Hello {name},\n\nyour cancellation has arrived, and this is your written confirmation.\n\n"
                . "{paket} runs until {ende}.\n"
                . "Until then {betrag} per month is charged, after that it stops — the last charge is the one for the month that {ende} falls in.\n\n"
                . "What happens afterwards:\n\n"
                . "· The site stays online and stays yours. Nothing gets switched off.\n"
                . "· Updates, backups and monitoring stop. From that day the site is in your hands, or in those of whoever you appoint.\n"
                . "· On request you get all the logins and a full backup, so you can take it anywhere.\n\n"
                . "Your page stays reachable afterwards too: {seite}\n\n"
                . "If you cancelled because something wasn’t right, tell me — I genuinely want to know, even if you don’t change your mind."],
        ],

        'restzahlung' => [
            'it' => ['Saldo per {paket}',
                "Buongiorno {name},\n\nil sito è pronto per la consegna. Resta il saldo di {betrag}:\n\n{link}\n\nGrazie!\nUwe Vetter · Vecom Design"],
            'de' => ['Restzahlung für {paket}',
                "Guten Tag {name},\n\ndie Website ist bereit zur Übergabe. Offen ist noch die Restzahlung über {betrag}:\n\n{link}\n\nDanke!\nUwe Vetter · Vecom Design"],
            'en' => ['Balance for {paket}',
                "Hello {name},\n\nthe site is ready for handover. The remaining balance is {betrag}:\n\n{link}\n\nThank you!\nUwe Vetter · Vecom Design"],
        ],
        /* Das individuelle Angebot: Wird es in der Verwaltung verschickt, geht
           diese Mail an den Kunden — mit dem Link, unter dem er das Angebot
           ansieht und annimmt. Vorher blieb der Link in der Verwaltung liegen
           und der Kunde bekam nichts. */
        /* KEIN PREIS IN DER MAIL (22.09.2026)
           Der Betrag stand in der Betreffzeile und im ersten Satz -- also im
           Vorschautext jedes Postfachs, lesbar fuer jeden, der zufaellig auf
           den Bildschirm sieht, und weitergeleitet mit jedem "Fwd:". Das
           Angebot gehoert auf die Kundenseite: Dort steht es vollstaendig,
           dort wird es angenommen oder abgelehnt, und dort ist es auch in
           vier Wochen noch zu finden. Die Mail sagt nur noch, dass es da
           ist. */
        'angebot' => [
            'it' => ['Il suo preventivo è pronto',
                "Buongiorno {name},\n\nil suo preventivo personale è pronto e la aspetta sulla sua pagina:\n\n{link}\n\n"
                . "Lì lo vede per intero, voce per voce, e da lì può accettarlo o rifiutarlo.{gueltigsatz}\n\n"
                . "Domande? Risponda pure a questa e-mail.\n\nUwe Vetter · Vecom Design"],
            'de' => ['Ihr Angebot liegt bereit',
                "Guten Tag {name},\n\nIhr persönliches Angebot ist fertig und liegt auf Ihrer Seite:\n\n{link}\n\n"
                . "Dort sehen Sie es vollständig, Punkt für Punkt, und dort können Sie es annehmen oder ablehnen.{gueltigsatz}\n\n"
                . "Fragen? Antworten Sie einfach auf diese E-Mail.\n\nUwe Vetter · Vecom Design"],
            'en' => ['Your quote is ready',
                "Hello {name},\n\nyour personal quote is ready and waiting on your page:\n\n{link}\n\n"
                . "There you can see it in full, item by item, and accept or decline it.{gueltigsatz}\n\n"
                . "Questions? Just reply to this email.\n\nUwe Vetter · Vecom Design"],
        ],
    ];

    /* ----------------------------------------------------------------------
       Der Bedarfs-Konfigurator auf der Website.

       Der Ton ist derselbe wie ueberall: siezen, kurze Saetze, und was der
       Kunde tun soll, steht im ersten Satz. Was hier NICHT steht, ist ein
       Preis — der entsteht erst am Ende aus seinen Antworten.
       ---------------------------------------------------------------------- */
    /* Die Seite zugang.php und die Felder auf der Startseite (24.09.2026, E1).
       Der Satz nach dem Absenden ist fuer jede Adresse derselbe (E2) --
       ob neu, schon Kunde oder frei erfunden. */
    public const ZUGANG = [
        'titel' => ['it' => 'La sua dashboard personale', 'de' => 'Ihr persönliches Dashboard', 'en' => 'Your personal dashboard'],
        'lead'  => [
            'it' => 'Inserisca il suo indirizzo e-mail: le mando il link alla sua dashboard personale. Da lì passa tutto, fino alla consegna del sito.',
            'de' => 'Tragen Sie Ihre E-Mail-Adresse ein: Ich schicke Ihnen den Link zu Ihrem persönlichen Dashboard. Darüber läuft alles bis zur Übergabe Ihrer Website.',
            'en' => 'Enter your email address and I will send you the link to your personal dashboard. Everything runs through it until your website is handed over.'],
        'feld'  => ['it' => 'Il suo indirizzo e-mail', 'de' => 'Ihre E-Mail-Adresse', 'en' => 'Your email address'],
        'knopf' => ['it' => 'Inviarmi la dashboard', 'de' => 'Mein Dashboard zusenden', 'en' => 'Send me my dashboard'],
        'schritte' => [
            'it' => 'Progetto · Dati · Preventivo · Anteprima · Online',
            'de' => 'Vorhaben · Angaben · Angebot · Entwurf · Online',
            'en' => 'Project · Details · Quote · Draft · Live'],
        'hinweis' => [
            'it' => 'Nessun account, nessuna password. Gratuito e senza impegno.',
            'de' => 'Kein Konto, kein Passwort. Kostenlos und unverbindlich.',
            'en' => 'No account, no password. Free and without obligation.'],
        'gesendet' => [
            'it' => 'Fatto. Controlli la sua casella di posta: il link è in arrivo. Non lo trova? Guardi anche nella cartella spam.',
            'de' => 'Erledigt. Sehen Sie in Ihr Postfach: Der Link ist unterwegs. Nicht da? Schauen Sie auch im Spam-Ordner nach.',
            'en' => 'Done. Check your inbox: the link is on its way. Not there? Have a look in your spam folder too.'],
        'ungueltig' => [
            'it' => 'Questo indirizzo non sembra corretto. Lo controlli, per favore.',
            'de' => 'Diese Adresse scheint nicht zu stimmen. Bitte prüfen Sie sie noch einmal.',
            'en' => 'That address doesn’t look right. Please check it again.'],
        'abgelaufen' => [
            'it' => 'Questo link è scaduto. Inserisca di nuovo il suo indirizzo e gliene mando uno nuovo.',
            'de' => 'Dieser Link ist abgelaufen. Tragen Sie Ihre Adresse noch einmal ein, dann schicke ich Ihnen einen neuen.',
            'en' => 'This link has expired. Enter your address again and I will send you a new one.'],
        'unbekannt' => [
            'it' => 'Questo link non è valido. Inserisca il suo indirizzo e gliene mando uno nuovo.',
            'de' => 'Dieser Link gilt nicht. Tragen Sie Ihre Adresse ein, dann schicke ich Ihnen einen neuen.',
            'en' => 'This link is not valid. Enter your address and I will send you a new one.'],
        'panne' => [
            'it' => 'Qualcosa non ha funzionato. Riprovi tra poco oppure mi scriva direttamente.',
            'de' => 'Etwas hat nicht geklappt. Versuchen Sie es gleich noch einmal oder schreiben Sie mir direkt.',
            'en' => 'Something went wrong. Try again shortly or write to me directly.'],
        'datenschutz' => [
            'it' => 'Uso il suo indirizzo solo per inviarle il link e per il suo progetto. Se non apre il link, viene cancellato dopo {tage} giorni.',
            'de' => 'Ich nutze Ihre Adresse nur für den Link und für Ihr Vorhaben. Öffnen Sie den Link nicht, wird sie nach {tage} Tagen gelöscht.',
            'en' => 'I only use your address to send the link and for your project. If you don’t open the link, it is deleted after {tage} days.'],
    ];

    public const BEDARF = [
        'titel' => ['it' => 'Di che cosa ha bisogno?', 'de' => 'Was brauchen Sie?', 'en' => 'What do you need?'],
        'lead'  => [
            'it' => 'Otto domande brevi, circa un minuto e mezzo. Alla fine sa in che ordine di prezzo si muove — senza impegno.',
            'de' => 'Acht kurze Fragen, etwa anderthalb Minuten. Am Ende wissen Sie, in welcher Größenordnung Sie liegen — unverbindlich.',
            'en' => 'Eight short questions, about ninety seconds. At the end you know the ballpark — no obligation.',
        ],
        'schritt'  => ['it' => 'Passo {n} di {g}', 'de' => 'Schritt {n} von {g}', 'en' => 'Step {n} of {g}'],
        'weiter'   => ['it' => 'Avanti', 'de' => 'Weiter', 'en' => 'Next'],
        'zurueck'  => ['it' => 'Indietro', 'de' => 'Zurück', 'en' => 'Back'],
        'absenden' => ['it' => 'Richiedere il preventivo', 'de' => 'Angebot anfordern', 'en' => 'Request a quote'],

        'ergebnisTitel' => [
            'it' => 'Per quello che ha descritto',
            'de' => 'Für das, was Sie beschrieben haben',
            'en' => 'For what you have described',
        ],
        'ergebnisText' => [
            'it' => 'Questa è una stima, non un preventivo. Il prezzo definitivo glielo mando entro 24 ore, con le voci una per una — e vale quello.',
            'de' => 'Das ist eine Schätzung, kein Angebot. Den verbindlichen Preis schicke ich Ihnen binnen 24 Stunden, Position für Position — und der gilt dann.',
            'en' => 'This is an estimate, not a quote. I will send you the binding price within 24 hours, item by item — and that one holds.',
        ],
        'ergebnisMonat' => [
            'it' => 'più {betrag} al mese per l’assistenza, se la desidera. È un contratto a parte e può decidere dopo.',
            'de' => 'dazu {betrag} im Monat für die Betreuung, wenn Sie möchten. Das ist ein eigener Vertrag, und Sie können später entscheiden.',
            'en' => 'plus {betrag} a month for care, if you want it. That is a separate contract and you can decide later.',
        ],
        'kontaktTitel' => [
            'it' => 'Dove le mando il preventivo?',
            'de' => 'Wohin schicke ich das Angebot?',
            'en' => 'Where should I send the quote?',
        ],
        /* Im Dashboard (D1, D2): Die Adresse ist schon da, gefragt wird nur,
           wie ich ihn ansprechen soll -- und ob er mir eine Nummer gibt. */
        'kontaktTitelDashboard' => [
            'it' => 'Come posso chiamarla?',
            'de' => 'Wie darf ich Sie ansprechen?',
            'en' => 'How should I address you?',
        ],
        'absendenDashboard' => ['it' => 'Inviare il progetto', 'de' => 'Vorhaben absenden', 'en' => 'Send your project'],
        'zumDashboard'      => ['it' => '← La sua dashboard', 'de' => '← Ihr Dashboard', 'en' => '← Your dashboard'],
        'emailFest'         => ['it' => 'Le scrivo a', 'de' => 'Ich schreibe Ihnen an', 'en' => 'I will write to'],
        'fName'    => ['it' => 'Il suo nome', 'de' => 'Ihr Name', 'en' => 'Your name'],
        'fEmail'   => ['it' => 'E-mail', 'de' => 'E-Mail', 'en' => 'Email'],
        'fTelefon' => ['it' => 'Telefono (facoltativo)', 'de' => 'Telefon (freiwillig)', 'en' => 'Phone (optional)'],
        'fFirma'   => ['it' => 'Nome dell’attività (facoltativo)', 'de' => 'Name des Betriebs (freiwillig)', 'en' => 'Business name (optional)'],

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
            'it' => 'In che lingua desidera che le scriva',
            'de' => 'In welcher Sprache soll ich Ihnen schreiben',
            'en' => 'Which language should I write to you in',
        ],
        'fSpracheHilfe' => [
            'it' => 'Vale per le e-mail, i documenti e la sua pagina. Può cambiarla in qualsiasi momento.',
            'de' => 'Gilt für E-Mails, Unterlagen und Ihre eigene Seite. Sie können sie jederzeit ändern.',
            'en' => 'Applies to emails, documents and your own page. You can change it at any time.',
        ],

        'danke' => [
            'it' => 'Grazie! Ho ricevuto tutto. Le scrivo entro 24 ore con il preventivo.',
            'de' => 'Danke! Alles angekommen. Ich melde mich binnen 24 Stunden mit dem Angebot.',
            'en' => 'Thank you! I have everything. I will come back to you within 24 hours with the quote.',
        ],
        'pflicht' => [
            'it' => 'Mi servono almeno il nome e un indirizzo e-mail valido.',
            'de' => 'Ich brauche mindestens Ihren Namen und eine gültige E-Mail-Adresse.',
            'en' => 'I need at least your name and a valid email address.',
        ],
        'nichts' => [
            'it' => 'Scelga almeno una risposta, così posso calcolare qualcosa.',
            'de' => 'Wählen Sie mindestens eine Antwort, damit ich etwas rechnen kann.',
            'en' => 'Pick at least one answer so I have something to work with.',
        ],
        'panne' => [
            'it' => 'Qualcosa non ha funzionato. Riprovi tra poco — quello che ha già scelto è salvato.',
            'de' => 'Etwas hat nicht geklappt. Versuchen Sie es gleich noch einmal — was Sie gewählt haben, ist gespeichert.',
            'en' => 'Something went wrong. Try again shortly — what you picked is saved.',
        ],
        'weg' => [
            'it' => 'Questo link non è più valido. Può ricominciare da capo.',
            'de' => 'Dieser Link gilt nicht mehr. Sie können neu anfangen.',
            'en' => 'This link is no longer valid. You can start again.',
        ],
        'neu' => ['it' => 'Ricominciare', 'de' => 'Neu anfangen', 'en' => 'Start again'],
        'fEmpfehlung' => [
            'it' => 'Chi le ha consigliato noi? (facoltativo)',
            'de' => 'Wer hat uns empfohlen? (freiwillig)',
            'en' => 'Who recommended us? (optional)',
        ],
        'empfehlungHilfe' => [
            'it' => 'Il nome basta. Se diventa un lavoro, chi ci ha consigliati riceve uno sconto sull’assistenza.',
            'de' => 'Der Name genügt. Wird ein Auftrag daraus, bekommt derjenige einen Nachlass auf seine Betreuung.',
            'en' => 'A name is enough. If it turns into a job, they get a discount on their care plan.',
        ],
        'empfehlungErkannt' => [
            'it' => 'Consigliato da {name} — grazie a entrambi.',
            'de' => 'Empfohlen von {name} — vielen Dank an Sie beide.',
            'en' => 'Recommended by {name} — thank you both.',
        ],
        // Wer aus einer Branchen-Demo der Startseite kommt, bringt seine
        // Auswahl mit. Sie steht oben in der Anfrage, damit Uwe weiss,
        // wovon der Kunde ausgeht, ohne nachfragen zu muessen.
        'demoAusgang' => [
            'it' => 'Punto di partenza: {wahl}',
            'de' => 'Ausgangspunkt: {wahl}',
            'en' => 'Starting point: {wahl}',
        ],
        'demoErkannt' => [
            'it' => 'Parto dalla sua scelta nella demo: {wahl}.',
            'de' => 'Ich gehe von Ihrer Auswahl in der Demo aus: {wahl}.',
            'en' => 'I’m starting from your choice in the demo: {wahl}.',
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
            'it' => 'Salvo a ogni passo. Può chiudere e tornare con lo stesso link.',
            'de' => 'Ich speichere bei jedem Schritt. Sie können die Seite schließen und mit demselben Link zurückkommen.',
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
            'it' => 'Il prezzo per il suo sito',
            'de' => 'Der Preis für Ihre Website',
            'en' => 'The price for your website',
        ],
        'preisEinleitung' => [
            'it' => 'grazie per le sue indicazioni. In base a quello che mi ha descritto, il sito viene {preis}.',
            'de' => 'vielen Dank für Ihre Angaben. Nach dem, was Sie beschrieben haben, kostet die Website {preis}.',
            'en' => 'thank you for your answers. Based on what you described, the website comes to {preis}.',
        ],
        'preisInhalt' => [
            'it' => 'Che cosa comprende:',
            'de' => 'Was darin enthalten ist:',
            'en' => 'What that includes:',
        ],
        'preisBetreuung' => [
            'it' => 'In più c’è l’assistenza mensile, {betrag} al mese. È un contratto a parte e può anche farne a meno: il sito funziona lo stesso.',
            'de' => 'Dazu kommt die monatliche Betreuung, {betrag} im Monat. Das ist ein eigener Vertrag, den Sie auch weglassen können — die Website läuft genauso.',
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
            'it' => 'Il preventivo dettagliato glielo mando subito dopo, voce per voce: basta un clic per accettarlo. Se c’è qualcosa da aggiungere o da togliere, me lo dica e rifaccio il conto.',
            'de' => 'Das Angebot dazu schicke ich Ihnen gleich hinterher — Posten für Posten, mit einem Klick zum Annehmen. Soll etwas dazu oder weg, sagen Sie Bescheid, dann rechne ich es neu.',
            'en' => 'The detailed quote follows right after — line by line, with a single click to accept. If something should be added or removed, tell me and I will redo the figures.',
        ],
    ];

    /* ----------------------------------------------------------------------
       Das Angebot, so wie der Kunde es sieht.

       Ein Angebot ist der Moment, in dem aus einem Gespraech Geld wird. Der
       Ton bleibt trotzdem derselbe: siezen, kurze Saetze, und keine Zeile,
       die man zweimal lesen muss.
       ---------------------------------------------------------------------- */
    public const ANGEBOT = [
        'titel'   => ['it' => 'La sua offerta', 'de' => 'Ihr Angebot', 'en' => 'Your quote'],
        'lead'    => [
            'it' => 'Ecco che cosa costa quello che ci siamo detti. Nessuna sorpresa dopo: quello che legge qui è il prezzo.',
            'de' => 'Das kostet, worüber wir gesprochen haben. Keine Überraschungen danach — was hier steht, ist der Preis.',
            'en' => 'Here is what we discussed, and what it costs. No surprises later — what you read here is the price.',
        ],
        'nummer'  => ['it' => 'Offerta', 'de' => 'Angebot', 'en' => 'Quote'],
        'gueltig' => ['it' => 'Valida fino al {datum}', 'de' => 'Gültig bis {datum}', 'en' => 'Valid until {datum}'],
        'posten'  => ['it' => 'Che cosa è compreso', 'de' => 'Was drin ist', 'en' => 'What is included'],
        'summe'   => ['it' => 'Totale una tantum', 'de' => 'Einmalig gesamt', 'en' => 'One-off total'],
        'monat'   => ['it' => 'Assistenza mensile', 'de' => 'Betreuung monatlich', 'en' => 'Monthly care'],
        'zahlung' => [
            'it' => 'Si paga in due volte: {anzahlung} all’ordine, il resto alla consegna del sito.',
            'de' => 'Bezahlt wird in zwei Schritten: {anzahlung} bei Auftrag, der Rest bei Übergabe der Website.',
            'en' => 'Paid in two steps: {anzahlung} on order, the rest when the site is handed over.',
        ],
        'annehmen'  => ['it' => 'Accetto l’offerta', 'de' => 'Angebot annehmen', 'en' => 'Accept this quote'],
        /* Die Zusage auf die Rueckfrage. "Ja, annehmen" liest man auch dann
           richtig, wenn man die Frage darueber ueberflogen hat -- "OK" nicht. */
        'jaAnnehmen' => ['it' => 'Sì, accetto', 'de' => 'Ja, annehmen', 'en' => 'Yes, accept'],
        'abbrechen'  => ['it' => 'Annulla', 'de' => 'Abbrechen', 'en' => 'Cancel'],
        /* Ueber der Zustimmung. Kein Kleingedrucktes: Wer hier klickt,
           schliesst einen Vertrag, und das darf man ihm auch sagen. */
        'zustKopf' => [
            'it' => 'Prima di accettare',
            'de' => 'Bevor Sie annehmen',
            'en' => 'Before you accept',
        ],
        'fehlerZust' => [
            'it' => 'Servono entrambe le conferme per accettare l’offerta.',
            'de' => 'Beide Bestätigungen sind nötig, um das Angebot anzunehmen.',
            'en' => 'Both confirmations are needed to accept the quote.',
        ],
        'ablehnen'  => ['it' => 'Non fa per me', 'de' => 'Passt so nicht', 'en' => 'Not for me'],
        'grundFrage'=> [
            'it' => 'Che cosa non va? Basta una riga — mi aiuta a capire.',
            'de' => 'Was passt nicht? Eine Zeile genügt — sie hilft mir weiter.',
            'en' => 'What is not right? One line is enough — it helps me.',
        ],
        'pdf' => ['it' => 'Scaricare in PDF', 'de' => 'Als PDF herunterladen', 'en' => 'Download as PDF'],
        'proMonat'  => ['it' => 'al mese', 'de' => 'im Monat', 'en' => 'per month'],
        'pdfAn'     => ['it' => 'A', 'de' => 'An', 'en' => 'To'],
        'pdfDatum'  => ['it' => 'Data', 'de' => 'Datum', 'en' => 'Date'],
        'pdfGueltig'=> ['it' => 'Valida fino al', 'de' => 'Gültig bis', 'en' => 'Valid until'],
        'pdfKunde'  => ['it' => 'N. cliente', 'de' => 'Kundennummer', 'en' => 'Customer no.'],
        'pdfWas'    => ['it' => 'Prestazione', 'de' => 'Leistung', 'en' => 'Item'],
        'pdfBetrag' => ['it' => 'Importo', 'de' => 'Betrag', 'en' => 'Amount'],
        'pdfFest'   => [
            'it' => 'Quello che legge qui è il prezzo. Se durante il lavoro serve altro, glielo dico prima.',
            'de' => 'Was hier steht, ist der Preis. Kommt während der Arbeit etwas dazu, spreche ich es vorher ab.',
            'en' => 'What is written here is the price. If anything comes up during the work, I agree it with you first.',
        ],
        'dankeAn' => [
            'it' => 'Grazie! Le scrivo subito con il link per l’acconto — poi si comincia.',
            'de' => 'Danke! Ich melde mich gleich mit dem Link für die Anzahlung — dann geht es los.',
            'en' => 'Thank you! I will send you the deposit link shortly — then we start.',
        ],
        'dankeAb' => [
            'it' => 'Va bene, grazie per avermelo detto. Se cambia idea, sa dove trovarmi.',
            'de' => 'Alles gut, danke für die Rückmeldung. Wenn Sie es sich anders überlegen, wissen Sie, wo Sie mich finden.',
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
            'it' => 'Questa offerta è scaduta. Mi scriva e gliene faccio una nuova — di solito al prezzo di prima.',
            'de' => 'Dieses Angebot ist abgelaufen. Schreiben Sie mir, dann mache ich ein neues — meist zum alten Preis.',
            'en' => 'This quote has expired. Write to me and I will make a new one — usually at the old price.',
        ],
        /* ---- Gegenvorschlag ------------------------------------------
           Der Kunde stellt sich zusammen, was er will. Die Zahl, die er dabei
           sieht, ist eine Auskunft -- deshalb sagt jeder dieser Saetze, dass
           das verbindliche Angebot danach kommt. */
        'aendernKopf' => [
            'it' => 'Le serve qualcosa in più o in meno?',
            'de' => 'Brauchen Sie mehr oder weniger?',
            'en' => 'Need more, or less?',
        ],
        'aendernLead' => [
            'it' => 'Tolga la spunta a quello che non le serve, cambi il numero di pagine, aggiunga quello che manca. Il totale si aggiorna subito.',
            'de' => 'Entfernen Sie das Häkchen bei allem, was Sie nicht brauchen, ändern Sie die Zahl der Seiten, nehmen Sie dazu, was fehlt. Die Summe rechnet sich sofort mit.',
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
            'it' => 'Indicazione, non un’offerta. Quella vincolante gliela mando io, di solito lo stesso giorno.',
            'de' => 'Auskunft, kein Angebot. Das verbindliche schicke ich Ihnen, meist noch am selben Tag.',
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
            'it' => 'Ricevuto. Le mando l’offerta aggiornata, di solito lo stesso giorno.',
            'de' => 'Angekommen. Ich schicke Ihnen das geänderte Angebot, meist noch am selben Tag.',
            'en' => 'Got it. I’ll send you the updated quote, usually the same day.',
        ],
        'aendernGenug' => [
            'it' => 'Abbiamo già fatto due giri. Se manca ancora qualcosa, mi chiami: in due minuti al telefono si risolve meglio che qui.',
            'de' => 'Wir haben schon zweimal hin und her. Wenn noch etwas fehlt, rufen Sie mich an — zwei Minuten am Telefon klären mehr als eine dritte Runde.',
            'en' => 'We’ve been back and forth twice. If something is still missing, call me — two minutes on the phone beats a third round.',
        ],
        'aendernOffen' => [
            'it' => 'Il suo desiderio è arrivato. Le rispondo con l’offerta aggiornata.',
            'de' => 'Ihr Wunsch ist angekommen. Ich melde mich mit dem geänderten Angebot.',
            'en' => 'Your request has arrived. I’ll come back with the updated quote.',
        ],
        'ersetzt' => [
            'it' => 'Questa offerta è stata sostituita da una nuova, con le modifiche che mi ha chiesto. La trova nell’e-mail più recente. Qui sotto resta la versione precedente, così può confrontarle.',
            'de' => 'Dieses Angebot wurde durch ein neues ersetzt — mit den Änderungen, um die Sie gebeten haben. Es steht in der jüngeren E-Mail. Hier unten bleibt die vorige Fassung stehen, damit Sie vergleichen können.',
            'en' => 'This quote has been replaced by a new one with the changes you asked for. It is in the more recent email. The previous version stays below so you can compare.',
        ],
        'weg' => [
            'it' => 'Questo link non è più valido.',
            'de' => 'Dieser Link gilt nicht mehr.',
            'en' => 'This link is no longer valid.',
        ],
        'panne' => [
            'it' => 'Qualcosa non ha funzionato. Riprovi tra poco.',
            'de' => 'Etwas hat nicht geklappt. Versuchen Sie es gleich noch einmal.',
            'en' => 'Something went wrong. Please try again shortly.',
        ],
    ];

    /* ========================================================================
       WAS DER TELEFONASSISTENT VORLIEST, WENN JEMAND NICHT WEITERKOMMT
       ------------------------------------------------------------------------
       Diese Saetze werden GESPROCHEN, nicht gelesen. Deshalb sind sie kuerzer
       als alles andere hier: kein Nebensatz, keine Klammer, keine Aufzaehlung
       in einem Satz. Wer am Telefon einen Schachtelsatz hoert, steigt aus.

       Und sie stehen hier und nicht im Prompt bei STRATO, weil sie sonst an
       zwei Stellen leben und beim naechsten Mal auseinanderlaufen.

       Eine Regel gilt fuer alle: KEIN BETRAG, und kein Satz, der verraet, ob
       jemand noch etwas offen hat. Am Telefon sitzt kein Ausweis, sondern
       eine Stimme. Was ansteht, steht auf der Kundenseite -- und die geht an
       die hinterlegte Adresse, nicht an eine, die am Telefon genannt wurde.
       ======================================================================== */
    public const TELEFON_HILFE = [

        'kundenseite' => [
            'it' => [
                'Le mando subito il link alla sua pagina, all’indirizzo che abbiamo.',
                'Lì vede a che punto siamo e cosa può fare adesso.',
                'Se non arriva, guardi nello spam.',
            ],
            'de' => [
                'Ich schicke Ihnen gleich den Link zu Ihrer Seite — an die Adresse, die wir haben.',
                'Dort sehen Sie, wo wir stehen und was Sie jetzt tun können.',
                'Wenn nichts ankommt, sehen Sie bitte im Spam nach.',
            ],
            'en' => [
                'I am sending you the link to your page right now, to the address we have.',
                'There you can see where we stand and what you can do next.',
                'If nothing arrives, please check your spam folder.',
            ],
        ],

        'fragebogen_neu' => [
            'it' => [
                'Le rimando subito il questionario, all’indirizzo che abbiamo.',
                'Lo apra con calma: può salvare e continuare più tardi.',
                'Se una domanda non le è chiara, la lasci vuota e lo mandi lo stesso.',
            ],
            'de' => [
                'Ich schicke Ihnen den Fragebogen gleich noch einmal — an die Adresse, die wir haben.',
                'Öffnen Sie ihn in Ruhe. Sie können zwischendurch speichern und später weitermachen.',
                'Wenn eine Frage unklar ist, lassen Sie sie leer und schicken Sie ihn trotzdem ab.',
            ],
            'en' => [
                'I am sending you the questionnaire again, to the address we have.',
                'Open it when you have time. You can save and come back later.',
                'If a question is unclear, leave it empty and send it anyway.',
            ],
        ],

        'fragebogen_zurueck' => [
            'it' => [
                'Il suo questionario è già arrivato.',
                'Non deve fare altro: adesso tocca a noi.',
            ],
            'de' => [
                'Ihr Fragebogen ist schon bei uns.',
                'Sie müssen nichts mehr tun — jetzt sind wir dran.',
            ],
            'en' => [
                'Your questionnaire has already reached us.',
                'Nothing more to do on your side — we are on it now.',
            ],
        ],

        'fragebogen_noch_nicht' => [
            'it' => [
                'Il questionario non è ancora partito.',
                'Glielo manda Uwe: arriva per e-mail.',
            ],
            'de' => [
                'Der Fragebogen ist noch nicht an Sie rausgegangen.',
                'Uwe schickt ihn Ihnen — er kommt per E-Mail.',
            ],
            'en' => [
                'The questionnaire has not gone out yet.',
                'Uwe will send it to you — it comes by email.',
            ],
        ],

        'vorschau_noch_nicht' => [
            'it' => [
                'L’anteprima non è ancora aperta.',
                'Appena è pronta riceve un’e-mail con il link.',
            ],
            'de' => [
                'Der Entwurf ist noch nicht freigegeben.',
                'Sobald er steht, bekommen Sie eine E-Mail mit dem Link.',
            ],
            'en' => [
                'The draft is not open yet.',
                'As soon as it is ready you get an email with the link.',
            ],
        ],

        'unbekannt' => [
            'it' => [
                'Con questo numero non la trovo.',
                'Mi dica il suo problema: lo passo avanti e qualcuno la richiama.',
            ],
            'de' => [
                'Unter dieser Nummer finde ich Sie nicht.',
                'Sagen Sie mir, worum es geht — ich gebe es weiter, und jemand ruft Sie zurück.',
            ],
            'en' => [
                'I cannot find you under this number.',
                'Tell me what it is about — I will pass it on and someone will call you back.',
            ],
        ],

        'gemeldet' => [
            'it' => [
                'Ho preso nota e l’ho passata avanti.',
                'Qualcuno la richiama.',
            ],
            'de' => [
                'Ich habe das aufgenommen und weitergegeben.',
                'Jemand meldet sich bei Ihnen.',
            ],
            'en' => [
                'I have noted this and passed it on.',
                'Someone will get back to you.',
            ],
        ],
    ];

    public static function h(array $karte, string $sprache, string $ersatz = ''): string
    {
        return (string) ($karte[$sprache] ?? $karte['it'] ?? $ersatz);
    }

    /**
     * Die Ueberschriften im Mittelteil der Uebergabe-Mail.
     *
     * Sie stehen hier und nicht in der Vorlage, weil ein Baustein fehlen
     * darf: Wurde keine Spanne genannt, faellt „Groessenordnung" mit weg.
     * Eine Ueberschrift ohne Inhalt sieht aus wie ein Fehler -- und ist einer.
     */
    public const UEBERGABE_TEILE = [
        'besprochen' => ['it' => 'Di che cosa abbiamo parlato',
                         'de' => 'Worüber wir gesprochen haben',
                         'en' => 'What we talked about'],
        'spanne'     => ['it' => 'Ordine di grandezza',
                         'de' => 'Größenordnung',
                         'en' => 'Rough range'],
        'befund'     => ['it' => 'Quello che ho visto sul suo sito',
                         'de' => 'Was mir an Ihrer Seite aufgefallen ist',
                         'en' => 'What I noticed on your site'],
    ];

    public static function mail(string $anlass, string $sprache, array $werte): array
    {
        $satz = self::MAILS[$anlass][$sprache] ?? self::MAILS[$anlass]['it'] ?? ['', ''];
        $suchen  = array_map(static fn($k) => '{' . $k . '}', array_keys($werte));
        $ersetzen = array_values($werte);
        return [str_replace($suchen, $ersetzen, $satz[0]), str_replace($suchen, $ersetzen, $satz[1])];
    }
}
