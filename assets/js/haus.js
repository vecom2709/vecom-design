/* ==========================================================================
   haus.js — Die Villa im Labor.

   DREI FRAGEN, EIN GEBÄUDE

   Ein Bauunternehmer, ein Architekt oder ein Makler versteht nicht aus
   einem Absatz, was „3D im Netz" für ihn heißt. Er versteht es, wenn der
   Plan vor ihm hochwächst und er anschließend durch das Haus geht:

     Grundriss   Der Plan liegt flach — und richtet sich zum Haus auf.
     Bauablauf   Bodenplatte bis Außenanlage, acht Schritte, vor und zurück.
     Begehung    Kamera auf 1,65 m. Durch die Tür, durch die Räume.

   Alles an EINEM Modell: 362 KB, 307 Bauteile, keine zweite Datei je
   Ansicht. Das ist der ganze Punkt des Abschnitts.

   WARUM DIE BAUTEILE IHREN URSPRUNG UNTEN HABEN
   In Blender sitzt der Ursprung jedes Quaders auf seiner Unterkante. Damit
   genügt scale.y von 0 auf 1, und die Wand steigt aus dem Boden — ohne die
   Geometrie anzufassen. Bei einem Ursprung in der Mitte wüchse sie in beide
   Richtungen und das halbe Haus stünde im Keller.

   WARUM KEINE ECHTE KOLLISION
   Die Begehung prüft, ob der nächste Schritt in einem der Raumrechtecke
   oder Türdurchgänge landet. Das kostet nichts, fährt nie durch eine Wand
   und kommt aus derselben Quelle wie der Grundriss — zwei getrennte
   Beschreibungen desselben Hauses laufen sonst irgendwann auseinander.

   AUSBAUSTUFEN
     - gestartet wird erst, wenn der Abschnitt ins Bild kommt
     - ohne WebGL/bei schwacher Grafik bleibt der GEZEICHNETE Grundriss
       stehen — kein Fehlerbild, sondern ein echter Plan mit Maßen, plus die
       gerechneten Ansichten. Die Registerleiste bedient beide Stufen.
     - ?haus=echtzeit oder ?haus=bild erzwingt eine Stufe
   ========================================================================== */
import { supportsWebGL, grafikZuSchwach } from './world/quality.js';

const kasten = document.querySelector('[data-haus]');
const D = '/assets/3d/haus/';

let plan = null;          /* haus-manifest.json */
let station = 'grundriss';
let geladen = 0;
let geschoss = 'eg';

/* Wird von der Echtzeitfassung gefüllt. Solange nur der Plan steht,
   verpuffen die Aufrufe — das ist Absicht: Die Register sollen in beiden
   Stufen dieselben Knöpfe haben. */
let echtzeit = null;

function el(w) { return kasten ? kasten.querySelector(w) : null; }
function alle(w) { return kasten ? [...kasten.querySelectorAll(w)] : []; }
function melden(t) { const m = el('[data-haus-stand]'); if (m) { m.textContent = t; } }
function titel(t) { const n = el('[data-haus-titel]'); if (n) { n.textContent = t; } }

/* Ein Wort aus dem Wörterbuch, das im Markup hinterlegt ist — so bleibt
   jeder sichtbare Text übersetzbar, auch der, den JavaScript setzt. */
function wort(name, ersatz) {
  /* ZUERST AM KASTEN SELBST NACHSEHEN. querySelector durchsucht nur
     Nachfahren -- und genau dort stehen diese Attribute nicht, sondern am
     Kasten. Ohne die erste Zeile griff immer der deutsche Ersatztext, was
     auf der italienischen Fassung niemandem auffällt und auf der deutschen
     wie ein Erfolg aussieht. Gemessen am 17.09.2026: Der Grundriss zeigte
     "kueche" statt "Küche". */
  const attr = 'data-wort-' + name;
  if (kasten && kasten.hasAttribute(attr)) { return kasten.getAttribute(attr) || ersatz; }
  const q = el('[' + attr + ']');
  return (q && q.getAttribute(attr)) || ersatz;
}

function kb(n) { return Math.round(n / 1024) + ' KB'; }

/* ------------------------------------------------------------ Der Plan
   Der Grundriss wird als SVG gezeichnet, nicht als Bild geladen. Drei
   Gründe: er ist in jeder Auflösung scharf, er ist übersetzbar (die
   Raumnamen stehen im Wörterbuch), und er steht auch dann, wenn es kein
   WebGL gibt. Ein Plan, den man erst herunterladen muss, ist kein Plan.  */

/* Raumnamen und Abschnittstitel stehen als EINE Liste im Markup, nicht als
   zwanzig einzelne Attribute: "entree=Entrée|wc=Gäste-WC|…". Ein Schlüssel
   im Wörterbuch je Liste, eine Zeile im Markup — und trotzdem übersetzbar.
   Zwanzig data-i18n-attr-Paare in einer Zeile wären nicht zu pflegen. */
let _liste = {};
function ausListe(attr, schluessel, ersatz) {
  /* Nicht zwischenspeichern, solange die Liste leer ist: Dieses Modul kann
     starten, BEVOR app.js die Attribute gesetzt hat. Ein leerer Cache
     bliebe dann bis zum Neuladen leer -- und der Grundriss zeigte statt
     "Küche" den Schlüssel "kueche". Genau so am 17.09.2026 gemessen. */
  const roh = wort(attr, '');
  if (!_liste[attr] && roh) {
    _liste[attr] = Object.create(null);
    for (const paar of roh.split('|')) {
      const i = paar.indexOf('=');
      if (i > 0) { _liste[attr][paar.slice(0, i).trim()] = paar.slice(i + 1).trim(); }
    }
  }
  return (_liste[attr] && _liste[attr][schluessel]) || ersatz;
}

/* Beim Sprachwechsel ist jede gemerkte Liste falsch. */
document.addEventListener('vecom:sprache', () => {
  _liste = {};
  planZeichnen();
  geschossSetzen(geschoss);
});

function raumName(schluessel) {
  return ausListe('raeume', schluessel, schluessel);
}

function planZeichnen() {
  const flaeche = el('[data-haus-plan]');
  if (!flaeche || !plan) { return; }
  const g = plan.geschosse.find((x) => x.schluessel === geschoss) || plan.geschosse[0];
  const K = plan.koerper[geschoss] || plan.koerper.eg;
  const rand = 1.2;
  const bx = K.x1 - K.x0 + 2 * rand;
  const by = K.y1 - K.y0 + 2 * rand;

  const teile = [];
  teile.push('<rect class="grundriss__grund" x="' + (K.x0) + '" y="' + (K.y0) +
             '" width="' + (K.x1 - K.x0) + '" height="' + (K.y1 - K.y0) + '"/>');

  for (const r of g.raeume) {
    teile.push('<rect class="grundriss__raum" x="' + r.x0 + '" y="' + r.y0 +
               '" width="' + (r.x1 - r.x0) + '" height="' + (r.y1 - r.y0) + '"/>');
  }
  if (geschoss === 'og' && plan.luftraum) {
    const L = plan.luftraum;
    teile.push('<rect class="grundriss__luft" x="' + L.x0 + '" y="' + L.y0 +
               '" width="' + (L.x1 - L.x0) + '" height="' + (L.y1 - L.y0) + '"/>');
  }
  for (const d of plan.durchgaenge) {
    const inGeschoss = Math.abs(d.z - g.z) < 0.5;
    if (!inGeschoss) { continue; }
    teile.push('<rect class="grundriss__tuer" x="' + d.x0 + '" y="' + d.y0 +
               '" width="' + (d.x1 - d.x0) + '" height="' + (d.y1 - d.y0) + '"/>');
  }
  for (const r of g.raeume) {
    const mx = (r.x0 + r.x1) / 2;
    const my = (r.y0 + r.y1) / 2;
    teile.push('<text class="grundriss__name" x="' + mx + '" y="' + (my - 0.18) + '">' +
               raumName(r.schluessel) + '</text>');
    teile.push('<text class="grundriss__mass" x="' + mx + '" y="' + (my + 0.62) + '">' +
               r.flaeche.toFixed(1).replace('.', ',') + ' m²</text>');
  }
  /* Maßkette an der Südkante: eine Zahl, die man nachmessen kann, macht aus
     einer Zeichnung einen Plan. */
  teile.push('<line class="grundriss__kette" x1="' + K.x0 + '" y1="' + (K.y1 + 0.75) +
             '" x2="' + K.x1 + '" y2="' + (K.y1 + 0.75) + '"/>');
  teile.push('<text class="grundriss__kettentext" x="' + ((K.x0 + K.x1) / 2) +
             '" y="' + (K.y1 + 0.52) + '">' +
             (K.x1 - K.x0).toFixed(2).replace('.', ',') + ' m</text>');

  flaeche.setAttribute('viewBox',
    (K.x0 - rand) + ' ' + (K.y0 - rand) + ' ' + bx + ' ' + by);
  flaeche.innerHTML = teile.join('');
}

function geschossTitel() {
  const g = plan && plan.geschosse.find((x) => x.schluessel === geschoss);
  if (g) {
    titel(raumName(geschoss) + ' · ' + g.flaeche.toFixed(1).replace('.', ',') + ' m²');
  }
}

function geschossSetzen(neu) {
  geschoss = neu;
  alle('[data-geschoss]').forEach((b) => {
    b.setAttribute('aria-pressed', String(b.getAttribute('data-geschoss') === neu));
  });
  planZeichnen();
  if (echtzeit) { echtzeit.geschossSetzen(neu); }
  geschossTitel();
}


/* ------------------------------------------------------- Register wechseln */

function stationSetzen(neu) {
  station = neu;
  alle('[data-hstation]').forEach((b) => {
    b.setAttribute('aria-selected', String(b.getAttribute('data-hstation') === neu));
  });
  alle('[data-hpult]').forEach((p) => {
    p.hidden = p.getAttribute('data-hpult') !== neu;
  });
  kasten.setAttribute('data-haus-station', neu);
  if (echtzeit) { echtzeit.stationSetzen(neu); }
  else { bildFassungSetzen(); }
  /* Das Vergleichsregister beginnt IMMER beim Foto. Wer es oeffnet, soll
     zuerst sehen, wie gut es geht -- und dann selbst umschalten. Anders
     herum wirkt die Echtzeitfassung wie der Normalfall und das Foto wie
     eine Zugabe; hier ist es umgekehrt gemeint. */
  if (neu === 'vergleich') {
    standpunktSetzen(standpunkt);
    fotoSchalten(true);
  } else {
    kasten.setAttribute('data-haus-foto', 'aus');
    const bild = el('[data-haus-bild]');
    if (bild && echtzeit) { bild.hidden = true; }
  }
}

/* Der gezeichnete Plan liegt ÜBER der Leinwand und blendet sich aus, während
   das Haus hochwächst. Das ist der ganze Übergang: Bei 0 % sieht man einen
   scharfen Plan mit Maßen, bei 100 % das Gebäude — und dazwischen beides
   übereinander, was das eine als Herkunft des anderen lesbar macht.
   In den anderen zwei Registern ist der Plan weg; dort geht es nicht um ihn. */
function planSchleier(wachstum) {
  const svg = el('[data-haus-plan]');
  if (!svg) { return; }
  if (station !== 'grundriss') { svg.style.opacity = '0'; svg.hidden = true; return; }
  svg.hidden = false;
  const o = Math.max(0, 1 - wachstum * 1.35);
  svg.style.opacity = String(o);
}

/* Die Bildfassung: gerechnete Ansichten plus der gezeichnete Plan. Sie ist
   kein Notbehelf — für jemanden, der nur wissen will, wie das Haus aussieht,
   ist sie schneller und schärfer als die Echtzeitfassung. */
const BILDER = {
  grundriss: null,
  bauablauf: 'haus-garten',
  begehung: 'haus-wohnen',
  vergleich: 'haus-garten',
};

/* Die Fassung im Markup trägt den Versionsstempel, den der Bau vergibt
   (…webp?v=abc123). Wechselt JavaScript das Bild, muss derselbe Stempel
   mit — sonst holt ein wiederkehrender Besucher die alte Datei aus dem
   Zwischenspeicher und sieht ein Haus, das es so nicht mehr gibt. */
let _stempel = null;
function stempel() {
  if (_stempel === null) {
    const bild = el('[data-haus-bild]');
    const roh = bild ? (bild.getAttribute('src') || '') : '';
    const i = roh.indexOf('?');
    _stempel = i < 0 ? '' : roh.slice(i);
  }
  return _stempel;
}

function bildFassungSetzen() {
  const bild = el('[data-haus-bild]');
  const svg = el('[data-haus-plan]');
  const name = station === 'vergleich' ? 'haus-' + standpunkt : BILDER[station];
  if (svg) { svg.hidden = name !== null; }
  if (bild) {
    bild.hidden = name === null;
    if (name) {
      const w = '/assets/img/3d/haus/' + name;
      const v = stempel();
      /* Zwei Stufen aus demselben gerechneten Bild: 1600 px für die Anzeige,
         800 px für schmale Geräte. Das spart dort rund zwei Drittel der
         Bytes, ohne dass jemand einen Unterschied sieht. */
      bild.srcset = w + '-800.webp' + v + ' 800w, ' + w + '.webp' + v + ' 1600w';
      bild.sizes = '(max-width: 900px) 100vw, 1000px';
      bild.src = w + '.webp' + v;
    }
  }
}

/* ------------------------------------------------------ Foto oder Echtzeit

   Das Register, in dem der Besucher sieht, was der Unterschied kostet und
   was er bringt. Dasselbe Haus, einmal in Cycles gerechnet (Minuten je
   Bild auf einer RTX 5070, EIN Standpunkt, 21 bis 41 KB) und einmal in
   Echtzeit (einmal das Modell laden, dann jeder Standpunkt sofort).

   ENTSCHEIDEND: Die Echtzeitkamera springt auf GENAU den Standpunkt, aus
   dem gerechnet wurde. Sonst vergleicht man zwei Ansichten statt zwei
   Verfahren — und das waere ein unehrlicher Vergleich. Die Standpunkte
   stehen im Manifest, direkt aus villa_szene.py exportiert.                */

let standpunkt = 'garten';
let fotoAn = true;

function standpunktSetzen(neu) {
  standpunkt = neu;
  alle('[data-standpunkt]').forEach((b) => {
    b.setAttribute('aria-pressed', String(b.getAttribute('data-standpunkt') === neu));
  });
  if (echtzeit) { echtzeit.standpunktSetzen(neu, fotoAn); }
  if (station === 'vergleich' && fotoAn) { bildFassungSetzen(); }
  const s = plan && plan.fotostandpunkte && plan.fotostandpunkte[standpunkt];
  titel(raumName(standpunkt) + (s ? ' · ' + s.brennweite + ' mm' : ''));
}

function fotoSchalten(an) {
  fotoAn = an;
  kasten.setAttribute('data-haus-foto', an ? 'an' : 'aus');
  const b = el('[data-fotoschalter]');
  if (b) {
    b.setAttribute('aria-pressed', String(an));
    b.textContent = an ? wort('vEchtzeit', 'Echtzeit zeigen')
                       : wort('vFoto', 'Gerechnetes Foto zeigen');
  }
  if (station === 'vergleich') {
    if (an) {
      bildFassungSetzen();
    } else {
      const bild = el('[data-haus-bild]');
      if (bild) { bild.hidden = true; }
    }
  }
  if (echtzeit) { echtzeit.standpunktSetzen(standpunkt, an); }
}

/* ------------------------------------------------------------- Echtzeit */

async function echtzeitStarten(leinwand) {
  const [THREE, { GLTFLoader }] = await Promise.all([
    import('three'),
    import('three/addons/loaders/GLTFLoader.js'),
  ]);

  const renderer = new THREE.WebGLRenderer({
    canvas: leinwand, antialias: true, alpha: false,
    powerPreference: 'high-performance',
  });
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.22;
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;

  const szene = new THREE.Scene();
  const kamera = new THREE.PerspectiveCamera(50, 1, 0.08, 400);

  /* HIMMEL UND UMGEBUNGSLICHT AUS EINER QUELLE.
     Ein Verlauf auf einer Leinwand, einmal als Hintergrund und einmal durch
     den PMREM-Generator als Umgebung. Das ist der Unterschied zwischen
     Produktfoto und Blitzlicht: Die Umgebung trägt das Bild, nicht das
     Führungslicht. Eine echte HDR-Datei wäre besser und kostet 2 MB. */
  const himmelTex = (() => {
    const c = document.createElement('canvas');
    c.width = 16; c.height = 256;
    const g = c.getContext('2d');
    const v = g.createLinearGradient(0, 0, 0, 256);
    v.addColorStop(0.00, '#7ea9d8');
    v.addColorStop(0.42, '#bcd3e8');
    v.addColorStop(0.50, '#e6ecef');
    v.addColorStop(0.52, '#6f7a68');
    v.addColorStop(1.00, '#2d3a26');
    g.fillStyle = v; g.fillRect(0, 0, 16, 256);
    const t = new THREE.CanvasTexture(c);
    t.mapping = THREE.EquirectangularReflectionMapping;
    t.colorSpace = THREE.SRGBColorSpace;
    return t;
  })();
  const pmrem = new THREE.PMREMGenerator(renderer);
  const umgebung = pmrem.fromEquirectangular(himmelTex).texture;
  szene.environment = umgebung;
  szene.background = himmelTex;
  szene.fog = new THREE.Fog(0xbcd3e8, 120, 330);

  /* Sonne: derselbe Stand wie im gerechneten Bild (27° hoch, Azimut 191°) —
     wer beide nebeneinander sieht, soll dasselbe Haus sehen. */
  const sonne = new THREE.DirectionalLight(0xfff0dc, 3.1);
  const hoehe = 27 * Math.PI / 180;
  const az = 191 * Math.PI / 180;
  sonne.position.set(
    40 * Math.cos(hoehe) * Math.sin(az),
    40 * Math.sin(hoehe),
    40 * Math.cos(hoehe) * Math.cos(az));
  sonne.castShadow = true;
  sonne.shadow.mapSize.set(2048, 2048);
  sonne.shadow.camera.near = 1;
  sonne.shadow.camera.far = 120;
  sonne.shadow.camera.left = -28;
  sonne.shadow.camera.right = 28;
  sonne.shadow.camera.top = 28;
  sonne.shadow.camera.bottom = -28;
  sonne.shadow.bias = -0.0006;
  sonne.shadow.normalBias = 0.02;
  szene.add(sonne);
  szene.add(sonne.target);
  sonne.target.position.set(9, 2, -5.5);
  szene.add(new THREE.HemisphereLight(0xcfe0f2, 0x3a4030, 0.72));

  /* -------------------------------------------------------- Modell laden */
  const glb = await new GLTFLoader().loadAsync(D + 'haus.glb');
  const haus = glb.scene;
  szene.add(haus);

  /* Nach Bauabschnitt sortieren. Der Abschnitt steckt im Knotennamen
     (p01_ bis p08_), weil glTF Collections nicht mitnimmt. */
  const abschnitt = new Map();     /* 1..8 -> [Object3D] */
  const wachsen = [];              /* alles, was aus dem Boden steigt */
  haus.traverse((o) => {
    if (!o.isMesh) { return; }
    o.castShadow = true;
    o.receiveShadow = true;
    if (o.material) {
      o.material.envMapIntensity = 1.0;
      if (o.material.transparent) { o.castShadow = false; }
    }
    const m = /^p(\d\d)_/.exec(o.name);
    const n = m ? Number(m[1]) : 9;
    if (!abschnitt.has(n)) { abschnitt.set(n, []); }
    abschnitt.get(n).push(o);
    /* Der Rasen und die Bodenplatte wachsen nicht mit — sie SIND der Plan. */
    if (n >= 2 && n <= 7) { wachsen.push(o); }
  });

  for (const o of wachsen) { o.userData.zielY = o.scale.y; }

  return { THREE, renderer, szene, kamera, haus, abschnitt, wachsen,
           sonne, umgebung, pmrem };
}


/* --------------------------------------------------- Die Welt bedienen */

function echtzeitVerdrahten(w, leinwand) {
  const { THREE, renderer, szene, kamera, abschnitt, wachsen, sonne } = w;

  /* Blender: X rechts, Y in den Garten, Z oben.
     three nach dem Y-oben-Export: X bleibt, Z = -Y, Y = Z.
     Die beiden Umrechner stehen hier einmal, damit sie nicht in jeder
     Funktion neu erfunden werden. */
  const nachDrei = (bx, by, bz) => new THREE.Vector3(bx, bz, -by);
  const nachPlan = (v) => ({ x: v.x, y: -v.z });

  const mitte = plan ? {
    x: (plan.koerper.eg.x0 + plan.koerper.eg.x1) / 2,
    y: (plan.koerper.eg.y0 + plan.koerper.eg.y1) / 2,
  } : { x: 9, y: 5.5 };

  let wachstum = 1;      /* 0 = Grundriss, 1 = fertiges Haus */
  let stufe = 8;         /* sichtbarer Bauabschnitt */
  let lauf = 0;
  let ruhig = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* --- Wachsen ---------------------------------------------------------
     Die Bauteile steigen nicht gleichzeitig, sondern von unten nach oben.
     Der Versatz kommt aus der Höhe des Bauteils selbst: Was tief liegt,
     ist früh fertig. Das liest sich wie ein Bauablauf und nicht wie ein
     Aufblasen. */
  const OBEN = 7.3;
  function wachstumSetzen(t) {
    wachstum = Math.max(0, Math.min(1, t));
    for (const o of wachsen) {
      const basis = o.position.y;
      const start = Math.max(0, Math.min(0.72, basis / OBEN * 0.72));
      const f = Math.max(0, Math.min(1, (wachstum - start) / 0.28));
      o.scale.y = Math.max(0.0001, o.userData.zielY * f);
      o.visible = f > 0.002;
    }
    const a = el('[data-haus-wachstum]');
    if (a) { a.textContent = Math.round(wachstum * 100) + ' %'; }
    planSchleier(wachstum);
  }

  /* --- Bauabschnitte --------------------------------------------------- */
  function stufeSetzen(n) {
    stufe = Math.max(1, Math.min(8, n));
    for (const [nr, liste] of abschnitt) {
      const sichtbar = nr <= stufe;
      for (const o of liste) { o.visible = sichtbar; }
    }
    const b = plan && plan.abschnitte[stufe - 1];
    titel((b ? ausListe('abschnitte', String(stufe), b.titel) : '') + ' · ' + stufe + '/8');
    alle('[data-stufe-wert]').forEach((x) => { x.textContent = String(stufe); });
    const r = el('[data-regler="stufe"]');
    if (r && Number(r.value) !== stufe) { r.value = String(stufe); }
  }

  /* --- Kamerabahnen ----------------------------------------------------
     Drei Register, drei Kamerazustände. Der Wechsel wird geglitten, nicht
     geschnitten: Ein Schnitt zwischen Vogelperspektive und Augenhöhe
     kostet den Besucher jedes Mal die Orientierung. */
  const blick = { pos: new THREE.Vector3(), ziel: new THREE.Vector3() };
  const wunsch = { pos: new THREE.Vector3(), ziel: new THREE.Vector3() };
  let umlauf = 0.0;
  let frei = false;               /* true = Begehung, Kamera gehört dem Besucher */

  /* DIE KAMERA RICHTET SICH MIT AUF.
     Bei 0 % steht sie senkrecht über dem Haus — dieselbe Ansicht wie der
     gezeichnete Plan, der darüberliegt. Mit jedem Prozent Höhe wandert sie
     nach außen und kippt in den Dreiviertelblick. Das ist der eigentliche
     Übergang des Abschnitts: Der Plan wird zum Gebäude, ohne dass irgendwo
     geschnitten wird. Eine Kamera, die oben stehen bleibt, zeigt am Ende
     nur ein Flachdach. */
  function planBlick() {
    const t = wachstum;
    const e = t * t * (3 - 2 * t);
    const hoehe = 34 - 20.5 * e;
    const weg = 26 * e;
    wunsch.pos.copy(nachDrei(
      mitte.x + weg * 0.62,
      mitte.y - 0.01 + weg * 0.80,
      hoehe));
    wunsch.ziel.copy(nachDrei(mitte.x, mitte.y + e * 1.6, 1.4 + e * 1.7));
  }
  function schauBlick() {
    const r = 33;
    wunsch.pos.copy(nachDrei(
      mitte.x + r * Math.cos(umlauf),
      mitte.y + r * Math.sin(umlauf) - 6,
      9.5));
    wunsch.ziel.copy(nachDrei(mitte.x, mitte.y + 1.5, 3.2));
  }

  /* Der Standpunkt eines gerechneten Bildes, Eins zu eins. Brennweite in
     Millimeter auf einem 36-mm-Sensor -- dieselbe Umrechnung, die Blender
     benutzt, sonst stimmt der Bildausschnitt nicht und der Vergleich taugt
     nichts. */
  let stehtFest = null;
  function fotoBlick() {
    if (!stehtFest) { return; }
    wunsch.pos.copy(nachDrei(stehtFest.pos[0], stehtFest.pos[1], stehtFest.pos[2]));
    wunsch.ziel.copy(nachDrei(stehtFest.ziel[0], stehtFest.ziel[1], stehtFest.ziel[2]));
    const grad = 2 * Math.atan(18 / stehtFest.brennweite) * 180 / Math.PI;
    if (Math.abs(kamera.fov - grad) > 0.01) {
      kamera.fov = grad;
      kamera.updateProjectionMatrix();
    }
  }

  /* --- Begehung --------------------------------------------------------
     Kein Physikmodul: Der nächste Schritt muss in einem Raumrechteck oder
     einem Türdurchgang landen, sonst findet er nicht statt. Dieselben
     Rechtecke zeichnen den Grundriss — zwei Beschreibungen desselben
     Hauses laufen sonst auseinander. */
  const AUGEN = 1.65;
  let gier = 0, neigung = 0;
  let stand = { x: 3.1, y: -5.4, z: 0 };
  let punkt = 0;

  function flaechen(z) {
    if (!plan) { return []; }
    const g = plan.geschosse.find((x) => Math.abs(x.z - z) < 0.5);
    const raus = g ? g.raeume.map((r) => [r.x0, r.y0, r.x1, r.y1]) : [];
    for (const d of plan.durchgaenge) {
      if (Math.abs(d.z - z) < 0.5) { raus.push([d.x0, d.y0, d.x1, d.y1]); }
    }
    return raus;
  }
  function drin(x, y, z) {
    if (z < 0.2 && (y < 0.2 || y > 11.2)) { return true; }   /* draußen herumgehen */
    const r = 0.30;
    for (const [x0, y0, x1, y1] of flaechen(z)) {
      if (x > x0 + r && x < x1 - r && y > y0 + r && y < y1 - r) { return true; }
    }
    return false;
  }

  function punktSetzen(i) {
    if (!plan || !plan.rundgang.length) { return; }
    punkt = (i + plan.rundgang.length) % plan.rundgang.length;
    const p = plan.rundgang[punkt];
    stand = { x: p.x, y: p.y, z: p.z };
    gier = -p.blick * Math.PI / 180;
    neigung = 0;
    titel(raumName(p.raum) + ' · ' + (punkt + 1) + '/' + plan.rundgang.length);
  }

  const tasten = Object.create(null);
  function gehen(dt) {
    let vx = 0, vy = 0;
    if (tasten.w || tasten.ArrowUp) { vy += 1; }
    if (tasten.s || tasten.ArrowDown) { vy -= 1; }
    if (tasten.a || tasten.ArrowLeft) { vx -= 1; }
    if (tasten.d || tasten.ArrowRight) { vx += 1; }
    if (!vx && !vy) { return; }
    const tempo = 2.6 * dt;
    const s = Math.sin(gier), c = Math.cos(gier);
    /* Blick: gier 0 heißt nach +Y in Blender. Rechts davon liegt +X. */
    const dx = (vy * s + vx * c) * tempo;
    const dy = (vy * c - vx * s) * tempo;
    if (drin(stand.x + dx, stand.y, stand.z)) { stand.x += dx; }
    if (drin(stand.x, stand.y + dy, stand.z)) { stand.y += dy; }
  }


  /* --- Maus, Finger, Tasten ------------------------------------------- */
  let zieht = false, lx = 0, ly = 0;
  leinwand.addEventListener('pointerdown', (e) => {
    zieht = true; lx = e.clientX; ly = e.clientY;
    leinwand.setPointerCapture(e.pointerId);
  });
  leinwand.addEventListener('pointerup', (e) => {
    zieht = false;
    try { leinwand.releasePointerCapture(e.pointerId); } catch (f) { /* egal */ }
  });
  leinwand.addEventListener('pointermove', (e) => {
    if (!zieht) { return; }
    const dx = e.clientX - lx, dy = e.clientY - ly;
    lx = e.clientX; ly = e.clientY;
    if (frei) {
      gier -= dx * 0.005;
      neigung = Math.max(-0.9, Math.min(0.9, neigung - dy * 0.004));
    } else {
      umlauf -= dx * 0.006;
    }
  });
  leinwand.addEventListener('keydown', (e) => {
    tasten[e.key] = true;
    if (frei && ['w', 'a', 's', 'd', 'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
      e.preventDefault();
    }
  });
  leinwand.addEventListener('keyup', (e) => { tasten[e.key] = false; });
  leinwand.addEventListener('blur', () => { for (const k in tasten) { tasten[k] = false; } });
  leinwand.tabIndex = 0;

  /* --- Bildschleife ---------------------------------------------------- */
  let bilder = 0, letzteMessung = performance.now(), fps = 0, letzte = performance.now();

  /* RÜCKFALL NACH UNTEN, GEMESSEN STATT GERATEN.
     Das möblierte Haus ist 1,6 MB und deutlich schwerer als der Tisch. Wer
     darunter zusammenbricht, soll nicht vier Bilder je Sekunde bekommen,
     sondern die gerechneten Fotos — die sind auf so einem Gerät ohnehin das
     bessere Bild. Drei Sekunden unter acht Bildern reichen als Beleg; kurze
     Einbrüche beim Laden der Textur sollen nicht zählen. */
  let mager = 0;
  function messen(jetzt) {
    bilder += 1;
    if (jetzt - letzteMessung >= 1000) {
      fps = Math.round(bilder * 1000 / (jetzt - letzteMessung));
      bilder = 0; letzteMessung = jetzt;
      melden(fps + ' ' + wort('fps', 'Bilder/s') + ' · ' + kb(geladen));
      mager = fps < 8 ? mager + 1 : 0;
      if (mager >= 3 && !kasten.hasAttribute('data-haus-erzwungen')) {
        zurueckfallen();
      }
    }
  }

  function zurueckfallen() {
    cancelAnimationFrame(lauf);
    echtzeit = null;
    kasten.setAttribute('data-haus-stufe', 'bild');
    leinwand.hidden = true;
    melden(wort('planbereit', 'Grundriss gezeichnet') + ' · ' + kb(geladen));
    bildFassungSetzen();
    planSchleier(0);
  }

  function schleife(jetzt) {
    lauf = requestAnimationFrame(schleife);
    const dt = Math.min(0.05, (jetzt - letzte) / 1000);
    letzte = jetzt;
    messen(jetzt);

    if (frei) {
      gehen(dt);
      const p = nachDrei(stand.x, stand.y, stand.z + AUGEN);
      kamera.position.lerp(p, 1 - Math.pow(0.001, dt));
      const richtung = nachDrei(
        stand.x + Math.sin(gier) * 4,
        stand.y + Math.cos(gier) * 4,
        stand.z + AUGEN + Math.tan(neigung) * 4);
      blick.ziel.lerp(richtung, 1 - Math.pow(0.0005, dt));
      kamera.lookAt(blick.ziel);
    } else {
      if (station === 'grundriss') { planBlick(); }
      else if (station === 'vergleich') { fotoBlick(); }
      else { if (!ruhig && !zieht) { umlauf += dt * 0.055; } schauBlick(); }
      const k = 1 - Math.pow(0.0025, dt);
      kamera.position.lerp(wunsch.pos, k);
      blick.ziel.lerp(wunsch.ziel, k);
      kamera.lookAt(blick.ziel);
    }
    renderer.render(szene, kamera);
  }

  function groesseSetzen() {
    /* Die Leinwand misst SICH, nicht ihren Kasten: Der Kasten enthält auch
       die Ablesung, und wer die mitmisst, schiebt die Szene nach oben. */
    const b = leinwand.clientWidth || 800;
    const h = leinwand.clientHeight || 500;
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.9));
    renderer.setSize(b, h, false);
    kamera.aspect = b / h;
    kamera.updateProjectionMatrix();
  }
  groesseSetzen();
  window.addEventListener('resize', groesseSetzen, { passive: true });

  /* --- Nach außen ------------------------------------------------------ */
  const nachAussen = {
    stationSetzen(neu) {
      frei = (neu === 'begehung');
      if (neu === 'grundriss') {
        /* Erst den Abschnitt auf "fertig", DANN wachsen -- sonst
           überschreibt stufeSetzen() die Überschrift mit dem Bauabschnitt,
           und über dem Grundriss stand "Außenanlage · 8/8". */
        stufeSetzen(8);
        wachstumSetzen(Number((el('[data-regler="wachstum"]') || {}).value || 100) / 100);
        geschossTitel();
      } else if (neu === 'bauablauf') {
        wachstumSetzen(1);
        stufeSetzen(Number((el('[data-regler="stufe"]') || {}).value || 8));
      } else if (neu === 'vergleich') {
        wachstumSetzen(1);
        stufeSetzen(8);
        /* Bei einem Innenstandpunkt muss die Perspektivkorrektur weg: Die
           gerechneten Innenbilder sind frei geneigt, die Aussenbilder
           lotrecht. Hier genuegt Position und Blickpunkt -- beides steht
           im Manifest. */
      } else {
        wachstumSetzen(1);
        stufeSetzen(8);
        if (neu === 'begehung') { punktSetzen(punkt); leinwand.focus({ preventScroll: true }); }
      }
    },
    geschossSetzen() { /* die Echtzeitfassung zeigt immer das ganze Haus */ },
    wachstumSetzen,
    stufeSetzen,
    punktWeiter(d) { punktSetzen(punkt + d); },
    standpunktSetzen(name) {
      stehtFest = (plan && plan.fotostandpunkte && plan.fotostandpunkte[name]) || null;
    },
    anhalten() {
      cancelAnimationFrame(lauf);
      renderer.dispose();
    },
  };

  lauf = requestAnimationFrame(schleife);
  return nachAussen;
}


/* --------------------------------------------------------------- Knöpfe */

function knoepfeVerdrahten() {
  alle('[data-hstation]').forEach((b) => {
    b.addEventListener('click', () => stationSetzen(b.getAttribute('data-hstation')));
  });
  alle('[data-geschoss]').forEach((b) => {
    b.addEventListener('click', () => geschossSetzen(b.getAttribute('data-geschoss')));
  });
  const wachs = el('[data-regler="wachstum"]');
  if (wachs) {
    wachs.addEventListener('input', () => {
      const t = Number(wachs.value) / 100;
      const a = el('[data-haus-wachstum]');
      if (a) { a.textContent = Math.round(t * 100) + ' %'; }
      if (echtzeit) { echtzeit.wachstumSetzen(t); }
    });
  }
  const st = el('[data-regler="stufe"]');
  if (st) {
    st.addEventListener('input', () => {
      if (echtzeit) { echtzeit.stufeSetzen(Number(st.value)); }
      alle('[data-stufe-wert]').forEach((x) => { x.textContent = st.value; });
    });
  }
  alle('[data-punkt]').forEach((b) => {
    b.addEventListener('click', () => {
      if (echtzeit) { echtzeit.punktWeiter(Number(b.getAttribute('data-punkt'))); }
    });
  });
  alle('[data-standpunkt]').forEach((b) => {
    b.addEventListener('click', () => standpunktSetzen(b.getAttribute('data-standpunkt')));
  });
  const fs = el('[data-fotoschalter]');
  if (fs) { fs.addEventListener('click', () => fotoSchalten(!fotoAn)); }
  /* „Haus aufrichten": der Regler fährt von selbst durch. Das ist der
     Moment, für den der Abschnitt gebaut ist — er darf nicht davon
     abhängen, dass jemand einen Schieber findet. */
  const auf = el('[data-aufrichten]');
  if (auf) {
    auf.addEventListener('click', () => {
      if (!echtzeit || !wachs) { return; }
      const start = performance.now();
      const von = Number(wachs.value) / 100 < 0.05 ? 0 : 0;
      wachs.value = '0';
      echtzeit.wachstumSetzen(0);
      const dauer = 2600;
      const schritt = (jetzt) => {
        const t = Math.min(1, (jetzt - start) / dauer);
        const e = 1 - Math.pow(1 - t, 3);
        const wert = von + (1 - von) * e;
        wachs.value = String(Math.round(wert * 100));
        echtzeit.wachstumSetzen(wert);
        if (t < 1) { requestAnimationFrame(schritt); }
      };
      requestAnimationFrame(schritt);
    });
  }
}

/* ---------------------------------------------------------------- Start */

async function starten() {
  if (!kasten) { return; }
  knoepfeVerdrahten();

  /* Der Plan zuerst: 10 KB, und der Abschnitt ist sofort etwas wert —
     auch dann, wenn das Modell nie geladen wird. */
  try {
    const a = await fetch(D + 'haus-manifest.json');
    plan = await a.json();
    geladen += Number(a.headers.get('content-length') || 9800);
    planZeichnen();
    geschossSetzen('eg');
  } catch (f) {
    melden(wort('planfehler', 'Grundriss nicht erreichbar'));
    return;
  }

  const frage = new URLSearchParams(location.search).get('haus');
  const darf = frage === 'echtzeit'
    || (frage !== 'bild' && supportsWebGL() && !grafikZuSchwach());
  /* ?haus=echtzeit schaltet auch den Rueckfall ab -- sonst kann man die
     Echtzeitfassung auf einem langsamen Geraet gar nicht pruefen. */
  if (frage === 'echtzeit') { kasten.setAttribute('data-haus-erzwungen', 'ja'); }

  stationSetzen('grundriss');
  if (!darf) {
    kasten.setAttribute('data-haus-stufe', 'bild');
    melden(wort('planbereit', 'Grundriss gezeichnet') + ' · ' + kb(geladen));
    bildFassungSetzen();
    return;
  }

  const leinwand = el('canvas');
  if (!leinwand) { return; }

  /* Erst laden, wenn der Abschnitt ins Bild kommt. Die Oberfläche hat
     Vorrang vor der Szene — immer. */
  const sichtbar = () => new Promise((fertig) => {
    const b = new IntersectionObserver((e) => {
      if (e.some((x) => x.isIntersecting)) { b.disconnect(); fertig(); }
    }, { rootMargin: '400px' });
    b.observe(kasten);
  });
  await sichtbar();

  melden(wort('laedt', 'lädt') + ' …');
  /* Die Groesse steht im Manifest, das ohnehin schon geladen ist. Vorher
     stand hier eine feste Zahl -- und die Ablesung behauptete nach dem
     Moeblieren weiter 363 KB, obwohl 1,6 MB ueber die Leitung gingen. Eine
     gemessene Zahl, die nicht stimmt, ist schlimmer als keine. */
  const angesagt = (plan.dateien && plan.dateien.bytes) || 0;
  try {
    const a = await fetch(D + 'haus.glb', { method: 'HEAD' });
    geladen += Number(a.headers.get('content-length') || angesagt);
  } catch (f) { geladen += angesagt; }

  try {
    const welt = await echtzeitStarten(leinwand);
    echtzeit = echtzeitVerdrahten(welt, leinwand);
    kasten.setAttribute('data-haus-stufe', 'echtzeit');
    leinwand.hidden = false;
    const bild = el('[data-haus-bild]');
    if (bild) { bild.hidden = true; }
    stationSetzen(station);
  } catch (f) {
    /* Bricht das Laden, bleibt der gezeichnete Plan stehen. Kein leeres
       Feld, keine Fehlermeldung in der Fläche — der Abschnitt funktioniert
       weiter, nur ohne Bewegung. */
    kasten.setAttribute('data-haus-stufe', 'bild');
    melden(wort('planbereit', 'Grundriss gezeichnet'));
    bildFassungSetzen();
  }
}

if (kasten) { starten(); }
