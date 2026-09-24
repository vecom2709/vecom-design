/* ==========================================================================
   kuechenplaner.js — Küche selbst planen (Demo „Küchenbau" der Galerie).

   WARUM ES DAS GIBT
   Uwe am 23.09.2026: „so wie bei Ikea mit Maßen verschiedene Küchen
   zusammenbauen". Eine Kochinsel zum Drehen zeigt ein Möbel; ein Planer zeigt
   dem Endkunden SEINE Küche -- Wandlänge eingeben, Form wählen, Schränke
   setzen. Das ist der Moment, in dem aus Neugier eine Anfrage wird.

   AUFBAU
   Diese Datei hält den Plan und die Bedienung (DOM, dreisprachig). Die Szene
   baut kuechenplaner-3d.js; sie wird erst geladen, wenn jemand die Bühne
   öffnet. Ohne WebGL 2 steht ein gezeichneter Grundriss (SVG) da -- planen,
   prüfen und anfragen geht trotzdem vollständig.

   Maße nach Küchennorm (Rastermaß 5 cm). Der Plan passt in einen kurzen
   Code (?plan=...), den bedarf.php prüft und lesbar in die Anfrage schreibt --
   wer anfragt, muss seine Küche nicht noch einmal beschreiben (Wunsch A3).
   ========================================================================== */
const L = (document.documentElement.lang || 'it').slice(0, 2);
const SPRACHE = ['it', 'de', 'en'].includes(L) ? L : 'it';
const $ = (s, r = document) => r.querySelector(s);

/* ------------------------------------------------------------ Katalog */
const TYPEN = {
  auszug:        { code: 'a', breiten: [40, 45, 50, 60, 80, 90] },
  tuer:          { code: 't', breiten: [30, 40, 45, 50, 60] },
  spuele:        { code: 's', breiten: [60, 80, 90] },
  kochfeld:      { code: 'k', breiten: [60, 80, 90] },
  gs:            { code: 'g', breiten: [45, 60] },
  backofen_u:    { code: 'o', breiten: [60] },
  // Passblenden werden auf den Zentimeter zugeschnitten (Wand B beginnt nach
  // der 62 cm tiefen Ecke -- ohne 1-cm-Raster bliebe dort immer ein Rest)
  blende:        { code: 'b', breiten: Array.from({ length: 15 }, (_, i) => i + 1) },
  eck:           { code: 'e', breiten: [110] },
  hoch_backofen: { code: 'h', breiten: [60], hoch: true },
  hoch_kuehl:    { code: 'c', breiten: [60], hoch: true },
  hoch_vorrat:   { code: 'v', breiten: [30, 40, 50, 60], hoch: true },
};
const AUS_CODE = Object.fromEntries(Object.entries(TYPEN).map(([k, t]) => [t.code, k]));
const GRENZEN = { a: [180, 600], b: [150, 420], insel: [120, 300] };
const PT = 62, GANG = 120;   // Plattentiefe und Gang in cm (wie im 3D-Modul)
const STIL = {
  front: { salbei: '#6b7a6b', weiss: '#e6e4df', nussbaum: '#6b4a33', graphit: '#35373a' },
  platte: { eiche: '#b88d5e', marmor: '#e9e7e2', keramik: '#8d8a84' },
  griff: { messing: '#e1b36c', edelstahl: '#c9c9c6', schwarz: '#151516', grifflos: '#2a2a2c' },
};

/* ------------------------------------------------------------ Texte */
const TEXTE = {
  de: {
    ar: { knopf: 'Im eigenen Raum ansehen (AR)', laden: 'Einen Moment – die Küche lädt. Dann noch einmal tippen.', vorbereiten: 'AR wird vorbereitet …', oeffnen: 'Jetzt in AR öffnen', fehler: 'AR ließ sich auf diesem Gerät nicht starten.', handy: 'AR funktioniert auf dem Handy: iPhone mit Safari oder Android mit Chrome. Öffnen Sie diese Seite dort – mit dem Link zur Planung kommt Ihre Küche mit.', suchen: 'Handy langsam über den Boden bewegen …', tippen: 'Tippen, um die Küche hinzustellen', steht: 'Steht. Zum Umstellen noch einmal tippen.', zu: 'Beenden' },
    form: 'Grundform', formen: { zeile: 'Küchenzeile', l: 'L-Form', insel: 'Mit Insel' },
    masse: 'Maße des Raums', waende: { a: 'Wand A', b: 'Wand B', insel: 'Insel' },
    auto: 'Automatisch planen', autoText: 'Setzt Spüle, Geschirrspüler, Kochfeld und Kühlschrank an die richtige Stelle und füllt den Rest passend auf.',
    schraenke: 'Schränke', breite: 'Breite', links: 'Nach links', rechts: 'Nach rechts', entfernen: 'Entfernen',
    hinzu: 'Schrank hinzufügen', hinzuKnopf: 'Hinzufügen', leer: 'Noch kein Schrank an dieser Wand.',
    typen: { auszug: 'Auszugschrank', tuer: 'Türschrank', spuele: 'Spülenschrank', kochfeld: 'Kochfeldschrank', gs: 'Geschirrspüler', backofen_u: 'Backofen unter der Platte', blende: 'Passblende', eck: 'Eckschrank', hoch_backofen: 'Hochschrank Backofen', hoch_kuehl: 'Hochschrank Kühlen', hoch_vorrat: 'Vorratsschrank' },
    nutzen: { auszug: 'Vollauszug – alles auf einen Blick', tuer: 'Einlegeboden, Scharnier mit Dämpfung', spuele: 'Unterbaubecken, Platz für Mülltrennung', kochfeld: 'Induktion, Töpfe direkt darunter', gs: 'Vollintegriert – unsichtbar hinter der Front', backofen_u: 'Backofen unter dem Kochfeld', blende: 'Gleicht Wandmaße aus', eck: 'Nutzt die Ecke mit Karussell', hoch_backofen: 'Backofen auf Augenhöhe – kein Bücken', hoch_kuehl: 'Kühl-Gefrier-Kombination, integriert', hoch_vorrat: 'Hoher Auszug für Vorräte' },
    stil: 'Ausführung', fronten: 'Fronten', platte: 'Arbeitsplatte', griffe: 'Griffe', ober: 'Hängeschränke', oberAn: 'Mit Hängeschränken',
    frontNamen: { salbei: 'Salbei matt', weiss: 'Weiß seidenmatt', nussbaum: 'Nussbaum', graphit: 'Graphit' },
    platteNamen: { eiche: 'Eiche geölt', marmor: 'Carrara-Marmor', keramik: 'Keramik Beton' },
    griffNamen: { messing: 'Messing', edelstahl: 'Edelstahl', schwarz: 'Schwarz matt', grifflos: 'Grifflos' },
    ansicht: 'Ansicht', ansichten: { '3d': '3D', oben: 'Von oben' }, oeffnen: 'Türen & Auszüge öffnen', masseZeigen: 'Maße zeigen',
    pruefung: 'Planungsprüfung', stueck: 'Stückliste',
    stueckZeile: (n, t, b) => `${n} × ${t}, ${b} cm`, platteLfm: (m) => `Arbeitsplatte: ${m} m`, oberZahl: (n) => `Hängeschränke: ${n}`,
    cta: 'Diesen Planer für mein Küchenstudio', kunde: 'So sieht es Ihre Kundschaft: Planung ans Studio senden', kTitel: 'Planung ans Küchenstudio senden', kTermin: 'mit Beratungstermin im Studio', kSchraenke: (n) => `${n} Schränke`, teilen: 'Link zur Planung kopieren', kopiert: 'Link kopiert',
    kennung: (f) => `Echtzeit · maßstäblich${f ? ` · ${f} Bilder/s` : ''}`, kennungPlan: 'Grundriss · maßstäblich',
    laedt: 'Lade den Planer …', keinWebgl: 'Dieses Gerät zeigt den Grundriss. Die 3D-Ansicht braucht WebGL 2.',
    leinwand: 'Die geplante Küche in 3D — ziehen zum Drehen, Pfeiltasten, Schrank antippen zum Bearbeiten',
    h: {
      ueber: (w, cm) => `${w}: ${cm} cm zu lang — einen Schrank schmaler wählen oder entfernen.`,
      rest: (w, cm) => `${w}: ${cm} cm frei — schmalen Schrank oder Passblende einsetzen.`,
      passt: (w) => `${w} passt auf den Zentimeter.`,
      spueleFehlt: 'Noch keine Spüle geplant.', spueleMehr: 'Mehr als eine Spüle geplant.',
      kuehlFehlt: 'Noch kein Kühlschrank geplant — ein Hochschrank Kühlen passt z. B. ans Ende einer Wand.',
      kochFehlt: 'Noch kein Kochfeld geplant.', kochMehr: 'Mehr als ein Kochfeld geplant.',
      gsOk: 'Geschirrspüler direkt neben der Spüle — kurze Wege, ein Wasseranschluss.',
      gsWeg: 'Geschirrspüler nicht neben der Spüle — Anschluss und Wege werden länger.',
      flaecheOk: (cm) => `Arbeitsfläche zwischen Spüle und Kochfeld: ${cm} cm.`,
      flaecheKnapp: (cm) => `Nur ${cm} cm Arbeitsfläche zwischen Spüle und Kochfeld — 60 cm oder mehr sind bequem.`,
      kochHoch: 'Kochfeld direkt neben einem Hochschrank — 30 cm Abstand vorsehen.',
      kochWand: 'Kochfeld direkt an der Wand — seitlich 30 cm Platz lassen.',
      kuehlKoch: 'Kühlschrank direkt neben dem Kochfeld — die Wärme kostet Strom.',
      dreieckOk: (m) => `Arbeitsdreieck Kühlen – Spülen – Kochen: ${m} m (ideal 3,6 bis 7,9 m).`,
      dreieckAus: (m) => `Arbeitsdreieck Kühlen – Spülen – Kochen: ${m} m — ideal sind 3,6 bis 7,9 m.`,
      eck: 'Der Eckschrank gehört in die Ecke: erster Schrank an Wand A der L-Form.',
      gang: 'Gang zwischen Zeile und Insel: 120 cm — genug für zwei Personen.',
    },
  },
  it: {
    ar: { knopf: 'Nella sua stanza (AR)', laden: 'Un momento – la cucina si carica. Poi tocchi di nuovo.', vorbereiten: 'Preparo l’AR …', oeffnen: 'Apri in AR', fehler: 'Su questo dispositivo l’AR non è partita.', handy: 'L’AR funziona sul telefono: iPhone con Safari o Android con Chrome. Apra lì questa pagina – con il link al progetto arriva anche la sua cucina.', suchen: 'Muova piano il telefono sopra il pavimento …', tippen: 'Tocchi per posizionare la cucina', steht: 'Fatto. Tocchi di nuovo per spostarla.', zu: 'Esci' },
    form: 'Forma', formen: { zeile: 'Lineare', l: 'Ad angolo', insel: 'Con isola' },
    masse: 'Misure della stanza', waende: { a: 'Parete A', b: 'Parete B', insel: 'Isola' },
    auto: 'Progettazione automatica', autoText: 'Mette lavello, lavastoviglie, piano cottura e frigorifero al posto giusto e completa il resto su misura.',
    schraenke: 'Mobili', breite: 'Larghezza', links: 'A sinistra', rechts: 'A destra', entfernen: 'Togli',
    hinzu: 'Aggiungi un mobile', hinzuKnopf: 'Aggiungi', leer: 'Ancora nessun mobile su questa parete.',
    typen: { auszug: 'Cassettiera', tuer: 'Base a anta', spuele: 'Base lavello', kochfeld: 'Base piano cottura', gs: 'Lavastoviglie', backofen_u: 'Forno sotto il piano', blende: 'Compensatore', eck: 'Base ad angolo', hoch_backofen: 'Colonna forno', hoch_kuehl: 'Colonna frigo', hoch_vorrat: 'Colonna dispensa' },
    nutzen: { auszug: 'Estrazione totale – tutto sotto gli occhi', tuer: 'Ripiano, cerniera con ammortizzatore', spuele: 'Vasca sottotop, spazio per la differenziata', kochfeld: 'Induzione, pentole subito sotto', gs: 'Totalmente integrata – invisibile dietro l’anta', backofen_u: 'Forno sotto il piano cottura', blende: 'Assorbe le tolleranze del muro', eck: 'Sfrutta l’angolo con il carosello', hoch_backofen: 'Forno all’altezza degli occhi – senza chinarsi', hoch_kuehl: 'Frigo-congelatore integrato', hoch_vorrat: 'Estraibile alto per la dispensa' },
    stil: 'Finiture', fronten: 'Ante', platte: 'Piano di lavoro', griffe: 'Maniglie', ober: 'Pensili', oberAn: 'Con pensili',
    frontNamen: { salbei: 'Salvia opaco', weiss: 'Bianco satinato', nussbaum: 'Noce', graphit: 'Grafite' },
    platteNamen: { eiche: 'Rovere oliato', marmor: 'Marmo di Carrara', keramik: 'Ceramica effetto cemento' },
    griffNamen: { messing: 'Ottone', edelstahl: 'Acciaio', schwarz: 'Nero opaco', grifflos: 'Senza maniglie' },
    ansicht: 'Vista', ansichten: { '3d': '3D', oben: 'Dall’alto' }, oeffnen: 'Apri ante e cassetti', masseZeigen: 'Mostra misure',
    pruefung: 'Controllo del progetto', stueck: 'Elenco mobili',
    stueckZeile: (n, t, b) => `${n} × ${t}, ${b} cm`, platteLfm: (m) => `Piano di lavoro: ${m} m`, oberZahl: (n) => `Pensili: ${n}`,
    cta: 'Questo progettatore per il mio showroom', kunde: 'Come lo vede il cliente: invii il progetto allo showroom', kTitel: 'Invio del progetto allo showroom', kTermin: 'con appuntamento in showroom', kSchraenke: (n) => `${n} mobili`, teilen: 'Copia il link al progetto', kopiert: 'Link copiato',
    kennung: (f) => `Tempo reale · in scala${f ? ` · ${f} fps` : ''}`, kennungPlan: 'Pianta · in scala',
    laedt: 'Carico il progettatore …', keinWebgl: 'Questo dispositivo mostra la pianta. La vista 3D richiede WebGL 2.',
    leinwand: 'La cucina progettata in 3D — trascini per girare, frecce, tocchi un mobile per modificarlo',
    h: {
      ueber: (w, cm) => `${w}: ${cm} cm di troppo — scelga un mobile più stretto o lo tolga.`,
      rest: (w, cm) => `${w}: ${cm} cm liberi — aggiunga un mobile stretto o un compensatore.`,
      passt: (w) => `${w} torna al centimetro.`,
      spueleFehlt: 'Manca ancora il lavello.', spueleMehr: 'Più di un lavello nel progetto.',
      kuehlFehlt: 'Manca ancora il frigorifero — una colonna frigo sta bene in fondo a una parete.',
      kochFehlt: 'Manca ancora il piano cottura.', kochMehr: 'Più di un piano cottura nel progetto.',
      gsOk: 'Lavastoviglie accanto al lavello — percorsi brevi, un solo attacco.',
      gsWeg: 'Lavastoviglie lontana dal lavello — attacchi e percorsi più lunghi.',
      flaecheOk: (cm) => `Piano libero tra lavello e cottura: ${cm} cm.`,
      flaecheKnapp: (cm) => `Solo ${cm} cm liberi tra lavello e cottura — 60 cm o più sono comodi.`,
      kochHoch: 'Piano cottura accanto a una colonna — preveda 30 cm di distanza.',
      kochWand: 'Piano cottura contro il muro — lasci 30 cm di lato.',
      kuehlKoch: 'Frigo accanto al piano cottura — il calore costa corrente.',
      dreieckOk: (m) => `Triangolo di lavoro frigo – lavello – cottura: ${m} m (ideale da 3,6 a 7,9 m).`,
      dreieckAus: (m) => `Triangolo di lavoro frigo – lavello – cottura: ${m} m — l’ideale è da 3,6 a 7,9 m.`,
      eck: 'La base ad angolo va nell’angolo: primo mobile sulla parete A della cucina ad angolo.',
      gang: 'Passaggio tra cucina e isola: 120 cm — comodo per due persone.',
    },
  },
  en: {
    ar: { knopf: 'See it in your room (AR)', laden: 'One moment – the kitchen is loading. Then tap again.', vorbereiten: 'Preparing AR …', oeffnen: 'Open in AR now', fehler: 'AR could not start on this device.', handy: 'AR works on phones: iPhone with Safari or Android with Chrome. Open this page there – the plan link brings your kitchen along.', suchen: 'Move your phone slowly over the floor …', tippen: 'Tap to place the kitchen', steht: 'Placed. Tap again to move it.', zu: 'Exit' },
    form: 'Layout', formen: { zeile: 'Single wall', l: 'L-shaped', insel: 'With island' },
    masse: 'Room dimensions', waende: { a: 'Wall A', b: 'Wall B', insel: 'Island' },
    auto: 'Plan it for me', autoText: 'Puts sink, dishwasher, hob and fridge in the right place and fills the rest to size.',
    schraenke: 'Cabinets', breite: 'Width', links: 'Move left', rechts: 'Move right', entfernen: 'Remove',
    hinzu: 'Add a cabinet', hinzuKnopf: 'Add', leer: 'No cabinet on this wall yet.',
    typen: { auszug: 'Drawer unit', tuer: 'Door unit', spuele: 'Sink unit', kochfeld: 'Hob unit', gs: 'Dishwasher', backofen_u: 'Oven under the worktop', blende: 'Filler panel', eck: 'Corner unit', hoch_backofen: 'Tall oven unit', hoch_kuehl: 'Tall fridge unit', hoch_vorrat: 'Larder unit' },
    nutzen: { auszug: 'Full extension – everything in view', tuer: 'Shelf, soft-close hinge', spuele: 'Undermount sink, room for recycling', kochfeld: 'Induction, pans right below', gs: 'Fully integrated – hidden behind the front', backofen_u: 'Oven under the hob', blende: 'Takes up wall tolerances', eck: 'Uses the corner with a carousel', hoch_backofen: 'Oven at eye level – no bending', hoch_kuehl: 'Integrated fridge-freezer', hoch_vorrat: 'Tall pull-out for supplies' },
    stil: 'Finish', fronten: 'Fronts', platte: 'Worktop', griffe: 'Handles', ober: 'Wall cabinets', oberAn: 'With wall cabinets',
    frontNamen: { salbei: 'Sage matt', weiss: 'Satin white', nussbaum: 'Walnut', graphit: 'Graphite' },
    platteNamen: { eiche: 'Oiled oak', marmor: 'Carrara marble', keramik: 'Concrete ceramic' },
    griffNamen: { messing: 'Brass', edelstahl: 'Stainless steel', schwarz: 'Matt black', grifflos: 'Handleless' },
    ansicht: 'View', ansichten: { '3d': '3D', oben: 'From above' }, oeffnen: 'Open doors & drawers', masseZeigen: 'Show dimensions',
    pruefung: 'Plan check', stueck: 'Cabinet list',
    stueckZeile: (n, t, b) => `${n} × ${t}, ${b} cm`, platteLfm: (m) => `Worktop: ${m} m`, oberZahl: (n) => `Wall cabinets: ${n}`,
    cta: 'This planner for my kitchen studio', kunde: 'What your customers see: send the plan to the studio', kTitel: 'Send the plan to the kitchen studio', kTermin: 'with a consultation in the studio', kSchraenke: (n) => `${n} cabinets`, teilen: 'Copy link to this plan', kopiert: 'Link copied',
    kennung: (f) => `Real time · to scale${f ? ` · ${f} fps` : ''}`, kennungPlan: 'Floor plan · to scale',
    laedt: 'Loading the planner …', keinWebgl: 'This device shows the floor plan. The 3D view needs WebGL 2.',
    leinwand: 'The planned kitchen in 3D — drag to turn, arrow keys, tap a cabinet to edit it',
    h: {
      ueber: (w, cm) => `${w}: ${cm} cm too long — pick a narrower cabinet or remove one.`,
      rest: (w, cm) => `${w}: ${cm} cm left — add a narrow cabinet or a filler panel.`,
      passt: (w) => `${w} fits to the centimetre.`,
      spueleFehlt: 'No sink planned yet.', spueleMehr: 'More than one sink planned.',
      kuehlFehlt: 'No fridge planned yet — a tall fridge unit fits well at the end of a wall.',
      kochFehlt: 'No hob planned yet.', kochMehr: 'More than one hob planned.',
      gsOk: 'Dishwasher right next to the sink — short paths, one water connection.',
      gsWeg: 'Dishwasher away from the sink — longer connections and paths.',
      flaecheOk: (cm) => `Worktop between sink and hob: ${cm} cm.`,
      flaecheKnapp: (cm) => `Only ${cm} cm between sink and hob — 60 cm or more is comfortable.`,
      kochHoch: 'Hob right next to a tall unit — allow 30 cm of space.',
      kochWand: 'Hob right against the wall — leave 30 cm at the side.',
      kuehlKoch: 'Fridge right next to the hob — the heat costs energy.',
      dreieckOk: (m) => `Work triangle fridge – sink – hob: ${m} m (ideal 3.6 to 7.9 m).`,
      dreieckAus: (m) => `Work triangle fridge – sink – hob: ${m} m — ideal is 3.6 to 7.9 m.`,
      eck: 'The corner unit belongs in the corner: first cabinet on wall A of the L-shape.',
      gang: 'Aisle between run and island: 120 cm — room for two people.',
    },
  },
}[SPRACHE];
const ZAHL = new Intl.NumberFormat({ it: 'it-IT', de: 'de-DE', en: 'en-GB' }[SPRACHE], { maximumFractionDigits: 2, minimumFractionDigits: 2 });

/* ------------------------------------------------------------ Planen */
const m = (typ, breite) => ({ typ, breite });
const summe = (r) => r.reduce((a, x) => a + x.breite, 0);

/* Rest mit möglichst wenigen Schränken füllen (Raster 5 cm). Gesucht ist die
   größte erreichbare Summe <= rest, die höchstens 15 cm offen lässt; die
   Lücke schließt eine Passblende. Bei der Insel muss es genau aufgehen. */
function auffuellen(rest, genau = false) {
  const W = [90, 80, 60, 50, 45, 40].map((b) => b / 5), R = Math.floor(rest / 5), off = rest - R * 5;
  if (R <= 0) return { module: [], luecke: Math.max(0, rest) };
  const beste = new Array(R + 1).fill(null); beste[0] = [];
  for (let s = 1; s <= R; s++) {
    for (const w of W) {
      if (w > s || !beste[s - w]) continue;
      const kand = [...beste[s - w], w];
      // Weniger Schränke gewinnen; bei Gleichstand die mit mehr 60ern (Norm, günstig)
      const wert = (l) => l.length * 10 - l.filter((x) => x === 12).length;
      if (!beste[s] || wert(kand) < wert(beste[s])) beste[s] = kand;
    }
  }
  for (let s = R; s >= 0 && s >= R - (genau ? 0 : 3); s--) {
    if (beste[s] && (!genau || off === 0)) return { module: beste[s].map((w) => w * 5).sort((a, b) => b - a), luecke: rest - s * 5 };
  }
  return { module: [], luecke: rest };
}

/* Vorlagen mit Platzhaltern (null) für die Füllschränke. Reihenfolge der
   Plätze = wohin zuerst gefüllt wird: zuerst Arbeitsfläche zwischen Spüle und
   Kochfeld, dann neben dem Kochfeld, dann der Rest. */
function vorlage(form, a, b) {
  const koch = (x) => m('kochfeld', x >= 420 ? 90 : x >= 280 ? 80 : 60);
  if (form === 'zeile') {
    if (a >= 360) return { a: [m('hoch_kuehl', 60), m('hoch_backofen', 60), 'F3', m('spuele', 80), m('gs', 60), 'F1', koch(a), 'F2'] };
    if (a >= 300) return { a: [m('hoch_kuehl', 60), 'F3', m('spuele', 80), m('gs', 60), 'F1', koch(a), 'F2'] };
    return { a: [m('spuele', a >= 240 ? 80 : 60), m('gs', a >= 240 ? 60 : 45), 'F1', m('kochfeld', 60), 'F2'] };
  }
  if (form === 'l') {
    const B = b - PT;
    return {
      a: [m('eck', 110), 'F3', m('spuele', 80), m('gs', 60), 'F4'],
      b: B >= 300 ? ['F1', koch(B + 60), 'F2', m('hoch_backofen', 60), m('hoch_kuehl', 60)]
        : B >= 200 ? ['F1', koch(B + 60), 'F2', m('hoch_kuehl', 60)] : ['F1', m('kochfeld', 60), 'F2'],
    };
  }
  return {
    a: a >= 300 ? [m('hoch_kuehl', 60), m('hoch_backofen', 60), 'F3', m('spuele', 80), m('gs', 60), 'F4'] : [m('hoch_kuehl', 60), 'F3', m('spuele', 80), m('gs', 60), 'F4'],
    insel: ['F1', m('kochfeld', 90), 'F2'],
  };
}

/* Bewertung einer Reihe nach den Regeln der Küchenplanung -- dieselben, die
   die Planungsprüfung anzeigt. Kleiner ist besser. Damit wählt "Automatisch
   planen" unter allen Breiten und Verteilungen die beste, statt eine feste
   Vorlage stur aufzufüllen (erste Fassung: Kochfeld landete an der Wand). */
function bewerten(r, lauf, form, luecke) {
  let p = luecke * 4 + (luecke > 15 ? 400 : 0) + r.length * 6;
  const idx = (t) => r.findIndex((x) => x.typ === t);
  const ko = idx('kochfeld'), sp = idx('spuele'), gs = idx('gs');
  const zwischen = (a, b) => r.slice(Math.min(a, b) + 1, Math.max(a, b)).reduce((s, x) => s + (TYPEN[x.typ].hoch ? 0 : x.breite), 0);
  if (ko >= 0 && sp >= 0) { const f = zwischen(ko, sp); if (f < 60) p += 300; if (f > 150) p += (f - 150); }
  if (gs >= 0 && sp >= 0 && Math.abs(gs - sp) !== 1) p += 200;
  if (ko >= 0 && lauf !== 'insel') {
    // Platz links/rechts bis zur Wand oder zum Hochschrank; Wand B beginnt an der Ecke (offen)
    let l = 0, k = ko - 1; while (k >= 0 && !TYPEN[r[k].typ].hoch) l += r[k--].breite;
    const linksZu = k >= 0 || !(lauf === 'b' && form === 'l');
    let re = 0; k = ko + 1; while (k < r.length && !TYPEN[r[k].typ].hoch) re += r[k++].breite;
    if (linksZu && l < 30) p += 250;
    if (re < 30) p += 250;
    if ((r[ko - 1] && r[ko - 1].typ === 'hoch_kuehl') || (r[ko + 1] && r[ko + 1].typ === 'hoch_kuehl')) p += 200;
  }
  if (ko >= 0 && lauf === 'insel') {
    // Auf der Insel mittig: an der Kante spritzt es in den Gang
    const l = r.slice(0, ko).reduce((s, x) => s + x.breite, 0), re = r.slice(ko + 1).reduce((s, x) => s + x.breite, 0);
    p += (l < 30 ? 120 : 0) + (re < 30 ? 120 : 0) + Math.abs(l - re) / 4;
  }
  if (ko >= 0) p -= r[ko].breite / 10;   // großes Kochfeld ist ein Verkaufsargument
  if (sp >= 0) p -= r[sp].breite / 20;
  return p;
}

function laufPlanen(liste, platz, lauf, form) {
  const genau = lauf === 'insel';
  const variabel = { kochfeld: [60, 80, 90], spuele: [60, 80, 90], gs: [45, 60] };
  const fest = liste.map((x, i) => [x, i]).filter(([x]) => typeof x !== 'string');
  const plaetze = liste.map((x, i) => [x, i]).filter(([x]) => typeof x === 'string').map(([, i]) => i);
  // Alle Breitenkombinationen der festen Geräte
  let kombis = [[]];
  for (const [x] of fest) kombis = kombis.flatMap((k) => (variabel[x.typ] || [x.breite]).map((b) => [...k, b]));
  let bestes = null;
  for (const breiten of kombis) {
    const rest = platz - breiten.reduce((a, b) => a + b, 0);
    if (rest < 0) continue;
    const { module, luecke } = auffuellen(rest, genau);
    if (genau && luecke) continue;
    const n = module.length, s = Math.max(1, plaetze.length);
    const grenze = n <= 7 ? s ** n : 1;   // mehr Schränke: reihum verteilen statt alles durchprobieren
    for (let code = 0; code < grenze; code++) {
      const verteilt = new Map(plaetze.map((i) => [i, []]));
      let c = code;
      module.forEach((b, k) => { const z = n <= 7 ? c % s : k % s; c = Math.floor(c / s); verteilt.get(plaetze[z])?.push(m(b === 30 ? 'tuer' : 'auszug', b)); });
      const r = []; let f = 0;
      liste.forEach((x, i) => { if (typeof x === 'string') r.push(...verteilt.get(i)); else r.push(m(x.typ, breiten[f++])); });
      if (luecke >= 1 && !genau) r.push(m('blende', Math.min(15, luecke)));
      const wert = bewerten(r, lauf, form, luecke > 15 ? luecke : 0);
      if (!bestes || wert < bestes.wert) bestes = { wert, r };
    }
  }
  return bestes ? bestes.r : [];
}

function automatisch(plan) {
  const v = vorlage(plan.form, plan.waende.a, plan.waende.b);
  const reihen = { a: [], b: [], insel: [] };
  for (const [lauf, liste] of Object.entries(v)) {
    const platz = lauf === 'b' ? plan.waende.b - PT : lauf === 'insel' ? plan.waende.insel : plan.waende.a;
    // Zu kurz für die Vorlage: nacheinander Hochschränke und Geschirrspüler weglassen
    const weglassen = ['hoch_backofen', 'hoch_kuehl', 'gs'];
    let r = laufPlanen(liste, platz, lauf, plan.form);
    while (!r.length && weglassen.length) {
      const t = weglassen.shift(); const i = liste.findIndex((x) => x && x.typ === t);
      if (i >= 0) { liste.splice(i, 1); r = laufPlanen(liste, platz, lauf, plan.form); }
    }
    reihen[lauf] = r;
  }
  plan.reihen = reihen;
}

/* ------------------------------------------------------------ Code */
function codieren(p) {
  const r = (l) => l.map((x) => TYPEN[x.typ].code + x.breite).join('');
  const f = { zeile: 'z', l: 'l', insel: 'i' }[p.form];
  const w = (x) => String(x).padStart(3, '0');
  return `${f}-${w(p.waende.a)}-${w(p.waende.b)}-${w(p.waende.insel)}_${p.front}-${p.platte}-${p.griff}-${p.oberschraenke ? 1 : 0}_${r(p.reihen.a)}_${r(p.form === 'l' ? p.reihen.b : [])}_${r(p.form === 'insel' ? p.reihen.insel : [])}`;
}
const CODE_RE = /^([zli])-(\d{3})-(\d{3})-(\d{3})_(salbei|weiss|nussbaum|graphit)-(eiche|marmor|keramik)-(messing|edelstahl|schwarz|grifflos)-([01])_((?:[atskgobehcv]\d{1,3}){0,24})_((?:[atskgobehcv]\d{1,3}){0,24})_((?:[atskgobehcv]\d{1,3}){0,24})$/;
function decodieren(code) {
  const t = CODE_RE.exec(code || ''); if (!t) return null;
  const reihe = (s) => [...s.matchAll(/([a-z])(\d+)/g)].map(([, c, b]) => m(AUS_CODE[c], Number(b))).filter((x) => TYPEN[x.typ].breiten.includes(x.breite));
  const kl = (v, [a, b]) => Math.min(b, Math.max(a, Math.round(v / 5) * 5));
  return {
    form: { z: 'zeile', l: 'l', i: 'insel' }[t[1]],
    waende: { a: kl(+t[2], GRENZEN.a), b: kl(+t[3], GRENZEN.b), insel: kl(+t[4], GRENZEN.insel) },
    front: t[5], platte: t[6], griff: t[7], oberschraenke: t[8] === '1',
    reihen: { a: reihe(t[9]), b: reihe(t[10]), insel: reihe(t[11]) },
  };
}

/* ------------------------------------------------------------ Prüfen */
function laeufe(p) {
  const l = [['a', p.waende.a]];
  if (p.form === 'l') l.push(['b', p.waende.b - PT]);
  if (p.form === 'insel') l.push(['insel', null]);
  return l;
}
function pruefen(p) {
  const H = TEXTE.h, out = [];   // [art: ok|warn|fehler, text]
  const alle = [];
  for (const [lauf, platz] of laeufe(p)) {
    const r = p.reihen[lauf]; const s = summe(r); const name = TEXTE.waende[lauf];
    r.forEach((x, i) => alle.push({ ...x, lauf, i }));
    if (platz === null) continue;
    if (s > platz) out.push(['fehler', H.ueber(name, s - platz)]);
    else if (platz - s > 0 && r.length) out.push(['warn', H.rest(name, platz - s)]);
    else if (r.length) out.push(['ok', H.passt(name)]);
  }
  const sp = alle.filter((x) => x.typ === 'spuele'), ko = alle.filter((x) => x.typ === 'kochfeld'), ku = alle.find((x) => x.typ === 'hoch_kuehl');
  if (!sp.length) out.push(['warn', H.spueleFehlt]); else if (sp.length > 1) out.push(['warn', H.spueleMehr]);
  if (!ku) out.push(['warn', H.kuehlFehlt]);
  if (!ko.length) out.push(['warn', H.kochFehlt]); else if (ko.length > 1) out.push(['warn', H.kochMehr]);
  const nachbarn = (x) => [p.reihen[x.lauf][x.i - 1], p.reihen[x.lauf][x.i + 1]];
  const gs = alle.find((x) => x.typ === 'gs');
  if (gs && sp[0]) out.push(nachbarn(gs).some((n) => n && n.typ === 'spuele') ? ['ok', H.gsOk] : ['warn', H.gsWeg]);
  if (sp[0] && ko[0] && sp[0].lauf === ko[0].lauf) {
    const [a, b] = [sp[0].i, ko[0].i].sort((x, y) => x - y);
    const frei = p.reihen[sp[0].lauf].slice(a + 1, b).filter((x) => !TYPEN[x.typ].hoch).reduce((s, x) => s + x.breite, 0);
    out.push(frei >= 60 ? ['ok', H.flaecheOk(frei)] : ['warn', H.flaecheKnapp(frei)]);
  }
  if (ko[0]) {
    const n = nachbarn(ko[0]);
    if (n.some((x) => x && TYPEN[x.typ].hoch)) out.push(['warn', H.kochHoch]);
    // An der Wand: erster Schrank an Wand A, oder letzter vor der Wand (Passblende zählt nicht)
    const r = p.reihen[ko[0].lauf]; const letzter = r.map((x) => x.typ).lastIndexOf(r.filter((x) => x.typ !== 'blende').pop()?.typ);
    if (ko[0].lauf !== 'insel' && ((ko[0].i === 0 && ko[0].lauf === 'a') || ko[0].i === letzter)) out.push(['warn', H.kochWand]);
    if (n.some((x) => x && x.typ === 'hoch_kuehl')) out.push(['warn', H.kuehlKoch]);
  }
  const eck = alle.filter((x) => x.typ === 'eck');
  if (eck.some((x) => p.form !== 'l' || x.lauf !== 'a' || x.i !== 0)) out.push(['fehler', H.eck]);
  // Arbeitsdreieck aus den Schrankmitten (Grundriss, Meter)
  if (sp[0] && ko[0] && ku) {
    const pos = (x) => {
      const r = p.reihen[x.lauf]; const start = x.lauf === 'b' ? PT : 0;
      const u = (start + summe(r.slice(0, x.i)) + x.breite / 2) / 100;
      if (x.lauf === 'b') return [0.3, u];
      if (x.lauf === 'insel') { const li = summe(r) / 100; return [Math.max(0, p.waende.a / 200 - li / 2) + u, (PT + GANG + 30) / 100]; }
      return [u, 0.3];
    };
    const [A, B, C] = [pos(ku), pos(sp[0]), pos(ko[0])];
    const d = (q, w) => Math.hypot(q[0] - w[0], q[1] - w[1]);
    const um = d(A, B) + d(B, C) + d(C, A);
    const t = um.toLocaleString({ it: 'it-IT', de: 'de-DE', en: 'en-GB' }[SPRACHE], { maximumFractionDigits: 1, minimumFractionDigits: 1 });
    out.push(um >= 3.6 && um <= 7.9 ? ['ok', H.dreieckOk(t)] : ['warn', H.dreieckAus(t)]);
  }
  if (p.form === 'insel') out.push(['ok', H.gang]);
  return out;
}

function stueckliste(p) {
  const zaehl = new Map(); let platte = 0, ober = 0;
  for (const [lauf] of laeufe(p)) {
    for (const x of p.reihen[lauf]) {
      const k = `${x.typ}|${x.breite}`; zaehl.set(k, (zaehl.get(k) || 0) + 1);
      if (!TYPEN[x.typ].hoch) platte += x.breite;
      if (p.oberschraenke && lauf !== 'insel' && !TYPEN[x.typ].hoch && !['blende', 'kochfeld'].includes(x.typ)) ober++;
    }
  }
  const zeilen = [...zaehl.entries()].map(([k, n]) => { const [t, b] = k.split('|'); return TEXTE.stueckZeile(n, TEXTE.typen[t], b); });
  zeilen.push(TEXTE.platteLfm(ZAHL.format(platte / 100)));
  if (p.oberschraenke && ober) zeilen.push(TEXTE.oberZahl(ober));
  return zeilen;
}

/* ------------------------------------------------------------ Grundriss (SVG) */
// Ohne WebGL: maßstäblicher Grundriss, derselbe Plan, dieselben Maße.
function grundriss(p) {
  const S = 100;   // px je Meter
  const la = p.waende.a / 100, lb = p.form === 'l' ? p.waende.b / 100 : 0;
  const inselZ = (PT + GANG) / 100, li = summe(p.reihen.insel) / 100;
  const breite = Math.max(la, 1.8) + 0.8, tiefe = p.form === 'insel' ? inselZ + 0.92 + 0.5 : Math.max(lb, 1.4) + 0.4;
  const e = [];
  const rect = (x, z, w, d, fill, t) => e.push(`<rect x="${(x * S).toFixed(1)}" y="${(z * S).toFixed(1)}" width="${(w * S).toFixed(1)}" height="${(d * S).toFixed(1)}" fill="${fill}" stroke="#0b0d12" stroke-width="1"/>` + (t ? `<text x="${((x + w / 2) * S).toFixed(1)}" y="${((z + d / 2) * S + 4).toFixed(1)}" text-anchor="middle" font-size="12" fill="#0b0d12">${t}</text>` : ''));
  e.push(`<rect x="${-0.1 * S}" y="${-0.1 * S}" width="${(la + 0.1) * S}" height="${0.1 * S}" fill="#8591a8"/>`);
  if (p.form === 'l') e.push(`<rect x="${-0.1 * S}" y="0" width="${0.1 * S}" height="${lb * S}" fill="#8591a8"/>`);
  const farbe = (x) => TYPEN[x.typ].hoch ? '#9aa3b5' : x.typ === 'blende' ? '#4a5263' : '#d7dbe4';
  let u = 0; for (const x of p.reihen.a) { rect(u, 0, x.breite / 100, PT / 100, farbe(x), x.breite); u += x.breite / 100; }
  if (p.form === 'l') { let v = PT / 100; for (const x of p.reihen.b) { rect(0, v, PT / 100, x.breite / 100, farbe(x), x.breite); v += x.breite / 100; } }
  if (p.form === 'insel') { let x0 = Math.max(0, la / 2 - li / 2); for (const x of p.reihen.insel) { rect(x0, inselZ, x.breite / 100, (PT + 30) / 100, farbe(x), x.breite); x0 += x.breite / 100; } }
  e.push(`<text x="${(la / 2) * S}" y="${-0.2 * S}" text-anchor="middle" font-size="14" fill="#e9eef8">${TEXTE.waende.a} · ${p.waende.a} cm</text>`);
  return `<svg class="kp-plan" viewBox="${-0.5 * S} ${-0.4 * S} ${breite * S} ${tiefe * S}" role="img" aria-label="${TEXTE.kennungPlan}">${e.join('')}</svg>`;
}

/* ------------------------------------------------------------ Oberfläche */
const sek = document.getElementById('kuechenplaner');
if (sek) {
  const fig = $('.kp-buehne', sek), panel = $('.kp-panel', sek), kennung = $('.kennung__text', fig);
  const start = decodieren(new URLSearchParams(location.search).get('plan'));
  const plan = start || { form: 'l', waende: { a: 300, b: 270, insel: 240 }, reihen: { a: [], b: [], insel: [] }, front: 'salbei', platte: 'eiche', griff: 'messing', oberschraenke: true };
  if (!start) automatisch(plan);
  const kopf = document.createElement('div'), rumpf = document.createElement('div');
  kopf.className = 'kp-kopf'; rumpf.className = 'kp-rumpf'; panel.append(kopf, rumpf);
  let aktiverLauf = 'a', auswahl = null, api = null, laden = null, ansicht = '3d', offen = false, masse = true;

  const el = (tag, attr = {}, ...kinder) => {
    const n = document.createElement(tag);
    for (const [k, v] of Object.entries(attr)) { if (k === 'text') n.textContent = v; else if (k.startsWith('on')) n.addEventListener(k.slice(2), v); else if (v !== false && v != null) n.setAttribute(k, v === true ? '' : v); }
    n.append(...kinder.filter(Boolean)); return n;
  };
  const gruppe = (titel, id, ...inhalt) => el('div', { class: 'gruppe' }, el('span', { class: 'gruppe__name', id, text: titel }), ...inhalt);
  const chips = (label, eintraege, wert, setzen) => el('div', { class: 'chips chips--umbruch', role: 'group', 'aria-labelledby': label },
    ...eintraege.map(([k, t, farbe, svg]) => {
      const b = el('button', { type: 'button', 'aria-pressed': String(k === wert), onclick: () => setzen(k) });
      if (svg) b.insertAdjacentHTML('beforeend', svg);
      if (farbe) { const pk = el('i', { class: 'farbpunkt', 'aria-hidden': 'true' }); pk.style.setProperty('--f', farbe); b.append(pk); }
      b.append(t); return b;
    }));
  // Kleine Grundrisse als Zeichen für die Form
  const FORM_SVG = {
    zeile: '<svg class="kp-form" viewBox="0 0 24 16" aria-hidden="true"><rect x="2" y="2" width="20" height="4" rx="1"/></svg>',
    l: '<svg class="kp-form" viewBox="0 0 24 16" aria-hidden="true"><rect x="2" y="2" width="20" height="4" rx="1"/><rect x="2" y="2" width="4" height="12" rx="1"/></svg>',
    insel: '<svg class="kp-form" viewBox="0 0 24 16" aria-hidden="true"><rect x="2" y="2" width="20" height="4" rx="1"/><rect x="7" y="10" width="10" height="4" rx="1"/></svg>',
  };

  function regler(lauf) {
    const [min, max] = GRENZEN[lauf]; const id = `kp-w-${lauf}`;
    const zahl = el('input', { type: 'number', inputmode: 'numeric', min, max, step: 5, value: plan.waende[lauf], id: `${id}-n`, 'aria-labelledby': `${id}-l` });
    const schieb = el('input', { type: 'range', min, max, step: 5, value: plan.waende[lauf], id, 'aria-labelledby': `${id}-l` });
    const setzen = (v, neu) => {
      v = Math.min(max, Math.max(min, Math.round((Number(v) || min) / 5) * 5));
      plan.waende[lauf] = v; zahl.value = v; schieb.value = v;
      if (neu) { automatisch(plan); zeichnen(); }
    };
    schieb.addEventListener('input', () => setzen(schieb.value, true));
    zahl.addEventListener('change', () => setzen(zahl.value, true));
    return el('div', { class: 'kp-regler' }, el('label', { id: `${id}-l`, for: id, text: TEXTE.waende[lauf] }), schieb, el('span', { class: 'kp-zahl' }, zahl, ' cm'));
  }

  function modulZeile(lauf, x, i, n) {
    const T = TYPEN[x.typ]; const gewaehlt = auswahl && auswahl.lauf === lauf && auswahl.index === i;
    const breite = el('select', { 'aria-label': `${TEXTE.breite} ${TEXTE.typen[x.typ]}`, onchange: (e) => { x.breite = Number(e.target.value); zeichnen(); } },
      ...T.breiten.map((b) => el('option', { value: b, selected: b === x.breite, text: `${b} cm` })));
    const knopf = (t, zeichen, fn, aus) => el('button', { type: 'button', class: 'kp-mini', 'aria-label': t, title: t, disabled: aus, onclick: fn, text: zeichen });
    const reihe = plan.reihen[lauf];
    return el('li', { class: `kp-modul${gewaehlt ? ' ist-gewaehlt' : ''}${T.hoch ? ' ist-hoch' : ''}`, 'data-index': i },
      el('button', { type: 'button', class: 'kp-modul__name', 'aria-pressed': String(gewaehlt), onclick: () => waehlen(lauf, i, true) },
        el('b', { text: String(i + 1) }), el('span', {}, el('strong', { text: TEXTE.typen[x.typ] }), el('small', { text: TEXTE.nutzen[x.typ] }))),
      breite,
      knopf(TEXTE.links, '←', () => { [reihe[i - 1], reihe[i]] = [reihe[i], reihe[i - 1]]; auswahl = { lauf, index: i - 1 }; zeichnen(); }, i === 0),
      knopf(TEXTE.rechts, '→', () => { [reihe[i + 1], reihe[i]] = [reihe[i], reihe[i + 1]]; auswahl = { lauf, index: i + 1 }; zeichnen(); }, i === n - 1),
      knopf(TEXTE.entfernen, '×', () => { reihe.splice(i, 1); auswahl = null; zeichnen(); }));
  }

  function waehlen(lauf, index, vonListe) {
    aktiverLauf = lauf; auswahl = { lauf, index };
    zeichnen();
    if (!vonListe) panel.querySelector(`.kp-module [data-index="${index}"]`)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }

  function zeichnen() {
    if (plan.form !== 'l' && aktiverLauf === 'b') aktiverLauf = 'a';
    if (plan.form !== 'insel' && aktiverLauf === 'insel') aktiverLauf = 'a';
    const laeufeJetzt = laeufe(plan).map(([k]) => k);
    const reihe = plan.reihen[aktiverLauf];
    const neuTyp = el('select', { 'aria-label': TEXTE.hinzu, id: 'kp-neu' },
      ...Object.keys(TYPEN).filter((t) => t !== 'eck' || (plan.form === 'l' && aktiverLauf === 'a')).filter((t) => aktiverLauf !== 'insel' || !TYPEN[t].hoch)
        .map((t) => el('option', { value: t, text: TEXTE.typen[t] })));
    const hinweise = pruefen(plan);
    // Kopf (Form, Maße) nur neu bauen, wenn sich die Form ändert -- sonst
    // verlöre der Schieber mitten im Ziehen den Finger.
    if (kopf.dataset.form !== plan.form) {
      kopf.dataset.form = plan.form;
      kopf.replaceChildren(
        gruppe(TEXTE.form, 'kp-l-form', chips('kp-l-form', ['zeile', 'l', 'insel'].map((k) => [k, TEXTE.formen[k], null, FORM_SVG[k]]), plan.form, (k) => { plan.form = k; automatisch(plan); auswahl = null; zeichnen(); })),
        gruppe(TEXTE.masse, 'kp-l-masse', ...laeufeJetzt.map(regler),
          el('button', { type: 'button', class: 'knopf knopf--leer kp-auto', onclick: () => { automatisch(plan); auswahl = null; zeichnen(); } }, TEXTE.auto),
          el('p', { class: 'kp-klein', text: TEXTE.autoText })));
    }
    rumpf.replaceChildren(
      gruppe(TEXTE.schraenke, 'kp-l-schr',
        laeufeJetzt.length > 1 ? el('div', { class: 'chips', role: 'tablist', 'aria-labelledby': 'kp-l-schr' }, ...laeufeJetzt.map((k) => el('button', { type: 'button', role: 'tab', 'aria-selected': String(k === aktiverLauf), 'aria-pressed': String(k === aktiverLauf), onclick: () => { aktiverLauf = k; auswahl = null; zeichnen(); }, text: `${TEXTE.waende[k]} · ${summe(plan.reihen[k])} cm` }))) : null,
        reihe.length ? el('ol', { class: 'kp-module' }, ...reihe.map((x, i) => modulZeile(aktiverLauf, x, i, reihe.length))) : el('p', { class: 'kp-klein', text: TEXTE.leer }),
        el('div', { class: 'kp-hinzu' }, neuTyp, el('button', { type: 'button', class: 'knopf knopf--leer', onclick: () => { const t = neuTyp.value; const b = TYPEN[t].breiten.includes(60) ? 60 : TYPEN[t].breiten[0]; if (t === 'eck') reihe.unshift(m(t, b)); else reihe.push(m(t, b)); auswahl = { lauf: aktiverLauf, index: t === 'eck' ? 0 : reihe.length - 1 }; zeichnen(); } }, TEXTE.hinzuKnopf))),
      gruppe(TEXTE.fronten, 'kp-l-front', chips('kp-l-front', Object.keys(STIL.front).map((k) => [k, TEXTE.frontNamen[k], STIL.front[k]]), plan.front, (k) => { plan.front = k; zeichnen(); })),
      gruppe(TEXTE.platte, 'kp-l-platte', chips('kp-l-platte', Object.keys(STIL.platte).map((k) => [k, TEXTE.platteNamen[k], STIL.platte[k]]), plan.platte, (k) => { plan.platte = k; zeichnen(); })),
      gruppe(TEXTE.griffe, 'kp-l-griff', chips('kp-l-griff', Object.keys(STIL.griff).map((k) => [k, TEXTE.griffNamen[k], STIL.griff[k]]), plan.griff, (k) => { plan.griff = k; zeichnen(); })),
      gruppe(TEXTE.ober, 'kp-l-ober', el('div', { class: 'chips' }, el('button', { type: 'button', 'aria-pressed': String(plan.oberschraenke), onclick: () => { plan.oberschraenke = !plan.oberschraenke; zeichnen(); }, text: TEXTE.oberAn }))),
      el('div', { class: 'kp-pruefung', role: 'status' }, el('span', { class: 'gruppe__name', text: TEXTE.pruefung }),
        el('ul', {}, ...hinweise.map(([art, t]) => el('li', { class: `kp-h kp-h--${art}` }, t)))),
      el('details', { class: 'kp-stueck' }, el('summary', { text: TEXTE.stueck }), el('ul', {}, ...stueckliste(plan).map((t) => el('li', { text: t })))),
      el('button', { type: 'button', class: 'knopf knopf--leer kunde-knopf', onclick: kundeZeigen }, TEXTE.kunde),
      el('button', { type: 'button', class: 'knopf knopf--leer ar-knopf', onclick: arZeigen }, TEXTE.ar.knopf),
      el('p', { class: 'kp-klein kp-ar-hinweis', 'aria-live': 'polite' }),
      el('div', { class: 'kp-aktion' }, ctaKnopf(), el('button', { type: 'button', class: 'knopf knopf--leer', onclick: teilen }, TEXTE.teilen)),
    );
    ansichtLeiste();
    szeneNachziehen();
  }
  // Beim Ziehen am Schieber höchstens einmal je Bild neu bauen
  let nachziehen = 0;
  function szeneNachziehen() {
    if (arZustand.url) { URL.revokeObjectURL(arZustand.url); arZustand.url = null; }
    if (nachziehen) return;
    nachziehen = requestAnimationFrame(() => {
      nachziehen = 0;
      if (api) { api.aktualisieren(szenePlan()); api.auswahl(auswahl?.lauf, auswahl?.index); }
      else if (fig.classList.contains('ist-grundriss')) planZeigen();
    });
  }

  function ctaKnopf() {
    const u = new URL(sek.dataset.anfrage || '/zugang.php', location.href);
    u.searchParams.set('lang', SPRACHE); u.searchParams.set('demo', 'kueche-planer'); u.searchParams.set('plan', codieren(plan));
    return el('a', { class: 'knopf knopf--voll', href: u.pathname + u.search, onclick: () => zaehlen('cta-kueche') }, TEXTE.cta);
  }
  /* Die geplante Küche im eigenen Raum (A2): Boden und Wände bleiben weg,
     Maßstab 1:1. Jeder Neuaufbau macht die vorbereitete USDZ-Datei alt. */
  const arZustand = {};
  async function arZeigen(e) {
    const knopf = e.currentTarget, hinweis = panel.querySelector('.kp-ar-hinweis');
    const melden = (t) => { if (hinweis) hinweis.textContent = t; };
    if (!api) { melden(TEXTE.ar.laden); starten(); return; }
    zaehlen('ar-kueche');
    const AR = await import(new URL('ar.js', import.meta.url).href);
    await AR.ausloesen(arZustand, knopf, api.arQuelle(), TEXTE.ar, melden);
  }
  // Der Weg des Endkunden (kundenablauf.js): die Planung mit allen Maßen ans Studio
  function kundeZeigen() {
    const zahl = laeufe(plan).reduce((n, [k]) => n + plan.reihen[k].length, 0);
    const masse = laeufe(plan).map(([k]) => `${TEXTE.waende[k]} ${k === 'insel' ? summe(plan.reihen.insel) : plan.waende[k]} cm`).join(' · ');
    zaehlen('kueche-kunde');
    document.dispatchEvent(new CustomEvent('vecom:kunde', { detail: {
      titel: TEXTE.kTitel, zeilen: [`${TEXTE.formen[plan.form]} · ${masse}`, `${TEXTE.kSchraenke(zahl)} · ${TEXTE.frontNamen[plan.front]} · ${TEXTE.platteNamen[plan.platte]} · ${TEXTE.griffNamen[plan.griff]}`, TEXTE.kTermin],
      termin: { zeiten: ['10:00', '14:00', '17:00'] }, ziel: panel.querySelector('.kp-aktion a')?.getAttribute('href') || '/zugang.php',
    } }));
  }
  async function teilen(e) {
    const u = new URL(location.href); u.search = ''; u.searchParams.set('plan', codieren(plan)); u.hash = 'kuechenplaner';
    try { await navigator.clipboard.writeText(u.href); e.target.textContent = TEXTE.kopiert; setTimeout(() => { e.target.textContent = TEXTE.teilen; }, 2200); }
    catch { prompt(TEXTE.teilen, u.href); }
  }

  // Ansicht-Leiste liegt unter der Bühne, damit sie beim Scrollen der Liste nicht wandert
  const leiste = $('.kp-ansicht', sek);
  function ansichtLeiste() {
    if (!leiste) return;
    leiste.replaceChildren(
      chips('kp-l-ansicht', Object.entries(TEXTE.ansichten).map(([k, t]) => [k, t]), ansicht, (k) => { ansicht = k; api?.ansicht(k === 'oben' ? 'oben' : '3d'); ansichtLeiste(); }),
      el('div', { class: 'chips' },
        el('button', { type: 'button', 'aria-pressed': String(offen), disabled: !api, onclick: () => { offen = !offen; api?.oeffnen(offen); if (offen) zaehlen('kueche-oeffnen'); ansichtLeiste(); }, text: TEXTE.oeffnen }),
        el('button', { type: 'button', 'aria-pressed': String(masse), disabled: !api, onclick: () => { masse = !masse; api?.masse(masse); ansichtLeiste(); }, text: TEXTE.masseZeigen })));
  }

  const szenePlan = () => ({ ...plan, reihen: { a: plan.reihen.a, b: plan.form === 'l' ? plan.reihen.b : [], insel: plan.form === 'insel' ? plan.reihen.insel : [] }, texte: { leinwand: TEXTE.leinwand } });

  function planZeigen() {
    fig.classList.add('ist-grundriss');
    let box = $('.kp-plan-box', fig); if (!box) { box = el('div', { class: 'kp-plan-box' }); fig.append(box); }
    box.innerHTML = grundriss(plan);
    kennung.textContent = TEXTE.kennungPlan;
  }

  function webgl2() { try { const c = document.createElement('canvas'); const g = c.getContext('webgl2'); g?.getExtension('WEBGL_lose_context')?.loseContext(); return !!g; } catch { return false; } }

  async function starten() {
    if (api || laden) return laden;
    if (!webgl2()) { planZeigen(); $('.kp-hinweis', sek)?.replaceChildren(TEXTE.keinWebgl); return null; }
    fig.classList.add('ist-laedt'); kennung.textContent = TEXTE.laedt;
    laden = (async () => {
      try {
        const mod = await import(new URL(fig.dataset.src, document.baseURI).href);
        const ziel = $('.echtzeit', fig);
        api = await mod.starten(ziel, szenePlan(), (n) => {
          if (n.art === 'modul') waehlen(n.lauf, n.index, false);
          else if (n.art === 'fps') kennung.textContent = TEXTE.kennung(n.fps);
        });
        api.auswahl(auswahl?.lauf, auswahl?.index);
        fig.classList.remove('ist-laedt'); fig.classList.add('ist-echtzeit', 'hat-echtzeit');
        kennung.textContent = TEXTE.kennung(0);
        ansichtLeiste(); zaehlen('kueche-planer');
        return api;
      } catch (err) {
        console.warn('Küchenplaner:', err);
        fig.classList.remove('ist-laedt'); planZeigen(); return null;
      }
    })();
    return laden;
  }

  sek.addEventListener('demo:auf', starten);
  sek.addEventListener('demo:zu', () => api?.anhalten());
  sek.addEventListener('demo:auf', () => api?.fortsetzen());
  // Unsichtbar (Tab im Hintergrund) nicht weiterrechnen
  document.addEventListener('visibilitychange', () => { if (!api) return; if (document.hidden) api.anhalten(); else if (!sek.hidden) api.fortsetzen(); });

  zeichnen();
  if (!sek.hidden) starten();
  if (new URLSearchParams(location.search).has('pruefen')) window.__kueche = { plan, codieren, decodieren, pruefen, automatisch, api: () => api };
}

function zaehlen(e) {
  try { navigator.sendBeacon ? navigator.sendBeacon(`/d.php?e=${e}`) : fetch(`/d.php?e=${e}`, { method: 'POST', keepalive: true }); } catch { /* egal */ }
}
