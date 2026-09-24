/* ==========================================================================
   kuechenplaner-3d.js — Echtzeit-Szene des Küchenplaners.

   Baut aus einem Plan (kuechenplaner.js) eine Küche nach Küchennorm:
   Sockel 150 mm, Korpus 720 mm (Tiefe 560), Front 19 mm, Arbeitsplatte
   40 mm (Tiefe 620, Überstand 20 mm), Arbeitshöhe 910 mm, Hängeschränke
   720 mm hoch ab 1450 mm, Hochschränke bis 2170 mm, Fugen 3 mm. Alles in
   Metern, y oben; Wand A liegt bei z = 0 und läuft nach +x, Wand B (L-Form)
   bei x = 0 und läuft nach +z; die Insel steht 1,20 m vor der Zeile.

   Materialien sind dieselben Karten wie im Blender-Modell der Kochinsel
   (Eiche, Carrara, Keramik, Nussbaum, Kochfeld-Siebdruck). UV in Metern aus
   Weltkoordinaten, damit Furnier und Stein über Fugen weiterlaufen.
   ========================================================================== */
import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

const M = {
  SOCKEL: 0.15, KORPUS: 0.72, PLATTE: 0.04, TIEFE: 0.56, FRONT: 0.019, FUGE: 0.003,
  PT: 0.62, OB_UNTEN: 1.45, OB_H: 0.72, OB_T: 0.35, HOCH: 2.17, WAND_H: 2.60, GANG: 1.20,
};
M.ARBEIT = M.SOCKEL + M.KORPUS + M.PLATTE;   // 0,91

const PFAD = new URL('../../3d/branchen/kuechenplaner/', import.meta.url).href;

/* ------------------------------------------------------------ Materialien */
function karte(url, farbig, kachel, anis) {
  const t = new THREE.TextureLoader().load(url);
  t.wrapS = t.wrapT = THREE.RepeatWrapping;
  t.repeat.set(1 / kachel, 1 / kachel);
  if (farbig) t.colorSpace = THREE.SRGBColorSpace;
  t.anisotropy = anis;
  return t;
}

export function materialien(renderer) {
  const an = Math.min(8, renderer.capabilities.getMaxAnisotropy());
  const K = (n, f, k) => karte(PFAD + n, f, k, an);
  /* Moderne Küche (24.09.2026, K1): dieselben Oberflächen wie im Render --
     Eiche furniert, Kaschmir und Tiefschwarz supermatt, Keramik Calacatta Oro
     und Nero, Griffmulde in Champagner. Steinkarten laden erst, wenn die
     Platte gewählt wird (spaet), damit der Planer nicht 0,5 MB vorab zieht. */
  const spaet = (mat, laden) => { mat.userData.laden = laden; return mat; };
  const m = {
    front: {
      furnier: new THREE.MeshPhysicalMaterial({ map: K('furnier-farbe.webp', true, 1.83), normalMap: K('furnier-normal.webp', false, 1.83), normalScale: new THREE.Vector2(0.3, 0.3), roughness: 0.46, sheen: 0.15, sheenRoughness: 0.6, sheenColor: 0x6b4a2c }),
      kaschmir: new THREE.MeshPhysicalMaterial({ color: 0xb5aa9a, roughness: 0.62 }),
      tiefschwarz: new THREE.MeshPhysicalMaterial({ color: 0x19191a, roughness: 0.55 }),
      weiss: new THREE.MeshPhysicalMaterial({ color: 0xe6e4df, roughness: 0.42, clearcoat: 0.2, clearcoatRoughness: 0.4 }),
    },
    platte: {
      oro: spaet(new THREE.MeshPhysicalMaterial({ color: 0xffffff, roughness: 0.3 }), (mm) => { mm.map = K('oro-farbe.webp', true, 1.6); }),
      nero: spaet(new THREE.MeshPhysicalMaterial({ color: 0xffffff, roughness: 0.32 }), (mm) => { mm.map = K('nero-farbe.webp', true, 1.6); }),
      keramik: spaet(new THREE.MeshPhysicalMaterial({ roughness: 0.58, normalScale: new THREE.Vector2(0.12, 0.12) }), (mm) => { mm.map = K('keramik-farbe.webp', true, 1.2); mm.normalMap = K('keramik-normal.webp', false, 1.2); }),
      eiche: spaet(new THREE.MeshPhysicalMaterial({ roughness: 0.5, normalScale: new THREE.Vector2(0.35, 0.35) }), (mm) => { mm.map = K('eiche-farbe.webp', true, 2.4); mm.normalMap = K('eiche-normal.webp', false, 2.4); }),
    },
    griff: {
      // Grifflos: die Mulde ist ein Profil in Champagner, wie im Render
      grifflos: new THREE.MeshPhysicalMaterial({ color: 0xbd9a5f, metalness: 1, roughness: 0.34 }),
      messing: new THREE.MeshPhysicalMaterial({ color: 0xd8bb86, metalness: 1, roughness: 0.28 }),
      edelstahl: new THREE.MeshPhysicalMaterial({ color: 0xc9c9c6, metalness: 1, roughness: 0.26 }),
      schwarz: new THREE.MeshPhysicalMaterial({ color: 0x161615, metalness: 0, roughness: 0.45 }),
    },
    korpus: new THREE.MeshStandardMaterial({ color: 0xdad9d5, roughness: 0.5 }),
    zarge: new THREE.MeshStandardMaterial({ color: 0x3e3d3c, metalness: 0.6, roughness: 0.35 }),
    sockel: new THREE.MeshStandardMaterial({ color: 0x1a1a1b, roughness: 0.6 }),
    edelstahl: new THREE.MeshPhysicalMaterial({ color: 0xbfbfbc, metalness: 1, roughness: 0.22 }),
    glasSchwarz: new THREE.MeshPhysicalMaterial({ color: 0x060605, roughness: 0.04, clearcoat: 1, clearcoatRoughness: 0.03 }),
    kochfeld: new THREE.MeshPhysicalMaterial({ map: K('kochfeld.webp', true, 1), roughness: 0.05, clearcoat: 1, clearcoatRoughness: 0.02 }),
    // Boden: Eichendielen wie im Render (vorher gezeichnete Fliesen); Wände Kalkputz
    boden: new THREE.MeshStandardMaterial({ map: K('diele-farbe.webp', true, 1.7), normalMap: K('diele-normal.webp', false, 1.7), normalScale: new THREE.Vector2(0.5, 0.5), roughness: 0.42 }),
    wand: new THREE.MeshStandardMaterial({ map: K('putz-farbe.webp', true, 2.0), roughness: 0.92 }),
    led: new THREE.MeshStandardMaterial({ color: 0xfff4e2, emissive: 0xfff1dc, emissiveIntensity: 2.2 }),
    auswahl: new THREE.MeshBasicMaterial({ color: 0xf1d38b, transparent: true, opacity: 0.22, depthWrite: false }),
    zuviel: new THREE.MeshBasicMaterial({ color: 0xff4a4a, transparent: true, opacity: 0.28, depthWrite: false }),
    porzellan: new THREE.MeshPhysicalMaterial({ color: 0xf2f1ee, roughness: 0.08, clearcoat: 0.5 }),
    glasKlar: new THREE.MeshPhysicalMaterial({ color: 0xe8eeee, roughness: 0.05, transmission: 0.85, thickness: 0.004, ior: 1.5 }),
    einsatz: new THREE.MeshStandardMaterial({ color: 0xb58c62, roughness: 0.55 }),
    schirm: new THREE.MeshPhysicalMaterial({ color: 0x1f1f1e, roughness: 0.4, side: THREE.DoubleSide }),
    birne: new THREE.MeshStandardMaterial({ color: 0xfff1d6, emissive: 0xffd9a0, emissiveIntensity: 6 }),
  };
  m.kochfeld.map.repeat.set(1, 1);
  m.bereit = (mat) => { if (mat && mat.userData.laden) { mat.userData.laden(mat); delete mat.userData.laden; mat.needsUpdate = true; } return mat; };
  return m;
}

/* ------------------------------------------------------------ Geometrie-Helfer */
// UV in Metern aus Weltkoordinaten; hoch = Maserung senkrecht (Fronten)
function uvWelt(g, hoch) {
  g.computeVertexNormals();
  const p = g.attributes.position, n = g.attributes.normal;
  const uv = new Float32Array(p.count * 2);
  for (let i = 0; i < p.count; i++) {
    const ax = Math.abs(n.getX(i)), ay = Math.abs(n.getY(i)), az = Math.abs(n.getZ(i));
    let u, v;
    if (ay >= ax && ay >= az) { u = p.getX(i); v = p.getZ(i); }
    else if (az >= ax) { u = p.getX(i); v = p.getY(i); }
    else { u = p.getZ(i); v = p.getY(i); }
    if (hoch && ay < Math.max(ax, az)) [u, v] = [v, u];
    uv[i * 2] = u; uv[i * 2 + 1] = v;
  }
  g.setAttribute('uv', new THREE.BufferAttribute(uv, 2));
  return g;
}
// Quader in Weltlage (Mitte cx, cy, cz; Maße sx, sy, sz), gerundet r
function quader(sx, sy, sz, cx, cy, cz, r = 0.0015, hoch = false) {
  const g = r > 0 ? new RoundedBoxGeometry(sx, sy, sz, 2, Math.min(r, sx / 2 - 1e-4, sy / 2 - 1e-4, sz / 2 - 1e-4)) : new THREE.BoxGeometry(sx, sy, sz);
  g.translate(cx, cy, cz);
  return uvWelt(g, hoch);
}
function netz(g, mat, schatten = true) {
  const o = new THREE.Mesh(g, mat); o.castShadow = schatten; o.receiveShadow = true; return o;
}

/* Lauf-Koordinaten: u entlang der Wand, v von der Wand weg, y hoch.
   Wand A: x = u, z = v.  Wand B: x = v, z = u.  Insel: gespiegelt (Fronten nach -z).
   Wand B und Insel sind Spiegelungen (Determinante -1): Drehsinn und Wicklung
   kippen dort. Deshalb drehen Türen nicht um einen Winkel mit Vorzeichen je
   Lauf, sondern um eine Achse aus Weltvektoren (Kante x vorn) -- die stimmt
   in jeder Lage, ohne Fallunterscheidung. */
function lage(lauf) {
  let L;
  if (lauf.wand === 'b') L = { p: (u, y, v) => [v, y, u], s: (su, sy, sv) => [sv, sy, su], dreh: -Math.PI / 2, spiegel: true };
  else if (lauf.wand === 'insel') {
    const z0 = lauf.z0, x0 = lauf.x0;
    L = { p: (u, y, v) => [x0 + u, y, z0 + M.PT - v], s: (su, sy, sv) => [su, sy, sv], dreh: Math.PI, spiegel: true };
  } else L = { p: (u, y, v) => [u, y, v], s: (su, sy, sv) => [su, sy, sv], dreh: 0, spiegel: false };
  const o = new THREE.Vector3(...L.p(0, 0, 0));
  L.udir = new THREE.Vector3(...L.p(1, 0, 0)).sub(o).normalize();
  L.vorn = new THREE.Vector3(...L.p(0, 0, 1)).sub(o).normalize();
  return L;
}
const OBEN = new THREE.Vector3(0, 1, 0);

/* ------------------------------------------------------------ Szene */
export async function starten(buehne, plan, mitteilen) {
  const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false, powerPreference: 'high-performance' });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
  renderer.outputColorSpace = THREE.SRGBColorSpace;
  renderer.toneMapping = THREE.AgXToneMapping; renderer.toneMappingExposure = 1.05;
  renderer.shadowMap.enabled = true; renderer.shadowMap.type = THREE.PCFShadowMap;   // PCFSoft ist in r185 veraltet
  buehne.appendChild(renderer.domElement);
  renderer.domElement.setAttribute('aria-label', plan.texte?.leinwand || 'Küche in 3D');
  const szene = new THREE.Scene();
  // Der Boden läuft im Dunst aus, statt an einer harten Kante im Schwarz zu enden
  szene.background = new THREE.Color(0x181716);
  szene.fog = new THREE.Fog(0x181716, 7.5, 15);
  const pm = new THREE.PMREMGenerator(renderer);
  szene.environment = pm.fromScene(new RoomEnvironment(), 0.04).texture;
  szene.environmentIntensity = 0.55;
  const mat = materialien(renderer);

  // Licht: Tageslicht von links vorn (Fenster), weicher Himmel, Arbeitsplatzlicht unter den Hängeschränken
  const sonne = new THREE.DirectionalLight(0xfff3e6, 2.2);
  sonne.castShadow = true; sonne.shadow.mapSize.set(2048, 2048); sonne.shadow.bias = -0.0004; sonne.shadow.normalBias = 0.02;
  sonne.shadow.radius = 5;
  szene.add(sonne, sonne.target);
  szene.add(new THREE.HemisphereLight(0xf8f6f2, 0x6b645c, 0.55));

  const kamera = new THREE.PerspectiveCamera(38, 16 / 9, 0.05, 60);
  const oben = new THREE.OrthographicCamera(-4, 4, 3, -3, 0.1, 30);
  let ansicht = '3d';
  /* Blickwinkel je Form. Bei der L-Form steht Wand B bei x = 0: Von links
     (az < 0) sah man in der ersten Fassung nur ihre Rückseite. */
  const HEIM = { zeile: { az: 0.3, pol: 1.12, min: -1.3, max: 1.3 }, l: { az: 0.62, pol: 1.1, min: 0.08, max: 1.45 }, insel: { az: 0.38, pol: 1.02, min: -1.3, max: 1.3 } };
  let form = plan.form;
  const blick = { az: HEIM[form].az, pol: HEIM[form].pol, abst: 6.5, ziel: new THREE.Vector3(1.8, 0.9, 0.8) };
  const soll = { ...blick, ziel: blick.ziel.clone() };

  let kueche = new THREE.Group(); szene.add(kueche);
  let fronten = [];   // { obj, art: 'lade'|'dreh', vorn/weg bzw. achse/winkel }
  let module = [];    // Klickbare Hüllen je Modul
  let offen = 0, offenSoll = 0;
  let masse = [];     // Maßketten-Etiketten (DOM)
  const etiketten = document.createElement('div'); etiketten.className = 'kp-masse'; buehne.appendChild(etiketten);
  let zeigeMasse = true;

  function entsorgenGruppe(g) {
    g.traverse((o) => { if (o.geometry) o.geometry.dispose(); });
    g.removeFromParent();
  }

  /* ------------------------------------------------ Aufbau */
  function bauen(p) {
    if (p.form !== form) { form = p.form; soll.az = HEIM[form].az; soll.pol = HEIM[form].pol; }
    entsorgenGruppe(kueche); kueche = new THREE.Group(); szene.add(kueche);
    fronten = []; module = []; masse = []; etiketten.replaceChildren();
    const F = mat.front[p.front] || mat.front.furnier, PL = mat.bereit(mat.platte[p.platte] || mat.platte.oro), G = mat.griff[p.griff] || mat.griff.grifflos;
    const grifflos = p.griff === 'grifflos';

    // Raum: Boden, Wände
    const la = p.waende.a / 100, lb = p.form === 'l' ? p.waende.b / 100 : 0;
    // Raum: im AR-Modus weggelassen (userData.raum) -- dort ist der echte Raum
    const boden = netz(quader(la + 6, 0.02, 7, la / 2, -0.01, 2.8, 0), mat.boden, false); boden.userData.raum = true; kueche.add(boden);
    const wandA = netz(quader(la + 0.8, M.WAND_H, 0.08, la / 2 + (p.form === 'l' ? 0.4 : 0), M.WAND_H / 2, -0.04, 0), mat.wand); wandA.userData.raum = true; kueche.add(wandA);
    if (p.form === 'l') { const wb = netz(quader(0.08, M.WAND_H, lb + 0.4, -0.04, M.WAND_H / 2, lb / 2 + 0.2, 0), mat.wand); wb.userData.raum = true; kueche.add(wb); }

    const laeufe = [{ wand: 'a', laenge: la, module: p.reihen.a }];
    if (p.form === 'l') laeufe.push({ wand: 'b', laenge: lb, module: p.reihen.b, start: M.PT });
    if (p.form === 'insel') {
      const li = p.waende.insel / 100;
      laeufe.push({ wand: 'insel', laenge: li, module: p.reihen.insel, x0: Math.max(0, la / 2 - li / 2), z0: M.PT + M.GANG, insel: true });
    }
    for (const lauf of laeufe) laufBauen(lauf, p, F, PL, G, grifflos);
    pendel(p, laeufe.find((l) => l.insel));
    // Licht auf die Küche ausrichten
    const mx = la / 2, mz = p.form === 'insel' ? 1.4 : 0.9;
    sonne.position.set(mx - 3.2, 4.2, mz + 4.5); sonne.target.position.set(mx, 0.6, mz);
    const sc = sonne.shadow.camera; const ext = Math.max(la, lb, 3) * 0.8 + 1;
    sc.left = -ext; sc.right = ext; sc.top = ext; sc.bottom = -ext; sc.near = 0.5; sc.far = 16; sc.updateProjectionMatrix();
    // Kamera-Ziel: Mitte der Küche
    soll.ziel.set(p.form === 'l' ? Math.max(la, 1.2) / 2 + 0.2 : la / 2, 1.08, p.form === 'insel' ? 1.3 : (p.form === 'l' ? Math.min(lb, 2.4) / 2 : 0.45));
    soll.abst = Math.max(4.6, Math.max(la, lb) * 1.45 + (p.form === 'insel' ? 1.3 : 0.8));
    oben.left = -Math.max(la, 3) / 2 - 0.9; oben.right = -oben.left;
    const hz = (p.form === 'insel' ? M.PT + M.GANG + 1.0 : Math.max(lb, M.PT + 0.8)) / 2 + 0.6;
    oben.top = hz; oben.bottom = -hz; oben.updateProjectionMatrix();
    oben.position.set(p.form === 'l' ? la / 2 : la / 2, 12, (p.form === 'insel' ? (M.PT + M.GANG + 0.95) : Math.max(lb, 1.2)) / 2);
    oben.up.set(0, 0, -1); oben.lookAt(oben.position.x, 0, oben.position.z);
    anpassen();
  }

  /* Pendelleuchten (B2): über der Insel zwei oder drei. Ohne Insel keine --
     über leerem Boden hängend sähen sie verloren aus. Je Leuchte ein Punktlicht ohne Schatten --
     Schatten kommen von der Sonne; drei weitere Schattenkarten kosteten auf
     dem Telefon mehr, als sie zeigen. */
  let pendelLichter = [];
  function pendel(p, insel) {
    for (const l of pendelLichter) l.removeFromParent();
    pendelLichter = [];
    if (!insel) return;
    const li = insel.laenge; const n = li >= 2.2 ? 3 : 2;
    const xs = Array.from({ length: n }, (_, k) => insel.x0 + li * (k + 0.5) / n), z = insel.z0 + (M.PT + 0.30) / 2;
    const hUnten = M.ARBEIT + 0.72;
    for (const x of xs) {
      const kabel = new THREE.CylinderGeometry(0.0015, 0.0015, M.WAND_H - hUnten - 0.2, 6); kabel.translate(x, (M.WAND_H + hUnten + 0.2) / 2, z); kueche.add(netz(kabel, mat.sockel, false));
      const prof = [[0.012, 0.22], [0.02, 0.215], [0.07, 0.17], [0.13, 0.06], [0.15, 0.0], [0.148, -0.004]].map(([r, y]) => new THREE.Vector2(r, y));
      const schirm = new THREE.LatheGeometry(prof, 48); schirm.translate(x, hUnten, z); kueche.add(netz(schirm, mat.schirm));
      const birne = new THREE.SphereGeometry(0.035, 24, 16); birne.translate(x, hUnten + 0.05, z); kueche.add(netz(birne, mat.birne, false));
      const licht = new THREE.PointLight(0xffd6a0, 1.6, 4.5, 2); licht.position.set(x, hUnten + 0.02, z); kueche.add(licht); pendelLichter.push(licht);
    }
  }

  function laufBauen(lauf, p, F, PL, G, grifflos) {
    const L = lage(lauf);
    const add = (g, m, schatten) => { const o = netz(g, m, schatten); kueche.add(o); return o; };
    // Quader in Laufkoordinaten (u0..u1, y0..y1, v0..v1)
    const box = (u0, u1, y0, y1, v0, v1, m, r = 0.0015, hoch = false) => {
      const [cx, cy, cz] = L.p((u0 + u1) / 2, (y0 + y1) / 2, (v0 + v1) / 2);
      const [sx, sy, sz] = L.s(Math.abs(u1 - u0), Math.abs(y1 - y0), Math.abs(v1 - v0));
      return quader(sx, sy, sz, cx, cy, cz, r, hoch);
    };
    let u = lauf.start || 0;
    const segmente = []; let seg = null;     // zusammenhängende Unterschränke für die Platte
    const massU = [u];
    for (const [i, mod] of lauf.module.entries()) {
      const w = mod.breite / 100; const u0 = u, u1 = u + w;
      const hoch = mod.typ.startsWith('hoch');
      const V0 = 0.02, V1 = 0.02 + M.TIEFE, VF = V1 + M.FRONT;   // Korpus hinten/vorn, Frontfläche
      // Klickbare Hülle
      const huelle = new THREE.Mesh(box(u0, u1, 0, hoch ? M.HOCH : M.ARBEIT, 0, VF, mat.auswahl, 0), mat.auswahl);
      huelle.visible = false; huelle.userData = { lauf: lauf.wand, index: i, keinAR: true }; kueche.add(huelle); module.push(huelle);
      if (u1 > lauf.laenge + 0.005 && !lauf.insel) {
        const z = new THREE.Mesh(box(u0, u1, 0, hoch ? M.HOCH : M.ARBEIT, 0, VF, mat.zuviel, 0), mat.zuviel); kueche.add(z);
      }
      if (mod.typ === 'blende') {
        add(box(u0, u1, M.SOCKEL, hoch ? M.HOCH : M.SOCKEL + M.KORPUS, V1, VF, F, 0.001, true), F);
        add(box(u0, u1, 0, M.SOCKEL - 0.004, V1 - 0.06, V1 - 0.04, mat.sockel, 0), mat.sockel);
      } else {
        // Sockel (zurückgesetzt), Korpus
        add(box(u0, u1, 0, M.SOCKEL - 0.004, V1 - 0.06, V1 - 0.04, mat.sockel, 0), mat.sockel);
        const yK1 = hoch ? M.HOCH : M.SOCKEL + M.KORPUS;
        add(box(u0, u0 + 0.019, M.SOCKEL, yK1, V0, V1, mat.korpus, 0), mat.korpus);
        add(box(u1 - 0.019, u1, M.SOCKEL, yK1, V0, V1, mat.korpus, 0), mat.korpus);
        add(box(u0 + 0.019, u1 - 0.019, M.SOCKEL, M.SOCKEL + 0.019, V0, V1, mat.korpus, 0), mat.korpus);
        add(box(u0 + 0.019, u1 - 0.019, M.SOCKEL, yK1, V0, V0 + 0.008, mat.korpus, 0), mat.korpus);
        if (hoch) add(box(u0 + 0.019, u1 - 0.019, yK1 - 0.019, yK1, V0, V1, mat.korpus, 0), mat.korpus);
        frontenBauen(lauf, L, mod, u0, u1, V1, VF, F, G, grifflos, box, add);
      }
      if (!hoch) {
        if (!seg) { seg = { u0, u1, loecher: [], koch: [] }; segmente.push(seg); } else seg.u1 = u1;
        if (mod.typ === 'spuele') seg.loecher.push({ u: (u0 + u1) / 2, b: Math.min(w - 0.12, 0.70) });
        if (mod.typ === 'kochfeld') seg.koch.push({ u: (u0 + u1) / 2, b: w >= 0.8 ? 0.80 : 0.58 });
      } else seg = null;
      // Hängeschränke / Haube
      if (p.oberschraenke && !hoch && !lauf.insel && mod.typ !== 'blende') {
        if (mod.typ === 'kochfeld') haube(L, u0, u1, add);
        else oberschrank(L, mod, u0, u1, F, G, grifflos, box, add);
      }
      u = u1; massU.push(u);
    }
    // Arbeitsplatten mit Ausschnitten, Nischenrückwand, Kochfelder, Becken
    for (const s of segmente) platteBauen(lauf, L, s, PL, add, p);
    if (lauf.insel) {
      // Inselrückwand (Sitzseite) mit Front, Überstand zum Sitzen
      const u0 = 0, u1 = lauf.module.reduce((a, m) => a + m.breite / 100, 0);
      add(box(u0, u1, 0.004, M.SOCKEL + M.KORPUS, -0.019, 0.02, F, 0.001, true), F);
    }
    // Maßkette (Etiketten)
    for (let k = 0; k < lauf.module.length; k++) {
      const um = (massU[k] + massU[k + 1]) / 2; const [x, y, z] = L.p(um, M.ARBEIT + 0.04, lauf.insel ? M.PT : M.PT + 0.02);
      masse.push({ pos: new THREE.Vector3(x, y, z), text: String(lauf.module[k].breite), art: 'modul' });
    }
    const [gx, gy, gz] = L.p(((lauf.start || 0) + massU[massU.length - 1]) / 2, lauf.insel ? M.ARBEIT + 0.30 : M.HOCH + 0.12, lauf.insel ? M.PT : 0.1);
    // Belegt / verfügbar -- bei Wand B ohne die Ecke, die Wand A schon belegt
    const summe = Math.round((massU[massU.length - 1] - (lauf.start || 0)) * 100);
    const frei = Math.round((lauf.laenge - (lauf.start || 0)) * 100);
    masse.push({ pos: new THREE.Vector3(gx, gy, gz), text: lauf.insel ? `${summe} cm` : `${summe} / ${frei} cm`, art: 'summe', zuviel: !lauf.insel && summe > frei });
    for (const m of masse) if (!m.el) { const e = document.createElement('span'); e.className = `kp-mass kp-mass--${m.art}${m.zuviel ? ' kp-mass--zuviel' : ''}`; e.textContent = m.text; etiketten.appendChild(e); m.el = e; }
  }

  function griffBauen(L, uM, yM, vF, laenge, senkrecht, G, grifflos, add, gruppe) {
    if (grifflos) return;
    const r = 0.006;
    const g1 = new THREE.CylinderGeometry(r, r, laenge + 0.04, 16);
    if (!senkrecht) g1.rotateZ(Math.PI / 2);
    const [x, y, z] = L.p(uM, yM, vF + 0.034);
    if (!senkrecht && L.dreh) g1.rotateY(L.dreh);
    g1.translate(x, y, z);
    const teile = [g1];
    for (const s of [-1, 1]) {
      const f = new THREE.CylinderGeometry(0.0045, 0.0045, 0.034, 12); f.rotateX(Math.PI / 2);
      if (L.dreh) f.rotateY(L.dreh);
      const [fx, fy, fz] = senkrecht ? L.p(uM, yM + s * laenge / 2, vF + 0.017) : L.p(uM + s * laenge / 2, yM, vF + 0.017);
      f.translate(fx, fy, fz); teile.push(f);
    }
    for (const g of teile) { const o = netz(g, G); (gruppe || kueche).add(o); }
  }
  function griffLaenge(w) { return w <= 0.45 ? 0.128 : (w <= 0.6 ? 0.192 : 0.256); }

  // Freie Türkante zeigt vom Scharnier weg; sie soll nach vorn schwenken:
  // Drehachse = Kante x vorn, Winkel positiv.
  function tuerAchse(L, anschlag) {
    const kante = L.udir.clone().multiplyScalar(anschlag === 'l' ? 1 : -1);
    return kante.cross(L.vorn).normalize();
  }

  /* Inhalt der Auszüge (Wunsch B2: beim Öffnen Töpfe und Geschirr sehen).
     Alles in der Gruppe der Lade, damit es mit herausfährt. */
  function ladeInhalt(z, L, art, ua, ub, yb, va, vb) {
    const breit = ub - ua, tief = vb - va;
    const zyl = (rOben, rUnten, h, u, y, v, m, seg = 36, offen = false) => {
      const g = new THREE.CylinderGeometry(rOben, rUnten, h, seg, 1, offen);
      const [x, yy, zz] = L.p(u, y + h / 2, v); g.translate(x, yy, zz); z.add(netz(g, m)); return g;
    };
    if (art === 'toepfe') {
      // Topfset: je nach Breite zwei bis vier Töpfe, dahinter ein Deckelstapel
      const n = Math.max(1, Math.min(4, Math.floor(breit / 0.22)));
      const r = Math.min(0.105, breit / n / 2 - 0.012);
      for (let k = 0; k < n; k++) {
        const u = ua + (k + 0.5) * breit / n, v = va + tief * 0.62, h = 0.10 + (k % 2) * 0.05;
        zyl(r, r, h, u, yb, v, mat.edelstahl, 40, true);
        zyl(r - 0.002, r - 0.002, 0.004, u, yb + 0.002, v, mat.zarge, 40);
        const gr = new THREE.TorusGeometry(r + 0.001, 0.0035, 8, 40); gr.rotateX(Math.PI / 2);
        const [x, yy, zz] = L.p(u, yb + h, v); gr.translate(x, yy, zz); z.add(netz(gr, mat.edelstahl));
      }
      for (let k = 0; k < 3; k++) zyl(0.1 - k * 0.012, 0.1 - k * 0.012, 0.006, ua + breit * 0.5, yb + k * 0.008, va + tief * 0.16, mat.glasKlar, 40);
    } else if (art === 'teller') {
      const n = Math.max(1, Math.min(3, Math.floor(breit / 0.30)));
      for (let k = 0; k < n; k++) {
        const u = ua + (k + 0.5) * breit / n;
        for (let j = 0; j < 7; j++) zyl(0.125, 0.105, 0.012, u, yb + j * 0.014, va + tief * 0.55, mat.porzellan, 40);
      }
      // Schalen vorn
      for (let k = 0; k < n; k++) for (let j = 0; j < 3; j++) zyl(0.075, 0.045, 0.05, ua + (k + 0.5) * breit / n, yb + j * 0.02, va + tief * 0.14, mat.porzellan, 32, true);
    } else if (art === 'besteck') {
      // Besteckeinsatz: Holzboden mit Fächern, darin Löffel/Gabeln als flache Stäbe
      const box = (a0, a1, c0, c1, d0, d1, m) => { const [cx, cy, cz] = L.p((a0 + a1) / 2, (c0 + c1) / 2, (d0 + d1) / 2); const [sx, sy, sz] = L.s(a1 - a0, c1 - c0, d1 - d0); z.add(netz(quader(sx, sy, sz, cx, cy, cz, 0.001), m)); };
      box(ua + 0.005, ub - 0.005, yb, yb + 0.006, va + 0.01, vb - 0.01, mat.einsatz);
      const faecher = Math.max(3, Math.floor(breit / 0.085));
      for (let k = 0; k <= faecher; k++) { const u = ua + 0.005 + k * (breit - 0.01) / faecher; box(u - 0.004, u + 0.004, yb, yb + 0.05, va + 0.01, vb - 0.01, mat.einsatz); }
      for (let k = 0; k < faecher; k++) {
        const um = ua + 0.005 + (k + 0.5) * (breit - 0.01) / faecher;
        for (let j = 0; j < 4; j++) box(um - 0.011 + j * 0.007, um - 0.008 + j * 0.007, yb + 0.008 + j * 0.003, yb + 0.011 + j * 0.003, va + 0.08, va + 0.08 + Math.min(0.2, tief - 0.12), mat.edelstahl);
      }
    }
  }

  // Front als drehbare/verschiebbare Gruppe: Geometrie in Weltlage, dann um den Drehpunkt verschoben
  function frontGruppe(teileFn, pivot) {
    const gr = new THREE.Group(); gr.position.copy(pivot); kueche.add(gr);
    const fake = { add: (o) => { o.geometry.translate(-pivot.x, -pivot.y, -pivot.z); gr.add(o); } };
    teileFn(fake);
    return gr;
  }

  function frontenBauen(lauf, L, mod, u0, u1, V1, VF, F, G, grifflos, box, add) {
    const w = u1 - u0; const fu0 = u0 + M.FUGE / 2, fu1 = u1 - M.FUGE / 2;
    const y0 = M.SOCKEL + M.FUGE, y1 = mod.typ.startsWith('hoch') ? M.HOCH - M.FUGE : M.SOCKEL + M.KORPUS - M.FUGE;
    const vorn = L.vorn;
    const lade = (ya, yb, inhalt = null, tiefe = 0.47) => {
      const pivot = new THREE.Vector3(...L.p((u0 + u1) / 2, ya, VF));
      const gr = frontGruppe((z) => {
        z.add(netz(box(fu0, fu1, ya, yb, V1, VF, F, 0.0015, true), F));
        if (grifflos) z.add(netz(box(fu0, fu1, yb - 0.02, yb - 0.002, VF - 0.002, VF + 0.0005, mat.griff.grifflos, 0), mat.griff.grifflos));
        const zh = Math.min(0.18, yb - ya - 0.04);
        z.add(netz(box(u0 + 0.03, u0 + 0.043, ya + 0.02, ya + 0.02 + zh, V1 - tiefe, V1, mat.zarge, 0.001), mat.zarge));
        z.add(netz(box(u1 - 0.043, u1 - 0.03, ya + 0.02, ya + 0.02 + zh, V1 - tiefe, V1, mat.zarge, 0.001), mat.zarge));
        z.add(netz(box(u0 + 0.043, u1 - 0.043, ya + 0.02, ya + 0.036, V1 - tiefe, V1, mat.korpus, 0), mat.korpus));
        z.add(netz(box(u0 + 0.043, u1 - 0.043, ya + 0.02, ya + 0.02 + zh - 0.02, V1 - tiefe, V1 - tiefe + 0.012, mat.zarge, 0), mat.zarge));
        griffBauen(L, (u0 + u1) / 2, yb - (yb - ya < 0.2 ? 0.045 : 0.06), VF, griffLaenge(w), false, G, grifflos, null, z);
        if (inhalt) ladeInhalt(z, L, inhalt, u0 + 0.043, u1 - 0.043, ya + 0.036, V1 - tiefe + 0.012, V1 - 0.004);
      }, pivot);
      fronten.push({ obj: gr, art: 'lade', vorn, weg: 0.40 });
    };
    const tuer = (ua, ub, ya, yb, anschlag, griffOben = true) => {
      const hu = anschlag === 'l' ? ua : ub;
      const pivot = new THREE.Vector3(...L.p(hu, 0, VF));
      const gr = frontGruppe((z) => {
        z.add(netz(box(ua, ub, ya, yb, V1, VF, F, 0.0015, true), F));
        if (grifflos) z.add(netz(box(ua, ub, yb - 0.02, yb - 0.002, VF - 0.002, VF + 0.0005, mat.griff.grifflos, 0), mat.griff.grifflos));
        const gu = anschlag === 'l' ? ub - 0.045 : ua + 0.045;
        griffBauen(L, gu, griffOben ? yb - 0.13 : ya + 0.13, VF, 0.128, true, G, grifflos, null, z);
      }, pivot);
      fronten.push({ obj: gr, art: 'dreh', achse: tuerAchse(L, anschlag), winkel: 1.75 });
    };
    const fh = y1 - y0;
    switch (mod.typ) {
      case 'auszug': {
        const hs = [0.265, 0.265, fh - 0.53 - 2 * M.FUGE]; let y = y0;
        // Von unten: Töpfe, Teller, Besteck -- so packt man eine Küche ein
        const inh = ['toepfe', 'teller', 'besteck'];
        for (const [k, h] of hs.entries()) { lade(y, y + h, inh[k]); y += h + M.FUGE; }
        break;
      }
      case 'kochfeld': { let y = y0; for (const [k, h] of [0.40, fh - 0.40 - M.FUGE].entries()) { lade(y, y + h, k ? 'besteck' : 'toepfe'); y += h + M.FUGE; } break; }
      case 'tuer': case 'spuele': case 'eck': {
        if (mod.typ === 'eck') {
          // Eckschrank: nur der vordere Teil ist sichtbar (blinder Teil unter dem Nachbarlauf)
          add(box(u0, u0 + M.PT, y0, y1, V1, VF, mat.korpus, 0), mat.korpus);
          tuer(u0 + M.PT + M.FUGE / 2, fu1, y0, y1, 'r');
        } else if (w >= 0.79) { const um = (u0 + u1) / 2; tuer(fu0, um - M.FUGE / 2, y0, y1, 'l'); tuer(um + M.FUGE / 2, fu1, y0, y1, 'r'); }
        else tuer(fu0, fu1, y0, y1, 'l');
        if (mod.typ === 'tuer') add(box(u0 + 0.02, u1 - 0.02, M.SOCKEL + 0.36, M.SOCKEL + 0.379, 0.03, V1 - 0.02, mat.korpus, 0), mat.korpus);
        if (mod.typ === 'spuele') {
          // Siphon und Mülltrennung angedeutet
          add(box(u0 + 0.05, u0 + 0.30, M.SOCKEL + 0.02, M.SOCKEL + 0.40, 0.08, 0.46, mat.sockel, 0.01), mat.sockel);
        }
        break;
      }
      case 'gs': { // Geschirrspüler vollintegriert: eine Front mit Griff oben
        const pivot = new THREE.Vector3(...L.p((u0 + u1) / 2, y0, VF));
        const gr = frontGruppe((z) => {
          z.add(netz(box(fu0, fu1, y0, y1, V1, VF, F, 0.0015, true), F));
          griffBauen(L, (u0 + u1) / 2, y1 - 0.05, VF, griffLaenge(w), false, G, grifflos, null, z);
        }, pivot);
        fronten.push({ obj: gr, art: 'dreh', achse: OBEN.clone().cross(L.vorn).normalize(), winkel: 1.2 });
        add(box(u0 + 0.02, u1 - 0.02, y0 + 0.02, y1 - 0.03, 0.05, V1 - 0.01, mat.edelstahl, 0.004), mat.edelstahl);
        break;
      }
      case 'backofen_u': {
        lade(y0, y0 + 0.12);
        const yo = y0 + 0.12 + M.FUGE; backofen(L, u0, u1, yo, yo + 0.595, VF, add, G);
        break;
      }
      case 'hoch_backofen': {
        lade(y0, y0 + 0.265); lade(y0 + 0.265 + M.FUGE, y0 + 0.53 + M.FUGE);
        const yo = y0 + 0.53 + 2 * M.FUGE; backofen(L, u0, u1, yo, yo + 0.595, VF, add, G);
        const ym = yo + 0.595 + M.FUGE; mikrowelle(L, u0, u1, ym, ym + 0.38, VF, add, G);
        tuer(fu0, fu1, ym + 0.38 + M.FUGE, y1, 'l', false);
        break;
      }
      case 'hoch_kuehl': {
        const yt = y0 + 0.80; tuer(fu0, fu1, y0, yt, 'l'); tuer(fu0, fu1, yt + M.FUGE, y1, 'l', false);
        add(box(u0 + 0.03, u1 - 0.03, y0 + 0.02, y1 - 0.03, 0.03, V1 - 0.02, mat.wand, 0.02), mat.wand);   // Innenraum hell
        break;
      }
      case 'hoch_vorrat': { const yt = y0 + 1.28; tuer(fu0, fu1, y0, yt, 'l'); tuer(fu0, fu1, yt + M.FUGE, y1, 'l', false); break; }
      default: break;
    }
  }

  function backofen(L, u0, u1, ya, yb, VF, add, G) {
    const box = (a, b, c, d, e, f, m, r) => { const [cx, cy, cz] = L.p((a + b) / 2, (c + d) / 2, (e + f) / 2); const [sx, sy, sz] = L.s(Math.abs(b - a), Math.abs(d - c), Math.abs(f - e)); return quader(sx, sy, sz, cx, cy, cz, r); };
    add(box(u0 + 0.004, u1 - 0.004, ya, yb, VF - 0.022, VF + 0.004, mat.glasSchwarz, 0.004), mat.glasSchwarz);
    add(box(u0 + 0.004, u1 - 0.004, yb - 0.10, yb, VF + 0.004, VF + 0.008, mat.edelstahl, 0.002), mat.edelstahl);
    // Griff Edelstahl quer
    const g = new THREE.CylinderGeometry(0.009, 0.009, (u1 - u0) - 0.12, 16); g.rotateZ(Math.PI / 2); if (L.dreh) g.rotateY(L.dreh);
    const [x, y, z] = L.p((u0 + u1) / 2, yb - 0.14, VF + 0.035); g.translate(x, y, z); add(g, mat.edelstahl);
  }
  function mikrowelle(L, u0, u1, ya, yb, VF, add) {
    const box = (a, b, c, d, e, f, m, r) => { const [cx, cy, cz] = L.p((a + b) / 2, (c + d) / 2, (e + f) / 2); const [sx, sy, sz] = L.s(Math.abs(b - a), Math.abs(d - c), Math.abs(f - e)); return quader(sx, sy, sz, cx, cy, cz, r); };
    add(box(u0 + 0.004, u1 - 0.004, ya, yb, VF - 0.022, VF + 0.004, mat.glasSchwarz, 0.004), mat.glasSchwarz);
    add(box(u1 - 0.12, u1 - 0.004, ya, yb, VF + 0.004, VF + 0.007, mat.edelstahl, 0.002), mat.edelstahl);
  }
  function haube(L, u0, u1, add) {
    const box = (a, b, c, d, e, f, m, r) => { const [cx, cy, cz] = L.p((a + b) / 2, (c + d) / 2, (e + f) / 2); const [sx, sy, sz] = L.s(Math.abs(b - a), Math.abs(d - c), Math.abs(f - e)); return quader(sx, sy, sz, cx, cy, cz, r); };
    const um = (u0 + u1) / 2, b = Math.min(0.90, u1 - u0);
    add(box(um - b / 2, um + b / 2, 1.58, 1.64, 0.02, 0.50, mat.edelstahl, 0.006), mat.edelstahl);
    add(box(um - 0.16, um + 0.16, 1.64, M.HOCH + 0.30, 0.02, 0.28, mat.edelstahl, 0.004), mat.edelstahl);
    add(box(um - b / 2 + 0.02, um + b / 2 - 0.02, 1.577, 1.581, 0.05, 0.47, mat.zarge, 0), mat.zarge);
  }
  function oberschrank(L, mod, u0, u1, F, G, grifflos, box, add) {
    const w = u1 - u0; const V0 = 0.02, V1 = V0 + M.OB_T - M.FRONT, VF = V0 + M.OB_T;
    const ya = M.OB_UNTEN, yb = M.OB_UNTEN + M.OB_H;
    add(box(u0, u1, ya, yb, V0, V1, mat.korpus, 0.001), mat.korpus);
    add(box(u0 + 0.02, u1 - 0.02, ya - 0.012, ya - 0.004, V1 - 0.06, V1 - 0.03, mat.led, 0), mat.led, false);   // LED-Leiste
    const tuer = (ua, ub, anschlag) => {
      const pivot = new THREE.Vector3(...L.p(anschlag === 'l' ? ua : ub, 0, VF));
      const gr = frontGruppe((z) => {
        z.add(netz(box(ua, ub, ya + M.FUGE, yb - M.FUGE, V1, VF, F, 0.0015, true), F));
        const gu = anschlag === 'l' ? ub - 0.045 : ua + 0.045;
        griffBauen(L, gu, ya + 0.10, VF, 0.128, true, G, grifflos, null, z);
      }, pivot);
      fronten.push({ obj: gr, art: 'dreh', achse: tuerAchse(L, anschlag), winkel: 1.75 });
    };
    if (w >= 0.79) { const um = (u0 + u1) / 2; tuer(u0 + M.FUGE / 2, um - M.FUGE / 2, 'l'); tuer(um + M.FUGE / 2, u1 - M.FUGE / 2, 'r'); }
    else tuer(u0 + M.FUGE / 2, u1 - M.FUGE / 2, 'l');
    // Geschirr im Schrank (sieht man beim Öffnen)
    for (let k = 0; k < 5; k++) {
      const [x, y, z] = L.p((u0 + u1) / 2, ya + 0.03 + k * 0.016, 0.20);
      const g = new THREE.CylinderGeometry(0.13, 0.11, 0.012, 40); g.translate(x, y, z); add(g, mat.porzellan);
    }
  }

  function platteBauen(lauf, L, s, PL, add, p) {
    // Platte als Extrusion mit Ausschnitten für Becken (in u/v), Höhe 40 mm
    const u0 = s.u0 - (lauf.insel ? 0 : 0), u1 = s.u1;
    const v0 = lauf.insel ? -0.30 : 0, v1 = M.PT;           // Insel: Sitzüberstand 30 cm
    const form = new THREE.Shape(); form.moveTo(u0, v0); form.lineTo(u1, v0); form.lineTo(u1, v1); form.lineTo(u0, v1); form.lineTo(u0, v0);
    for (const l of s.loecher) {
      const h = new THREE.Path(); const b = l.b / 2, t0 = 0.08, t1 = 0.08 + 0.40, r = 0.012;
      h.moveTo(l.u - b + r, t0); h.lineTo(l.u + b - r, t0); h.quadraticCurveTo(l.u + b, t0, l.u + b, t0 + r);
      h.lineTo(l.u + b, t1 - r); h.quadraticCurveTo(l.u + b, t1, l.u + b - r, t1); h.lineTo(l.u - b + r, t1);
      h.quadraticCurveTo(l.u - b, t1, l.u - b, t1 - r); h.lineTo(l.u - b, t0 + r); h.quadraticCurveTo(l.u - b, t0, l.u - b + r, t0);
      form.holes.push(h);
    }
    const g = new THREE.ExtrudeGeometry(form, { depth: M.PLATTE, bevelEnabled: true, bevelThickness: 0.0015, bevelSize: 0.0015, bevelSegments: 2, curveSegments: 6 });
    // Extrusion läuft in z (lokal): lokal (u, v, h) -> Lauf (u, y=h, v)
    const pos = g.attributes.position;
    for (let i = 0; i < pos.count; i++) {
      const uu = pos.getX(i), vv = pos.getY(i), hh = pos.getZ(i);
      const [x, y, z] = L.p(uu, M.SOCKEL + M.KORPUS + hh, vv);
      pos.setXYZ(i, x, y, z);
    }
    // Gespiegelte Läufe (Wand B, Insel) kehren die Wicklung um -- Dreiecke
    // zurückdrehen, sonst zeigen die Normalen nach innen und die Platte ist dunkel.
    if (L.spiegel) {
      for (const name of ['position', 'normal', 'uv']) {
        const a = g.attributes[name]; if (!a) continue; const n = a.itemSize, arr = a.array;
        for (let t = 0; t < a.count; t += 3) for (let c = 0; c < n; c++) { const i1 = (t + 1) * n + c, i2 = (t + 2) * n + c; const tmp = arr[i1]; arr[i1] = arr[i2]; arr[i2] = tmp; }
      }
    }
    g.computeVertexNormals(); uvWelt(g, false);
    add(g, PL);
    // Nischenrückwand in Plattenmaterial
    if (!lauf.insel && p.oberschraenke) {
      const [cx, cy, cz] = L.p((u0 + u1) / 2, (M.ARBEIT + M.OB_UNTEN) / 2, 0.006);
      const [sx, sy, sz] = L.s(u1 - u0, M.OB_UNTEN - M.ARBEIT, 0.012);
      add(quader(sx, sy, sz, cx, cy, cz, 0), PL, false);
    }
    // Becken (Unterbau), Armatur
    for (const l of s.loecher) {
      const b = l.b, t = 0.40;
      const becken = (a0, a1, c0, c1, d0, d1) => { const [cx, cy, cz] = L.p((a0 + a1) / 2, (c0 + c1) / 2, (d0 + d1) / 2); const [sx, sy, sz] = L.s(a1 - a0, c1 - c0, d1 - d0); add(quader(sx, sy, sz, cx, cy, cz, 0.003), mat.edelstahl); };
      const yt = M.SOCKEL + M.KORPUS;
      becken(l.u - b / 2, l.u + b / 2, yt - 0.20, yt - 0.198, 0.08, 0.08 + t);
      becken(l.u - b / 2, l.u - b / 2 + 0.002, yt - 0.20, yt, 0.08, 0.08 + t);
      becken(l.u + b / 2 - 0.002, l.u + b / 2, yt - 0.20, yt, 0.08, 0.08 + t);
      becken(l.u - b / 2, l.u + b / 2, yt - 0.20, yt, 0.08, 0.082);
      becken(l.u - b / 2, l.u + b / 2, yt - 0.20, yt, 0.08 + t - 0.002, 0.08 + t);
      // Armatur hinter dem Becken: Säule + Bogen
      const kurve = new THREE.CatmullRomCurve3([
        new THREE.Vector3(...L.p(l.u, M.ARBEIT, 0.04)), new THREE.Vector3(...L.p(l.u, M.ARBEIT + 0.24, 0.04)),
        new THREE.Vector3(...L.p(l.u, M.ARBEIT + 0.34, 0.12)), new THREE.Vector3(...L.p(l.u, M.ARBEIT + 0.30, 0.22)),
        new THREE.Vector3(...L.p(l.u, M.ARBEIT + 0.22, 0.25))]);
      add(new THREE.TubeGeometry(kurve, 48, 0.0115, 16, false), mat.griff[p.griff === 'grifflos' ? 'edelstahl' : p.griff]);
      const fuss = new THREE.CylinderGeometry(0.026, 0.028, 0.012, 32); const [fx, fy, fz] = L.p(l.u, M.ARBEIT + 0.006, 0.04); fuss.translate(fx, fy, fz);
      add(fuss, mat.griff[p.griff === 'grifflos' ? 'edelstahl' : p.griff]);
    }
    for (const k of s.koch) {
      const [cx, cy, cz] = L.p(k.u, M.ARBEIT + 0.003, 0.05 + 0.26);
      const [sx, sy, sz] = L.s(k.b, 0.006, 0.52);
      const g2 = new THREE.BoxGeometry(sx, sy, sz); g2.translate(cx, cy, cz);
      // Siebdruck 0..1 über das Feld: UV aus der Lage
      const pp = g2.attributes.position, uv = g2.attributes.uv;
      for (let i = 0; i < pp.count; i++) {
        const du = L.dreh === -Math.PI / 2 ? (pp.getZ(i) - (cz - sz / 2)) / sz : (pp.getX(i) - (cx - sx / 2)) / sx;
        const dv = L.dreh === -Math.PI / 2 ? (pp.getX(i) - (cx - sx / 2)) / sx : (pp.getZ(i) - (cz - sz / 2)) / sz;
        uv.setXY(i, lauf.insel ? 1 - du : du, lauf.insel ? dv : 1 - dv);
      }
      add(g2, mat.kochfeld);
    }
  }

  /* ------------------------------------------------ Kamera, Maße, Schleife */
  let breitenFaktor = 1;
  function anpassen() {
    const w = buehne.clientWidth || 16, h = buehne.clientHeight || 9;
    renderer.setSize(w, h, false);
    kamera.aspect = w / h; kamera.updateProjectionMatrix();
    // Schmale Bühne (Telefon, 4:3): weiter zurück, sonst ragt die Küche seitlich hinaus
    breitenFaktor = Math.max(1, (16 / 9) / (w / h));
    const a = w / h, hb = (oben.top - oben.bottom), bb = oben.right - oben.left;
    if (bb / hb < a) { const nb = hb * a; oben.left = -nb / 2; oben.right = nb / 2; } else { const nh = bb / a; oben.top = nh / 2; oben.bottom = -nh / 2; }
    oben.updateProjectionMatrix();
  }
  new ResizeObserver(anpassen).observe(buehne);

  function kameraSetzen() {
    const b = blick; const sp = Math.sin(b.pol);
    const d = b.abst * breitenFaktor;
    kamera.position.set(b.ziel.x + d * sp * Math.sin(b.az), b.ziel.y + d * Math.cos(b.pol), b.ziel.z + d * sp * Math.cos(b.az));
    kamera.lookAt(b.ziel);
  }
  const v = new THREE.Vector3();
  function masseZeigen(cam) {
    const w = buehne.clientWidth, h = buehne.clientHeight;
    for (const m of masse) {
      v.copy(m.pos).project(cam);
      const sichtbar = zeigeMasse && v.z < 1 && Math.abs(v.x) < 1.05 && Math.abs(v.y) < 1.05;
      m.el.style.opacity = sichtbar ? '1' : '0';
      m.el.style.transform = `translate(${((v.x + 1) / 2 * w).toFixed(1)}px, ${((1 - v.y) / 2 * h).toFixed(1)}px) translate(-50%, -50%)`;
    }
  }

  // Ziehen = drehen, Rad/zwei Finger = Abstand
  let zieh = null; const zeiger = new Map();
  const el = renderer.domElement; el.style.touchAction = 'none'; el.tabIndex = 0;
  el.addEventListener('pointerdown', (e) => { zeiger.set(e.pointerId, e); el.setPointerCapture(e.pointerId); zieh = { x: e.clientX, y: e.clientY, az: soll.az, pol: soll.pol, bew: 0, abst: soll.abst, d: 0 }; });
  el.addEventListener('pointermove', (e) => {
    if (!zieh || !zeiger.has(e.pointerId)) return;
    zeiger.set(e.pointerId, e);
    if (zeiger.size === 2) {
      const [a, b] = [...zeiger.values()]; const d = Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
      if (!zieh.d) zieh.d = d; soll.abst = THREE.MathUtils.clamp(zieh.abst * zieh.d / d, 2.2, 14); return;
    }
    const dx = e.clientX - zieh.x, dy = e.clientY - zieh.y; zieh.bew = Math.max(zieh.bew, Math.hypot(dx, dy));
    if (ansicht === '3d') { soll.az = THREE.MathUtils.clamp(zieh.az - dx * 0.006, HEIM[form].min, HEIM[form].max); soll.pol = THREE.MathUtils.clamp(zieh.pol - dy * 0.005, 0.35, 1.45); }
  });
  const loslassen = (e) => {
    zeiger.delete(e.pointerId);
    if (zieh && zieh.bew < 5 && zeiger.size === 0) waehlenBei(e);
    if (zeiger.size === 0) zieh = null;
  };
  el.addEventListener('pointerup', loslassen); el.addEventListener('pointercancel', loslassen);
  el.addEventListener('wheel', (e) => { if (!(e.ctrlKey || e.metaKey)) return; e.preventDefault(); soll.abst = THREE.MathUtils.clamp(soll.abst * (1 + e.deltaY * 0.001), 2.2, 14); }, { passive: false });
  el.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') soll.az = Math.max(HEIM[form].min, soll.az - 0.1); else if (e.key === 'ArrowRight') soll.az = Math.min(HEIM[form].max, soll.az + 0.1);
    else if (e.key === 'ArrowUp') soll.pol = Math.max(0.35, soll.pol - 0.08); else if (e.key === 'ArrowDown') soll.pol = Math.min(1.45, soll.pol + 0.08);
    else return; e.preventDefault();
  });
  const ray = new THREE.Raycaster(); const nd = new THREE.Vector2();
  let auswahlHuelle = null;
  function waehlenBei(e) {
    const r = el.getBoundingClientRect(); nd.set(((e.clientX - r.left) / r.width) * 2 - 1, -((e.clientY - r.top) / r.height) * 2 + 1);
    ray.setFromCamera(nd, ansicht === '3d' ? kamera : oben);
    for (const h of module) h.visible = true;
    const t = ray.intersectObjects(module, false)[0];
    for (const h of module) h.visible = h === auswahlHuelle;
    if (t) mitteilen({ art: 'modul', lauf: t.object.userData.lauf, index: t.object.userData.index });
  }
  function auswahl(lauf, index) {
    auswahlHuelle = module.find((h) => h.userData.lauf === lauf && h.userData.index === index) || null;
    for (const h of module) h.visible = h === auswahlHuelle;
  }

  const uhr = new THREE.Timer(); let laeuft = true; let fps = 0, fz = 0, ft = 0;
  function schleife() {
    if (!laeuft) return;
    requestAnimationFrame(schleife);
    uhr.update(); const dt = Math.min(0.05, uhr.getDelta());
    const f = 1 - Math.pow(0.001, dt);
    blick.az += (soll.az - blick.az) * f; blick.pol += (soll.pol - blick.pol) * f; blick.abst += (soll.abst - blick.abst) * f;
    blick.ziel.lerp(soll.ziel, f);
    offen += (offenSoll - offen) * Math.min(1, dt * 3);
    for (const fr of fronten) {
      if (fr.art === 'lade') fr.obj.position.copy(fr.ruhe || (fr.ruhe = fr.obj.position.clone())).addScaledVector(fr.vorn, fr.weg * offen);
      else fr.obj.quaternion.setFromAxisAngle(fr.achse, fr.winkel * offen);
    }
    kameraSetzen();
    const cam = ansicht === '3d' ? kamera : oben;
    renderer.render(szene, cam);
    masseZeigen(cam);
    fz++; ft += dt; if (ft > 1) { fps = Math.round(fz / ft); fz = 0; ft = 0; mitteilen({ art: 'fps', fps }); }
  }

  bauen(plan); kameraSetzen(); blick.ziel.copy(soll.ziel); blick.abst = soll.abst;
  schleife();
  return {
    aktualisieren(p) { const warOffen = offenSoll; bauen(p); offen = warOffen; offenSoll = warOffen; },
    ansicht(a) { ansicht = a; },
    oeffnen(an) { offenSoll = an ? 1 : 0; },
    masse(an) { zeigeMasse = an; },
    auswahl,
    heim() { soll.az = HEIM[form].az; soll.pol = HEIM[form].pol; },
    arQuelle() { return kueche; },
    anhalten() { laeuft = false; },
    fortsetzen() { if (!laeuft) { laeuft = true; uhr.update(); schleife(); } },
    entsorgen() { laeuft = false; entsorgenGruppe(kueche); renderer.dispose(); renderer.domElement.remove(); etiketten.remove(); },
  };
}
