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
    zerlegt: 'Echtzeit · zerlegt in seine Teile',
    innen: 'Echtzeit · Fahrerplatz', innenFoto: 'Gerechnet · Blender Cycles · Fahrerplatz',
    laedt: 'Lade das 3D-Modell …',
    drehen: 'Selbst drehen', zumFoto: 'Zurück zum Foto',
    leinwand: { auto: 'Das Auto in Echtzeit — ziehen oder Pfeiltasten zum Drehen', schuh: 'Der Schuh in Echtzeit — ziehen oder Pfeiltasten zum Drehen' },
    korb: (n, f, g, p) => `${n} · ${f} · Gr. ${g} — ${p}`,
    korbLeer: 'Der Warenkorb ist leer.',
    korbZahl: (n) => `Warenkorb (${n})`,
    groesseFehlt: 'Erst eine Größe wählen.',
    keinWebgl: 'Dieses Gerät zeigt die gerechneten Bilder. Drehen und Zerlegen brauchen WebGL.',
    teile: { tueren: 'Türen', haube: 'Fronthaube', heck: 'Heck mit Rückleuchten', dach: 'Dach', raeder: 'Räder', bremse: 'Bremsscheibe und Sattel', antrieb: 'Antrieb', fahrwerk: 'Fahrwerk', sitze: 'Sitze', obermaterial: 'Obermaterial aus Strick', zwischensohle: 'Zwischensohle aus Schaum', schnuerung: 'Schnürung', himmel: 'Dachhimmel', lenkrad: 'Lenkrad', cockpit: 'Armaturentafel', zylinderkopf: 'Zylinderkopf', turbo: 'Turbolader', getriebe: 'Getriebe', kuehler: 'Kühler', abgas: 'Abgasanlage', rohbau: 'Rohbau, grundiert' },
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
    zerlegt: 'Tempo reale · scomposto nei suoi pezzi',
    innen: 'Tempo reale · posto guida', innenFoto: 'Calcolato · Blender Cycles · posto guida',
    laedt: 'Carico il modello 3D …',
    drehen: 'Giralo tu', zumFoto: 'Torna alla foto',
    leinwand: { auto: 'L’auto in tempo reale — trascina o usa le frecce per girarla', schuh: 'La scarpa in tempo reale — trascina o usa le frecce per girarla' },
    korb: (n, f, g, p) => `${n} · ${f} · tg. ${g} — ${p}`,
    korbLeer: 'Il carrello è vuoto.',
    korbZahl: (n) => `Carrello (${n})`,
    groesseFehlt: 'Scegli prima una taglia.',
    keinWebgl: 'Questo dispositivo mostra le immagini calcolate. Girare e scomporre richiedono WebGL.',
    teile: { tueren: 'Portiere', haube: 'Cofano', heck: 'Coda con fanali', dach: 'Tetto', raeder: 'Ruote', bremse: 'Disco e pinza', antrieb: 'Motore', fahrwerk: 'Sospensioni', sitze: 'Sedili', obermaterial: 'Tomaia in maglia', zwischensohle: 'Intersuola in schiuma', schnuerung: 'Allacciatura', himmel: 'Cielo', lenkrad: 'Volante', cockpit: 'Plancia', zylinderkopf: 'Testata', turbo: 'Turbocompressore', getriebe: 'Cambio', kuehler: 'Radiatore', abgas: 'Scarico', rohbau: 'Scocca con fondo' },
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
    zerlegt: 'Real time · taken apart',
    innen: 'Real time · driver’s seat', innenFoto: 'Rendered · Blender Cycles · driver’s seat',
    laedt: 'Loading the 3D model …',
    drehen: 'Turn it yourself', zumFoto: 'Back to the photo',
    leinwand: { auto: 'The car in real time — drag or use the arrow keys to turn it', schuh: 'The shoe in real time — drag or use the arrow keys to turn it' },
    korb: (n, f, g, p) => `${n} · ${f} · size ${g} — ${p}`,
    korbLeer: 'The cart is empty.',
    korbZahl: (n) => `Cart (${n})`,
    groesseFehlt: 'Pick a size first.',
    keinWebgl: 'This device shows the rendered images. Turning and taking apart need WebGL.',
    teile: { tueren: 'Doors', haube: 'Bonnet', heck: 'Rear with tail lights', dach: 'Roof', raeder: 'Wheels', bremse: 'Disc and caliper', antrieb: 'Drivetrain', fahrwerk: 'Suspension', sitze: 'Seats', obermaterial: 'Knit upper', zwischensohle: 'Foam midsole', schnuerung: 'Laces', himmel: 'Headliner', lenkrad: 'Steering wheel', cockpit: 'Dashboard', zylinderkopf: 'Cylinder head', turbo: 'Turbocharger', getriebe: 'Gearbox', kuehler: 'Radiator', abgas: 'Exhaust', rohbau: 'Body shell, primed' },
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
const $ = (s, w = document) => w.querySelector(s);
const $$ = (s, w = document) => [...w.querySelectorAll(s)];

/* Stand der gerechneten Bilder: JavaScript setzt diese Adressen, nicht
   build.mjs -- wer neu rechnet, zählt hier hoch (der Server gibt Bildern
   dreißig Tage). */
const BILD_STAND = '3';
/* Dasselbe für Modelle, Umgebungen und Kameradaten unter assets/3d/branchen. */
const MODELL_STAND = '4';
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
    kennung.textContent = z.zerlegt ? TEXT.zerlegt : TEXT.echtzeit(0);
    z.p.starten();
  }
  /* Foto zur aktuellen Ansicht: aussen der Lack, innen die Ausstattung
     (Innenraumfoto vom Fahrerplatz, dieselbe Kamera wie das Ziel der
     Kamerafahrt). */
  function fotoSchluessel() { return z.innen && z.ausstattung ? `innen-${z.ausstattung}` : z.variante; }
  function aus(ansicht) {
    // Zerlegt und mit Licht gibt es kein Foto -- dann bleibt die Echtzeit.
    if (!z.echtzeit || z.zerlegt || z.licht || z.details) return;
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
      z.zerlegt = false; z.licht = false; z.details = false;
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
    z.p = null; z.laedt = null; z.zerlegt = false; z.licht = false; z.details = false; z.echtzeit = false; z.innen = false;
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
  return { variante, ausstattung, innenraum, zerlegen, licht, details, modellWechseln, ruhen, get z() { return z; }, get modell() { return modell; } };
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
