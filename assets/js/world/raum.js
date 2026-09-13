/* ==========================================================================
   raum.js — Der Blender-Showroom als Bühne der Website.

   Bis zum 13.09.2026 stand hinter der Startseite eine im Code gebaute Welt:
   ein extrudiertes V aus Konturpunkten, ein Boden, Staub, ein Halo. Sie war
   gut gemacht, aber sie war gerechnet — und man sah es. Seitdem gibt es den
   Raum aus Blender: ein Podest, Wände, Deckenfelder, Displays mit echten
   Arbeiten, und die Marke als Körper mit eigenen Kanten statt als Nachbau.

   WAS DIESES MODUL IST UND WAS NICHT
   Es ist die Bühne: Renderer, Licht, Modell, Nachbearbeitung, Kamera. Es
   kennt die Seite nicht. Wohin die Kamera fährt, entscheidet raum-beats.js
   anhand der Abschnitte — dieselbe Trennung wie vorher zwischen scene.js
   und site-beats.js, damit ein Textumbau nie die Bühne anfasst.

   DIE EIGENSCHAFTEN, DIE VON AUSSEN BEWEGT WERDEN
   camGoal, lookGoal, fovZiel, markeRig.position/.rotation, scene.fog.density,
   key (+ .position), spitze, wand, bloom.strength, grade.uniforms, drift.
   Alle sind Sollwerte: render() fährt sie gedämpft an, niemand setzt die
   Kamera direkt. Wer hier etwas umbenennt, muss raum-beats.js mitnehmen.

   KOORDINATEN
   Blender rechnet mit Z nach oben, glTF mit Y. b2t() rechnet um, damit die
   Zahlen in den Beats eins zu eins zur .blend-Datei passen und man sie dort
   ablesen kann, statt sie zu erraten.
   ========================================================================== */
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';
import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';
import { BokehPass } from 'three/addons/postprocessing/BokehPass.js';
import { ShaderPass } from 'three/addons/postprocessing/ShaderPass.js';
import { OutputPass } from 'three/addons/postprocessing/OutputPass.js';

/* Blender (Z oben) → three (Y oben) */
export const b2t = (x, y, z) => new THREE.Vector3(x, z, -y);

/* --------------------------------------------------------------------------
   Was auf welcher Stufe läuft.

   Die Reihenfolge des Abschaltens ist nicht beliebig: Zuerst fällt die
   Tiefenschärfe (teuer, und auf einem kleinen Bild ohnehin kaum zu sehen),
   dann der Schattenwurf, dann das Leuchten, zuletzt die Auflösung. Die
   Inszenierung — Kamerafahrt, Licht, Materialien — bleibt auf jeder Stufe
   dieselbe. Eine Seite, die auf schwachen Geräten anders aussieht, ist eine
   andere Seite; eine, die dort nur weniger fein aussieht, ist dieselbe.
   -------------------------------------------------------------------------- */
const STUFEN = {
  ultra:  { dpr: 2.0,  aa: true,  bloom: 0.62, tiefe: true,  schatten: 2048 },
  high:   { dpr: 1.75, aa: true,  bloom: 0.58, tiefe: true,  schatten: 1024 },
  medium: { dpr: 1.35, aa: false, bloom: 0.52, tiefe: false, schatten: 512  },
  low:    { dpr: 1.0,  aa: false, bloom: 0,    tiefe: false, schatten: 0    },
};

/* Die Displays zeigen echte Arbeiten statt blauer Flächen. Die Bilder liegen
   ohnehin im Projekt — sie kommen deshalb nicht ins GLB, das bleibt schlank
   (390 KB, gezippt 24). */
const TAFELN = {
  Display_0_WEBDESIGN:   'work/cavaleri-desktop.webp',
  Display_1_ONLINESHOPS: 'work/vecomshop.webp',
  Display_2_BRANDING:    'work/trendonix-desktop.webp',
  Display_3_3D:          'work/jonika-laptop-standbild.webp',
  Display_4_WARTUNG:     'work/mensaena-laptop-standbild.webp',
};

/* EEVEE und three rechnen Emission unterschiedlich. Statt pauschal
   hochzudrehen bekommt jedes Material seinen Wert — sonst ertrinkt das Bild
   in Türkis, weil die Bodenfugen am längsten im Bild sind. */
const EMISSION = {
  M_LichtCyan: 0.50, M_LichtBlau: 1.10, M_Deckenfeld: 1.05,
  M_Kantenlicht: 1.70, M_Sockelkante: 1.00, M_Fuge: 1.00, M_Display: 0.85,
};

/* --------------------------------------------------------------------------
   Der Objektiv-Pass.

   Ein Bild aus einer echten Kamera hat Randabfall, ein wenig Korn, an den
   Rändern leichte Farbverschiebung und niemals reines Schwarz. Fehlt das,
   liest das Auge das Bild als gerechnet — egal wie gut die Materialien sind.
   -------------------------------------------------------------------------- */
const KameraShader = {
  uniforms: {
    tDiffuse:   { value: null },
    zeit:       { value: 0 },
    koern:      { value: 0.017 },
    vignette:   { value: 0.40 },
    aberration: { value: 0.0026 },
    anhebung:   { value: 0.55 },
  },
  vertexShader: `
    varying vec2 vUv;
    void main() { vUv = uv; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0); }
  `,
  fragmentShader: `
    uniform sampler2D tDiffuse;
    uniform float zeit, koern, vignette, aberration, anhebung;
    varying vec2 vUv;
    void main() {
      vec2 mitte = vUv - 0.5;
      float r2 = dot(mitte, mitte);

      float ab = aberration * r2 * 4.0;
      vec3 farbe;
      farbe.r = texture2D(tDiffuse, vUv - mitte * ab).r;
      farbe.g = texture2D(tDiffuse, vUv).g;
      farbe.b = texture2D(tDiffuse, vUv + mitte * ab).b;

      farbe *= clamp(1.0 - vignette * r2 * 1.9, 0.0, 1.0);

      float helligkeit = dot(farbe, vec3(0.2126, 0.7152, 0.0722));
      float dunkel = 1.0 - smoothstep(0.0, 0.30, helligkeit);
      farbe += anhebung * dunkel * vec3(0.012, 0.020, 0.038);

      float n = fract(sin(dot(vUv * vec2(2560.0, 1440.0) + zeit, vec2(12.9898, 78.233))) * 43758.5453);
      farbe += (n - 0.5) * koern * (0.35 + dunkel);

      gl_FragColor = vec4(farbe, 1.0);
    }
  `,
};

/* Die Farbe der Sonne als Leiter, nicht als Formel — von tief stehend
   rotorange bis zum neutralen Mittag. Übernommen aus scene.js, damit der
   Tagesgang derselbe bleibt; wer ihn dort ändert, muss ihn hier mitziehen. */
const SONNENLEITER = [
  [0.00, 0xff7a3c], [0.18, 0xff9d52], [0.38, 0xfec67e],
  [0.62, 0xffe3b8], [1.00, 0xdce8ff],
];

function sonnenfarbe(h, ziel) {
  h = Math.max(0, Math.min(1, h));
  for (let i = 1; i < SONNENLEITER.length; i++) {
    const [p1, c1] = SONNENLEITER[i - 1];
    const [p2, c2] = SONNENLEITER[i];
    if (h <= p2) {
      const t = (h - p1) / (p2 - p1 || 1);
      const a = new THREE.Color(c1), b = new THREE.Color(c2);
      return ziel.copy(a).lerp(b, t);
    }
  }
  return ziel.setHex(SONNENLEITER[SONNENLEITER.length - 1][1]);
}

export class Raum {
  /* quality: das Objekt aus quality.js — es kennt die Stufe und beobachtet
     die Bildrate. Der Raum liest daraus nur, er entscheidet nicht selbst. */
  constructor(canvas, quality) {
    this.canvas = canvas;
    this.q = quality;
    const stufe = STUFEN[quality.level] ? quality.level : 'medium';
    this.stufe = stufe;
    const S = this.S = STUFEN[stufe];

    this.uhr = new THREE.Clock();
    this.tmp = new THREE.Vector3();

    this.renderer = new THREE.WebGLRenderer({
      canvas, antialias: S.aa, powerPreference: 'high-performance', alpha: false,
    });
    this.pixelwert = Math.min(window.devicePixelRatio || 1, S.dpr);
    this.renderer.setPixelRatio(this.pixelwert);
    this.renderer.setSize(window.innerWidth, window.innerHeight, false);
    if (S.schatten) {
      this.renderer.shadowMap.enabled = true;
      /* PCF statt PCFSoft: der weiche Filter kostet ein Vielfaches und ist
         bei einem einzigen Schattenwerfer nicht zu sehen. */
      this.renderer.shadowMap.type = THREE.PCFShadowMap;
      this.renderer.shadowMap.autoUpdate = false;   /* der Raum steht still */
    }
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.toneMappingExposure = 1.12;
    if ('outputColorSpace' in this.renderer) this.renderer.outputColorSpace = THREE.SRGBColorSpace;

    this.scene = new THREE.Scene();
    this.scene.background = new THREE.Color(0x03050a);
    /* Die Dichte setzen die Abschnitte; hier steht nur der Anfangswert. */
    this.scene.fog = new THREE.FogExp2(0x04070e, 0.0098);

    this.camera = new THREE.PerspectiveCamera(46, window.innerWidth / window.innerHeight, 0.1, 200);
    this.camGoal = b2t(-5.2, -15.8, 2.35);
    this.lookGoal = b2t(0, 5.0, 3.30);
    this.fovZiel = 46;
    this._camPos = this.camGoal.clone();
    this._camZiel = this.lookGoal.clone();
    this._fov = 46;
    this._blick = new THREE.Vector3();
    this.camera.position.copy(this._camPos);
    this.camera.lookAt(this._camZiel);

    this.parallax = new THREE.Vector2();
    /* Von den Abschnitten getriebene Dauerbewegung: eine Umdrehung über die
       ganze Seite, ein Heben und Senken, und die Scrollgeschwindigkeit. */
    this.drift = { rotY: 0, rotX: 0, bob: 0, vel: 0 };

    this._licht();
    this._nachbearbeitung();
    this.bereit = this._modell();
    this.setTageszeit();

    /* Die Bühne hört selbst auf die Fenstergröße — wer sie benutzt, soll sich
       darum nicht kümmern müssen. Passiv, weil hier nichts abgefangen wird. */
    this._aufResize = () => this.resize();
    window.addEventListener('resize', this._aufResize, { passive: true });
  }

  /* ------------------------------------------------------------------ Licht
     Sparsam: Die Leuchtflächen im Modell tragen die Hauptlast. Was hier steht,
     ist das, was Blender nicht mitliefern kann — Richtungslicht mit Schatten,
     ein Spitzlicht, das der Marke folgt, und eine Wandwäsche nach hinten. */
  _licht() {
    const S = this.S;

    const pmrem = new THREE.PMREMGenerator(this.renderer);
    pmrem.compileEquirectangularShader();
    /* Rückfall sofort setzen, damit nie ein Bild ohne Spiegelung erscheint —
       Metall ohne Umgebung ist schwarze Farbe, kein Material. */
    this.scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
    if ('environmentIntensity' in this.scene) this.scene.environmentIntensity = 0.55;
    new THREE.TextureLoader().load('/assets/img/env/studio.webp', (t) => {
      t.mapping = THREE.EquirectangularReflectionMapping;
      if ('colorSpace' in t) t.colorSpace = THREE.SRGBColorSpace;
      this.scene.environment = pmrem.fromEquirectangular(t).texture;
      /* Das Bild ist eine dunkle Bühne — ohne kräftige Anhebung spiegelt das
         Metall nichts. Die Strahlen darin sind das, was Chrom echt macht. */
      if ('environmentIntensity' in this.scene) this.scene.environmentIntensity = 2.1;
      t.dispose();
    });

    this.key = new THREE.DirectionalLight(0xdce8ff, 2.2);
    this.key.position.set(-6, 9, 8);
    if (S.schatten) {
      this.key.castShadow = true;
      this.key.shadow.mapSize.set(S.schatten, S.schatten);
      const k = this.key.shadow.camera;
      /* eng um Podest und Marke gelegt: jeder Meter mehr kostet Auflösung */
      k.left = -11; k.right = 11; k.top = 11; k.bottom = -11; k.near = 2; k.far = 34;
      this.key.shadow.bias = -0.0009;
      this.key.shadow.normalBias = 0.02;
    }
    this.scene.add(this.key);

    this.gegen = new THREE.DirectionalLight(0x1fe8ff, 0.8);
    this.gegen.position.set(3, 5, -14);
    this.scene.add(this.gegen);

    this.himmel = new THREE.HemisphereLight(0x2a4a80, 0x02040a, 0.55);
    this.scene.add(this.himmel);

    /* Spitzlicht auf die Marke: ein harter Reflex ist der Unterschied
       zwischen blauer Farbe und blauem Metall. Es wandert mit ihr mit. */
    this.spitze = new THREE.SpotLight(0xffffff, 260, 26, 0.55, 0.35, 1.8);
    this.spitze.position.set(-5.5, 7.5, 7.5);
    this.scene.add(this.spitze);
    this.scene.add(this.spitze.target);
    this.spitze.target.position.set(0, 3.4, -4);

    /* Wandwäsche hinten, damit der Raum nicht im Nichts endet */
    this.wand = new THREE.PointLight(0x2f6ad0, 120, 30, 2.0);
    this.wand.position.set(0, 4.5, -28);
    this.scene.add(this.wand);
  }

  /* ---------------------------------------------------- Modell und Material */
  _modell() {
    const S = this.S;
    const lader = new THREE.TextureLoader();
    /* Unregelmäßigkeit ist der Unterschied zwischen Rechnung und Aufnahme.
       Eine gleichmäßig glatte Fläche kommt in der Wirklichkeit nicht vor —
       Schleifspuren, Putzschlieren und Staub brechen jede Spiegelung auf. */
    const karte = (datei, wdh) => {
      const t = lader.load('/assets/img/3d/' + datei);
      t.wrapS = t.wrapT = THREE.RepeatWrapping;
      t.repeat.set(wdh, wdh);
      return t;
    };
    const K_BODEN = karte('boden-rauheit.webp', 9);
    const K_METALL = karte('metall-rauheit.webp', 4);
    const K_NORMAL = karte('fein-normal.webp', 12);
    this._karten = [K_BODEN, K_METALL, K_NORMAL];

    const aniso = this.renderer.capabilities.getMaxAnisotropy();

    return new Promise((fertig, schiefgegangen) => {
      new GLTFLoader().load('/assets/3d/showroom.glb', (gltf) => {
        const m = gltf.scene;
        let dreiecke = 0;
        m.traverse((o) => {
          if (!o.isMesh) return;
          const g = o.geometry;
          dreiecke += (g.index ? g.index.count : g.attributes.position.count) / 3;
          const mat = o.material;
          if (!mat) return;

          if (mat.emissiveIntensity !== undefined) {
            mat.emissiveIntensity = EMISSION[mat.name] !== undefined ? EMISSION[mat.name] : 1.0;
          }
          mat.envMapIntensity = 1.0;
          if (S.schatten) {
            /* Schatten nur dort, wo er zu sehen ist: die Marke wirft, Boden
               und Podest nehmen auf. 75 Werfer kosten das Zwanzigfache und
               liefern in diesem Raum kein einziges zusätzliches Bild. */
            o.castShadow = (o.name === 'Marke_V');
            o.receiveShadow = (mat.name === 'M_Boden' || mat.name === 'M_Chrom');
          }
          if (mat.map) mat.map.anisotropy = aniso;

          if (mat.name === 'M_Marke') {
            /* Auch eloxiertes Metall ist nicht makellos: die feine Struktur
               nimmt der Fläche das Gegossene, ohne dass man sie sieht. */
            mat.metalness = 1.0; mat.roughness = 0.155; mat.envMapIntensity = 2.0;
            mat.roughnessMap = K_METALL;
            mat.normalMap = K_NORMAL;
            mat.normalScale = new THREE.Vector2(0.10, 0.10);
            this.markeMat = mat;
          }
          if (mat.name === 'M_Boden') {
            /* Der Boden trägt die Spiegelung — hier fällt jede Perfektion auf. */
            mat.metalness = 0.88; mat.roughness = 0.30; mat.envMapIntensity = 0.75;
            mat.roughnessMap = K_BODEN;
            mat.normalMap = K_NORMAL;
            mat.normalScale = new THREE.Vector2(0.22, 0.22);
          }
          if (mat.name === 'M_MetallGeb' || mat.name === 'M_Chrom') {
            mat.roughness = mat.name === 'M_Chrom' ? 0.22 : 0.52;
            mat.roughnessMap = K_METALL;
            mat.normalMap = K_NORMAL;
            mat.normalScale = new THREE.Vector2(0.16, 0.16);
          }
          if (mat.name === 'M_Wand') {
            mat.roughness = 0.95;
            mat.roughnessMap = K_BODEN;
            mat.normalMap = K_NORMAL;
            mat.normalScale = new THREE.Vector2(0.30, 0.30);
          }

          const bild = TAFELN[o.name];
          if (bild) {
            /* DIE TAFELN HABEN WUERFEL-UVs, KEINE BILDSCHIRM-UVs
               ------------------------------------------------------------
               Am 13.09.2026 in Blender nachgesehen: Jede Tafel ist ein
               Quader mit acht Ecken, und ihre UV-Insel laeuft von 0,12 bis
               0,88 — das ist das Standard-Auswickeln eines Wuerfels, bei
               dem sich alle sechs Seiten dieselbe Flaeche teilen. Ein Bild
               darauf zeigt vorn einen Streifen und auf den Kanten den Rest.
               Genau so sahen die fuenf Displays hier aus, seit es sie gibt.

               Statt das Modell neu auszuwickeln — es haengt auch an der
               Blender-Datei — rechnen wir die UVs beim Laden aus dem
               eigenen Huellquader: x wird die Breite, y die Hoehe. Fuer
               eine flache Tafel ist das genau ein Bildschirm. */
            const g = o.geometry;
            g.computeBoundingBox();
            const bb = g.boundingBox;
            const bx = (bb.max.x - bb.min.x) || 1;
            const by = (bb.max.y - bb.min.y) || 1;
            const pos = g.attributes.position;
            const uv = new Float32Array(pos.count * 2);
            for (let i = 0; i < pos.count; i++) {
              uv[i * 2] = (pos.getX(i) - bb.min.x) / bx;
              uv[i * 2 + 1] = (pos.getY(i) - bb.min.y) / by;
            }
            g.setAttribute('uv', new THREE.BufferAttribute(uv, 2));

            /* eigenes Material je Tafel, sonst färbt das letzte Bild alle fünf */
            o.material = mat.clone();
            const seiten = bx / by;              /* Seitenverhältnis der Tafel */
            const t = lader.load('/assets/img/' + bild, (tex) => {
              /* Die Arbeiten sind Vollseiten-Aufnahmen — cavaleri-desktop ist
                 600 x 4184. Ungeschnitten wird daraus auf einer Tafel im
                 Format 1,5:1 ein Strich. Also den oberen Teil zeigen, den ein
                 Besucher auch zuerst sieht. Erst hier, weil vorher niemand
                 weiss, wie hoch das Bild ist. */
              const b = tex.image;
              if (!b || !b.width || !b.height) return;
              const noetig = b.width / seiten;   /* so hoch darf der Ausschnitt sein */
              if (noetig >= b.height * 0.94) return;
              const k = noetig / b.height;
              tex.repeat.set(1, k);
              /* flipY ist aus: v = 0 ist der Kopf der Seite. */
              tex.offset.set(0, 0);
              tex.needsUpdate = true;
            });
            t.flipY = false;                     /* glTF-UVs laufen andersherum */
            t.wrapS = t.wrapT = THREE.ClampToEdgeWrapping;
            if ('colorSpace' in t) t.colorSpace = THREE.SRGBColorSpace;
            t.anisotropy = aniso;
            o.material.map = t;
            o.material.emissiveMap = t;
            o.material.emissive = new THREE.Color(0xffffff);
            o.material.emissiveIntensity = 0.55;
            o.material.roughness = 0.075;
            o.material.metalness = 0.15;
            o.material.needsUpdate = true;
          }
        });

        /* DIE MARKE BEKOMMT EIN RIG
           Sie sitzt im Modell an ihrem Platz, und die Abschnitte wollen sie
           bewegen und drehen. Würde man das Objekt selbst drehen, drehte es
           sich um den Nullpunkt des Raums und flöge durch die Wand. Also
           eine Gruppe genau an ihrer Stelle, das Objekt auf null hinein —
           dann dreht sich die Marke um ihre eigene Achse, und die Gruppe ist
           das, was die Beats anfassen. */
        const marke = m.getObjectByName('Marke_V');
        if (marke) {
          const rig = new THREE.Group();
          rig.position.copy(marke.position);
          marke.parent.add(rig);
          marke.position.set(0, 0, 0);
          rig.add(marke);
          this.marke = marke;
          this.markeRig = rig;
          this.markeHeim = rig.position.clone();
        } else {
          /* Kein Absturz, wenn das Modell umbenannt wird: dann steht der Raum
             eben still. Sichtbar ist das, eine weiße Seite wäre schlimmer. */
          this.markeRig = new THREE.Group();
          this.markeHeim = new THREE.Vector3();
          this.scene.add(this.markeRig);
        }

        this.modell = m;
        this.dreiecke = Math.round(dreiecke);
        this.scene.add(m);
        if (S.schatten) this.renderer.shadowMap.needsUpdate = true;
        fertig(this);
      }, undefined, (e) => schiefgegangen(e));
    });
  }

  /* ----------------------------------------------------------- Nachbearbeitung */
  _nachbearbeitung() {
    const S = this.S;
    const w = window.innerWidth, h = window.innerHeight;
    this.composer = new EffectComposer(this.renderer);
    this.composer.addPass(new RenderPass(this.scene, this.camera));

    if (S.tiefe) {
      /* Tiefenschärfe ist der stärkste Hinweis auf eine echte Aufnahme:
         Alles gleich scharf gibt es nur in der Rechnung. Der Punkt liegt auf
         der Marke und wandert mit ihr.
         WICHTIG: Der Pass rechnet nur die Tiefe und mischt die Farbe aus dem
         Durchgang davor — er ersetzt den Render-Pass nicht, er folgt ihm. */
      this.dof = new BokehPass(this.scene, this.camera, { focus: 14.0, aperture: 0.00055, maxblur: 0.0042 });
      this.composer.addPass(this.dof);
    }
    if (S.bloom) {
      /* Auf halber Kantenlänge — ein Viertel der Pixel. Er ist ohnehin ein
         Weichzeichner; die Hälfte sieht man ihm nicht an, die Bildrate schon.
         Schwelle hoch, damit nur strahlt, was wirklich leuchtet. */
      this.bloom = new UnrealBloomPass(new THREE.Vector2(w * 0.5, h * 0.5), S.bloom, 0.58, 0.92);
      this.composer.addPass(this.bloom);
    } else {
      /* Damit die Abschnitte blind `bloom.strength` schreiben dürfen, ohne
         jedes Mal zu prüfen, ob es den Pass auf dieser Stufe gibt. */
      this.bloom = { strength: 0 };
    }
    this.grade = new ShaderPass(KameraShader);
    this.composer.addPass(this.grade);
    this.composer.addPass(new OutputPass());
    this.composer.setPixelRatio(this.pixelwert);
    this.composer.setSize(w, h);
  }

  /* ------------------------------------------------------------ Tageszeit
     Der Raum ist ein Innenraum ohne Fenster — die Sonne scheint hier nicht
     herein. Was der Tagesgang steuert, ist die Richtung und die Farbe des
     Hauptlichts: morgens tief und warm von links, mittags hoch und neutral,
     abends warm von rechts, nachts kühl und flach. Der Körper bleibt immer
     dunkel; es wandert nur, woher das Licht kommt.

     Die Faktoren liegen in this.sonne offen, weil die Abschnitte ihre eigenen
     Lichtstärken setzen und durch die Sonne hindurchrechnen — sonst wäre der
     Tagesgang beim ersten Scrollen wieder weg. */
  setTageszeit(t) {
    if (t === undefined) t = Raum.tageszeitJetzt();
    this.tageszeit = t;
    if (!this.key) return;

    if (t === null) {
      this.key.position.set(-6, 9, 8);
      this.key.color.setHex(0xdce8ff);
      this.key.intensity = 2.2;
      this.sonne = null;
      return;
    }
    t = Math.max(0.02, Math.min(0.98, t));
    const bogen = Math.sin(Math.PI * t);          // 0 früh/spät, 1 mittags
    const az = Math.PI * (1 - t);                 // links → oben → rechts

    this.key.position.set(Math.cos(az) * 9.0, 2.0 + bogen * 8.5, 6.0 + bogen * 2.0);
    /* Die Farbe hängt an der Höhe, nicht an der Uhrzeit: bogen steigt gleich
       nach Sonnenaufgang steil an. Der Exponent zieht den unteren Teil
       auseinander, damit ein Vormittag lange warm bleibt und ein Mittag
       kurz weiß ist — so, wie man es draußen sieht. */
    sonnenfarbe(Math.pow(bogen, 1.8), this.key.color);
    this.sonne = {
      sx: Math.cos(az),                 // -1 Osten, 0 Zenit, +1 Westen
      hoehe: bogen,                     // 0 Horizont, 1 Mittag
      key: 0.50 + bogen * 0.55,
      spec: 0.52 + bogen * 0.48,
    };
  }

  /* null heißt Nacht. Zwischen 6 und 20 Uhr läuft t von 0 bis 1. */
  static tageszeitJetzt() {
    const h = new Date().getHours() + new Date().getMinutes() / 60;
    if (h < 6 || h > 20) return null;
    return (h - 6) / 14;
  }

  setParallax(x, y) {
    this.parallax.set(x, y);
  }

  resize() {
    const w = window.innerWidth, h = window.innerHeight;
    this.camera.aspect = w / h;
    this.camera.updateProjectionMatrix();
    this.renderer.setSize(w, h, false);
    if (this.composer) this.composer.setSize(w, h);
    if (this.bloom && this.bloom.setSize) this.bloom.setSize(w * 0.5, h * 0.5);
  }

  /* --------------------------------------------------------------- Bildausschnitt
     Die Beats sind für ein breites Bild gebaut. Auf einem hochkant gehaltenen
     Telefon fällt mit demselben Blickwinkel der halbe Raum aus dem Rahmen.
     Drei gedeckelte Korrekturen aus dem Seitenverhältnis, alle am Showroom
     gemessen (siehe showroom.html):
       1. Der Blickwinkel wächst, aber nie über 74 Grad.
       2. Die Kamera geht zusätzlich ein Stück zurück.
       3. Hochkant sinkt das Blickziel, damit der Raum in die obere Hälfte
          steigt und die untere der dunkle Grund für den Text wird.
     Punkt 3 hängt an "hochkant", nicht am Seitenverhältnis allein: Ein
     schmales Schreibtischfenster behält Text links und Raum rechts. */
  static get BILD_REF() { return 16 / 9; }

  _weitung() {
    const v = window.innerWidth / window.innerHeight;
    return Math.min(Math.max(Raum.BILD_REF / Math.max(v, 0.3), 1), 2.3);
  }

  _hochformat() {
    return window.innerWidth <= 1100 && window.innerHeight > window.innerWidth * 1.05;
  }

  /* --------------------------------------------------------------- Standbild
     Für Besucher, die „weniger Bewegung" eingestellt haben: Der Raum wird
     einmal aufgebaut und einmal gezeichnet, danach passiert nichts mehr.
     Kein Anflug, kein Driften, kein Atmen, keine Bildschleife — und damit
     auch kein Stromverbrauch. Die Kamera springt hart auf den Sollwert,
     statt ihn gedämpft anzufahren: Dämpfung ist Bewegung. */
  standbild() {
    this._camPos.copy(this.camGoal);
    this._camZiel.copy(this.lookGoal);
    this._fov = this.fovZiel;
    this.ruhig = true;
    this.render();
  }

  /* ------------------------------------------------------------------ Bild */
  render() {
    const dt = Math.min(this.uhr.getDelta(), 0.1);
    const jetzt = performance.now();

    /* Sollwerte gedämpft anfahren — nie hart setzen, sonst springt jede
       Änderung, statt zu gleiten. */
    this._camPos.lerp(this.camGoal, Math.min(1, dt * 2.6));
    this._camZiel.lerp(this.lookGoal, Math.min(1, dt * 2.6));
    this._fov += (this.fovZiel - this._fov) * Math.min(1, dt * 2.4);

    if (this.markeRig) {
      /* Die Dauerbewegung liegt über dem, was die Abschnitte setzen: Drehung
         aus dem Scrollstand, ein Heben und Senken, und ein winziges Wiegen,
         damit die Marke nie ganz still steht. Im Standbild entfällt das
         Wiegen — es ist klein, aber es ist Bewegung. */
      this.markeRig.rotation.y = this.drift.rotY + (this.ruhig ? 0 : Math.sin(jetzt * 0.00022) * 0.045);
      this.markeRig.rotation.x = this.drift.rotX;
      this.markeRig.position.y = this.markeHeim.y + this.drift.bob;
      if (this.spitze && this.marke) {
        this.marke.getWorldPosition(this.tmp);
        this.spitze.target.position.copy(this.tmp);
        this.spitze.target.updateMatrixWorld();
      }
    }

    /* Ein leichtes Atmen plus die Maus. Beides klein: Eine Bühne, die auf
       jede Mausbewegung deutlich reagiert, zieht die Aufmerksamkeit vom
       Text weg — und der Text ist hier die Hauptsache. */
    const atem = this.ruhig ? 0 : Math.sin(jetzt * 0.00035) * 0.07;
    const px = this.ruhig ? 0 : this.parallax.x, py = this.ruhig ? 0 : this.parallax.y;
    this.camera.position.set(
      this._camPos.x + atem + px * 0.30,
      this._camPos.y + atem * 0.4 - py * 0.18,
      this._camPos.z,
    );

    const w = this._weitung();
    const hoch = this._hochformat();
    if (w > 1.001) {
      const halb = Math.atan(Math.tan(this._fov * Math.PI / 360) * Math.min(w, 1.5));
      this.camera.fov = Math.min(74, halb * 360 / Math.PI);
      this.camera.position.sub(this._camZiel).multiplyScalar(1 + (w - 1) * (hoch ? 0.10 : 0.25)).add(this._camZiel);
    } else {
      this.camera.fov = this._fov;
    }
    this.camera.updateProjectionMatrix();
    if (hoch && w > 1.001) {
      this._blick.copy(this._camZiel);
      this._blick.y -= (w - 1) * 2.1;
      this.camera.lookAt(this._blick);
    } else {
      this.camera.lookAt(this._camZiel);
    }

    if (this.dof && this.marke) {
      /* Der Punkt liegt genau auf der Marke — deshalb wird sie scharf und
         alles davor und dahinter weich, wie bei einer offenen Blende. */
      this.marke.getWorldPosition(this.tmp);
      const d = this.camera.position.distanceTo(this.tmp);
      const u = this.dof.uniforms['focus'];
      u.value += (d - u.value) * Math.min(1, dt * 3.0);
    }
    if (this.grade) this.grade.uniforms.zeit.value = jetzt * 0.001;

    this.composer.render();
  }

  dispose() {
    if (this._aufResize) window.removeEventListener('resize', this._aufResize);
    if (this.composer) this.composer.dispose();
    if (this._karten) this._karten.forEach((t) => t.dispose());
    this.scene.traverse((o) => {
      if (o.isMesh) {
        if (o.geometry) o.geometry.dispose();
        const m = o.material;
        if (Array.isArray(m)) m.forEach((x) => x.dispose()); else if (m) m.dispose();
      }
    });
    this.renderer.dispose();
  }
}
