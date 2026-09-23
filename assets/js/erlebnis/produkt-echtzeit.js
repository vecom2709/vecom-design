/* ==========================================================================
   produkt-echtzeit.js — Produkte in Echtzeit, passgenau über dem Poster.

   WOZU
   Auto und Schuh stehen auf der Seite zuerst als gerechnete Fotos aus
   Blender Cycles (branchen_studio.py). Dieses Modul legt dasselbe Modell in
   Echtzeit darüber: dieselbe Kamera, dieselbe Brennweite, dasselbe Licht aus
   derselben HDRI. Wer zieht, dreht das Modell; wer loslässt, sieht es auf
   den Standpunkt des Fotos zurückfahren, und die Seite blendet das Foto der
   gewählten Variante wieder ein. Dasselbe Prinzip wie bei der Villa.

   WAS ES KANN
   - Varianten aus KHR_materials_variants: EIN Modell, alle Lacke oder
     Farben. Umgeschaltet wird das Material, nicht die Datei -- ohne
     Nachladen, ohne Flackern.
   - Zerlegen: jedes Teil fährt entlang der Linie von der Modellmitte zu
     seiner eigenen Mitte nach außen. Dieselbe Rechnung für jedes Modell,
     kein Teil wird von Hand gesetzt.
   - Licht an/aus für alles, was im Modell leuchtet (Scheinwerfer, Rückleuchten).

   KAMERA (aus render/<modell>/kamera.json)
   Vollformat, Sensor horizontal eingepasst, Blick direkt auf den Zielpunkt
   (kein Objektivversatz -- das Studio-Skript richtet die Kamera mit
   to_track_quat aus). Der Beschnitt wird wie bei der Villa nachgerechnet:
   Die Fotos sind 16:9 und stehen mit object-fit: cover auf der Bühne.

   DER BODEN
   Blender beleuchtet den Boden mit einem eigenen Licht, das nur den Boden
   trifft (Lichtverknüpfung). three.js kennt das nicht -- deshalb kommt die
   Beleuchtung des Bodens als gebackenes Bild (boden.png, 16 Bit, neutral)
   und wirkt hier als lightMap. Den Glanz rechnet der Boden selbst, der
   hängt am Blickwinkel.
   ========================================================================== */
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { HDRLoader } from 'three/addons/loaders/HDRLoader.js';
import { MeshoptDecoder } from 'three/addons/libs/meshopt_decoder.module.js';
import { Reflector } from 'three/addons/objects/Reflector.js';

const REF = 16 / 9;
const BEWEGUNG_AUS = matchMedia('(prefers-reduced-motion: reduce)').matches;
const wickel = (a) => Math.atan2(Math.sin(a), Math.cos(a));

/* Radialer Verlauf als Textur (für Glanzinsel und Auslauf des Bodens).
   Beide Werte stehen in ALLEN Kanälen, auch im Alpha: three.js liest
   specularIntensityMap aus dem Alphakanal, alphaMap aus dem Grünkanal --
   ein Verlauf nur in RGB hätte den Glanz still auf voller Stärke gelassen. */
function radial(innen, aussen, groesse, von, nach) {
  const n = 256; const c = document.createElement('canvas'); c.width = c.height = n;
  const g = c.getContext('2d'); const mitte = n / 2; const px = n / groesse;
  const v = g.createRadialGradient(mitte, mitte, innen * px, mitte, mitte, aussen * px);
  v.addColorStop(0, `rgba(${von},${von},${von},${von / 255})`); v.addColorStop(1, `rgba(${nach},${nach},${nach},${nach / 255})`);
  g.fillStyle = v; g.fillRect(0, 0, n, n);
  const t = new THREE.CanvasTexture(c); t.colorSpace = THREE.NoColorSpace;
  return t;
}

/* AgX mit dem Look aus Blender. Die Poster laufen durch "AgX - Medium High
   Contrast"; three.js kennt nur AgX ohne Look. Blender legt den Look als
   Kontrast im AgX-Log-Raum an (OCIO GradingPrimary, Stil "log") -- genau
   dort setzt dieser Nachbau an: nach der Log-Kodierung, vor der Sigmoide.
   Kontrast, Drehpunkt und Sättigung sind am Poster gemessen (Prüfstand
   tools/produkt-probe.html), nicht aus der OCIO-Datei abgeschrieben. Der
   Eingriff gilt nur für Renderer mit CustomToneMapping -- die Villa bleibt
   unberührt. */
let lookGesetzt = false;
function lookSetzen({ kontrast = 1.2, pivot = 0.606, saettigung = 1.0 } = {}) {
  if (lookGesetzt) return;
  lookGesetzt = true;
  const quelle = THREE.ShaderChunk.tonemapping_pars_fragment;
  /* Vollständig ausgeschrieben statt aus three.js herausgeschnitten: Der
     erste Versuch suchte das Funktionsende über Leerzeichen und Tabulatoren
     -- im minifizierten Build stehen die anders, und jedes Material schlug
     mit "VecomAgX nicht gefunden" fehl. Konstanten wie in three r185. */
  const agx = `
vec3 VecomAgX( vec3 color ) {
  const mat3 AgXInsetMatrix = mat3(
    vec3( 0.856627153315983, 0.137318972929847, 0.11189821299995 ),
    vec3( 0.0951212405381588, 0.761241990602591, 0.0767994186031903 ),
    vec3( 0.0482516061458583, 0.101439036467562, 0.811302368396859 ) );
  const mat3 AgXOutsetMatrix = mat3(
    vec3( 1.1271005818144368, - 0.1413297634984383, - 0.14132976349843826 ),
    vec3( - 0.11060664309660323, 1.157823702216272, - 0.11060664309660294 ),
    vec3( - 0.016493938717834573, - 0.016493938717834257, 1.2519364065950405 ) );
  const float AgxMinEv = - 12.47393;
  const float AgxMaxEv = 4.026069;
  color *= toneMappingExposure;
  color = LINEAR_SRGB_TO_LINEAR_REC2020 * color;
  color = AgXInsetMatrix * color;
  color = max( color, 1e-10 );
  color = log2( color );
  color = ( color - AgxMinEv ) / ( AgxMaxEv - AgxMinEv );
  color = clamp( color, 0.0, 1.0 );
  float l = dot( color, vec3( 0.2126, 0.7152, 0.0722 ) );
  color = l + ( color - l ) * ${saettigung.toFixed(4)};
  color = ( color - ${pivot.toFixed(4)} ) * ${kontrast.toFixed(4)} + ${pivot.toFixed(4)};
  color = clamp( color, 0.0, 1.0 );
  color = agxDefaultContrastApprox( color );
  color = AgXOutsetMatrix * color;
  color = pow( max( vec3( 0.0 ), color ), vec3( 2.2 ) );
  color = LINEAR_REC2020_TO_LINEAR_SRGB * color;
  return clamp( color, 0.0, 1.0 );
}
vec3 CustomToneMapping( vec3 color ) { return VecomAgX( color ); }`;
  const platzhalter = 'vec3 CustomToneMapping( vec3 color ) { return color; }';
  if (!quelle.includes(platzhalter)) { console.warn('produkt-echtzeit: CustomToneMapping-Platzhalter fehlt'); return; }
  THREE.ShaderChunk.tonemapping_pars_fragment = quelle.replace(platzhalter, agx);
}

/* Spiegelung des Modells im Boden. Blenders Boden hat Rauheit 0,26-0,40 --
   die Spiegelung ist dort weich. Hier: Spiegelbild in halber Auflösung,
   weichgezeichnet über die Mipmap-Stufen plus vier Nachbarabtastungen, und
   wie im Poster nur in der Glanzinsel um das Modell. Additiv, weil eine
   Spiegelung Licht hinzufügt, nichts abdeckt. */
const SPIEGEL = {
  name: 'Bodenspiegel',
  uniforms: {
    color: { value: null }, tDiffuse: { value: null }, textureMatrix: { value: null },
    staerke: { value: 0.3 }, lod: { value: 2.5 }, insel: { value: new THREE.Vector2(2.6, 9) },
    mitte: { value: new THREE.Vector3() }, texel: { value: new THREE.Vector2(1 / 800, 1 / 450) },
  },
  vertexShader: /* glsl */`
    uniform mat4 textureMatrix;
    varying vec4 vUv; varying vec3 vWelt;
    #include <common>
    void main() {
      vUv = textureMatrix * vec4( position, 1.0 );
      vWelt = ( modelMatrix * vec4( position, 1.0 ) ).xyz;
      gl_Position = projectionMatrix * modelViewMatrix * vec4( position, 1.0 );
    }`,
  fragmentShader: /* glsl */`
    uniform sampler2D tDiffuse; uniform float staerke; uniform float lod;
    uniform vec2 insel; uniform vec2 texel; uniform vec3 mitte;
    varying vec4 vUv; varying vec3 vWelt;
    void main() {
      // Zwölf Abtastungen auf zwei Ringen in einer groben Mipmap-Stufe --
      // genug, dass von den Rädern nur noch ein Farbschimmer bleibt, wie
      // bei Rauheit 0,3 im Poster. Die erste Fassung (fünf Abtastungen,
      // Stufe 2,5) zeigte die Felgen noch als Felgen.
      vec2 uv = vUv.xy / vUv.w; vec2 d = texel * exp2( lod );
      vec3 c = textureLod( tDiffuse, uv, lod ).rgb * 0.16;
      for ( int i = 0; i < 6; i ++ ) {
        float w = float( i ) * 1.0472;
        vec2 o = vec2( cos( w ), sin( w ) );
        c += textureLod( tDiffuse, uv + o * d, lod ).rgb * 0.08;
        c += textureLod( tDiffuse, uv + o.yx * vec2( 1.0, - 1.0 ) * d * 2.2, lod + 0.7 ).rgb * 0.06;
      }
      float f = 1.0 - smoothstep( insel.x, insel.y, distance( vWelt.xz, mitte.xz ) );
      gl_FragColor = vec4( c * staerke * f, 1.0 );
      #include <tonemapping_fragment>
      #include <colorspace_fragment>
    }`,
};

export async function erstellen({
  behaelter, glb, kameraUrl, bodenUrl, einstellungen = { pixel: 1.5, schatten: 0 },
  bezeichnung, beiBewegung, beiRuhe, beiBild, beiAnker, variante = 0, belichtung,
  look,
}) {
  const K = await fetch(kameraUrl).then((a) => a.json());

  /* ------------------------------------------------------------- Renderer */
  const leinwand = document.createElement('canvas');
  leinwand.className = 'echtzeit__leinwand';
  const r = new THREE.WebGLRenderer({ canvas: leinwand, antialias: true, alpha: false, powerPreference: 'high-performance' });
  r.outputColorSpace = THREE.SRGBColorSpace;
  /* AgX wie im Poster. Blender legt noch den Look "Medium High Contrast"
     darüber; den gibt es in three.js nicht -- die Belichtung ist deshalb
     am Poster gemessen, nicht übernommen. */
  lookSetzen(look || K.look);
  r.toneMapping = THREE.CustomToneMapping;
  r.toneMappingExposure = belichtung ?? K.web_belichtung ?? 1.0;
  r.shadowMap.enabled = false;
  behaelter.appendChild(leinwand);
  if (bezeichnung) leinwand.setAttribute('aria-label', bezeichnung);

  const szene = new THREE.Scene();
  /* Grund der Seite, wie ihn Blender für Kamerastrahlen setzt. Der Wert ist
     am Poster abgelesen (linke obere Ecke), nicht umgerechnet: AgX hebt
     tiefe Schwarztöne leicht an. */
  r.setClearColor(new THREE.Color().setRGB(...(K.grund_srgb || [0.02, 0.024, 0.035]), THREE.SRGBColorSpace));

  /* --------------------------------------------------------------- Licht */
  /* Das ganze Studio als Umgebung: Blender rechnet aus der Mitte des Modells
     ein Rundumbild von HDRI UND Flächenlichtern (branchen_studio.py,
     Modus "umgebung"). Gerichtete Lichter hätten aus dem 3,2 x 6 m großen
     Deckendiffusor einen Lichtpunkt gemacht; so liegt er als weiches Band auf
     dem Lack, wie im Poster. Beide Rundumbilder schauen in der Bildmitte
     nach +X, oben ist oben -- dieselbe Lage wie three.js' Equirect-Abbildung,
     deshalb ohne Drehung. */
  const pmrem = new THREE.PMREMGenerator(r);
  // Der Stand (?v=…) der Kameradatei gilt für alles aus demselben Ordner:
  // Der Server gibt diesen Dateien dreißig Tage Zwischenspeicher.
  const ordner = kameraUrl.replace(/[^/]+$/, '');
  const stand = new URL(kameraUrl, location.href).search;
  const hdrLader = new HDRLoader();
  async function umgebungLaden(datei) {
    const t = await hdrLader.loadAsync(ordner + datei + stand);
    t.mapping = THREE.EquirectangularReflectionMapping;
    const u = pmrem.fromEquirectangular(t).texture; t.dispose();
    return u;
  }
  const umgebung = await umgebungLaden(K.umgebung.datei);
  /* Der Boden sieht die Modelllichter nicht (Lichtverknüpfung in Blender) --
     er bekommt die Umgebung OHNE sie. Mit der vollen Umgebung spiegelte er
     den Deckendiffusor und wurde hellgrau (erster Vergleich am Poster). */
  const umgebungBoden = await umgebungLaden(K.umgebung_boden.datei);
  pmrem.dispose();
  szene.environment = umgebung;
  szene.environmentIntensity = 1;
  const lichter = [];

  /* --------------------------------------------------------------- Modell */
  const lader = new GLTFLoader(); lader.setMeshoptDecoder(MeshoptDecoder);
  const gltf = await lader.loadAsync(glb);
  const modell = gltf.scene; szene.add(modell);
  const parser = gltf.parser;
  const json = parser.json;
  const namen = (json.extensions && json.extensions.KHR_materials_variants && json.extensions.KHR_materials_variants.variants || []).map((v) => v.name);

  /* Variantenzuordnung je Mesh: welches glTF-Material bei welcher Variante.
     parser.associations kennt zu jedem three-Mesh das glTF-Primitiv. */
  const zuordnung = [];
  let dreiecke = 0;
  const leuchtend = new Set();
  modell.traverse((o) => {
    if (!o.isMesh) return;
    const idx = o.geometry.index; dreiecke += (idx ? idx.count : o.geometry.attributes.position.count) / 3;
    const a = parser.associations.get(o);
    if (a && a.meshes !== undefined && a.primitives !== undefined) {
      const prim = json.meshes[a.meshes].primitives[a.primitives];
      const ext = prim.extensions && prim.extensions.KHR_materials_variants;
      if (ext) zuordnung.push({ mesh: o, standard: o.material, mappings: ext.mappings });
    }
    const m = o.material;
    if (m && m.emissive && /head|brake|signal|light|dashboard/i.test(m.name || '')) leuchtend.add(m);
  });
  const leuchtStaerke = new Map([...leuchtend].map((m) => [m, m.emissiveIntensity]));

  /* Zwei Gruppen im selben GLB (23.09.2026): Lacke und Ausstattungen
     ("Innen: ..."). Jedes Netz gehoert zu genau einer Gruppe; beim Wechsel
     des Lacks darf der Innenraum nicht auf seinen Standard zurueckfallen.
     assignFinalMaterial: Netze mit gebackener Verdeckung (Punktfarbe)
     brauchen eine Materialkopie mit vertexColors -- ohne sie war der
     Innenraum nach dem ersten Wechsel wieder flach ausgeleuchtet. */
  const istInnen = (i) => /^Innen/.test(namen[i] || '');
  const lackIdx = namen.map((_, i) => i).filter((i) => !istInnen(i));
  const innenIdx = namen.map((_, i) => i).filter(istInnen);
  let aktLack = lackIdx.length ? lackIdx[0] : 0; let aktInnen = innenIdx.length ? innenIdx[0] : -1;
  async function varianteSetzen(i) {
    if (istInnen(i)) aktInnen = i; else aktLack = i;
    const auftraege = zuordnung.map(async (z) => {
      const treffer = z.mappings.find((mp) => mp.variants.includes(aktLack) || mp.variants.includes(aktInnen));
      z.mesh.material = treffer ? await parser.getDependency('material', treffer.material) : z.standard;
      parser.assignFinalMaterial(z.mesh);
      if (rohbauMat) rohbauMat.delete(z.mesh.material);
    });
    await Promise.all(auftraege);
    rohbauAnwenden();
    einmal();
  }

  /* ------------------------------------------------------------ Zerlegen
     Wie eine technische Explosionszeichnung, nicht wie eine Explosion: Jede
     Baugruppe fährt auf ihrer eigenen, konstruktiv sinnvollen Achse aus --
     Türen zur Seite, Haube nach vorn oben, Heck nach hinten oben, das Dach
     senkrecht hoch, Räder auf der Achse nach außen, dahinter Bremsscheibe
     und Sattel gestaffelt. Und nacheinander: erst die Hülle, dann Glas und
     Dach, dann Räder und Bremsen, zuletzt der Innenraum. So liest man, wie
     das Auto gebaut ist.

     Die erste Fassung schob jedes Teil strahlenförmig von der Modellmitte
     weg, alle gleichzeitig -- das sah aus wie ein Unfall, nicht wie ein
     Aufbau. Die Regeln stehen jetzt als Daten in kamera.json ("zerlegen"),
     damit ein anderes Modell eigene bekommt, ohne dass hier Code wechselt.

     Achsen in Weltkoordinaten: seite = nach außen (Vorzeichen der Seite, auf
     der das Teil sitzt), hoch = +Y, vor = +Z (Fahrtrichtung). */
  const Z_REGELN = (K.zerlegen && K.zerlegen.regeln) || [];
  const Z_DAUER = (K.zerlegen && K.zerlegen.dauer) || 2.4;
  const Z_BREITE = (K.zerlegen && K.zerlegen.breite) || 0.34;
  const gesamt = new THREE.Box3().setFromObject(modell);
  const mitte = gesamt.getCenter(new THREE.Vector3());
  const teile = [];
  const anker = new Map();           // Beschriftung -> Teil
  {
    const regeln = Z_REGELN.map((r) => ({ ...r, re: new RegExp(r.muster), eltern: r.eltern ? new RegExp(r.eltern) : null }));
    const bewegt = new Set();
    const k = new THREE.Box3(); const c = new THREE.Vector3();
    modell.updateMatrixWorld(true);
    modell.traverse((o) => {
      if (o === modell) return;
      // Hängt schon ein Vorfahr an einer Regel, fährt dieses Teil mit ihm.
      for (let v = o.parent; v; v = v.parent) if (bewegt.has(v)) return;
      const name = o.name || '';
      const eltern = (o.parent && o.parent.name) || '';
      const r = regeln.find((x) => x.re.test(name) && (!x.eltern || x.eltern.test(eltern)));
      if (!r) return;
      bewegt.add(o);
      k.setFromObject(o); k.getCenter(c);
      const seite = Math.sign(c.x - mitte.x) || 1;
      const weltWeg = new THREE.Vector3((r.seite || 0) * seite, r.hoch || 0, r.vor || 0);
      const lokal = o.parent.worldToLocal(c.clone().add(weltWeg)).sub(o.parent.worldToLocal(c.clone()));
      const t = { o, ruhe: o.position.clone(), weg: lokal, start: r.start || 0, stufe: r.stufe || 1, beschriftung: r.beschriftung || null, mitteLokal: o.worldToLocal(c.clone()) };
      /* Tueren drehen an ihrer Scharnierachse (extras aus fahrzeug_bau.py):
         Achse in glTF-Koordinaten, Winkel in Grad, Vorzeichen so, dass die
         Hinterkante nach aussen schwingt. */
      if (r.dreh && o.userData && o.userData.achse) {
        t.dreh = { achse: new THREE.Vector3(...o.userData.achse).normalize(), winkel: THREE.MathUtils.degToRad(o.userData.winkel || 65) * -(o.userData.seite || 1), ruhe: o.quaternion.clone() };
        t.weg = new THREE.Vector3();
      }
      if (r.rohbau) t.rohbau = true;
      teile.push(t);
      o.traverse((m) => { if (m.isMesh) m.castShadow = true; });
      if (t.beschriftung && !anker.has(t.beschriftung)) anker.set(t.beschriftung, [t]);
      else if (t.beschriftung) anker.get(t.beschriftung).push(t);
    });
  }
  let zerlegt = 0; let zerlegtSoll = 0; let zerlegtSeit = 0;
  /* Feste Punkte mit Namen (23.09.2026, zuerst beim Schuh): Wo sich nichts
     zerlegen laesst, zeigt die Ansicht "Details" die Teile trotzdem --
     Obermaterial, Zwischensohle, Schnuerung. Die Orte stehen in kamera.json
     (punkte), gemessen per Strahl auf das Netz; die Normale sagt, ob der
     Punkt gerade zur Kamera zeigt oder hinter dem Schuh liegt. */
  const punkte = (K.punkte || []).map((q) => ({ schluessel: q.schluessel, ort: new THREE.Vector3(...q.ort), normale: new THREE.Vector3(...(q.normale || [0, 1, 0])).normalize() }));
  let punkteSoll = 0; let punkteAnteil = 0;
  const pz = new THREE.Vector3(); const pnrm = new THREE.Vector3();
  const weich = (x) => (x < 0.5 ? 4 * x * x * x : 1 - Math.pow(-2 * x + 2, 3) / 2);
  function anteil(t, p) { return weich(Math.min(1, Math.max(0, (p - t.start) / Z_BREITE))); }
  /* Stufen: Zielwert von "zerlegt", bei dem alle Teile bis einschliesslich
     Stufe k stehen und die naechste Stufe noch nicht begonnen hat. */
  const STUFEN_ZIEL = [0];
  for (let k = 1; k <= 4; k++) {
    const st = teile.filter((t) => t.stufe <= k).map((t) => t.start);
    STUFEN_ZIEL.push(st.length ? Math.min(1, (Math.max(...st) + Z_BREITE) / (1 + Z_BREITE)) : STUFEN_ZIEL[k - 1]);
  }
  const tuerQ = new THREE.Quaternion();
  let fahrerTuer = 0;               // Kamerafahrt: Fahrertuer oeffnen, unabhaengig vom Zerlegen
  let rohbau = 0;
  function zerlegenAnwenden() {
    const p = zerlegt * (1 + Z_BREITE);
    for (const t of teile) {
      const a = anteil(t, p);
      if (t.dreh) {
        const f = t.o.name === 'tuer_v_r_angel' ? Math.max(a, fahrerTuer) : a;
        t.o.quaternion.copy(t.dreh.ruhe).multiply(tuerQ.setFromAxisAngle(t.dreh.achse, t.dreh.winkel * f));
      } else t.o.position.copy(t.ruhe).addScaledVector(t.weg, a);
      if (t.rohbau) rohbau = a;
    }
    rohbauAnwenden();
  }
  /* Rohbau (Stufe 4): Der Lack weicht der grauen Tauchgrundierung (KTL),
     so steht eine Karosserie vor der Lackierung im Werk. */
  const rohbauMat = new Map();
  const KTL = new THREE.Color().setRGB(0.29, 0.30, 0.31);
  let rohbauStand = -1;
  function rohbauAnwenden() {
    if (Math.abs(rohbau - rohbauStand) < 1e-4) return;
    rohbauStand = rohbau;
    for (const z of zuordnung) {
      if (!z.mappings.some((mp) => mp.variants.some((v) => lackIdx.includes(v)))) continue;
      const m = z.mesh.material;
      if (!rohbauMat.has(m)) rohbauMat.set(m, { c: m.color.clone(), me: m.metalness, ro: m.roughness, cc: m.clearcoat || 0 });
      const o = rohbauMat.get(m);
      m.color.copy(o.c).lerp(KTL, rohbau);
      m.metalness = o.me * (1 - rohbau); m.roughness = o.ro + (0.62 - o.ro) * rohbau;
      if ('clearcoat' in m) m.clearcoat = o.cc * (1 - rohbau);
    }
  }

  /* Wie weit muss die Kamera zurück, damit das Zerlegte ganz ins Bild passt?
     Gerechnet an der Hülle im voll zerlegten Zustand, nicht geschätzt: Die
     erste Fassung ging pauschal auf das 1,5-Fache -- zu wenig, als die Türen
     1,15 m zur Seite fuhren. */
  function zerlegtHuelle() {
    const vorher = zerlegt; zerlegt = 1; zerlegenAnwenden(); modell.updateMatrixWorld(true);
    const b = new THREE.Box3().setFromObject(modell);
    zerlegt = vorher; zerlegenAnwenden(); modell.updateMatrixWorld(true);
    return b;
  }

  /* Kleinster Abstand, bei dem alle acht Ecken der Hülle im Bild liegen --
     mit dem Bildwinkel und Beschnitt, der gerade gilt. Die zweite Fassung
     passte eine Kugel um den Zielpunkt ein: sicher, aber das zerlegte Auto
     stand danach als Spielzeug in der Bildmitte (Probe 2). */
  function einpassen(b, winkel, neig) {
    const ecken = [];
    for (const x of [b.min.x, b.max.x]) for (const y of [b.min.y, b.max.y]) for (const z of [b.min.z, b.max.z]) ecken.push(new THREE.Vector3(x, y, z));
    const merk = { ...ist }; const v = new THREE.Vector3();
    const passt = (abst) => {
      ist = { ...ist, winkel, neig, abst }; kameraSetzen(); kamera.updateMatrixWorld();
      return ecken.every((e) => { v.copy(e).project(kamera); return Math.abs(v.x) < 0.9 && v.y < 0.78 && v.y > -0.9 && v.z < 1; });
    };
    let lo = heim.abst * 0.6, hi = heim.abst * 4;
    for (let i = 0; i < 22; i++) { const m = (lo + hi) / 2; if (passt(m)) hi = m; else lo = m; }
    ist = merk; kameraSetzen();
    return hi;
  }

  /* Schatten nur für das, was sich bewegt. Unter dem ganzen Auto liegt der
     gebackene Kontaktschatten aus Blender; ein zweiter Echtzeitschatten dort
     hätte den Boden doppelt abgedunkelt. Ein Rad, das 85 cm neben dem Auto
     schwebt, braucht aber seinen eigenen -- sonst schwebt es wirklich. Das
     Licht hat die Stärke null: Es wirft nur Schatten, es hellt nichts auf. */
  const schattenLicht = new THREE.DirectionalLight(0xffffff, 0);
  schattenLicht.position.set(mitte.x + 0.8, mitte.y + 12, mitte.z + 1.2);
  schattenLicht.target.position.copy(mitte);
  Object.assign(schattenLicht.shadow.camera, { left: -5, right: 5, top: 5, bottom: -5, near: 1, far: 30 });
  schattenLicht.shadow.bias = -0.0004; schattenLicht.shadow.normalBias = 0.02;
  schattenLicht.shadow.radius = 6; schattenLicht.shadow.blurSamples = 16;
  szene.add(schattenLicht, schattenLicht.target);
  const schattenFlaeche = new THREE.Mesh(new THREE.PlaneGeometry(12, 12), new THREE.ShadowMaterial({ opacity: 0, transparent: true, depthWrite: false }));
  schattenFlaeche.rotation.x = -Math.PI / 2;
  schattenFlaeche.receiveShadow = true;
  schattenFlaeche.renderOrder = 1;
  szene.add(schattenFlaeche);

  /* ---------------------------------------------------------------- Boden */
  const B = K.boden;
  const bodenGeo = new THREE.PlaneGeometry(B.groesse_m, B.groesse_m); bodenGeo.rotateX(-Math.PI / 2);
  // uv2 = uv: lightMap liest den zweiten Kanal
  bodenGeo.setAttribute('uv1', bodenGeo.attributes.uv);
  const bodenLicht = await new THREE.TextureLoader().loadAsync(bodenUrl);
  // sRGB-kodiert, damit der Kontaktschatten 8 Bit übersteht (branchen-web.py)
  bodenLicht.colorSpace = THREE.SRGBColorSpace; bodenLicht.channel = 1;
  const glanzInsel = radial(B.insel_m[0], B.insel_m[1], B.groesse_m, 255, 0);
  const auslauf = radial(B.auslauf_m[0], Math.min(B.auslauf_m[1], B.groesse_m / 2), B.groesse_m, 255, 0);
  const bodenMat = new THREE.MeshPhysicalMaterial({
    color: new THREE.Color().setRGB(...(K.web_boden?.farbe || [1, 1, 1]).map((f) => B.albedo_echt * f)),
    roughness: (B.rauheit[0] + B.rauheit[1]) / 2, metalness: 0,
    lightMap: bodenLicht,
    /* Blender backt die Leuchtdichte eines Bodens mit Albedo 0,08 (L = a·E/π);
       three.js rechnet L = Texel·I·Albedo/π. Mit dem Texel normiert auf
       licht_skala ergibt das I = π·Skala/a -- gerechnet, nicht eingestellt. */
    lightMapIntensity: Math.PI * (B.licht_skala || 1) / B.albedo_gebacken,
    specularIntensity: K.web_boden?.glanz ?? B.glanz_max, specularIntensityMap: glanzInsel,
    alphaMap: auslauf, transparent: true, depthWrite: false,
    envMap: umgebungBoden, envMapIntensity: K.web_boden?.umgebung ?? 1,
  });
  const bodenLichtStaerke = bodenMat.lightMapIntensity;
  const boden = new THREE.Mesh(bodenGeo, bodenMat);
  boden.position.set(B.mitte[0], B.mitte[1] - 0.0005, B.mitte[2]);
  boden.renderOrder = -1;
  szene.add(boden);
  schattenFlaeche.position.set(B.mitte[0], B.mitte[1] + 0.001, B.mitte[2]);

  let spiegel = null;
  function spiegelBauen(an) {
    if (!!spiegel === an) return;
    if (!an) { szene.remove(spiegel); spiegel.dispose(); spiegel = null; einmal(); return; }
    const w = Math.max(2, Math.round((behaelter.clientWidth || 800) * 0.5));
    const h = Math.max(2, Math.round((behaelter.clientHeight || 450) * 0.5));
    spiegel = new Reflector(new THREE.PlaneGeometry(B.groesse_m, B.groesse_m), {
      textureWidth: w, textureHeight: h, clipBias: 0.003, multisample: 0, shader: SPIEGEL,
    });
    const rt = spiegel.getRenderTarget();
    rt.texture.generateMipmaps = true; rt.texture.minFilter = THREE.LinearMipmapLinearFilter;
    const u = spiegel.material.uniforms;
    u.staerke.value = K.web_spiegel?.staerke ?? 0.3;
    u.lod.value = K.web_spiegel?.lod ?? 2.5;
    u.insel.value.set(B.insel_m[0], B.insel_m[1]);
    u.mitte.value.set(...B.mitte);
    u.texel.value.set(1 / w, 1 / h);
    Object.assign(spiegel.material, { transparent: true, blending: THREE.AdditiveBlending, depthWrite: false });
    spiegel.rotation.x = -Math.PI / 2;
    spiegel.position.set(B.mitte[0], B.mitte[1] + 0.0008, B.mitte[2]);
    // Der Boden liegt genau in der Spiegelebene -- im Spiegelbild würde er
    // mit sich selbst flimmern. Für die Dauer des Spiegelbilds aus.
    const vor = spiegel.onBeforeRender;
    spiegel.onBeforeRender = function (...a) { boden.visible = false; vor.apply(this, a); boden.visible = true; };
    szene.add(spiegel);
  }

  /* ---------------------------------------------------------------- Kamera */
  const kamera = new THREE.PerspectiveCamera(30, 16 / 9, 0.05, 200);
  const Z = new THREE.Vector3(...K.ziel);
  const P0 = new THREE.Vector3(...K.position);
  const v0 = P0.clone().sub(Z);
  const heim = {
    winkel: Math.atan2(v0.x, v0.z),
    neig: Math.atan2(v0.y, Math.hypot(v0.x, v0.z)),
    abst: v0.length(),
    lens: K.brennweite_mm,
  };
  let soll = { ...heim }; let ist = { ...heim };
  const grenzen = { neig: [0.02, 1.2], abst: [heim.abst * 0.55, heim.abst * 1.6] };

  function projektion(w, h) {
    const a = w / h;
    const tanH = 18 / ist.lens;
    const tanV = tanH / REF;
    const tanVc = a <= REF ? tanV : tanH / a;
    kamera.aspect = a;
    kamera.fov = THREE.MathUtils.radToDeg(2 * Math.atan(tanVc));
    kamera.updateProjectionMatrix();
  }
  /* Freie Kamera (Kamerafahrt in den Innenraum): Ort, Blickziel und
     Brennweite direkt statt ueber die Umlaufbahn um Z. */
  let frei = null;
  function kameraSetzen() {
    if (frei) {
      kamera.position.copy(frei.ort); kamera.lookAt(frei.ziel);
      const merk = ist.lens; ist.lens = frei.lens;
      projektion(leinwand.clientWidth || 16, leinwand.clientHeight || 9);
      ist.lens = merk;
      return;
    }
    const cn = Math.cos(ist.neig);
    kamera.position.set(Z.x + Math.sin(ist.winkel) * cn * ist.abst, Z.y + Math.sin(ist.neig) * ist.abst, Z.z + Math.cos(ist.winkel) * cn * ist.abst);
    kamera.lookAt(Z);
    projektion(leinwand.clientWidth || 16, leinwand.clientHeight || 9);
  }
  function annaehern(f) {
    const dw = wickel(soll.winkel - ist.winkel);
    ist.winkel += dw * f; ist.neig += (soll.neig - ist.neig) * f; ist.abst += (soll.abst - ist.abst) * f;
    return Math.abs(dw) + Math.abs(soll.neig - ist.neig) + Math.abs(soll.abst - ist.abst) * 0.05;
  }

  /* ------------------------------------------------------------ Innenraum
     Kamerafahrt durch die Fahrertuer (23.09.2026): erst um das Auto herum
     auf die Fahrerseite (Umlaufbahn, die Tuer schwingt dabei auf), dann auf
     einer Bahn durch die Tueroeffnung bis zum Augpunkt des Fahrers -- genau
     der Standpunkt der Innenraumfotos aus Cycles (kamera.json "innen").
     Dort schliesst die Tuer, und wer zieht, schaut sich um. Zurueck
     dieselbe Bahn rueckwaerts. */
  const KI = K.innen || null;
  const innenAuge = KI ? new THREE.Vector3(...KI.position) : null;
  const innenZiel = KI ? new THREE.Vector3(...KI.ziel) : null;
  let modus = 'aussen';          // aussen | rein | innen | raus
  let wartendZerlegen = null; let aktStufe = 0;
  const MIT_STUFEN = !!(K.zerlegen && K.zerlegen.stufen);
  let fahrt = null;              // { t, dauer, ort: [..], ziel: [..], lens: [a, b], fertig }
  const blick = { gier: 0, nick: 0 }; const blickSoll = { gier: 0, nick: 0 };
  let tuerSoll = 0;
  const weichS = (x) => x * x * (3 - 2 * x);
  function bahnPunkt(pkte, u) {
    // Catmull-Rom durch die Stuetzpunkte, u in [0, 1]
    const n = pkte.length - 1; const f = Math.min(n - 1e-6, u * n); const i = Math.floor(f); const t = f - i;
    const p0 = pkte[Math.max(0, i - 1)], p1 = pkte[i], p2 = pkte[i + 1], p3 = pkte[Math.min(n, i + 2)];
    const t2 = t * t, t3 = t2 * t;
    return new THREE.Vector3(
      0.5 * (2 * p1.x + (-p0.x + p2.x) * t + (2 * p0.x - 5 * p1.x + 4 * p2.x - p3.x) * t2 + (-p0.x + 3 * p1.x - 3 * p2.x + p3.x) * t3),
      0.5 * (2 * p1.y + (-p0.y + p2.y) * t + (2 * p0.y - 5 * p1.y + 4 * p2.y - p3.y) * t2 + (-p0.y + 3 * p1.y - 3 * p2.y + p3.y) * t3),
      0.5 * (2 * p1.z + (-p0.z + p2.z) * t + (2 * p0.z - 5 * p1.z + 4 * p2.z - p3.z) * t2 + (-p0.z + 3 * p1.z - 3 * p2.z + p3.z) * t3));
  }
  function tuerPunkt() {
    // Vor der geoeffneten Fahrertuer: 0,95 m neben dem Augpunkt, etwas dahinter
    return innenAuge.clone().add(new THREE.Vector3(0.95, 0.10, -0.14));
  }
  function umlaufZuTuer() {
    const a = tuerPunkt();
    return { winkel: Math.atan2(a.x - Z.x, a.z - Z.z) - 0.25, neig: 0.10, abst: Math.max(heim.abst * 0.72, 3.3), lens: heim.lens };
  }
  function fahrtAnfangen(richtung) {
    const umlauf = umlaufZuTuer(); const cn = Math.cos(umlauf.neig);
    const ausPunkt = new THREE.Vector3(Z.x + Math.sin(umlauf.winkel) * cn * umlauf.abst, Z.y + Math.sin(umlauf.neig) * umlauf.abst, Z.z + Math.cos(umlauf.winkel) * cn * umlauf.abst);
    const ort = [ausPunkt, tuerPunkt(), innenAuge.clone()];
    const ziel = [Z.clone(), innenZiel.clone().lerp(Z, 0.35), innenZiel.clone()];
    const lens = [heim.lens, KI.brennweite_mm || 20];
    if (richtung < 0) { ort.reverse(); ziel.reverse(); lens.reverse(); }
    fahrt = { t: 0, dauer: 2.6, ort, ziel, lens, richtung };
  }
  function blickRichtung() {
    const v = innenZiel.clone().sub(innenAuge);
    v.applyAxisAngle(new THREE.Vector3(0, 1, 0), -blick.gier);
    const rechts = new THREE.Vector3().crossVectors(v, new THREE.Vector3(0, 1, 0)).normalize();
    v.applyAxisAngle(rechts, -blick.nick);
    return innenAuge.clone().add(v);
  }
  /* Ein Schritt der Fahrt; true, solange noch Bewegung ist. */
  function innenSchritt(dt) {
    let bewegt_ = false;
    // Tuer
    if (fahrerTuer !== tuerSoll) {
      const s = BEWEGUNG_AUS ? 1 : dt / 1.1;
      fahrerTuer = tuerSoll > fahrerTuer ? Math.min(tuerSoll, fahrerTuer + s) : Math.max(tuerSoll, fahrerTuer - s);
      zerlegenAnwenden(); bewegt_ = true;
    }
    if (modus === 'rein' && !fahrt) {
      // Phase 1: Umlauf zur Fahrerseite, bis die Kamera dort steht
      soll = { ...umlaufZuTuer() };
      if (Math.abs(wickel(soll.winkel - ist.winkel)) < 0.03 && Math.abs(soll.abst - ist.abst) < 0.05 && fahrerTuer > 0.85) fahrtAnfangen(1);
      return true;
    }
    if (fahrt) {
      fahrt.t = Math.min(1, fahrt.t + (BEWEGUNG_AUS ? 1 : dt / fahrt.dauer));
      const u = weichS(fahrt.t);
      const ziel = fahrt.ziel[0].clone().lerp(fahrt.ziel[1], Math.min(1, u * 2)).lerp(fahrt.ziel[2], Math.max(0, u * 2 - 1));
      frei = { ort: bahnPunkt(fahrt.ort, u), ziel, lens: fahrt.lens[0] + (fahrt.lens[1] - fahrt.lens[0]) * u };
      if (fahrt.t >= 1) {
        if (fahrt.richtung > 0) { modus = 'innen'; fahrt = null; tuerSoll = 0; blick.gier = blick.nick = blickSoll.gier = blickSoll.nick = 0; letzteBewegung = performance.now(); }
        else {
          modus = 'aussen'; fahrt = null; frei = null; tuerSoll = 0;
          const umlauf = umlaufZuTuer(); ist = { ...ist, ...umlauf }; soll = { ...heim };
          if (wartendZerlegen) { const w = wartendZerlegen; wartendZerlegen = null; api.zerlegen(w); }
        }
      }
      return true;
    }
    if (modus === 'innen') {
      const f = BEWEGUNG_AUS ? 1 : 1 - Math.exp(-dt * (ziehen ? 10 : 3));
      blick.gier += (blickSoll.gier - blick.gier) * f; blick.nick += (blickSoll.nick - blick.nick) * f;
      // nach dem Loslassen langsam zurueck auf den Standpunkt des Fotos
      if (!ziehen && performance.now() - letzteBewegung > 2200) { blickSoll.gier = 0; blickSoll.nick = 0; }
      frei = { ort: innenAuge, ziel: blickRichtung(), lens: KI.brennweite_mm || 20 };
      return bewegt_ || Math.abs(blickSoll.gier - blick.gier) + Math.abs(blickSoll.nick - blick.nick) > 0.0005 || fahrerTuer !== tuerSoll;
    }
    if (modus === 'raus' && !fahrt) {
      if (fahrerTuer > 0.85) fahrtAnfangen(-1);
      return true;
    }
    return bewegt_;
  }

  /* ---------------------------------------------------------------- Finger */
  let ziehen = null; let letzteBewegung = performance.now(); let ruhtGemeldet = true;
  const zeiger = new Map(); let spreiz = 0;
  leinwand.style.touchAction = 'pan-y';
  leinwand.tabIndex = 0;
  function bewegt() { ruhtGemeldet = false; letzteBewegung = performance.now(); beiBewegung && beiBewegung(); starten(); }
  leinwand.addEventListener('pointerdown', (e) => {
    if (e.button !== 0) return;
    zeiger.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (zeiger.size === 2) { const [a, b] = [...zeiger.values()]; spreiz = Math.hypot(a.x - b.x, a.y - b.y); }
    ziehen = { x: e.clientX, y: e.clientY, id: e.pointerId };
    // Kann werfen, wenn der Zeiger schon wieder weg ist (schneller Tipp) --
    // dann eben ohne Einfangen, das Drehen geht trotzdem.
    try { leinwand.setPointerCapture(e.pointerId); } catch { /* ohne */ }
    bewegt();
  });
  leinwand.addEventListener('pointermove', (e) => {
    if (!zeiger.has(e.pointerId)) return;
    zeiger.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (zeiger.size === 2) {
      const [a, b] = [...zeiger.values()]; const d = Math.hypot(a.x - b.x, a.y - b.y);
      if (spreiz) soll.abst = THREE.MathUtils.clamp(soll.abst * (spreiz / d), ...grenzen.abst);
      spreiz = d; letzteBewegung = performance.now(); return;
    }
    if (!ziehen || e.pointerId !== ziehen.id) return;
    const dx = e.clientX - ziehen.x, dy = e.clientY - ziehen.y;
    ziehen.x = e.clientX; ziehen.y = e.clientY;
    if (modus === 'innen') {
      blickSoll.gier = THREE.MathUtils.clamp(blickSoll.gier + dx * 0.004, -1.2, 1.2);
      blickSoll.nick = THREE.MathUtils.clamp(blickSoll.nick + dy * 0.003, -0.45, 0.35);
      letzteBewegung = performance.now(); return;
    }
    if (modus !== 'aussen') return;
    soll.winkel -= dx * 0.006;
    soll.neig = THREE.MathUtils.clamp(soll.neig + dy * 0.004, ...grenzen.neig);
    letzteBewegung = performance.now();
  });
  const los = (e) => {
    zeiger.delete(e.pointerId); if (zeiger.size < 2) spreiz = 0;
    if (ziehen && e.pointerId === ziehen.id) { ziehen = null; letzteBewegung = performance.now(); }
  };
  leinwand.addEventListener('pointerup', los);
  leinwand.addEventListener('pointercancel', los);
  leinwand.addEventListener('lostpointercapture', los);
  /* Mausrad nur mit gedrückter Strg-Taste (oder Trackpad-Pinch, das Chrome
     als Strg+Rad meldet). Ohne diese Bedingung kapert die Bühne beim
     Scrollen durch die Seite das Rad -- der Besucher bliebe hängen. */
  leinwand.addEventListener('wheel', (e) => {
    if (!e.ctrlKey) return;
    e.preventDefault();
    soll.abst = THREE.MathUtils.clamp(soll.abst * Math.exp(e.deltaY * 0.004), ...grenzen.abst);
    bewegt();
  }, { passive: false });
  leinwand.addEventListener('keydown', (e) => {
    const t = { ArrowLeft: [1, 0], ArrowRight: [-1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[e.key];
    if (!t) return;
    e.preventDefault();
    soll.winkel += t[0] * 0.1;
    soll.neig = THREE.MathUtils.clamp(soll.neig + t[1] * 0.06, ...grenzen.neig);
    bewegt();
  });

  /* ------------------------------------------------------------- Stufen */
  let schattenErlaubt = false;
  function stufeSetzen(e) {
    r.setPixelRatio(Math.min(window.devicePixelRatio || 1, e.pixel));
    // Echtzeitschatten der zerlegten Teile: wie die Spiegelung erst ab HIGH.
    schattenErlaubt = !!e.spiegel && teile.length > 0;
    if (schattenErlaubt !== r.shadowMap.enabled) {
      r.shadowMap.enabled = schattenErlaubt; r.shadowMap.type = THREE.PCFShadowMap;   // PCFSoft ist in r185 abgekuendigt
      r.shadowMap.autoUpdate = false; r.shadowMap.needsUpdate = true;
      schattenLicht.castShadow = schattenErlaubt;
      schattenLicht.shadow.mapSize.set(e.pixel >= 2 ? 2048 : 1024, e.pixel >= 2 ? 2048 : 1024);
      schattenFlaeche.material.needsUpdate = true;
    }
    // Die Spiegelung rendert das Modell ein zweites Mal -- erst ab HIGH, und
    // nur, wo das Poster eine zeigt (Schuh: rauer Boden, keine Spiegelung).
    spiegelBauen(!!e.spiegel && (K.web_spiegel?.staerke ?? 0.3) > 0);
    groesse();
  }
  function groesse() {
    const w = Math.max(1, behaelter.clientWidth), h = Math.max(1, behaelter.clientHeight);
    r.setSize(w, h, false);
    if (spiegel) {
      const pr = r.getPixelRatio();
      const sw = Math.max(2, Math.round(w * pr * 0.5)), sh = Math.max(2, Math.round(h * pr * 0.5));
      spiegel.getRenderTarget().setSize(sw, sh);
      spiegel.material.uniforms.texel.value.set(1 / sw, 1 / sh);
    }
    kameraSetzen(); einmal();
  }
  const ro = new ResizeObserver(groesse); ro.observe(behaelter);

  /* ---------------------------------------------------------- Beschriftung
     Wo die beschrifteten Baugruppen gerade auf dem Bildschirm liegen. Die
     Seite zeichnet daraus die Etiketten (branchen.js); hier wird nur
     gerechnet. Von zwei gleichen Teilen (linke und rechte Tür, vier Räder)
     zählt das, das der Kamera am nächsten ist -- das andere steht dahinter. */
  const pw = new THREE.Vector3(); const pn = new THREE.Vector3();
  let ankerLeer = true; const zerlegtHuelleJetzt = new THREE.Box3();
  function ankerMelden() {
    if (!beiAnker) return;
    if (zerlegt < 0.02 && punkte.length && punkteAnteil > 0.01) {
      ankerLeer = false;
      const w = leinwand.clientWidth, h = leinwand.clientHeight; const liste = [];
      for (const q of punkte) {
        modell.localToWorld(pw.copy(q.ort));
        pnrm.copy(q.normale).transformDirection(modell.matrixWorld);
        const zurKamera = pnrm.dot(pz.copy(kamera.position).sub(pw).normalize());
        pn.copy(pw).project(kamera);
        const x = (pn.x * 0.5 + 0.5) * w, y = (-pn.y * 0.5 + 0.5) * h;
        // Weich ausblenden, wenn der Punkt sich von der Kamera wegdreht
        const sicht = Math.max(0, Math.min(1, (zurKamera - 0.05) / 0.2));
        liste.push({ schluessel: q.schluessel, x, y, sichtbar: pn.z < 1 && sicht > 0 && x > 8 && x < w - 8 && y > 8 && y < h - 8, anteil: punkteAnteil * sicht });
      }
      zerlegtHuelleJetzt.setFromObject(modell).getCenter(pw);
      pn.copy(pw).project(kamera);
      beiAnker(liste, { x: (pn.x * 0.5 + 0.5) * w, y: (-pn.y * 0.5 + 0.5) * h, w, h });
      return;
    }
    if (zerlegt < 0.02) { if (!ankerLeer) { beiAnker([]); ankerLeer = true; } return; }
    ankerLeer = false;
    const w = leinwand.clientWidth, h = leinwand.clientHeight; const liste = [];
    for (const [schluessel, gruppe] of anker) {
      // Mit Stufen: nur die Schilder der Stufe, die gerade aufgeht -- bei
      // allen vier waeren es dreizehn Schilder um ein Auto (Probe 23.09.).
      if (MIT_STUFEN && aktStufe && !gruppe.some((t) => t.stufe === aktStufe)) continue;
      let beste = null; let bestAbst = Infinity; let a = 0;
      for (const t of gruppe) {
        t.o.localToWorld(pw.copy(t.mitteLokal));
        const d = pw.distanceTo(kamera.position);
        if (d < bestAbst) { bestAbst = d; beste = pw.clone(); }
        a = Math.max(a, anteil(t, zerlegt * (1 + Z_BREITE)));
      }
      pn.copy(beste).project(kamera);
      const x = (pn.x * 0.5 + 0.5) * w, y = (-pn.y * 0.5 + 0.5) * h;
      liste.push({ schluessel, x, y, sichtbar: pn.z < 1 && x > 8 && x < w - 8 && y > 8 && y < h - 8, anteil: a });
    }
    zerlegtHuelleJetzt.setFromObject(modell).getCenter(pw);
    pn.copy(pw).project(kamera);
    beiAnker(liste, { x: (pn.x * 0.5 + 0.5) * w, y: (-pn.y * 0.5 + 0.5) * h, w, h });
  }

  /* ------------------------------------------------------------ Bildschleife */
  let laeuft = false; let aktiv = false; let letzt = 0; let bilder = 0; let seit = 0; let fps = 0;
  let zurueck = true;      // fährt nach dem Loslassen auf den Standpunkt
  function einmal() { r.render(szene, kamera); }
  function starten() { aktiv = true; if (!laeuft) { laeuft = true; letzt = 0; seit = 0; bilder = 0; requestAnimationFrame(bild); } }
  function anhalten() { aktiv = false; }
  function bild(t) {
    if (!aktiv || document.hidden) { laeuft = false; return; }
    const dt = letzt ? Math.min(0.25, (t - letzt) / 1000) : 1 / 60; letzt = t;
    if (modus === 'aussen' && zurueck && !ziehen && !zeiger.size && zerlegtSoll === 0 && performance.now() - letzteBewegung > 1400) soll = { ...heim };
    /* Zerlegt dreht sich das Modell langsam, bis jemand selbst greift --
       ein zerlegtes Auto liest man erst, wenn man um es herumgeht. Nach
       25 s steht es wieder still, damit die Grafikkarte nicht endlos rechnet. */
    if (modus === 'aussen' && zerlegtSoll > 0 && zerlegt === zerlegtSoll && !ziehen && !zeiger.size && !BEWEGUNG_AUS
        && performance.now() - letzteBewegung > 2500 && performance.now() - zerlegtSeit < 25000) {
      soll.winkel += dt * 0.14;
    }
    const f = BEWEGUNG_AUS ? 1 : 1 - Math.exp(-dt * (ziehen ? 9 : 2.6));
    let rest = modus === 'innen' || fahrt ? 0 : annaehern(f);
    if (modus !== 'aussen' || fahrerTuer !== tuerSoll) { if (innenSchritt(dt)) rest += 0.01; }
    if (zerlegt !== zerlegtSoll) {
      const schritt = BEWEGUNG_AUS ? 1 : dt / Z_DAUER;
      zerlegt = zerlegtSoll > zerlegt ? Math.min(zerlegtSoll, zerlegt + schritt) : Math.max(zerlegtSoll, zerlegt - schritt);
      zerlegenAnwenden(); rest += Math.abs(zerlegtSoll - zerlegt) + 0.01;
      letzteBewegung = performance.now();
      if (schattenErlaubt) r.shadowMap.needsUpdate = true;
    }
    if (punkteAnteil !== punkteSoll) {
      const schritt = BEWEGUNG_AUS ? 1 : dt / 0.35;
      punkteAnteil = punkteSoll > punkteAnteil ? Math.min(punkteSoll, punkteAnteil + schritt) : Math.max(punkteSoll, punkteAnteil - schritt);
      rest += Math.abs(punkteSoll - punkteAnteil) + 0.01;
    }
    schattenFlaeche.material.opacity = schattenErlaubt ? 0.5 * Math.min(1, zerlegt * 1.6) : 0;
    schattenFlaeche.visible = schattenFlaeche.material.opacity > 0.001;
    kameraSetzen();
    r.render(szene, kamera);
    ankerMelden();
    bilder++;
    if (!seit) seit = t;
    if (t - seit >= 500) { fps = Math.round((bilder * 1000) / (t - seit)); bilder = 0; seit = t; }
    beiBild && beiBild(dt * 1000, fps, false);
    const amZiel = rest < 0.002 && zerlegt === 0 && zerlegtSoll === 0;
    if (modus === 'innen' && !ziehen && !zeiger.size && rest < 0.002 && performance.now() - letzteBewegung > 1400 && !ruhtGemeldet) {
      // Innenraum in Ruhe: Standpunkt = Innenraumfoto, die Seite blendet es ein
      ruhtGemeldet = true; blick.gier = blick.nick = 0; frei = { ort: innenAuge, ziel: innenZiel, lens: KI.brennweite_mm || 20 };
      kameraSetzen(); r.render(szene, kamera);
      beiRuhe && beiRuhe('innen');
    } else if (modus === 'aussen' && zurueck && !ziehen && !zeiger.size && amZiel && performance.now() - letzteBewegung > 1400 && !ruhtGemeldet) {
      ruhtGemeldet = true; ist = { ...heim }; kameraSetzen(); r.render(szene, kamera);
      beiRuhe && beiRuhe('aussen');
    } else if (!ziehen && !zeiger.size && ruhtGemeldet && rest < 0.0005 && performance.now() - letzteBewegung > 6000) {
      laeuft = false; aktiv = false; beiBild && beiBild(dt * 1000, fps, true); return;
    }
    requestAnimationFrame(bild);
  }

  if (variante) await varianteSetzen(variante);
  stufeSetzen(einstellungen);

  const api = {
    varianten: namen,
    variante: varianteSetzen,
    /* 0 = ganz, 1 = zerlegt. Solange es zerlegt ist, bleibt die Kamera
       stehen, wo der Besucher sie hingedreht hat -- das Foto zeigt nur das
       ganze Modell. */
    zerlegen(an) {
      // Vom Fahrerplatz aus erst hinausfahren, dann zerlegen (merken)
      if (modus !== 'aussen') { wartendZerlegen = an; if (an) this.innenraum(false); return; }
      aktStufe = typeof an === 'number' ? Math.max(0, Math.min(4, an)) : (an ? 4 : 0);
      zerlegtSoll = STUFEN_ZIEL[aktStufe];
      ruhtGemeldet = false; letzteBewegung = performance.now();
      /* Zerlegt braucht das Modell rund anderthalbmal so viel Platz. Mit der
         Kamera des Fotos flogen Dach und Hinterrad aus dem Bild (erste
         Probe) -- also zurück und etwas höher, damit man hineinsieht. */
      if (an) {
        zerlegtSeit = performance.now();
        const b = zerlegtHuelle();
        const neig = Math.min(grenzen.neig[1], heim.neig + 0.16);
        // Für den ganzen Rundgang passend: Das zerlegte Auto dreht sich
        // danach langsam, und von der Seite ist es breiter als von vorn.
        let abst = 0;
        for (let i = 0; i < 12; i++) abst = Math.max(abst, einpassen(b, soll.winkel + (i * Math.PI) / 6, neig));
        grenzen.abst[1] = Math.max(grenzen.abst[1], abst * 1.25);
        soll = { ...soll, abst, neig };
      }
      starten();
    },
    get zerlegt() { return zerlegtSoll > 0; },
    get stufen() { return (K.zerlegen && K.zerlegen.stufen) || []; },
    /* Innenraum: true = Kamerafahrt durch die Fahrertuer auf den Fahrerplatz,
       false = zurueck nach draussen. Geht nur zusammengesetzt. */
    get hatInnen() { return !!KI; },
    get innen() { return modus === 'innen' || modus === 'rein'; },
    innenraum(an) {
      if (!KI) return;
      ruhtGemeldet = false; letzteBewegung = performance.now();
      if (an && (modus === 'aussen' || modus === 'raus')) {
        if (zerlegtSoll > 0) zerlegtSoll = 0;
        if (modus === 'raus' && fahrt) { fahrt.richtung = 1; fahrt.ort.reverse(); fahrt.ziel.reverse(); fahrt.lens.reverse(); fahrt.t = 1 - fahrt.t; }
        modus = 'rein'; tuerSoll = 1;
      } else if (!an && (modus === 'innen' || modus === 'rein')) {
        if (modus === 'rein' && !fahrt) { modus = 'aussen'; tuerSoll = 0; soll = { ...heim }; }
        else if (modus === 'rein' && fahrt) { fahrt.richtung = -1; fahrt.ort.reverse(); fahrt.ziel.reverse(); fahrt.lens.reverse(); fahrt.t = 1 - fahrt.t; modus = 'raus'; }
        else { modus = 'raus'; tuerSoll = 1; }
      }
      starten();
    },
    /* Details: die festen Punkte aus kamera.json beschriften (siehe oben). */
    punkte(an) { punkteSoll = an && punkte.length ? 1 : 0; ruhtGemeldet = false; letzteBewegung = performance.now(); starten(); },
    get hatPunkte() { return punkte.length > 0; },
    /* Nachtansicht: Das Studio geht fast aus, und übrig bleibt, was am
       Modell selbst leuchtet -- Tagfahrlicht, Rückleuchten, Armaturen. Die
       Scheinwerfer sind im Modell ohnehin an; ein Schalter "Licht an" hätte
       am hellen Studiobild nichts sichtbar verändert. */
    licht(an) {
      szene.environmentIntensity = an ? 0.07 : 1;
      bodenMat.envMapIntensity = (K.web_boden?.umgebung ?? 1) * (an ? 0.15 : 1);
      bodenMat.lightMapIntensity = bodenLichtStaerke * (an ? 0.08 : 1);
      for (const [m, s] of leuchtStaerke) m.emissiveIntensity = an ? s * 2.2 : s;
      einmal(); starten();
    },
    heim() {
      if (modus !== 'aussen') { this.innenraum(false); return; }
      soll = { ...heim }; ruhtGemeldet = false; letzteBewegung = performance.now() - 2000; starten();
    },
    ausstattung(k) { return innenIdx[k] !== undefined ? varianteSetzen(innenIdx[k]) : null; },
    stufe: stufeSetzen,
    starten, anhalten,
    get fps() { return fps; },
    info() { return { renderer: 'WebGL 2', dreiecke: Math.round(dreiecke), pixel: r.getPixelRatio(), teile: teile.length, beschriftet: [...anker.keys()], winkel: +ist.winkel.toFixed(3), zerlegt, laeuft, fps }; },
    _teile() { return teile.map((t) => (t.o.name || '(' + t.o.parent.name + ')') + ' @' + t.start); },
    /* Nur für die Prüfung: Belichtung, HDRI-Versatz und Lichtstärken am
       Poster abgleichen, ohne neu zu laden. */
    _abgleich(o) {
      if (o.bel !== undefined) r.toneMappingExposure = o.bel;
      if (o.env !== undefined) szene.environmentIntensity = o.env;
      if (o.dreh !== undefined) szene.environmentRotation.set(0, THREE.MathUtils.degToRad(o.dreh), 0);
      if (o.bodenEnv !== undefined) bodenMat.envMapIntensity = o.bodenEnv;
      if (spiegel && o.spiegel !== undefined) spiegel.material.uniforms.staerke.value = o.spiegel;
      if (spiegel && o.spiegelLod !== undefined) spiegel.material.uniforms.lod.value = o.spiegelLod;
      if (o.lichter) o.lichter.forEach((s, i) => { if (lichter[i]) lichter[i].intensity = s; });
      if (o.boden !== undefined) bodenMat.lightMapIntensity = o.boden;
      if (o.bodenFarbe !== undefined) bodenMat.color.setScalar(o.bodenFarbe);
      if (o.bodenGlanz !== undefined) bodenMat.specularIntensity = o.bodenGlanz;
      einmal();
    },
    entsorgen() { anhalten(); ro.disconnect(); r.dispose(); umgebung.dispose(); umgebungBoden.dispose(); leinwand.remove(); },
  };
  return api;
}
