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
    staende: { garten: 'Garten', ankunft: 'Ankunft', terrasse: 'Terrasse', wohnen: 'Wohnraum', kueche: 'Küche' },
    foto: (p) => `Gerechnet · Blender Cycles · ${p} Abtastungen je Bildpunkt`,
    echtzeit: (r, f, s) => `Echtzeit · ${r} · ${f} Bilder/s · Stufe ${s}`,
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
    staende: { garten: 'Giardino', ankunft: 'Arrivo', terrasse: 'Terrazza', wohnen: 'Soggiorno', kueche: 'Cucina' },
    foto: (p) => `Calcolata · Blender Cycles · ${p} campioni per pixel`,
    echtzeit: (r, f, s) => `Tempo reale · ${r} · ${f} fotogrammi/s · livello ${s}`,
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
    staende: { garten: 'Garden', ankunft: 'Arrival', terrasse: 'Terrace', wohnen: 'Living room', kueche: 'Kitchen' },
    foto: (p) => `Rendered · Blender Cycles · ${p} samples per pixel`,
    echtzeit: (r, f, s) => `Real time · ${r} · ${f} fps · tier ${s}`,
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
  immobilien: { demo: 'villa', stand: 'garten', zeit: 'nachmittag', stufe: 'C' },
  architektur: { demo: 'villa', stand: 'ankunft', zeit: 'mittag', stufe: 'D' },
  hotel: { demo: 'villa', stand: 'terrasse', zeit: 'abend', stufe: 'C' },
  gastro: { demo: 'villa', stand: 'terrasse', zeit: 'nacht', stufe: 'B' },
  ecommerce: { demo: 'tisch', stufe: 'C' },
  handwerk: { demo: 'klar', stufe: 'A' },
  medizin: { demo: 'klar', stufe: 'A' },
  industrie: { demo: 'tisch', stufe: 'C' },
};

/* ------------------------------------------------------------------ Hilfen */
const $ = (s, w = document) => w.querySelector(s);
const $$ = (s, w = document) => [...w.querySelectorAll(s)];
const BEWEGUNG_AUS = matchMedia('(prefers-reduced-motion: reduce)').matches;
const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const leerlauf = (fn, ms = 1500) => ('requestIdleCallback' in window ? requestIdleCallback(fn, { timeout: ms }) : setTimeout(fn, ms));

/* ================================================================== BÜHNE */
const BILDER = '/assets/img/erlebnis/villa/';
/* Stand der gerechneten Bilder. Wer sie neu rechnet und hochlädt, zählt hier
   hoch -- der Server gibt Bildern dreißig Tage, und diese Adressen setzt
   JavaScript, nicht build.mjs. */
const BILD_STAND = '2';
const PROBEN = 384;
const buehne = $('#buehne');
const ruheA = $('#ruhe-a');
const ruheB = $('#ruhe-b');
const kennungText = $('#kennung-text');
const knopfBewegen = $('#bewegen');
const zustand = { stand: 'garten', zeit: 'nachmittag', echtzeit: false, villa: null, laedt: null, stufe: null, wahl: 'AUTO' };

function bildAdresse(stand, zeit) {
  const klein = buehne.clientWidth * (window.devicePixelRatio || 1) <= 900;
  return `${BILDER}ruhe-${stand}-${zeit}${klein ? '-800' : ''}.webp?v=${BILD_STAND}`;
}

function kennungFoto() {
  kennungText.textContent = `${TEXT.foto(PROBEN)} · ${TEXT.staende[zustand.stand]}, ${TEXT.zeiten[zustand.zeit]}`;
}

let bildAuftrag = 0;
async function bildZeigen(stand, zeit) {
  const nr = ++bildAuftrag;
  const oben = ruheA.classList.contains('ist-oben') ? ruheA : ruheB.classList.contains('ist-oben') ? ruheB : ruheA;
  const unten = oben === ruheA ? ruheB : ruheA;
  const neu = new Image();
  neu.decoding = 'async';
  let adresse = bildAdresse(stand, zeit);
  const laden = (a) => new Promise((ok, nein) => { neu.onload = ok; neu.onerror = nein; neu.src = a; });
  try {
    await laden(adresse);
  } catch {
    // Noch nicht gerechnet: der nächste fertige Stand statt eines Lochs.
    adresse = bildAdresse(stand, 'nachmittag');
    try { await laden(adresse); } catch { return; }
  }
  if (nr !== bildAuftrag) return;
  // <picture> bindet ruhe-a an seine <source>; ohne sie zu leeren, gewinnt
  // beim ersten Wechsel auf schmalen Geräten wieder die Quelle.
  const quelle = unten.parentElement && unten.parentElement.tagName === 'PICTURE' ? unten.parentElement.querySelector('source') : null;
  if (quelle) quelle.remove();
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

let kennungTakt = 0;
function bildGemeldet(dtMs, fps) {
  if (zustand.manager && zustand.wahl === 'AUTO') zustand.manager.bildGemeldet(dtMs);
  const t = performance.now();
  if (t - kennungTakt > 500 && zustand.echtzeit) {
    kennungTakt = t;
    const name = TEXT.stufeNamen[wirksameStufe()] || wirksameStufe();
    kennungText.textContent = TEXT.echtzeit('WebGL 2', fps || '…', name);
    ablesungZeigen();
  }
}

function echtzeitAn() {
  if (!zustand.villa) return;
  zustand.echtzeit = true;
  buehne.classList.add('ist-echtzeit');
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
  for (const b of $$('button', stufenwahl)) {
    b.setAttribute('aria-pressed', String(b.dataset.stufe === zustand.wahl));
    b.classList.toggle('ist-auto', zustand.wahl === 'AUTO' && b.dataset.stufe === (zustand.stufe === 'LOW' ? 'MEDIUM' : zustand.stufe));
  }
  const s = wirksameStufe();
  if (zustand.villa && BUEHNEN_STUFEN[s]) zustand.villa.stufe(BUEHNEN_STUFEN[s]);
  bewegenAnbieten();
  ablesungZeigen();
}

stufenwahl.addEventListener('click', (e) => {
  const b = e.target.closest('button[data-stufe]');
  if (!b) return;
  zustand.wahl = b.dataset.stufe;
  stufeAnwenden();
});

async function geraetMessen() {
  if (!webglDa()) { zustand.stufe = 'SAFE'; stufeAnwenden(); return; }
  try {
    const kern = await import('../../vendor/experience/index.js');
    const m = new kern.AdaptiveExperienceManager({ szene: kern.SZENE_PRODUKT });
    zustand.manager = m;
    const e = await m.initialisieren();
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
  zieleEl.innerHTML = Object.entries(TEXT.ziele).map(([id, [t, s]]) =>
    `<button type="button" class="ziel" data-ziel="${id}" aria-pressed="${id === hub.ziel}"><b>${esc(t)}</b><span>${esc(s)}</span></button>`).join('');
  branchenEl.innerHTML = Object.entries(TEXT.branchen).map(([id, b]) =>
    `<button type="button" data-branche="${id}" aria-pressed="${id === hub.branche}">${esc(b.n)}</button>`).join('');
  ergebnisZeigen(false);
}

function ergebnisZeigen(bewegt = true) {
  const b = TEXT.branchen[hub.branche]; const d = BRANCHEN_DEMO[hub.branche];
  const demo = d.demo === 'villa'
    ? `<button type="button" class="knopf knopf--leer" data-demo="villa">${esc(TEXT.demoVilla)}</button>`
    : d.demo === 'tisch' ? `<a class="knopf knopf--leer" href="#tisch">${esc(TEXT.demoTisch)}</a>` : '';
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

zieleEl.addEventListener('click', (e) => {
  const b = e.target.closest('button[data-ziel]'); if (!b) return;
  hub.ziel = b.dataset.ziel; hub.branche = ZIEL_ZU_BRANCHE[hub.ziel] || hub.branche;
  for (const x of $$('button', zieleEl)) x.setAttribute('aria-pressed', String(x === b));
  for (const x of $$('button', branchenEl)) x.setAttribute('aria-pressed', String(x.dataset.branche === hub.branche));
  ergebnisZeigen();
});
branchenEl.addEventListener('click', (e) => {
  const b = e.target.closest('button[data-branche]'); if (!b) return;
  hub.branche = b.dataset.branche;
  for (const x of $$('button', branchenEl)) x.setAttribute('aria-pressed', String(x === b));
  ergebnisZeigen();
});
ergebnisEl.addEventListener('click', (e) => {
  const b = e.target.closest('[data-demo="villa"]'); if (!b) return;
  const d = BRANCHEN_DEMO[hub.branche];
  standSetzen(d.stand); zeitSetzen(d.zeit);
  $('#villa').scrollIntoView({ behavior: BEWEGUNG_AUS ? 'auto' : 'smooth', block: 'center' });
});

/* ================================================================ TISCH */
const dreh = $('#dreh');
const drehBild = $('#dreh-bild');
const DREH_N = 36;
const DREH = '/assets/img/3d/tisch/drehen/';
const drehAdresse = (i, g) => `${DREH}${g}/dreh-${String(((i % DREH_N) + DREH_N) % DREH_N).padStart(2, '0')}.webp`;
let drehI = 0; let drehGross = new Set(); let drehBereit = false; let drehZiehen = null; let selbstlauf = null;

function drehZeigen(i) {
  drehI = ((i % DREH_N) + DREH_N) % DREH_N;
  drehBild.src = drehAdresse(drehI, drehGross.has(drehI) ? 'gross' : 'klein');
  dreh.setAttribute('aria-valuenow', String(drehI));
}
function drehVorladen() {
  if (drehBereit) return; drehBereit = true;
  // Klein zuerst (je gut 4 KB): Dann springt das Drehen nie ins Leere.
  for (let i = 0; i < DREH_N; i++) { const k = new Image(); k.src = drehAdresse(i, 'klein'); }
  let n = 0;
  const weiter = () => {
    if (n >= DREH_N) return;
    const i = n++; const g = new Image();
    g.onload = () => { drehGross.add(i); if (i === drehI) drehZeigen(drehI); weiter(); };
    g.onerror = weiter; g.src = drehAdresse(i, 'gross');
  };
  weiter(); weiter();
}
function selbstlaufStop() { if (selbstlauf) { clearInterval(selbstlauf); selbstlauf = null; } }
dreh.addEventListener('pointerdown', (e) => {
  drehVorladen(); selbstlaufStop(); dreh.classList.add('ist-benutzt');
  drehZiehen = { x: e.clientX, i: drehI }; dreh.setPointerCapture(e.pointerId);
});
dreh.addEventListener('pointermove', (e) => {
  if (!drehZiehen) return;
  const schritt = Math.max(6, dreh.clientWidth / 48);
  drehZeigen(drehZiehen.i - Math.round((e.clientX - drehZiehen.x) / schritt));
});
const drehLos = () => { drehZiehen = null; };
dreh.addEventListener('pointerup', drehLos); dreh.addEventListener('pointercancel', drehLos);
dreh.addEventListener('keydown', (e) => {
  if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
  e.preventDefault(); drehVorladen(); selbstlaufStop(); dreh.classList.add('ist-benutzt');
  drehZeigen(drehI + (e.key === 'ArrowRight' ? 1 : -1));
});
new IntersectionObserver((eintraege) => {
  for (const e of eintraege) {
    if (e.isIntersecting) {
      drehVorladen();
      // Einmal langsam drehen, damit man sieht, dass es geht -- danach Ruhe.
      if (!BEWEGUNG_AUS && !selbstlauf && !dreh.classList.contains('ist-benutzt')) {
        let n = 0;
        selbstlauf = setInterval(() => { drehZeigen(drehI + 1); if (++n >= DREH_N) selbstlaufStop(); }, 140);
      }
    } else selbstlaufStop();
  }
}, { threshold: 0.45 }).observe(dreh);

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
