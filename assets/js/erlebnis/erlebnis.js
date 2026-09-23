/* ==========================================================================
   erlebnis.js — die Erlebnisseite: Bühne, Wegweiser, Tisch, Stufen.

   WAS HIER PASSIERT
   1. Bühne     Gerechnete Fotos (Blender Cycles) für fünf Standpunkte und
                fünf Tageszeiten. Auf Wunsch kommt das Echtzeitmodell dazu
                (villa-echtzeit.js); lässt der Besucher los, fährt die Kamera
                zurück und das Foto blendet wieder ein.
   2. Wegweiser Ziel -> Branche -> was die Website könnte, was ich weglasse,
                welche Stufe passt, welche Demo es zeigt.
   3. Tisch     36 gerechnete Ansichten zum Drehen -- ohne WebGL.
   4. Stufen    experience-core entscheidet, welche Fassung das Gerät trägt;
                der Besucher darf selbst umschalten.

   WARUM EINE DATEI (plus das Echtzeitmodul)
   build.mjs hängt nur an Adressen im HTML einen Fingerabdruck. Ein
   `import './texte.js'` in einem Modul bliebe ohne -- und der Server gibt
   Dateien dreißig Tage Zwischenspeicher. Ein Rückkehrer hätte neue Logik mit
   alten Texten bekommen. Deshalb stehen die Texte hier mit drin, und das
   Echtzeitmodul kommt über data-src, das build.mjs stempelt.

   DIE REIHENFOLGE IST DER PUNKT
   Das Foto ist sofort da (LCP, kein Skript nötig). Das 3D-Modell lädt erst,
   wenn jemand danach greift. Die Gerätemessung läuft, wenn die Seite ruht.
   Keine dieser Stufen hält die Bedienung auf.
   ========================================================================== */
const L = (document.documentElement.lang || 'it').slice(0, 2);
const SPRACHE = ['it', 'de', 'en'].includes(L) ? L : 'it';

const T = {
  de: {
    zeiten: { morgen: 'Morgen', mittag: 'Mittag', nachmittag: 'Nachmittag', abend: 'Abend', nacht: 'Blaue Stunde' },
    staende: { garten: 'Garten', ankunft: 'Ankunft', terrasse: 'Terrasse', wohnen: 'Wohnraum', essen: 'Essplatz' },
    foto: (p) => `Gerechnet · Blender Cycles · ${p} Abtastungen je Bildpunkt`,
    fotoKurz: 'Gerechnet · Blender Cycles',
    echtzeit: (r, f, s) => `Echtzeit · ${r} · ${f} Bilder/s · Stufe ${s}`,
    echtzeitRuht: (r, s) => `Echtzeit · ${r} · Stufe ${s}`,
    ansichten: { stand: 'Foto', grundriss: 'Grundriss', bau: 'Bauablauf', gehen: 'Hineingehen' },
    kennungAnsicht: (r, s, a) => `Echtzeit · ${r} · Stufe ${s} · ${a}`,
    kehrt: 'Loslassen — die Kamera kehrt zum Foto zurück',
    laedt: 'Lade das 3D-Modell …',
    keinWebgl: 'Dieses Gerät zeigt die gerechneten Bilder. Das 3D-Modell bleibt aus — so bleibt die Seite schnell.',
    fehlt: 'Dieses Bild wird gerade noch gerechnet. Gezeigt wird der nächste fertige Stand.',
    bewegen: 'Selbst drehen',
    zumFoto: 'Zurück zum Foto',
    leinwand: 'Die Villa in Echtzeit — ziehen oder Pfeiltasten zum Drehen',
    stufeNamen: { SAFE: 'ECO', LOW: 'STANDARD', MEDIUM: 'STANDARD', HIGH: 'HIGH', ULTRA: 'ULTRA' },
    messen: 'wird gemessen …',
    keine: 'aus',
    dein: (s, g) => `Dein Gerät bekommt ${s}${g ? ` — ${g}` : ''}.`,
    gewaehlt: (s) => `Von dir gewählt: ${s}.`,
    grund: { mobil: 'ein Telefon oder Tablet', schwach: 'eine schwächere Grafik', stark: 'eine starke Grafik', mittel: 'eine solide Grafik' },
    ablesung: { fps: 'Bilder je Sekunde', pr: 'Pixeldichte', schatten: 'Schattenkarte', dreiecke: 'Dreiecke' },
    koennteKopf: 'Deine Website könnte:',
    nichtKopf: 'Was ich dabei bewusst weglasse:',
    stufeKopf: 'Passende Ausbaustufe',
    stufen: { A: 'Klar & schnell', B: 'Premium mit feiner Bewegung', C: 'Motion & Storytelling', D: 'Immersiv mit 3D' },
    demoVilla: 'Diese Demo ansehen',
    demoTisch: 'Zur Produkt-Demo',
    demoAuto: 'Zum Auto-Konfigurator',
    demoShop: 'Zum Shop-Beispiel',
    start: 'Projekt starten',
    ohneDemo: 'Hier braucht es kein 3D. Eine Seite, die in zwei Sekunden am Telefon steht, bringt mehr als jede Szene.',
    ziele: {
      anfragen: ['Mehr Anfragen', 'Vom Besuch zum Anruf'],
      verkaufen: ['Produkte verkaufen', 'Zeigen, erklären, verkaufen'],
      zeigen: ['Objekte zeigen', 'Häuser, Räume, Entwürfe'],
      buchen: ['Mehr Buchungen', 'Erst die Stimmung, dann buchen'],
      vertrauen: ['Vertrauen gewinnen', 'Klar, schnell, seriös'],
      erlebnis: ['Etwas, worüber man spricht', 'Ein Erlebnis statt einer Seite'],
    },
    branchen: {
      immobilien: {
        n: 'Immobilien', u: 'Makler, Bauträger, Ferienhäuser',
        titel: 'Deine Interessenten gehen durchs Haus, bevor sie anrufen.',
        kann: ['das Objekt zu jeder Tageszeit zeigen — gerechnet wie ein Foto', 'Besucher selbst drehen und Räume betreten lassen', 'am Telefon schnell laden und das 3D erst danach holen', 'Interessenten vorab sortieren: Wer anfragt, kennt den Grundriss', 'die Besichtigung direkt aus der Ansicht anfragen lassen'],
        nicht: ['360°-Panoramen ohne Grundriss', 'einen Rundgang, der am Telefon ruckelt'],
      },
      architektur: {
        n: 'Architektur', u: 'Büros, Planer, Bauherren',
        titel: 'Dein Bauherr steht im Haus, bevor der erste Stein liegt.',
        kann: ['den Entwurf mit echten Maßen begehbar machen', 'Licht und Schatten zu jeder Tageszeit zeigen', 'jede Ansicht als gerechnetes Bild in Druckqualität liefern', 'Änderungen früher sichtbar machen — und damit billiger'],
        nicht: ['Renderings ohne Maßstab', 'Effekte statt Material'],
      },
      hotel: {
        n: 'Hotel & Ferienhaus', u: 'B&B, Agriturismo, Villa',
        titel: 'Dein Gast erlebt den Abend auf der Terrasse, bevor er bucht.',
        kann: ['Zimmer, Terrasse und Garten zeigen, bevor jemand bucht', 'zwischen Tag und Abend umschalten — die Stimmung verkauft mit', 'Verfügbarkeit mit einem Tippen erreichbar machen', 'am Telefon leicht bleiben, am Laptop mehr zeigen'],
        nicht: ['Videos, die von selbst mit Ton starten', 'Preise erst nach dem Formular'],
      },
      gastro: {
        n: 'Restaurant', u: 'Trattoria, Bar, Pasticceria',
        titel: 'Dein Gast weiß, wo er sitzt, bevor er reserviert.',
        kann: ['die Terrasse bei Abendlicht zeigen', 'die Speisekarte als echten Text zeigen — lesbar in zwei Sekunden', 'Öffnungszeiten, Anruf und Weg immer im Daumenbereich halten', 'Reservierungen per WhatsApp mit einem Tippen annehmen'],
        nicht: ['3D-Modelle von Gerichten', 'die Karte nur als PDF'],
      },
      automotive: {
        n: 'Automotive', u: 'Autohaus, Händler, Werkstatt',
        titel: 'Dein Kunde stellt sein Auto zusammen, bevor er zur Probefahrt kommt.',
        kann: ['jeden Lack und jede Felge aus einem Modell zeigen', 'das Auto auf Knopfdruck in seine Teile zerlegen', 'Scheinwerfer, Innenraum und Details aus der Nähe zeigen', 'die Probefahrt mit fertiger Konfiguration anfragen lassen'],
        nicht: ['Bildergalerien mit vierzig Fotos', 'ein Konfigurator, der erst eine App braucht'],
      },
      ecommerce: {
        n: 'Shop & Manufaktur', u: 'Möbel, Produkte, Varianten',
        titel: 'Dein Kunde dreht das Produkt, bevor er es kauft.',
        kann: ['jede Variante zeigen, ohne sie zu fotografieren', 'das Produkt drehen und aus der Nähe zeigen', 'Anfragen mit fertiger Konfiguration und Artikelnummer erzeugen', 'auf jedem Gerät gleich aussehen — auch ohne Grafikkarte'],
        nicht: ['3D bei Produkten mit nur einer Ausführung', 'Ladezeit auf Kosten der Kasse'],
      },
      handwerk: {
        n: 'Handwerk & Dienstleistung', u: 'Elektriker, Schreiner, Transport',
        titel: 'Eine klare Seite, die Anrufe bringt.',
        kann: ['in unter zwei Sekunden am Telefon dastehen', 'Anruf, WhatsApp und Weg immer im Daumenbereich zeigen', 'mit dezenter Bewegung führen statt ablenken', 'bei Google für deine Leistungen und deinen Ort gefunden werden'],
        nicht: ['Scroll-Kino vor der Telefonnummer', 'Animationen, die den Anruf verzögern'],
      },
      medizin: {
        n: 'Praxis & Gesundheit', u: 'Arzt, Physio, Zahnarzt',
        titel: 'Termin in zwei Klicks — und Vertrauen auf den ersten Blick.',
        kann: ['Sprechzeiten, Anfahrt und Telefon sofort zeigen', 'Termine ohne Anruf möglich machen', 'Leistungen verständlich erklären', 'für alle lesbar sein — große Schrift, guter Kontrast'],
        nicht: ['Effekte, die die Terminbuchung verstecken', 'Schrift auf unruhigem Bild'],
      },
      industrie: {
        n: 'Industrie & Technik', u: 'Maschinen, Anlagen, Messe',
        titel: 'Deine Maschine erklärt sich selbst.',
        kann: ['jede Ausführung aus einem Modell zeigen', 'Baugruppen einzeln zeigen und erklären', 'auf der Messe am Tablet laufen — ohne Installation', 'Datenblätter als echte, durchsuchbare Seiten zeigen'],
        nicht: ['Animation ohne Erklärung', 'Datenblätter nur als PDF'],
      },
    },
  },
  it: {
    zeiten: { morgen: 'Mattina', mittag: 'Mezzogiorno', nachmittag: 'Pomeriggio', abend: 'Sera', nacht: 'Ora blu' },
    staende: { garten: 'Giardino', ankunft: 'Arrivo', terrasse: 'Terrazza', wohnen: 'Soggiorno', essen: 'Zona pranzo' },
    foto: (p) => `Calcolata · Blender Cycles · ${p} campioni per pixel`,
    fotoKurz: 'Calcolata · Blender Cycles',
    echtzeit: (r, f, s) => `Tempo reale · ${r} · ${f} fotogrammi/s · livello ${s}`,
    echtzeitRuht: (r, s) => `Tempo reale · ${r} · livello ${s}`,
    ansichten: { stand: 'Foto', grundriss: 'Pianta', bau: 'Costruzione', gehen: 'Entra dentro' },
    kennungAnsicht: (r, s, a) => `Tempo reale · ${r} · livello ${s} · ${a}`,
    kehrt: 'Lascia andare — la camera torna alla foto',
    laedt: 'Carico il modello 3D …',
    keinWebgl: 'Questo dispositivo mostra le immagini calcolate. Il modello 3D resta spento — così la pagina resta veloce.',
    fehlt: 'Questa immagine è ancora in calcolo. Mostro l’ultima versione pronta.',
    bewegen: 'Giralo tu',
    zumFoto: 'Torna alla foto',
    leinwand: 'La villa in tempo reale — trascina o usa le frecce per girarla',
    stufeNamen: { SAFE: 'ECO', LOW: 'STANDARD', MEDIUM: 'STANDARD', HIGH: 'HIGH', ULTRA: 'ULTRA' },
    messen: 'misurazione in corso …',
    keine: 'spenta',
    dein: (s, g) => `Il tuo dispositivo riceve ${s}${g ? ` — ${g}` : ''}.`,
    gewaehlt: (s) => `Scelto da te: ${s}.`,
    grund: { mobil: 'un telefono o un tablet', schwach: 'una grafica più debole', stark: 'una grafica potente', mittel: 'una grafica solida' },
    ablesung: { fps: 'Fotogrammi al secondo', pr: 'Densità di pixel', schatten: 'Mappa delle ombre', dreiecke: 'Triangoli' },
    koennteKopf: 'Il tuo sito potrebbe:',
    nichtKopf: 'Cosa lascio fuori di proposito:',
    stufeKopf: 'Livello consigliato',
    stufen: { A: 'Chiaro e veloce', B: 'Premium con movimento discreto', C: 'Motion e storytelling', D: 'Immersivo in 3D' },
    demoVilla: 'Guarda questa demo',
    demoTisch: 'Alla demo di prodotto',
    demoAuto: 'Al configuratore auto',
    demoShop: 'All’esempio di negozio',
    start: 'Avvia il progetto',
    ohneDemo: 'Qui il 3D non serve. Una pagina che si apre in due secondi sul telefono rende più di qualsiasi scena.',
    ziele: {
      anfragen: ['Più richieste', 'Dalla visita alla telefonata'],
      verkaufen: ['Vendere prodotti', 'Mostrare, spiegare, vendere'],
      zeigen: ['Mostrare immobili', 'Case, ambienti, progetti'],
      buchen: ['Più prenotazioni', 'Prima l’atmosfera, poi la prenotazione'],
      vertrauen: ['Conquistare fiducia', 'Chiaro, veloce, serio'],
      erlebnis: ['Qualcosa di cui si parla', 'Un’esperienza, non solo una pagina'],
    },
    branchen: {
      immobilien: {
        n: 'Immobiliare', u: 'Agenzie, costruttori, case vacanza',
        titel: 'I tuoi clienti entrano in casa prima di telefonare.',
        kann: ['mostrare l’immobile a ogni ora del giorno — calcolato come una foto', 'far girare la casa ed entrare nelle stanze', 'caricarsi in fretta sul telefono e portare il 3D solo dopo', 'selezionare i contatti: chi chiede, conosce già la pianta', 'far richiedere la visita direttamente dalla vista'],
        nicht: ['panorami a 360° senza pianta', 'un tour che scatta sul telefono'],
      },
      architektur: {
        n: 'Architettura', u: 'Studi, progettisti, committenti',
        titel: 'Il tuo committente entra in casa prima che si posi la prima pietra.',
        kann: ['rendere il progetto percorribile con misure reali', 'mostrare luce e ombre a ogni ora', 'fornire ogni vista come immagine calcolata in qualità di stampa', 'far vedere le modifiche prima — quando costano meno'],
        nicht: ['render senza scala', 'effetti al posto dei materiali'],
      },
      hotel: {
        n: 'Hotel e casa vacanza', u: 'B&B, agriturismo, villa',
        titel: 'Il tuo ospite vive la sera in terrazza prima di prenotare.',
        kann: ['mostrare camere, terrazza e giardino prima della prenotazione', 'passare dal giorno alla sera — l’atmosfera vende con te', 'portare alla disponibilità con un tocco', 'restare leggero sul telefono, mostrare di più sul portatile'],
        nicht: ['video che partono da soli con l’audio', 'prezzi visibili solo dopo il modulo'],
      },
      gastro: {
        n: 'Ristorante', u: 'Trattoria, bar, pasticceria',
        titel: 'Il tuo ospite sa dove si siederà prima di prenotare.',
        kann: ['mostrare la terrazza con la luce della sera', 'mostrare il menù come testo vero — leggibile in due secondi', 'tenere orari, chiamata e percorso sempre sotto il pollice', 'accettare prenotazioni su WhatsApp con un tocco'],
        nicht: ['modelli 3D dei piatti', 'il menù solo in PDF'],
      },
      automotive: {
        n: 'Automotive', u: 'Concessionaria, rivenditore, officina',
        titel: 'Il tuo cliente compone la sua auto prima di venire al test drive.',
        kann: ['mostrare ogni vernice e ogni cerchio da un solo modello', 'scomporre l’auto nei suoi pezzi con un tocco', 'mostrare fari, interni e dettagli da vicino', 'far richiedere il test drive con la configurazione pronta'],
        nicht: ['gallerie di quaranta foto', 'un configuratore che richiede un’app'],
      },
      ecommerce: {
        n: 'Shop e manifattura', u: 'Mobili, prodotti, varianti',
        titel: 'Il tuo cliente gira il prodotto prima di comprarlo.',
        kann: ['mostrare ogni variante senza fotografarla', 'far girare il prodotto e mostrarlo da vicino', 'generare richieste con configurazione e codice articolo', 'apparire uguale su ogni dispositivo — anche senza scheda grafica'],
        nicht: ['il 3D per prodotti in un’unica versione', 'tempi di caricamento a spese della cassa'],
      },
      handwerk: {
        n: 'Artigiani e servizi', u: 'Elettricisti, falegnami, trasporti',
        titel: 'Una pagina chiara che porta telefonate.',
        kann: ['aprirsi in meno di due secondi sul telefono', 'tenere chiamata, WhatsApp e percorso sempre sotto il pollice', 'guidare con un movimento discreto invece di distrarre', 'farti trovare su Google per i tuoi servizi e la tua zona'],
        nicht: ['un film a scorrimento prima del numero di telefono', 'animazioni che ritardano la chiamata'],
      },
      medizin: {
        n: 'Studi medici e salute', u: 'Medico, fisioterapia, dentista',
        titel: 'Appuntamento in due clic — e fiducia al primo sguardo.',
        kann: ['mostrare subito orari, percorso e telefono', 'rendere possibile l’appuntamento senza chiamare', 'spiegare le prestazioni in modo comprensibile', 'essere leggibile per tutti — caratteri grandi, buon contrasto'],
        nicht: ['effetti che nascondono la prenotazione', 'testo su immagini agitate'],
      },
      industrie: {
        n: 'Industria e tecnica', u: 'Macchine, impianti, fiere',
        titel: 'La tua macchina si spiega da sola.',
        kann: ['mostrare ogni versione da un unico modello', 'mostrare e spiegare i gruppi uno per uno', 'funzionare in fiera su un tablet — senza installazione', 'mostrare le schede tecniche come pagine vere e ricercabili'],
        nicht: ['animazioni senza spiegazione', 'schede tecniche solo in PDF'],
      },
    },
  },
  en: {
    zeiten: { morgen: 'Morning', mittag: 'Noon', nachmittag: 'Afternoon', abend: 'Evening', nacht: 'Blue hour' },
    staende: { garten: 'Garden', ankunft: 'Arrival', terrasse: 'Terrace', wohnen: 'Living room', essen: 'Dining area' },
    foto: (p) => `Rendered · Blender Cycles · ${p} samples per pixel`,
    fotoKurz: 'Rendered · Blender Cycles',
    echtzeit: (r, f, s) => `Real time · ${r} · ${f} fps · tier ${s}`,
    echtzeitRuht: (r, s) => `Real time · ${r} · tier ${s}`,
    ansichten: { stand: 'Photo', grundriss: 'Floor plan', bau: 'Construction', gehen: 'Walk inside' },
    kennungAnsicht: (r, s, a) => `Real time · ${r} · tier ${s} · ${a}`,
    kehrt: 'Let go — the camera returns to the photo',
    laedt: 'Loading the 3D model …',
    keinWebgl: 'This device shows the rendered images. The 3D model stays off — that keeps the page fast.',
    fehlt: 'This image is still rendering. Showing the latest finished one.',
    bewegen: 'Turn it yourself',
    zumFoto: 'Back to the photo',
    leinwand: 'The villa in real time — drag or use the arrow keys to turn it',
    stufeNamen: { SAFE: 'ECO', LOW: 'STANDARD', MEDIUM: 'STANDARD', HIGH: 'HIGH', ULTRA: 'ULTRA' },
    messen: 'measuring …',
    keine: 'off',
    dein: (s, g) => `Your device gets ${s}${g ? ` — ${g}` : ''}.`,
    gewaehlt: (s) => `Chosen by you: ${s}.`,
    grund: { mobil: 'a phone or tablet', schwach: 'weaker graphics', stark: 'strong graphics', mittel: 'solid graphics' },
    ablesung: { fps: 'Frames per second', pr: 'Pixel density', schatten: 'Shadow map', dreiecke: 'Triangles' },
    koennteKopf: 'Your website could:',
    nichtKopf: 'What I leave out on purpose:',
    stufeKopf: 'Suggested level',
    stufen: { A: 'Clear & fast', B: 'Premium with subtle motion', C: 'Motion & storytelling', D: 'Immersive 3D' },
    demoVilla: 'See this demo',
    demoTisch: 'To the product demo',
    demoAuto: 'To the car configurator',
    demoShop: 'To the shop example',
    start: 'Start the project',
    ohneDemo: 'No 3D needed here. A page that is up in two seconds on a phone does more than any scene.',
    ziele: {
      anfragen: ['More enquiries', 'From visit to phone call'],
      verkaufen: ['Sell products', 'Show, explain, sell'],
      zeigen: ['Present properties', 'Houses, rooms, designs'],
      buchen: ['More bookings', 'The mood first, then the booking'],
      vertrauen: ['Earn trust', 'Clear, fast, credible'],
      erlebnis: ['Something people talk about', 'An experience, not just a page'],
    },
    branchen: {
      immobilien: {
        n: 'Real estate', u: 'Agents, developers, holiday homes',
        titel: 'Your prospects walk through the house before they call.',
        kann: ['show the property at any time of day — rendered like a photo', 'let visitors turn the house and step inside', 'load fast on a phone and fetch the 3D afterwards', 'pre-qualify leads: whoever enquires already knows the floor plan', 'let people request a viewing right from the view'],
        nicht: ['360° panoramas without a floor plan', 'a tour that stutters on a phone'],
      },
      architektur: {
        n: 'Architecture', u: 'Studios, planners, clients',
        titel: 'Your client stands in the house before the first stone is laid.',
        kann: ['make the design walkable at true scale', 'show light and shadow at any hour', 'deliver every view as a print-quality rendering', 'show changes earlier — while they are still cheap'],
        nicht: ['renderings without scale', 'effects instead of materials'],
      },
      hotel: {
        n: 'Hotel & holiday home', u: 'B&B, agriturismo, villa',
        titel: 'Your guest lives the evening on the terrace before booking.',
        kann: ['show rooms, terrace and garden before anyone books', 'switch between day and evening — the mood sells with you', 'put availability one tap away', 'stay light on a phone, show more on a laptop'],
        nicht: ['videos that start by themselves with sound', 'prices only after the form'],
      },
      gastro: {
        n: 'Restaurant', u: 'Trattoria, bar, pasticceria',
        titel: 'Your guest knows where they will sit before booking.',
        kann: ['show the terrace in evening light', 'show the menu as real text — readable in two seconds', 'keep hours, call and directions under the thumb', 'take reservations on WhatsApp with one tap'],
        nicht: ['3D models of dishes', 'the menu only as a PDF'],
      },
      automotive: {
        n: 'Automotive', u: 'Dealership, reseller, garage',
        titel: 'Your customer builds their car before coming in for a test drive.',
        kann: ['show every paint and every wheel from one model', 'take the car apart at a tap', 'show headlights, interior and details up close', 'let people request the test drive with the configuration attached'],
        nicht: ['galleries of forty photos', 'a configurator that needs an app first'],
      },
      ecommerce: {
        n: 'Shop & maker', u: 'Furniture, products, variants',
        titel: 'Your customer turns the product before buying it.',
        kann: ['show every variant without photographing it', 'let people turn the product and look closely', 'create enquiries with the finished configuration and item number', 'look the same on every device — even without a graphics card'],
        nicht: ['3D for products that come in one version', 'load time at the expense of checkout'],
      },
      handwerk: {
        n: 'Trades & services', u: 'Electricians, joiners, transport',
        titel: 'A clear page that brings phone calls.',
        kann: ['be up in under two seconds on a phone', 'keep call, WhatsApp and directions under the thumb', 'guide with subtle motion instead of distracting', 'get found on Google for your services and your area'],
        nicht: ['scroll cinema before the phone number', 'animations that delay the call'],
      },
      medizin: {
        n: 'Practice & health', u: 'Doctor, physio, dentist',
        titel: 'An appointment in two clicks — and trust at first sight.',
        kann: ['show hours, directions and phone at once', 'make appointments possible without a call', 'explain treatments clearly', 'be readable for everyone — large type, good contrast'],
        nicht: ['effects that hide the booking', 'text on busy images'],
      },
      industrie: {
        n: 'Industry & engineering', u: 'Machines, plants, trade fairs',
        titel: 'Your machine explains itself.',
        kann: ['show every version from one model', 'show and explain each assembly on its own', 'run on a tablet at a trade fair — nothing to install', 'show data sheets as real, searchable pages'],
        nicht: ['animation without explanation', 'data sheets only as PDFs'],
      },
    },
  },
};

const TEXT = T[SPRACHE];

/* Welche Branche zu welchem Ziel vorgewählt wird, welche Demo sie zeigt
   und auf welcher Stufe sie landet. Die Stufen folgen derselben Regel wie
   die Werkstatt (Technik.php): Die Branche setzt die Grundstufe, der Wunsch
   nach Wirkung darf sie heben -- nie umgekehrt. */
const ZIEL_ZU_BRANCHE = {
  anfragen: 'handwerk', verkaufen: 'ecommerce', zeigen: 'immobilien',
  buchen: 'hotel', vertrauen: 'medizin', erlebnis: 'architektur',
};
const BRANCHEN_DEMO = {
  automotive: { demo: 'auto', stufe: 'D' },
  immobilien: { demo: 'villa', stand: 'garten', zeit: 'nachmittag', stufe: 'C' },
  architektur: { demo: 'villa', stand: 'ankunft', zeit: 'mittag', stufe: 'D' },
  hotel: { demo: 'villa', stand: 'terrasse', zeit: 'abend', stufe: 'C' },
  gastro: { demo: 'villa', stand: 'terrasse', zeit: 'nacht', stufe: 'B' },
  ecommerce: { demo: 'shop', stufe: 'C' },
  handwerk: { demo: 'klar', stufe: 'A' },
  medizin: { demo: 'klar', stufe: 'A' },
  // Zerlegen ist das Werkzeug der Industrie -- Baugruppen einzeln zeigen.
  industrie: { demo: 'auto', stufe: 'C' },
};

/* ------------------------------------------------------------------ Hilfen */
const $ = (s, w = document) => w.querySelector(s);
const $$ = (s, w = document) => [...w.querySelectorAll(s)];
const BEWEGUNG_AUS = matchMedia('(prefers-reduced-motion: reduce)').matches;
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const leerlauf = (fn, ms = 1500) => ('requestIdleCallback' in window ? requestIdleCallback(fn, { timeout: ms }) : setTimeout(fn, ms));

/* Zählen, welche Demo genutzt wird (d.php: keine IP, kein Cookie, nur
   Datum, Stunde, Ereignis, Geräteart). Jedes Ereignis einmal je Seitenaufruf. */
const GEZAEHLT = new Set();
function zaehlen(e) {
  if (GEZAEHLT.has(e)) return; GEZAEHLT.add(e);
  try { navigator.sendBeacon ? navigator.sendBeacon(`/d.php?e=${e}`) : fetch(`/d.php?e=${e}`, { method: 'POST', keepalive: true }); } catch { /* egal */ }
}

/* ================================================================== BÜHNE */
const BILDER = '/assets/img/erlebnis/villa/';
/* Stand der gerechneten Bilder. Wer sie neu rechnet und hochlädt, zählt hier
   hoch -- der Server gibt Bildern dreißig Tage, und diese Adressen setzt
   JavaScript, nicht build.mjs. */
const BILD_STAND = '3';
const PROBEN = 384;
const buehne = $('#buehne');
const ruheA = $('#ruhe-a');
const ruheB = $('#ruhe-b');
const kennungText = $('#kennung-text');
const knopfBewegen = $('#bewegen');
const zustand = { stand: 'garten', zeit: 'nachmittag', ansicht: 'stand', schritt: 8, echtzeit: false, villa: null, laedt: null, stufe: null, wahl: 'AUTO' };

/* AVIF, wo der Browser es kann (23.09.2026): bei gleicher Treue zum
   Cycles-PNG rund 45 % kleiner als WebP (tools/bilder-avif.py). Das <picture>
   im HTML waehlt selbst; die Adressen, die JavaScript beim Wechseln setzt,
   brauchen dieselbe Entscheidung -- ein 1-px-AVIF sagt, ob es geht. */
const AVIF_PROBE = 'data:image/avif;base64,AAAAIGZ0eXBhdmlmAAAAAGF2aWZtaWYxbWlhZk1BMUIAAADrbWV0YQAAAAAAAAAhaGRscgAAAAAAAAAAcGljdAAAAAAAAAAAAAAAAAAAAAAOcGl0bQAAAAAAAQAAAB5pbG9jAAAAAEQAAAEAAQAAAAEAAAETAAAAKAAAAChpaW5mAAAAAAABAAAAGmluZmUCAAAAAAEAAGF2MDFDb2xvcgAAAABqaXBycAAAAEtpcGNvAAAAFGlzcGUAAAAAAAAAAQAAAAEAAAAQcGl4aQAAAAADCAgIAAAADGF2MUOBAAwAAAAAE2NvbHJuY2x4AAEADQAGgAAAABdpcG1hAAAAAAAAAAEAAQQBAoMEAAAAMG1kYXQSAAoIGAAGiAhoNCAyGhlHh4Yhh5555oAAAJBAyRxhSytNj1FFTqSg';
let bildEndung = 'webp';
const avifPruefung = new Promise((ok) => {
  const i = new Image();
  i.onload = () => ok(i.naturalWidth === 1); i.onerror = () => ok(false);
  i.src = AVIF_PROBE;
}).then((ja) => { if (ja) bildEndung = 'avif'; return ja; });
function bildAdresse(stand, zeit, endung = bildEndung) {
  const klein = buehne.clientWidth * (window.devicePixelRatio || 1) <= 900;
  return `${BILDER}ruhe-${stand}-${zeit}${klein ? '-800' : ''}.${endung}?v=${BILD_STAND}`;
}

function kennungFoto() {
  const wo = `${TEXT.staende[zustand.stand]}, ${TEXT.zeiten[zustand.zeit]}`;
  // Auf einer schmalen Buehne (kleines Fenster, Telefon) wuerde die lange
  // Fassung vierzeilig ueber das halbe Bild laufen. Dann nur das Werkzeug
  // und der Standpunkt -- die Abtastungen stehen im Text daneben.
  kennungText.textContent = buehne.clientWidth < 620
    ? `${TEXT.fotoKurz} · ${wo}`
    : `${TEXT.foto(PROBEN)} · ${wo}`;
}

let bildAuftrag = 0;
async function bildZeigen(stand, zeit) {
  const nr = ++bildAuftrag;
  const oben = ruheA.classList.contains('ist-oben') ? ruheA : ruheB.classList.contains('ist-oben') ? ruheB : ruheA;
  const unten = oben === ruheA ? ruheB : ruheA;
  const neu = new Image();
  neu.decoding = 'async';
  await avifPruefung;
  const laden = (a) => new Promise((ok, nein) => { neu.onload = ok; neu.onerror = nein; neu.src = a; });
  // Reihenfolge: gewuenschtes Bild, dann dasselbe als WebP, dann -- noch
  // nicht gerechnet -- der Nachmittag statt eines Lochs.
  const kandidaten = [...new Set([bildAdresse(stand, zeit), bildAdresse(stand, zeit, 'webp'),
    bildAdresse(stand, 'nachmittag'), bildAdresse(stand, 'nachmittag', 'webp')])];
  let adresse = null;
  for (const k of kandidaten) {
    try { await laden(k); adresse = k; break; } catch { /* naechste */ }
  }
  if (!adresse || nr !== bildAuftrag) return;
  // <picture> bindet ruhe-a an seine <source>-Eintraege; ohne sie zu leeren,
  // gewinnt beim ersten Wechsel wieder eine davon.
  const bild = unten.parentElement && unten.parentElement.tagName === 'PICTURE' ? unten.parentElement : null;
  if (bild) for (const q of [...bild.querySelectorAll('source')]) q.remove();
  unten.src = adresse;
  try { await unten.decode(); } catch { /* dann eben ohne Vorab-Dekodieren */ }
  if (nr !== bildAuftrag) return;
  unten.classList.add('ist-oben', 'ist-an');
  oben.classList.remove('ist-oben');
  setTimeout(() => { if (nr === bildAuftrag) oben.classList.remove('ist-an'); }, 650);
  // Der Alternativtext wandert mit: Vorgelesen wird immer das Bild, das oben liegt.
  const text = oben.alt || unten.alt;
  unten.alt = text; unten.removeAttribute('aria-hidden');
  oben.alt = ''; oben.setAttribute('aria-hidden', 'true');
}

function waehlen(gruppe, attr, wert) {
  for (const b of $$(`#${gruppe} button`)) b.setAttribute('aria-pressed', String(b.dataset[attr] === wert));
}

function standSetzen(stand) {
  if (stand === zustand.stand) return;
  zustand.stand = stand; waehlen('staende', 'stand', stand);
  echtzeitAus();
  bildZeigen(stand, zustand.zeit); kennungFoto();
  if (zustand.villa) zustand.villa.stand(stand);
}
function zeitSetzen(zeit) {
  if (zeit === zustand.zeit) return;
  zustand.zeit = zeit; waehlen('zeiten', 'zeit', zeit);
  bildZeigen(zustand.stand, zeit);
  if (zustand.villa) zustand.villa.zeit(zeit);
  if (!zustand.echtzeit) kennungFoto();
}

/* ---------------------------------------------------- Ansicht und Aufbau
   Grundriss, Bauablauf und Begehung lagen bis zum 22.09.2026 in einem
   eigenen Abschnitt (haus.js, am 23.09.2026 entfernt). Sie gehoeren an dieselbe Buehne wie das Foto:
   Es ist dasselbe Haus, nur anders angesehen. Alle drei brauchen das
   Echtzeitmodell -- ohne WebGL bleiben die Knoepfe weg. */
const ansichtenEl = $('#ansichten');
const schritteEl = $('#schritte');
const bauschritteEl = $('#bauschritte');

function woerterbuch() {
  return (window.VECOM_I18N && window.VECOM_I18N[SPRACHE] && window.VECOM_I18N[SPRACHE].erlebnis) || {};
}

function ansichtChips() {
  if (!ansichtenEl) return;
  for (const b of $$('button', ansichtenEl)) b.setAttribute('aria-pressed', String(b.dataset.ansicht === zustand.ansicht));
  if (schritteEl) schritteEl.hidden = zustand.ansicht !== 'bau';
  const staende = $('#staende');
  if (staende) staende.parentElement.hidden = zustand.ansicht !== 'stand';
  // Jede Ansicht sagt in einem Satz, was sie ist und wie man sie bedient.
  const hilfe = $('#ansicht-hilfe');
  if (hilfe) {
    const t = woerterbuch()[zustand.ansicht + '_hilfe'];
    hilfe.textContent = t || '';
    hilfe.hidden = !t;
  }
}

function bauschritteAufbauen() {
  if (!bauschritteEl || bauschritteEl.children.length) return;
  /* Die Namen der acht Abschnitte stehen im Woerterbuch (erlebnis.b1 bis b8),
     nicht hier: Sie stehen in drei Sprachen und gehoeren zum Text der Seite,
     nicht zur Mechanik. Fehlt das Woerterbuch, bleibt die Nummer. */
  const w = woerterbuch();
  bauschritteEl.innerHTML = [1, 2, 3, 4, 5, 6, 7, 8].map((n) => {
    const t = w['b' + n] || String(n);
    return `<button type="button" data-schritt="${n}" aria-pressed="${n === zustand.schritt}"><b>${n}</b> <span>${esc(t)}</span></button>`;
  }).join('');
}

async function ansichtSetzen(name) {
  if (name === zustand.ansicht) return;
  if (name !== 'stand') {
    const v = await echtzeitLaden();
    if (!v) return;
    zustand.ansicht = name;
    if (name === 'bau') bauschritteAufbauen();
    ansichtChips();
    echtzeitAn();
    v.ansicht(name, zustand.schritt);
  } else {
    zustand.ansicht = 'stand';
    ansichtChips();
    if (zustand.villa) zustand.villa.stand(zustand.stand);
    echtzeitAus();
  }
  kennungSetzen();
}

if (ansichtenEl) {
  ansichtenEl.addEventListener('click', (e) => {
    const b = e.target.closest('button[data-ansicht]');
    if (b) ansichtSetzen(b.dataset.ansicht);
  });
}
if (bauschritteEl) {
  bauschritteEl.addEventListener('click', (e) => {
    const b = e.target.closest('button[data-schritt]');
    if (!b) return;
    zustand.schritt = Number(b.dataset.schritt);
    for (const x of $$('button', bauschritteEl)) x.setAttribute('aria-pressed', String(x === b));
    if (zustand.villa) zustand.villa.schritt(zustand.schritt);
  });
}

$('#staende').addEventListener('click', (e) => { const b = e.target.closest('button[data-stand]'); if (b) standSetzen(b.dataset.stand); });
$('#zeiten').addEventListener('click', (e) => { const b = e.target.closest('button[data-zeit]'); if (b) zeitSetzen(b.dataset.zeit); });

/* ------------------------------------------------------------ Echtzeit */
/* Einmal fragen, dann merken: Jede Probe legt einen WebGL-Kontext an, und
   Browser begrenzen deren Zahl je Seite. */
let webglAntwort = null;
function webglDa() {
  if (webglAntwort === null) {
    try { const c = document.createElement('canvas'); const g = c.getContext('webgl2'); webglAntwort = !!g; if (g) g.getExtension('WEBGL_lose_context')?.loseContext(); } catch { webglAntwort = false; }
  }
  return webglAntwort;
}

/* Die Stufen des Kerns in Werte dieser Bühne. STANDARD verzichtet auf
   Schatten, weil sie auf schwachen Grafikchips mehr kosten als alles andere
   zusammen; ULTRA bekommt die große Karte und weiche Kanten. */
const BUEHNEN_STUFEN = {
  LOW:    { pixel: 1,    schatten: 0 },
  MEDIUM: { pixel: 1,    schatten: 0 },
  HIGH:   { pixel: 1.5,  schatten: 1024 },
  ULTRA:  { pixel: 2,    schatten: 2048 },
};
function wirksameStufe() {
  if (zustand.wahl !== 'AUTO') return zustand.wahl;
  return zustand.stufe || 'HIGH';
}

/* stumm: Vorwaermen beim Ueberfahren mit der Maus. Dann bleibt der Knopf,
   wie er ist -- "lade …" auf einem Knopf, den niemand gedrueckt hat,
   wirkt wie eine Panne. */
function ladeAnzeige(an) {
  if (an) { knopfBewegen.setAttribute('aria-busy', 'true'); $('#bewegen-text').textContent = TEXT.laedt; }
  else { knopfBewegen.removeAttribute('aria-busy'); $('#bewegen-text').textContent = zustand.echtzeit ? TEXT.zumFoto : TEXT.bewegen; }
}
async function echtzeitLaden(stumm = false) {
  if (zustand.villa) return zustand.villa;
  if (!stumm) ladeAnzeige(true);
  if (zustand.laedt) { try { return await zustand.laedt; } finally { if (!stumm) ladeAnzeige(false); } }
  zustand.laedt = (async () => {
    // data-src steht relativ zur SEITE (assets/… oder ../assets/…), import()
    // loest aber relativ zu DIESER Datei auf. Also erst gegen die Seite
    // aufloesen -- sonst sucht /de/ das Modul unter /assets/js/assets/.
    const modul = await import(new URL(buehne.dataset.src, document.baseURI).href);
    const stufe = wirksameStufe();
    const v = await modul.erstelle({
      behaelter: $('#echtzeit'),
      stand: zustand.stand, zeit: zustand.zeit,
      einstellungen: BUEHNEN_STUFEN[stufe] || BUEHNEN_STUFEN.HIGH,
      bezeichnung: TEXT.leinwand,
      beiBewegung: () => echtzeitAn(),
      beiRuhe: () => echtzeitAus(),
      beiBild: (dtMs, fps) => bildGemeldet(dtMs, fps),
    });
    zustand.villa = v;
    buehne.classList.add('hat-echtzeit');
    $('#echtzeit').removeAttribute('aria-hidden');
    return v;
  })();
  try {
    return await zustand.laedt;
  } catch (f) {
    // Kein Modell, keine Panne: Das Foto bleibt, der Knopf geht.
    console.warn('[erlebnis] Echtzeit nicht verfügbar:', f);
    knopfBewegen.hidden = true;
    zustand.laedt = null;
    return null;
  } finally {
    if (!stumm) ladeAnzeige(false);
  }
}

function kennungSetzen(fps) {
  if (!zustand.echtzeit) { kennungFoto(); return; }
  const name = TEXT.stufeNamen[wirksameStufe()] || wirksameStufe();
  if (zustand.ansicht !== 'stand') {
    kennungText.textContent = TEXT.kennungAnsicht('WebGL 2', name, TEXT.ansichten[zustand.ansicht]);
    return;
  }
  kennungText.textContent = fps ? TEXT.echtzeit('WebGL 2', fps, name) : TEXT.echtzeitRuht('WebGL 2', name);
}

let kennungTakt = 0;
function bildGemeldet(dtMs, fps, schlaeft) {
  if (zustand.manager && zustand.wahl === 'AUTO' && !schlaeft) zustand.manager.bildGemeldet(dtMs);
  const t = performance.now();
  if ((schlaeft || t - kennungTakt > 500) && zustand.echtzeit) {
    kennungTakt = t;
    // Ruht die Schleife (nichts bewegt sich), steht keine Bildrate da --
    // eine eingefrorene Zahl neben einem stehenden Bild waere eine Behauptung.
    kennungSetzen(schlaeft ? 0 : fps);
    ablesungZeigen();
  }
}

function echtzeitAn() {
  if (!zustand.villa) return;
  zustand.echtzeit = true;
  buehne.classList.add('ist-echtzeit'); zaehlen('villa-drehen');
  $('#bewegen-text').textContent = TEXT.zumFoto;
  zustand.villa.starten();
}
function echtzeitAus() {
  if (!zustand.echtzeit) return;
  zustand.echtzeit = false;
  buehne.classList.remove('ist-echtzeit');
  $('#bewegen-text').textContent = TEXT.bewegen;
  kennungFoto();
  // Erst nach der Blende anhalten, sonst friert das Bild sichtbar ein.
  setTimeout(() => { if (!zustand.echtzeit && zustand.villa) zustand.villa.anhalten(); }, 700);
}

knopfBewegen.addEventListener('click', async () => {
  if (zustand.echtzeit) {
    if (zustand.villa) zustand.villa.stand(zustand.stand);
    echtzeitAus();
    return;
  }
  const v = await echtzeitLaden();
  if (v) echtzeitAn();
});

function bewegenAnbieten() {
  const eco = wirksameStufe() === 'SAFE' || zustand.wahl === 'CINEMATIC';
  knopfBewegen.hidden = !webglDa() || eco;
  if (eco) echtzeitAus();
}

/* =============================================================== STUFEN */
const stufenwahl = $('#stufenwahl');
const ablesungSatz = $('#ablesung-satz');
const ablesungWerte = $('#ablesung-werte');

function ablesungZeigen() {
  if (!ablesungSatz || !ablesungWerte) return;
  const s = wirksameStufe();
  const g = zustand.geraet;
  let grund = '';
  if (g) grund = g.mobile ? TEXT.grund.mobil : g.softwareRenderer ? TEXT.grund.schwach : (g.score >= 7 ? TEXT.grund.stark : TEXT.grund.mittel);
  const name = zustand.wahl === 'CINEMATIC' ? 'CINEMATIC' : (TEXT.stufeNamen[s] || s);
  ablesungSatz.textContent = zustand.wahl !== 'AUTO' ? TEXT.gewaehlt(name) : zustand.stufe ? TEXT.dein(name, grund) : TEXT.messen;
  const info = zustand.villa ? zustand.villa.info() : null;
  const einst = BUEHNEN_STUFEN[s];
  const zeilen = [
    [TEXT.ablesung.fps, zustand.echtzeit && zustand.villa ? String(zustand.villa.fps || '…') : '—'],
    [TEXT.ablesung.pr, info ? info.pixel.toFixed(2).replace(/\.?0+$/, '') + '×' : einst ? `≤ ${einst.pixel}×` : '—'],
    [TEXT.ablesung.schatten, info ? (info.schatten ? `${info.schatten} px` : TEXT.keine) : einst ? (einst.schatten ? `${einst.schatten} px` : TEXT.keine) : '—'],
    [TEXT.ablesung.dreiecke, (info ? info.dreiecke : 32280).toLocaleString(SPRACHE)],
  ];
  ablesungWerte.innerHTML = zeilen.map(([k, v]) => `<div><dt>${esc(k)}</dt><dd>${esc(v)}</dd></div>`).join('');
}

function stufeAnwenden() {
  for (const b of (stufenwahl ? $$('button', stufenwahl) : [])) {
    b.setAttribute('aria-pressed', String(b.dataset.stufe === zustand.wahl));
    b.classList.toggle('ist-auto', zustand.wahl === 'AUTO' && b.dataset.stufe === (zustand.stufe === 'LOW' ? 'MEDIUM' : zustand.stufe));
  }
  const s = wirksameStufe();
  if (zustand.villa && BUEHNEN_STUFEN[s]) zustand.villa.stufe(BUEHNEN_STUFEN[s]);
  // Die Branchen-Bühnen (branchen.js) richten sich nach derselben Stufe.
  document.documentElement.dataset.erlebnisStufe = s;
  document.dispatchEvent(new CustomEvent('vecom:stufe', { detail: { stufe: s } }));
  bewegenAnbieten();
  ablesungZeigen();
}

if (stufenwahl) {
  stufenwahl.addEventListener('click', (e) => {
    const b = e.target.closest('button[data-stufe]');
    if (!b) return;
    zustand.wahl = b.dataset.stufe;
    stufeAnwenden();
  });
}

async function geraetMessen() {
  if (!webglDa()) { zustand.stufe = 'SAFE'; stufeAnwenden(); return; }
  /* Nicht in einem Hintergrund-Tab messen. Dort feuert requestAnimationFrame
     nicht, die Messung des Kerns wartet darauf -- und wer die Seite mit der
     mittleren Maustaste in einem zweiten Tab oeffnet, bekaeme fuer immer
     "wird gemessen". Am 22.09.2026 auf dem Arbeitsrechner genau so gesehen. */
  if (document.hidden) {
    await new Promise((ok) => document.addEventListener('visibilitychange', function fn() {
      if (document.hidden) return;
      document.removeEventListener('visibilitychange', fn); ok();
    }));
  }
  try {
    const kern = await import('../../vendor/experience/index.js');
    const m = new kern.AdaptiveExperienceManager({ szene: kern.SZENE_PRODUKT });
    zustand.manager = m;
    // Zehn Sekunden sind grosszuegig; wer dann noch nicht geantwortet hat,
    // bekommt die vorsichtige Vorgabe und darf spaeter nachliefern.
    const e = await Promise.race([
      m.initialisieren(),
      new Promise((ok) => setTimeout(() => ok(null), 10000)),
    ]);
    if (!e) { zustand.stufe = zustand.stufe || 'HIGH'; stufeAnwenden(); return; }
    zustand.geraet = m.geraet;
    zustand.stufe = e.strategie === 'SAFE_MEDIA' ? 'SAFE' : e.stufe;
    m.abonnieren((z) => {
      const neu = z.entscheidung.strategie === 'SAFE_MEDIA' ? 'SAFE' : z.entscheidung.stufe;
      if (neu !== zustand.stufe) { zustand.stufe = neu; stufeAnwenden(); }
    });
  } catch (f) {
    console.warn('[erlebnis] Gerätemessung fehlgeschlagen:', f);
    zustand.stufe = 'HIGH';
  }
  stufeAnwenden();
}

/* ============================================================ WEGWEISER */
const zieleEl = $('#ziele');
const branchenEl = $('#branchen');
const ergebnisEl = $('#ergebnis');
const hub = { ziel: 'zeigen', branche: 'immobilien' };

function wegweiserAufbauen() {
  if (!zieleEl || !branchenEl || !ergebnisEl) return;
  zieleEl.innerHTML = Object.entries(TEXT.ziele).map(([id, [t, s]]) =>
    `<button type="button" class="ziel" data-ziel="${id}" aria-pressed="${id === hub.ziel}"><b>${esc(t)}</b><span>${esc(s)}</span></button>`).join('');
  branchenEl.innerHTML = Object.entries(TEXT.branchen).map(([id, b]) =>
    `<button type="button" data-branche="${id}" aria-pressed="${id === hub.branche}">${esc(b.n)}</button>`).join('');
  ergebnisZeigen(false);
}

function ergebnisZeigen(bewegt = true) {
  if (!ergebnisEl) return;
  const b = TEXT.branchen[hub.branche]; const d = BRANCHEN_DEMO[hub.branche];
  const demo = d.demo === 'villa'
    ? `<button type="button" class="knopf knopf--leer" data-demo="villa">${esc(TEXT.demoVilla)}</button>`
    : d.demo === 'tisch' ? `<a class="knopf knopf--leer" href="#tisch">${esc(TEXT.demoTisch)}</a>`
    : d.demo === 'auto' ? `<a class="knopf knopf--leer" href="#bd-auto">${esc(TEXT.demoAuto)}</a>`
    : d.demo === 'shop' ? `<a class="knopf knopf--leer" href="#bd-shop">${esc(TEXT.demoShop)}</a>` : '';
  ergebnisEl.innerHTML = `
    <p class="ergebnis__branche">${esc(b.n)} · ${esc(b.u)}</p>
    <h3>${esc(b.titel)}</h3>
    <h4>${esc(TEXT.koennteKopf)}</h4>
    <ul class="kann">${b.kann.map((k) => `<li>${esc(k)}</li>`).join('')}</ul>
    <h4>${esc(TEXT.nichtKopf)}</h4>
    <ul class="nicht">${b.nicht.map((k) => `<li>${esc(k)}</li>`).join('')}</ul>
    ${d.demo === 'klar' ? `<p class="ergebnis__ohne">${esc(TEXT.ohneDemo)}</p>` : ''}
    <div class="ergebnis__stufe"><span>${esc(TEXT.stufeKopf)}</span><b>${d.stufe}</b><span>${esc(TEXT.stufen[d.stufe])}</span></div>
    <div class="knoepfe">
      <a class="knopf knopf--voll" href="/bedarf.php?lang=${SPRACHE}">${esc(TEXT.start)}</a>
      ${demo}
    </div>`;
  if (bewegt && !BEWEGUNG_AUS) { ergebnisEl.classList.remove('ist-neu'); void ergebnisEl.offsetWidth; ergebnisEl.classList.add('ist-neu'); }
}

if (zieleEl) zieleEl.addEventListener('click', (e) => {
  const b = e.target.closest('button[data-ziel]'); if (!b) return;
  hub.ziel = b.dataset.ziel; hub.branche = ZIEL_ZU_BRANCHE[hub.ziel] || hub.branche;
  for (const x of $$('button', zieleEl)) x.setAttribute('aria-pressed', String(x === b));
  for (const x of $$('button', branchenEl)) x.setAttribute('aria-pressed', String(x.dataset.branche === hub.branche));
  ergebnisZeigen();
});
if (branchenEl) branchenEl.addEventListener('click', (e) => {
  const b = e.target.closest('button[data-branche]'); if (!b) return;
  hub.branche = b.dataset.branche;
  for (const x of $$('button', branchenEl)) x.setAttribute('aria-pressed', String(x === b));
  ergebnisZeigen();
});
if (ergebnisEl) ergebnisEl.addEventListener('click', (e) => {
  const b = e.target.closest('[data-demo="villa"]'); if (!b) return;
  const d = BRANCHEN_DEMO[hub.branche];
  standSetzen(d.stand); zeitSetzen(d.zeit);
  if (demoOeffnen) demoOeffnen('villa', false);
  $('#villa').scrollIntoView({ behavior: BEWEGUNG_AUS ? 'auto' : 'smooth', block: 'center' });
});

/* ================================================================ TISCH
   23.09.2026, Uwe: „der Tisch flackert beim Drehen". Gemessen, zwei Ursachen:
   1. Das Bild wechselte zwischen zwei Sätzen: 360 px („klein", vorab) und
      1600 px („gross", nachgeladen). Solange nicht alle großen da waren,
      kam beim Drehen abwechselnd ein scharfes und ein hochskaliertes,
      weiches Bild -- das Auge liest das als Flackern. Dazu setzte jedes
      Bild img.src neu; ein noch nicht dekodiertes Bild kann für einen
      Frame leer bleiben.
   2. Die Messingwangen spiegeln das Studiolicht. Bei 36 Schritten à 10°
      springt die mittlere Helligkeit von einem Bild zum nächsten um bis zu
      15 Stufen (44 → 55 zwischen 7 und 8): Die Wange ist im einen Bild
      dunkel, im nächsten voll im Licht.
   Jetzt: ein <canvas> über dem Standbild, gezeichnet nur aus fertig
   dekodierten Bildern EINES Satzes (erst klein, dann -- einmal, wenn alle
   da sind -- groß), und zwischen zwei Nachbarbildern wird überblendet:
   Die Stellung ist eine Kommazahl, nicht mehr ein ganzer Schritt. Beim
   Loslassen rastet sie auf das nächste ganze Bild ein, damit im Stand kein
   Doppelbild bleibt. */
const dreh = $('#dreh');
if (dreh) {
  const drehBild = $('#dreh-bild');
  const DREH_N = 36;
  const DREH = '/assets/img/3d/tisch/drehen/';
  const drehAdresse = (i, g) => `${DREH}${g}/dreh-${String(i).padStart(2, '0')}.webp`;
  const mod = (i) => ((i % DREH_N) + DREH_N) % DREH_N;
  const leinwand = document.createElement('canvas');
  leinwand.className = 'dreh__leinwand'; leinwand.setAttribute('aria-hidden', 'true');
  dreh.insertBefore(leinwand, drehBild.nextSibling);
  const ctx = leinwand.getContext('2d');
  let satz = null;             // Array der fertig dekodierten Bilder (ein Satz)
  let pos = 0; let ziel = 0;   // Stellung in Bildern, Kommazahl
  let drehBereit = false; let drehZiehen = null; let selbstlauf = false; let rafId = 0; let letzt = 0;

  async function satzLaden(g) {
    const liste = await Promise.all(Array.from({ length: DREH_N }, async (_, i) => {
      const b = new Image(); b.decoding = 'async'; b.src = drehAdresse(i, g);
      try { await b.decode(); return b; } catch { return null; }
    }));
    return liste.every(Boolean) ? liste : null;
  }
  function groesse() {
    const r = Math.min(2, window.devicePixelRatio || 1);
    const w = Math.round(dreh.clientWidth * r), h = Math.round(dreh.clientHeight * r);
    if (leinwand.width !== w || leinwand.height !== h) { leinwand.width = w; leinwand.height = h; }
  }
  function malen(b, alpha) {
    // wie object-fit: cover
    const W = leinwand.width, H = leinwand.height, s = Math.max(W / b.naturalWidth, H / b.naturalHeight);
    const w = b.naturalWidth * s, h = b.naturalHeight * s;
    ctx.globalAlpha = alpha; ctx.drawImage(b, (W - w) / 2, (H - h) / 2, w, h);
  }
  function zeichnen() {
    if (!satz) return;
    groesse();
    const i0 = Math.floor(pos), f = pos - i0;
    malen(satz[mod(i0)], 1);
    if (f > 0.001) malen(satz[mod(i0 + 1)], f);
    ctx.globalAlpha = 1;
    dreh.setAttribute('aria-valuenow', String(mod(Math.round(pos))));
  }
  function schleife(t) {
    const dt = letzt ? Math.min(0.05, (t - letzt) / 1000) : 1 / 60; letzt = t;
    if (selbstlauf) {
      ziel += dt * 7;              // 36 Bilder in gut 5 s, wie zuvor
      if (ziel >= DREH_N) { ziel = DREH_N; selbstlauf = false; }
    }
    if (drehZiehen) pos = ziel;
    else pos += (ziel - pos) * (BEWEGUNG_AUS ? 1 : 1 - Math.exp(-dt * 18));
    if (Math.abs(ziel - pos) < 0.002) pos = ziel;
    zeichnen();
    if (selbstlauf || drehZiehen || pos !== ziel) rafId = requestAnimationFrame(schleife);
    else { rafId = 0; letzt = 0; pos = ziel = mod(Math.round(ziel)); zeichnen(); }
  }
  const anstossen = () => { if (!rafId && satz) rafId = requestAnimationFrame(schleife); };

  async function drehVorladen() {
    if (drehBereit) return; drehBereit = true;
    const klein = await satzLaden('klein');
    if (klein && !satz) { satz = klein; leinwand.classList.add('ist-an'); zeichnen(); anstossen(); }
    // Den großen Satz erst übernehmen, wenn er VOLLSTÄNDIG da ist -- nie gemischt.
    const gross = await satzLaden('gross');
    if (gross) { satz = gross; leinwand.classList.add('ist-an'); zeichnen(); anstossen(); }
  }
  function selbstlaufStop() { if (selbstlauf) { selbstlauf = false; ziel = Math.round(pos); } }
  dreh.addEventListener('pointerdown', (e) => {
    drehVorladen(); selbstlaufStop(); dreh.classList.add('ist-benutzt'); zaehlen('tisch-drehen');
    drehZiehen = { x: e.clientX, p: pos };
    try { dreh.setPointerCapture(e.pointerId); } catch { /* ohne Zeigerfang */ }
    anstossen();
  });
  dreh.addEventListener('pointermove', (e) => {
    if (!drehZiehen) return;
    const schritt = Math.max(6, dreh.clientWidth / 48);
    ziel = drehZiehen.p - (e.clientX - drehZiehen.x) / schritt;
    anstossen();
  });
  const drehLos = () => { if (!drehZiehen) return; drehZiehen = null; ziel = Math.round(pos); anstossen(); };
  dreh.addEventListener('pointerup', drehLos); dreh.addEventListener('pointercancel', drehLos);
  dreh.addEventListener('keydown', (e) => {
    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
    e.preventDefault(); drehVorladen(); selbstlaufStop(); dreh.classList.add('ist-benutzt');
    ziel = Math.round(ziel) + (e.key === 'ArrowRight' ? 1 : -1); anstossen();
  });
  new ResizeObserver(() => zeichnen()).observe(dreh);
  new IntersectionObserver((eintraege) => {
    for (const e of eintraege) {
      if (e.isIntersecting) {
        drehVorladen().then(() => {
          // Einmal langsam drehen, damit man sieht, dass es geht -- danach Ruhe.
          if (!BEWEGUNG_AUS && !selbstlauf && !dreh.classList.contains('ist-benutzt') && !dreh.dataset.gedreht) {
            dreh.dataset.gedreht = '1'; selbstlauf = true; anstossen();
          }
        });
      } else selbstlaufStop();
    }
  }, { threshold: 0.45 }).observe(dreh);
}

/* ======================================================= KAPITEL & KLAPPBLOCK
   23.09.2026. Die Kapitelleiste markiert, wo man gerade ist -- gemessen an
   einer Linie auf 45 % der Fensterhöhe, nicht am oberen Rand: Sonst gilt ein
   Abschnitt erst als erreicht, wenn man ihn schon halb gelesen hat. Der Tisch
   gehört zu „Auto & Shop" (auch ein Produkt), der Klappblock zu „Stufen". */
const kapitel = $('#kapitel');
if (kapitel) {
  const links = [...kapitel.querySelectorAll('a')];
  const ZUORDNUNG = [['demos', 0], ['demo-villa', 0], ['branchen-demo', 0], ['tisch', 0], ['wegweiser', 1], ['stufen', 2], ['tiefer', 2]];
  const ziele = ZUORDNUNG.map(([id, i]) => [document.getElementById(id), i]).filter(([el]) => el);
  let aktiv = -1;
  const markieren = (i) => {
    if (i === aktiv) return; aktiv = i;
    links.forEach((a, k) => (k === i ? a.setAttribute('aria-current', 'true') : a.removeAttribute('aria-current')));
    // Auf dem Telefon passt die Leiste nicht immer ganz: das aktive Wort sichtbar halten.
    const a = links[i]; if (a && kapitel.scrollWidth > kapitel.clientWidth) kapitel.scrollTo({ left: a.offsetLeft - 12, behavior: BEWEGUNG_AUS ? 'auto' : 'smooth' });
  };
  const sichtbar = new Set();
  const io = new IntersectionObserver((eintraege) => {
    for (const e of eintraege) (e.isIntersecting ? sichtbar.add(e.target) : sichtbar.delete(e.target));
    const treffer = ziele.filter(([el]) => sichtbar.has(el));
    if (treffer.length) markieren(treffer[treffer.length - 1][1]);
  }, { rootMargin: '-45% 0px -54% 0px' });
  ziele.forEach(([el]) => io.observe(el));
  // Unter der Kopfleiste sitzen, solange sie da ist; sie weicht beim Scrollen nach unten.
  const kopf = document.querySelector('.header');
  const oben = () => {
    const da = kopf && !kopf.classList.contains('ist-weg');
    const px = da ? kopf.offsetHeight + (parseFloat(getComputedStyle(kopf).top) || 0) : 0;
    kapitel.style.setProperty('--kapitel-top', `${Math.round(px) + 8}px`);
  };
  if (kopf) new MutationObserver(oben).observe(kopf, { attributes: true, attributeFilter: ['class'] });
  addEventListener('resize', oben, { passive: true }); oben();
}
/* ========================================================== DEMO-GALERIE
   24.09.2026. Kacheln statt untereinander stehender Bühnen. Ein Klick öffnet
   die Bühne direkt unter der Reihe, ein zweiter Klick oder "Schließen" klappt
   sie zu. Zugeklappt wird die Echtzeit angehalten (Ereignis demo:zu an der
   Bühne; branchen.js und die Villa hören darauf) -- sonst rechnete eine
   unsichtbare Szene weiter. Alte Adressen (#villa, #bd-auto, #tisch, ...)
   öffnen die passende Bühne, damit Links aus dem Wegweiser und von außen
   weiter funktionieren. */
const demos = $('#demos');
let demoOeffnen = null;
if (demos) {
  const kacheln = [...demos.querySelectorAll('[data-demo]')];
  const buehnen = [...document.querySelectorAll('[data-demo-buehne]')];
  const BUEHNE = { villa: 'villa', auto: 'branchen', shop: 'branchen', tisch: 'tisch', wein: 'produkt', schmuck: 'produkt', kueche: 'produkt', gastro: 'produkt', salon: 'produkt', lkw: 'produkt' };
  const AUS_ADRESSE = { wein: 'wein', schmuck: 'schmuck', kueche: 'kueche', gastro: 'gastro', salon: 'salon', lkw: 'lkw', villa: 'villa', 'demo-villa': 'villa', buehne: 'villa', 'branchen-demo': 'auto', 'bd-auto': 'auto', 'bd-shop': 'shop', tisch: 'tisch', dreh: 'tisch' };
  let offen = null;
  const zuklappen = (b) => { if (!b.hidden) { b.dispatchEvent(new CustomEvent('demo:zu')); b.hidden = true; } };
  function oeffnen(demo, rollen = true) {
    const ziel = buehnen.find((b) => b.dataset.demoBuehne === BUEHNE[demo]);
    if (!ziel) return;
    for (const b of buehnen) if (b !== ziel) zuklappen(b);
    ziel.hidden = false;
    // Auto und Shop teilen sich eine Bühne mit Reitern -- den richtigen wählen.
    if (demo === 'auto' || demo === 'shop') document.getElementById(`bd-tab-${demo}`)?.click();
    // Weitere Branchen teilen sich eine Produktbühne: branchen.js wechselt das Modell.
    if (BUEHNE[demo] === 'produkt') document.dispatchEvent(new CustomEvent('vecom:produkt', { detail: { demo } }));
    for (const k of kacheln) k.setAttribute('aria-expanded', String(k.dataset.demo === demo));
    // Telefon: Die Reihe wischt seitlich -- die offene Kachel ins Blickfeld holen
    const reihe = demos.querySelector('.demos__reihe'); const k = kacheln.find((x) => x.dataset.demo === demo);
    if (reihe && k && reihe.scrollWidth > reihe.clientWidth) reihe.scrollTo({ left: k.parentElement.offsetLeft - reihe.offsetLeft - 16, behavior: BEWEGUNG_AUS ? 'auto' : 'smooth' });
    offen = demo; zaehlen(`demo-${demo}`);
    if (rollen) requestAnimationFrame(() => ziel.scrollIntoView({ behavior: BEWEGUNG_AUS ? 'auto' : 'smooth', block: 'start' }));
  }
  function schliessen(rollen = true) {
    for (const b of buehnen) zuklappen(b);
    for (const k of kacheln) k.setAttribute('aria-expanded', 'false');
    const war = offen; offen = null;
    if (rollen && war) {
      const k = kacheln.find((x) => x.dataset.demo === war);
      demos.scrollIntoView({ behavior: BEWEGUNG_AUS ? 'auto' : 'smooth', block: 'start' });
      k && k.focus({ preventScroll: true });
    }
  }
  demos.addEventListener('click', (e) => {
    const k = e.target.closest('[data-demo]'); if (!k) return;
    if (offen === k.dataset.demo) schliessen(false); else oeffnen(k.dataset.demo);
  });
  for (const z of document.querySelectorAll('[data-demo-zu]')) z.addEventListener('click', () => schliessen());
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape' || !offen || !document.activeElement) return;
    if (document.activeElement.closest('[data-demo-buehne]')) schliessen();
  });
  function demoAusAdresse() {
    const id = decodeURIComponent(location.hash.slice(1)); if (!id) return;
    let demo = AUS_ADRESSE[id];
    if (!demo) {
      // Anker tief in einer Bühne (z. B. #bd-auto-cta): deren Demo öffnen
      const el = document.getElementById(id); const b = el && el.closest('[data-demo-buehne]');
      if (b) demo = b.dataset.demoBuehne === 'branchen' ? (el.closest('#bd-shop') ? 'shop' : 'auto') : b.dataset.demoBuehne;
    }
    if (demo && demo !== offen) oeffnen(demo);
  }
  addEventListener('hashchange', demoAusAdresse); demoAusAdresse();
  demoOeffnen = oeffnen;
}
$('#demo-villa')?.addEventListener('demo:zu', () => { if (zustand.echtzeit) { if (zustand.villa) zustand.villa.stand(zustand.stand); echtzeitAus(); } });

// Wer mit #technik, #vergleich oder #streaming kommt, bekommt den Block offen.
const tiefer = $('#tiefer');
function tieferAusAdresse() {
  const id = decodeURIComponent(location.hash.slice(1)); if (!id || !tiefer) return;
  const ziel = document.getElementById(id);
  if (ziel && ziel !== tiefer && tiefer.contains(ziel) && !tiefer.open) { tiefer.open = true; requestAnimationFrame(() => ziel.scrollIntoView()); }
}
addEventListener('hashchange', tieferAusAdresse); tieferAusAdresse();

/* ================================================================ START */
if (new URLSearchParams(location.search).has('pruefen')) window.__erlebnis = zustand;
kennungFoto();
wegweiserAufbauen();
if (webglDa()) knopfBewegen.hidden = false;
ablesungZeigen();
// Wer auf das Foto greift, bekommt sofort das Modell -- aber erst laden,
// wenn jemand es will. Maus: schon beim Überfahren vorwärmen.
buehne.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse' && knopfBewegen.hidden === false) leerlauf(() => echtzeitLaden(true), 400); }, { once: true });
buehne.addEventListener('pointerdown', async (e) => {
  // Nicht bei Beruehrung: Auf dem Telefon beginnt jedes Scrollen ueber der
  // Buehne mit einem pointerdown. Dort laedt das Modell nur ueber den Knopf
  // -- sonst kostete schon das Vorbeiscrollen ein halbes Megabyte.
  if (e.pointerType === 'touch') return;
  if (zustand.villa || knopfBewegen.hidden || e.target.closest('button')) return;
  const v = await echtzeitLaden();
  if (v) echtzeitAn();
});
leerlauf(geraetMessen, 2500);
