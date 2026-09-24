/* ==========================================================================
   branchen.js — Automotive und Shop im Erlebnisteil der Startseite.

   WAS HIER PASSIERT
   Zwei Bühnen nach demselben Muster wie die Villa: Zuerst steht ein
   gerechnetes Foto aus Blender Cycles (sofort da, kein Skript nötig). Wer
   danach greift, bekommt das Modell in Echtzeit darüber -- dieselbe Kamera,
   dasselbe Licht (produkt-echtzeit.js). Lässt er los, fährt die Kamera zurück
   und das Foto der gewählten Variante blendet wieder ein.

   Eine Variante wechseln braucht KEIN 3D: Für jeden Lack und jede Farbe
   liegt ein eigenes Foto bereit. Das Modell lädt erst, wenn jemand dreht,
   zerlegt oder das Licht einschaltet -- vorher kostet die Bühne nur Bilder.

   Texte für die Anzeigen, die sich ändern, stehen hier (dreisprachig); alles
   Feste steht mit data-i18n im HTML und wird von build.mjs übersetzt.
   ========================================================================== */
const L = (document.documentElement.lang || 'it').slice(0, 2);
const SPRACHE = ['it', 'de', 'en'].includes(L) ? L : 'it';

const TEXTE = {
  de: {
    foto: 'Gerechnet · Blender Cycles · 384 Abtastungen',
    fotoKurz: 'Gerechnet · Blender Cycles',
    echtzeit: (f) => `Echtzeit · WebGL 2${f ? ` · ${f} Bilder/s` : ''}`,
    zerlegt: 'Echtzeit · zerlegt in seine Teile', geoeffnet: 'Echtzeit · geöffnet',
    innen: 'Echtzeit · Fahrerplatz', innenFoto: 'Gerechnet · Blender Cycles · Fahrerplatz',
    laedt: 'Lade das 3D-Modell …',
    ar: { laden: 'Einen Moment – das 3D-Modell lädt. Dann noch einmal tippen.', vorbereiten: 'AR wird vorbereitet …', oeffnen: 'Jetzt in AR öffnen', fehler: 'AR ließ sich auf diesem Gerät nicht starten.', handy: 'AR funktioniert auf dem Handy: iPhone mit Safari oder Android mit Chrome. Öffnen Sie diese Seite dort.', suchen: 'Handy langsam über den Boden bewegen …', tippen: 'Tippen, um es hinzustellen', steht: 'Steht. Zum Umstellen noch einmal tippen.', zu: 'Beenden' },
    logo: 'Ihr Logo darauf', logoWaehlen: 'Logo wählen …', logoWeg: 'Logo entfernen', logoHinweis: 'PNG, JPG, SVG oder WebP. Das Bild bleibt auf Ihrem Gerät.', logoDa: 'So sähe es mit Ihrem Logo aus.', logoFehler: 'Diese Datei ließ sich nicht lesen.', logoGross: 'Bitte ein Bild unter 5 MB.', karteName: 'Name Ihres Restaurants',
    kiste: 'Geschenkkiste', kisteFmt: (n) => `${n} ${n === 1 ? 'Flasche' : 'Flaschen'}`,
    ladung: 'Ladung', ladungFmt: (n, p, lm) => `${n} von 33 Europaletten · Auslastung ${p} % · ${lm} Lademeter`,
    probeTitel: 'Probefahrt anfragen', probeModell: { kleinwagen: 'Kleinwagen', mittelklasse: 'Mittelklasse-Limousine', auto: 'Sportwagen-Studie' },
    gravur: 'Ihre Gravur', gravurPlatz: 'Für Anna – 24.09.2026', gravurHinweis: 'Bis zu drei Zeilen, erscheint sofort auf dem Boden.', karat: 'Stein', karatFmt: (k, mm) => `${k} ct · ${mm} mm`,
    drehen: 'Selbst drehen', zumFoto: 'Zurück zum Foto',
    leinwand: { auto: 'Das Auto in Echtzeit — ziehen oder Pfeiltasten zum Drehen', schuh: 'Der Schuh in Echtzeit — ziehen oder Pfeiltasten zum Drehen' },
    korb: (n, f, g, p) => `${n} · ${f} · Gr. ${g} — ${p}`,
    korbLeer: 'Der Warenkorb ist leer.',
    korbZahl: (n) => `Warenkorb (${n})`,
    groesseFehlt: 'Erst eine Größe wählen.',
    keinWebgl: 'Dieses Gerät zeigt die gerechneten Bilder. Drehen und Zerlegen brauchen WebGL.',
    teile: { tueren: 'Türen', haube: 'Fronthaube', heck: 'Heck mit Rückleuchten', dach: 'Dach', raeder: 'Räder', bremse: 'Bremsscheibe und Sattel', antrieb: 'Antrieb', fahrwerk: 'Fahrwerk', sitze: 'Sitze', obermaterial: 'Obermaterial aus Strick', zwischensohle: 'Zwischensohle aus Schaum', schnuerung: 'Schnürung', himmel: 'Dachhimmel', lenkrad: 'Lenkrad', cockpit: 'Armaturentafel', zylinderkopf: 'Zylinderkopf', turbo: 'Turbolader', getriebe: 'Getriebe', kuehler: 'Kühler', abgas: 'Abgasanlage', rohbau: 'Rohbau, grundiert', kiste: 'Holzkiste – fertig als Geschenk', kapsel: 'Kapsel – schützt den Korken', kork: 'Naturkorken – der Wein atmet', einschenken: 'Im Bordeauxglas – so schmeckt er am besten', pasta: 'Spaghetti al pomodoro – frisch angerichtet', dessert: 'Panna cotta – mit Himbeer-Coulis', glas: 'Saphirglas – praktisch kratzfest', luenette: 'Lünette – schützt das Glas', krone: 'Verschraubte Krone – dicht bis 10 bar', zeiger: 'Zeiger mit Leuchtmasse – ablesbar im Dunkeln', blatt: 'Zifferblatt – Indizes einzeln gesetzt', rotor: 'Rotor – zieht die Uhr beim Tragen auf', unruh: 'Unruh – schlägt 28.800-mal pro Stunde', platine: 'Platine – trägt das ganze Werk', boden: 'Gehäuseboden – Platz für Ihre Gravur', bruecke: 'Brücken – halten die Räder', rad: 'Räderwerk – überträgt die Kraft', tuer: 'Tür mit Topfscharnier', auszug: 'Vollauszug', platte: 'Arbeitsplatte 40 mm', kochfeld: 'Induktionskochfeld', armatur: 'Armatur', becken: 'Unterbaubecken', stuhl: 'Hydraulik, drehbar', plane: 'Schiebeplane – Seitenladung in Minuten', kabine: 'Kippkabine – Motor schnell erreichbar', zwilling: 'Zwillingsbereifung – mehr Traglast', achsen: 'Dreiachsaggregat – 24 t Achslast' },
    modelle: {
      kleinwagen: { alt: 'Kleinwagen in einem dunklen Fotostudio, gerechnet mit Blender Cycles',
        daten: 'Eigener Entwurf · Länge 4,07 m · Breite 1,76 m · Höhe 1,45 m · Radstand 2,57 m',
        bau: 'Dreizylinder quer, Frontantrieb · McPherson vorn, Verbundlenker hinten',
        lacke: { azzurro: 'Azurblau Metallic', bianco: 'Uni-Weiß', salvia: 'Salbeigrün Metallic' },
        innen: { 'stoff-anthrazit': 'Stoff Anthrazit', 'stoff-grau-blau': 'Stoff Grau-Blau', 'kunstleder-hell': 'Kunstleder Hell' }, innenAlt: 'Fahrerplatz des Kleinwagens, gerechnet mit Blender Cycles' },
      mittelklasse: { alt: 'Mittelklasse-Limousine in einem dunklen Fotostudio, gerechnet mit Blender Cycles',
        daten: 'Eigener Entwurf · Länge 4,76 m · Breite 1,83 m · Höhe 1,44 m · Radstand 2,85 m',
        bau: 'Vierzylinder längs, Hinterradantrieb · Federbeine vorn, Mehrlenker hinten',
        lacke: { blunotte: 'Nachtblau Metallic', argento: 'Silber Metallic', rosso: 'Rot Metallic' },
        innen: { 'stoff-anthrazit': 'Stoff Anthrazit', 'leder-cognac': 'Leder Cognac', 'leder-elfenbein': 'Leder Elfenbein' }, innenAlt: 'Fahrerplatz der Limousine, gerechnet mit Blender Cycles' },
      auto: { alt: 'Karminroter Sportwagen in einem dunklen Fotostudio, gerechnet mit Blender Cycles',
        daten: 'Designstudie · Länge 4,36 m · Höhe 1,31 m', bau: 'Konzeptauto „CarConcept“ von Khronos (CC BY 4.0)',
        lacke: { karmin: 'Karminrot', perl: 'Perlweiß', graphit: 'Graphit' } },
    },
  },
  it: {
    foto: 'Calcolato · Blender Cycles · 384 campioni',
    fotoKurz: 'Calcolato · Blender Cycles',
    echtzeit: (f) => `Tempo reale · WebGL 2${f ? ` · ${f} fps` : ''}`,
    zerlegt: 'Tempo reale · scomposto nei suoi pezzi', geoeffnet: 'Tempo reale · aperto',
    innen: 'Tempo reale · posto guida', innenFoto: 'Calcolato · Blender Cycles · posto guida',
    laedt: 'Carico il modello 3D …',
    ar: { laden: 'Un momento – il modello 3D si carica. Poi tocchi di nuovo.', vorbereiten: 'Preparo l’AR …', oeffnen: 'Apri in AR', fehler: 'Su questo dispositivo l’AR non è partita.', handy: 'L’AR funziona sul telefono: iPhone con Safari o Android con Chrome. Apra lì questa pagina.', suchen: 'Muova piano il telefono sopra il pavimento …', tippen: 'Tocchi per posizionarlo', steht: 'Fatto. Tocchi di nuovo per spostarlo.', zu: 'Esci' },
    logo: 'Il suo logo sopra', logoWaehlen: 'Scelga il logo …', logoWeg: 'Rimuova il logo', logoHinweis: 'PNG, JPG, SVG o WebP. L’immagine resta sul suo dispositivo.', logoDa: 'Ecco come sarebbe con il suo logo.', logoFehler: 'Non riesco a leggere questo file.', logoGross: 'Un’immagine sotto i 5 MB, per favore.', karteName: 'Nome del suo ristorante',
    kiste: 'Cassetta regalo', kisteFmt: (n) => `${n} ${n === 1 ? 'bottiglia' : 'bottiglie'}`,
    ladung: 'Carico', ladungFmt: (n, p, lm) => `${n} di 33 europallet · riempimento ${p} % · ${lm} metri di carico`,
    probeTitel: 'Richiesta di un giro di prova', probeModell: { kleinwagen: 'Utilitaria', mittelklasse: 'Berlina media', auto: 'Studio di sportiva' },
    gravur: 'La sua incisione', gravurPlatz: 'Per Anna – 24.09.2026', gravurHinweis: 'Fino a tre righe, appare subito sul fondello.', karat: 'Pietra', karatFmt: (k, mm) => `${k} ct · ${mm} mm`,
    drehen: 'Lo giri Lei', zumFoto: 'Torni alla foto',
    leinwand: { auto: 'L’auto in tempo reale — trascini o usi le frecce per girarla', schuh: 'La scarpa in tempo reale — trascini o usi le frecce per girarla' },
    korb: (n, f, g, p) => `${n} · ${f} · tg. ${g} — ${p}`,
    korbLeer: 'Il carrello è vuoto.',
    korbZahl: (n) => `Carrello (${n})`,
    groesseFehlt: 'Scelga prima una taglia.',
    keinWebgl: 'Questo dispositivo mostra le immagini calcolate. Girare e scomporre richiedono WebGL.',
    teile: { tueren: 'Portiere', haube: 'Cofano', heck: 'Coda con fanali', dach: 'Tetto', raeder: 'Ruote', bremse: 'Disco e pinza', antrieb: 'Motore', fahrwerk: 'Sospensioni', sitze: 'Sedili', obermaterial: 'Tomaia in maglia', zwischensohle: 'Intersuola in schiuma', schnuerung: 'Allacciatura', himmel: 'Cielo', lenkrad: 'Volante', cockpit: 'Plancia', zylinderkopf: 'Testata', turbo: 'Turbocompressore', getriebe: 'Cambio', kuehler: 'Radiatore', abgas: 'Scarico', rohbau: 'Scocca con fondo', kiste: 'Cassetta in legno – già pronta da regalare', kapsel: 'Capsula – protegge il tappo', kork: 'Tappo in sughero – il vino respira', einschenken: 'Nel calice Bordeaux – così dà il meglio', pasta: 'Spaghetti al pomodoro – impiattati al momento', dessert: 'Panna cotta – con coulis di lamponi', glas: 'Vetro zaffiro – quasi antigraffio', luenette: 'Lunetta – protegge il vetro', krone: 'Corona a vite – tenuta 10 bar', zeiger: 'Lancette luminescenti – leggibili al buio', blatt: 'Quadrante – indici applicati uno a uno', rotor: 'Rotore – carica l’orologio mentre lo indossa', unruh: 'Bilanciere – 28.800 alternanze l’ora', platine: 'Platina – porta tutto il movimento', boden: 'Fondello – spazio per la sua incisione', bruecke: 'Ponti – tengono le ruote', rad: 'Ruotismo – trasmette la forza', tuer: 'Anta con cerniera a scomparsa', auszug: 'Cassetto a estrazione totale', platte: 'Piano 40 mm', kochfeld: 'Piano a induzione', armatur: 'Miscelatore', becken: 'Lavello sottotop', stuhl: 'Idraulica, girevole', plane: 'Telone scorrevole – carico laterale in pochi minuti', kabine: 'Cabina ribaltabile – motore subito accessibile', zwilling: 'Ruote gemellate – più portata', achsen: 'Gruppo a tre assi – 24 t sugli assi' },
    modelle: {
      kleinwagen: { alt: 'Utilitaria in uno studio fotografico scuro, calcolata con Blender Cycles',
        daten: 'Progetto proprio · lunghezza 4,07 m · larghezza 1,76 m · altezza 1,45 m · passo 2,57 m',
        bau: 'Tre cilindri trasversale, trazione anteriore · McPherson davanti, ponte torcente dietro',
        lacke: { azzurro: 'Azzurro metallizzato', bianco: 'Bianco pastello', salvia: 'Verde salvia metallizzato' },
        innen: { 'stoff-anthrazit': 'Tessuto antracite', 'stoff-grau-blau': 'Tessuto grigio-blu', 'kunstleder-hell': 'Similpelle chiara' }, innenAlt: 'Posto guida dell’utilitaria, calcolato con Blender Cycles' },
      mittelklasse: { alt: 'Berlina media in uno studio fotografico scuro, calcolata con Blender Cycles',
        daten: 'Progetto proprio · lunghezza 4,76 m · larghezza 1,83 m · altezza 1,44 m · passo 2,85 m',
        bau: 'Quattro cilindri longitudinale, trazione posteriore · montanti davanti, multilink dietro',
        lacke: { blunotte: 'Blu notte metallizzato', argento: 'Argento metallizzato', rosso: 'Rosso metallizzato' },
        innen: { 'stoff-anthrazit': 'Tessuto antracite', 'leder-cognac': 'Pelle cognac', 'leder-elfenbein': 'Pelle avorio' }, innenAlt: 'Posto guida della berlina, calcolato con Blender Cycles' },
      auto: { alt: 'Auto sportiva rosso carminio in uno studio fotografico scuro, calcolata con Blender Cycles',
        daten: 'Studio di design · lunghezza 4,36 m · altezza 1,31 m', bau: 'Concept car «CarConcept» di Khronos (CC BY 4.0)',
        lacke: { karmin: 'Rosso carminio', perl: 'Bianco perla', graphit: 'Grafite' } },
    },
  },
  en: {
    foto: 'Rendered · Blender Cycles · 384 samples',
    fotoKurz: 'Rendered · Blender Cycles',
    echtzeit: (f) => `Real time · WebGL 2${f ? ` · ${f} fps` : ''}`,
    zerlegt: 'Real time · taken apart', geoeffnet: 'Real time · opened',
    innen: 'Real time · driver’s seat', innenFoto: 'Rendered · Blender Cycles · driver’s seat',
    laedt: 'Loading the 3D model …',
    ar: { laden: 'One moment – the 3D model is loading. Then tap again.', vorbereiten: 'Preparing AR …', oeffnen: 'Open in AR now', fehler: 'AR could not start on this device.', handy: 'AR works on phones: iPhone with Safari or Android with Chrome. Open this page there.', suchen: 'Move your phone slowly over the floor …', tippen: 'Tap to place it', steht: 'Placed. Tap again to move it.', zu: 'Exit' },
    logo: 'Your logo on it', logoWaehlen: 'Choose logo …', logoWeg: 'Remove logo', logoHinweis: 'PNG, JPG, SVG or WebP. The image stays on your device.', logoDa: 'This is how it looks with your logo.', logoFehler: 'This file could not be read.', logoGross: 'Please use an image under 5 MB.', karteName: 'Your restaurant’s name',
    kiste: 'Gift case', kisteFmt: (n) => `${n} ${n === 1 ? 'bottle' : 'bottles'}`,
    ladung: 'Load', ladungFmt: (n, p, lm) => `${n} of 33 Euro pallets · ${p} % full · ${lm} loading metres`,
    probeTitel: 'Request a test drive', probeModell: { kleinwagen: 'Small car', mittelklasse: 'Mid-size saloon', auto: 'Sports car study' },
    gravur: 'Your engraving', gravurPlatz: 'For Anna – 24.09.2026', gravurHinweis: 'Up to three lines, appears on the case back at once.', karat: 'Stone', karatFmt: (k, mm) => `${k} ct · ${mm} mm`,
    drehen: 'Turn it yourself', zumFoto: 'Back to the photo',
    leinwand: { auto: 'The car in real time — drag or use the arrow keys to turn it', schuh: 'The shoe in real time — drag or use the arrow keys to turn it' },
    korb: (n, f, g, p) => `${n} · ${f} · size ${g} — ${p}`,
    korbLeer: 'The cart is empty.',
    korbZahl: (n) => `Cart (${n})`,
    groesseFehlt: 'Pick a size first.',
    keinWebgl: 'This device shows the rendered images. Turning and taking apart need WebGL.',
    teile: { tueren: 'Doors', haube: 'Bonnet', heck: 'Rear with tail lights', dach: 'Roof', raeder: 'Wheels', bremse: 'Disc and caliper', antrieb: 'Drivetrain', fahrwerk: 'Suspension', sitze: 'Seats', obermaterial: 'Knit upper', zwischensohle: 'Foam midsole', schnuerung: 'Laces', himmel: 'Headliner', lenkrad: 'Steering wheel', cockpit: 'Dashboard', zylinderkopf: 'Cylinder head', turbo: 'Turbocharger', getriebe: 'Gearbox', kuehler: 'Radiator', abgas: 'Exhaust', rohbau: 'Body shell, primed', kiste: 'Wooden case – ready to give', kapsel: 'Capsule – protects the cork', kork: 'Natural cork – lets the wine breathe', einschenken: 'In a Bordeaux glass – where it tastes best', pasta: 'Spaghetti al pomodoro – freshly plated', dessert: 'Panna cotta – with raspberry coulis', glas: 'Sapphire crystal – virtually scratch-proof', luenette: 'Bezel – protects the crystal', krone: 'Screw-down crown – sealed to 10 bar', zeiger: 'Luminous hands – readable in the dark', blatt: 'Dial – indices set one by one', rotor: 'Rotor – winds the watch as you wear it', unruh: 'Balance – 28,800 beats an hour', platine: 'Main plate – carries the movement', boden: 'Case back – room for your engraving', bruecke: 'Bridges – hold the wheels', rad: 'Gear train – passes on the power', tuer: 'Door with concealed hinge', auszug: 'Full-extension drawer', platte: 'Worktop 40 mm', kochfeld: 'Induction hob', armatur: 'Tap', becken: 'Undermount sink', stuhl: 'Hydraulic, swivelling', plane: 'Sliding curtain – side loading in minutes', kabine: 'Tilting cab – engine within reach', zwilling: 'Twin tyres – more payload', achsen: 'Tri-axle bogie – 24 t axle load' },
    modelle: {
      kleinwagen: { alt: 'Small car in a dark photo studio, rendered with Blender Cycles',
        daten: 'Our own design · length 4.07 m · width 1.76 m · height 1.45 m · wheelbase 2.57 m',
        bau: 'Transverse three-cylinder, front-wheel drive · MacPherson front, twist beam rear',
        lacke: { azzurro: 'Azure blue metallic', bianco: 'Solid white', salvia: 'Sage green metallic' },
        innen: { 'stoff-anthrazit': 'Anthracite cloth', 'stoff-grau-blau': 'Grey-blue cloth', 'kunstleder-hell': 'Light leatherette' }, innenAlt: 'Driver’s seat of the small car, rendered with Blender Cycles' },
      mittelklasse: { alt: 'Mid-size saloon in a dark photo studio, rendered with Blender Cycles',
        daten: 'Our own design · length 4.76 m · width 1.83 m · height 1.44 m · wheelbase 2.85 m',
        bau: 'Longitudinal four-cylinder, rear-wheel drive · struts front, multi-link rear',
        lacke: { blunotte: 'Midnight blue metallic', argento: 'Silver metallic', rosso: 'Red metallic' },
        innen: { 'stoff-anthrazit': 'Anthracite cloth', 'leder-cognac': 'Cognac leather', 'leder-elfenbein': 'Ivory leather' }, innenAlt: 'Driver’s seat of the saloon, rendered with Blender Cycles' },
      auto: { alt: 'Carmine red sports car in a dark photo studio, rendered with Blender Cycles',
        daten: 'Design study · length 4.36 m · height 1.31 m', bau: 'Khronos “CarConcept” concept car (CC BY 4.0)',
        lacke: { karmin: 'Carmine red', perl: 'Pearl white', graphit: 'Graphite' } },
    },
  },
};
const TEXT = TEXTE[SPRACHE];

/* Produktdemos der Galerie (24.09.2026): eine Bühne, das Modell wechselt je
   Kachel. Texte je Sprache, Varianten und Schritte je Produkt. Die Schritte
   sind die Stufen des Zerlegens (kamera.json -> zerlegen.stufen). */
const PRODUKT_TEXTE = {
  de: {
    stufen: 'Ansicht', ganz: 'Geschlossen', auswahl: 'Produkt',
    wein: { kunde: { knopf: 'So sieht es Ihre Kundschaft: als Geschenk bestellen', titel: 'Geschenkbox bestellen', zeilen: ['Holzkiste mit Tür', 'Grußkarte handgeschrieben'] }, kicker: 'Wein & Naturprodukte', titel: 'Flasche mit Etikett zum Wählen', text: 'Drei Produkte aus einer Datei: Glas, Inhalt, Kapsel und Etikett wechseln zusammen, das Foto kommt gerechnet. Die Holzkiste öffnet sich, die Flasche gibt Kapsel und Korken frei — so sieht man, was man verschenkt.', alt: 'Weinflasche und Holzkiste im dunklen Studio, gerechnet mit Blender Cycles', cta: 'So etwas für meine Kellerei', varianten: { rosso: 'Nero d’Avola', bianco: 'Grillo', olio: 'Olivenöl extra vergine' }, schritte: ['Kiste öffnen', 'Flasche öffnen', 'Einschenken'] },
    schmuck: { kunde: { knopf: 'So sieht es Ihre Kundschaft: zur Anprobe reservieren', titel: 'Anprobe im Geschäft reservieren', zeilen: ['Uhr und Ring liegen bereit'] }, kicker: 'Schmuck & Uhren', titel: 'Uhr und Ring im Makrolicht', text: 'Metall, Zifferblatt, Band und Stein wechseln zusammen — Edelstahl, Gelbgold oder Roségold, gerechnet wie im Fotostudio. Die Uhr zerfällt in all ihre Teile, jedes frei sichtbar, und legt sich danach geordnet aufs Uhrmacher-Tablett. Den Gehäuseboden graviert der Kunde selbst, und beim Ring wählt er das Karat.', alt: 'Automatikuhr und Solitärring im dunklen Studio, gerechnet mit Blender Cycles', cta: 'So etwas für mein Juweliergeschäft', varianten: { stahl: 'Edelstahl · Saphir', gelbgold: 'Gelbgold · Diamant', rosegold: 'Roségold · Rubin' }, schritte: ['Explosionsansicht', 'Uhrmacher-Tablett', 'Gravur', 'Ring & Karat'] },
    kueche: { kicker: 'Küchenbau', titel: 'Kochinsel zum Aufmachen', text: 'Fronten, Arbeitsplatte und Griffe wechseln zusammen — Salbei mit Eiche, Weiß mit Carrara-Marmor, Nussbaum mit Keramik. Türen und Auszüge öffnen sich, die Platte hebt ab: So sieht der Kunde vor dem Aufmaß, was er bekommt.', alt: 'Kochinsel mit Spüle und Kochfeld im dunklen Studio, gerechnet mit Blender Cycles', cta: 'So etwas für mein Küchenstudio', varianten: { salbei: 'Salbei · Eiche · Messing', weiss: 'Weiß · Carrara · Edelstahl', nussbaum: 'Nussbaum · Keramik · Schwarz' }, schritte: ['Öffnen', 'Platte abheben'] },
    gastro: { kunde: { knopf: 'So sieht es Ihr Gast: Tisch reservieren', titel: 'Tisch reservieren', zeilen: ['Tisch am Fenster'] }, kicker: 'Gastronomie', titel: 'Tisch für zwei, gedeckt', text: 'Tischdecke und Geschirr wechseln zusammen — weißes Leinen mit Porzellan, Terrakotta mit Steingut, Anthrazit mit schwarzem Steingut. Gedeckt nach der Grundregel des Service; am Abend trägt die Kerze das Licht. Dann kommen Pasta und Dessert auf den Tisch, und die Menükarte trägt den Namen Ihres Restaurants.', alt: 'Gedeckter Tisch für zwei mit Kerze im dunklen Studio, gerechnet mit Blender Cycles', cta: 'So etwas für mein Restaurant', varianten: { weiss: 'Leinen weiß · Porzellan', terrakotta: 'Terrakotta · Steingut sand', anthrazit: 'Anthrazit · Steingut schwarz' }, ganz: 'Tag', schritte: ['Abend', 'Servieren', 'Dessert'] },
    salon: { kunde: { knopf: 'So sieht es Ihre Kundschaft: Termin buchen', titel: 'Termin buchen', zeilen: ['Schneiden & Föhnen'] }, kicker: 'Friseur & Salon', titel: 'Bedienplatz mit Stuhl und Spiegel', text: 'Polster, Metall und Wand wechseln zusammen — Cognac mit Messing auf Salbei, Schwarz mit Chrom auf Kalk, Samt in Petrol mit Schwarz auf Anthrazit. Der Stuhl dreht sich zum Gast und fährt hoch; am Abend leuchtet nur der Spiegel.', alt: 'Friseurstuhl vor hinterleuchtetem Spiegel im dunklen Studio, gerechnet mit Blender Cycles', cta: 'So etwas für meinen Salon', varianten: { cognac: 'Cognac · Messing · Salbei', schwarz: 'Schwarz · Chrom · Kalk', petrol: 'Samt Petrol · Schwarz · Anthrazit' }, schritte: ['Stuhl drehen', 'Abendlicht'] },
    lkw: { kunde: { knopf: 'So sieht es Ihr Kunde: Transport anfragen', titel: 'Transport anfragen', zeilen: ['Planenauflieger 13,6 m, Seitenladung'] }, kicker: 'Logistik', titel: 'Sattelzug mit Ladung', text: 'Zugmaschine und Planenauflieger nach EU-Maßen, 16,5 Meter, mit Europaletten beladen. Kabine und Plane wechseln die Farbe mit Ihrer Flotte; die Plane fährt zur Seite, die Kabine kippt, die Achsen gehen auseinander.', alt: 'Sattelzug mit Planenauflieger im dunklen Studio, gerechnet mit Blender Cycles', cta: 'So etwas für meine Spedition', varianten: { rot: 'Rot · Plane weiß', weiss: 'Weiß · Plane grau', blau: 'Blau · Plane blau' }, schritte: ['Plane öffnen', 'Kabine kippen', 'Achsen'] },
  },
  it: {
    stufen: 'Vista', ganz: 'Chiusa', auswahl: 'Prodotto',
    wein: { kunde: { knopf: 'Come lo vede il cliente: lo ordini come regalo', titel: 'Ordine della confezione regalo', zeilen: ['Cassetta in legno con anta', 'Biglietto scritto a mano'] }, kicker: 'Vino e prodotti naturali', titel: 'Bottiglia con etichetta a scelta', text: 'Tre prodotti in un solo file: vetro, contenuto, capsula ed etichetta cambiano insieme, la foto arriva già calcolata. La cassetta di legno si apre, la bottiglia libera capsula e tappo — così si vede cosa si regala.', alt: 'Bottiglia di vino e cassetta di legno in studio scuro, calcolate con Blender Cycles', cta: 'Una cosa così per la mia cantina', varianten: { rosso: 'Nero d’Avola', bianco: 'Grillo', olio: 'Olio extra vergine' }, schritte: ['Apra la cassetta', 'Apra la bottiglia', 'Versi il vino'] },
    schmuck: { kunde: { knopf: 'Come lo vede il cliente: lo prenoti per provarlo', titel: 'Prenotazione di una prova in negozio', zeilen: ['Orologio e anello pronti per Lei'] }, kicker: 'Gioielli e orologi', titel: 'Orologio e anello in luce macro', text: 'Metallo, quadrante, cinturino e pietra cambiano insieme — acciaio, oro giallo o oro rosa, calcolati come in studio fotografico. L’orologio si scompone in tutti i suoi pezzi, ognuno ben visibile, e poi si dispone in ordine sul vassoio dell’orologiaio. Il fondello lo incide il cliente stesso, e per l’anello sceglie i carati.', alt: 'Orologio automatico e anello solitario in studio scuro, calcolati con Blender Cycles', cta: 'Una cosa così per la mia gioielleria', varianten: { stahl: 'Acciaio · Zaffiro', gelbgold: 'Oro giallo · Diamante', rosegold: 'Oro rosa · Rubino' }, schritte: ['Esploso', 'Vassoio dell’orologiaio', 'Incisione', 'Anello e carati'] },
    kueche: { kicker: 'Cucine su misura', titel: 'Isola da aprire', text: 'Ante, piano e maniglie cambiano insieme — salvia con rovere, bianco con marmo di Carrara, noce con ceramica. Ante e cassetti si aprono, il piano si solleva: il cliente vede cosa riceve prima del rilievo.', alt: 'Isola cucina con lavello e piano cottura in studio scuro, calcolata con Blender Cycles', cta: 'Una cosa così per il mio showroom', varianten: { salbei: 'Salvia · Rovere · Ottone', weiss: 'Bianco · Carrara · Acciaio', nussbaum: 'Noce · Ceramica · Nero' }, schritte: ['Apra l’isola', 'Sollevi il piano'] },
    gastro: { kunde: { knopf: 'Come lo vede l’ospite: prenoti un tavolo', titel: 'Prenotazione di un tavolo', zeilen: ['Tavolo alla finestra'] }, kicker: 'Ristorazione', titel: 'Tavolo per due, apparecchiato', text: 'Tovaglia e stoviglie cambiano insieme — lino bianco con porcellana, terracotta con gres, antracite con gres nero. Apparecchiato secondo le regole del servizio; di sera la luce è quella della candela. Poi arrivano pasta e dessert, e il menu porta il nome del suo ristorante.', alt: 'Tavolo apparecchiato per due con candela in studio scuro, calcolato con Blender Cycles', cta: 'Una cosa così per il mio ristorante', varianten: { weiss: 'Lino bianco · Porcellana', terrakotta: 'Terracotta · Gres sabbia', anthrazit: 'Antracite · Gres nero' }, ganz: 'Giorno', schritte: ['Sera', 'In tavola', 'Dolce'] },
    salon: { kunde: { knopf: 'Come lo vede il cliente: prenoti', titel: 'Prenotazione di un appuntamento', zeilen: ['Taglio e piega'] }, kicker: 'Parrucchieri e saloni', titel: 'Postazione con poltrona e specchio', text: 'Imbottitura, metallo e parete cambiano insieme — cuoio con ottone su salvia, nero con cromo su calce, velluto petrolio con nero su antracite. La poltrona si gira verso il cliente e si alza; di sera resta acceso solo lo specchio.', alt: 'Poltrona da parrucchiere davanti a uno specchio retroilluminato in studio scuro, calcolata con Blender Cycles', cta: 'Una cosa così per il mio salone', varianten: { cognac: 'Cuoio · Ottone · Salvia', schwarz: 'Nero · Cromo · Calce', petrol: 'Velluto petrolio · Nero · Antracite' }, schritte: ['Giri la poltrona', 'Luce serale'] },
    lkw: { kunde: { knopf: 'Come lo vede il cliente: richieda un trasporto', titel: 'Richiesta di trasporto', zeilen: ['Semirimorchio centinato 13,6 m, carico laterale'] }, kicker: 'Logistica', titel: 'Autoarticolato con carico', text: 'Trattore e semirimorchio centinato a misure UE, 16,5 metri, carico di europallet. Cabina e telone prendono i colori della sua flotta; il telone scorre, la cabina si ribalta, gli assi si separano.', alt: 'Autoarticolato con semirimorchio centinato in studio scuro, calcolato con Blender Cycles', cta: 'Una cosa così per la mia azienda di trasporti', varianten: { rot: 'Rosso · Telone bianco', weiss: 'Bianco · Telone grigio', blau: 'Blu · Telone blu' }, schritte: ['Apra il telone', 'Ribalti la cabina', 'Assi'] },
  },
  en: {
    stufen: 'View', ganz: 'Closed', auswahl: 'Product',
    wein: { kunde: { knopf: 'What your customers see: order as a gift', titel: 'Order the gift box', zeilen: ['Wooden case with door', 'Handwritten card'] }, kicker: 'Wine & natural products', titel: 'Bottle with a label to choose', text: 'Three products from one file: glass, contents, capsule and label change together, the photo comes pre-rendered. The wooden case opens, the bottle releases capsule and cork — so people see what they are giving.', alt: 'Wine bottle and wooden case in a dark studio, rendered with Blender Cycles', cta: 'Something like this for my winery', varianten: { rosso: 'Nero d’Avola', bianco: 'Grillo', olio: 'Extra virgin olive oil' }, schritte: ['Open the case', 'Open the bottle', 'Pour'] },
    schmuck: { kunde: { knopf: 'What your customers see: reserve a fitting', titel: 'Reserve a fitting in store', zeilen: ['Watch and ring set aside for you'] }, kicker: 'Jewellery & watches', titel: 'Watch and ring in macro light', text: 'Metal, dial, strap and stone change together — steel, yellow gold or rose gold, rendered like a photo studio. The watch comes apart into every single part, each one in plain view, then lays itself out on the watchmaker’s tray. Customers engrave the case back themselves and pick the carat for the ring.', alt: 'Automatic watch and solitaire ring in a dark studio, rendered with Blender Cycles', cta: 'Something like this for my jewellery shop', varianten: { stahl: 'Steel · Sapphire', gelbgold: 'Yellow gold · Diamond', rosegold: 'Rose gold · Ruby' }, schritte: ['Exploded view', 'Watchmaker’s tray', 'Engraving', 'Ring & carat'] },
    kueche: { kicker: 'Kitchen design', titel: 'An island that opens', text: 'Fronts, worktop and handles change together — sage with oak, white with Carrara marble, walnut with ceramic. Doors and drawers open, the worktop lifts: customers see what they get before the survey.', alt: 'Kitchen island with sink and hob in a dark studio, rendered with Blender Cycles', cta: 'Something like this for my kitchen studio', varianten: { salbei: 'Sage · Oak · Brass', weiss: 'White · Carrara · Steel', nussbaum: 'Walnut · Ceramic · Black' }, schritte: ['Open', 'Lift the worktop'] },
    gastro: { kunde: { knopf: 'What your guests see: book a table', titel: 'Book a table', zeilen: ['Window table'] }, kicker: 'Restaurants', titel: 'A table for two, laid', text: 'Tablecloth and tableware change together — white linen with porcelain, terracotta with stoneware, charcoal with black stoneware. Laid by the rules of service; in the evening the candle carries the light. Then pasta and dessert are served, and the menu card carries your restaurant’s name.', alt: 'Table laid for two with a candle in a dark studio, rendered with Blender Cycles', cta: 'Something like this for my restaurant', varianten: { weiss: 'White linen · Porcelain', terrakotta: 'Terracotta · Sand stoneware', anthrazit: 'Charcoal · Black stoneware' }, ganz: 'Day', schritte: ['Evening', 'Serve', 'Dessert'] },
    salon: { kunde: { knopf: 'What your customers see: book', titel: 'Book an appointment', zeilen: ['Cut & blow-dry'] }, kicker: 'Hair & salon', titel: 'Styling station with chair and mirror', text: 'Upholstery, metal and wall change together — cognac with brass on sage, black with chrome on lime plaster, petrol velvet with black on charcoal. The chair turns to the guest and rises; in the evening only the mirror glows.', alt: 'Styling chair in front of a backlit mirror in a dark studio, rendered with Blender Cycles', cta: 'Something like this for my salon', varianten: { cognac: 'Cognac · Brass · Sage', schwarz: 'Black · Chrome · Lime', petrol: 'Petrol velvet · Black · Charcoal' }, schritte: ['Turn the chair', 'Evening light'] },
    lkw: { kunde: { knopf: 'What your customers see: request a transport', titel: 'Request a transport', zeilen: ['Curtainsider 13.6 m, side loading'] }, kicker: 'Logistics', titel: 'Articulated lorry with load', text: 'Tractor unit and curtainsider to EU dimensions, 16.5 metres, loaded with Euro pallets. Cab and curtain take your fleet colours; the curtain slides open, the cab tilts, the axles come apart.', alt: 'Articulated lorry with curtainsider trailer in a dark studio, rendered with Blender Cycles', cta: 'Something like this for my haulage company', varianten: { rot: 'Red · White curtain', weiss: 'White · Grey curtain', blau: 'Blue · Blue curtain' }, schritte: ['Open the curtain', 'Tilt the cab', 'Axles'] },
  },
}[SPRACHE];
const PRODUKTE = {
  wein: { mb: 0.9, kunde: {}, varianten: [['rosso', '#5b0f1c'], ['bianco', '#d9cf8a'], ['olio', '#66751f']] },
  schmuck: { mb: 1.2, kunde: { termin: { zeiten: ['10:00', '12:00', '15:00', '17:30'] } }, varianten: [['stahl', '#b9bcc0'], ['gelbgold', '#d9b25a'], ['rosegold', '#d99a86']] },
  kueche: { mb: 1.6, varianten: [['salbei', '#6f7f6c'], ['weiss', '#e4e2dc'], ['nussbaum', '#5a3a24']] },
  lkw: { mb: 1.1, kunde: { termin: { zeiten: ['06:00', '08:00', '10:00', '14:00'] } }, varianten: [['rot', '#8f1519'], ['weiss', '#e6e8ec'], ['blau', '#1f2c52']] },
  salon: { mb: 1.2, licht: 2, varianten: [['cognac', '#8a4a22'], ['schwarz', '#1d1d1f'], ['petrol', '#15474d']] },
  gastro: { mb: 1.8, licht: 1, kunde: { personen: true, termin: { zeiten: ['12:30', '13:15', '19:00', '19:30', '20:30'] } }, varianten: [['weiss', '#e8e6e0'], ['terrakotta', '#a4492c'], ['anthrazit', '#3a3b3e']] },
};
const $ = (s, w = document) => w.querySelector(s);
const $$ = (s, w = document) => [...w.querySelectorAll(s)];

/* Stand der gerechneten Bilder: JavaScript setzt diese Adressen, nicht
   build.mjs -- wer neu rechnet, zählt hier hoch (der Server gibt Bildern
   dreißig Tage). */
const BILD_STAND = '5';
/* Dasselbe für Modelle, Umgebungen und Kameradaten unter assets/3d/branchen. */
const MODELL_STAND = '7';
const PFAD = '/assets/img/erlebnis/branchen/';
/* Modelle der Automotive-Demo: Lackschluessel (= Dateiname der Fotos und
   Reihenfolge der Varianten im GLB), Farbe des Punkts, gemessene Uebertragung
   (gzip, samt three.js) und Dreiecke der Echtzeitfassung. */
const MODELLE = {
  kleinwagen: { mb: 3.2, dreiecke: 387222, lacke: [['azzurro', '#2a64ad'], ['bianco', '#ebeae5'], ['salvia', '#8a9d8c']],
    innen: [['stoff-anthrazit', '#2b2c30'], ['stoff-grau-blau', '#3d5578'], ['kunstleder-hell', '#b9b8b3']] },
  mittelklasse: { mb: 3.2, dreiecke: 384108, lacke: [['blunotte', '#1f2c52'], ['argento', '#b8bbbf'], ['rosso', '#8f1519']],
    innen: [['stoff-anthrazit', '#2b2c30'], ['leder-cognac', '#8a4a22'], ['leder-elfenbein', '#d8cdb4']] },
  auto: { mb: 2.9, dreiecke: 213347, lacke: [['karmin', '#b3121c'], ['perl', '#e6e8ec'], ['graphit', '#55595f']] },
};

/* Stufe aus dem Erlebnisteil (erlebnis.js misst das Gerät). Die Spiegelung
   im Boden rendert das Modell ein zweites Mal -- erst ab HIGH. */
const STUFEN = {
  LOW: { pixel: 1, spiegel: false },
  MEDIUM: { pixel: 1, spiegel: false },
  HIGH: { pixel: 1.5, spiegel: true },
  ULTRA: { pixel: 2, spiegel: true },
};
function stufe() {
  const s = document.documentElement.dataset.erlebnisStufe || 'HIGH';
  return STUFEN[s] || STUFEN.HIGH;
}
/* Einmal fragen, dann merken -- und den Probe-Kontext sofort freigeben.
   Bis zum 23.09.2026 legte jeder Aufruf einen neuen WebGL-Kontext an (vier
   beim Seitenstart, keiner freigegeben). Browser erlauben nur eine Handvoll
   gleichzeitig; der älteste geht dann verloren -- im schlimmsten Fall der
   der Villa. Gemessen kostete das beim Start am gedrosselten Telefon den
   größten Teil der 148 ms, die branchen.js auf dem Hauptfaden brauchte. */
let webglAntwort = null;
function webglDa() {
  if (webglAntwort === null) {
    try { const c = document.createElement('canvas'); const g = c.getContext('webgl2'); webglAntwort = !!g; if (g) g.getExtension('WEBGL_lose_context')?.loseContext(); } catch { webglAntwort = false; }
  }
  return webglAntwort;
}

/* AVIF, wo der Browser es kann (23.09.2026; dieselbe Pruefung wie in erlebnis.js): bei gleicher Treue zum
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

/* Zählen, welche Demo genutzt wird (d.php: keine IP, kein Cookie, nur
   Datum, Stunde, Ereignis, Geräteart). Jedes Ereignis einmal je Seitenaufruf. */
const GEZAEHLT = new Set();
function zaehlen(e) {
  if (GEZAEHLT.has(e)) return; GEZAEHLT.add(e);
  try { navigator.sendBeacon ? navigator.sendBeacon(`/d.php?e=${e}`) : fetch(`/d.php?e=${e}`, { method: 'POST', keepalive: true }); } catch { /* egal */ }
}

// Welche Etiketten auf schmalen Bühnen bleiben (Schuh: alle drei).
const KNAPP = new Map([['tueren', true], ['haube', true], ['raeder', true], ['antrieb', true],
  ['heck', false], ['dach', false], ['bremse', false], ['sitze', false], ['fahrwerk', false]]);

/* ---------------------------------------------------------------- Bühne */
function buehneAnlegen(fig) {
  // Beim Auto umschaltbar (Kleinwagen, Mittelklasse, Sportwagen): alles, was
  // Adressen baut, liest den aktuellen Wert.
  let modell = fig.dataset.modell;
  const ruheA = $('.ruhe-a', fig); const ruheB = $('.ruhe-b', fig);
  const knopf = $('.bewegen', fig); const knopfText = $('.bewegen__text', fig);
  const kennung = $('.kennung__text', fig);
  const halter = $('.echtzeit', fig);
  /* Etiketten der Baugruppen im zerlegten Zustand -- wie in einer
     technischen Zeichnung: Punkt am Teil, kurze Linie, Name. Sie liegen im
     DOM über der Leinwand (lesbar, übersetzbar, für Vorleser zugänglich) und
     folgen jedem Bild; die Lage rechnet produkt-echtzeit.js. */
  const schicht = document.createElement('div');
  schicht.className = 'bd-etiketten'; schicht.setAttribute('aria-hidden', 'true');
  const SVG = 'http://www.w3.org/2000/svg';
  const linien = document.createElementNS(SVG, 'svg'); linien.setAttribute('class', 'bd-linien');
  schicht.appendChild(linien); fig.appendChild(schicht);
  const etiketten = new Map();
  /* Lage wie bei einer Explosionszeichnung: Das Etikett sitzt vom Teil aus
     gesehen nach außen (weg von der Mitte des Modells), eine dünne Linie
     führt zum Punkt am Teil. Überlappen sich zwei, schieben sie sich in ein
     paar Runden auseinander. Die erste Fassung stapelte alle Namen senkrecht
     über den Teilen -- bei acht Teilen ein Turm aus Schildern. */
  function ankerZeigen(liste, mitte) {
    /* Auf dem Telefon (Bühne unter 520 px) deckten acht Schilder das halbe
       Auto zu -- am 23.09.2026 auf 390 px nachgesehen. Dort nur die vier
       Baugruppen, die man ohne Schild am wenigsten errät. */
    const schmal = mitte && mitte.w < 520;
    const sichtbar = liste.filter((a) => a.sichtbar && a.anteil > 0.05 && TEXT.teile[a.schluessel]
      && (!schmal || !KNAPP.has(a.schluessel) || KNAPP.get(a.schluessel)));
    if (!mitte || !sichtbar.length) { for (const e of etiketten.values()) { e.el.hidden = true; e.linie.style.display = 'none'; e.punkt.style.display = 'none'; } return; }
    const W = mitte.w, H = mitte.h;
    linien.setAttribute('viewBox', `0 0 ${W} ${H}`);
    const kaesten = sichtbar.map((a) => {
      let e = etiketten.get(a.schluessel);
      if (!e) {
        const el = document.createElement('span'); el.className = 'bd-etikett'; el.textContent = TEXT.teile[a.schluessel];
        schicht.appendChild(el);
        const linie = document.createElementNS(SVG, 'line'); const punkt = document.createElementNS(SVG, 'circle');
        punkt.setAttribute('r', '4'); linien.append(linie, punkt);
        e = { el, linie, punkt, b: 0, h: 0 }; etiketten.set(a.schluessel, e);
      }
      if (!e.b) { e.el.hidden = false; e.b = e.el.offsetWidth || 110; e.h = e.el.offsetHeight || 26; }
      let dx = a.x - mitte.x, dy = a.y - mitte.y; const l = Math.hypot(dx, dy) || 1; dx /= l; dy /= l;
      const weit = 46 + 0.12 * Math.min(W, H);
      return { a, e, dx, x: a.x + dx * weit - (dx < 0 ? e.b : 0), y: a.y + dy * weit * 0.8 - e.h / 2 };
    });
    for (let runde = 0; runde < 10; runde++) {
      for (let i = 0; i < kaesten.length; i++) for (let j = i + 1; j < kaesten.length; j++) {
        const p = kaesten[i], q = kaesten[j];
        const ueberX = Math.min(p.x + p.e.b, q.x + q.e.b) - Math.max(p.x, q.x) + 8;
        const ueberY = Math.min(p.y + p.e.h, q.y + q.e.h) - Math.max(p.y, q.y) + 6;
        if (ueberX > 0 && ueberY > 0) { const s = (p.y < q.y ? -1 : 1) * ueberY / 2; p.y += s; q.y -= s; }
      }
      // Oben links steht die Kennung (auf dem Telefon oben), unten der Knopf:
      // Etiketten bleiben aus diesen Streifen heraus.
      const oben = W < 620 ? 50 : 8, unten = H - 62;
      for (const k of kaesten) { k.x = Math.min(W - k.e.b - 8, Math.max(8, k.x)); k.y = Math.min(unten - k.e.h, Math.max(oben, k.y)); }
    }
    const aktiv = new Set();
    for (const k of kaesten) {
      aktiv.add(k.a.schluessel);
      const deck = String(Math.min(1, (k.a.anteil - 0.05) * 1.6));
      k.e.el.hidden = false; k.e.el.style.opacity = deck;
      k.e.el.style.transform = `translate3d(${k.x.toFixed(1)}px, ${k.y.toFixed(1)}px, 0)`;
      const zx = k.dx < 0 ? k.x + k.e.b : k.x, zy = k.y + k.e.h / 2;
      Object.entries({ x1: k.a.x, y1: k.a.y, x2: zx, y2: zy }).forEach(([n, v]) => k.e.linie.setAttribute(n, v.toFixed(1)));
      k.e.punkt.setAttribute('cx', k.a.x.toFixed(1)); k.e.punkt.setAttribute('cy', k.a.y.toFixed(1));
      k.e.linie.style.display = k.e.punkt.style.display = '';
      k.e.linie.style.opacity = k.e.punkt.style.opacity = deck;
    }
    for (const [key, e] of etiketten) if (!aktiv.has(key)) { e.el.hidden = true; e.linie.style.display = 'none'; e.punkt.style.display = 'none'; }
  }

  const z = { variante: fig.dataset.variante, gezeigt: fig.dataset.variante, echtzeit: false, p: null, laedt: null, zerlegt: false, licht: false, details: false,
    innen: false, ausstattung: null, ausstattungNr: 0 };

  function adresse(v) {
    const klein = fig.clientWidth * (window.devicePixelRatio || 1) <= 900;
    return `${PFAD}${modell}-${v}${klein ? '-800' : ''}.${bildEndung}?v=${BILD_STAND}`;
  }
  function kennungFoto() { kennung.textContent = z.innen ? TEXT.innenFoto : (fig.clientWidth < 560 ? TEXT.fotoKurz : TEXT.foto); }
  let auftrag = 0;
  async function bildZeigen(v) {
    const nr = ++auftrag;
    const oben = ruheA.classList.contains('ist-oben') ? ruheA : ruheB.classList.contains('ist-oben') ? ruheB : ruheA;
    const unten = oben === ruheA ? ruheB : ruheA;
    const bild = unten.parentElement && unten.parentElement.tagName === 'PICTURE' ? unten.parentElement : null;
    if (bild) for (const q of [...bild.querySelectorAll('source')]) q.remove();
    await avifPruefung;
    if (nr !== auftrag) return;
    unten.src = adresse(v); z.gezeigt = v;
    try { await unten.decode(); } catch { /* ohne Vorab-Dekodieren */ }
    if (nr !== auftrag) return;
    unten.classList.add('ist-oben', 'ist-an'); oben.classList.remove('ist-oben');
    setTimeout(() => { if (nr === auftrag) oben.classList.remove('ist-an'); }, 650);
    unten.alt = oben.alt || unten.alt; unten.removeAttribute('aria-hidden');
    oben.alt = ''; oben.setAttribute('aria-hidden', 'true');
  }

  function ladeAnzeige(an) {
    if (an) { knopf.setAttribute('aria-busy', 'true'); knopfText.textContent = TEXT.laedt; }
    else { knopf.removeAttribute('aria-busy'); knopfText.textContent = z.echtzeit ? TEXT.zumFoto : TEXT.drehen; }
  }
  async function laden(stumm = false) {
    if (z.p) return z.p;
    if (!webglDa()) return null;
    if (!stumm) ladeAnzeige(true);
    if (!z.laedt) {
      z.laedt = (async () => {
        const modul = await import(new URL(fig.dataset.src, document.baseURI).href);
        const basis = new URL(`assets/3d/branchen/${modell}/`, new URL('/', location.href)).href;
        const p = await modul.erstellen({
          behaelter: halter,
          glb: basis + `${modell}.glb?v=${MODELL_STAND}`,
          kameraUrl: basis + `kamera.json?v=${MODELL_STAND}`,
          bodenUrl: basis + `boden-licht.webp?v=${MODELL_STAND}`,
          einstellungen: stufe(),
          variante: Number(fig.dataset.varianteNr || 0),
          bezeichnung: TEXT.leinwand[modell === 'schuh' ? 'schuh' : 'auto'],
          beiBewegung: () => an(),
          beiRuhe: (ansicht) => aus(ansicht),
          beiBild: (dt, fps, schlaeft) => { if (z.echtzeit && !z.zerlegt) kennung.textContent = z.innen ? TEXT.innen : TEXT.echtzeit(schlaeft ? 0 : fps); },
          beiAnker: ankerZeigen,
        });
        z.p = p; fig.classList.add('hat-echtzeit'); halter.removeAttribute('aria-hidden');
        if (z.ausstattung && p.ausstattung) await p.ausstattung(z.ausstattungNr);
        return p;
      })();
    }
    try { return await z.laedt; }
    catch (f) { console.warn('[branchen] Echtzeit nicht verfügbar:', f); knopf.hidden = true; z.laedt = null; return null; }
    finally { if (!stumm) ladeAnzeige(false); }
  }
  function an() {
    if (!z.p) return;
    z.echtzeit = true; fig.classList.add('ist-echtzeit'); zaehlen(`${modell === 'schuh' ? 'schuh' : 'auto'}-drehen`);
    knopfText.textContent = TEXT.zumFoto;
    kennung.textContent = z.zerlegt ? (fig.id === 'bd-buehne-produkt' ? TEXT.geoeffnet : TEXT.zerlegt) : TEXT.echtzeit(0);
    z.p.starten();
  }
  /* Foto zur aktuellen Ansicht: aussen der Lack, innen die Ausstattung
     (Innenraumfoto vom Fahrerplatz, dieselbe Kamera wie das Ziel der
     Kamerafahrt). */
  function fotoSchluessel() { return z.innen && z.ausstattung ? `innen-${z.ausstattung}` : z.variante; }
  function aus(ansicht) {
    // Zerlegt und mit Licht gibt es kein Foto -- dann bleibt die Echtzeit.
    // Eine geänderte Ladung (Ladeplaner) gibt es auf keinem Foto -- dann auch.
    if (!z.echtzeit || z.zerlegt || z.licht || z.details || z.ladung) return;
    if ((ansicht === 'innen') !== z.innen) return;
    // In der Echtzeit gewählte Variante: erst ihr Foto unterlegen, dann blenden.
    if (z.gezeigt !== fotoSchluessel()) bildZeigen(fotoSchluessel());
    z.echtzeit = false; fig.classList.remove('ist-echtzeit');
    knopfText.textContent = TEXT.drehen; kennungFoto();
    setTimeout(() => { if (!z.echtzeit && z.p) z.p.anhalten(); }, 700);
  }

  knopf.addEventListener('click', async () => {
    if (z.echtzeit) {
      // Zurück zum Foto: zusammensetzen, Licht aus, auf den Standpunkt
      // fahren. Das Foto blendet ein, sobald die Kamera dort steht (beiRuhe).
      z.zerlegt = false; z.licht = false; z.details = false; z.ladung = false;
      const warInnen = z.innen; z.innen = false;
      if (z.p) { z.p.zerlegen(false); z.p.licht(false); z.p.punkte(false); if (warInnen) z.p.innenraum(false); else z.p.heim(); }
      fig.dispatchEvent(new CustomEvent('bd:zurueck'));
      return;
    }
    const p = await laden(); if (p) an();
  });
  // Wer mit der Maus darüberfährt, will wahrscheinlich gleich greifen:
  // vorwärmen, ohne den Knopf zu verändern.
  fig.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse') laden(true); }, { once: true });
  fig.addEventListener('pointerdown', async (e) => {
    if (e.pointerType === 'touch' || z.p || e.target.closest('.bewegen')) return;
    const p = await laden(); if (p) an();
  });

  async function variante(v, nr) {
    z.variante = v; fig.dataset.variante = v; fig.dataset.varianteNr = String(nr);
    if (!z.echtzeit && !z.innen) bildZeigen(v);
    if (z.p) await z.p.variante(nr);
  }
  /* Ausstattung: Material im Modell (Echtzeit) bzw. Innenraumfoto. */
  async function ausstattung(key, nr) {
    z.ausstattung = key; z.ausstattungNr = nr;
    if (z.innen && !z.echtzeit) bildZeigen(fotoSchluessel());
    if (z.p) await z.p.ausstattung(nr);
  }
  /* Fahrerplatz: Kamerafahrt durch die Fahrertuer (Echtzeit) -- ohne WebGL
     das gerechnete Innenraumfoto. */
  async function innenraum(an_) {
    z.innen = an_;
    if (an_) zaehlen(`${modell}-innen`);
    const p = webglDa() ? await laden() : null;
    if (!p || !p.hatInnen) { bildZeigen(fotoSchluessel()); kennungFoto(); return; }
    if (z.zerlegt) { z.zerlegt = false; }
    an();
    p.innenraum(an_);
  }
  /* Zerlegt und mit Licht gibt es kein Foto -- solange bleibt die Echtzeit
     stehen. Wird beides wieder zurückgenommen, fährt die Kamera heim, und
     das Foto kommt zurück (beiRuhe -> aus). */
  async function zerlegen(an_) {
    const p = await laden(); if (!p) return;
    z.zerlegt = !!an_; if (an_) zaehlen('auto-zerlegen');
    if (an_ && z.innen) { z.innen = false; p.innenraum(false); }
    an();
    p.zerlegen(an_);
    if (!an_ && !z.licht) p.heim();
  }
  async function licht(an_) {
    const p = await laden(); if (!p) return;
    z.licht = an_; p.licht(an_);
    an();
    if (!an_ && !z.zerlegt) p.heim();
  }
  /* Details: feste Namen am Modell (Schuh). Wie zerlegt bleibt dabei die
     Echtzeit stehen -- auf dem Foto gibt es keine Etiketten. */
  async function details(an_) {
    const p = await laden(); if (!p) return;
    z.details = an_; p.punkte(an_); if (an_) zaehlen('schuh-details');
    an();
    if (!an_ && !z.zerlegt && !z.licht) p.heim();
  }
  function stufeSetzen() { if (z.p) z.p.stufe(stufe()); }
  document.addEventListener('vecom:stufe', stufeSetzen);
  if (!webglDa()) { knopf.hidden = true; }
  kennungFoto();
  /* Modell wechseln (Automotive): Echtzeit des alten Modells freigeben --
     WebGL-Kontexte sind knapp, und zwei Autos gleichzeitig im Speicher
     braucht niemand. Dann das Foto des neuen Modells; war die Echtzeit an,
     laedt sie fuer das neue gleich nach. */
  async function modellWechseln(neu, v, nr, alt) {
    if (neu === modell) return;
    const warEchtzeit = z.echtzeit;
    if (z.p) { try { z.p.entsorgen(); } catch (f) { console.warn('[branchen] entsorgen:', f); } }
    z.p = null; z.laedt = null; z.zerlegt = false; z.licht = false; z.details = false; z.echtzeit = false; z.innen = false; z.ladung = false;
    z.ausstattung = MODELLE[neu] && MODELLE[neu].innen ? MODELLE[neu].innen[0][0] : null; z.ausstattungNr = 0;
    fig.classList.remove('hat-echtzeit', 'ist-echtzeit'); halter.setAttribute('aria-hidden', 'true');
    ankerZeigen([], null);
    modell = neu; fig.dataset.modell = neu;
    z.variante = v; fig.dataset.variante = v; fig.dataset.varianteNr = String(nr);
    knopfText.textContent = TEXT.drehen; kennungFoto();
    const oben = ruheA.classList.contains('ist-oben') ? ruheA : ruheB;
    if (alt) oben.alt = alt;
    await bildZeigen(v);
    if (warEchtzeit) { const p = await laden(); if (p) an(); }
  }
  /* Die Demo-Galerie (erlebnis.js) klappt die Bühne zu: sofort zurück aufs
     Foto und die Schleife anhalten -- eine unsichtbare Bühne soll keine
     Grafikkarte beschäftigen. Ohne Fahrt, man sieht es ja nicht. */
  function ruhen() {
    if (!z.p || !z.echtzeit) return;
    z.zerlegt = false; z.licht = false; z.details = false;
    if (z.innen) { z.innen = false; z.p.innenraum(false); }
    z.p.zerlegen(false); z.p.licht(false); z.p.punkte(false); z.p.heim();
    z.echtzeit = false; fig.classList.remove('ist-echtzeit');
    bildZeigen(fotoSchluessel()); knopfText.textContent = TEXT.drehen; kennungFoto();
    fig.dispatchEvent(new CustomEvent('bd:zurueck'));
    z.p.anhalten();
  }
  async function gravur(t) { const p = await laden(); if (p) p.gravur(t); }
  async function stein(k) { const p = await laden(); if (p) p.stein(k); }
  async function logo(datei) { const p = await laden(); if (!p || !p.logo) return false; z.ladung = !!datei || z.ladung; an(); return p.logo(datei); }
  async function karte(name) { const p = await laden(); if (!p || !p.karte) return; z.ladung = true; an(); p.karte(name); }
  async function gruppe(name, wert) { const p = await laden(); if (!p) return; z.ladung = wert !== '1'; an(); p.gruppe(name, wert); }
  async function ladung(n) { const p = await laden(); if (!p) return; z.ladung = true; an(); p.ladung(n); }
  return { variante, ausstattung, innenraum, zerlegen, licht, details, gravur, stein, ladung, gruppe, logo, karte, modellWechseln, ruhen, get z() { return z; }, get modell() { return modell; } };
}

/* Galerie: Bühne zugeklappt -> beide Demos anhalten (siehe ruhen()). */
const BUEHNEN = [];
document.getElementById('branchen-demo')?.addEventListener('demo:zu', () => { for (const b of BUEHNEN) b.ruhen(); });

/* ------------------------------------------------------------- Reiter */
const reiter = $('#bd-reiter');
if (reiter) {
  const tabs = $$('[role="tab"]', reiter);
  function zeigen(tab, fokus) {
    for (const t of tabs) {
      const an = t === tab;
      t.setAttribute('aria-selected', String(an)); t.tabIndex = an ? 0 : -1;
      const panel = document.getElementById(t.getAttribute('aria-controls'));
      if (panel) panel.hidden = !an;
    }
    if (fokus) tab.focus();
  }
  reiter.addEventListener('click', (e) => { const t = e.target.closest('[role="tab"]'); if (t) zeigen(t); });
  reiter.addEventListener('keydown', (e) => {
    const i = tabs.indexOf(document.activeElement); if (i < 0) return;
    const n = { ArrowRight: 1, ArrowLeft: -1 }[e.key]; if (!n) return;
    e.preventDefault(); zeigen(tabs[(i + n + tabs.length) % tabs.length], true);
  });
  // Der Wegweiser verlinkt mit #bd-auto / #bd-shop direkt auf einen Reiter.
  function ausAdresse() {
    const h = location.hash.slice(1);
    const t = tabs.find((x) => x.getAttribute('aria-controls') === h);
    if (t) zeigen(t);
  }
  window.addEventListener('hashchange', ausAdresse); ausAdresse();
}

/* Der Knopf unter jeder Demo nimmt die Auswahl mit in den Konfigurator
   (bedarf.php?demo=auto-karmin, demo=schuh-rose-42). Dort steht sie dann
   als erste Zeile der Anfrage -- wer einen Lack gewaehlt hat, soll ihn
   nicht noch einmal beschreiben muessen. bedarf.php laesst nur bekannte
   Schluessel durch; hier wird nur zusammengesetzt. */
function auswahlMitgeben(a, demo) {
  if (!a) return;
  if (!a.dataset.gezaehlt) { a.dataset.gezaehlt = '1'; a.addEventListener('click', () => zaehlen(a.id === 'bd-auto-cta' ? 'cta-auto' : 'cta-shop')); }
  const u = new URL(a.getAttribute('href'), location.href);
  u.searchParams.set('demo', demo);
  a.setAttribute('href', u.pathname + u.search);
}

/* ------------------------------------------------------------ Auto */
const autoFig = $('#bd-buehne-auto');
if (autoFig) {
  const b = buehneAnlegen(autoFig);
  BUEHNEN.push(b);
  const lack = $('#bd-lack');
  const innenWahl = $('#bd-innen'); const innenGruppe = $('#bd-g-innen');
  const stufen = $('#bd-stufen');
  function ansichtZeigen(a, stufe = 0) {
    if (ansicht) for (const x of $$('button', ansicht)) x.setAttribute('aria-pressed', String(x.dataset.ansicht === a));
    if (stufen) {
      stufen.hidden = a !== 'zerlegt' || !MODELLE[b.modell].innen;
      for (const x of $$('button', stufen)) x.setAttribute('aria-pressed', String(Number(x.dataset.stufe) === stufe));
    }
  }
  lack && lack.addEventListener('click', (e) => {
    const k = e.target.closest('button[data-variante]'); if (!k) return;
    for (const x of $$('button', lack)) x.setAttribute('aria-pressed', String(x === k));
    // Einen Lack sieht man von aussen: vom Fahrerplatz geht es dafuer hinaus.
    if (b.z.innen) { b.innenraum(false); ansichtZeigen('aussen'); }
    b.variante(k.dataset.variante, Number(k.dataset.nr));
    auswahlMitgeben($('#bd-auto-cta'), `${b.modell}-${k.dataset.variante}`);
  });
  innenWahl && innenWahl.addEventListener('click', (e) => {
    const k = e.target.closest('button[data-ausstattung]'); if (!k) return;
    for (const x of $$('button', innenWahl)) x.setAttribute('aria-pressed', String(x === k));
    b.ausstattung(k.dataset.ausstattung, Number(k.dataset.nr));
    // Eine Ausstattung sieht man innen: die Kamera faehrt auf den Fahrerplatz.
    if (!b.z.innen) { b.innenraum(true); ansichtZeigen('innen'); }
    zaehlen(`${b.modell}-ausstattung-${k.dataset.ausstattung}`);
  });
  auswahlMitgeben($('#bd-auto-cta'), `${b.modell}-${autoFig.dataset.variante}`);
  /* Wunsch B6: Probefahrt mit genau diesem Lack und dieser Ausstattung --
     so, wie es der Kunde des Autohauses erlebt (kundenablauf.js). */
  $('#bd-auto-kunde')?.addEventListener('click', () => {
    const m = b.modell, TM = TEXT.modelle[m];
    const l = lack && lack.querySelector('button[aria-pressed="true"]');
    const i = innenWahl && innenWahl.querySelector('button[aria-pressed="true"]');
    const zeilen = [TEXT.probeModell[m], TM.lacke[l ? l.dataset.variante : MODELLE[m].lacke[0][0]]];
    if (i && TM.innen) zeilen.push(TM.innen[i.dataset.ausstattung]);
    zaehlen('auto-kunde');
    document.dispatchEvent(new CustomEvent('vecom:kunde', { detail: { titel: TEXT.probeTitel, zeilen, termin: { zeiten: ['09:00', '11:00', '14:00', '16:30'] }, ziel: $('#bd-auto-cta').getAttribute('href') } }));
  });
  /* Drei Autos (23.09.2026): Kleinwagen und Mittelklasse sind eigene
     Entwuerfe mit den Massen ihrer Klasse (3d-produktion/scripts/
     fahrzeug_bau.py), der Sportwagen ist das Konzeptauto. Je Modell eigene
     Lacke, Fakten und eine Datenzeile; Fotos und Modelle liegen je Modell
     unter demselben Namensschema. */
  const modellWahl = $('#bd-modell');
  const daten = $('#bd-daten');
  const LOKAL = { it: 'it-IT', de: 'de-DE', en: 'en-GB' }[SPRACHE];
  function lackChips(m, aktiv) {
    if (!lack) return;
    lack.replaceChildren(...MODELLE[m].lacke.map(([key, farbe], nr) => {
      const k = document.createElement('button');
      k.type = 'button'; k.dataset.variante = key; k.dataset.nr = String(nr);
      k.setAttribute('aria-pressed', String(key === aktiv));
      const punkt = document.createElement('i'); punkt.className = 'farbpunkt'; punkt.setAttribute('aria-hidden', 'true');
      punkt.style.setProperty('--f', farbe);
      const name = document.createElement('span'); name.textContent = TEXT.modelle[m].lacke[key];
      k.append(punkt, name);
      return k;
    }));
  }
  function innenChips(m, aktiv) {
    const I = MODELLE[m].innen;
    if (innenGruppe) innenGruppe.hidden = !I;
    const kn = ansicht && $('button[data-ansicht="innen"]', ansicht); if (kn) kn.hidden = !I;
    if (!innenWahl || !I) { if (innenWahl) innenWahl.replaceChildren(); return; }
    innenWahl.replaceChildren(...I.map(([key, farbe], nr) => {
      const k = document.createElement('button');
      k.type = 'button'; k.dataset.ausstattung = key; k.dataset.nr = String(nr);
      k.setAttribute('aria-pressed', String(key === aktiv));
      const punkt = document.createElement('i'); punkt.className = 'farbpunkt'; punkt.setAttribute('aria-hidden', 'true');
      punkt.style.setProperty('--f', farbe);
      const name = document.createElement('span'); name.textContent = TEXT.modelle[m].innen[key];
      k.append(punkt, name);
      return k;
    }));
  }
  function faktenSetzen(m) {
    const M = MODELLE[m]; const T = TEXT.modelle[m];
    if (daten) { $('.bd-daten__masse', daten).textContent = T.daten; $('.bd-daten__bau', daten).textContent = T.bau; }
    const mb = M.mb.toLocaleString(LOKAL, { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' MB';
    const z2 = $('#bd-auto-z2'); if (z2) z2.textContent = mb;
    const z3 = $('#bd-auto-z3'); if (z3) z3.textContent = M.dreiecke.toLocaleString(LOKAL);
    const gr = $('.bewegen__groesse', autoFig); if (gr) gr.textContent = mb;
  }
  modellWahl && modellWahl.addEventListener('click', (e) => {
    const k = e.target.closest('button[data-modell]'); if (!k) return;
    const m = k.dataset.modell; if (!MODELLE[m] || m === b.modell) return;
    for (const x of $$('button', modellWahl)) x.setAttribute('aria-pressed', String(x === k));
    const [erster] = MODELLE[m].lacke[0];
    lackChips(m, erster); innenChips(m, MODELLE[m].innen ? MODELLE[m].innen[0][0] : null); faktenSetzen(m);
    ansichtZeigen('aussen');
    b.modellWechseln(m, erster, 0, TEXT.modelle[m].alt);
    auswahlMitgeben($('#bd-auto-cta'), `${m}-${erster}`);
    zaehlen(`modell-${m}`);
  });
  const ansicht = $('#bd-ansicht');
  ansicht && ansicht.addEventListener('click', (e) => {
    const k = e.target.closest('button[data-ansicht]'); if (!k) return;
    const a = k.dataset.ansicht;
    if (a === 'aussen') { if (b.z.innen) b.innenraum(false); if (b.z.zerlegt) b.zerlegen(false); ansichtZeigen('aussen'); }
    else if (a === 'innen') { if (b.z.zerlegt) b.zerlegen(false); b.innenraum(true); ansichtZeigen('innen'); }
    else {
      // Zerlegen in Stufen (Serienautos): erst die Anbauteile, weiter ueber
      // die Schritte. Das Konzeptauto zerlegt sich in einem Zug.
      const st = MODELLE[b.modell].innen ? 1 : true;
      b.zerlegen(st); ansichtZeigen('zerlegt', st === true ? 0 : 1);
    }
  });
  stufen && stufen.addEventListener('click', (e) => {
    const k = e.target.closest('button[data-stufe]'); if (!k) return;
    const n = Number(k.dataset.stufe);
    b.zerlegen(n); ansichtZeigen('zerlegt', n);
    zaehlen(`${b.modell}-stufe-${n}`);
  });
  /* Eine Nachtansicht (Studio aus, nur die Leuchten des Autos) stand hier
     bis zum 23.09.2026 als dritter Schalter. Ohne Bloom glühten die
     Tagfahrlichter kaum, das Auto wurde nur braun-dunkel -- ein Schalter,
     der nichts zeigt, kostet mehr Vertrauen, als er bringt. Die Funktion
     bleibt im Modul (licht()), der Schalter ist weg. */
  const licht = null;
  innenChips(b.modell, MODELLE[b.modell].innen ? MODELLE[b.modell].innen[0][0] : null);
  autoFig.addEventListener('bd:zurueck', () => {
    ansichtZeigen('aussen');
    if (licht) licht.setAttribute('aria-pressed', 'false');
  });
  if (!webglDa()) {
    for (const el of [ansicht, licht]) if (el) el.closest('.gruppe').hidden = true;
    const h = $('#bd-auto-hinweis'); if (h) { h.textContent = TEXT.keinWebgl; h.hidden = false; }
  }
}

/* ------------------------------------------------------------ Shop */
const schuhFig = $('#bd-buehne-schuh');
if (schuhFig) {
  const b = buehneAnlegen(schuhFig);
  BUEHNEN.push(b);
  const farben = $('#bd-farbe'); const groessen = $('#bd-groesse');
  const korbKnopf = $('#bd-in-korb'); const korbListe = $('#bd-korb-liste'); const korbZahl = $('#bd-korb-zahl');
  const meldung = $('#bd-korb-meldung');
  const korb = [];
  function shopAuswahl() {
    const f = farben && $('button[aria-pressed="true"]', farben);
    const g = groessen && $('button[aria-pressed="true"]', groessen);
    auswahlMitgeben($('#bd-shop-cta'), `schuh-${f ? f.dataset.variante : schuhFig.dataset.variante}${g ? '-' + g.dataset.groesse : ''}`);
  }
  farben && farben.addEventListener('click', (e) => {
    const k = e.target.closest('button[data-variante]'); if (!k) return;
    for (const x of $$('button', farben)) x.setAttribute('aria-pressed', String(x === k));
    b.variante(k.dataset.variante, Number(k.dataset.nr));
    shopAuswahl();
  });
  const detailWahl = $('#bd-details');
  detailWahl && detailWahl.addEventListener('click', (e) => {
    const k = e.target.closest('button[data-details]'); if (!k) return;
    for (const x of $$('button', detailWahl)) x.setAttribute('aria-pressed', String(x === k));
    b.details(k.dataset.details === '1');
  });
  schuhFig.addEventListener('bd:zurueck', () => {
    if (detailWahl) for (const x of $$('button', detailWahl)) x.setAttribute('aria-pressed', String(x.dataset.details === '0'));
  });
  if (!webglDa() && detailWahl) detailWahl.closest('.gruppe').hidden = true;
  groessen && groessen.addEventListener('click', (e) => {
    const k = e.target.closest('button[data-groesse]'); if (!k) return;
    for (const x of $$('button', groessen)) x.setAttribute('aria-pressed', String(x === k));
    if (meldung) meldung.textContent = '';
    shopAuswahl();
  });
  shopAuswahl();
  function korbZeigen() {
    if (!korbListe) return;
    korbListe.innerHTML = korb.length
      ? korb.map((k) => `<li>${TEXT.korb(k.name, k.farbe, k.groesse, k.preis)}</li>`).join('')
      : `<li class="leer">${TEXT.korbLeer}</li>`;
    if (korbZahl) korbZahl.textContent = TEXT.korbZahl(korb.length);
  }
  korbKnopf && korbKnopf.addEventListener('click', () => {
    const g = groessen && $('button[aria-pressed="true"]', groessen);
    if (!g) { if (meldung) meldung.textContent = TEXT.groesseFehlt; groessen && $('button', groessen).focus(); return; }
    const f = farben && $('button[aria-pressed="true"]', farben);
    zaehlen('schuh-korb');
    korb.push({ name: korbKnopf.dataset.name, farbe: f ? f.textContent.trim() : '', groesse: g.dataset.groesse, preis: korbKnopf.dataset.preis });
    korbZeigen();
    const kasten = $('#bd-korb');
    if (kasten) { kasten.classList.remove('ist-neu'); void kasten.offsetWidth; kasten.classList.add('ist-neu'); }
    if (meldung) meldung.textContent = '';
  });
  korbZeigen();
}

/* ------------------------------------------------------------ Produkte
   Eine Bühne für alle weiteren Branchen der Galerie (Wein, ...). Die Kachel
   meldet per vecom:produkt, welches Produkt; hier wechselt das Modell, und
   die rechte Spalte füllt sich aus PRODUKT_TEXTE / PRODUKTE. */
const produktFig = $('#bd-buehne-produkt');
if (produktFig) {
  const b = buehneAnlegen(produktFig);
  BUEHNEN.push(b);
  const kicker = $('#pd-kicker'), titel = $('#pd-h'), text = $('#pd-text'), wahl = $('#pd-var'), schritte = $('#pd-schritte');
  const groesse = $('.bewegen__groesse', produktFig), cta = $('#pd-cta');
  const LOKAL = { it: 'it-IT', de: 'de-DE', en: 'en-GB' }[SPRACHE];
  $('#produkt-demo')?.addEventListener('demo:zu', () => b.ruhen());
  function schrittZeigen(n) { for (const x of $$('button', schritte)) x.setAttribute('aria-pressed', String(Number(x.dataset.stufe) === n)); }
  function fuellen(was) {
    const T = PRODUKT_TEXTE[was], M = PRODUKTE[was]; if (!T || !M) return;
    if (typeof extraZeigen === 'function') extraZeigen(0, was);
    kicker.textContent = T.kicker; titel.textContent = T.titel; text.textContent = T.text;
    if (groesse) groesse.textContent = M.mb.toLocaleString(LOKAL, { minimumFractionDigits: 1 }) + ' MB';
    if (cta) { cta.textContent = T.cta; auswahlMitgeben(cta, `${was}-${M.varianten[0][0]}`); }
    const arK = $('#pd-ar'); if (arK) { arK.hidden = !AR_MODELLE.has(was); arVeraltet(); $('#pd-ar-hinweis').textContent = ''; }
    const kk = $('#pd-kunde');
    if (kk) { kk.hidden = !(T.kunde && M.kunde); if (T.kunde) kk.textContent = T.kunde.knopf; }
    wahl.replaceChildren(...M.varianten.map(([key, farbe], nr) => {
      const k = document.createElement('button'); k.type = 'button'; k.dataset.variante = key; k.dataset.nr = String(nr);
      k.setAttribute('aria-pressed', String(nr === 0));
      const punkt = document.createElement('i'); punkt.className = 'farbpunkt'; punkt.setAttribute('aria-hidden', 'true'); punkt.style.setProperty('--f', farbe);
      const name = document.createElement('span'); name.textContent = T.varianten[key];
      k.append(punkt, name); return k;
    }));
    const knoepfe = [[0, T.ganz || PRODUKT_TEXTE.ganz], ...T.schritte.map((t, i) => [i + 1, t])];
    schritte.replaceChildren(...knoepfe.map(([n, t]) => {
      const k = document.createElement('button'); k.type = 'button'; k.dataset.stufe = String(n);
      k.setAttribute('aria-pressed', String(n === 0));
      if (n) { const nr = document.createElement('b'); nr.textContent = String(n); k.append(nr, ' '); }
      k.append(t); return k;
    }));
  }
  document.addEventListener('vecom:produkt', async (e) => {
    const was = e.detail.demo; if (!PRODUKTE[was]) return;
    fuellen(was);
    await b.modellWechseln(was, PRODUKTE[was].varianten[0][0], 0, PRODUKT_TEXTE[was].alt);
  });
  wahl.addEventListener('click', (e) => {
    const k = e.target.closest('button[data-variante]'); if (!k) return;
    for (const x of $$('button', wahl)) x.setAttribute('aria-pressed', String(x === k));
    b.variante(k.dataset.variante, Number(k.dataset.nr));
    if (cta) auswahlMitgeben(cta, `${b.modell}-${k.dataset.variante}`);
  });
  /* Zusätze je Stufe (Uhr, 24.09.2026): Bei „Gravur" ein Textfeld, dessen
     Inhalt sofort auf dem Gehäuseboden steht (U3); bei „Ring" das Karat (U4).
     Der Durchmesser folgt der dritten Wurzel des Gewichts, 1 ct = 6,5 mm. */
  const extra = $('#pd-extra');
  let gravurText = '', karatWahl = 1, ladungN = 24, kisteWahl = '1';
  const AR_MODELLE = new Set(['wein', 'schmuck', 'gastro']);
  /* Ihr Logo auf dem Produkt (A1) und, beim Restaurant, der eigene Name auf
     der Tischkarte. Die Datei wird nur im Browser gelesen. */
  const LOGO_MODELLE = new Set(['wein', 'lkw', 'schmuck', 'gastro']);
  let karteName = '';
  function logoZeile(was) {
    const box = document.createElement('div'); box.className = 'pd-logo';
    const l = document.createElement('span'); l.className = 'gruppe__name'; l.textContent = TEXT.logo;
    const zeile = document.createElement('div'); zeile.className = 'pd-logo__zeile';
    const f = document.createElement('input'); f.type = 'file'; f.accept = 'image/png,image/jpeg,image/webp,image/svg+xml'; f.id = 'pd-logo-datei'; f.className = 'pd-logo__datei';
    const knopf = document.createElement('label'); knopf.htmlFor = f.id; knopf.className = 'knopf knopf--leer'; knopf.textContent = TEXT.logoWaehlen;
    const weg = document.createElement('button'); weg.type = 'button'; weg.className = 'knopf knopf--leer'; weg.textContent = TEXT.logoWeg; weg.hidden = true;
    f.addEventListener('change', async () => {
      const d = f.files && f.files[0]; if (!d) return;
      if (d.size > 5 * 1024 * 1024) { hinweis.textContent = TEXT.logoGross; return; }
      const ok = await b.logo(d); weg.hidden = !ok; zaehlen('logo');
      hinweis.textContent = ok ? TEXT.logoDa : TEXT.logoFehler;
    });
    weg.addEventListener('click', async () => { await b.logo(null); weg.hidden = true; f.value = ''; hinweis.textContent = TEXT.logoHinweis; });
    const hinweis = document.createElement('p'); hinweis.className = 'pd-klein'; hinweis.textContent = TEXT.logoHinweis;
    zeile.append(f, knopf, weg);
    box.append(l, zeile);
    if (was === 'gastro') {
      const n = document.createElement('input'); n.type = 'text'; n.maxLength = 40; n.className = 'pd-name'; n.placeholder = TEXT.karteName; n.value = karteName;
      n.setAttribute('aria-label', TEXT.karteName);
      n.addEventListener('input', () => { karteName = n.value.trim(); b.karte(karteName); });
      box.append(n);
    }
    box.append(hinweis);
    return box;
  }
  function extraZeigen(n, was = b.modell) {
    extraInhalt(n, was);
    if (extra && LOGO_MODELLE.has(was) && !extra.querySelector('.pd-logo')) { extra.append(logoZeile(was)); extra.hidden = false; }
  }
  function extraInhalt(n, was) {
    if (!extra) return;
    if (was === 'lkw' && extra.querySelector('#pd-ladung')) return;   // Schieber nicht unter dem Finger ersetzen
    if (was === 'wein' && extra.querySelector('#pd-l-kiste')) return;
    extra.replaceChildren(); extra.hidden = true;
    if (was === 'lkw') {
      /* Ladeplaner (B5): Speditionen denken in Paletten und Lademetern. Drei
         Europaletten stehen quer nebeneinander, eine Reihe braucht 1,2 m. */
      const id = 'pd-ladung';
      const l = document.createElement('label'); l.className = 'gruppe__name'; l.htmlFor = id; l.textContent = TEXT.ladung;
      const r = document.createElement('input'); r.type = 'range'; r.id = id; r.min = '1'; r.max = '33'; r.step = '1'; r.value = String(ladungN); r.className = 'pd-ladung';
      const w = document.createElement('p'); w.className = 'pd-ladung-wert'; w.setAttribute('aria-live', 'polite');
      const zeigen = () => {
        const lm = (Math.ceil(ladungN / 3) * 1.2).toLocaleString(LOKAL, { minimumFractionDigits: 1, maximumFractionDigits: 1 });
        w.textContent = TEXT.ladungFmt(ladungN, Math.round((ladungN / 33) * 100), lm);
        r.setAttribute('aria-valuetext', w.textContent);
      };
      r.addEventListener('input', () => {
        ladungN = Number(r.value); zeigen(); b.ladung(ladungN);
        // Ladung sieht man nur bei offener Plane: sonst den ersten Schritt auslösen
        const offen = schritte.querySelector('button[aria-pressed="true"]');
        if (!offen || offen.dataset.stufe === '0') schritte.querySelector('button[data-stufe="1"]')?.click();
      });
      r.addEventListener('change', () => zaehlen('lkw-ladung'), { once: true });
      zeigen(); extra.append(l, r, w); extra.hidden = false;
      return;
    }
    if (was === 'wein') {
      /* Geschenkkiste für 1, 2 oder 3 Flaschen (B1) -- Direktverkauf und Präsente */
      const l = document.createElement('span'); l.className = 'gruppe__name'; l.id = 'pd-l-kiste'; l.textContent = TEXT.kiste;
      const c = document.createElement('div'); c.className = 'chips chips--umbruch'; c.setAttribute('role', 'group'); c.setAttribute('aria-labelledby', 'pd-l-kiste');
      for (const k of ['1', '2', '3']) {
        const bt = document.createElement('button'); bt.type = 'button'; bt.setAttribute('aria-pressed', String(k === kisteWahl)); bt.textContent = TEXT.kisteFmt(Number(k));
        bt.addEventListener('click', () => { kisteWahl = k; for (const x of c.children) x.setAttribute('aria-pressed', String(x === bt)); b.gruppe('geschenk', k); zaehlen('wein-kiste'); });
        c.append(bt);
      }
      extra.append(l, c); extra.hidden = false;
      return;
    }
    if (was !== 'schmuck') return;
    if (n === 3) {
      const id = 'pd-gravur';
      const l = document.createElement('label'); l.className = 'gruppe__name'; l.htmlFor = id; l.textContent = TEXT.gravur;
      const f = document.createElement('textarea'); f.id = id; f.className = 'pd-gravur'; f.rows = 2; f.maxLength = 60; f.placeholder = TEXT.gravurPlatz; f.value = gravurText;
      f.addEventListener('input', () => { gravurText = f.value.split('\n').slice(0, 3).join('\n'); b.gravur(gravurText); });
      const h = document.createElement('p'); h.className = 'pd-klein'; h.textContent = TEXT.gravurHinweis;
      extra.append(l, f, h); extra.hidden = false;
      b.gravur(gravurText || TEXT.gravurPlatz);
      if (!gravurText) f.addEventListener('focus', () => { if (!gravurText) b.gravur(''); }, { once: true });
    } else if (n === 4) {
      const l = document.createElement('span'); l.className = 'gruppe__name'; l.id = 'pd-l-karat'; l.textContent = TEXT.karat;
      const c = document.createElement('div'); c.className = 'chips chips--umbruch'; c.setAttribute('role', 'group'); c.setAttribute('aria-labelledby', 'pd-l-karat');
      for (const k of [0.5, 0.75, 1, 1.5, 2]) {
        const bt = document.createElement('button'); bt.type = 'button'; bt.setAttribute('aria-pressed', String(k === karatWahl));
        const mm = (6.5 * Math.cbrt(k)).toLocaleString(LOKAL, { maximumFractionDigits: 1, minimumFractionDigits: 1 });
        bt.textContent = TEXT.karatFmt(k.toLocaleString(LOKAL), mm);
        bt.addEventListener('click', () => { karatWahl = k; for (const x of c.children) x.setAttribute('aria-pressed', String(x === bt)); b.stein(k); zaehlen('schmuck-karat'); });
        c.append(bt);
      }
      extra.append(l, c); extra.hidden = false;
    }
  }
  schritte.addEventListener('click', (e) => {
    const k = e.target.closest('button[data-stufe]'); if (!k) return;
    const n = Number(k.dataset.stufe);
    schrittZeigen(n); extraZeigen(n);
    // Ein Schritt kann statt Zerlegen das Licht wechseln (Gastronomie: Abend)
    const lichtStufe = PRODUKTE[b.modell] && PRODUKTE[b.modell].licht;
    if (lichtStufe && n === lichtStufe) { b.zerlegen(false); b.licht(true); }
    else { if (lichtStufe) b.licht(false); b.zerlegen(n || false); }
    if (n) zaehlen(`${b.modell}-stufe-${n}`);
  });
  produktFig.addEventListener('bd:zurueck', () => { schrittZeigen(0); extraZeigen(0); });
  /* Der Weg des Endkunden (kundenablauf.js): mit der gewählten Variante und,
     bei der Uhr, Gravur und Karat -- genau so käme es beim Betrieb an. */
  if (new URLSearchParams(location.search).has('pruefen')) window.__produkt = () => b.z.p;
  /* Im eigenen Raum ansehen (A2): ar.js lädt erst beim Tipp (es braucht
     three.js, das die Echtzeit ohnehin schon geladen hat). */
  const arZustand = {};
  function arVeraltet() { if (arZustand.url) { URL.revokeObjectURL(arZustand.url); arZustand.url = null; } if (arZustand.text) $('#pd-ar').textContent = arZustand.text; }
  // Jede andere Wahl im Feld (Variante, Logo, Kiste, Gravur, Karat) macht die fertige USDZ alt
  const rechts = $('#produkt-demo .bd-rechts');
  rechts?.addEventListener('click', (e) => { if (!e.target.closest('#pd-ar')) arVeraltet(); }, true);
  rechts?.addEventListener('input', () => arVeraltet(), true);
  $('#pd-ar')?.addEventListener('click', async (e) => {
    const knopf = e.currentTarget, hinweis = $('#pd-ar-hinweis');
    const melden = (t) => { hinweis.textContent = t; };
    if (!b.z.p) { melden(TEXT.ar.laden); await b.zerlegen(false); return; }
    zaehlen(`ar-${b.modell}`);
    const AR = await import(new URL('ar.js', import.meta.url).href);
    await AR.ausloesen(arZustand, knopf, b.z.p.modellObjekt, TEXT.ar, melden);
  });
  $('#pd-kunde')?.addEventListener('click', () => {
    const was = b.modell, T = PRODUKT_TEXTE[was], M = PRODUKTE[was]; if (!T || !T.kunde || !M.kunde) return;
    const aktiv = wahl.querySelector('button[aria-pressed="true"]');
    const zeilen = [T.varianten[aktiv ? aktiv.dataset.variante : M.varianten[0][0]], ...T.kunde.zeilen];
    if (was === 'wein') zeilen.splice(1, 1, `${TEXT.kiste}: ${TEXT.kisteFmt(Number(kisteWahl))}`);
    if (was === 'lkw') zeilen.splice(1, 0, TEXT.ladungFmt(ladungN, Math.round((ladungN / 33) * 100), (Math.ceil(ladungN / 3) * 1.2).toLocaleString(LOKAL, { minimumFractionDigits: 1, maximumFractionDigits: 1 })));
    if (was === 'schmuck') {
      if (karatWahl !== 1) zeilen.splice(1, 0, TEXT.karatFmt(karatWahl.toLocaleString(LOKAL), (6.5 * Math.cbrt(karatWahl)).toLocaleString(LOKAL, { maximumFractionDigits: 1, minimumFractionDigits: 1 })));
      if (gravurText.trim()) zeilen.splice(1, 0, `${TEXT.gravur}: „${gravurText.trim().replace(/\n/g, ' / ')}“`);
    }
    zaehlen('produkt-kunde');
    document.dispatchEvent(new CustomEvent('vecom:kunde', { detail: { titel: T.kunde.titel, zeilen, ...M.kunde, ziel: cta ? cta.getAttribute('href') : '/bedarf.php' } }));
  });
  if (!webglDa()) schritte.closest('.gruppe').hidden = true;
}
