/* ==========================================================================
   VECOM Esstisch — Konfigurator aus gerechneten Bildern

   DER UNTERSCHIED ZUR ERSTEN VORSCHAU
   Die three.js-Vorschau rechnet den Tisch im Browser. Das ist beeindruckend,
   solange der Besucher eine Grafikkarte hat -- und auf einem drei Jahre
   alten Telefon ist es eine Diashow. Hier ist es umgekehrt: Gerechnet hat
   Unreal, der Browser zeigt nur noch Bilder. Damit sieht jeder Besucher
   dasselbe, unabhaengig von seiner Rechenleistung, und ein Variantenwechsel
   kostet 16 KB statt eines Neuaufbaus der Szene.

   ZWEI FASSUNGEN JE BILD, UND ZWAR ABSICHTLICH
   Die kleine (480 px, 3-4 KB) liegt nach dem ersten Laden komplett im
   Zwischenspeicher -- alle 144 zusammen 500 KB. Sie erscheint beim Klick
   SOFORT. Die grosse blendet darueber, sobald sie da ist. Ohne das haette
   jeder Klick eine Lücke, und eine Lücke liest der Besucher als "langsam",
   auch wenn sie nur 200 Millisekunden dauert.

   DIE DRITTE ANSICHT: DREHEN
   36 Bilder, je zehn Grad. Damit faellt der Satz "der Besucher kann den
   Tisch nicht drehen" weg, ohne dass eine Echtzeitszene noetig wird --
   dieselben 16 KB je Bild, dieselbe Unabhaengigkeit von der Grafikkarte.

   Die Drehung gibt es fuer EINE Variante. Das ist eine Rechnung, keine
   Nachlaessigkeit: Ein Umlauf kostet acht Minuten Pfadverfolgung; alle 72
   waeren neun Stunden. Deshalb schaltet die Ansicht auf die gerechnete
   Variante um und sagt das auch. Wer danach eine Achse anfasst, bekommt
   sein Standbild -- keine Bedienung, die ins Leere greift.
   ========================================================================== */

/* Auf der Seite liegen die Bilder unter assets/, nicht neben der Datei:
   3d-produktion/ ist per .gitignore aussen vor, dort ist die Werkstatt.
   Was ausgeliefert wird, steht in assets/img/3d/. */
const D = '/assets/img/3d/tisch/';
const KUERZEL = {
  holz:   { Eiche: 'EI', Esche: 'ES', Nussbaum: 'NU', Raeuchereiche: 'RE' },
  metall: { Schwarzstahl: 'S', Edelstahl: 'E', Messing: 'M' },
  gestell:{ wange: 'W', vierbein: 'V' },
  laenge: { klein: '180', mittel: '200', gross: '240' },
};
/* ---------------------------------------------------------------- Sprache
   Die Seite gibt es dreisprachig. Die Achsenwerte im Katalog bleiben aber
   deutsche Schluessel (Eiche, wange, gross): Sie sind Daten und stecken in
   den Artikelnummern und Dateinamen. Uebersetzt wird nur, was der Besucher
   liest.

   Woher die Sprache kommt: aus <html lang>, das build.mjs je Fassung setzt.
   Damit braucht diese Datei keine eigene Weiche, keinen zweiten Build und
   keine dritte Kopie -- eine Datei bedient alle drei Seiten. */
const SPRACHE = ['de', 'it', 'en'].includes((document.documentElement.lang || '').slice(0, 2))
  ? document.documentElement.lang.slice(0, 2)
  : 'de';

const WORTE = {
  de: {
    Raeuchereiche: 'Räuchereiche', wange: 'Wange', vierbein: 'Vierbein',
    klein: '1,80 m', mittel: '2,00 m', gross: '2,40 m',
    masse: ['Länge', 'Breite', 'Oberkante', 'Beinfreiheit', 'Überstand', 'Gedecke je Seite'],
    hinweis: 'Gerechnet als <b>36 Bilder à 10°</b> für <b>Eiche · Messing · Wange · 2,00 m</b>. '
           + 'Ziehen, wischen oder ← → drücken. Jede andere Variante liegt als Standbild vor.',
  },
  it: {
    Eiche: 'Rovere', Esche: 'Frassino', Nussbaum: 'Noce', Raeuchereiche: 'Rovere affumicato',
    Schwarzstahl: 'Acciaio nero', Edelstahl: 'Acciaio inox', Messing: 'Ottone',
    wange: 'Fianco pieno', vierbein: 'Quattro gambe',
    klein: '1,80 m', mittel: '2,00 m', gross: '2,40 m',
    masse: ['Lunghezza', 'Larghezza', 'Altezza piano', 'Spazio gambe', 'Sporgenza', 'Coperti per lato'],
    hinweis: 'Calcolate <b>36 immagini a 10°</b> per <b>rovere · ottone · fianco pieno · 2,00 m</b>. '
           + 'Trascina, scorri o premi ← →. Ogni altra variante è disponibile come fermo immagine.',
  },
  en: {
    Eiche: 'Oak', Esche: 'Ash', Nussbaum: 'Walnut', Raeuchereiche: 'Smoked oak',
    Schwarzstahl: 'Black steel', Edelstahl: 'Stainless steel', Messing: 'Brass',
    wange: 'Panel base', vierbein: 'Four legs',
    klein: '1.80 m', mittel: '2.00 m', gross: '2.40 m',
    masse: ['Length', 'Width', 'Top height', 'Legroom', 'Overhang', 'Settings per side'],
    hinweis: 'Computed as <b>36 images at 10°</b> for <b>oak · brass · panel base · 2.00 m</b>. '
           + 'Drag, swipe or press ← →. Every other variant is there as a still.',
  },
};
const wort = (k) => WORTE[SPRACHE][k] || k;
/* Komma oder Punkt ist keine Kleinigkeit: "2.40 m" liest ein deutscher
   Besucher als zweitausendvierhundert Meter, bis er stutzt. */
const zahl = (v, n) => v.toFixed(n).replace('.', SPRACHE === 'en' ? '.' : ',');

/* Die eine Variante, die als Umlauf vorliegt. */
const DREH_WAHL = { holz: 'Eiche', metall: 'Messing', gestell: 'wange', laenge: 'mittel' };
const DREH_N = 36;

const wahl = { holz: 'Eiche', metall: 'Messing', gestell: 'wange', laenge: 'mittel' };
let satz = 'ansicht';
let katalog = null;

const rahmen    = document.getElementById('rahmen');
const bildKlein = document.getElementById('bildKlein');
const bildGross = document.getElementById('bildGross');
const laedt     = document.getElementById('laedt');
const hinweis   = document.getElementById('hinweis');
const kaesten   = {};

function artikelnummer(w) {
  return 'VD-T-' + KUERZEL.gestell[w.gestell] + KUERZEL.laenge[w.laenge] +
         '-' + KUERZEL.holz[w.holz] + KUERZEL.metall[w.metall];
}

async function laden() {
  katalog = await fetch(D + 'tisch-katalog.json').then((r) => r.json());
  bedienungBauen();
  ansichtenBauen();
  drehungVerdrahten();
  anwenden();
  vorwaermen();
}

function bedienungBauen() {
  for (const [achse, id] of Object.entries({
    holz: 'a-holz', metall: 'a-metall', gestell: 'a-gestell', laenge: 'a-laenge',
  })) {
    const kasten = document.getElementById(id);
    kaesten[achse] = kasten;
    for (const wert of katalog.achsen[achse]) {
      const b = document.createElement('button');
      b.type = 'button';
      b.textContent = wort(wert);
      b.dataset.wert = wert;
      b.setAttribute('aria-pressed', String(wahl[achse] === wert));
      b.addEventListener('click', () => {
        wahl[achse] = wert;
        knoepfeNachziehen();
        // Fuer diese Variante gibt es keinen Umlauf. Statt einen toten
        // Knopf stehen zu lassen, geht die Ansicht zurueck aufs Standbild.
        if (satz === 'drehen') satzSetzen('ansicht');
        else anwenden();
      });
      kasten.appendChild(b);
    }
  }
}

function knoepfeNachziehen() {
  for (const [achse, kasten] of Object.entries(kaesten)) {
    for (const g of kasten.children) {
      g.setAttribute('aria-pressed', String(g.dataset.wert === wahl[achse]));
    }
  }
}

function ansichtenBauen() {
  for (const b of document.getElementById('ansichten').children) {
    b.addEventListener('click', () => satzSetzen(b.dataset.satz));
  }
}

function satzSetzen(neu) {
  satz = neu;
  for (const g of document.getElementById('ansichten').children) {
    g.setAttribute('aria-pressed', String(g.dataset.satz === satz));
  }
  rahmen.classList.toggle('platte', satz === 'platte');
  rahmen.classList.toggle('dreh', satz === 'drehen');
  if (satz === 'drehen') {
    Object.assign(wahl, DREH_WAHL);
    knoepfeNachziehen();
    // Im Drehmodus ist der Rahmen ein Schieberegler, kein Bild. Damit
    // versteht auch ein Vorleseprogramm, was die Pfeiltasten hier tun.
    rahmen.setAttribute('role', 'slider');
    rahmen.setAttribute('aria-label', 'Tisch drehen');
    rahmen.setAttribute('aria-valuemin', '0');
    rahmen.setAttribute('aria-valuemax', String(360 - 360 / DREH_N));
  } else {
    selbstlaufAus();
    rahmen.setAttribute('role', 'img');
    rahmen.setAttribute('aria-label', 'VECOM Esstisch');
    for (const a of ['aria-valuemin', 'aria-valuemax', 'aria-valuenow', 'aria-valuetext']) {
      rahmen.removeAttribute(a);
    }
  }
  anwenden();
}

let laufendeNummer = 0;

function anwenden() {
  const artikel = artikelnummer(wahl);
  schildSetzen(artikel);
  if (satz === 'drehen') { drehAnzeigen(); return; }

  hinweis.hidden = true;
  const meins = ++laufendeNummer;
  bildKlein.src = `${D}${satz}/klein/${artikel}.webp`;
  bildGross.classList.remove('da');
  laedt.classList.add('an');

  const gross = new Image();
  gross.onload = () => {
    // Wer in der Zwischenzeit weitergeklickt hat, bekommt nicht das alte
    // Bild nachgereicht -- sonst springt die Anzeige zurueck.
    if (meins !== laufendeNummer) return;
    bildGross.src = gross.src;
    bildGross.classList.add('da');
    laedt.classList.remove('an');
  };
  gross.src = `${D}${satz}/gross/${artikel}.webp`;
}

function schildSetzen(artikel) {
  const s = katalog.varianten.find((v) => v.artikel === artikel);
  document.getElementById('artikel').textContent = artikel;
  if (!s) return;
  const M = WORTE[SPRACHE].masse;
  document.getElementById('masse').innerHTML = [
    [M[0], zahl(s.laenge_m, 2) + ' m'],
    [M[1], zahl(s.breite_m, 2) + ' m'],
    [M[2], zahl(s.oberkante_m, 3) + ' m'],
    [M[3], zahl(s.beinfreiheit_m, 3) + ' m'],
    [M[4], zahl(s.ueberstand_m, 3) + ' m'],
    [M[5], String(s.gedecke_je_seite)],
  ].map(([k, v]) => `<dt>${k}</dt><dd>${v}</dd>`).join('');
  const w = document.getElementById('warnung');
  w.hidden = s.haelt;
  w.textContent = s.haelt ? '' : s.befunde.join(' · ');
}

/* ------------------------------------------------------------------ Drehen */
let drehIndex = 0;
let drehGeladen = false;
let selbstlauf = null;
let angefasst = false;
let grossZeitgeber = null;

const drehPfad = (groesse, i) =>
  `${D}drehen/${groesse}/dreh-${String(((i % DREH_N) + DREH_N) % DREH_N).padStart(2, '0')}.webp`;

function drehAnzeigen() {
  hinweis.hidden = false;
  hinweis.innerHTML = WORTE[SPRACHE].hinweis;
  bildGross.classList.remove('da');
  bildKlein.src = drehPfad('klein', drehIndex);
  winkelMelden();
  drehVorladen();
  // Auch beim Wiedereintritt in die Ansicht, nicht nur nach dem Vorladen:
  // Wer schon einmal gedreht hat, bekaeme sonst nur die kleine Fassung.
  grossNachladen();
}

function winkelMelden() {
  const grad = Math.round((360 * drehIndex) / DREH_N);
  rahmen.setAttribute('aria-valuenow', String(grad));
  rahmen.setAttribute('aria-valuetext', grad + ' Grad');
}

function drehVorladen() {
  if (drehGeladen) { selbstlaufAn(); return; }
  laedt.classList.add('an');
  let offen = DREH_N;
  for (let i = 0; i < DREH_N; i++) {
    const b = new Image();
    b.onload = b.onerror = () => {
      if (--offen > 0) return;
      drehGeladen = true;
      laedt.classList.remove('an');
      // Erst wenn ALLE 36 im Zwischenspeicher liegen, darf gedreht werden.
      // Sonst holt der erste Zug die Bilder einzeln nach, und genau das
      // fuehlt sich an wie eine hakende Anwendung.
      if (satz === 'drehen') { selbstlaufAn(); grossNachladen(); }
    };
    b.src = drehPfad('klein', i);
  }
}

function indexSetzen(i) {
  const neu = ((i % DREH_N) + DREH_N) % DREH_N;
  if (neu === drehIndex) return;
  drehIndex = neu;
  bildKlein.src = drehPfad('klein', drehIndex);
  bildGross.classList.remove('da');
  winkelMelden();
}

function grossNachladen() {
  clearTimeout(grossZeitgeber);
  // Erst wenn die Hand stillsteht. Waehrend des Ziehens waere jedes grosse
  // Bild verworfen, bevor es da ist -- 36 angefangene Ladungen umsonst.
  grossZeitgeber = setTimeout(() => {
    if (satz !== 'drehen') return;
    const meins = ++laufendeNummer;
    const ziel = drehIndex;
    const gross = new Image();
    gross.onload = () => {
      if (meins !== laufendeNummer || ziel !== drehIndex) return;
      bildGross.src = gross.src;
      bildGross.classList.add('da');
    };
    gross.src = drehPfad('gross', ziel);
  }, 140);
}

function selbstlaufAn() {
  // Eine Umdrehung von allein, damit niemand raten muss, dass hier etwas
  // zu ziehen ist. Danach steht der Tisch still -- eine Seite, auf der
  // dauernd etwas kreiselt, liest sich als Bildschirmschoner.
  if (selbstlauf || angefasst) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  let uebrig = DREH_N;
  selbstlauf = setInterval(() => {
    if (document.hidden) return;
    indexSetzen(drehIndex + 1);
    if (--uebrig <= 0) { selbstlaufAus(); grossNachladen(); }
  }, 85);
}

function selbstlaufAus() {
  if (!selbstlauf) return;
  clearInterval(selbstlauf);
  selbstlauf = null;
}

function drehungVerdrahten() {
  let zug = null;

  rahmen.addEventListener('pointerdown', (e) => {
    if (satz !== 'drehen' || !drehGeladen) return;
    angefasst = true;
    selbstlaufAus();
    // Ohne Fangen springt der Zug ab, sobald der Zeiger den Rahmen
    // verlaesst. Mit try, weil es nicht jeder Zeiger zulaesst -- und ein
    // Fehler hier duerfte nicht den ganzen Zug verschlucken.
    try { rahmen.setPointerCapture(e.pointerId); } catch (_) { /* egal */ }
    rahmen.classList.add('zieht');
    zug = { x: e.clientX, start: drehIndex };
  });

  rahmen.addEventListener('pointermove', (e) => {
    if (!zug) return;
    // Ein Umlauf soll gut eine Bildbreite Weg kosten. Bei 1100 px sind das
    // 38 px je Zehntelgrad-Schritt -- nah genug an dem, was die Hand von
    // einem Drehteller erwartet.
    const proSchritt = Math.max(10, (rahmen.clientWidth * 1.25) / DREH_N);
    const schritte = Math.round((e.clientX - zug.x) / proSchritt);
    // Vorzeichen an den Bildern abgelesen, nicht hergeleitet: Zwischen
    // Bild 00 und Bild 06 wandert die NAHE Wange von links nach rechts
    // durchs Bild. Wer den Tisch nach rechts zieht, will genau das --
    // also steigt der Gierwinkel mit dem Weg nach rechts.
    indexSetzen(zug.start + schritte);
  });

  for (const art of ['pointerup', 'pointercancel']) {
    rahmen.addEventListener(art, (e) => {
      if (!zug) return;
      zug = null;
      rahmen.classList.remove('zieht');
      try { rahmen.releasePointerCapture(e.pointerId); } catch (_) { /* egal */ }
      grossNachladen();
    });
  }

  rahmen.addEventListener('keydown', (e) => {
    if (satz !== 'drehen') return;
    const schritt = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
    if (!schritt) return;
    e.preventDefault();
    angefasst = true;
    selbstlaufAus();
    indexSetzen(drehIndex + schritt);
    grossNachladen();
  });
}

function vorwaermen() {
  // Alle kleinen Fassungen im Hintergrund holen -- zusammen rund 500 KB.
  // Danach ist JEDER Variantenwechsel augenblicklich, auch der erste.
  // Bewusst erst nach dem ersten Bild, damit das Vorwaermen nicht mit dem
  // wichtigsten Bild um die Leitung streitet. Die Drehbilder bleiben
  // aussen vor: Sie kosten noch einmal so viel und werden nur gebraucht,
  // wenn jemand die dritte Ansicht auch anklickt.
  requestIdleCallback ? requestIdleCallback(hol) : setTimeout(hol, 800);
  function hol() {
    for (const v of katalog.varianten) {
      for (const s of ['ansicht', 'platte']) {
        new Image().src = `${D}${s}/klein/${v.artikel}.webp`;
      }
    }
  }
}

laden().catch((e) => {
  document.getElementById('artikel').textContent = 'Fehler: ' + e.message;
  console.error(e);
});
