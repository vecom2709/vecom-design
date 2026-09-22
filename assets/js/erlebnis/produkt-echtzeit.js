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
  bezeichnung, beiBewegung, beiRuhe, beiBild, variante = 0, belichtung,
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

  async function varianteSetzen(i) {
    const auftraege = zuordnung.map(async (z) => {
      const treffer = z.mappings.find((mp) => mp.variants.includes(i));
      z.mesh.material = treffer ? await parser.getDependency('material', treffer.material) : z.standard;
    });
    await Promise.all(auftraege);
    einmal();
  }

  /* ------------------------------------------------------------ Zerlegen */
  const gesamt = new THREE.Box3().setFromObject(modell);
  const mitte = gesamt.getCenter(new THREE.Vector3());
  const teile = [];
  const k = new THREE.Box3(); const c = new THREE.Vector3();
  modell.traverse((o) => {
    if (!o.isMesh) return;
    k.setFromObject(o); k.getCenter(c);
    const weg = c.clone().sub(mitte);
    // Etwas mehr nach oben als zur Seite: Ein zerlegtes Auto liest sich,
    // wenn das Dach abhebt und die Räder nach außen gehen -- nicht, wenn
    // alles flach auf dem Boden auseinanderrutscht.
    weg.y = Math.max(0, weg.y) * 1.8 + 0.12;
    teile.push({ o, ruhe: o.position.clone(), weg: o.parent.worldToLocal(c.clone().add(weg)).sub(o.parent.worldToLocal(c.clone())) });
  });
  let zerlegt = 0; let zerlegtSoll = 0;
  function zerlegenAnwenden() {
    const e = zerlegt * zerlegt * (3 - 2 * zerlegt);   // weich an beiden Enden
    for (const t of teile) t.o.position.copy(t.ruhe).addScaledVector(t.weg, e * 0.6);
  }

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
  function kameraSetzen() {
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
    leinwand.setPointerCapture(e.pointerId);
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
  function stufeSetzen(e) {
    r.setPixelRatio(Math.min(window.devicePixelRatio || 1, e.pixel));
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

  /* ------------------------------------------------------------ Bildschleife */
  let laeuft = false; let aktiv = false; let letzt = 0; let bilder = 0; let seit = 0; let fps = 0;
  let zurueck = true;      // fährt nach dem Loslassen auf den Standpunkt
  function einmal() { r.render(szene, kamera); }
  function starten() { aktiv = true; if (!laeuft) { laeuft = true; letzt = 0; seit = 0; bilder = 0; requestAnimationFrame(bild); } }
  function anhalten() { aktiv = false; }
  function bild(t) {
    if (!aktiv || document.hidden) { laeuft = false; return; }
    const dt = letzt ? Math.min(0.25, (t - letzt) / 1000) : 1 / 60; letzt = t;
    if (zurueck && !ziehen && !zeiger.size && zerlegtSoll === 0 && performance.now() - letzteBewegung > 1400) soll = { ...heim };
    const f = BEWEGUNG_AUS ? 1 : 1 - Math.exp(-dt * (ziehen ? 9 : 2.6));
    let rest = annaehern(f);
    if (zerlegt !== zerlegtSoll) {
      const schritt = BEWEGUNG_AUS ? 1 : dt / 1.6;
      zerlegt = zerlegtSoll > zerlegt ? Math.min(zerlegtSoll, zerlegt + schritt) : Math.max(zerlegtSoll, zerlegt - schritt);
      zerlegenAnwenden(); rest += Math.abs(zerlegtSoll - zerlegt) + 0.01;
      letzteBewegung = performance.now();
    }
    kameraSetzen();
    r.render(szene, kamera);
    bilder++;
    if (!seit) seit = t;
    if (t - seit >= 500) { fps = Math.round((bilder * 1000) / (t - seit)); bilder = 0; seit = t; }
    beiBild && beiBild(dt * 1000, fps, false);
    const amZiel = rest < 0.002 && zerlegt === 0 && zerlegtSoll === 0;
    if (zurueck && !ziehen && !zeiger.size && amZiel && performance.now() - letzteBewegung > 1400 && !ruhtGemeldet) {
      ruhtGemeldet = true; ist = { ...heim }; kameraSetzen(); r.render(szene, kamera);
      beiRuhe && beiRuhe();
    } else if (!ziehen && !zeiger.size && ruhtGemeldet && rest < 0.0005 && performance.now() - letzteBewegung > 6000) {
      laeuft = false; aktiv = false; beiBild && beiBild(dt * 1000, fps, true); return;
    }
    requestAnimationFrame(bild);
  }

  if (variante) await varianteSetzen(variante);
  stufeSetzen(einstellungen);

  return {
    varianten: namen,
    variante: varianteSetzen,
    /* 0 = ganz, 1 = zerlegt. Solange es zerlegt ist, bleibt die Kamera
       stehen, wo der Besucher sie hingedreht hat -- das Foto zeigt nur das
       ganze Modell. */
    zerlegen(an) {
      zerlegtSoll = an ? 1 : 0; ruhtGemeldet = false; letzteBewegung = performance.now();
      /* Zerlegt braucht das Modell rund anderthalbmal so viel Platz. Mit der
         Kamera des Fotos flogen Dach und Hinterrad aus dem Bild (erste
         Probe) -- also zurück und etwas höher, damit man hineinsieht. */
      if (an) soll = { ...soll, abst: heim.abst * 1.5, neig: Math.min(grenzen.neig[1], heim.neig + 0.14) };
      starten();
    },
    get zerlegt() { return zerlegtSoll === 1; },
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
    heim() { soll = { ...heim }; ruhtGemeldet = false; letzteBewegung = performance.now() - 2000; starten(); },
    stufe: stufeSetzen,
    starten, anhalten,
    get fps() { return fps; },
    info() { return { renderer: 'WebGL 2', dreiecke: Math.round(dreiecke), pixel: r.getPixelRatio() }; },
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
}
