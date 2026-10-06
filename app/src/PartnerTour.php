<?php
declare(strict_types=1);

/**
 * Einführung für Partner (06.10.2026, Uwe: Ja zu allen vier Vorschlägen).
 *
 *  1. Geführte Tour beim ersten Login — abgedunkelt, ein Element leuchtet, Sprechblase, Weiter/Zurück/Überspringen.
 *  2. Mini-Tour je Bereich — beim ersten Öffnen eines Bereichs, 2 bis 5 Schritte am echten Knopf.
 *  3. Checkliste „Deine ersten 7 Tage“ — hakt sich aus echten Daten ab, „Zeig mir, wo“ startet die Tour an der Stelle.
 *  4. „?“ in jedem Bereich — ein Satz, wofür der Bereich da ist, und die Tour dazu.
 *
 * Die Ziele sind data-tour-Attribute in den Seiten, keine CSS-Klassen: Ein Umbau der Optik darf die Führung
 * nicht still zerbrechen. Fehlt ein Ziel (leere Liste, anderer Teil der Seite), zeigt die Tour den Schritt
 * mittig ohne Lichtkegel — sie bleibt nie hängen. Gespeichert wird nur, ob eine Tour fertig oder übersprungen
 * ist und welche Schritte der Checkliste per Klick erledigt wurden (partner_einstieg). In der Admin-Ansicht
 * („nur lesen“) startet nichts von selbst und nichts wird gespeichert.
 */
final class PartnerTour
{
    /** Ereignisse, die nur der Browser sieht (Kopieren, Teilen, eigene Seite, Nachricht, App). */
    public const EREIGNISSE = ['link_kopiert', 'geteilt', 'seite', 'nachricht', 'app'];

    /**
     * Die Touren. Jeder Schritt: k (Schlüssel), seite (wo er steht), ziel (data-tour-Werte, der erste sichtbare gilt;
     * leer = mittig), titel, text — je it/de/en. Italienisch höflich (Lei), Deutsch du, wie im ganzen Partnerbereich.
     */
    public const TOUREN = [
        'haupt' => [
            ['k' => 'hallo', 'seite' => 'start', 'ziel' => [],
             'titel' => ['it' => 'Benvenuto in Vecom', 'de' => 'Willkommen bei Vecom', 'en' => 'Welcome to Vecom'],
             'text' => ['it' => 'In un minuto le mostriamo dove si trova tutto. Può toccare «Salta» in qualsiasi momento e riaprire il giro più tardi con il «?» in alto.',
                        'de' => 'In einer Minute zeigen wir dir, wo alles ist. Du kannst jederzeit „Überspringen“ tippen und die Tour später über das „?“ oben wieder starten.',
                        'en' => 'In one minute we show you where everything is. You can tap “Skip” at any time and restart the tour later with the “?” at the top.']],
            ['k' => 'zahlen', 'seite' => 'start', 'ziel' => ['zahlen'],
             'titel' => ['it' => 'I suoi numeri', 'de' => 'Deine Zahlen', 'en' => 'Your numbers'],
             'text' => ['it' => 'Visite tramite il suo link, i suoi clienti e la sua provvigione. Tocchi un numero per vedere i dettagli.',
                        'de' => 'Besuche über deinen Link, deine Kunden und deine Provision. Tippe eine Zahl an, dann siehst du die Einzelheiten.',
                        'en' => 'Visits through your link, your customers and your commission. Tap a number to see the details.']],
            ['k' => 'wichtig', 'seite' => 'start', 'ziel' => ['wichtig'],
             'titel' => ['it' => 'Cosa conta adesso', 'de' => 'Was jetzt wichtig ist', 'en' => 'What matters now'],
             'text' => ['it' => 'Qui c’è ciò che la aspetta — prima il rosso. Un tocco la porta nel punto giusto.',
                        'de' => 'Hier steht, was auf dich wartet — Rot zuerst. Ein Tipp führt dich direkt an die richtige Stelle.',
                        'en' => 'This is what is waiting for you — red first. One tap takes you straight to the right place.']],
            ['k' => 'jetzt', 'seite' => 'start', 'ziel' => ['jetzt'],
             'titel' => ['it' => 'Cosa faccio adesso?', 'de' => 'Was soll ich jetzt tun?', 'en' => 'What should I do now?'],
             'text' => ['it' => 'Non è sicuro? Tocchi qui. Le mostriamo l’unico compito che oggi rende di più.',
                        'de' => 'Unsicher? Tippe hier. Wir zeigen dir die eine Aufgabe, die gerade am meisten bringt.',
                        'en' => 'Not sure? Tap here. We show you the one task that pays off most right now.']],
            ['k' => 'kunden', 'seite' => 'start', 'ziel' => ['nav-finden'],
             'titel' => ['it' => 'Clienti', 'de' => 'Kunden', 'en' => 'Customers'],
             'text' => ['it' => 'I suoi contatti e le attività. Qui li aggiunge, vede a che punto sono e li passa a Vecom.',
                        'de' => 'Deine Kontakte und Betriebe. Hier legst du sie an, siehst ihren Stand und gibst sie an Vecom weiter.',
                        'en' => 'Your contacts and businesses. Add them here, see where they stand and hand them over to Vecom.']],
            ['k' => 'marketing', 'seite' => 'start', 'ziel' => ['nav-werben'],
             'titel' => ['it' => 'Marketing', 'de' => 'Marketing', 'en' => 'Marketing'],
             'text' => ['it' => 'Il suo link, post pronti da condividere e aiuto per il colloquio di vendita. Il suo link è già dentro ovunque.',
                        'de' => 'Dein Link, fertige Beiträge zum Teilen und Hilfe fürs Verkaufsgespräch. Dein Link steckt überall schon drin.',
                        'en' => 'Your link, ready-made posts to share and help for the sales talk. Your link is already in everything.']],
            ['k' => 'ergebnisse', 'seite' => 'start', 'ziel' => ['nav-geld'],
             'titel' => ['it' => 'Risultati', 'de' => 'Ergebnisse', 'en' => 'Results'],
             'text' => ['it' => 'La sua provvigione, il suo livello e i suoi pagamenti.',
                        'de' => 'Deine Provision, dein Level und deine Auszahlungen.',
                        'en' => 'Your commission, your level and your payouts.']],
            ['k' => 'konto', 'seite' => 'start', 'ziel' => ['nav-profil', 'nav-mehr'],
             'titel' => ['it' => 'Account e altro', 'de' => 'Mein Konto und mehr', 'en' => 'Account and more'],
             'text' => ['it' => 'Profilo, modo di pagamento, Shop e Academy. Sul telefono li trova sotto «Altro».',
                        'de' => 'Profil, Auszahlungsweg, Shop und Academy. Am Handy findest du sie unter „Mehr“.',
                        'en' => 'Profile, payout method, Shop and Academy. On your phone they are under “More”.']],
            ['k' => 'hilfe', 'seite' => 'start', 'ziel' => ['hilfe'],
             'titel' => ['it' => 'Aiuto in ogni pagina', 'de' => 'Hilfe auf jeder Seite', 'en' => 'Help on every page'],
             'text' => ['it' => 'Non sa come andare avanti? Tocchi «?». Le spieghiamo esattamente quella sezione — clic per clic.',
                        'de' => 'Weißt du nicht weiter? Tippe auf „?“. Dann erklären wir dir genau diesen Bereich — Klick für Klick.',
                        'en' => 'Stuck? Tap “?”. We explain exactly that section — click by click.']],
            ['k' => 'checkliste', 'seite' => 'start', 'ziel' => ['checkliste'],
             'titel' => ['it' => 'I suoi primi 7 giorni', 'de' => 'Deine ersten 7 Tage', 'en' => 'Your first 7 days'],
             'text' => ['it' => 'Segua questa lista. A ogni punto «Mi mostri dove» le indica il pulsante giusto. Ciò che è fatto si spunta da solo.',
                        'de' => 'Arbeite diese Liste ab. Bei jedem Punkt zeigt dir „Zeig mir, wo“ genau den Knopf. Erledigtes hakt sich von selbst ab.',
                        'en' => 'Work through this list. For each item, “Show me where” points to the exact button. Done items tick themselves off.']],
        ],
        'kunden' => [
            ['k' => 'neu', 'seite' => 'kunden', 'ziel' => ['kd-neu'],
             'titel' => ['it' => 'Nuovo contatto', 'de' => 'Neuen Kontakt anlegen', 'en' => 'Add a contact'],
             'text' => ['it' => 'Conosce qualcuno che ha bisogno di un sito? Tocchi «+ Nuovo contatto» e inserisca nome e telefono. Il contatto è suo.',
                        'de' => 'Kennst du jemanden, der eine Website braucht? Tippe auf „+ Neuer Kontakt“ und trage Name und Telefon ein. Der Kontakt gehört dir.',
                        'en' => 'Know someone who needs a website? Tap “+ New contact” and enter name and phone. The contact is yours.']],
            ['k' => 'stufen', 'seite' => 'kunden', 'ziel' => ['kd-stufen'],
             'titel' => ['it' => 'A che punto sono', 'de' => 'Wo jeder steht', 'en' => 'Where everyone stands'],
             'text' => ['it' => 'Ogni contatto ha una fase: nuovo, contattato, interesse, offerta, cliente. Tocchi una fase per vedere solo quelli.',
                        'de' => 'Jeder Kontakt hat einen Stand: neu, kontaktiert, Interesse, Angebot, Kunde. Tippe einen Stand an, dann siehst du nur diese.',
                        'en' => 'Each contact has a stage: new, contacted, interest, offer, customer. Tap a stage to see only those.']],
            ['k' => 'liste', 'seite' => 'kunden', 'ziel' => ['kd-liste'],
             'titel' => ['it' => 'I suoi contatti', 'de' => 'Deine Kontakte', 'en' => 'Your contacts'],
             'text' => ['it' => 'Tocchi un contatto: note, compiti, storico e il pulsante per passarlo a Vecom. Con la cornetta chiama subito.',
                        'de' => 'Tippe einen Kontakt an: Notizen, Aufgaben, Verlauf und der Knopf, um ihn an Vecom zu übergeben. Mit dem Hörer rufst du direkt an.',
                        'en' => 'Tap a contact: notes, tasks, history and the button to hand it over to Vecom. The phone icon calls right away.']],
            ['k' => 'werkzeuge', 'seite' => 'kunden', 'ziel' => ['kd-werkzeuge'],
             'titel' => ['it' => 'Strumenti', 'de' => 'Werkzeuge', 'en' => 'Tools'],
             'text' => ['it' => 'Trovare attività vicine, lista chiamate, check del sito: tutto per trovare nuovi clienti.',
                        'de' => 'Betriebe in der Nähe finden, Anrufliste, Website-Check: alles, um neue Kunden zu gewinnen.',
                        'en' => 'Find businesses nearby, call list, website check: everything to win new customers.']],
        ],
        'marketing' => [
            ['k' => 'teile', 'seite' => 'marketing', 'ziel' => ['mk-teile'],
             'titel' => ['it' => 'Quattro parti', 'de' => 'Vier Bereiche', 'en' => 'Four parts'],
             'text' => ['it' => 'Assistente, Mediateca, Campagne e Aiuto vendita. Qui sopra passa dall’una all’altra.',
                        'de' => 'Assistent, Mediathek, Kampagnen und Verkaufshilfe. Hier oben wechselst du zwischen ihnen.',
                        'en' => 'Assistant, Media library, Campaigns and Sales help. Switch between them up here.']],
            ['k' => 'heute', 'seite' => 'marketing', 'ziel' => ['mk-assistent'],
             'titel' => ['it' => 'Da pubblicare oggi', 'de' => 'Heute posten', 'en' => 'Post today'],
             'text' => ['it' => 'Tre contenuti pronti, adatti al suo settore e al suo canale.',
                        'de' => 'Drei fertige Beiträge, passend zu deiner Branche und deinem Kanal.',
                        'en' => 'Three ready-made posts that fit your industry and your channel.']],
            ['k' => 'kopieren', 'seite' => 'marketing', 'ziel' => ['mk-kopieren'],
             'titel' => ['it' => 'Copiare e condividere', 'de' => 'Kopieren und teilen', 'en' => 'Copy and share'],
             'text' => ['it' => 'Tocchi «Copia» e incolli il testo nello stato WhatsApp, su Instagram o Facebook. Il suo link c’è già.',
                        'de' => 'Tippe auf „Kopieren“ und füge den Text im WhatsApp-Status, auf Instagram oder Facebook ein. Dein Link steckt schon drin.',
                        'en' => 'Tap “Copy” and paste the text into your WhatsApp status, Instagram or Facebook. Your link is already in it.']],
            ['k' => 'link', 'seite' => 'marketing_link', 'ziel' => ['mk-link'],
             'titel' => ['it' => 'Il suo link personale', 'de' => 'Dein persönlicher Link', 'en' => 'Your personal link'],
             'text' => ['it' => 'Questo è il suo link. Tutto ciò che arriva tramite lui conta per lei. Lo copi e lo mandi a cinque conoscenti.',
                        'de' => 'Das ist dein Link. Alles, was über ihn kommt, zählt für dich. Kopiere ihn und schick ihn an fünf Bekannte.',
                        'en' => 'This is your link. Everything that comes through it counts for you. Copy it and send it to five people you know.']],
        ],
        'ergebnisse' => [
            ['k' => 'zahlen', 'seite' => 'ergebnisse', 'ziel' => ['eg-zahlen'],
             'titel' => ['it' => 'Il quadro', 'de' => 'Der Überblick', 'en' => 'The overview'],
             'text' => ['it' => 'Prima i numeri principali: visite, clienti, provvigione.',
                        'de' => 'Zuerst die wichtigsten Zahlen: Besuche, Kunden, Provision.',
                        'en' => 'First the key numbers: visits, customers, commission.']],
            ['k' => 'level', 'seite' => 'ergebnisse', 'ziel' => ['eg-level'],
             'titel' => ['it' => 'Il suo livello', 'de' => 'Dein Level', 'en' => 'Your level'],
             'text' => ['it' => 'Più vendite nell’anno, livello più alto. Qui vede quanto manca al prossimo.',
                        'de' => 'Mehr Verkäufe im Jahr, höheres Level. Hier siehst du, was bis zum nächsten fehlt.',
                        'en' => 'More sales per year, higher level. See here what is missing for the next one.']],
            ['k' => 'provisionen', 'seite' => 'ergebnisse', 'ziel' => ['eg-provisionen'],
             'titel' => ['it' => 'Le sue provvigioni', 'de' => 'Deine Provisionen', 'en' => 'Your commissions'],
             'text' => ['it' => 'Ogni provvigione attende il periodo di recesso, poi diventa libera.',
                        'de' => 'Jede Provision wartet die Widerrufsfrist ab, dann wird sie frei.',
                        'en' => 'Each commission waits out the withdrawal period, then it is released.']],
            ['k' => 'auszahlung', 'seite' => 'ergebnisse', 'ziel' => ['eg-auszahlung'],
             'titel' => ['it' => 'Pagamenti', 'de' => 'Auszahlungen', 'en' => 'Payouts'],
             'text' => ['it' => 'Raggiunto l’importo minimo, paghiamo. Le ricevute sono qui.',
                        'de' => 'Ist der Mindestbetrag erreicht, zahlen wir aus. Die Belege stehen hier.',
                        'en' => 'Once the minimum is reached, we pay out. The receipts are here.']],
        ],
        'shop' => [
            ['k' => 'produkte', 'seite' => 'shop', 'ziel' => ['sh-produkte'],
             'titel' => ['it' => 'Materiali con i suoi dati', 'de' => 'Werbemittel mit deinen Daten', 'en' => 'Materials with your details'],
             'text' => ['it' => 'Volantini, biglietti e altro, già con il suo nome e il suo QR. Guardi l’anteprima, poi approvi.',
                        'de' => 'Flyer, Karten und mehr, schon mit deinem Namen und QR-Code. Vorschau ansehen, dann freigeben.',
                        'en' => 'Flyers, cards and more, already with your name and QR code. Check the preview, then approve.']],
            ['k' => 'bestellungen', 'seite' => 'shop', 'ziel' => ['sh-bestellungen'],
             'titel' => ['it' => 'I suoi ordini', 'de' => 'Deine Bestellungen', 'en' => 'Your orders'],
             'text' => ['it' => 'Qui segue la spedizione e segnala se qualcosa non va.',
                        'de' => 'Hier verfolgst du den Versand und meldest, wenn etwas nicht stimmt.',
                        'en' => 'Track shipping here and report if something is wrong.']],
        ],
        'support' => [
            ['k' => 'suche', 'seite' => 'support', 'ziel' => ['su-suche'],
             'titel' => ['it' => 'Prima cercare', 'de' => 'Erst suchen', 'en' => 'Search first'],
             'text' => ['it' => 'Scriva una parola: spesso la risposta c’è già.',
                        'de' => 'Gib ein Wort ein: Oft steht die Antwort schon da.',
                        'en' => 'Type a word: the answer is often already there.']],
            ['k' => 'neu', 'seite' => 'support', 'ziel' => ['su-neu'],
             'titel' => ['it' => 'Scrivere a Vecom', 'de' => 'Vecom schreiben', 'en' => 'Write to Vecom'],
             'text' => ['it' => 'Scelga il tema, scriva in breve cosa serve — rispondiamo qui.',
                        'de' => 'Thema wählen, kurz schreiben, was du brauchst — wir antworten hier.',
                        'en' => 'Pick a topic, write briefly what you need — we reply here.']],
            ['k' => 'liste', 'seite' => 'support', 'ziel' => ['su-liste'],
             'titel' => ['it' => 'Le sue richieste', 'de' => 'Deine Anliegen', 'en' => 'Your requests'],
             'text' => ['it' => 'Tutte le richieste con il loro stato.',
                        'de' => 'Alle Anliegen mit ihrem Stand.',
                        'en' => 'All requests with their status.']],
        ],
        'finden' => [
            ['k' => 'suche', 'seite' => 'voll', 'ziel' => ['fi-suche'],
             'titel' => ['it' => 'Cercare attività', 'de' => 'Betriebe suchen', 'en' => 'Search businesses'],
             'text' => ['it' => 'Scriva la sua città e scelga un settore. Trova attività senza sito o con un sito debole.',
                        'de' => 'Gib deinen Ort ein und wähle eine Branche. Du bekommst Betriebe ohne oder mit schwacher Website.',
                        'en' => 'Enter your town and pick an industry. You get businesses with no or a weak website.']],
            ['k' => 'reserv', 'seite' => 'voll', 'ziel' => ['fi-reserv'],
             'titel' => ['it' => 'Prenotare', 'de' => 'Reservieren', 'en' => 'Reserve'],
             'text' => ['it' => 'Le piace un’attività? Tocchi «Prenota». Per 30 giorni è solo sua.',
                        'de' => 'Gefällt dir ein Betrieb? Tippe auf „Reservieren“. 30 Tage lang gehört er nur dir.',
                        'en' => 'Like a business? Tap “Reserve”. For 30 days it is yours only.']],
            ['k' => 'meine', 'seite' => 'voll', 'ziel' => ['fi-meine'],
             'titel' => ['it' => 'Le sue attività prenotate', 'de' => 'Deine reservierten Betriebe', 'en' => 'Your reserved businesses'],
             'text' => ['it' => 'Qui ci sono, ognuna con un testo già pronto.',
                        'de' => 'Hier stehen sie, jeder mit einem fertigen Text.',
                        'en' => 'Here they are, each with a ready-made text.']],
            ['k' => 'nachricht', 'seite' => 'voll', 'ziel' => ['fi-nachricht'],
             'titel' => ['it' => 'Aprire il messaggio', 'de' => 'Nachricht öffnen', 'en' => 'Open the message'],
             'text' => ['it' => 'Tocchi WhatsApp o E-mail. Il testo è pronto, invia lei dal suo telefono.',
                        'de' => 'Tippe auf WhatsApp oder E-Mail. Der Text ist fertig, du sendest selbst von deinem Handy.',
                        'en' => 'Tap WhatsApp or Email. The text is ready, you send it yourself from your phone.']],
            ['k' => 'melden', 'seite' => 'voll', 'ziel' => ['fi-melden'],
             'titel' => ['it' => 'Segnare il risultato', 'de' => 'Ergebnis eintragen', 'en' => 'Record the result'],
             'text' => ['it' => 'È interessato? Lo segnali qui — così il cliente è suo.',
                        'de' => 'Interessiert? Hier meldest du ihn — dann gehört der Kunde dir.',
                        'en' => 'Interested? Report it here — then the customer is yours.']],
        ],
        'profil' => [
            ['k' => 'profil', 'seite' => 'start', 'ziel' => ['profil'],
             'titel' => ['it' => 'Il suo profilo', 'de' => 'Dein Profil', 'en' => 'Your profile'],
             'text' => ['it' => 'Quattro domande: settori, canali, obiettivo e zona. Così le proponiamo i contenuti giusti.',
                        'de' => 'Vier Fragen: Branchen, Wege, Ziel und Ort. Dann schlagen wir dir die passenden Inhalte vor.',
                        'en' => 'Four questions: industries, channels, goal and area. Then we suggest the right content.']],
        ],
        'seite' => [
            ['k' => 'seite', 'seite' => 'voll_seite', 'ziel' => ['seite'],
             'titel' => ['it' => 'La sua pagina', 'de' => 'Deine Partnerseite', 'en' => 'Your partner page'],
             'text' => ['it' => 'La sua pagina personale per i clienti. Tocchi qui, la apra una volta e guardi come la vedono gli altri.',
                        'de' => 'Deine persönliche Seite für Kunden. Tippe hier, öffne sie einmal und schau, wie andere sie sehen.',
                        'en' => 'Your personal page for customers. Tap here, open it once and see how others see it.']],
        ],
        'app' => [
            ['k' => 'app', 'seite' => 'voll_app', 'ziel' => ['app'],
             'titel' => ['it' => 'L’app sul telefono', 'de' => 'Die App aufs Handy', 'en' => 'The app on your phone'],
             'text' => ['it' => 'Installi l’app e attivi gli avvisi: così sa subito quando qualcuno compra tramite lei.',
                        'de' => 'Installiere die App und schalte Hinweise ein: Dann weißt du sofort, wenn jemand über dich kauft.',
                        'en' => 'Install the app and turn on notifications: then you know right away when someone buys through you.']],
        ],
    ];

    /** Welche Tour gehört zu welcher Seite (für die automatische Mini-Tour und das „?“). */
    public const TOUR_JE_SEITE = ['start' => 'haupt', 'kunden' => 'kunden', 'marketing' => 'marketing', 'marketing_link' => 'marketing',
                                  'ergebnisse' => 'ergebnisse', 'shop' => 'shop', 'support' => 'support', 'voll' => 'finden'];

    /** Das „?“: ein Satz je Bereich. */
    public const HILFE = [
        'start' => ['it' => 'La pagina iniziale: i suoi numeri, ciò che conta adesso e il prossimo passo.', 'de' => 'Die Startseite: deine Zahlen, was jetzt wichtig ist, und der nächste Schritt.', 'en' => 'The home page: your numbers, what matters now and the next step.'],
        'kunden' => ['it' => 'Qui gestisce i suoi contatti — dal primo contatto fino al cliente.', 'de' => 'Hier verwaltest du deine Kontakte — vom ersten Kontakt bis zum Kunden.', 'en' => 'Manage your contacts here — from first contact to customer.'],
        'marketing' => ['it' => 'Il suo link e contenuti pronti da condividere.', 'de' => 'Dein Link und fertige Inhalte zum Teilen.', 'en' => 'Your link and ready-made content to share.'],
        'marketing_link' => ['it' => 'Il suo link e contenuti pronti da condividere.', 'de' => 'Dein Link und fertige Inhalte zum Teilen.', 'en' => 'Your link and ready-made content to share.'],
        'ergebnisse' => ['it' => 'Provvigioni, livello e pagamenti.', 'de' => 'Provisionen, Level und Auszahlungen.', 'en' => 'Commissions, level and payouts.'],
        'shop' => ['it' => 'Materiali stampati con i suoi dati.', 'de' => 'Druckfertige Werbemittel mit deinen Daten.', 'en' => 'Print-ready materials with your details.'],
        'support' => ['it' => 'Domande a Vecom e risposte.', 'de' => 'Fragen an Vecom und Antworten.', 'en' => 'Questions to Vecom and answers.'],
        'voll' => ['it' => 'Trovare attività, prenotarle e scrivere loro — con testi pronti.', 'de' => 'Betriebe finden, reservieren und anschreiben — mit fertigen Texten.', 'en' => 'Find businesses, reserve them and write to them — with ready-made texts.'],
    ];

    public const TEXTE = [
        'weiter' => ['it' => 'Avanti', 'de' => 'Weiter', 'en' => 'Next'],
        'zurueck' => ['it' => 'Indietro', 'de' => 'Zurück', 'en' => 'Back'],
        'ueberspringen' => ['it' => 'Salta', 'de' => 'Überspringen', 'en' => 'Skip'],
        'fertig' => ['it' => 'Iniziamo', 'de' => 'Los geht’s', 'en' => 'Let’s go'],
        'von' => ['it' => '{a} di {b}', 'de' => '{a} von {b}', 'en' => '{a} of {b}'],
        'fehlt' => ['it' => 'Questo punto appare appena c’è qualcosa da mostrare.', 'de' => 'Diese Stelle erscheint, sobald es hier etwas gibt.', 'en' => 'This spot appears as soon as there is something here.'],
        'hilfe' => ['it' => 'Aiuto', 'de' => 'Hilfe', 'en' => 'Help'],
        'hilfe_aria' => ['it' => 'Aiuto per questa pagina', 'de' => 'Hilfe zu dieser Seite', 'en' => 'Help for this page'],
        'tour_bereich' => ['it' => 'Mi mostri questa sezione', 'de' => 'Zeig mir diesen Bereich', 'en' => 'Show me this section'],
        'tour_ganz' => ['it' => 'Giro completo', 'de' => 'Ganze Einführung', 'en' => 'Full introduction'],
        'schliessen' => ['it' => 'Chiudi', 'de' => 'Schließen', 'en' => 'Close'],
        'cl_titel' => ['it' => 'I suoi primi 7 giorni', 'de' => 'Deine ersten 7 Tage', 'en' => 'Your first 7 days'],
        'cl_stand' => ['it' => '{n} di 7 fatti', 'de' => '{n} von 7 erledigt', 'en' => '{n} of 7 done'],
        'cl_zeig' => ['it' => 'Mi mostri dove', 'de' => 'Zeig mir, wo', 'en' => 'Show me where'],
        'seite_oeffnen' => ['it' => 'Apra la sua pagina', 'de' => 'Meine Seite ansehen', 'en' => 'View my page'],
    ];

    /** Die Checkliste: Schlüssel → Titel und wo „Zeig mir, wo“ hinführt (Tour + Schritt). */
    public const CHECKLISTE = [
        'link' => ['tour' => 'marketing', 'ab' => 'link', 'titel' => ['it' => 'Copiare il suo link', 'de' => 'Deinen Link kopieren', 'en' => 'Copy your link']],
        'profil' => ['tour' => 'profil', 'ab' => 'profil', 'titel' => ['it' => 'Completare il profilo', 'de' => 'Profil ausfüllen', 'en' => 'Complete your profile']],
        'seite' => ['tour' => 'seite', 'ab' => 'seite', 'titel' => ['it' => 'Guardare la sua pagina', 'de' => 'Deine Partnerseite ansehen', 'en' => 'Look at your partner page']],
        'reserviert' => ['tour' => 'finden', 'ab' => 'suche', 'titel' => ['it' => 'Prenotare la prima attività', 'de' => 'Ersten Betrieb reservieren', 'en' => 'Reserve your first business']],
        'nachricht' => ['tour' => 'finden', 'ab' => 'meine', 'titel' => ['it' => 'Mandare il primo messaggio', 'de' => 'Erste Nachricht verschicken', 'en' => 'Send your first message']],
        'geteilt' => ['tour' => 'marketing', 'ab' => 'kopieren', 'titel' => ['it' => 'Condividere un post', 'de' => 'Einen Beitrag teilen', 'en' => 'Share a post']],
        'app' => ['tour' => 'app', 'ab' => 'app', 'titel' => ['it' => 'Installare l’app', 'de' => 'App installieren', 'en' => 'Install the app']],
    ];

    private static function t(array $x, string $sp): string { return (string) ($x[$sp] ?? $x['de'] ?? reset($x)); }

    /** @return array<string,string> art → wert (tour:haupt → fertig, ev:geteilt → 1) */
    public static function stand(int $pid): array
    {
        try {
            $aus = [];
            foreach (Db::all('SELECT art, wert FROM partner_einstieg WHERE partner_id = ?', [$pid]) as $r) { $aus[(string) $r['art']] = (string) $r['wert']; }
            return $aus;
        } catch (Throwable $e) { return []; }   // vor Migration 201
    }

    /** Vom Browser: Tour fertig/übersprungen oder ein Ereignis der Checkliste. @return bool gespeichert */
    public static function melden(int $pid, string $tat, array $d): bool
    {
        try {
            if ($tat === 'tour_stand') {
                $tour = (string) ($d['tour'] ?? '');
                $st = (string) ($d['status'] ?? '');
                if (!isset(self::TOUREN[$tour]) || !in_array($st, ['fertig', 'uebersprungen'], true)) { return false; }
                /* „fertig“ schlägt „übersprungen“, nie umgekehrt — wer die Tour später doch zu Ende sieht, hat sie gesehen. */
                Db::run("INSERT INTO partner_einstieg (partner_id, art, wert, am) VALUES (?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE wert = IF(wert = 'fertig', wert, VALUES(wert)), am = NOW()", [$pid, 'tour:' . $tour, $st]);
                return true;
            }
            if ($tat === 'tour_ev') {
                $ev = (string) ($d['ev'] ?? '');
                if (!in_array($ev, self::EREIGNISSE, true)) { return false; }
                Db::run('INSERT IGNORE INTO partner_einstieg (partner_id, art, wert, am) VALUES (?, ?, ?, NOW())', [$pid, 'ev:' . $ev, '1']);
                return true;
            }
        } catch (Throwable $e) { }
        return false;
    }

    /**
     * Die sieben Punkte, aus echten Daten — der Browser-Klick zählt zusätzlich, ersetzt aber keine vorhandene Spur.
     * @return array{punkte:list<array{k:string,titel:string,erledigt:bool,tour:string,ab:string}>, n:int}
     */
    public static function checkliste(array $p, string $sp = 'de'): array
    {
        $pid = (int) $p['id'];
        $s = self::stand($pid);
        $w = static fn(string $sql, array $a = []): int => (int) (static function () use ($sql, $a) { try { return Db::wert($sql, $a, 0); } catch (Throwable $e) { return 0; } })();
        require_once __DIR__ . '/PartnerStart.php';
        require_once __DIR__ . '/PartnerCommand.php';
        $st = (static function () use ($p) { try { return PartnerStart::schritte($p)['erledigt']; } catch (Throwable $e) { return []; } })();
        $pf = (static function () use ($p) { try { return PartnerCommand::profil($p)['fertig']; } catch (Throwable $e) { return false; } })();
        $e = [
            'link' => isset($s['ev:link_kopiert']) || !empty($st['teilen']),
            'profil' => (bool) $pf,
            'seite' => isset($s['ev:seite']),
            'reserviert' => $w('SELECT COUNT(*) FROM partner_reservierungen WHERE partner_id = ?', [$pid]) > 0
                || $w('SELECT COUNT(*) FROM partner_entscheide WHERE partner_id = ?', [$pid]) > 0
                || $w('SELECT COUNT(*) FROM partner_leads WHERE partner_id = ? AND firma_id IS NOT NULL', [$pid]) > 0,
            'nachricht' => isset($s['ev:nachricht'])
                || $w('SELECT COUNT(*) FROM partner_reservierungen WHERE partner_id = ? AND angeschrieben_am IS NOT NULL', [$pid]) > 0
                || $w("SELECT COUNT(*) FROM partner_lead_verlauf WHERE partner_id = ? AND art IN ('whatsapp','email','anruf')", [$pid]) > 0,
            'geteilt' => isset($s['ev:geteilt']) || !empty($st['teilen']),
            'app' => isset($s['ev:app']) || !empty($st['app']),
        ];
        $punkte = [];
        foreach (self::CHECKLISTE as $k => $c) {
            $punkte[] = ['k' => $k, 'titel' => self::t($c['titel'], $sp), 'erledigt' => (bool) $e[$k], 'tour' => $c['tour'], 'ab' => $c['ab']];
        }
        return ['punkte' => $punkte, 'n' => count(array_filter($e))];
    }

    /**
     * Alles, was partner-tour.js braucht — als JSON in die Seite (kein Skript im HTML).
     * @param array<string,string> $urls seite → Adresse (ohne tour-Parameter)
     */
    public static function daten(array $p, string $seite, string $sp, array $urls, ?string $meldenUrl, bool $nurLesen): array
    {
        $stand = self::stand((int) $p['id']);
        $tourHier = self::TOUR_JE_SEITE[$seite] ?? null;
        $auto = null;
        if (!$nurLesen && $tourHier !== null && !isset($stand['tour:' . $tourHier])) {
            /* Auf dem Start erst die große Tour; auf anderen Seiten die Mini-Tour des Bereichs beim ersten Besuch. */
            $auto = $tourHier;
        }
        $start = null;
        $tq = (string) ($_GET['tour'] ?? '');
        if ($tq !== '' && isset(self::TOUREN[$tq])) {
            $ab = (string) ($_GET['ab'] ?? '');
            $start = ['tour' => $tq, 'ab' => preg_match('~^[a-z_]{1,20}$~', $ab) ? $ab : ''];
            $auto = null;
        }
        $touren = [];
        foreach (self::TOUREN as $tk => $schritte) {
            $touren[$tk] = array_map(static fn(array $s): array => ['k' => $s['k'], 'seite' => $s['seite'], 'ziel' => $s['ziel'],
                'titel' => self::t($s['titel'], $sp), 'text' => self::t($s['text'], $sp)], $schritte);
        }
        $texte = [];
        foreach (self::TEXTE as $k => $x) { $texte[$k] = self::t($x, $sp); }
        return [
            'seite' => $seite, 'auto' => $auto, 'start' => $start, 'tour_hier' => $tourHier, 'touren' => $touren, 'urls' => $urls,
            'texte' => $texte, 'hilfe' => self::t(self::HILFE[$seite] ?? self::HILFE['start'], $sp),
            'melden' => $nurLesen ? null : $meldenUrl, 'csrf' => $nurLesen ? '' : (string) ($_SESSION['csrf'] ?? ''),
            'code' => (string) $p['code'],
        ];
    }

    /** Adresse für „Zeig mir, wo“: die Seite des Schritts mit ?tour=…&ab=… (der Anker bleibt hinten). */
    public static function zeigUrl(array $urls, string $tour, string $ab): string
    {
        $seite = 'start';
        foreach (self::TOUREN[$tour] ?? [] as $s) { if ($s['k'] === $ab) { $seite = $s['seite']; break; } }
        $u = (string) ($urls[$seite] ?? ($urls['start'] ?? ''));
        [$vorne, $anker] = array_pad(explode('#', $u, 2), 2, '');
        return $vorne . (str_contains($vorne, '?') ? '&' : '?') . 'tour=' . rawurlencode($tour) . '&ab=' . rawurlencode($ab) . ($anker !== '' ? '#' . $anker : '');
    }

    /** Die Adressen der Seiten (Command Center und Partnerseite). */
    public static function urls(callable $selbst, bool $shop): array
    {
        $u = [
            'start' => $selbst(['cc' => 1]), 'kunden' => $selbst(['cc' => 1, 'kunden' => 1]),
            'marketing' => $selbst(['cc' => 1, 'marketing' => 1, 'teil' => 'assistent']),
            'marketing_link' => $selbst(['cc' => 1, 'marketing' => 1, 'teil' => 'kampagnen']) . '#kurzlink',
            'ergebnisse' => $selbst(['cc' => 1, 'ergebnisse' => 1]), 'support' => $selbst(['cc' => 1, 'support' => 1]),
            'voll' => $selbst() . '#recherche', 'voll_seite' => $selbst() . '#seite', 'voll_app' => $selbst() . '#app',
        ];
        if ($shop) { $u['shop'] = $selbst(['cc' => 1, 'shop' => 1]); }
        return $u;
    }

    /** Das JSON sicher ins HTML (kein </script> im Text, kein Skript ausführbar). */
    public static function json(array $d): string
    {
        return '<script type="application/json" id="tour-daten">'
            . json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
    }

    /** Für die Partner-Akte in der Verwaltung: Touren und Checkliste. */
    public static function fuerAdmin(array $p): array
    {
        $s = self::stand((int) $p['id']);
        $touren = [];
        foreach (array_keys(self::TOUREN) as $tk) { $touren[$tk] = $s['tour:' . $tk] ?? null; }
        return ['touren' => $touren, 'checkliste' => self::checkliste($p, 'de')];
    }
}
