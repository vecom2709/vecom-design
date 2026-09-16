/* ============================================================================
   werkbank.js — Der Tisch zum Anfassen, mitten auf der Startseite.

   WARUM DAS HIER STEHT UND NICHT AUF EINER UNTERSEITE

   Eine Agenturseite behauptet Dinge. Wer zum Beleg erst eine Unterseite
   aufrufen muss, liest die Behauptung und geht. Also steht der Beleg dort,
   wo die Behauptung steht: im Abschnitt selbst, anfassbar, ohne Klick weg.

   WAS DER BESUCHER DABEI LERNT — und das ist der eigentliche Zweck

   Er dreht einen Tisch, wechselt Holz und Metall, und sieht nebenbei drei
   Zahlen: geladene Datenmenge, Bilder je Sekunde, Zahl der Varianten. Damit
   versteht er in zwanzig Sekunden, was ein Konfigurator ist, was er kostet
   und warum er nicht aus Fotos besteht. Kein Text kann das leisten.

   AUSBAUSTUFEN, NICHT AN ODER AUS

   Diese Seite trägt schon eine 3D-Welt im Hintergrund. Ein zweiter Kontext
   ist deshalb kein Selbstläufer:

     - gestartet wird erst, wenn der Abschnitt ins Bild kommt (nicht beim
       Laden der Seite — die Oberfläche hat Vorrang)
     - kein WebGL, schwache Grafik, Datensparmodus oder weniger Bewegung:
       dann bleiben die bereits gerechneten Bilder stehen. Das ist keine
       Notfassung, sondern dieselbe Sache in fotografischer Qualität —
       dieselben 72 Varianten, nur vorberechnet.
     - fällt die Bildrate unter zehn, wird abgeschaltet und auf die Bilder
       zurückgefallen. Ein ruckelnder Tisch verkauft nichts.

   Die Daten liegen fertig in der 3D-Werkstatt: ein Modell (133 KB) mit allen
   Teilen für beide Gestelle und drei Plattenlängen, vier Holztexturen und
   ein Bauplan, der sagt, wo die Teile je Länge stehen.
   ========================================================================== */
import { supportsWebGL, grafikZuSchwach } from './world/quality.js';

const kasten = document.querySelector('[data-werkbank]');
/* Der Aufruf steht GANZ UNTEN, nicht hier. Funktionen werden hochgezogen,
   const-Werte nicht: Ein starten() an dieser Stelle lief los, bevor WAHL
   ueberhaupt existierte, und brach mit "Cannot access WAHL before
   initialization" ab -- sichtbar nur in der Konsole, auf der Seite blieb
   einfach das Bild stehen. (16.09.2026) */

/* ---------------------------------------------------------------- Zustand */
const D = '/assets/3d/tisch/';
const WAHL = { holz: 'Eiche', metall: 'Messing', gestell: 'wange', laenge: 'mittel' };

/* Die Kürzel der Artikelnummer. Sie sind KEINE Übersetzung, sondern Teil der
   Bestellung — sie bleiben in jeder Sprache gleich. */
const KURZ_HOLZ = { Eiche: 'EI', Esche: 'ES', Nussbaum: 'NU', Raeuchereiche: 'RE' };
const KURZ_METALL = { Schwarzstahl: 'S', Edelstahl: 'E', Messing: 'M' };
const KURZ_LAENGE = { klein: '180', mittel: '200', gross: '240' };

function artikel() {
  return 'VD-T-' + (WAHL.gestell === 'wange' ? 'W' : 'V') + KURZ_LAENGE[WAHL.laenge]
       + '-' + KURZ_HOLZ[WAHL.holz] + KURZ_METALL[WAHL.metall];
}

/* ------------------------------------------------------------- Rückfallbild
   Dieselbe Variante, nur gerechnet statt gerendert. Der Pfad ist derselbe,
   den der große Konfigurator benutzt — eine Quelle, kein zweiter Bestand. */
function bildPfad() { return '/assets/img/3d/tisch/ansicht/gross/' + artikel() + '.webp'; }

/* WAS IN DER ABLESUNG STEHT, IST VERKAUFSTEXT.
   In der Bildfassung steht dort deshalb, was zu sehen IST -- ein gerechnetes
   Bild -- und nicht, was fehlt. "Grafik zu schwach" ist sachlich richtig und
   als Satz auf einer Agenturseite trotzdem falsch: Der Besucher liest, dass
   sein Geraet nicht reicht, und nimmt das mit. Der Grund steht weiter in der
   Konsole, fuer die Fehlersuche. */
function aufBilderZurueck(grund) {
  kasten.setAttribute('data-werkbank-stufe', 'bild');
  const bild = kasten.querySelector('[data-werkbank-bild]');
  if (bild) { bild.src = bildPfad(); bild.hidden = false; }
  const leinwand = kasten.querySelector('canvas');
  if (leinwand) { leinwand.hidden = true; }
  const wort = kasten.getAttribute('data-werkbank-wort') || 'gerechnetes Bild';
  melden(wort);
  if (grund) { console.info('[Werkbank] Bildfassung:', grund); }
}

function melden(text) {
  const m = kasten.querySelector('[data-werkbank-stand]');
  if (m) { m.textContent = text; }
}

/* ============================================================== Der Start */
function starten() {
  /* Die Regler funktionieren SOFORT, auch ohne 3D: Sie schalten dann die
     gerechneten Bilder um. Erst danach wird geprüft, ob mehr geht. */
  reglerVerdrahten(() => aktualisierenBild());
  aktualisierenBild();

  /* Zum Nachsehen erzwingbar: ?werkbank=echtzeit oder ?werkbank=bild.
     Ohne diesen Schalter zeigt ein kopfloser Browser mit Softwaregrafik nie
     die Echtzeitfassung -- und genau die will man beim Pruefen sehen. */
  const erzwungen = new URLSearchParams(location.search).get('werkbank');
  if (erzwungen === 'bild') { return aufBilderZurueck('erzwungen'); }

  const wenigerBewegung = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const sparen = navigator.connection && navigator.connection.saveData;

  if (erzwungen !== 'echtzeit') {
  if (!supportsWebGL()) { return aufBilderZurueck('ohne 3D-Grafik'); }
  if (sparen) { return aufBilderZurueck('Datensparmodus'); }
  if (wenigerBewegung) { return aufBilderZurueck('weniger Bewegung'); }
  if (grafikZuSchwach()) { return aufBilderZurueck('Grafik zu schwach'); }
  }

  /* Erst wenn der Abschnitt wirklich zu sehen ist. */
  const beobachter = new IntersectionObserver((eintraege) => {
    if (eintraege.some((e) => e.isIntersecting)) {
      beobachter.disconnect();
      echtzeitStarten().catch((e) => aufBilderZurueck('3D nicht geladen'));
    }
  }, { rootMargin: '200px 0px' });
  beobachter.observe(kasten);
}

function aktualisierenBild() {
  const bild = kasten.querySelector('[data-werkbank-bild]');
  if (bild && kasten.getAttribute('data-werkbank-stufe') !== 'echtzeit') { bild.src = bildPfad(); }
  nummerZeigen();
}

function nummerZeigen() {
  const n = kasten.querySelector('[data-werkbank-nummer]');
  if (n) { n.textContent = artikel(); }
}

/* Wird von der Echtzeitfassung überschrieben. */
let anwenden = () => aktualisierenBild();

function reglerVerdrahten(beiAenderung) {
  kasten.querySelectorAll('[data-achse]').forEach((knopf) => {
    knopf.addEventListener('click', () => {
      const achse = knopf.getAttribute('data-achse');
      const wert = knopf.getAttribute('data-wert');
      if (WAHL[achse] === wert) { return; }
      WAHL[achse] = wert;
      kasten.querySelectorAll('[data-achse="' + achse + '"]').forEach((k) => {
        k.setAttribute('aria-pressed', String(k.getAttribute('data-wert') === wert));
      });
      anwenden();
      beiAenderung();
    });
    knopf.setAttribute('aria-pressed', String(knopf.getAttribute('data-wert') === WAHL[knopf.getAttribute('data-achse')]));
  });
}

/* ========================================================== Die Echtzeit */
async function echtzeitStarten() {
  melden('wird geladen');
  const [THREE, { GLTFLoader }, { RoomEnvironment }] = await Promise.all([
    import('three'),
    import('three/addons/loaders/GLTFLoader.js'),
    import('three/addons/environments/RoomEnvironment.js'),
  ]);

  const leinwand = kasten.querySelector('canvas');
  const bauplan = await fetch(D + 'tisch-manifest.json').then((r) => r.json());

  let geladen = 0;   /* Bytes, die für diese Werkbank über die Leitung gingen */
  const zaehlen = (n) => { geladen += n; };

  const renderer = new THREE.WebGLRenderer({ canvas: leinwand, antialias: true, alpha: true, powerPreference: 'high-performance' });
  renderer.setPixelRatio(Math.min(devicePixelRatio, 1.85));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 0.94;
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;

  const szene = new THREE.Scene();
  const kamera = new THREE.PerspectiveCamera(34, 1, 0.1, 60);

  /* STUDIO STATT SONNE. Die 180 gerechneten Bilder zeigen den Tisch in einem
     dunklen Studio mit einer großen weichen Leuchte. Hier dieselbe Anlage —
     sonst sähen Bild und Echtzeit nebeneinander wie zwei Möbel aus. */
  const pmrem = new THREE.PMREMGenerator(renderer);
  szene.environment = pmrem.fromScene(new RoomEnvironment(), 0.035).texture;
  /* Die Umgebung traegt das Bild, nicht die Leuchte: Das ist der
     Unterschied zwischen Produktfoto und Blitzlicht. */
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
  const boden = new THREE.Mesh(
    new THREE.PlaneGeometry(24, 24),
    new THREE.ShadowMaterial({ opacity: 0.42 }));
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
    /* Grob geschätzt reicht hier nicht: Die Zahl steht in der Ableseleiste
       und muss stimmen. Die vier Sätze wiegen gemessen je rund 180 KB. */
    zaehlen(183 * 1024);
    const paar = { grund, rau };
    holzKarten.set(name, paar);
    return paar;
  }

  const holzMat = new THREE.MeshPhysicalMaterial({
    color: 0xffffff, metalness: 0, roughness: 1,
    /* Geoeltes Holz, kein Klavierlack. 0,18 Klarlack sah auf der Platte aus
       wie Folie -- die Rauheitskarte aus der Werkstatt macht die Arbeit. */
    clearcoat: 0.05, clearcoatRoughness: 0.65,
  });
  const metallMat = new THREE.MeshPhysicalMaterial({ metalness: 1, roughness: 0.28 });

  function holzSetzen(name) {
    const k = holzKarte(name);
    holzMat.map = k.grund;
    holzMat.roughnessMap = k.rau;
    holzMat.needsUpdate = true;
  }
  function metallSetzen(name) {
    const m = bauplan.material.metall[name];
    const f = m.grundfarbe;
    metallMat.color.setRGB(f[0], f[1], f[2], THREE.LinearSRGBColorSpace);
    metallMat.metalness = m.metallisch;
    metallMat.roughness = m.rauheit;
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
     ganzen Gestells — sonst wüchse das 12-mm-Wangenblech mit. Genau so steht
     es im Bauplan, und genau so wird es hier umgesetzt. */
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
    metallSetzen(WAHL.metall);
    stellungSetzen();
    nummerZeigen();
  };
  anwenden();

  /* -------------------------------------------------------------- Ansicht */
  let gier = -0.62, neigung = 0.30, ziehen = null, schwung = 0.0016;
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

  /* --------------------------------------------------------- Bildschleife */
  function messen() {
    const nummer = kasten.querySelector('[data-werkbank-nummer]');
    if (nummer) { nummer.textContent = artikel(); }
  }
  messen();

  let letzte = performance.now(), bilder = 0, seit = letzte, schwach = 0;
  function bild(jetzt) {
    if (kasten.getAttribute('data-werkbank-stufe') !== 'echtzeit') { return; }
    requestAnimationFrame(bild);
    const dt = Math.min((jetzt - letzte) / 1000, 0.1);
    letzte = jetzt;

    if (!ziehen) { gier += schwung * 60 * dt; }
    kameraSetzen();

    /* DIE LEINWAND MISST SICH SELBST, nicht den ganzen Kasten. Mit
       kasten.getBoundingClientRect() bekam sie die Breite von Buehne UND
       Reglerspalte zusammen und schob sich quer ueber die Knoepfe -- am
       fertigen Bild gesehen, 16.09.2026. clientWidth ist das, was das
       Stilblatt ihr zugewiesen hat: inset 0 in der Buehne. */
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
      melden(fps + ' Bilder/s · ' + Math.round(geladen / 1024) + ' KB geladen');
      /* Zwei Sekunden unter zehn Bildern: Dann ist es kein "etwas zäh",
         sondern unbenutzbar — und die gerechneten Bilder sind besser. */
      schwach = fps < 10 ? schwach + 1 : 0;
      if (schwach >= 2 && new URLSearchParams(location.search).get('werkbank') !== 'echtzeit') {
        renderer.setAnimationLoop(null);
        aufBilderZurueck('zu langsam — gerechnete Bilder');
        try { renderer.dispose(); } catch (e) {}
      }
    }
  }

  kasten.setAttribute('data-werkbank-stufe', 'echtzeit');
  const bildEl = kasten.querySelector('[data-werkbank-bild]');
  if (bildEl) { bildEl.hidden = true; }
  leinwand.hidden = false;
  requestAnimationFrame(bild);
}

/* ============================================================================
   Erst jetzt loslaufen: Alle Werte oben sind angelegt.
   ========================================================================== */
if (kasten) { starten(); }
