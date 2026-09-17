/* ==========================================================================
   site-world.js — Die 3D-Welt als Hintergrund der ganzen Seite.

   Grundsatz umgekehrt zur früheren Unterseite: Hier ist der Inhalt die
   Hauptsache und die Welt die Bühne dahinter. Deshalb:
   - kein Ladebild, das den Inhalt aufhält; die Seite ist sofort da
   - das Bild blendet sich ein, wenn es steht
   - fehlt WebGL, ist reduzierte Bewegung gewünscht oder ist das Gerät schwach,
     bleibt exakt die Seite übrig, die vorher da war
   ========================================================================== */
import { Quality, detectLevel, supportsWebGL, grafikZuSchwach, nurSoftwaregrafik, grafikKennung } from './quality.js';
/* three.js und die Bühne werden erst geladen, wenn feststeht, dass sie laufen
   sollen — auf schwachen Telefonen spart das rund 750 KB, die sonst nur
   heruntergeladen und weggeworfen würden. */

const root = document.documentElement;
const canvas = document.querySelector('[data-canvas]');
const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const saveData = navigator.connection && navigator.connection.saveData;
const coarse = window.matchMedia('(pointer: coarse)').matches;
const weak = coarse && ((navigator.deviceMemory || 4) < 4 || (navigator.hardwareConcurrency || 4) < 6);

function off(reason) {
  root.setAttribute('data-world', reason);
  if (canvas) canvas.hidden = true;
  /* Ohne Welt gibt es keinen Bruch, der die Seite freigibt. Also sofort. */
  if (window.__auftaktFrei) { window.__auftaktFrei(); }
}

if (!canvas) {
  /* nichts zu tun */
} else if (!supportsWebGL()) {
  off('no-webgl');
} else if (reduced) {
  /* WENIGER BEWEGUNG HEISST WENIGER BEWEGUNG, NICHT WENIGER INHALT
     ------------------------------------------------------------------
     Bis zum 13.09.2026 wurde die Buehne hier ganz abgeschaltet. Das war
     zu viel: Die Einstellung gibt es fuer Menschen, denen von bewegten
     Flaechen schwindelig wird -- nicht fuer Menschen, die nichts sehen
     wollen. Ein stehendes Bild tut ihnen nichts.

     Aufgefallen ist es, weil Uwe auf seinem eigenen Rechner nichts von
     der neuen Buehne sah: In Windows sind bei ihm die Animationseffekte
     aus, Chrome meldet das als prefers-reduced-motion, und die Seite
     nahm ihn beim Wort. Er ist damit nicht allein -- die Einstellung
     wird auch gesetzt, um Akku zu sparen oder weil ein Administrator
     sie gesetzt hat.

     Jetzt wird der Raum gebaut und GENAU EINMAL gezeichnet: kein
     Eroeffnungsflug, keine Kamerafahrt zwischen den Abschnitten, kein
     Driften, kein Atmen, keine Bildschleife. Damit steht auch der
     Stromverbrauch bei null, sobald das Bild da ist. */
  standbild();
} else if (saveData) {
  off('save-data');
} else if (nurSoftwaregrafik()) {
  /* DIE GRAFIK ENTSCHEIDET, NICHT DIE KERNE -- ABER SIE ENTSCHEIDET
     UEBER DIE BUEHNE, DIE WIRKLICH GEBAUT WIRD
     ------------------------------------------------------------------
     Die halbe Minute Stillstand vom 14.09.2026 auf einer Intel HD 4400 ist
     echt, und die Lehre daraus bleibt. Nur stand damals der schwere
     Blender-Raum auf der Buehne, und das Geraet landete ausserdem auf der
     mittleren Stufe -- mit Bloom und erhoehter Pixeldichte. Beides ist
     seither anders: detectLevel() stuft eine alte Grafik auf 'low', und
     hier steht seit dem 17.09.2026 wieder die leichte Marken-Buehne, die
     auf genau diesem Rechner monatelang lief, bevor der Raum kam.

     Eine alte Grafik ist deshalb kein Ausschlussgrund mehr. Ein
     Software-Rasterizer schon: Der rechnet jedes Bild auf der CPU und
     blockiert den Aufbau am Stueck -- gemessen 16,1 s auf SwiftShader,
     gegenueber 20,6 s fuer den Raum. Dagegen hilft keine Stufe.

     Bleibt das Netz darunter: quality.onAufgeben baut die Buehne ab, wenn
     selbst die unterste Stufe die Bilder nicht schafft. Wer dort landet,
     bekommt das gerechnete Standbild. */
  off('device');
} else if (weak) {
  off('device');
} else if (!window.gsap || !window.ScrollTrigger) {
  off('no-gsap');
} else {
  start();
}


/* WARUM HIER KEIN WARTEN MEHR STEHT
   Vorher lief zuerst ein Film (auftakt.mp4/.webm, zusammen 1,16 MB), und die
   Welt wurde erst danach gebaut -- three.js zu laden und die Buehne
   aufzustellen kostet mehrere Sekunden Blockade am Stueck, und die haetten
   den Film ruckeln lassen.

   Der Film ist raus. Die Szene hat ihren eigenen Auftakt laengst: einen
   Kameraflug von ausserhalb des Nebels auf den Hero-Zustand, den Bruch und
   das Zusammensetzen der Marke. Der war als Rueckfall gebaut, falls der Film
   fehlt -- jetzt ist er die Hauptsache. Das spart 1,16 MB, einen blockierenden
   Inline-Block, ein Videoelement und eine ganze Zustandsmaschine. */

async function start() {
  const quality = new Quality(detectLevel());
  /* DIE BUEHNE IST SEIT DEM 17.09.2026 WIEDER DIE MARKE
     ------------------------------------------------------------------
     Vom 13. bis zum 17.09.2026 stand hier raum.js: der in Blender gebaute
     Raum mit Podest, Portalen, Deckenfeldern und Displays. Er war der
     reichere Ort -- aber er hat die Marke zum Ausstellungsstueck gemacht,
     und wer die Seite scrollte, sah vor allem Architektur.

     Zurueck steht jetzt scene.js: eine im Code gebaute Welt mit dem aus den
     Konturen der Logodatei extrudierten V, Staub und Halo. Der Koerper
     dreht sich frei im Nebel und wandert beim Scrollen von Abschnitt zu
     Abschnitt hinter den Inhalt -- das ist die Bewegung, die Uwe gemeint
     hat, und sie lief auf seinem eigenen Rechner, bevor der Raum kam.

     Der Tausch haengt weiter an diesen beiden Zeilen. raum.js, raum-beats.js
     und das Modell bleiben liegen: showroom.html benutzt den Raum weiter,
     und wer ihn zurueckholen will, tauscht die zwei Zeilen zurueck.

     Gemessen am 17.09.2026 auf SwiftShader, Aufbau als eine Blockade:
     Raum 20,6 s -- Marke 16,1 s. Beide zu viel fuer einen Rasterizer auf
     der CPU, deshalb bleibt der draussen. Eine alte, echte Grafik traegt
     die Marke; den Raum trug sie nicht. */
  let world, bindBeats;
  try {
    const [{ World }, beats] = await Promise.all([
      import('./scene.js'),
      import('./site-beats.js'),
    ]);
    bindBeats = beats.bindSiteBeats;
    world = new World(canvas, quality);
    /* Ohne Modell keine Buehne. Waere hier kein Warten, saehe der Besucher
       fuer einen Moment einen leeren, blauschwarzen Raum -- und bei einem
       Ladefehler dauerhaft. Der Inhalt der Seite steht derweil laengst; das
       Warten haelt nichts auf ausser dem Einblenden der Buehne selbst. */
    /* MIT UHR, NICHT AUF GUT GLUECK
       ----------------------------------------------------------------
       world.bereit wartet auf das Modell und auf die erste Uebersetzung
       der Shader. Auf einer alten Grafik dauert genau das Letzte lange --
       und solange es dauert, steht der Hauptthread. Ohne Uhr wartet die
       Buehne unbegrenzt; mit ihr faellt sie nach zwoelf Sekunden auf das
       Standbild zurueck, das dann sofort steht.

       Zwoelf Sekunden sind grosszuegig: Eine schlechte Leitung soll nicht
       zum Abbruch fuehren, ein ueberfordertes Geraet schon. */
    /* Nur die Raum-Buehne laedt ein Modell nach und hat deshalb ein
       .bereit. Die Marken-Buehne steht nach dem Konstruktor. Eine Uhr, die
       auf nichts wartet, wuerde nach zwoelf Sekunden ins Leere ablehnen --
       eine unbehandelte Ablehnung in jeder Sitzung. */
    if (world.bereit) {
      const zuLang = new Promise((_, weg) => setTimeout(() => weg(new Error('Aufbau dauerte laenger als 12 s')), 12000));
      await Promise.race([world.bereit, zuLang]);
    }
  } catch (e) {
    console.warn('3D-Bühne nicht gestartet:', e);
    try { if (world) world.dispose(); } catch (e2) { /* nichts */ }
    off('device');
    return;
  }

  canvas.addEventListener('webglcontextlost', (e) => { e.preventDefault(); off('context-lost'); running = false; });

  const { gsap, ScrollTrigger, Lenis } = window;
  gsap.registerPlugin(ScrollTrigger);

  // Ein Scrollsystem. Lenis treibt ScrollTrigger, sonst gibt es zwei Wahrheiten.
  let lenis = null;
  if (Lenis && !window.matchMedia('(pointer: coarse)').matches) {
    lenis = new Lenis({ duration: 1.05, smoothWheel: true });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((t) => lenis.raf(t * 1000));
    gsap.ticker.lagSmoothing(0);
    window.__vecomLenis = lenis;
  }

  await (document.fonts ? document.fonts.ready : Promise.resolve());
  bindBeats({ world, gsap, ScrollTrigger });

    /* ----------------------------------------------------------------------
     DIE SONNE FOLGT DER UHR DES BESUCHERS

     Der Umschalter zwischen Tag und Nacht ist wieder raus -- die Seite ist
     eine Nachtseite, und der Versuch, dasselbe Metall auf hellem Grund
     glaenzen zu lassen, hat gegen eine einfache Rechnung verloren: Glanz
     ist Kontrast, und ueber Weiss gibt es keinen mehr.

     Geblieben ist der Gedanke, der daran gut war. Die Tageszeit steuert
     jetzt das Licht IM dunklen Studio: Wer morgens kommt, sieht ein
     tiefstehendes warmes Licht von links; mittags steht es hoch und
     neutral; abends faellt es warm von rechts; nachts bleibt es kuehl und
     flach. Der Koerper bleibt dabei immer tief -- es wandert nur, woher
     die Sonne kommt.

     Nachgezogen wird alle fuenf Minuten. Oefter waere Rechenzeit fuer eine
     Aenderung, die niemand sieht; seltener verpasst den Uebergang, wenn
     jemand die Seite lange offen laesst.
     ---------------------------------------------------------------------- */
  if (world.setTageszeit) {
    /* Das Ereignis sagt den Abschnitten, dass sie ihr Licht neu durch die
       Sonne rechnen muessen -- sonst bliebe der Stand auf dem, was beim
       letzten Scrollen galt. */
    const nachziehen = () => {
      world.setTageszeit();
      window.dispatchEvent(new Event('vecom:sonne'));
    };
    nachziehen();
    setInterval(() => { if (!document.hidden) { nachziehen(); } }, 300000);
    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) { nachziehen(); }
    });
  }

  window.addEventListener('pointermove', (e) => {
    if (e.pointerType !== 'mouse') return;
    world.setParallax(
      (e.clientX / window.innerWidth - 0.5) * 2,
      -(e.clientY / window.innerHeight - 0.5) * 2
    );
  }, { passive: true });

  let running = true;
  document.addEventListener('visibilitychange', () => {
    running = !document.hidden;
    if (running) requestAnimationFrame(loop);
  });

  /* Die Abstufung bekommt endlich Messwerte. Bis heute rief niemand
     quality.sample() auf -- die Klasse hat gemessen, was man ihr gab, und
     man gab ihr nichts. */
  let letzter = 0;
  function loop(jetzt) {
    if (!running) return;
    if (letzter) quality.sample(jetzt - letzter);
    letzter = jetzt;
    world.render();
    requestAnimationFrame(loop);
  }

  /* Wenn auch die unterste Stufe nicht traegt: Buehne abbauen, Standbild
     zeigen. Kein Zurueck -- ein Hin und Her waere sichtbarer als beides. */
  quality.onAufgeben = (avg) => {
    console.info('Bühne abgebaut: ' + avg.toFixed(0) + ' ms je Bild auf der untersten Stufe.');
    running = false;
    try { world.dispose(); } catch (e) { /* egal, sie wird ohnehin versteckt */ }
    off('device');
  };

  /* Die Stufe nach aussen geben: Das Stilblatt haengt die Staerke der
     Glas-Unschaerfe daran. backdrop-filter ist die teuerste Zeile CSS auf
     dieser Seite, und ein schwaches Geraet soll dieselbe Optik bekommen,
     ohne dieselbe Rechenarbeit. */
  root.setAttribute('data-stufe', quality.level);
  quality.onChange = (einst, stufe) => root.setAttribute('data-stufe', stufe);

  world.render();
  requestAnimationFrame(() => {
    root.setAttribute('data-world', 'on');
    requestAnimationFrame(loop);
    ScrollTrigger.refresh();
  });

  const select = document.querySelector('[data-quality]');
  if (select) {
    select.value = quality.level;
    select.addEventListener('change', () => {
      if (select.value === 'auto') { quality.locked = false; quality.set(detectLevel()); }
      else { quality.locked = true; quality.set(select.value); }
    });
  }

  window.__vecomWorld = { world, quality, lenis };
}


/* --------------------------------------------------------------------------
   Der Raum als Standbild.

   Absichtlich ein eigener, kurzer Weg statt eines Schalters in start():
   Hier gibt es kein gsap, kein ScrollTrigger, kein Lenis und keine
   Bildschleife. Was fehlt, kann auch nicht versehentlich wieder anspringen.
   -------------------------------------------------------------------------- */
async function standbild() {
  /* Auch hier zuerst die Grafik fragen -- aber nur nach dem einen, was
     wirklich nicht geht. Ein stehendes Bild aus der Echtzeitwelt kostet
     trotzdem den ganzen Aufbau: Szene stellen, Shader uebersetzen, einmal
     zeichnen. Auf einem Software-Rasterizer ist genau das der teure Teil;
     auf einer alten, echten Grafik ist es bezahlbar. */
  if (nurSoftwaregrafik()) { off('device'); return; }

  const quality = new Quality(detectLevel());
  let world, hero;
  try {
    const [{ World }, beats] = await Promise.all([
      import('./scene.js'),
      import('./site-beats.js'),
    ]);
    world = new World(canvas, quality);
    hero = beats.SITE_BEATS[0];
  } catch (e) {
    console.warn('3D-Standbild nicht gestartet:', e);
    try { if (world) world.dispose(); } catch (e2) { /* nichts */ }
    off('device');
    return;
  }

  canvas.addEventListener('webglcontextlost', (e) => { e.preventDefault(); off('context-lost'); });

  /* Auf schmalen Schirmen liegt der Text ueber der Buehne -- dieselbe
     Ruecknahme wie im bewegten Fall (HERO_GEDRAENGT in site-beats.js),
     sonst waere der Hero dort unlesbar. */
  const schmal = !window.matchMedia('(min-width: 900px)').matches;
  const bild = () => {
    world.camGoal.set(hero.cam[0], hero.cam[1], hero.cam[2]);
    world.lookGoal.set(hero.look[0], hero.look[1], hero.look[2]);
    world.scene.fog.density = schmal ? 0.088 : hero.fog;
    world.key.intensity = schmal ? 125 : hero.key;
    world.bloom.strength = schmal ? 0.09 : hero.bloom;
    world.drift.rotY = hero.rotY;
    world.drift.rotX = hero.rotX;
    world.logo.rotation.y = hero.rotY;
    world.logo.rotation.x = hero.rotX;
    world.logo.position.set(hero.pos[0], hero.pos[1], hero.pos[2]);
    /* Die Kamera faehrt im bewegten Fall gedaempft an ihren Sollwert. Hier
       gibt es keinen zweiten Frame, in dem sie ankommen koennte -- also
       wird sie direkt gesetzt. */
    world.camera.position.copy(world.camGoal);
    world.camTarget.copy(world.lookGoal);
    world.camera.lookAt(world.camTarget);
    root.style.setProperty('--world-scrim', String(schmal ? 0.60 : hero.scrim));
    world.render();
  };
  bild();

  root.setAttribute('data-stufe', quality.level);
  root.setAttribute('data-world', 'on');
  root.setAttribute('data-opening', 'done');
  if (window.__auftaktFrei) { window.__auftaktFrei(); }

  /* Ein neues Fenstermass braucht ein neues Bild -- sonst steht ein
     verzerrter Ausschnitt da. Das ist keine Bewegung, sondern eine Antwort. */
  let warte = 0;
  window.addEventListener('resize', () => {
    clearTimeout(warte);
    warte = setTimeout(() => { world.resize(); bild(); }, 200);
  }, { passive: true });

  window.__vecomWorld = { world, quality, lenis: null, ruhig: true };
}
