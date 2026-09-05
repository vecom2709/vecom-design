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
import { bruchGeometrie, bruchMaterial } from './bruch.js';

const BLUE = 0x0648e8;
const CYAN = 0x1fe8ff;
const DEEP = 0x030509;

/* ==========================================================================
   ZWEI LICHTSITUATIONEN, EIN AUFBAU

   Die Buehne war fuer die Nacht gebaut: dunkler Raum, zwei Markenflaechen,
   eine weisse Softbox. Auf hellem Grund kippt genau das — der Lichtkegel
   frisst die Ueberschrift, und die Neonkanten, die nachts die Silhouette
   zeichnen, waschen tagsueber nur aus.

   Deshalb steht hier nicht eine zweite Szene, sondern ein zweiter Satz Werte
   fuer dieselbe. Was sich aendert, ist Licht, Nebel, Grund und Material —
   nicht die Geometrie, nicht die Kamera, nicht die Dramaturgie. Ein
   Szenenwechsel waere ein zweiter Ort, an dem dieselbe Wahrheit gepflegt
   werden muesste.

   Die Regel dahinter kommt aus dem Handwerk: Licht vor Material vor Kamera.
   Wer am Tag nur die Materialfarbe aufhellt, bekommt ein blasses Objekt in
   einem Nachtraum. Also wird zuerst der Raum umgebaut.
   ========================================================================== */
const THEMEN = {
  dark: {
    grund: ['#070f22', '#040814', '#02040a'],
    nebelFarbe: 0x02040a, nebelDichte: 0.032,
    envGrund: 0x02030a,
    /* [Breite, Hoehe, Farbe, Staerke, Position, Drehung] */
    panels: [
      [13, 8,  0xffffff, 6.5, [-1, 9, 2],    [Math.PI / 2, 0, 0]],
      [11, 13, 0x1c6cff, 6.0, [-9, 1.5, 1],  [0, Math.PI / 2, 0]],
      [4,  13, 0x1fe8ff, 5.5, [9, 0.5, -1],  [0, -Math.PI / 2, 0]],
      [20, 13, 0x0a1633, 1.6, [0, 0, -11],   null],
      [20, 20, 0x03050c, 1.0, [0, -6, 0],    [-Math.PI / 2, 0, 0]],
    ],
    ambient: [0x0a1526, 0.35],
    key: [0xffffff, 340],
    rimA: [BLUE, 90], rimB: [CYAN, 55],
    fill: [0x5a7cba, 18], spec: [0xffffff, 34], pointer: [0x9fd0ff, 26],
    logo: { color: 0x164bc4, roughness: 0.3, envMapIntensity: 1.9, iridescence: 0.08 },
    boden: { color: 0x070c16, roughness: 0.3, envMapIntensity: 0.7 },
    /* Nachts braucht es keinen Fond: Der Grund IST dunkel. */
    fond: { farbe: 0x02040a, staerke: 0.0 },
    belichtung: 1.05,
    bloom: 0.46,
    vignette: 0.55,
  },
};

/* Es gibt nur noch eine Fassung.
   Der Umschalter zwischen Tag und Nacht ist wieder raus: Auf hellem Grund
   liess sich derselbe Hochglanz nicht halten -- Glanz ist Kontrast, und
   ueber Weiss gibt es keinen. Was vom Tagmodus bleibt, ist die bessere
   Haelfte davon: Die Tageszeit steuert jetzt das Licht im dunklen Studio. */
function themaJetzt() { return 'dark'; }

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
    this.thema = themaJetzt();
    const T = THEMEN[this.thema];
    this.renderer.toneMappingExposure = T.belichtung;

    this.scene = new THREE.Scene();
    this.scene.background = this._backdropTexture(T.grund);
    this.scene.fog = new THREE.FogExp2(T.nebelFarbe, T.nebelDichte);

    // Eigenes Studio als Umgebung: dunkler Raum, zwei Lichtflächen in den
    // Markenfarben, eine weiße Softbox oben. Das ist der Grund, warum das Metall
    // blau spiegelt und nicht bunt — eine fertige Zimmerumgebung brächte fremde
    // Farben in jede Reflexion. Kosten: 0 KB, alles prozedural.
    this._bakeEnvironment(T);

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
    this.drift = { rotY: 0, rotX: 0, bob: 0, vel: 0, extraRot: 0, auftaktRot: 0 };

    this._buildLights();
    this._buildLogo();
    this._buildFloor();
    this._buildDust();
    this._buildComposer();

    /* Das Licht steht von Anfang an auf der Uhr des Besuchers -- sonst
       saehe die erste halbe Sekunde anders aus als der Rest. */
    this.setTageszeit();

    this.q.onChange = (s) => this._applyQuality(s);
    window.addEventListener('resize', () => this.resize(), { passive: true });
  }

  /* Hintergrundverlauf statt Schwarz: gibt der Silhouette eine Kante. */
  _backdropTexture(stufen) {
    const c = document.createElement('canvas');
    c.width = 4; c.height = 256;
    const ctx = c.getContext('2d');
    const g = ctx.createLinearGradient(0, 0, 0, 256);
    g.addColorStop(0.0, stufen[0]);
    g.addColorStop(0.45, stufen[1]);
    g.addColorStop(1.0, stufen[2]);
    ctx.fillStyle = g; ctx.fillRect(0, 0, 4, 256);
    const t = new THREE.CanvasTexture(c);
    t.colorSpace = THREE.SRGBColorSpace;
    t.mapping = THREE.EquirectangularReflectionMapping;
    return t;
  }

  /* ====================================================================
     DER HIMMEL HINTER DER MARKE

     Der Tagmodus stand vor einer Rechnung, die nicht aufgeht: Auf
     papierweissem Grund gibt es keinen Spielraum nach oben. Ein Glanz,
     der heller sein soll als der hellste Punkt der Seite, kann es nicht.
     Ein additiver Versuch hat die Marke prompt als Geist verschluckt.

     Die Loesung ist nicht mehr Licht, sondern ein dunklerer Grund --
     aber kein Grau. Grau hinter einem Koerper ist Studio; hier soll es
     draussen sein. Also ein Himmel: mittags tiefes Blau, morgens und
     abends warm am Horizont und nach oben ins Blaue laufend. Dieselbe
     Scheibe ist beides -- der Grund, VOR dem die Silhouette steht, und
     das, was das Metall SPIEGELT. Nur deshalb sieht die Sonne auf dem
     Koerper echt aus: Sie steht wirklich im Raum, statt als Glanzfleck
     aufgemalt zu sein.

     Der Rand laeuft weich in den Seitengrund aus, damit die Seite hell
     und der Text lesbar bleibt. Dunkel wird es nur dort, wo die Marke
     steht.
     ==================================================================== */
  _himmelTextur(t, weich) {
    const W = 1024, H = 512, HOR = Math.round(H * 0.62);
    const c = document.createElement('canvas');
    c.width = W; c.height = H;
    const ctx = c.getContext('2d');
    const bogen = Math.sin(Math.PI * Math.max(0, Math.min(1, t)));   // 0 früh/spät, 1 mittags

    const hsl = (h, sa, l) => 'hsl(' + h.toFixed(1) + ' ' + sa.toFixed(1) + '% ' + l.toFixed(1) + '%)';

    /* ZWEI HIMMEL UEBEREINANDER, NICHT EIN FARBTON DAZWISCHEN
       Erster Versuch war, den Farbton vom Orange des Horizonts zum Blau
       des Zenits zu rechnen. Der kurze Weg zwischen 28 und 210 Grad
       fuehrt aber durch Gruen -- und genau das kam heraus: ein sanft
       gruener Himmel.

       Richtig ist, was in der Luft auch passiert: Der Himmel ist immer
       blau, und davor liegt am Horizont eine warme Schicht, die umso
       staerker und hoeher ist, je tiefer die Sonne steht. Also ein
       blauer Verlauf, darueber ein warmer mit veraenderlicher Deckung. */
    const g = ctx.createLinearGradient(0, 0, 0, HOR);
    g.addColorStop(0.00, hsl(222, 58 + bogen * 10, 12 + bogen * 13));
    g.addColorStop(0.45, hsl(214, 54 + bogen * 8,  24 + bogen * 18));
    g.addColorStop(0.80, hsl(206, 46 + bogen * 6,  38 + bogen * 20));
    g.addColorStop(1.00, hsl(202, 40 + bogen * 6,  48 + bogen * 22));
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, HOR);

    /* Die warme Schicht. Mittags fast weg, morgens und abends bis weit
       nach oben. */
    const warm = ctx.createLinearGradient(0, HOR * 0.30, 0, HOR);
    const staerke = 1 - bogen;
    warm.addColorStop(0.00, 'rgba(255,150,60,0)');
    warm.addColorStop(0.55, 'rgba(255,140,52,' + (0.30 * staerke).toFixed(3) + ')');
    warm.addColorStop(0.85, 'rgba(255,132,44,' + (0.68 * staerke + 0.06).toFixed(3) + ')');
    warm.addColorStop(1.00, 'rgba(255,164,76,' + (0.82 * staerke + 0.10).toFixed(3) + ')');
    ctx.fillStyle = warm; ctx.fillRect(0, 0, W, HOR);

    /* Boden: warm und dunkler als der Himmel -- er haelt die untere
       Haelfte des Koerpers tief. */
    const b = ctx.createLinearGradient(0, HOR, 0, H);
    b.addColorStop(0.00, hsl(26, 44 - bogen * 10, 34 + bogen * 8));
    b.addColorStop(0.50, hsl(24, 38 - bogen * 8, 20 + bogen * 6));
    b.addColorStop(1.00, hsl(22, 32 - bogen * 8, 11 + bogen * 4));
    ctx.fillStyle = b; ctx.fillRect(0, HOR, W, H - HOR);

    /* Die Sonnenscheibe an ihrer wirklichen Stelle. Sie ist der Grund,
       warum das Glanzlicht wandert, wenn die Zeit laeuft. */
    const az = Math.PI * (1 - t);
    const hoehe = 0.8 + bogen * 9.5;
    const weite = Math.hypot(Math.cos(az) * 9.5, 6.4);
    const u = ((Math.atan2(6.4, Math.cos(az) * 9.5) / (2 * Math.PI)) + 0.75) % 1;
    const v = 0.5 - Math.atan2(hoehe, weite) / Math.PI;
    const sx = u * W, sy = Math.max(10, v * H);
    const glut = ctx.createRadialGradient(sx, sy, 1, sx, sy, 150);
    glut.addColorStop(0.00, 'rgba(255,253,246,1)');
    glut.addColorStop(0.08, 'rgba(255,238,206,0.92)');
    glut.addColorStop(0.34, 'rgba(255,206,150,0.30)');
    glut.addColorStop(1.00, 'rgba(255,190,130,0)');
    ctx.fillStyle = glut; ctx.beginPath(); ctx.arc(sx, sy, 150, 0, 7); ctx.fill();
    ctx.fillStyle = '#fffdf6'; ctx.beginPath(); ctx.arc(sx, sy, 15, 0, 7); ctx.fill();

    /* Duenne Dunstbaender ueber dem Horizont: Struktur statt Verlauf --
       daran erkennt das Auge im Spiegelbild eine Welt und keine Farbe. */
    ctx.globalAlpha = 0.14 + bogen * 0.08;
    for (let i = 0; i < 6; i++) {
      ctx.fillStyle = i % 2 ? '#ffffff' : '#ffdca8';
      ctx.fillRect(0, HOR - 14 - i * 15, W, 2 + (i % 2) * 2);
    }
    ctx.globalAlpha = 1;

    /* Fuer die Scheibe hinter der Marke: weicher runder Rand, damit sie
       im Seitengrund verlaeuft statt als Kreis darin zu stehen. */
    if (weich) {
      ctx.globalCompositeOperation = 'destination-in';
      const m = ctx.createRadialGradient(W / 2, H / 2, 0, W / 2, H / 2, W / 2);
      m.addColorStop(0.00, 'rgba(0,0,0,1)');
      m.addColorStop(0.44, 'rgba(0,0,0,1)');
      m.addColorStop(0.64, 'rgba(0,0,0,0.86)');
      m.addColorStop(0.82, 'rgba(0,0,0,0.34)');
      m.addColorStop(1.00, 'rgba(0,0,0,0)');
      ctx.fillStyle = m; ctx.fillRect(0, 0, W, H);
      ctx.globalCompositeOperation = 'source-over';
    }

    const tex = new THREE.CanvasTexture(c);
    tex.colorSpace = THREE.SRGBColorSpace;
    if (!weich) tex.mapping = THREE.EquirectangularReflectionMapping;
    return tex;
  }

  /* ====================================================================
     DIE SONNE IM DUNKLEN STUDIO

     Vom Tagmodus ist das hier uebrig geblieben, und es ist der Teil, der
     getragen hat. Die Seite bleibt eine Nachtseite -- dort funktioniert
     der Hochglanz, weil es Dunkelheit gibt, gegen die er leuchtet. Aber
     das Licht darin steht nicht mehr fest: Es folgt der Uhr des
     Besuchers.

       morgens   tief von links, warmes Bernstein, langes weiches Licht
       mittags   hoch und fast neutral, harter kleiner Glanzpunkt
       abends    tief von rechts, tiefes Orange
       nachts    kuehl und flach, wie die Seite sie bisher hatte

     Es wandert also, WOHER das Licht kommt und welche Farbe es hat --
     nicht, wie hell der Raum ist. Der Koerper bleibt immer tief, die
     Spiegelung bleibt das Studio. Deshalb kippt nichts, egal zu welcher
     Stunde jemand kommt.

     Die Sonnenscheibe kommt zusaetzlich in die Umgebung, mit kleinem
     Anteil: Sie ist der Grund, warum das Glanzlicht ueber den Tag
     wandert, statt nur die Farbe zu wechseln.
     ==================================================================== */

  /** Sonnenstand aus der Uhr: 0 = Aufgang, 1 = Untergang, null = Nacht. */
  static tageszeitJetzt(jetzt) {
    const d = jetzt || new Date();
    const std = d.getHours() + d.getMinutes() / 60;
    /* Sizilien grob gemittelt: sechs bis zwanzig Uhr ist Tag. Genauer zu
       rechnen (Datum, Breitengrad) waere Aufwand fuer einen Unterschied,
       den auf einem Logo niemand sieht. */
    if (std < 6 || std > 20) { return null; }
    return (std - 6) / 14;
  }

  /**
   * @param {number|null|undefined} t 0..1, null = Nacht.
   *        Ohne Angabe wird die Uhr des Besuchers gelesen.
   */
  setTageszeit(t) {
    if (t === undefined) { t = World.tageszeitJetzt(); }
    this.tageszeit = t;
    const T = THEMEN[this.thema];
    if (!T || !this.key) { return; }

    if (t === null) {
      /* Nacht: der Zustand, den die Seite immer hatte. Kein Sonnenlicht,
         keine Scheibe in der Umgebung -- nur das Studio. */
      this.key.position.set(-4.5, 7.5, 6);
      this.key.color.setHex(T.key[0]);
      this.key.intensity = T.key[1];
      this.rimA.color.setHex(T.rimA[0]);
      this.rimB.color.setHex(T.rimB[0]);
      this._bakeEnvironment(T);
      return;
    }

    t = Math.max(0.02, Math.min(0.98, t));
    const bogen = Math.sin(Math.PI * t);          // 0 frueh/spaet, 1 mittags
    const az = Math.PI * (1 - t);                 // links -> oben -> rechts

    this.key.position.set(Math.cos(az) * 8.5, 1.4 + bogen * 8.0, 5.5 + bogen * 1.6);
    /* Farbe: tiefes Bernstein am Rand des Tages, fast weiss im Zenit. */
    this.key.color.setHSL(0.075 + bogen * 0.010, 0.68 - bogen * 0.60, 0.56 + bogen * 0.40);
    this.key.intensity = T.key[1] * (0.72 + bogen * 0.55);

    /* Die Kantenlichter nehmen die Stimmung auf, ohne die Marke zu
       verlassen: Das Blau bleibt Blau, es wird nur waermer oder kuehler. */
    this.rimA.color.setHSL(0.60 - bogen * 0.02, 0.85, 0.42 + bogen * 0.10);
    this.rimB.color.setHSL(0.52 + bogen * 0.02, 0.90, 0.44 + bogen * 0.08);

    const himmel = this._himmelTextur(t, false);
    this._bakeEnvironment(T, himmel, 0.16);
    himmel.dispose();
  }



  /* Softboxen als Umgebung — nur emissive Flächen, keine Lichtberechnung. */
  _studioEnvironment(T, himmelTex, anteil) {
    const env = new THREE.Scene();
    env.background = new THREE.Color(T.envGrund);

    /* Der Himmel als Kuppel um das Studio herum. Von innen sichtbar,
       abgedunkelt ueber die Materialfarbe: Er soll den Raum ergaenzen,
       nicht ihn ausleuchten. Steht keine Textur an, bleibt es beim
       Studio -- also nachts. */
    if (himmelTex) {
      const k = new THREE.Mesh(
        new THREE.SphereGeometry(46, 32, 24),
        new THREE.MeshBasicMaterial({
          map: himmelTex,
          side: THREE.BackSide,
          color: new THREE.Color().setScalar(Math.max(0, Math.min(1, anteil == null ? 0.3 : anteil))),
        })
      );
      env.add(k);
    }
    T.panels.forEach(([w, h, color, intensity, pos, rot]) => {
      const m = new THREE.Mesh(
        new THREE.PlaneGeometry(w, h),
        new THREE.MeshBasicMaterial({ color: new THREE.Color(color).multiplyScalar(intensity), side: THREE.DoubleSide })
      );
      m.position.set(...pos);
      if (rot) m.rotation.set(...rot);
      env.add(m);
    });
    return env;
  }

  /* Umgebung backen. Beim Wechsel wird die alte Textur freigegeben — sonst
     bleibt bei jedem Umschalten ein Render Target im Speicher liegen, und
     genau daran erkennt man eine Szene, die nie umgeschaltet wurde. */
  /* STUDIO UND HIMMEL ZUSAMMEN, NICHT STATT
     --------------------------------------------------------------------
     Ein Fehler, den ich zweimal gemacht habe: erst das Studio durch einen
     hellen Tagesraum ersetzt (der Koerper wurde Milchglas), dann durch
     den Himmel (derselbe Effekt, nur blauer). Beide Male war es ein
     Tausch, wo es eine Ergaenzung sein muss.

     Das dunkle Studio macht die Tiefe und die Kante -- es ist der Grund,
     warum die Flaechen satt bleiben. Der Himmel macht die Sonne: eine
     Scheibe, die wandert, und ein Glanzlicht, das mitwandert. Beides in
     EINER gebackenen Umgebung, der Himmel deutlich schwaecher gewichtet,
     damit er die Tiefe nicht wieder aufhellt.

     @param {number} anteil 0 = nur Studio, 1 = Himmel in voller Staerke.
  */
  _bakeEnvironment(T, himmelTex, anteil) {
    const alt = this.envRT;
    const pmrem = new THREE.PMREMGenerator(this.renderer);
    this.envRT = pmrem.fromScene(this._studioEnvironment(T, himmelTex, anteil), 0.03);
    this.scene.environment = this.envRT.texture;
    pmrem.dispose();
    if (alt) { alt.dispose(); }
  }

  /* ---------- Licht: eine Hauptquelle, zwei Kanten, ein Fülllicht ---------- */
  _buildLights() {
    const T = THEMEN[this.thema];
    this.ambient = new THREE.AmbientLight(T.ambient[0], T.ambient[1]);
    this.scene.add(this.ambient);

    this.key = new THREE.SpotLight(T.key[0], T.key[1], 40, Math.PI / 6, 0.55, 1.6);
    this.key.position.set(-4.5, 7.5, 6);
    this.key.castShadow = this.q.settings.shadow > 0;
    if (this.key.castShadow) {
      this.key.shadow.mapSize.set(this.q.settings.shadow, this.q.settings.shadow);
      this.key.shadow.bias = -0.0016;
      this.key.shadow.radius = 3;
    }
    this.scene.add(this.key, this.key.target);

    this.rimA = new THREE.PointLight(T.rimA[0], T.rimA[1], 22, 2);
    this.rimA.position.set(5.5, 1.2, -3.5);
    this.rimB = new THREE.PointLight(T.rimB[0], T.rimB[1], 16, 2);
    this.rimB.position.set(-4.2, 1.8, -3.2);
    this.fill = new THREE.PointLight(T.fill[0], T.fill[1], 26, 2);
    this.fill.position.set(0, 3.2, 7.5);
    this.spec = new THREE.PointLight(T.spec[0], T.spec[1], 20, 2);   // Glanzpunkt
    this.spec.position.set(2.4, 3.0, 5.2);
    this.pointerLight = new THREE.PointLight(T.pointer[0], T.pointer[1], 16, 2);
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
    const TM = THEMEN[this.thema];
    this.mat = new THREE.MeshPhysicalMaterial({
      color: TM.logo.color,
      metalness: 0.86,      // nicht 1.0: ein Rest Diffusanteil hält die Fläche
      roughness: TM.logo.roughness,   // je nach Grund: dunkel spiegelt mehr

      roughnessMap: brushed,      // gebürstete Struktur: bricht die tote Fläche
      clearcoat: 1.0,
      clearcoatRoughness: 0.07,
      envMapIntensity: TM.logo.envMapIntensity,
      anisotropy: 0.6,           // längliche Reflexe wie bei geschliffenem Metall
      anisotropyRotation: Math.PI / 3,
      transmission: 0.0,          // wird für den Glaszustand hochgefahren
      thickness: 0.0,
      ior: 1.5,
      emissive: new THREE.Color(0x1a6cff),
      emissiveIntensity: 0.0,
      iridescence: TM.logo.iridescence,   // nur ein Hauch — mehr kippt ins Violette
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
    /* Der Bruchkörper: dieselbe Kontur, dieselbe Materialinstanz-Vorlage, nur in
       Voronoi-Brocken zerlegt. Steht bei uBruch = 0 deckungsgleich über dem
       heilen Körper — deshalb springt im Moment des Bruchs nichts. Er ist
       normalerweise unsichtbar und kostet dann auch nichts. */
    const bruch = bruchMaterial(THREE, this.mat);
    this.bruchMat = bruch.mat;
    this.bruchU = bruch.uniformen;
    this.bruchMesh = new THREE.Mesh(
      bruchGeometrie({ tiefe: EXTRUDE.depth, mitte: c, zellen: 30 }),
      this.bruchMat
    );
    this.bruchMesh.visible = false;
    this.logo.add(this.bruchMesh);

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

  /* Die Verlaufsscheibe fuer den Fond.
     Nicht dieselbe wie fuer den Lichtsaum: Der Saum soll schnell abfallen,
     damit er ein Schimmer bleibt. Ein Fond braucht das Gegenteil -- eine
     breite volle Mitte und einen langen weichen Rand, sonst steht ein
     dunkler Fleck hinter der Marke statt einer Flaeche, die im Grund
     verlaeuft. */
  _fondTexture() {
    const s = 512;
    const c = document.createElement('canvas');
    c.width = c.height = s;
    const ctx = c.getContext('2d');
    const g = ctx.createRadialGradient(s / 2, s / 2, 0, s / 2, s / 2, s / 2);
    /* Volle Mitte bis weit nach aussen, dann ein kurzer weicher Rand.
       Der lange Verlauf von vorher war ein Schimmer -- die Silhouette
       stand nur zur Haelfte davor und die untere Spitze lief ins Grau
       der Seite. Ein Leuchtkasten hat eine Flaeche, keinen Hauch. */
    g.addColorStop(0.00, 'rgba(255,255,255,1)');
    g.addColorStop(0.58, 'rgba(255,255,255,1)');
    g.addColorStop(0.74, 'rgba(255,255,255,0.92)');
    g.addColorStop(0.88, 'rgba(255,255,255,0.45)');
    g.addColorStop(1.00, 'rgba(255,255,255,0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, s, s);
    const t = new THREE.CanvasTexture(c);
    t.colorSpace = THREE.SRGBColorSpace;
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
    const TB = THEMEN[this.thema];
    const m = new THREE.MeshStandardMaterial({
      color: TB.boden.color,
      metalness: 0.8,
      roughness: TB.boden.roughness,
      envMapIntensity: TB.boden.envMapIntensity,
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
      new THREE.Vector2(window.innerWidth, window.innerHeight),
      THEMEN[this.thema].bloom, 0.75, 0.95
    );
    this.composer.addPass(this.bloom);

    // Abschluss-Pass: Lichtstreuung, Tiefenunschärfe, Farbsaum, Vignette.
    this.finish = new ShaderPass(FinishShader);
    this.finish.uniforms.uLightPos.value = new THREE.Vector2(0.5, 0.35);
    this.finish.uniforms.uVignette.value = THEMEN[this.thema].vignette;
    this.finish.uniforms.uAspect.value = window.innerWidth / window.innerHeight;
    this.composer.addPass(this.finish);

    this.composer.addPass(new OutputPass());
    this.bloom.enabled = this.q.settings.bloom;
  }

  /* ==================================================================== */
  /*  Umschalten zwischen Tag und Nacht                                    */
  /* ==================================================================== */

  /**
   * Ehemals der Umschalter zwischen Tag und Nacht.
   *
   * Es gibt nur noch die Nachtfassung -- der Tagmodus ist wieder raus,
   * weil derselbe Hochglanz auf hellem Grund nicht zu halten war. Die
   * Methode bleibt als leerer Griff stehen, damit aelterer Code, der sie
   * noch ruft, nicht ins Leere laeuft. Was die Stimmung aendert, ist
   * jetzt setTageszeit().
   */
  setThema() { /* eine Fassung, nichts umzuschalten */ }



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
    this.logo.rotation.y += ((this.drift.rotY + this.drift.extraRot + this.drift.auftaktRot) - this.logo.rotation.y) * dl;
    this.logo.rotation.x += (this.drift.rotX - this.logo.rotation.x) * dl;
    this.logo.position.y += ((Math.sin(time * 0.35) * 0.12 + this.drift.bob) - this.logo.position.y) * dl;
    // Scrollgeschwindigkeit gibt einen kurzen Schub — das macht die Bewegung
    // greifbar, statt sie nur ablaufen zu lassen.
    /* DIE SCHWUNG-ZAHL MUSS ZURUECKFALLEN
       ------------------------------------------------------------------
       drift.vel kommt aus der Scrollgeschwindigkeit und wird NUR gesetzt,
       solange gescrollt wird -- danach behaelt sie ihren letzten Wert fuer
       immer. Die Kamera stand damit dauerhaft um den Betrag der letzten
       Bewegung verschoben: gemessen z = 10,0 statt 12,4, also eine Marke,
       die zu gross im Bild steht und dort bleibt.

       Sichtbar wurde das erst, als der Auftaktfilm wegfiel. Vorher war die
       Welt beim Aufblenden vier Sekunden alt und noch im Anflug -- der
       Versatz ging im Anflug unter. Ohne Film sieht man ihn sofort.

       Also klingt die Zahl von selbst ab: Ein Schwung gibt weiter seinen
       Stoss, aber wer aufhoert zu scrollen, bekommt seine Bildeinstellung
       zurueck. */
    this.drift.vel += (0 - this.drift.vel) * (1 - Math.pow(0.02, dt));
    this.camera.position.z += (this.drift.vel * 0.9 - 0) * dt * 2.0;

    /* Der Fond folgt der Marke in der Bildebene, bleibt aber fest in der
       Tiefe und ungedreht -- er ist eine Wand, kein Anhaengsel. */
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
