/* ==========================================================================
   villa-echtzeit.js — die Villa in Echtzeit, passgenau über dem Foto.

   WOZU
   Unter der Bühne liegt ein gerechnetes Foto aus Blender Cycles. Dieses
   Modul legt dasselbe Haus in Echtzeit darüber -- mit derselben Kamera,
   derselben Brennweite und demselben Objektivversatz. Solange jemand zieht,
   sieht er das Modell; lässt er los, fährt die Kamera auf den Standpunkt
   zurück, und die Seite blendet das Foto wieder ein. Stimmt die Kamera nicht
   aufs Grad, springt das Haus beim Überblenden -- deshalb steht die
   Kameramathematik hier so ausführlich.

   DIE KAMERA WIE IN BLENDER (villa_szene._kamera)
   Vollformat 36 x 24 mm, Sensor horizontal eingepasst. Außen bleibt die
   Kamera waagerecht, und der Ausschnitt wandert über den Objektivversatz
   (shift_y) -- stürzende Linien wären Urlaubsfoto, nicht Architektur.
   Innen ist sie geneigt, ohne Versatz. Beides wird hier nachgebaut, auch
   beim Drehen: Wer außen kreist, kreist mit waagerechter Kamera.

   DER BESCHNITT
   Die Fotos sind 16:9. Auf schmalen Bildschirmen wird die Bühne höher und
   das Foto links und rechts beschnitten (object-fit: cover). Die Projektion
   rechnet denselben Beschnitt nach, sonst läge das Modell auf dem Telefon
   neben dem Foto.

   KOORDINATEN
   Blender zählt z nach oben, glTF y. Alle Standpunkte stehen hier in
   Blender-Werten (wie in villa_szene.KAMERAS und haus-manifest.json) und
   werden erst beim Setzen umgerechnet: (x, y, z) -> (x, z, -y).
   ========================================================================== */
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';

const GLB = '/assets/3d/haus/haus.glb';
const MANIFEST = '/assets/3d/haus/haus-manifest.json';
const HIMMEL = '/assets/img/3d/haus/haus-himmel.webp';
const REF = 16 / 9;
const BEWEGUNG_AUS = matchMedia('(prefers-reduced-motion: reduce)').matches;

/* Dieselben Zahlen wie villa_szene.KAMERAS. Wer dort einen Standpunkt
   verschiebt, muss ihn hier nachziehen -- sonst liegt das Modell neben dem
   Foto. Die Prüfung steht in der Übergabe (PROJEKT.md). */
export const STAENDE = {
  garten:   { pos: [30.0, 30.5, 4.4],  ziel: [8.5, 8.0, 3.6],  lens: 35 },
  ankunft:  { pos: [-7.5, -13.0, 3.2], ziel: [7.0, 3.0, 2.6],  lens: 35 },
  terrasse: { pos: [17.5, 20.0, 2.6],  ziel: [7.0, 8.0, 2.6],  lens: 24 },
  wohnen:   { pos: [10.6, 4.9, 1.62],  ziel: [15.6, 9.6, 1.35], lens: 20, innen: true },
  /* Die Kueche stand hier bis zum 22.09.2026 an fuenfter Stelle. Ihr Blick
     (9,1 / 10,0 nach 5,8 / 5,4) zeigt zu zwei Dritteln eine leere Wand --
     im gerechneten Bild sofort zu sehen. Der Essplatz zeigt Tisch, Stuehle,
     das Fensterband und die Sonnenflecken auf dem Boden. */
  essen:    { pos: [16.4, 3.1, 1.6],   ziel: [7.6, 1.6, 1.3],  lens: 22, innen: true },
};

/* Sonnenstand wie lauf_ruhebilder.ZEITEN (Höhe, Azimut). Die übrigen Werte
   sind für Echtzeit abgestimmt: Sie sollen dem Foto nahekommen, nicht es
   ersetzen -- ohne Pfadverfolgung fehlt das indirekte Licht, und das
   gleichen Umgebung und Belichtung aus. */
const ZEITEN = {
  morgen:     { h: 12,  az: 100, sonne: 0xffd6b0, i: 3.2, env: 1.0,  bg: 1.8, bel: 2.6, himmel: 'tag',   nebel: 0xd9d6d2, innen: 0 },
  mittag:     { h: 60,  az: 185, sonne: 0xfff4e6, i: 4.4, env: 1.25, bg: 2.0, bel: 2.3, himmel: 'tag',   nebel: 0xc4d6e8, innen: 0 },
  nachmittag: { h: 27,  az: 249, sonne: 0xffe4c4, i: 4.0, env: 1.15, bg: 2.0, bel: 2.6, himmel: 'tag',   nebel: 0xc0d3e6, innen: 0 },
  abend:      { h: 4.5, az: 282, sonne: 0xffa05a, i: 2.4, env: 0.6,  bg: 1.3, bel: 3.0, himmel: 'abend', nebel: 0x9a8a88, innen: 1 },
  nacht:      { h: -4,  az: 300, sonne: 0x6f8cc8, i: 0.0, env: 0.35, bg: 1.0, bel: 3.4, himmel: 'nacht', nebel: 0x27314d, innen: 1 },
};
/* GEMESSEN, NICHT GESCHAETZT (22.09.2026): Vier Sonnenrichtungen gegen das
   Foto des Gartenblicks gerechnet und die Helligkeit im Hausbereich
   korreliert. Die naheliegende Umrechnung der Blender-Sonnenlampe traf
   0,39; gewonnen hat mit 0,73 die Richtung (-sin az, h, -cos az) -- das
   Licht kommt in den Fotos aus der Sonnenscheibe des Nishita-Himmels
   (villa_exr_gleich: "die Sonnenlampe steht ohnehin auf 0"), und der zaehlt
   seinen Winkel anders als die Lampe. Belichtung, Umgebung und Himmel sind
   an denselben Bildern abgestimmt: Ohne Pfadverfolgung fehlt das
   indirekte Licht, das gleicht die Umgebung aus. */

/* Leuchten im Haus (Blender-Werte), grob an den Deckenleuchten. */
const LEUCHTEN = [[2.8, 4.5, 2.5], [11.5, 2.1, 2.5], [7.45, 7.3, 2.5], [13.6, 7.3, 2.5], [10.0, 4.3, 5.9], [11.0, 9.5, 5.9]];

const b3 = (x, y, z) => new THREE.Vector3(x, z, -y);
const wickel = (a) => Math.atan2(Math.sin(a), Math.cos(a));

function verlauf(stopps) {
  const c = document.createElement('canvas'); c.width = 4; c.height = 256;
  const g = c.getContext('2d'); const v = g.createLinearGradient(0, 0, 0, 256);
  for (const [p, f] of stopps) v.addColorStop(p, f);
  g.fillStyle = v; g.fillRect(0, 0, 4, 256);
  const t = new THREE.CanvasTexture(c);
  t.mapping = THREE.EquirectangularReflectionMapping; t.colorSpace = THREE.SRGBColorSpace;
  return t;
}

/* Stand -> Kameraparameter. Außen: Zielpunkt, waagerechter Abstand,
   Winkel um den Zielpunkt, Kamerahöhe. Innen: fester Ort, Blickrichtung
   und Neigung. So lässt sich beides mit denselben Fingerbewegungen drehen
   und beim Zurückfahren einzeln dämpfen. */
/* Die Draufsicht ist keine Foto-Kamera, sondern ein Plan: senkrecht von
   oben, Norden oben. Damit das Haus formatfuellend liegt, haengt die Hoehe
   an der Brennweite und am Bildverhaeltnis -- gerechnet, nicht geraten. */
// Mitte zwischen Haus, Terrasse und Pool -- nicht die Hausmitte: sonst
// liegt das Becken ausserhalb des Bildes.
const MITTE = [9.0, 7.0];
export function draufParameter(lens = 26) {
  // 26 m ueber dem Boden: Bei 26 mm liegen damit rund 36 m Breite im Bild.
  // Bei 21 m stiess das Haus oben und rechts an -- nachgemessen am Bild.
  return { drauf: true, lens, mitte: MITTE, hoehe: 26 };
}
/* Begehung: Augenhoehe 1,65 m (haus-manifest: hoehen.augen), Blick frei.
   Gegangen wird nur dort, wo im Manifest ein Raum oder ein Durchgang ist. */
export function gehParameter(ort, gier) {
  return { gehen: true, lens: 24, ort: [ort[0], ort[1], 1.65], gier, neig: 0 };
}

function parameter(name) {
  const s = STAENDE[name];
  const [px, py, pz] = s.pos; const [zx, zy, zz] = s.ziel;
  if (s.innen) {
    const dx = zx - px, dy = zy - py, dz = zz - pz;
    return { innen: true, lens: s.lens, ort: [px, py, pz], gier: Math.atan2(dy, dx), neig: Math.atan2(dz, Math.hypot(dx, dy)) };
  }
  return { innen: false, lens: s.lens, ziel: [zx, zy, zz], abst: Math.hypot(px - zx, py - zy), winkel: Math.atan2(py - zy, px - zx), hoehe: pz };
}

export async function erstelle({ behaelter, stand = 'garten', zeit = 'nachmittag', einstellungen, bezeichnung, beiBewegung, beiRuhe, beiBild }) {
  const leinwand = document.createElement('canvas');
  const r = new THREE.WebGLRenderer({ canvas: leinwand, antialias: true, powerPreference: 'high-performance' });
  r.outputColorSpace = THREE.SRGBColorSpace;
  r.toneMapping = THREE.AgXToneMapping;
  r.shadowMap.enabled = true;
  r.shadowMap.type = THREE.PCFShadowMap;

  const szene = new THREE.Scene();
  const kamera = new THREE.PerspectiveCamera(30, REF, 0.08, 900);

  const lader = new THREE.TextureLoader();
  const himmelTag = await new Promise((ok) => lader.load(HIMMEL, (t) => {
    t.mapping = THREE.EquirectangularReflectionMapping; t.colorSpace = THREE.SRGBColorSpace; ok(t);
  }, undefined, () => ok(null)));
  const pmrem = new THREE.PMREMGenerator(r);
  const umgebungTag = himmelTag ? pmrem.fromEquirectangular(himmelTag).texture : null;
  const himmelAbend = verlauf([[0, '#5d7392'], [0.38, '#b9b3b5'], [0.47, '#f0c49a'], [0.5, '#ffd9ad'], [0.52, '#3a3228'], [1, '#1a1612']]);
  const himmelNacht = verlauf([[0, '#2a3f78'], [0.4, '#6d7fb0'], [0.48, '#c0a8c0'], [0.5, '#d9b7b4'], [0.52, '#10131c'], [1, '#07080c']]);
  const umgebungAbend = pmrem.fromEquirectangular(himmelAbend).texture;
  const umgebungNacht = pmrem.fromEquirectangular(himmelNacht).texture;
  pmrem.dispose();

  const nebel = new THREE.Fog(0xc0d3e6, 140, 520);
  const sonne = new THREE.DirectionalLight(0xfff0dc, 3.1);
  const sonnenZiel = new THREE.Object3D(); sonnenZiel.position.copy(b3(9, 5.5, 0));
  szene.add(sonnenZiel); sonne.target = sonnenZiel; szene.add(sonne);
  Object.assign(sonne.shadow.camera, { near: 1, far: 140, left: -30, right: 30, top: 30, bottom: -30 });
  sonne.shadow.bias = -0.0005; sonne.shadow.normalBias = 0.025;
  const halb = new THREE.HemisphereLight(0xcfdcf0, 0x3c3a30, 0);
  szene.add(halb);
  const leuchten = LEUCHTEN.map(([x, y, z]) => {
    const l = new THREE.PointLight(0xffb978, 0, 14, 2); l.position.copy(b3(x, y, z)); szene.add(l); return l;
  });

  const gltf = await new GLTFLoader().loadAsync(GLB);
  const haus = gltf.scene; szene.add(haus);
  const schirme = new Set(); const materialien = new Set(); let dreiecke = 0;
  /* Nach Bauabschnitt sortiert (p01_ bis p08_, Praefixe aus
     haus-manifest.json). Damit laesst sich das Haus Schritt fuer Schritt
     aufbauen und fuer den Grundriss die Decke abnehmen. */
  const nachAbschnitt = new Map();
  const oben = [];
  const kasten = new THREE.Box3();
  haus.traverse((o) => {
    if (!o.isMesh) return;
    /* Die alte Kegelreihe aus villa.py steht noch im Web-Modell. In den
       Fotos ist sie laengst durch gebaute Zypressen an der Westgrenze
       ersetzt -- im Modell stuende sie zwischen Kamera und Haus, und beim
       Ueberblenden wuechsen fuenf gruene Kegel aus dem Rasen. */
    const idx = o.geometry.index; dreiecke += (idx ? idx.count : o.geometry.attributes.position.count) / 3;
    if (/zypresse/i.test(o.name)) { o.visible = false; return; }
    o.castShadow = true; o.receiveShadow = true;
    const m = o.material; if (!m) return;
    materialien.add(m);
    if (m.transparent || /glas/i.test(m.name)) o.castShadow = false;
    if (m.name === 'Leuchtenschirm') schirme.add(m);
    const t = /^p0(\d)_/.exec(o.name);
    if (t) {
      const nr = Number(t[1]);
      if (!nachAbschnitt.has(nr)) nachAbschnitt.set(nr, []);
      nachAbschnitt.get(nr).push(o);
    }
    // Was ueber 3,2 m beginnt, gehoert ins Obergeschoss oder aufs Dach --
    // im Grundriss faellt es weg, sonst sieht man nur die Attika.
    kasten.setFromObject(o);
    if (kasten.min.y > 3.2) oben.push(o);
    // Poolwasser: im Web-Modell ein helles, stumpfes Blau. In den Fotos ist
    // das Becken dunkel und spiegelt -- so sieht Wasser aus, wenn man von
    // der Seite draufschaut.
    if (/wasser|pool_w/i.test(m.name) && m.isMeshStandardMaterial) {
      m.color.set(0x0c2129); m.roughness = 0.03; m.metalness = 0; m.transparent = false; m.opacity = 1;
      if ('transmission' in m) m.transmission = 0;
      // Die Umgebung ist hier nur Himmel. Im Foto spiegelt das Becken Haus
      // und Baeume -- also dunkler. Voll gespiegelt stuende ein hellblauer
      // Spiegel im Rasen.
      m.envMapIntensity = 0.45;
    }
  });

  /* Grundriss und Begehung brauchen die Maße, nicht das Modell: Raumrechtecke
     und Türdurchgänge stehen im Manifest, aus derselben Quelle wie das GLB.
     Zwei getrennte Beschreibungen desselben Hauses laufen sonst auseinander
     (dieselbe Überlegung wie in haus.js). */
  let plan = null;
  try { plan = await fetch(MANIFEST).then((a) => a.json()); } catch { plan = null; }
  const raeume = plan && plan.geschosse && plan.geschosse[0] ? plan.geschosse[0].raeume : [];
  const durchgaenge = plan ? plan.durchgaenge || [] : [];
  const rundgang = plan ? plan.rundgang || [] : [];

  let modus = 'stand'; let bauschritt = 8;
  function sicht() {
    const grundriss = modus === 'grundriss';
    for (const o of oben) o.visible = !grundriss;
    for (const [nr, liste] of nachAbschnitt) {
      const an = modus === 'bau' ? nr <= bauschritt
        : grundriss ? nr !== 3 && nr !== 4 && nr !== 5
        : true;
      for (const o of liste) { if (!/zypresse/i.test(o.name)) o.visible = an; }
    }
  }

  /* Darf die Kamera dorthin? Erlaubt ist, was in einem Raum des Erdgeschosses
     oder in einem Türdurchgang liegt. Das kostet nichts, faehrt nie durch eine
     Wand und kommt aus derselben Quelle wie der Grundriss. */
  function begehbar(x, y) {
    const rand = 0.35;
    for (const r of raeume) {
      if (x > r.x0 + rand && x < r.x1 - rand && y > r.y0 + rand && y < r.y1 - rand) return true;
    }
    for (const d of durchgaenge) {
      if (x > d.x0 - 0.1 && x < d.x1 + 0.1 && y > d.y0 - 0.5 && y < d.y1 + 0.5) return true;
    }
    return false;
  }

  /* Wo die Begehung anfaengt.

     Vorher stand hier rundgang[0] -- und das war ein Fehler, den erst ein
     Blick aufs Bild gezeigt hat: Der erste Wegpunkt im Manifest ist der
     Fotostandpunkt "Ankunft" bei (3.1, -5.4), also draussen auf dem Vorplatz.
     Begehbar ist aber nur, was im Manifest ein Raum oder ein Tuerdurchgang
     ist, und das faengt erst bei y = 0,75 an. Der Besucher stand damit mit
     der Nase an der Haustuer und konnte keinen Schritt gehen -- keine Taste
     tat etwas, weil jede Achse einzeln an der Wand abgewiesen wurde.

     Der Startpunkt ist jetzt am Bild gewaehlt -- elf Kandidaten gerechnet
     und nebeneinandergelegt: (11,6 | 2,2), Blick 40 Grad. Von dort steht die
     Sitzgruppe mittig im Bild, die Eckverglasung geht nach rechts weg, und
     kein Wandstueck frisst ein Drittel des Bildes. Die naheliegenden
     Wegpunkte taugen dafuer nicht: Das Entree zeigt eine Treppenwand, der
     Essplatz eine dunkle Ecke. Ist der Punkt einmal nicht mehr begehbar,
     wird der erste begehbare Wegpunkt genommen und erst dann die Mitte des
     groessten Raums -- damit die Begehung nie wieder an einer Wand anfaengt. */
  const GEH_HEIM = [11.6, 2.2]; const GEH_HEIM_BLICK = 40;

  function gehStart() {
    if (begehbar(GEH_HEIM[0], GEH_HEIM[1])) return GEH_HEIM;
    const w = rundgang.find(p => !p.z && begehbar(p.x, p.y));
    if (w) return [w.x, w.y];
    const r = raeume.slice().sort((a, b) => (b.x1 - b.x0) * (b.y1 - b.y0) - (a.x1 - a.x0) * (a.y1 - a.y0))[0];
    return r ? [(r.x0 + r.x1) / 2, (r.y0 + r.y1) / 2] : [11.6, 2.2];
  }
  function gehBlick() {
    if (begehbar(GEH_HEIM[0], GEH_HEIM[1])) return THREE.MathUtils.degToRad(90 - GEH_HEIM_BLICK);
    const w = rundgang.find(p => !p.z && begehbar(p.x, p.y));
    return THREE.MathUtils.degToRad(90 - (w ? w.blick || 0 : 0));
  }

  const tasten = new Set();
  const GEH_TASTEN = { KeyW: [1, 0], KeyS: [-1, 0], KeyA: [0, -1], KeyD: [0, 1], ArrowUp: [1, 0], ArrowDown: [-1, 0] };

  behaelter.appendChild(leinwand);
  if (bezeichnung) leinwand.setAttribute('aria-label', bezeichnung);

  /* ---------------------------------------------------------------- Kamera */
  let soll = parameter(stand);           // wohin die Kamera will
  let ist = { ...soll };                 // wo sie gerade ist
  let heim = parameter(stand);           // der Standpunkt des Fotos
  let ziehen = null; let letzteBewegung = performance.now(); let ruhtGemeldet = true;

  function projektion(p, w, h) {
    const a = w / h;
    const tanH = 18 / p.lens;
    const tanV = tanH / REF;
    const tanVc = a <= REF ? tanV : tanH / a;
    kamera.aspect = a;
    kamera.fov = THREE.MathUtils.radToDeg(2 * Math.atan(tanVc));
    kamera.updateProjectionMatrix();
    if (!p.innen && !p.gehen && !p.drauf) {
      // shift_y wie in villa_szene._kamera, in Sensorbreiten des 16:9-Bildes.
      const [zx, zy, zz] = p.ziel;
      const cx = zx + Math.cos(p.winkel) * p.abst; const cy = zy + Math.sin(p.winkel) * p.abst;
      const waag = Math.hypot(zx - cx, zy - cy) || 1;
      const shift = ((zz - p.hoehe) / waag) * (p.lens / 36);
      kamera.projectionMatrix.elements[9] = (shift * 2 * tanH) / tanVc;
      kamera.projectionMatrixInverse.copy(kamera.projectionMatrix).invert();
    }
  }

  function kameraSetzen(p) {
    if (p.drauf) {
      // Norden oben: der Aufblick braucht einen eigenen Oben-Vektor, sonst
      // dreht three.js den Plan um seine eigene Achse.
      kamera.up.set(0, 0, -1);
      kamera.position.copy(b3(p.mitte[0], p.mitte[1], p.hoehe));
      kamera.lookAt(b3(p.mitte[0], p.mitte[1], 0));
      projektion(p, leinwand.clientWidth || 16, leinwand.clientHeight || 9);
      return;
    }
    kamera.up.set(0, 1, 0);
    if (p.gehen || p.innen) {
      const [x, y, z] = p.ort;
      kamera.position.copy(b3(x, y, z));
      const d = new THREE.Vector3(Math.cos(p.gier) * Math.cos(p.neig), Math.sin(p.gier) * Math.cos(p.neig), Math.sin(p.neig));
      kamera.lookAt(b3(x + d.x, y + d.y, z + d.z));
    } else {
      const [zx, zy] = p.ziel;
      const cx = zx + Math.cos(p.winkel) * p.abst; const cy = zy + Math.sin(p.winkel) * p.abst;
      kamera.position.copy(b3(cx, cy, p.hoehe));
      // waagerecht auf den Zielpunkt -- die Höhe regelt der Versatz
      kamera.lookAt(b3(zx, zy, p.hoehe));
    }
    projektion(p, leinwand.clientWidth || 16, leinwand.clientHeight || 9);
  }

  function annaehern(k) {
    let rest = 0;
    if (!!ist.innen !== !!soll.innen || !!ist.drauf !== !!soll.drauf || !!ist.gehen !== !!soll.gehen || ist.lens !== soll.lens) { ist = { ...soll }; return 0; }
    if (soll.drauf) return 0;
    if (soll.innen || soll.gehen) {
      const dg = wickel(soll.gier - ist.gier); ist.gier += dg * k; ist.neig += (soll.neig - ist.neig) * k;
      rest = Math.abs(dg) + Math.abs(soll.neig - ist.neig);
      if (soll.gehen) {
        ist.ort = ist.ort.map((w, i) => w + (soll.ort[i] - w) * k);
        rest += Math.abs(soll.ort[0] - ist.ort[0]) * 0.2 + Math.abs(soll.ort[1] - ist.ort[1]) * 0.2;
      }
    } else {
      const dw = wickel(soll.winkel - ist.winkel); ist.winkel += dw * k;
      ist.hoehe += (soll.hoehe - ist.hoehe) * k; ist.abst += (soll.abst - ist.abst) * k;
      rest = Math.abs(dw) + Math.abs(soll.hoehe - ist.hoehe) * 0.05 + Math.abs(soll.abst - ist.abst) * 0.02;
    }
    return rest;
  }

  /* ---------------------------------------------------------------- Finger */
  leinwand.style.touchAction = 'pan-y';
  leinwand.addEventListener('pointerdown', (e) => {
    if (e.button !== 0) return;
    ziehen = { x: e.clientX, y: e.clientY, id: e.pointerId };
    leinwand.setPointerCapture(e.pointerId);
    ruhtGemeldet = false; letzteBewegung = performance.now();
    beiBewegung && beiBewegung();
    starten();
  });
  leinwand.addEventListener('pointermove', (e) => {
    if (!ziehen || e.pointerId !== ziehen.id) return;
    const dx = e.clientX - ziehen.x, dy = e.clientY - ziehen.y;
    ziehen.x = e.clientX; ziehen.y = e.clientY;
    if (soll.drauf) return;
    if (soll.innen || soll.gehen) {
      soll.gier -= dx * 0.004;
      soll.neig = Math.max(-0.6, Math.min(0.45, soll.neig - dy * 0.003));
    } else {
      soll.winkel -= dx * 0.005;
      soll.hoehe = Math.max(1.3, Math.min(16, soll.hoehe + dy * 0.03));
    }
    letzteBewegung = performance.now();
  });
  const los = (e) => { if (ziehen && e.pointerId === ziehen.id) { ziehen = null; letzteBewegung = performance.now(); } };
  /* Tastatur: Pfeile drehen wie der Finger. Die Leinwand ist fokussierbar,
     sobald es sie gibt; wer mit Tab ankommt, bekommt dasselbe Modell. */
  leinwand.tabIndex = 0;
  leinwand.addEventListener('keyup', (e) => { tasten.delete(e.code); });
  leinwand.addEventListener('blur', () => tasten.clear());
  leinwand.addEventListener('keydown', (e) => {
    if (modus === 'gehen' && GEH_TASTEN[e.code]) {
      e.preventDefault(); tasten.add(e.code);
      ruhtGemeldet = false; letzteBewegung = performance.now();
      beiBewegung && beiBewegung(); starten();
      return;
    }
    const t = { ArrowLeft: [1, 0], ArrowRight: [-1, 0], ArrowUp: [0, 1], ArrowDown: [0, -1] }[e.key];
    if (!t) return;
    e.preventDefault();
    if (soll.innen) { soll.gier += t[0] * 0.09; soll.neig = Math.max(-0.6, Math.min(0.45, soll.neig + t[1] * 0.05)); }
    else { soll.winkel += t[0] * 0.09; soll.hoehe = Math.max(1.3, Math.min(16, soll.hoehe + t[1] * 0.6)); }
    ruhtGemeldet = false; letzteBewegung = performance.now();
    beiBewegung && beiBewegung();
    starten();
  });
  leinwand.addEventListener('pointerup', los);
  leinwand.addEventListener('pointercancel', los);
  leinwand.addEventListener('lostpointercapture', los);

  /* ---------------------------------------------------------- Licht und Zeit */
  let zeitJetzt = zeit;
  let einst = einstellungen;
  function lichtSetzen() {
    const z = ZEITEN[zeitJetzt] || ZEITEN.nachmittag;
    const hr = THREE.MathUtils.degToRad(z.h), ar = THREE.MathUtils.degToRad(z.az);
    const richt = new THREE.Vector3(-Math.sin(ar) * Math.cos(hr), Math.sin(hr), -Math.cos(ar) * Math.cos(hr));
    sonne.position.copy(sonnenZiel.position).addScaledVector(richt, 60);
    sonne.color.set(z.sonne); sonne.intensity = z.h > 0 ? z.i : 0;
    sonne.castShadow = einst.schatten > 0 && z.h > 0;
    szene.background = z.himmel === 'tag' ? himmelTag : z.himmel === 'abend' ? himmelAbend : himmelNacht;
    szene.environment = z.himmel === 'tag' ? umgebungTag : z.himmel === 'abend' ? umgebungAbend : umgebungNacht;
    szene.environmentIntensity = z.env;
    szene.backgroundIntensity = z.bg;
    nebel.color.set(z.nebel); szene.fog = nebel;
    halb.intensity = einst.schatten ? 0 : 0.35 * z.env;
    r.toneMappingExposure = z.bel;
    for (const l of leuchten) l.intensity = z.innen ? 26 : 0;
    for (const m of schirme) {
      if (!m.emissive) continue;
      m.emissive.set(z.innen ? 0xffc890 : 0x000000); m.emissiveIntensity = z.innen ? 2.4 : 0;
    }
  }

  let schattenAn = null; let schattenGroesse = 0;
  function stufeSetzen(e) {
    einst = e;
    const dpr = Math.min(window.devicePixelRatio || 1, e.pixel);
    r.setPixelRatio(dpr);
    const an = e.schatten > 0;
    if (an !== schattenAn) { r.shadowMap.enabled = an; for (const m of materialien) m.needsUpdate = true; schattenAn = an; }
    if (an && schattenGroesse !== e.schatten) {
      sonne.shadow.mapSize.set(e.schatten, e.schatten);
      if (sonne.shadow.map) { sonne.shadow.map.dispose(); sonne.shadow.map = null; }
      schattenGroesse = e.schatten;
    }
    sonne.shadow.radius = e.schatten >= 2048 ? 3 : 1.5;
    lichtSetzen();
    groesse();
  }

  function groesse() {
    const w = Math.max(1, behaelter.clientWidth), h = Math.max(1, behaelter.clientHeight);
    r.setSize(w, h, false);
    kameraSetzen(ist);
    einmal();
  }
  const ro = new ResizeObserver(groesse); ro.observe(behaelter);

  /* ------------------------------------------------------------ Bildschleife */
  let laeuft = false; let aktiv = false; let letzt = 0; let bilder = 0; let seit = 0; let fps = 0;
  function einmal() { r.render(szene, kamera); }
  function starten() { aktiv = true; if (!laeuft) { laeuft = true; letzt = 0; seit = 0; bilder = 0; requestAnimationFrame(bild); } }
  function anhalten() { aktiv = false; }
  function bild(t) {
    if (!aktiv || document.hidden) { laeuft = false; return; }
    const dt = letzt ? Math.min(0.25, (t - letzt) / 1000) : 1 / 60; letzt = t;
    // Zurück zum Standpunkt, sobald niemand mehr zieht. 1,4 s ist so lang,
    // dass ein zweiter Griff nicht gegen die Rückfahrt kämpft, und so kurz,
    // dass man den Zusammenhang Modell -> Foto noch sieht.
    if (modus === 'gehen' && tasten.size) {
      // 1,35 m/s -- ein ruhiger Schritt. Wer rennt, sieht nichts.
      let vx = 0, vy = 0;
      for (const c of tasten) { const t = GEH_TASTEN[c]; if (t) { vx += t[0]; vy += t[1]; } }
      const laenge = Math.hypot(vx, vy) || 1;
      const s = 1.35 * dt / laenge;
      const vor = [Math.cos(soll.gier), Math.sin(soll.gier)];
      const quer = [-vor[1], vor[0]];
      const nx = soll.ort[0] + (vor[0] * vx + quer[0] * vy) * s;
      const ny = soll.ort[1] + (vor[1] * vx + quer[1] * vy) * s;
      // Achsen einzeln pruefen: an einer Wand entlang geht es weiter, statt
      // dass der Schritt ganz verfaellt.
      if (begehbar(nx, soll.ort[1])) soll.ort[0] = nx;
      if (begehbar(soll.ort[0], ny)) soll.ort[1] = ny;
      letzteBewegung = performance.now();
    }
    // Nur der Foto-Modus faehrt von selbst auf den Standpunkt zurueck --
    // im Grundriss, im Bauablauf und beim Gehen waere das eine Entmuendigung.
    if (modus === 'stand' && !ziehen && performance.now() - letzteBewegung > 1400) soll = { ...heim };
    const k = BEWEGUNG_AUS ? 1 : 1 - Math.exp(-dt * (ziehen ? 9 : 2.6));
    const rest = annaehern(k);
    kameraSetzen(ist);
    r.render(szene, kamera);
    bilder++;
    if (!seit) seit = t;
    if (t - seit >= 500) { fps = Math.round((bilder * 1000) / (t - seit)); bilder = 0; seit = t; }
    beiBild && beiBild(dt * 1000, fps, false);
    if (modus === 'stand' && !ziehen && rest < 0.002 && performance.now() - letzteBewegung > 1400 && !ruhtGemeldet) {
      ruhtGemeldet = true; ist = { ...heim }; kameraSetzen(ist); r.render(szene, kamera);
      beiRuhe && beiRuhe();
    }
    // Steht die Kamera und zieht niemand, gibt es nichts Neues zu zeichnen.
    // Die Schleife ruht dann, bis der naechste Finger kommt -- ein Laptop
    // soll nicht fuer ein stehendes Bild die Grafikkarte heizen.
    else if (!ziehen && !tasten.size && ruhtGemeldet && rest < 0.0005 && performance.now() - letzteBewegung > 6000) {
      laeuft = false; aktiv = false;
      beiBild && beiBild(dt * 1000, fps, true);
      return;
    }
    requestAnimationFrame(bild);
  }
  document.addEventListener('visibilitychange', () => { if (!document.hidden && aktiv && !laeuft) starten(); });

  stufeSetzen(einstellungen);

  return {
    /* Standwechsel springt: Die Seite blendet in diesem Moment ohnehin das
       Foto des neuen Standpunkts ein. Eine Fahrt zwischen Garten und Küche
       ginge durch Wände. */
    stand(name) {
      modus = 'stand'; sicht();
      heim = parameter(name); soll = { ...heim }; ist = { ...heim }; ruhtGemeldet = true;
      kameraSetzen(ist); einmal();
    },
    /* Grundriss, Bauablauf, Begehung -- die drei Ansichten aus dem alten
       Haus-Labor, jetzt in derselben Buehne. Sie kehren nicht von selbst zum
       Foto zurueck; das tut nur der Standpunkt-Modus. */
    ansicht(name, schritt) {
      modus = name === 'grundriss' || name === 'bau' || name === 'gehen' ? name : 'stand';
      if (schritt) bauschritt = Math.max(1, Math.min(8, schritt));
      sicht();
      if (modus === 'grundriss') heim = draufParameter();
      else if (modus === 'bau') heim = parameter('ankunft');
      else if (modus === 'gehen') heim = gehParameter(gehStart(), gehBlick());
      else heim = parameter('garten');
      soll = { ...heim, ort: heim.ort ? [...heim.ort] : undefined };
      ist = { ...soll, ort: soll.ort ? [...soll.ort] : undefined };
      ruhtGemeldet = true; letzteBewegung = performance.now();
      kameraSetzen(ist); einmal();
      if (modus !== 'stand') { leinwand.focus({ preventScroll: true }); starten(); }
    },
    schritt(nr) { bauschritt = Math.max(1, Math.min(8, nr)); sicht(); einmal(); },
    get modus() { return modus; },
    zeit(name) { zeitJetzt = name; lichtSetzen(); einmal(); },
    stufe: stufeSetzen,
    starten, anhalten,
    get fps() { return fps; },
    /* Nur fuer die Pruefung (?pruefen in der Adresse): Sonnenrichtung in
       three-Koordinaten setzen, um sie gegen das Foto zu messen. */
    _licht(o) {
      if (o.bel !== undefined) r.toneMappingExposure = o.bel;
      if (o.env !== undefined) szene.environmentIntensity = o.env;
      if (o.sonne !== undefined) sonne.intensity = o.sonne;
      if (o.bg !== undefined) szene.backgroundIntensity = o.bg;
      if (o.fill !== undefined) halb.intensity = o.fill;
      einmal();
    },
    _sonne(x, y, z) { sonne.position.copy(sonnenZiel.position).addScaledVector(new THREE.Vector3(x, y, z).normalize(), 60); einmal(); },
    /* Startpunkt der Begehung von aussen setzen, um ihn am Bild zu waehlen
       statt ihn zu schaetzen. Blick in Grad wie im Manifest: 0 = nach Norden. */
    _wo() { return soll.ort ? { x: +soll.ort[0].toFixed(2), y: +soll.ort[1].toFixed(2), gier: Math.round(THREE.MathUtils.radToDeg(soll.gier)) } : null; },
    _geh(x, y, blick) {
      heim = gehParameter([x, y], THREE.MathUtils.degToRad(90 - blick));
      soll = { ...heim, ort: [...heim.ort] };
      ist = { ...soll, ort: [...soll.ort] };
      kameraSetzen(ist); einmal();
    },
    info() { return { renderer: 'WebGL 2', dreiecke: Math.round(dreiecke), pixel: r.getPixelRatio(), schatten: schattenAn ? schattenGroesse : 0 }; },
    entsorgen() {
      anhalten(); ro.disconnect();
      r.dispose(); leinwand.remove();
    },
  };
}
