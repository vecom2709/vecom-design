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
    laedt: 'Lade das 3D-Modell …',
    drehen: 'Selbst drehen', zumFoto: 'Zurück zum Foto',
    leinwand: { auto: 'Das Auto in Echtzeit — ziehen oder Pfeiltasten zum Drehen', schuh: 'Der Schuh in Echtzeit — ziehen oder Pfeiltasten zum Drehen' },
    korb: (n, f, g, p) => `${n} · ${f} · Gr. ${g} — ${p}`,
    korbLeer: 'Der Warenkorb ist leer.',
    korbZahl: (n) => `Warenkorb (${n})`,
    groesseFehlt: 'Erst eine Größe wählen.',
    keinWebgl: 'Dieses Gerät zeigt die gerechneten Bilder. Drehen und Zerlegen brauchen WebGL.',
    teile: { tueren: 'Türen', haube: 'Fronthaube', heck: 'Heck mit Rückleuchten', dach: 'Dach', raeder: 'Räder', bremse: 'Bremsscheibe und Sattel', antrieb: 'Antrieb', sitze: 'Sitze' },
  },
  it: {
    foto: 'Calcolato · Blender Cycles · 384 campioni',
    fotoKurz: 'Calcolato · Blender Cycles',
    echtzeit: (f) => `Tempo reale · WebGL 2${f ? ` · ${f} fps` : ''}`,
    zerlegt: 'Tempo reale · scomposto nei suoi pezzi',
    laedt: 'Carico il modello 3D …',
    drehen: 'Giralo tu', zumFoto: 'Torna alla foto',
    leinwand: { auto: 'L’auto in tempo reale — trascina o usa le frecce per girarla', schuh: 'La scarpa in tempo reale — trascina o usa le frecce per girarla' },
    korb: (n, f, g, p) => `${n} · ${f} · tg. ${g} — ${p}`,
    korbLeer: 'Il carrello è vuoto.',
    korbZahl: (n) => `Carrello (${n})`,
    groesseFehlt: 'Scegli prima una taglia.',
    keinWebgl: 'Questo dispositivo mostra le immagini calcolate. Girare e scomporre richiedono WebGL.',
    teile: { tueren: 'Portiere', haube: 'Cofano', heck: 'Coda con fanali', dach: 'Tetto', raeder: 'Ruote', bremse: 'Disco e pinza', antrieb: 'Motore', sitze: 'Sedili' },
  },
  en: {
    foto: 'Rendered · Blender Cycles · 384 samples',
    fotoKurz: 'Rendered · Blender Cycles',
    echtzeit: (f) => `Real time · WebGL 2${f ? ` · ${f} fps` : ''}`,
    zerlegt: 'Real time · taken apart',
    laedt: 'Loading the 3D model …',
    drehen: 'Turn it yourself', zumFoto: 'Back to the photo',
    leinwand: { auto: 'The car in real time — drag or use the arrow keys to turn it', schuh: 'The shoe in real time — drag or use the arrow keys to turn it' },
    korb: (n, f, g, p) => `${n} · ${f} · size ${g} — ${p}`,
    korbLeer: 'The cart is empty.',
    korbZahl: (n) => `Cart (${n})`,
    groesseFehlt: 'Pick a size first.',
    keinWebgl: 'This device shows the rendered images. Turning and taking apart need WebGL.',
    teile: { tueren: 'Doors', haube: 'Bonnet', heck: 'Rear with tail lights', dach: 'Roof', raeder: 'Wheels', bremse: 'Disc and caliper', antrieb: 'Drivetrain', sitze: 'Seats' },
  },
};
const TEXT = TEXTE[SPRACHE];
const $ = (s, w = document) => w.querySelector(s);
const $$ = (s, w = document) => [...w.querySelectorAll(s)];

/* Stand der gerechneten Bilder: JavaScript setzt diese Adressen, nicht
   build.mjs -- wer neu rechnet, zählt hier hoch (der Server gibt Bildern
   dreißig Tage). */
const BILD_STAND = '1';
/* Dasselbe für Modelle, Umgebungen und Kameradaten unter assets/3d/branchen. */
const MODELL_STAND = '1';
const PFAD = '/assets/img/erlebnis/branchen/';

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
function webglDa() {
  try { const c = document.createElement('canvas'); return !!(c.getContext('webgl2')); } catch { return false; }
}

/* ---------------------------------------------------------------- Bühne */
function buehneAnlegen(fig) {
  const modell = fig.dataset.modell;
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
    const sichtbar = liste.filter((a) => a.sichtbar && a.anteil > 0.05 && TEXT.teile[a.schluessel]);
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

  const z = { variante: fig.dataset.variante, gezeigt: fig.dataset.variante, echtzeit: false, p: null, laedt: null, zerlegt: false, licht: false };

  function adresse(v) {
    const klein = fig.clientWidth * (window.devicePixelRatio || 1) <= 900;
    return `${PFAD}${modell}-${v}${klein ? '-800' : ''}.webp?v=${BILD_STAND}`;
  }
  function kennungFoto() { kennung.textContent = fig.clientWidth < 560 ? TEXT.fotoKurz : TEXT.foto; }
  let auftrag = 0;
  async function bildZeigen(v) {
    const nr = ++auftrag;
    const oben = ruheA.classList.contains('ist-oben') ? ruheA : ruheB.classList.contains('ist-oben') ? ruheB : ruheA;
    const unten = oben === ruheA ? ruheB : ruheA;
    const quelle = unten.parentElement && unten.parentElement.tagName === 'PICTURE' ? unten.parentElement.querySelector('source') : null;
    if (quelle) quelle.remove();
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
          bezeichnung: TEXT.leinwand[modell],
          beiBewegung: () => an(),
          beiRuhe: () => aus(),
          beiBild: (dt, fps, schlaeft) => { if (z.echtzeit && !z.zerlegt) kennung.textContent = TEXT.echtzeit(schlaeft ? 0 : fps); },
          beiAnker: ankerZeigen,
        });
        z.p = p; fig.classList.add('hat-echtzeit'); halter.removeAttribute('aria-hidden');
        return p;
      })();
    }
    try { return await z.laedt; }
    catch (f) { console.warn('[branchen] Echtzeit nicht verfügbar:', f); knopf.hidden = true; z.laedt = null; return null; }
    finally { if (!stumm) ladeAnzeige(false); }
  }
  function an() {
    if (!z.p) return;
    z.echtzeit = true; fig.classList.add('ist-echtzeit');
    knopfText.textContent = TEXT.zumFoto;
    kennung.textContent = z.zerlegt ? TEXT.zerlegt : TEXT.echtzeit(0);
    z.p.starten();
  }
  function aus() {
    // Zerlegt und mit Licht gibt es kein Foto -- dann bleibt die Echtzeit.
    if (!z.echtzeit || z.zerlegt || z.licht) return;
    // In der Echtzeit gewählte Variante: erst ihr Foto unterlegen, dann blenden.
    if (z.gezeigt !== z.variante) bildZeigen(z.variante);
    z.echtzeit = false; fig.classList.remove('ist-echtzeit');
    knopfText.textContent = TEXT.drehen; kennungFoto();
    setTimeout(() => { if (!z.echtzeit && z.p) z.p.anhalten(); }, 700);
  }

  knopf.addEventListener('click', async () => {
    if (z.echtzeit) {
      // Zurück zum Foto: zusammensetzen, Licht aus, auf den Standpunkt
      // fahren. Das Foto blendet ein, sobald die Kamera dort steht (beiRuhe).
      z.zerlegt = false; z.licht = false;
      if (z.p) { z.p.zerlegen(false); z.p.licht(false); z.p.heim(); }
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
    if (!z.echtzeit) bildZeigen(v);
    if (z.p) await z.p.variante(nr);
  }
  /* Zerlegt und mit Licht gibt es kein Foto -- solange bleibt die Echtzeit
     stehen. Wird beides wieder zurückgenommen, fährt die Kamera heim, und
     das Foto kommt zurück (beiRuhe -> aus). */
  async function zerlegen(an_) {
    const p = await laden(); if (!p) return;
    z.zerlegt = an_;
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
  function stufeSetzen() { if (z.p) z.p.stufe(stufe()); }
  document.addEventListener('vecom:stufe', stufeSetzen);
  if (!webglDa()) { knopf.hidden = true; }
  kennungFoto();
  return { variante, zerlegen, licht, get z() { return z; } };
}

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

/* ------------------------------------------------------------ Auto */
const autoFig = $('#bd-buehne-auto');
if (autoFig) {
  const b = buehneAnlegen(autoFig);
  const lack = $('#bd-lack');
  lack && lack.addEventListener('click', (e) => {
    const k = e.target.closest('button[data-variante]'); if (!k) return;
    for (const x of $$('button', lack)) x.setAttribute('aria-pressed', String(x === k));
    b.variante(k.dataset.variante, Number(k.dataset.nr));
  });
  const ansicht = $('#bd-ansicht');
  ansicht && ansicht.addEventListener('click', (e) => {
    const k = e.target.closest('button[data-zerlegt]'); if (!k) return;
    for (const x of $$('button', ansicht)) x.setAttribute('aria-pressed', String(x === k));
    b.zerlegen(k.dataset.zerlegt === '1');
  });
  /* Eine Nachtansicht (Studio aus, nur die Leuchten des Autos) stand hier
     bis zum 23.09.2026 als dritter Schalter. Ohne Bloom glühten die
     Tagfahrlichter kaum, das Auto wurde nur braun-dunkel -- ein Schalter,
     der nichts zeigt, kostet mehr Vertrauen, als er bringt. Die Funktion
     bleibt im Modul (licht()), der Schalter ist weg. */
  const licht = null;
  autoFig.addEventListener('bd:zurueck', () => {
    if (ansicht) for (const x of $$('button', ansicht)) x.setAttribute('aria-pressed', String(x.dataset.zerlegt === '0'));
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
  const farben = $('#bd-farbe'); const groessen = $('#bd-groesse');
  const korbKnopf = $('#bd-in-korb'); const korbListe = $('#bd-korb-liste'); const korbZahl = $('#bd-korb-zahl');
  const meldung = $('#bd-korb-meldung');
  const korb = [];
  farben && farben.addEventListener('click', (e) => {
    const k = e.target.closest('button[data-variante]'); if (!k) return;
    for (const x of $$('button', farben)) x.setAttribute('aria-pressed', String(x === k));
    b.variante(k.dataset.variante, Number(k.dataset.nr));
  });
  groessen && groessen.addEventListener('click', (e) => {
    const k = e.target.closest('button[data-groesse]'); if (!k) return;
    for (const x of $$('button', groessen)) x.setAttribute('aria-pressed', String(x === k));
    if (meldung) meldung.textContent = '';
  });
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
    korb.push({ name: korbKnopf.dataset.name, farbe: f ? f.textContent.trim() : '', groesse: g.dataset.groesse, preis: korbKnopf.dataset.preis });
    korbZeigen();
    const kasten = $('#bd-korb');
    if (kasten) { kasten.classList.remove('ist-neu'); void kasten.offsetWidth; kasten.classList.add('ist-neu'); }
    if (meldung) meldung.textContent = '';
  });
  korbZeigen();
}
