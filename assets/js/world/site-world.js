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
  off('reduced-motion');
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
  let world, bindSiteBeats;
  try {
    const [{ World }, beats] = await Promise.all([
      import('./scene.js'),
      import('./site-beats.js'),
    ]);
    bindSiteBeats = beats.bindSiteBeats;
    world = new World(canvas, quality);
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
  bindSiteBeats({ world, gsap, ScrollTrigger });

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
