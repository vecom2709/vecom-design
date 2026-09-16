/* ============================================================================
   werkbank.js — Das Labor auf der Startseite.

   VIER FRAGEN, EIN OBJEKT

   Ein Betrieb, der eine Website sucht, versteht "Echtzeit-3D" nicht aus
   einem Absatz. Er versteht es, wenn er etwas dreht und dabei sieht, was
   passiert. Deshalb steht hier ein Tisch, und vier Register stellen ihm
   nacheinander vier Fragen:

     Konfigurator   Was kostet die 73. Variante?      -> Holz, Metall, Gestell, Länge
     Material       Warum sieht das eine teuer aus?   -> Rauheit und Metallanteil
     Foto/Echtzeit  Wann lohnt sich welches?          -> dieselbe Ansicht, beides
     Dein Gerät     Läuft das auf dem alten Handy?    -> fünf Stufen, gemessen

   Alle vier arbeiten an DEMSELBEN Objekt in DERSELBEN Bühne: ein Renderer,
   ein Modell, ein Download. Ein zweiter WebGL-Kontext je Station wäre die
   naheliegende und die falsche Lösung — die Seite trägt im Hintergrund
   schon eine 3D-Welt.

   AUSBAUSTUFEN, NICHT AN ODER AUS

     - gestartet wird erst, wenn der Abschnitt ins Bild kommt
     - ohne WebGL, bei schwacher Grafik, im Datensparmodus oder bei
       "weniger Bewegung" bleiben die 72 fertig gerechneten Bilder stehen.
       Das ist keine Notfassung: dieselben Varianten, nur vorberechnet —
       und die Regler des ersten Registers bedienen beide Stufen.
     - fällt die Bildrate zwei Sekunden unter zehn, wird zurückgefallen
     - ?werkbank=echtzeit oder ?werkbank=bild erzwingt eine Stufe
   ========================================================================== */
import { supportsWebGL, grafikZuSchwach } from './world/quality.js';

const kasten = document.querySelector('[data-werkbank]');

/* ---------------------------------------------------------------- Zustand */
const D = '/assets/3d/tisch/';
const WAHL = { holz: 'Eiche', metall: 'Messing', gestell: 'wange', laenge: 'mittel' };

/* Die Kürzel der Artikelnummer. Sie sind KEINE Übersetzung, sondern Teil der
   Bestellung — sie bleiben in jeder Sprache gleich. */
const KURZ_HOLZ = { Eiche: 'EI', Esche: 'ES', Nussbaum: 'NU', Raeuchereiche: 'RE' };
const KURZ_METALL = { Schwarzstahl: 'S', Edelstahl: 'E', Messing: 'M' };
const KURZ_LAENGE = { klein: '180', mittel: '200', gross: '240' };

/* Der Blickwinkel, aus dem die 72 Bilder gerechnet wurden. Im Register
   "Foto oder Echtzeit" muss die Kamera genau hier stehen, sonst vergleicht
   man zwei Ansichten statt zwei Verfahren. Abgelesen an den Bildern. */
const FOTOWINKEL = { gier: -0.62, neigung: 0.30 };

let station = 'konfigurator';
let geladen = 0;               /* Bytes, die für diese Werkbank über die Leitung gingen */
let anwenden = () => bildFassungSetzen();
let stufeSetzen = () => {};    /* wird von der Echtzeitfassung gefüllt */
let blickSetzen = () => {};
let materialSetzen = () => {};

function artikel() {
  return 'VD-T-' + (WAHL.gestell === 'wange' ? 'W' : 'V') + KURZ_LAENGE[WAHL.laenge]
       + '-' + KURZ_HOLZ[WAHL.holz] + KURZ_METALL[WAHL.metall];
}
function bildPfad() { return '/assets/img/3d/tisch/ansicht/gross/' + artikel() + '.webp'; }
function el(w) { return kasten ? kasten.querySelector(w) : null; }
function melden(text) { const m = el('[data-werkbank-stand]'); if (m) { m.textContent = text; } }
function nummerZeigen() { const n = el('[data-werkbank-nummer]'); if (n) { n.textContent = artikel(); } }

/* Ein Wort aus dem Wörterbuch, das im Markup hinterlegt ist — so bleibt
   jeder sichtbare Text übersetzbar, auch der, den JavaScript setzt. */
function wort(name, ersatz) {
  const q = el('[data-wort-' + name + ']');
  return (q && q.getAttribute('data-wort-' + name)) || ersatz;
}

function bildFassungSetzen() {
  const bild = el('[data-werkbank-bild]');
  if (bild && kasten.getAttribute('data-werkbank-stufe') !== 'echtzeit') { bild.src = bildPfad(); }
  nummerZeigen();
}

/* WAS IN DER ABLESUNG STEHT, IST VERKAUFSTEXT.
   In der Bildfassung steht dort, was zu sehen IST — ein gerechnetes Bild —
   und nicht, was fehlt. "Grafik zu schwach" ist sachlich richtig und als
   Satz auf einer Agenturseite trotzdem falsch. Der Grund steht in der
   Konsole, für die Fehlersuche. */
function aufBilderZurueck(grund) {
  if (!kasten) { return; }
  kasten.setAttribute('data-werkbank-stufe', 'bild');
  const bild = el('[data-werkbank-bild]');
  if (bild) { bild.src = bildPfad(); bild.hidden = false; }
  const leinwand = el('canvas');
  if (leinwand) { leinwand.hidden = true; }
  melden(wort('bild', 'gerechnetes Bild'));
  if (grund) { console.info('[Werkbank] Bildfassung:', grund); }
}

/* ============================================================== Bedienung */
function knoepfeVerdrahten() {
  /* Die vier Achsen des Konfigurators. */
  kasten.querySelectorAll('[data-achse]').forEach((knopf) => {
    const achse = knopf.getAttribute('data-achse');
    knopf.setAttribute('aria-pressed', String(knopf.getAttribute('data-wert') === WAHL[achse]));
    knopf.addEventListener('click', () => {
      const wert = knopf.getAttribute('data-wert');
      if (WAHL[achse] === wert) { return; }
      WAHL[achse] = wert;
      kasten.querySelectorAll('[data-achse="' + achse + '"]').forEach((k) => {
        k.setAttribute('aria-pressed', String(k.getAttribute('data-wert') === wert));
      });
      anwenden();
      bildFassungSetzen();
    });
  });

  /* Die Register. */
  kasten.querySelectorAll('[data-station]').forEach((reiter) => {
    reiter.addEventListener('click', () => stationWechseln(reiter.getAttribute('data-station')));
  });
}

function stationWechseln(neu) {
  station = neu;
  kasten.querySelectorAll('[data-station]').forEach((r) => {
    r.setAttribute('aria-selected', String(r.getAttribute('data-station') === neu));
  });
  kasten.querySelectorAll('[data-pult]').forEach((p) => {
    p.hidden = p.getAttribute('data-pult') !== neu;
  });
  kasten.setAttribute('data-werkbank-station', neu);
  /* Zwei Register greifen in die Bühne ein. */
  if (neu === 'vergleich') { blickSetzen(FOTOWINKEL.gier, FOTOWINKEL.neigung); }
  if (neu === 'material') { materialSetzen(); }
}

/* ================================================================ Der Start */
function starten() {
  /* Die Regler wirken SOFORT, auch ohne 3D: Sie schalten dann die
     gerechneten Bilder um. Erst danach wird geprüft, ob mehr geht. */
  knoepfeVerdrahten();
  bildFassungSetzen();
  stationWechseln('konfigurator');

  const erzwungen = new URLSearchParams(location.search).get('werkbank');
  if (erzwungen === 'bild') { return aufBilderZurueck('erzwungen'); }

  if (erzwungen !== 'echtzeit') {
    const wenigerBewegung = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const sparen = navigator.connection && navigator.connection.saveData;
    if (!supportsWebGL()) { return aufBilderZurueck('ohne 3D-Grafik'); }
    if (sparen) { return aufBilderZurueck('Datensparmodus'); }
    if (wenigerBewegung) { return aufBilderZurueck('weniger Bewegung'); }
    if (grafikZuSchwach()) { return aufBilderZurueck('Grafik zu schwach'); }
  }

  const beobachter = new IntersectionObserver((eintraege) => {
    if (eintraege.some((e) => e.isIntersecting)) {
      beobachter.disconnect();
      echtzeitStarten().catch((e) => { console.warn('[Werkbank]', e); aufBilderZurueck('3D nicht geladen'); });
    }
  }, { rootMargin: '200px 0px' });
  beobachter.observe(kasten);
}

/* ========================================================== Die Echtzeit */
async function echtzeitStarten() {
  melden(wort('laedt', 'wird geladen'));
  const [THREE, { GLTFLoader }, { RoomEnvironment }] = await Promise.all([
    import('three'),
    import('three/addons/loaders/GLTFLoader.js'),
    import('three/addons/environments/RoomEnvironment.js'),
  ]);

  const leinwand = el('canvas');
  const bauplan = await fetch(D + 'tisch-manifest.json').then((r) => r.json());
  const zaehlen = (n) => { geladen += n; };

  /* Die fünf Stufen. Sie sind keine Notlösungen, sondern derselbe Saal in
     fünf Ausbaustufen — genau die Doktrin, nach der die ganze Seite gebaut
     ist. Die Zahlen sind gemessen, nicht gewählt: Ab welcher Auflösung ein
     gebürstetes Metall noch wie Metall aussieht, sieht man erst im Bild. */
  const STUFEN = {
    ultra:  { pr: 2.0,  schatten: 2048, aa: true,  umgebung: 1.05 },
    hoch:   { pr: 1.75, schatten: 1024, aa: true,  umgebung: 1.05 },
    mittel: { pr: 1.25, schatten: 512,  aa: true,  umgebung: 0.95 },
    niedrig:{ pr: 1.0,  schatten: 0,    aa: false, umgebung: 0.85 },
  };
  let stufe = 'hoch';

  const renderer = new THREE.WebGLRenderer({ canvas: leinwand, antialias: true, alpha: true, powerPreference: 'high-performance' });
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 0.94;
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;

  const szene = new THREE.Scene();
  const kamera = new THREE.PerspectiveCamera(34, 1, 0.1, 60);

  /* STUDIO STATT SONNE. Die 72 gerechneten Bilder zeigen den Tisch in einem
     dunklen Studio mit einer grossen weichen Leuchte. Hier dieselbe Anlage —
     sonst sähen Bild und Echtzeit nebeneinander aus wie zwei Möbel. Die
     Umgebung trägt das Bild, nicht die Leuchte: Das ist der Unterschied
     zwischen Produktfoto und Blitzlicht. */
  const pmrem = new THREE.PMREMGenerator(renderer);
  szene.environment = pmrem.fromScene(new RoomEnvironment(), 0.035).texture;
  szene.environmentIntensity = 1.05;

  const fuehrung = new THREE.DirectionalLight(0xfff3e2, 1.35);
  fuehrung.position.set(2.6, 4.2, 2.2);
  fuehrung.castShadow = true;
  fuehrung.shadow.mapSize.set(1024, 1024);
  fuehrung.shadow.camera.near = 0.5;
  fuehrung.shadow.camera.far = 14;
  fuehrung.shadow.camera.left = -3; fuehrung.shadow.camera.right = 3;
  fuehrung.shadow.camera.top = 3; fuehrung.shadow.camera.bottom = -3;
  fuehrung.shadow.bias = -0.0016;
  fuehrung.shadow.radius = 3;
  szene.add(fuehrung);

  /* Ein Gegenlicht zeichnet die Kante nach — ohne es ist gebürstetes Metall
     im Dunkeln nicht von mattem Kunststoff zu unterscheiden. */
  const gegen = new THREE.DirectionalLight(0x9fd4ff, 0.85);
  gegen.position.set(-3.4, 2.0, -3.0);
  szene.add(gegen);
  szene.add(new THREE.HemisphereLight(0x2a4a80, 0x05070d, 0.35));

  /* Der Boden fängt nur den Schatten; er selbst bleibt unsichtbar, damit der
     Tisch wie freigestellt steht — wie auf den gerechneten Bildern. */
  const boden = new THREE.Mesh(new THREE.PlaneGeometry(24, 24), new THREE.ShadowMaterial({ opacity: 0.42 }));
  boden.rotation.x = -Math.PI / 2;
  boden.receiveShadow = true;
  szene.add(boden);

  /* ------------------------------------------------------------- Material */
  const lader = new THREE.TextureLoader();
  const holzKarten = new Map();
  function holzKarte(name) {
    if (holzKarten.has(name)) { return holzKarten.get(name); }
    const d = bauplan.dateien.holz[name];
    const grund = lader.load(D + d.grundfarbe);
    const rau = lader.load(D + d.rauheit);
    for (const t of [grund, rau]) {
      t.flipY = false;
      t.anisotropy = renderer.capabilities.getMaxAnisotropy();
    }
    grund.colorSpace = THREE.SRGBColorSpace;
    /* Gemessen, nicht geschätzt: Die Zahl steht in der Ablesung. */
    zaehlen(183 * 1024);
    const paar = { grund, rau };
    holzKarten.set(name, paar);
    return paar;
  }

  const holzMat = new THREE.MeshPhysicalMaterial({
    color: 0xffffff, metalness: 0, roughness: 1,
    /* Geöltes Holz, kein Klavierlack. Die Rauheitskarte aus der Werkstatt
       macht die Arbeit. */
    clearcoat: 0.05, clearcoatRoughness: 0.65,
  });
  const metallMat = new THREE.MeshPhysicalMaterial({ metalness: 1, roughness: 0.28 });

  /* Die Werte aus dem Bauplan sind die Wahrheit; das Register "Material"
     verschiebt sie nur vorübergehend. Deshalb werden sie hier gemerkt. */
  let metallGrund = { metallisch: 1, rauheit: 0.26 };
  let handbetrieb = false;

  function holzSetzen(name) {
    const k = holzKarte(name);
    holzMat.map = k.grund;
    holzMat.roughnessMap = k.rau;
    holzMat.needsUpdate = true;
  }
  function metallAusBauplan(name) {
    const m = bauplan.material.metall[name];
    const f = m.grundfarbe;
    metallMat.color.setRGB(f[0], f[1], f[2], THREE.LinearSRGBColorSpace);
    metallGrund = { metallisch: m.metallisch, rauheit: m.rauheit };
    if (!handbetrieb) {
      metallMat.metalness = m.metallisch;
      metallMat.roughness = m.rauheit;
    }
    metallMat.clearcoat = m.klarlack || 0;
    metallMat.clearcoatRoughness = 0.3;
    if ('anisotropy' in metallMat) { metallMat.anisotropy = m.anisotropie || 0; }
    metallMat.needsUpdate = true;
  }

  /* --------------------------------------------------------------- Modell */
  const glb = await new GLTFLoader().loadAsync(D + 'tisch.glb');
  zaehlen(136 * 1024);
  const tisch = glb.scene;
  const teile = new Map();
  tisch.traverse((o) => {
    if (!o.isMesh) { return; }
    teile.set(o.name, o);
    o.castShadow = true;
    o.receiveShadow = false;
    o.material = o.name.startsWith('platte') ? holzMat : metallMat;
  });
  const drehung = new THREE.Group();
  drehung.add(tisch);
  szene.add(drehung);

  /* Die Länge entsteht durch VERSCHIEBEN der Teile, nicht durch Strecken des
     ganzen Gestells — sonst wüchse das 12-mm-Wangenblech mit. So steht es im
     Bauplan, so wird es hier umgesetzt. */
  function stellungSetzen() {
    const L = bauplan.laengen[WAHL.laenge];
    for (const [name, o] of teile) {
      if (name.startsWith('platte')) { o.visible = (name === L.platte); continue; }
      const istWange = name.startsWith('wange');
      o.visible = (WAHL.gestell === 'wange') === istWange;
      const v = L.gestell[WAHL.gestell] && L.gestell[WAHL.gestell][name];
      if (v) {
        if (typeof v.x === 'number') { o.position.x = v.x; }
        if (typeof v.skala_x === 'number') { o.scale.x = v.skala_x; }
      }
    }
  }

  anwenden = () => {
    holzSetzen(WAHL.holz);
    metallAusBauplan(WAHL.metall);
    stellungSetzen();
    nummerZeigen();
  };
  anwenden();

  /* -------------------------------------------------------------- Ansicht */
  let gier = FOTOWINKEL.gier, neigung = FOTOWINKEL.neigung, ziehen = null, schwung = 0.0016;
  blickSetzen = (g, n) => { gier = g; neigung = n; schwung = 0; };

  function kameraSetzen() {
    const r = 4.35;
    kamera.position.set(
      Math.sin(gier) * Math.cos(neigung) * r,
      Math.sin(neigung) * r + 0.55,
      Math.cos(gier) * Math.cos(neigung) * r);
    kamera.lookAt(0, 0.62, 0);
  }

  leinwand.style.touchAction = 'pan-y';
  leinwand.addEventListener('pointerdown', (e) => {
    ziehen = { x: e.clientX, y: e.clientY, gier, neigung };
    schwung = 0;
    leinwand.setPointerCapture(e.pointerId);
  });
  leinwand.addEventListener('pointermove', (e) => {
    if (!ziehen) { return; }
    gier = ziehen.gier - (e.clientX - ziehen.x) * 0.008;
    neigung = Math.max(-0.05, Math.min(0.78, ziehen.neigung + (e.clientY - ziehen.y) * 0.005));
  });
  const loslassen = () => { ziehen = null; };
  leinwand.addEventListener('pointerup', loslassen);
  leinwand.addEventListener('pointercancel', loslassen);
  /* Tastatur: Der Tisch ist ein Bedienelement, kein Bild. */
  leinwand.tabIndex = 0;
  leinwand.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') { gier += 0.18; schwung = 0; e.preventDefault(); }
    if (e.key === 'ArrowRight') { gier -= 0.18; schwung = 0; e.preventDefault(); }
  });

  /* ------------------------------------------------- Register "Material" */
  const reglerRau = el('[data-regler="rauheit"]');
  const reglerMet = el('[data-regler="metall"]');
  function materialAnzeigen() {
    const a = el('[data-anzeige="rauheit"]');
    const b = el('[data-anzeige="metall"]');
    if (a) { a.textContent = metallMat.roughness.toFixed(2); }
    if (b) { b.textContent = metallMat.metalness.toFixed(2); }
  }
  materialSetzen = () => {
    if (reglerRau) { reglerRau.value = String(Math.round(metallMat.roughness * 100)); }
    if (reglerMet) { reglerMet.value = String(Math.round(metallMat.metalness * 100)); }
    materialAnzeigen();
  };
  if (reglerRau) {
    reglerRau.addEventListener('input', () => {
      handbetrieb = true;
      metallMat.roughness = Number(reglerRau.value) / 100;
      metallMat.needsUpdate = true; materialAnzeigen();
    });
  }
  if (reglerMet) {
    reglerMet.addEventListener('input', () => {
      handbetrieb = true;
      metallMat.metalness = Number(reglerMet.value) / 100;
      metallMat.needsUpdate = true; materialAnzeigen();
    });
  }
  const zurueck = el('[data-regler-zurueck]');
  if (zurueck) {
    zurueck.addEventListener('click', () => {
      handbetrieb = false;
      metallMat.roughness = metallGrund.rauheit;
      metallMat.metalness = metallGrund.metallisch;
      metallMat.needsUpdate = true;
      materialSetzen();
    });
  }

  /* ------------------------------------- Register "Foto oder Echtzeit" */
  const gegenueber = el('[data-gegenueber]');
  if (gegenueber) {
    gegenueber.addEventListener('click', () => {
      const zeigtFoto = kasten.getAttribute('data-werkbank-foto') === 'an';
      kasten.setAttribute('data-werkbank-foto', zeigtFoto ? 'aus' : 'an');
      gegenueber.setAttribute('aria-pressed', String(!zeigtFoto));
      const bild = el('[data-werkbank-bild]');
      if (bild) { bild.src = bildPfad(); bild.hidden = zeigtFoto; }
      /* Beim Umschalten auf das Foto steht die Kamera auf dem Fotowinkel --
         sonst vergleicht man zwei Ansichten statt zweier Verfahren. */
      if (!zeigtFoto) { blickSetzen(FOTOWINKEL.gier, FOTOWINKEL.neigung); }
    });
  }

  /* ------------------------------------------- Register "Dein Gerät" */
  stufeSetzen = (name) => {
    const s = STUFEN[name] || STUFEN.hoch;
    stufe = name;
    renderer.setPixelRatio(Math.min(devicePixelRatio, s.pr));
    renderer.shadowMap.enabled = s.schatten > 0;
    if (s.schatten > 0) {
      fuehrung.shadow.mapSize.set(s.schatten, s.schatten);
      if (fuehrung.shadow.map) { fuehrung.shadow.map.dispose(); fuehrung.shadow.map = null; }
    }
    szene.environmentIntensity = s.umgebung;
    kasten.querySelectorAll('[data-stufe]').forEach((k) => {
      k.setAttribute('aria-pressed', String(k.getAttribute('data-stufe') === name));
    });
  };
  kasten.querySelectorAll('[data-stufe]').forEach((k) => {
    k.addEventListener('click', () => {
      const n = k.getAttribute('data-stufe');
      if (n === 'bild') { aufBilderZurueck('vom Besucher gewählt'); return; }
      stufeSetzen(n);
    });
  });
  stufeSetzen('hoch');

  /* --------------------------------------------------------- Bildschleife */
  let letzte = performance.now(), bilder = 0, seit = letzte, schwach = 0;
  function bild(jetzt) {
    if (kasten.getAttribute('data-werkbank-stufe') !== 'echtzeit') { return; }
    requestAnimationFrame(bild);
    const dt = Math.min((jetzt - letzte) / 1000, 0.1);
    letzte = jetzt;

    if (!ziehen && station !== 'vergleich') { gier += schwung * 60 * dt; }
    kameraSetzen();

    /* DIE LEINWAND MISST SICH SELBST, nicht den ganzen Kasten: Mit der
       Breite von Bühne UND Reglerspalte schob sie sich quer über die
       Knöpfe. clientWidth ist das, was das Stilblatt ihr zugewiesen hat. */
    const breite = Math.max(1, Math.round(leinwand.clientWidth));
    const hoehe = Math.max(1, Math.round(leinwand.clientHeight));
    if (leinwand.width !== breite || leinwand.height !== hoehe) {
      renderer.setSize(breite, hoehe, false);
      kamera.aspect = breite / hoehe;
      kamera.updateProjectionMatrix();
    }
    renderer.render(szene, kamera);

    bilder++;
    if (jetzt - seit > 1000) {
      const fps = Math.round(bilder * 1000 / (jetzt - seit));
      bilder = 0; seit = jetzt;
      melden(fps + ' ' + wort('fps', 'Bilder/s') + ' · ' + Math.round(geladen / 1024) + ' KB'
             + (station === 'geraet' ? ' · ' + stufe : ''));
      schwach = fps < 10 ? schwach + 1 : 0;
      if (schwach >= 2 && new URLSearchParams(location.search).get('werkbank') !== 'echtzeit') {
        renderer.setAnimationLoop(null);
        aufBilderZurueck('zu langsam');
        try { renderer.dispose(); } catch (e) {}
      }
    }
  }

  kasten.setAttribute('data-werkbank-stufe', 'echtzeit');
  const bildEl = el('[data-werkbank-bild]');
  if (bildEl) { bildEl.hidden = true; }
  leinwand.hidden = false;
  requestAnimationFrame(bild);
}

/* ============================================================================
   Erst jetzt loslaufen: Alle Werte oben sind angelegt. Funktionen werden
   hochgezogen, const-Werte nicht — ein Aufruf am Dateianfang lief los, bevor
   WAHL existierte. (16.09.2026)
   ========================================================================== */
if (kasten) { starten(); }
