/* ==========================================================================
   Landeseiten — Provinz, Branchen, Ratgeber (26.09.2026, Uwe: „alles“).

   build.mjs setzt jede dieser Seiten in das fertig gebaute Gerüst der
   Preisseite (Kopf, Fuß, Sprachwahl, Stil) und trägt sie in die Sitemap ein.

   REGELN FÜR DIESE TEXTE (VECOM-STANDARD: „niemals erfunden“)
   - Keine Kunden, Orte oder Zahlen, die nicht belegt sind. Einzige genannte
     Arbeit ist Cavaleri Trasporti -- sie steht als Fallstudie auf der
     Startseite.
   - Keine Preise im Text: Sie kommen live aus dem Baukasten und stehen auf
     der Preisseite. Hier steht der Weg dorthin, nicht eine Zahl, die veraltet.
   - Keine sechs fast gleichen Ortsseiten (Uwe, 26.09.: „eine starke
     Provinzseite“) -- Google wertet so etwas als Brückenseiten.
   - Stimme der Website: erste Person, IT „Lei“, DE „Sie“.
   ========================================================================== */

/* Stand der Texte -- lastmod in der Sitemap und dateModified im Ratgeber. */
export const LANDESEITEN_STAND = '2026-09-26';

export const LANDESEITEN = [
  /* ---------------------------------------------------------------- Provinz */
  {
    schluessel: 'provinz',
    art: 'Service',
    ziele: { it: 'siti-web-agrigento.html', de: 'de/websites-agrigent.html', en: 'en/websites-agrigento.html' },
    it: {
      titel: 'Siti web in provincia di Agrigento — da Aragona | Vecom Design',
      desc: 'Siti su misura per attività della provincia di Agrigento: da Aragona, in italiano, tedesco e inglese. Prezzo chiaro prima di iniziare, una persona che la segue.',
      kicker: 'Provincia di Agrigento', h1: 'Siti web per le attività della provincia di Agrigento',
      lead: 'Lavoro da Aragona per bar, ristoranti, B&B, parrucchieri, artigiani e aziende di trasporto della provincia. Un sito che si fa trovare su Google quando qualcuno nella sua zona cerca quello che lei offre — e che le porta telefonate, non solo visite.',
      blocchi: [
        ['Perché un sito, se c’è già Facebook', 'Chi cerca «parrucchiere Canicattì» o «ristorante Porto Empedocle» su Google trova prima i siti e le schede di Google, poi forse una pagina Facebook. Un sito suo, con indirizzo, orari, telefono e foto vere, è la cosa che Google capisce meglio — e che resta sua, qualunque cosa decida Facebook domani.'],
        ['Dove lavoro', null, ['Agrigento, Aragona, Favara, Raffadali, Porto Empedocle', 'Canicattì, Naro, Ravanusa, Campobello di Licata', 'Licata, Palma di Montechiaro', 'Sciacca, Ribera, Menfi', 'Realmonte, Siculiana — e il resto della provincia']],
        ['Come funziona', null, ['Inserisce la sua e-mail e riceve il link alla sua dashboard personale — nessun account, nessuna password.', 'Otto domande brevi, poi vede subito la sua fascia di prezzo.', 'Riceve una proposta a prezzo fisso. Solo dopo il suo sì si inizia.', 'Vede ogni passo nella dashboard, carica logo, foto e testi. Il sito va online solo quando lei dice «va bene così».']],
        ['Tre lingue, se servono', 'La provincia vive anche di turisti. Se i suoi clienti parlano tedesco o inglese, il sito parla la loro lingua — con testi veri, non traduzioni automatiche. Sono tedesco e vivo in Sicilia: le due parti le conosco entrambe.'],
      ],
      faq: [
        ['Devo venire di persona?', 'No. Tutto passa dalla sua dashboard, per telefono o su WhatsApp. Se preferisce parlarne a voce, mi chiami o si faccia richiamare dalla pagina iniziale.'],
        ['Quanto costa?', 'Dipende da quante pagine, lingue e funzioni servono. Le cifre con cui faccio i preventivi sono pubbliche sulla pagina dei prezzi, e nel calcolatore vede la sua fascia in un minuto e mezzo.'],
        ['E dopo, chi se ne occupa?', 'Se vuole, io: con l’assistenza mensile, facoltativa e con contratto a parte. Senza, il sito funziona lo stesso ed è suo.'],
      ],
    },
    de: {
      titel: 'Websites in der Provinz Agrigent — aus Aragona | Vecom Design',
      desc: 'Websites nach Maß für Betriebe in der Provinz Agrigent: aus Aragona, auf Italienisch, Deutsch und Englisch. Klarer Preis vor dem Start, ein Mensch, der Sie begleitet.',
      kicker: 'Provinz Agrigent', h1: 'Websites für Betriebe in der Provinz Agrigent',
      lead: 'Ich arbeite von Aragona aus für Bars, Restaurants, Ferienwohnungen, Friseure, Handwerker und Transportfirmen der Provinz. Eine Website, die bei Google gefunden wird, wenn jemand in Ihrer Gegend sucht, was Sie anbieten — und die Anrufe bringt, nicht nur Besuche.',
      blocchi: [
        ['Warum eine Website, wenn es Facebook gibt', 'Wer „Friseur Canicattì“ oder „Restaurant Porto Empedocle“ bei Google sucht, findet zuerst Websites und Google-Einträge, dann vielleicht eine Facebook-Seite. Eine eigene Seite mit Adresse, Öffnungszeiten, Telefon und echten Fotos versteht Google am besten — und sie bleibt Ihre, egal was Facebook morgen entscheidet.'],
        ['Wo ich arbeite', null, ['Agrigent, Aragona, Favara, Raffadali, Porto Empedocle', 'Canicattì, Naro, Ravanusa, Campobello di Licata', 'Licata, Palma di Montechiaro', 'Sciacca, Ribera, Menfi', 'Realmonte, Siculiana — und der Rest der Provinz']],
        ['So läuft es', null, ['Sie tragen Ihre E-Mail ein und bekommen den Link zu Ihrem persönlichen Bereich — kein Konto, kein Passwort.', 'Acht kurze Fragen, dann sehen Sie sofort Ihre Preisspanne.', 'Sie bekommen ein Angebot zum festen Preis. Erst nach Ihrem Ja geht es los.', 'Jeden Schritt sehen Sie in Ihrem Bereich und laden Logo, Fotos und Texte hoch. Online geht die Seite erst, wenn Sie sagen: So passt es.']],
        ['Drei Sprachen, wenn nötig', 'Die Provinz lebt auch von Gästen. Sprechen Ihre Kunden Deutsch oder Englisch, spricht die Seite ihre Sprache — mit echten Texten, nicht maschinell übersetzt. Ich bin Deutscher und lebe in Sizilien: Ich kenne beide Seiten.'],
      ],
      faq: [
        ['Muss ich persönlich vorbeikommen?', 'Nein. Alles läuft über Ihren Bereich, per Telefon oder WhatsApp. Wenn Sie lieber sprechen, rufen Sie an oder lassen Sie sich über die Startseite zurückrufen.'],
        ['Was kostet das?', 'Das hängt von Seiten, Sprachen und Funktionen ab. Die Zahlen, mit denen ich rechne, stehen offen auf der Preisseite, und im Rechner sehen Sie Ihre Spanne in anderthalb Minuten.'],
        ['Und wer kümmert sich danach?', 'Wenn Sie möchten, ich: mit der monatlichen Betreuung, freiwillig und als eigener Vertrag. Ohne sie läuft die Seite trotzdem und gehört Ihnen.'],
      ],
    },
    en: {
      titel: 'Websites in the province of Agrigento — from Aragona | Vecom Design',
      desc: 'Custom websites for businesses in the province of Agrigento: from Aragona, in Italian, German and English. A clear price before we start, one person who looks after you.',
      kicker: 'Province of Agrigento', h1: 'Websites for businesses in the province of Agrigento',
      lead: 'I work from Aragona for bars, restaurants, holiday rentals, hairdressers, tradespeople and transport companies across the province. A website that Google shows when someone nearby searches for what you offer — and that brings phone calls, not just visits.',
      blocchi: [
        ['Why a website when there is Facebook', 'Someone searching “hairdresser Canicattì” or “restaurant Porto Empedocle” on Google finds websites and Google listings first, and maybe a Facebook page after that. A site of your own with address, opening hours, phone and real photos is what Google understands best — and it stays yours, whatever Facebook decides tomorrow.'],
        ['Where I work', null, ['Agrigento, Aragona, Favara, Raffadali, Porto Empedocle', 'Canicattì, Naro, Ravanusa, Campobello di Licata', 'Licata, Palma di Montechiaro', 'Sciacca, Ribera, Menfi', 'Realmonte, Siculiana — and the rest of the province']],
        ['How it works', null, ['You enter your e-mail and get the link to your personal dashboard — no account, no password.', 'Eight short questions, then you see your price range straight away.', 'You get a fixed-price proposal. We only start after your yes.', 'You follow every step in the dashboard and upload logo, photos and texts. The site goes live only when you say it is right.']],
        ['Three languages, if you need them', 'The province also lives from visitors. If your customers speak German or English, the site speaks their language — with real texts, not machine translation. I am German and live in Sicily: I know both sides.'],
      ],
      faq: [
        ['Do I have to come in person?', 'No. Everything runs through your dashboard, by phone or on WhatsApp. If you would rather talk, call me or request a call back on the home page.'],
        ['What does it cost?', 'It depends on pages, languages and features. The figures I quote with are public on the pricing page, and the calculator shows your range in a minute and a half.'],
        ['Who looks after it afterwards?', 'If you like, I do: with the monthly care plan, optional and a separate contract. Without it the site still works and belongs to you.'],
      ],
    },
  },

  /* ------------------------------------------------------------- Ristoranti */
  {
    schluessel: 'ristoranti', art: 'Service',
    ziele: { it: 'siti-web-ristoranti.html', de: 'de/websites-restaurants.html', en: 'en/websites-restaurants.html' },
    it: {
      titel: 'Sito web per ristoranti, bar e pizzerie in Sicilia | Vecom Design',
      desc: 'Un sito per il suo ristorante: menu sempre aggiornato, orari, prenotazioni, foto vere e testi anche in tedesco e inglese per i turisti.',
      kicker: 'Ristoranti, bar, pizzerie', h1: 'Il sito per il suo ristorante',
      lead: 'Chi cerca dove mangiare decide in pochi secondi, quasi sempre dal telefono: cosa c’è nel menu, quanto costa, è aperto adesso, come ci arrivo. Il suo sito risponde a queste quattro domande prima che il cliente passi al locale accanto.',
      blocchi: [
        ['Cosa può avere', null, ['Il menu online, facile da aggiornare, con prezzi e allergeni', 'Orari, telefono e mappa in alto — senza cercare', 'Richiesta di prenotazione del tavolo', 'Foto vere dei piatti e della sala', 'Italiano, tedesco e inglese per i turisti']],
        ['Da provare subito', 'Sulla pagina iniziale c’è una demo per la gastronomia: scelga «La mia attività è …», poi il ristorante, e apra l’esempio. Così vede come si comporta un sito del genere sul suo telefono, prima di parlarne.'],
        ['Il prezzo', 'Un menu da consultare, le prenotazioni e le lingue fanno parte del calcolo. Sulla pagina dei prezzi trova le cifre voce per voce; nel calcolatore vede la sua fascia in un minuto e mezzo.'],
      ],
      demo: '#betrieb',
      faq: [
        ['Posso cambiare il menu da solo?', 'Sì, se vuole: si stabilisce nel preventivo. Oppure me lo manda e lo aggiorno io con l’assistenza mensile.'],
        ['Serve il sito se sono su TripAdvisor e Google?', 'Le schede servono per farsi trovare, il sito per convincere: lì decide lei cosa si vede, e nessuno mette il concorrente accanto.'],
      ],
    },
    de: {
      titel: 'Website für Restaurants, Bars und Pizzerien in Sizilien | Vecom Design',
      desc: 'Eine Website für Ihr Restaurant: aktuelle Speisekarte, Öffnungszeiten, Reservierung, echte Fotos und Texte auch auf Deutsch und Englisch für Gäste.',
      kicker: 'Restaurants, Bars, Pizzerien', h1: 'Die Website für Ihr Restaurant',
      lead: 'Wer ein Lokal sucht, entscheidet in Sekunden, fast immer am Handy: Was steht auf der Karte, was kostet es, ist jetzt offen, wie komme ich hin. Ihre Seite beantwortet diese vier Fragen, bevor der Gast zum Nachbarn weitergeht.',
      blocchi: [
        ['Was sie haben kann', null, ['Die Speisekarte online, leicht zu ändern, mit Preisen und Allergenen', 'Öffnungszeiten, Telefon und Karte ganz oben — ohne Suchen', 'Tischreservierung als Anfrage', 'Echte Fotos von Gerichten und Gastraum', 'Italienisch, Deutsch und Englisch für Gäste']],
        ['Gleich ausprobieren', 'Auf der Startseite gibt es eine Demo für die Gastronomie: bei „Mein Betrieb ist …“ das Restaurant wählen und das Beispiel öffnen. So sehen Sie auf Ihrem Handy, wie sich so eine Seite anfühlt, bevor wir darüber sprechen.'],
        ['Der Preis', 'Eine Speisekarte, Reservierungen und Sprachen gehen in die Rechnung ein. Auf der Preisseite stehen die Zahlen Posten für Posten; im Rechner sehen Sie Ihre Spanne in anderthalb Minuten.'],
      ],
      demo: '#betrieb',
      faq: [
        ['Kann ich die Karte selbst ändern?', 'Ja, wenn Sie möchten — das wird im Angebot festgelegt. Oder Sie schicken sie mir, und ich ändere sie mit der monatlichen Betreuung.'],
        ['Brauche ich eine Website, wenn ich bei TripAdvisor und Google stehe?', 'Die Einträge sorgen dafür, dass man Sie findet, die Website dafür, dass man kommt: Dort entscheiden Sie, was man sieht, und niemand stellt die Konkurrenz daneben.'],
      ],
    },
    en: {
      titel: 'Website for restaurants, bars and pizzerias in Sicily | Vecom Design',
      desc: 'A website for your restaurant: an up-to-date menu, opening hours, table requests, real photos and texts in German and English for visitors.',
      kicker: 'Restaurants, bars, pizzerias', h1: 'The website for your restaurant',
      lead: 'People looking for somewhere to eat decide in seconds, almost always on their phone: what is on the menu, what does it cost, is it open now, how do I get there. Your site answers those four questions before the guest moves on to the place next door.',
      blocchi: [
        ['What it can include', null, ['The menu online, easy to update, with prices and allergens', 'Opening hours, phone and map at the top — no searching', 'Table requests', 'Real photos of the dishes and the room', 'Italian, German and English for visitors']],
        ['Try it now', 'On the home page there is a demo for restaurants: under “My business is …” pick the restaurant and open the example. You will see how such a site feels on your phone before we even talk.'],
        ['The price', 'A menu, table requests and languages go into the calculation. The pricing page lists the figures item by item; the calculator shows your range in a minute and a half.'],
      ],
      demo: '#betrieb',
      faq: [
        ['Can I change the menu myself?', 'Yes, if you want to — we agree that in the proposal. Or you send it to me and I update it under the monthly care plan.'],
        ['Do I need a website if I am on TripAdvisor and Google?', 'Listings get you found, the website gets people to come: there you decide what they see, and nobody puts a competitor next to you.'],
      ],
    },
  },

  /* ---------------------------------------------------- B&B, case vacanza */
  {
    schluessel: 'bb', art: 'Service',
    ziele: { it: 'siti-web-bed-and-breakfast.html', de: 'de/websites-ferienwohnungen.html', en: 'en/websites-holiday-rentals.html' },
    it: {
      titel: 'Sito web per B&B e case vacanza in Sicilia | Vecom Design',
      desc: 'Un sito per il suo B&B o la sua casa vacanza: camere con foto vere, richiesta di prenotazione diretta, tre lingue per gli ospiti stranieri.',
      kicker: 'B&B, case vacanza, affittacamere', h1: 'Il sito per il suo B&B',
      lead: 'Molti ospiti la trovano su Booking o Airbnb — e poi la cercano su Google per nome, per guardare meglio. Lì deve esserci lei, con le sue foto e un modo semplice per chiedere direttamente.',
      blocchi: [
        ['Cosa può avere', null, ['Le camere con foto vere, dotazioni e prezzi indicativi', 'Richiesta di prenotazione diretta (e-mail o WhatsApp)', 'Come arrivare, cosa vedere nei dintorni', 'Italiano, tedesco e inglese', 'Il collegamento alle sue recensioni']],
        ['E Booking?', 'Non deve scegliere. Il sito non sostituisce Booking: lo affianca. Chi è già stato da lei, o la trova per nome, può prenotare direttamente — senza commissione. Ne parlo più a fondo nella guida qui sotto.'],
        ['Il prezzo', 'Le lingue e la richiesta di prenotazione fanno parte del calcolo. Le cifre sono sulla pagina dei prezzi; nel calcolatore vede la sua fascia.'],
      ],
      guida: 'booking',
      faq: [
        ['Serve un calendario delle disponibilità?', 'Non sempre. Per poche camere basta spesso una richiesta con le date: risponde lei, e non rischia doppie prenotazioni con Booking.'],
        ['Posso cambiare foto e prezzi da solo?', 'Sì, se lo prevediamo nel preventivo — oppure lo faccio io con l’assistenza mensile.'],
      ],
    },
    de: {
      titel: 'Website für B&B und Ferienwohnungen in Sizilien | Vecom Design',
      desc: 'Eine Website für Ihr B&B oder Ihre Ferienwohnung: Zimmer mit echten Fotos, direkte Buchungsanfrage, drei Sprachen für Gäste aus dem Ausland.',
      kicker: 'B&B, Ferienwohnungen, Zimmervermietung', h1: 'Die Website für Ihre Ferienwohnung',
      lead: 'Viele Gäste finden Sie bei Booking oder Airbnb — und suchen Sie dann bei Google nach Namen, um genauer hinzusehen. Dort sollten Sie selbst stehen, mit Ihren Fotos und einem einfachen Weg, direkt anzufragen.',
      blocchi: [
        ['Was sie haben kann', null, ['Die Zimmer mit echten Fotos, Ausstattung und Richtpreisen', 'Direkte Buchungsanfrage (E-Mail oder WhatsApp)', 'Anreise und was es in der Umgebung zu sehen gibt', 'Italienisch, Deutsch und Englisch', 'Der Weg zu Ihren Bewertungen']],
        ['Und Booking?', 'Sie müssen sich nicht entscheiden. Die Website ersetzt Booking nicht, sie steht daneben. Wer schon bei Ihnen war oder Sie beim Namen findet, bucht direkt — ohne Provision. Mehr dazu im Ratgeber unten.'],
        ['Der Preis', 'Sprachen und Buchungsanfrage gehen in die Rechnung ein. Die Zahlen stehen auf der Preisseite; im Rechner sehen Sie Ihre Spanne.'],
      ],
      guida: 'booking',
      faq: [
        ['Brauche ich einen Belegungskalender?', 'Nicht immer. Bei wenigen Zimmern reicht oft eine Anfrage mit Daten: Sie antworten selbst und riskieren keine Doppelbuchung mit Booking.'],
        ['Kann ich Fotos und Preise selbst ändern?', 'Ja, wenn wir es im Angebot vorsehen — oder ich mache es mit der monatlichen Betreuung.'],
      ],
    },
    en: {
      titel: 'Website for B&Bs and holiday rentals in Sicily | Vecom Design',
      desc: 'A website for your B&B or holiday rental: rooms with real photos, direct booking requests and three languages for guests from abroad.',
      kicker: 'B&Bs, holiday rentals, guest rooms', h1: 'The website for your B&B',
      lead: 'Many guests find you on Booking or Airbnb — and then search for you by name on Google to take a closer look. You should be the one they find there, with your photos and a simple way to ask directly.',
      blocchi: [
        ['What it can include', null, ['Rooms with real photos, amenities and guide prices', 'Direct booking requests (e-mail or WhatsApp)', 'How to get there and what to see nearby', 'Italian, German and English', 'A link to your reviews']],
        ['And Booking?', 'You do not have to choose. The website does not replace Booking, it sits next to it. Guests who have stayed before, or who find you by name, can book directly — without commission. More on that in the guide below.'],
        ['The price', 'Languages and booking requests go into the calculation. The figures are on the pricing page; the calculator shows your range.'],
      ],
      guida: 'booking',
      faq: [
        ['Do I need an availability calendar?', 'Not always. With a few rooms a request with dates is often enough: you reply yourself and avoid double bookings with Booking.'],
        ['Can I change photos and prices myself?', 'Yes, if we plan for it in the proposal — or I do it under the monthly care plan.'],
      ],
    },
  },

  /* ------------------------------------------------------------ Parrucchieri */
  {
    schluessel: 'parrucchieri', art: 'Service',
    ziele: { it: 'siti-web-parrucchieri.html', de: 'de/websites-friseure.html', en: 'en/websites-hairdressers.html' },
    it: {
      titel: 'Sito web per parrucchieri ed estetiste | Vecom Design',
      desc: 'Un sito per il suo salone: servizi e prezzi, lavori fatti, richiesta di appuntamento dal telefono. Da Aragona, per la provincia di Agrigento.',
      kicker: 'Parrucchieri, barbieri, estetiste', h1: 'Il sito per il suo salone',
      lead: 'Nel suo mestiere si sceglie con gli occhi. Chi cerca un parrucchiere nuovo vuole vedere i lavori, sapere quanto costa un colore e fissare un appuntamento senza telefonare mentre lei ha le mani nei capelli di qualcuno.',
      blocchi: [
        ['Cosa può avere', null, ['Servizi e prezzi, chiari e aggiornati', 'Una galleria dei suoi lavori — prima e dopo, se vuole', 'Richiesta di appuntamento dal telefono', 'Orari, indirizzo e WhatsApp in un tocco', 'Il collegamento a Instagram']],
        ['Da provare subito', 'Sulla pagina iniziale c’è la consulenza colore: si sceglie un colore e lo si vede su capelli calcolati ciocca per ciocca, girando la testa — poi si prenota con quel colore. È il tipo di cosa che fa restare una cliente sul sito.'],
        ['Il prezzo', 'Galleria e richiesta di appuntamento fanno parte del calcolo. Le cifre sono sulla pagina dei prezzi; nel calcolatore vede la sua fascia.'],
      ],
      demo: '#haarfarben',
      faq: [
        ['Ho già Instagram, perché un sito?', 'Instagram mostra, il sito fa prenotare: prezzi, orari e appuntamento in un posto solo — e su Google si trova il sito, non il profilo.'],
        ['Le prenotazioni arrivano a me?', 'Sì, come richiesta con giorno e ora: conferma lei. Nessun calendario da tenere allineato se non lo desidera.'],
      ],
    },
    de: {
      titel: 'Website für Friseure und Kosmetik | Vecom Design',
      desc: 'Eine Website für Ihren Salon: Leistungen und Preise, gezeigte Arbeiten, Terminanfrage vom Handy. Aus Aragona, für die Provinz Agrigent.',
      kicker: 'Friseure, Barbiere, Kosmetik', h1: 'Die Website für Ihren Salon',
      lead: 'In Ihrem Beruf wählt man mit den Augen. Wer einen neuen Friseur sucht, will Arbeiten sehen, wissen, was eine Farbe kostet, und einen Termin machen, ohne anzurufen, während Sie gerade die Hände in fremden Haaren haben.',
      blocchi: [
        ['Was sie haben kann', null, ['Leistungen und Preise, klar und aktuell', 'Eine Galerie Ihrer Arbeiten — auf Wunsch vorher/nachher', 'Terminanfrage vom Handy', 'Öffnungszeiten, Adresse und WhatsApp mit einem Tipp', 'Der Weg zu Ihrem Instagram']],
        ['Gleich ausprobieren', 'Auf der Startseite gibt es die Farbberatung: eine Farbe wählen und an Strähne für Strähne berechnetem Haar sehen, den Kopf dabei drehen — und mit dieser Farbe buchen. Genau so etwas hält eine Kundin auf der Seite.'],
        ['Der Preis', 'Galerie und Terminanfrage gehen in die Rechnung ein. Die Zahlen stehen auf der Preisseite; im Rechner sehen Sie Ihre Spanne.'],
      ],
      demo: '#haarfarben',
      faq: [
        ['Ich habe schon Instagram — warum eine Website?', 'Instagram zeigt, die Website bringt Termine: Preise, Öffnungszeiten und Anfrage an einem Ort — und bei Google findet man die Seite, nicht das Profil.'],
        ['Kommen die Anfragen bei mir an?', 'Ja, als Anfrage mit Tag und Uhrzeit: Sie bestätigen. Kein Kalender, den Sie pflegen müssen, wenn Sie das nicht wollen.'],
      ],
    },
    en: {
      titel: 'Website for hairdressers and beauty salons | Vecom Design',
      desc: 'A website for your salon: services and prices, your work on show, appointment requests from the phone. From Aragona, for the province of Agrigento.',
      kicker: 'Hairdressers, barbers, beauty', h1: 'The website for your salon',
      lead: 'In your trade people choose with their eyes. Someone looking for a new hairdresser wants to see your work, know what a colour costs and book without phoning while your hands are in someone’s hair.',
      blocchi: [
        ['What it can include', null, ['Services and prices, clear and up to date', 'A gallery of your work — before and after, if you like', 'Appointment requests from the phone', 'Opening hours, address and WhatsApp in one tap', 'A link to your Instagram']],
        ['Try it now', 'On the home page there is the colour consultation: pick a shade and see it on hair rendered strand by strand, turning the head — then book with that colour. That is the kind of thing that keeps a client on the site.'],
        ['The price', 'Gallery and appointment requests go into the calculation. The figures are on the pricing page; the calculator shows your range.'],
      ],
      demo: '#haarfarben',
      faq: [
        ['I already have Instagram — why a website?', 'Instagram shows, the website books: prices, hours and requests in one place — and Google shows the site, not the profile.'],
        ['Do the requests come to me?', 'Yes, as a request with day and time: you confirm. No calendar to keep in sync unless you want one.'],
      ],
    },
  },

  /* --------------------------------------------------------------- Artigiani */
  {
    schluessel: 'artigiani', art: 'Service',
    ziele: { it: 'siti-web-artigiani.html', de: 'de/websites-handwerk.html', en: 'en/websites-tradespeople.html' },
    it: {
      titel: 'Sito web per artigiani e imprese edili | Vecom Design',
      desc: 'Un sito per chi lavora con le mani: lavori realizzati, zona servita, richiesta di preventivo e il telefono sempre in vista.',
      kicker: 'Artigiani, imprese, falegnami, impiantisti', h1: 'Il sito per il suo mestiere',
      lead: 'Chi ha un tetto che perde o una cucina da rifare cerca su Google, guarda due o tre siti e chiama il primo che lo convince. Convince chi mostra lavori veri, dice dove lavora e si fa chiamare con un tocco.',
      blocchi: [
        ['Cosa può avere', null, ['I lavori realizzati, con foto vere', 'I servizi e la zona in cui lavora', 'Il numero di telefono sempre in vista sul cellulare', 'Una richiesta di preventivo con foto allegate', 'Certificazioni e anni di esperienza — solo quello che c’è davvero']],
        ['Da provare subito', 'Sulla pagina iniziale c’è un progettatore di cucine: si sceglie la forma, si inseriscono le misure delle pareti e si dispongono i mobili, in scala. Per un falegname o chi fa cucine è il modo di far capire il lavoro prima del sopralluogo.'],
        ['Il prezzo', 'Per la maggior parte degli artigiani basta un sito di poche pagine. Le cifre sono sulla pagina dei prezzi; nel calcolatore vede la sua fascia.'],
      ],
      demo: '#kuechenplaner',
      faq: [
        ['Non ho belle foto dei lavori.', 'Bastano foto dal telefono, fatte con un po’ di luce. Le sistemo io; e ne aggiungiamo man mano.'],
        ['Mi arrivano solo richieste di preventivo inutili?', 'Si può chiedere già nel modulo la zona e il tipo di lavoro — così le richieste fuori zona le riconosce subito.'],
      ],
    },
    de: {
      titel: 'Website für Handwerker und Baubetriebe | Vecom Design',
      desc: 'Eine Website für alle, die mit den Händen arbeiten: gezeigte Arbeiten, Einsatzgebiet, Angebotsanfrage und die Telefonnummer immer im Blick.',
      kicker: 'Handwerk, Bau, Tischler, Installateure', h1: 'Die Website für Ihr Handwerk',
      lead: 'Wer ein undichtes Dach oder eine neue Küche braucht, sucht bei Google, schaut zwei, drei Seiten an und ruft den ersten an, der überzeugt. Überzeugend ist, wer echte Arbeiten zeigt, sagt, wo er arbeitet, und sich mit einem Tipp anrufen lässt.',
      blocchi: [
        ['Was sie haben kann', null, ['Ihre Arbeiten, mit echten Fotos', 'Leistungen und Einsatzgebiet', 'Die Telefonnummer am Handy immer sichtbar', 'Eine Angebotsanfrage mit Foto-Anhang', 'Zertifikate und Jahre Erfahrung — nur, was es wirklich gibt']],
        ['Gleich ausprobieren', 'Auf der Startseite gibt es einen Küchenplaner: Form wählen, Wandmaße eingeben, Möbel stellen — maßstabsgerecht. Für Tischler und Küchenbauer ist das der Weg, die Arbeit schon vor dem Termin vor Ort zu zeigen.'],
        ['Der Preis', 'Den meisten Handwerkern genügt eine Seite mit wenigen Unterseiten. Die Zahlen stehen auf der Preisseite; im Rechner sehen Sie Ihre Spanne.'],
      ],
      demo: '#kuechenplaner',
      faq: [
        ['Ich habe keine schönen Fotos meiner Arbeiten.', 'Handyfotos bei etwas Licht genügen. Ich bereite sie auf, und nach und nach kommen weitere dazu.'],
        ['Bekomme ich dann nur unpassende Anfragen?', 'Das Formular kann Ort und Art der Arbeit gleich mit abfragen — Anfragen außerhalb Ihres Gebiets erkennen Sie sofort.'],
      ],
    },
    en: {
      titel: 'Website for tradespeople and builders | Vecom Design',
      desc: 'A website for people who work with their hands: finished jobs, the area you cover, quote requests and your phone number always in view.',
      kicker: 'Trades, builders, carpenters, installers', h1: 'The website for your trade',
      lead: 'Someone with a leaking roof or a kitchen to redo searches on Google, looks at two or three sites and calls the first one that convinces. What convinces is real work on show, a clear service area and a phone number one tap away.',
      blocchi: [
        ['What it can include', null, ['Your finished jobs, with real photos', 'Services and the area you cover', 'Your phone number always visible on mobile', 'A quote request with photos attached', 'Certificates and years of experience — only what is real']],
        ['Try it now', 'On the home page there is a kitchen planner: choose the layout, enter the wall measurements and place the units, to scale. For carpenters and kitchen fitters it shows the work before the site visit.'],
        ['The price', 'Most tradespeople need a site of just a few pages. The figures are on the pricing page; the calculator shows your range.'],
      ],
      demo: '#kuechenplaner',
      faq: [
        ['I have no good photos of my work.', 'Phone photos in decent light are enough. I tidy them up, and we add more over time.'],
        ['Will I only get useless quote requests?', 'The form can ask for location and type of job up front — requests from outside your area are obvious at a glance.'],
      ],
    },
  },

  /* --------------------------------------------------------------- Trasporti */
  {
    schluessel: 'trasporti', art: 'Service',
    ziele: { it: 'siti-web-trasporti.html', de: 'de/websites-transport.html', en: 'en/websites-transport.html' },
    it: {
      titel: 'Sito web per aziende di trasporto e logistica | Vecom Design',
      desc: 'Un sito per la sua azienda di trasporti: servizi, mezzi, zone servite e richiesta di preventivo — anche in tedesco e inglese per i clienti esteri.',
      kicker: 'Trasporti, logistica, traslochi', h1: 'Il sito per la sua azienda di trasporti',
      lead: 'Chi deve spedire o traslocare confronta in fretta: fate questa tratta, avete il mezzo giusto, quanto costa, rispondete oggi? Il sito risponde prima della telefonata — e raccoglie le richieste già con i dati che le servono.',
      blocchi: [
        ['Cosa può avere', null, ['Servizi e tratte, spiegati semplici', 'I mezzi con portata e dimensioni', 'Richiesta di preventivo con partenza, arrivo, data e merce', 'Italiano, tedesco e inglese per i clienti esteri', 'Telefono e WhatsApp in un tocco']],
        ['Un lavoro fatto', 'Per Cavaleri Trasporti (Caltanissetta, dal 1974) ho costruito il sito: quattro attività in una pagina, la flotta vera al posto delle foto di repertorio, una sola richiesta per tutto, in tre lingue. È tra i lavori sulla pagina iniziale, con il link per vederlo dal vivo.'],
        ['Il prezzo', 'Lingue e richiesta di preventivo strutturata fanno parte del calcolo. Le cifre sono sulla pagina dei prezzi; nel calcolatore vede la sua fascia.'],
      ],
      demo: '#work',
      faq: [
        ['Le richieste arrivano complete?', 'Sì: il modulo chiede tratta, data, tipo e quantità di merce. Lei risponde con un prezzo, senza dover richiamare per chiedere i dati.'],
        ['Serve anche il tedesco?', 'Se lavora con clienti o partner all’estero, sì — e lo scrivo io, non un traduttore automatico.'],
      ],
    },
    de: {
      titel: 'Website für Transport- und Logistikunternehmen | Vecom Design',
      desc: 'Eine Website für Ihr Transportunternehmen: Leistungen, Fahrzeuge, Einsatzgebiete und Angebotsanfrage — auch auf Deutsch und Englisch für Kunden im Ausland.',
      kicker: 'Transport, Logistik, Umzüge', h1: 'Die Website für Ihr Transportunternehmen',
      lead: 'Wer etwas verschicken oder umziehen muss, vergleicht schnell: Fahren Sie diese Strecke, haben Sie das richtige Fahrzeug, was kostet es, antworten Sie heute? Die Seite antwortet vor dem Anruf — und sammelt Anfragen gleich mit den Angaben, die Sie brauchen.',
      blocchi: [
        ['Was sie haben kann', null, ['Leistungen und Strecken, einfach erklärt', 'Die Fahrzeuge mit Nutzlast und Maßen', 'Angebotsanfrage mit Start, Ziel, Datum und Ware', 'Italienisch, Deutsch und Englisch für Kunden im Ausland', 'Telefon und WhatsApp mit einem Tipp']],
        ['Eine umgesetzte Arbeit', 'Für Cavaleri Trasporti (Caltanissetta, seit 1974) habe ich die Seite gebaut: vier Geschäftsfelder auf einer Seite, die eigene Flotte statt Stockfotos, eine Anfrage für alles, in drei Sprachen. Sie steht unter den Arbeiten auf der Startseite, mit Link zur echten Seite.'],
        ['Der Preis', 'Sprachen und eine gegliederte Angebotsanfrage gehen in die Rechnung ein. Die Zahlen stehen auf der Preisseite; im Rechner sehen Sie Ihre Spanne.'],
      ],
      demo: '#work',
      faq: [
        ['Kommen die Anfragen vollständig an?', 'Ja: Das Formular fragt Strecke, Datum, Art und Menge der Ware ab. Sie antworten mit einem Preis, ohne erst zurückzurufen.'],
        ['Brauche ich Deutsch?', 'Wenn Sie mit Kunden oder Partnern im Ausland arbeiten, ja — und ich schreibe es selbst, keine Maschine.'],
      ],
    },
    en: {
      titel: 'Website for transport and logistics companies | Vecom Design',
      desc: 'A website for your transport company: services, vehicles, areas covered and quote requests — in German and English for customers abroad too.',
      kicker: 'Transport, logistics, removals', h1: 'The website for your transport company',
      lead: 'People who need to ship or move compare quickly: do you run this route, do you have the right vehicle, what does it cost, will you answer today? The site answers before the call — and collects requests with the details you need.',
      blocchi: [
        ['What it can include', null, ['Services and routes, explained simply', 'Vehicles with payload and dimensions', 'Quote requests with origin, destination, date and goods', 'Italian, German and English for customers abroad', 'Phone and WhatsApp in one tap']],
        ['A finished job', 'I built the site for Cavaleri Trasporti (Caltanissetta, since 1974): four lines of business on one page, their own fleet instead of stock photos, one request form for everything, in three languages. It is among the work on the home page, with a link to the live site.'],
        ['The price', 'Languages and a structured quote request go into the calculation. The figures are on the pricing page; the calculator shows your range.'],
      ],
      demo: '#work',
      faq: [
        ['Do the requests arrive complete?', 'Yes: the form asks for route, date, type and quantity of goods. You reply with a price without calling back for details.'],
        ['Do I need German?', 'If you work with customers or partners abroad, yes — and I write it myself, not a machine.'],
      ],
    },
  },

  /* ------------------------------------------------------- Ratgeber: Booking */
  {
    schluessel: 'booking', art: 'Article',
    ziele: { it: 'sito-o-booking.html', de: 'de/eigene-website-oder-booking.html', en: 'en/own-website-or-booking.html' },
    it: {
      titel: 'Serve un sito se il mio B&B è già su Booking? | Vecom Design',
      desc: 'Booking porta ospiti, ma su ogni prenotazione trattiene una commissione. Quando conviene un sito proprio accanto a Booking — e quando no. Una risposta sincera.',
      kicker: 'Guida', h1: 'Serve un sito se il mio B&B è già su Booking?',
      lead: 'La risposta breve: non al posto di Booking, ma accanto. Booking la fa trovare da chi non la conosce; il sito le porta le prenotazioni di chi la conosce già — senza commissione.',
      blocchi: [
        ['Cosa fa bene Booking', 'Visibilità verso chi non la conosce, pagamenti, recensioni, lingue. Per un B&B piccolo è difficile fare meglio da soli, e non ha senso provarci.'],
        ['Cosa costa Booking', 'Su ogni prenotazione trattiene una commissione — in Italia spesso intorno al 15 % del prezzo. Su un ospite che torna ogni anno la paga ogni anno, anche se ormai la conosce.'],
        ['Cosa fa un sito proprio', null, ['Chi la cerca per nome trova lei, non la sua scheda tra altre dieci', 'Chi è già stato da lei può chiedere direttamente', 'Le prenotazioni dirette non pagano commissione', 'Decide lei foto, testi e lingue']],
        ['Quando non conviene', 'Se ha una sola stanza, quasi solo ospiti di passaggio e nessuno che torna, un sito le porterà poco. Glielo dico prima, non dopo averglielo venduto.'],
        ['Il modo semplice', 'Per la maggior parte dei B&B basta un sito con le camere, le foto e una richiesta di prenotazione con le date. Risponde lei: nessun calendario da sincronizzare, nessun rischio di doppie prenotazioni.'],
      ],
      faq: [
        ['Booking non vieta di prendere prenotazioni dirette?', 'Può chiedere di non offrire prezzi più bassi online che sulla piattaforma; le condizioni cambiano, le legga nel suo contratto. Prendere prenotazioni dirette da ospiti che la contattano è normale.'],
        ['Quanto costa un sito così?', 'Le cifre sono sulla pagina dei prezzi; nel calcolatore vede la sua fascia in un minuto e mezzo.'],
      ],
      branche: 'bb',
    },
    de: {
      titel: 'Brauche ich eine Website, wenn mein B&B schon bei Booking ist? | Vecom Design',
      desc: 'Booking bringt Gäste, behält aber von jeder Buchung eine Provision. Wann sich eine eigene Seite neben Booking lohnt — und wann nicht. Eine ehrliche Antwort.',
      kicker: 'Ratgeber', h1: 'Brauche ich eine Website, wenn mein B&B schon bei Booking ist?',
      lead: 'Die kurze Antwort: nicht statt Booking, sondern daneben. Booking bringt Sie zu Menschen, die Sie nicht kennen; die Website bringt die Buchungen derer, die Sie schon kennen — ohne Provision.',
      blocchi: [
        ['Was Booking gut macht', 'Sichtbarkeit bei Fremden, Zahlungen, Bewertungen, Sprachen. Für ein kleines B&B ist das allein kaum besser zu machen, und es lohnt nicht, es zu versuchen.'],
        ['Was Booking kostet', 'Von jeder Buchung bleibt eine Provision dort — in Italien oft um die 15 % des Preises. Bei einem Gast, der jedes Jahr wiederkommt, zahlen Sie sie jedes Jahr, obwohl er Sie längst kennt.'],
        ['Was eine eigene Seite leistet', null, ['Wer Sie beim Namen sucht, findet Sie — nicht Ihren Eintrag zwischen zehn anderen', 'Wer schon da war, kann direkt anfragen', 'Direkte Buchungen kosten keine Provision', 'Fotos, Texte und Sprachen bestimmen Sie']],
        ['Wann es sich nicht lohnt', 'Mit nur einem Zimmer, fast nur Durchreisenden und niemandem, der wiederkommt, bringt eine Website wenig. Das sage ich vorher, nicht nachdem ich sie verkauft habe.'],
        ['Der einfache Weg', 'Den meisten B&B genügt eine Seite mit Zimmern, Fotos und einer Buchungsanfrage mit Daten. Sie antworten selbst: kein Kalender zum Abgleichen, keine Gefahr von Doppelbuchungen.'],
      ],
      faq: [
        ['Verbietet Booking nicht direkte Buchungen?', 'Booking kann verlangen, online keine niedrigeren Preise als auf der Plattform anzubieten; die Bedingungen ändern sich, lesen Sie Ihren Vertrag. Direkte Buchungen von Gästen, die sich bei Ihnen melden, sind normal.'],
        ['Was kostet so eine Seite?', 'Die Zahlen stehen auf der Preisseite; im Rechner sehen Sie Ihre Spanne in anderthalb Minuten.'],
      ],
      branche: 'bb',
    },
    en: {
      titel: 'Do I need a website if my B&B is already on Booking? | Vecom Design',
      desc: 'Booking brings guests but keeps a commission on every booking. When a website of your own next to Booking pays off — and when it does not. An honest answer.',
      kicker: 'Guide', h1: 'Do I need a website if my B&B is already on Booking?',
      lead: 'The short answer: not instead of Booking, but next to it. Booking puts you in front of people who do not know you; your website brings the bookings of people who already do — without commission.',
      blocchi: [
        ['What Booking does well', 'Visibility with strangers, payments, reviews, languages. For a small B&B that is hard to beat alone, and there is no point trying.'],
        ['What Booking costs', 'It keeps a commission on every booking — in Italy often around 15 % of the price. For a guest who returns every year you pay it every year, even though they know you by now.'],
        ['What a site of your own does', null, ['People searching for your name find you, not your listing among ten others', 'Guests who have stayed can ask you directly', 'Direct bookings pay no commission', 'You decide on photos, texts and languages']],
        ['When it is not worth it', 'With a single room, mostly passing guests and nobody who comes back, a website will bring you little. I tell you that before, not after selling it to you.'],
        ['The simple way', 'For most B&Bs a site with rooms, photos and a booking request with dates is enough. You reply yourself: no calendar to sync, no risk of double bookings.'],
      ],
      faq: [
        ['Doesn’t Booking forbid direct bookings?', 'It may require you not to offer lower prices online than on the platform; terms change, so check your contract. Taking direct bookings from guests who contact you is normal.'],
        ['What does such a site cost?', 'The figures are on the pricing page; the calculator shows your range in a minute and a half.'],
      ],
      branche: 'bb',
    },
  },
];

/* Wörter rund um die Seiten, je Sprache. */
export const LANDESEITEN_WORTE = {
  it: { faq: 'Domande frequenti', cta_titel: 'Vuole sapere quanto costa il suo?', cta_text: 'Inserisca la sua e-mail: riceve il link alla sua dashboard e in un minuto e mezzo vede la sua fascia di prezzo. Nessun account, nessun impegno.',
        cta_knopf: 'Iniziare', preise: 'Vedere tutti i prezzi', demo: 'Vedere la demo sulla pagina iniziale', auch: 'Siti anche per', guida: 'Leggere la guida', branche: 'Il sito per il suo B&B', home: 'Pagina iniziale' },
  de: { faq: 'Häufige Fragen', cta_titel: 'Wissen, was Ihre kostet?', cta_text: 'E-Mail eintragen: Sie bekommen den Link zu Ihrem Bereich und sehen in anderthalb Minuten Ihre Preisspanne. Kein Konto, keine Verpflichtung.',
        cta_knopf: 'Loslegen', preise: 'Alle Preise ansehen', demo: 'Demo auf der Startseite ansehen', auch: 'Websites auch für', guida: 'Zum Ratgeber', branche: 'Die Website für Ihre Ferienwohnung', home: 'Startseite' },
  en: { faq: 'Frequently asked questions', cta_titel: 'Want to know what yours costs?', cta_text: 'Enter your e-mail: you get the link to your dashboard and see your price range in a minute and a half. No account, no commitment.',
        cta_knopf: 'Get started', preise: 'See all prices', demo: 'See the demo on the home page', auch: 'Websites also for', guida: 'Read the guide', branche: 'The website for your B&B', home: 'Home page' },
};

/* Kurznamen für die Querverweise „Siti anche per …“ */
export const LANDESEITEN_KURZ = {
  provinz:      { it: 'Provincia di Agrigento', de: 'Provinz Agrigent', en: 'Province of Agrigento' },
  ristoranti:   { it: 'Ristoranti', de: 'Restaurants', en: 'Restaurants' },
  bb:           { it: 'B&B e case vacanza', de: 'Ferienwohnungen', en: 'Holiday rentals' },
  parrucchieri: { it: 'Parrucchieri', de: 'Friseure', en: 'Hairdressers' },
  artigiani:    { it: 'Artigiani', de: 'Handwerk', en: 'Tradespeople' },
  trasporti:    { it: 'Trasporti', de: 'Transport', en: 'Transport' },
};
