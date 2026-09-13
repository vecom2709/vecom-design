/* ==========================================================================
   site-world.js — Die 3D-Welt als Hintergrund der ganzen Seite.

   Grundsatz umgekehrt zur früheren Unterseite: Hier ist der Inhalt die
   Hauptsache und die Welt die Bühne dahinter. Deshalb:
   - kein Ladebild, das den Inhalt aufhält; die Seite ist sofort da
   - das Bild blendet sich ein, wenn es steht
   - fehlt WebGL, ist reduzierte Bewegung gewünscht oder ist das Gerät schwach,
     bleibt exakt die Seite übrig, die vorher da war
   ========================================================================== */
import { Quality, detectLevel, supportsWebGL } from './quality.js';
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
  /* DIE BUEHNE IST SEIT DEM 13.09.2026 DER BLENDER-RAUM
     ------------------------------------------------------------------
     Vorher stand hier scene.js: eine im Code gebaute Welt mit einem aus
     Konturpunkten extrudierten V, einem Boden, Staub und einem Halo. Sie
     war gut gemacht -- aber sie war gerechnet, und man sah es.

     Jetzt laedt raum.js den Raum, der in Blender gebaut wurde: Podest,
     Portale, Deckenfelder, Displays mit echten Arbeiten, und die Marke als
     Koerper mit eigenen Kanten. Ueber die Leitung kostet das 26 KB (die GLB
     gezippt) plus drei Texturkarten -- weniger als das Bild, das frueher im
     Hero stand.

     scene.js, site-beats.js, bruch.js und logo-shape.js bleiben liegen: Der
     Wechsel haengt an diesen beiden Zeilen, und wer zurueck will, tauscht
     sie zurueck. */
  let world, bindRaumBeats;
  try {
    const [{ Raum }, beats] = await Promise.all([
      import('./raum.js'),
      import('./raum-beats.js'),
    ]);
    bindRaumBeats = beats.bindRaumBeats;
    world = new Raum(canvas, quality);
    /* Ohne Modell keine Buehne. Waere hier kein Warten, saehe der Besucher
       fuer einen Moment einen leeren, blauschwarzen Raum -- und bei einem
       Ladefehler dauerhaft. Der Inhalt der Seite steht derweil laengst; das
       Warten haelt nichts auf ausser dem Einblenden der Buehne selbst. */
    await world.bereit;
  } catch (e) {
    console.warn('3D-Bühne nicht gestartet:', e);
    off('init-error');
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
  bindRaumBeats({ raum: world, gsap, ScrollTrigger });

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

  function loop() {
    if (!running) return;
    world.render();
    requestAnimationFrame(loop);
  }

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
  const quality = new Quality(detectLevel());
  let raum, hero;
  try {
    const [{ Raum }, beats] = await Promise.all([
      import('./raum.js'),
      import('./raum-beats.js'),
    ]);
    raum = new Raum(canvas, quality);
    await raum.bereit;
    hero = beats.RAUM_BEATS[0];
  } catch (e) {
    console.warn('3D-Standbild nicht gestartet:', e);
    off('init-error');
    return;
  }

  canvas.addEventListener('webglcontextlost', (e) => { e.preventDefault(); off('context-lost'); });

  /* Auf schmalen Schirmen liegt der Text ueber der Buehne — dieselbe
     Ruecknahme wie im bewegten Fall, sonst waere der Hero dort unlesbar.
     Die Werte stehen in raum-beats.js; hier nur die zwei, die ohne die
     Beat-Maschine gebraucht werden. */
  const schmal = !window.matchMedia('(min-width: 900px)').matches;
  raum.camGoal.copy(hero.cam);
  raum.lookGoal.copy(hero.ziel);
  raum.fovZiel = hero.fov;
  raum.scene.fog.density = schmal ? 0.0125 : hero.fog;
  raum.key.intensity = schmal ? 2.0 : hero.key;
  raum.spitze.intensity = schmal ? 195 : hero.spitze;
  raum.wand.intensity = hero.wand;
  raum.bloom.strength = schmal ? 0.42 : hero.bloom;
  root.style.setProperty('--world-scrim', String(schmal ? 0.06 : hero.scrim));

  raum.standbild();
  root.setAttribute('data-world', 'on');
  root.setAttribute('data-opening', 'done');
  if (window.__auftaktFrei) { window.__auftaktFrei(); }

  /* Ein neues Fenstermass braucht ein neues Bild — sonst steht ein
     verzerrter Ausschnitt da. Das ist keine Bewegung, sondern eine Antwort. */
  let warte = 0;
  window.addEventListener('resize', () => {
    clearTimeout(warte);
    warte = setTimeout(() => raum.standbild(), 200);
  }, { passive: true });

  window.__vecomWorld = { world: raum, quality, lenis: null, ruhig: true };
}
