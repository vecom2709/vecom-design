/* ==========================================================================
   tisch-wahl.js — Holzart und Maße direkt in der Galerie (Wunsch B7).

   Der Tisch in der Galerie ließ sich nur drehen; die 72 gerechneten
   Varianten gab es erst im Konfigurator auf einer eigenen Seite. Wer einen
   Esstisch kauft, fragt aber zuerst: Welches Holz, wie lang, wie viele
   Plätze? Deshalb stehen die Achsen jetzt hier. Ein Klick zeigt das
   gerechnete Standbild genau dieser Variante (Unreal, dieselben Dateien wie
   der Konfigurator); wer am Bild zieht, dreht wieder die Drehvariante.
   ========================================================================== */
const L = (document.documentElement.lang || 'it').slice(0, 2);
const SPRACHE = ['it', 'de', 'en'].includes(L) ? L : 'it';
const LOKAL = { it: 'it-IT', de: 'de-DE', en: 'en-GB' }[SPRACHE];
const $ = (s, r = document) => r.querySelector(s);
const D = '/assets/img/3d/tisch/';
const K = {
  holz: { Eiche: 'EI', Esche: 'ES', Nussbaum: 'NU', Raeuchereiche: 'RE' },
  metall: { Schwarzstahl: 'S', Edelstahl: 'E', Messing: 'M' },
  gestell: { wange: 'W', vierbein: 'V' },
  laenge: { klein: '180', mittel: '200', gross: '240' },
};
const FARBE = { Eiche: '#b98c5a', Esche: '#d8c29a', Nussbaum: '#5c3b26', Raeuchereiche: '#4a3526', Schwarzstahl: '#1c1c1e', Edelstahl: '#c8c9c7', Messing: '#d6ad62' };
const T = {
  de: { holz: 'Holz', laenge: 'Länge', gestell: 'Gestell', metall: 'Metall',
    Eiche: 'Eiche', Esche: 'Esche', Nussbaum: 'Nussbaum', Raeuchereiche: 'Räuchereiche', Schwarzstahl: 'Schwarzstahl', Edelstahl: 'Edelstahl', Messing: 'Messing',
    wange: 'Wange', vierbein: 'Vierbein', klein: '1,80 m', mittel: '2,00 m', gross: '2,40 m',
    daten: (l, b, g) => `${l} × ${b} m · ${g} Plätze`, zurueck: 'Ziehen dreht wieder die gerechnete Drehung',
    kunde: 'So sieht es Ihre Kundschaft: Tisch bestellen', kTitel: 'Esstisch anfragen', kLieferung: 'Lieferung und Aufbau',
    platz: (g) => `${g} Plätze` },
  it: { holz: 'Legno', laenge: 'Lunghezza', gestell: 'Base', metall: 'Metallo',
    Eiche: 'Rovere', Esche: 'Frassino', Nussbaum: 'Noce', Raeuchereiche: 'Rovere affumicato', Schwarzstahl: 'Acciaio nero', Edelstahl: 'Acciaio inox', Messing: 'Ottone',
    wange: 'Fianco pieno', vierbein: 'Quattro gambe', klein: '1,80 m', mittel: '2,00 m', gross: '2,40 m',
    daten: (l, b, g) => `${l} × ${b} m · ${g} posti`, zurueck: 'Trascinando torni alla rotazione calcolata',
    kunde: 'Come lo vede il cliente: ordina il tavolo', kTitel: 'Richiedi il tavolo', kLieferung: 'Consegna e montaggio',
    platz: (g) => `${g} posti` },
  en: { holz: 'Wood', laenge: 'Length', gestell: 'Base', metall: 'Metal',
    Eiche: 'Oak', Esche: 'Ash', Nussbaum: 'Walnut', Raeuchereiche: 'Smoked oak', Schwarzstahl: 'Black steel', Edelstahl: 'Stainless steel', Messing: 'Brass',
    wange: 'Panel base', vierbein: 'Four legs', klein: '1.80 m', mittel: '2.00 m', gross: '2.40 m',
    daten: (l, b, g) => `${l} × ${b} m · seats ${g}`, zurueck: 'Drag to return to the rendered rotation',
    kunde: 'What your customers see: order the table', kTitel: 'Enquire about the table', kLieferung: 'Delivery and assembly',
    platz: (g) => `seats ${g}` },
}[SPRACHE];

const sek = document.getElementById('tisch');
const dreh = document.getElementById('dreh');
const ziel = sek && sek.querySelector('.tisch-wahl');
if (sek && dreh && ziel) {
  const wahl = { holz: 'Eiche', laenge: 'mittel', gestell: 'wange', metall: 'Messing' };
  let katalog = null;
  const still = document.createElement('img');
  still.className = 'tisch-still'; still.alt = ''; still.decoding = 'async'; still.width = 1600; still.height = 1000;
  dreh.append(still);
  const artikel = () => `VD-T-${K.gestell[wahl.gestell]}${K.laenge[wahl.laenge]}-${K.holz[wahl.holz]}${K.metall[wahl.metall]}`;
  const daten = el('p', { class: 'tisch-daten', 'aria-live': 'polite' });

  function el(tag, attr = {}, ...k) { const n = document.createElement(tag); for (const [a, v] of Object.entries(attr)) n.setAttribute(a, v); n.append(...k); return n; }
  function gruppe(achse, werte) {
    const id = `tw-${achse}`;
    const chips = el('div', { class: 'chips chips--umbruch', role: 'group', 'aria-labelledby': id });
    for (const w of werte) {
      const b = el('button', { type: 'button', 'aria-pressed': String(wahl[achse] === w), 'data-wert': w });
      if (FARBE[w]) { const p = el('i', { class: 'farbpunkt', 'aria-hidden': 'true' }); p.style.setProperty('--f', FARBE[w]); b.append(p); }
      b.append(T[w]);
      b.addEventListener('click', () => { wahl[achse] = w; for (const x of chips.children) x.setAttribute('aria-pressed', String(x === b)); zeigen(); zaehlen('tisch-wahl'); });
      chips.append(b);
    }
    return el('div', { class: 'gruppe' }, el('span', { class: 'gruppe__name', id }, T[achse]), chips);
  }
  async function zeigen() {
    const a = artikel();
    // Erst klein (liegt schnell da), dann groß darüber -- wie im Konfigurator
    still.src = `${D}ansicht/klein/${a}.webp`; still.classList.add('ist-an'); dreh.classList.add('hat-still');
    const g = new Image(); g.src = `${D}ansicht/gross/${a}.webp`;
    g.decode().then(() => { if (artikel() === a) still.src = g.src; }).catch(() => {});
    if (!katalog) { try { katalog = await (await fetch(`${D}tisch-katalog.json`)).json(); } catch { katalog = { varianten: [] }; } }
    const v = katalog.varianten.find((x) => x.artikel === a);
    const f = (x) => x.toLocaleString(LOKAL, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    daten.textContent = v ? T.daten(f(v.laenge_m), f(v.breite_m), v.gedecke_je_seite * 2) : '';
  }
  // Wer greift, will drehen: Standbild weg, die Drehung übernimmt
  dreh.addEventListener('pointerdown', () => { still.classList.remove('ist-an'); dreh.classList.remove('hat-still'); }, true);

  const kunde = el('button', { type: 'button', class: 'knopf knopf--leer kunde-knopf' }, T.kunde);
  kunde.addEventListener('click', () => {
    const v = katalog && katalog.varianten.find((x) => x.artikel === artikel());
    zaehlen('tisch-kunde');
    document.dispatchEvent(new CustomEvent('vecom:kunde', { detail: {
      titel: T.kTitel,
      zeilen: [`${T[wahl.holz]} · ${T[wahl.laenge]} · ${T[wahl.gestell]} · ${T[wahl.metall]}`, v ? T.platz(v.gedecke_je_seite * 2) : '', T.kLieferung].filter(Boolean),
      termin: { zeiten: ['09:00', '13:00', '16:00'] }, ziel: `/bedarf.php?lang=${SPRACHE}`,
    } }));
  });
  ziel.append(gruppe('holz', Object.keys(K.holz)), gruppe('laenge', Object.keys(K.laenge)), gruppe('gestell', Object.keys(K.gestell)), gruppe('metall', Object.keys(K.metall)), daten, kunde);
  fetch(`${D}tisch-katalog.json`).then((r) => r.json()).then((k) => { katalog = k; const v = k.varianten.find((x) => x.artikel === artikel()); if (v) daten.textContent = T.daten(v.laenge_m.toLocaleString(LOKAL, { minimumFractionDigits: 2 }), v.breite_m.toLocaleString(LOKAL, { minimumFractionDigits: 2 }), v.gedecke_je_seite * 2); }).catch(() => {});
}

function zaehlen(e) {
  try { navigator.sendBeacon ? navigator.sendBeacon(`/d.php?e=${e}`) : fetch(`/d.php?e=${e}`, { method: 'POST', keepalive: true }); } catch { /* egal */ }
}
