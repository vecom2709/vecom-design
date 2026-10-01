<?php
declare(strict_types=1);
require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Texte.php';

/**
 * Der ausführliche, verständliche Website-Bericht (28.09.2026, Uwe: Ja zu
 * A1–A10: „ausführlicher und verständlicher, so dass ein Bedarf ausgelöst
 * wird“).
 *
 * GRUNDSATZ
 * Der Bericht zeigt dem Inhaber seinen EIGENEN Fall -- mit seinen gemessenen
 * Werten, seinem Titel bei Google, seiner Ladezeit, seinen Zahlen im Rechner.
 * Keine erfundenen Statistiken, keine Angstsätze, keine Versprechen über
 * Google-Plätze oder Umsatz. Bedarf entsteht, weil er sieht, was ist.
 *
 * Teile: Note + ein Satz (A1), Handyfoto mit Markierungen (A2, nur mit Audit
 * vom PC), Google/WhatsApp vorher-nachher (A3), Ladezeit zum Miterleben (A4),
 * Vergleich mit der Umgebung (A5, nur mit genug Audits derselben Branche),
 * Rechner mit seinen Zahlen (A6), Maßnahmenplan mit Preisen (A7, nur im
 * persönlichen Bereich), Alltagssprache mit Symbolen (A8), PDF (A9),
 * zwölf Punkte (A10, PartnerCheck::mehrPunkte).
 */
final class WebBericht
{
    public const REIHE = ['tempo', 'handy', 'sicher', 'google', 'telefon', 'adresse', 'zeiten', 'aktuell', 'teilen', 'bilder', 'alt', 'rechtlich'];
    /** Gewicht je Punkt für die Note. Was ein Besucher sofort merkt, wiegt mehr. */
    public const GEWICHT = ['tempo' => 3, 'handy' => 3, 'sicher' => 2, 'google' => 2, 'telefon' => 2, 'adresse' => 1, 'zeiten' => 1,
                            'aktuell' => 1, 'teilen' => 1, 'bilder' => 1, 'alt' => 1, 'rechtlich' => 1];
    public const WERT = ['gut' => 1.0, 'hinweis' => 0.5, 'schlecht' => 0.0];
    public const TEMPO_GUT_S = 1.5;   // dieselbe Grenze wie PartnerCheck (1500 ms)
    public const FRIST_TAGE = 180;

    /** Kurzform für den einen Satz oben (A1): Was fällt zuerst auf? */
    public const KURZ = [
        'tempo'     => ['schlecht' => ['it' => 'il sito è molto lento', 'de' => 'die Seite lädt sehr langsam', 'en' => 'the site is very slow'],
                        'hinweis'  => ['it' => 'il sito si carica lentamente', 'de' => 'die Seite lädt spürbar langsam', 'en' => 'the site loads noticeably slowly']],
        'handy'     => ['schlecht' => ['it' => 'sul telefono si legge a fatica', 'de' => 'auf dem Handy ist sie kaum lesbar', 'en' => 'it is hard to read on a phone']],
        'sicher'    => ['schlecht' => ['it' => 'il browser la segnala come «Non sicuro»', 'de' => 'der Browser zeigt „Nicht sicher“', 'en' => 'the browser shows “Not secure”'],
                        'hinweis'  => ['it' => 'il certificato di sicurezza scade presto', 'de' => 'das Sicherheitszertifikat läuft bald ab', 'en' => 'the security certificate expires soon']],
        'google'    => ['schlecht' => ['it' => 'su Google appare senza un nome chiaro', 'de' => 'bei Google erscheint sie ohne klaren Namen', 'en' => 'on Google it appears without a clear name'],
                        'hinweis'  => ['it' => 'su Google manca la descrizione', 'de' => 'bei Google fehlt die Beschreibung', 'en' => 'on Google the description is missing']],
        'telefon'   => ['schlecht' => ['it' => 'sulla pagina iniziale manca il telefono', 'de' => 'auf der Startseite fehlt die Telefonnummer', 'en' => 'the home page has no phone number'],
                        'hinweis'  => ['it' => 'il numero non si tocca per chiamare', 'de' => 'die Nummer lässt sich nicht antippen', 'en' => 'the number can’t be tapped to call']],
        'adresse'   => ['hinweis'  => ['it' => 'sulla pagina iniziale non si trova l’indirizzo', 'de' => 'auf der Startseite fehlt die Adresse', 'en' => 'the home page has no address']],
        'zeiten'    => ['hinweis'  => ['it' => 'Google non legge gli orari', 'de' => 'Google kann die Öffnungszeiten nicht lesen', 'en' => 'Google can’t read the opening hours']],
        'aktuell'   => ['schlecht' => ['it' => 'sembra fermo da anni', 'de' => 'sie wirkt seit Jahren unverändert', 'en' => 'it looks unchanged for years'],
                        'hinweis'  => ['it' => 'non sembra del tutto aggiornato', 'de' => 'sie wirkt nicht ganz aktuell', 'en' => 'it doesn’t look fully up to date']],
        'teilen'    => ['hinweis'  => ['it' => 'condiviso su WhatsApp non mostra un’immagine', 'de' => 'beim Teilen in WhatsApp erscheint kein Bild', 'en' => 'shared on WhatsApp it shows no image']],
        'bilder'    => ['hinweis'  => ['it' => 'le immagini pesano più del necessario', 'de' => 'die Bilder sind schwerer als nötig', 'en' => 'images are heavier than needed']],
        'alt'       => ['schlecht' => ['it' => 'le immagini non hanno descrizioni', 'de' => 'die Bilder haben keine Beschreibungen', 'en' => 'images have no descriptions'],
                        'hinweis'  => ['it' => 'a una parte delle immagini manca la descrizione', 'de' => 'einem Teil der Bilder fehlt die Beschreibung', 'en' => 'some images lack descriptions']],
        'rechtlich' => ['schlecht' => ['it' => 'mancano i dati obbligatori', 'de' => 'die Pflichtangaben fehlen', 'en' => 'the legal details are missing'],
                        'hinweis'  => ['it' => 'i dati obbligatori sono incompleti', 'de' => 'die Pflichtangaben sind unvollständig', 'en' => 'the legal details are incomplete']],
        'erreichbar' => ['schlecht' => ['it' => 'durante la verifica il sito non si apriva', 'de' => 'die Seite ließ sich bei der Prüfung nicht öffnen', 'en' => 'the site didn’t open during the check']],
    ];

    /** A8: „Was heißt das für Sie?“ -- je Punkt ein Satz, wenn nicht alles passt. */
    public const HEISST = [
        'tempo'     => ['it' => 'Chi la cerca dal telefono aspetta davanti a una pagina bianca. Molti tornano indietro prima di vedere cosa offre.', 'de' => 'Wer Sie vom Handy aus sucht, wartet vor einer weißen Seite. Viele gehen zurück, bevor sie sehen, was Sie anbieten.', 'en' => 'Anyone looking you up on a phone waits in front of a blank page. Many go back before seeing what you offer.'],
        'handy'     => ['it' => 'Sul telefono bisogna ingrandire con le dita. È il momento in cui molti chiudono e chiamano un altro.', 'de' => 'Auf dem Handy muss man mit den Fingern vergrößern. Genau dann schließen viele die Seite und rufen woanders an.', 'en' => 'On a phone people have to pinch to zoom. That’s when many close the page and call someone else.'],
        'sicher'    => ['it' => 'Accanto al suo indirizzo compare un avviso. Chi deve lasciare dati o prenotare, spesso si ferma lì.', 'de' => 'Neben Ihrer Adresse steht eine Warnung. Wer Daten eintragen oder buchen soll, hört oft genau dort auf.', 'en' => 'A warning appears next to your address. People who should enter details or book often stop right there.'],
        'google'    => ['it' => 'Su Google il suo risultato dice poco. Accanto a risultati chiari, la gente sceglie quelli.', 'de' => 'Bei Google sagt Ihr Eintrag wenig. Neben klaren Einträgen klicken die Leute eher dort.', 'en' => 'On Google your result says little. Next to clear results, people click those instead.'],
        'telefon'   => ['it' => 'Dal telefono chiamarla richiede passaggi in più: copiare, cambiare app, digitare. Ogni passaggio fa perdere qualcuno.', 'de' => 'Sie vom Handy anzurufen kostet Umwege: abschreiben, App wechseln, tippen. Bei jedem Schritt springt jemand ab.', 'en' => 'Calling you from a phone takes detours: copy, switch app, type. Someone drops off at every step.'],
        'adresse'   => ['it' => 'Chi vuole passare da lei deve cercare l’indirizzo altrove — o non la trova.', 'de' => 'Wer vorbeikommen will, muss die Adresse woanders suchen — oder findet Sie nicht.', 'en' => 'People who want to visit have to look for the address elsewhere — or don’t find you.'],
        'zeiten'    => ['it' => 'Google non può mostrare «Aperto ora» dal suo sito. Chi cerca all’ultimo momento sceglie chi lo mostra.', 'de' => 'Google kann von Ihrer Seite kein „Jetzt geöffnet“ anzeigen. Wer spontan sucht, nimmt den, bei dem es dasteht.', 'en' => 'Google can’t show “Open now” from your site. Last-minute searchers pick whoever shows it.'],
        'aktuell'   => ['it' => 'Un anno vecchio fa pensare che anche orari e prezzi non siano più validi.', 'de' => 'Eine alte Jahreszahl lässt vermuten, dass auch Zeiten und Preise nicht mehr stimmen.', 'en' => 'An old year suggests hours and prices may no longer be right either.'],
        'teilen'    => ['it' => 'Quando un cliente la consiglia su WhatsApp, arriva solo un link grigio invece di un’anteprima che invita a toccare.', 'de' => 'Empfiehlt ein Kunde Sie per WhatsApp, kommt nur ein grauer Link an statt einer Vorschau, die zum Antippen einlädt.', 'en' => 'When a customer recommends you on WhatsApp, only a grey link arrives instead of an inviting preview.'],
        'bilder'    => ['it' => 'Il telefono scarica immagini più grandi di quanto serva: la pagina è lenta, soprattutto fuori casa.', 'de' => 'Das Handy lädt größere Bilder als nötig: Die Seite wird langsam, gerade unterwegs.', 'en' => 'Phones download bigger images than needed: the page gets slow, especially on the go.'],
        'alt'       => ['it' => 'Google non sa cosa mostrano le sue foto, quindi non le trova nella ricerca immagini.', 'de' => 'Google weiß nicht, was Ihre Fotos zeigen — und findet sie deshalb in der Bildersuche nicht.', 'en' => 'Google doesn’t know what your photos show — so it doesn’t find them in image search.'],
        'rechtlich' => ['it' => 'Mancano dati che per un’attività sono obbligatori. È una cosa piccola da sistemare, ma va sistemata.', 'de' => 'Es fehlen Angaben, die für ein Geschäft Pflicht sind. Das ist schnell behoben, sollte aber behoben werden.', 'en' => 'Details that are mandatory for a business are missing. Quick to fix, but it should be fixed.'],
        'erreichbar' => ['it' => 'Chi l’ha cercata in quel momento non ha trovato niente.', 'de' => 'Wer Sie in dem Moment gesucht hat, fand nichts.', 'en' => 'Anyone looking for you at that moment found nothing.'],
    ];
    /** A8: „Warum ist das wichtig?“ -- aufklappbar. */
    public const WARUM = [
        'tempo'     => ['it' => 'Misuriamo quanto impiega la pagina iniziale ad arrivare. Sotto 1,5 secondi è buono. Oltre, ogni secondo in più fa perdere visitatori, soprattutto da telefono.', 'de' => 'Gemessen wird, wie lange die Startseite braucht. Unter 1,5 Sekunden ist gut. Darüber kostet jede Sekunde Besucher, vor allem am Handy.', 'en' => 'We measure how long the home page takes to arrive. Under 1.5 seconds is good. Beyond that, every extra second loses visitors, especially on phones.'],
        'handy'     => ['it' => 'La maggior parte delle persone guarda un sito dal telefono. Un sito pensato per il telefono adatta testo e pulsanti allo schermo.', 'de' => 'Die meisten Menschen sehen eine Website auf dem Handy an. Eine fürs Handy eingerichtete Seite passt Text und Knöpfe an den Bildschirm an.', 'en' => 'Most people view websites on a phone. A site set up for phones fits text and buttons to the screen.'],
        'sicher'    => ['it' => 'Il collegamento cifrato (https) protegge ciò che i visitatori scrivono. Senza, i browser mostrano «Non sicuro».', 'de' => 'Die verschlüsselte Verbindung (https) schützt, was Besucher eintippen. Ohne sie zeigen Browser „Nicht sicher“.', 'en' => 'The encrypted connection (https) protects what visitors type. Without it, browsers show “Not secure”.'],
        'google'    => ['it' => 'Titolo e descrizione sono ciò che le persone leggono su Google prima di decidere se cliccare.', 'de' => 'Titel und Beschreibung sind das, was Menschen bei Google lesen, bevor sie entscheiden, ob sie klicken.', 'en' => 'Title and description are what people read on Google before deciding whether to click.'],
        'telefon'   => ['it' => 'Un numero come «link di chiamata» si tocca e chiama. È il modo più veloce per una piccola attività di ricevere una richiesta.', 'de' => 'Eine Nummer als „Anruf-Link“ wird angetippt und ruft an. Für jeden Betrieb der schnellste Weg zu einer Anfrage.', 'en' => 'A number set as a “call link” is tapped and calls. For any business, it’s the fastest route to an enquiry.'],
        'adresse'   => ['it' => 'Indirizzo e mappa aiutano chi vuole venire di persona, e aiutano Google a capire dove si trova.', 'de' => 'Adresse und Karte helfen allen, die vorbeikommen wollen — und Google, zu verstehen, wo Sie sind.', 'en' => 'Address and map help people who want to visit — and help Google understand where you are.'],
        'zeiten'    => ['it' => 'Scritti in un formato che Google capisce, gli orari possono comparire direttamente nei risultati.', 'de' => 'In einem Format hinterlegt, das Google versteht, können die Zeiten direkt in den Suchergebnissen erscheinen.', 'en' => 'Marked up in a format Google understands, opening hours can appear right in the results.'],
        'aktuell'   => ['it' => 'Cerchiamo l’anno più recente accanto al ©. È un segnale piccolo, ma i visitatori lo notano.', 'de' => 'Gesucht wird die jüngste Jahreszahl neben dem ©. Ein kleines Zeichen, aber Besucher bemerken es.', 'en' => 'We look for the latest year next to the ©. A small sign, but visitors notice it.'],
        'teilen'    => ['it' => 'Un’«immagine di anteprima» decide cosa appare quando il sito viene condiviso su WhatsApp o Facebook.', 'de' => 'Ein „Vorschaubild“ bestimmt, was erscheint, wenn die Seite in WhatsApp oder Facebook geteilt wird.', 'en' => 'A “preview image” decides what appears when the site is shared on WhatsApp or Facebook.'],
        'bilder'    => ['it' => 'Formati moderni (WebP, AVIF) e misure diverse per telefono e computer fanno pesare meno le immagini a parità di qualità.', 'de' => 'Moderne Formate (WebP, AVIF) und eigene Größen für Handy und Computer machen Bilder leichter — bei gleicher Qualität.', 'en' => 'Modern formats (WebP, AVIF) and separate sizes for phone and computer make images lighter at the same quality.'],
        'alt'       => ['it' => 'Una breve descrizione per ogni immagine aiuta Google e chi usa un lettore di schermo.', 'de' => 'Eine kurze Beschreibung je Bild hilft Google und Menschen, die sich die Seite vorlesen lassen.', 'en' => 'A short description per image helps Google and people using a screen reader.'],
        'rechtlich' => ['it' => 'In Italia la partita IVA deve comparire sul sito; in tutta Europa serve una pagina sulla privacy.', 'de' => 'In Deutschland sind Impressum und Datenschutzerklärung Pflicht; in Italien die Partita IVA und eine Privacy-Seite.', 'en' => 'Business websites need legal details (e.g. VAT number, legal notice) and a privacy page in most of Europe.'],
        'erreichbar' => ['it' => 'Può succedere per un guasto momentaneo. Se capita spesso, vale la pena guardare il server.', 'de' => 'Das kann eine kurze Störung sein. Passiert es öfter, lohnt ein Blick auf den Server.', 'en' => 'It can be a brief outage. If it happens often, the server is worth a look.'],
    ];

    public const WORTE = [
        'it' => ['note' => 'Voto del sito', 'stufe' => [85 => 'Molto buono', 65 => 'Buono, con margine', 40 => 'Da migliorare', 0 => 'Da migliorare presto'],
                 'zuerst' => 'La prima cosa che si nota: {a}.', 'zuerst2' => 'La prima cosa che si nota: {a} e {b}.', 'alles' => 'Una base solida: nessuno dei dodici punti è negativo.',
                 'punkte' => 'I dodici punti', 'heisst' => 'Cosa significa per lei', 'warum' => 'Perché è importante?', 'passt' => 'Va bene così: qui non c’è niente da fare.',
                 'google_t' => 'Come la vede Google', 'heute' => 'Oggi', 'vorschlag' => 'Con la nostra proposta', 'ohne_b' => 'Nessuna descrizione: Google mostra un pezzo di testo a caso.', 'beispiel' => 'Esempio',
                 'wa_t' => 'Quando qualcuno la condivide su WhatsApp', 'wa_ohne' => 'Solo il link, senza immagine', 'wa_mit' => 'Con immagine, nome e descrizione',
                 'tempo_t' => 'Quanto aspetta il suo cliente', 'ihre' => 'Il suo sito', 'ziel' => 'Un sito veloce', 'nochmal' => 'Guardare di nuovo', 'sek' => 's',
                 'foto_t' => 'Il suo sito sul telefono', 'foto_d' => 'Così lo vede un cliente sullo smartphone. I numeri indicano i punti da migliorare.',
                 'mark' => ['schrift' => 'Testo molto piccolo', 'tippen' => 'Pulsante difficile da toccare', 'telefon' => 'Numero che non si tocca per chiamare', 'bild' => 'Immagine senza descrizione', 'breite' => 'Il contenuto esce dallo schermo'],
                 'vgl_t' => 'Confronto con la sua zona', 'vgl_d' => 'Siti di {branche} a {ort} che abbiamo verificato con lo stesso metodo. Nomi nascosti.', 'vgl_platz' => 'Posto {platz} su {von}', 'vgl_sie' => 'Lei', 'vgl_betrieb' => 'Attività {x}',
                 'rech_t' => 'Faccia il conto con i suoi numeri', 'rech_wert' => 'Quanto le porta in media un nuovo cliente? (€)', 'rech_mehr' => 'Nuovi clienti in più al mese grazie al sito', 'rech_ergebnis' => 'In un anno: {n} clienti in più = {summe}', 'rech_hinweis' => 'Sono i suoi numeri, non una nostra promessa. Servono solo a dare un ordine di grandezza.', 'rech_knopf' => 'Calcolare', 'rech_bezahlt' => 'Un nuovo sito si ripaga dopo circa {n} clienti.',
                 'plan_t' => 'Cosa facciamo e quanto costa', 'plan_d' => 'Ogni punto con la soluzione. Prezzi dal nostro listino, aggiornati.', 'plan_gesamt' => 'Tutto insieme: nuovo sito con i contenuti attuali', 'plan_knopf' => 'Calcolare il prezzo esatto', 'plan_gespraech' => 'Lo vediamo insieme nella chiamata',
                 'pdf' => 'Scaricare il rapporto (PDF)', 'teilen' => 'Inoltrare', 'stand' => ['gut' => 'va bene', 'hinweis' => 'da migliorare', 'schlecht' => 'problema'], 'geprueft' => 'Verificato il {datum}'],
        'de' => ['note' => 'Note der Website', 'stufe' => [85 => 'Sehr gut', 65 => 'Gut, mit Luft nach oben', 40 => 'Verbesserbar', 0 => 'Dringend verbesserbar'],
                 'zuerst' => 'Das fällt zuerst auf: {a}.', 'zuerst2' => 'Das fällt zuerst auf: {a}, und {b}.', 'alles' => 'Eine solide Grundlage: Keiner der zwölf Punkte fällt negativ auf.',
                 'punkte' => 'Die zwölf Punkte', 'heisst' => 'Was heißt das für Sie?', 'warum' => 'Warum ist das wichtig?', 'passt' => 'Passt: Hier ist nichts zu tun.',
                 'google_t' => 'So sieht Google Sie', 'heute' => 'Heute', 'vorschlag' => 'Mit unserem Vorschlag', 'ohne_b' => 'Keine Beschreibung: Google zeigt irgendeinen Textschnipsel.', 'beispiel' => 'Beispiel',
                 'wa_t' => 'Wenn jemand Sie per WhatsApp weiterempfiehlt', 'wa_ohne' => 'Nur der Link, ohne Bild', 'wa_mit' => 'Mit Bild, Namen und Beschreibung',
                 'tempo_t' => 'So lange wartet Ihr Kunde', 'ihre' => 'Ihre Seite', 'ziel' => 'Eine schnelle Seite', 'nochmal' => 'Noch einmal ansehen', 'sek' => 's',
                 'foto_t' => 'Ihre Seite auf dem Handy', 'foto_d' => 'So sieht ein Kunde Ihre Seite auf dem Smartphone. Die Nummern zeigen, wo es hakt.',
                 'mark' => ['schrift' => 'Sehr kleine Schrift', 'tippen' => 'Knopf schwer zu treffen', 'telefon' => 'Nummer nicht antippbar', 'bild' => 'Bild ohne Beschreibung', 'breite' => 'Inhalt ragt über den Bildschirm'],
                 'vgl_t' => 'Vergleich mit Ihrer Umgebung', 'vgl_d' => 'Websites von {branche} in {ort}, die wir mit derselben Methode geprüft haben. Namen verdeckt.', 'vgl_platz' => 'Platz {platz} von {von}', 'vgl_sie' => 'Sie', 'vgl_betrieb' => 'Betrieb {x}',
                 'rech_t' => 'Rechnen Sie mit Ihren Zahlen', 'rech_wert' => 'Was bringt Ihnen ein neuer Kunde im Durchschnitt? (€)', 'rech_mehr' => 'Neue Kunden pro Monat zusätzlich durch die Website', 'rech_ergebnis' => 'Im Jahr: {n} Kunden mehr = {summe}', 'rech_hinweis' => 'Das sind Ihre Zahlen, kein Versprechen von uns. Sie dienen nur zur Einordnung.', 'rech_knopf' => 'Rechnen', 'rech_bezahlt' => 'Eine neue Website hat sich nach etwa {n} Kunden bezahlt gemacht.',
                 'plan_t' => 'Was wir tun und was es kostet', 'plan_d' => 'Jeder Punkt mit der Lösung. Preise aus unserer Preisliste, immer aktuell.', 'plan_gesamt' => 'Alles zusammen: neue Website mit Ihren bisherigen Inhalten', 'plan_knopf' => 'Genauen Preis ausrechnen', 'plan_gespraech' => 'Klären wir im Gespräch',
                 'pdf' => 'Bericht herunterladen (PDF)', 'teilen' => 'Weiterleiten', 'stand' => ['gut' => 'gut', 'hinweis' => 'verbesserbar', 'schlecht' => 'Problem'], 'geprueft' => 'Geprüft am {datum}'],
        'en' => ['note' => 'Website score', 'stufe' => [85 => 'Very good', 65 => 'Good, with room to grow', 40 => 'Could be better', 0 => 'Needs attention soon'],
                 'zuerst' => 'What stands out first: {a}.', 'zuerst2' => 'What stands out first: {a}, and {b}.', 'alles' => 'A solid foundation: none of the twelve points is negative.',
                 'punkte' => 'The twelve points', 'heisst' => 'What does this mean for you?', 'warum' => 'Why does it matter?', 'passt' => 'All good: nothing to do here.',
                 'google_t' => 'How Google shows you', 'heute' => 'Today', 'vorschlag' => 'With our proposal', 'ohne_b' => 'No description: Google shows a random snippet.', 'beispiel' => 'Example',
                 'wa_t' => 'When someone shares you on WhatsApp', 'wa_ohne' => 'Just the link, no image', 'wa_mit' => 'With image, name and description',
                 'tempo_t' => 'How long your customer waits', 'ihre' => 'Your site', 'ziel' => 'A fast site', 'nochmal' => 'Watch again', 'sek' => 's',
                 'foto_t' => 'Your site on a phone', 'foto_d' => 'This is how a customer sees your site on a smartphone. The numbers show where it snags.',
                 'mark' => ['schrift' => 'Very small text', 'tippen' => 'Button hard to tap', 'telefon' => 'Number can’t be tapped', 'bild' => 'Image without description', 'breite' => 'Content spills over the screen'],
                 'vgl_t' => 'Compared with your area', 'vgl_d' => '{branche} websites in {ort} that we checked with the same method. Names hidden.', 'vgl_platz' => 'Rank {platz} of {von}', 'vgl_sie' => 'You', 'vgl_betrieb' => 'Business {x}',
                 'rech_t' => 'Do the maths with your numbers', 'rech_wert' => 'What is a new customer worth to you on average? (€)', 'rech_mehr' => 'Extra new customers per month thanks to the website', 'rech_ergebnis' => 'Per year: {n} more customers = {summe}', 'rech_hinweis' => 'These are your numbers, not a promise from us. They are only meant to give a sense of scale.', 'rech_knopf' => 'Calculate', 'rech_bezahlt' => 'A new website pays for itself after about {n} customers.',
                 'plan_t' => 'What we do and what it costs', 'plan_d' => 'Every point with its solution. Prices from our price list, always current.', 'plan_gesamt' => 'All together: new website with your current content', 'plan_knopf' => 'Calculate the exact price', 'plan_gespraech' => 'We’ll go through it in our call',
                 'pdf' => 'Download the report (PDF)', 'teilen' => 'Forward', 'stand' => ['gut' => 'good', 'hinweis' => 'could be better', 'schlecht' => 'problem'], 'geprueft' => 'Checked on {datum}'],
    ];

    /** A7: welche Bausteine der Preisliste einen Punkt lösen (nur, was ihr Text wirklich sagt). */
    public const LOESUNG = [
        'tempo' => ['basis', 'fotos'], 'handy' => ['basis'], 'sicher' => ['basis'], 'telefon' => ['basis'], 'adresse' => ['basis'],
        'google' => ['texte'], 'alt' => ['fotos'], 'bilder' => ['fotos'], 'aktuell' => ['betreuung_basis'], 'teilen' => ['basis'],
        'zeiten' => [], 'rechtlich' => [], 'erreichbar' => ['basis'],
    ];

    /* ------------------------------ Rechnen --------------------------------- */

    /** @param list<array{was:string,stand:string}> $punkte */
    public static function note(array $punkte): int
    {
        $summe = 0.0; $max = 0.0;
        foreach ($punkte as $p) {
            if ($p['was'] === 'erreichbar') { return 0; }
            $g = self::GEWICHT[$p['was']] ?? 1;
            $summe += $g * (self::WERT[$p['stand']] ?? 0.5);
            $max += $g;
        }
        return $max > 0 ? (int) round(100 * $summe / $max) : 0;
    }

    public static function stufe(int $note, string $sp): string
    {
        foreach (self::WORTE[$sp]['stufe'] as $ab => $wort) { if ($note >= $ab) { return $wort; } }
        return '';
    }

    public static function farbe(int $note): string
    {
        return $note >= 85 ? '#3fb56b' : ($note >= 65 ? '#9ccc4a' : ($note >= 40 ? '#e0b341' : '#e5534b'));
    }

    /** A1: der eine Satz. */
    public static function satz(array $punkte, string $sp): string
    {
        $W = self::WORTE[$sp];
        $kand = [];
        foreach ($punkte as $p) {
            $k = self::KURZ[$p['was']][$p['stand']][$sp] ?? null;
            if ($k === null) { continue; }
            $kand[] = [(self::GEWICHT[$p['was']] ?? 3) * (1 - (self::WERT[$p['stand']] ?? 0)), $k];
        }
        if (!$kand) { return $W['alles']; }
        usort($kand, static fn($a, $b) => $b[0] <=> $a[0]);
        $a = self::gross($kand[0][1]);
        return isset($kand[1]) ? strtr($W['zuerst2'], ['{a}' => $a, '{b}' => $kand[1][1]]) : strtr($W['zuerst'], ['{a}' => $a]);
    }

    private static function gross(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }

    /** Satz zu einem Punkt (mit {wert} und genauerer Fassung „k“). */
    public static function punktSatz(array $p, string $sp): string
    {
        $K = Texte::PARTNER_CHECK['punkte'][$p['was']] ?? null;
        if (!$K) { return ''; }
        $k = (string) ($p['k'] ?? '');
        if ($k === '' && $p['was'] === 'aktuell' && $p['stand'] === 'hinweis' && (string) ($p['wert'] ?? '') === '') { $k = 'hinweis_leer'; }
        return strtr(Texte::h($K[$k !== '' ? $k : $p['stand']] ?? $K[$p['stand']] ?? [], $sp), ['{wert}' => (string) ($p['wert'] ?? '')]);
    }

    /** Punkte in fester Reihenfolge: zuerst, was nicht passt. */
    public static function sortiert(array $punkte): array
    {
        $rang = array_flip(self::REIHE);
        usort($punkte, static function ($a, $b) use ($rang) {
            $wa = self::WERT[$a['stand']] ?? 0.5; $wb = self::WERT[$b['stand']] ?? 0.5;
            return $wa <=> $wb ?: (($rang[$a['was']] ?? 99) <=> ($rang[$b['was']] ?? 99));
        });
        return $punkte;
    }

    /** Ladezeit in Sekunden aus dem Kurz-Check (A4), oder null. */
    public static function sekunden(array $kc): ?float
    {
        $ms = (int) ($kc['meta']['ms'] ?? 0);
        if ($ms > 0) { return round($ms / 1000, 1); }
        foreach ($kc['punkte'] ?? [] as $p) {
            if ($p['was'] === 'tempo' && preg_match('~([\d]+)[,.](\d)~', (string) ($p['wert'] ?? ''), $m)) { return (float) ($m[1] . '.' . $m[2]); }
        }
        return null;
    }

    /* ------------------------------ A3 Google / WhatsApp ------------------- */

    /** @return array{jetzt:array{titel:string,url:string,beschreibung:string},vorschlag:array{titel:string,url:string,beschreibung:string},og:bool} */
    public static function google(array $kc, string $sp, ?array $firma = null): array
    {
        require_once __DIR__ . '/AkquiseKurz.php';
        $host = (string) ($kc['host'] ?? '');
        $m = (array) ($kc['meta'] ?? []);
        $titel = trim((string) ($m['titel'] ?? ''));
        $name = trim((string) ($firma['name'] ?? '')) ?: (trim((string) ($m['name'] ?? '')) ?: AkquiseKurz::nameAusHost($host));
        $ort = trim((string) ($firma['stadt'] ?? ''));
        $branche = '';
        if (!empty($firma['branche'])) {
            try { require_once __DIR__ . '/Akquise.php'; $branche = Akquise::branchenName((string) $firma['branche'], $sp); if ($branche === '—') { $branche = ''; } } catch (Throwable $e) { }
        }
        $url = preg_replace('~^www\.~', '', $host) . ' › ';
        $vTitel = $name . ($branche !== '' ? ' · ' . $branche : '') . ($ort !== '' ? ' ' . ['it' => 'a', 'de' => 'in', 'en' => 'in'][$sp] . ' ' . $ort : '');
        if ($branche === '' && $ort === '' && $titel !== '' && mb_strlen($titel) <= 60 && mb_stripos($titel, $name) !== false) { $vTitel = $titel; }
        $vText = [
            'it' => ($branche !== '' || $ort !== '' ? $name . ': ' . trim($branche . ($ort !== '' ? ' a ' . $ort : '')) . '. ' : '') . 'Orari, indirizzo, foto e contatti a colpo d’occhio. Chiami o scriva subito.',
            'de' => ($branche !== '' || $ort !== '' ? $name . ': ' . trim($branche . ($ort !== '' ? ' in ' . $ort : '')) . '. ' : '') . 'Öffnungszeiten, Anfahrt, Fotos und Kontakt auf einen Blick. Gleich anrufen oder anfragen.',
            'en' => ($branche !== '' || $ort !== '' ? $name . ': ' . trim($branche . ($ort !== '' ? ' in ' . $ort : '')) . '. ' : '') . 'Opening hours, directions, photos and contact at a glance. Call or enquire right away.',
        ][$sp];
        return ['jetzt' => ['titel' => $titel !== '' ? mb_substr($titel, 0, 70) : $host, 'url' => $url, 'beschreibung' => mb_substr(trim((string) ($m['beschreibung'] ?? '')), 0, 160)],
                'vorschlag' => ['titel' => mb_substr($vTitel, 0, 70), 'url' => $url, 'beschreibung' => mb_substr($vText, 0, 160)],
                'og' => !empty($m['og']), 'name' => $name];
    }

    /* ------------------------------ A5 Vergleich --------------------------- */

    /**
     * Vergleich mit geprüften Betrieben derselben Branche in derselben Stadt
     * (sonst demselben Kreis). Grundlage ist dieselbe Prüfung vom PC (Score
     * der Akquise, umgedreht: 100 − Score = je höher, desto besser). Nur mit
     * mindestens vier anderen -- sonst null. Namen werden nie gezeigt.
     * @return null|array{platz:int,von:int,ort:string,branche:string,liste:list<array{wert:int,selbst:bool}>}
     */
    public static function vergleich(int $firmaId, string $sp = 'it'): ?array
    {
        try {
            $f = Db::one('SELECT id, branche, stadt, kreis, score FROM akq_firmen WHERE id = ?', [$firmaId]);
            if (!$f || empty($f['branche']) || $f['score'] === null) { return null; }
            foreach ([['stadt', (string) $f['stadt']], ['kreis', (string) $f['kreis']]] as [$spalte, $wert]) {
                if (trim($wert) === '') { continue; }
                $andere = Db::all("SELECT id, score FROM akq_firmen WHERE branche = ? AND $spalte = ? AND id <> ? AND score IS NOT NULL AND audit_status = 'fertig' AND gesperrt = 0 ORDER BY score LIMIT 30",
                    [(string) $f['branche'], $wert, $firmaId]);
                if (count($andere) < 4) { continue; }
                $liste = array_map(static fn($r) => ['wert' => max(0, 100 - (int) $r['score']), 'selbst' => false], $andere);
                $liste[] = ['wert' => max(0, 100 - (int) $f['score']), 'selbst' => true];
                usort($liste, static fn($a, $b) => $b['wert'] <=> $a['wert'] ?: ($a['selbst'] ? -1 : 1));
                $platz = 1 + (int) array_search(true, array_column($liste, 'selbst'), true);
                $branche = (string) $f['branche'];
                try { require_once __DIR__ . '/Akquise.php'; $branche = Akquise::branchenName($branche, $sp); } catch (Throwable $e) { }
                return ['platz' => $platz, 'von' => count($liste), 'ort' => $wert, 'branche' => $branche, 'liste' => $liste];
            }
        } catch (Throwable $e) { }
        return null;
    }

    /* ------------------------------ A2 Markierungen ------------------------ */

    /** Markierungen aus dem Audit (Handyfoto 390 × 844). @return list<array{art:string,x:float,y:float,b:float,h:float}> */
    public static function marken(?array $audit): array
    {
        $roh = json_decode((string) ($audit['marken'] ?? ''), true);
        if (!is_array($roh)) { return []; }
        $aus = [];
        foreach (array_slice($roh, 0, 6) as $m) {
            if (!isset(self::WORTE['de']['mark'][$m['art'] ?? ''])) { continue; }
            $x = (float) ($m['x'] ?? -1); $y = (float) ($m['y'] ?? -1);
            if ($x < 0 || $y < 0 || $x > 390 || $y > 844) { continue; }
            $aus[] = ['art' => (string) $m['art'], 'x' => $x, 'y' => $y, 'b' => max(8.0, min(390.0, (float) ($m['b'] ?? 20))), 'h' => max(8.0, min(844.0, (float) ($m['h'] ?? 20)))];
        }
        return $aus;
    }

    /* ------------------------------ A7 Plan -------------------------------- */

    /** Ganze Euro, in der Schreibweise der Sprache: 345 € / €345. */
    public static function euro(int $cents, string $sp): string
    {
        $z = number_format($cents / 100, 0, ',', '.');
        return $sp === 'en' ? '€' . number_format($cents / 100, 0, '.', ',') : $z . ' €';
    }

    public static function spanne(int $von, int $bis, string $sp): string
    {
        if ($bis <= $von) { return self::euro($von, $sp); }
        return $sp === 'en' ? '€' . number_format($von / 100, 0, '.', ',') . '–' . number_format($bis / 100, 0, '.', ',')
                            : number_format($von / 100, 0, ',', '.') . '–' . number_format($bis / 100, 0, ',', '.') . ' €';
    }

    /** @return array{zeilen:list<array{was:string,titel:string,loesung:string,preis:string}>,gesamt:string,von:int,bis:int} */
    public static function plan(array $punkte, string $sp): array
    {
        require_once __DIR__ . '/Baukasten.php';
        require_once __DIR__ . '/Fmt.php';
        $kat = Baukasten::katalog();
        $W = self::WORTE[$sp];
        $zeilen = [];
        $gesehen = [];
        foreach (self::sortiert($punkte) as $p) {
            if ($p['stand'] === 'gut') { continue; }
            $slugs = array_values(array_filter(self::LOESUNG[$p['was']] ?? [], static fn($s) => isset($kat[$s])));
            $namen = array_map(static fn($s) => Baukasten::name($kat[$s], $sp), $slugs);
            $preis = '';
            /* Derselbe Baustein löst oft mehrere Punkte -- sein Preis steht nur beim ersten, sonst sähe es nach der Summe aller Zeilen aus. */
            if ($slugs && isset($gesehen[$slugs[0]])) {
                $preis = ['it' => 'già compreso sopra', 'de' => 'oben schon enthalten', 'en' => 'already included above'][$sp];
                $slugs = [];
            }
            if ($slugs) {
                $gesehen[$slugs[0]] = true;
                $b = $kat[$slugs[0]];
                $preis = (int) ($b['monatlich'] ?? 0) === 1
                    ? self::euro((int) $b['preis_cents'], $sp) . ' / ' . ['it' => 'mese', 'de' => 'Monat', 'en' => 'month'][$sp]
                    : self::spanne((int) $b['preis_cents'], (int) ($b['preis_bis_cents'] ?? 0), $sp);
                if ((string) ($b['einheit'] ?? '') === 'seite') { $preis .= ' ' . ['it' => 'per pagina', 'de' => 'je Seite', 'en' => 'per page'][$sp]; }
            }
            $zeilen[] = ['was' => $p['was'], 'titel' => Texte::h(Texte::PARTNER_CHECK['punkte'][$p['was']]['titel'] ?? [], $sp),
                         'loesung' => $namen ? implode(' + ', $namen) : $W['plan_gespraech'], 'preis' => $preis];
        }
        $r = Baukasten::rechnen(['bestand' => 'erneuern', 'material' => ['texte'], 'umfang' => '', 'sprachen' => 1], $kat);
        return ['zeilen' => $zeilen, 'von' => (int) $r['von_cents'], 'bis' => (int) $r['bis_cents'],
                'gesamt' => self::spanne((int) $r['von_cents'], (int) $r['bis_cents'], $sp)];
    }

    /* ------------------------------ A6 Rechner ----------------------------- */

    /** @return array{wert:int,mehr:int,jahr:int,summe:int} */
    public static function rechnen(int $wert, int $mehr): array
    {
        $wert = max(0, min(100000, $wert));
        $mehr = max(1, min(30, $mehr));
        return ['wert' => $wert, 'mehr' => $mehr, 'jahr' => $mehr * 12, 'summe' => $mehr * 12 * $wert];
    }

    /* ------------------------------ Speichern ------------------------------ */

    public static function speichern(array $kc, ?int $firmaId = null, string $quelle = 'analisi'): string
    {
        $daten = ['host' => (string) ($kc['host'] ?? ''), 'url' => (string) ($kc['url'] ?? ''), 'punkte' => array_values((array) ($kc['punkte'] ?? [])), 'meta' => (array) ($kc['meta'] ?? []),
                  'geprueft' => date('Y-m-d H:i:s')];
        $token = bin2hex(random_bytes(16));
        Db::insert('web_berichte', ['token' => $token, 'firma_id' => $firmaId, 'host' => mb_substr($daten['host'], 0, 190), 'url' => mb_substr($daten['url'], 0, 500),
            'daten' => json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'note' => self::note($daten['punkte']), 'quelle' => mb_substr($quelle, 0, 20)]);
        /* Growth Engine Phase 4: der öffentliche Check im Besuch über einen Partner- oder
           Kampagnenlink zählt als Ereignis (ohne Besuch passiert in Spur nichts). */
        if (in_array($quelle, ['analisi', 'check'], true)) {
            try { require_once __DIR__ . '/Spur.php'; Spur::ereignis('website_check_completed', ['seite' => $quelle === 'check' ? '/website-check.php' : '/analisi.php', 'meta' => ['note' => self::note($daten['punkte'])]]); } catch (Throwable $e) { }
        }
        return $token;
    }

    /** @return null|array{token:string,firma_id:?int,kc:array,created_at:string} */
    public static function laden(string $token, bool $zaehlen = false): ?array
    {
        if (!preg_match('~^[a-f0-9]{32}$~', $token)) { return null; }
        $r = Db::one('SELECT * FROM web_berichte WHERE token = ?', [$token]);
        if (!$r) { return null; }
        if ($zaehlen) {
            try { Db::run('UPDATE web_berichte SET aufrufe = aufrufe + 1 WHERE id = ?', [(int) $r['id']]); } catch (Throwable $e) { }
            /* Heißer Lead (30.09.2026): Der Bericht eines bekannten Betriebs zum dritten Mal geöffnet. */
            if ($r['firma_id'] !== null && (int) ($r['aufrufe'] ?? 0) + 1 === 3) {
                try {
                    require_once __DIR__ . '/AkquiseAnalyse.php';
                    $f = Db::one('SELECT * FROM akq_firmen WHERE id = ?', [(int) $r['firma_id']]);
                    if ($f) { AkquiseAnalyse::heissMelden($f, '3× ausführlichen Bericht geöffnet'); }
                } catch (Throwable $e) { }
            }
        }
        $kc = json_decode((string) $r['daten'], true) ?: [];
        return ['token' => (string) $r['token'], 'firma_id' => $r['firma_id'] !== null ? (int) $r['firma_id'] : null, 'kc' => $kc, 'created_at' => (string) $r['created_at']];
    }

    /** Der jüngste Bericht zu einem Betrieb (höchstens $tage alt). */
    public static function fuerFirma(int $firmaId, int $tage = 60): ?array
    {
        try {
            $t = Db::wert('SELECT token FROM web_berichte WHERE firma_id = ? AND created_at >= ? ORDER BY id DESC LIMIT 1', [$firmaId, date('Y-m-d H:i:s', time() - $tage * 86400)], null);
            return $t !== null ? self::laden((string) $t) : null;
        } catch (Throwable $e) { return null; }
    }

    /** Für einen Betrieb mit Website: vorhandenen Bericht nehmen oder jetzt prüfen. */
    public static function sicherFuerFirma(int $firmaId, string $quelle = 'analyse'): ?array
    {
        $b = self::fuerFirma($firmaId, 14);
        if ($b !== null) { return $b; }
        $url = (string) Db::wert('SELECT url FROM akq_firmen WHERE id = ?', [$firmaId], '');
        if ($url === '') { return null; }
        require_once __DIR__ . '/PartnerCheck.php';
        $e = PartnerCheck::pruefen($url);
        if (($e['fehler'] ?? '') === 'adresse') { return null; }
        return self::laden(self::speichern($e, $firmaId, $quelle));
    }

    public static function adresse(string $token, string $sp): string
    {
        return rtrim((string) Config::get('website', 'https://vecom-design.it'), '/') . '/bericht.php?b=' . $token . '&lang=' . $sp;
    }

    /** Für Betriebe mit persönlichem Bereich oder Einwilligung, die noch keinen Bericht haben: nachholen (Cron, wenige je Lauf). */
    public static function nachholen(int $n = 2): int
    {
        $gemacht = 0;
        try {
            $grenze = date('Y-m-d H:i:s', time() - 60 * 86400);
            foreach (Db::all("SELECT f.id FROM akq_firmen f WHERE f.gesperrt = 0 AND f.url IS NOT NULL AND f.url <> ''
                                AND (f.customer_id IS NOT NULL OR (f.einwilligung IS NOT NULL AND f.einwilligung <> ''))
                                AND NOT EXISTS (SELECT 1 FROM web_berichte w WHERE w.firma_id = f.id AND w.created_at >= ?)
                              ORDER BY f.dashboard_am IS NULL, f.id DESC LIMIT " . max(1, min(5, $n)), [$grenze]) as $r) {
                if (self::sicherFuerFirma((int) $r['id'], 'nachgeholt') !== null) { $gemacht++; }
            }
        } catch (Throwable $e) { }
        return $gemacht;
    }

    public static function aufraeumen(): int
    {
        try { return Db::run('DELETE FROM web_berichte WHERE firma_id IS NULL AND created_at < ?', [date('Y-m-d H:i:s', time() - self::FRIST_TAGE * 86400)])->rowCount(); }
        catch (Throwable $e) { return 0; }
    }

    /** A9: der Bericht als PDF (eine Seite A4, QR zur Online-Fassung). */
    public static function pdf(array $b, string $sprache): string
    {
        require_once __DIR__ . '/Pdf.php';
        require_once dirname(__DIR__) . '/lib/qrcode.php';
        $kc = $b['kc'];
        $W = self::WORTE[$sprache];
        $P = array_values(array_filter((array) ($kc['punkte'] ?? []), static fn($p) => isset(Texte::PARTNER_CHECK['punkte'][$p['was'] ?? ''])));
        $note = self::note($P);
        $hex = static fn(string $c): array => [hexdec(substr($c, 1, 2)) / 255, hexdec(substr($c, 3, 2)) / 255, hexdec(substr($c, 5, 2)) / 255];
        $gold = [0.72, 0.54, 0.18]; $grau = [0.38, 0.36, 0.33]; $tinte = [0.09, 0.08, 0.06];
        $pdf = new Pdf();
        $x = 48; $breit = Pdf::A4_BREIT - 96;
        $pdf->flaeche(0, 0, Pdf::A4_BREIT, 6, $gold);
        $pdf->text($x, 44, 'VECOM DESIGN', 11, true, 'links', $gold);
        $pdf->text(Pdf::A4_BREIT - 48, 44, strtr($W['geprueft'], ['{datum}' => date('d.m.Y', strtotime((string) ($kc['geprueft'] ?? $b['created_at'])))]), 9, false, 'rechts', $grau);
        $pdf->text($x, 80, (string) ($kc['host'] ?? ''), 22, true, 'links', $tinte);
        /* Note als Kasten */
        $pdf->flaeche($x, 96, 120, 78, [0.965, 0.955, 0.93]);
        $pdf->text($x + 60, 146, (string) $note, 38, true, 'mitte', $hex(self::farbe($note)));
        $pdf->text($x + 60, 166, $W['note'], 8, false, 'mitte', $grau);
        $pdf->text($x + 138, 116, self::stufe($note, $sprache), 15, true, 'links', $hex(self::farbe($note)));
        $y = $pdf->zeilen($x + 138, 136, $pdf->umbrechen(self::satz($P, $sprache), $breit - 138, 11), 11, false, 1.36);
        $sek = self::sekunden($kc);
        if ($sek !== null) {
            $pdf->text($x + 138, max($y + 4, 166), $W['tempo_t'] . ': ' . str_replace('.', $sprache === 'en' ? '.' : ',', (string) $sek) . ' s (' . $W['ziel'] . ': ' . ($sprache === 'en' ? '1.5' : '1,5') . ' s)', 9.5, false, 'links', $grau);
        }
        $y = 200;
        $pdf->text($x, $y, $W['punkte'], 13, true, 'links', $tinte);
        $y += 18;
        $farben = ['gut' => [0.25, 0.71, 0.42], 'hinweis' => [0.88, 0.70, 0.25], 'schlecht' => [0.90, 0.33, 0.29]];
        foreach (self::sortiert($P) as $p) {
            $titel = Texte::h(Texte::PARTNER_CHECK['punkte'][$p['was']]['titel'], $sprache);
            $satz = self::punktSatz($p, $sprache);
            $zeilen = $pdf->umbrechen($satz, $breit - 150, 9.5);
            $heisst = $p['stand'] !== 'gut' ? $pdf->umbrechen(self::HEISST[$p['was']][$sprache] ?? '', $breit - 150, 9) : [];
            $hoehe = 8 + 13 * count($zeilen) + 12 * count($heisst);
            if ($y + $hoehe > Pdf::A4_HOCH - 150) { break; }
            $pdf->flaeche($x, $y - 1, 8, 8, $farben[$p['stand']] ?? $farben['hinweis']);
            $pdf->text($x + 16, $y + 7, $titel, 10, true, 'links', $tinte);
            $pdf->text($x + 16, $y + 19, $W['stand'][$p['stand']] ?? '', 8.5, false, 'links', $farben[$p['stand']] ?? $grau);
            $yy = $pdf->zeilen($x + 150, $y + 7, $zeilen, 9.5, false, 1.37);
            if ($heisst) { $pdf->zeilen($x + 150, $yy, $heisst, 9, false, 1.33, $grau); }
            $y += max(30, $hoehe + 6);
            $pdf->linie($x, $y - 8, $x + $breit, $y - 8, 0.4, [0.88, 0.86, 0.82]);
        }
        /* Fuß: QR zur Online-Fassung */
        $url = self::adresse($b['token'], $sprache);
        $qr = QRCode::getMinimumQRCode($url, QR_ERROR_CORRECT_LEVEL_M);
        $n = $qr->getModuleCount(); $mod = 76 / $n; $qx = Pdf::A4_BREIT - 48 - 76; $qy = Pdf::A4_HOCH - 128;
        for ($r = 0; $r < $n; $r++) { for ($c = 0; $c < $n; $c++) { if ($qr->isDark($r, $c)) { $pdf->flaeche($qx + $c * $mod, $qy + $r * $mod, $mod + 0.05, $mod + 0.05, [0, 0, 0]); } } }
        $fuss = ['it' => ['Il rapporto completo online, con confronto e calcolatore:', 'Analisi gratuita per altri siti: vecom-design.it/analisi.php'],
                 'de' => ['Der vollständige Bericht online, mit Vergleich und Rechner:', 'Kostenlose Analyse weiterer Websites: vecom-design.it/analisi.php'],
                 'en' => ['The full report online, with comparison and calculator:', 'Free analysis for other websites: vecom-design.it/analisi.php']][$sprache];
        $pdf->text($x, Pdf::A4_HOCH - 110, $fuss[0], 10, true, 'links', $tinte);
        $pdf->zeilen($x, Pdf::A4_HOCH - 94, $pdf->umbrechen($url, $breit - 100, 8.5), 8.5, false, 1.3, $grau);
        $pdf->text($x, Pdf::A4_HOCH - 62, $fuss[1], 9, false, 'links', $grau);
        $pdf->text($x, Pdf::A4_HOCH - 40, 'Vecom Design · Uwe Vetter · Aragona (AG) · vecom-design.it', 8.5, false, 'links', $grau);
        $pdf->flaeche(0, Pdf::A4_HOCH - 6, Pdf::A4_BREIT, 6, $gold);

        return $pdf->fertig();
    }

    /* ------------------------------ Symbole (A8) --------------------------- */

    /** Einfache Linien-Symbole, 24 × 24, currentColor. */
    public static function symbol(string $was): string
    {
        $d = [
            'tempo' => '<path d="M12 21a9 9 0 1 1 9-9"/><path d="M12 12l5-4"/><circle cx="12" cy="12" r="1.3"/>',
            'handy' => '<rect x="7" y="2.5" width="10" height="19" rx="2.2"/><path d="M10.5 18.5h3"/>',
            'sicher' => '<rect x="5" y="10.5" width="14" height="10" rx="2"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',
            'google' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="M15.5 15.5L21 21"/>',
            'telefon' => '<path d="M6.5 3.5h3l1.5 4-2 1.5a11 11 0 0 0 6 6l1.5-2 4 1.5v3a2 2 0 0 1-2 2A16 16 0 0 1 4.5 5.5a2 2 0 0 1 2-2z"/>',
            'adresse' => '<path d="M12 21s-6.5-6-6.5-11a6.5 6.5 0 0 1 13 0c0 5-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.3"/>',
            'zeiten' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
            'aktuell' => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/>',
            'teilen' => '<circle cx="18" cy="5.5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="18.5" r="2.5"/><path d="M8.2 10.8l7.6-4M8.2 13.2l7.6 4"/>',
            'bilder' => '<rect x="3" y="4.5" width="18" height="15" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="M21 16l-5-5-8 8"/>',
            'alt' => '<rect x="3" y="4.5" width="18" height="15" rx="2"/><path d="M7 15h6M7 11.5h10"/>',
            'rechtlich' => '<path d="M12 3v18M6 7h12M4 14l2-7 2 7a2.5 2.5 0 0 1-4 0zM16 14l2-7 2 7a2.5 2.5 0 0 1-4 0zM8 21h8"/>',
            'erreichbar' => '<path d="M2 8.5a15 15 0 0 1 20 0M5 12a10 10 0 0 1 14 0M8.5 15.5a5 5 0 0 1 7 0"/><path d="M3 3l18 18"/>',
        ][$was] ?? '<circle cx="12" cy="12" r="8"/>';
        return '<svg class="wb-sym" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
    }
}
