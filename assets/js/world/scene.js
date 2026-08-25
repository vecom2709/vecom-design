/* ==========================================================================
   Die Bühne. Reihenfolge der Arbeit: Licht → Material → Kamera → Geometrie →
   Postprocessing. Bloom rettet kein schlechtes Licht.
   ========================================================================== */
import * as THREE from 'three';
import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';
import { OutputPass } from 'three/addons/postprocessing/OutputPass.js';
import { ShaderPass } from 'three/addons/postprocessing/ShaderPass.js';
import { FinishShader } from './finish-pass.js';
import { LOGO_CONTOURS } from './logo-shape.js';

const BLUE = 0x0648e8;
const CYAN = 0x1fe8ff;
const DEEP = 0x030509;

export class World {
  constructor(canvas, quality) {
    this.canvas = canvas;
    this.q = quality;
    this.clock = new THREE.Clock();
    this.tmp = new THREE.Vector3();

    /* ---------- Renderer ---------- */
    this.renderer = new THREE.WebGLRenderer({
      canvas,
      antialias: quality.settings.aa,
      powerPreference: 'high-performance',
      alpha: false,
    });
    this.renderer.setPixelRatio(Math.min(quality.settings.dpr, window.devicePixelRatio || 1));
    this.renderer.setSize(window.innerWidth, window.innerHeight, false);
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;  // filmische Kennlinie
    this.renderer.toneMappingExposure = 1.05;
    this.renderer.shadowMap.enabled = quality.settings.shadow > 0;
    this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;

    /* ---------- Szene & Atmosphäre ---------- */
    this.scene = new THREE.Scene();
    this.scene.background = this._backdropTexture();
    this.scene.fog = new THREE.FogExp2(0x02040a, 0.032);

    // Eigenes Studio als Umgebung: dunkler Raum, zwei Lichtflächen in den
    // Markenfarben, eine weiße Softbox oben. Das ist der Grund, warum das Metall
    // blau spiegelt und nicht bunt — eine fertige Zimmerumgebung brächte fremde
    // Farben in jede Reflexion. Kosten: 0 KB, alles prozedural.
    const pmrem = new THREE.PMREMGenerator(this.renderer);
    this.envRT = pmrem.fromScene(this._studioEnvironment(), 0.03);
    this.scene.environment = this.envRT.texture;
    pmrem.dispose();

    /* ---------- Kamera ---------- */
    this.camera = new THREE.PerspectiveCamera(38, window.innerWidth / window.innerHeight, 0.1, 120);
    this.camera.position.set(0, 0.6, 9);
    this.camTarget = new THREE.Vector3(0, 0, 0);     // Sollwerte, gedämpft angefahren
    this.camGoal = new THREE.Vector3(0, 0.6, 9);
    this.lookGoal = new THREE.Vector3(0, 0, 0);
    this.parallax = new THREE.Vector2();
    /* Dauerbewegung: Sie hängt am Scrollfortschritt der ganzen Seite, nicht am
       Abschnitt. Dadurch steht die Marke nie still — auch nicht in langen
       Abschnitten und nicht am Seitenende. */
    this.drift = { rotY: 0, rotX: 0, bob: 0, vel: 0 };

    this._buildLights();
    this._buildLogo();
    this._buildFloor();
    this._buildDust();
    this._buildComposer();

    this.q.onChange = (s) => this._applyQuality(s);
    window.addEventListener('resize', () => this.resize(), { passive: true });
  }

  /* Hintergrundverlauf statt Schwarz: gibt der Silhouette eine Kante. */
  _backdropTexture() {
    const c = document.createElement('canvas');
    c.width = 4; c.height = 256;
    const ctx = c.getContext('2d');
    const g = ctx.createLinearGradient(0, 0, 0, 256);
    g.addColorStop(0.0, '#070f22');
    g.addColorStop(0.45, '#040814');
    g.addColorStop(1.0, '#02040a');
    ctx.fillStyle = g; ctx.fillRect(0, 0, 4, 256);
    const t = new THREE.CanvasTexture(c);
    t.colorSpace = THREE.SRGBColorSpace;
    t.mapping = THREE.EquirectangularReflectionMapping;
    return t;
  }

  /* Softboxen als Umgebung — nur emissive Flächen, keine Lichtberechnung. */
  _studioEnvironment() {
    const env = new THREE.Scene();
    env.background = new THREE.Color(0x02030a);
    const panel = (w, h, color, intensity, pos, rot) => {
      const m = new THREE.Mesh(
        new THREE.PlaneGeometry(w, h),
        new THREE.MeshBasicMaterial({ color: new THREE.Color(color).multiplyScalar(intensity), side: THREE.DoubleSide })
      );
      m.position.set(...pos);
      if (rot) m.rotation.set(...rot);
      env.add(m);
      return m;
    };
    panel(13, 8, 0xffffff, 6.5, [-1, 9, 2], [Math.PI / 2, 0, 0]);     // große Softbox oben
    panel(11, 13, 0x1c6cff, 6.0, [-9, 1.5, 1], [0, Math.PI / 2, 0]);  // blaue Wand links
    panel(4, 13, 0x1fe8ff, 5.5, [9, 0.5, -1], [0, -Math.PI / 2, 0]);  // cyan Kante rechts
    panel(20, 13, 0x0a1633, 1.6, [0, 0, -11]);                        // Rückwand, leicht angehoben
    panel(20, 20, 0x03050c, 1.0, [0, -6, 0], [-Math.PI / 2, 0, 0]);   // dunkler Boden
    return env;
  }

  /* ---------- Licht: eine Hauptquelle, zwei Kanten, ein Fülllicht ---------- */
  _buildLights() {
    this.scene.add(new THREE.AmbientLight(0x0a1526, 0.35));

    this.key = new THREE.SpotLight(0xffffff, 340, 40, Math.PI / 6, 0.55, 1.6);
    this.key.position.set(-4.5, 7.5, 6);
    this.key.castShadow = this.q.settings.shadow > 0;
    if (this.key.castShadow) {
      this.key.shadow.mapSize.set(this.q.settings.shadow, this.q.settings.shadow);
      this.key.shadow.bias = -0.0016;
      this.key.shadow.radius = 3;
    }
    this.scene.add(this.key, this.key.target);

    this.rimA = new THREE.PointLight(BLUE, 90, 22, 2);
    this.rimA.position.set(5.5, 1.2, -3.5);
    this.rimB = new THREE.PointLight(CYAN, 55, 16, 2);
    this.rimB.position.set(-4.2, 1.8, -3.2);
    this.fill = new THREE.PointLight(0x5a7cba, 18, 26, 2);
    this.fill.position.set(0, 3.2, 7.5);
    this.spec = new THREE.PointLight(0xffffff, 34, 20, 2);   // Glanzpunkt
    this.spec.position.set(2.4, 3.0, 5.2);
    this.pointerLight = new THREE.PointLight(0x9fd0ff, 26, 16, 2);
    this.pointerLight.position.set(0, 0, 5.2);
    this.scene.add(this.rimA, this.rimB, this.fill, this.spec, this.pointerLight);
  }

  /* ---------- Die Marke als Körper ---------- */
  _buildLogo() {
    const shapes = LOGO_CONTOURS.map((pts) => {
      const s = new THREE.Shape();
      pts.forEach(([x, y], i) => (i ? s.lineTo(x, y) : s.moveTo(x, y)));
      s.closePath();
      return s;
    });

    const EXTRUDE = {
      depth: 0.34, bevelEnabled: true, bevelThickness: 0.045,
      bevelSize: 0.035, bevelSegments: 4, curveSegments: 6,
    };
    // Zwei getrennte Körper statt eines: nur so lassen sich die beiden Schenkel
    // im Kapitel „Ablauf" auseinanderziehen. Beide werden um denselben Betrag
    // verschoben, damit sie zusammengesetzt exakt das Logo ergeben.
    const geo = new THREE.ExtrudeGeometry(shapes, EXTRUDE);
    geo.computeBoundingBox();
    const c = geo.boundingBox.getCenter(new THREE.Vector3());
    geo.dispose();

    const parts = shapes.map((sh) => {
      const g = new THREE.ExtrudeGeometry([sh], EXTRUDE);
      g.translate(-c.x, -c.y, -c.z);
      g.computeVertexNormals();
      return g;
    });

    // Gebürstetes, blau eingefärbtes Metall mit Klarlack — der Klarlack erzeugt
    // die zweite, schärfere Reflexionsschicht, die Metall teuer aussehen lässt.
    const brushed = this._brushedTexture();
    this.mat = new THREE.MeshPhysicalMaterial({
      color: 0x164bc4,
      metalness: 0.86,      // nicht 1.0: ein Rest Diffusanteil hält die Fläche
      roughness: 0.3,       // auf dunklem Grund lesbar

      roughnessMap: brushed,      // gebürstete Struktur: bricht die tote Fläche
      clearcoat: 1.0,
      clearcoatRoughness: 0.07,
      envMapIntensity: 1.9,
      anisotropy: 0.6,           // längliche Reflexe wie bei geschliffenem Metall
      anisotropyRotation: Math.PI / 3,
      transmission: 0.0,          // wird für den Glaszustand hochgefahren
      thickness: 0.0,
      ior: 1.5,
      emissive: new THREE.Color(0x1a6cff),
      emissiveIntensity: 0.0,
      iridescence: 0.08,          // nur ein Hauch — mehr kippt ins Violette
      iridescenceIOR: 1.35,
      iridescenceThicknessRange: [180, 420],
    });

    this.logo = new THREE.Group();
    this.logoLeft = new THREE.Mesh(parts[0], this.mat);
    this.logoRight = new THREE.Mesh(parts[1], this.mat);
    [this.logoLeft, this.logoRight].forEach((m) => {
      m.castShadow = this.q.settings.shadow > 0;
      m.receiveShadow = false;
      this.logo.add(m);
    });
    this.logo.scale.setScalar(1.75);

    // Licht im Spalt: wird sichtbar, sobald die Schenkel auseinandergehen.
    this.gap = new THREE.PointLight(0x39d8ff, 0, 6, 2);
    this.gap.position.set(0, 0, 0.3);
    this.logo.add(this.gap);

    this.logoRig = new THREE.Group();      // Rig trägt die Story-Bewegung (Position + Drehung)
    this.logoRig.add(this.logo);
    this.scene.add(this.logoRig);

    // Lichtsaum hinter der Marke: eine additive Fläche, kein echtes Volumen —
    // billiger und auf dunklem Grund nicht zu unterscheiden.
    const halo = new THREE.Mesh(
      new THREE.PlaneGeometry(16, 16),
      new THREE.MeshBasicMaterial({
        map: this._radialTexture(),
        transparent: true,
        blending: THREE.AdditiveBlending,
        depthWrite: false,
        opacity: 0.4,
      })
    );
    halo.position.set(0, 0.1, -2.6);
    halo.scale.setScalar(0.34);
    halo.material.opacity = 0.3;
    this.halo = halo;
    this.logoRig.add(halo);   // wandert mit der Marke, statt als Scheibe im Bild zu stehen
  }

  /* Gebürstetes Metall als Rauheitskarte. Ohne diese Struktur bleibt die
     Vorderfläche eine tote, gleichmäßige Farbe — der häufigste Grund, warum
     3D im Web nach Plastik aussieht. */
  _brushedTexture() {
    const w = 512, h = 512;
    const c = document.createElement('canvas');
    c.width = w; c.height = h;
    const ctx = c.getContext('2d');
    ctx.fillStyle = '#6a6a6a';
    ctx.fillRect(0, 0, w, h);
    for (let i = 0; i < 5200; i++) {
      const y = Math.random() * h;
      const len = 60 + Math.random() * 300;
      const v = 70 + Math.random() * 110;
      ctx.strokeStyle = `rgba(${v},${v},${v},${0.05 + Math.random() * 0.16})`;
      ctx.lineWidth = 0.6 + Math.random() * 1.6;
      ctx.beginPath();
      ctx.moveTo(Math.random() * w, y);
      ctx.lineTo(Math.random() * w + len, y + (Math.random() - 0.5) * 2);
      ctx.stroke();
    }
    const t = new THREE.CanvasTexture(c);
    t.wrapS = t.wrapT = THREE.RepeatWrapping;
    t.repeat.set(2.5, 2.5);
    return t;
  }

  _radialTexture() {
    const s = 256;
    const c = document.createElement('canvas');
    c.width = c.height = s;
    const g = c.getContext('2d').createRadialGradient(s / 2, s / 2, 0, s / 2, s / 2, s / 2);
    g.addColorStop(0.0, 'rgba(60,150,255,0.95)');
    g.addColorStop(0.35, 'rgba(20,90,230,0.35)');
    g.addColorStop(1.0, 'rgba(0,0,0,0)');
    const ctx = c.getContext('2d');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, s, s);
    const t = new THREE.CanvasTexture(c);
    t.colorSpace = THREE.SRGBColorSpace;
    return t;
  }

  /* ---------- Boden: spiegelnd genug für Tiefe, ruhig genug für Text ---------- */
  _buildFloor() {
    const g = new THREE.PlaneGeometry(120, 120);
    const m = new THREE.MeshStandardMaterial({
      color: 0x070c16,
      metalness: 0.8,
      roughness: 0.3,
      envMapIntensity: 0.7,
    });
    this.floor = new THREE.Mesh(g, m);
    this.floor.rotation.x = -Math.PI / 2;
    this.floor.position.y = -3.4;
    this.floor.receiveShadow = this.q.settings.shadow > 0;
    this.scene.add(this.floor);

    // Bodenkontakt: ohne diesen Lichtfleck schwebt die Marke im Nichts, sobald
    // die Kamera den Boden ins Bild nimmt.
    this.contact = new THREE.Mesh(
      new THREE.PlaneGeometry(11, 11),
      new THREE.MeshBasicMaterial({
        map: this._radialTexture(), transparent: true,
        blending: THREE.AdditiveBlending, depthWrite: false, opacity: 0.3,
      })
    );
    this.contact.rotation.x = -Math.PI / 2;
    this.contact.position.set(0, -3.36, 0);
    this.scene.add(this.contact);
  }

  /* ---------- Staub in der Luft: gibt dem Raum Maßstab ---------- */
  _buildDust() {
    if (this.dust) {
      this.scene.remove(this.dust);
      this.dust.geometry.dispose();
      this.dust.material.dispose();
    }
    const n = this.q.settings.particles;
    const pos = new Float32Array(n * 3);
    const seed = new Float32Array(n);
    for (let i = 0; i < n; i++) {
      pos[i * 3] = (Math.random() - 0.5) * 20;
      pos[i * 3 + 1] = (Math.random() - 0.5) * 11;
      pos[i * 3 + 2] = (Math.random() - 0.5) * 13 - 1;
      seed[i] = Math.random() * Math.PI * 2;
    }
    const g = new THREE.BufferGeometry();
    g.setAttribute('position', new THREE.BufferAttribute(pos, 3));
    g.setAttribute('aSeed', new THREE.BufferAttribute(seed, 1));

    const m = new THREE.ShaderMaterial({
      transparent: true,
      depthWrite: false,
      blending: THREE.AdditiveBlending,
      uniforms: { uTime: { value: 0 }, uSize: { value: 17 * (this.q.settings.dpr || 1) } },
      vertexShader: `
        attribute float aSeed;
        uniform float uTime; uniform float uSize;
        varying float vA;
        void main() {
          vec3 p = position;
          p.y += sin(uTime * 0.16 + aSeed) * 0.5;
          p.x += cos(uTime * 0.1 + aSeed * 1.7) * 0.4;
          vec4 mv = modelViewMatrix * vec4(p, 1.0);
          gl_PointSize = uSize / max(-mv.z, 0.6);
          vA = 0.25 + 0.75 * (0.5 + 0.5 * sin(uTime * 0.7 + aSeed * 3.0));
          gl_Position = projectionMatrix * mv;
        }`,
      fragmentShader: `
        varying float vA;
        void main() {
          float d = length(gl_PointCoord - 0.5);
          if (d > 0.5) discard;
          float a = smoothstep(0.5, 0.0, d) * vA * 0.10;
          gl_FragColor = vec4(vec3(0.55, 0.74, 1.0), a);
        }`,
    });
    this.dust = new THREE.Points(g, m);
    this.dust.frustumCulled = false;
    this.scene.add(this.dust);
  }

  /* ---------- Postprocessing ---------- */
  _buildComposer() {
    this.composer = new EffectComposer(this.renderer);
    this.composer.setPixelRatio(this.renderer.getPixelRatio());
    this.composer.setSize(window.innerWidth, window.innerHeight);
    this.composer.addPass(new RenderPass(this.scene, this.camera));
    this.bloom = new UnrealBloomPass(
      new THREE.Vector2(window.innerWidth, window.innerHeight), 0.46, 0.75, 0.95
    );
    this.composer.addPass(this.bloom);

    // Abschluss-Pass: Lichtstreuung, Tiefenunschärfe, Farbsaum, Vignette.
    this.finish = new ShaderPass(FinishShader);
    this.finish.uniforms.uLightPos.value = new THREE.Vector2(0.5, 0.35);
    this.finish.uniforms.uAspect.value = window.innerWidth / window.innerHeight;
    this.composer.addPass(this.finish);

    this.composer.addPass(new OutputPass());
    this.bloom.enabled = this.q.settings.bloom;
  }

  _applyQuality(s) {
    this.renderer.setPixelRatio(Math.min(s.dpr, window.devicePixelRatio || 1));
    this.composer.setPixelRatio(this.renderer.getPixelRatio());
    this.bloom.enabled = s.bloom;
    if (this.finish) this.finish.enabled = s.bloom;   // gleiche Schwelle wie Bloom
    this.renderer.shadowMap.enabled = s.shadow > 0;
    this.logo.castShadow = s.shadow > 0;
    this.key.castShadow = s.shadow > 0;
    this._buildDust();
    this.resize();
  }

  /* Drei Aggregatzustände desselben Motivs. Die Werte werden von außen
     angetweent, hier stehen nur die Ziele. */
  static MATERIALS = {
    metal: { metalness: 0.86, roughness: 0.30, transmission: 0.0, ior: 1.5, thickness: 0.0,
             clearcoatRoughness: 0.07, iridescence: 0.08, emissiveIntensity: 0.0, envMapIntensity: 1.9, opacity: 1 },
    /* Kein Glas mehr: Durchsichtigkeit zeigt bei einem massiven Körper die
       Rückseiten mit — das sah wie doppelte Spiegelungen aus. Stattdessen
       poliertes Metall: schärfere Reflexe, mehr Glanz, keine Doppelbilder. */
    glass: { metalness: 0.95, roughness: 0.09, transmission: 0.0, ior: 1.5, thickness: 0.0,
             clearcoatRoughness: 0.02, iridescence: 0.22, emissiveIntensity: 0.0, envMapIntensity: 2.2, opacity: 1 },
    glow:  { metalness: 0.75, roughness: 0.22, transmission: 0.0, ior: 1.5, thickness: 0.0,
             clearcoatRoughness: 0.05, iridescence: 0.2, emissiveIntensity: 1.5, envMapIntensity: 1.4, opacity: 1 },
  };

  /* Die Lichtquelle in Bildkoordinaten — die Strahlen müssen dort ansetzen,
     wo das Licht im Bild wirklich steht, sonst wirkt der Effekt aufgeklebt. */
  _updateLightScreenPos() {
    if (!this.finish) return;
    this.tmp.copy(this.key.position).project(this.camera);
    this.finish.uniforms.uLightPos.value.set(
      this.tmp.x * 0.5 + 0.5,
      this.tmp.y * 0.5 + 0.5
    );
  }

  resize() {
    const w = window.innerWidth, h = window.innerHeight;
    this.camera.aspect = w / h;
    // Auf schmalen Bildschirmen weiter aufziehen, sonst schneidet die Marke an.
    this.camera.fov = w / h < 0.85 ? 52 : 38;
    this.camera.updateProjectionMatrix();
    this.renderer.setSize(w, h, false);
    this.composer.setSize(w, h);
    this.bloom.setSize(w, h);
    if (this.finish) this.finish.uniforms.uAspect.value = w / h;
  }

  setParallax(x, y) {
    this.parallax.set(x, y);
    // Zeigerlicht: eine kleine Quelle, die vor der Marke mitwandert. Dadurch
    // bewegen sich die Reflexe unter dem Zeiger — echte Interaktion mit der
    // Szene statt eines nachgezeichneten Cursors.
    if (this.pointerLight) this.pointerLight.position.set(x * 6.5, y * 4.2, 5.2);
  }

  render() {
    const t0 = performance.now();
    const dt = Math.min(this.clock.getDelta(), 0.05);
    const time = this.clock.elapsedTime;

    // Kamera gedämpft an den Sollwert führen — nie direkt setzen.
    this.camGoal.x += this.parallax.x * 0.0;   // Parallaxe kommt über lookGoal
    this.camera.position.lerp(this.camGoal, 1 - Math.pow(0.001, dt));
    this.tmp.copy(this.lookGoal);
    this.tmp.x += this.parallax.x * 0.55;
    this.tmp.y += this.parallax.y * 0.35;
    this.camTarget.lerp(this.tmp, 1 - Math.pow(0.004, dt));
    this.camera.lookAt(this.camTarget);

    // Dauerbewegung auf der inneren Gruppe: überlagert die Beats, ohne sie zu
    // überschreiben (die setzen die Drehung des Rigs, nicht die des Körpers).
    const dl = 1 - Math.pow(0.02, dt);
    this.logo.rotation.y += (this.drift.rotY - this.logo.rotation.y) * dl;
    this.logo.rotation.x += (this.drift.rotX - this.logo.rotation.x) * dl;
    this.logo.position.y += ((Math.sin(time * 0.35) * 0.12 + this.drift.bob) - this.logo.position.y) * dl;
    // Scrollgeschwindigkeit gibt einen kurzen Schub — das macht die Bewegung
    // greifbar, statt sie nur ablaufen zu lassen.
    this.camera.position.z += (this.drift.vel * 0.9 - 0) * dt * 2.0;

    if (this.dust) this.dust.material.uniforms.uTime.value = time;
    this.key.target.position.set(0, 0, 0);
    this.key.target.updateMatrixWorld();

    this._updateLightScreenPos();
    this.composer.render();
    this.q.sample(performance.now() - t0);
  }

  dispose() {
    this.renderer.dispose();
    this.envRT.dispose();
  }
}
