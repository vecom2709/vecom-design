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
    public const PARTNER = [
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
        'konto_bereit'=> ['it' => 'Il conto è pronto: le provvigioni vengono pagate automaticamente.', 'de' => 'Das Konto ist bereit: Provisionen werden automatisch ausgezahlt.', 'en' => 'Your account is ready: commissions are paid out automatically.'],
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
        'teilen_text'=> ['it' => 'Ti serve un sito web? Ti consiglio Vecom Design: ', 'de' => 'Du brauchst eine Website? Ich kann Vecom Design empfehlen: ', 'en' => 'Need a website? I can recommend Vecom Design: '],
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
        'w_post1'    => ['it' => "Ti serve un sito web per la tua attività? Con Vecom Design ti trovi bene: prezzo chiaro prima, lavoro seguito passo passo. 👉 {link}", 'de' => "Du brauchst eine Website für deinen Betrieb? Bei Vecom Design bist du gut aufgehoben: klarer Preis vorher, Schritt für Schritt begleitet. 👉 {link}", 'en' => "Need a website for your business? Vecom Design takes good care of you: clear price upfront, guided step by step. 👉 {link}"],
        'w_post2'    => ['it' => "Il tuo sito è vecchio o non ce l’hai? Ti consiglio Vecom Design — dalla prima chiacchierata al sito online. {link} #pubblicità", 'de' => "Deine Website ist alt oder du hast keine? Ich empfehle Vecom Design — vom ersten Gespräch bis die Seite online ist. {link} #Werbung", 'en' => "Old website or none at all? I recommend Vecom Design — from the first chat to your site going live. {link} #ad"],
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
        'fi_chance_hoch'   => ['it' => 'Nessun sito / molto da fare', 'de' => 'Keine Website / viel zu tun', 'en' => 'No website / much to do'],
        'fi_chance_mittel' => ['it' => 'Sito migliorabile', 'de' => 'Website ausbaufähig', 'en' => 'Website could improve'],
        'fi_chance_gering' => ['it' => 'Sito già discreto', 'de' => 'Website schon ordentlich', 'en' => 'Website already decent'],
        'fi_web_neu'  => ['it' => '{n} attività aggiunte ora da OpenStreetMap.', 'de' => '{n} Betriebe gerade aus OpenStreetMap ergänzt.', 'en' => '{n} businesses just added from OpenStreetMap.'],
        'fi_web_fehler' => ['it' => 'OpenStreetMap al momento non risponde — vede solo le attività già note. Riprovi tra un’ora.', 'de' => 'OpenStreetMap antwortet gerade nicht — Sie sehen nur die schon bekannten Betriebe. In einer Stunde nochmal versuchen.', 'en' => 'OpenStreetMap isn’t responding right now — you only see businesses we already know. Try again in an hour.'],
        'fi_osm'      => ['it' => 'Dati: © contributori di OpenStreetMap (ODbL) · Overture Maps Foundation (CDLA-Permissive-2.0)', 'de' => 'Daten: © OpenStreetMap-Mitwirkende (ODbL) · Overture Maps Foundation (CDLA-Permissive-2.0)', 'en' => 'Data: © OpenStreetMap contributors (ODbL) · Overture Maps Foundation (CDLA-Permissive-2.0)'],
        'fi_quellen'  => ['it' => 'Cercare anche altrove (nel suo browser)', 'de' => 'Auch woanders suchen (in Ihrem Browser)', 'en' => 'Search elsewhere too (in your browser)'],
        'fi_quellen_text' => ['it' => 'Apre la ricerca con settore e luogo. Trovato qualcosa? Lo inserisca qui sotto — è subito suo.', 'de' => 'Öffnet die Suche mit Branche und Ort. Etwas gefunden? Unten eintragen — dann gehört er Ihnen.', 'en' => 'Opens the search with sector and place. Found something? Add it below — it’s yours right away.'],
        'fi_q' => ['maps' => ['it' => 'Google Maps', 'de' => 'Google Maps', 'en' => 'Google Maps'], 'pagine' => ['it' => 'Pagine Gialle', 'de' => 'Pagine Gialle', 'en' => 'Pagine Gialle'],
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
        'ck_wa_text'  => ['it' => 'Ciao! Ho dato un’occhiata veloce al tuo sito, ecco il risultato: ', 'de' => 'Hallo! Ich habe mir deine Website kurz angesehen, hier das Ergebnis: ', 'en' => 'Hi! I had a quick look at your website, here’s the result: '],
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
        'it' => "ACCORDO PARTNER — VECOM DESIGN\n\n"
              . "1. Il partner consiglia Vecom Design. Per gli acquisti di clienti arrivati per la prima volta tramite il suo link o il suo codice riceve una provvigione di {satz} sull’importo netto effettivamente pagato. Conta il primo contatto; un cliente già acquisito da un altro partner o già cliente non viene riassegnato. L’assegnazione vale per {zuordnung} mesi; per contratti mensili la provvigione vale per i primi {monate} mesi.\n"
              . "2. La provvigione nasce solo a pagamento ricevuto e diventa pagabile dopo {tage} giorni (periodo di recesso del cliente). Se il cliente viene rimborsato, la provvigione decade o viene stornata; importi già pagati possono essere trattenuti o richiesti indietro.\n"
              . "3. Il pagamento avviene tramite Stripe a partire da {min}, sul conto che il partner configura presso Stripe. Vecom Design non vede i dati bancari.\n"
              . "4. Non spettano provvigioni su acquisti propri. È vietata pubblicità ingannevole, spam e l’acquisto di annunci sul nome «Vecom». Il partner indica chiaramente che si tratta di un link con provvigione (per es. #pubblicità).\n"
              . "5. Il partner non è dipendente né rappresentante di Vecom Design e non fa promesse a suo nome.\n"
              . "6. Il partner è responsabile della propria posizione fiscale. Se la legge lo prevede, Vecom Design trattiene le imposte dovute.\n"
              . "7. Il partner vede solo numeri, mai dati dei clienti.\n"
              . "8. Entrambi possono recedere in qualsiasi momento. Le provvigioni già maturate vengono pagate. Vecom Design può modificare le condizioni per il futuro, comunicandolo per e-mail.",
        'de' => "PARTNERVEREINBARUNG — VECOM DESIGN\n\n"
              . "1. Der Partner empfiehlt Vecom Design. Für Käufe von Kunden, die zum ersten Mal über seinen Link oder Code kommen, erhält er eine Provision von {satz} vom tatsächlich bezahlten Nettobetrag. Es zählt der erste Kontakt; wer bereits Kunde ist oder über einen anderen Partner kam, wird nicht umgehängt. Die Zuordnung gilt {zuordnung} Monate; bei monatlichen Verträgen gilt die Provision für die ersten {monate} Monate.\n"
              . "2. Die Provision entsteht erst mit dem Zahlungseingang und wird nach {tage} Tagen (Widerrufsfrist des Kunden) auszahlbar. Wird dem Kunden erstattet, entfällt sie oder wird zurückgebucht; bereits ausgezahlte Beträge können verrechnet oder zurückgefordert werden.\n"
              . "3. Ausgezahlt wird über Stripe ab {min} auf das Konto, das der Partner bei Stripe einrichtet. Vecom Design sieht keine Bankdaten.\n"
              . "4. Auf eigene Käufe gibt es keine Provision. Irreführende Werbung, Spam und gekaufte Anzeigen auf den Namen „Vecom“ sind nicht erlaubt. Der Partner kennzeichnet seinen Link als Werbung mit Provision (z. B. „Werbung“).\n"
              . "5. Der Partner ist weder Angestellter noch Vertreter von Vecom Design und macht keine Zusagen in dessen Namen.\n"
              . "6. Für seine Steuern ist der Partner selbst verantwortlich. Wo das Gesetz es verlangt, behält Vecom Design Steuern ein.\n"
              . "7. Der Partner sieht nur Zahlen, nie Kundendaten.\n"
              . "8. Beide Seiten können jederzeit beenden. Bereits verdiente Provisionen werden ausgezahlt. Vecom Design kann die Bedingungen für die Zukunft ändern und teilt das per E-Mail mit.",
        'en' => "PARTNER AGREEMENT — VECOM DESIGN\n\n"
              . "1. The partner recommends Vecom Design. For purchases by customers who arrive for the first time through the partner’s link or code, the partner receives a commission of {satz} of the net amount actually paid. The first contact counts; existing customers or customers of another partner are not reassigned. The assignment lasts {zuordnung} months; for monthly contracts the commission applies to the first {monate} months.\n"
              . "2. Commission arises only once payment is received and becomes payable after {tage} days (the customer’s withdrawal period). If the customer is refunded, the commission lapses or is reversed; amounts already paid may be offset or reclaimed.\n"
              . "3. Payouts are made via Stripe from {min}, to the account the partner sets up with Stripe. Vecom Design never sees bank details.\n"
              . "4. No commission on one’s own purchases. Misleading advertising, spam and paid ads on the name “Vecom” are not allowed. The partner clearly discloses that the link earns a commission (e.g. #ad).\n"
              . "5. The partner is neither an employee nor an agent of Vecom Design and makes no promises on its behalf.\n"
              . "6. The partner is responsible for their own taxes. Where the law requires it, Vecom Design withholds tax.\n"
              . "7. The partner sees only numbers, never customer data.\n"
              . "8. Either side may end the partnership at any time. Commission already earned is paid out. Vecom Design may change the terms for the future and will announce this by email.",
    ];

    /** Die Abzugszeile auf dem Beleg einer Betreuungsrate (26.09.2026). */
    public const EMPFEHLUNGSRABATT = [
        'it' => 'Sconto per raccomandazione ({p} %)',
        'de' => 'Empfehlungsrabatt ({p} %)',
        'en' => 'Referral discount ({p} %)',
    ];

    /** Die Landeseite hinter /p/CODE (26.09.2026). {name} = wie der Partner genannt werden will. */
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
            'check' => ['it' => 'Verifica veloce', 'de' => 'Schnellcheck', 'en' => 'Quick check'],
            'erfolg' => ['it' => 'Post «È online»', 'de' => 'Beitrag „Ist online“', 'en' => '“It’s live” post'],
            'weiter' => ['it' => 'Inoltrata da clienti', 'de' => 'Von Kunden weitergeleitet', 'en' => 'Forwarded by customers'],
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
                        'de' => "Hey! Du hattest doch erwähnt, dass du eine neue Website brauchst. Ich kann dir Vecom Design empfehlen: Du sagst, was du brauchst, und kennst den Preis vorher. Danach siehst du jeden Schritt in deinem eigenen Bereich, bis die Seite online ist.\n\nSchau es dir hier an: {link}\n\n(PS: Ich bin Partner von Vecom Design.)",
                        'en' => "Hi! You mentioned you need a new website. I can recommend Vecom Design: you tell them what you need and know the price upfront. Then you follow every step in your own area until the site is live.\n\nHave a look here: {link}\n\n(PS: I’m a Vecom Design partner.)"],
                ],
                'status' => [
                    'titel' => ['it' => 'Stato / gruppo', 'de' => 'Status / Gruppe', 'en' => 'Status / group'],
                    'text' => [
                        'it' => "Conosci qualcuno a cui serve un sito web? 🌐\nVecom Design lo realizza: prezzo chiaro prima, seguito di persona.\n👉 {link}\n#adv",
                        'de' => "Kennst du jemanden, der eine Website braucht? 🌐\nVecom Design baut sie: klarer Preis vorher, persönlich begleitet.\n👉 {link}\n#Werbung",
                        'en' => "Know someone who needs a website? 🌐\nVecom Design builds it: clear price upfront, personal guidance.\n👉 {link}\n#ad"],
                ],
            ],
            'instagram' => [
                'post' => [
                    'titel' => ['it' => 'Post (didascalia)', 'de' => 'Beitrag (Bildtext)', 'en' => 'Post (caption)'],
                    'text' => [
                        'it' => "Oggi un buon sito è la vetrina di ogni attività. 🪟\n\nSe te ne serve uno: Vecom Design lo realizza per te. Dici cosa ti serve e sai il prezzo prima, poi vedi ogni passo nella tua area personale.\n\n🔗 Link nella mia bio: {link}\n\n#adv #sitoweb #webdesign #sicilia #piccoleimprese #partitaiva",
                        'de' => "Eine gute Website ist heute das Schaufenster jedes Betriebs. 🪟\n\nWenn du eine brauchst: Vecom Design baut sie für dich. Du sagst, was du brauchst, kennst den Preis vorher und siehst jeden Schritt in deinem persönlichen Bereich.\n\n🔗 Link in meiner Bio: {link}\n\n#Werbung #website #webdesign #sizilien #selbstständig #kleinunternehmen",
                        'en' => "A good website is every business’s shop window today. 🪟\n\nIf you need one: Vecom Design builds it for you. You say what you need, know the price upfront and follow every step in your personal area.\n\n🔗 Link in my bio: {link}\n\n#ad #website #webdesign #sicily #smallbusiness #entrepreneur"],
                ],
                'story' => [
                    'titel' => ['it' => 'Storia (testo + sticker)', 'de' => 'Story (Text + Sticker)', 'en' => 'Story (text + sticker)'],
                    'text' => [
                        'it' => "Testo sull’immagine:\nTi serve un sito web?\nTi consiglio Vecom Design.\n\nSticker «Link»: {link}\nIndicazione: #adv",
                        'de' => "Text aufs Bild:\nDu brauchst eine Website?\nIch empfehle Vecom Design.\n\nSticker „Link“: {link}\nKennzeichnung: #Werbung",
                        'en' => "Text on the image:\nNeed a website?\nI recommend Vecom Design.\n\n“Link” sticker: {link}\nLabel: #ad"],
                ],
                'reel' => [
                    'titel' => ['it' => 'Reel: copione 15 secondi', 'de' => 'Reel: Drehbuch 15 Sekunden', 'en' => 'Reel: 15-second script'],
                    'text' => [
                        'it' => "🎬 0–3 s, lei in camera: «La tua attività non ha un sito? Per tanti clienti allora non esisti.»\n🎬 3–10 s, mostri il telefono e scorri: «Io consiglio Vecom Design: dici cosa ti serve e sai il prezzo prima.»\n🎬 10–15 s, codice QR o cartolina finale: «Link nella mia bio.»\n\nDidascalia:\nIl tuo sito, con il prezzo chiaro prima. 🔗 Link in bio: {link}\n#adv #sitoweb #webdesign #sicilia",
                        'de' => "🎬 0–3 s, Sie in die Kamera: „Dein Betrieb hat keine Website? Dann gibt es dich für viele Kunden nicht.“\n🎬 3–10 s, Handy zeigen und scrollen: „Ich empfehle Vecom Design: Du sagst, was du brauchst, und kennst den Preis vorher.“\n🎬 10–15 s, QR-Code oder Endkarte: „Link in meiner Bio.“\n\nBildtext:\nDeine Website, mit klarem Preis vorher. 🔗 Link in Bio: {link}\n#Werbung #website #webdesign #sizilien",
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
                        'de' => "Text im Bild, 0–2 s: „3 Gründe, warum dein Betrieb eine Website braucht“\n1) „Kunden suchen dich zuerst bei Google.“\n2) „Ohne Website entscheiden sie sich für die Konkurrenz.“\n3) „Mit Vecom Design kennst du den Preis vorher.“\nSchluss: QR-Code zeigen + „Link in Bio“\n\nGesprochen oder Trend-Sound, Untertitel an.",
                        'en' => "On-screen text, 0–2 s: “3 reasons your business needs a website”\n1) “Customers look you up on Google first.”\n2) “Without a website they choose the competition.”\n3) “With Vecom Design you know the price upfront.”\nEnd: show the QR code + “Link in bio”\n\nVoiceover or trending sound, captions on."],
                ],
                'text' => [
                    'titel' => ['it' => 'Descrizione del video', 'de' => 'Beschreibung zum Video', 'en' => 'Video caption'],
                    'text' => [
                        'it' => "Il tuo sito senza sorprese sul prezzo 👇 Link in bio: {link}\n#adv #webdesign #sitoweb #sicilia #piccoleimprese",
                        'de' => "Deine Website ohne Überraschungen beim Preis 👇 Link in Bio: {link}\n#Werbung #webdesign #website #selbstständig #sizilien",
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
                        'de' => "Hi, hier ist {name}. Mein Tipp für deine Website: Vecom Design, klarer Preis vorher und persönlich begleitet. {link} (Ich bin dort Partner.)",
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
                'titel' => ['it' => 'Il tuo sito web. Prezzo chiaro prima.', 'de' => 'Deine Website. Klarer Preis vorher.', 'en' => 'Your website. Clear price upfront.'],
                'unter' => ['it' => 'Seguito di persona, finché è online.', 'de' => 'Persönlich begleitet, bis sie online ist.', 'en' => 'Personally guided until it’s live.']],
            'gastro' => ['name' => ['it' => 'Ristoranti & bar', 'de' => 'Gastronomie', 'en' => 'Restaurants & bars'],
                'titel' => ['it' => 'Più ospiti ti trovano online.', 'de' => 'Mehr Gäste finden dich online.', 'en' => 'More guests find you online.'],
                'unter' => ['it' => 'Siti web per ristoranti, bar e caffè.', 'de' => 'Websites für Restaurants, Bars und Cafés.', 'en' => 'Websites for restaurants, bars and cafés.']],
            'unterkunft' => ['name' => ['it' => 'Hotel & case vacanza', 'de' => 'Unterkünfte', 'en' => 'Stays'],
                'titel' => ['it' => 'La tua struttura, mostrata bene.', 'de' => 'Deine Unterkunft, schön gezeigt.', 'en' => 'Your place, beautifully shown.'],
                'unter' => ['it' => 'Siti web per hotel, B&B e case vacanza.', 'de' => 'Websites für Hotels, B&Bs und Ferienwohnungen.', 'en' => 'Websites for hotels, B&Bs and holiday homes.']],
            'handwerk' => ['name' => ['it' => 'Artigiani & edilizia', 'de' => 'Handwerk & Bau', 'en' => 'Trades'],
                'titel' => ['it' => 'Un buon lavoro merita un buon sito.', 'de' => 'Gute Arbeit verdient eine gute Website.', 'en' => 'Good work deserves a good website.'],
                'unter' => ['it' => 'Per artigiani, imprese edili e installatori.', 'de' => 'Für Handwerk, Bau und Installateure.', 'en' => 'For tradespeople, builders and installers.']],
            'laden' => ['name' => ['it' => 'Negozi & beauty', 'de' => 'Laden & Beauty', 'en' => 'Shops & beauty'],
                'titel' => ['it' => 'Il tuo negozio, visibile anche online.', 'de' => 'Dein Laden, jetzt auch online sichtbar.', 'en' => 'Your shop, visible online too.'],
                'unter' => ['it' => 'Per negozi, parrucchieri ed estetiste.', 'de' => 'Für Geschäfte, Friseure und Kosmetik.', 'en' => 'For shops, hairdressers and beauty salons.']],
        ],
        'punkte' => [
            ['it' => 'Dici cosa ti serve.', 'de' => 'Du sagst, was du brauchst.', 'en' => 'You say what you need.'],
            ['it' => 'Sai il prezzo prima.', 'de' => 'Du kennst den Preis vorher.', 'en' => 'You know the price upfront.'],
            ['it' => 'Ti seguiamo fino online.', 'de' => 'Wir begleiten dich bis online.', 'en' => 'We guide you until it’s live.'],
        ],
        'empf'     => ['it' => 'Consigliato da {name}', 'de' => 'Empfohlen von {name}', 'en' => 'Recommended by {name}'],
        'scan'     => ['it' => 'Inquadra e inizia', 'de' => 'Scannen & loslegen', 'en' => 'Scan & get started'],
        'codewort' => ['it' => 'Codice', 'de' => 'Code', 'en' => 'Code'],
        'hook'     => ['it' => 'La tua attività non ha un sito?', 'de' => 'Dein Betrieb hat keine Website?', 'en' => 'Your business has no website?'],
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
        ],
        'titel'   => ['it' => 'Verifica veloce: {host}', 'de' => 'Kurz-Check: {host}', 'en' => 'Quick check: {host}'],
        'lead'    => ['it' => 'Controllato automaticamente il {datum}. Sei punti che visitatori e Google notano subito.', 'de' => 'Automatisch geprüft am {datum}. Sechs Punkte, die Besucher und Google sofort merken.', 'en' => 'Checked automatically on {datum}. Six things visitors and Google notice right away.'],
        'fazit0'  => ['it' => 'Una base solida.', 'de' => 'Eine solide Grundlage.', 'en' => 'A solid foundation.'],
        'fazit1'  => ['it' => 'Qui si perde potenziale.', 'de' => 'Hier geht Potenzial verloren.', 'en' => 'Potential is being lost here.'],
        'fazit3'  => ['it' => 'Questo sito probabilmente le fa perdere clienti.', 'de' => 'Diese Seite kostet Sie wahrscheinlich Kunden.', 'en' => 'This site is probably costing you customers.'],
        'empf'    => ['it' => '{name} le consiglia Vecom Design', 'de' => '{name} empfiehlt Ihnen Vecom Design', 'en' => '{name} recommends Vecom Design to you'],
        'empf_text' => ['it' => 'Dice cosa le serve e conosce il prezzo prima. Poi segue ogni passo, finché il nuovo sito è online.', 'de' => 'Sie sagen, was Sie brauchen, und kennen den Preis vorher. Danach begleiten wir jeden Schritt, bis die neue Seite online ist.', 'en' => 'You say what you need and know the price upfront. Then we guide every step until the new site is live.'],
        'knopf'   => ['it' => 'Richiesta gratuita', 'de' => 'Kostenlos anfragen', 'en' => 'Free enquiry'],
        'klein'   => ['it' => 'Verifica automatica della pagina iniziale, non una perizia completa.', 'de' => 'Automatische Kurzprüfung der Startseite, kein vollständiges Gutachten.', 'en' => 'Automatic quick check of the home page, not a full audit.'],
        'weg'     => ['it' => 'Questo rapporto non è più disponibile.', 'de' => 'Dieser Bericht ist nicht mehr verfügbar.', 'en' => 'This report is no longer available.'],
    ];

    /* Gesprächsleitfaden für Partner: kurz, auf dem Handy lesbar. */
    public const PARTNER_LEITFADEN = [
        ['titel' => ['it' => 'Come iniziare', 'de' => 'So fangen Sie an', 'en' => 'How to start'],
         'text' => ['it' => "Non vendere: chieda. «Come ti trovano i clienti nuovi?» apre più porte di qualsiasi presentazione.\nSe il sito c’è: faccia la verifica veloce insieme e guardi il risultato sul telefono.\nSe non c’è: «Hai mai pensato a un sito? Conosco chi lo fa con il prezzo chiaro prima.»",
                    'de' => "Nicht verkaufen, sondern fragen. „Wie finden dich neue Kunden?“ öffnet mehr Türen als jede Präsentation.\nGibt es eine Website: den Schnellcheck gemeinsam machen und das Ergebnis auf dem Handy zeigen.\nGibt es keine: „Hast du mal über eine Website nachgedacht? Ich kenne jemanden, bei dem man den Preis vorher weiß.“",
                    'en' => "Don’t sell — ask. “How do new customers find you?” opens more doors than any pitch.\nIf there’s a website: run the quick check together and show the result on your phone.\nIf there isn’t: “Ever thought about a website? I know someone where you know the price upfront.”"]],
        ['titel' => ['it' => 'Cinque domande utili', 'de' => 'Fünf gute Fragen', 'en' => 'Five good questions'],
         'text' => ['it' => "1. Da dove arrivano oggi i clienti nuovi?\n2. Cosa cercano su Google prima di venire da te?\n3. Cosa ti chiedono sempre al telefono? (orari, prezzi, menù…)\n4. Il sito attuale lo mostri volentieri?\n5. Cosa dovrebbe fare un visitatore: chiamare, prenotare, scrivere?",
                    'de' => "1. Woher kommen heute neue Kunden?\n2. Was suchen sie bei Google, bevor sie zu dir kommen?\n3. Was fragen sie dich am Telefon immer wieder? (Öffnungszeiten, Preise, Speisekarte …)\n4. Zeigst du deine jetzige Seite gern her?\n5. Was soll ein Besucher tun: anrufen, buchen, schreiben?",
                    'en' => "1. Where do new customers come from today?\n2. What do they search on Google before coming to you?\n3. What do they always ask on the phone? (hours, prices, menu…)\n4. Are you happy to show your current site?\n5. What should a visitor do: call, book, write?"]],
        ['titel' => ['it' => 'Il prezzo', 'de' => 'Der Preis', 'en' => 'The price'],
         'text' => ['it' => "Non dica cifre a memoria. Il questionario sul suo link mostra subito un prezzo indicativo; l’offerta precisa arriva entro un giorno lavorativo. Prima di un accordo scritto non si paga nulla.",
                    'de' => "Keine Zahlen aus dem Kopf nennen. Der Fragebogen hinter Ihrem Link zeigt sofort einen Richtpreis; das genaue Angebot kommt innerhalb eines Werktags. Vor einer schriftlichen Einigung wird nichts bezahlt.",
                    'en' => "Don’t quote figures from memory. The questionnaire behind your link shows a guide price right away; the exact quote follows within one working day. Nothing is paid before a written agreement."]],
        ['titel' => ['it' => 'Obiezioni frequenti', 'de' => 'Häufige Einwände', 'en' => 'Common objections'],
         'text' => ['it' => "«Costa troppo.» → Il prezzo lo sai prima e decidi tu. Guardarlo non costa niente.\n«Ho già Facebook.» → Facebook è in affitto: le regole le fa un altro. Il sito è tuo, e Google lo trova.\n«Non ho tempo.» → Rispondi a qualche domanda, il resto lo fanno loro. Vedi ogni passo sul telefono.\n«Ho già qualcuno.» → Perfetto. Se un giorno vuoi un confronto, il link resta valido.\n«Più avanti.» → Nessun problema: ti mando il link, lo apri quando vuoi.",
                    'de' => "„Zu teuer.“ → Den Preis kennst du vorher, und du entscheidest. Anschauen kostet nichts.\n„Ich hab doch Facebook.“ → Facebook ist gemietet: Die Regeln macht ein anderer. Die Website gehört dir, und Google findet sie.\n„Keine Zeit.“ → Ein paar Fragen beantworten, den Rest machen sie. Du siehst jeden Schritt auf dem Handy.\n„Ich hab schon jemanden.“ → Prima. Wenn du mal vergleichen willst, der Link bleibt gültig.\n„Später.“ → Kein Problem: Ich schick dir den Link, du öffnest ihn, wann du willst.",
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
        'vorlagen' => ['gold' => ['it' => 'Oro scuro', 'de' => 'Gold dunkel', 'en' => 'Dark gold'], 'hell' => ['it' => 'Chiaro elegante', 'de' => 'Hell elegant', 'en' => 'Light elegant'],
                       'mediterran' => ['it' => 'Mediterraneo', 'de' => 'Mediterran', 'en' => 'Mediterranean'], 'minimal' => ['it' => 'Minimal', 'de' => 'Minimal', 'en' => 'Minimal']],
        'akzente' => ['gold' => ['it' => 'Oro', 'de' => 'Gold', 'en' => 'Gold'], 'terrakotta' => ['it' => 'Terracotta', 'de' => 'Terrakotta', 'en' => 'Terracotta'],
                      'meer' => ['it' => 'Mare', 'de' => 'Meer', 'en' => 'Sea'], 'salbei' => ['it' => 'Salvia', 'de' => 'Salbei', 'en' => 'Sage'],
                      'rose' => ['it' => 'Rosa', 'de' => 'Rosé', 'en' => 'Rose'], 'graphit' => ['it' => 'Grafite', 'de' => 'Graphit', 'en' => 'Graphite']],
        'bilder' => ['' => ['it' => 'Nessuna immagine', 'de' => 'Kein Bild', 'en' => 'No image'], 'eigen' => ['it' => 'La mia foto', 'de' => 'Mein Foto', 'en' => 'My photo'],
                     'gastro' => ['it' => 'Ristorante', 'de' => 'Restaurant', 'en' => 'Restaurant'], 'hotel' => ['it' => 'Hotel & casa vacanze', 'de' => 'Hotel & Ferienhaus', 'en' => 'Hotel & holiday home'],
                     'friseur' => ['it' => 'Parrucchiere & beauty', 'de' => 'Friseur & Beauty', 'en' => 'Hair & beauty'], 'auto' => ['it' => 'Auto & officina', 'de' => 'Auto & Werkstatt', 'en' => 'Cars & garage'],
                     'kueche' => ['it' => 'Artigianato & cucine', 'de' => 'Handwerk & Küchen', 'en' => 'Craft & kitchens'], 'wein' => ['it' => 'Vino & prodotti', 'de' => 'Wein & Produkte', 'en' => 'Wine & products'],
                     'mode' => ['it' => 'Moda & negozio', 'de' => 'Mode & Laden', 'en' => 'Fashion & shop'], 'schmuck' => ['it' => 'Gioielli', 'de' => 'Schmuck', 'en' => 'Jewellery'],
                     'transport' => ['it' => 'Trasporti', 'de' => 'Transport', 'en' => 'Transport']],
        'arbeiten_titel' => ['it' => 'Alcuni nostri lavori', 'de' => 'Einige unserer Arbeiten', 'en' => 'Some of our work'],
        'arbeiten' => [
            'cavaleri' => ['name' => 'Cavaleri Srl', 'it' => 'Trasporti e logistica · Caltanissetta, dal 1974', 'de' => 'Transport & Logistik · Caltanissetta, seit 1974', 'en' => 'Transport & logistics · Caltanissetta, since 1974'],
            'jonika' => ['name' => 'Jonika Venturis', 'it' => 'Autrice · libri per bambini', 'de' => 'Autorin · Kinderbücher', 'en' => 'Author · children’s books'],
            'mensaena' => ['name' => 'Mensaena', 'it' => 'Piattaforma senza scopo di lucro · aiuto tra vicini', 'de' => 'Gemeinnützige Plattform · Nachbarschaftshilfe', 'en' => 'Non-profit platform · neighbourhood help'],
        ],
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
}
