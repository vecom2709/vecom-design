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
        /* Ein Fragebogen statt zwei (26.09.2026, Uwe: „Die 8 Fragen sollen in
           den großen Fragebogen zusammenlaufen“): Der Knopf heißt, was er ist. */
        'vorhabenKnopf'  => ['it' => 'Iniziare il questionario', 'de' => 'Fragebogen beginnen', 'en' => 'Start the questionnaire'],
        'vorhabenWeiter' => ['it' => 'Continuare il questionario', 'de' => 'Fragebogen weiter ausfüllen', 'en' => 'Continue the questionnaire'],
        'angebotKommt'     => ['it' => 'Preparo il suo preventivo', 'de' => 'Ich schreibe Ihr Angebot', 'en' => 'I’m writing your quote'],
        /* Festpreis vom Partner (PartnerVorab, 02.10.2026) */
        'vorabKommtText'   => ['it' => 'I suoi dati sono arrivati. Entro un giorno lavorativo trova qui il preventivo al prezzo concordato di {preis}, voce per voce.',
                               'de' => 'Ihre Angaben sind da. Innerhalb eines Werktags finden Sie hier Ihr Angebot zum vereinbarten Preis von {preis}, Position für Position.',
                               'en' => 'Your details have arrived. Within one working day you will find your quote here at the agreed price of {preis}, item by item.'],
        'vorabLeistungen'  => ['it' => 'Concordato:', 'de' => 'Vereinbart:', 'en' => 'Agreed:'],
        'angebotKommtText' => ['it' => 'Il suo questionario è arrivato. Entro un giorno lavorativo trova qui il preventivo, voce per voce.',
                               'de' => 'Ihr Fragebogen ist da. Innerhalb eines Werktags finden Sie hier Ihr Angebot, Position für Position.',
                               'en' => 'Your questionnaire has arrived. Within one working day you will find your quote here, item by item.'],
        'richtpreis'     => ['it' => 'Il suo prezzo indicativo: {spanne}', 'de' => 'Ihr Richtpreis: {spanne}', 'en' => 'Your guide price: {spanne}'],
        'richtpreisHilfe'=> ['it' => 'Senza impegno. Il preventivo vincolante arriva appena il questionario è completo.',
                             'de' => 'Unverbindlich. Das verbindliche Angebot kommt, sobald der Fragebogen fertig ist.',
                             'en' => 'No obligation. The binding quote follows as soon as the questionnaire is complete.'],
        'vorhabenAngekommen' => ['it' => 'Grazie, il suo progetto è arrivato. Adesso qualche informazione in più, perché il preventivo sia preciso — quello che ha appena risposto è già compilato. Può fermarsi e riprendere quando vuole.',
                                 'de' => 'Danke, Ihr Vorhaben ist angekommen. Jetzt noch die Angaben, damit das Angebot genau passt — was Sie eben beantwortet haben, steht schon drin. Sie können jederzeit aufhören und weitermachen.',
                                 'en' => 'Thank you, your project has arrived. Now a few more details so the quote fits exactly — what you just answered is already filled in. You can stop and continue any time.'],
        /* Nach den acht Fragen: keine Schrittzahl (B6, 25.09.2026), die
           Restzeit steht darüber als „Noch etwa X Minuten“. */
        'vorlaufTitel' => ['it' => 'Otto domande e prezzo indicativo: fatto', 'de' => 'Acht Fragen und Richtpreis: erledigt', 'en' => 'Eight questions and guide price: done'],
        'angabenTitel' => ['it' => 'Poi: le informazioni per il preventivo', 'de' => 'Danach: die Angaben für Ihr Angebot', 'en' => 'Then: the details for your quote'],
        // Dieselbe Überschrift wie über den acht Fragen: ein Fragebogen
        'titelWeiter'  => ['it' => 'Il suo questionario', 'de' => 'Ihr Fragebogen', 'en' => 'Your questionnaire'],
        'leadWeiter' => ['it' => 'Ora solo l’essenziale, quasi tutto da spuntare. Il resto è facoltativo e si può aggiungere anche dopo.',
                         'de' => 'Jetzt nur noch das Nötigste, fast alles zum Anklicken. Der Rest ist freiwillig und geht auch später.',
                         'en' => 'Now just the essentials, mostly tapping. The rest is optional and can be added later.'],
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
        /* Vor dem Preis ist der Fragebogen das Ende seines Teils (26.09.2026) */
        'dankeVorPreis' => ['it' => 'Grazie, il questionario è completo! Entro un giorno lavorativo trova il suo preventivo sulla sua pagina.',
                            'de' => 'Danke, Ihr Fragebogen ist komplett! Innerhalb eines Werktags finden Sie Ihr Angebot auf Ihrer Seite.',
                            'en' => 'Thank you, your questionnaire is complete! Within one working day you will find your quote on your page.'],
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
        /* Empfehlen und sparen (Marketing-Studio 8, 01.10.2026, Uwe: „ja“ zu S3) — der eigene Link steht da, wo der Kunde zufrieden ist. */
        'empfTitel'  => ['it' => 'Consigli Vecom e risparmi', 'de' => 'Weiterempfehlen und sparen', 'en' => 'Recommend us and save'],
        'empfText'   => [
            'it' => 'Conosce qualcuno che ha bisogno di un sito? Gli mandi il suo link personale. Se da lì nasce un incarico, Lei paga il {rabatt} % in meno sull’assistenza per {monate} mesi — ogni nuovo incarico allunga lo sconto.',
            'de' => 'Kennen Sie jemanden, der eine Website braucht? Schicken Sie ihm Ihren persönlichen Link. Wird daraus ein Auftrag, zahlen Sie {monate} Monate lang {rabatt} % weniger für die Betreuung — jeder weitere Auftrag verlängert den Rabatt.',
            'en' => 'Know someone who needs a website? Send them your personal link. If it turns into an order, you pay {rabatt} % less for your care plan for {monate} months — every further order extends the discount.'],
        'empfLink'   => ['it' => 'Il suo link personale', 'de' => 'Ihr persönlicher Link', 'en' => 'Your personal link'],
        'empfKopieren' => ['it' => 'Copia link', 'de' => 'Link kopieren', 'en' => 'Copy link'],
        'empfKopiert'  => ['it' => 'Copiato', 'de' => 'Kopiert', 'en' => 'Copied'],
        'empfWhatsapp' => ['it' => 'Inviare su WhatsApp', 'de' => 'Per WhatsApp schicken', 'en' => 'Send on WhatsApp'],
        'empfNachricht' => [
            'it' => 'Ciao! Il mio nuovo sito l’ha fatto Vecom Design: prezzo fisso, il dominio resta tuo, e mi sono trovato bene. Se ti serve un sito, guarda qui: {link}',
            'de' => 'Guten Tag! Meine neue Website hat Vecom Design gemacht: Festpreis, die Domain gehört dem Kunden, und ich war zufrieden. Falls Sie eine Website brauchen, schauen Sie hier: {link}',
            'en' => 'Hi! My new website was made by Vecom Design: fixed price, the domain stays yours, and I was happy with it. If you need a website, have a look: {link}'],
        'empfStand'  => ['it' => 'Incarichi nati dai suoi consigli: {n}', 'de' => 'Aufträge aus Ihren Empfehlungen: {n}', 'en' => 'Orders from your recommendations: {n}'],
        'empfRabatt' => ['it' => 'Il suo sconto vale fino al {datum}.', 'de' => 'Ihr Rabatt gilt bis {datum}.', 'en' => 'Your discount is valid until {datum}.'],
        'empfQr'     => ['it' => 'Oppure lo faccia inquadrare dal telefono:', 'de' => 'Oder mit dem Handy abfotografieren lassen:', 'en' => 'Or let them scan it with their phone:'],
        /* Vorher/Nachher (Marketing-Studio 9, 01.10.2026): nur mit ausdrücklicher Zustimmung, jederzeit zurücknehmbar. */
        'refTitel'   => ['it' => 'Possiamo mostrare il suo nuovo sito?', 'de' => 'Dürfen wir Ihre neue Website zeigen?', 'en' => 'May we show your new website?'],
        'refText'    => ['it' => 'Un “prima e dopo” del suo sito, con il nome della sua attività, aiuta altri imprenditori a capire cosa cambia. È gratuito per Lei ed è anche un po’ di pubblicità per la sua attività.',
                         'de' => 'Ein „Vorher – nachher“ Ihrer Website mit dem Namen Ihres Betriebs zeigt anderen Betrieben, was sich ändert. Für Sie kostenlos — und ein wenig Werbung für Ihren Betrieb.',
                         'en' => 'A “before and after” of your website, with the name of your business, shows other businesses what changes. Free for you — and a little advertising for your business.'],
        'refJa'      => ['it' => 'Sì, potete mostrarlo', 'de' => 'Ja, Sie dürfen sie zeigen', 'en' => 'Yes, you may show it'],
        'refIstJa'   => ['it' => 'Ha dato il consenso il {datum}. Grazie!', 'de' => 'Sie haben am {datum} zugestimmt. Danke!', 'en' => 'You agreed on {datum}. Thank you!'],
        'refZurueck' => ['it' => 'Ritirare il consenso', 'de' => 'Zustimmung zurückziehen', 'en' => 'Withdraw consent'],
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
        'stimmeGoogle'  => ['it' => 'Le andrebbe di scriverlo anche su Google? Per un piccolo studio conta moltissimo.',
                            'de' => 'Möchten Sie das auch auf Google schreiben? Für ein kleines Studio zählt das sehr.',
                            'en' => 'Would you also write it on Google? For a small studio it means a lot.'],
        'stimmeGoogleKnopf' => ['it' => 'Valutare su Google', 'de' => 'Auf Google bewerten', 'en' => 'Review on Google'],
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
            'kurz' => ['it' => 'Questionario', 'de' => 'Fragebogen', 'en' => 'Questionnaire'],
            'it' => 'Il suo questionario', 'de' => 'Ihr Fragebogen',
            'en' => 'Your questionnaire',
            'text' => ['it' => 'Comincia con otto domande brevi sul suo progetto: subito dopo vede il suo prezzo indicativo, senza impegno. Poi qualche informazione per il preventivo — può fermarsi e riprendere quando vuole.',
                       'de' => 'Er beginnt mit acht kurzen Fragen zu Ihrem Vorhaben — gleich danach sehen Sie Ihren Richtpreis, unverbindlich. Dann die Angaben für Ihr Angebot; Sie können jederzeit aufhören und weitermachen.',
                       'en' => 'It starts with eight short questions about your project — right after, you see your guide price, no obligation. Then the details for your quote; you can stop and continue any time.']],
        'anfrage'  => ['wer' => 'wir',
            'kurz' => ['it' => 'Questionario', 'de' => 'Fragebogen', 'en' => 'Questionnaire'],
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
            'kurz' => ['it' => 'Questionario', 'de' => 'Fragebogen', 'en' => 'Questionnaire'],
            'it' => 'Il suo questionario: avanti da dove si era fermato', 'de' => 'Ihr Fragebogen — weiter, wo Sie aufgehört haben',
            'en' => 'Your questionnaire — carry on where you left off',
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
        'bewertung_bitte' => [
            'it' => ['Una piccola richiesta', "Buongiorno {name},\n\nil suo sito è online da un po’ — spero che le porti clienti.\n\nSe è soddisfatto del lavoro, mi aiuterebbe molto una sua recensione su Google. Bastano due righe:\n{link}\n\nGrazie di cuore,\nUwe"],
            'de' => ['Eine kleine Bitte', "Guten Tag {name},\n\nIhre Website ist jetzt eine Weile online — ich hoffe, sie bringt Ihnen Kunden.\n\nWenn Sie zufrieden sind, hilft mir eine Bewertung auf Google sehr. Zwei Sätze genügen:\n{link}\n\nHerzlichen Dank,\nUwe"],
            'en' => ['A small request', "Hello {name},\n\nyour website has been online for a while now — I hope it brings you customers.\n\nIf you are happy with the work, a Google review would help me a lot. Two sentences are enough:\n{link}\n\nThank you very much,\nUwe"],
        ],
        'partner_neukunde' => [
            'it' => ['Un nuovo contatto tramite il suo link', "Buongiorno {name},\n\nqualcuno è arrivato tramite il suo link e ci ha contattati. Da ora questa persona è assegnata a lei: se acquista, riceve la sua provvigione.\n\n(Per rispetto non le diciamo chi è.)\n\nLa sua pagina: {portal}"],
            'de' => ['Ein neuer Kontakt über Ihren Link', "Guten Tag {name},\n\njemand ist über Ihren Link gekommen und hat uns kontaktiert. Diese Person ist ab jetzt Ihnen zugeordnet: Kauft sie, bekommen Sie Ihre Provision.\n\n(Aus Rücksicht sagen wir nicht, wer es ist.)\n\nIhre Seite: {portal}"],
            'en' => ['A new contact through your link', "Hello {name},\n\nsomeone came through your link and contacted us. From now on they are assigned to you: if they buy, you earn your commission.\n\n(Out of respect, we don’t say who it is.)\n\nYour page: {portal}"],
        ],
        'partner_verdient' => [
            'it' => ['Ha guadagnato {betrag}', "Buongiorno {name},\n\nun cliente arrivato da lei ha pagato: ha guadagnato {betrag} di provvigione. Sarà pagabile dal {datum} (fino ad allora il cliente può recedere).\n\nGrazie! La sua pagina: {portal}"],
            'de' => ['Sie haben {betrag} verdient', "Guten Tag {name},\n\nein Kunde, der über Sie kam, hat bezahlt: Sie haben {betrag} Provision verdient. Auszahlbar ab {datum} (bis dahin kann der Kunde widerrufen).\n\nDanke! Ihre Seite: {portal}"],
            'en' => ['You earned {betrag}', "Hello {name},\n\na customer who came through you has paid: you earned {betrag} in commission. Payable from {datum} (until then the customer can still withdraw).\n\nThank you! Your page: {portal}"],
        ],
        /* Partner-Nachrichten und Erinnerung an den Auszahlungsweg (26.09.2026) */
        'partner_antwort' => [
            'it' => ['Nuovo messaggio da Vecom Design', "Buongiorno {name},\n\nle abbiamo risposto:\n\n{text}\n\nPuò rispondere direttamente dalla sua pagina: {portal}#nachrichten"],
            'de' => ['Neue Nachricht von Vecom Design', "Guten Tag {name},\n\nwir haben Ihnen geantwortet:\n\n{text}\n\nAntworten können Sie direkt auf Ihrer Seite: {portal}#nachrichten"],
            'en' => ['New message from Vecom Design', "Hello {name},\n\nwe have replied:\n\n{text}\n\nYou can reply straight from your page: {portal}#nachrichten"],
        ],
        'partner_weg_fehlt' => [
            'it' => ['{betrag} sono pronti per lei', "Buongiorno {name},\n\nsul suo conto partner ci sono {betrag} pronti per il pagamento, ma manca ancora il modo in cui vuole riceverli.\n\nBasta un minuto: apra la sua pagina e scelga il metodo (sezione «Come ricevere i pagamenti»):\n{portal}#wege\n\nIl denaro resta al sicuro finché non l’ha indicato — non scade."],
            'de' => ['{betrag} liegen für Sie bereit', "Guten Tag {name},\n\nauf Ihrem Partnerkonto liegen {betrag} zur Auszahlung bereit — es fehlt nur noch, wie Sie das Geld bekommen möchten.\n\nDas dauert eine Minute: Seite öffnen und den Weg wählen (Abschnitt „Wie Sie Ihr Geld bekommen“):\n{portal}#wege\n\nDas Geld bleibt so lange sicher liegen — es verfällt nicht."],
            'en' => ['{betrag} is ready for you', "Hello {name},\n\nthere is {betrag} ready to be paid out on your partner account — we just need to know how you want to receive it.\n\nIt takes a minute: open your page and choose a method (section “How you get paid”):\n{portal}#wege\n\nThe money stays safe until then — it does not expire."],
        ],
        'partner_ruhend' => [
            'it' => ['Tre idee per il suo link', "Buongiorno {name},\n\nil suo link non è stato aperto da un po’. Tre idee che funzionano:\n\n1. Lo mandi su WhatsApp a chi le ha parlato di un sito (c’è il pulsante pronto).\n2. Stampi la cartolina con il codice QR e la lasci sul bancone o in vetrina.\n3. Usi un testo pronto per Instagram o Facebook — li trova nella sua pagina.\n\n{portal}"],
            'de' => ['Drei Ideen für Ihren Link', "Guten Tag {name},\n\nIhr Link wurde eine Weile nicht geöffnet. Drei Ideen, die funktionieren:\n\n1. Per WhatsApp an jemanden schicken, der von einer Website gesprochen hat (der Knopf ist fertig).\n2. Die Karte mit QR-Code ausdrucken und auf den Tresen oder ins Schaufenster legen.\n3. Einen fertigen Text für Instagram oder Facebook nehmen — sie stehen auf Ihrer Seite.\n\n{portal}"],
            'en' => ['Three ideas for your link', "Hello {name},\n\nyour link hasn’t been opened for a while. Three ideas that work:\n\n1. Send it on WhatsApp to someone who mentioned needing a website (the button is ready).\n2. Print the card with the QR code and leave it on the counter or in the window.\n3. Use a ready-made text for Instagram or Facebook — you’ll find them on your page.\n\n{portal}"],
        ],
        'partner_jahr' => [
            'it' => ['Il riepilogo {jahr} per la dichiarazione', "Buongiorno {name},\n\nil riepilogo di tutte le provvigioni pagate nel {jahr} è pronto come PDF nella sua pagina partner — utile per la dichiarazione dei redditi:\n{portal}"],
            'de' => ['Ihre Jahresübersicht {jahr} für die Steuer', "Guten Tag {name},\n\ndie Übersicht aller {jahr} ausgezahlten Provisionen liegt als PDF auf Ihrer Partnerseite — für Ihre Steuererklärung:\n{portal}"],
            'en' => ['Your {jahr} summary for your tax return', "Hello {name},\n\nthe summary of all commissions paid in {jahr} is ready as a PDF on your partner page — for your tax return:\n{portal}"],
        ],
        /* Partnerprogramm (26.09.2026) */
        'partner_willkommen' => [
            'it' => ['Benvenuto nel programma partner di Vecom Design',
                "Buongiorno {name},\n\nla sua candidatura è stata accettata. Da oggi ogni cliente che arriva tramite il suo link e acquista le porta una provvigione.\n\nIl suo link: {link}\nIl suo codice (per chi non clicca): {code}\n\nNella sua pagina partner vede clic, clienti, vendite e provvigioni — e lì imposta una volta il conto per i pagamenti (tramite Stripe, noi non vediamo i suoi dati bancari):\n{portal}\n\nLa pagina è personale: non la inoltri."],
            'de' => ['Willkommen im Partnerprogramm von Vecom Design',
                "Guten Tag {name},\n\nIhre Bewerbung ist angenommen. Ab heute bringt Ihnen jeder Kunde, der über Ihren Link kommt und kauft, eine Provision.\n\nIhr Link: {link}\nIhr Code (für alle, die nicht klicken): {code}\n\nAuf Ihrer Partnerseite sehen Sie Klicks, Kunden, Verkäufe und Provisionen — und richten dort einmal Ihr Auszahlungskonto ein (über Stripe, wir sehen Ihre Bankdaten nicht):\n{portal}\n\nDie Seite ist persönlich: Bitte nicht weitergeben."],
            'en' => ['Welcome to the Vecom Design partner programme',
                "Hello {name},\n\nyour application has been accepted. From today, every customer who arrives through your link and buys earns you a commission.\n\nYour link: {link}\nYour code (for people who don’t click): {code}\n\nOn your partner page you see clicks, customers, sales and commissions — and you set up your payout account there once (via Stripe; we never see your bank details):\n{portal}\n\nThe page is personal: please don’t forward it."],
        ],
        /* Neue Vereinbarung und Freischaltung (30.09.2026, PartnerSchutz) */
        'partner_neufassung' => [
            'it' => ['Nuovo accordo partner: la sua conferma',
                "Buongiorno {name},\n\nabbiamo aggiornato l’accordo partner di Vecom Design. Tutela i dati delle aziende, i materiali e il marchio. Fino alla sua conferma l’area partner resta bloccata; il suo link continua a funzionare e i nuovi clienti le vengono assegnati.\n\nLegga e confermi qui (due spunte, un minuto):\n{portal}\n\nDopo la conferma attiviamo la sua area e la avvisiamo."],
            'de' => ['Neue Partnervereinbarung: Ihre Zustimmung',
                "Guten Tag {name},\n\nwir haben die Partnervereinbarung von Vecom Design erneuert. Sie schützt die Betriebsdaten, die Unterlagen und die Marke. Bis zu Ihrer Zustimmung bleibt der Partnerbereich gesperrt; Ihr Link zählt weiter, neue Kunden werden Ihnen zugeordnet.\n\nHier lesen und bestätigen (zwei Haken, eine Minute):\n{portal}\n\nNach Ihrer Zustimmung schalten wir Ihren Bereich frei und geben Ihnen Bescheid."],
            'en' => ['New partner agreement: your confirmation',
                "Hello {name},\n\nwe have updated the Vecom Design partner agreement. It protects the business data, the materials and the brand. Until you confirm, the partner area stays locked; your link keeps working and new customers are still assigned to you.\n\nRead and confirm here (two ticks, one minute):\n{portal}\n\nOnce you confirm, we will activate your area and let you know."],
        ],
        'partner_freigeschaltet' => [
            'it' => ['La sua area partner è attiva',
                "Buongiorno {name},\n\nla sua area partner di Vecom Design è attiva. Il testo dell’accordo che ha accettato si trova nella sua area.\n\n{portal}"],
            'de' => ['Ihr Partnerbereich ist freigeschaltet',
                "Guten Tag {name},\n\nIhr Partnerbereich bei Vecom Design ist freigeschaltet. Den Wortlaut der Vereinbarung, der Sie zugestimmt haben, finden Sie in Ihrem Bereich.\n\n{portal}"],
            'en' => ['Your partner area is active',
                "Hello {name},\n\nyour Vecom Design partner area is now active. The text of the agreement you accepted is in your area.\n\n{portal}"],
        ],
        'partner_auszahlung' => [
            'it' => ['Provvigione pagata: {betrag}',
                "Buongiorno {name},\n\nabbiamo appena pagato {betrag} di provvigioni. Il dettaglio e il documento sono nella sua pagina partner:\n{portal}\n\nGrazie per le sue raccomandazioni."],
            'de' => ['Provision ausgezahlt: {betrag}',
                "Guten Tag {name},\n\nwir haben soeben {betrag} Provision ausgezahlt. Die Aufstellung und den Beleg finden Sie auf Ihrer Partnerseite:\n{portal}\n\nDanke für Ihre Empfehlungen."],
            'en' => ['Commission paid: {betrag}',
                "Hello {name},\n\nwe have just paid out {betrag} in commission. The breakdown and the statement are on your partner page:\n{portal}\n\nThank you for your recommendations."],
        ],
        'partner_bericht' => [
            'it' => ['Il suo mese da partner: {monat}',
                "Buongiorno {name},\n\necco i suoi numeri di {monat}:\nClic sul suo link: {klicks}\nNuovi clienti: {kunden}\nVendite: {verkaeufe}\nProvvigioni: {provision}\n\nIn attesa di pagamento in totale: {offen}\n\nTutti i dettagli: {portal}"],
            'de' => ['Ihr Partnermonat: {monat}',
                "Guten Tag {name},\n\nIhre Zahlen für {monat}:\nKlicks auf Ihren Link: {klicks}\nNeue Kunden: {kunden}\nVerkäufe: {verkaeufe}\nProvision: {provision}\n\nNoch nicht ausgezahlt, insgesamt: {offen}\n\nAlle Einzelheiten: {portal}"],
            'en' => ['Your partner month: {monat}',
                "Hello {name},\n\nhere are your numbers for {monat}:\nClicks on your link: {klicks}\nNew customers: {kunden}\nSales: {verkaeufe}\nCommission: {provision}\n\nNot yet paid out, in total: {offen}\n\nAll the details: {portal}"],
        ],
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
                "Buongiorno{name},\n\necco la sua dashboard personale di Vecom Design:\n\n{link}\n\nDa lì passa tutto, fino alla consegna del sito. I prossimi passi:\n\n1. Il suo questionario: comincia con otto domande brevi e subito dopo vede un prezzo indicativo; poi qualche informazione sulla sua attività, perché il preventivo sia preciso.\n2. Preventivo e acconto: legge il preventivo con calma e decide lei.\n3. Anteprima e approvazione: vede il suo sito prima che vada online.\n\nNessun account, nessuna password. Apra il link entro {tage} giorni; dopo il primo clic resta valido e può salvarlo tra i preferiti.\n\nNon ha richiesto lei questa e-mail? Allora la ignori: senza un clic sul link non viene salvato nulla.\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Ihr persönlicher Zugang – Vecom Design',
                "Guten Tag{name},\n\nhier ist Ihr persönliches Dashboard bei Vecom Design:\n\n{link}\n\nDarüber läuft alles bis zur Übergabe Ihrer Website. Die nächsten Schritte:\n\n1. Ihr Fragebogen: Er beginnt mit acht kurzen Fragen, danach sehen Sie sofort einen Richtpreis; dann ein paar Angaben zu Ihrem Betrieb, damit das Angebot genau passt.\n2. Angebot und Anzahlung: Sie lesen das Angebot in Ruhe und entscheiden.\n3. Entwurf und Freigabe: Sie sehen Ihre Seite, bevor sie online geht.\n\nKein Konto, kein Passwort. Öffnen Sie den Link innerhalb von {tage} Tagen; nach dem ersten Klick bleibt er gültig, und Sie können ihn als Lesezeichen ablegen.\n\nSie haben diese Mail nicht angefordert? Dann ignorieren Sie sie einfach: Ohne einen Klick auf den Link wird nichts gespeichert.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your personal access – Vecom Design',
                "Hello{name},\n\nhere is your personal dashboard at Vecom Design:\n\n{link}\n\nEverything runs through it until your website is handed over. The next steps:\n\n1. Your questionnaire: it starts with eight short questions and you see a guide price straight away; then a few details about your business, so the quote fits exactly.\n2. Quote and deposit: you read the quote in your own time and decide.\n3. Draft and approval: you see your site before it goes live.\n\nNo account, no password. Open the link within {tage} days; after the first click it stays valid and you can bookmark it.\n\nDidn’t request this email? Then just ignore it: nothing is stored unless the link is clicked.\n\nBest regards\nUwe Vetter · Vecom Design"],
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
        /* Nach den acht Fragen, wenn der Fragebogen weitergeht (26.09.2026).
           Verschickt unter demselben Typ 'anfrage_eingegangen' -- die Führung
           liest daran ab, dass der Kunde seinen Link hat. Der alte Text
           versprach „innerhalb eines Werktags eine erste Einschätzung“; das
           Angebot ist aber bis zum fertigen Fragebogen gesperrt. */
        'anfrage_eingegangen_fb' => [
            'it' => ['Il suo progetto è arrivato',
                "Buongiorno {name},\n\ngrazie — il suo progetto è arrivato.\n\nManca solo il resto del questionario: qualche informazione sulla sua attività e sul sito, perché il preventivo sia preciso. Può fermarsi quando vuole e continuare con lo stesso link:\n\n{link}\n\nAppena il questionario è completo, riceve il preventivo entro un giorno lavorativo, direttamente su questa pagina. È gratuito e senza impegno: un incarico nasce soltanto quando ci accordiamo per iscritto.\n\nLì può anche scrivermi e caricare i suoi documenti (fino a {maxdatei} per file). Nessun account, nessuna password.\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Ihr Vorhaben ist angekommen',
                "Guten Tag {name},\n\nvielen Dank — Ihr Vorhaben ist angekommen.\n\nJetzt fehlt nur noch der Rest des Fragebogens: ein paar Angaben zu Ihrem Betrieb und zur Seite, damit das Angebot genau passt. Sie können jederzeit aufhören und mit demselben Link weitermachen:\n\n{link}\n\nSobald der Fragebogen fertig ist, bekommen Sie Ihr Angebot innerhalb eines Werktags, direkt auf dieser Seite. Kostenlos und unverbindlich: Ein Auftrag entsteht erst, wenn wir uns schriftlich einig sind.\n\nDort können Sie mir auch schreiben und Unterlagen hochladen (bis {maxdatei} je Datei). Kein Konto, kein Passwort.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your project has arrived',
                "Hello {name},\n\nthank you — your project has arrived.\n\nAll that is left is the rest of the questionnaire: a few details about your business and the site, so the quote fits exactly. You can stop any time and continue with the same link:\n\n{link}\n\nAs soon as the questionnaire is complete, you receive your quote within one working day, right on this page. Free and without obligation: a project only comes about once we agree in writing.\n\nThere you can also write to me and upload your material (up to {maxdatei} per file). No account, no password.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        /* Der Fragebogen ist vor dem Preis fertig: Der Kunde erfährt, was jetzt
           passiert und bis wann (26.09.2026). Nur ohne Projekt -- danach ist
           das Angebot längst angenommen. */
        'fragebogen_danke' => [
            'it' => ['Il suo questionario è completo',
                "Buongiorno {name},\n\ngrazie — il suo questionario è completo e l’ho ricevuto.\n\nLo leggo con calma e entro un giorno lavorativo trova il suo preventivo, voce per voce, sulla sua pagina:\n\n{link}\n\nSe nel frattempo le viene in mente qualcosa, me lo scriva lì.\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Ihr Fragebogen ist komplett',
                "Guten Tag {name},\n\nvielen Dank — Ihr Fragebogen ist komplett, und er ist bei mir angekommen.\n\nIch lese ihn in Ruhe, und innerhalb eines Werktags finden Sie Ihr Angebot, Position für Position, auf Ihrer Seite:\n\n{link}\n\nFällt Ihnen bis dahin noch etwas ein, schreiben Sie es mir einfach dort.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your questionnaire is complete',
                "Hello {name},\n\nthank you — your questionnaire is complete and has reached me.\n\nI’ll read it properly, and within one working day you will find your quote, item by item, on your page:\n\n{link}\n\nIf anything else comes to mind in the meantime, just write to me there.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        /* Vom Partner vorgestellt (26.09.2026): Der Kunde hat nicht selbst
           angefragt -- ein „vielen Dank für Ihre Anfrage“ wäre gelogen. Also
           sagt die Mail, wer uns den Kontakt gegeben hat und warum. */
        'partner_vorstellung' => [
            'it' => ['Un saluto da parte di {partner}',
                "Buongiorno {name},\n\n{partner} mi ha detto che sta pensando a un sito nuovo e mi ha chiesto di contattarla. Molto volentieri!\n\nIn breve: lei dice cosa le serve e conosce il prezzo prima di iniziare. Poi vede ogni passo sulla sua pagina personale:\n\n{link}\n\nLì può anche scrivermi subito cosa ha in mente. Entro un giorno lavorativo le rispondo con una prima indicazione. Gratis e senza impegno.\n\nSe ora non è il momento, basta una breve risposta e non le scrivo più.\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Eine Empfehlung von {partner}',
                "Guten Tag {name},\n\n{partner} hat mir erzählt, dass Sie über eine neue Website nachdenken, und mich gebeten, mich bei Ihnen zu melden. Sehr gern!\n\nKurz zu uns: Sie sagen, was Sie brauchen, und kennen den Preis, bevor es losgeht. Danach sehen Sie jeden Schritt auf Ihrer eigenen Seite:\n\n{link}\n\nDort können Sie mir auch gleich schreiben, was Ihnen vorschwebt. Innerhalb eines Werktags melde ich mich mit einer ersten Einschätzung. Kostenlos und unverbindlich.\n\nPasst es gerade nicht, genügt eine kurze Antwort — dann schreibe ich nicht wieder.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['A recommendation from {partner}',
                "Hello {name},\n\n{partner} told me you’re thinking about a new website and asked me to get in touch. Gladly!\n\nIn short: you say what you need and know the price before anything starts. Then you follow every step on your own page:\n\n{link}\n\nYou can also write to me there straight away about what you have in mind. Within one working day I’ll reply with a first assessment. Free and without obligation.\n\nIf now isn’t a good time, a short reply is enough and I won’t write again.\n\nBest regards\nUwe Vetter · Vecom Design"],
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
        'sprache' => ['it' => 'Lingua della dashboard:', 'de' => 'Sprache des Dashboards:', 'en' => 'Dashboard language:'],
        'knopf' => ['it' => 'Inviarmi la dashboard', 'de' => 'Mein Dashboard zusenden', 'en' => 'Send me my dashboard'],
        'schritte' => [
            'it' => 'Questionario · Preventivo · Anteprima · Online',
            'de' => 'Fragebogen · Angebot · Entwurf · Online',
            'en' => 'Questionnaire · Quote · Draft · Live'],
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
        // Mini-App im Telegram-Kanal (30.09.2026): nach dem Absenden zurück in den Kanal
        'tgZurueck' => ['it' => 'Torna a Telegram', 'de' => 'Zurück zu Telegram', 'en' => 'Back to Telegram'],
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
        /* Live-Richtpreis (28.09.2026, R1–R3) */
        'liveTitel' => ['it' => 'Il suo prezzo indicativo finora', 'de' => 'Ihr Richtpreis bis jetzt', 'en' => 'Your guide price so far'],
        'liveAb'    => ['it' => 'da {betrag}', 'de' => 'ab {betrag}', 'en' => 'from {betrag}'],
        'liveStart' => ['it' => 'Un sito parte da qui. Ogni risposta aggiorna il prezzo subito.', 'de' => 'Hier beginnt eine Website. Jede Antwort rechnet den Preis sofort neu.', 'en' => 'This is where a website starts. Every answer updates the price right away.'],
        'liveMonat' => ['it' => '+ {betrag} al mese per l’assistenza (contratto a parte)', 'de' => '+ {betrag} im Monat für die Betreuung (eigener Vertrag)', 'en' => '+ {betrag} a month for maintenance (separate contract)'],
        'livePlus'  => ['it' => '{was}: + {betrag}', 'de' => '{was}: + {betrag}', 'en' => '{was}: + {betrag}'],
        'liveMinus' => ['it' => '{was}: − {betrag}', 'de' => '{was}: − {betrag}', 'en' => '{was}: − {betrag}'],
        'liveGleich'=> ['it' => '{was}: il prezzo non cambia', 'de' => '{was}: ändert den Preis nicht', 'en' => '{was}: price unchanged'],
        'liveOffen' => ['it' => 'Niente spuntato — me ne occupo io: + {betrag}', 'de' => 'Noch nichts angekreuzt — das übernehme ich: + {betrag}', 'en' => 'Nothing ticked yet — I will take care of it: + {betrag}'],
        'liveMonatPlus' => ['it' => '{was}: + {betrag} al mese', 'de' => '{was}: + {betrag} im Monat', 'en' => '{was}: + {betrag} a month'],
        'liveMonatWeg'  => ['it' => '{was}: niente costo mensile', 'de' => '{was}: keine monatlichen Kosten', 'en' => '{was}: no monthly cost'],
        'liveHinweis' => ['it' => 'Stima, non preventivo — il prezzo definitivo arriva con il preventivo.', 'de' => 'Schätzung, kein Angebot — der feste Preis kommt mit dem Angebot.', 'en' => 'An estimate, not a quote — the fixed price comes with the quote.'],

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
        'absendenDashboard' => ['it' => 'Avanti con il questionario', 'de' => 'Weiter im Fragebogen', 'en' => 'Continue the questionnaire'],
        /* Im Dashboard ist das der Anfang des einen Fragebogens (26.09.2026) */
        'titelEins' => ['it' => 'Il suo questionario', 'de' => 'Ihr Fragebogen', 'en' => 'Your questionnaire'],
        'leadEins'  => [
            'it' => 'Prima otto domande brevi sul suo progetto: subito dopo vede il suo prezzo indicativo. Poi le informazioni per il preventivo.',
            'de' => 'Zuerst acht kurze Fragen zu Ihrem Vorhaben — gleich danach sehen Sie Ihren Richtpreis. Dann folgen die Angaben für Ihr Angebot.',
            'en' => 'First eight short questions about your project — right after, you see your guide price. Then the details for your quote.',
        ],
        'ergebnisTextEins' => [
            'it' => 'Questa è una stima, non un preventivo. Il preventivo vincolante, voce per voce, arriva appena ha completato il questionario — e vale quello.',
            'de' => 'Das ist eine Schätzung, kein Angebot. Das verbindliche Angebot, Position für Position, kommt, sobald Sie den Fragebogen fertig haben — und das gilt dann.',
            'en' => 'This is an estimate, not a quote. The binding quote, item by item, follows once you have completed the questionnaire — and that one holds.',
        ],
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

    /* ======================================================================
       Partnerprogramm (26.09.2026) — Bewerbung, Partnerseite, Vereinbarung.
       Platzhalter: {satz} {min} {tage} {zuordnung} {monate}
       ====================================================================== */
    /**
     * Länder für das Stripe-Auszahlungskonto (Partner::STRIPE_LAENDER), dreisprachig.
     * Auch für die Admin-Anzeige. Adjektiv 'adj' (deutsch, sächlich) für den
     * Hinweis „als deutsches Konto erstellt“.
     */
    public const STRIPE_LAENDER = [
        'IT' => ['it' => 'Italia', 'de' => 'Italien', 'en' => 'Italy', 'adj' => 'italienisches'],
        'DE' => ['it' => 'Germania', 'de' => 'Deutschland', 'en' => 'Germany', 'adj' => 'deutsches'],
        'AT' => ['it' => 'Austria', 'de' => 'Österreich', 'en' => 'Austria', 'adj' => 'österreichisches'],
        'CH' => ['it' => 'Svizzera', 'de' => 'Schweiz', 'en' => 'Switzerland', 'adj' => 'schweizerisches'],
        'FR' => ['it' => 'Francia', 'de' => 'Frankreich', 'en' => 'France', 'adj' => 'französisches'],
        'ES' => ['it' => 'Spagna', 'de' => 'Spanien', 'en' => 'Spain', 'adj' => 'spanisches'],
        'NL' => ['it' => 'Paesi Bassi', 'de' => 'Niederlande', 'en' => 'Netherlands', 'adj' => 'niederländisches'],
        'BE' => ['it' => 'Belgio', 'de' => 'Belgien', 'en' => 'Belgium', 'adj' => 'belgisches'],
        'LU' => ['it' => 'Lussemburgo', 'de' => 'Luxemburg', 'en' => 'Luxembourg', 'adj' => 'luxemburgisches'],
        'PT' => ['it' => 'Portogallo', 'de' => 'Portugal', 'en' => 'Portugal', 'adj' => 'portugiesisches'],
        'IE' => ['it' => 'Irlanda', 'de' => 'Irland', 'en' => 'Ireland', 'adj' => 'irisches'],
        'PL' => ['it' => 'Polonia', 'de' => 'Polen', 'en' => 'Poland', 'adj' => 'polnisches'],
        'GB' => ['it' => 'Regno Unito', 'de' => 'Vereinigtes Königreich', 'en' => 'United Kingdom', 'adj' => 'britisches'],
        'BG' => ['it' => 'Bulgaria', 'de' => 'Bulgarien', 'en' => 'Bulgaria', 'adj' => 'bulgarisches'],
        'HR' => ['it' => 'Croazia', 'de' => 'Kroatien', 'en' => 'Croatia', 'adj' => 'kroatisches'],
        'CY' => ['it' => 'Cipro', 'de' => 'Zypern', 'en' => 'Cyprus', 'adj' => 'zyprisches'],
        'CZ' => ['it' => 'Repubblica Ceca', 'de' => 'Tschechien', 'en' => 'Czechia', 'adj' => 'tschechisches'],
        'DK' => ['it' => 'Danimarca', 'de' => 'Dänemark', 'en' => 'Denmark', 'adj' => 'dänisches'],
        'EE' => ['it' => 'Estonia', 'de' => 'Estland', 'en' => 'Estonia', 'adj' => 'estnisches'],
        'FI' => ['it' => 'Finlandia', 'de' => 'Finnland', 'en' => 'Finland', 'adj' => 'finnisches'],
        'GR' => ['it' => 'Grecia', 'de' => 'Griechenland', 'en' => 'Greece', 'adj' => 'griechisches'],
        'HU' => ['it' => 'Ungheria', 'de' => 'Ungarn', 'en' => 'Hungary', 'adj' => 'ungarisches'],
        'LV' => ['it' => 'Lettonia', 'de' => 'Lettland', 'en' => 'Latvia', 'adj' => 'lettisches'],
        'LI' => ['it' => 'Liechtenstein', 'de' => 'Liechtenstein', 'en' => 'Liechtenstein', 'adj' => 'liechtensteinisches'],
        'LT' => ['it' => 'Lituania', 'de' => 'Litauen', 'en' => 'Lithuania', 'adj' => 'litauisches'],
        'MT' => ['it' => 'Malta', 'de' => 'Malta', 'en' => 'Malta', 'adj' => 'maltesisches'],
        'NO' => ['it' => 'Norvegia', 'de' => 'Norwegen', 'en' => 'Norway', 'adj' => 'norwegisches'],
        'RO' => ['it' => 'Romania', 'de' => 'Rumänien', 'en' => 'Romania', 'adj' => 'rumänisches'],
        'SK' => ['it' => 'Slovacchia', 'de' => 'Slowakei', 'en' => 'Slovakia', 'adj' => 'slowakisches'],
        'SI' => ['it' => 'Slovenia', 'de' => 'Slowenien', 'en' => 'Slovenia', 'adj' => 'slowenisches'],
        'SE' => ['it' => 'Svezia', 'de' => 'Schweden', 'en' => 'Sweden', 'adj' => 'schwedisches'],
        'US' => ['it' => 'Stati Uniti', 'de' => 'Vereinigte Staaten', 'en' => 'United States', 'adj' => 'US-amerikanisches'],
        'CA' => ['it' => 'Canada', 'de' => 'Kanada', 'en' => 'Canada', 'adj' => 'kanadisches'],
        'GI' => ['it' => 'Gibilterra', 'de' => 'Gibraltar', 'en' => 'Gibraltar', 'adj' => 'gibraltarisches'],
    ];

    /* ---------- vorab.php: Festpreis vom Partner (02.10.2026) ----------
       Der Kunde sieht Preis und Leistungen und trägt nur seine Angaben ein.
       Sie / Lei, wie überall in Kundentexten. */
    public const VORAB = [
        'titel'      => ['it' => 'Il suo preventivo al prezzo concordato', 'de' => 'Ihr Angebot zum vereinbarten Preis', 'en' => 'Your quote at the agreed price'],
        'schritte'   => ['it' => 'Vecom Design · Il suo accesso personale', 'de' => 'Vecom Design · Ihr persönlicher Zugang', 'en' => 'Vecom Design · Your personal access'],
        'empfohlen'  => ['it' => 'Concordato con {partner}', 'de' => 'Vereinbart mit {partner}', 'en' => 'Agreed with {partner}'],
        'preis'      => ['it' => 'Prezzo concordato', 'de' => 'Vereinbarter Preis', 'en' => 'Agreed price'],
        'fest'       => ['it' => 'prezzo fisso, una tantum', 'de' => 'Festpreis, einmalig', 'en' => 'fixed price, one-off'],
        'leistungen' => ['it' => 'Cosa è incluso', 'de' => 'Was enthalten ist', 'en' => 'What is included'],
        'lead'       => ['it' => 'Per preparare il preventivo e la pagina con i dati legali del suo sito ci servono solo alcune informazioni. Nessun questionario.',
                         'de' => 'Damit wir Ihr Angebot ausstellen und das Impressum Ihrer Website vorbereiten können, brauchen wir nur noch ein paar Angaben. Ein Fragebogen ist nicht nötig.',
                         'en' => 'To issue your quote and prepare the legal notice of your website, we only need a few details. No questionnaire.'],
        'kontakt'    => ['it' => 'Contatto', 'de' => 'Kontakt', 'en' => 'Contact'],
        'impressum'  => ['it' => 'Dati per il sito e la ricevuta', 'de' => 'Angaben für Impressum und Rechnung', 'en' => 'Details for legal notice and invoice'],
        'f_name'     => ['it' => 'Nome e cognome', 'de' => 'Vor- und Nachname', 'en' => 'First and last name'],
        'f_firma'    => ['it' => 'Azienda / attività', 'de' => 'Firma / Betrieb', 'en' => 'Company / business'],
        'f_email'    => ['it' => 'E-mail', 'de' => 'E-Mail', 'en' => 'Email'],
        'f_telefon'  => ['it' => 'Telefono', 'de' => 'Telefon', 'en' => 'Phone'],
        'f_strasse'  => ['it' => 'Via e numero civico', 'de' => 'Straße und Hausnummer', 'en' => 'Street and number'],
        'f_plz'      => ['it' => 'CAP', 'de' => 'PLZ', 'en' => 'Postcode'],
        'f_ort'      => ['it' => 'Città', 'de' => 'Ort', 'en' => 'City'],
        'f_land'     => ['it' => 'Paese', 'de' => 'Land', 'en' => 'Country'],
        'optional'   => ['it' => '(facoltativo)', 'de' => '(optional)', 'en' => '(optional)'],
        'zustimmung' => ['it' => 'Ho letto la {datenschutz} e accetto le {agb}.', 'de' => 'Ich habe die {datenschutz} gelesen und bin mit den {agb} einverstanden.', 'en' => 'I have read the {datenschutz} and accept the {agb}.'],
        'datenschutz'=> ['it' => 'informativa privacy', 'de' => 'Datenschutzerklärung', 'en' => 'privacy policy'],
        'agb'        => ['it' => 'condizioni generali', 'de' => 'AGB', 'en' => 'terms and conditions'],
        'knopf'      => ['it' => 'Avanti al mio spazio personale', 'de' => 'Weiter zu meinem Dashboard', 'en' => 'Continue to my dashboard'],
        'klein'      => ['it' => 'Così non si impegna ancora: il preventivo arriva nel suo spazio personale e diventa vincolante solo quando lo accetta.',
                         'de' => 'Damit gehen Sie noch keine Verpflichtung ein: Das Angebot kommt in Ihr Dashboard und wird erst verbindlich, wenn Sie es annehmen.',
                         'en' => 'This does not commit you yet: the quote arrives in your dashboard and only becomes binding when you accept it.'],
        'fehlt'      => ['it' => 'Compili per favore i campi evidenziati.', 'de' => 'Bitte füllen Sie die markierten Felder aus.', 'en' => 'Please fill in the highlighted fields.'],
        'unbekannt'  => ['it' => 'Questo link non è valido. Chieda per favore alla persona che glielo ha inviato.', 'de' => 'Dieser Link ist nicht gültig. Bitte fragen Sie bei der Person nach, die ihn Ihnen geschickt hat.', 'en' => 'This link is not valid. Please ask the person who sent it to you.'],
        'abgelaufen' => ['it' => 'Questo link è scaduto. Chieda per favore un link nuovo.', 'de' => 'Dieser Link ist abgelaufen. Bitte fragen Sie nach einem neuen.', 'en' => 'This link has expired. Please ask for a new one.'],
        'schon'      => ['it' => 'Questo link è già stato usato. Il link al suo spazio personale è nella nostra e-mail.', 'de' => 'Dieser Link wurde bereits verwendet. Den Link zu Ihrem Dashboard finden Sie in unserer E-Mail.', 'en' => 'This link has already been used. The link to your dashboard is in our email.'],
        'panne'      => ['it' => 'Non è andato a buon fine. Riprovi tra un minuto, per favore.', 'de' => 'Das hat gerade nicht geklappt. Bitte versuchen Sie es in einer Minute noch einmal.', 'en' => 'That did not work just now. Please try again in a minute.'],
        'land_Italien'     => ['it' => 'Italia', 'de' => 'Italien', 'en' => 'Italy'],
        'land_Deutschland' => ['it' => 'Germania', 'de' => 'Deutschland', 'en' => 'Germany'],
        'land_Österreich'  => ['it' => 'Austria', 'de' => 'Österreich', 'en' => 'Austria'],
        'land_Schweiz'     => ['it' => 'Svizzera', 'de' => 'Schweiz', 'en' => 'Switzerland'],
        'land_Sonstiges'   => ['it' => 'Altro paese', 'de' => 'Anderes Land', 'en' => 'Other country'],
    ];

    public const PARTNER = [
        /* Kunde mit vereinbartem Preis (PartnerVorab, 02.10.2026) */
        'pv_titel'    => ['it' => 'Cliente con prezzo concordato', 'de' => 'Kunde mit vereinbartem Preis', 'en' => 'Customer with an agreed price'],
        'pv_text'     => ['it' => 'Ha già concordato un prezzo con un cliente? Lo inserisca qui. Riceve un link personale da inviare lei stesso al cliente: inserisce solo i suoi dati e arriva nel suo spazio personale, senza questionario. Vecom controlla il prezzo e gli invia il preventivo; il cliente conta per lei.',
                         'de' => 'Sie haben mit einem Kunden schon einen Preis vereinbart? Tragen Sie ihn hier ein. Sie bekommen einen persönlichen Link, den Sie dem Kunden selbst schicken: Er trägt nur seine Daten ein und landet in seinem Dashboard – ohne Fragebogen. Vecom prüft den Preis und schickt ihm das Angebot; der Kunde zählt für Sie.',
                         'en' => 'Already agreed a price with a customer? Enter it here. You get a personal link to send to the customer yourself: they only enter their details and land in their dashboard – no questionnaire. Vecom checks the price and sends the quote; the customer counts for you.'],
        'pv_f_preis'  => ['it' => 'Prezzo concordato in € (una tantum)', 'de' => 'Vereinbarter Preis in € (einmalig)', 'en' => 'Agreed price in € (one-off)'],
        'pv_f_leistungen' => ['it' => 'Cosa è concordato? (es. sito di 5 pagine + logo)', 'de' => 'Was ist vereinbart? (z. B. Website 5 Seiten + Logo)', 'en' => 'What is agreed? (e.g. 5-page website + logo)'],
        'pv_f_bezeichnung' => ['it' => 'Sua nota per riconoscerlo (es. nome dell’attività) – solo per lei', 'de' => 'Ihre Notiz zur Wiedererkennung (z. B. Name des Betriebs) – nur für Sie', 'en' => 'Your note to recognise it (e.g. business name) – only for you'],
        'pv_f_sprache'=> ['it' => 'Lingua del cliente', 'de' => 'Sprache des Kunden', 'en' => 'Customer’s language'],
        'pv_knopf'    => ['it' => 'Crea link', 'de' => 'Link erstellen', 'en' => 'Create link'],
        'pv_neu'      => ['it' => 'Link creato. Lo copi e lo invii al cliente:', 'de' => 'Link erstellt. Kopieren und dem Kunden schicken:', 'en' => 'Link created. Copy it and send it to the customer:'],
        'pv_kopieren' => ['it' => 'Copia', 'de' => 'Kopieren', 'en' => 'Copy'],
        'pv_whatsapp' => ['it' => 'Invia con WhatsApp', 'de' => 'Per WhatsApp senden', 'en' => 'Send via WhatsApp'],
        'pv_gueltig'  => ['it' => 'Il link vale {tage} giorni e si può usare una sola volta.', 'de' => 'Der Link gilt {tage} Tage und lässt sich einmal verwenden.', 'en' => 'The link is valid for {tage} days and can be used once.'],
        'pv_liste'    => ['it' => 'I suoi link', 'de' => 'Ihre Links', 'en' => 'Your links'],
        'pv_weg'      => ['it' => 'Ritira', 'de' => 'Zurückziehen', 'en' => 'Withdraw'],
        'pv_s_offen'       => ['it' => 'In attesa del cliente', 'de' => 'Wartet auf den Kunden', 'en' => 'Waiting for the customer'],
        'pv_s_abgelaufen'  => ['it' => 'Scaduto', 'de' => 'Abgelaufen', 'en' => 'Expired'],
        'pv_s_eingetragen' => ['it' => 'Cliente registrato – preventivo in preparazione', 'de' => 'Kunde eingetragen – Angebot in Vorbereitung', 'en' => 'Customer registered – quote being prepared'],
        'pv_s_beim_kunden' => ['it' => 'Preventivo dal cliente', 'de' => 'Angebot beim Kunden', 'en' => 'Quote with the customer'],
        'pv_s_angenommen'  => ['it' => 'Accettato', 'de' => 'Angenommen', 'en' => 'Accepted'],
        'pv_s_abgelehnt'   => ['it' => 'Non accettato', 'de' => 'Nicht angenommen', 'en' => 'Not accepted'],
        'pv_e_preis'  => ['it' => 'Inserisca un prezzo tra 50 € e 100.000 € (es. 950 oppure 950,00).', 'de' => 'Bitte einen Preis zwischen 50 € und 100.000 € eintragen (z. B. 950 oder 950,00).', 'en' => 'Please enter a price between €50 and €100,000 (e.g. 950 or 950,00).'],
        'pv_e_leistungen' => ['it' => 'Scriva in breve cosa è concordato.', 'de' => 'Bitte kurz eintragen, was vereinbart ist.', 'en' => 'Please briefly enter what is agreed.'],
        'pv_e_genug'  => ['it' => 'Per oggi ha già creato molti link. Riprovi domani.', 'de' => 'Für heute haben Sie schon viele Links erstellt. Bitte morgen weiter.', 'en' => 'You have already created many links today. Please continue tomorrow.'],
        'pv_wa_text'  => ['it' => 'Buongiorno! Ecco il suo link personale per Vecom Design con il prezzo concordato: {link}', 'de' => 'Guten Tag! Hier ist Ihr persönlicher Link zu Vecom Design mit dem vereinbarten Preis: {link}', 'en' => 'Hello! Here is your personal link to Vecom Design with the agreed price: {link}'],
        'titel'      => ['it' => 'Programma partner', 'de' => 'Partnerprogramm', 'en' => 'Partner programme'],
        'lead'       => ['it' => 'Consiglia Vecom Design a chi ha bisogno di un sito. Per ogni acquisto che arriva tramite il suo link riceve una provvigione.',
                         'de' => 'Empfehlen Sie Vecom Design an Betriebe, die eine Website brauchen. Für jeden Kauf, der über Ihren Link kommt, bekommen Sie eine Provision.',
                         'en' => 'Recommend Vecom Design to businesses that need a website. Every purchase that comes through your link earns you a commission.'],
        'bedingungen'=> ['it' => 'Oggi: {satz} sull’importo netto effettivamente pagato. Pagamento da {min}, dopo {tage} giorni (periodo di recesso del cliente).',
                         'de' => 'Derzeit: {satz} vom tatsächlich bezahlten Nettobetrag. Auszahlung ab {min}, nach {tage} Tagen (Widerrufsfrist des Kunden).',
                         'en' => 'Currently: {satz} of the net amount actually paid. Paid out from {min}, after {tage} days (the customer’s withdrawal period).'],
        'f_name'     => ['it' => 'Nome e cognome', 'de' => 'Vor- und Nachname', 'en' => 'Full name'],
        'f_email'    => ['it' => 'E-mail', 'de' => 'E-Mail', 'en' => 'Email'],
        'f_firma'    => ['it' => 'Azienda (facoltativo)', 'de' => 'Firma (optional)', 'en' => 'Company (optional)'],
        'f_steuer'   => ['it' => 'Partita IVA o codice fiscale (facoltativo)', 'de' => 'Partita IVA / Steuernummer (optional)', 'en' => 'VAT or tax number (optional)'],
        'f_kanal'    => ['it' => 'Dove ci consiglierà? (sito, social, clienti, …)', 'de' => 'Wo werden Sie uns empfehlen? (Website, Social Media, Kunden, …)', 'en' => 'Where will you recommend us? (website, social media, clients, …)'],
        'f_text'     => ['it' => 'Qualcosa da aggiungere? (facoltativo)', 'de' => 'Möchten Sie noch etwas sagen? (optional)', 'en' => 'Anything to add? (optional)'],
        'lesen'      => ['it' => 'Leggere l’accordo partner', 'de' => 'Partnervereinbarung lesen', 'en' => 'Read the partner agreement'],
        'ok'         => ['it' => 'Ho letto l’accordo partner e lo accetto.', 'de' => 'Ich habe die Partnervereinbarung gelesen und stimme ihr zu.', 'en' => 'I have read the partner agreement and accept it.'],
        'knopf'      => ['it' => 'Candidarsi', 'de' => 'Bewerben', 'en' => 'Apply'],
        'danke'      => ['it' => 'Grazie! Esaminiamo la sua candidatura e le scriviamo entro pochi giorni.',
                         'de' => 'Danke! Wir sehen uns Ihre Bewerbung an und melden uns in wenigen Tagen.',
                         'en' => 'Thank you! We’ll review your application and get back to you within a few days.'],
        'zu'         => ['it' => 'Al momento non accettiamo nuove candidature.', 'de' => 'Im Moment nehmen wir keine neuen Bewerbungen an.', 'en' => 'We are not accepting new applications at the moment.'],
        'angaben'    => ['it' => 'Servono nome e un indirizzo e-mail valido.', 'de' => 'Es braucht Name und eine gültige E-Mail-Adresse.', 'en' => 'A name and a valid email address are required.'],
        'vereinbarung'=> ['it' => 'Per candidarsi occorre accettare l’accordo partner.', 'de' => 'Zum Bewerben muss die Partnervereinbarung bestätigt werden.', 'en' => 'Please accept the partner agreement to apply.'],
        'panne'      => ['it' => 'Qualcosa non ha funzionato. Riprovi tra poco.', 'de' => 'Etwas hat nicht geklappt. Bitte gleich noch einmal.', 'en' => 'Something went wrong. Please try again shortly.'],
        'p_titel'    => ['it' => 'La sua pagina partner', 'de' => 'Ihre Partnerseite', 'en' => 'Your partner page'],
        'p_link'     => ['it' => 'Il suo link', 'de' => 'Ihr Link', 'en' => 'Your link'],
        'p_code'     => ['it' => 'Il suo codice — chi non clicca lo scrive in «Chi ci ha consigliato?»', 'de' => 'Ihr Code — wer nicht klickt, tippt ihn bei „Wer hat uns empfohlen?“ ein', 'en' => 'Your code — people who don’t click type it into “Who recommended us?”'],
        'kopieren'   => ['it' => 'Copia', 'de' => 'Kopieren', 'en' => 'Copy'],
        'klicks'     => ['it' => 'Clic', 'de' => 'Klicks', 'en' => 'Clicks'],
        'kunden'     => ['it' => 'Clienti', 'de' => 'Kunden', 'en' => 'Customers'],
        'verkaeufe'  => ['it' => 'Vendite', 'de' => 'Verkäufe', 'en' => 'Sales'],
        'provision'  => ['it' => 'Provvigioni', 'de' => 'Provision', 'en' => 'Commission'],
        's_wartet'   => ['it' => 'in attesa (recesso)', 'de' => 'wartet (Widerrufsfrist)', 'en' => 'waiting (withdrawal period)'],
        's_freigabe' => ['it' => 'in verifica', 'de' => 'in Prüfung', 'en' => 'under review'],
        's_bereit'   => ['it' => 'pronta per il pagamento', 'de' => 'auszahlungsbereit', 'en' => 'ready to pay'],
        's_ausgezahlt'=> ['it' => 'pagata', 'de' => 'ausgezahlt', 'en' => 'paid'],
        's_storniert'=> ['it' => 'annullata (rimborso)', 'de' => 'entfallen (Erstattung)', 'en' => 'cancelled (refund)'],
        's_zurueckgeholt' => ['it' => 'stornata dopo rimborso', 'de' => 'nach Erstattung zurückgebucht', 'en' => 'reversed after refund'],
        's_rueckforderung'=> ['it' => 'da restituire (rimborso)', 'de' => 'zurückzuzahlen (Erstattung)', 'en' => 'to be repaid (refund)'],
        'liste'      => ['it' => 'Le sue provvigioni', 'de' => 'Ihre Provisionen', 'en' => 'Your commissions'],
        'datum'      => ['it' => 'Data', 'de' => 'Datum', 'en' => 'Date'],
        'art'        => ['it' => 'Tipo', 'de' => 'Art', 'en' => 'Type'],
        'betrag'     => ['it' => 'Importo', 'de' => 'Betrag', 'en' => 'Amount'],
        'stand'      => ['it' => 'Stato', 'de' => 'Stand', 'en' => 'Status'],
        'a_website'  => ['it' => 'Sito web', 'de' => 'Website', 'en' => 'Website'],
        'a_betreuung'=> ['it' => 'Assistenza', 'de' => 'Betreuung', 'en' => 'Care plan'],
        'a_hosting'  => ['it' => 'Hosting', 'de' => 'Hosting', 'en' => 'Hosting'],
        'keine'      => ['it' => 'Ancora nessuna provvigione.', 'de' => 'Noch keine Provision.', 'en' => 'No commission yet.'],
        'privat'     => ['it' => 'Per rispetto dei clienti qui non compaiono nomi — solo numeri.', 'de' => 'Aus Rücksicht auf die Kunden stehen hier keine Namen — nur Zahlen.', 'en' => 'Out of respect for customers, no names appear here — only numbers.'],
        'konto'      => ['it' => 'Conto per i pagamenti', 'de' => 'Auszahlungskonto', 'en' => 'Payout account'],
        'konto_text' => ['it' => 'Le provvigioni arrivano tramite Stripe. Stripe verifica una volta la sua identità e il suo IBAN — noi non vediamo i suoi dati bancari.',
                         'de' => 'Provisionen kommen über Stripe. Stripe prüft einmal Ihre Identität und Ihre IBAN — wir sehen Ihre Bankdaten nicht.',
                         'en' => 'Commissions are paid via Stripe. Stripe verifies your identity and IBAN once — we never see your bank details.'],
        'konto_knopf'=> ['it' => 'Configura il conto su Stripe', 'de' => 'Konto bei Stripe einrichten', 'en' => 'Set up account with Stripe'],
        'konto_weiter'=> ['it' => 'Completa la configurazione su Stripe', 'de' => 'Einrichtung bei Stripe fortsetzen', 'en' => 'Continue setup with Stripe'],
        'konto_laden' => ['it' => 'Stripe si sta aprendo…', 'de' => 'Stripe wird geöffnet …', 'en' => 'Opening Stripe…'],
        'konto_bereit'=> ['it' => 'Il conto è pronto: le provvigioni vengono pagate automaticamente.', 'de' => 'Das Konto ist bereit: Provisionen werden automatisch ausgezahlt.', 'en' => 'Your account is ready: commissions are paid out automatically.'],
        // Land und Prüfung (28.09.2026, Uwe: Land bei Stripe nur Italien; wie verifizieren sie sich?)
        'stripe_agb_voll' => ['it' => 'Configurando il conto accetta lo [Stripe Connected Account Agreement](https://stripe.com/connect-account/legal/full).',
                              'de' => 'Mit der Einrichtung stimmen Sie dem [Stripe Connected Account Agreement](https://stripe.com/connect-account/legal/full) zu.',
                              'en' => 'By setting up the account you agree to the [Stripe Connected Account Agreement](https://stripe.com/connect-account/legal/full).'],
        'konto_land' => ['it' => 'Paese in cui vive (o ha sede la sua attività)', 'de' => 'Land, in dem Sie wohnen (bzw. Ihr Betrieb sitzt)', 'en' => 'Country where you live (or your business is based)'],
        'konto_land_hilfe' => ['it' => 'Stripe verifica i dati secondo le regole di questo paese. Dopo la verifica il paese non si può più cambiare.',
                               'de' => 'Stripe prüft Ihre Angaben nach den Regeln dieses Landes. Nach der Prüfung lässt sich das Land nicht mehr ändern.',
                               'en' => 'Stripe checks your details under this country’s rules. After verification the country can no longer be changed.'],
        'konto_land_bereit' => ['it' => 'Il conto è già verificato: per cambiare paese ci scriva.', 'de' => 'Das Konto ist schon geprüft: Für ein anderes Land schreiben Sie uns bitte.', 'en' => 'The account is already verified: to change the country, please write to us.'],
        'konto_land_wahl'  => ['it' => 'Scelga il paese', 'de' => 'Bitte Land wählen', 'en' => 'Please choose a country'],
        'konto_land_suche' => ['it' => 'Cerca paese…', 'de' => 'Land suchen …', 'en' => 'Search country…'],
        'konto_land_keins' => ['it' => 'Nessun paese trovato.', 'de' => 'Kein Land gefunden.', 'en' => 'No country found.'],
        'konto_land_gewaehlt' => ['it' => 'Paese scelto: {land}', 'de' => 'Gewähltes Land: {land}', 'en' => 'Selected country: {land}'],
        'konto_land_fehlt' => ['it' => 'Scelga prima il paese in cui vive.', 'de' => 'Bitte wählen Sie zuerst Ihr Land.', 'en' => 'Please choose your country first.'],
        'konto_land_nicht' => ['it' => 'Questo paese al momento non è supportato da Stripe Connect.', 'de' => 'Dieses Land wird von Stripe Connect aktuell nicht unterstützt.', 'en' => 'This country is currently not supported by Stripe Connect.'],
        'konto_start_fehler' => ['it' => 'Non è stato possibile avviare la verifica Stripe. Riprovi, per favore.', 'de' => 'Die Stripe-Verifizierung konnte nicht gestartet werden. Bitte versuchen Sie es erneut.', 'en' => 'The Stripe verification could not be started. Please try again.'],
        'konto_abweichend' => ['it' => 'Il suo conto Stripe è stato creato per un altro paese. Scelga qui sotto, in «Cambia paese», il paese giusto: poi configura Stripe di nuovo con quel paese.', 'de' => 'Ihr Stripe-Konto wurde für ein anderes Land angelegt. Wählen Sie unten unter „Land ändern“ Ihr Land — dann richten Sie Stripe damit neu ein.', 'en' => 'Your Stripe account was created for a different country. Choose your country below under “Change country” — then set up Stripe again with it.'],
        'konto_wechsel_titel' => ['it' => 'Cambia paese', 'de' => 'Land ändern', 'en' => 'Change country'],
        'konto_wechsel_hilfe' => ['it' => 'Stripe fissa il paese quando crea il conto. Un altro paese significa: nuovo conto Stripe con nuova verifica (documento, IBAN). Finché il nuovo conto non è confermato non paghiamo tramite Stripe. Il conto precedente resta su Stripe se contiene ancora denaro.',
                                  'de' => 'Stripe legt das Land beim Anlegen fest. Ein anderes Land heißt: ein neues Stripe-Konto mit neuer Verifizierung (Ausweis, IBAN). Bis das neue Konto bestätigt ist, zahlen wir nicht über Stripe aus. Ihr bisheriges Konto bleibt bei Stripe bestehen, falls dort noch Geld liegt.',
                                  'en' => 'Stripe fixes the country when the account is created. A different country means a new Stripe account with a new verification (ID, IBAN). Until the new account is confirmed we don’t pay out via Stripe. Your previous account stays at Stripe if it still holds money.'],
        'konto_wechsel_ok' => ['it' => 'Sì, creare un nuovo conto Stripe per questo paese', 'de' => 'Ja, neues Stripe-Konto für dieses Land anlegen', 'en' => 'Yes, create a new Stripe account for this country'],
        'konto_wechsel_knopf' => ['it' => 'Cambia paese e verifica di nuovo', 'de' => 'Land ändern und neu verifizieren', 'en' => 'Change country and verify again'],
        'konto_land_bestaetigen' => ['it' => 'Confermi che deve essere creato un nuovo conto Stripe.', 'de' => 'Bitte bestätigen Sie, dass ein neues Stripe-Konto angelegt werden soll.', 'en' => 'Please confirm that a new Stripe account should be created.'],
        'konto_land_gleich' => ['it' => 'Questo è già il paese del suo conto Stripe.', 'de' => 'Das ist bereits das Land Ihres Stripe-Kontos.', 'en' => 'That is already the country of your Stripe account.'],
        'konto_st_frist' => ['it' => 'Stripe chiede ancora dati ({n}){frist}. Altrimenti Stripe sospende i pagamenti. Continui la verifica, per favore.', 'de' => 'Stripe braucht noch Angaben ({n}){frist}. Sonst setzt Stripe Ihre Auszahlungen aus. Bitte die Verifizierung fortsetzen.', 'en' => 'Stripe still needs some details ({n}){frist}. Otherwise Stripe will pause your payouts. Please continue the verification.'],
        'konto_frist' => ['it' => ' entro il {datum}', 'de' => ' bis {datum}', 'en' => ' by {datum}'],
        'konto_ck_titel'   => ['it' => 'Stato della verifica', 'de' => 'Stand der Verifizierung', 'en' => 'Verification status'],
        'konto_ck_identitaet' => ['it' => 'Identità confermata', 'de' => 'Identität bestätigt', 'en' => 'Identity confirmed'],
        'konto_ck_auszahlung' => ['it' => 'Pagamenti attivati', 'de' => 'Auszahlungen aktiviert', 'en' => 'Payouts enabled'],
        'konto_ck_voll'    => ['it' => 'Conto configurato completamente', 'de' => 'Konto vollständig eingerichtet', 'en' => 'Account fully set up'],
        'konto_nicht_fertig' => ['it' => 'Verifica non ancora completata', 'de' => 'Verifizierung noch nicht abgeschlossen', 'en' => 'Verification not yet completed'],
        'konto_fortsetzen' => ['it' => 'Continua la verifica Stripe', 'de' => 'Stripe-Verifizierung fortsetzen', 'en' => 'Continue Stripe verification'],
        'konto_abgelehnt'  => ['it' => 'Stripe non ha potuto verificare il conto. Ci scriva: troviamo una soluzione insieme.', 'de' => 'Stripe konnte das Konto nicht bestätigen. Schreiben Sie uns bitte — wir finden gemeinsam eine Lösung.', 'en' => 'Stripe could not verify the account. Please write to us — we’ll find a solution together.'],
        'konto_wie_titel' => ['it' => 'Come funziona la verifica', 'de' => 'So läuft die Prüfung', 'en' => 'How verification works'],
        'konto_wie' => [
            ['it' => 'Scelga il paese e clicchi su «Configura il conto su Stripe».', 'de' => 'Land wählen und auf „Konto bei Stripe einrichten“ tippen.', 'en' => 'Choose your country and tap “Set up account with Stripe”.'],
            ['it' => 'Stripe chiede nome, data di nascita, indirizzo, telefono e il suo IBAN — per le aziende anche dati aziendali e partita IVA.', 'de' => 'Stripe fragt Name, Geburtsdatum, Adresse, Telefon und Ihre IBAN ab — bei einem Betrieb auch Firmendaten und Steuernummer.', 'en' => 'Stripe asks for your name, date of birth, address, phone and IBAN — for a business also company details and tax number.'],
            ['it' => 'A seconda del paese Stripe può chiedere una foto del documento d’identità (fronte e retro) o un selfie.', 'de' => 'Je nach Land und Angaben will Stripe ein Foto Ihres Ausweises (Vorder- und Rückseite) oder ein Selfie.', 'en' => 'Depending on the country and details, Stripe may ask for a photo of your ID (front and back) or a selfie.'],
            ['it' => 'Stripe verifica di solito in pochi minuti, a volte fino a due giorni lavorativi. Quando qui compare «Il conto è pronto», le provvigioni vengono pagate automaticamente.', 'de' => 'Stripe prüft meist in wenigen Minuten, manchmal bis zu zwei Werktage. Sobald hier „Das Konto ist bereit“ steht, werden Provisionen automatisch ausgezahlt.', 'en' => 'Stripe usually verifies within minutes, sometimes up to two working days. Once “Your account is ready” appears here, commissions are paid out automatically.'],
        ],
        'konto_st_pruefung' => ['it' => 'Stripe sta verificando i suoi dati. Non deve fare nulla — la avvisiamo quando il conto è pronto.', 'de' => 'Stripe prüft gerade Ihre Angaben. Sie müssen nichts tun — Sie sehen es hier, sobald das Konto bereit ist.', 'en' => 'Stripe is checking your details. Nothing to do — you’ll see it here once the account is ready.'],
        'konto_st_fehlt' => ['it' => 'A Stripe mancano ancora dati ({n}). Riprenda la configurazione: Stripe le mostra cosa manca.', 'de' => 'Stripe braucht noch Angaben ({n}). Bitte die Einrichtung fortsetzen — Stripe zeigt Ihnen, was fehlt.', 'en' => 'Stripe still needs some details ({n}). Please continue the setup — Stripe shows you what’s missing.'],
        'konto_st_land' => ['it' => 'Conto Stripe in: {land}', 'de' => 'Stripe-Konto in: {land}', 'en' => 'Stripe account in: {land}'],
        'stripe_agb' => ['it' => 'Configurando il conto accetta il [Stripe Recipient Agreement](https://stripe.com/connect-account/legal/recipient).',
                         'de' => 'Mit der Einrichtung stimmen Sie dem [Stripe Recipient Agreement](https://stripe.com/connect-account/legal/recipient) zu.',
                         'en' => 'By setting up the account you agree to the [Stripe Recipient Agreement](https://stripe.com/connect-account/legal/recipient).'],
        'v_fehlt'    => ['it' => 'Manca ancora la sua conferma dell’accordo partner. Senza, non possiamo pagare.',
                         'de' => 'Ihre Bestätigung der Partnervereinbarung fehlt noch. Ohne sie können wir nicht auszahlen.',
                         'en' => 'We still need your acceptance of the partner agreement. Without it we can’t pay out.'],
        'v_knopf'    => ['it' => 'Accetto', 'de' => 'Zustimmen', 'en' => 'Accept'],
        'auszahlungen'=> ['it' => 'Pagamenti', 'de' => 'Auszahlungen', 'en' => 'Payouts'],
        'beleg'      => ['it' => 'Documento (PDF)', 'de' => 'Beleg (PDF)', 'en' => 'Statement (PDF)'],
        'pausiert'   => ['it' => 'Il suo link è in pausa: i nuovi clic non contano finché non lo riattiviamo.',
                         'de' => 'Ihr Link ist pausiert: Neue Klicks zählen nicht, bis wir ihn wieder aktivieren.',
                         'en' => 'Your link is paused: new clicks don’t count until we reactivate it.'],
        /* „So funktioniert's“ und der nächste Schritt (Uwe, 26.09.2026: „verständlicher“) */
        'so_titel'   => ['it' => 'Come funziona', 'de' => 'So funktioniert’s', 'en' => 'How it works'],
        'so_1_t'     => ['it' => 'Condivida il suo link', 'de' => 'Link teilen', 'en' => 'Share your link'],
        'so_1'       => ['it' => 'Con amici, clienti, sui social o su WhatsApp. Chi clicca vede il nostro sito normale — noi sappiamo che arriva da lei.',
                         'de' => 'Mit Bekannten, Kunden, in sozialen Netzen oder per WhatsApp. Wer klickt, sieht unsere normale Website — wir wissen, dass er von Ihnen kommt.',
                         'en' => 'With friends, clients, on social media or WhatsApp. Whoever clicks sees our normal website — we know they came from you.'],
        'so_2_t'     => ['it' => 'Il cliente acquista', 'de' => 'Der Kunde kauft', 'en' => 'The customer buys'],
        'so_2'       => ['it' => 'Anche giorni dopo e da un altro dispositivo: conta il primo contatto. Riceve {satz} su ciò che paga davvero.',
                         'de' => 'Auch Tage später und von einem anderen Gerät: Es zählt der erste Kontakt. Sie bekommen {satz} von dem, was er wirklich bezahlt.',
                         'en' => 'Even days later and from another device: the first contact counts. You get {satz} of what they actually pay.'],
        'so_3_t'     => ['it' => 'Lei riceve il denaro', 'de' => 'Sie bekommen Ihr Geld', 'en' => 'You get paid'],
        'so_3'       => ['it' => 'Dopo {tage} giorni (il cliente può ancora recedere), da {min}, nel modo che sceglie qui sotto.',
                         'de' => 'Nach {tage} Tagen (so lange kann der Kunde widerrufen), ab {min}, auf dem Weg, den Sie unten wählen.',
                         'en' => 'After {tage} days (the customer can still withdraw until then), from {min}, the way you choose below.'],
        'n_titel'    => ['it' => 'Il prossimo passo', 'de' => 'Ihr nächster Schritt', 'en' => 'Your next step'],
        'n_vereinbarung' => ['it' => 'Confermi l’accordo partner qui sotto — senza non possiamo pagare.', 'de' => 'Bestätigen Sie unten die Partnervereinbarung — ohne sie können wir nicht auszahlen.', 'en' => 'Accept the partner agreement below — we can’t pay out without it.'],
        'n_weg'      => ['it' => 'Indichi come vuole ricevere il denaro (sezione «Come ricevere i pagamenti»).', 'de' => 'Legen Sie fest, wie Sie Ihr Geld bekommen (Abschnitt „Wie Sie Ihr Geld bekommen“).', 'en' => 'Choose how you want to be paid (section “How you get paid”).'],
        'n_teilen'   => ['it' => 'Tutto pronto. Ora condivida il suo link — per esempio via WhatsApp.', 'de' => 'Alles eingerichtet. Jetzt Ihren Link teilen — zum Beispiel per WhatsApp.', 'en' => 'All set. Now share your link — for example on WhatsApp.'],
        'n_laeuft'   => ['it' => 'Tutto a posto. Le provvigioni arrivano da sole appena pronte.', 'de' => 'Alles läuft. Provisionen kommen von selbst, sobald sie fällig sind.', 'en' => 'All good. Commissions arrive by themselves as soon as they’re due.'],
        'weck_titel' => ['it' => 'Il suo link aspetta da un mese', 'de' => 'Ihr Link wartet seit einem Monat', 'en' => 'Your link has been waiting a month'],
        'teilen_mehr' => ['it' => 'Condividere …', 'de' => 'Teilen …', 'en' => 'Share …'],
        'teilen_mail' => ['it' => 'Per e-mail', 'de' => 'Per E-Mail', 'en' => 'By email'],
        'teilen_ansehen' => ['it' => 'Vedere la pagina', 'de' => 'Seite ansehen', 'en' => 'View the page'],
        'teilen_betreff' => ['it' => 'Un sito web per la sua attività', 'de' => 'Eine Website für Ihren Betrieb', 'en' => 'A website for your business'],
        'teilen_seite' => ['it' => 'Condividere la sua pagina', 'de' => 'Ihre Seite teilen', 'en' => 'Share your page'],
        'ck_loeschen' => ['it' => 'Elimina', 'de' => 'Löschen', 'en' => 'Delete'],
        'ck_loeschen_frage' => ['it' => 'Eliminare il rapporto? Il link smette di funzionare.', 'de' => 'Bericht löschen? Der Link funktioniert danach nicht mehr.', 'en' => 'Delete the report? The link will stop working.'],
        'ck_weg_gut' => ['it' => 'Rapporto eliminato.', 'de' => 'Bericht gelöscht.', 'en' => 'Report deleted.'],
        'teilen_wa'  => ['it' => 'Invia su WhatsApp', 'de' => 'Per WhatsApp senden', 'en' => 'Send on WhatsApp'],
        'teilen_text'=> ['it' => 'Ti serve un sito web? Ti consiglio Vecom Design: ', 'de' => 'Sie brauchen eine Website? Ich kann Vecom Design empfehlen: ', 'en' => 'Need a website? I can recommend Vecom Design: '],
        'z_klicks'   => ['it' => 'volte che il link è stato aperto', 'de' => 'so oft wurde Ihr Link geöffnet', 'en' => 'times your link was opened'],
        'z_kunden'   => ['it' => 'persone arrivate da lei', 'de' => 'Menschen, die über Sie kamen', 'en' => 'people who came through you'],
        'z_verkaeufe'=> ['it' => 'pagamenti con provvigione', 'de' => 'Zahlungen mit Provision', 'en' => 'payments earning commission'],
        'z_provision'=> ['it' => 'guadagnato in totale', 'de' => 'insgesamt verdient', 'en' => 'earned in total'],
        'frei_ab'    => ['it' => 'pagabile dal {datum}', 'de' => 'auszahlbar ab {datum}', 'en' => 'payable from {datum}'],
        'faq_titel'  => ['it' => 'Domande frequenti', 'de' => 'Häufige Fragen', 'en' => 'Frequently asked questions'],
        'faq'        => ['it' => "Devo vendere qualcosa?\nNo. Condivide il link, del resto ci occupiamo noi: consulenza, offerta, lavoro.\n\nQuanto guadagno?\n{satz} di ciò che il cliente paga davvero — sul sito e, se previsto, anche sui primi mesi di assistenza.\n\nE se il cliente compra dopo settimane?\nConta lo stesso: il cliente resta assegnato a lei per {zuordnung} mesi dal primo contatto.\n\nChi vede i miei dati?\nSolo noi. I clienti non vedono nulla di lei, e lei non vede i dati dei clienti.\n\nCosa costa?\nNiente. Nessun abbonamento, nessun obbligo, può smettere quando vuole.",
                         'de' => "Muss ich etwas verkaufen?\nNein. Sie teilen den Link, alles Weitere machen wir: Beratung, Angebot, Arbeit.\n\nWie viel verdiene ich?\n{satz} von dem, was der Kunde wirklich bezahlt — für die Website und, wo vorgesehen, auch für die ersten Monate Betreuung.\n\nUnd wenn der Kunde erst Wochen später kauft?\nZählt trotzdem: Der Kunde bleibt ab dem ersten Kontakt {zuordnung} Monate Ihnen zugeordnet.\n\nWer sieht meine Daten?\nNur wir. Kunden sehen nichts von Ihnen, und Sie sehen keine Kundendaten.\n\nWas kostet das?\nNichts. Kein Abo, keine Pflicht, Sie können jederzeit aufhören.",
                         'en' => "Do I have to sell anything?\nNo. You share the link; we do the rest: advice, quote, the work.\n\nHow much do I earn?\n{satz} of what the customer actually pays — for the website and, where applicable, the first months of care too.\n\nWhat if the customer buys weeks later?\nIt still counts: the customer stays assigned to you for {zuordnung} months from the first contact.\n\nWho sees my data?\nOnly us. Customers see nothing about you, and you see no customer data.\n\nWhat does it cost?\nNothing. No subscription, no obligation, stop whenever you like."],
        'konto_fehler' => ['it' => 'La configurazione su Stripe non è riuscita. Siamo stati avvisati. Nel frattempo può scegliere il bonifico SEPA qui sopra.',
                           'de' => 'Die Einrichtung bei Stripe hat nicht geklappt. Wir sind informiert. Bis dahin können Sie oben die SEPA-Überweisung wählen.',
                           'en' => 'Setting up with Stripe didn’t work. We’ve been notified. Meanwhile you can choose SEPA bank transfer above.'],
        'anleitung'  => ['it' => 'Come funziona — passo per passo', 'de' => 'So geht’s — Schritt für Schritt', 'en' => 'How it works — step by step'],
        'anl_stripe' => ['it' => "1. Tocchi «Configura il conto su Stripe». Si apre la pagina sicura di Stripe.\n2. Inserisca e-mail e numero di cellulare: Stripe invia un codice via SMS.\n3. Dati personali: nome, data di nascita, indirizzo. Se ha una partita IVA, la indichi.\n4. Il suo IBAN — lì arriveranno le provvigioni.\n5. A volte Stripe chiede una foto del documento d’identità.\n6. Confermi: torna automaticamente qui. Quando Stripe ha verificato (da pochi minuti a 1–2 giorni), qui compare «Il conto è pronto».\nNoi non vediamo mai i suoi dati bancari.",
                         'de' => "1. Tippen Sie auf „Konto bei Stripe einrichten“. Die sichere Seite von Stripe öffnet sich.\n2. E-Mail und Handynummer eingeben — Stripe schickt einen Code per SMS.\n3. Persönliche Angaben: Name, Geburtsdatum, Adresse. Wenn Sie eine Partita IVA haben, geben Sie sie an.\n4. Ihre IBAN — dorthin kommen die Provisionen.\n5. Manchmal verlangt Stripe ein Foto Ihres Ausweises.\n6. Bestätigen: Sie landen automatisch wieder hier. Sobald Stripe geprüft hat (wenige Minuten bis 1–2 Tage), steht hier „Das Konto ist bereit“.\nIhre Bankdaten sehen wir nie.",
                         'en' => "1. Tap “Set up account with Stripe”. Stripe’s secure page opens.\n2. Enter your email and mobile number — Stripe texts you a code.\n3. Personal details: name, date of birth, address. If you have a VAT number, add it.\n4. Your IBAN — that’s where commissions go.\n5. Sometimes Stripe asks for a photo of your ID.\n6. Confirm: you’re brought back here automatically. Once Stripe has checked (a few minutes to 1–2 days), this page says “Your account is ready”.\nWe never see your bank details."],
        'anl_sepa'   => ['it' => "1. Scelga «Bonifico SEPA».\n2. Inserisca l’intestatario del conto e l’IBAN (lo trova nell’app della banca).\n3. Tocchi «Salva». Da quel momento le provvigioni pronte arrivano con bonifico sul suo conto.",
                         'de' => "1. „SEPA-Überweisung“ wählen.\n2. Kontoinhaber und IBAN eintragen (steht in Ihrer Banking-App).\n3. „Speichern“ tippen. Ab dann kommen fällige Provisionen per Überweisung auf Ihr Konto.",
                         'en' => "1. Choose “SEPA bank transfer”.\n2. Enter the account holder and IBAN (you’ll find it in your banking app).\n3. Tap “Save”. From then on, commissions due are paid by transfer to your account."],
        'anl_paypal' => ['it' => "1. Scelga «PayPal».\n2. Inserisca l’indirizzo e-mail del suo conto PayPal.\n3. Tocchi «Salva». Le provvigioni arrivano automaticamente su PayPal.",
                         'de' => "1. „PayPal“ wählen.\n2. Die E-Mail-Adresse Ihres PayPal-Kontos eintragen.\n3. „Speichern“ tippen. Provisionen kommen dann automatisch auf PayPal.",
                         'en' => "1. Choose “PayPal”.\n2. Enter the email address of your PayPal account.\n3. Tap “Save”. Commissions then arrive in PayPal automatically."],
        'st_bronze'  => ['it' => 'Bronzo', 'de' => 'Bronze', 'en' => 'Bronze'],
        'st_silber'  => ['it' => 'Argento', 'de' => 'Silber', 'en' => 'Silver'],
        'st_gold'    => ['it' => 'Oro', 'de' => 'Gold', 'en' => 'Gold'],
        'st_text'    => ['it' => 'Il suo livello: {stufe} — {satz}. Negli ultimi 12 mesi: {n} vendite.', 'de' => 'Ihre Stufe: {stufe} — {satz}. In den letzten 12 Monaten: {n} Verkäufe.', 'en' => 'Your level: {stufe} — {satz}. Last 12 months: {n} sales.'],
        'st_naechst' => ['it' => 'Ancora {fehlen} vendite e passa a {naechste}.', 'de' => 'Noch {fehlen} Verkäufe bis {naechste}.', 'en' => '{fehlen} more sales to reach {naechste}.'],
        'st_top'     => ['it' => 'Ha raggiunto il livello più alto. Grazie!', 'de' => 'Sie haben die höchste Stufe erreicht. Danke!', 'en' => 'You’ve reached the top level. Thank you!'],
        'm_titel'    => ['it' => 'Ho un cliente per voi', 'de' => 'Ich habe einen Kunden für euch', 'en' => 'I have a customer for you'],
        'm_text'     => ['it' => 'Conosce qualcuno che ha bisogno di un sito? Lo inserisca qui: lo contattiamo noi e da subito conta per lei.', 'de' => 'Sie kennen jemanden, der eine Website braucht? Tragen Sie ihn hier ein: Wir melden uns, und er zählt ab sofort für Sie.', 'en' => 'Know someone who needs a website? Enter them here: we’ll get in touch, and they count for you straight away.'],
        'm_telefon'  => ['it' => 'Telefono (facoltativo)', 'de' => 'Telefon (optional)', 'en' => 'Phone (optional)'],
        'm_anliegen' => ['it' => 'Di cosa ha bisogno? (facoltativo)', 'de' => 'Was braucht er? (optional)', 'en' => 'What do they need? (optional)'],
        'm_einverstanden' => ['it' => 'Il cliente è d’accordo che Vecom Design lo contatti.', 'de' => 'Der Kunde ist einverstanden, dass Vecom Design ihn kontaktiert.', 'en' => 'The customer agrees to be contacted by Vecom Design.'],
        'm_knopf'    => ['it' => 'Inviare', 'de' => 'Absenden', 'en' => 'Send'],
        'm_danke'    => ['it' => 'Grazie! Il cliente riceve subito una e-mail personale da noi, in cui c’è scritto che è lei ad averci consigliato. Lo contattiamo a breve.', 'de' => 'Danke! Der Kunde bekommt gleich eine persönliche E-Mail von uns, in der steht, dass Sie uns empfohlen haben. Wir melden uns in Kürze.', 'en' => 'Thank you! The customer gets a personal email from us right away, saying that you recommended us. We’ll be in touch shortly.'],
        'm_genug'    => ['it' => 'Per oggi ha già segnalato molti clienti. Riprovi domani.', 'de' => 'Für heute haben Sie schon viele Kunden gemeldet. Bitte morgen weiter.', 'en' => 'You’ve already sent many customers today. Please continue tomorrow.'],
        'w_titel'    => ['it' => 'Materiale pronto', 'de' => 'Fertige Werbemittel', 'en' => 'Ready-made material'],
        'w_text'     => ['it' => 'Copi un testo, scarichi l’immagine per le storie o stampi la cartolina con il codice QR. Il suo link è già dentro.', 'de' => 'Text kopieren, Bild für Stories laden oder Karte mit QR-Code drucken. Ihr Link ist schon drin.', 'en' => 'Copy a text, download the story image or print the card with the QR code. Your link is already in it.'],
        'w_post1'    => ['it' => "Ti serve un sito web per la tua attività? Con Vecom Design ti trovi bene: prezzo chiaro prima, lavoro seguito passo passo. 👉 {link}", 'de' => "Sie brauchen eine Website für Ihren Betrieb? Bei Vecom Design sind Sie gut aufgehoben: klarer Preis vorher, Schritt für Schritt begleitet. 👉 {link}", 'en' => "Need a website for your business? Vecom Design takes good care of you: clear price upfront, guided step by step. 👉 {link}"],
        'w_post2'    => ['it' => "Il tuo sito è vecchio o non ce l’hai? Ti consiglio Vecom Design — dalla prima chiacchierata al sito online. {link} #pubblicità", 'de' => "Ihre Website ist alt, oder Sie haben keine? Ich empfehle Vecom Design — vom ersten Gespräch bis die Seite online ist. {link} #Werbung", 'en' => "Old website or none at all? I recommend Vecom Design — from the first chat to your site going live. {link} #ad"],
        'w_post3'    => ['it' => "Per chi ha un negozio, un ristorante o un laboratorio in Sicilia: siti web fatti bene, con assistenza. Dai un’occhiata: {link} #pubblicità", 'de' => "Für alle mit Laden, Restaurant oder Werkstatt: Websites, die gut gemacht sind, mit Betreuung. Schau mal: {link} #Werbung", 'en' => "For anyone with a shop, restaurant or workshop: well-made websites, with ongoing care. Take a look: {link} #ad"],
        'w_hinweis'  => ['it' => 'Se pubblica sui social, indichi che è un link con provvigione (per es. #pubblicità).', 'de' => 'Wenn Sie in sozialen Netzen posten, kennzeichnen Sie den Link als Werbung (z. B. #Werbung).', 'en' => 'When posting on social media, mark the link as an ad (e.g. #ad).'],
        'w_bild'     => ['it' => 'Scarica immagine per storie', 'de' => 'Bild für Stories laden', 'en' => 'Download story image'],
        'w_karte'    => ['it' => 'Stampa cartolina con QR', 'de' => 'Karte mit QR drucken', 'en' => 'Print card with QR'],
        'w_qr'       => ['it' => 'Scarica codice QR', 'de' => 'QR-Code laden', 'en' => 'Download QR code'],
        'karte_titel'=> ['it' => 'Il tuo sito web, fatto bene.', 'de' => 'Ihre Website, gut gemacht.', 'en' => 'Your website, done right.'],
        'karte_text' => ['it' => 'Inquadra il codice — consigliato da {name}.', 'de' => 'Code scannen — empfohlen von {name}.', 'en' => 'Scan the code — recommended by {name}.'],
        'karte_titel_kurz' => ['it' => 'Biglietto', 'de' => 'Karte', 'en' => 'Card'],
        'karte_druck'=> ['it' => 'Stampa (o «Salva come PDF»)', 'de' => 'Drucken (oder „Als PDF speichern“)', 'en' => 'Print (or “Save as PDF”)'],
        'k_titel'    => ['it' => 'Link per canale', 'de' => 'Links je Kanal', 'en' => 'Links per channel'],
        'k_text'     => ['it' => 'Stesso link, con il canale in fondo — così vede dove funziona meglio.', 'de' => 'Derselbe Link mit dem Kanal hinten dran — so sehen Sie, wo es am besten wirkt.', 'en' => 'The same link with the channel at the end — so you see where it works best.'],
        'k_kanal'    => ['it' => 'Canale', 'de' => 'Kanal', 'en' => 'Channel'],
        'jahr_titel' => ['it' => 'Riepilogo provvigioni', 'de' => 'Jahresübersicht Provisionen', 'en' => 'Annual commission summary'],
        'jahr_link'  => ['it' => 'Riepilogo {jahr} (PDF)', 'de' => 'Jahresübersicht {jahr} (PDF)', 'en' => '{jahr} summary (PDF)'],
        'sofort'     => ['it' => 'Avvisami per e-mail quando arriva un contatto o guadagno una provvigione', 'de' => 'Mich per E-Mail benachrichtigen, wenn ein Kontakt kommt oder ich Provision verdiene', 'en' => 'Email me when a contact arrives or I earn commission'],
        's_unterwegs'=> ['it' => 'in pagamento', 'de' => 'unterwegs', 'en' => 'on its way'],
        'wege'       => ['it' => 'Come ricevere i pagamenti', 'de' => 'Wie Sie Ihr Geld bekommen', 'en' => 'How you get paid'],
        'w_stripe'   => ['it' => 'Stripe (automatico)', 'de' => 'Stripe (automatisch)', 'en' => 'Stripe (automatic)'],
        'w_sepa'     => ['it' => 'Bonifico SEPA', 'de' => 'SEPA-Überweisung', 'en' => 'SEPA bank transfer'],
        'w_paypal'   => ['it' => 'PayPal (automatico)', 'de' => 'PayPal (automatisch)', 'en' => 'PayPal (automatic)'],
        'w_wise'     => ['it' => 'Wise', 'de' => 'Wise', 'en' => 'Wise'],
        'w_gutschrift' => ['it' => 'Compensazione con le mie fatture', 'de' => 'Verrechnung mit meinen Rechnungen', 'en' => 'Offset against my invoices'],
        'w_hand'     => ['it' => 'Bonifico', 'de' => 'Überweisung', 'en' => 'Bank transfer'],
        'wd_stripe'  => ['it' => 'Stripe verifica una volta identità e IBAN; poi i pagamenti arrivano da soli.', 'de' => 'Stripe prüft einmal Identität und IBAN; danach kommt das Geld von selbst.', 'en' => 'Stripe verifies your identity and IBAN once; after that payments arrive by themselves.'],
        'wd_sepa'    => ['it' => 'Bonifico sul suo conto. L’IBAN viene conservato cifrato.', 'de' => 'Überweisung auf Ihr Konto. Die IBAN wird verschlüsselt gespeichert.', 'en' => 'Transfer to your bank account. Your IBAN is stored encrypted.'],
        'wd_paypal'  => ['it' => 'Pagamento sul suo conto PayPal (indirizzo e-mail PayPal).', 'de' => 'Zahlung auf Ihr PayPal-Konto (Ihre PayPal-E-Mail).', 'en' => 'Payment to your PayPal account (your PayPal email).'],
        'wd_wise'    => ['it' => 'Bonifico tramite Wise sul suo IBAN.', 'de' => 'Überweisung über Wise auf Ihre IBAN.', 'en' => 'Transfer via Wise to your IBAN.'],
        'wd_gutschrift' => ['it' => 'La provvigione viene detratta dalla sua prossima fattura da Vecom Design.', 'de' => 'Die Provision wird mit Ihrer nächsten Rechnung von Vecom Design verrechnet.', 'en' => 'The commission is deducted from your next Vecom Design invoice.'],
        'iban'       => ['it' => 'IBAN', 'de' => 'IBAN', 'en' => 'IBAN'],
        'iban_da'    => ['it' => 'salvato, termina con', 'de' => 'gespeichert, endet auf', 'en' => 'saved, ending in'],
        'inhaber'    => ['it' => 'Intestatario del conto', 'de' => 'Kontoinhaber', 'en' => 'Account holder'],
        'paypal_email' => ['it' => 'E-mail PayPal', 'de' => 'PayPal-E-Mail', 'en' => 'PayPal email'],
        'w_speichern'=> ['it' => 'Salva', 'de' => 'Speichern', 'en' => 'Save'],
        'w_gut'      => ['it' => 'Salvato.', 'de' => 'Gespeichert.', 'en' => 'Saved.'],
        /* Nachrichten, Geld bereit, Empfehlungen, App (26.09.2026) */
        'nachr_titel' => ['it' => 'Scriverci', 'de' => 'Uns schreiben', 'en' => 'Write to us'],
        'nachr_text'  => ['it' => 'Domande su un cliente, sul pagamento o un’idea? Scriva qui — risponde Uwe, di solito in giornata.', 'de' => 'Frage zu einem Kunden, zur Auszahlung oder eine Idee? Schreiben Sie hier — Uwe antwortet, meist am selben Tag.', 'en' => 'A question about a customer, a payout or an idea? Write here — Uwe replies, usually the same day.'],
        'nachr_feld'  => ['it' => 'Il suo messaggio', 'de' => 'Ihre Nachricht', 'en' => 'Your message'],
        'nachr_knopf' => ['it' => 'Invia messaggio', 'de' => 'Nachricht senden', 'en' => 'Send message'],
        'nachr_danke' => ['it' => 'Messaggio inviato. La avvisiamo appena c’è una risposta.', 'de' => 'Nachricht gesendet. Sie bekommen Bescheid, sobald eine Antwort da ist.', 'en' => 'Message sent. We’ll let you know as soon as there is a reply.'],
        'nachr_leer'  => ['it' => 'Il messaggio è vuoto.', 'de' => 'Die Nachricht ist leer.', 'en' => 'The message is empty.'],
        'nachr_sie'   => ['it' => 'Lei', 'de' => 'Sie', 'en' => 'You'],
        'nachr_wir'   => ['it' => 'Vecom Design', 'de' => 'Vecom Design', 'en' => 'Vecom Design'],
        'nachr_zuviel'=> ['it' => 'Troppi messaggi in poco tempo — riprovi tra qualche minuto.', 'de' => 'Zu viele Nachrichten in kurzer Zeit — bitte in ein paar Minuten noch einmal.', 'en' => 'Too many messages in a short time — please try again in a few minutes.'],
        'geld_bereit' => ['it' => '{betrag} sono pronti per lei — manca solo come vuole riceverli.', 'de' => '{betrag} liegen für Sie bereit — es fehlt nur noch, wie Sie sie bekommen möchten.', 'en' => '{betrag} is ready for you — we just need to know how you want to receive it.'],
        'geld_knopf'  => ['it' => 'Scegliere il metodo', 'de' => 'Weg wählen', 'en' => 'Choose method'],
        'emp_titel'   => ['it' => 'Le sue segnalazioni', 'de' => 'Ihre Empfehlungen', 'en' => 'Your referrals'],
        'emp_text'    => ['it' => 'Una riga per ogni cliente arrivato da lei — senza nomi, solo a che punto è.', 'de' => 'Eine Zeile je Kunde, der über Sie kam — ohne Namen, nur wo er steht.', 'en' => 'One row per customer who came through you — no names, just where things stand.'],
        'emp_nr'      => ['it' => 'Segnalazione {n}', 'de' => 'Empfehlung {n}', 'en' => 'Referral {n}'],
        'emp_seit'    => ['it' => 'dal {datum}', 'de' => 'seit {datum}', 'en' => 'since {datum}'],
        'emp_s_zugeordnet' => ['it' => 'Registrato', 'de' => 'Angekommen', 'en' => 'Registered'],
        'emp_s_anfrage'    => ['it' => 'Richiesta', 'de' => 'Anfrage', 'en' => 'Enquiry'],
        'emp_s_angebot'    => ['it' => 'Preventivo', 'de' => 'Angebot', 'en' => 'Quote'],
        'emp_s_bezahlt'    => ['it' => 'Pagato', 'de' => 'Bezahlt', 'en' => 'Paid'],
        'emp_s_online'     => ['it' => 'Online', 'de' => 'Online', 'en' => 'Live'],
        'emp_prov'    => ['it' => 'Provvigione: {betrag}', 'de' => 'Provision: {betrag}', 'en' => 'Commission: {betrag}'],
        'emp_frei'    => ['it' => 'pagabile dal {datum}', 'de' => 'auszahlbar ab {datum}', 'en' => 'payable from {datum}'],
        'app_titel'   => ['it' => 'Sul telefono', 'de' => 'Aufs Handy', 'en' => 'On your phone'],
        'app_text'    => ['it' => 'Metta questa pagina sulla schermata iniziale e riceva un avviso a ogni nuova provvigione o risposta.', 'de' => 'Legen Sie diese Seite auf den Startbildschirm und bekommen Sie bei jeder neuen Provision oder Antwort einen Hinweis.', 'en' => 'Put this page on your home screen and get a notification for every new commission or reply.'],
        'app_an'      => ['it' => 'Attivare gli avvisi', 'de' => 'Hinweise einschalten', 'en' => 'Turn on notifications'],
        'app_ist_an'  => ['it' => 'Avvisi attivi su questo dispositivo.', 'de' => 'Hinweise auf diesem Gerät an.', 'en' => 'Notifications on for this device.'],
        'app_nein'    => ['it' => 'Questo browser non supporta gli avvisi. Su iPhone: prima «Aggiungi alla schermata Home», poi apra la pagina da lì.', 'de' => 'Dieser Browser kann keine Hinweise. Auf dem iPhone: erst „Zum Home-Bildschirm“, dann die Seite von dort öffnen.', 'en' => 'This browser can’t show notifications. On iPhone: first “Add to Home Screen”, then open the page from there.'],
        'app_verboten'=> ['it' => 'Gli avvisi sono bloccati nelle impostazioni del browser.', 'de' => 'Hinweise sind in den Browser-Einstellungen gesperrt.', 'en' => 'Notifications are blocked in the browser settings.'],
        'app_ios'     => ['it' => 'iPhone: tocchi in Safari il simbolo Condividi (quadrato con freccia) → «Aggiungi alla schermata Home» → «Aggiungi». Poi apra la pagina dall’icona e attivi gli avvisi.', 'de' => 'iPhone: In Safari auf das Teilen-Symbol (Quadrat mit Pfeil) → „Zum Home-Bildschirm“ → „Hinzufügen“. Danach die Seite über das neue Symbol öffnen und hier die Hinweise einschalten.', 'en' => 'iPhone: in Safari tap the Share icon (square with arrow) → “Add to Home Screen” → “Add”. Then open the page from the new icon and turn on notifications here.'],
        'app_android' => ['it' => 'Android: se il pulsante non compare, apra il menu ⋮ del browser → «Installa app» (o «Aggiungi a schermata Home»). Su alcuni telefoni l’icona finisce nell’elenco delle app.', 'de' => 'Android: Erscheint kein Knopf, im Browser-Menü ⋮ → „App installieren“ (oder „Zum Startbildschirm hinzufügen“). Auf manchen Handys landet das Symbol in der App-Übersicht statt auf dem Startbildschirm.', 'en' => 'Android: if no button appears, open the browser menu ⋮ → “Install app” (or “Add to Home screen”). On some phones the icon lands in the app drawer rather than the home screen.'],
        'app_samsung' => ['it' => 'Samsung Internet: tocchi l’icona di installazione nella barra degli indirizzi, se c’è. Altrimenti ≡ in basso a destra → «Aggiungi pagina a» → «Schermata Home».', 'de' => 'Samsung Internet: Steht in der Adresszeile ein Installieren-Symbol, darauf tippen. Sonst unten rechts ≡ → „Seite hinzufügen zu“ → „Startbildschirm“.', 'en' => 'Samsung Internet: if there is an install icon in the address bar, tap it. Otherwise ≡ at the bottom right → “Add page to” → “Home screen”.'],
        'app_firefox' => ['it' => 'Firefox: menu ⋮ → «Installa» (o «Aggiungi a schermata Home») → confermi. L’icona appare sulla schermata Home.', 'de' => 'Firefox: Menü ⋮ → „Installieren“ (oder „Zum Startbildschirm hinzufügen“) → bestätigen. Das Symbol erscheint auf dem Startbildschirm.', 'en' => 'Firefox: menu ⋮ → “Install” (or “Add to Home screen”) → confirm. The icon appears on your home screen.'],
        'app_andere'  => ['it' => 'Questo browser crea solo un segnalibro, non un’app. Apra la pagina in Chrome (preinstallato su Android) e poi menu ⋮ → «Installa app».', 'de' => 'Dieser Browser legt nur ein Lesezeichen an, keine App. Öffnen Sie die Seite in Chrome (auf Android vorinstalliert) und dann Menü ⋮ → „App installieren“.', 'en' => 'This browser only creates a bookmark, not an app. Open the page in Chrome (pre-installed on Android), then menu ⋮ → “Install app”.'],
        'app_chrome'  => ['it' => 'Apri in Chrome →', 'de' => 'In Chrome öffnen →', 'en' => 'Open in Chrome →'],
        'pk_titel'    => ['it' => 'Pacchetto promozionale', 'de' => 'Werbe-Paket', 'en' => 'Marketing kit'],
        'pk_text'     => ['it' => 'Scelga dove vuole condividere. Ogni testo contiene già il suo link per quel canale — più avanti vede cosa funziona.', 'de' => 'Wählen Sie, wo Sie teilen möchten. Jeder Text enthält schon Ihren Link für diesen Kanal — später sehen Sie, was wirkt.', 'en' => 'Choose where you want to share. Every text already contains your link for that channel — later you’ll see what works.'],
        'pk_teilen'   => ['it' => 'Condividi', 'de' => 'Teilen', 'en' => 'Share'],
        'pk_senden'   => ['it' => 'Invia', 'de' => 'Senden', 'en' => 'Send'],
        'pk_link'     => ['it' => 'Il suo link per {kanal}', 'de' => 'Ihr Link für {kanal}', 'en' => 'Your link for {kanal}'],
        'pk_betreff'  => ['it' => 'Oggetto', 'de' => 'Betreff', 'en' => 'Subject'],
        'pk_werkzeuge'=> ['it' => 'Firma e-mail e pulsante', 'de' => 'Signatur und Website-Knopf', 'en' => 'Signature and website button'],
        'sig_titel'   => ['it' => 'Firma e-mail', 'de' => 'E-Mail-Signatur', 'en' => 'Email signature'],
        'sig_text'    => ['it' => 'Copi e incolli. Gmail: Impostazioni → Firma. Outlook: File → Opzioni → Posta → Firme.', 'de' => 'Kopieren und einfügen. Gmail: Einstellungen → Signatur. Outlook: Datei → Optionen → E-Mail → Signaturen.', 'en' => 'Copy and paste. Gmail: Settings → Signature. Outlook: File → Options → Mail → Signatures.'],
        'sig_kopieren'=> ['it' => 'Copia firma', 'de' => 'Signatur kopieren', 'en' => 'Copy signature'],
        'web_titel'   => ['it' => 'Pulsante per il suo sito', 'de' => 'Knopf für Ihre Website', 'en' => 'Button for your website'],
        'web_text'    => ['it' => 'Incolli questo codice dove vuole il pulsante (per es. in fondo alla pagina). Non carica nulla da noi.', 'de' => 'Diesen Code dort einfügen, wo der Knopf erscheinen soll (z. B. im Fußbereich). Er lädt nichts von uns nach.', 'en' => 'Paste this code where the button should appear (e.g. in the footer). It loads nothing from us.'],
        'code_kopieren'=> ['it' => 'Copia codice', 'de' => 'Code kopieren', 'en' => 'Copy code'],
        'aw_titel'    => ['it' => 'Cosa funziona', 'de' => 'Was wirkt', 'en' => 'What works'],
        'aw_leer'     => ['it' => 'Ancora nessun clic. Appena qualcuno apre uno dei suoi link, lo vede qui.', 'de' => 'Noch keine Klicks. Sobald jemand einen Ihrer Links öffnet, steht es hier.', 'en' => 'No clicks yet. As soon as someone opens one of your links, it shows here.'],
        'aw_kanal'    => ['it' => 'Canale', 'de' => 'Kanal', 'en' => 'Channel'],
        'aw_klicks'   => ['it' => 'Clic', 'de' => 'Klicks', 'en' => 'Clicks'],
        'aw_kunden'   => ['it' => 'Clienti', 'de' => 'Kunden', 'en' => 'Customers'],
        'aw_verkaeufe'=> ['it' => 'Vendite', 'de' => 'Verkäufe', 'en' => 'Sales'],
        'aw_bester'   => ['it' => 'Il suo canale migliore finora: {kanal}. Lì conviene insistere.', 'de' => 'Ihr bester Kanal bisher: {kanal}. Dort lohnt sich mehr.', 'en' => 'Your best channel so far: {kanal}. It’s worth doing more there.'],
        'pf_titel'    => ['it' => 'La sua pagina di consiglio', 'de' => 'Ihre Empfehlungsseite', 'en' => 'Your recommendation page'],
        'pf_text'     => ['it' => 'Chi apre il suo link vede prima questa pagina. Con la sua foto e una sua frase il consiglio è più personale — e porta più richieste.', 'de' => 'Wer Ihrem Link folgt, sieht zuerst diese Seite. Mit Ihrem Foto und einem Satz von Ihnen wirkt die Empfehlung persönlicher — und bringt mehr Anfragen.', 'en' => 'Anyone who follows your link sees this page first. With your photo and a sentence from you the recommendation feels personal — and brings more enquiries.'],
        'pf_foto'     => ['it' => 'Foto (viene ritagliata quadrata da sola)', 'de' => 'Foto (wird von selbst quadratisch zugeschnitten)', 'en' => 'Photo (cropped square automatically)'],
        'pf_satz'     => ['it' => 'La sua frase (max. 200 caratteri, senza link)', 'de' => 'Ihr Satz (höchstens 200 Zeichen, ohne Links)', 'en' => 'Your sentence (max. 200 characters, no links)'],
        'pf_satz_ph'  => ['it' => 'Perché consiglia Vecom Design?', 'de' => 'Warum empfehlen Sie Vecom Design?', 'en' => 'Why do you recommend Vecom Design?'],
        'pf_speichern'=> ['it' => 'Salva', 'de' => 'Speichern', 'en' => 'Save'],
        'pf_foto_weg' => ['it' => 'Rimuovi foto', 'de' => 'Foto entfernen', 'en' => 'Remove photo'],
        'pf_vorschau' => ['it' => 'Vedi la mia pagina →', 'de' => 'Meine Seite ansehen →', 'en' => 'View my page →'],
        'pf_gut'      => ['it' => 'Salvato.', 'de' => 'Gespeichert.', 'en' => 'Saved.'],
        'satz_link'   => ['it' => 'Per favore senza indirizzi o link.', 'de' => 'Bitte ohne Adressen oder Links.', 'en' => 'Please no addresses or links.'],
        'satz_lang'   => ['it' => 'Al massimo 200 caratteri.', 'de' => 'Höchstens 200 Zeichen.', 'en' => '200 characters at most.'],
        'foto_gross'  => ['it' => 'La foto è troppo grande (max. 8 MB).', 'de' => 'Das Foto ist zu groß (höchstens 8 MB).', 'en' => 'The photo is too large (max. 8 MB).'],
        'foto_art'    => ['it' => 'Per favore una foto JPG, PNG o WebP.', 'de' => 'Bitte ein Foto als JPG, PNG oder WebP.', 'en' => 'Please use a JPG, PNG or WebP photo.'],
        'md_titel'    => ['it' => 'Immagini, stampa e video', 'de' => 'Bilder, Druck und Video', 'en' => 'Images, print and video'],
        'md_text'     => ['it' => 'Tutto con il suo codice QR e il suo link — chi inquadra o tocca arriva da lei.', 'de' => 'Alles mit Ihrem QR-Code und Ihrem Link — wer scannt oder tippt, landet bei Ihnen.', 'en' => 'Everything carries your QR code and link — whoever scans or taps lands with you.'],
        'bild_titel'  => ['it' => 'Immagini per i social', 'de' => 'Bilder für soziale Netze', 'en' => 'Images for social media'],
        'bild_motiv'  => ['it' => 'Tema', 'de' => 'Motiv', 'en' => 'Theme'],
        'bild_format' => ['it' => 'Formato', 'de' => 'Format', 'en' => 'Format'],
        'bf_quadrat'  => ['it' => 'Post 1:1', 'de' => 'Beitrag 1:1', 'en' => 'Post 1:1'],
        'bf_hoch'     => ['it' => 'Post 4:5', 'de' => 'Beitrag 4:5', 'en' => 'Post 4:5'],
        'bf_story'    => ['it' => 'Storia / Reel', 'de' => 'Story / Reel', 'en' => 'Story / Reel'],
        'bf_quer'     => ['it' => 'Facebook / LinkedIn', 'de' => 'Facebook / LinkedIn', 'en' => 'Facebook / LinkedIn'],
        'bf_banner'   => ['it' => 'Banner e-mail', 'de' => 'E-Mail-Banner', 'en' => 'Email banner'],
        'bf_qr'       => ['it' => 'Solo QR', 'de' => 'Nur QR-Code', 'en' => 'QR code only'],
        'bf_qrfoto'   => ['it' => 'QR con foto', 'de' => 'QR mit Foto', 'en' => 'QR with photo'],
        'bild_laden'  => ['it' => 'Scarica immagine', 'de' => 'Bild laden', 'en' => 'Download image'],
        'bild_teilen' => ['it' => 'Condividi immagine', 'de' => 'Bild teilen', 'en' => 'Share image'],
        'video_titel' => ['it' => 'Video breve per Reel e TikTok', 'de' => 'Kurzvideo für Reels und TikTok', 'en' => 'Short video for Reels and TikTok'],
        'video_text'  => ['it' => '10 secondi, verticale, con il suo codice QR alla fine — con il tema scelto sopra. La musica la aggiunge in Instagram o TikTok. Tenga aperta la pagina durante la creazione.', 'de' => '10 Sekunden, Hochformat, am Ende Ihr QR-Code — mit dem Motiv von oben. Musik fügen Sie in Instagram oder TikTok hinzu. Seite während der Aufnahme offen lassen.', 'en' => '10 seconds, vertical, with your QR code at the end — using the theme above. Add music in Instagram or TikTok. Keep the page open while it records.'],
        'video_erzeugen' => ['it' => 'Crea video', 'de' => 'Video erzeugen', 'en' => 'Create video'],
        'video_laeuft'=> ['it' => 'Registrazione … ancora {s} s', 'de' => 'Wird aufgenommen … noch {s} s', 'en' => 'Recording … {s} s left'],
        'video_fertig'=> ['it' => 'Pronto.', 'de' => 'Fertig.', 'en' => 'Done.'],
        'video_laden' => ['it' => 'Scarica video', 'de' => 'Video laden', 'en' => 'Download video'],
        'video_teilen'=> ['it' => 'Condividi video', 'de' => 'Video teilen', 'en' => 'Share video'],
        'video_nein'  => ['it' => 'Questo browser non sa creare video. Usi Chrome o Safari.', 'de' => 'Dieser Browser kann keine Videos erzeugen. Bitte Chrome oder Safari verwenden.', 'en' => 'This browser can’t create videos. Please use Chrome or Safari.'],
        'video_webm'  => ['it' => 'Pronto — ma in formato WebM, che Instagram non accetta sempre. Meglio crearlo in Chrome o Safari (MP4).', 'de' => 'Fertig — aber als WebM, das Instagram nicht immer annimmt. Besser in Chrome oder Safari erzeugen (MP4).', 'en' => 'Done — but as WebM, which Instagram doesn’t always accept. Better to create it in Chrome or Safari (MP4).'],
        'druck_titel' => ['it' => 'Da stampare', 'de' => 'Zum Ausdrucken', 'en' => 'To print'],
        'druck_text'  => ['it' => 'Si apre una pagina pronta per la stampa (anche in tipografia: «Salva come PDF»).', 'de' => 'Öffnet eine druckfertige Seite (auch für die Druckerei: „Als PDF speichern“).', 'en' => 'Opens a print-ready page (for a print shop too: “Save as PDF”).'],
        'dr_visitenkarten' => ['it' => '10 biglietti da visita (A4)', 'de' => '10 Visitenkarten (A4)', 'en' => '10 business cards (A4)'],
        'dr_flyer'    => ['it' => 'Volantino A5', 'de' => 'Flyer A5', 'en' => 'Flyer A5'],
        'dr_aufsteller' => ['it' => 'Segnaposto da banco (A4, da piegare)', 'de' => 'Tischaufsteller (A4, zum Falten)', 'en' => 'Table tent (A4, to fold)'],
        'dr_aufkleber'=> ['it' => '12 adesivi (A4)', 'de' => '12 Aufkleber (A4)', 'en' => '12 stickers (A4)'],
        'dr_karte'    => ['it' => 'Cartolina A6', 'de' => 'Karte A6', 'en' => 'Card A6'],
        'vk_titel'    => ['it' => 'Biglietti da visita in quattro stili', 'de' => 'Visitenkarten in vier Stilen', 'en' => 'Business cards in four styles'],
        'vk_text'     => ['it' => 'Fronte con il marchio, retro con il suo nome ({name}), il suo link ({link}) e il suo codice QR: chi lo scansiona resta abbinato a lei.', 'de' => 'Vorderseite mit der Marke, Rückseite mit Ihrem Namen ({name}), Ihrem Link ({link}) und Ihrem QR-Code: Wer scannt, bleibt Ihnen zugeordnet.', 'en' => 'Front with the brand, back with your name ({name}), your link ({link}) and your QR code: whoever scans it stays assigned to you.'],
        'vk_kontakt'  => ['it' => 'Contatto sul biglietto', 'de' => 'Kontakt auf der Karte', 'en' => 'Contact on the card'],
        'vk_sprache'  => ['it' => 'Lingua del biglietto', 'de' => 'Sprache der Karte', 'en' => 'Card language'],
        'vk_alt'      => ['it' => 'Biglietto da visita {name}, fronte e retro', 'de' => 'Visitenkarte {name}, Vorder- und Rückseite', 'en' => '{name} business card, front and back'],
        'vk_pdf'      => ['it' => 'PDF per la tipografia', 'de' => 'PDF für die Druckerei', 'en' => 'PDF for the print shop'],
        'vk_bogen'    => ['it' => '10 su A4 (stampa a casa)', 'de' => '10 auf A4 (selbst drucken)', 'en' => '10 on A4 (print at home)'],
        'vk_vorn'     => ['it' => 'Fronte (immagine)', 'de' => 'Vorderseite (Bild)', 'en' => 'Front (image)'],
        'vk_hinten'   => ['it' => 'Retro (immagine)', 'de' => 'Rückseite (Bild)', 'en' => 'Back (image)'],
        'vk_hinweis'  => ['it' => 'Formato 85 × 55 mm. Il PDF per la tipografia ha 3 mm di abbondanza e il codice QR vettoriale; il foglio A4 ha i segni di taglio e il retro già speculare per la stampa fronte-retro (lato lungo). Carta consigliata: 350 g opaca; per il bianco e oro anche con lamina oro.', 'de' => 'Format 85 × 55 mm. Das Druckerei-PDF hat 3 mm Beschnitt und den QR-Code als Vektor; der A4-Bogen hat Schnittmarken und die Rückseite schon gespiegelt für beidseitigen Druck (lange Kante). Empfohlen: 350 g matt; Weiß-Gold gern mit Goldfolie.', 'en' => 'Size 85 × 55 mm. The print-shop PDF has 3 mm bleed and a vector QR code; the A4 sheet has crop marks and the back already mirrored for double-sided printing (long edge). Recommended: 350 g matte; white & gold ideally with gold foil.'],
        'ap_titel'     => ['it' => 'Oggi per lei: {n} attività a {ort}', 'de' => 'Heute für Sie: {n} Betriebe in {ort}', 'en' => 'Today for you: {n} businesses in {ort}'],
        'ap_titel_leer'=> ['it' => 'Ogni mattina 5 attività da visitare', 'de' => 'Jeden Morgen 5 Betriebe zum Vorbeigehen', 'en' => 'Every morning 5 businesses to visit'],
        'ap_text'      => ['it' => 'Scelte per lei ogni mattina: prima chi non ha un sito, poi chi ha i problemi più grandi. Porti il volantino con il suo codice QR: chi lo scansiona vede la sua analisi, dice sì e riceve la sua area personale — e resta abbinato a lei. Solo visite di persona, nessuna email.', 'de' => 'Jeden Morgen für Sie ausgesucht: zuerst Betriebe ohne Website, dann die mit den größten Schwächen. Nehmen Sie den Flyer mit Ihrem QR-Code mit: Wer scannt, sieht seine Analyse, sagt Ja und bekommt sein Dashboard — und bleibt Ihnen zugeordnet. Nur persönliche Besuche, keine E-Mails.', 'en' => 'Picked for you every morning: businesses without a website first, then those with the biggest weaknesses. Bring the flyer with your QR code: whoever scans it sees their analysis, says yes and gets their dashboard — and stays assigned to you. In-person visits only, no emails.'],
        'ap_ort_frage' => ['it' => 'In quale città o zona gira?', 'de' => 'In welchem Ort sind Sie unterwegs?', 'en' => 'Which town are you working in?'],
        'ap_ort_ph'    => ['it' => 'es. Agrigento', 'de' => 'z. B. Agrigento', 'en' => 'e.g. Agrigento'],
        'ap_ort_knopf' => ['it' => 'Salva', 'de' => 'Speichern', 'en' => 'Save'],
        'ap_ort_aendern' => ['it' => 'Zona: {ort} — cambiare', 'de' => 'Ort: {ort} — ändern', 'en' => 'Town: {ort} — change'],
        'ap_leer'      => ['it' => 'Per oggi in questa zona non ci sono attività libere. Provi un’altra città o la ricerca qui sotto.', 'de' => 'Heute gibt es in diesem Ort keine freien Betriebe. Versuchen Sie einen anderen Ort oder die Suche weiter unten.', 'en' => 'No free businesses in this town today. Try another town or the search below.'],
        'ap_ohne_web'  => ['it' => 'senza sito', 'de' => 'ohne Website', 'en' => 'no website'],
        'ap_reserviert'=> ['it' => 'Riservata a lei', 'de' => 'Für Sie reserviert', 'en' => 'Reserved for you'],
        'ap_flyer'     => ['it' => 'Volantino: {name}', 'de' => 'Flyer: {name}', 'en' => 'Flyer: {name}'],
        'ap_route'     => ['it' => 'Percorso per tutte e 5', 'de' => 'Route für alle 5', 'en' => 'Route for all 5'],
        'ap_leitfaden' => ['it' => 'Cosa dire', 'de' => 'Was sagen?', 'en' => 'What to say'],
        'ap_push_titel'=> ['it' => 'Le attività di oggi', 'de' => 'Ihre Betriebe für heute', 'en' => 'Your businesses for today'],
        'ap_push_text' => ['it' => '{n} attività a {ort}, con volantino e percorso.', 'de' => '{n} Betriebe in {ort}, mit Flyer und Route.', 'en' => '{n} businesses in {ort}, with flyer and route.'],
        /* Anrufliste (29.09.2026, T2) */
        'al_titel' => ['it' => 'Da chiamare, da Vecom ({n})', 'de' => 'Anrufliste von Vecom ({n})', 'en' => 'Call list from Vecom ({n})'],
        /* Einklappbare Listen (03.10.2026, Uwe: „die Anruflisten einklappbar, dass man nicht ewig nach unten scrollen muss“) */
        'kl_details' => ['it' => 'Scheda e guida alla chiamata', 'de' => 'Steckbrief und Gesprächshilfe', 'en' => 'Profile and call guide'],
        'kl_weitere' => ['it' => 'Mostra altri {n}', 'de' => 'Weitere {n} zeigen', 'en' => 'Show {n} more'],
        'al_text' => ['it' => 'Queste attività le ha scelte Vecom per lei. Chiami, dica il testo e poi segni cosa ha risposto. Se accetta e più avanti compra, il cliente è suo — con il {satz} di provvigione.', 'de' => 'Diese Betriebe hat Vecom für Sie ausgesucht. Anrufen, den Text sagen, danach eintragen, was er geantwortet hat. Stimmt er zu und kauft später, gehört der Kunde Ihnen — mit {satz} Provision.', 'en' => 'Vecom picked these businesses for you. Call, say the text, then note the answer. If they agree and buy later, the customer is yours — with {satz} commission.'],
        'al_regel' => ['it' => 'Chiami solo in orario di lavoro. Chi dice «no» o «non chiamatemi più»: tocchi «Nessun interesse» — poi nessuno lo contatta più.', 'de' => 'Nur zu Geschäftszeiten anrufen. Wer „nein“ oder „bitte nicht mehr anrufen“ sagt: „Kein Interesse“ antippen — dann meldet sich niemand mehr.', 'en' => 'Only call during business hours. Anyone who says “no” or “don’t call again”: tap “Not interested” — then nobody contacts them again.'],
        'al_leer' => ['it' => 'Al momento nessuna chiamata aperta. Quando Vecom le passa delle attività, le trova qui.', 'de' => 'Gerade keine Anrufe offen. Sobald Vecom Ihnen Betriebe gibt, stehen sie hier.', 'en' => 'No open calls right now. As soon as Vecom gives you businesses, they appear here.'],
        'al_erledigt' => ['it' => 'Finora: {z} hanno accettato · {k} nessun interesse', 'de' => 'Bisher: {z} zugestimmt · {k} kein Interesse', 'en' => 'So far: {z} agreed · {k} not interested'],
        'al_anrufen' => ['it' => 'Chiama', 'de' => 'Anrufen', 'en' => 'Call'],
        'al_sagen' => ['it' => 'Cosa dire?', 'de' => 'Was sagen?', 'en' => 'What to say?'],
        'al_s_hallo' => ['it' => 'Saluto — chieda 30 secondi', 'de' => 'Begrüßen — um 30 Sekunden bitten', 'en' => 'Greeting — ask for 30 seconds'],
        'al_s_anlass' => ['it' => 'Motivo', 'de' => 'Anlass', 'en' => 'Reason'],
        'al_s_frage' => ['it' => 'Piccola richiesta (senza impegno)', 'de' => 'Kleine Bitte (unverbindlich)', 'en' => 'Small request (no obligation)'],
        'al_s_ja' => ['it' => 'Se dice sì: legga questa domanda', 'de' => 'Wenn ja: diese Frage vorlesen', 'en' => 'If yes: read out this question'],
        'al_s_nein' => ['it' => 'Se dice no', 'de' => 'Wenn nein', 'en' => 'If no'],
        'al_zugestimmt' => ['it' => 'Ha accettato', 'de' => 'Zugestimmt', 'en' => 'Agreed'],
        'al_kein' => ['it' => 'Nessun interesse', 'de' => 'Kein Interesse', 'en' => 'Not interested'],
        'al_nicht' => ['it' => 'Non raggiunto', 'de' => 'Nicht erreicht', 'en' => 'Not reached'],
        'al_versuche' => ['it' => '{n}× non raggiunto', 'de' => '{n}× nicht erreicht', 'en' => '{n}× not reached'],
        'al_person' => ['it' => 'Chi ha accettato?', 'de' => 'Wer hat zugestimmt?', 'en' => 'Who agreed?'],
        'al_person_ph' => ['it' => 'es. Maria Rossi, titolare', 'de' => 'z. B. Maria Rossi, Inhaberin', 'en' => 'e.g. Maria Rossi, owner'],
        'al_email' => ['it' => 'E-mail', 'de' => 'E-Mail-Adresse', 'en' => 'Email address'],
        'al_wa' => ['it' => 'Numero WhatsApp', 'de' => 'WhatsApp-Nummer', 'en' => 'WhatsApp number'],
        'al_eins_hinweis' => ['it' => 'L’e-mail serve sempre: così lo spazio personale parte subito in automatico.', 'de' => 'Die E-Mail braucht es immer: Nur so geht der persönliche Bereich sofort automatisch raus.', 'en' => 'The email is always needed: that is how the personal area goes out automatically right away.'],
        'al_haken' => ['it' => 'Ho letto la domanda e ha detto sì.', 'de' => 'Frage vorgelesen, und er hat Ja gesagt.', 'en' => 'I read out the question and they said yes.'],
        'al_speichern' => ['it' => 'Salva', 'de' => 'Speichern', 'en' => 'Save'],
        'al_danke' => ['it' => 'Salvato. Ora riceve in automatico il suo spazio personale via e-mail — ed è assegnato a lei.', 'de' => 'Gespeichert. Er bekommt jetzt automatisch seinen persönlichen Bereich per Mail — und ist Ihnen zugeordnet.', 'en' => 'Saved. They now automatically receive their personal area by email — and are assigned to you.'],
        'al_danke_wa' => ['it' => 'Salvato. Vecom gli scrive su WhatsApp — ed è assegnato a lei.', 'de' => 'Gespeichert. Vecom meldet sich per WhatsApp bei ihm — und er ist Ihnen zugeordnet.', 'en' => 'Saved. Vecom will contact them on WhatsApp — and they are assigned to you.'],
        'al_s_problem' => ['it' => 'Il problema, visto dai suoi clienti', 'de' => 'Das Problem aus Sicht seiner Kunden', 'en' => 'The problem from their customers’ view'],
        'al_s_loesung' => ['it' => 'La soluzione (senza prezzi)', 'de' => 'Die Lösung (ohne Preise)', 'en' => 'The solution (no prices)'],
        'al_s_email' => ['it' => 'Poi chieda l’e-mail e la scriva qui sotto', 'de' => 'Dann nach der E-Mail fragen und unten eintragen', 'en' => 'Then ask for the email and enter it below'],
        'al_raus' => ['it' => 'Tre volte non raggiunto: l’attività esce dalla lista.', 'de' => 'Dreimal nicht erreicht: Der Betrieb ist aus der Liste genommen.', 'en' => 'Not reached three times: the business has been removed from the list.'],
        'al_wv' => ['it' => 'Da richiamare: {n} (la prossima il {datum})', 'de' => 'Wiedervorlage: {n} (nächste am {datum})', 'en' => 'To call back: {n} (next on {datum})'],
        'al_wv_hinweis' => ['it' => '«Non raggiunto»: torna in lista fra 2–3 giorni. Dopo tre volte esce da sola.', 'de' => '„Nicht erreicht“: Der Betrieb kommt in 2–3 Tagen wieder. Nach dem dritten Mal fällt er von selbst heraus.', 'en' => '“Not reached”: the business comes back in 2–3 days. After the third time it drops off automatically.'],
        'al_rr_titel' => ['it' => 'Da richiamare oggi', 'de' => 'Heute zurückrufen', 'en' => 'Call back today'],
        'al_rr_text' => ['it' => 'Oggi {n} attività della sua lista da richiamare.', 'de' => 'Heute {n} Betriebe aus Ihrer Anrufliste wieder anrufen.', 'en' => 'Today {n} businesses from your call list to call again.'],
        'al_wa_zusatz' => ['it' => 'WhatsApp in più, se lo desidera', 'de' => 'WhatsApp zusätzlich, wenn er möchte', 'en' => 'WhatsApp as well, if they like'],
        'al_s_lob' => ['it' => 'Aggancio', 'de' => 'Aufhänger', 'en' => 'Hook'],
        'al_s_zahl' => ['it' => 'Un dato vero della sua zona', 'de' => 'Echte Zahl aus seinem Ort', 'en' => 'A real number from their town'],
        'al_s_einwaende' => ['it' => 'Se dice …', 'de' => 'Wenn er sagt …', 'en' => 'If they say …'],
        'al_ok' => ['it' => 'Salvato.', 'de' => 'Gespeichert.', 'en' => 'Saved.'],
        'al_weg' => ['it' => 'Questa attività non è più nella sua lista.', 'de' => 'Dieser Betrieb ist nicht mehr in Ihrer Liste.', 'en' => 'This business is no longer on your list.'],
        'al_mail' => ['it' => 'Inserisca un indirizzo e-mail valido.', 'de' => 'Bitte eine gültige E-Mail-Adresse eintragen.', 'en' => 'Please enter a valid email address.'],
        'al_wa_fehler' => ['it' => 'Il numero WhatsApp non è leggibile.', 'de' => 'Die WhatsApp-Nummer ist nicht lesbar.', 'en' => 'The WhatsApp number cannot be read.'],
        'al_eins' => ['it' => 'Inserisca l’e-mail o il numero WhatsApp.', 'de' => 'Bitte E-Mail oder WhatsApp-Nummer eintragen.', 'en' => 'Please enter the email or WhatsApp number.'],
        'al_person_fehler' => ['it' => 'Scriva chi ha accettato.', 'de' => 'Bitte eintragen, wer zugestimmt hat.', 'en' => 'Please enter who agreed.'],
        'al_haken_fehler' => ['it' => 'Confermi di aver letto la domanda e che ha detto sì.', 'de' => 'Bitte bestätigen, dass Sie die Frage vorgelesen haben und er Ja gesagt hat.', 'en' => 'Please confirm you read out the question and they said yes.'],
        'al_fehler' => ['it' => 'Non è andato a buon fine. Riprovi.', 'de' => 'Das hat nicht geklappt. Bitte noch einmal versuchen.', 'en' => 'That didn’t work. Please try again.'],
        'al_push_titel' => ['it' => 'Nuova lista da chiamare', 'de' => 'Neue Anrufliste', 'en' => 'New call list'],
        'al_push_text' => ['it' => 'Vecom le ha passato {n} attività da chiamare.', 'de' => 'Vecom hat Ihnen {n} Betriebe zum Anrufen gegeben.', 'en' => 'Vecom gave you {n} businesses to call.'],
        'fi_flyer'    => ['it' => 'Volantino adatto: {name}', 'de' => 'Passender Flyer: {name}', 'en' => 'Matching flyer: {name}'],
        'fl_titel'    => ['it' => 'Volantini pronti per settore', 'de' => 'Fertige Flyer nach Branche', 'en' => 'Ready-made flyers by industry'],
        'fl_text'     => ['it' => 'Ogni volantino porta già il suo codice QR e il suo link ({link}): chi scansiona arriva da noi e resta abbinato a lei. Scelga il settore del cliente oppure un volantino generale.', 'de' => 'Jeder Flyer trägt schon Ihren QR-Code und Ihren Link ({link}): Wer scannt, landet bei uns und bleibt Ihnen zugeordnet. Wählen Sie die Branche des Kunden oder einen allgemeinen Flyer.', 'en' => 'Every flyer already carries your QR code and your link ({link}): whoever scans it lands with us and stays assigned to you. Pick the customer’s industry or a general flyer.'],
        'fl_filter'   => ['it' => 'Filtra per settore', 'de' => 'Nach Branche filtern', 'en' => 'Filter by industry'],
        'fl_alle'     => ['it' => 'Tutti', 'de' => 'Alle', 'en' => 'All'],
        'fl_alt'      => ['it' => 'Volantino {name} con il suo codice QR', 'de' => 'Flyer {name} mit Ihrem QR-Code', 'en' => '{name} flyer with your QR code'],
        'fl_jpg'      => ['it' => 'Immagine', 'de' => 'Bild', 'en' => 'Image'],
        'fl_pdf'      => ['it' => 'PDF', 'de' => 'PDF', 'en' => 'PDF'],
        'fl_hinweis'  => ['it' => 'I volantini sono in tedesco. «Immagine» per WhatsApp e social; «PDF» per la tipografia (larghezza A5, codice QR nitido a ogni dimensione). Prima di stampare in grande quantità: scansioni una volta il codice con il telefono.', 'de' => 'Die Flyer sind auf Deutsch. „Bild“ für WhatsApp und soziale Netzwerke, „PDF“ für die Druckerei (A5-Breite, QR-Code in jeder Größe scharf). Vor einer großen Auflage: den Code einmal mit dem Handy scannen.', 'en' => 'The flyers are in German. “Image” for WhatsApp and social media, “PDF” for the print shop (A5 width, QR code sharp at any size). Before a large print run: scan the code once with your phone.'],
        'dr_hell'     => ['it' => 'Versione chiara (risparmia inchiostro)', 'de' => 'Helle Fassung (spart Tinte)', 'en' => 'Light version (saves ink)'],
        'dr_drucken'  => ['it' => 'Stampa', 'de' => 'Drucken', 'en' => 'Print'],
        'dr_falz'     => ['it' => 'piegare qui', 'de' => 'hier falten', 'en' => 'fold here'],
        're_titel'    => ['it' => 'Trovare clienti', 'de' => 'Kunden finden', 'en' => 'Find customers'],
        'fi_titel'    => ['it' => 'Attività nella sua zona', 'de' => 'Betriebe in Ihrer Nähe', 'en' => 'Businesses near you'],
        'fi_text'     => ['it' => 'Attività dalla nostra ricerca e da OpenStreetMap, prima quelle senza sito. Ne prenoti una e per 60 giorni è sua: nessun altro partner la vede, e noi non la contattiamo.', 'de' => 'Betriebe aus unserer Recherche und aus OpenStreetMap, die ohne Website zuerst. Reservieren Sie einen, gehört er 60 Tage Ihnen: Kein anderer Partner sieht ihn, und wir schreiben ihn nicht an.', 'en' => 'Businesses from our research and from OpenStreetMap, those without a website first. Reserve one and it’s yours for 60 days: no other partner sees it, and we won’t contact it.'],
        'fi_ort'      => ['it' => 'Città o CAP', 'de' => 'Ort oder PLZ', 'en' => 'Town or postcode'],
        'fi_branche'  => ['it' => 'Settore', 'de' => 'Branche', 'en' => 'Sector'],
        'fi_alle'     => ['it' => 'Tutti i settori', 'de' => 'Alle Branchen', 'en' => 'All sectors'],
        'fi_suchen'   => ['it' => 'Cerca', 'de' => 'Suchen', 'en' => 'Search'],
        'fi_keine'    => ['it' => 'Nessuna attività libera trovata qui. Provi un paese vicino o un altro settore.', 'de' => 'Hier keine freien Betriebe gefunden. Versuchen Sie einen Nachbarort oder eine andere Branche.', 'en' => 'No free businesses found here. Try a nearby town or another sector.'],
        'fi_genug'    => ['it' => 'Per oggi basta ricerche — domani di nuovo.', 'de' => 'Für heute genug gesucht — morgen wieder.', 'en' => 'Enough searches for today — again tomorrow.'],
        'fi_reserv'   => ['it' => 'Prenota', 'de' => 'Reservieren', 'en' => 'Reserve'],
        'fi_frei'     => ['it' => 'Libera', 'de' => 'Freigeben', 'en' => 'Release'],
        'fi_meine'    => ['it' => 'Le sue prenotazioni', 'de' => 'Ihre Reservierungen', 'en' => 'Your reservations'],
        'fi_bis'      => ['it' => 'sua fino al {datum}', 'de' => 'Ihrer bis {datum}', 'en' => 'yours until {datum}'],
        'fi_vecom'    => ['it' => 'Già contattata da noi', 'de' => 'Von uns schon angeschrieben', 'en' => 'Already contacted by us'],
        'fi_weg'      => ['it' => 'Nel frattempo non è più disponibile.', 'de' => 'Ist inzwischen nicht mehr frei.', 'en' => 'Is no longer available.'],
        'fi_voll'     => ['it' => 'Ha già 25 prenotazioni attive. Ne liberi una prima.', 'de' => 'Sie haben schon 25 Reservierungen. Geben Sie erst eine frei.', 'en' => 'You already have 25 reservations. Release one first.'],
        'fi_tag'      => ['it' => 'Oggi ha già prenotato 15 aziende. Domani può continuare.', 'de' => 'Sie haben heute schon 15 Betriebe reserviert. Morgen geht es weiter.', 'en' => 'You have already reserved 15 businesses today. You can continue tomorrow.'],
        /* Gesperrter Partnerbereich (30.09.2026, PartnerSchutz) */
        'sp_titel'    => ['it' => 'Nuovo accordo partner', 'de' => 'Neue Partnervereinbarung', 'en' => 'New partner agreement'],
        'sp_text'     => ['it' => 'Buongiorno {name}, la sua area partner è bloccata finché non accetta la nuova versione dell’accordo. Protegge i dati delle aziende, i materiali e il marchio di Vecom Design. La legga e confermi con le due spunte. Poi attiviamo la sua area.', 'de' => 'Guten Tag {name}, Ihr Partnerbereich ist gesperrt, bis Sie der neuen Fassung der Vereinbarung zustimmen. Sie schützt die Betriebsdaten, die Unterlagen und die Marke von Vecom Design. Bitte lesen und mit den beiden Haken bestätigen. Danach schalten wir Ihren Bereich frei.', 'en' => 'Hello {name}, your partner area is locked until you accept the new version of the agreement. It protects the business data, the materials and the brand of Vecom Design. Please read it and confirm with the two ticks. We will then activate your area.'],
        'sp_haken1'   => ['it' => 'Ho letto l’accordo partner e lo accetto.', 'de' => 'Ich habe die Partnervereinbarung gelesen und stimme ihr zu.', 'en' => 'I have read the partner agreement and accept it.'],
        'sp_knopf'    => ['it' => 'Accetto l’accordo', 'de' => 'Vereinbarung annehmen', 'en' => 'Accept the agreement'],
        'sp_klein'    => ['it' => 'Il suo link continua a funzionare: i clienti che arrivano ora le vengono comunque assegnati. Riceverà una copia del testo nella sua area.', 'de' => 'Ihr Link zählt weiter: Kunden, die jetzt kommen, werden Ihnen trotzdem zugeordnet. Den Wortlaut finden Sie danach in Ihrem Bereich.', 'en' => 'Your link keeps working: customers arriving now are still assigned to you. You will find the text in your area afterwards.'],
        'sp_wartet_titel' => ['it' => 'Grazie, abbiamo ricevuto la sua accettazione', 'de' => 'Danke, Ihre Zustimmung ist angekommen', 'en' => 'Thank you, we have received your acceptance'],
        'sp_wartet_text'  => ['it' => 'Vecom Design attiva la sua area a breve e la avvisa per e-mail. Il suo link continua a funzionare.', 'de' => 'Vecom Design schaltet Ihren Bereich in Kürze frei und gibt Ihnen per E-Mail Bescheid. Ihr Link zählt weiter.', 'en' => 'Vecom Design will activate your area shortly and let you know by email. Your link keeps working.'],
        'sp_gesperrt_titel' => ['it' => 'Area partner bloccata', 'de' => 'Partnerbereich gesperrt', 'en' => 'Partner area locked'],
        'sp_gesperrt_text'  => ['it' => 'La sua area partner è stata bloccata da Vecom Design. Per chiarimenti risponda all’ultima e-mail ricevuta da noi.', 'de' => 'Ihr Partnerbereich wurde von Vecom Design gesperrt. Für Rückfragen antworten Sie bitte auf unsere letzte E-Mail.', 'en' => 'Your partner area has been locked by Vecom Design. For questions, please reply to our last email.'],
        'sp_ihre'     => ['it' => 'Il suo accordo partner (versione {fassung}, accettato il {datum})', 'de' => 'Ihre Partnervereinbarung (Fassung {fassung}, zugestimmt am {datum})', 'en' => 'Your partner agreement (version {fassung}, accepted on {datum})'],
        'haken'       => ['it' => 'Per continuare servono entrambe le spunte.', 'de' => 'Zum Fortfahren braucht es beide Haken.', 'en' => 'Both ticks are needed to continue.'],
        'fi_chance_hoch'   => ['it' => 'Nessun sito / molto da fare', 'de' => 'Keine Website / viel zu tun', 'en' => 'No website / much to do'],
        'fi_chance_mittel' => ['it' => 'Sito migliorabile', 'de' => 'Website ausbaufähig', 'en' => 'Website could improve'],
        'fi_chance_gering' => ['it' => 'Sito già discreto', 'de' => 'Website schon ordentlich', 'en' => 'Website already decent'],
        'fi_web_neu'  => ['it' => '{n} attività aggiunte ora da OpenStreetMap.', 'de' => '{n} Betriebe gerade aus OpenStreetMap ergänzt.', 'en' => '{n} businesses just added from OpenStreetMap.'],
        'fi_web_fehler' => ['it' => 'OpenStreetMap al momento non risponde — vede solo le attività già note. Riprovi tra un’ora.', 'de' => 'OpenStreetMap antwortet gerade nicht — Sie sehen nur die schon bekannten Betriebe. In einer Stunde nochmal versuchen.', 'en' => 'OpenStreetMap isn’t responding right now — you only see businesses we already know. Try again in an hour.'],
        'fi_osm'      => ['it' => 'Dati: © contributori di OpenStreetMap (ODbL) · Overture Maps Foundation (CDLA-Permissive-2.0)', 'de' => 'Daten: © OpenStreetMap-Mitwirkende (ODbL) · Overture Maps Foundation (CDLA-Permissive-2.0)', 'en' => 'Data: © OpenStreetMap contributors (ODbL) · Overture Maps Foundation (CDLA-Permissive-2.0)'],
        'fi_quellen'  => ['it' => 'Cercare anche altrove (nel suo browser)', 'de' => 'Auch woanders suchen (in Ihrem Browser)', 'en' => 'Search elsewhere too (in your browser)'],
        'fi_quellen_text' => ['it' => 'Apre la ricerca con settore e luogo. Trovato qualcosa? Lo inserisca qui sotto — è subito suo.', 'de' => 'Öffnet die Suche mit Branche und Ort. Etwas gefunden? Unten eintragen — dann gehört er Ihnen.', 'en' => 'Opens the search with sector and place. Found something? Add it below — it’s yours right away.'],
        'fi_q' => ['maps' => ['it' => 'Google Maps', 'de' => 'Google Maps', 'en' => 'Google Maps'], 'google' => ['it' => 'Ricerca Google', 'de' => 'Google-Suche', 'en' => 'Google Search'], 'pagine' => ['it' => 'Pagine Gialle', 'de' => 'Gelbe Seiten', 'en' => 'Pagine Gialle'],
                   'facebook' => ['it' => 'Facebook', 'de' => 'Facebook', 'en' => 'Facebook'], 'indeed' => ['it' => 'Indeed (cercano personale)', 'de' => 'Indeed (suchen Personal)', 'en' => 'Indeed (hiring)'],
                   'tripadvisor' => ['it' => 'Tripadvisor', 'de' => 'Tripadvisor', 'en' => 'Tripadvisor']],
        'fe_titel'    => ['it' => 'Inserire un’attività trovata da lei', 'de' => 'Selbst gefundenen Betrieb eintragen', 'en' => 'Add a business you found'],
        'fe_name'     => ['it' => 'Nome dell’attività', 'de' => 'Name des Betriebs', 'en' => 'Business name'],
        'fe_ort'      => ['it' => 'Comune', 'de' => 'Ort', 'en' => 'Town'],
        'fe_branche'  => ['it' => 'Settore', 'de' => 'Branche', 'en' => 'Sector'],
        'fe_adresse'  => ['it' => 'Indirizzo (facoltativo)', 'de' => 'Adresse (freiwillig)', 'en' => 'Address (optional)'],
        'fe_website'  => ['it' => 'Sito web (se c’è)', 'de' => 'Website (falls vorhanden)', 'en' => 'Website (if any)'],
        'fe_knopf'    => ['it' => 'Inserire e prenotare', 'de' => 'Eintragen und reservieren', 'en' => 'Add and reserve'],
        'fe_gut'      => ['it' => 'Inserita e prenotata per lei (60 giorni). Se ha un sito, la verifica veloce è già pronta qui sopra.', 'de' => 'Eingetragen und für Sie reserviert (60 Tage). Hat er eine Website, steht der Schnellcheck oben schon bereit.', 'en' => 'Added and reserved for you (60 days). If it has a website, the quick check above is ready.'],
        'fe_fehler' => [
            'fe_name' => ['it' => 'Per favore il nome dell’attività.', 'de' => 'Bitte den Namen des Betriebs.', 'en' => 'Please enter the business name.'],
            'fe_ort' => ['it' => 'Per favore il comune.', 'de' => 'Bitte den Ort.', 'en' => 'Please enter the town.'],
            'fe_branche' => ['it' => 'Per favore scelga il settore.', 'de' => 'Bitte die Branche wählen.', 'en' => 'Please choose the sector.'],
            'fe_website' => ['it' => 'L’indirizzo del sito non sembra giusto.', 'de' => 'Die Website-Adresse sieht nicht richtig aus.', 'en' => 'The website address doesn’t look right.'],
            'fe_gesperrt' => ['it' => 'Questa attività non può essere prenotata.', 'de' => 'Dieser Betrieb kann nicht reserviert werden.', 'en' => 'This business can’t be reserved.'],
        ],
        'fi_laeuft'   => ['it' => 'Ricerca in corso … (fino a 20 secondi)', 'de' => 'Suche läuft … (bis zu 20 Sekunden)', 'en' => 'Searching … (up to 20 seconds)'],
        'fi_hinweis'  => ['it' => 'Niente telefono né e-mail, di proposito: il modo migliore è passare di persona o chiedere a chi li conosce.', 'de' => 'Bewusst ohne Telefon und E-Mail: Am besten persönlich vorbeigehen oder jemanden fragen, der den Betrieb kennt.', 'en' => 'Deliberately without phone or email: best to drop by in person or ask someone who knows them.'],
        'ck_titel'    => ['it' => 'Verifica veloce di un sito', 'de' => 'Website-Schnellcheck', 'en' => 'Website quick check'],
        'ck_text'     => ['it' => 'Inserisca l’indirizzo di un’attività. Riceve un rapporto di una pagina da mandare — con il suo consiglio e il suo link.', 'de' => 'Adresse eines Betriebs eingeben. Sie bekommen einen einseitigen Bericht zum Weiterschicken — mit Ihrer Empfehlung und Ihrem Link.', 'en' => 'Enter a business’s address. You get a one-page report to send on — with your recommendation and your link.'],
        'ck_feld'     => ['it' => 'Indirizzo del sito (es. trattoria-rossi.it)', 'de' => 'Adresse der Website (z. B. trattoria-rossi.it)', 'en' => 'Website address (e.g. trattoria-rossi.it)'],
        'ck_pruefen'  => ['it' => 'Verifica', 'de' => 'Prüfen', 'en' => 'Check'],
        'ck_laeuft'   => ['it' => 'Verifica in corso … (fino a 10 secondi)', 'de' => 'Wird geprüft … (bis zu 10 Sekunden)', 'en' => 'Checking … (up to 10 seconds)'],
        'ck_adresse'  => ['it' => 'Questo non sembra un indirizzo di un sito.', 'de' => 'Das sieht nicht nach einer Website-Adresse aus.', 'en' => 'That doesn’t look like a website address.'],
        'ck_genug'    => ['it' => 'Per oggi basta verifiche — domani di nuovo.', 'de' => 'Für heute genug geprüft — morgen wieder.', 'en' => 'Enough checks for today — again tomorrow.'],
        'ck_fertig'   => ['it' => 'Rapporto pronto:', 'de' => 'Bericht fertig:', 'en' => 'Report ready:'],
        'ck_oeffnen'  => ['it' => 'Apri rapporto', 'de' => 'Bericht öffnen', 'en' => 'Open report'],
        'ck_wa'       => ['it' => 'Invia via WhatsApp', 'de' => 'Per WhatsApp schicken', 'en' => 'Send via WhatsApp'],
        'ck_wa_text'  => ['it' => 'Ciao! Ho dato un’occhiata veloce al tuo sito, ecco il risultato: ', 'de' => 'Guten Tag! Ich habe mir Ihre Website kurz angesehen, hier das Ergebnis: ', 'en' => 'Hi! I had a quick look at your website, here’s the result: '],
        'ck_letzte'   => ['it' => 'Ultime verifiche', 'de' => 'Letzte Prüfungen', 'en' => 'Recent checks'],
        'ck_aufrufe'  => ['it' => '{n}× aperto', 'de' => '{n}× geöffnet', 'en' => 'opened {n}×'],
        'ck_punkte'   => ['it' => '{n} punti deboli', 'de' => '{n} Schwachstellen', 'en' => '{n} weak spots'],
        'lf_titel'    => ['it' => 'Guida alla conversazione', 'de' => 'Gesprächsleitfaden', 'en' => 'Conversation guide'],
        'app_fertig'  => ['it' => 'Installata. L’icona «Vecom Partner» è sulla schermata Home o nell’elenco delle app.', 'de' => 'Installiert. Das Symbol „Vecom Partner“ liegt auf dem Startbildschirm oder in der App-Übersicht.', 'en' => 'Installed. The “Vecom Partner” icon is on your home screen or in the app drawer.'],
        'app_laeuft'  => ['it' => 'Aperta come app ✓', 'de' => 'Als App geöffnet ✓', 'en' => 'Opened as an app ✓'],
        'app_installieren' => ['it' => 'Aggiungi alla schermata Home', 'de' => 'Zum Startbildschirm', 'en' => 'Add to home screen'],
        'push_prov_t' => ['it' => 'Nuova provvigione: {betrag}', 'de' => 'Neue Provision: {betrag}', 'en' => 'New commission: {betrag}'],
        'push_prov_x' => ['it' => 'Un cliente arrivato da lei ha pagato.', 'de' => 'Ein Kunde, der über Sie kam, hat bezahlt.', 'en' => 'A customer who came through you has paid.'],
        'push_antw_t' => ['it' => 'Risposta da Vecom Design', 'de' => 'Antwort von Vecom Design', 'en' => 'Reply from Vecom Design'],
        'w_fehlt'    => ['it' => 'Mancano ancora i dati per il pagamento.', 'de' => 'Es fehlen noch Ihre Angaben für die Auszahlung.', 'en' => 'Your payout details are still missing.'],
        'iban_falsch'=> ['it' => 'L’IBAN non è valido. Lo controlli, per favore.', 'de' => 'Die IBAN ist nicht gültig. Bitte prüfen.', 'en' => 'That IBAN isn’t valid. Please check it.'],
        'inhaber_fehlt' => ['it' => 'Manca l’intestatario del conto.', 'de' => 'Der Kontoinhaber fehlt.', 'en' => 'The account holder is missing.'],
        'email_falsch' => ['it' => 'L’indirizzo e-mail non è valido.', 'de' => 'Die E-Mail-Adresse ist nicht gültig.', 'en' => 'That email address isn’t valid.'],
        'pdf_titel'  => ['it' => 'Liquidazione provvigioni', 'de' => 'Provisionsabrechnung', 'en' => 'Commission statement'],
        'pdf_an'     => ['it' => 'Partner', 'de' => 'Partner', 'en' => 'Partner'],
        'pdf_basis'  => ['it' => 'Base netta', 'de' => 'Netto-Basis', 'en' => 'Net basis'],
        'pdf_satz'   => ['it' => 'Aliquota', 'de' => 'Satz', 'en' => 'Rate'],
        'pdf_einbehalt' => ['it' => 'Ritenuta', 'de' => 'Steuereinbehalt', 'en' => 'Tax withheld'],
        'pdf_summe'  => ['it' => 'Pagato', 'de' => 'Ausgezahlt', 'en' => 'Paid'],
        'pdf_weg'    => ['it' => 'Pagato tramite', 'de' => 'Gezahlt über', 'en' => 'Paid via'],
        'pdf_hinweis'=> ['it' => 'Il partner è responsabile della dichiarazione fiscale dei compensi ricevuti.',
                         'de' => 'Für die Versteuerung der erhaltenen Provisionen ist der Partner selbst verantwortlich.',
                         'en' => 'The partner is responsible for declaring and paying tax on the commissions received.'],
    ];

    /** Die Partnervereinbarung — der Wortlaut, dem zugestimmt wird, wird am Partner gespeichert. */
    public const PARTNER_VEREINBARUNG = [
        'it' => "ACCORDO PARTNER — VECOM DESIGN (versione 1 ottobre 2026)\n\n"
              . "1. Provvigione. Il partner consiglia Vecom Design. Per gli acquisti di clienti arrivati per la prima volta tramite il suo link o il suo codice riceve una provvigione di {satz} sull’importo netto effettivamente pagato. Conta il primo contatto; un cliente già acquisito o arrivato tramite un altro partner non viene riassegnato. L’assegnazione vale per {zuordnung} mesi; per contratti mensili la provvigione vale per i primi {monate} mesi.\n"
              . "2. Maturazione. La provvigione nasce solo a pagamento ricevuto e diventa pagabile dopo {tage} giorni (periodo di recesso del cliente). Se il cliente viene rimborsato, la provvigione decade o viene stornata; importi già pagati possono essere compensati o richiesti indietro.\n"
              . "3. Pagamento. Il pagamento avviene a partire da {min} tramite il canale che il partner sceglie nella sua area (per es. Stripe, bonifico, PayPal).\n"
              . "4. Comportamento. Non spettano provvigioni su acquisti propri. Sono vietati pubblicità ingannevole, spam e annunci a pagamento sul nome «Vecom». Il partner indica che il link porta una provvigione (per es. #pubblicità). Non contatta aziende via e-mail, WhatsApp, SMS o chiamate automatiche senza il loro consenso preventivo; telefona solo secondo le regole della sua area (in Italia mai numeri iscritti al Registro delle Opposizioni).\n"
              . "5. Autonomia. Il partner agisce in modo autonomo, senza esclusiva, senza zona assegnata e senza obbligo di attività o di risultati minimi. Non è dipendente né agente di commercio di Vecom Design e non fa promesse a suo nome.\n"
              . "6. Imposte. Il partner è responsabile della propria posizione fiscale. Se la legge lo prevede, Vecom Design trattiene le imposte dovute.\n"
              . "7. Materiali Vecom. Nella sua area il partner trova dati e analisi di aziende, valutazioni, liste di chiamata, guide al colloquio, modelli e materiali pubblicitari («Materiali Vecom»). Non vede i dati dei clienti di Vecom Design.\n"
              . "8. Proprietà. La raccolta, la selezione, le valutazioni e le analisi delle aziende, gli esiti delle chiamate, tutti i testi, modelli, grafiche, foto, immagini 3D, video, volantini e il logo sono di proprietà di Vecom Design o protetti dal diritto d’autore. Il partner riceve solo un diritto semplice, non esclusivo, non trasferibile e revocabile in ogni momento di usarli durante la collaborazione ed esclusivamente per raccomandare Vecom Design.\n"
              . "9. Riservatezza. I Materiali Vecom sono segreti commerciali (artt. 98–99 D.Lgs. 30/2005). Il partner non li copia, esporta, raccoglie, cede a terzi né li usa per scopi propri o altrui, anche dopo la fine della collaborazione. Alla fine cancella ogni copia e lo conferma su richiesta. I dati contengono voci di controllo e ogni accesso viene registrato, così Vecom Design può riconoscere una cessione.\n"
              . "10. Tutela della clientela. Durante la collaborazione e per {schutz} mesi dopo la sua fine il partner non offre, né direttamente né tramite terzi, siti web, negozi online, hosting o marketing online alle aziende conosciute tramite la sua area (ricerca aziende, lista di chiamata, prenotazioni) né ai clienti di Vecom Design, e non li sottrae a Vecom Design. Sono escluse le aziende che il partner ha inserito lui stesso o che dimostra di conoscere già prima.\n"
              . "11. Marchio e logo. Nome e logo «Vecom Design» si usano solo nelle versioni approvate della sua area e senza modifiche (forma, colori, proporzioni, nessuna combinazione con segni propri). È vietato usare «Vecom» in domini, profili social, nomi d’impresa, parole chiave pubblicitarie o loghi propri e dare l’impressione di essere Vecom Design. Alla fine il partner rimuove tutti i materiali entro 7 giorni.\n"
              . "12. Protezione dei dati. Se tratta dati personali della sua area (per es. nome e telefono di una ditta individuale), il partner agisce solo su istruzione di Vecom Design e solo per raccomandare Vecom Design (artt. 28–29 GDPR). Li tiene riservati e al sicuro e segnala subito ogni perdita. Se li usa per scopi propri, ne risponde come titolare autonomo.\n"
              . "13. Penale. Per ogni violazione colpevole dei punti 9, 10, 11 o 12 il partner paga una penale di {penale} (art. 1382 c.c.), salvo il risarcimento del danno ulteriore.\n"
              . "14. Fine della collaborazione. Entrambi possono recedere in qualsiasi momento. In caso di violazione dei punti 4, 9, 10, 11 o 12 Vecom Design può risolvere l’accordo con effetto immediato (art. 1456 c.c.) e bloccare l’area partner; le provvigioni legate alla violazione decadono, le altre già maturate vengono pagate. I punti 8–13 restano validi dopo la fine.\n"
              . "15. Modifiche, legge e foro. Vecom Design può modificare l’accordo per il futuro; l’area partner si riapre dopo una nuova accettazione. Si applica la legge italiana. Foro competente è Agrigento, nei limiti di legge.\n"
              . "16. Attivazione. L’area partner viene attivata da Vecom Design dopo l’accettazione di questo accordo.",
        'de' => "PARTNERVEREINBARUNG — VECOM DESIGN (Fassung vom 1. Oktober 2026)\n\n"
              . "1. Provision. Der Partner empfiehlt Vecom Design. Für Käufe von Kunden, die zum ersten Mal über seinen Link oder Code kommen, erhält er eine Provision von {satz} vom tatsächlich bezahlten Nettobetrag. Es zählt der erste Kontakt; wer bereits Kunde ist oder über einen anderen Partner kam, wird nicht umgehängt. Die Zuordnung gilt {zuordnung} Monate; bei monatlichen Verträgen gilt die Provision für die ersten {monate} Monate.\n"
              . "2. Entstehen. Die Provision entsteht erst mit dem Zahlungseingang und wird nach {tage} Tagen (Widerrufsfrist des Kunden) auszahlbar. Wird dem Kunden erstattet, entfällt sie oder wird zurückgebucht; bereits ausgezahlte Beträge können verrechnet oder zurückgefordert werden.\n"
              . "3. Auszahlung. Ausgezahlt wird ab {min} über den Weg, den der Partner in seinem Bereich wählt (z. B. Stripe, Überweisung, PayPal).\n"
              . "4. Verhalten. Auf eigene Käufe gibt es keine Provision. Irreführende Werbung, Spam und gekaufte Anzeigen auf den Namen „Vecom“ sind nicht erlaubt. Der Partner kennzeichnet seinen Link als Werbung mit Provision (z. B. „Werbung“). Er schreibt Betriebe nicht per E-Mail, WhatsApp, SMS oder automatischem Anruf an, ohne dass sie vorher eingewilligt haben, und ruft nur nach den Regeln in seinem Bereich an (in Italien nie Nummern aus dem Registro delle Opposizioni).\n"
              . "5. Selbstständigkeit. Der Partner arbeitet selbstständig, nicht exklusiv, ohne festes Gebiet und ohne Pflicht zu Tätigkeit oder Mindestergebnissen. Er ist weder Angestellter noch Handelsvertreter von Vecom Design und macht keine Zusagen in dessen Namen.\n"
              . "6. Steuern. Für seine Steuern ist der Partner selbst verantwortlich. Wo das Gesetz es verlangt, behält Vecom Design Steuern ein.\n"
              . "7. Vecom-Unterlagen. Im Partnerbereich stehen Betriebsdaten und -analysen, Bewertungen, Anruflisten, Gesprächsleitfäden, Vorlagen und Werbemittel („Vecom-Unterlagen“). Daten von Kunden von Vecom Design sieht der Partner nicht.\n"
              . "8. Eigentum. Die Zusammenstellung, Auswahl, Bewertungen und Analysen der Betriebe, die Anrufergebnisse, alle Texte, Vorlagen, Grafiken, Fotos, 3D-Bilder, Videos, Flyer und das Logo sind Eigentum von Vecom Design bzw. urheberrechtlich geschützt. Der Partner erhält nur ein einfaches, nicht übertragbares und jederzeit widerrufliches Recht, sie während der Partnerschaft und ausschließlich zur Empfehlung von Vecom Design zu nutzen.\n"
              . "9. Vertraulichkeit. Die Vecom-Unterlagen sind Geschäftsgeheimnisse (Art. 98–99 D.Lgs. 30/2005, GeschGehG). Der Partner darf sie nicht kopieren, exportieren, sammeln, an Dritte weitergeben oder für eigene oder fremde Zwecke nutzen, auch nicht nach Ende der Partnerschaft. Bei Ende löscht er alle Kopien und bestätigt das auf Nachfrage. Die Daten enthalten Kontrolleinträge, und jeder Zugriff wird protokolliert, damit Vecom Design eine Weitergabe erkennen kann.\n"
              . "10. Kundenschutz. Während der Partnerschaft und {schutz} Monate nach ihrem Ende bietet der Partner Betrieben, die er über seinen Bereich kennengelernt hat (Firmen-Finder, Anrufliste, Reservierungen), und Kunden von Vecom Design weder selbst noch über Dritte Websites, Online-Shops, Hosting oder Online-Marketing an und wirbt sie Vecom Design nicht ab. Ausgenommen sind Betriebe, die der Partner selbst eingetragen hat oder nachweislich schon vorher kannte.\n"
              . "11. Marke und Logo. Name und Logo „Vecom Design“ nur in den freigegebenen Fassungen aus dem Partnerbereich und unverändert (Form, Farben, Proportionen; keine Verbindung mit eigenen Zeichen). Verboten sind „Vecom“ in Domains, Social-Media-Konten, Firmennamen, Anzeigen-Stichwörtern oder eigenen Logos sowie der Eindruck, der Partner sei Vecom Design. Bei Ende entfernt der Partner alle Werbemittel innerhalb von 7 Tagen.\n"
              . "12. Datenschutz. Verarbeitet der Partner personenbezogene Daten aus seinem Bereich (z. B. Name und Telefon eines Einzelunternehmers), handelt er nur nach Weisung von Vecom Design und nur zur Empfehlung von Vecom Design (Art. 28–29 DSGVO). Er hält die Daten vertraulich und sicher und meldet jeden Verlust sofort. Nutzt er sie für eigene Zwecke, haftet er dafür als eigener Verantwortlicher.\n"
              . "13. Vertragsstrafe. Für jeden schuldhaften Verstoß gegen Nr. 9, 10, 11 oder 12 zahlt der Partner eine Vertragsstrafe von {penale} (Art. 1382 c.c.); der Ersatz eines weitergehenden Schadens bleibt vorbehalten.\n"
              . "14. Ende. Beide Seiten können jederzeit beenden. Bei einem Verstoß gegen Nr. 4, 9, 10, 11 oder 12 kann Vecom Design die Vereinbarung sofort auflösen (Art. 1456 c.c.) und den Partnerbereich sperren; Provisionen, die mit dem Verstoß zusammenhängen, entfallen, übrige bereits verdiente werden ausgezahlt. Nr. 8–13 gelten nach dem Ende weiter.\n"
              . "15. Änderungen, Recht und Gericht. Vecom Design kann die Vereinbarung für die Zukunft ändern; der Partnerbereich öffnet sich dann erst nach erneuter Zustimmung. Es gilt italienisches Recht. Gerichtsstand ist Agrigento, soweit gesetzlich zulässig.\n"
              . "16. Freischaltung. Vecom Design schaltet den Partnerbereich frei, nachdem der Partner dieser Vereinbarung zugestimmt hat.",
        'en' => "PARTNER AGREEMENT — VECOM DESIGN (version of 1 October 2026)\n\n"
              . "1. Commission. The partner recommends Vecom Design. For purchases by customers who arrive for the first time through the partner’s link or code, the partner earns a commission of {satz} on the net amount actually paid. The first contact counts; existing customers or customers who came through another partner are not reassigned. The assignment lasts {zuordnung} months; for monthly contracts the commission applies to the first {monate} months.\n"
              . "2. Earning. Commission is earned only once payment is received and becomes payable after {tage} days (the customer’s withdrawal period). If the customer is refunded, the commission lapses or is reversed; amounts already paid may be offset or reclaimed.\n"
              . "3. Payout. Payouts are made from {min} through the method the partner chooses in their area (e.g. Stripe, bank transfer, PayPal).\n"
              . "4. Conduct. No commission on one’s own purchases. Misleading advertising, spam and paid ads on the name “Vecom” are not allowed. The partner discloses that the link earns a commission (e.g. #ad). The partner does not contact businesses by email, WhatsApp, SMS or automated calls without their prior consent and only phones according to the rules in their area (in Italy never numbers listed in the Registro delle Opposizioni).\n"
              . "5. Independence. The partner works independently, non-exclusively, without an assigned territory and without any duty to be active or to reach minimum results. The partner is neither an employee nor a commercial agent of Vecom Design and makes no promises on its behalf.\n"
              . "6. Taxes. The partner is responsible for their own taxes. Where the law requires it, Vecom Design withholds tax.\n"
              . "7. Vecom materials. The partner area contains business data and analyses, ratings, call lists, call guides, templates and advertising material (“Vecom materials”). The partner does not see data of Vecom Design’s customers.\n"
              . "8. Ownership. The compilation, selection, ratings and analyses of businesses, call results, all texts, templates, graphics, photos, 3D images, videos, flyers and the logo are the property of Vecom Design or protected by copyright. The partner receives only a simple, non-exclusive, non-transferable right, revocable at any time, to use them during the partnership and solely to recommend Vecom Design.\n"
              . "9. Confidentiality. Vecom materials are trade secrets (Arts. 98–99 Italian Legislative Decree 30/2005). The partner must not copy, export, collect, pass on or use them for their own or third-party purposes, including after the partnership ends. When it ends, the partner deletes all copies and confirms this on request. The data contain control entries and every access is logged, so Vecom Design can detect any disclosure.\n"
              . "10. Customer protection. During the partnership and for {schutz} months after it ends, the partner will not offer websites, online shops, hosting or online marketing, directly or through third parties, to businesses the partner got to know through the partner area (business finder, call list, reservations) or to customers of Vecom Design, nor draw them away from Vecom Design. Businesses the partner entered themselves or can prove they already knew are excluded.\n"
              . "11. Brand and logo. The name and logo “Vecom Design” may only be used in the approved versions from the partner area and without changes (shape, colours, proportions; no combination with other marks). Using “Vecom” in domains, social media accounts, company names, ad keywords or own logos, or giving the impression of being Vecom Design, is prohibited. When the partnership ends, the partner removes all material within 7 days.\n"
              . "12. Data protection. Where the partner processes personal data from the partner area (e.g. name and phone of a sole trader), the partner acts only on Vecom Design’s instructions and only to recommend Vecom Design (Arts. 28–29 GDPR), keeps the data confidential and secure and reports any loss immediately. If the partner uses them for their own purposes, the partner is liable as an independent controller.\n"
              . "13. Contractual penalty. For each culpable breach of sections 9, 10, 11 or 12 the partner pays a penalty of {penale} (Art. 1382 Italian Civil Code), without prejudice to compensation for further damage.\n"
              . "14. Termination. Either side may end the partnership at any time. If sections 4, 9, 10, 11 or 12 are breached, Vecom Design may terminate with immediate effect (Art. 1456 Italian Civil Code) and block the partner area; commission connected with the breach lapses, other commission already earned is paid out. Sections 8–13 survive termination.\n"
              . "15. Changes, law and venue. Vecom Design may change this agreement for the future; the partner area then reopens only after renewed acceptance. Italian law applies. The place of jurisdiction is Agrigento, to the extent permitted by law.\n"
              . "16. Activation. Vecom Design activates the partner area after the partner has accepted this agreement.",
    ];

    /** Die Abzugszeile auf dem Beleg einer Betreuungsrate (26.09.2026). */
    public const EMPFEHLUNGSRABATT = [
        'it' => 'Sconto per raccomandazione ({p} %)',
        'de' => 'Empfehlungsrabatt ({p} %)',
        'en' => 'Referral discount ({p} %)',
    ];

    /** Die Landeseite hinter /p/CODE (26.09.2026). {name} = wie der Partner genannt werden will. */
    /* Branchentexte für die Partnerseite (28.09.2026, Uwe: Ja zu R1). Der Gestalter
       setzt sie auf Klick in die Felder; danach sind es die Texte des Partners.
       Ohne Adressen und ohne {name}: eigene Texte werden nicht ersetzt. */
    public const SEITE_BRANCHEN = [
        'gastro' => [
            'name' => ['it' => 'Ristorante & bar', 'de' => 'Restaurant & Bar', 'en' => 'Restaurant & bar'],
            'it' => ['titel' => 'Siti web per ristoranti e bar in Sicilia', 'lead' => 'Menù, orari e prenotazione del tavolo in un unico posto, facili da trovare sul telefono. Realizziamo il suo sito con un prezzo chiaro prima e una persona che la segue.', 'p1' => 'Menù e orari ben leggibili sul telefono.', 'p2' => 'Prenotazione del tavolo direttamente sul sito, se lo desidera.', 'p3' => 'Su richiesta in italiano, tedesco e inglese per i suoi ospiti.'],
            'de' => ['titel' => 'Websites für Restaurants und Bars in Sizilien', 'lead' => 'Speisekarte, Öffnungszeiten und Tischreservierung an einem Ort, auf dem Handy schnell gefunden. Wir bauen Ihre Website mit klarem Preis vorher und einem Menschen, der Sie begleitet.', 'p1' => 'Speisekarte und Öffnungszeiten, gut lesbar auf dem Handy.', 'p2' => 'Tischreservierung direkt auf der Website, wenn Sie möchten.', 'p3' => 'Auf Wunsch auf Italienisch, Deutsch und Englisch für Ihre Gäste.'],
            'en' => ['titel' => 'Websites for restaurants and bars in Sicily', 'lead' => 'Menu, opening hours and table booking in one place, easy to find on a phone. We build your website with a clear price upfront and a real person guiding you.', 'p1' => 'Menu and opening hours, easy to read on a phone.', 'p2' => 'Table booking right on the website, if you like.', 'p3' => 'In Italian, German and English for your guests, on request.'],
        ],
        'hotel' => [
            'name' => ['it' => 'Hotel & case vacanza', 'de' => 'Hotel & Ferienhaus', 'en' => 'Hotel & holiday home'],
            'it' => ['titel' => 'Siti web per hotel e case vacanza in Sicilia', 'lead' => 'Mostri camere, posizione e prezzi in modo che gli ospiti chiedano direttamente a lei. Realizziamo il suo sito con un prezzo chiaro prima e una persona che la segue.', 'p1' => 'Camere e dintorni in immagini grandi e tranquille.', 'p2' => 'Richieste direttamente da lei, su richiesta con sistema di prenotazione.', 'p3' => 'Su richiesta in italiano, tedesco e inglese.'],
            'de' => ['titel' => 'Websites für Hotels und Ferienhäuser in Sizilien', 'lead' => 'Zeigen Sie Zimmer, Lage und Preise so, dass Gäste direkt bei Ihnen anfragen. Wir bauen Ihre Website mit klarem Preis vorher und einem Menschen, der Sie begleitet.', 'p1' => 'Zimmer und Umgebung in großen, ruhigen Bildern.', 'p2' => 'Anfragen direkt bei Ihnen, auf Wunsch mit Buchungssystem.', 'p3' => 'Auf Wunsch auf Italienisch, Deutsch und Englisch.'],
            'en' => ['titel' => 'Websites for hotels and holiday homes in Sicily', 'lead' => 'Show rooms, location and prices so guests ask you directly. We build your website with a clear price upfront and a real person guiding you.', 'p1' => 'Rooms and surroundings in large, calm images.', 'p2' => 'Enquiries come straight to you, with a booking system if you like.', 'p3' => 'In Italian, German and English on request.'],
        ],
        'beauty' => [
            'name' => ['it' => 'Parrucchiere & estetica', 'de' => 'Friseur & Kosmetik', 'en' => 'Hair & beauty'],
            'it' => ['titel' => 'Siti web per parrucchieri ed estetica in Sicilia', 'lead' => 'Servizi, prezzi e appuntamenti liberi a colpo d’occhio, così i nuovi clienti trovano la strada da lei. Realizziamo il suo sito con un prezzo chiaro prima e una persona che la segue.', 'p1' => 'Servizi e prezzi chiari, anche sul telefono.', 'p2' => 'Prenotazione degli appuntamenti online, se lo desidera.', 'p3' => 'I suoi lavori in belle immagini.'],
            'de' => ['titel' => 'Websites für Friseure und Kosmetik in Sizilien', 'lead' => 'Leistungen, Preise und freie Termine auf einen Blick, damit neue Kundinnen und Kunden den Weg zu Ihnen finden. Wir bauen Ihre Website mit klarem Preis vorher und einem Menschen, der Sie begleitet.', 'p1' => 'Leistungen und Preise übersichtlich, auch auf dem Handy.', 'p2' => 'Terminbuchung online, wenn Sie möchten.', 'p3' => 'Ihre Arbeiten in schönen Bildern.'],
            'en' => ['titel' => 'Websites for hair and beauty salons in Sicily', 'lead' => 'Services, prices and free appointments at a glance, so new clients find their way to you. We build your website with a clear price upfront and a real person guiding you.', 'p1' => 'Services and prices laid out clearly, also on a phone.', 'p2' => 'Online appointment booking, if you like.', 'p3' => 'Your work shown in beautiful images.'],
        ],
        'auto' => [
            'name' => ['it' => 'Auto & officina', 'de' => 'Auto & Werkstatt', 'en' => 'Cars & garage'],
            'it' => ['titel' => 'Siti web per concessionarie e officine in Sicilia', 'lead' => 'Veicoli, servizi e contatti mostrati in modo che i clienti chiamino subito o prendano un appuntamento. Realizziamo il suo sito con un prezzo chiaro prima e una persona che la segue.', 'p1' => 'Veicoli e servizi chiari, anche sul telefono.', 'p2' => 'Appuntamenti online, se lo desidera.', 'p3' => 'Il prezzo lo sa prima che iniziamo.'],
            'de' => ['titel' => 'Websites für Autohäuser und Werkstätten in Sizilien', 'lead' => 'Fahrzeuge, Leistungen und Kontakt so gezeigt, dass Kunden sofort anrufen oder einen Termin machen. Wir bauen Ihre Website mit klarem Preis vorher und einem Menschen, der Sie begleitet.', 'p1' => 'Fahrzeuge und Leistungen übersichtlich, auch auf dem Handy.', 'p2' => 'Termine online vereinbaren, wenn Sie möchten.', 'p3' => 'Den Preis kennen Sie, bevor wir anfangen.'],
            'en' => ['titel' => 'Websites for car dealers and garages in Sicily', 'lead' => 'Vehicles, services and contact shown so customers call right away or book an appointment. We build your website with a clear price upfront and a real person guiding you.', 'p1' => 'Vehicles and services laid out clearly, also on a phone.', 'p2' => 'Online appointments, if you like.', 'p3' => 'You know the price before we start.'],
        ],
        'handwerk' => [
            'name' => ['it' => 'Artigianato & cucine', 'de' => 'Handwerk & Küchen', 'en' => 'Craft & kitchens'],
            'it' => ['titel' => 'Siti web per artigiani e cucine in Sicilia', 'lead' => 'I suoi lavori in immagini grandi, così i clienti vedono cosa sa fare e chiedono direttamente. Realizziamo il suo sito con un prezzo chiaro prima e una persona che la segue.', 'p1' => 'Referenze e lavori in immagini grandi.', 'p2' => 'Le richieste arrivano direttamente a lei.', 'p3' => 'Il prezzo lo sa prima che iniziamo.'],
            'de' => ['titel' => 'Websites für Handwerk und Küchenbau in Sizilien', 'lead' => 'Ihre Arbeiten in großen Bildern, damit Kunden sehen, was Sie können, und direkt anfragen. Wir bauen Ihre Website mit klarem Preis vorher und einem Menschen, der Sie begleitet.', 'p1' => 'Referenzen und Arbeiten in großen Bildern.', 'p2' => 'Anfragen kommen direkt zu Ihnen.', 'p3' => 'Den Preis kennen Sie, bevor wir anfangen.'],
            'en' => ['titel' => 'Websites for craftspeople and kitchen makers in Sicily', 'lead' => 'Your work in large images, so customers see what you can do and get in touch directly. We build your website with a clear price upfront and a real person guiding you.', 'p1' => 'References and projects in large images.', 'p2' => 'Enquiries come straight to you.', 'p3' => 'You know the price before we start.'],
        ],
        'produkte' => [
            'name' => ['it' => 'Vino, olio & gastronomia', 'de' => 'Wein, Öl & Feinkost', 'en' => 'Wine, oil & fine food'],
            'it' => ['titel' => 'Siti web e negozi online per vino, olio e gastronomia', 'lead' => 'Racconti la storia dei suoi prodotti e, se vuole, venda direttamente online, anche all’estero. Realizziamo il suo sito con un prezzo chiaro prima e una persona che la segue.', 'p1' => 'I suoi prodotti in immagini tranquille e curate.', 'p2' => 'Su richiesta con negozio online.', 'p3' => 'In italiano, tedesco e inglese per i clienti all’estero.'],
            'de' => ['titel' => 'Websites und Online-Shops für Wein, Öl und Feinkost', 'lead' => 'Erzählen Sie die Geschichte Ihrer Produkte und verkaufen Sie auf Wunsch direkt online, auch ins Ausland. Wir bauen Ihre Website mit klarem Preis vorher und einem Menschen, der Sie begleitet.', 'p1' => 'Ihre Produkte in ruhigen, hochwertigen Bildern.', 'p2' => 'Auf Wunsch mit Online-Shop.', 'p3' => 'Auf Italienisch, Deutsch und Englisch für Kunden im Ausland.'],
            'en' => ['titel' => 'Websites and online shops for wine, oil and fine food', 'lead' => 'Tell the story of your products and, if you like, sell directly online, also abroad. We build your website with a clear price upfront and a real person guiding you.', 'p1' => 'Your products in calm, high-quality images.', 'p2' => 'With an online shop, if you like.', 'p3' => 'In Italian, German and English for customers abroad.'],
        ],
        'laden' => [
            'name' => ['it' => 'Moda & gioielli', 'de' => 'Mode & Schmuck', 'en' => 'Fashion & jewellery'],
            'it' => ['titel' => 'Siti web e negozi online per moda e gioielli', 'lead' => 'Mostri i suoi pezzi come appaiono in negozio e, se vuole, venda anche online. Realizziamo il suo sito con un prezzo chiaro prima e una persona che la segue.', 'p1' => 'I suoi pezzi in immagini grandi e precise.', 'p2' => 'Su richiesta con negozio online.', 'p3' => 'Il prezzo lo sa prima che iniziamo.'],
            'de' => ['titel' => 'Websites und Online-Shops für Mode und Schmuck', 'lead' => 'Zeigen Sie Ihre Stücke so, wie sie im Laden wirken, und verkaufen Sie auf Wunsch auch online. Wir bauen Ihre Website mit klarem Preis vorher und einem Menschen, der Sie begleitet.', 'p1' => 'Ihre Stücke in großen, genauen Bildern.', 'p2' => 'Auf Wunsch mit Online-Shop.', 'p3' => 'Den Preis kennen Sie, bevor wir anfangen.'],
            'en' => ['titel' => 'Websites and online shops for fashion and jewellery', 'lead' => 'Show your pieces the way they look in the shop and, if you like, sell online too. We build your website with a clear price upfront and a real person guiding you.', 'p1' => 'Your pieces in large, precise images.', 'p2' => 'With an online shop, if you like.', 'p3' => 'You know the price before we start.'],
        ],
        'transport' => [
            'name' => ['it' => 'Trasporti & logistica', 'de' => 'Transport & Logistik', 'en' => 'Transport & logistics'],
            'it' => ['titel' => 'Siti web per trasporti e logistica in Sicilia', 'lead' => 'Servizi, mezzi e zona di lavoro mostrati con chiarezza, così i clienti aziendali chiedono in fretta. Realizziamo il suo sito con un prezzo chiaro prima e una persona che la segue.', 'p1' => 'Servizi e zona di lavoro a colpo d’occhio.', 'p2' => 'Le richieste arrivano direttamente a lei.', 'p3' => 'Su richiesta in italiano, tedesco e inglese.'],
            'de' => ['titel' => 'Websites für Transport und Logistik in Sizilien', 'lead' => 'Leistungen, Fahrzeuge und Einsatzgebiet klar gezeigt, damit Geschäftskunden schnell anfragen. Wir bauen Ihre Website mit klarem Preis vorher und einem Menschen, der Sie begleitet.', 'p1' => 'Leistungen und Einsatzgebiet auf einen Blick.', 'p2' => 'Anfragen kommen direkt zu Ihnen.', 'p3' => 'Auf Wunsch auf Italienisch, Deutsch und Englisch.'],
            'en' => ['titel' => 'Websites for transport and logistics in Sicily', 'lead' => 'Services, vehicles and area clearly shown, so business customers get in touch quickly. We build your website with a clear price upfront and a real person guiding you.', 'p1' => 'Services and area at a glance.', 'p2' => 'Enquiries come straight to you.', 'p3' => 'In Italian, German and English on request.'],
        ],
    ];

    public const PARTNER_LANDE = [
        'titel'  => ['it' => 'Siti web per chi lavora in Sicilia', 'de' => 'Websites für Betriebe in Sizilien', 'en' => 'Websites for businesses in Sicily'],
        'marke'  => ['it' => 'Consigliato da {name}', 'de' => 'Empfohlen von {name}', 'en' => 'Recommended by {name}'],
        'lead'   => ['it' => '{name} ci consiglia. Realizziamo il suo sito web — dalla prima chiacchierata al sito online, con un prezzo chiaro prima e una persona che la segue.',
                     'de' => '{name} empfiehlt uns. Wir bauen Ihre Website — vom ersten Gespräch bis sie online ist, mit klarem Preis vorher und einem Menschen, der Sie begleitet.',
                     'en' => '{name} recommends us. We build your website — from the first chat until it’s live, with a clear price upfront and a real person guiding you.'],
        'p1'     => ['it' => 'Lei dice cosa le serve — il prezzo lo sa prima.', 'de' => 'Sie sagen, was Sie brauchen — den Preis kennen Sie vorher.', 'en' => 'You say what you need — you know the price upfront.'],
        'p2'     => ['it' => 'Costruiamo noi, lei vede ogni passo nella sua area personale.', 'de' => 'Wir bauen, Sie sehen jeden Schritt in Ihrem persönlichen Bereich.', 'en' => 'We build it; you see every step in your personal area.'],
        'p3'     => ['it' => 'Il sito va online — se vuole con dominio, e-mail e assistenza.', 'de' => 'Die Seite geht online — auf Wunsch mit Domain, E-Mail und Betreuung.', 'en' => 'Your site goes live — with domain, email and care if you like.'],
        'feld'   => ['it' => 'La sua e-mail', 'de' => 'Ihre E-Mail-Adresse', 'en' => 'Your email address'],
        'knopf'  => ['it' => 'Iniziare', 'de' => 'Loslegen', 'en' => 'Get started'],
        'klein'  => ['it' => 'Riceve il link alla sua area personale. Gratis e senza impegno.', 'de' => 'Sie bekommen den Link zu Ihrem persönlichen Bereich. Kostenlos und unverbindlich.', 'en' => 'You’ll get the link to your personal area. Free and without obligation.'],
        'foto_alt' => ['it' => 'Foto di {name}', 'de' => 'Foto von {name}', 'en' => 'Photo of {name}'],
        'weiter' => ['it' => 'Prima guardare il sito →', 'de' => 'Erst die Website ansehen →', 'en' => 'See the website first →'],
        // Weiterleiten (27.09.2026, Uwe: „alles muss für Kunden mit Kunden teilbar sein“)
        'wl_titel' => ['it' => 'Conosce qualcuno a cui serve un sito?', 'de' => 'Kennen Sie jemanden, der eine Website braucht?', 'en' => 'Know someone who needs a website?'],
        'wl_text' => ['it' => 'Inoltri questa pagina — con WhatsApp, e-mail o link.', 'de' => 'Leiten Sie diese Seite weiter — per WhatsApp, E-Mail oder Link.', 'en' => 'Forward this page — via WhatsApp, email or link.'],
        'wl_wa' => ['it' => 'Inoltrare su WhatsApp', 'de' => 'Per WhatsApp weiterleiten', 'en' => 'Forward on WhatsApp'],
        'wl_mail' => ['it' => 'Per e-mail', 'de' => 'Per E-Mail', 'en' => 'By email'],
        'wl_kopieren' => ['it' => 'Copiare il link', 'de' => 'Link kopieren', 'en' => 'Copy link'],
        'wl_teilen' => ['it' => 'Condividere …', 'de' => 'Teilen …', 'en' => 'Share …'],
        'wl_kopiert' => ['it' => 'Copiato ✓', 'de' => 'Kopiert ✓', 'en' => 'Copied ✓'],
        'wl_nachricht' => ['it' => 'Guarda: siti web di Vecom Design, consigliati da {name}: ', 'de' => 'Schau mal: Websites von Vecom Design, empfohlen von {name}: ', 'en' => 'Have a look: websites by Vecom Design, recommended by {name}: '],
        'wl_betreff' => ['it' => 'Siti web — Vecom Design', 'de' => 'Websites — Vecom Design', 'en' => 'Websites — Vecom Design'],
    ];

    /* ------------------------------------------------------------------------
       Werbe-Paket der Partner (26.09.2026). Jede Vorlage trägt {link} -- den
       Link DES Kanals -- und, wo sie öffentlich ist, die Werbekennzeichnung.
       {name} = Anzeigename des Partners. [Name] füllt der Partner selbst aus.
       Keine Versprechen, die Vecom nicht hält: nur, was auf der Website steht
       (Preis vorher, persönlicher Bereich, Begleitung bis online).
       ------------------------------------------------------------------------ */
    public const PARTNER_WERBUNG = [
        'namen' => [
            '_haupt' => ['it' => 'Link principale', 'de' => 'Hauptlink', 'en' => 'Main link'],
            'whatsapp' => 'WhatsApp', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok',
            'email' => ['it' => 'E-mail', 'de' => 'E-Mail', 'en' => 'Email'], 'linkedin' => 'LinkedIn', 'sms' => 'SMS',
            'signatur' => ['it' => 'Firma e-mail', 'de' => 'E-Mail-Signatur', 'en' => 'Email signature'],
            'website' => ['it' => 'Pulsante sito', 'de' => 'Website-Knopf', 'en' => 'Website button'],
            'karte' => ['it' => 'Cartolina', 'de' => 'Karte', 'en' => 'Card'], 'flyer' => 'Flyer',
            'bild' => ['it' => 'Immagini', 'de' => 'Bilder', 'en' => 'Images'], 'video' => 'Video',
            'bild3d' => ['it' => 'Immagini 3D', 'de' => '3D-Bilder', 'en' => '3D images'], 'video3d' => ['it' => 'Video 3D', 'de' => '3D-Videos', 'en' => '3D videos'],
            'check' => ['it' => 'Verifica veloce', 'de' => 'Schnellcheck', 'en' => 'Quick check'],
            'erfolg' => ['it' => 'Post «È online»', 'de' => 'Beitrag „Ist online“', 'en' => '“It’s live” post'],
            'weiter' => ['it' => 'Inoltrata da clienti', 'de' => 'Von Kunden weitergeleitet', 'en' => 'Forwarded by customers'],
            'kalender' => ['it' => 'Post del calendario', 'de' => 'Beitrag aus dem Kalender', 'en' => 'Calendar post'],
            'mappe' => ['it' => 'Cartella stampata', 'de' => 'Gedruckte Mappe', 'en' => 'Printed folder'],
            'kachel' => ['it' => 'Immagine recensione/sito', 'de' => 'Bild Kundenstimme/Seite', 'en' => 'Review/site image'],
            'anschreiben' => ['it' => 'Messaggio personale ad attività', 'de' => 'Persönliche Nachricht an Betrieb', 'en' => 'Personal message to business'],
            'brief' => ['it' => 'Lettera di Vecom per il partner', 'de' => 'Brief von Vecom für den Partner', 'en' => 'Letter from Vecom for the partner'],
        ],
        'tipps' => [
            'whatsapp'  => ['it' => 'Il messaggio personale funziona meglio del testo generico: scriva a chi le ha detto di recente che gli serve un sito.', 'de' => 'Die persönliche Nachricht wirkt stärker als der allgemeine Text: Schreiben Sie denen, die kürzlich erwähnt haben, dass sie eine Website brauchen.', 'en' => 'The personal message works better than the general text: write to people who recently mentioned they need a website.'],
            'instagram' => ['it' => 'Metta questo link nella bio. Nelle storie usi lo sticker «Link» con lo stesso link.', 'de' => 'Diesen Link in die Bio setzen. In Stories den Sticker „Link“ mit demselben Link verwenden.', 'en' => 'Put this link in your bio. In stories, use the “Link” sticker with the same link.'],
            'facebook'  => ['it' => 'Nei gruppi locali legga prima le regole del gruppo: molti permettono consigli, non pubblicità continua.', 'de' => 'In lokalen Gruppen vorher die Gruppenregeln lesen: Viele erlauben Empfehlungen, aber keine Dauerwerbung.', 'en' => 'In local groups, read the group rules first: many allow recommendations, not constant advertising.'],
            'tiktok'    => ['it' => 'Non tutti gli account TikTok possono mettere un link nella bio (di solito solo gli account Business). In quel caso mostri il codice QR nel video.', 'de' => 'Nicht jedes TikTok-Konto darf einen Link in die Bio setzen (meist nur Business-Konten). Dann den QR-Code im Video zeigen.', 'en' => 'Not every TikTok account can put a link in the bio (usually only Business accounts). Then show the QR code in the video.'],
            'email'     => ['it' => '«Invia» apre il suo programma di posta con oggetto e testo già pronti. Sostituisca [Nome].', 'de' => '„Senden“ öffnet Ihr Mailprogramm mit Betreff und Text. [Name] ersetzen.', 'en' => '“Send” opens your mail app with subject and text filled in. Replace [Name].'],
            'linkedin'  => ['it' => 'Su LinkedIn funzionano i post in prima persona. Aggiunga una frase sua all’inizio.', 'de' => 'Auf LinkedIn wirken Beiträge in der Ich-Form. Setzen Sie einen eigenen Satz an den Anfang.', 'en' => 'First-person posts work on LinkedIn. Add a sentence of your own at the start.'],
            'sms'       => ['it' => 'Breve e personale — solo a persone che conosce.', 'de' => 'Kurz und persönlich — nur an Menschen, die Sie kennen.', 'en' => 'Short and personal — only to people you know.'],
        ],
        'vorlagen' => [
            'whatsapp' => [
                'persoenlich' => [
                    'titel' => ['it' => 'Messaggio personale', 'de' => 'Persönliche Nachricht', 'en' => 'Personal message'],
                    'text' => [
                        'it' => "Ciao! Mi avevi detto che ti serve un sito nuovo. Ti consiglio Vecom Design: dici cosa ti serve e sai il prezzo prima. Poi segui ogni passo nella tua area personale, finché il sito è online.\n\nDai un’occhiata qui: {link}\n\n(PS: collaboro con Vecom Design.)",
                        'de' => "Guten Tag! Sie hatten erwähnt, dass Sie eine neue Website brauchen. Ich kann Ihnen Vecom Design empfehlen: Sie sagen, was Sie brauchen, und kennen den Preis vorher. Danach sehen Sie jeden Schritt in Ihrem eigenen Bereich, bis die Seite online ist.\n\nHier können Sie es sich ansehen: {link}\n\n(PS: Ich bin Partner von Vecom Design.)",
                        'en' => "Hi! You mentioned you need a new website. I can recommend Vecom Design: you tell them what you need and know the price upfront. Then you follow every step in your own area until the site is live.\n\nHave a look here: {link}\n\n(PS: I’m a Vecom Design partner.)"],
                ],
                'status' => [
                    'titel' => ['it' => 'Stato / gruppo', 'de' => 'Status / Gruppe', 'en' => 'Status / group'],
                    'text' => [
                        'it' => "Conosci qualcuno a cui serve un sito web? 🌐\nVecom Design lo realizza: prezzo chiaro prima, seguito di persona.\n👉 {link}\n#adv",
                        'de' => "Kennen Sie jemanden, der eine Website braucht? 🌐\nVecom Design baut sie: klarer Preis vorher, persönlich begleitet.\n👉 {link}\n#Werbung",
                        'en' => "Know someone who needs a website? 🌐\nVecom Design builds it: clear price upfront, personal guidance.\n👉 {link}\n#ad"],
                ],
            ],
            'instagram' => [
                'post' => [
                    'titel' => ['it' => 'Post (didascalia)', 'de' => 'Beitrag (Bildtext)', 'en' => 'Post (caption)'],
                    'text' => [
                        'it' => "Oggi un buon sito è la vetrina di ogni attività. 🪟\n\nSe te ne serve uno: Vecom Design lo realizza per te. Dici cosa ti serve e sai il prezzo prima, poi vedi ogni passo nella tua area personale.\n\n🔗 Link nella mia bio: {link}\n\n#adv #sitoweb #webdesign #sicilia #piccoleimprese #partitaiva",
                        'de' => "Eine gute Website ist heute das Schaufenster jedes Betriebs. 🪟\n\nWenn Sie eine brauchen: Vecom Design baut sie für Sie. Sie sagen, was Sie brauchen, kennen den Preis vorher und sehen jeden Schritt in Ihrem persönlichen Bereich.\n\n🔗 Link in meiner Bio: {link}\n\n#Werbung #website #webdesign #sizilien #selbstständig #kleinunternehmen",
                        'en' => "A good website is every business’s shop window today. 🪟\n\nIf you need one: Vecom Design builds it for you. You say what you need, know the price upfront and follow every step in your personal area.\n\n🔗 Link in my bio: {link}\n\n#ad #website #webdesign #sicily #smallbusiness #entrepreneur"],
                ],
                'story' => [
                    'titel' => ['it' => 'Storia (testo + sticker)', 'de' => 'Story (Text + Sticker)', 'en' => 'Story (text + sticker)'],
                    'text' => [
                        'it' => "Testo sull’immagine:\nTi serve un sito web?\nTi consiglio Vecom Design.\n\nSticker «Link»: {link}\nIndicazione: #adv",
                        'de' => "Text aufs Bild:\nSie brauchen eine Website?\nIch empfehle Vecom Design.\n\nSticker „Link“: {link}\nKennzeichnung: #Werbung",
                        'en' => "Text on the image:\nNeed a website?\nI recommend Vecom Design.\n\n“Link” sticker: {link}\nLabel: #ad"],
                ],
                'reel' => [
                    'titel' => ['it' => 'Reel: copione 15 secondi', 'de' => 'Reel: Drehbuch 15 Sekunden', 'en' => 'Reel: 15-second script'],
                    'text' => [
                        'it' => "🎬 0–3 s, lei in camera: «La tua attività non ha un sito? Per tanti clienti allora non esisti.»\n🎬 3–10 s, mostri il telefono e scorri: «Io consiglio Vecom Design: dici cosa ti serve e sai il prezzo prima.»\n🎬 10–15 s, codice QR o cartolina finale: «Link nella mia bio.»\n\nDidascalia:\nIl tuo sito, con il prezzo chiaro prima. 🔗 Link in bio: {link}\n#adv #sitoweb #webdesign #sicilia",
                        'de' => "🎬 0–3 s, Sie in die Kamera: „Ihr Betrieb hat keine Website? Dann gibt es Sie für viele Kunden nicht.“\n🎬 3–10 s, Handy zeigen und scrollen: „Ich empfehle Vecom Design: Sie sagen, was Sie brauchen, und kennen den Preis vorher.“\n🎬 10–15 s, QR-Code oder Endkarte: „Link in meiner Bio.“\n\nBildtext:\nIhre Website, mit klarem Preis vorher. 🔗 Link in Bio: {link}\n#Werbung #website #webdesign #sizilien",
                        'en' => "🎬 0–3 s, you to camera: “Your business has no website? Then for many customers you don’t exist.”\n🎬 3–10 s, show your phone and scroll: “I recommend Vecom Design: you say what you need and know the price upfront.”\n🎬 10–15 s, QR code or end card: “Link in my bio.”\n\nCaption:\nYour website, with a clear price upfront. 🔗 Link in bio: {link}\n#ad #website #webdesign #sicily"],
                ],
            ],
            'facebook' => [
                'post' => [
                    'titel' => ['it' => 'Post sul profilo', 'de' => 'Beitrag im Profil', 'en' => 'Profile post'],
                    'text' => [
                        'it' => "Un consiglio per chi lavora in proprio o ha un’attività: 💡\n\nSe vi serve un sito nuovo o volete rinnovare quello vecchio, vi consiglio Vecom Design. Dite cosa vi serve, sapete il prezzo prima e vedete ogni passo nella vostra area personale.\n\nEcco il link: {link}\n\n#adv, collaboro con Vecom Design.",
                        'de' => "Kurzer Tipp für alle Selbstständigen und Betriebe in meinem Umfeld: 💡\n\nWer eine neue Website braucht oder die alte erneuern will, dem empfehle ich Vecom Design. Man sagt, was man braucht, kennt den Preis vorher und sieht jeden Schritt im eigenen Bereich.\n\nHier geht es direkt hin: {link}\n\n#Werbung, ich bin Partner von Vecom Design.",
                        'en' => "A quick tip for everyone self-employed or running a business: 💡\n\nIf you need a new website or want to refresh your old one, I recommend Vecom Design. You say what you need, know the price upfront and follow every step in your own area.\n\nHere’s the link: {link}\n\n#ad, I’m a Vecom Design partner."],
                ],
                'gruppe' => [
                    'titel' => ['it' => 'Risposta in un gruppo locale', 'de' => 'Antwort in einer lokalen Gruppe', 'en' => 'Reply in a local group'],
                    'text' => [
                        'it' => "Ciao a tutti! Visto che qui si chiede spesso di siti web: vi consiglio Vecom Design. Prezzo chiaro prima, seguiti di persona, a richiesta anche in più lingue.\n👉 {link}\n(Per trasparenza: collaboro con Vecom Design.)",
                        'de' => "Hallo zusammen! Weil hier öfter nach Webdesign gefragt wird: Ich kann Vecom Design empfehlen. Klarer Preis vorher, persönlich begleitet, auf Wunsch auch mehrsprachig.\n👉 {link}\n(Zur Transparenz: Ich bin Partner von Vecom Design.)",
                        'en' => "Hi all! Since people here often ask about web design: I can recommend Vecom Design. Clear price upfront, personal guidance, multilingual on request.\n👉 {link}\n(For transparency: I’m a Vecom Design partner.)"],
                ],
            ],
            'tiktok' => [
                'skript' => [
                    'titel' => ['it' => 'Video: copione 3 motivi', 'de' => 'Video: Drehbuch „3 Gründe“', 'en' => 'Video: “3 reasons” script'],
                    'text' => [
                        'it' => "Testo a schermo, 0–2 s: «3 motivi per cui la tua attività ha bisogno di un sito»\n1) «I clienti ti cercano prima su Google.»\n2) «Senza sito scelgono la concorrenza.»\n3) «Con Vecom Design sai il prezzo prima.»\nFinale: mostri il codice QR + «Link in bio»\n\nVoce o suono di tendenza, sottotitoli accesi.",
                        'de' => "Text im Bild, 0–2 s: „3 Gründe, warum Ihr Betrieb eine Website braucht“\n1) „Kunden suchen Sie zuerst bei Google.“\n2) „Ohne Website entscheiden sie sich für die Konkurrenz.“\n3) „Mit Vecom Design kennen Sie den Preis vorher.“\nSchluss: QR-Code zeigen + „Link in Bio“\n\nGesprochen oder Trend-Sound, Untertitel an.",
                        'en' => "On-screen text, 0–2 s: “3 reasons your business needs a website”\n1) “Customers look you up on Google first.”\n2) “Without a website they choose the competition.”\n3) “With Vecom Design you know the price upfront.”\nEnd: show the QR code + “Link in bio”\n\nVoiceover or trending sound, captions on."],
                ],
                'text' => [
                    'titel' => ['it' => 'Descrizione del video', 'de' => 'Beschreibung zum Video', 'en' => 'Video caption'],
                    'text' => [
                        'it' => "Il tuo sito senza sorprese sul prezzo 👇 Link in bio: {link}\n#adv #webdesign #sitoweb #sicilia #piccoleimprese",
                        'de' => "Ihre Website ohne Überraschungen beim Preis 👇 Link in Bio: {link}\n#Werbung #webdesign #website #selbstständig #sizilien",
                        'en' => "Your website with no surprises on price 👇 Link in bio: {link}\n#ad #webdesign #website #smallbusiness #sicily"],
                ],
            ],
            'email' => [
                'kontakt' => [
                    'titel' => ['it' => 'A un contatto di lavoro (Lei)', 'de' => 'An einen Geschäftskontakt (Sie)', 'en' => 'To a business contact'],
                    'betreff' => ['it' => 'Un consiglio per il suo sito web', 'de' => 'Eine Empfehlung für Ihre Website', 'en' => 'A recommendation for your website'],
                    'text' => [
                        'it' => "Gentile [Nome],\n\ndi recente abbiamo parlato del suo sito. Le consiglio Vecom Design: lei dice cosa le serve e conosce il prezzo prima di iniziare. Poi segue ogni passo nella sua area personale, finché il sito è online.\n\nPuò dare un’occhiata qui, senza impegno:\n{link}\n\nCordiali saluti\n{name}\n\nPS: collaboro con Vecom Design e ricevo una provvigione in caso di incarico.",
                        'de' => "Guten Tag [Name],\n\nwir hatten kürzlich über Ihren Internetauftritt gesprochen. Ich möchte Ihnen Vecom Design empfehlen: Sie sagen, was Sie brauchen, und kennen den Preis, bevor es losgeht. Danach sehen Sie jeden Schritt in Ihrem persönlichen Bereich, bis die Seite online ist.\n\nHier können Sie sich unverbindlich umsehen:\n{link}\n\nViele Grüße\n{name}\n\nPS: Ich bin Partner von Vecom Design und erhalte bei einem Auftrag eine Provision.",
                        'en' => "Dear [Name],\n\nwe recently talked about your website. I’d like to recommend Vecom Design: you say what you need and know the price before anything starts. Then you follow every step in your personal area until the site is live.\n\nYou can take a look here, without obligation:\n{link}\n\nBest regards\n{name}\n\nPS: I’m a Vecom Design partner and receive a commission if you place an order."],
                ],
                'kurz' => [
                    'titel' => ['it' => 'Breve, tra conoscenti (tu)', 'de' => 'Kurz, unter Bekannten (du)', 'en' => 'Short, between friends'],
                    'betreff' => ['it' => 'Sito web: il mio consiglio', 'de' => 'Website: mein Tipp', 'en' => 'Website: my tip'],
                    'text' => [
                        'it' => "Ciao [Nome],\n\ncome promesso, ecco il mio consiglio per il sito: Vecom Design. Prezzo chiaro prima, e ti seguono passo passo.\n\n{link}\n\nA presto\n{name}\n\n(PS: collaboro con loro.)",
                        'de' => "Hallo [Name],\n\nwie versprochen mein Tipp für deine Website: Vecom Design. Klarer Preis vorher, und sie begleiten dich Schritt für Schritt.\n\n{link}\n\nBis bald\n{name}\n\n(PS: Ich bin dort Partner.)",
                        'en' => "Hi [Name],\n\nas promised, here’s my tip for your website: Vecom Design. Clear price upfront, and they guide you step by step.\n\n{link}\n\nSee you soon\n{name}\n\n(PS: I’m a partner of theirs.)"],
                ],
            ],
            'linkedin' => [
                'post' => [
                    'titel' => ['it' => 'Post professionale', 'de' => 'Fachbeitrag', 'en' => 'Professional post'],
                    'text' => [
                        'it' => "Un pensiero da tante chiacchierate con imprenditori: il sito web viene spesso rimandato, perché nessuno sa quanto costerà alla fine.\n\nPer questo consiglio Vecom Design: si descrive cosa serve, si riceve il prezzo prima e si segue ogni passo nella propria area.\n\nSe ci state pensando: {link}\n\n#adv #webdesign #PMI #digitalizzazione",
                        'de' => "Ein Gedanke aus vielen Gesprächen mit Selbstständigen: Die Website wird oft aufgeschoben, weil niemand weiß, was sie am Ende kostet.\n\nDeshalb empfehle ich Vecom Design: Man beschreibt, was man braucht, bekommt den Preis vorher und verfolgt jeden Schritt im eigenen Bereich.\n\nWer gerade darüber nachdenkt: {link}\n\n#Werbung #Webdesign #KMU #Digitalisierung",
                        'en' => "A thought from many conversations with business owners: the website gets postponed because nobody knows what it will cost in the end.\n\nThat’s why I recommend Vecom Design: you describe what you need, get the price upfront and follow every step in your own area.\n\nIf you’re thinking about it: {link}\n\n#ad #webdesign #SMB #digitalisation"],
                ],
            ],
            'sms' => [
                'kurz' => [
                    'titel' => ['it' => 'SMS breve', 'de' => 'Kurze SMS', 'en' => 'Short text'],
                    'text' => [
                        'it' => "Ciao, sono {name}. Il mio consiglio per il tuo sito: Vecom Design, prezzo chiaro prima e seguito di persona. {link} (Collaboro con loro.)",
                        'de' => "Guten Tag, hier ist {name}. Mein Tipp für Ihre Website: Vecom Design, klarer Preis vorher und persönlich begleitet. {link} (Ich bin dort Partner.)",
                        'en' => "Hi, it’s {name}. My tip for your website: Vecom Design, clear price upfront and personal guidance. {link} (I’m a partner of theirs.)"],
                ],
            ],
        ],
        'sig' => [
            'zeile'   => ['it' => 'Ti serve un sito web? Te lo consiglio: Vecom Design.', 'de' => 'Sie brauchen eine Website? Meine Empfehlung: Vecom Design.', 'en' => 'Need a website? My recommendation: Vecom Design.'],
            'knopf'   => ['it' => 'Il tuo sito con Vecom Design', 'de' => 'Ihre Website mit Vecom Design', 'en' => 'Your website with Vecom Design'],
            'website' => ['it' => 'Sito web? Consigliato: Vecom Design', 'de' => 'Website gesucht? Empfohlen: Vecom Design', 'en' => 'Need a website? Recommended: Vecom Design'],
        ],
    ];

    /* ------------------------------------------------------------------------
       Bilder, Druck und Kurzvideos der Partner (26.09.2026). Kurz genug für
       ein Bild, das man im Vorbeiscrollen liest. {name} = Anzeigename.
       ------------------------------------------------------------------------ */
    public const PARTNER_MEDIEN = [
        'motive' => [
            'allgemein' => ['name' => ['it' => 'Per tutti', 'de' => 'Für alle', 'en' => 'For everyone'],
                'titel' => ['it' => 'Il tuo sito web. Prezzo chiaro prima.', 'de' => 'Ihre Website. Klarer Preis vorher.', 'en' => 'Your website. Clear price upfront.'],
                'unter' => ['it' => 'Seguito di persona, finché è online.', 'de' => 'Persönlich begleitet, bis sie online ist.', 'en' => 'Personally guided until it’s live.']],
            'gastro' => ['name' => ['it' => 'Ristoranti & bar', 'de' => 'Gastronomie', 'en' => 'Restaurants & bars'],
                'titel' => ['it' => 'Più ospiti ti trovano online.', 'de' => 'Mehr Gäste finden Sie online.', 'en' => 'More guests find you online.'],
                'unter' => ['it' => 'Siti web per ristoranti, bar e caffè.', 'de' => 'Websites für Restaurants, Bars und Cafés.', 'en' => 'Websites for restaurants, bars and cafés.']],
            'unterkunft' => ['name' => ['it' => 'Hotel & case vacanza', 'de' => 'Unterkünfte', 'en' => 'Stays'],
                'titel' => ['it' => 'La tua struttura, mostrata bene.', 'de' => 'Ihre Unterkunft, schön gezeigt.', 'en' => 'Your place, beautifully shown.'],
                'unter' => ['it' => 'Siti web per hotel, B&B e case vacanza.', 'de' => 'Websites für Hotels, B&Bs und Ferienwohnungen.', 'en' => 'Websites for hotels, B&Bs and holiday homes.']],
            'handwerk' => ['name' => ['it' => 'Artigiani & edilizia', 'de' => 'Handwerk & Bau', 'en' => 'Trades'],
                'titel' => ['it' => 'Un buon lavoro merita un buon sito.', 'de' => 'Gute Arbeit verdient eine gute Website.', 'en' => 'Good work deserves a good website.'],
                'unter' => ['it' => 'Per artigiani, imprese edili e installatori.', 'de' => 'Für Handwerk, Bau und Installateure.', 'en' => 'For tradespeople, builders and installers.']],
            'laden' => ['name' => ['it' => 'Negozi & beauty', 'de' => 'Laden & Beauty', 'en' => 'Shops & beauty'],
                'titel' => ['it' => 'Il tuo negozio, visibile anche online.', 'de' => 'Ihr Laden, jetzt auch online sichtbar.', 'en' => 'Your shop, visible online too.'],
                'unter' => ['it' => 'Per negozi, parrucchieri ed estetiste.', 'de' => 'Für Geschäfte, Friseure und Kosmetik.', 'en' => 'For shops, hairdressers and beauty salons.']],
            'praxis' => ['name' => ['it' => 'Studi & professionisti', 'de' => 'Praxen & Studios', 'en' => 'Practices & studios'],
                'titel' => ['it' => 'Fiducia prima del primo incontro.', 'de' => 'Vertrauen vor dem ersten Termin.', 'en' => 'Trust before the first appointment.'],
                'unter' => ['it' => 'Per studi, ambulatori, palestre e professionisti.', 'de' => 'Für Praxen, Studios und Selbstständige.', 'en' => 'For practices, studios and professionals.']],
        ],
        'punkte' => [
            ['it' => 'Dici cosa ti serve.', 'de' => 'Sie sagen, was Sie brauchen.', 'en' => 'You say what you need.'],
            ['it' => 'Sai il prezzo prima.', 'de' => 'Sie kennen den Preis vorher.', 'en' => 'You know the price upfront.'],
            ['it' => 'Ti seguiamo fino online.', 'de' => 'Wir begleiten Sie bis online.', 'en' => 'We guide you until it’s live.'],
        ],
        'empf'     => ['it' => 'Consigliato da {name}', 'de' => 'Empfohlen von {name}', 'en' => 'Recommended by {name}'],
        'scan'     => ['it' => 'Inquadra e inizia', 'de' => 'Scannen & loslegen', 'en' => 'Scan & get started'],
        'codewort' => ['it' => 'Codice', 'de' => 'Code', 'en' => 'Code'],
        'hook'     => ['it' => 'La tua attività non ha un sito?', 'de' => 'Ihr Betrieb hat keine Website?', 'en' => 'Your business has no website?'],
        'bio'      => ['it' => 'Inquadra il codice o link in bio', 'de' => 'Code scannen oder Link in Bio', 'en' => 'Scan the code or link in bio'],
        'werbung'  => ['it' => '#adv', 'de' => 'Werbung', 'en' => '#ad'],
        'sticker'  => ['it' => 'Sito web? Inquadra!', 'de' => 'Website? Scannen!', 'en' => 'Website? Scan me!'],
    ];

    /* ------------------------------------------------------------------------
       Website-Schnellcheck (26.09.2026): Texte des Berichts. Je Punkt ein
       Titel und je Stand ein Satz, den ein Betriebsinhaber ohne Fachwissen
       versteht. {wert} = Messwert (Sekunden, Tage, Jahr).
       ------------------------------------------------------------------------ */
    public const PARTNER_CHECK = [
        'wl_titel' => ['it' => 'Inoltrare il rapporto', 'de' => 'Bericht weiterleiten', 'en' => 'Forward this report'],
        'wl_wa' => ['it' => 'Su WhatsApp', 'de' => 'Per WhatsApp', 'en' => 'On WhatsApp'],
        'wl_mail' => ['it' => 'Per e-mail', 'de' => 'Per E-Mail', 'en' => 'By email'],
        'wl_nachricht' => ['it' => 'Ecco la verifica veloce del sito {host}: ', 'de' => 'Hier der Schnellcheck der Website {host}: ', 'en' => 'Here’s the quick check of the website {host}: '],
        'punkte' => [
            'erreichbar' => ['titel' => ['it' => 'Raggiungibile', 'de' => 'Erreichbar', 'en' => 'Reachable'],
                'schlecht' => ['it' => 'Il sito non era raggiungibile durante la verifica.', 'de' => 'Die Seite war bei der Prüfung nicht erreichbar.', 'en' => 'The site could not be reached during the check.']],
            'tempo' => ['titel' => ['it' => 'Velocità', 'de' => 'Ladezeit', 'en' => 'Speed'],
                'gut' => ['it' => 'Si carica in fretta ({wert}).', 'de' => 'Lädt schnell ({wert}).', 'en' => 'Loads quickly ({wert}).'],
                'hinweis' => ['it' => 'Si carica lentamente ({wert}). Molti visitatori non aspettano così tanto.', 'de' => 'Lädt spürbar langsam ({wert}). Viele Besucher warten nicht so lange.', 'en' => 'Noticeably slow ({wert}). Many visitors won’t wait that long.'],
                'schlecht' => ['it' => 'Molto lento ({wert}). Tanti visitatori da telefono se ne vanno prima che il sito appaia.', 'de' => 'Sehr langsam ({wert}). Viele Handy-Besucher sind weg, bevor die Seite da ist.', 'en' => 'Very slow ({wert}). Many mobile visitors leave before the page appears.']],
            'sicher' => ['titel' => ['it' => 'Sicurezza', 'de' => 'Sicherheit', 'en' => 'Security'],
                'gut' => ['it' => 'Collegamento cifrato (https).', 'de' => 'Verschlüsselte Verbindung (https).', 'en' => 'Encrypted connection (https).'],
                'hinweis' => ['it' => 'Il certificato di sicurezza scade tra {wert} giorni.', 'de' => 'Das Sicherheitszertifikat läuft in {wert} Tagen ab.', 'en' => 'The security certificate expires in {wert} days.'],
                'schlecht' => ['it' => 'Non cifrato: il browser mostra «Non sicuro».', 'de' => 'Nicht verschlüsselt: Der Browser zeigt „Nicht sicher“.', 'en' => 'Not encrypted: the browser shows “Not secure”.']],
            'handy' => ['titel' => ['it' => 'Telefono', 'de' => 'Handy', 'en' => 'Mobile'],
                'gut' => ['it' => 'Pensato per lo smartphone.', 'de' => 'Für Smartphones eingerichtet.', 'en' => 'Set up for smartphones.'],
                'schlecht' => ['it' => 'Non pensato per lo smartphone: sul telefono appare minuscolo.', 'de' => 'Nicht für Smartphones eingerichtet: Auf dem Handy erscheint die Seite winzig.', 'en' => 'Not set up for smartphones: on a phone the page looks tiny.']],
            'google' => ['titel' => ['it' => 'Google', 'de' => 'Google', 'en' => 'Google'],
                'gut' => ['it' => 'Titolo e descrizione per Google presenti.', 'de' => 'Titel und Beschreibung für Google vorhanden.', 'en' => 'Title and description for Google are in place.'],
                'hinweis' => ['it' => 'C’è il titolo ma manca la descrizione: Google mostra un pezzo di testo a caso.', 'de' => 'Titel da, aber keine Beschreibung: Google zeigt dann irgendeinen Textschnipsel.', 'en' => 'Title present but no description: Google then shows a random snippet.'],
                'schlecht' => ['it' => 'Nessun titolo: su Google il sito appare senza un nome chiaro.', 'de' => 'Kein Titel: Bei Google erscheint die Seite ohne klaren Namen.', 'en' => 'No title: on Google the site appears without a clear name.']],
            'aktuell' => ['titel' => ['it' => 'Aggiornato', 'de' => 'Aktualität', 'en' => 'Up to date'],
                'gut' => ['it' => 'Sembra curato (© {wert}).', 'de' => 'Wirkt gepflegt (© {wert}).', 'en' => 'Looks maintained (© {wert}).'],
                'hinweis' => ['it' => 'Ultimo © {wert}: non sembra del tutto aggiornato.', 'de' => 'Zuletzt © {wert}: wirkt nicht ganz aktuell.', 'en' => 'Last © {wert}: doesn’t look fully up to date.'],
                'hinweis_leer' => ['it' => 'Nessuna data trovata sulla pagina.', 'de' => 'Kein Datum auf der Seite gefunden.', 'en' => 'No date found on the page.'],
                'schlecht' => ['it' => 'Fermo al © {wert}: i visitatori lo considerano vecchio.', 'de' => 'Stand © {wert}: Besucher halten die Seite für veraltet.', 'en' => 'Stuck at © {wert}: visitors will think it’s outdated.']],
            'teilen' => ['titel' => ['it' => 'Condivisione', 'de' => 'Teilen', 'en' => 'Sharing'],
                'gut' => ['it' => 'Condiviso su WhatsApp o Facebook appare un’anteprima.', 'de' => 'Beim Teilen in WhatsApp oder Facebook erscheint ein Vorschaubild.', 'en' => 'Shared on WhatsApp or Facebook, a preview image appears.'],
                'hinweis' => ['it' => 'Condiviso su WhatsApp o Facebook non appare nessuna immagine.', 'de' => 'Beim Teilen in WhatsApp oder Facebook erscheint kein Bild.', 'en' => 'Shared on WhatsApp or Facebook, no image appears.']],
            /* Punkte 7–12 (28.09.2026, A10) */
            'rechtlich' => ['titel' => ['it' => 'Dati obbligatori', 'de' => 'Pflichtangaben', 'en' => 'Legal details'],
                'gut' => ['it' => 'Partita IVA/note legali e privacy presenti.', 'de' => 'Impressum und Datenschutz vorhanden.', 'en' => 'Legal notice and privacy page are in place.'],
                'hinweis' => ['it' => 'Trovato solo uno dei due: partita IVA/note legali o privacy.', 'de' => 'Nur eins von beiden gefunden: Impressum oder Datenschutz.', 'en' => 'Only one of the two found: legal notice or privacy page.'],
                'schlecht' => ['it' => 'Né partita IVA/note legali né privacy trovate sulla pagina iniziale.', 'de' => 'Weder Impressum noch Datenschutz auf der Startseite gefunden.', 'en' => 'Neither legal notice nor privacy page found on the home page.']],
            'telefon' => ['titel' => ['it' => 'Chiamare', 'de' => 'Anrufen', 'en' => 'Calling'],
                'gut' => ['it' => 'Il numero si tocca e parte la chiamata.', 'de' => 'Die Nummer lässt sich antippen, der Anruf startet sofort.', 'en' => 'The number can be tapped to call straight away.'],
                'hinweis' => ['it' => 'Il numero c’è, ma sul telefono non si può toccare per chiamare.', 'de' => 'Die Nummer steht da, lässt sich auf dem Handy aber nicht antippen.', 'en' => 'The number is there but can’t be tapped to call on a phone.'],
                'schlecht' => ['it' => 'Nessun numero di telefono trovato sulla pagina iniziale.', 'de' => 'Keine Telefonnummer auf der Startseite gefunden.', 'en' => 'No phone number found on the home page.']],
            'adresse' => ['titel' => ['it' => 'Dove siete', 'de' => 'Wo Sie sind', 'en' => 'Where you are'],
                'gut' => ['it' => 'Indirizzo o mappa presenti.', 'de' => 'Adresse oder Karte vorhanden.', 'en' => 'Address or map in place.'],
                'hinweis' => ['it' => 'Nessun indirizzo né mappa trovati sulla pagina iniziale.', 'de' => 'Keine Adresse und keine Karte auf der Startseite gefunden.', 'en' => 'No address or map found on the home page.']],
            'bilder' => ['titel' => ['it' => 'Immagini', 'de' => 'Bilder', 'en' => 'Images'],
                'gut' => ['it' => 'Le immagini si caricano in formato adatto al telefono.', 'de' => 'Die Bilder werden in einem Format geladen, das zum Handy passt.', 'en' => 'Images load in a format suited to phones.'],
                'gut_leer' => ['it' => 'Nessuna immagine pesante trovata.', 'de' => 'Keine schweren Bilder gefunden.', 'en' => 'No heavy images found.'],
                'hinweis' => ['it' => 'Immagini solo in JPG/PNG e in una sola misura: il telefono scarica più del necessario.', 'de' => 'Bilder nur als JPG/PNG in einer Größe: Das Handy lädt mehr als nötig.', 'en' => 'Images only as JPG/PNG in one size: phones download more than needed.']],
            'alt' => ['titel' => ['it' => 'Descrizioni delle immagini', 'de' => 'Bildbeschreibungen', 'en' => 'Image descriptions'],
                'gut' => ['it' => 'Le immagini hanno una descrizione (per Google e per chi non vede).', 'de' => 'Die Bilder haben Beschreibungen (für Google und für Menschen, die nicht sehen).', 'en' => 'Images have descriptions (for Google and for people who can’t see them).'],
                'gut_leer' => ['it' => 'Nessuna immagine senza descrizione trovata.', 'de' => 'Keine Bilder ohne Beschreibung gefunden.', 'en' => 'No images without a description found.'],
                'hinweis' => ['it' => 'Una parte delle immagini non ha descrizione ({wert} senza).', 'de' => 'Ein Teil der Bilder hat keine Beschreibung ({wert} ohne).', 'en' => 'Some images have no description ({wert} without).'],
                'schlecht' => ['it' => 'Quasi nessuna immagine ha una descrizione ({wert} senza): Google non capisce cosa mostrano.', 'de' => 'Kaum ein Bild hat eine Beschreibung ({wert} ohne): Google versteht nicht, was darauf zu sehen ist.', 'en' => 'Hardly any image has a description ({wert} without): Google can’t tell what they show.']],
            'zeiten' => ['titel' => ['it' => 'Orari', 'de' => 'Öffnungszeiten', 'en' => 'Opening hours'],
                'gut' => ['it' => 'Gli orari sono scritti in modo che Google li legga.', 'de' => 'Die Öffnungszeiten sind so hinterlegt, dass Google sie lesen kann.', 'en' => 'Opening hours are marked up so Google can read them.'],
                'hinweis' => ['it' => 'Gli orari ci sono, ma Google non li può leggere dal sito.', 'de' => 'Öffnungszeiten stehen da, Google kann sie von der Seite aber nicht lesen.', 'en' => 'Opening hours are there, but Google can’t read them from the site.'],
                'hinweis_leer' => ['it' => 'Nessun orario trovato sulla pagina iniziale.', 'de' => 'Keine Öffnungszeiten auf der Startseite gefunden.', 'en' => 'No opening hours found on the home page.']],
        ],
        'titel'   => ['it' => 'Verifica veloce: {host}', 'de' => 'Kurz-Check: {host}', 'en' => 'Quick check: {host}'],
        'lead'    => ['it' => 'Controllato automaticamente il {datum}. Dodici punti che visitatori e Google notano subito.', 'de' => 'Automatisch geprüft am {datum}. Zwölf Punkte, die Besucher und Google sofort merken.', 'en' => 'Checked automatically on {datum}. Six things visitors and Google notice right away.'],
        'fazit0'  => ['it' => 'Una base solida.', 'de' => 'Eine solide Grundlage.', 'en' => 'A solid foundation.'],
        'fazit1'  => ['it' => 'Qui si perde potenziale.', 'de' => 'Hier geht Potenzial verloren.', 'en' => 'Potential is being lost here.'],
        'fazit3'  => ['it' => 'Questo sito probabilmente le fa perdere clienti.', 'de' => 'Diese Seite kostet Sie wahrscheinlich Kunden.', 'en' => 'This site is probably costing you customers.'],
        'empf'    => ['it' => '{name} le consiglia Vecom Design', 'de' => '{name} empfiehlt Ihnen Vecom Design', 'en' => '{name} recommends Vecom Design to you'],
        'empf_text' => ['it' => 'Dice cosa le serve e conosce il prezzo prima. Poi segue ogni passo, finché il nuovo sito è online.', 'de' => 'Sie sagen, was Sie brauchen, und kennen den Preis vorher. Danach begleiten wir jeden Schritt, bis die neue Seite online ist.', 'en' => 'You say what you need and know the price upfront. Then we guide every step until the new site is live.'],
        'knopf'   => ['it' => 'Richiesta gratuita', 'de' => 'Kostenlos anfragen', 'en' => 'Free enquiry'],
        'klein'   => ['it' => 'Verifica automatica della pagina iniziale, non una perizia completa.', 'de' => 'Automatische Kurzprüfung der Startseite, kein vollständiges Gutachten.', 'en' => 'Automatic quick check of the home page, not a full audit.'],
        'weg'     => ['it' => 'Questo rapporto non è più disponibile.', 'de' => 'Dieser Bericht ist nicht mehr verfügbar.', 'en' => 'This report is no longer available.'],
    ];

    /* Öffentlicher Website-Check (27.09.2026): website-check.php. Zwei
       Häkchen, die nichts miteinander zu tun haben -- die Anfrage und die
       Werbeeinwilligung. Die Punkte selbst kommen aus PARTNER_CHECK. */
    /* Erste WhatsApp-Nachricht an einen Betrieb, der für WhatsApp eingewilligt
       hat (27.09.2026). Uwe schickt sie selbst aus seinem WhatsApp; die Seite
       füllt sie nur vor. Der Widerspruchs-Hinweis steht immer darin. */
    public const AKQ_WHATSAPP = [
        'hallo' => ['it' => 'Buongiorno, sono {inhaber} di {absender} – grazie per il consenso.', 'de' => 'Guten Tag, hier ist {inhaber} von {absender} – danke für Ihre Einwilligung.', 'en' => 'Hello, this is {inhaber} from {absender} – thank you for your consent.'],
        'analyse' => ['it' => 'Ecco l’analisi del sito di {firma}: {link}', 'de' => 'Hier ist die Analyse der Website von {firma}: {link}', 'en' => 'Here is the website analysis for {firma}: {link}'],
        'ohne' => ['it' => 'Le scrivo volentieri qui due o tre spunti per il sito di {firma}.', 'de' => 'Gern schicke ich Ihnen hier zwei, drei Hinweise zur Website von {firma}.', 'en' => 'Happy to send you two or three pointers on the {firma} website here.'],
        'stopp' => ['it' => 'Se non desidera più messaggi, risponda semplicemente «STOP».', 'de' => 'Wenn Sie keine Nachrichten mehr möchten, antworten Sie einfach „STOPP“.', 'en' => 'If you’d rather not get messages, just reply “STOP”.'],
    ];

    public const AKQ_CHECK = [
        'meta_titel' => ['it' => 'Verifica gratuita del sito web · Vecom Design', 'de' => 'Kostenloser Website-Check · Vecom Design', 'en' => 'Free website check · Vecom Design'],
        'meta_beschr' => ['it' => 'In pochi secondi: velocità, sicurezza, telefono, Google e aggiornamento del suo sito. Gratis e senza impegno.', 'de' => 'In wenigen Sekunden: Ladezeit, Sicherheit, Handy, Google und Aktualität Ihrer Website. Kostenlos und unverbindlich.', 'en' => 'In a few seconds: speed, security, mobile, Google and freshness of your website. Free, no strings attached.'],
        'marke_zeile' => ['it' => 'Verifica gratuita', 'de' => 'Kostenloser Check', 'en' => 'Free check'],
        'h1' => ['it' => 'Il suo sito le porta clienti o li fa scappare?', 'de' => 'Bringt Ihre Website Kunden – oder vertreibt sie?', 'en' => 'Is your website winning customers – or losing them?'],
        'lead' => ['it' => 'Inserisca l’indirizzo: in pochi secondi vede dodici punti che visitatori e Google notano subito. Gratis, senza impegno.', 'de' => 'Adresse eingeben: In wenigen Sekunden sehen Sie zwölf Punkte, die Besucher und Google sofort merken. Kostenlos und unverbindlich.', 'en' => 'Enter the address: in a few seconds you’ll see twelve points visitors and Google notice at once. Free, no strings attached.'],
        'was' => ['it' => 'Velocità · Sicurezza · Telefono · Google · Aggiornamento · Condivisione', 'de' => 'Ladezeit · Sicherheit · Handy · Google · Aktualität · Teilen', 'en' => 'Speed · Security · Mobile · Google · Freshness · Sharing'],
        'f_url' => ['it' => 'Indirizzo del sito', 'de' => 'Adresse der Website', 'en' => 'Website address'],
        'f_url_bsp' => ['it' => 'es. trattoria-rossi.it', 'de' => 'z. B. baeckerei-mueller.de', 'en' => 'e.g. my-business.com'],
        'f_firma' => ['it' => 'Nome dell’attività', 'de' => 'Name des Betriebs', 'en' => 'Business name'],
        'f_name' => ['it' => 'Il suo nome', 'de' => 'Ihr Name', 'en' => 'Your name'],
        'f_email' => ['it' => 'E-mail', 'de' => 'E-Mail', 'en' => 'Email'],
        'f_telefon' => ['it' => 'Telefono', 'de' => 'Telefon', 'en' => 'Phone'],
        'f_optional' => ['it' => 'facoltativo', 'de' => 'freiwillig', 'en' => 'optional'],
        'f_land' => ['it' => 'Paese', 'de' => 'Land', 'en' => 'Country'],
        'land_IT' => ['it' => 'Italia', 'de' => 'Italien', 'en' => 'Italy'],
        'land_DE' => ['it' => 'Germania', 'de' => 'Deutschland', 'en' => 'Germany'],
        'f_sprache' => ['it' => 'Lingua per la risposta', 'de' => 'Sprache für die Antwort', 'en' => 'Language for our reply'],
        'ausf' => ['it' => 'Mandatemi l’analisi completa e il link al mio spazio personale.', 'de' => 'Schicken Sie mir die ausführliche Analyse und den Link zu meinem persönlichen Bereich.', 'en' => 'Send me the full analysis and the link to my personal area.'],
        'ausf_hilfe' => ['it' => 'Riceve subito un’e-mail con il link: lì trova l’analisi. Nessuna pubblicità per questo.', 'de' => 'Sie bekommen sofort eine Mail mit dem Link, dort liegt die Analyse. Dafür keine Werbung.', 'en' => 'You get an email with the link right away; the analysis is there. No advertising for this.'],
        'mkt_vor' => ['it' => 'Inoltre acconsento:', 'de' => 'Außerdem bin ich einverstanden:', 'en' => 'I also agree:'],
        'mkt_hilfe' => ['it' => 'Facoltativo. Le mandiamo un’e-mail di conferma: conta solo il suo clic lì dentro.', 'de' => 'Freiwillig. Sie bekommen eine Bestätigungsmail – erst Ihr Klick darin zählt.', 'en' => 'Optional. You’ll get a confirmation email – only your click in it counts.'],
        'wa_vor' => ['it' => 'Oppure anche su WhatsApp:', 'de' => 'Oder auch per WhatsApp:', 'en' => 'Or also on WhatsApp:'],
        'wa_nummer' => ['it' => 'indicato', 'de' => 'der angegebenen Nummer', 'en' => 'the number given'],
        'wa_hilfe' => ['it' => 'Facoltativo. Il numero compare nell’e-mail di conferma: il suo clic vale per entrambi.', 'de' => 'Freiwillig. Die Nummer steht in der Bestätigungsmail – Ihr Klick gilt für beides.', 'en' => 'Optional. The number appears in the confirmation email – your click covers both.'],
        'f_whatsapp' => ['it' => 'Numero WhatsApp', 'de' => 'WhatsApp-Nummer', 'en' => 'WhatsApp number'],
        'wa_leer' => ['it' => 'vuoto = il telefono sopra', 'de' => 'leer = Telefon von oben', 'en' => 'empty = phone above'],
        'knopf' => ['it' => 'Verificare il sito ora', 'de' => 'Website jetzt prüfen', 'en' => 'Check website now'],
        'datenschutz' => ['it' => 'Usiamo i suoi dati solo per questa verifica e, se lo desidera, per la sua richiesta. Nome, e-mail e telefono vengono cancellati dopo 180 giorni, se non ne nasce nulla.', 'de' => 'Wir verwenden Ihre Angaben nur für diesen Check und – falls gewünscht – für Ihre Anfrage. Name, E-Mail und Telefon löschen wir nach 180 Tagen, wenn daraus nichts entsteht.', 'en' => 'We use your details only for this check and, if you wish, for your request. Name, email and phone are deleted after 180 days if nothing comes of it.'],
        'datenschutz_link' => ['it' => 'Privacy', 'de' => 'Datenschutz', 'en' => 'Privacy'],
        'fehler_angaben' => ['it' => 'Per favore inserisca il suo nome e il nome dell’attività.', 'de' => 'Bitte Ihren Namen und den Namen des Betriebs eintragen.', 'en' => 'Please enter your name and the business name.'],
        'fehler_email' => ['it' => 'Questo indirizzo e-mail non sembra corretto.', 'de' => 'Diese E-Mail-Adresse scheint nicht zu stimmen.', 'en' => 'This email address doesn’t look right.'],
        'fehler_adresse' => ['it' => 'Questo indirizzo non si può verificare. Controlli che sia scritto bene, es. trattoria-rossi.it', 'de' => 'Diese Adresse lässt sich nicht prüfen. Bitte die Schreibweise prüfen, z. B. baeckerei-mueller.de', 'en' => 'This address can’t be checked. Please check the spelling, e.g. my-business.com'],
        'fehler_whatsapp' => ['it' => 'Il numero WhatsApp non è leggibile. Lo inserisca con prefisso, es. +39 333 1234567.', 'de' => 'Die WhatsApp-Nummer ist nicht lesbar. Bitte mit Vorwahl eintragen, z. B. +49 171 1234567.', 'en' => 'The WhatsApp number can’t be read. Please include the country code, e.g. +39 333 1234567.'],
        'fehler_zuviel' => ['it' => 'Oggi ci sono già state molte verifiche. Riprovi domani.', 'de' => 'Heute gab es schon viele Prüfungen. Bitte morgen wieder versuchen.', 'en' => 'There have been many checks today. Please try again tomorrow.'],
        'fehler_warten' => ['it' => 'Un momento, per favore: riprovi tra qualche secondo.', 'de' => 'Einen Moment bitte – in ein paar Sekunden noch einmal absenden.', 'en' => 'One moment, please – send again in a few seconds.'],
        'fehler_aus' => ['it' => 'La verifica è momentaneamente disattivata.', 'de' => 'Der Check ist gerade ausgeschaltet.', 'en' => 'The check is switched off at the moment.'],
        'fehler_zeit' => ['it' => 'Per favore invii di nuovo il modulo.', 'de' => 'Bitte das Formular noch einmal absenden.', 'en' => 'Please send the form again.'],
        'r_lead' => ['it' => 'Verificato automaticamente il {datum}. Dodici punti che visitatori e Google notano subito.', 'de' => 'Automatisch geprüft am {datum}. Zwölf Punkte, die Besucher und Google sofort merken.', 'en' => 'Checked automatically on {datum}. Twelve points visitors and Google notice right away.'],
        'n_ausf' => ['it' => 'Grazie! Le abbiamo appena mandato un’e-mail con il link al suo spazio personale: lì trova l’analisi completa.', 'de' => 'Danke! Wir haben Ihnen gerade eine Mail mit dem Link zu Ihrem persönlichen Bereich geschickt – dort finden Sie die ausführliche Analyse.', 'en' => 'Thank you! We just emailed you the link to your personal area – the full analysis is there.'],
        'n_mkt' => ['it' => 'Per favore confermi l’e-mail che le abbiamo appena mandato. Senza il suo clic non le scriviamo.', 'de' => 'Bitte bestätigen Sie die E-Mail, die wir Ihnen gerade geschickt haben. Ohne Ihren Klick schreiben wir Ihnen nicht.', 'en' => 'Please confirm the email we just sent you. Without your click we won’t write to you.'],
        'n_mkt_zuviel' => ['it' => 'Per questo indirizzo oggi è già partita una conferma: controlli la posta.', 'de' => 'Für diese Adresse ging heute schon eine Bestätigung raus – bitte im Postfach nachsehen.', 'en' => 'A confirmation was already sent to this address today – please check your inbox.'],
        'weiter_titel' => ['it' => 'Quanto costerebbe un sito nuovo?', 'de' => 'Was würde eine neue Seite kosten?', 'en' => 'What would a new site cost?'],
        'weiter_text' => ['it' => 'In due minuti vede cosa serve alla sua attività, e il prezzo prima di decidere.', 'de' => 'In zwei Minuten sehen Sie, was Ihr Betrieb braucht – und den Preis, bevor Sie entscheiden.', 'en' => 'In two minutes you’ll see what your business needs – and the price before you decide.'],
        'weiter_knopf' => ['it' => 'Vedere esigenze e prezzo', 'de' => 'Bedarf und Preis ansehen', 'en' => 'See needs and price'],
        'nochmal' => ['it' => 'Verificare un altro sito', 'de' => 'Andere Website prüfen', 'en' => 'Check another website'],
    ];

    /* Terminbuchung (27.09.2026): termin.php und die Mails dazu. */
    public const AKQ_TERMIN = [
        'meta_titel' => ['it' => 'Prenotare una chiamata · Vecom Design', 'de' => 'Gesprächstermin buchen · Vecom Design', 'en' => 'Book a call · Vecom Design'],
        'zeile' => ['it' => '15 minuti, senza impegno', 'de' => '15 Minuten, unverbindlich', 'en' => '15 minutes, no obligation'],
        'h1' => ['it' => 'Parliamo del suo sito', 'de' => 'Sprechen wir über Ihre Website', 'en' => 'Let’s talk about your website'],
        'lead' => ['it' => 'Scelga un orario libero. La chiamiamo al telefono o in video, nella sua lingua.', 'de' => 'Wählen Sie eine freie Zeit. Wir rufen Sie an oder sprechen per Video, in Ihrer Sprache.', 'en' => 'Pick a free time. We’ll call you or meet by video, in your language.'],
        'zeitzone' => ['it' => 'Orari dell’Italia (come in Germania).', 'de' => 'Uhrzeiten in italienischer Zeit (gleich wie in Deutschland).', 'en' => 'Times in Italian time (same as Germany).'],
        'keine' => ['it' => 'Al momento non ci sono orari liberi. Ci scriva: le proponiamo noi un momento.', 'de' => 'Gerade sind keine Zeiten frei. Schreiben Sie uns – wir schlagen Ihnen einen Termin vor.', 'en' => 'No free times right now. Write to us and we’ll suggest one.'],
        'schritt_zeit' => ['it' => '1. Scelga l’orario', 'de' => '1. Uhrzeit wählen', 'en' => '1. Choose a time'],
        'schritt_daten' => ['it' => '2. I suoi dati', 'de' => '2. Ihre Angaben', 'en' => '2. Your details'],
        'f_name' => ['it' => 'Il suo nome', 'de' => 'Ihr Name', 'en' => 'Your name'],
        'f_firma' => ['it' => 'Attività', 'de' => 'Betrieb', 'en' => 'Business'],
        'f_email' => ['it' => 'E-mail', 'de' => 'E-Mail', 'en' => 'Email'],
        'f_telefon' => ['it' => 'Telefono', 'de' => 'Telefon', 'en' => 'Phone'],
        'f_optional' => ['it' => 'facoltativo', 'de' => 'freiwillig', 'en' => 'optional'],
        'f_thema' => ['it' => 'Di cosa vuole parlare?', 'de' => 'Worum soll es gehen?', 'en' => 'What would you like to discuss?'],
        'thema_neu' => ['it' => 'Un sito nuovo', 'de' => 'Eine neue Website', 'en' => 'A new website'],
        'thema_ueberarbeiten' => ['it' => 'Rinnovare il sito attuale', 'de' => 'Die jetzige Website erneuern', 'en' => 'Renewing my current website'],
        'thema_analyse' => ['it' => 'L’analisi del mio sito', 'de' => 'Die Analyse meiner Website', 'en' => 'The analysis of my website'],
        'thema_preis' => ['it' => 'Prezzi e tempi', 'de' => 'Preise und Ablauf', 'en' => 'Prices and process'],
        'thema_sonst' => ['it' => 'Altro', 'de' => 'Etwas anderes', 'en' => 'Something else'],
        'f_art' => ['it' => 'Come?', 'de' => 'Wie?', 'en' => 'How?'],
        'art_telefon' => ['it' => 'Telefono', 'de' => 'Telefon', 'en' => 'Phone'],
        'art_video' => ['it' => 'Videochiamata', 'de' => 'Videogespräch', 'en' => 'Video call'],
        'f_sprache' => ['it' => 'Lingua', 'de' => 'Sprache', 'en' => 'Language'],
        'f_nachricht' => ['it' => 'Qualcosa che dovremmo sapere prima?', 'de' => 'Gibt es etwas, das wir vorher wissen sollten?', 'en' => 'Anything we should know beforehand?'],
        'knopf' => ['it' => 'Prenotare l’appuntamento', 'de' => 'Termin buchen', 'en' => 'Book the call'],
        'hinweis' => ['it' => 'Usiamo i suoi dati solo per questo appuntamento. Riceve una conferma per e-mail con il link per disdire.', 'de' => 'Wir verwenden Ihre Angaben nur für diesen Termin. Sie bekommen eine Bestätigung per E-Mail mit einem Link zum Absagen.', 'en' => 'We use your details only for this appointment. You’ll get an email confirmation with a link to cancel.'],
        'fehler_zeit' => ['it' => 'Per favore scelga uno degli orari.', 'de' => 'Bitte eine der Uhrzeiten wählen.', 'en' => 'Please choose one of the times.'],
        'fehler_belegt' => ['it' => 'Questo orario è appena stato prenotato. Ne scelga un altro.', 'de' => 'Diese Zeit wurde gerade vergeben. Bitte eine andere wählen.', 'en' => 'That time was just taken. Please choose another.'],
        'fehler_angaben' => ['it' => 'Per favore inserisca nome ed e-mail corretti.', 'de' => 'Bitte Namen und eine gültige E-Mail-Adresse eintragen.', 'en' => 'Please enter your name and a valid email.'],
        'fehler_zuviel' => ['it' => 'Ha già un appuntamento prenotato. Per spostarlo usi il link nell’e-mail.', 'de' => 'Sie haben schon einen Termin gebucht. Zum Verschieben den Link in der E-Mail nutzen.', 'en' => 'You already have a booking. Use the link in the email to change it.'],
        'fehler_formular' => ['it' => 'Per favore invii di nuovo il modulo.', 'de' => 'Bitte das Formular noch einmal absenden.', 'en' => 'Please send the form again.'],
        'ok_titel' => ['it' => 'Appuntamento confermato', 'de' => 'Termin steht', 'en' => 'You’re booked'],
        'ok_text' => ['it' => 'Le abbiamo mandato una conferma a {email}.', 'de' => 'Die Bestätigung ist unterwegs an {email}.', 'en' => 'A confirmation is on its way to {email}.'],
        'kalender' => ['it' => 'Aggiungi al calendario', 'de' => 'In den Kalender', 'en' => 'Add to calendar'],
        'absagen' => ['it' => 'Disdire l’appuntamento', 'de' => 'Termin absagen', 'en' => 'Cancel the appointment'],
        'abgesagt' => ['it' => 'L’appuntamento è disdetto.', 'de' => 'Der Termin ist abgesagt.', 'en' => 'The appointment has been cancelled.'],
        'neu_buchen' => ['it' => 'Prenotare un altro orario', 'de' => 'Eine andere Zeit buchen', 'en' => 'Book another time'],
        'vorbei' => ['it' => 'Questo appuntamento è già passato.', 'de' => 'Dieser Termin liegt in der Vergangenheit.', 'en' => 'This appointment is in the past.'],
        'weg' => ['it' => 'Appuntamento non trovato.', 'de' => 'Termin nicht gefunden.', 'en' => 'Appointment not found.'],
        'mail_betreff' => ['it' => 'Appuntamento confermato: {zeit}', 'de' => 'Ihr Termin: {zeit}', 'en' => 'Your appointment: {zeit}'],
        'mail_text' => ['it' => "Buongiorno {name},\n\nil suo appuntamento con {absender} è confermato:\n\n{zeit} (ora italiana)\n{art} · {thema}\n\nSe non può, disdica qui: {link}\n\nA presto\n{inhaber}", 'de' => "Guten Tag {name},\n\nIhr Termin mit {absender} steht:\n\n{zeit} (italienische Zeit, wie in Deutschland)\n{art} · {thema}\n\nFalls es nicht passt, sagen Sie hier ab: {link}\n\nBis bald\n{inhaber}", 'en' => "Hello {name},\n\nyour appointment with {absender} is confirmed:\n\n{zeit} (Italian time)\n{art} · {thema}\n\nIf it doesn’t work for you, cancel here: {link}\n\nSee you soon\n{inhaber}"],
        'erinnerung_betreff' => ['it' => 'Promemoria: domani alle {uhr}', 'de' => 'Erinnerung: morgen um {uhr} Uhr', 'en' => 'Reminder: tomorrow at {uhr}'],
        'erinnerung_text' => ['it' => "Buongiorno {name},\n\nle ricordo il nostro appuntamento di domani:\n\n{zeit} (ora italiana)\n{art} · {thema}\n\nSe non può, disdica qui: {link}\n\nA domani\n{inhaber}", 'de' => "Guten Tag {name},\n\nkurz zur Erinnerung an unseren Termin morgen:\n\n{zeit} (italienische Zeit)\n{art} · {thema}\n\nFalls es nicht passt: {link}\n\nBis morgen\n{inhaber}", 'en' => "Hello {name},\n\na quick reminder of our appointment tomorrow:\n\n{zeit} (Italian time)\n{art} · {thema}\n\nIf it doesn’t work: {link}\n\nSee you tomorrow\n{inhaber}"],
        'absage_betreff' => ['it' => 'Appuntamento disdetto: {zeit}', 'de' => 'Termin abgesagt: {zeit}', 'en' => 'Appointment cancelled: {zeit}'],
        'absage_text' => ['it' => "Buongiorno {name},\n\npurtroppo dobbiamo disdire l’appuntamento del {zeit}. Scelga un nuovo orario qui: {buchen}\n\nCi scusi per il disagio.\n{inhaber}", 'de' => "Guten Tag {name},\n\nleider müssen wir den Termin am {zeit} absagen. Eine neue Zeit wählen Sie hier: {buchen}\n\nEntschuldigen Sie die Umstände.\n{inhaber}", 'en' => "Hello {name},\n\nunfortunately we have to cancel the appointment on {zeit}. Choose a new time here: {buchen}\n\nSorry for the inconvenience.\n{inhaber}"],
        'mail_satz' => ['it' => "\n\nOppure scelga subito un orario: {link}", 'de' => "\n\nOder wählen Sie direkt einen Termin: {link}", 'en' => "\n\nOr pick a time directly: {link}"],
        'tage' => ['it' => 'Lun,Mar,Mer,Gio,Ven,Sab,Dom', 'de' => 'Mo,Di,Mi,Do,Fr,Sa,So', 'en' => 'Mon,Tue,Wed,Thu,Fri,Sat,Sun'],
    ];

    /* Gesprächsleitfaden für Partner: kurz, auf dem Handy lesbar. */
    public const PARTNER_LEITFADEN = [
        ['titel' => ['it' => 'Come iniziare', 'de' => 'So fangen Sie an', 'en' => 'How to start'],
         'text' => ['it' => "Non vendere: chieda. «Come ti trovano i clienti nuovi?» apre più porte di qualsiasi presentazione.\nSe il sito c’è: faccia la verifica veloce insieme e guardi il risultato sul telefono.\nSe non c’è: «Hai mai pensato a un sito? Conosco chi lo fa con il prezzo chiaro prima.»",
                    'de' => "Nicht verkaufen, sondern fragen. „Wie finden Sie neue Kunden?“ öffnet mehr Türen als jede Präsentation.\nGibt es eine Website: den Schnellcheck gemeinsam machen und das Ergebnis auf dem Handy zeigen.\nGibt es keine: „Haben Sie schon einmal über eine Website nachgedacht? Ich kenne jemanden, bei dem man den Preis vorher weiß.“",
                    'en' => "Don’t sell — ask. “How do new customers find you?” opens more doors than any pitch.\nIf there’s a website: run the quick check together and show the result on your phone.\nIf there isn’t: “Ever thought about a website? I know someone where you know the price upfront.”"]],
        ['titel' => ['it' => 'Cinque domande utili', 'de' => 'Fünf gute Fragen', 'en' => 'Five good questions'],
         'text' => ['it' => "1. Da dove arrivano oggi i clienti nuovi?\n2. Cosa cercano su Google prima di venire da te?\n3. Cosa ti chiedono sempre al telefono? (orari, prezzi, menù…)\n4. Il sito attuale lo mostri volentieri?\n5. Cosa dovrebbe fare un visitatore: chiamare, prenotare, scrivere?",
                    'de' => "1. Woher kommen heute neue Kunden?\n2. Was suchen sie bei Google, bevor sie zu Ihnen kommen?\n3. Was fragen sie Sie am Telefon immer wieder? (Öffnungszeiten, Preise, Speisekarte …)\n4. Zeigen Sie Ihre jetzige Seite gern her?\n5. Was soll ein Besucher tun: anrufen, buchen, schreiben?",
                    'en' => "1. Where do new customers come from today?\n2. What do they search on Google before coming to you?\n3. What do they always ask on the phone? (hours, prices, menu…)\n4. Are you happy to show your current site?\n5. What should a visitor do: call, book, write?"]],
        ['titel' => ['it' => 'Il prezzo', 'de' => 'Der Preis', 'en' => 'The price'],
         'text' => ['it' => "Non dica cifre a memoria. Il questionario sul suo link mostra subito un prezzo indicativo; l’offerta precisa arriva entro un giorno lavorativo. Prima di un accordo scritto non si paga nulla.",
                    'de' => "Keine Zahlen aus dem Kopf nennen. Der Fragebogen hinter Ihrem Link zeigt sofort einen Richtpreis; das genaue Angebot kommt innerhalb eines Werktags. Vor einer schriftlichen Einigung wird nichts bezahlt.",
                    'en' => "Don’t quote figures from memory. The questionnaire behind your link shows a guide price right away; the exact quote follows within one working day. Nothing is paid before a written agreement."]],
        ['titel' => ['it' => 'Obiezioni frequenti', 'de' => 'Häufige Einwände', 'en' => 'Common objections'],
         'text' => ['it' => "«Costa troppo.» → Il prezzo lo sai prima e decidi tu. Guardarlo non costa niente.\n«Ho già Facebook.» → Facebook è in affitto: le regole le fa un altro. Il sito è tuo, e Google lo trova.\n«Non ho tempo.» → Rispondi a qualche domanda, il resto lo fanno loro. Vedi ogni passo sul telefono.\n«Ho già qualcuno.» → Perfetto. Se un giorno vuoi un confronto, il link resta valido.\n«Più avanti.» → Nessun problema: ti mando il link, lo apri quando vuoi.",
                    'de' => "„Zu teuer.“ → Den Preis kennen Sie vorher, und Sie entscheiden. Anschauen kostet nichts.\n„Ich habe doch Facebook.“ → Facebook ist gemietet: Die Regeln macht ein anderer. Die Website gehört Ihnen, und Google findet sie.\n„Keine Zeit.“ → Ein paar Fragen beantworten, den Rest macht Vecom Design. Sie sehen jeden Schritt auf dem Handy.\n„Ich habe schon jemanden.“ → Prima. Wenn Sie einmal vergleichen möchten: Der Link bleibt gültig.\n„Später.“ → Kein Problem: Ich schicke Ihnen den Link, Sie öffnen ihn, wann Sie möchten.",
                    'en' => "“Too expensive.” → You know the price first and you decide. Looking costs nothing.\n“I have Facebook.” → Facebook is rented: someone else makes the rules. The website is yours, and Google finds it.\n“No time.” → Answer a few questions, they do the rest. You follow every step on your phone.\n“I already have someone.” → Great. If you ever want to compare, the link stays valid.\n“Later.” → No problem: I’ll send you the link, open it whenever you like."]],
        ['titel' => ['it' => 'Per settore', 'de' => 'Je Branche', 'en' => 'By sector'],
         'text' => ['it' => "Ristoranti e bar: menù sempre aggiornato, orari, prenotazione, posizione su Google Maps.\nHotel e case vacanza: foto grandi, richiesta diretta senza commissioni del portale, più lingue.\nArtigiani ed edilizia: lavori fatti con foto, zona servita, modulo di richiesta.\nNegozi e beauty: orari, servizi e prezzi, prenotazione, recensioni.",
                    'de' => "Gastronomie: Speisekarte immer aktuell, Öffnungszeiten, Reservierung, Lage auf Google Maps.\nUnterkünfte: große Fotos, Direktanfrage ohne Portalprovision, mehrere Sprachen.\nHandwerk und Bau: Referenzen mit Fotos, Einzugsgebiet, Anfrageformular.\nLaden und Beauty: Öffnungszeiten, Leistungen und Preise, Terminbuchung, Bewertungen.",
                    'en' => "Restaurants and bars: always-current menu, opening hours, booking, location on Google Maps.\nStays: big photos, direct enquiries without portal commission, several languages.\nTrades: past work with photos, service area, enquiry form.\nShops and beauty: opening hours, services and prices, booking, reviews."]],
        ['titel' => ['it' => 'Chiudere', 'de' => 'Zum Abschluss', 'en' => 'Closing'],
         'text' => ['it' => "Tre strade, tutte contano per lei: mostri il codice QR, mandi il link o inserisca il contatto qui sotto («Ho un cliente per voi») — con il suo permesso.",
                    'de' => "Drei Wege, alle zählen für Sie: QR-Code zeigen, Link schicken oder den Kontakt unten eintragen („Ich habe einen Kunden für euch“) — mit seinem Einverständnis.",
                    'en' => "Three ways, all count for you: show the QR code, send the link, or enter the contact below (“I have a customer for you”) — with their permission."]],
    ];

    /* Wochen-Impuls per Hinweis aufs Handy: einer je Woche, der Reihe nach. */
    public const PARTNER_IMPULSE = [
        ['titel' => ['it' => 'Lunedì: 30 secondi per lo stato', 'de' => 'Montag: 30 Sekunden für den Status', 'en' => 'Monday: 30 seconds for your status'], 'text' => ['it' => 'Condivida oggi il testo per lo stato WhatsApp — è già pronto.', 'de' => 'Teilen Sie heute die Status-Vorlage in WhatsApp — sie ist fertig.', 'en' => 'Share the WhatsApp status template today — it’s ready.'], 'anker' => 'werbung'],
        ['titel' => ['it' => 'Conosce un’attività con un sito vecchio?', 'de' => 'Kennen Sie einen Betrieb mit alter Website?', 'en' => 'Know a business with an old website?'], 'text' => ['it' => 'Faccia la verifica veloce e gli mandi il rapporto.', 'de' => 'Machen Sie den Schnellcheck und schicken Sie ihm den Bericht.', 'en' => 'Run the quick check and send them the report.'], 'anker' => 'recherche'],
        ['titel' => ['it' => 'Una nuova immagine per le storie', 'de' => 'Ein neues Bild für Ihre Story', 'en' => 'A new image for your story'], 'text' => ['it' => 'Scelga il tema del suo settore e lo pubblichi.', 'de' => 'Wählen Sie das Motiv Ihrer Branche und posten Sie es.', 'en' => 'Pick the theme for your sector and post it.'], 'anker' => 'medien'],
        ['titel' => ['it' => 'Biglietti con il suo QR', 'de' => 'Visitenkarten mit Ihrem QR', 'en' => 'Business cards with your QR'], 'text' => ['it' => 'Ne stampi dieci per il prossimo incontro.', 'de' => 'Drucken Sie zehn für die nächste Begegnung.', 'en' => 'Print ten for your next meeting.'], 'anker' => 'medien'],
        ['titel' => ['it' => 'Attività vicino a lei', 'de' => 'Betriebe in Ihrer Nähe', 'en' => 'Businesses near you'], 'text' => ['it' => 'Guardi chi nel suo paese non ha ancora un sito.', 'de' => 'Sehen Sie nach, wer in Ihrem Ort noch keine Website hat.', 'en' => 'See who in your town has no website yet.'], 'anker' => 'recherche'],
        ['titel' => ['it' => 'Ogni e-mail fa pubblicità', 'de' => 'Jede Mail wirbt mit', 'en' => 'Every email advertises'], 'text' => ['it' => 'Imposti una volta la firma con il pulsante.', 'de' => 'Richten Sie einmal die Signatur mit Knopf ein.', 'en' => 'Set up the signature with button once.'], 'anker' => 'werbung'],
        ['titel' => ['it' => 'Un video in 10 secondi', 'de' => 'Ein Video in 10 Sekunden', 'en' => 'A video in 10 seconds'], 'text' => ['it' => 'Crei un Reel con il suo QR e lo pubblichi oggi.', 'de' => 'Erzeugen Sie ein Reel mit Ihrem QR und posten Sie es heute.', 'en' => 'Create a Reel with your QR and post it today.'], 'anker' => 'medien'],
        ['titel' => ['it' => 'Chi le ha chiesto di un sito?', 'de' => 'Wer hat Sie zuletzt nach einer Website gefragt?', 'en' => 'Who last asked you about a website?'], 'text' => ['it' => 'Inserisca il contatto: lo chiamiamo noi e conta per lei.', 'de' => 'Tragen Sie den Kontakt ein: Wir melden uns, und er zählt für Sie.', 'en' => 'Enter the contact: we get in touch and it counts for you.'], 'anker' => 'melden'],
    ];

    /* ------------------------------------------------------------------------
       Selbst gestaltete Empfehlungsseite (26.09.2026). Bausteine der Seite
       und Beschriftungen des Gestalters im Partner-Dashboard.
       ------------------------------------------------------------------------ */
    public const PARTNER_SEITE = [
        /* Gestalter als Assistent (03.10.2026, Uwe: Ja zu E1–E4) */
        'ga_ab' => ['it' => 'Seconda intestazione da testare (facoltativa)', 'de' => 'Zweite Überschrift zum Testen (freiwillig)', 'en' => 'Second headline to test (optional)'],
        'ga_ab_hilfe' => ['it' => 'La pagina mostra a turno le due intestazioni. Dopo 100 visite resta da sola quella con cui più visitatori hanno fatto qualcosa.', 'de' => 'Die Seite zeigt abwechselnd beide Überschriften. Nach 100 Besuchen bleibt von selbst die, nach der mehr Besucher etwas getan haben.', 'en' => 'The page shows both headlines in turn. After 100 visits the one after which more visitors did something stays on its own.'],
        'ga_ab_stand' => ['it' => 'Test in corso: A {ab} visite, {ae} attive · B {bb} visite, {be} attive', 'de' => 'Test läuft: A {ab} Besuche, {ae} aktiv · B {bb} Besuche, {be} aktiv', 'en' => 'Test running: A {ab} visits, {ae} active · B {bb} visits, {be} active'],
        'ga_ab_gewonnen' => ['it' => 'Ha vinto {v}: «{t}»', 'de' => 'Gewonnen hat {v}: „{t}“', 'en' => '{v} won: “{t}”'],
        'ga_ab_push_t' => ['it' => 'Test delle intestazioni concluso', 'de' => 'Überschriften-Test entschieden', 'en' => 'Headline test decided'],
        'ga_ab_push_x' => ['it' => 'Resta: «{t}»', 'de' => 'Es bleibt: „{t}“', 'en' => 'Staying: “{t}”'],
        'ga_s1' => ['it' => '1 · Stile', 'de' => '1 · Stil', 'en' => '1 · Style'],
        'ga_s2' => ['it' => '2 · Le sue parole', 'de' => '2 · Ihre Worte', 'en' => '2 · Your words'],
        'ga_s3' => ['it' => '3 · Cosa c’è sulla pagina', 'de' => '3 · Was auf der Seite steht', 'en' => '3 · What’s on the page'],
        'ga_weiter' => ['it' => 'Avanti', 'de' => 'Weiter', 'en' => 'Next'],
        'ga_zurueck' => ['it' => 'Indietro', 'de' => 'Zurück', 'en' => 'Back'],
        'ga_look_titel' => ['it' => 'Con un tocco: il suo settore', 'de' => 'Mit einem Tipp: Ihre Branche', 'en' => 'One tap: your industry'],
        'ga_look_text' => ['it' => 'Immagine, colori, carattere e testi si abbinano subito — poi può cambiare tutto.', 'de' => 'Titelbild, Farben, Schrift und Texte passen sofort zusammen — danach können Sie alles ändern.', 'en' => 'Cover image, colours, font and texts match right away — you can change everything afterwards.'],
        'ga_look_ersetzen' => ['it' => 'Sostituire anche i suoi testi?', 'de' => 'Auch Ihre eigenen Texte ersetzen?', 'en' => 'Replace your own texts too?'],
        'ga_look_ja' => ['it' => 'Sì, sostituisci', 'de' => 'Ja, ersetzen', 'en' => 'Yes, replace'],
        'ga_selbst' => ['it' => 'Scegliere da sé: stile, colore, immagine, carattere', 'de' => 'Selbst wählen: Vorlage, Farbe, Bild, Schrift', 'en' => 'Choose yourself: style, colour, image, font'],
        'ga_eigene' => ['it' => 'I suoi testi in {sprache}', 'de' => 'Ihre Texte auf {sprache}', 'en' => 'Your texts in {sprache}'],
        'ga_andere' => ['it' => 'Altre lingue', 'de' => 'Andere Sprachen', 'en' => 'Other languages'],
        'ga_auto_hinweis' => ['it' => 'Lasci vuoto: Vecom traduce il suo testo automaticamente (di solito entro un giorno). Ciò che scrive lei ha la precedenza.', 'de' => 'Leer lassen: Vecom übersetzt Ihren Text automatisch (meist innerhalb eines Tages). Was Sie selbst eintragen, geht vor.', 'en' => 'Leave empty: Vecom translates your text automatically (usually within a day). Whatever you enter yourself takes priority.'],
        'ga_auto_fertig' => ['it' => 'tradotto', 'de' => 'übersetzt', 'en' => 'translated'],
        'ga_auto_offen' => ['it' => 'in traduzione', 'de' => 'wird übersetzt', 'en' => 'being translated'],
        'ga_fertig' => ['it' => 'La sua pagina è pronta al {p} %', 'de' => 'Ihre Seite ist zu {p} % fertig', 'en' => 'Your page is {p} % done'],
        'ga_fehlt' => ['it' => 'Manca: ', 'de' => 'Es fehlt: ', 'en' => 'Missing: '],
        'ga_fertig_alles' => ['it' => 'La sua pagina è completa.', 'de' => 'Ihre Seite ist komplett.', 'en' => 'Your page is complete.'],
        'ga_f_foto' => ['it' => 'foto', 'de' => 'Foto', 'en' => 'photo'],
        'ga_f_satz' => ['it' => 'la sua frase', 'de' => 'Ihr Satz', 'en' => 'your sentence'],
        'ga_f_bild' => ['it' => 'immagine di copertina', 'de' => 'Titelbild', 'en' => 'cover image'],
        'ga_f_text' => ['it' => 'testo proprio', 'de' => 'eigener Text', 'en' => 'own text'],
        'ga_f_wa' => ['it' => 'numero WhatsApp', 'de' => 'WhatsApp-Nummer', 'en' => 'WhatsApp number'],
        'ga_f_gruss' => ['it' => 'messaggio vocale', 'de' => 'Sprachgruß', 'en' => 'voice greeting'],
        'ga_vorschau' => ['it' => 'Anteprima sul telefono', 'de' => 'Vorschau am Handy', 'en' => 'Preview on phone'],
        'ga_vorschau_live' => ['it' => 'Mostra subito le modifiche — si salva solo con «Salva».', 'de' => 'Zeigt Ihre Änderungen sofort — gespeichert wird erst mit „Speichern“.', 'en' => 'Shows your changes right away — nothing is saved until “Save”.'],
        'ga_schliessen' => ['it' => 'Chiudi', 'de' => 'Schließen', 'en' => 'Close'],
        'ga_hoch' => ['it' => 'Più in alto', 'de' => 'Nach oben', 'en' => 'Move up'],
        'ga_runter' => ['it' => 'Più in basso', 'de' => 'Nach unten', 'en' => 'Move down'],
        'vorlagen' => ['gold' => ['it' => 'Oro scuro', 'de' => 'Gold dunkel', 'en' => 'Dark gold'], 'hell' => ['it' => 'Chiaro elegante', 'de' => 'Hell elegant', 'en' => 'Light elegant'],
                       'mediterran' => ['it' => 'Mediterraneo', 'de' => 'Mediterran', 'en' => 'Mediterranean'], 'minimal' => ['it' => 'Minimal', 'de' => 'Minimal', 'en' => 'Minimal'],
                       'nacht' => ['it' => 'Notte', 'de' => 'Nacht', 'en' => 'Night'], 'espresso' => ['it' => 'Espresso', 'de' => 'Espresso', 'en' => 'Espresso'],
                       'bordeaux' => ['it' => 'Bordeaux', 'de' => 'Bordeaux', 'en' => 'Bordeaux'], 'salbei' => ['it' => 'Salvia chiaro', 'de' => 'Salbei hell', 'en' => 'Light sage'],
                       'limone' => ['it' => 'Limone', 'de' => 'Limone', 'en' => 'Lemon'], 'marmor' => ['it' => 'Marmo', 'de' => 'Marmor', 'en' => 'Marble']],
        'akzente' => ['gold' => ['it' => 'Oro', 'de' => 'Gold', 'en' => 'Gold'], 'terrakotta' => ['it' => 'Terracotta', 'de' => 'Terrakotta', 'en' => 'Terracotta'],
                      'meer' => ['it' => 'Mare', 'de' => 'Meer', 'en' => 'Sea'], 'salbei' => ['it' => 'Salvia', 'de' => 'Salbei', 'en' => 'Sage'],
                      'rose' => ['it' => 'Rosa', 'de' => 'Rosé', 'en' => 'Rose'], 'graphit' => ['it' => 'Grafite', 'de' => 'Graphit', 'en' => 'Graphite'],
                      'bordeaux' => ['it' => 'Bordeaux', 'de' => 'Bordeaux', 'en' => 'Bordeaux'], 'zitrone' => ['it' => 'Limone', 'de' => 'Zitrone', 'en' => 'Lemon'],
                      'petrol' => ['it' => 'Petrolio', 'de' => 'Petrol', 'en' => 'Teal'], 'lavendel' => ['it' => 'Lavanda', 'de' => 'Lavendel', 'en' => 'Lavender'],
                      'kupfer' => ['it' => 'Rame', 'de' => 'Kupfer', 'en' => 'Copper'], 'nachtblau' => ['it' => 'Blu notte', 'de' => 'Nachtblau', 'en' => 'Midnight blue']],
        'bilder' => ['' => ['it' => 'Nessuna immagine', 'de' => 'Kein Bild', 'en' => 'No image'], 'eigen' => ['it' => 'La mia foto', 'de' => 'Mein Foto', 'en' => 'My photo'],
                     'gastro' => ['it' => 'Ristorante', 'de' => 'Restaurant', 'en' => 'Restaurant'], 'hotel' => ['it' => 'Hotel & casa vacanze', 'de' => 'Hotel & Ferienhaus', 'en' => 'Hotel & holiday home'],
                     'friseur' => ['it' => 'Parrucchiere & beauty', 'de' => 'Friseur & Beauty', 'en' => 'Hair & beauty'], 'auto' => ['it' => 'Auto & officina', 'de' => 'Auto & Werkstatt', 'en' => 'Cars & garage'],
                     'kueche' => ['it' => 'Artigianato & cucine', 'de' => 'Handwerk & Küchen', 'en' => 'Craft & kitchens'], 'wein' => ['it' => 'Vino & prodotti', 'de' => 'Wein & Produkte', 'en' => 'Wine & products'],
                     'mode' => ['it' => 'Moda & negozio', 'de' => 'Mode & Laden', 'en' => 'Fashion & shop'], 'schmuck' => ['it' => 'Gioielli', 'de' => 'Schmuck', 'en' => 'Jewellery'],
                     'transport' => ['it' => 'Trasporti', 'de' => 'Transport', 'en' => 'Transport'],
                     'holz' => ['it' => 'Falegnameria & legno', 'de' => 'Tischlerei & Holz', 'en' => 'Carpentry & wood'], 'salon_modern' => ['it' => 'Salone moderno', 'de' => 'Salon modern', 'en' => 'Modern salon'],
                     'salon_klassisch' => ['it' => 'Salone classico', 'de' => 'Salon klassisch', 'en' => 'Classic salon'], 'weisswein' => ['it' => 'Vino bianco & cantina', 'de' => 'Weißwein & Kellerei', 'en' => 'White wine & winery'],
                     'olio' => ['it' => 'Olio & gastronomia', 'de' => 'Olivenöl & Feinkost', 'en' => 'Olive oil & deli'], 'uhren' => ['it' => 'Orologi & oro rosa', 'de' => 'Uhren & Roségold', 'en' => 'Watches & rose gold'],
                     'autohaus' => ['it' => 'Concessionaria', 'de' => 'Autohaus', 'en' => 'Car dealer'], 'spedition' => ['it' => 'Spedizioni', 'de' => 'Spedition', 'en' => 'Freight'],
                     'chauffeur' => ['it' => 'NCC & noleggio', 'de' => 'Chauffeur & Mietwagen', 'en' => 'Chauffeur & car hire'], 'gastro_hell' => ['it' => 'Ristorante chiaro', 'de' => 'Restaurant hell', 'en' => 'Bright restaurant'],
                     'villa_garten' => ['it' => 'Casa vacanze & giardino', 'de' => 'Ferienhaus & Garten', 'en' => 'Holiday home & garden'], 'agriturismo' => ['it' => 'Agriturismo & ospitalità', 'de' => 'Agriturismo & Gastlichkeit', 'en' => 'Agriturismo & hospitality']],
        'g_schrift' => ['it' => 'Carattere dei titoli', 'de' => 'Schrift der Überschriften', 'en' => 'Heading font'],
        'schriften' => ['modern' => ['it' => 'Moderno', 'de' => 'Modern', 'en' => 'Modern'], 'klassisch' => ['it' => 'Classico', 'de' => 'Klassisch', 'en' => 'Classic'], 'elegant' => ['it' => 'Elegante', 'de' => 'Elegant', 'en' => 'Elegant']],
        'g_kopf' => ['it' => 'Immagine di copertina', 'de' => 'Titelbild zeigen', 'en' => 'Cover image style'],
        'koepfe' => ['karte' => ['it' => 'Immagine sopra il testo', 'de' => 'Bild über dem Text', 'en' => 'Image above the text'], 'buehne' => ['it' => 'Titolo sull’immagine', 'de' => 'Überschrift auf dem Bild', 'en' => 'Heading on the image']],
        'g_vorschlag' => ['it' => 'Si abbina a questa immagine:', 'de' => 'Passt zu diesem Bild:', 'en' => 'Goes with this image:'],
        'g_uebernehmen' => ['it' => 'Applica', 'de' => 'Übernehmen', 'en' => 'Apply'],
        /* Runde 2 (28.09.2026, Uwe: Ja zu R1–R7) */
        'g_branche' => ['it' => 'Testi per questo settore:', 'de' => 'Texte für diese Branche:', 'en' => 'Texts for this sector:'],
        'g_branche_knopf' => ['it' => 'Inserire in tutte e tre le lingue', 'de' => 'In alle drei Sprachen einsetzen', 'en' => 'Fill in all three languages'],
        'g_branche_ersetzen' => ['it' => 'Sostituire davvero i suoi testi?', 'de' => 'Eigene Texte wirklich ersetzen?', 'en' => 'Really replace your texts?'],
        'g_branche_fertig' => ['it' => 'Inseriti. Controlli e salvi.', 'de' => 'Eingesetzt. Bitte ansehen und speichern.', 'en' => 'Filled in. Please check and save.'],
        'g_b_kurzcheck' => ['it' => 'Verifica del sito direttamente sulla pagina', 'de' => 'Website-Check direkt auf der Seite', 'en' => 'Website check right on the page'],
        'g_b_preise' => ['it' => 'Quanto costa un sito? (prezzi veri)', 'de' => 'Was kostet eine Website? (echte Preise)', 'en' => 'What does a website cost? (real prices)'],
        'g_gruss' => ['it' => 'Messaggio vocale personale (fino a 30 secondi)', 'de' => 'Persönliche Sprachnachricht (bis 30 Sekunden)', 'en' => 'Personal voice message (up to 30 seconds)'],
        'g_gruss_hilfe' => ['it' => 'Dica con parole sue perché consiglia Vecom Design. Appare accanto alla sua foto.', 'de' => 'Sagen Sie in eigenen Worten, warum Sie Vecom Design empfehlen. Erscheint neben Ihrem Foto.', 'en' => 'Say in your own words why you recommend Vecom Design. It appears next to your photo.'],
        'g_gruss_auf' => ['it' => 'Registrare', 'de' => 'Aufnehmen', 'en' => 'Record'],
        'g_gruss_stop' => ['it' => 'Fermare', 'de' => 'Aufnahme beenden', 'en' => 'Stop recording'],
        'g_gruss_neu' => ['it' => 'Registrazione pronta: premere «Salvare».', 'de' => 'Aufnahme fertig: jetzt „Speichern“ drücken.', 'en' => 'Recording ready: now press “Save”.'],
        'g_gruss_datei' => ['it' => 'Oppure scegliere un file audio (MP3, M4A, OGG, WebM)', 'de' => 'Oder Audiodatei wählen (MP3, M4A, OGG, WebM)', 'en' => 'Or choose an audio file (MP3, M4A, OGG, WebM)'],
        'g_gruss_jetzt' => ['it' => 'Il suo messaggio attuale:', 'de' => 'Ihre aktuelle Nachricht:', 'en' => 'Your current message:'],
        'g_gruss_weg' => ['it' => 'Eliminare il messaggio vocale', 'de' => 'Sprachnachricht löschen', 'en' => 'Delete voice message'],
        'gruss_gross' => ['it' => 'Il messaggio vocale è troppo lungo o troppo grande (al massimo 30 secondi, 1 MB).', 'de' => 'Die Sprachnachricht ist zu lang oder zu groß (höchstens 30 Sekunden, 1 MB).', 'en' => 'The voice message is too long or too large (30 seconds, 1 MB at most).'],
        'gruss_art' => ['it' => 'Questo file non è una registrazione audio. Usi MP3, M4A, OGG o WebM.', 'de' => 'Diese Datei ist keine Tonaufnahme. Bitte MP3, M4A, OGG oder WebM.', 'en' => 'This file is not an audio recording. Please use MP3, M4A, OGG or WebM.'],
        'gruss_titel' => ['it' => 'Un messaggio di {name}', 'de' => 'Eine Nachricht von {name}', 'en' => 'A message from {name}'],
        'wunsch_frage' => ['it' => 'Di cosa ha bisogno?', 'de' => 'Was brauchen Sie?', 'en' => 'What do you need?'],
        'wuensche' => ['neu' => ['it' => 'Nuovo sito', 'de' => 'Neue Website', 'en' => 'New website'], 'ueberarbeitung' => ['it' => 'Rinnovare il sito', 'de' => 'Bestehende überarbeiten', 'en' => 'Redo my site'],
                       'shop' => ['it' => 'Negozio online', 'de' => 'Online-Shop', 'en' => 'Online shop'], 'unsicher' => ['it' => 'Non so ancora', 'de' => 'Noch unsicher', 'en' => 'Not sure yet']],
        'wunsch_weiter' => ['it' => 'Bene. Dove le mandiamo il link?', 'de' => 'Gut. Wohin dürfen wir den Link schicken?', 'en' => 'Great. Where should we send the link?'],
        'termine_frage' => ['it' => 'Preferisce parlarne? Prossimi orari liberi:', 'de' => 'Lieber sprechen? Nächste freie Termine:', 'en' => 'Rather talk? Next free times:'],
        'kc_titel' => ['it' => 'Com’è messo il suo sito attuale?', 'de' => 'Wie steht Ihre jetzige Website da?', 'en' => 'How is your current website doing?'],
        'kc_text' => ['it' => 'Inserisca l’indirizzo: in pochi secondi vede dodici punti con semaforo. Senza nome, senza e-mail.', 'de' => 'Adresse eingeben: In wenigen Sekunden sehen Sie zwölf Punkte als Ampel. Ohne Namen, ohne E-Mail.', 'en' => 'Enter the address: in a few seconds you see twelve points as traffic lights. No name, no email.'],
        'kc_feld' => ['it' => 'Indirizzo del sito, per es. trattoria-rossi.it', 'de' => 'Adresse der Website, z. B. trattoria-rossi.it', 'en' => 'Website address, e.g. trattoria-rossi.it'],
        'kc_knopf' => ['it' => 'Verificare', 'de' => 'Prüfen', 'en' => 'Check'],
        'kc_ergebnis' => ['it' => 'Risultato per {host}', 'de' => 'Ergebnis für {host}', 'en' => 'Result for {host}'],
        'kc_stand' => ['gut' => ['it' => 'va bene', 'de' => 'gut', 'en' => 'good'], 'hinweis' => ['it' => 'da migliorare', 'de' => 'verbesserbar', 'en' => 'could be better'], 'schlecht' => ['it' => 'problema', 'de' => 'Problem', 'en' => 'problem']],
        'kc_mehr' => ['it' => 'Ricevere il risultato completo con spiegazioni', 'de' => 'Ausführliches Ergebnis mit Erklärungen erhalten', 'en' => 'Get the full result with explanations'],
        'kc_fehler' => ['adresse' => ['it' => 'Questo indirizzo non sembra un sito raggiungibile. Lo controlli.', 'de' => 'Diese Adresse sieht nicht nach einer erreichbaren Website aus. Bitte prüfen.', 'en' => 'This address doesn’t look like a reachable website. Please check it.'],
                        'warten' => ['it' => 'Un attimo: può verificare di nuovo tra pochi secondi.', 'de' => 'Einen Moment: In ein paar Sekunden können Sie wieder prüfen.', 'en' => 'One moment: you can check again in a few seconds.'],
                        'zuviel' => ['it' => 'Per oggi sono state fatte molte verifiche. Usi la verifica completa qui sotto.', 'de' => 'Für heute wurde schon oft geprüft. Nutzen Sie den ausführlichen Check darunter.', 'en' => 'Many checks have been run today. Please use the full check below.']],
        /* Ja in einem Schritt (28.09.2026, Z2) */
        'kc_ja_titel' => ['it' => 'Vuole l’analisi completa e il suo spazio personale?', 'de' => 'Ausführliche Analyse und persönlicher Bereich?', 'en' => 'Full analysis and your personal area?'],
        'kc_ja_text' => ['it' => 'Le mandiamo l’analisi dettagliata con i consigli. Prima riceve solo una e-mail di conferma.', 'de' => 'Wir schicken Ihnen die ausführliche Analyse mit Tipps. Zuerst kommt nur eine Bestätigungsmail.', 'en' => 'We send you the detailed analysis with tips. First you only receive a confirmation email.'],
        'kc_ja_email' => ['it' => 'La sua e-mail', 'de' => 'Ihre E-Mail-Adresse', 'en' => 'Your email address'],
        'kc_ja_wa' => ['it' => 'Anche su WhatsApp', 'de' => 'Auch per WhatsApp', 'en' => 'Also on WhatsApp'],
        'kc_ja_knopf' => ['it' => 'Ricevere l’analisi completa', 'de' => 'Ausführliche Analyse erhalten', 'en' => 'Get the full analysis'],
        'kc_ja_ok' => ['it' => 'Fatto! Le abbiamo scritto a {email}: tocchi il link di conferma nell’e-mail.', 'de' => 'Erledigt! Wir haben an {email} geschrieben: Tippen Sie auf den Bestätigungslink in der Mail.', 'en' => 'Done! We wrote to {email}: tap the confirmation link in the email.'],
        'kc_ja_fehler' => ['adresse' => ['it' => 'L’indirizzo del sito non è leggibile.', 'de' => 'Die Adresse der Website ist nicht lesbar.', 'en' => 'The website address cannot be read.'],
                           'email' => ['it' => 'Controlli l’e-mail e la spunta.', 'de' => 'Bitte E-Mail und Häkchen prüfen.', 'en' => 'Please check the email and the tick box.'],
                           'whatsapp' => ['it' => 'Il numero WhatsApp non è leggibile.', 'de' => 'Die WhatsApp-Nummer ist nicht lesbar.', 'en' => 'The WhatsApp number cannot be read.'],
                           'zuviel' => ['it' => 'Troppe richieste oggi: riprovi domani.', 'de' => 'Heute zu viele Anfragen: bitte morgen noch einmal.', 'en' => 'Too many requests today: please try tomorrow.'],
                           'gesperrt' => ['it' => 'Per questo indirizzo non è possibile.', 'de' => 'Für diese Adresse nicht möglich.', 'en' => 'Not possible for this address.'],
                           'zeit' => ['it' => 'Qualcosa non ha funzionato: riprovi.', 'de' => 'Etwas hat nicht geklappt: bitte noch einmal.', 'en' => 'Something went wrong: please try again.']],
        'preise_titel' => ['it' => 'Quanto costa un sito?', 'de' => 'Was kostet eine Website?', 'en' => 'What does a website cost?'],
        'preise_faelle' => ['f1' => ['it' => 'Una pagina, una lingua', 'de' => 'Eine Seite, eine Sprache', 'en' => 'One page, one language'], 'f2' => ['it' => 'Cinque pagine', 'de' => 'Fünf Seiten', 'en' => 'Five pages'],
                            'f3' => ['it' => 'Cinque pagine in tre lingue', 'de' => 'Fünf Seiten in drei Sprachen', 'en' => 'Five pages in three languages'], 'f4' => ['it' => 'Cinque pagine con negozio online', 'de' => 'Fünf Seiten mit Online-Shop', 'en' => 'Five pages with online shop']],
        'preise_hinweis' => ['it' => 'Prezzi una tantum, se testi e immagini li fornisce lei. Il suo prezzo esatto lo vede dopo poche domande.', 'de' => 'Einmalpreise, wenn Texte und Bilder von Ihnen kommen. Ihren genauen Preis sehen Sie nach ein paar Fragen.', 'en' => 'One-off prices when you supply the texts and images. You’ll see your exact price after a few questions.'],
        'preise_betreuung' => ['it' => 'Assistenza su richiesta: {preis} al mese', 'de' => 'Betreuung auf Wunsch: {preis} im Monat', 'en' => 'Care on request: {preis} a month'],
        'preise_selbst' => ['it' => 'Calcoli subito: il prezzo cambia a ogni clic', 'de' => 'Gleich ausrechnen: Der Preis ändert sich bei jedem Klick', 'en' => 'Work it out now: the price updates with every click'],
        'preise_knopf' => ['it' => 'Calcolare il mio prezzo', 'de' => 'Meinen Preis berechnen', 'en' => 'Work out my price'],
        'arbeiten_titel' => ['it' => 'Alcuni nostri lavori', 'de' => 'Einige unserer Arbeiten', 'en' => 'Some of our work'],
        'arbeiten' => [
            'cavaleri' => ['name' => 'Cavaleri Srl', 'it' => 'Trasporti e logistica · Caltanissetta, dal 1974', 'de' => 'Transport & Logistik · Caltanissetta, seit 1974', 'en' => 'Transport & logistics · Caltanissetta, since 1974'],
            'jonika' => ['name' => 'Jonika Venturis', 'it' => 'Autrice · libri per bambini', 'de' => 'Autorin · Kinderbücher', 'en' => 'Author · children’s books'],
            'mensaena' => ['name' => 'Mensaena', 'it' => 'Piattaforma senza scopo di lucro · aiuto tra vicini', 'de' => 'Gemeinnützige Plattform · Nachbarschaftshilfe', 'en' => 'Non-profit platform · neighbourhood help'],
            'trendonix' => ['name' => 'Trendonix', 'it' => 'Casa editrice · serie di libri illustrati', 'de' => 'Buchverlag · illustrierte Buchreihe', 'en' => 'Publisher · illustrated book series'],
            'drehesum' => ['name' => 'Dreh es um', 'it' => 'Progetto di ricerca · informazione per i consumatori', 'de' => 'Recherche-Projekt · Verbraucheraufklärung', 'en' => 'Research project · consumer information'],
        ],
        'arbeiten_ansehen' => ['it' => 'Vedere il sito', 'de' => 'Website ansehen', 'en' => 'View the website'],
        'neuer_tab' => ['it' => '(si apre in una nuova scheda)', 'de' => '(öffnet in neuem Tab)', 'en' => '(opens in a new tab)'],
        'film_titel' => ['it' => 'Farsi vedere', 'de' => 'Sichtbar werden', 'en' => 'Get seen'],
        'film_text' => ['it' => 'Di notte i negozi sono chiusi. Online no: un buon sito lavora anche quando lei dorme.', 'de' => 'Nachts sind die Läden zu. Online nicht: Eine gute Website arbeitet auch, wenn Sie schlafen.', 'en' => 'At night the shops are closed. Online they are not: a good website works even while you sleep.'],
        'film_alt_sichtbar_werden' => ['it' => 'Film: un vicolo siciliano di notte, i negozi si illuminano uno dopo l’altro, sui telefoni compaiono i siti; alla fine il logo Vecom Design in oro.', 'de' => 'Film: eine sizilianische Gasse bei Nacht, die Läden leuchten nacheinander auf, auf Telefonen erscheinen die Websites; am Ende das Vecom-Design-Logo in Gold.', 'en' => 'Film: a Sicilian alley at night, the shops light up one after another, websites appear on phones; at the end the Vecom Design logo in gold.'],
        'film_alt_showreel' => ['it' => 'Showreel Vecom Design: siti web, negozi online, esperienze 3D e branding, con voce narrante.', 'de' => 'Showreel von Vecom Design: Websites, Online-Shops, 3D-Erlebnisse und Branding, mit Sprecherstimme.', 'en' => 'Vecom Design showreel: websites, online shops, 3D experiences and branding, with voice-over.'],
        'showreel_titel' => ['it' => 'Cosa facciamo in 48 secondi', 'de' => 'Was wir machen, in 48 Sekunden', 'en' => 'What we do, in 48 seconds'],
        'showreel_text' => ['it' => 'Siti web, negozi online, 3D e marchio: tutto da un unico interlocutore.', 'de' => 'Websites, Online-Shops, 3D und Marke: alles aus einer Hand.', 'en' => 'Websites, online shops, 3D and brand: all from one partner.'],
        'g_film_wahl' => ['it' => 'Quale film?', 'de' => 'Welcher Film?', 'en' => 'Which film?'],
        'g_film_sichtbar_werden' => ['it' => '«Farsi vedere» (45 s, cinematografico, voce nella lingua della pagina)', 'de' => '„Sichtbar werden“ (45 s, filmisch, Sprecher in der Seitensprache)', 'en' => '“Get seen” (45 s, cinematic, voice-over in the page language)'],
        'g_film_showreel' => ['it' => 'Showreel (48 s, voce nella lingua della pagina)', 'de' => 'Showreel (48 s, Sprecher in der Seitensprache)', 'en' => 'Showreel (48 s, voice-over in the page language)'],
        'film_ton_an' => ['it' => 'Audio sì', 'de' => 'Ton an', 'en' => 'Sound on'],
        'film_ton_aus' => ['it' => 'Audio no', 'de' => 'Ton aus', 'en' => 'Sound off'],
        'film_start' => ['it' => 'Guarda il film', 'de' => 'Film ansehen', 'en' => 'Watch the film'],
        'film_alt' => ['it' => 'Film: un vicolo siciliano di notte, i negozi si illuminano uno dopo l’altro, sui telefoni compaiono i siti; alla fine il logo Vecom Design in oro.', 'de' => 'Film: eine sizilianische Gasse bei Nacht, die Läden leuchten nacheinander auf, auf Telefonen erscheinen die Websites; am Ende das Vecom-Design-Logo in Gold.', 'en' => 'Film: a Sicilian alley at night, the shops light up one after another, websites appear on phones; at the end the Vecom Design logo in gold.'],
        /* Ausbau 27.09.2026 (Uwe: Ja zu 15 Vorschlägen) */
        'knoepfe' => [
            'loslegen' => ['it' => 'Iniziare', 'de' => 'Loslegen', 'en' => 'Get started'],
            'angebot' => ['it' => 'Richiedere un’offerta gratuita', 'de' => 'Kostenloses Angebot anfordern', 'en' => 'Request a free quote'],
            'preis' => ['it' => 'Scoprire il mio prezzo', 'de' => 'Meinen Preis erfahren', 'en' => 'Find out my price'],
            'beratung' => ['it' => 'Ricevere una consulenza senza impegno', 'de' => 'Unverbindlich beraten lassen', 'en' => 'Get free, no-obligation advice'],
        ],
        'empfiehlt' => ['it' => 'consiglia Vecom Design', 'de' => 'empfiehlt Vecom Design', 'en' => 'recommends Vecom Design'],
        'wege_titel' => ['it' => 'Oppure, senza impegno:', 'de' => 'Oder erst einmal unverbindlich:', 'en' => 'Or, with no obligation:'],
        'wege' => [
            'check' => [['it' => 'Verificare il suo sito', 'de' => 'Ihre Website prüfen', 'en' => 'Check your website'], ['it' => 'Gratis, in un minuto: cosa va e cosa no.', 'de' => 'Kostenlos, in einer Minute: was gut ist und was nicht.', 'en' => 'Free, in a minute: what works and what doesn’t.']],
            'preis' => [['it' => 'Il prezzo in 2 minuti', 'de' => 'Preis in 2 Minuten', 'en' => 'Price in 2 minutes'], ['it' => 'Poche domande, e vede subito quanto costa.', 'de' => 'Ein paar Fragen, und Sie sehen sofort, was es kostet.', 'en' => 'A few questions and you see the cost right away.']],
            'termin' => [['it' => 'Prenotare una chiacchierata', 'de' => 'Gespräch buchen', 'en' => 'Book a call'], ['it' => '15 minuti al telefono o in video, all’orario che sceglie lei.', 'de' => '15 Minuten am Telefon oder per Video, zur Zeit Ihrer Wahl.', 'en' => '15 minutes by phone or video, at a time you choose.']],
        ],
        'ds' => ['it' => 'Trattiamo i suoi dati solo per questa richiesta.', 'de' => 'Wir verwenden Ihre Angaben nur für diese Anfrage.', 'en' => 'We use your details only for this request.'],
        'ds_link' => ['it' => 'Informativa privacy', 'de' => 'Datenschutzerklärung', 'en' => 'Privacy policy'],
        'werbung' => ['it' => 'Inoltre acconsento a ricevere da Vecom Design, a questo indirizzo, consigli e offerte adatte. Posso revocare in qualsiasi momento.', 'de' => 'Außerdem möchte ich von Vecom Design an diese Adresse Tipps und passende Angebote bekommen. Ich kann das jederzeit widerrufen.', 'en' => 'I also agree to receive tips and suitable offers from Vecom Design at this address. I can withdraw at any time.'],
        'werbung_hilfe' => ['it' => 'Facoltativo. Riceve un’e-mail di conferma: conta solo il suo clic.', 'de' => 'Freiwillig. Sie bekommen eine Bestätigungsmail — erst Ihr Klick darin zählt.', 'en' => 'Optional. You’ll get a confirmation email — only your click counts.'],
        'betrieb' => ['it' => 'Nome della sua attività', 'de' => 'Name Ihres Betriebs', 'en' => 'Name of your business'],
        'webseite' => ['it' => 'Il suo sito (se c’è)', 'de' => 'Ihre Website (falls vorhanden)', 'en' => 'Your website (if any)'],
        'st_start' => ['it' => 'Iniziare', 'de' => 'Loslegen', 'en' => 'Start'],
        'st_rr' => ['it' => 'Richiamatemi', 'de' => 'Rückruf', 'en' => 'Call back'],
        'st_wa' => ['it' => 'WhatsApp', 'de' => 'WhatsApp', 'en' => 'WhatsApp'],
        'og_text' => ['it' => '{name} consiglia Vecom Design: siti web con prezzo chiaro prima e una persona che la segue.', 'de' => '{name} empfiehlt Vecom Design: Websites mit klarem Preis vorher und einem Menschen, der Sie begleitet.', 'en' => '{name} recommends Vecom Design: websites with a clear price upfront and a real person guiding you.'],
        'g_b_wege' => ['it' => 'Tre vie: verifica sito · prezzo · appuntamento', 'de' => 'Drei Wege: Website-Check · Preis · Termin', 'en' => 'Three ways: website check · price · booking'],
        'g_reihenfolge' => ['it' => 'Sezioni: accese e ordine (1 = in alto)', 'de' => 'Abschnitte: an/aus und Reihenfolge (1 = oben)', 'en' => 'Sections: on/off and order (1 = top)'],
        'g_arbeiten' => ['it' => 'Quali lavori mostrare (max. 3)', 'de' => 'Welche Arbeiten zeigen (höchstens 3)', 'en' => 'Which work to show (max. 3)'],
        'g_knopf' => ['it' => 'Testo del pulsante', 'de' => 'Text des Knopfs', 'en' => 'Button text'],
        'g_eigen' => ['it' => 'testi suoi', 'de' => 'eigene Texte', 'en' => 'your texts'],
        'g_std' => ['it' => 'testo standard', 'de' => 'Standardtext', 'en' => 'standard text'],
        'g_sprache_hinweis' => ['it' => 'I visitatori vedono la pagina nella lingua del loro browser (italiano, tedesco o inglese). Dove non ha scritto nulla, compare il testo standard.', 'de' => 'Besucher sehen die Seite in der Sprache ihres Browsers (Italienisch, Deutsch oder Englisch). Wo Sie nichts eingetragen haben, steht der Standardtext.', 'en' => 'Visitors see the page in their browser’s language (Italian, German or English). Where you haven’t written anything, the standard text appears.'],
        // Trichter im Partnerbereich
        't_titel' => ['it' => 'Cosa succede sulla sua pagina', 'de' => 'Was auf Ihrer Seite passiert', 'en' => 'What happens on your page'],
        't_text' => ['it' => 'Solo persone reali: anteprime di WhatsApp, Facebook & co. e le sue visite non contano.', 'de' => 'Nur echte Menschen: Vorschau-Abrufe von WhatsApp, Facebook & Co. und Ihre eigenen Aufrufe zählen nicht.', 'en' => 'Real people only: previews from WhatsApp, Facebook & co. and your own visits don’t count.'],
        't_zeit' => ['it' => 'Ultimi {n} giorni', 'de' => 'Letzte {n} Tage', 'en' => 'Last {n} days'],
        't_besuche' => ['it' => 'Visite', 'de' => 'Besuche', 'en' => 'Visits'],
        't_anfragen' => ['it' => 'Richieste', 'de' => 'Anfragen', 'en' => 'Enquiries'],
        't_kunden' => ['it' => 'Clienti', 'de' => 'Kunden', 'en' => 'Customers'],
        't_verkaeufe' => ['it' => 'Vendite', 'de' => 'Verkäufe', 'en' => 'Sales'],
        't_provision' => ['it' => 'Provvigione', 'de' => 'Provision', 'en' => 'Commission'],
        't_quote' => ['it' => '{p} % dei visitatori ha fatto una richiesta', 'de' => '{p} % der Besucher haben angefragt', 'en' => '{p}% of visitors made an enquiry'],
        't_wege' => ['email' => ['it' => 'E-mail inserita', 'de' => 'E-Mail eingetragen', 'en' => 'Email entered'], 'rueckruf' => ['it' => 'Richiamata richiesta', 'de' => 'Rückruf gewünscht', 'en' => 'Call-back requested'],
                     'wa' => ['it' => 'WhatsApp aperto', 'de' => 'WhatsApp geöffnet', 'en' => 'WhatsApp opened'], 'check' => ['it' => 'Verifica sito fatta', 'de' => 'Website-Check gemacht', 'en' => 'Website check done'],
                     'termin' => ['it' => 'Appuntamento prenotato', 'de' => 'Termin gebucht', 'en' => 'Call booked'], 'preis' => ['it' => 'Calcolo prezzo aperto', 'de' => 'Preisrechner geöffnet', 'en' => 'Price calculator opened']],
        'arbeiten_mehr' => ['it' => 'Vedere altri lavori →', 'de' => 'Weitere Arbeiten ansehen →', 'en' => 'See more work →'],
        'ablauf_titel' => ['it' => 'Come funziona', 'de' => 'So läuft es ab', 'en' => 'How it works'],
        'ablauf' => [
            [['it' => 'Mi dice cosa le serve', 'de' => 'Sie sagen, was Sie brauchen', 'en' => 'You tell us what you need'], ['it' => 'Poche domande nella sua area personale — il prezzo indicativo lo vede subito.', 'de' => 'Ein paar Fragen in Ihrem persönlichen Bereich — den Richtpreis sehen Sie sofort.', 'en' => 'A few questions in your personal area — you see a guide price right away.']],
            [['it' => 'Riceve l’offerta', 'de' => 'Sie bekommen das Angebot', 'en' => 'You get the quote'], ['it' => 'Punto per punto, entro un giorno lavorativo. Decide lei.', 'de' => 'Position für Position, innerhalb eines Werktags. Sie entscheiden.', 'en' => 'Item by item, within one working day. You decide.']],
            [['it' => 'Costruiamo, lei segue', 'de' => 'Wir bauen, Sie sehen zu', 'en' => 'We build, you follow along'], ['it' => 'Ogni passo nella sua area, fino al sito online.', 'de' => 'Jeden Schritt in Ihrem Bereich, bis die Seite online ist.', 'en' => 'Every step in your area, until the site is live.']],
        ],
        'faq_titel' => ['it' => 'Domande frequenti', 'de' => 'Häufige Fragen', 'en' => 'Frequently asked questions'],
        'faq' => [
            [['it' => 'Quanto costa?', 'de' => 'Was kostet das?', 'en' => 'What does it cost?'], ['it' => 'Il questionario mostra subito un prezzo indicativo; l’offerta precisa arriva entro un giorno lavorativo.', 'de' => 'Der Fragebogen zeigt sofort einen Richtpreis; das genaue Angebot kommt innerhalb eines Werktags.', 'en' => 'The questionnaire shows a guide price right away; the exact quote follows within one working day.']],
            [['it' => 'Mi impegno a qualcosa?', 'de' => 'Gehe ich eine Verpflichtung ein?', 'en' => 'Am I committing to anything?'], ['it' => 'No. La richiesta è gratuita e senza impegno: un incarico nasce solo quando ci accordiamo per iscritto.', 'de' => 'Nein. Die Anfrage ist kostenlos und unverbindlich: Ein Auftrag entsteht erst, wenn wir uns schriftlich einig sind.', 'en' => 'No. The enquiry is free and without obligation: a project only comes about once we agree in writing.']],
            [['it' => 'Devo preparare qualcosa?', 'de' => 'Muss ich etwas vorbereiten?', 'en' => 'Do I need to prepare anything?'], ['it' => 'No. Quello che ha (logo, foto, testi) lo carica nella sua area; quello che manca lo vediamo insieme.', 'de' => 'Nein. Was Sie haben (Logo, Fotos, Texte), laden Sie in Ihrem Bereich hoch; was fehlt, besprechen wir.', 'en' => 'No. What you have (logo, photos, texts) you upload in your area; what’s missing we discuss together.']],
            [['it' => 'In quali lingue?', 'de' => 'In welchen Sprachen?', 'en' => 'In which languages?'], ['it' => 'Parliamo italiano, tedesco e inglese — e il sito può essere in più lingue.', 'de' => 'Wir sprechen Italienisch, Deutsch und Englisch — und die Seite kann mehrsprachig sein.', 'en' => 'We speak Italian, German and English — and the site can be multilingual.']],
        ],
        'wa_knopf' => ['it' => 'Domande? Scrivi a {name} su WhatsApp', 'de' => 'Fragen? Schreib {name} auf WhatsApp', 'en' => 'Questions? Message {name} on WhatsApp'],
        'wa_text' => ['it' => 'Ciao {name}, ho visto la pagina di Vecom Design e ho una domanda: ', 'de' => 'Hallo {name}, ich habe die Seite von Vecom Design gesehen und habe eine Frage: ', 'en' => 'Hi {name}, I saw the Vecom Design page and have a question: '],
        // Gestalter im Dashboard
        'g_titel' => ['it' => 'Personalizzi la sua pagina', 'de' => 'Ihre Seite gestalten', 'en' => 'Design your page'],
        'g_text' => ['it' => 'Il logo Vecom, il modulo di richiesta e le note legali restano uguali — tutto il resto lo sceglie lei. Le modifiche sono subito online.', 'de' => 'Vecom-Logo, Anfrageformular und Rechtliches bleiben gleich — alles andere wählen Sie. Änderungen sind sofort online.', 'en' => 'The Vecom logo, enquiry form and legal footer stay the same — you choose everything else. Changes go live immediately.'],
        'g_vorlage' => ['it' => 'Stile', 'de' => 'Vorlage', 'en' => 'Style'],
        'g_akzent' => ['it' => 'Colore', 'de' => 'Farbe', 'en' => 'Colour'],
        'g_bild' => ['it' => 'Immagine di copertina', 'de' => 'Titelbild', 'en' => 'Cover image'],
        'g_bild_hoch' => ['it' => 'Carichi una sua foto (orizzontale, min. 400 px)', 'de' => 'Eigenes Foto hochladen (quer, mind. 400 px)', 'en' => 'Upload your own photo (landscape, min. 400 px)'],
        'g_bild_weg' => ['it' => 'Rimuovi la mia foto', 'de' => 'Mein Foto entfernen', 'en' => 'Remove my photo'],
        'g_texte' => ['it' => 'Testi (vuoto = testo standard)', 'de' => 'Texte (leer = Standardtext)', 'en' => 'Texts (empty = standard text)'],
        'g_t_titel' => ['it' => 'Titolo', 'de' => 'Überschrift', 'en' => 'Headline'],
        'g_t_lead' => ['it' => 'Introduzione', 'de' => 'Einleitung', 'en' => 'Introduction'],
        'g_t_p' => ['it' => 'Vantaggio {n}', 'de' => 'Vorteil {n}', 'en' => 'Benefit {n}'],
        'g_bausteine' => ['it' => 'Sezioni aggiuntive', 'de' => 'Zusätzliche Abschnitte', 'en' => 'Extra sections'],
        'g_b_arbeiten' => ['it' => 'Alcuni nostri lavori', 'de' => 'Beispielarbeiten', 'en' => 'Example work'],
        'g_b_film' => ['it' => 'Film di Vecom Design (voce in italiano, tedesco o inglese)', 'de' => 'Film von Vecom Design (Sprecher auf Italienisch, Deutsch oder Englisch)', 'en' => 'Vecom Design film (voice-over in Italian, German or English)'],
        'g_b_ablauf' => ['it' => 'Come funziona (3 passi)', 'de' => 'Ablauf in 3 Schritten', 'en' => 'How it works (3 steps)'],
        'g_b_faq' => ['it' => 'Domande frequenti', 'de' => 'Häufige Fragen', 'en' => 'FAQ'],
        'g_b_whatsapp' => ['it' => 'Pulsante WhatsApp verso di me', 'de' => 'WhatsApp-Knopf zu mir', 'en' => 'WhatsApp button to me'],
        'g_b_stimmen' => ['it' => 'Recensioni dei clienti (approvate da Vecom)', 'de' => 'Kundenstimmen (von Vecom freigegeben)', 'en' => 'Customer reviews (approved by Vecom)'],
        'g_b_rueckruf' => ['it' => 'Pulsante «Richiamatemi»', 'de' => 'Rückruf-Knopf', 'en' => '“Call me back” button'],
        // Kundenstimmen, Rückruf, Vorher/Nachher (27.09.2026)
        'stimmen_titel' => ['it' => 'Cosa dicono i clienti', 'de' => 'Was Kunden sagen', 'en' => 'What customers say'],
        'rr_titel' => ['it' => 'Preferisce parlare al telefono?', 'de' => 'Lieber telefonieren?', 'en' => 'Rather talk on the phone?'],
        'rr_text' => ['it' => 'Lasci il suo numero — la richiamiamo quando le fa comodo.', 'de' => 'Hinterlassen Sie Ihre Nummer — wir rufen zurück, wann es Ihnen passt.', 'en' => 'Leave your number — we’ll call you back when it suits you.'],
        'rr_name' => ['it' => 'Nome', 'de' => 'Name', 'en' => 'Name'],
        'rr_telefon' => ['it' => 'Numero di telefono', 'de' => 'Telefonnummer', 'en' => 'Phone number'],
        'rr_tag' => ['it' => 'Giorno', 'de' => 'Tag', 'en' => 'Day'],
        'rr_fenster' => ['it' => 'Orario', 'de' => 'Uhrzeit', 'en' => 'Time'],
        'rr_tage' => ['heute' => ['it' => 'Oggi', 'de' => 'Heute', 'en' => 'Today'], 'morgen' => ['it' => 'Domani', 'de' => 'Morgen', 'en' => 'Tomorrow'],
                      'tag1' => ['it' => 'Lunedì', 'de' => 'Montag', 'en' => 'Monday'], 'tag2' => ['it' => 'Martedì', 'de' => 'Dienstag', 'en' => 'Tuesday'],
                      'tag3' => ['it' => 'Mercoledì', 'de' => 'Mittwoch', 'en' => 'Wednesday'], 'tag4' => ['it' => 'Giovedì', 'de' => 'Donnerstag', 'en' => 'Thursday'],
                      'tag5' => ['it' => 'Venerdì', 'de' => 'Freitag', 'en' => 'Friday'], 'tag6' => ['it' => 'Sabato', 'de' => 'Samstag', 'en' => 'Saturday']],
        'rr_ok' => ['it' => 'Acconsento che Vecom Design mi chiami per questo. Il numero serve solo a questo.', 'de' => 'Ich bin einverstanden, dass Vecom Design mich dafür anruft. Die Nummer wird nur dafür verwendet.', 'en' => 'I agree that Vecom Design may call me about this. The number is used for nothing else.'],
        'rr_knopf' => ['it' => 'Richiamatemi', 'de' => 'Rückruf anfordern', 'en' => 'Request a call back'],
        'rr_danke' => ['it' => 'Grazie! La richiamiamo all’orario scelto.', 'de' => 'Danke! Wir rufen Sie zur gewählten Zeit an.', 'en' => 'Thank you! We’ll call you at the time you chose.'],
        'rr_fehler' => [
            'rr_name' => ['it' => 'Per favore il suo nome.', 'de' => 'Bitte Ihren Namen.', 'en' => 'Please enter your name.'],
            'rr_telefon' => ['it' => 'Il numero non sembra corretto.', 'de' => 'Die Nummer sieht nicht richtig aus.', 'en' => 'The number doesn’t look right.'],
            'rr_wann' => ['it' => 'Per favore scelga giorno e orario.', 'de' => 'Bitte Tag und Uhrzeit wählen.', 'en' => 'Please choose a day and time.'],
            'rr_ok' => ['it' => 'Per favore confermi il consenso.', 'de' => 'Bitte das Einverständnis bestätigen.', 'en' => 'Please confirm your consent.'],
            'rr_zeit' => ['it' => 'Il modulo è scaduto — ricarichi la pagina e riprovi.', 'de' => 'Das Formular ist abgelaufen — bitte Seite neu laden und nochmal senden.', 'en' => 'The form expired — please reload the page and try again.'],
            'rr_genug' => ['it' => 'Oggi abbiamo già molte richieste da questa pagina. Ci scriva a kontakt@vecom-design.it.', 'de' => 'Heute sind über diese Seite schon viele Wünsche eingegangen. Schreiben Sie uns an kontakt@vecom-design.it.', 'en' => 'This page has had many requests today. Please email kontakt@vecom-design.it.'],
            'rr_falle' => ['it' => 'Non è stato possibile inviare.', 'de' => 'Das ging leider nicht.', 'en' => 'That didn’t work.'],
        ],
        'rr_push_t' => ['it' => 'Qualcuno vuole essere richiamato', 'de' => 'Jemand möchte zurückgerufen werden', 'en' => 'Someone wants a call back'],
        'rr_push_x' => ['it' => 'Tramite la sua pagina — ci pensa Vecom.', 'de' => 'Über Ihre Seite — Vecom kümmert sich darum.', 'en' => 'Via your page — Vecom takes care of it.'],
        'vn_knopf' => ['it' => 'Prima / dopo', 'de' => 'Vorher / Nachher', 'en' => 'Before / after'],
        'vn_regler' => ['it' => 'Cursore prima e dopo', 'de' => 'Regler Vorher und Nachher', 'en' => 'Before and after slider'],
        'vn_vorher' => ['it' => 'Prima', 'de' => 'Vorher', 'en' => 'Before'],
        'vn_nachher' => ['it' => 'Dopo', 'de' => 'Nachher', 'en' => 'After'],
        'g_wa' => ['it' => 'Il suo numero WhatsApp (con prefisso, es. +39 …)', 'de' => 'Ihre WhatsApp-Nummer (mit Vorwahl, z. B. +39 …)', 'en' => 'Your WhatsApp number (with country code, e.g. +39 …)'],
        'g_speichern' => ['it' => 'Salva e pubblica', 'de' => 'Speichern und veröffentlichen', 'en' => 'Save and publish'],
        'g_standard' => ['it' => 'Torna allo standard', 'de' => 'Auf Standard zurücksetzen', 'en' => 'Reset to standard'],
        'g_gut' => ['it' => 'Salvato — la sua pagina è aggiornata.', 'de' => 'Gespeichert — Ihre Seite ist aktualisiert.', 'en' => 'Saved — your page is updated.'],
        'g_vorschau' => ['it' => 'Anteprima della sua pagina', 'de' => 'Vorschau Ihrer Seite', 'en' => 'Preview of your page'],
        'text_link' => ['it' => 'Nei testi niente indirizzi web o e-mail, per favore.', 'de' => 'Bitte keine Web- oder E-Mail-Adressen in den Texten.', 'en' => 'Please no web or email addresses in the texts.'],
        'wa_nummer' => ['it' => 'Il numero WhatsApp deve iniziare con + e il prefisso del paese.', 'de' => 'Die WhatsApp-Nummer muss mit + und Ländervorwahl beginnen.', 'en' => 'The WhatsApp number must start with + and the country code.'],
        'bild_gross' => ['it' => 'L’immagine è troppo grande (max. 10 MB).', 'de' => 'Das Bild ist zu groß (höchstens 10 MB).', 'en' => 'The image is too large (max. 10 MB).'],
        'bild_art' => ['it' => 'Per favore una foto JPG, PNG o WebP di almeno 400 px di larghezza.', 'de' => 'Bitte ein Foto als JPG, PNG oder WebP, mindestens 400 px breit.', 'en' => 'Please a JPG, PNG or WebP photo at least 400 px wide.'],
    ];

    /** Betriebe kontaktieren (27.09.2026, Uwe: Ja zu Vorlagen, „Vecom schreibt für ihn“ und Nummer/Mail bei eigenen Reservierungen).
        'nachricht' in der Sprache des BETRIEBS (Sie/Lei), 'ui' in der Sprache des Partners. */
    public const PARTNER_ANSCHREIBEN = [
        'nachricht' => [
            'wa' => [
                'it' => "Buongiorno, sono {name}. {aufhaenger}Collaboro con Vecom Design, che realizza siti per attività come la vostra: dite cosa vi serve e conoscete il prezzo prima, poi seguite ogni passo fino alla messa online.\n\nQui trovate tutto, senza impegno: {link}\n\nSe preferite, passo volentieri di persona. Buona giornata!",
                'de' => "Guten Tag, hier ist {name}. {aufhaenger}Ich arbeite mit Vecom Design zusammen, die Websites für Betriebe wie Ihren bauen: Sie sagen, was Sie brauchen, kennen den Preis vorher und sehen jeden Schritt bis die Seite online ist.\n\nHier finden Sie alles, unverbindlich: {link}\n\nWenn Ihnen das lieber ist, komme ich gern persönlich vorbei. Einen schönen Tag!",
                'en' => "Hello, this is {name}. {aufhaenger}I work with Vecom Design, who build websites for businesses like yours: you say what you need, know the price upfront and follow every step until the site is live.\n\nEverything is here, no obligation: {link}\n\nIf you prefer, I’m happy to drop by in person. Have a good day!",
            ],
            'betreff' => ['it' => 'Un’idea per {firma}', 'de' => 'Eine Idee für {firma}', 'en' => 'An idea for {firma}'],
            'mail' => [
                'it' => "Buongiorno,\n\nmi chiamo {name} e vi scrivo personalmente. {aufhaenger}\n\nCollaboro con Vecom Design: realizzano siti per attività come {firma}. Dite in due minuti cosa vi serve e conoscete il prezzo prima di iniziare; poi seguite ogni passo nella vostra area personale, finché il sito è online.\n\nSe vi interessa, qui trovate tutto senza impegno: {link}\n\nSe preferite, passo volentieri da voi.\n\nCordiali saluti\n{name}",
                'de' => "Guten Tag,\n\nmein Name ist {name}, und ich schreibe Ihnen persönlich. {aufhaenger}\n\nIch arbeite mit Vecom Design zusammen: Sie bauen Websites für Betriebe wie {firma}. Sie sagen in zwei Minuten, was Sie brauchen, und kennen den Preis, bevor es losgeht; danach sehen Sie jeden Schritt in Ihrem persönlichen Bereich, bis die Seite online ist.\n\nWenn Sie das interessiert, finden Sie hier alles, unverbindlich: {link}\n\nGern komme ich auch persönlich vorbei.\n\nFreundliche Grüße\n{name}",
                'en' => "Hello,\n\nmy name is {name} and I’m writing to you personally. {aufhaenger}\n\nI work with Vecom Design: they build websites for businesses like {firma}. You say what you need in two minutes and know the price before anything starts; then you follow every step in your personal area until the site is live.\n\nIf you’re interested, everything is here, no obligation: {link}\n\nI’m also happy to drop by in person.\n\nKind regards\n{name}",
            ],
            'mit_check' => ['it' => 'Ho fatto una verifica veloce gratuita del vostro sito, eccola: {check} ', 'de' => 'Ich habe einen kostenlosen Kurz-Check Ihrer Website gemacht, hier ist er: {check} ', 'en' => 'I ran a free quick check of your website, here it is: {check} '],
            'ohne_web' => ['it' => 'Ho visto che {firma} non ha ancora un sito tutto suo. ', 'de' => 'Mir ist aufgefallen, dass {firma} noch keine eigene Website hat. ', 'en' => 'I noticed that {firma} doesn’t have its own website yet. '],
            'mit_web' => ['it' => 'Ho dato un’occhiata al sito di {firma}. ', 'de' => 'Ich habe mir die Website von {firma} angesehen. ', 'en' => 'I had a look at the {firma} website. '],
        ],
        'ui' => [
            'titel'     => ['it' => 'Contattare', 'de' => 'Kontaktieren', 'en' => 'Get in touch'],
            'regel'     => ['it' => 'Di persona e uno alla volta: un messaggio a questa attività, niente invii in serie. In Italia e-mail e telefonate pubblicitarie sono permesse solo in parte (consenso, Registro delle opposizioni) — nel dubbio passi di persona con la cartella.', 'de' => 'Persönlich und einzeln: eine Nachricht an genau diesen Betrieb, keine Serien. Werbe-E-Mails und -Anrufe sind in Italien nur eingeschränkt erlaubt (Einwilligung, Registro delle opposizioni) — im Zweifel vorbeigehen und die Mappe mitbringen.', 'en' => 'Personal and one at a time: one message to this business, no mass sending. In Italy, promotional emails and calls are only partly allowed (consent, Registro delle opposizioni) — when in doubt, drop by with the printed folder.'],
            'tel'       => ['it' => 'Telefono', 'de' => 'Telefon', 'en' => 'Phone'],
            'mail'      => ['it' => 'E-mail', 'de' => 'E-Mail', 'en' => 'Email'],
            'keine'     => ['it' => 'Non conosciamo né telefono né e-mail.', 'de' => 'Keine Nummer und keine E-Mail bekannt.', 'en' => 'No phone or email on file.'],
            'anrufen'   => ['it' => 'Chiama', 'de' => 'Anrufen', 'en' => 'Call'],
            'web'       => ['it' => 'Apri il sito', 'de' => 'Website öffnen', 'en' => 'Open website'],
            'route'     => ['it' => 'Percorso', 'de' => 'Route', 'en' => 'Directions'],
            'suche'     => ['it' => 'Cerca il numero su Google', 'de' => 'Nummer bei Google suchen', 'en' => 'Search number on Google'],
            'wa'        => ['it' => 'Invia su WhatsApp', 'de' => 'Per WhatsApp senden', 'en' => 'Send on WhatsApp'],
            'mail_neu'  => ['it' => 'Scrivi l’e-mail', 'de' => 'E-Mail schreiben', 'en' => 'Write email'],
            'kopieren'  => ['it' => 'Copia', 'de' => 'Kopieren', 'en' => 'Copy'],
            'sprache'   => ['it' => 'Lingua del messaggio', 'de' => 'Sprache der Nachricht', 'en' => 'Message language'],
            'vecom'     => ['it' => 'Vecom scriva per me', 'de' => 'Vecom soll anschreiben', 'en' => 'Ask Vecom to write'],
            'vecom_text' => ['it' => 'Vecom invia una lettera con il suo nome, la sua foto e il suo codice QR. Uwe la controlla e la spedisce; il francobollo lo paga Vecom.', 'de' => 'Vecom schickt einen Brief mit Ihrem Namen, Foto und QR-Code. Uwe prüft und verschickt ihn; das Porto zahlt Vecom.', 'en' => 'Vecom sends a letter with your name, photo and QR code. Uwe checks and sends it; Vecom pays the postage.'],
            'vecom_gut' => ['it' => 'Richiesta inviata: le diremo quando la lettera è partita.', 'de' => 'Angefragt — Sie erfahren es, sobald der Brief unterwegs ist.', 'en' => 'Requested — you’ll hear when the letter is on its way.'],
            'vecom_offen' => ['it' => 'Lettera richiesta il {datum}', 'de' => 'Brief angefragt am {datum}', 'en' => 'Letter requested on {datum}'],
            'vecom_raus' => ['it' => 'Lettera spedita il {datum}', 'de' => 'Brief verschickt am {datum}', 'en' => 'Letter sent on {datum}'],
            'vecom_nein' => ['it' => 'Vecom ha già scritto a questa attività.', 'de' => 'Vecom hat diesen Betrieb schon angeschrieben.', 'en' => 'Vecom has already written to this business.'],
        ],
        // Auf dem Brief von Vecom, in der Sprache des Briefs
        'brief' => [
            'empf' => ['it' => 'Vi è stato segnalato da {name}', 'de' => 'Empfohlen von {name}', 'en' => 'Recommended by {name}'],
            'scan' => ['it' => 'Inquadrate il codice: richiesta gratuita, senza impegno.', 'de' => 'Code scannen: kostenlos und unverbindlich anfragen.', 'en' => 'Scan the code: free enquiry, no obligation.'],
        ],
    ];

    /** Marketing im Partner-Dashboard (27.09.2026, Uwe: Ja zu Posting-Kalender, Mappe, Stufen & Monatswettbewerb, Erfolge als Kacheln). */
    /**
     * Marketing-Ausbau der Partnerseite (28.09.2026, Uwe: Ja zu Heißer
     * Kontakt, Nachfass-Erinnerung, Meine Kontakte, Gutschein, Zentrale
     * Aktion, Branchen-Pakete, Kundenstimmen, Mini-Kurs, Meilensteine).
     * Oberfläche in der Anrede des Partnerbereichs (Sie); Texte an Betriebe
     * wie die Anschreiben (it: Lei/voi, de: Sie); Texte an eigene Kontakte
     * (Freunde, Bekannte) per du.
     */
    public const PARTNER_PLUS = [
        // Vecom auf Telegram (02.10.2026): beitreten und weitersagen
        'tg_titel'   => ['it' => 'Vecom su Telegram', 'de' => 'Vecom auf Telegram', 'en' => 'Vecom on Telegram'],
        'tg_text'    => ['it' => 'Novità, esempi di siti e consigli — prima che altrove. Entri nel canale e lo passi a chi ha un’attività: più persone lo seguono, più facile è parlarne.',
                         'de' => 'Neuigkeiten, Beispiele und Tipps — dort zuerst. Treten Sie dem Kanal bei und geben Sie ihn an Betriebe weiter, die Sie kennen: Je mehr ihn lesen, desto leichter kommt man ins Gespräch.',
                         'en' => 'News, website examples and tips — there first. Join the channel and pass it on to business owners you know: the more people read it, the easier the conversation.'],
        'tg_knopf'   => ['it' => 'Entra nel canale', 'de' => 'Kanal beitreten', 'en' => 'Join the channel'],
        'tg_msg'     => ['it' => "Ti segnalo un canale Telegram utile se hai un’attività: novità, esempi di siti e consigli pratici di Vecom Design. 👉 {link}",
                         'de' => "Ein Telegram-Kanal, der sich lohnt, wenn Sie einen Betrieb haben: Neuigkeiten, Website-Beispiele und praktische Tipps von Vecom Design. 👉 {link}",
                         'en' => "A Telegram channel worth following if you run a business: news, website examples and practical tips from Vecom Design. 👉 {link}"],
        'tg_wa'      => ['it' => 'Inoltra su WhatsApp', 'de' => 'Per WhatsApp weitergeben', 'en' => 'Share on WhatsApp'],
        // Heißer Kontakt
        'hk_titel'   => ['it' => 'Contatti caldi', 'de' => 'Heiße Kontakte', 'en' => 'Hot contacts'],
        'hk_text'    => ['it' => 'Queste attività hanno aperto la verifica che ha mandato loro. È il momento giusto per chiamare o scrivere due righe.', 'de' => 'Diese Betriebe haben den Schnellcheck geöffnet, den Sie ihnen geschickt haben. Jetzt ist der richtige Moment für einen Anruf oder zwei Zeilen.', 'en' => 'These businesses opened the quick check you sent them. Now is the right moment to call or write a line.'],
        'hk_vor'     => ['it' => 'aperto {zeit} fa', 'de' => 'vor {zeit} geöffnet', 'en' => 'opened {zeit} ago'],
        'hk_min'     => ['it' => '{n} min', 'de' => '{n} Min.', 'en' => '{n} min'],
        'hk_std'     => ['it' => '{n} ore', 'de' => '{n} Std.', 'en' => '{n} h'],
        'hk_push_t'  => ['it' => '{host} sta guardando la sua verifica', 'de' => '{host} sieht sich gerade Ihren Schnellcheck an', 'en' => '{host} is looking at your quick check'],
        'hk_push_x'  => ['it' => 'Ora è il momento giusto per una chiamata o un messaggio.', 'de' => 'Jetzt ist ein guter Moment für einen Anruf oder eine kurze Nachricht.', 'en' => 'Now is a good moment for a call or a short message.'],
        // Nachfassen
        'nf_titel'   => ['it' => 'Da ricontattare', 'de' => 'Nachhaken', 'en' => 'Follow up'],
        'nf_text'    => ['it' => 'Chi non risponde subito spesso ha solo dimenticato. Un messaggio breve dopo qualche giorno porta più clienti di qualsiasi post.', 'de' => 'Wer nicht gleich antwortet, hat es oft nur vergessen. Eine kurze Nachricht nach ein paar Tagen bringt mehr Kunden als jeder Beitrag.', 'en' => 'People who don’t reply right away have often just forgotten. A short message after a few days brings more customers than any post.'],
        'nf_check'   => ['it' => 'Verifica inviata {tage} giorni fa', 'de' => 'Schnellcheck vor {tage} Tagen geschickt', 'en' => 'Quick check sent {tage} days ago'],
        'nf_firma'   => ['it' => 'Scritto {tage} giorni fa', 'de' => 'Vor {tage} Tagen angeschrieben', 'en' => 'Contacted {tage} days ago'],
        'nf_gesehen' => ['it' => 'aperta {n}×', 'de' => '{n}× geöffnet', 'en' => 'opened {n}×'],
        'nf_ok'      => ['it' => 'Fatto', 'de' => 'Erledigt', 'en' => 'Done'],
        'nf_wa'      => ['it' => 'Scrivi su WhatsApp', 'de' => 'Per WhatsApp schreiben', 'en' => 'Write on WhatsApp'],
        'nf_push_t'  => ['it' => 'Da ricontattare: {n}', 'de' => 'Zum Nachhaken: {n}', 'en' => 'To follow up: {n}'],
        'nf_push_x'  => ['it' => 'Un messaggio breve adesso: il testo è pronto nella sua pagina.', 'de' => 'Jetzt kurz nachhaken — der Text liegt fertig auf Ihrer Seite.', 'en' => 'A short follow-up now — the text is ready on your page.'],
        'nf_msg_check' => [
            'it' => "Buongiorno, sono {name}. Qualche giorno fa le ho mandato la verifica gratuita del sito {host}: {check}\n\nHa avuto modo di guardarla? Se vuole le mostro in due minuti cosa si può migliorare subito, senza impegno: {link}",
            'de' => "Guten Tag, hier ist {name}. Vor ein paar Tagen habe ich Ihnen den kostenlosen Check der Website {host} geschickt: {check}\n\nKonnten Sie schon reinschauen? Wenn Sie mögen, zeige ich Ihnen in zwei Minuten, was sich schnell verbessern lässt, unverbindlich: {link}",
            'en' => "Hello, this is {name}. A few days ago I sent you the free check of the website {host}: {check}\n\nHave you had a chance to look? If you like, I can show you in two minutes what could be improved quickly, no obligation: {link}"],
        'nf_msg_firma' => [
            'it' => "Buongiorno, sono di nuovo {name}. Le avevo scritto qualche giorno fa per {firma}: ha avuto modo di dare un’occhiata? Qui c’è tutto, senza impegno: {link}\n\nSe preferisce, la chiamo io due minuti quando le va bene.",
            'de' => "Guten Tag, hier noch einmal {name}. Ich hatte Ihnen vor ein paar Tagen wegen {firma} geschrieben — konnten Sie schon einen Blick darauf werfen? Hier ist alles, unverbindlich: {link}\n\nWenn es Ihnen lieber ist, rufe ich kurz an, wann es Ihnen passt.",
            'en' => "Hello, it’s {name} again. I wrote to you a few days ago about {firma} — have you had a chance to take a look? Everything is here, no obligation: {link}\n\nIf you prefer, I can give you a quick call whenever suits you."],
        // Zentrale Aktion
        'ak_titel'   => ['it' => 'Promozione in corso', 'de' => 'Aktuelle Aktion', 'en' => 'Current promotion'],
        'ak_rest'    => ['it' => 'Ancora {n} giorni (fino al {datum})', 'de' => 'Noch {n} Tage (bis {datum})', 'en' => '{n} days left (until {datum})'],
        'ak_rest1'   => ['it' => 'Ultimo giorno!', 'de' => 'Letzter Tag!', 'en' => 'Last day!'],
        'ak_beitrag' => ['it' => 'Post pronto per la promozione', 'de' => 'Fertiger Beitrag zur Aktion', 'en' => 'Ready-made post for the promotion'],
        'ak_kopieren'=> ['it' => 'Copia', 'de' => 'Kopieren', 'en' => 'Copy'],
        // Branchen-Pakete
        'br_titel'   => ['it' => 'Pacchetti per settore', 'de' => 'Branchen-Pakete', 'en' => 'Industry packs'],
        'br_text'    => ['it' => 'Scelga il settore: argomenti, messaggio e post sono pronti. Il link porta sempre alla sua pagina.', 'de' => 'Branche wählen: Argumente, Nachricht und Beitrag liegen fertig bereit. Der Link führt immer auf Ihre Seite.', 'en' => 'Choose the industry: arguments, message and post are ready. The link always leads to your page.'],
        'br_warum'   => ['it' => 'Perché serve a loro', 'de' => 'Warum sie das brauchen', 'en' => 'Why they need it'],
        'br_args'    => ['it' => 'Tre argomenti', 'de' => 'Drei Argumente', 'en' => 'Three arguments'],
        'br_satz'    => ['it' => 'Per iniziare a voce', 'de' => 'Zum Einstieg im Gespräch', 'en' => 'Opening line in person'],
        'br_wa'      => ['it' => 'Messaggio a un’attività', 'de' => 'Nachricht an einen Betrieb', 'en' => 'Message to a business'],
        'br_post'    => ['it' => 'Post per i social', 'de' => 'Beitrag für soziale Netze', 'en' => 'Social media post'],
        'br_bild'    => ['it' => 'Immagine per questo settore', 'de' => 'Bild für diese Branche', 'en' => 'Image for this industry'],
        // Gutschein
        'gs_titel'   => ['it' => 'Buono da regalare', 'de' => 'Gutschein zum Verschenken', 'en' => 'Gift voucher'],
        'gs_text'    => ['it' => 'Un buono personale per un’attività precisa: verifica del sito e consulenza gratuite. Arriva come un regalo, non come pubblicità.', 'de' => 'Ein persönlicher Gutschein für einen bestimmten Betrieb: Website-Check und Beratung gratis. Kommt an wie ein Geschenk, nicht wie Werbung.', 'en' => 'A personal voucher for a specific business: free website check and consultation. It lands like a gift, not like an ad.'],
        'gs_fuer'    => ['it' => 'Per quale attività? (facoltativo)', 'de' => 'Für welchen Betrieb? (freiwillig)', 'en' => 'For which business? (optional)'],
        'gs_sprache' => ['it' => 'Lingua del buono', 'de' => 'Sprache des Gutscheins', 'en' => 'Voucher language'],
        'gs_laden'   => ['it' => 'Scarica il buono', 'de' => 'Gutschein herunterladen', 'en' => 'Download voucher'],
        'gs_teilen'  => ['it' => 'Condividi il buono', 'de' => 'Gutschein teilen', 'en' => 'Share voucher'],
        'gs_begleit' => ['it' => 'Testo da mandare insieme', 'de' => 'Text zum Mitschicken', 'en' => 'Text to send along'],
        'gs_karte' => [
            'kopf'   => ['it' => 'BUONO', 'de' => 'GUTSCHEIN', 'en' => 'VOUCHER'],
            'fuer'   => ['it' => 'per {firma}', 'de' => 'für {firma}', 'en' => 'for {firma}'],
            'titel'  => ['it' => 'Verifica del sito e consulenza gratuite', 'de' => 'Website-Check und Beratung gratis', 'en' => 'Free website check and consultation'],
            'unter'  => ['it' => 'Senza impegno. Il prezzo di ogni proposta lo conosce prima.', 'de' => 'Unverbindlich. Den Preis jedes Vorschlags kennen Sie vorher.', 'en' => 'No obligation. You know the price of every proposal upfront.'],
            'von'    => ['it' => 'Un regalo di {name}', 'de' => 'Ein Geschenk von {name}', 'en' => 'A gift from {name}'],
            'scan'   => ['it' => 'Inquadra per riscattare', 'de' => 'Scannen und einlösen', 'en' => 'Scan to redeem'],
            'begleit'=> ['it' => "Buongiorno! Ho un piccolo regalo per {firma}: una verifica del sito e una consulenza gratuite con Vecom Design. Basta inquadrare il codice o aprire il link: {link}", 'de' => "Guten Tag! Ich habe ein kleines Geschenk für {firma}: einen kostenlosen Website-Check mit Beratung bei Vecom Design. Einfach den Code scannen oder den Link öffnen: {link}", 'en' => "Hello! I have a small gift for {firma}: a free website check and consultation with Vecom Design. Just scan the code or open the link: {link}"],
            'betrieb'=> ['it' => 'la sua attività', 'de' => 'Ihren Betrieb', 'en' => 'your business'],
        ],
        // Kundenstimmen
        'st_titel'   => ['it' => 'Parlano i clienti', 'de' => 'Kundenstimmen zum Teilen', 'en' => 'Customer voices to share'],
        'st_text'    => ['it' => 'Recensioni vere di clienti Vecom che hanno dato il permesso. Una frase di un cliente convince più di dieci post.', 'de' => 'Echte Bewertungen von Vecom-Kunden, die zugestimmt haben. Ein Satz eines Kunden überzeugt mehr als zehn Beiträge.', 'en' => 'Real reviews from Vecom customers who gave permission. One sentence from a customer convinces more than ten posts.'],
        'st_beitrag' => ['it' => "«{text}»\n— {wer}\n\nConsiglio Vecom Design anch’io: {link}", 'de' => "„{text}“\n— {wer}\n\nIch kann Vecom Design auch empfehlen: {link}", 'en' => "“{text}”\n— {wer}\n\nI recommend Vecom Design too: {link}"],
        'st_bild'    => ['it' => 'Immagine con la recensione', 'de' => 'Bild mit dieser Stimme', 'en' => 'Image with this review'],
        'st_check'   => ['it' => 'Cosa dicono i clienti', 'de' => 'Was Kunden sagen', 'en' => 'What customers say'],
        // Beispielarbeiten zum Teilen (28.09.2026, Uwe: Ja)
        'bw_titel'   => ['it' => 'I nostri lavori da condividere', 'de' => 'Unsere Arbeiten zum Teilen', 'en' => 'Our work to share'],
        'bw_text'    => ['it' => 'Tre siti veri realizzati da Vecom Design. Chi vede cosa costruiamo, chiede prima. Gli stessi esempi sono sulla sua pagina, cliccabili.', 'de' => 'Drei echte Websites von Vecom Design. Wer sieht, was wir bauen, fragt eher an. Dieselben Beispiele stehen auch auf Ihrer Empfehlungsseite, anklickbar.', 'en' => 'Three real websites built by Vecom Design. People who see what we build ask sooner. The same examples are on your page, clickable.'],
        'bw_sprache' => ['it' => 'Lingua del post', 'de' => 'Sprache des Beitrags', 'en' => 'Language of the post'],
        'bw_beitrag' => ['it' => "Questi siti li ha realizzati Vecom Design, dia un’occhiata:\n\n{liste}\n\nVuole qualcosa del genere per la sua attività? Si parte da qui: {link}",
                         'de' => "Diese Websites hat Vecom Design gebaut, schauen Sie selbst:\n\n{liste}\n\nSo etwas für Ihren Betrieb? Hier geht es los: {link}",
                         'en' => "Vecom Design built these websites, take a look:\n\n{liste}\n\nWant something like this for your business? Start here: {link}"],
        // Mini-Kurs
        'ku_titel'   => ['it' => 'Il suo primo cliente in 7 giorni', 'de' => 'Ihr erster Kunde in 7 Tagen', 'en' => 'Your first customer in 7 days'],
        'ku_text'    => ['it' => 'Ogni giorno un piccolo compito da 5 minuti. Si sblocca un giorno alla volta.', 'de' => 'Jeden Tag eine kleine Aufgabe von 5 Minuten. Jeden Tag wird eine freigeschaltet.', 'en' => 'Every day a small 5-minute task. One unlocks each day.'],
        'ku_tag'     => ['it' => 'Giorno {n}', 'de' => 'Tag {n}', 'en' => 'Day {n}'],
        'ku_stand'   => ['it' => '{n} di 7 fatti', 'de' => '{n} von 7 erledigt', 'en' => '{n} of 7 done'],
        'ku_morgen'  => ['it' => 'Domani', 'de' => 'Morgen', 'en' => 'Tomorrow'],
        'ku_gesperrt'=> ['it' => 'Si sblocca il giorno {n}', 'de' => 'Wird an Tag {n} freigeschaltet', 'en' => 'Unlocks on day {n}'],
        'ku_los'     => ['it' => 'Vai', 'de' => 'Los', 'en' => 'Go'],
        'ku_ok'      => ['it' => 'Fatto', 'de' => 'Erledigt', 'en' => 'Done'],
        'ku_fertig'  => ['it' => 'Corso completato — complimenti! Ora vale una cosa sola: restare costanti.', 'de' => 'Kurs geschafft — Glückwunsch! Jetzt zählt nur noch eins: dranbleiben.', 'en' => 'Course completed — congratulations! Now only one thing counts: keep going.'],
        'ku_push'    => ['it' => 'Giorno {n} di 7: {titel}', 'de' => 'Tag {n} von 7: {titel}', 'en' => 'Day {n} of 7: {titel}'],
        'ku_push_x'  => ['it' => '5 minuti oggi — il compito è pronto nella sua pagina.', 'de' => 'Heute 5 Minuten — die Aufgabe liegt auf Ihrer Seite bereit.', 'en' => '5 minutes today — the task is waiting on your page.'],
        'kurs' => [
            1 => ['titel' => ['it' => 'Foto e una frase personale', 'de' => 'Foto und ein persönlicher Satz', 'en' => 'Photo and a personal sentence'],
                  'text'  => ['it' => 'La gente si fida delle persone, non dei loghi. Una foto e una frase su perché consiglia Vecom rendono ogni suo link più credibile.', 'de' => 'Menschen vertrauen Menschen, nicht Logos. Ein Foto und ein Satz, warum Sie Vecom empfehlen, machen jeden Ihrer Links glaubwürdiger.', 'en' => 'People trust people, not logos. A photo and a sentence about why you recommend Vecom make every link of yours more credible.']],
            2 => ['titel' => ['it' => 'Personalizzi la sua pagina', 'de' => 'Ihre Empfehlungsseite gestalten', 'en' => 'Design your recommendation page'],
                  'text'  => ['it' => 'Colore, immagine, due righe con parole sue. Chi apre il suo link deve sentire lei, non una pubblicità.', 'de' => 'Farbe, Bild, zwei Zeilen in Ihren Worten. Wer Ihren Link öffnet, soll Sie spüren, nicht eine Werbung.', 'en' => 'Colour, image, two lines in your own words. Whoever opens your link should feel you, not an ad.']],
            3 => ['titel' => ['it' => 'Il primo post', 'de' => 'Der erste Beitrag', 'en' => 'The first post'],
                  'text'  => ['it' => 'Scelga un testo pronto e lo pubblichi nello stato di WhatsApp o in una storia. Basta una volta per iniziare.', 'de' => 'Nehmen Sie eine fertige Vorlage und stellen Sie sie in Ihren WhatsApp-Status oder eine Story. Einmal reicht für den Anfang.', 'en' => 'Pick a ready-made text and put it in your WhatsApp status or a story. Once is enough to start.']],
            4 => ['titel' => ['it' => 'Cinque persone da contattare', 'de' => 'Fünf Leute notieren', 'en' => 'Note down five people'],
                  'text'  => ['it' => 'Pensi a cinque persone con un’attività: parenti, amici, il suo parrucchiere. Le annoti in «I miei contatti».', 'de' => 'Denken Sie an fünf Leute mit einem Betrieb: Familie, Freunde, Ihr Friseur. Notieren Sie sie unter „Meine Kontakte“.', 'en' => 'Think of five people with a business: family, friends, your hairdresser. Note them down under “My contacts”.']],
            5 => ['titel' => ['it' => 'Una verifica veloce', 'de' => 'Ein Schnellcheck', 'en' => 'One quick check'],
                  'text'  => ['it' => 'Inserisca il sito di un’attività che conosce: in un minuto ha un rapporto da mandare. È il modo più facile per iniziare una conversazione.', 'de' => 'Geben Sie die Website eines Betriebs ein, den Sie kennen: In einer Minute haben Sie einen Bericht zum Schicken. Der leichteste Gesprächseinstieg.', 'en' => 'Enter the website of a business you know: in a minute you have a report to send. The easiest way to start a conversation.']],
            6 => ['titel' => ['it' => 'Un’attività nella sua zona', 'de' => 'Ein Betrieb in Ihrer Nähe', 'en' => 'A business near you'],
                  'text'  => ['it' => 'Cerchi nel trova-attività un’azienda della sua città e la prenoti: per 60 giorni è solo sua.', 'de' => 'Suchen Sie im Firmen-Finder einen Betrieb in Ihrem Ort und reservieren Sie ihn: 60 Tage gehört er Ihnen.', 'en' => 'Search the business finder for a company in your town and reserve it: it’s yours for 60 days.']],
            7 => ['titel' => ['it' => 'Ricontattare', 'de' => 'Nachhaken', 'en' => 'Follow up'],
                  'text'  => ['it' => 'Scriva di nuovo a chi non ha ancora risposto. Il testo è pronto in «Da ricontattare». Qui nascono i primi clienti.', 'de' => 'Schreiben Sie allen noch einmal, die noch nicht geantwortet haben. Der Text liegt unter „Nachhaken“. Hier entstehen die ersten Kunden.', 'en' => 'Write again to everyone who hasn’t replied yet. The text is under “Follow up”. This is where first customers come from.']],
        ],
        // Meilensteine
        'ms_titel'   => ['it' => 'Traguardi', 'de' => 'Meilensteine', 'en' => 'Milestones'],
        'ms_text'    => ['it' => 'Ogni traguardo raggiunto si può condividere come immagine.', 'de' => 'Jeden erreichten Meilenstein können Sie als Bild teilen.', 'en' => 'You can share every milestone you reach as an image.'],
        'ms_teilen'  => ['it' => 'Immagine da condividere', 'de' => 'Bild zum Teilen', 'en' => 'Image to share'],
        'ms_push_t'  => ['it' => 'Traguardo raggiunto: {titel}', 'de' => 'Meilenstein erreicht: {titel}', 'en' => 'Milestone reached: {titel}'],
        'ms_push_x'  => ['it' => 'Complimenti! Lo trova nella sua pagina, anche come immagine da condividere.', 'de' => 'Glückwunsch! Sie finden ihn auf Ihrer Seite, auch als Bild zum Teilen.', 'en' => 'Congratulations! You’ll find it on your page, also as an image to share.'],
        'ms_karte'   => ['it' => 'Traguardo', 'de' => 'Meilenstein', 'en' => 'Milestone'],
        'ms_karte_unter' => ['it' => 'Consiglio Vecom Design — siti web con prezzo chiaro prima.', 'de' => 'Ich empfehle Vecom Design — Websites mit klarem Preis vorher.', 'en' => 'I recommend Vecom Design — websites with a clear price upfront.'],
        'meilensteine' => [
            'profil'  => ['it' => 'Profilo completo', 'de' => 'Profil komplett', 'en' => 'Profile complete'],
            'klick1'  => ['it' => 'Prima visita', 'de' => 'Erster Besuch', 'en' => 'First visit'],
            'klick10' => ['it' => '10 visite', 'de' => '10 Besuche', 'en' => '10 visits'],
            'klick100'=> ['it' => '100 visite', 'de' => '100 Besuche', 'en' => '100 visits'],
            'check5'  => ['it' => '5 verifiche inviate', 'de' => '5 Schnellchecks', 'en' => '5 quick checks'],
            'anfrage1'=> ['it' => 'Prima richiesta', 'de' => 'Erste Anfrage', 'en' => 'First enquiry'],
            'kunde1'  => ['it' => 'Primo cliente', 'de' => 'Erster Kunde', 'en' => 'First customer'],
            'kunde5'  => ['it' => '5 clienti', 'de' => '5 Kunden', 'en' => '5 customers'],
            'geld1'   => ['it' => 'Primo pagamento', 'de' => 'Erste Auszahlung', 'en' => 'First payout'],
            'kurs'    => ['it' => 'Corso di 7 giorni', 'de' => '7-Tage-Kurs', 'en' => '7-day course'],
        ],
        // Meine Kontakte
        'mk_titel'   => ['it' => 'I miei contatti', 'de' => 'Meine Kontakte', 'en' => 'My contacts'],
        'mk_text'    => ['it' => 'Persone della sua cerchia con un’attività. Resta solo su questo telefono — noi non vediamo nulla.', 'de' => 'Leute aus Ihrem Umfeld mit einem Betrieb. Bleibt nur auf diesem Gerät gespeichert — wir sehen davon nichts.', 'en' => 'People you know who run a business. Stored only on this device — we see none of it.'],
        'mk_name'    => ['it' => 'Nome', 'de' => 'Name', 'en' => 'Name'],
        'mk_branche' => ['it' => 'Settore', 'de' => 'Branche', 'en' => 'Industry'],
        'mk_notiz'   => ['it' => 'Nota (facoltativa)', 'de' => 'Notiz (freiwillig)', 'en' => 'Note (optional)'],
        'mk_neu'     => ['it' => 'Aggiungi', 'de' => 'Hinzufügen', 'en' => 'Add'],
        'mk_leer'    => ['it' => 'Ancora nessuno. Inizi con cinque persone.', 'de' => 'Noch niemand. Fangen Sie mit fünf Leuten an.', 'en' => 'Nobody yet. Start with five people.'],
        'mk_nachricht'=> ['it' => 'Messaggio', 'de' => 'Nachricht', 'en' => 'Message'],
        'mk_weg'     => ['it' => 'Rimuovi', 'de' => 'Entfernen', 'en' => 'Remove'],
        'mk_voll'    => ['it' => 'Massimo 30 contatti.', 'de' => 'Höchstens 30 Kontakte.', 'en' => 'At most 30 contacts.'],
        'mk_nachhaken'=> ['it' => 'Scritto {n} giorni fa — ricontattare?', 'de' => 'Vor {n} Tagen angeschrieben — nachhaken?', 'en' => 'Contacted {n} days ago — follow up?'],
        'mk_andere'  => ['it' => 'Altro', 'de' => 'Andere', 'en' => 'Other'],
        'mk_status'  => [
            'neu'          => ['it' => 'Da contattare', 'de' => 'Noch anschreiben', 'en' => 'To contact'],
            'angeschrieben'=> ['it' => 'Scritto', 'de' => 'Angeschrieben', 'en' => 'Contacted'],
            'interessiert' => ['it' => 'Interessato', 'de' => 'Interessiert', 'en' => 'Interested'],
            'kunde'        => ['it' => 'Cliente', 'de' => 'Kunde', 'en' => 'Customer'],
            'nein'         => ['it' => 'Non ora', 'de' => 'Gerade nicht', 'en' => 'Not now'],
        ],
        'mk_msg' => ['it' => "Ciao {name}! Ti scrivo perché collaboro con Vecom Design: fanno siti web per attività come la tua, e il prezzo lo sai prima. {satz}\n\nDai un’occhiata, senza impegno: {link}", 'de' => "Guten Tag {name}! Ich schreibe Ihnen, weil ich mit Vecom Design zusammenarbeite: Dort entstehen Websites für Betriebe wie Ihren, und den Preis kennen Sie vorher. {satz}\n\nSchauen Sie gern unverbindlich hinein: {link}", 'en' => "Hi {name}! I’m writing because I work with Vecom Design: they build websites for businesses like yours, and you know the price upfront. {satz}\n\nHave a look, no obligation: {link}"],
    ];

    /**
     * Branchen-Pakete (28.09.2026, Uwe: Ja). Schlüssel wie die Motive in
     * PARTNER_MEDIEN, damit das passende Bild dazugehört. {link} ist der
     * Partnerlink mit Kanal „branche“.
     */
    public const PARTNER_BRANCHEN = [
        'gastro' => [
            'warum' => ['it' => 'Chi cerca dove mangiare guarda sul telefono: menu, foto, orari, prenotazione. Senza sito decide Google Maps o la concorrenza.', 'de' => 'Wer essen gehen will, schaut aufs Handy: Karte, Fotos, Öffnungszeiten, Reservierung. Ohne Website entscheidet Google Maps — oder die Konkurrenz.', 'en' => 'People looking for a place to eat check their phone: menu, photos, opening hours, booking. Without a website, Google Maps — or the competition — decides.'],
            'args'  => [['it' => 'Menu sempre aggiornato, anche in inglese e tedesco per i turisti.', 'de' => 'Speisekarte immer aktuell, auch auf Englisch und Deutsch für Touristen.', 'en' => 'Menu always up to date, also in English and German for tourists.'],
                        ['it' => 'Prenotazioni e WhatsApp con un tocco, senza commissioni ai portali.', 'de' => 'Reservierung und WhatsApp mit einem Tippen — ohne Provision an Portale.', 'en' => 'Bookings and WhatsApp with one tap — no commission to portals.'],
                        ['it' => 'Foto vere del locale che fanno venire fame.', 'de' => 'Echte Fotos vom Lokal, die Hunger machen.', 'en' => 'Real photos of the place that make people hungry.']],
            'satz'  => ['it' => 'I turisti la trovano su Google? E vedono subito il menu?', 'de' => 'Finden Touristen Sie auf Google — und sehen sie gleich die Karte?', 'en' => 'Do tourists find you on Google — and see the menu right away?'],
            'wa'    => ['it' => "Buongiorno, sono {name}. Lavoro con Vecom Design, che fa siti per ristoranti e bar: menu sempre aggiornato, anche per i turisti, e prenotazioni con un tocco. Il prezzo lo conoscete prima.\n\nQui trovate tutto: {link}", 'de' => "Guten Tag, hier ist {name}. Ich arbeite mit Vecom Design zusammen — die bauen Websites für Restaurants und Bars: Karte immer aktuell, auch für Touristen, Reservierung mit einem Tippen. Den Preis kennen Sie vorher.\n\nHier ist alles: {link}", 'en' => "Hello, this is {name}. I work with Vecom Design, who build websites for restaurants and bars: menu always up to date, also for tourists, and bookings with one tap. You know the price upfront.\n\nEverything is here: {link}"],
            'post'  => ['it' => "Conosci un ristorante o un bar che merita di più online? Vecom Design fa siti con menu, foto e prenotazioni — e il prezzo lo sai prima. {link}", 'de' => "Kennen Sie ein Restaurant oder eine Bar, die online mehr verdient? Vecom Design baut Websites mit Karte, Fotos und Reservierung — und den Preis kennen Sie vorher. {link}", 'en' => "Know a restaurant or bar that deserves more online? Vecom Design builds websites with menu, photos and booking — and you know the price upfront. {link}"],
        ],
        'unterkunft' => [
            'warum' => ['it' => 'Ogni prenotazione diretta risparmia la commissione di Booking. Un bel sito trasforma chi guarda in chi prenota.', 'de' => 'Jede Direktbuchung spart die Provision von Booking. Eine schöne Website macht aus Schauenden Buchende.', 'en' => 'Every direct booking saves the Booking.com commission. A beautiful website turns lookers into bookers.'],
            'args'  => [['it' => 'Prenotazioni dirette: meno commissioni ai portali.', 'de' => 'Direktbuchungen: weniger Provision an Portale.', 'en' => 'Direct bookings: less commission to portals.'],
                        ['it' => 'Camere, dintorni e prezzi in tre lingue.', 'de' => 'Zimmer, Umgebung und Preise in drei Sprachen.', 'en' => 'Rooms, surroundings and prices in three languages.'],
                        ['it' => 'Una struttura che si presenta bene si può permettere prezzi migliori.', 'de' => 'Wer sich schön zeigt, kann bessere Preise nehmen.', 'en' => 'A place that presents itself well can charge better prices.']],
            'satz'  => ['it' => 'Quanto paga di commissioni ai portali ogni anno?', 'de' => 'Wie viel Provision zahlen Sie im Jahr an die Portale?', 'en' => 'How much commission do you pay the portals each year?'],
            'wa'    => ['it' => "Buongiorno, sono {name}. Lavoro con Vecom Design, che fa siti per hotel, B&B e case vacanza: più prenotazioni dirette, meno commissioni. Il prezzo lo conoscete prima.\n\nQui trovate tutto: {link}", 'de' => "Guten Tag, hier ist {name}. Ich arbeite mit Vecom Design zusammen — die bauen Websites für Hotels, B&Bs und Ferienwohnungen: mehr Direktbuchungen, weniger Provision. Den Preis kennen Sie vorher.\n\nHier ist alles: {link}", 'en' => "Hello, this is {name}. I work with Vecom Design, who build websites for hotels, B&Bs and holiday homes: more direct bookings, less commission. You know the price upfront.\n\nEverything is here: {link}"],
            'post'  => ['it' => "Hai un B&B o una casa vacanza? Con un sito tuo arrivano prenotazioni dirette, senza commissioni. Vecom Design lo fa con prezzo chiaro prima: {link}", 'de' => "Sie haben ein B&B oder eine Ferienwohnung? Mit einer eigenen Website kommen Direktbuchungen — ohne Provision. Vecom Design macht das mit klarem Preis vorher: {link}", 'en' => "Run a B&B or holiday home? With your own website, direct bookings come in — no commission. Vecom Design does it with a clear price upfront: {link}"],
        ],
        'handwerk' => [
            'warum' => ['it' => 'Chi cerca un artigiano vuole vedere lavori fatti e un numero da chiamare. Il passaparola funziona meglio se c’è un sito da mostrare.', 'de' => 'Wer einen Handwerker sucht, will fertige Arbeiten sehen und eine Nummer zum Anrufen. Mundpropaganda wirkt besser, wenn es eine Seite zum Zeigen gibt.', 'en' => 'People looking for a tradesperson want to see finished work and a number to call. Word of mouth works better with a website to show.'],
            'args'  => [['it' => 'Galleria di lavori fatti: la prova migliore.', 'de' => 'Galerie fertiger Arbeiten: der beste Beweis.', 'en' => 'Gallery of finished work: the best proof.'],
                        ['it' => 'Richieste di preventivo direttamente dal telefono.', 'de' => 'Angebotsanfragen direkt vom Handy.', 'en' => 'Quote requests straight from the phone.'],
                        ['it' => 'Trovati da chi cerca «idraulico» o «elettricista» in zona.', 'de' => 'Gefunden von allen, die „Elektriker“ oder „Maler“ in der Nähe suchen.', 'en' => 'Found by everyone searching “electrician” or “plumber” nearby.']],
            'satz'  => ['it' => 'Se un cliente nuovo vuole vedere i suoi lavori, cosa gli mostra?', 'de' => 'Wenn ein neuer Kunde Ihre Arbeiten sehen will — was zeigen Sie ihm?', 'en' => 'When a new customer wants to see your work — what do you show them?'],
            'wa'    => ['it' => "Buongiorno, sono {name}. Lavoro con Vecom Design, che fa siti per artigiani e imprese: lavori fatti in galleria e richieste di preventivo dal telefono. Il prezzo lo conoscete prima.\n\nQui trovate tutto: {link}", 'de' => "Guten Tag, hier ist {name}. Ich arbeite mit Vecom Design zusammen — die bauen Websites für Handwerk und Bau: fertige Arbeiten in der Galerie, Angebotsanfragen vom Handy. Den Preis kennen Sie vorher.\n\nHier ist alles: {link}", 'en' => "Hello, this is {name}. I work with Vecom Design, who build websites for tradespeople and builders: finished work in a gallery and quote requests from the phone. You know the price upfront.\n\nEverything is here: {link}"],
            'post'  => ['it' => "Il lavoro buono si vede. Conosci un artigiano senza sito? Vecom Design gli fa vedere i lavori online, prezzo chiaro prima: {link}", 'de' => "Gute Arbeit soll man sehen. Kennen Sie einen Handwerker ohne Website? Vecom Design zeigt seine Arbeiten online — klarer Preis vorher: {link}", 'en' => "Good work should be seen. Know a tradesperson without a website? Vecom Design shows their work online — clear price upfront: {link}"],
        ],
        'laden' => [
            'warum' => ['it' => 'Prima di entrare in un negozio o prenotare un appuntamento, i clienti guardano online. Chi non si trova, non esiste.', 'de' => 'Bevor jemand in einen Laden geht oder einen Termin bucht, schaut er online. Wer nicht zu finden ist, existiert nicht.', 'en' => 'Before walking into a shop or booking an appointment, customers look online. If you can’t be found, you don’t exist.'],
            'args'  => [['it' => 'Orari, prodotti e servizi sempre visibili.', 'de' => 'Öffnungszeiten, Produkte und Leistungen immer sichtbar.', 'en' => 'Opening hours, products and services always visible.'],
                        ['it' => 'Appuntamenti prenotabili online, anche la sera.', 'de' => 'Termine online buchbar, auch abends.', 'en' => 'Appointments bookable online, even in the evening.'],
                        ['it' => 'Una vetrina che lavora 24 ore su 24.', 'de' => 'Ein Schaufenster, das rund um die Uhr arbeitet.', 'en' => 'A shop window that works around the clock.']],
            'satz'  => ['it' => 'I clienti possono prenotare da lei anche la sera, dal divano?', 'de' => 'Können Kunden bei Ihnen auch abends vom Sofa aus buchen?', 'en' => 'Can customers book with you in the evening, from the sofa?'],
            'wa'    => ['it' => "Buongiorno, sono {name}. Lavoro con Vecom Design, che fa siti per negozi, parrucchieri ed estetiste: orari, servizi e appuntamenti online. Il prezzo lo conoscete prima.\n\nQui trovate tutto: {link}", 'de' => "Guten Tag, hier ist {name}. Ich arbeite mit Vecom Design zusammen — die bauen Websites für Läden, Friseure und Kosmetik: Öffnungszeiten, Leistungen und Termine online. Den Preis kennen Sie vorher.\n\nHier ist alles: {link}", 'en' => "Hello, this is {name}. I work with Vecom Design, who build websites for shops, hairdressers and beauty salons: opening hours, services and online appointments. You know the price upfront.\n\nEverything is here: {link}"],
            'post'  => ['it' => "Il tuo parrucchiere, il negozio sotto casa: si trovano online? Vecom Design fa siti con appuntamenti online, prezzo chiaro prima: {link}", 'de' => "Ihr Friseur, der Laden um die Ecke — findet man sie online? Vecom Design baut Websites mit Online-Terminen, klarer Preis vorher: {link}", 'en' => "Your hairdresser, the shop around the corner — can people find them online? Vecom Design builds websites with online booking, clear price upfront: {link}"],
        ],
        'praxis' => [
            'warum' => ['it' => 'Pazienti e clienti vogliono capire subito chi sei, cosa offri e come prenotare. Un sito serio crea fiducia prima del primo incontro.', 'de' => 'Patienten und Kunden wollen sofort sehen, wer Sie sind, was Sie anbieten und wie sie einen Termin bekommen. Eine seriöse Website schafft Vertrauen vor dem ersten Treffen.', 'en' => 'Patients and clients want to see right away who you are, what you offer and how to book. A serious website builds trust before the first meeting.'],
            'args'  => [['it' => 'Servizi, orari e contatti chiari.', 'de' => 'Leistungen, Zeiten und Kontakt klar auf einen Blick.', 'en' => 'Services, hours and contact clear at a glance.'],
                        ['it' => 'Prenotazione di appuntamenti senza telefonate.', 'de' => 'Terminbuchung ohne Telefonschleife.', 'en' => 'Appointment booking without phone tag.'],
                        ['it' => 'Un’immagine professionale che rassicura.', 'de' => 'Ein professioneller Auftritt, der beruhigt.', 'en' => 'A professional presence that reassures.']],
            'satz'  => ['it' => 'Quante chiamate al giorno sono solo per chiedere orari o appuntamenti?', 'de' => 'Wie viele Anrufe am Tag sind nur Fragen nach Zeiten oder Terminen?', 'en' => 'How many calls a day are just about hours or appointments?'],
            'wa'    => ['it' => "Buongiorno, sono {name}. Lavoro con Vecom Design, che fa siti per studi, ambulatori e professionisti: servizi chiari e appuntamenti online. Il prezzo lo conoscete prima.\n\nQui trovate tutto: {link}", 'de' => "Guten Tag, hier ist {name}. Ich arbeite mit Vecom Design zusammen — die bauen Websites für Praxen, Studios und Selbstständige: klare Leistungen und Online-Termine. Den Preis kennen Sie vorher.\n\nHier ist alles: {link}", 'en' => "Hello, this is {name}. I work with Vecom Design, who build websites for practices, studios and professionals: clear services and online appointments. You know the price upfront.\n\nEverything is here: {link}"],
            'post'  => ['it' => "Studi, palestre, professionisti: un sito chiaro fa arrivare clienti e toglie telefonate. Vecom Design, prezzo chiaro prima: {link}", 'de' => "Praxen, Studios, Selbstständige: Eine klare Website bringt Kunden und spart Anrufe. Vecom Design, klarer Preis vorher: {link}", 'en' => "Practices, studios, professionals: a clear website brings clients and saves calls. Vecom Design, clear price upfront: {link}"],
        ],
    ];

    public const PARTNER_MARKETING = [
        // Posting-Kalender
        'ka_titel'    => ['it' => 'Da pubblicare oggi', 'de' => 'Heute posten', 'en' => 'Post today'],
        'ka_text'     => ['it' => 'Ogni giorno un post pronto con il suo link. Lo copi, aggiunga un’immagine da «Immagini per i social» e pubblichi.', 'de' => 'Jeden Tag ein fertiger Beitrag mit Ihrem Link. Kopieren, ein Bild aus „Bilder für soziale Netze“ dazu — fertig.', 'en' => 'A ready-made post with your link every day. Copy it, add an image from “Images for social media”, done.'],
        'ka_heute'    => ['it' => 'Oggi', 'de' => 'Heute', 'en' => 'Today'],
        'ka_anlass'   => ['it' => 'Ricorrenza', 'de' => 'Anlass', 'en' => 'Occasion'],
        'ka_bald'     => ['it' => 'Tra {n} giorni: {anlass} ({datum}).', 'de' => 'In {n} Tagen: {anlass} ({datum}).', 'en' => 'In {n} days: {anlass} ({datum}).'],
        'ka_woche'    => ['it' => 'I prossimi giorni', 'de' => 'Die nächsten Tage', 'en' => 'The next few days'],
        'ka_kopieren' => ['it' => 'Copia il testo', 'de' => 'Text kopieren', 'en' => 'Copy text'],
        'ka_wa'       => ['it' => 'Su WhatsApp', 'de' => 'Per WhatsApp', 'en' => 'On WhatsApp'],
        /* Handy-Vorschau (03.10.2026, Uwe: Ja zu D2) */
        'hv_knopf'     => ['it' => 'Vedi sul telefono', 'de' => 'Am Handy ansehen', 'en' => 'Preview on phone'],
        'hv_titel'     => ['it' => 'Così lo vedono i suoi contatti', 'de' => 'So sehen es Ihre Kontakte', 'en' => 'This is what your contacts see'],
        'hv_schliessen'=> ['it' => 'Chiudi', 'de' => 'Schließen', 'en' => 'Close'],
        'ka_teilen'   => ['it' => 'Condividi …', 'de' => 'Teilen …', 'en' => 'Share …'],
        'ka_bild'     => ['it' => 'Crea un’immagine', 'de' => 'Bild dazu erstellen', 'en' => 'Create an image'],
        'tage'        => ['it' => ['dom', 'lun', 'mar', 'mer', 'gio', 'ven', 'sab'], 'de' => ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'], 'en' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']],
        'monate'      => ['it' => ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'],
                          'de' => ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
                          'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']],
        // Stufen
        'st_naechst2' => ['it' => 'Ancora {fehlen} vendite e passa a {naechste}: poi {satz} su ogni vendita.', 'de' => 'Noch {fehlen} Verkäufe bis {naechste} — dann {satz} auf jeden Verkauf.', 'en' => '{fehlen} more sales to reach {naechste}, then {satz} on every sale.'],
        'st_balken'   => ['it' => 'Avanzamento verso {naechste}', 'de' => 'Fortschritt bis {naechste}', 'en' => 'Progress to {naechste}'],
        // Monatswettbewerb
        'wb_titel'    => ['it' => 'Classifica di {monat}', 'de' => 'Rangliste {monat}', 'en' => '{monat} leaderboard'],
        'wb_text'     => ['it' => 'Chi porta più vendite questo mese; a pari merito, più clienti nuovi. Senza importi e senza nomi di clienti.', 'de' => 'Wer diesen Monat die meisten Verkäufe bringt, bei Gleichstand die meisten neuen Kunden. Ohne Beträge und ohne Kundennamen.', 'en' => 'Who brings the most sales this month; on a tie, the most new customers. No amounts, no customer names.'],
        'wb_leer'     => ['it' => 'Questo mese nessuno ha ancora fatto punti. Il primo cliente nuovo la porta al primo posto.', 'de' => 'Diesen Monat hat noch niemand gepunktet. Der erste neue Kunde bringt Sie auf Platz 1.', 'en' => 'Nobody has scored yet this month. Your first new customer puts you in first place.'],
        'wb_sie'      => ['it' => 'Lei', 'de' => 'Sie', 'en' => 'You'],
        'wb_partner'  => ['it' => 'Partner', 'de' => 'Partner', 'en' => 'Partner'],
        'wb_zeile'    => ['it' => 'Vendite: {v} · Clienti: {k}', 'de' => 'Verkäufe: {v} · Kunden: {k}', 'en' => 'Sales: {v} · Customers: {k}'],
        'wb_nicht'    => ['it' => 'Questo mese non è ancora in classifica: basta un cliente nuovo.', 'de' => 'Sie stehen diesen Monat noch nicht in der Liste — ein neuer Kunde genügt.', 'en' => 'You’re not on the list yet this month; one new customer is enough.'],
        'wb_name'     => ['it' => 'Mostrare il mio nome ({name}) in classifica', 'de' => 'Meinen Vornamen ({name}) in der Rangliste zeigen', 'en' => 'Show my first name ({name}) on the leaderboard'],
        'wb_name_klein' => ['it' => 'Altrimenti appare solo come «Partner».', 'de' => 'Sonst stehen Sie dort nur als „Partner“.', 'en' => 'Otherwise you appear only as “Partner”.'],
        'wb_speichern' => ['it' => 'Salva', 'de' => 'Speichern', 'en' => 'Save'],
        // Mappe zum Vorbeibringen
        'mp_knopf'    => ['it' => 'Cartella da stampare', 'de' => 'Mappe drucken', 'en' => 'Print folder'],
        'mp_erklaer'  => ['it' => 'Un foglio A4 da lasciare di persona: cosa abbiamo notato, come lavora Vecom Design, la sua foto e il codice QR.', 'de' => 'Ein A4-Blatt zum Vorbeibringen: was aufgefallen ist, wie Vecom Design arbeitet, Ihr Foto und der QR-Code.', 'en' => 'An A4 sheet to drop off in person: what we noticed, how Vecom Design works, your photo and the QR code.'],
        'mp_fuer'     => ['it' => 'Per {firma}', 'de' => 'Für {firma}', 'en' => 'For {firma}'],
        'mp_lead'     => ['it' => 'Preparato il {datum} da {name}, partner di Vecom Design.', 'de' => 'Zusammengestellt am {datum} von {name}, Partner von Vecom Design.', 'en' => 'Put together on {datum} by {name}, partner of Vecom Design.'],
        'mp_ohne_titel' => ['it' => 'Online la sua attività si trova a fatica', 'de' => 'Online ist Ihr Betrieb kaum zu finden', 'en' => 'Your business is hard to find online'],
        'mp_ohne'     => ['it' => ['Chi cerca {was} su Google trova prima gli altri.', 'Dal telefono mancano orari, contatti e indicazioni a colpo d’occhio.', 'Un sito tutto suo crea fiducia prima ancora che qualcuno chiami.'],
                          'de' => ['Wer bei Google nach {was} sucht, findet zuerst andere.', 'Am Handy fehlen Öffnungszeiten, Kontakt und Anfahrt auf einen Blick.', 'Eine eigene Seite schafft Vertrauen, noch bevor jemand anruft.'],
                          'en' => ['People searching Google for {was} find others first.', 'On a phone, opening hours, contact and directions aren’t there at a glance.', 'A website of your own builds trust before anyone calls.']],
        'mp_was'      => ['it' => '{branche} a {ort}', 'de' => '{branche} in {ort}', 'en' => '{branche} in {ort}'],
        'mp_was_leer' => ['it' => 'la sua attività', 'de' => 'Ihrem Betrieb', 'en' => 'your business'],
        'mp_liste_titel' => ['it' => 'Cosa deve saper fare oggi un sito', 'de' => 'Was eine Website heute können muss', 'en' => 'What a website needs to do today'],
        'mp_liste'    => ['it' => ['Si legge bene sul telefono.', 'Si carica in pochi secondi.', 'Su Google ha un titolo e una descrizione chiari.', 'È cifrato (https) e aggiornato.'],
                          'de' => ['Am Handy gut lesbar.', 'In wenigen Sekunden geladen.', 'Bei Google mit klarem Titel und Beschreibung.', 'Verschlüsselt (https) und aktuell.'],
                          'en' => ['Easy to read on a phone.', 'Loads in a few seconds.', 'Clear title and description on Google.', 'Encrypted (https) and up to date.']],
        'mp_liste_klein' => ['it' => 'Se vuole, {name} le fa gratis la verifica veloce del suo sito.', 'de' => 'Den kostenlosen Kurz-Check Ihrer Seite macht {name} gern für Sie.', 'en' => '{name} is happy to run the free quick check of your site for you.'],
        'mp_vecom_titel' => ['it' => 'Come lavora Vecom Design', 'de' => 'So arbeitet Vecom Design', 'en' => 'How Vecom Design works'],
        'mp_vecom'    => ['it' => ['Dice cosa le serve: in due minuti, online o al telefono.', 'Conosce il prezzo prima di iniziare. Nessuna sorpresa alla fine.', 'Segue ogni passo nella sua area personale, finché il sito è online.'],
                          'de' => ['Sie sagen, was Sie brauchen: in zwei Minuten, online oder am Telefon.', 'Sie kennen den Preis, bevor es losgeht. Keine Überraschung am Ende.', 'Sie sehen jeden Schritt in Ihrem persönlichen Bereich, bis die Seite online ist.'],
                          'en' => ['You say what you need: two minutes, online or by phone.', 'You know the price before anything starts. No surprises at the end.', 'You follow every step in your personal area until the site is live.']],
        'mp_stimme_titel' => ['it' => 'Dicono i clienti', 'de' => 'Das sagen Kunden', 'en' => 'What customers say'],
        'mp_kontakt_titel' => ['it' => 'Il suo riferimento', 'de' => 'Ihr Ansprechpartner', 'en' => 'Your contact'],
        'mp_scan'     => ['it' => 'Inquadri e chieda gratis', 'de' => 'Scannen und kostenlos anfragen', 'en' => 'Scan and enquire for free'],
        'mp_code'     => ['it' => 'Oppure: {kurz} · codice {code}', 'de' => 'Oder: {kurz} · Code {code}', 'en' => 'Or: {kurz} · code {code}'],
        'mp_klein'    => ['it' => 'Senza impegno. Questo foglio l’ha preparato per lei {name}, partner di Vecom Design (vecom-design.it).', 'de' => 'Unverbindlich. Dieses Blatt hat {name} als Partner von Vecom Design (vecom-design.it) für Sie zusammengestellt.', 'en' => 'No obligation. {name}, a partner of Vecom Design (vecom-design.it), put this sheet together for you.'],
        'mp_drucken'  => ['it' => 'Stampa / Salva come PDF', 'de' => 'Drucken / Als PDF sichern', 'en' => 'Print / Save as PDF'],
        'mp_zurueck'  => ['it' => '← Indietro', 'de' => '← Zurück', 'en' => '← Back'],
        'mp_sprache'  => ['it' => 'Lingua del foglio', 'de' => 'Sprache des Blatts', 'en' => 'Sheet language'],
        // Erfolge als Kacheln
        'kc_titel'    => ['it' => 'Come immagine per i social', 'de' => 'Als Bild für soziale Netze', 'en' => 'As an image for social media'],
        'kc_text'     => ['it' => 'Recensioni e siti finiti come immagine quadrata, con il suo codice QR. Solo ciò che i clienti hanno permesso di mostrare.', 'de' => 'Kundenstimmen und fertige Seiten als quadratisches Bild, mit Ihrem QR-Code. Nur, was Kunden zum Zeigen freigegeben haben.', 'en' => 'Reviews and finished sites as a square image with your QR code. Only what customers have allowed to be shown.'],
        'kc_stimme'   => ['it' => 'Recensione', 'de' => 'Kundenstimme', 'en' => 'Review'],
        'kc_online'   => ['it' => '{firma} è online', 'de' => '{firma} ist online', 'en' => '{firma} is live'],
        'kc_neu'      => ['it' => 'Nuovo sito di Vecom Design', 'de' => 'Neue Website von Vecom Design', 'en' => 'New website by Vecom Design'],
        'kc_laden'    => ['it' => 'Salva immagine', 'de' => 'Bild speichern', 'en' => 'Save image'],
        'kc_teilen'   => ['it' => 'Condividi immagine', 'de' => 'Bild teilen', 'en' => 'Share image'],
        'kc_stimmen_titel' => ['it' => 'Recensioni da condividere', 'de' => 'Kundenstimmen zum Teilen', 'en' => 'Reviews to share'],
    ];

    /** Posting-Kalender der Partner (27.09.2026): Anlässe in Italien und Themen für alle anderen Tage. {link} = Partnerlink mit Kanal „kalender“. */
    public const PARTNER_KALENDER = [
        'anlaesse' => [
            'capodanno' => ['titel' => ['it' => "Capodanno", 'de' => "Neujahr", 'en' => "New Year"],
                'text' => ['it' => "Buon anno! 🥂 Se quest’anno vuoi finalmente un sito tutto tuo: con Vecom Design dici cosa ti serve e sai il prezzo prima.\n\nSi parte da qui: {link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Frohes neues Jahr! 🥂 Wenn Sie sich dieses Jahr endlich eine eigene Website vornehmen: Bei Vecom Design sagen Sie, was Sie brauchen, und kennen den Preis vorher.\n\nHier geht’s los: {link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Happy New Year! 🥂 If this is the year you finally get your own website: with Vecom Design you say what you need and know the price upfront.\n\nStart here: {link}\n\n#ad #website #smallbusiness"]],
            'valentino' => ['titel' => ['it' => "San Valentino", 'de' => "Valentinstag", 'en' => "Valentine’s Day"],
                'text' => ['it' => "Oggi si parla d’amore. ❤️ E i tuoi clienti? Li trovano in un attimo su Google, anche dal telefono? Un sito curato è il primo appuntamento con chi ancora non ti conosce.\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Heute geht’s um Liebe. ❤️ Und Ihre Kunden? Finden sie Sie schnell bei Google, auch am Handy? Eine gepflegte Website ist das erste Date mit allen, die Sie noch nicht kennen.\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Today is all about love. ❤️ What about your customers? Can they find you quickly on Google, even on their phone? A well-kept website is the first date with everyone who doesn’t know you yet.\n\n{link}\n\n#ad #website #smallbusiness"]],
            'donna' => ['titel' => ['it' => "Festa della donna", 'de' => "Weltfrauentag", 'en' => "International Women’s Day"],
                'text' => ['it' => "Auguri a tutte le donne che mandano avanti un’attività! 🌼 Se il tuo lavoro merita di essere visto anche online, Vecom Design ti prepara il sito: prezzo chiaro, ogni passo visibile.\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Alles Gute allen Frauen, die einen Betrieb führen! 🌼 Wenn Ihre Arbeit auch online gesehen werden soll: Vecom Design baut Ihnen die Website — klarer Preis, jeder Schritt sichtbar.\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Best wishes to every woman running a business! 🌼 If your work deserves to be seen online too, Vecom Design builds your website: clear price, every step visible.\n\n{link}\n\n#ad #website #smallbusiness"]],
            'papa' => ['titel' => ['it' => "Festa del papà", 'de' => "Vatertag in Italien", 'en' => "Father’s Day in Italy"],
                'text' => ['it' => "Buona festa del papà! 👔 Tanti papà hanno un’attività e zero tempo per il sito. Con Vecom Design bastano due minuti per dire cosa serve, al resto pensano loro.\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Heute ist in Italien Vatertag! 👔 Viele Väter haben einen Betrieb und null Zeit für die Website. Bei Vecom Design reichen zwei Minuten, um zu sagen, was Sie brauchen — den Rest macht Vecom Design.\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "It’s Father’s Day in Italy! 👔 Plenty of dads run a business and have zero time for a website. With Vecom Design, two minutes are enough to say what you need; they handle the rest.\n\n{link}\n\n#ad #website #smallbusiness"]],
            'pasqua' => ['titel' => ['it' => "Pasqua", 'de' => "Ostern", 'en' => "Easter"],
                'text' => ['it' => "Buona Pasqua! 🕊️ Nei giorni di festa tanti cercano dal telefono dove mangiare, dormire, comprare. Chi ha un sito chiaro viene trovato. Se il tuo non c’è ancora:\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Frohe Ostern! 🕊️ An Feiertagen suchen viele am Handy, wo sie essen, schlafen, einkaufen können. Wer eine klare Website hat, wird gefunden. Falls Ihre noch fehlt:\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Happy Easter! 🕊️ On holidays lots of people search on their phone for where to eat, stay and shop. Businesses with a clear website get found. If yours is still missing:\n\n{link}\n\n#ad #website #smallbusiness"]],
            'lavoro' => ['titel' => ['it' => "Festa dei lavoratori", 'de' => "Tag der Arbeit", 'en' => "Labour Day"],
                'text' => ['it' => "Buon Primo Maggio a chi lavora ogni giorno nella propria attività! 🛠️ Il tuo lavoro è fatto bene: fallo vedere anche online. Vecom Design ti prepara il sito, prezzo prima.\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Einen schönen 1. Mai an alle, die jeden Tag in ihrem Betrieb stehen! 🛠️ Ihre Arbeit ist gut — zeigen Sie sie auch online. Vecom Design baut Ihnen die Website, Preis vorher.\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Happy May Day to everyone who works in their own business every day! 🛠️ Your work is good; show it online too. Vecom Design builds your website, price upfront.\n\n{link}\n\n#ad #website #smallbusiness"]],
            'mamma' => ['titel' => ['it' => "Festa della mamma", 'de' => "Muttertag", 'en' => "Mother’s Day"],
                'text' => ['it' => "Auguri a tutte le mamme! 💐 Fiori, dolci, un pranzo fuori: oggi tanti cercano un regalo dal telefono. Chi ha un sito con orari e contatti viene scelto. Il tuo c’è?\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Alles Gute zum Muttertag! 💐 Blumen, Torte, ein Essen: Heute suchen viele am Handy nach einem Geschenk. Wer eine Website mit Öffnungszeiten und Kontakt hat, wird gewählt. Haben Sie eine?\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Happy Mother’s Day! 💐 Flowers, cake, lunch out: today lots of people look for a gift on their phone. Businesses with opening hours and contact online get chosen. Do you have that?\n\n{link}\n\n#ad #website #smallbusiness"]],
            'repubblica' => ['titel' => ['it' => "Festa della Repubblica", 'de' => "Tag der Republik", 'en' => "Italy’s Republic Day"],
                'text' => ['it' => "Buon 2 giugno! 🇮🇹 Il made in Italy delle nostre attività merita di farsi trovare. Se vuoi un sito fatto bene e un prezzo chiaro prima di iniziare:\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Heute feiert Italien den Tag der Republik. 🇮🇹 Das Made in Italy der Betriebe vor Ort verdient es, gefunden zu werden. Wenn Sie eine gute Website und vorher einen klaren Preis möchten:\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Today Italy celebrates Republic Day. 🇮🇹 The made in Italy of local businesses deserves to be found. If you want a well-made website and a clear price before you start:\n\n{link}\n\n#ad #website #smallbusiness"]],
            'estate' => ['titel' => ['it' => "Inizio dell’estate", 'de' => "Sommeranfang", 'en' => "First day of summer"],
                'text' => ['it' => "Arriva l’estate e arrivano i turisti. ☀️ Cercano su Google, dal telefono, spesso in inglese o tedesco. Il tuo sito parla anche la loro lingua?\n\nVecom Design lo prepara in più lingue: {link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Der Sommer beginnt — und mit ihm die Urlauber. ☀️ Sie suchen bei Google, am Handy, oft auf Englisch oder Deutsch. Spricht Ihre Website ihre Sprache?\n\nVecom Design baut sie mehrsprachig: {link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Summer is here, and so are the tourists. ☀️ They search on Google, on their phone, often in English or German. Does your website speak their language?\n\nVecom Design builds it in several languages: {link}\n\n#ad #website #smallbusiness"]],
            'ferragosto' => ['titel' => ['it' => "Ferragosto", 'de' => "Ferragosto", 'en' => "Ferragosto"],
                'text' => ['it' => "Buon Ferragosto! 🌊 Mentre sei al mare, il tuo sito lavora: mostra orari, prende contatti, fa trovare la tua attività a chi è in vacanza qui. Se non ce l’hai ancora:\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Buon Ferragosto — heute ist in Italien der große Sommerfeiertag! 🌊 Während Sie am Meer sind, arbeitet Ihre Website: Sie zeigt Öffnungszeiten, nimmt Anfragen an und macht Ihren Betrieb für Urlauber sichtbar. Falls Sie noch keine haben:\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Buon Ferragosto — Italy’s big summer holiday! 🌊 While you’re at the beach, your website keeps working: it shows your hours, takes enquiries and makes your business visible to holidaymakers. If you don’t have one yet:\n\n{link}\n\n#ad #website #smallbusiness"]],
            'rientro' => ['titel' => ['it' => "Rientro", 'de' => "Nach den Ferien", 'en' => "Back to work"],
                'text' => ['it' => "Si riparte! 📅 Settembre è il mese dei buoni propositi: se il sito della tua attività è vecchio o non c’è, è il momento giusto. Prezzo chiaro, ogni passo visibile.\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Die Ferien sind vorbei, es geht wieder los! 📅 September ist der Monat der guten Vorsätze: Wenn die Website Ihres Betriebs alt ist oder fehlt, ist jetzt der richtige Moment. Klarer Preis, jeder Schritt sichtbar.\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Back to work! 📅 September is the month for fresh starts: if your business website is old or missing, now is the moment. Clear price, every step visible.\n\n{link}\n\n#ad #website #smallbusiness"]],
            'black_friday' => ['titel' => ['it' => "Black Friday", 'de' => "Black Friday", 'en' => "Black Friday"],
                'text' => ['it' => "Oggi tutti cercano offerte online. 🛍️ Chi ha un sito veloce e chiaro vende anche oggi; chi non ce l’ha resta fuori. Per l’anno prossimo preparati adesso:\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Heute suchen alle online nach Angeboten. 🛍️ Wer eine schnelle, klare Website hat, verkauft auch heute; wer keine hat, bleibt draußen. Fürs nächste Jahr jetzt vorbereiten:\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Today everyone is hunting for deals online. 🛍️ Businesses with a fast, clear website sell today too; the rest are left out. Get ready for next year now:\n\n{link}\n\n#ad #website #smallbusiness"]],
            'immacolata' => ['titel' => ['it' => "Inizio delle feste", 'de' => "Das Weihnachtsgeschäft beginnt", 'en' => "The holiday season starts"],
                'text' => ['it' => "Con l’8 dicembre partono le feste. 🎄 Regali, cene, prenotazioni: tanti cercano dal telefono. Orari, contatti, WhatsApp a portata di clic fanno la differenza.\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Am 8. Dezember beginnt in Italien die Weihnachtszeit. 🎄 Geschenke, Essen, Reservierungen: Viele suchen am Handy. Öffnungszeiten, Kontakt und WhatsApp mit einem Klick machen den Unterschied.\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "In Italy, 8 December opens the holiday season. 🎄 Gifts, dinners, bookings: lots of people search on their phone. Opening hours, contact and WhatsApp one tap away make the difference.\n\n{link}\n\n#ad #website #smallbusiness"]],
            'natale' => ['titel' => ['it' => "Natale", 'de' => "Weihnachten", 'en' => "Christmas"],
                'text' => ['it' => "Buon Natale! ✨ Grazie a chi sostiene le attività della propria zona. Se l’anno prossimo vuoi un sito tutto tuo, sai dove trovarmi:\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Frohe Weihnachten! ✨ Danke an alle, die die Betriebe in ihrer Gegend unterstützen. Wenn Sie nächstes Jahr eine eigene Website möchten, wissen Sie, wo Sie mich finden:\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Merry Christmas! ✨ Thanks to everyone who supports small local businesses. If you want your own website next year, you know where to find me:\n\n{link}\n\n#ad #website #smallbusiness"]],
            /* Deutsche Anlässe (02.10.2026, Uwe: Ja) — für Partner in Deutschland, Österreich, der Schweiz. */
            'vatertag' => ['titel' => ['it' => "Festa del papà in Germania", 'de' => "Vatertag", 'en' => "Father’s Day in Germany"],
                'text' => ['it' => "Oggi in Germania è la festa del papà! 👔 Tanti papà hanno un’attività e poco tempo per il sito. Con Vecom Design bastano due minuti per dire cosa serve, e il prezzo lo sai prima.\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Schönen Vatertag! 👔 Viele Väter führen einen Betrieb und haben kaum Zeit für die Website. Bei Vecom Design reichen zwei Minuten, um zu sagen, was Sie brauchen — den Preis kennen Sie vorher.\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "It’s Father’s Day in Germany! 👔 Plenty of dads run a business and have little time for a website. With Vecom Design, two minutes are enough to say what you need, and you know the price upfront.\n\n{link}\n\n#ad #website #smallbusiness"]],
            'einheit' => ['titel' => ['it' => "Giornata dell’unità tedesca", 'de' => "Tag der Deutschen Einheit", 'en' => "German Unity Day"],
                'text' => ['it' => "Oggi in Germania è festa nazionale. 🇩🇪 Nei giorni di festa tanti cercano dal telefono gite, caffè e negozi vicini. Chi ha un sito chiaro viene trovato. Se il tuo non c’è ancora:\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Einen schönen Tag der Deutschen Einheit! 🇩🇪 Feiertag heißt: Viele haben Zeit und suchen am Handy nach Ausflugszielen, Cafés und Läden in der Nähe. Wer eine klare Website hat, wird gefunden. Falls Ihre noch fehlt:\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Happy German Unity Day! 🇩🇪 A holiday means lots of people search on their phone for day trips, cafés and shops nearby. Businesses with a clear website get found. If yours is still missing:\n\n{link}\n\n#ad #website #smallbusiness"]],
            'advent' => ['titel' => ['it' => "Prima domenica d’Avvento", 'de' => "Erster Advent", 'en' => "First Sunday of Advent"],
                'text' => ['it' => "Buona prima domenica d’Avvento! 🕯️ Inizia il periodo in cui tanti cercano regali e buoni, quasi sempre prima online. Il tuo sito mostra cosa offri e come trovarti?\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Einen schönen ersten Advent! 🕯️ Jetzt beginnt die Zeit, in der viele Geschenke und Gutscheine suchen — meist zuerst online. Zeigt Ihre Website, was Sie anbieten und wie man Sie erreicht?\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Happy first Sunday of Advent! 🕯️ This is when lots of people start looking for gifts and vouchers, usually online first. Does your website show what you offer and how to reach you?\n\n{link}\n\n#ad #website #smallbusiness"]],
        ],
        'themen' => [
            'google' => ['titel' => ['it' => "Su Google", 'de' => "Bei Google", 'en' => "On Google"],
                'text' => ['it' => "Prova a cercare la tua attività su Google, dal telefono. 🔎 Cosa vede un cliente nuovo? Se la risposta non ti piace, Vecom Design ti prepara un sito che si fa trovare.\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Suchen Sie einmal Ihren Betrieb bei Google, am Handy. 🔎 Was sieht ein neuer Kunde? Wenn Ihnen die Antwort nicht gefällt: Vecom Design baut Ihnen eine Website, die gefunden wird.\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Try searching for your business on Google, on your phone. 🔎 What does a new customer see? If you don’t like the answer, Vecom Design builds you a website that gets found.\n\n{link}\n\n#ad #website #smallbusiness"]],
            'handy' => ['titel' => ['it' => "Dal telefono", 'de' => "Am Handy", 'en' => "On the phone"],
                'text' => ['it' => "Oggi quasi tutti cercano dal telefono. 📱 Se il tuo sito sul telefono è minuscolo o lento, il cliente va dal vicino. Un sito pensato per lo smartphone non è un lusso.\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Heute sucht fast jeder am Handy. 📱 Ist Ihre Seite dort winzig oder langsam, geht der Kunde zum Nachbarn. Eine Website fürs Smartphone ist kein Luxus.\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "These days almost everyone searches on their phone. 📱 If your site is tiny or slow there, the customer goes next door. A phone-friendly website isn’t a luxury.\n\n{link}\n\n#ad #website #smallbusiness"]],
            'preis' => ['titel' => ['it' => "Prezzo chiaro", 'de' => "Preis vorher", 'en' => "Price upfront"],
                'text' => ['it' => "Quello che mi piace di Vecom Design: sai il prezzo prima di iniziare. 💶 Niente sorprese a fine lavoro, niente «dipende». Dici cosa ti serve e ricevi un prezzo chiaro.\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Was ich an Vecom Design mag: Sie kennen den Preis, bevor es losgeht. 💶 Keine Überraschung am Ende, kein „kommt drauf an“. Sie sagen, was Sie brauchen, und bekommen einen klaren Preis.\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "What I like about Vecom Design: you know the price before anything starts. 💶 No surprises at the end, no “it depends”. You say what you need and get a clear price.\n\n{link}\n\n#ad #website #smallbusiness"]],
            'schritte' => ['titel' => ['it' => "Ogni passo", 'de' => "Jeden Schritt sehen", 'en' => "Every step"],
                'text' => ['it' => "Con Vecom Design vedi ogni passo del tuo sito nella tua area personale: bozza, correzioni, messa online. 👀 Niente attese al buio.\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Bei Vecom Design sehen Sie jeden Schritt Ihrer Website in Ihrem persönlichen Bereich: Entwurf, Korrekturen, online gehen. 👀 Kein Warten im Dunkeln.\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "With Vecom Design you follow every step of your website in your personal area: draft, changes, going live. 👀 No waiting in the dark.\n\n{link}\n\n#ad #website #smallbusiness"]],
            'facebook' => ['titel' => ['it' => "Solo Facebook?", 'de' => "Nur Facebook?", 'en' => "Only Facebook?"],
                'text' => ['it' => "«Tanto ho la pagina Facebook.» 🤔 Ma chi non usa Facebook non ti trova, e su Google una pagina social conta poco. Un sito tuo resta tuo.\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "„Ich hab doch Facebook.“ 🤔 Wer kein Facebook nutzt, findet Sie aber nicht — und bei Google zählt eine Social-Seite wenig. Eine eigene Website gehört Ihnen.\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "“But I have a Facebook page.” 🤔 People who don’t use Facebook won’t find you, and on Google a social page counts for little. Your own website stays yours.\n\n{link}\n\n#ad #website #smallbusiness"]],
            'frage' => ['titel' => ['it' => "Una domanda", 'de' => "Eine Frage", 'en' => "A question"],
                'text' => ['it' => "Domanda: conosci un’attività qui in zona con un sito vecchio o senza sito? 🙋 Mandale questo link: dice cosa le serve e sa il prezzo prima.\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Frage an euch: Kennt ihr einen Betrieb in der Gegend mit alter oder ohne Website? 🙋 Schickt ihm diesen Link: Er sagt, was er braucht, und kennt den Preis vorher.\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Question for you: do you know a local business with an old website, or none at all? 🙋 Send them this link: they say what they need and know the price upfront.\n\n{link}\n\n#ad #website #smallbusiness"]],
            'check' => ['titel' => ['it' => "Verifica gratuita", 'de' => "Gratis-Check", 'en' => "Free check"],
                'text' => ['it' => "Vuoi sapere come se la cava il sito della tua attività? 📋 Scrivimi l’indirizzo: ti mando una verifica veloce gratuita su velocità, telefono, Google e sicurezza.\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Möchten Sie wissen, wie gut die Website Ihres Betriebs ist? 📋 Schicken Sie mir die Adresse: Ich schicke Ihnen einen kostenlosen Kurz-Check zu Tempo, Handy, Google und Sicherheit.\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Want to know how your business website is doing? 📋 Send me the address: I’ll send you a free quick check on speed, mobile, Google and security.\n\n{link}\n\n#ad #website #smallbusiness"]],
            'lokal' => ['titel' => ['it' => "Attività della zona", 'de' => "Betriebe vor Ort", 'en' => "Local businesses"],
                'text' => ['it' => "Le attività della nostra zona sono il cuore del paese. 🏘️ Aiutiamole a farsi trovare anche online: se ne conosci una senza sito, passale questo link.\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Die Betriebe hier vor Ort sind das Herz unserer Gegend. 🏘️ Helfen wir ihnen, auch online gefunden zu werden: Wenn Sie einen ohne Website kennen, geben Sie ihm diesen Link.\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Local businesses are the heart of our area. 🏘️ Let’s help them get found online too: if you know one without a website, pass on this link.\n\n{link}\n\n#ad #website #smallbusiness"]],
            'vertrauen' => ['titel' => ['it' => "Fiducia", 'de' => "Vertrauen", 'en' => "Trust"],
                'text' => ['it' => "Prima di chiamare, oggi i clienti guardano online. 🤝 Un sito curato, con foto vere e contatti chiari, crea fiducia ancora prima del primo incontro.\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Bevor jemand anruft, schaut er heute online nach. 🤝 Eine gepflegte Website mit echten Fotos und klarem Kontakt schafft Vertrauen, noch bevor ihr euch kennt.\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "Before calling, customers look online first. 🤝 A well-kept website with real photos and clear contact details builds trust before you ever meet.\n\n{link}\n\n#ad #website #smallbusiness"]],
            'zeit' => ['titel' => ['it' => "Due minuti", 'de' => "Zwei Minuten", 'en' => "Two minutes"],
                'text' => ['it' => "Non hai tempo per pensare al sito? ⏱️ Con Vecom Design bastano due minuti per dire cosa ti serve. Poi ricevi il prezzo e decidi con calma.\n\n{link}\n\n#adv #sitoweb #piccoleimprese",
                'de' => "Keine Zeit, sich um eine Website zu kümmern? ⏱️ Bei Vecom Design reichen zwei Minuten, um zu sagen, was Sie brauchen. Dann bekommen Sie den Preis und entscheiden in Ruhe.\n\n{link}\n\n#Werbung #website #kleinunternehmen",
                'en' => "No time to think about a website? ⏱️ With Vecom Design, two minutes are enough to say what you need. Then you get the price and decide in your own time.\n\n{link}\n\n#ad #website #smallbusiness"]],
        ],
    ];
    /** Reiter der Partnerseite wie in einer App (27.09.2026, Uwe: „Reiter wie eine App“).
        kurz = untere Leiste am Handy, titel = Leiste am Rechner und Kopf des Reiters. */
    public const PARTNER_REITER = [
        'aria' => ['it' => 'Sezioni della pagina partner', 'de' => 'Bereiche der Partnerseite', 'en' => 'Partner page sections'],
        /* So geht's je Reiter und Schnellsuche (03.10.2026, Uwe: Ja zu D2) */
        'so'         => ['it' => 'Come funziona', 'de' => 'So geht’s', 'en' => 'How it works'],
        'suche'      => ['it' => 'Cerca: volantino, video, pagamento …', 'de' => 'Suchen: Flyer, Video, Auszahlung …', 'en' => 'Search: flyer, video, payout …'],
        'suche_aria' => ['it' => 'Cerca nella pagina partner', 'de' => 'In der Partnerseite suchen', 'en' => 'Search the partner page'],
        'suche_leer' => ['it' => 'Nessun risultato. Provi con un’altra parola.', 'de' => 'Nichts gefunden. Versuchen Sie ein anderes Wort.', 'en' => 'Nothing found. Try another word.'],
        /* Werben neu geordnet (03.10.2026, Uwe: Ja zu D2) — zwei Gruppen, oben das Fertige */
        'g_teilen'       => ['it' => 'Pronto da condividere', 'de' => 'Fertig zum Teilen', 'en' => 'Ready to share'],
        'g_teilen_satz'  => ['it' => 'Copiare, salvare, condividere — il suo link c’è già.', 'de' => 'Kopieren, speichern, teilen — Ihr Link steckt schon drin.', 'en' => 'Copy, save, share — your link is already in it.'],
        'g_selbst'       => ['it' => 'Da personalizzare', 'de' => 'Selbst gestalten', 'en' => 'Make your own'],
        'g_selbst_satz'  => ['it' => 'Immagini proprie, stampa, biglietti da visita e pacchetti per settore.', 'de' => 'Eigene Bilder, Druck, Visitenkarten und Pakete für Branchen.', 'en' => 'Your own images, print, business cards and industry packs.'],
        'reiter' => [
            'start' => [
                'kurz'  => ['it' => 'Inizio', 'de' => 'Start', 'en' => 'Home'],
                'titel' => ['it' => 'Inizio', 'de' => 'Start', 'en' => 'Home'],
                'satz'  => ['it' => 'Il suo link, i suoi numeri e il prossimo passo.', 'de' => 'Ihr Link, Ihre Zahlen und der nächste Schritt.', 'en' => 'Your link, your numbers and the next step.'],
                'so1'   => ['it' => 'Copi il suo link e lo mandi a cinque conoscenti.', 'de' => 'Ihren Link kopieren und an fünf Bekannte schicken.', 'en' => 'Copy your link and send it to five people you know.'],
                'so2'   => ['it' => 'Ogni giorno: sbrighi «Da fare oggi» qui in alto.', 'de' => 'Jeden Tag: „Heute zu tun“ hier oben abarbeiten.', 'en' => 'Every day: work through “To do today” up here.'],
                'so3'   => ['it' => 'Se qualcuno compra tramite lei, lo vede qui e sotto Guadagni.', 'de' => 'Kauft jemand über Sie, sehen Sie es hier und unter „Geld“.', 'en' => 'If someone buys through you, you see it here and under “Money”.'],
            ],
            'werben' => [
                'kurz'  => ['it' => 'Promuovi', 'de' => 'Werben', 'en' => 'Promote'],
                'titel' => ['it' => 'Promuovere', 'de' => 'Werben', 'en' => 'Promote'],
                'satz'  => ['it' => 'Testi, immagini e video pronti da condividere.', 'de' => 'Fertige Texte, Bilder und Videos zum Teilen.', 'en' => 'Ready-made texts, images and videos to share.'],
                'so1'   => ['it' => 'Sotto «Pubblichi oggi» copi il post già pronto.', 'de' => 'Unter „Heute posten“ den fertigen Beitrag kopieren.', 'en' => 'Under “Post today”, copy the ready-made post.'],
                'so2'   => ['it' => 'Salvi l’immagine o il video — il suo link è già dentro.', 'de' => 'Bild oder Video dazu speichern — Ihr Link steckt schon drin.', 'en' => 'Save the image or video — your link is already in it.'],
                'so3'   => ['it' => 'Lo condivida nello stato WhatsApp, su Instagram o Facebook.', 'de' => 'Im WhatsApp-Status, auf Instagram oder Facebook teilen.', 'en' => 'Share it in your WhatsApp status, on Instagram or Facebook.'],
            ],
            'finden' => [
                'kurz'  => ['it' => 'Clienti', 'de' => 'Kunden', 'en' => 'Customers'],
                'titel' => ['it' => 'Trovare clienti', 'de' => 'Kunden finden', 'en' => 'Find customers'],
                'satz'  => ['it' => 'Attività vicine, il check del sito da mostrare e clienti da segnalare.', 'de' => 'Betriebe in der Nähe, der Website-Check zum Vorzeigen und Kunden direkt melden.', 'en' => 'Businesses nearby, the website check to show and customers to report.'],
                'so1'   => ['it' => 'Chiami le attività scelte da Vecom o visiti quelle vicine.', 'de' => 'Die Anrufliste von Vecom abtelefonieren oder Betriebe in der Nähe besuchen.', 'en' => 'Call the list from Vecom or visit businesses nearby.'],
                'so2'   => ['it' => 'Faccia il check del sito e lo mostri al titolare.', 'de' => 'Den Website-Check machen und dem Inhaber zeigen.', 'en' => 'Run the website check and show it to the owner.'],
                'so3'   => ['it' => 'Interessato? Lo segnali — il cliente è suo.', 'de' => 'Interessiert? Melden — der Kunde gehört Ihnen.', 'en' => 'Interested? Report it — the customer is yours.'],
            ],
            'geld' => [
                'kurz'  => ['it' => 'Guadagni', 'de' => 'Geld', 'en' => 'Money'],
                'titel' => ['it' => 'Guadagni', 'de' => 'Geld', 'en' => 'Money'],
                'satz'  => ['it' => 'Provvigioni, pagamenti e come arrivano i soldi.', 'de' => 'Provisionen, Auszahlungen und wie das Geld zu Ihnen kommt.', 'en' => 'Commissions, payouts and how the money reaches you.'],
                'so1'   => ['it' => 'Scelga una volta come ricevere i soldi.', 'de' => 'Einmal festlegen, wie das Geld zu Ihnen kommt.', 'en' => 'Choose once how the money reaches you.'],
                'so2'   => ['it' => 'Ogni provvigione attende il periodo di recesso, poi diventa libera.', 'de' => 'Jede Provision wartet die Widerrufsfrist ab, dann wird sie frei.', 'en' => 'Each commission waits out the withdrawal period, then it is released.'],
                'so3'   => ['it' => 'Raggiunto l’importo minimo, paghiamo — le ricevute sono qui.', 'de' => 'Ist der Mindestbetrag erreicht, zahlen wir aus — die Belege stehen hier.', 'en' => 'Once the minimum is reached, we pay out — the receipts are here.'],
            ],
            'profil' => [
                'kurz'  => ['it' => 'Profilo', 'de' => 'Profil', 'en' => 'Profile'],
                'titel' => ['it' => 'Profilo', 'de' => 'Profil', 'en' => 'Profile'],
                'satz'  => ['it' => 'La sua foto, la sua pagina, i messaggi e l’app sul telefono.', 'de' => 'Ihr Foto, Ihre eigene Seite, Nachrichten und die App aufs Handy.', 'en' => 'Your photo, your own page, messages and the app on your phone.'],
                'so1'   => ['it' => 'Aggiunga una foto e una frase — le persone si fidano dei volti.', 'de' => 'Foto und einen Satz hinzufügen — Menschen vertrauen Gesichtern.', 'en' => 'Add a photo and a sentence — people trust faces.'],
                'so2'   => ['it' => 'Adatti colori e testi della sua pagina.', 'de' => 'Farben und Texte Ihrer Seite anpassen.', 'en' => 'Adjust the colours and texts of your page.'],
                'so3'   => ['it' => 'Installi l’app sul telefono, così riceve subito gli avvisi.', 'de' => 'Die App aufs Handy holen, dann kommen Hinweise sofort.', 'en' => 'Put the app on your phone so alerts arrive right away.'],
            ],
        ],
    ];

    /* Antwort-Helfer für Kommentare und Nachrichten (03.10.2026, Uwe: Ja zu K4). {zitat} wird zu „…“, wenn der Partner den Kommentar einfügt. */
    public const PARTNER_ANTWORTEN = [
        'titel' => ['it' => 'Aiuto risposte', 'de' => 'Antwort-Helfer', 'en' => 'Reply helper'],
        'text' => ['it' => 'Qualcuno ha commentato, scritto o messo like? Scelga cosa ha fatto, incolli il commento se vuole — il testo pronto porta alla sua pagina.', 'de' => 'Jemand hat kommentiert, geschrieben oder geliked? Wählen Sie, was er getan hat, fügen Sie den Kommentar ein, wenn Sie mögen — der fertige Text führt auf Ihre Seite.', 'en' => 'Someone commented, wrote or liked? Pick what they did, paste the comment if you like — the ready text leads to your page.'],
        'was' => ['it' => 'Cosa ha fatto?', 'de' => 'Was hat er getan?', 'en' => 'What did they do?'],
        'vorname' => ['it' => 'Nome (facoltativo)', 'de' => 'Vorname (freiwillig)', 'en' => 'First name (optional)'],
        'zitat_feld' => ['it' => 'Il suo commento (facoltativo)', 'de' => 'Sein Kommentar (freiwillig)', 'en' => 'Their comment (optional)'],
        'sprache' => ['it' => 'Lingua della risposta', 'de' => 'Sprache der Antwort', 'en' => 'Reply language'],
        'kopieren' => ['it' => 'Copia', 'de' => 'Kopieren', 'en' => 'Copy'],
        'kopiert' => ['it' => 'Copiato', 'de' => 'Kopiert', 'en' => 'Copied'],
        'wa' => ['it' => 'Invia su WhatsApp', 'de' => 'Per WhatsApp senden', 'en' => 'Send on WhatsApp'],
        'hinweis' => ['it' => 'Non viene inviato nulla da solo: lei copia e risponde nell’app.', 'de' => 'Es geht nichts von selbst raus: Sie kopieren und antworten in der App.', 'en' => 'Nothing is sent on its own: you copy and reply in the app.'],
        'lagen' => [
            'kommentar' => ['name' => ['it' => 'Ha commentato', 'de' => 'Hat kommentiert', 'en' => 'Commented'], 'text' => ['it' => 'Grazie per il commento{zitat}, {vorname}! Se le interessa vedere cosa si può fare per la sua attività, qui c’è un controllo gratuito del sito e qualche esempio: {link}', 'de' => 'Danke für Ihren Kommentar{zitat}, {vorname}! Wenn Sie sehen möchten, was für Ihren Betrieb drin ist: Hier gibt es einen kostenlosen Website-Check und ein paar Beispiele: {link}', 'en' => 'Thanks for your comment{zitat}, {vorname}! If you’d like to see what’s possible for your business, here’s a free website check and a few examples: {link}']],
            'like' => ['name' => ['it' => 'Ha messo like', 'de' => 'Hat geliked', 'en' => 'Liked'], 'text' => ['it' => 'Ciao {vorname}, grazie per il like! Se ha un’attività: in 30 secondi vede gratis come va il suo sito sul telefono — {link}', 'de' => 'Hallo {vorname}, danke fürs Like! Falls Sie einen Betrieb haben: In 30 Sekunden sehen Sie kostenlos, wie Ihre Website am Handy abschneidet — {link}', 'en' => 'Hi {vorname}, thanks for the like! If you run a business: in 30 seconds you can see for free how your website does on a phone — {link}']],
            'nachricht' => ['name' => ['it' => 'Ha scritto', 'de' => 'Hat geschrieben', 'en' => 'Sent a message'], 'text' => ['it' => 'Ciao {vorname}, grazie del messaggio{zitat}! Le rispondo volentieri — qui intanto trova esempi, prezzi chiari e un controllo gratuito: {link}. Quando le va bene una chiamata di 5 minuti?', 'de' => 'Hallo {vorname}, danke für Ihre Nachricht{zitat}! Ich melde mich gern — hier schon mal Beispiele, klare Preise und ein kostenloser Check: {link}. Wann passt Ihnen ein Anruf von 5 Minuten?', 'en' => 'Hi {vorname}, thanks for your message{zitat}! Happy to help — here are examples, clear prices and a free check: {link}. When would a 5-minute call suit you?']],
            'preis' => ['name' => ['it' => 'Chiede il prezzo', 'de' => 'Fragt nach dem Preis', 'en' => 'Asks the price'], 'text' => ['it' => 'Ciao {vorname}, bella domanda! Dipende da cosa le serve — qui vede subito il prezzo per il suo caso, senza impegno: {link}', 'de' => 'Hallo {vorname}, gute Frage! Das hängt davon ab, was Sie brauchen — hier sehen Sie sofort den Preis für Ihren Fall, unverbindlich: {link}', 'en' => 'Hi {vorname}, good question! It depends on what you need — here you see the price for your case right away, no obligation: {link}']],
            'geteilt' => ['name' => ['it' => 'Ha condiviso', 'de' => 'Hat geteilt', 'en' => 'Shared'], 'text' => ['it' => 'Grazie mille per la condivisione, {vorname}! Se qualcuno dei suoi contatti cerca un sito, ecco il link giusto: {link}', 'de' => 'Vielen Dank fürs Teilen, {vorname}! Falls jemand aus Ihrem Umfeld eine Website sucht, hier der passende Link: {link}', 'en' => 'Thanks a lot for sharing, {vorname}! If anyone you know is looking for a website, here’s the right link: {link}']],
            'folgt' => ['name' => ['it' => 'La segue', 'de' => 'Folgt Ihnen neu', 'en' => 'New follower'], 'text' => ['it' => 'Benvenuto/a {vorname}! Qui parlo di siti web per attività locali. Se vuole sapere come va il suo: controllo gratuito qui — {link}', 'de' => 'Willkommen, {vorname}! Hier geht es um Websites für Betriebe vor Ort. Wenn Sie wissen wollen, wie gut Ihre ist: kostenloser Check hier — {link}', 'en' => 'Welcome, {vorname}! I post about websites for local businesses. Curious how yours does? Free check here — {link}']],
            'hat_schon' => ['name' => ['it' => '«Ho già un sito»', 'de' => '„Hab schon eine Website“', 'en' => '“I already have a website”'], 'text' => ['it' => 'Ottimo, {vorname}! Allora vale la pena vedere se porta clienti: il controllo gratuito le dice in un minuto cosa funziona e cosa no — {link}', 'de' => 'Sehr gut, {vorname}! Dann lohnt der Blick, ob sie Kunden bringt: Der kostenlose Check zeigt in einer Minute, was funktioniert und was nicht — {link}', 'en' => 'Great, {vorname}! Then it’s worth checking whether it brings customers: the free check shows in a minute what works and what doesn’t — {link}']],
        ],
    ];

    /* Kundenstimmen über den Link des Partners (03.10.2026, PartnerStimmen, N4). */
    public const PARTNER_STIMMEN = [
        'titel' => ['it' => 'Com’è andata con {name}?', 'de' => 'Wie war es mit {name}?', 'en' => 'How was it with {name}?'],
        'lead' => ['it' => 'Due frasi sincere bastano. Con il suo permesso compaiono, con nome e foto, sulla pagina di {name} — dopo un controllo di Vecom Design.', 'de' => 'Zwei ehrliche Sätze genügen. Mit Ihrer Erlaubnis erscheinen sie mit Name und Foto auf der Seite von {name} — nach einer Prüfung durch Vecom Design.', 'en' => 'Two honest sentences are enough. With your permission they appear with your name and photo on {name}’s page — after a check by Vecom Design.'],
        'name' => ['it' => 'Nome e cognome', 'de' => 'Vor- und Nachname', 'en' => 'First and last name'],
        'firma' => ['it' => 'Attività (facoltativo)', 'de' => 'Betrieb (freiwillig)', 'en' => 'Business (optional)'],
        'ort' => ['it' => 'Città (facoltativo)', 'de' => 'Ort (freiwillig)', 'en' => 'Town (optional)'],
        'text' => ['it' => 'La sua esperienza', 'de' => 'Ihre Erfahrung', 'en' => 'Your experience'],
        'text_ph' => ['it' => 'Es. Mi ha spiegato tutto con calma, il sito era pronto in due settimane.', 'de' => 'Z. B. Er hat mir alles in Ruhe erklärt, die Website war in zwei Wochen fertig.', 'en' => 'E.g. He explained everything calmly, the website was ready in two weeks.'],
        'sterne' => ['it' => 'Voto', 'de' => 'Bewertung', 'en' => 'Rating'],
        'foto' => ['it' => 'Foto (facoltativo — lei o la sua attività)', 'de' => 'Foto (freiwillig — Sie oder Ihr Betrieb)', 'en' => 'Photo (optional — you or your business)'],
        'ok' => ['it' => 'Acconsento che il mio testo compaia con nome, attività, città e foto sulla pagina di {name} (Vecom Design). Posso revocarlo in qualsiasi momento.', 'de' => 'Ich bin einverstanden, dass mein Text mit Name, Betrieb, Ort und Foto auf der Seite von {name} (Vecom Design) erscheint. Ich kann das jederzeit widerrufen.', 'en' => 'I agree that my text appears with name, business, town and photo on {name}’s page (Vecom Design). I can withdraw this at any time.'],
        'knopf' => ['it' => 'Invia', 'de' => 'Absenden', 'en' => 'Send'],
        'danke' => ['it' => 'Grazie! Il suo testo compare dopo un breve controllo.', 'de' => 'Danke! Ihr Text erscheint nach einer kurzen Prüfung.', 'en' => 'Thank you! Your text appears after a short check.'],
        'f_st_name' => ['it' => 'Inserisca il suo nome.', 'de' => 'Bitte Ihren Namen eintragen.', 'en' => 'Please enter your name.'],
        'f_st_text' => ['it' => 'Scriva almeno due frasi, senza link.', 'de' => 'Bitte mindestens zwei Sätze, ohne Links.', 'en' => 'Please write at least two sentences, without links.'],
        'f_st_ok' => ['it' => 'Senza il suo consenso non possiamo pubblicarlo.', 'de' => 'Ohne Ihre Zustimmung können wir es nicht zeigen.', 'en' => 'Without your consent we cannot show it.'],
        'f_st_zeit' => ['it' => 'Il modulo è scaduto — ricarichi la pagina e riprovi.', 'de' => 'Das Formular ist abgelaufen — Seite neu laden und noch einmal.', 'en' => 'The form expired — reload the page and try again.'],
        'f_st_genug' => ['it' => 'Oggi abbiamo già ricevuto molti testi — riprovi domani.', 'de' => 'Heute kamen schon viele Texte — bitte morgen noch einmal.', 'en' => 'We received many texts today — please try again tomorrow.'],
        'f_st_foto' => ['it' => 'La foto non si apre (JPG, PNG o WebP, al massimo 8 MB).', 'de' => 'Das Foto lässt sich nicht öffnen (JPG, PNG oder WebP, höchstens 8 MB).', 'en' => 'The photo cannot be opened (JPG, PNG or WebP, max. 8 MB).'],
        'f_st_falle' => ['it' => 'Qualcosa non ha funzionato — riprovi.', 'de' => 'Das hat nicht geklappt — bitte noch einmal.', 'en' => 'That didn’t work — please try again.'],
        'p_titel' => ['it' => 'Raccogliere una recensione', 'de' => 'Kundenstimme sammeln', 'en' => 'Collect a review'],
        'p_text' => ['it' => 'Manda questo link a chi è contento del suo sito. Scrive due frasi, se vuole con foto — dopo il controllo di Vecom compare sulla sua pagina.', 'de' => 'Schicken Sie diesen Link an Kunden, die mit ihrer Website zufrieden sind. Sie schreiben zwei Sätze, gern mit Foto — nach Vecoms Prüfung steht das auf Ihrer Seite.', 'en' => 'Send this link to customers who are happy with their website. They write two sentences, with a photo if they like — after Vecom’s check it appears on your page.'],
        'p_msg' => ['it' => 'Ciao! Sei contento del tuo nuovo sito? Mi aiuteresti con due frasi? Ci vogliono 30 secondi: {link}', 'de' => 'Hallo! Sind Sie zufrieden mit Ihrer neuen Website? Würden Sie mir mit zwei Sätzen helfen? Dauert 30 Sekunden: {link}', 'en' => 'Hi! Are you happy with your new website? Would you help me with two sentences? Takes 30 seconds: {link}'],
        'p_wartet' => ['it' => '{n} in attesa del controllo', 'de' => '{n} warten auf Prüfung', 'en' => '{n} awaiting review'],
        'p_online' => ['it' => '{n} sulla sua pagina', 'de' => '{n} auf Ihrer Seite', 'en' => '{n} on your page'],
    ];

    /* Wer auf der Partnerseite war, Kontakt mit Einwilligung, Sofort-Hinweis (03.10.2026, PartnerBesuche). */
    public const PARTNER_BESUCHE = [
        'titel' => ['it' => 'Chi è passato sulla sua pagina', 'de' => 'Wer auf Ihrer Seite war', 'en' => 'Who visited your page'],
        'text' => ['it' => 'Ogni visita con piattaforma, post, zona, dispositivo e cosa ha fatto. Senza nomi: chi ha cliccato, messo like o commentato non lo dice nessuna piattaforma. I contatti compaiono solo se il visitatore li ha lasciati per lei.', 'de' => 'Jeder Besuch mit Plattform, Beitrag, Gegend, Gerät und was er getan hat. Ohne Namen: Wer geklickt, geliked oder kommentiert hat, verrät keine Plattform. Kontakte erscheinen nur, wenn der Besucher sie für Sie freigegeben hat.', 'en' => 'Every visit with platform, post, area, device and what they did. No names: no platform reveals who clicked, liked or commented. Contacts only appear if the visitor released them for you.'],
        'leer' => ['it' => 'Ancora nessuna visita negli ultimi 14 giorni. Condivida il post di oggi — qui vede subito chi arriva.', 'de' => 'Noch kein Besuch in den letzten 14 Tagen. Teilen Sie den Beitrag von heute — hier sehen Sie sofort, wer kommt.', 'en' => 'No visits in the last 14 days yet. Share today’s post — you’ll see right here who comes.'],
        'kontakte_titel' => ['it' => 'Vogliono essere contattati da lei', 'de' => 'Möchten von Ihnen hören', 'en' => 'Want to hear from you'],
        'kontakte_text' => ['it' => 'Hanno lasciato il numero o l’e-mail e hanno dato il permesso che lei li contatti di persona. Il testo è pronto.', 'de' => 'Haben Nummer oder E-Mail eingetragen und erlaubt, dass Sie sich persönlich melden. Der Text ist fertig.', 'en' => 'Left a number or e-mail and allowed you to get in touch personally. The text is ready.'],
        'kontakt_wann' => ['it' => 'Preferisce: {wann}', 'de' => 'Am liebsten: {wann}', 'en' => 'Prefers: {wann}'],
        'anrufen' => ['it' => 'Chiama', 'de' => 'Anrufen', 'en' => 'Call'],
        'whatsapp' => ['it' => 'WhatsApp con testo', 'de' => 'WhatsApp mit Text', 'en' => 'WhatsApp with text'],
        'email' => ['it' => 'E-mail con testo', 'de' => 'E-Mail mit Text', 'en' => 'E-mail with text'],
        'erledigt' => ['it' => 'Fatto', 'de' => 'Erledigt', 'en' => 'Done'],
        'chance_hoch' => ['it' => 'Probabilità alta', 'de' => 'Hohe Chance', 'en' => 'High chance'],
        'chance_mittel' => ['it' => 'Media', 'de' => 'Mittel', 'en' => 'Medium'],
        'chance_niedrig' => ['it' => 'Bassa', 'de' => 'Niedrig', 'en' => 'Low'],
        'oggi' => ['it' => 'Oggi', 'de' => 'Heute', 'en' => 'Today'],
        'ieri' => ['it' => 'Ieri', 'de' => 'Gestern', 'en' => 'Yesterday'],
        'min' => ['it' => '{n} min', 'de' => '{n} Min.', 'en' => '{n} min'],
        'sek' => ['it' => '{n} s', 'de' => '{n} Sek.', 'en' => '{n} s'],
        'seiten' => ['it' => '{n} pagine', 'de' => '{n} Seiten', 'en' => '{n} pages'],
        'seite' => ['it' => '1 pagina', 'de' => '1 Seite', 'en' => '1 page'],
        'g_smartphone' => ['it' => 'telefono', 'de' => 'Handy', 'en' => 'phone'],
        'g_desktop' => ['it' => 'computer', 'de' => 'Computer', 'en' => 'computer'],
        'g_tablet' => ['it' => 'tablet', 'de' => 'Tablet', 'en' => 'tablet'],
        'g_telegram' => ['it' => 'Telegram', 'de' => 'Telegram', 'en' => 'Telegram'],
        'ohne_ort' => ['it' => 'zona sconosciuta', 'de' => 'Gegend unbekannt', 'en' => 'area unknown'],
        'tipp_hoch' => ['it' => 'Nessun contatto lasciato. Se ha commentato o scritto: risponda con l’«Aiuto risposte» qui sotto.', 'de' => 'Keinen Kontakt hinterlassen. Hat jemand kommentiert oder geschrieben: mit dem Antwort-Helfer unten antworten.', 'en' => 'No contact left. If someone commented or wrote: reply with the reply helper below.'],
        'b_kalender' => ['it' => 'post del giorno {datum}', 'de' => 'Tagesbeitrag vom {datum}', 'en' => 'post of {datum}'],
        'b_beitrag' => ['it' => 'post {titel}', 'de' => 'Beitrag {titel}', 'en' => 'post {titel}'],
        'b_bild3d' => ['it' => 'immagine 3D', 'de' => '3D-Bild', 'en' => '3D image'],
        'b_video3d' => ['it' => 'video 3D', 'de' => '3D-Video', 'en' => '3D video'],
        'k_weiter' => ['it' => 'inoltrato da un cliente', 'de' => 'von einem Kunden weitergeleitet', 'en' => 'forwarded by a customer'],
        'k_kontakte' => ['it' => 'la sua lista contatti', 'de' => 'Ihre Kontaktliste', 'en' => 'your contact list'],
        'k_check' => ['it' => 'report della verifica', 'de' => 'Website-Check-Bericht', 'en' => 'website check report'],
        'k_mappe' => ['it' => 'cartella', 'de' => 'Mappe', 'en' => 'folder'],
        'k_brief' => ['it' => 'lettera', 'de' => 'Brief', 'en' => 'letter'],
        'k_signatur' => ['it' => 'firma e-mail', 'de' => 'E-Mail-Signatur', 'en' => 'e-mail signature'],
        'k_website' => ['it' => 'il suo sito', 'de' => 'Ihre Website', 'en' => 'your website'],
        'k_antwort' => ['it' => 'la sua risposta', 'de' => 'Ihre Antwort', 'en' => 'your reply'],
        'k_anschreiben' => ['it' => 'il suo messaggio', 'de' => 'Ihre Nachricht', 'en' => 'your message'],
        'k_direkt' => ['it' => 'senza indicazione (es. WhatsApp)', 'de' => 'ohne Angabe (z. B. WhatsApp)', 'en' => 'not stated (e.g. WhatsApp)'],
        'k_andere' => ['it' => 'altro sito', 'de' => 'andere Website', 'en' => 'other website'],
        't_preis' => ['it' => 'ha guardato i prezzi', 'de' => 'Preise angesehen', 'en' => 'looked at prices'],
        't_rechner' => ['it' => 'ha calcolato un prezzo', 'de' => 'Preis ausgerechnet', 'en' => 'calculated a price'],
        't_check' => ['it' => 'ha aperto la verifica del sito', 'de' => 'Website-Check geöffnet', 'en' => 'opened the website check'],
        't_check_fertig' => ['it' => 'ha fatto la verifica del sito', 'de' => 'Website-Check gemacht', 'en' => 'did the website check'],
        't_termin' => ['it' => 'ha aperto gli appuntamenti', 'de' => 'Termine angesehen', 'en' => 'looked at appointments'],
        't_termin_fertig' => ['it' => 'ha prenotato un appuntamento', 'de' => 'Termin gebucht', 'en' => 'booked an appointment'],
        't_wa' => ['it' => 'ha aperto WhatsApp', 'de' => 'WhatsApp geöffnet', 'en' => 'opened WhatsApp'],
        't_rueckruf' => ['it' => 'vuole essere richiamato', 'de' => 'möchte zurückgerufen werden', 'en' => 'wants a call back'],
        't_formular' => ['it' => 'ha iniziato il modulo', 'de' => 'Formular angefangen', 'en' => 'started the form'],
        't_anfrage' => ['it' => 'ha inviato una richiesta', 'de' => 'Anfrage geschickt', 'en' => 'sent a request'],
        't_kunde' => ['it' => 'è diventato cliente', 'de' => 'Kunde geworden', 'en' => 'became a customer'],
        'msg_wa' => ['it' => 'Ciao {vorname}, sono {name} di Vecom Design. Ha chiesto che la contattassi per il suo sito web — quando le va bene una chiamata di 5 minuti? Intanto qui trova la mia pagina: {link}', 'de' => 'Hallo {vorname}, hier ist {name} von Vecom Design. Sie wollten, dass ich mich wegen Ihrer Website melde — wann passt Ihnen ein Anruf von 5 Minuten? Hier schon mal meine Seite: {link}', 'en' => 'Hi {vorname}, this is {name} from Vecom Design. You asked me to get in touch about your website — when would a 5-minute call suit you? Here is my page meanwhile: {link}'],
        'msg_mail' => ['it' => "Buongiorno {vorname},\n\nsono {name} di Vecom Design. Ha chiesto che la contattassi per il suo sito web. Mi dica quando le va bene una breve telefonata di 5 minuti — oppure risponda direttamente a questa e-mail.\n\nQui trova la mia pagina con esempi e prezzi: {link}\n\nCordiali saluti\n{name}", 'de' => "Guten Tag {vorname},\n\nhier ist {name} von Vecom Design. Sie wollten, dass ich mich wegen Ihrer Website melde. Sagen Sie mir gern, wann Ihnen ein kurzes Telefonat von 5 Minuten passt — oder antworten Sie einfach auf diese E-Mail.\n\nHier meine Seite mit Beispielen und Preisen: {link}\n\nViele Grüße\n{name}", 'en' => "Hello {vorname},\n\nthis is {name} from Vecom Design. You asked me to get in touch about your website. Let me know when a short 5-minute call suits you — or simply reply to this e-mail.\n\nHere is my page with examples and prices: {link}\n\nBest regards\n{name}"],
        'mail_betreff' => ['it' => 'Il suo sito web — Vecom Design', 'de' => 'Ihre Website — Vecom Design', 'en' => 'Your website — Vecom Design'],
        'push_titel' => ['it' => 'Ora sulla sua pagina: {plattform}', 'de' => 'Gerade auf Ihrer Seite: {plattform}', 'en' => 'On your page now: {plattform}'],
        'push_text' => ['it' => 'Probabilità alta — guardi chi è.', 'de' => 'Hohe Chance — sehen Sie nach.', 'en' => 'High chance — have a look.'],
        'push_kontakt' => ['it' => 'vuole essere contattato da lei', 'de' => 'möchte von Ihnen hören', 'en' => 'wants to hear from you'],
        'einwilligung' => ['it' => '{name} può contattarmi anche di persona (telefono o WhatsApp).', 'de' => '{name} darf sich auch selbst bei mir melden (Telefon oder WhatsApp).', 'en' => '{name} may also contact me personally (phone or WhatsApp).'],
        'einwilligung_hinweis' => ['it' => 'Facoltativo. Senza questa spunta la richiama solo Vecom.', 'de' => 'Freiwillig. Ohne dieses Häkchen ruft nur Vecom zurück.', 'en' => 'Optional. Without this tick only Vecom calls back.'],
    ];

    /* Automatisierungen, die der Partner selbst schaltet (03.10.2026, PartnerAutomatik). */
    public const PARTNER_AUTOMATIK = [
        'titel'   => ['it' => 'Automatico per lei', 'de' => 'Automatisch für Sie', 'en' => 'Automatic for you'],
        'text'    => ['it' => 'Lei decide cosa succede da solo. Niente viene inviato a terzi a suo nome — solo avvisi sul suo telefono.', 'de' => 'Sie entscheiden, was von selbst passiert. Nichts geht in Ihrem Namen an andere — nur Hinweise auf Ihr Handy.', 'en' => 'You decide what happens on its own. Nothing is sent to others in your name — only alerts on your phone.'],
        'schalter' => [
            'medien'        => [['it' => 'Nuovi video e immagini subito sul telefono', 'de' => 'Neue Videos und Bilder sofort aufs Handy', 'en' => 'New videos and images straight to your phone'],
                                ['it' => 'Appena Vecom approva un nuovo video, lo sa.', 'de' => 'Sobald Vecom ein neues Video freigibt, wissen Sie es.', 'en' => 'As soon as Vecom approves a new video, you know.']],
            'wochenpaket'   => [['it' => 'Il lunedì: pacchetto pubblicitario della settimana', 'de' => 'Montags: Werbepaket der Woche', 'en' => 'Mondays: this week’s promo pack'],
                                ['it' => 'Un’idea pronta e il link ai post della settimana.', 'de' => 'Eine fertige Idee und der Sprung zu den Beiträgen der Woche.', 'en' => 'A ready idea and a jump to the week’s posts.']],
            'autopilot'     => [['it' => 'Ogni mattina: attività vicine da visitare', 'de' => 'Jeden Morgen: Betriebe zum Vorbeigehen', 'en' => 'Every morning: businesses to visit'],
                                ['it' => 'Quante e a che ora — sceglie lei.', 'de' => 'Wie viele und um wie viel Uhr — Sie wählen.', 'en' => 'How many and at what time — you choose.']],
            'nachfass'      => [['it' => 'Promemoria per ricontattare', 'de' => 'Erinnerung zum Nachhaken', 'en' => 'Follow-up reminder'],
                                ['it' => 'Dopo 3 e dopo 7 giorni, se non ha ancora risposto nessuno.', 'de' => 'Nach 3 und nach 7 Tagen, wenn noch niemand geantwortet hat.', 'en' => 'After 3 and after 7 days, if nobody has replied yet.']],
            'check'         => [['it' => 'Verifica e cartella da sole', 'de' => 'Check und Mappe von selbst', 'en' => 'Check and folder on their own'],
                                ['it' => 'Prenota un’attività con sito? Prepariamo la verifica e glielo diciamo.', 'de' => 'Reservieren Sie einen Betrieb mit Website, liegt der Check kurz darauf bereit.', 'en' => 'Reserve a business with a website and the check is ready shortly after.']],
            'wochenbericht' => [['it' => 'Il venerdì: la sua settimana in una frase', 'de' => 'Freitags: Ihre Woche in einem Satz', 'en' => 'Fridays: your week in one sentence'],
                                ['it' => 'Visite, clienti, vendite e provvigione.', 'de' => 'Besuche, Kunden, Verkäufe und Provision.', 'en' => 'Visits, customers, sales and commission.']],
            'heiss'         => [['it' => 'Visita calda: avviso subito', 'de' => 'Heißer Besuch: sofort Bescheid', 'en' => 'Hot visit: tell me right away'],
                                ['it' => 'Se qualcuno resta più di 2 minuti, guarda i prezzi o vuole essere richiamato.', 'de' => 'Wenn jemand über 2 Minuten bleibt, Preise ansieht oder zurückgerufen werden will.', 'en' => 'When someone stays over 2 minutes, looks at prices or wants a call back.']],
            'kalender'      => [['it' => 'Richiamate nel suo calendario', 'de' => 'Rückrufe in Ihren Kalender', 'en' => 'Callbacks in your calendar'],
                                ['it' => 'Abbonamento per il calendario del telefono — si aggiorna da solo.', 'de' => 'Ein Abo für den Kalender im Handy — aktualisiert sich von selbst.', 'en' => 'A subscription for your phone calendar — updates itself.']],
        ],
        'anzahl'   => ['it' => 'Quante', 'de' => 'Wie viele', 'en' => 'How many'],
        'stunde'   => ['it' => 'Alle ore', 'de' => 'Um', 'en' => 'At'],
        'uhr'      => ['it' => '{h}:00', 'de' => '{h} Uhr', 'en' => '{h}:00'],
        'ruhe'     => ['it' => 'Modalità vacanza', 'de' => 'Urlaubsmodus', 'en' => 'Holiday mode'],
        'ruhe_text'=> ['it' => 'Fino a questo giorno: nessun avviso e nessuna lista del mattino. I soldi arrivano comunque.', 'de' => 'Bis zu diesem Tag: keine Hinweise und keine Morgenliste. Geld kommt trotzdem.', 'en' => 'Until this day: no alerts and no morning list. Money still arrives.'],
        'ruhe_bis' => ['it' => 'Fino al', 'de' => 'Bis einschließlich', 'en' => 'Until'],
        'ruhig'    => ['it' => 'In vacanza fino al {datum} — tutto tace.', 'de' => 'Im Urlaub bis {datum} — alles ist still.', 'en' => 'On holiday until {datum} — all quiet.'],
        'kal_link' => ['it' => 'Link per il calendario', 'de' => 'Link für den Kalender', 'en' => 'Calendar link'],
        'kal_abo'  => ['it' => 'Aggiungi al calendario', 'de' => 'Im Kalender abonnieren', 'en' => 'Subscribe in calendar'],
        'kal_hinweis' => ['it' => 'Il link vale solo per il calendario, non apre la sua area partner.', 'de' => 'Der Link gilt nur für den Kalender, er öffnet nicht Ihren Partnerbereich.', 'en' => 'The link only works for the calendar; it does not open your partner area.'],
        'ohne_push'=> ['it' => 'Gli avvisi arrivano solo se ha attivato gli avvisi sul telefono (Profilo › Sul telefono).', 'de' => 'Hinweise kommen nur, wenn Sie sie am Handy eingeschaltet haben (Profil › Aufs Handy).', 'en' => 'Alerts only arrive if you have turned them on on your phone (Profile › On your phone).'],
        'speichern'=> ['it' => 'Salva', 'de' => 'Speichern', 'en' => 'Save'],
        'gespeichert' => ['it' => 'Salvato.', 'de' => 'Gespeichert.', 'en' => 'Saved.'],
        'push' => [
            'medien_video' => ['it' => 'Nuovo video da Vecom', 'de' => 'Neues Video von Vecom', 'en' => 'New video from Vecom'],
            'medien_bild'  => ['it' => 'Nuova immagine da Vecom', 'de' => 'Neues Bild von Vecom', 'en' => 'New image from Vecom'],
            'medien_text'  => ['it' => 'Pronto da condividere, con il suo link.', 'de' => 'Fertig zum Teilen, mit Ihrem Link.', 'en' => 'Ready to share, with your link.'],
            'bericht_titel'=> ['it' => 'La sua settimana', 'de' => 'Ihre Woche', 'en' => 'Your week'],
            'bericht_text' => ['it' => '{klicks} visite · {kunden} clienti · {verkaeufe} vendite · {betrag}', 'de' => '{klicks} Besuche · {kunden} Kunden · {verkaeufe} Verkäufe · {betrag}', 'en' => '{klicks} visits · {kunden} customers · {verkaeufe} sales · {betrag}'],
            'check_titel'  => ['it' => 'Verifica pronta: {name}', 'de' => 'Check fertig: {name}', 'en' => 'Check ready: {name}'],
            'check_text'   => ['it' => 'La cartella da mostrare è pronta.', 'de' => 'Die Mappe zum Zeigen liegt bereit.', 'en' => 'The folder to show is ready.'],
        ],
        'ics' => [
            'name'      => ['it' => 'Vecom – chiamate', 'de' => 'Vecom – Anrufe', 'en' => 'Vecom – calls'],
            'rueckruf'  => ['it' => 'Richiamare: {name}', 'de' => 'Zurückrufen: {name}', 'en' => 'Call back: {name}'],
            'nachhaken' => ['it' => 'Ricontattare: {name}', 'de' => 'Nachhaken: {name}', 'en' => 'Follow up: {name}'],
        ],
    ];

    /* „Heute zu tun“ und Fortschritt ganz oben (03.10.2026, PartnerHeute). Je Punkt [eins, mehrere]. */
    public const PARTNER_HEUTE = [
        'titel'   => ['it' => 'Da fare oggi', 'de' => 'Heute zu tun', 'en' => 'To do today'],
        'leer'    => ['it' => 'Per oggi è tutto fatto. Domattina qui trova cosa c’è da fare.', 'de' => 'Für heute ist alles erledigt. Morgen früh steht hier, was dran ist.', 'en' => 'All done for today. Tomorrow morning you’ll find what’s next here.'],
        'los'     => ['it' => 'Vai', 'de' => 'Los', 'en' => 'Go'],
        'punkte'  => [
            'kontakte'    => [['it' => '1 persona vuole essere contattata da lei', 'de' => '1 Person möchte von Ihnen hören', 'en' => '1 person wants to hear from you'],
                              ['it' => '{n} persone vogliono essere contattate da lei', 'de' => '{n} Personen möchten von Ihnen hören', 'en' => '{n} people want to hear from you']],
            'heiss'       => [['it' => 'Qualcuno sta guardando la sua verifica — chiami ora', 'de' => 'Jemand sieht gerade Ihren Website-Check an — jetzt anrufen', 'en' => 'Someone is viewing your website check — call now'],
                              ['it' => '{n} verifiche aperte proprio ora — chiami ora', 'de' => '{n} Website-Checks gerade angesehen — jetzt anrufen', 'en' => '{n} website checks being viewed — call now']],
            'nachhaken'   => [['it' => '1 contatto aspetta un suo messaggio', 'de' => '1 Kontakt wartet aufs Nachhaken', 'en' => '1 contact is waiting for your follow-up'],
                              ['it' => '{n} contatti aspettano un suo messaggio', 'de' => '{n} Kontakte warten aufs Nachhaken', 'en' => '{n} contacts are waiting for your follow-up']],
            'anrufen'     => [['it' => '1 attività da chiamare, scelta da Vecom', 'de' => '1 Betrieb von Vecom anrufen', 'en' => 'Call 1 business picked by Vecom'],
                              ['it' => '{n} attività da chiamare, scelte da Vecom', 'de' => '{n} Betriebe von Vecom anrufen', 'en' => 'Call {n} businesses picked by Vecom']],
            'nachrichten' => [['it' => '1 nuovo messaggio da Vecom', 'de' => '1 neue Nachricht von Vecom', 'en' => '1 new message from Vecom'],
                              ['it' => '{n} nuovi messaggi da Vecom', 'de' => '{n} neue Nachrichten von Vecom', 'en' => '{n} new messages from Vecom']],
            'vorbeigehen' => [['it' => '1 attività vicino a lei da visitare', 'de' => '1 Betrieb in Ihrer Nähe besuchen', 'en' => 'Visit 1 business near you'],
                              ['it' => '{n} attività vicino a lei da visitare', 'de' => '{n} Betriebe in Ihrer Nähe besuchen', 'en' => 'Visit {n} businesses near you']],
            'medien'      => [['it' => '1 nuovo video o immagine da Vecom', 'de' => '1 neues Video oder Bild von Vecom', 'en' => '1 new video or image from Vecom'],
                              ['it' => '{n} nuovi video e immagini da Vecom', 'de' => '{n} neue Videos und Bilder von Vecom', 'en' => '{n} new videos and images from Vecom']],
            'kurs'        => [['it' => 'Corso, giorno {n}: {titel}', 'de' => 'Kurs, Tag {n}: {titel}', 'en' => 'Course, day {n}: {titel}'],
                              ['it' => 'Corso, giorno {n}: {titel}', 'de' => 'Kurs, Tag {n}: {titel}', 'en' => 'Course, day {n}: {titel}']],
            'posten'      => [['it' => 'Pubblichi oggi: {titel}', 'de' => 'Heute posten: {titel}', 'en' => 'Post today: {titel}'],
                              ['it' => 'Pubblichi oggi: {titel}', 'de' => 'Heute posten: {titel}', 'en' => 'Post today: {titel}']],
        ],
        'verdient' => ['it' => 'Guadagnato finora', 'de' => 'Bisher verdient', 'en' => 'Earned so far'],
        'wartet'   => ['it' => 'di cui {betrag} nel periodo di recesso', 'de' => 'davon {betrag} in der Widerrufsfrist', 'en' => 'of which {betrag} in the withdrawal period'],
        'bis_eins' => ['it' => 'Ancora 1 vendita fino a {stufe}', 'de' => 'Noch 1 Verkauf bis {stufe}', 'en' => '1 more sale to {stufe}'],
        'bis'      => ['it' => 'Ancora {n} vendite fino a {stufe}', 'de' => 'Noch {n} Verkäufe bis {stufe}', 'en' => '{n} more sales to {stufe}'],
        'oben'     => ['it' => 'Livello più alto raggiunto: {stufe}', 'de' => 'Höchste Stufe erreicht: {stufe}', 'en' => 'Top level reached: {stufe}'],
    ];

    /** Erste Schritte und Wochenverlauf im Partner-Dashboard (27.09.2026). */
    public const PARTNER_START = [
        's_titel' => ['it' => 'Primi passi', 'de' => 'Erste Schritte', 'en' => 'First steps'],
        's_stand' => ['it' => '{n} di {alle} fatti', 'de' => '{n} von {alle} erledigt', 'en' => '{n} of {alle} done'],
        's_jetzt' => ['it' => 'Adesso', 'de' => 'Jetzt', 'en' => 'Now'],
        's_los' => ['it' => 'Vai', 'de' => 'Los', 'en' => 'Go'],
        'schritte' => [
            'vereinbarung' => [['it' => 'Confermare l’accordo', 'de' => 'Vereinbarung bestätigen', 'en' => 'Accept the agreement'], ['it' => 'Senza non possiamo pagare le provvigioni.', 'de' => 'Ohne sie können wir keine Provision auszahlen.', 'en' => 'Without it we can’t pay commissions.']],
            'weg' => [['it' => 'Scegliere come ricevere il denaro', 'de' => 'Auszahlungsweg festlegen', 'en' => 'Choose how you get paid'], ['it' => 'Bonifico, PayPal o Stripe — due minuti.', 'de' => 'Überweisung, PayPal oder Stripe — zwei Minuten.', 'en' => 'Bank transfer, PayPal or Stripe — two minutes.']],
            'profil' => [['it' => 'Foto e una frase', 'de' => 'Foto und ein Satz', 'en' => 'Photo and one sentence'], ['it' => 'Chi la conosce si fida di più quando vede il suo volto.', 'de' => 'Wer Sie kennt, vertraut mehr, wenn er Ihr Gesicht sieht.', 'en' => 'People who know you trust more when they see your face.']],
            'seite' => [['it' => 'Personalizzare la pagina', 'de' => 'Seite gestalten', 'en' => 'Design your page'], ['it' => 'Colore, immagine e testi a suo gusto.', 'de' => 'Farbe, Bild und Texte nach Ihrem Geschmack.', 'en' => 'Colour, image and texts to your taste.']],
            'teilen' => [['it' => 'Condividere il link la prima volta', 'de' => 'Link zum ersten Mal teilen', 'en' => 'Share your link for the first time'], ['it' => 'Testi pronti per WhatsApp, Instagram e altri.', 'de' => 'Fertige Texte für WhatsApp, Instagram und mehr.', 'en' => 'Ready texts for WhatsApp, Instagram and more.']],
            'app' => [['it' => 'Attivare gli avvisi', 'de' => 'Hinweise einschalten', 'en' => 'Turn on notifications'], ['it' => 'Così sa subito quando arriva un cliente.', 'de' => 'Dann wissen Sie sofort, wenn ein Kunde kommt.', 'en' => 'So you know right away when a customer arrives.']],
        ],
        'w_titel' => ['it' => 'La sua pagina nelle ultime 8 settimane', 'de' => 'Ihre Seite in den letzten 8 Wochen', 'en' => 'Your page over the last 8 weeks'],
        'w_besuche' => ['it' => 'Visite', 'de' => 'Besuche', 'en' => 'Visits'],
        'w_seit' => ['it' => 'Visite e clic contati dal {datum}.', 'de' => 'Besuche und Klicks gezählt seit {datum}.', 'en' => 'Visits and clicks counted since {datum}.'],
        'w_kunden' => ['it' => 'Nuovi clienti', 'de' => 'Neue Kunden', 'en' => 'New customers'],
        'w_verkaeufe' => ['it' => 'Vendite', 'de' => 'Verkäufe', 'en' => 'Sales'],
        'w_woche' => ['it' => 'Settimana dal', 'de' => 'Woche ab', 'en' => 'Week from'],
        'w_zahlen' => ['it' => 'Vedere i numeri', 'de' => 'Zahlen ansehen', 'en' => 'See the numbers'],
        'w_leer' => ['it' => 'Ancora nessuna visita. Condivida il suo link — qui compariranno le prime barre.', 'de' => 'Noch keine Besuche. Teilen Sie Ihren Link — dann erscheinen hier die ersten Balken.', 'en' => 'No visits yet. Share your link — the first bars will appear here.'],
        'w_aria' => ['it' => 'Visite e nuovi clienti per settimana', 'de' => 'Besuche und neue Kunden je Woche', 'en' => 'Visits and new customers per week'],
    ];

    /** „Ihre Empfehlung ist online“ -- Hinweis, Zustimmung des Kunden, fertiger Beitrag (27.09.2026). */
    public const PARTNER_ERFOLG = [
        'push_online_t' => ['it' => 'Una sua segnalazione è online!', 'de' => 'Eine Ihrer Empfehlungen ist jetzt online!', 'en' => 'One of your referrals is now live!'],
        'push_online_x' => ['it' => 'Se il cliente è d’accordo, riceve un post pronto da condividere.', 'de' => 'Stimmt der Kunde zu, bekommen Sie einen fertigen Beitrag zum Teilen.', 'en' => 'If the customer agrees, you get a ready-made post to share.'],
        'push_zeigen_t' => ['it' => 'Può mostrare il nuovo sito', 'de' => 'Sie dürfen die neue Website zeigen', 'en' => 'You may show the new website'],
        'push_zeigen_x' => ['it' => 'Il post è pronto nella sua area.', 'de' => 'Der Beitrag liegt fertig in Ihrem Dashboard.', 'en' => 'The post is ready in your dashboard.'],
        'e_titel' => ['it' => 'Le sue segnalazioni online', 'de' => 'Ihre Empfehlungen, die online sind', 'en' => 'Your referrals that are live'],
        'e_text' => ['it' => 'Un risultato convince più di qualsiasi pubblicità. Se il cliente è d’accordo, il post è già pronto.', 'de' => 'Ein Ergebnis überzeugt mehr als jede Werbung. Stimmt der Kunde zu, ist der Beitrag schon fertig.', 'en' => 'A result convinces more than any advert. If the customer agrees, the post is ready.'],
        'e_wartet' => ['it' => 'Online dal {datum} — in attesa del consenso del cliente. Senza non mostriamo nomi.', 'de' => 'Online seit {datum} — wartet auf die Zustimmung des Kunden. Ohne sie zeigen wir keinen Namen.', 'en' => 'Live since {datum} — waiting for the customer’s consent. Without it we show no names.'],
        'e_seit' => ['it' => 'online dal {datum}', 'de' => 'online seit {datum}', 'en' => 'live since {datum}'],
        'e_kopieren' => ['it' => 'Copiare il post', 'de' => 'Beitrag kopieren', 'en' => 'Copy the post'],
        'e_wa' => ['it' => 'Condividere su WhatsApp', 'de' => 'Per WhatsApp teilen', 'en' => 'Share on WhatsApp'],
        'e_teilen' => ['it' => 'Condividere …', 'de' => 'Teilen …', 'en' => 'Share …'],
        'e_ansehen' => ['it' => 'Vedere il sito', 'de' => 'Website ansehen', 'en' => 'View the website'],
        'post_text' => ['it' => "Guardate cosa è nato per {firma}: {url}\nRealizzato da Vecom Design — li consiglio di cuore.",
                        'de' => "Seht, was für {firma} entstanden ist: {url}\nGemacht von Vecom Design — ich kann sie nur empfehlen.",
                        'en' => "Look what was created for {firma}: {url}\nMade by Vecom Design — I can only recommend them."],
        'post_link' => ['it' => 'Anche voi volete un sito così? Da qui:', 'de' => 'Auch so eine Website? Hier entlang:', 'en' => 'Want a site like this? Start here:'],
        // Kundenseite
        'k_titel' => ['it' => '{partner} può mostrare il suo nuovo sito?', 'de' => 'Darf {partner} Ihre neue Website zeigen?', 'en' => 'May {partner} show your new website?'],
        'k_text' => ['it' => '{partner} l’ha portata da noi. Se è d’accordo, {partner} può condividere il nome della sua attività e il link al sito — per esempio su WhatsApp o Instagram. Può ritirare il consenso quando vuole.',
                     'de' => '{partner} hat Sie zu uns gebracht. Wenn Sie zustimmen, darf {partner} den Namen Ihres Betriebs und den Link zur Website teilen — zum Beispiel auf WhatsApp oder Instagram. Sie können das jederzeit zurücknehmen.',
                     'en' => '{partner} brought you to us. If you agree, {partner} may share your business name and a link to the site — for example on WhatsApp or Instagram. You can withdraw this at any time.'],
        'k_ja' => ['it' => 'Sì, volentieri', 'de' => 'Ja, gern', 'en' => 'Yes, gladly'],
        'k_ist_ja' => ['it' => 'Ha dato il consenso — {partner} può mostrare il suo sito.', 'de' => 'Sie haben zugestimmt — {partner} darf Ihre Website zeigen.', 'en' => 'You agreed — {partner} may show your website.'],
        'k_zurueck' => ['it' => 'Ritirare il consenso', 'de' => 'Zustimmung zurücknehmen', 'en' => 'Withdraw consent'],
    ];

    /* ======================================================================
       TELEGRAM (30.09.2026) — was der Bot sagt. Die Fragen selbst kommen aus
       Baukasten::FRAGEN; hier stehen nur Menü, Rahmen und Rückfragen.
       Gesiezt (Sie/Lei), weil die Fragen des Baukastens siezen und der Bot
       nicht mitten im Gespräch wechseln soll.
       ====================================================================== */
    public const TELEGRAM = [
        'de' => [
            'kurz' => 'Individuelle Websites, Branding und interaktive Web-Erlebnisse. Preis-Richtwert in zwei Minuten.',
            'beschreibung' => "Willkommen bei Vecom Design.\n\nHier können Sie in acht kurzen Fragen einen Preis-Richtwert für Ihre Website bekommen, eine unverbindliche Anfrage senden oder persönliche Beratung anfordern.\n\nTippen Sie auf „Starten“.",
            'befehle' => ['start' => 'Neu beginnen', 'menu' => 'Hauptmenü', 'preis' => 'Preis berechnen', 'sprache' => 'Sprache ändern', 'hilfe' => 'Hilfe', 'delete' => 'Meine Chat-Daten löschen'],
            'menu' => "<b>Willkommen bei Vecom Design</b> 👋\n\nWir entwickeln individuelle Websites, digitale Markenauftritte und interaktive Web-Erlebnisse.\n\nWas möchten Sie tun?",
            'k_neu' => '🌐 Neue Website', 'k_besser' => '✨ Website verbessern', 'k_preis' => '💰 Preis berechnen', 'k_pruefen' => '🔎 Website prüfen',
            'k_logo' => '🎨 Logo & Branding', 'k_3d' => '🧊 3D & Interactive', 'k_hosting' => '🗂 Domain & Hosting', 'k_kunde' => '👤 Ich bin bereits Kunde',
            'k_mensch' => '💬 Persönliche Beratung', 'k_sprache' => '🌍 Sprache', 'k_kanal' => '📢 Neuigkeiten im Kanal',
            // Der angeheftete Menü-Beitrag im Kanal (30.09.2026); die Knöpfe darunter sind die des Bot-Menüs.
            'kanalMenue' => "👋 Willkommen bei Vecom Design!\n\nHier gibt es Neuigkeiten, Beispiele und Tipps rund um Websites für Betriebe und Unternehmen.\n\nUnd es geht gleich hier los: Tippen Sie auf einen Knopf — alles öffnet sich direkt hier in Telegram. Den Preis-Richtwert kennen Sie nach acht kurzen Fragen, bei Fragen antwortet ein Mensch.", 'k_menu' => '🏠 Menü', 'k_zurueck' => '⬅️ Zurück',
            'k_abbrechen' => '✖️ Abbrechen', 'k_weiter' => 'Weiter ➜',
            'frageKopf' => 'Frage {n} von {gesamt}',
            'mehrfach' => 'Mehrere Antworten möglich — danach „Weiter“.',
            'liveBisher' => 'Richtwert bisher: {betrag}',
            'mindEins' => 'Bitte wählen Sie mindestens eine Antwort.',
            'ergebnisKopf' => 'Ihre Projektindikation',
            'einmalig' => 'Richtwert (einmalig): <b>{spanne}</b>',
            'indikation' => 'Das ist eine unverbindliche Orientierung aus unserem Preisbaukasten — kein Angebot. Angebot und Erstgespräch sind kostenlos und unverbindlich.',
            'keinPreis' => 'Für diese Angaben möchten wir Ihnen keinen falschen Preis nennen. Wir prüfen sie persönlich und melden uns mit einer konkreten Einschätzung.',
            'logoHinweis' => 'Ein Logo gestalten wir nur auf Wunsch — es ist hier nicht mitgerechnet.',
            'k_senden' => '📩 Anfrage senden', 'k_aendern' => '✏️ Angaben ändern',
            'ds' => "🔒 <b>Kurz zum Datenschutz</b>\n\nWenn Sie uns schreiben, speichern wir Ihren Namen, Ihre E-Mail-Adresse und Ihre Angaben, um Ihre Anfrage zu bearbeiten und Ihnen zu antworten. Verantwortlich ist Vecom Design (Uwe Vetter), Aragona. Die Nachrichten laufen über Telegram; von Ihrem Telegram-Konto speichern wir nur die Chat-Nummer, die gewählte Sprache und den Stand dieses Gesprächs. Mit /delete können Sie das jederzeit entfernen.\n\nEinzelheiten und Ihre Rechte: {link}\n\nEinverstanden?",
            'dsLink' => 'Datenschutzerklärung',
            'k_ja' => '✅ Einverstanden',
            'fragName' => 'Wie dürfen wir Sie ansprechen? Bitte schreiben Sie Ihren <b>Vor- und Nachnamen</b>.',
            'nameFalsch' => 'Das sieht nicht wie ein Name aus. Bitte nur Vor- und Nachnamen, ohne Links.',
            'fragEmail' => 'Danke, {name}. An welche <b>E-Mail-Adresse</b> dürfen wir Ihnen schreiben?',
            'emailFalsch' => 'Diese E-Mail-Adresse sieht nicht richtig aus. Bitte noch einmal, z. B. name@beispiel.de',
            'fragNachricht' => 'Worum geht es? Schreiben Sie uns <b>in ein, zwei Sätzen</b> Ihr Anliegen.',
            'nachrichtFalsch' => 'Bitte schreiben Sie ein paar Worte zu Ihrem Anliegen (höchstens 1.000 Zeichen).',
            'pruefenKopf' => 'Bitte kurz prüfen:',
            'f_name' => 'Name', 'f_email' => 'E-Mail', 'f_anliegen' => 'Anliegen', 'f_richtwert' => 'Richtwert', 'f_thema' => 'Thema',
            'k_jetzt' => '✅ Jetzt senden', 'k_korrigieren' => '✏️ Korrigieren',
            'dankAnfrage' => "Danke, {name}! Ihre Anfrage ist bei uns angekommen. ✅\n\nSie bekommen gleich eine E-Mail an <b>{email}</b> mit Ihrem persönlichen Link — dort geht es mit ein paar Fragen zu Ihrem Projekt weiter. Das Angebot ist kostenlos und unverbindlich.",
            'dankBeratung' => "Danke, {name}! Ihre Nachricht ist bei uns angekommen. ✅\n\nUwe meldet sich persönlich bei Ihnen — per E-Mail an <b>{email}</b>.",
            'fehlerSenden' => 'Das Senden hat gerade nicht geklappt. Ihre Angaben sind nicht verloren — bitte versuchen Sie es gleich noch einmal.',
            'logoText' => "🎨 <b>Logo & Branding</b>\n\nEin Logo entwerfen wir individuell und nur auf Wunsch. Den Preis nennen wir nach einem kurzen Gespräch — ohne Rätselraten, kostenlos und unverbindlich.",
            'dreiDText' => "🧊 <b>3D & Interactive</b>\n\nInteraktive 3D-Erlebnisse planen wir individuell — dafür gibt es keinen Pauschalpreis. Erzählen Sie uns kurz, was Sie vorhaben.",
            'k_beispiel' => '🌐 Beispiel ansehen', 'k_anfragen' => '📩 Unverbindlich anfragen',
            'pruefenText' => "🔎 <b>Website prüfen</b>\n\nSchicken Sie mir einfach die Adresse Ihrer Website, z. B. <i>trattoria-rossi.it</i>.\n\nIch prüfe zwölf Punkte und nenne Ihnen die drei wichtigsten, die wir verbessern würden — kostenlos, in wenigen Sekunden.",
            'k_check' => '📊 Ausführliche Analyse',
            'hostingText' => "🗂 <b>Domain & Hosting</b>\n\nIhre Wunschdomain mit Hosting und E-Mail — auch ohne Website. Verfügbarkeit, Preis und Bestellung auf der Seite:",
            'k_hosting_seite' => '🗂 Domain & Hosting ansehen',
            'kundeText' => "👤 <b>Ihr persönlicher Bereich</b>\n\nIhren Projektstand, Nachrichten und Dateien finden Sie in Ihrem persönlichen Bereich. Aus Sicherheitsgründen schicken wir den Link nur an Ihre hinterlegte E-Mail-Adresse.\n\nTipp: Im persönlichen Bereich können Sie Telegram mit Ihrem Kundenkonto verbinden — dann sehen Sie hier Ihren Projektstand.",
            'k_zugang' => '🔐 Link per E-Mail anfordern',
            'menschText' => "💬 <b>Persönliche Beratung</b>\n\nGern verbinden wir Sie mit Uwe persönlich. Schreiben Sie direkt per WhatsApp — oder hinterlassen Sie hier Ihr Anliegen, dann meldet er sich per E-Mail.",
            'k_whatsapp' => '💬 Per WhatsApp schreiben', 'k_rueckmeldung' => '📩 Anliegen hinterlassen',
            'verstehe' => 'Ich bin der digitale Assistent von Vecom Design und arbeite mit Knöpfen. Für alles andere erreichen Sie Uwe über „Persönliche Beratung“.',
            'nurText' => 'Dateien, Bilder und Sprachnachrichten können wir hier noch nicht annehmen. Bitte nutzen Sie die Knöpfe.',
            'zuLang' => 'Das ist leider zu lang (höchstens 1.000 Zeichen).',
            'langsamer' => 'Einen Moment bitte — das waren gerade sehr viele Nachrichten.',
            'panne' => 'Da ist gerade etwas schiefgelaufen. Ihre bisherigen Angaben sind nicht verloren gegangen — bitte versuchen Sie es gleich noch einmal.',
            'abgebrochen' => 'Abgebrochen. Ihre bisherigen Antworten bleiben erhalten.',
            'hilfe' => "So funktioniert es:\n\n• <b>Preis berechnen</b> — acht kurze Fragen, danach ein Richtwert aus unserem Preisbaukasten.\n• <b>Anfrage senden</b> — kostenlos und unverbindlich.\n• <b>Persönliche Beratung</b> — jederzeit, zu einem Menschen.\n\n/menu Hauptmenü · /sprache Sprache · /delete Chat-Daten löschen",
            'loeschenFrage' => 'Sollen wir alles löschen, was dieser Chat bei uns gespeichert hat (Sprache, Antworten, Gesprächsstand)?',
            'k_loeschen' => '🗑 Ja, löschen', 'k_behalten' => 'Nein, behalten',
            'geloescht' => 'Erledigt — die Daten dieses Chats sind gelöscht. Eine bereits gesendete Anfrage bleibt bei uns; für deren Löschung schreiben Sie an kontakt@vecom-design.it.',
            'spracheGesetzt' => 'Alles klar — ab jetzt auf Deutsch.',
            // Telegram Growth Engine T3 (01.10.2026): Website-Check im Chat, KI & Automatisierung, Partner werden.
            'checkLaeuft' => "⏳ Ich prüfe <b>{host}</b> … einen Moment.",
            'checkKopf' => "🔎 <b>Website-Check: {host}</b>",
            'checkStand' => "{gut} von {gesamt} Punkten in Ordnung.",
            'checkTop3' => "<b>Das würden wir zuerst verbessern:</b>",
            'checkAlles' => "Technisch sieht die Startseite gut aus — alle {gesamt} Punkte in Ordnung.",
            'checkNicht' => "<i>Nicht automatisch geprüft: Gestaltung, Texte und wie klar Besucher zur Kontaktaufnahme geführt werden. Das sehen wir uns gern persönlich an.</i>",
            'checkNichtErreichbar' => "Die Seite <b>{host}</b> war bei der Prüfung nicht erreichbar. Stimmt die Adresse? Schicken Sie mir gern eine andere.",
            'checkAdresse' => "Das sieht nicht nach einer Website-Adresse aus. Bitte z. B. so: <i>trattoria-rossi.it</i>",
            'checkWarten' => "Einen Moment — die nächste Prüfung geht in ein paar Sekunden.",
            'checkZuviel' => "Für heute waren es viele Prüfungen. Morgen geht es wieder — oder Sie nutzen die ausführliche Analyse auf der Website.",
            'kiText' => "🤖 <b>KI & Automatisierung</b>\n\nWir bauen digitale Helfer, die Ihnen wiederkehrende Arbeit abnehmen — zum Beispiel einen Assistenten wie diesen hier in Telegram, automatische Antworten auf häufige Fragen, Terminbuchung oder Benachrichtigungen.\n\nWas für Ihren Betrieb sinnvoll ist, klären wir in einem kurzen Gespräch. Einen Pauschalpreis gibt es dafür nicht.",
            'partnerText' => "🤝 <b>Partner werden</b>\n\nSie kennen Betriebe, die eine neue oder bessere Website brauchen? Als Partner empfehlen Sie Vecom Design mit Ihrem persönlichen Link und erhalten für Aufträge, die darüber zustande kommen, eine Provision. Die Bedingungen stehen in der Partnervereinbarung.\n\nDie Bewerbung dauert zwei Minuten:",
            'k_verbessern' => '✨ Verbesserung planen', 'k_beratung' => '📞 Beratung', 'k_anderer' => '🔎 Andere Website prüfen',
            'k_ki' => '🤖 KI & Automatisierung', 'k_partner' => '🤝 Partner werden', 'k_partner_seite' => '🤝 Als Partner bewerben',
            // Kanal statt Bot (01.10.2026, Uwe: „normale Nutzer nur über den Kanal“).
            'nurKanal' => "👋 <b>Willkommen bei Vecom Design!</b>\n\nAlles finden Sie in unserem Kanal: Preis berechnen, Website prüfen, Beratung und mehr — es öffnet sich dort direkt als Fenster.",
            'botsText' => "📲 <b>Telegram-Bots erstellen</b>\n\nWir bauen Telegram-Bots und Mini-Apps für Ihren Betrieb — zum Beispiel für Anfragen, Preis-Richtwerte, Terminwünsche oder Neuigkeiten in einem eigenen Kanal, so wie hier bei Vecom Design.\n\nWas Ihr Bot können soll, klären wir in einem kurzen Gespräch. Einen Pauschalpreis gibt es dafür nicht.",
            'k_zum_kanal' => '📢 Zum Kanal', 'k_fenster' => '🧭 Vecom-Menü öffnen', 'k_bots' => '📲 Telegram-Bots erstellen',
            'thema' => ['logo' => 'Logo & Branding', '3d' => '3D & Interactive', 'allgemein' => 'Persönliche Beratung', 'ki' => 'KI & Automatisierung', 'bots' => 'Telegram-Bots'],
            'k_projekt' => '📂 Mein Projekt',
            'kundeKopf' => 'Ihr Projekt',
            'k_dashboard' => '🌐 Persönlichen Bereich öffnen',
            'k_schreiben' => '💬 Nachricht an Uwe',
            'k_datei' => '📎 Datei schicken',
            'k_hinweise_an' => '🔔 Hinweise: an',
            'k_hinweise_aus' => '🔕 Hinweise: aus',
            'k_trennen' => '🔌 Verbindung trennen',
            'verbunden' => '✅ <b>Verbunden.</b> Hallo {name}! Hier sehen Sie ab jetzt den Stand Ihres Projekts, können Uwe schreiben und Dateien schicken — und bekommen einen kurzen Hinweis, wenn Post von Vecom Design kommt.',
            'codeUngueltig' => 'Dieser Verbindungslink gilt nicht mehr (er ist 30 Minuten gültig und nur einmal). Bitte im persönlichen Bereich noch einmal auf „Mit Telegram verbinden“ tippen.',
            'fragKundenNachricht' => 'Schreiben Sie Ihre Nachricht an Uwe. Sie landet in Ihrem Projekt, genau wie im persönlichen Bereich.',
            'kundenNachrichtOk' => 'Ist bei Uwe angekommen. ✅ Die Antwort kommt per E-Mail — und hier als kurzer Hinweis.',
            'dateiTipp' => 'Schicken Sie die Datei einfach hier in den Chat: Bilder, PDF, Office-Dateien, ZIP, MP4 oder MP3, bis {max}.',
            'dateiFrage' => 'Soll „{name}“ zu Ihrem Projekt hinzugefügt werden?',
            'k_datei_ja' => '✅ Ja, hinzufügen',
            'dateiOk' => 'Danke! „{name}“ liegt jetzt bei Ihrem Projekt. ✅',
            'dateiFehler' => 'Diese Datei konnte ich nicht übernehmen. Erlaubt sind Bilder, PDF, Office-Dateien, ZIP, MP4 und MP3 bis {max}.',
            'dateiVoll' => 'Bei Ihrem Projekt liegen schon 40 Dateien. Bitte schreiben Sie Uwe, dann räumt er auf.',
            'dateiGross' => 'Telegram gibt Bots Dateien nur bis 20 MB heraus. Größere Dateien laden Sie bitte im persönlichen Bereich hoch.',
            'getrennt' => 'Die Verbindung zu Ihrem Kundenkonto ist gelöst. Sie können sie jederzeit im persönlichen Bereich neu herstellen.',
            'hinweiseAn' => 'Hinweise sind an.',
            'hinweiseAus' => 'Hinweise sind aus. Die E-Mails kommen weiter wie gewohnt.',
            'hinweisKopf' => 'Neue Nachricht von Vecom Design',
            'hinweisText' => 'Die ganze Nachricht liegt in Ihrem E-Mail-Postfach und in Ihrem persönlichen Bereich.',
            'beratungKopf' => 'Wunsch nach persönlicher Beratung (über Telegram) — Thema: {thema}',
        ],
        'it' => [
            'kurz' => 'Siti web su misura, branding ed esperienze web interattive. Una stima del prezzo in due minuti.',
            'beschreibung' => "Benvenuto da Vecom Design.\n\nQui può ottenere in otto brevi domande una stima del prezzo per il suo sito, inviare una richiesta senza impegno o chiedere una consulenza personale.\n\nTocchi «Avvia».",
            'befehle' => ['start' => 'Ricomincia', 'menu' => 'Menu principale', 'prezzo' => 'Calcola il prezzo', 'lingua' => 'Cambia lingua', 'aiuto' => 'Aiuto', 'delete' => 'Cancella i miei dati della chat'],
            'menu' => "<b>Benvenuto da Vecom Design</b> 👋\n\nRealizziamo siti web su misura, identità di marca digitali ed esperienze web interattive.\n\nChe cosa desidera fare?",
            'k_neu' => '🌐 Nuovo sito', 'k_besser' => '✨ Migliorare il sito', 'k_preis' => '💰 Calcola il prezzo', 'k_pruefen' => '🔎 Analisi del sito',
            'k_logo' => '🎨 Logo & branding', 'k_3d' => '🧊 3D & interattivo', 'k_hosting' => '🗂 Dominio & hosting', 'k_kunde' => '👤 Sono già cliente',
            'k_mensch' => '💬 Consulenza personale', 'k_sprache' => '🌍 Lingua', 'k_kanal' => '📢 Novità sul canale',
            'kanalMenue' => "👋 Benvenuti su Vecom Design!\n\nQui trova novità, esempi e consigli sui siti web per attività e aziende.\n\nE si comincia subito qui sotto: tocchi un pulsante — tutto si apre direttamente qui in Telegram. Il prezzo indicativo lo conosce dopo otto brevi domande, e per ogni dubbio risponde una persona.", 'k_menu' => '🏠 Menu', 'k_zurueck' => '⬅️ Indietro',
            'k_abbrechen' => '✖️ Annulla', 'k_weiter' => 'Avanti ➜',
            'frageKopf' => 'Domanda {n} di {gesamt}',
            'mehrfach' => 'Può scegliere più risposte — poi «Avanti».',
            'liveBisher' => 'Stima finora: {betrag}',
            'mindEins' => 'Scelga almeno una risposta, per favore.',
            'ergebnisKopf' => 'La sua stima di progetto',
            'einmalig' => 'Stima (una tantum): <b>{spanne}</b>',
            'indikation' => 'È un orientamento senza impegno calcolato con il nostro listino — non un’offerta. Offerta e primo colloquio sono gratuiti e senza impegno.',
            'keinPreis' => 'Per queste indicazioni non vogliamo darle un prezzo sbagliato. Le verifichiamo di persona e le scriviamo con una stima concreta.',
            'logoHinweis' => 'Il logo lo realizziamo solo su richiesta — qui non è compreso.',
            'k_senden' => '📩 Invia richiesta', 'k_aendern' => '✏️ Modifica risposte',
            'ds' => "🔒 <b>Due parole sulla privacy</b>\n\nSe ci scrive, salviamo il suo nome, il suo indirizzo e-mail e le sue indicazioni per gestire la richiesta e risponderle. Titolare è Vecom Design (Uwe Vetter), Aragona. I messaggi passano da Telegram; del suo account Telegram salviamo solo il numero della chat, la lingua scelta e lo stato di questa conversazione. Con /delete può cancellare tutto in qualsiasi momento.\n\nDettagli e diritti: {link}\n\nÈ d’accordo?",
            'dsLink' => 'Informativa sulla privacy',
            'k_ja' => '✅ Sono d’accordo',
            'fragName' => 'Come possiamo chiamarla? Scriva il suo <b>nome e cognome</b>.',
            'nameFalsch' => 'Non sembra un nome. Solo nome e cognome, senza link, per favore.',
            'fragEmail' => 'Grazie, {name}. A quale <b>indirizzo e-mail</b> possiamo scriverle?',
            'emailFalsch' => 'Questo indirizzo e-mail non sembra corretto. Me lo riscriva, per es. nome@esempio.it',
            'fragNachricht' => 'Di che cosa si tratta? Ci scriva <b>in una o due frasi</b> la sua richiesta.',
            'nachrichtFalsch' => 'Scriva qualche parola sulla sua richiesta (al massimo 1.000 caratteri).',
            'pruefenKopf' => 'Controlli un attimo:',
            'f_name' => 'Nome', 'f_email' => 'E-mail', 'f_anliegen' => 'Richiesta', 'f_richtwert' => 'Stima', 'f_thema' => 'Tema',
            'k_jetzt' => '✅ Invia ora', 'k_korrigieren' => '✏️ Correggi',
            'dankAnfrage' => "Grazie, {name}! La sua richiesta è arrivata. ✅\n\nTra poco riceve un’e-mail a <b>{email}</b> con il suo link personale — lì si prosegue con qualche domanda sul progetto. L’offerta è gratuita e senza impegno.",
            'dankBeratung' => "Grazie, {name}! Il suo messaggio è arrivato. ✅\n\nUwe le risponde di persona — via e-mail a <b>{email}</b>.",
            'fehlerSenden' => 'L’invio non è riuscito. I suoi dati non sono andati persi — riprovi tra un momento.',
            'logoText' => "🎨 <b>Logo & branding</b>\n\nIl logo lo progettiamo su misura e solo su richiesta. Il prezzo lo indichiamo dopo un breve colloquio — gratuito e senza impegno.",
            'dreiDText' => "🧊 <b>3D & interattivo</b>\n\nLe esperienze 3D interattive le progettiamo su misura — non esiste un prezzo forfettario. Ci racconti in breve che cosa ha in mente.",
            'k_beispiel' => '🌐 Vedi un esempio', 'k_anfragen' => '📩 Richiesta senza impegno',
            'pruefenText' => "🔎 <b>Analisi del sito</b>\n\nMi mandi semplicemente l’indirizzo del suo sito, per esempio <i>trattoria-rossi.it</i>.\n\nControllo dodici punti e le indico i tre più importanti che miglioreremmo — gratis, in pochi secondi.",
            'k_check' => '📊 Analisi completa',
            'hostingText' => "🗂 <b>Dominio & hosting</b>\n\nIl dominio che desidera, con hosting ed e-mail — anche senza sito. Disponibilità, prezzo e ordine sulla pagina:",
            'k_hosting_seite' => '🗂 Vedi dominio & hosting',
            'kundeText' => "👤 <b>Il suo spazio personale</b>\n\nStato del progetto, messaggi e file sono nel suo spazio personale. Per sicurezza inviamo il link solo all’indirizzo e-mail registrato.\n\nSuggerimento: nello spazio personale può collegare Telegram al suo account cliente — così qui vede lo stato del progetto.",
            'k_zugang' => '🔐 Ricevi il link via e-mail',
            'menschText' => "💬 <b>Consulenza personale</b>\n\nLa mettiamo volentieri in contatto con Uwe. Scriva direttamente su WhatsApp — oppure lasci qui la sua richiesta e lui le risponde via e-mail.",
            'k_whatsapp' => '💬 Scrivi su WhatsApp', 'k_rueckmeldung' => '📩 Lascia la richiesta',
            'verstehe' => 'Sono l’assistente digitale di Vecom Design e lavoro con i pulsanti. Per tutto il resto trova Uwe in «Consulenza personale».',
            'nurText' => 'File, immagini e messaggi vocali qui non li possiamo ancora ricevere. Usi i pulsanti, per favore.',
            'zuLang' => 'Purtroppo è troppo lungo (al massimo 1.000 caratteri).',
            'langsamer' => 'Un momento, per favore — erano moltissimi messaggi.',
            'panne' => 'Qualcosa è andato storto. I dati inseriti finora non sono andati persi — riprovi tra un momento.',
            'abgebrochen' => 'Annullato. Le risposte date finora restano salvate.',
            'hilfe' => "Come funziona:\n\n• <b>Calcola il prezzo</b> — otto brevi domande, poi una stima dal nostro listino.\n• <b>Invia richiesta</b> — gratuita e senza impegno.\n• <b>Consulenza personale</b> — in qualsiasi momento, con una persona.\n\n/menu menu · /lingua lingua · /delete cancella i dati della chat",
            'loeschenFrage' => 'Cancelliamo tutto ciò che questa chat ha salvato da noi (lingua, risposte, stato della conversazione)?',
            'k_loeschen' => '🗑 Sì, cancella', 'k_behalten' => 'No, tieni',
            'geloescht' => 'Fatto — i dati di questa chat sono cancellati. Una richiesta già inviata resta da noi; per cancellarla scriva a kontakt@vecom-design.it.',
            'spracheGesetzt' => 'Perfetto — da ora in italiano.',
            // Telegram Growth Engine T3 (01.10.2026): Website-Check im Chat, KI & Automatisierung, Partner werden.
            'checkLaeuft' => "⏳ Sto controllando <b>{host}</b> … un attimo.",
            'checkKopf' => "🔎 <b>Analisi del sito: {host}</b>",
            'checkStand' => "{gut} punti su {gesamt} sono a posto.",
            'checkTop3' => "<b>Ecco cosa miglioreremmo per primo:</b>",
            'checkAlles' => "Dal punto di vista tecnico la pagina iniziale è a posto — tutti i {gesamt} punti.",
            'checkNicht' => "<i>Non controllato in automatico: grafica, testi e quanto chiaramente i visitatori vengono guidati a contattarla. Lo guardiamo volentieri di persona.</i>",
            'checkNichtErreichbar' => "Il sito <b>{host}</b> non era raggiungibile durante la verifica. L’indirizzo è giusto? Me ne può mandare un altro.",
            'checkAdresse' => "Questo non sembra l’indirizzo di un sito. Per esempio così: <i>trattoria-rossi.it</i>",
            'checkWarten' => "Un attimo — la prossima verifica tra pochi secondi.",
            'checkZuviel' => "Per oggi le verifiche sono state tante. Domani si riparte — oppure usi l’analisi completa sul sito.",
            'kiText' => "🤖 <b>IA & automazione</b>\n\nRealizziamo aiutanti digitali che le tolgono il lavoro ripetitivo — per esempio un assistente come questo su Telegram, risposte automatiche alle domande frequenti, prenotazioni o notifiche.\n\nCosa ha senso per la sua attività lo chiariamo in una breve conversazione. Non esiste un prezzo forfettario.",
            'partnerText' => "🤝 <b>Diventare partner</b>\n\nConosce attività che hanno bisogno di un sito nuovo o migliore? Come partner consiglia Vecom Design con il suo link personale e riceve una provvigione per gli incarichi che nascono così. Le condizioni sono nell’accordo di partnership.\n\nLa candidatura richiede due minuti:",
            'k_verbessern' => '✨ Pianificare il miglioramento', 'k_beratung' => '📞 Consulenza', 'k_anderer' => '🔎 Controllare un altro sito',
            'k_ki' => '🤖 IA & automazione', 'k_partner' => '🤝 Diventare partner', 'k_partner_seite' => '🤝 Candidarsi come partner',
            // Kanal statt Bot (01.10.2026, Uwe: „normale Nutzer nur über den Kanal“).
            'nurKanal' => "👋 <b>Benvenuto da Vecom Design!</b>\n\nTrova tutto nel nostro canale: calcolare il prezzo, analizzare il sito, consulenza e altro — si apre lì direttamente come finestra.",
            'botsText' => "📲 <b>Creare bot Telegram</b>\n\nRealizziamo bot e mini-app Telegram per la sua attività — per esempio per richieste, stime di prezzo, prenotazioni o novità in un canale proprio, proprio come qui da Vecom Design.\n\nCosa deve saper fare il suo bot lo chiariamo in una breve conversazione. Non esiste un prezzo forfettario.",
            'k_zum_kanal' => '📢 Al canale', 'k_fenster' => '🧭 Aprire il menu Vecom', 'k_bots' => '📲 Creare bot Telegram',
            'thema' => ['logo' => 'Logo & branding', '3d' => '3D & interattivo', 'allgemein' => 'Consulenza personale', 'ki' => 'IA & automazione', 'bots' => 'Bot Telegram'],
            'k_projekt' => '📂 Il mio progetto',
            'kundeKopf' => 'Il suo progetto',
            'k_dashboard' => '🌐 Apri lo spazio personale',
            'k_schreiben' => '💬 Messaggio a Uwe',
            'k_datei' => '📎 Invia un file',
            'k_hinweise_an' => '🔔 Avvisi: attivi',
            'k_hinweise_aus' => '🔕 Avvisi: spenti',
            'k_trennen' => '🔌 Scollega',
            'verbunden' => '✅ <b>Collegato.</b> Buongiorno {name}! Da ora qui vede lo stato del suo progetto, può scrivere a Uwe e inviare file — e riceve un breve avviso quando arriva posta da Vecom Design.',
            'codeUngueltig' => 'Questo link di collegamento non è più valido (vale 30 minuti e una sola volta). Tocchi di nuovo «Collega Telegram» nel suo spazio personale.',
            'fragKundenNachricht' => 'Scriva il suo messaggio a Uwe. Arriva nel suo progetto, proprio come nello spazio personale.',
            'kundenNachrichtOk' => 'Arrivato a Uwe. ✅ La risposta arriva via e-mail — e qui come breve avviso.',
            'dateiTipp' => 'Invii semplicemente il file qui in chat: immagini, PDF, file Office, ZIP, MP4 o MP3, fino a {max}.',
            'dateiFrage' => 'Aggiungere «{name}» al suo progetto?',
            'k_datei_ja' => '✅ Sì, aggiungi',
            'dateiOk' => 'Grazie! «{name}» ora è nel suo progetto. ✅',
            'dateiFehler' => 'Non ho potuto accettare questo file. Sono ammessi immagini, PDF, file Office, ZIP, MP4 e MP3 fino a {max}.',
            'dateiVoll' => 'Nel suo progetto ci sono già 40 file. Scriva a Uwe e lui farà ordine.',
            'dateiGross' => 'Telegram consegna ai bot solo file fino a 20 MB. I file più grandi li carichi nel suo spazio personale.',
            'getrennt' => 'Il collegamento con il suo account cliente è stato rimosso. Può ricollegarlo in qualsiasi momento dallo spazio personale.',
            'hinweiseAn' => 'Avvisi attivi.',
            'hinweiseAus' => 'Avvisi spenti. Le e-mail continuano ad arrivare come sempre.',
            'hinweisKopf' => 'Nuovo messaggio da Vecom Design',
            'hinweisText' => 'Il messaggio completo è nella sua casella e-mail e nel suo spazio personale.',
            'beratungKopf' => 'Richiesta di consulenza personale (via Telegram) — tema: {thema}',
        ],
        'en' => [
            'kurz' => 'Custom websites, branding and interactive web experiences. A price estimate in two minutes.',
            'beschreibung' => "Welcome to Vecom Design.\n\nAnswer eight short questions to get a price estimate for your website, send a no-obligation request or ask for personal advice.\n\nTap “Start”.",
            'befehle' => ['start' => 'Start over', 'menu' => 'Main menu', 'price' => 'Calculate the price', 'language' => 'Change language', 'help' => 'Help', 'delete' => 'Delete my chat data'],
            'menu' => "<b>Welcome to Vecom Design</b> 👋\n\nWe build custom websites, digital brand identities and interactive web experiences.\n\nWhat would you like to do?",
            'k_neu' => '🌐 New website', 'k_besser' => '✨ Improve my website', 'k_preis' => '💰 Calculate the price', 'k_pruefen' => '🔎 Check my website',
            'k_logo' => '🎨 Logo & branding', 'k_3d' => '🧊 3D & interactive', 'k_hosting' => '🗂 Domain & hosting', 'k_kunde' => '👤 I am already a client',
            'k_mensch' => '💬 Personal advice', 'k_sprache' => '🌍 Language', 'k_kanal' => '📢 News on the channel',
            'kanalMenue' => "👋 Welcome to Vecom Design!\n\nNews, examples and tips about websites for businesses and companies.\n\nAnd it starts right here: tap a button — everything opens right here in Telegram. You get the price estimate after eight short questions, and a real person answers any questions.", 'k_menu' => '🏠 Menu', 'k_zurueck' => '⬅️ Back',
            'k_abbrechen' => '✖️ Cancel', 'k_weiter' => 'Next ➜',
            'frageKopf' => 'Question {n} of {gesamt}',
            'mehrfach' => 'You can pick several — then “Next”.',
            'liveBisher' => 'Estimate so far: {betrag}',
            'mindEins' => 'Please pick at least one answer.',
            'ergebnisKopf' => 'Your project estimate',
            'einmalig' => 'Estimate (one-off): <b>{spanne}</b>',
            'indikation' => 'This is a no-obligation guide from our price list — not a quote. The quote and the first conversation are free and without obligation.',
            'keinPreis' => 'For these answers we would rather not give you a wrong price. We will look at them personally and get back to you with a concrete estimate.',
            'logoHinweis' => 'We only design a logo on request — it is not included here.',
            'k_senden' => '📩 Send request', 'k_aendern' => '✏️ Change answers',
            'ds' => "🔒 <b>A word on privacy</b>\n\nIf you write to us, we store your name, your email address and your answers to handle your request and reply to you. The controller is Vecom Design (Uwe Vetter), Aragona. Messages go through Telegram; from your Telegram account we only store the chat number, the language you chose and the state of this conversation. You can remove this at any time with /delete.\n\nDetails and your rights: {link}\n\nDo you agree?",
            'dsLink' => 'Privacy policy',
            'k_ja' => '✅ I agree',
            'fragName' => 'How may we address you? Please write your <b>first and last name</b>.',
            'nameFalsch' => 'That does not look like a name. Just first and last name, without links, please.',
            'fragEmail' => 'Thank you, {name}. Which <b>email address</b> may we write to?',
            'emailFalsch' => 'That email address does not look right. Please try again, e.g. name@example.com',
            'fragNachricht' => 'What is it about? Tell us <b>in one or two sentences</b>.',
            'nachrichtFalsch' => 'Please write a few words about your request (at most 1,000 characters).',
            'pruefenKopf' => 'Please check:',
            'f_name' => 'Name', 'f_email' => 'Email', 'f_anliegen' => 'Request', 'f_richtwert' => 'Estimate', 'f_thema' => 'Topic',
            'k_jetzt' => '✅ Send now', 'k_korrigieren' => '✏️ Correct',
            'dankAnfrage' => "Thank you, {name}! Your request has arrived. ✅\n\nYou will shortly get an email at <b>{email}</b> with your personal link — a few questions about your project continue there. The quote is free and without obligation.",
            'dankBeratung' => "Thank you, {name}! Your message has arrived. ✅\n\nUwe will get back to you personally — by email at <b>{email}</b>.",
            'fehlerSenden' => 'Sending did not work just now. Your details are not lost — please try again in a moment.',
            'logoText' => "🎨 <b>Logo & branding</b>\n\nWe design logos individually and only on request. We name the price after a short conversation — free and without obligation.",
            'dreiDText' => "🧊 <b>3D & interactive</b>\n\nWe plan interactive 3D experiences individually — there is no flat price. Tell us briefly what you have in mind.",
            'k_beispiel' => '🌐 See an example', 'k_anfragen' => '📩 Ask without obligation',
            'pruefenText' => "🔎 <b>Check my website</b>\n\nJust send me the address of your website, e.g. <i>trattoria-rossi.it</i>.\n\nI check twelve points and name the three most important ones we would improve — free, in a few seconds.",
            'k_check' => '📊 Full analysis',
            'hostingText' => "🗂 <b>Domain & hosting</b>\n\nYour domain with hosting and email — even without a website. Availability, price and ordering on the page:",
            'k_hosting_seite' => '🗂 See domain & hosting',
            'kundeText' => "👤 <b>Your personal area</b>\n\nProject status, messages and files are in your personal area. For security we only send the link to the email address on file.\n\nTip: in your personal area you can connect Telegram to your client account — then you can see your project status here.",
            'k_zugang' => '🔐 Get the link by email',
            'menschText' => "💬 <b>Personal advice</b>\n\nWe will gladly put you in touch with Uwe. Write directly on WhatsApp — or leave your request here and he will reply by email.",
            'k_whatsapp' => '💬 Write on WhatsApp', 'k_rueckmeldung' => '📩 Leave a request',
            'verstehe' => 'I am the digital assistant of Vecom Design and work with buttons. For anything else, reach Uwe via “Personal advice”.',
            'nurText' => 'We cannot accept files, images or voice messages here yet. Please use the buttons.',
            'zuLang' => 'Sorry, that is too long (at most 1,000 characters).',
            'langsamer' => 'One moment please — that was a lot of messages.',
            'panne' => 'Something went wrong just now. Your answers so far are not lost — please try again in a moment.',
            'abgebrochen' => 'Cancelled. Your answers so far are kept.',
            'hilfe' => "How it works:\n\n• <b>Calculate the price</b> — eight short questions, then an estimate from our price list.\n• <b>Send request</b> — free and without obligation.\n• <b>Personal advice</b> — any time, to a person.\n\n/menu menu · /language language · /delete delete chat data",
            'loeschenFrage' => 'Shall we delete everything this chat has stored with us (language, answers, conversation state)?',
            'k_loeschen' => '🗑 Yes, delete', 'k_behalten' => 'No, keep',
            'geloescht' => 'Done — the data of this chat has been deleted. A request you already sent stays with us; to delete it, write to kontakt@vecom-design.it.',
            'spracheGesetzt' => 'All right — English from now on.',
            // Telegram Growth Engine T3 (01.10.2026): Website-Check im Chat, KI & Automatisierung, Partner werden.
            'checkLaeuft' => "⏳ Checking <b>{host}</b> … one moment.",
            'checkKopf' => "🔎 <b>Website check: {host}</b>",
            'checkStand' => "{gut} of {gesamt} points are fine.",
            'checkTop3' => "<b>This is what we would improve first:</b>",
            'checkAlles' => "Technically the home page looks good — all {gesamt} points are fine.",
            'checkNicht' => "<i>Not checked automatically: design, texts and how clearly visitors are guided to get in touch. We are happy to look at that in person.</i>",
            'checkNichtErreichbar' => "The site <b>{host}</b> could not be reached during the check. Is the address right? Feel free to send another one.",
            'checkAdresse' => "That does not look like a website address. For example: <i>trattoria-rossi.it</i>",
            'checkWarten' => "One moment — the next check is possible in a few seconds.",
            'checkZuviel' => "That was a lot of checks for today. Tomorrow works again — or use the full analysis on the website.",
            'kiText' => "🤖 <b>AI & automation</b>\n\nWe build digital helpers that take repetitive work off your hands — for example an assistant like this one on Telegram, automatic answers to frequent questions, bookings or notifications.\n\nWhat makes sense for your business we clarify in a short conversation. There is no flat price for it.",
            'partnerText' => "🤝 <b>Become a partner</b>\n\nDo you know businesses that need a new or better website? As a partner you recommend Vecom Design with your personal link and receive a commission for orders that come through it. The terms are in the partner agreement.\n\nApplying takes two minutes:",
            'k_verbessern' => '✨ Plan the improvement', 'k_beratung' => '📞 Advice', 'k_anderer' => '🔎 Check another website',
            'k_ki' => '🤖 AI & automation', 'k_partner' => '🤝 Become a partner', 'k_partner_seite' => '🤝 Apply as a partner',
            // Kanal statt Bot (01.10.2026, Uwe: „normale Nutzer nur über den Kanal“).
            'nurKanal' => "👋 <b>Welcome to Vecom Design!</b>\n\nYou will find everything in our channel: price estimate, website check, advice and more — it opens right there as a window.",
            'botsText' => "📲 <b>Build Telegram bots</b>\n\nWe build Telegram bots and mini apps for your business — for example for requests, price estimates, booking requests or news in your own channel, just like here at Vecom Design.\n\nWhat your bot should do we clarify in a short conversation. There is no flat price for it.",
            'k_zum_kanal' => '📢 To the channel', 'k_fenster' => '🧭 Open the Vecom menu', 'k_bots' => '📲 Build Telegram bots',
            'thema' => ['logo' => 'Logo & branding', '3d' => '3D & interactive', 'allgemein' => 'Personal advice', 'ki' => 'AI & automation', 'bots' => 'Telegram bots'],
            'k_projekt' => '📂 My project',
            'kundeKopf' => 'Your project',
            'k_dashboard' => '🌐 Open personal area',
            'k_schreiben' => '💬 Message Uwe',
            'k_datei' => '📎 Send a file',
            'k_hinweise_an' => '🔔 Notices: on',
            'k_hinweise_aus' => '🔕 Notices: off',
            'k_trennen' => '🔌 Disconnect',
            'verbunden' => '✅ <b>Connected.</b> Hello {name}! From now on you can see your project status here, write to Uwe and send files — and you get a short notice when mail from Vecom Design arrives.',
            'codeUngueltig' => 'This connection link is no longer valid (it lasts 30 minutes and works once). Please tap “Connect Telegram” again in your personal area.',
            'fragKundenNachricht' => 'Write your message to Uwe. It goes into your project, just like in your personal area.',
            'kundenNachrichtOk' => 'Uwe has it. ✅ The reply comes by email — and here as a short notice.',
            'dateiTipp' => 'Just send the file here in the chat: images, PDF, Office files, ZIP, MP4 or MP3, up to {max}.',
            'dateiFrage' => 'Add “{name}” to your project?',
            'k_datei_ja' => '✅ Yes, add it',
            'dateiOk' => 'Thank you! “{name}” is now in your project. ✅',
            'dateiFehler' => 'I could not accept this file. Images, PDF, Office files, ZIP, MP4 and MP3 up to {max} are allowed.',
            'dateiVoll' => 'There are already 40 files in your project. Please write to Uwe and he will tidy up.',
            'dateiGross' => 'Telegram only hands files up to 20 MB to bots. Please upload larger files in your personal area.',
            'getrennt' => 'The connection to your client account has been removed. You can reconnect any time from your personal area.',
            'hinweiseAn' => 'Notices are on.',
            'hinweiseAus' => 'Notices are off. Emails keep arriving as usual.',
            'hinweisKopf' => 'New message from Vecom Design',
            'hinweisText' => 'The full message is in your email inbox and in your personal area.',
            'beratungKopf' => 'Request for personal advice (via Telegram) — topic: {thema}',
        ],
    ];

    /* Der Block „Telegram“ im persönlichen Bereich (30.09.2026, Stufe 2). */
    /* ======================================================================
       TELEGRAM_APP — das Vecom-Fenster im Kanal (01.10.2026, Uwe: „normale
       Nutzer nur über den Kanal“). Menü, Anfrage und Website-Check als
       Mini-App über dem Kanal; die Inhalte (Texte der Themen, Check-Sätze)
       kommen aus TELEGRAM und PARTNER_CHECK — hier steht nur der Rahmen.
       ====================================================================== */
    public const TELEGRAM_APP = [
        'unter'    => ['it' => 'Che cosa desidera fare?', 'de' => 'Was möchten Sie tun?', 'en' => 'What would you like to do?'],
        'menu'     => ['it' => '← Menu', 'de' => '← Menü', 'en' => '← Menu'],
        'zurueck'  => ['it' => 'Torna a Telegram', 'de' => 'Zurück zu Telegram', 'en' => 'Back to Telegram'],
        'extern'   => ['it' => 'si apre nel browser', 'de' => 'öffnet sich im Browser', 'en' => 'opens in the browser'],
        'f_name'   => ['it' => 'Nome e cognome', 'de' => 'Vor- und Nachname', 'en' => 'First and last name'],
        'f_email'  => ['it' => 'E-mail', 'de' => 'E-Mail', 'en' => 'Email'],
        'f_text'   => ['it' => 'Di che cosa si tratta? (facoltativo)', 'de' => 'Worum geht es? (freiwillig)', 'en' => 'What is it about? (optional)'],
        'f_ds'     => ['it' => 'Ho letto l’informativa sulla privacy e sono d’accordo.', 'de' => 'Ich habe den Datenschutzhinweis gelesen und bin einverstanden.', 'en' => 'I have read the privacy notice and agree.'],
        'senden'   => ['it' => 'Invia richiesta', 'de' => 'Anfrage senden', 'en' => 'Send request'],
        'pflicht'  => ['it' => 'Controlli nome, e-mail e la spunta.', 'de' => 'Bitte Name, E-Mail und das Häkchen prüfen.', 'en' => 'Please check name, email and the tick box.'],
        'zuviel'   => ['it' => 'Per oggi sono arrivate molte richieste da qui. Ci scriva a kontakt@vecom-design.it.', 'de' => 'Für heute kamen von hier schon viele Anfragen. Schreiben Sie uns gern an kontakt@vecom-design.it.', 'en' => 'Many requests came from here today. Please write to kontakt@vecom-design.it.'],
        'fehler'   => ['it' => 'Qualcosa è andato storto: riprovi tra un attimo.', 'de' => 'Da ist etwas schiefgegangen — bitte gleich noch einmal.', 'en' => 'Something went wrong — please try again in a moment.'],
        'f_url'    => ['it' => 'Indirizzo del sito, per es. trattoria-rossi.it', 'de' => 'Adresse der Website, z. B. trattoria-rossi.it', 'en' => 'Website address, e.g. trattoria-rossi.it'],
        'pruefen'  => ['it' => 'Verificare', 'de' => 'Prüfen', 'en' => 'Check'],
        /* Eigener Hinweis: Im Fenster gibt es keinen Chat, keine Chat-Nummer und kein /delete (anders als im Bot). */
        'ds'       => [
            'it' => "🔒 <b>Due parole sulla privacy</b>\n\nSe ci scrive, salviamo il suo nome, il suo indirizzo e-mail e la sua richiesta per gestirla e risponderle — come per una richiesta dal sito. Titolare è Vecom Design (Uwe Vetter), Aragona. Del suo account Telegram non riceviamo né salviamo nulla.\n\nDettagli e diritti: {link}",
            'de' => "🔒 <b>Kurz zum Datenschutz</b>\n\nWenn Sie uns schreiben, speichern wir Ihren Namen, Ihre E-Mail-Adresse und Ihr Anliegen, um Ihre Anfrage zu bearbeiten und Ihnen zu antworten — wie bei einer Anfrage über die Website. Verantwortlich ist Vecom Design (Uwe Vetter), Aragona. Von Ihrem Telegram-Konto erhalten und speichern wir dabei nichts.\n\nEinzelheiten und Ihre Rechte: {link}",
            'en' => "🔒 <b>A word on privacy</b>\n\nIf you write to us, we store your name, your email address and your request to handle it and reply to you — just like a request made through the website. The controller is Vecom Design (Uwe Vetter), Aragona. We receive and store nothing from your Telegram account.\n\nDetails and your rights: {link}",
        ],
    ];

    /** Fassung des Datenschutzhinweises im Vecom-Fenster — steht mit dem Wortlaut in der Zustimmung. */
    public const TELEGRAM_APP_FASSUNG = 'tgapp-2026-10-01';

    public const TELEGRAM_DASHBOARD = [
        'titel'     => ['it' => 'Telegram', 'de' => 'Telegram', 'en' => 'Telegram'],
        'kanal_text' => ['it' => 'Il nostro canale: novità, esempi di siti e consigli.', 'de' => 'Unser Kanal: Neuigkeiten, Website-Beispiele und Tipps.', 'en' => 'Our channel: news, website examples and tips.'],
        'kanal_knopf' => ['it' => 'Apri il canale', 'de' => 'Kanal öffnen', 'en' => 'Open the channel'],
        'text'      => ['it' => 'Stato del progetto, messaggi a Uwe, file e brevi avvisi — direttamente in Telegram. Il link vale 30 minuti e una sola volta.',
                        'de' => 'Projektstand, Nachrichten an Uwe, Dateien und kurze Hinweise — direkt in Telegram. Der Link gilt 30 Minuten und nur einmal.',
                        'en' => 'Project status, messages to Uwe, files and short notices — right in Telegram. The link lasts 30 minutes and works once.'],
        'knopf'     => ['it' => 'Collega Telegram', 'de' => 'Mit Telegram verbinden', 'en' => 'Connect Telegram'],
        'verbunden' => ['it' => 'Collegato dal {datum}. Gli avvisi arrivano in Telegram.', 'de' => 'Verbunden seit {datum}. Hinweise kommen in Telegram an.', 'en' => 'Connected since {datum}. Notices arrive in Telegram.'],
        'trennen'   => ['it' => 'Scollega', 'de' => 'Verbindung trennen', 'en' => 'Disconnect'],
        'getrennt'  => ['it' => 'Il collegamento con Telegram è stato rimosso.', 'de' => 'Die Verbindung mit Telegram ist gelöst.', 'en' => 'The Telegram connection has been removed.'],
        'nichtJetzt'=> ['it' => 'Telegram al momento non è disponibile. Riprovi più tardi.', 'de' => 'Telegram ist gerade nicht verfügbar. Bitte später noch einmal.', 'en' => 'Telegram is not available right now. Please try again later.'],
    ];

    /* ======================================================================
       VERZEICHNIS — Texte für Einträge in Verzeichnissen und für Anfragen an
       Kanäle (Telegram Growth Engine T5, 01.10.2026, Uwe: „ja“). Sie stehen
       öffentlich bei Google, in Branchenbüchern und Telegram-Katalogen —
       deshalb nur, was auch auf der Website steht (Aragona, dreisprachig,
       offene Preise, acht Fragen bis zum Richtwert, kostenloser Check). Keine
       Kundenzahlen, keine Sterne, keine Versprechen, die niemand geprüft hat.

       kanal   die Beschreibung des Telegram-Kanals, IT und DE in einem Text —
               italienische Kataloge übernehmen sie (Telegram: höchstens 255 Zeichen)
       kurz    Kurzbeschreibung für Kataloge (CanaliTelegram: höchstens 250 Zeichen)
       lang    Firmenbeschreibung für Branchenverzeichnisse und Karten
       kooperation  Anfrage an den Admin eines Kanals, der Kooperationen anbietet;
               {kanal} dessen Name, {inhaber} wer schreibt, {link} unser Kanal-Link
       ====================================================================== */
    public const VERZEICHNIS = [
        'kanal' => "🇮🇹 Siti web per attività e aziende in Sicilia: novità, esempi e consigli.\n🇩🇪 Websites für Betriebe und Unternehmen aus Sizilien: Neuigkeiten, Beispiele und Tipps.\n🌐 vecom-design.it",
        'kurz' => [
            'it' => 'Siti web su misura per attività e aziende in provincia di Agrigento e in Sicilia. Trilingue IT/DE/EN, prezzi allo scoperto: il prezzo indicativo dopo otto brevi domande. Check del sito gratuito.',
            'de' => 'Websites nach Maß für Betriebe und Unternehmen aus Sizilien (Aragona, Agrigent). Dreisprachig IT/DE/EN, Preise offen gelegt: den Richtwert gibt es nach acht kurzen Fragen. Website-Check kostenlos.',
            'en' => 'Custom websites for businesses and companies in Sicily (Aragona, Agrigento). Trilingual IT/DE/EN, prices in the open: a price estimate after eight short questions. Free website check.',
        ],
        'lang' => [
            'it' => 'Vecom Design realizza siti web su misura, negozi online e loghi per attività e aziende – da Aragona (AG) per la provincia di Agrigento e tutta la Sicilia. I siti sono in tre lingue (italiano, tedesco, inglese), veloci e costruiti per Google. I prezzi sono pubblici: sul sito, dopo otto brevi domande, si conosce il prezzo indicativo. Inoltre: check gratuito del sito esistente, dominio e hosting, assistenza continuativa, automazioni con IA e bot Telegram. A ogni domanda risponde una persona.',
            'de' => 'Vecom Design baut Websites nach Maß, Online-Shops und Logos für Betriebe und Unternehmen – aus Aragona (Agrigent) für die Provinz Agrigent und ganz Sizilien. Die Seiten sind dreisprachig (Italienisch, Deutsch, Englisch), schnell und für Google gebaut. Die Preise sind offen: Nach acht kurzen Fragen auf der Website steht der Richtwert fest. Dazu: kostenloser Check der bestehenden Website, Domain und Hosting, laufende Betreuung, KI-Automatisierung und Telegram-Bots. Jede Frage beantwortet ein Mensch.',
            'en' => 'Vecom Design builds custom websites, online stores and logos for businesses and companies – from Aragona (Agrigento) for the province of Agrigento and all of Sicily. Sites are trilingual (Italian, German, English), fast and built for Google. Prices are public: after eight short questions on the website you know the price estimate. Also: a free check of your existing website, domain and hosting, ongoing support, AI automation and Telegram bots. A real person answers every question.',
        ],
        'kooperation' => [
            'it' => "Buongiorno! Ho visto che il canale {kanal} accoglie collaborazioni con attività locali. Sono {inhaber} di Vecom Design ad Aragona (AG): realizziamo siti web per attività e aziende e nel nostro canale Telegram pubblichiamo novità, esempi e consigli. Le interesserebbe una menzione reciproca? Presenteremmo volentieri il suo canale ai nostri iscritti.\n\nIl nostro canale: {link}\n\nGrazie e buona giornata!",
            'de' => "Guten Tag! Ich habe gesehen, dass {kanal} Kooperationen mit lokalen Betrieben anbietet. Ich bin {inhaber} von Vecom Design in Aragona (AG): Wir bauen Websites für Betriebe und Unternehmen und zeigen in unserem Telegram-Kanal Neuigkeiten, Beispiele und Tipps. Hätten Sie Interesse an einer gegenseitigen Erwähnung? Wir stellen Ihren Kanal gern unseren Abonnenten vor.\n\nUnser Kanal: {link}\n\nDanke und einen schönen Tag!",
            'en' => "Hello! I saw that {kanal} welcomes collaborations with local businesses. I am {inhaber} from Vecom Design in Aragona (Agrigento): we build websites for businesses and companies and share news, examples and tips in our Telegram channel. Would you be interested in a mutual mention? We would be happy to introduce your channel to our subscribers.\n\nOur channel: {link}\n\nThank you and have a nice day!",
        ],
    ];
}
