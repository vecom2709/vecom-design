/* ==========================================================================
   haarfarben.js — Demo „Friseur & Salon": Wunschfarbe von allen Seiten.

   WARUM
   Uwe am 23.09.2026: statt des Friseurstuhls, der sich dreht, etwas, das
   die Kundschaft eines Salons anspricht. Wer eine neue Haarfarbe will,
   fragt sich: Wie sieht das von hinten aus, von der Seite, im Licht? Genau
   das zeigt diese Bühne -- und führt direkt zum Termin mit Wunschfarbe.

   WIE
   Je Farbe 16 Bilder aus Blender Cycles (echte Haarkurven, physikalischer
   Haarshader), gerechnet auf Uwes Rechner. Gezeichnet wird auf ein Canvas
   mit Überblendung zwischen Nachbarbildern, wie beim Tisch. Beim Farbwechsel
   blendet dieselbe Ansicht in der neuen Farbe über -- man sieht die Farbe
   wechseln, nicht das Bild. Kein WebGL nötig; läuft auf jedem Telefon.
   ========================================================================== */
const L = (document.documentElement.lang || 'it').slice(0, 2);
const SPRACHE = ['it', 'de', 'en'].includes(L) ? L : 'it';
const $ = (s, r = document) => r.querySelector(s);
const BEWEGUNG_AUS = matchMedia('(prefers-reduced-motion: reduce)').matches;

const N = 16;
const STAND = '1';   // Bildstand: wer neu rechnet, zählt hoch
const BASIS = new URL('../../img/erlebnis/haar/', import.meta.url).href;
const adresse = (farbe, g, i) => `${BASIS}${farbe}/${g}/dreh-${String(i).padStart(2, '0')}.webp?s=${STAND}`;

const FARBEN = [
  ['kastanie', '#4a2a1a'], ['schwarz', '#161210'], ['kupfer', '#8f3f1c'],
  ['balayage', '#8a6038'], ['aschblond', '#a8997f'], ['rosegold', '#c08276'],
];
const TEXTE = {
  de: {
    namen: { schwarz: 'Naturschwarz', kastanie: 'Kastanie', kupfer: 'Kupfer', balayage: 'Karamell-Balayage', aschblond: 'Aschblond', rosegold: 'Rosé-Gold' },
    info: {
      schwarz: 'Tiefes Schwarz mit Glanz — wirkt edel und macht jede Frisur klar.',
      kastanie: 'Warmes Braun mit rotem Schimmer im Licht — natürlich und pflegeleicht.',
      kupfer: 'Leuchtendes Kupfer — in der Sonne fast golden, im Schatten satt.',
      balayage: 'Dunkler Ansatz, helle Längen — frei gemalt, wächst weich heraus.',
      aschblond: 'Kühles Blond ohne Gelbstich — wird mit einem Glossing gepflegt.',
      rosegold: 'Blond mit rosé Schimmer — der Hingucker, am schönsten mit Farbpflege.',
    },
    ziehen: 'Ziehen dreht den Kopf', kennung: 'Gerechnet · Blender Cycles · echte Haarsträhnen',
    laedt: 'Lade die Ansichten …', kTitel: 'Termin mit Wunschfarbe', kLeistung: 'Färben, Pflege, Föhnen', kFarbe: (f) => `Wunschfarbe: ${f}`,
  },
  it: {
    namen: { schwarz: 'Nero naturale', kastanie: 'Castano', kupfer: 'Ramato', balayage: 'Balayage caramello', aschblond: 'Biondo cenere', rosegold: 'Oro rosa' },
    info: {
      schwarz: 'Nero profondo e lucido — elegante, rende netta ogni acconciatura.',
      kastanie: 'Castano caldo con riflessi rossi alla luce — naturale e facile da curare.',
      kupfer: 'Ramato luminoso — al sole quasi dorato, all’ombra pieno.',
      balayage: 'Radice scura, lunghezze chiare — dipinto a mano, cresce senza stacchi.',
      aschblond: 'Biondo freddo senza giallo — si mantiene con un gloss.',
      rosegold: 'Biondo con riflesso rosa — il colpo d’occhio, più bello con la cura del colore.',
    },
    ziehen: 'Trascina per girare la testa', kennung: 'Calcolato · Blender Cycles · ciocche vere',
    laedt: 'Carico le viste …', kTitel: 'Appuntamento con il colore desiderato', kLeistung: 'Colore, trattamento, piega', kFarbe: (f) => `Colore desiderato: ${f}`,
  },
  en: {
    namen: { schwarz: 'Natural black', kastanie: 'Chestnut', kupfer: 'Copper', balayage: 'Caramel balayage', aschblond: 'Ash blonde', rosegold: 'Rose gold' },
    info: {
      schwarz: 'Deep, glossy black — elegant, makes every cut look crisp.',
      kastanie: 'Warm brown with a red glint in the light — natural and easy to care for.',
      kupfer: 'Bright copper — almost golden in the sun, rich in the shade.',
      balayage: 'Dark roots, light lengths — hand-painted, grows out softly.',
      aschblond: 'Cool blonde without brassiness — kept fresh with a gloss.',
      rosegold: 'Blonde with a rosy shimmer — the eye-catcher, best with colour care.',
    },
    ziehen: 'Drag to turn the head', kennung: 'Rendered · Blender Cycles · real hair strands',
    laedt: 'Loading the views …', kTitel: 'Appointment with your chosen colour', kLeistung: 'Colour, treatment, blow-dry', kFarbe: (f) => `Chosen colour: ${f}`,
  },
}[SPRACHE];

const sek = document.getElementById('haarfarben');
if (sek) {
  const buehne = $('.hf-buehne', sek), bild = $('.hf-bild', sek), wahl = $('.hf-farben', sek);
  const info = $('.hf-info', sek), cta = $('.hf-cta', sek), kennung = $('.kennung__text', sek);
  const leinwand = document.createElement('canvas'); leinwand.className = 'hf-leinwand'; leinwand.setAttribute('aria-hidden', 'true');
  const bildHuelle = bild.closest('picture') || bild; buehne.insertBefore(leinwand, bildHuelle.nextSibling);
  const ctx = leinwand.getContext('2d');
  const saetze = new Map();          // farbe -> { klein: [..], gross: [..] }
  let farbe = 'kastanie', satz = null, alt = null, altAlpha = 0;
  let pos = (N - 1) / 2, ziel = pos, ziehen = null, raf = 0, letzt = 0, gestartet = false;

  async function laden(f, g) {
    const liste = await Promise.all(Array.from({ length: N }, async (_, i) => {
      const b = new Image(); b.decoding = 'async'; b.src = adresse(f, g, i);
      try { await b.decode(); return b; } catch { return null; }
    }));
    return liste.every(Boolean) ? liste : null;
  }
  async function satzHolen(f) {
    if (!saetze.has(f)) saetze.set(f, {});
    const s = saetze.get(f);
    if (!s.klein) s.klein = await laden(f, 'klein');
    // Groß nachladen, aber nie gemischt übernehmen (wie beim Tisch)
    if (!s.grossLaeuft) { s.grossLaeuft = true; laden(f, 'gross').then((g) => { if (g) { s.gross = g; if (f === farbe) { satz = g; anstossen(true); } } }); }
    return s.gross || s.klein;
  }

  function groesse() {
    const r = Math.min(2, window.devicePixelRatio || 1);
    const w = Math.round(buehne.clientWidth * r), h = Math.round(buehne.clientHeight * r);
    if (leinwand.width !== w || leinwand.height !== h) { leinwand.width = w; leinwand.height = h; }
  }
  function malen(b, a) {
    const W = leinwand.width, H = leinwand.height, s = Math.max(W / b.naturalWidth, H / b.naturalHeight);
    const w = b.naturalWidth * s, h = b.naturalHeight * s;
    ctx.globalAlpha = a; ctx.drawImage(b, (W - w) / 2, (H - h) / 2, w, h);
  }
  function stellung(s, a) {
    const i0 = Math.floor(pos), f = pos - i0;
    malen(s[i0], a);
    if (f > 0.001 && s[i0 + 1]) malen(s[i0 + 1], a * f);
  }
  function zeichnen() {
    if (!satz) return;
    groesse();
    ctx.globalAlpha = 1; ctx.clearRect(0, 0, leinwand.width, leinwand.height);
    stellung(satz, 1);
    if (alt && altAlpha > 0.001) stellung(alt, altAlpha);   // alte Farbe blendet aus
    ctx.globalAlpha = 1;
    buehne.setAttribute('aria-valuenow', String(Math.round(pos)));
  }
  function schleife(t) {
    const dt = letzt ? Math.min(0.05, (t - letzt) / 1000) : 1 / 60; letzt = t;
    if (ziehen) pos = ziel; else pos += (ziel - pos) * (BEWEGUNG_AUS ? 1 : 1 - Math.exp(-dt * 16));
    if (Math.abs(ziel - pos) < 0.002) pos = ziel;
    if (alt) { altAlpha = BEWEGUNG_AUS ? 0 : Math.max(0, altAlpha - dt * 2.2); if (!altAlpha) alt = null; }
    zeichnen();
    if (ziehen || pos !== ziel || alt) raf = requestAnimationFrame(schleife);
    else { raf = 0; letzt = 0; pos = ziel = Math.round(ziel); zeichnen(); }
  }
  function anstossen(sofort) { if (sofort && !raf) zeichnen(); if (!raf && satz) raf = requestAnimationFrame(schleife); }

  async function farbeWaehlen(f, nutzer) {
    if (nutzer) zaehlen(`haar-${f}`);
    const vorher = satz; farbe = f;
    for (const k of wahl.querySelectorAll('button')) k.setAttribute('aria-pressed', String(k.dataset.farbe === f));
    info.textContent = TEXTE.info[f];
    const u = new URL(sek.dataset.anfrage || '/bedarf.php', location.href);
    u.searchParams.set('lang', SPRACHE); u.searchParams.set('demo', `haar-${f}`);
    cta.href = u.pathname + u.search;
    buehne.classList.add('ist-laedt'); kennung.textContent = TEXTE.laedt;
    const s = await satzHolen(f);
    if (farbe !== f) return;              // inzwischen andere Farbe gewählt
    buehne.classList.remove('ist-laedt'); kennung.textContent = TEXTE.kennung;
    if (!s) return;
    if (vorher && vorher !== s) { alt = vorher; altAlpha = 1; }
    satz = s; leinwand.classList.add('ist-an'); anstossen(true);
  }

  // Der Weg der Kundin: Termin mit genau dieser Farbe (kundenablauf.js)
  $('.hf-kunde', sek)?.addEventListener('click', () => {
    zaehlen('haar-kunde');
    document.dispatchEvent(new CustomEvent('vecom:kunde', { detail: {
      titel: TEXTE.kTitel, zeilen: [TEXTE.kFarbe(TEXTE.namen[farbe]), TEXTE.kLeistung],
      termin: { zeiten: ['09:00', '10:30', '14:00', '16:30'] }, ziel: cta.getAttribute('href'),
    } }));
  });
  wahl.replaceChildren(...FARBEN.map(([f, c]) => {
    const k = document.createElement('button'); k.type = 'button'; k.dataset.farbe = f; k.setAttribute('aria-pressed', String(f === farbe));
    const p = document.createElement('i'); p.className = 'farbpunkt'; p.setAttribute('aria-hidden', 'true'); p.style.setProperty('--f', c);
    k.append(p, TEXTE.namen[f]); return k;
  }));
  // Nur Farben anbieten, deren Bilder schon gerechnet sind (farben.json
  // schreibt tools/haar-web.py) -- ein Chip ohne Bilder lädt ins Leere.
  fetch(`${BASIS}farben.json?s=${STAND}`).then((r) => r.json()).then((j) => {
    const da = new Set(j.farben || []);
    if (da.size) for (const k of wahl.querySelectorAll('button')) k.hidden = !da.has(k.dataset.farbe);
  }).catch(() => {});
  wahl.addEventListener('click', (e) => { const k = e.target.closest('button[data-farbe]'); if (k && k.dataset.farbe !== farbe) farbeWaehlen(k.dataset.farbe, true); });
  // Die übrigen Farben leise vorladen, sobald jemand wählt -- klein zuerst
  wahl.addEventListener('pointerenter', () => { for (const [f] of FARBEN) if (!saetze.has(f)) satzHolen(f); }, { once: true });

  buehne.addEventListener('pointerdown', (e) => {
    if (!satz) return;
    ziehen = { x: e.clientX, p: pos }; zaehlen('haar-drehen');
    try { buehne.setPointerCapture(e.pointerId); } catch { /* ohne Zeigerfang */ }
    buehne.classList.add('ist-benutzt'); anstossen();
  });
  buehne.addEventListener('pointermove', (e) => {
    if (!ziehen) return;
    const schritt = Math.max(8, buehne.clientWidth / 28);
    ziel = Math.min(N - 1, Math.max(0, ziehen.p - (e.clientX - ziehen.x) / schritt)); anstossen();
  });
  const los = () => { if (!ziehen) return; ziehen = null; ziel = Math.round(pos); anstossen(); };
  buehne.addEventListener('pointerup', los); buehne.addEventListener('pointercancel', los);
  buehne.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') ziel = Math.max(0, Math.round(ziel) - 1); else if (e.key === 'ArrowRight') ziel = Math.min(N - 1, Math.round(ziel) + 1); else return;
    e.preventDefault(); anstossen();
  });
  new ResizeObserver(() => zeichnen()).observe(buehne);

  const starten = () => { if (gestartet) return; gestartet = true; farbeWaehlen(farbe, false); };
  sek.addEventListener('demo:auf', starten);
  if (!sek.hidden) starten();
  info.textContent = TEXTE.info[farbe];
}

function zaehlen(e) {
  try { navigator.sendBeacon ? navigator.sendBeacon(`/d.php?e=${e}`) : fetch(`/d.php?e=${e}`, { method: 'POST', keepalive: true }); } catch { /* egal */ }
}
