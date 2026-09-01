<?php
declare(strict_types=1);

/**
 * Alle Texte, die an Kunden gehen — in Italienisch, Deutsch und Englisch.
 * An einer Stelle, damit sich Formulierungen ändern lassen, ohne Code zu
 * durchsuchen. Platzhalter in geschweiften Klammern werden ersetzt.
 */
final class Texte
{
    public const FRAGEBOGEN = [
        'unternehmen' => [
            'it' => 'La tua azienda', 'de' => 'Dein Unternehmen', 'en' => 'Your business',
            'felder' => [
                'firmenname' => ['it' => 'Nome dell’azienda', 'de' => 'Firmenname', 'en' => 'Company name', 'art' => 'text'],
                'branche'    => ['it' => 'Settore', 'de' => 'Branche', 'en' => 'Industry', 'art' => 'text'],
                'beschreibung' => ['it' => 'Cosa fate, in poche frasi', 'de' => 'Was ihr macht, in wenigen Sätzen', 'en' => 'What you do, in a few sentences', 'art' => 'lang'],
                'zielgruppe' => ['it' => 'Chi sono i vostri clienti', 'de' => 'Wer sind eure Kunden', 'en' => 'Who are your customers', 'art' => 'lang'],
                'standort'   => ['it' => 'Sede e zona servita', 'de' => 'Standort und Einzugsgebiet', 'en' => 'Location and area served', 'art' => 'text'],
                'kontakt'    => ['it' => 'Telefono, e-mail, orari', 'de' => 'Telefon, E-Mail, Öffnungszeiten', 'en' => 'Phone, email, opening hours', 'art' => 'lang'],
            ],
        ],
        'website' => [
            'it' => 'Il sito', 'de' => 'Die Website', 'en' => 'The website',
            'felder' => [
                'seiten'     => ['it' => 'Quali pagine servono', 'de' => 'Welche Seiten sollen es sein', 'en' => 'Which pages do you need', 'art' => 'lang'],
                'funktionen' => ['it' => 'Funzioni desiderate', 'de' => 'Gewünschte Funktionen', 'en' => 'Features you want', 'art' => 'lang'],
                'ziel'       => ['it' => 'Cosa deve ottenere il sito', 'de' => 'Was die Website erreichen soll', 'en' => 'What the site should achieve', 'art' => 'lang'],
                'inhalte'    => ['it' => 'Quali contenuti avete già', 'de' => 'Welche Inhalte gibt es schon', 'en' => 'What content do you already have', 'art' => 'lang'],
                'beispiele'  => ['it' => 'Siti che vi piacciono', 'de' => 'Websites, die euch gefallen', 'en' => 'Websites you like', 'art' => 'lang'],
            ],
        ],
        'design' => [
            'it' => 'Aspetto', 'de' => 'Gestaltung', 'en' => 'Design',
            'felder' => [
                'farben'      => ['it' => 'Colori preferiti', 'de' => 'Bevorzugte Farben', 'en' => 'Preferred colours', 'art' => 'text'],
                'stil'        => ['it' => 'Stile desiderato', 'de' => 'Gewünschter Stil', 'en' => 'Style you want', 'art' => 'text'],
                'schriften'   => ['it' => 'Caratteri, se avete preferenze', 'de' => 'Schriftarten, falls ihr Wünsche habt', 'en' => 'Fonts, if you have preferences', 'art' => 'text'],
                'logo'        => ['it' => 'Avete già un logo?', 'de' => 'Gibt es schon ein Logo?', 'en' => 'Do you already have a logo?', 'art' => 'text'],
                'vorbilder'   => ['it' => 'Esempi che vi ispirano', 'de' => 'Beispiele, die euch gefallen', 'en' => 'Examples that inspire you', 'art' => 'lang'],
            ],
        ],
        'inhalte' => [
            'it' => 'Materiale', 'de' => 'Material', 'en' => 'Material',
            'felder' => [
                'texte'  => ['it' => 'Testi: già pronti o da scrivere?', 'de' => 'Texte: schon fertig oder zu schreiben?', 'en' => 'Copy: ready or to be written?', 'art' => 'lang'],
                'bilder' => ['it' => 'Foto disponibili', 'de' => 'Vorhandene Bilder', 'en' => 'Available photos', 'art' => 'lang'],
                'videos' => ['it' => 'Video disponibili', 'de' => 'Vorhandene Videos', 'en' => 'Available videos', 'art' => 'text'],
                'social' => ['it' => 'Profili social', 'de' => 'Social-Media-Profile', 'en' => 'Social media profiles', 'art' => 'lang'],
                'sonstiges' => ['it' => 'Altro che dovremmo sapere', 'de' => 'Sonst noch etwas, das wir wissen sollten', 'en' => 'Anything else we should know', 'art' => 'lang'],
            ],
        ],
    ];

    public const SEITE = [
        'titel'      => ['it' => 'Il tuo progetto', 'de' => 'Dein Projekt', 'en' => 'Your project'],
        'lead'       => ['it' => 'Più cose ci racconti, meno domande dovremo farti dopo. Puoi salvare e continuare più tardi.',
                         'de' => 'Je mehr du uns erzählst, desto weniger müssen wir später nachfragen. Du kannst zwischendurch speichern und später weitermachen.',
                         'en' => 'The more you tell us, the fewer questions we’ll need later. You can save and come back any time.'],
        'speichern'  => ['it' => 'Salva e continua dopo', 'de' => 'Zwischenspeichern', 'en' => 'Save for later'],
        'absenden'   => ['it' => 'Invia definitivamente', 'de' => 'Endgültig absenden', 'en' => 'Send'],
        'gespeichert'=> ['it' => 'Salvato. Puoi tornare quando vuoi con lo stesso link.',
                         'de' => 'Gespeichert. Du kannst mit demselben Link jederzeit zurückkommen.',
                         'en' => 'Saved. Come back any time with the same link.'],
        'danke'      => ['it' => 'Grazie! Abbiamo ricevuto tutto e ci mettiamo al lavoro.',
                         'de' => 'Danke! Wir haben alles bekommen und legen los.',
                         'en' => 'Thank you! We have everything and we’re getting started.'],
        'weg'        => ['it' => 'Questo link non è più valido.', 'de' => 'Dieser Link gilt nicht mehr.', 'en' => 'This link is no longer valid.'],
        'schon'      => ['it' => 'Hai già inviato le informazioni. Grazie!', 'de' => 'Du hast die Angaben schon abgeschickt. Danke!', 'en' => 'You have already sent your answers. Thank you!'],
        'pflicht'    => ['it' => 'Compila almeno il nome dell’azienda.', 'de' => 'Bitte trag mindestens den Firmennamen ein.', 'en' => 'Please enter at least the company name.'],
        'panne'      => ['it' => 'Qualcosa non ha funzionato. Riprova tra poco — oppure scrivici e ce ne occupiamo noi.',
                         'de' => 'Da hat etwas nicht geklappt. Versuch es gleich noch einmal — oder schreib uns, dann kümmern wir uns.',
                         'en' => 'Something went wrong. Please try again shortly — or write to us and we’ll sort it out.'],
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
        'gesendet'    => ['it' => 'Messaggio inviato. Rispondiamo il prima possibile.',
                          'de' => 'Nachricht ist raus. Wir melden uns so schnell wie möglich.',
                          'en' => 'Message sent. We’ll get back to you as soon as we can.'],
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
        'vonUns'      => ['it' => 'da noi', 'de' => 'von uns', 'en' => 'from us'],
        'vonDir'      => ['it' => 'da te', 'de' => 'von dir', 'en' => 'from you'],
        'leer'        => ['it' => 'Scrivi qualcosa prima di inviare.', 'de' => 'Bitte schreib etwas, bevor du absendest.', 'en' => 'Please write something first.'],
        'belege'      => ['it' => 'Ricevute', 'de' => 'Belege', 'en' => 'Receipts'],
        'freigabe'    => ['it' => 'Come ti sembra?', 'de' => 'Wie findest du es?', 'en' => 'What do you think?'],
        'freigabeText'=> ['it' => 'Se il sito va bene così, dallo pure per buono — poi lo pubblichiamo. Se qualcosa non va, scrivicelo: lo sistemiamo.',
                          'de' => 'Wenn die Seite so passt, gib sie frei — dann veröffentlichen wir. Wenn etwas nicht stimmt, schreib es uns: wir ändern es.',
                          'en' => 'If the site is right, approve it — then we publish. If something is off, tell us: we’ll change it.'],
        'freigeben'   => ['it' => 'Va bene così — pubblicate', 'de' => 'Passt so — veröffentlichen', 'en' => 'Looks good — publish it'],
        'aendern'     => ['it' => 'Vorrei delle modifiche', 'de' => 'Ich möchte Änderungen', 'en' => 'I’d like changes'],
        'freigegeben' => ['it' => 'Grazie! Ci mettiamo subito a pubblicare.',
                          'de' => 'Danke! Wir kümmern uns gleich um die Veröffentlichung.',
                          'en' => 'Thank you! We’ll get it published right away.'],
        'aenderungOk' => ['it' => 'Ricevuto. Ci mettiamo mano.', 'de' => 'Angekommen. Wir machen uns dran.', 'en' => 'Got it. We’re on it.'],
        'aendernWie'  => ['it' => 'Scrivi cosa cambiare prima di inviare.',
                          'de' => 'Schreib bitte dazu, was geändert werden soll.',
                          'en' => 'Please write what should change.'],
        'keineBelege' => ['it' => 'Ancora nessuna ricevuta.', 'de' => 'Noch keine Belege.', 'en' => 'No receipts yet.'],
    ];

    /** Die Stufen des Projekts, wie der Kunde sie sieht. */
    public const PROJEKT_STAND = [
        'bestellung_eingegangen' => ['it' => 'Ordine ricevuto', 'de' => 'Bestellung eingegangen', 'en' => 'Order received'],
        'zahlung_bestaetigt'     => ['it' => 'Pagamento confermato', 'de' => 'Zahlung bestätigt', 'en' => 'Payment confirmed'],
        'onboarding'             => ['it' => 'Raccogliamo le informazioni', 'de' => 'Wir sammeln die Angaben', 'en' => 'Gathering information'],
        'informationen_erhalten' => ['it' => 'Informazioni ricevute', 'de' => 'Informationen erhalten', 'en' => 'Information received'],
        'design'                 => ['it' => 'Progettazione', 'de' => 'Gestaltung', 'en' => 'Design'],
        'entwicklung'            => ['it' => 'Realizzazione', 'de' => 'Umsetzung', 'en' => 'Development'],
        'vorschau'               => ['it' => 'Anteprima pronta', 'de' => 'Vorschau steht', 'en' => 'Preview ready'],
        'kundenfeedback'         => ['it' => 'Aspettiamo il tuo parere', 'de' => 'Wir warten auf deine Rückmeldung', 'en' => 'Waiting for your feedback'],
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
                         'en' => 'Bookmark it or keep the email with the link. If it gets lost, write to me and a new one comes.'],
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
        /* Sofort nach dem Absenden. Zwei Aufgaben: der Kunde weiss, dass es
           angekommen ist — und er hat schwarz auf weiss, dass ihn nichts
           bindet. Beides fehlte bisher ganz. */
        'anfrage_eingegangen' => [
            'it' => ['Abbiamo ricevuto la tua richiesta',
                "Ciao {name},\n\ngrazie per la tua richiesta{paketsatz}. È arrivata e la sto leggendo con calma.\n\nTi rispondo entro un giorno lavorativo con una prima indicazione concreta — non con un «dipende».\n\nUna cosa importante: la richiesta è gratuita e senza alcun impegno. Un incarico nasce soltanto quando ci accordiamo per iscritto e firmiamo il contratto. Fino ad allora non ti costa nulla e non ti obbliga a niente.\n\n--------------------------------------------------\nCOME FUNZIONA — tutto passa da un link\n--------------------------------------------------\n\n{link}\n\nSu questa pagina si svolge tutto il nostro scambio. Nessun account, nessuna password, nessuna app: il link è il tuo accesso. Funziona dal telefono come dal computer.\n\n1) Apri il link e mettilo da parte\n   Salvalo tra i preferiti oppure tieni questa e-mail. Se lo perdi, scrivimi e te ne mando uno nuovo.\n\n2) Scrivimi lì, non per e-mail\n   Così resta tutto in un posto solo e niente si perde in una casella di posta. Ogni messaggio mi arriva subito.\n\n3) Carica i tuoi documenti\n   Logo, foto, testi, il sito vecchio — quello che dovrei vedere. Scegli il file e invia. Fino a {maxdatei} per file.\n\n4) E poi?\n   Ti mando una proposta a prezzo fisso. Se ti convince, ricevi il link per il pagamento. Da lì la stessa pagina cresce con noi: questionario, bozza, approvazione, messa online.\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Deine Anfrage ist angekommen',
                "Hallo {name},\n\ndanke für deine Anfrage{paketsatz}. Sie ist da und ich lese sie in Ruhe durch.\n\nDu hörst innerhalb eines Werktags von mir, mit einer ersten konkreten Einschätzung — nicht mit einem «kommt darauf an».\n\nEines vorweg: Die Anfrage ist kostenlos und völlig unverbindlich. Ein Auftrag entsteht erst, wenn wir uns schriftlich einig sind und den Vertrag schließen. Bis dahin kostet dich das nichts und verpflichtet dich zu nichts.\n\n--------------------------------------------------\nSO LÄUFT ES — alles über einen Link\n--------------------------------------------------\n\n{link}\n\nAuf dieser Seite läuft unser ganzer Austausch. Kein Konto, kein Passwort, keine App: Der Link ist dein Zugang. Er funktioniert auf dem Handy genauso wie am Rechner.\n\n1) Link öffnen und ablegen\n   Speichere ihn als Lesezeichen oder behalte diese E-Mail. Wenn du ihn verlierst, schreib mir — dann kommt ein neuer.\n\n2) Schreib mir dort, nicht per E-Mail\n   So steht alles an einer Stelle und nichts geht im Postfach unter. Jede Nachricht erreicht mich sofort.\n\n3) Lade deine Unterlagen hoch\n   Logo, Fotos, Texte, die alte Seite — was ich sehen sollte. Datei auswählen und senden. Bis {maxdatei} je Datei.\n\n4) Und dann?\n   Ich schicke dir einen Vorschlag zum Festpreis. Passt er, bekommst du den Zahlungslink. Ab da wächst dieselbe Seite mit: Fragebogen, Entwurf, Freigabe, Veröffentlichung.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your enquiry has arrived',
                "Hello {name},\n\nthank you for your enquiry{paketsatz}. It has arrived and I am reading it properly.\n\nYou will hear from me within one working day, with a first concrete assessment — not with an «it depends».\n\nOne thing up front: the enquiry is free and entirely without obligation. A project only comes into being once we agree in writing and sign the contract. Until then it costs you nothing and commits you to nothing.\n\n--------------------------------------------------\nHOW IT WORKS — everything runs through one link\n--------------------------------------------------\n\n{link}\n\nThat page carries our whole exchange. No account, no password, no app: the link is your way in. It works on a phone just as well as on a computer.\n\n1) Open the link and keep it\n   Bookmark it or keep this email. If you lose it, write to me and a new one comes.\n\n2) Write to me there, not by email\n   That way everything stays in one place and nothing gets buried in an inbox. Every message reaches me at once.\n\n3) Upload your material\n   Logo, photos, text, the old site — whatever I should see. Pick the file and send. Up to {maxdatei} per file.\n\n4) And then?\n   I send you a proposal at a fixed price. If it suits you, you get the payment link. From there the same page grows with us: questionnaire, draft, approval, going live.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        /* Der Zahlungslink, wenn der Kunde zugesagt hat. */
        'zahlungslink' => [
            'it' => ['Il link per il pagamento — {paket}',
                "Ciao {name},\n\ncome concordato, ecco il link per {was} di {betrag}:\n\n{link}\n\nIl pagamento avviene tramite un fornitore certificato; i dati della carta non passano da me. Appena arriva ti scrivo e partiamo.\n\nSe qualcosa non torna, rispondi a questa e-mail prima di pagare.\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Dein Zahlungslink — {paket}',
                "Hallo {name},\n\nwie besprochen hier der Link für {was} über {betrag}:\n\n{link}\n\nBezahlt wird über einen geprüften Anbieter; deine Kartendaten sehe ich nicht. Sobald die Zahlung da ist, melde ich mich und wir legen los.\n\nWenn etwas nicht stimmt, antworte einfach auf diese E-Mail, bevor du zahlst.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your payment link — {paket}',
                "Hello {name},\n\nas agreed, here is the link for {was} of {betrag}:\n\n{link}\n\nPayment runs through a certified provider; I never see your card details. As soon as it arrives I will write and we start.\n\nIf anything looks wrong, just reply to this email before paying.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        'zahlung_ok' => [
            'it' => ['Pagamento ricevuto — {paket}',
                "Ciao {name},\n\nabbiamo ricevuto il tuo acconto di {betrag}. Grazie!\n\nOra iniziamo: il prossimo passo è raccontarci il tuo progetto.\nApri questo link e compila con calma — puoi salvare e continuare più tardi:\n\n{link}\n\nA presto\nUwe Vetter · Vecom Design"],
            'de' => ['Zahlung erhalten — {paket}',
                "Hallo {name},\n\ndeine Anzahlung über {betrag} ist angekommen. Danke!\n\nJetzt geht es los: Der nächste Schritt ist, uns dein Projekt zu beschreiben.\nÖffne diesen Link und fülle in Ruhe aus — du kannst zwischendurch speichern:\n\n{link}\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Payment received — {paket}',
                "Hello {name},\n\nyour deposit of {betrag} has arrived. Thank you!\n\nNext step: tell us about your project.\nOpen this link and take your time — you can save and come back:\n\n{link}\n\nBest regards\nUwe Vetter · Vecom Design"],
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
            'it' => ['La tua anteprima è pronta — {paket}',
                "Ciao {name},\n\nl’anteprima del tuo sito è pronta. Guardala con calma:\n\n{link}\n\nDicci cosa ne pensi — quello che non va lo sistemiamo. Puoi rispondere a questa e-mail o scrivere direttamente dalla pagina.\n\nUwe Vetter · Vecom Design"],
            'de' => ['Deine Vorschau steht bereit — {paket}',
                "Hallo {name},\n\ndie Vorschau deiner Website steht. Schau sie dir in Ruhe an:\n\n{link}\n\nSag uns, was du denkst — was nicht passt, ändern wir. Du kannst auf diese E-Mail antworten oder direkt auf der Seite schreiben.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your preview is ready — {paket}',
                "Hello {name},\n\nthe preview of your site is ready. Take your time with it:\n\n{link}\n\nTell us what you think — whatever doesn’t fit, we’ll change. Reply to this email or write from the page itself.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        'online' => [
            'it' => ['Il tuo sito è online — {paket}',
                "Ciao {name},\n\nil sito è online:\n{link}\n\nGrazie per la fiducia. Se serve qualcosa, siamo qui.\n\nUwe Vetter · Vecom Design"],
            'de' => ['Deine Website ist online — {paket}',
                "Hallo {name},\n\ndie Website ist online:\n{link}\n\nDanke für dein Vertrauen. Wenn etwas ist, melde dich einfach.\n\nHerzliche Grüße\nUwe Vetter · Vecom Design"],
            'en' => ['Your site is live — {paket}',
                "Hello {name},\n\nthe site is live:\n{link}\n\nThank you for your trust. If anything comes up, just get in touch.\n\nBest regards\nUwe Vetter · Vecom Design"],
        ],
        'nachricht' => [
            'it' => ['Un messaggio sul tuo progetto',
                "Ciao {name},\n\nti abbiamo scritto sul tuo progetto:\n\n{text}\n\nPuoi rispondere qui:\n{link}\n\nUwe Vetter · Vecom Design"],
            'de' => ['Eine Nachricht zu deinem Projekt',
                "Hallo {name},\n\nwir haben dir zu deinem Projekt geschrieben:\n\n{text}\n\nAntworten kannst du hier:\n{link}\n\nUwe Vetter · Vecom Design"],
            'en' => ['A message about your project',
                "Hello {name},\n\nwe’ve written to you about your project:\n\n{text}\n\nYou can reply here:\n{link}\n\nUwe Vetter · Vecom Design"],
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
