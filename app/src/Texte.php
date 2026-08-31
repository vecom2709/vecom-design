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
    public const MAILS = [
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
