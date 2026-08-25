/* ==========================================================================
   site-beats.js — Die Welt liegt jetzt hinter der ganzen Seite, nicht mehr
   auf einer eigenen Unterseite. Jeder Abschnitt hat einen Kamerazustand;
   beim Betreten gleitet die Kamera dorthin.

   Warum nicht durchgehend gescrubbt wie auf einer Story-Seite: Die Abschnitte
   sind unterschiedlich hoch und wachsen mit dem Inhalt. Ein fester Zeitstrahl
   würde bei jeder Textänderung verrutschen. Ein Zustand je Abschnitt bleibt
   richtig, egal wie lang der Abschnitt wird.

   ABSCHNITT   AUSSAGE                  KAMERA                LICHT
   hero        Ankunft, Marke groß      weit, leicht seitlich Kante + Softbox
   services    Das Handwerk             Dolly heran, links    Hauptlicht hoch
   work        Die Arbeiten             Dreiviertel, rechts   Reflexe
   process     Der Schnitt / Methode    nah an der Kerbe      hartes Kantenlicht
   pillars     Haltung                  Kranfahrt zurück      Bodenkontakt
   plans       Das Angebot              frontal, ruhig        ausgeglichen
   contact     Ruhelage                 Augenhöhe, mittig     weich
   ========================================================================== */

export const SITE_BEATS = [
  { id: 'hero',     cam: [0.6, 0.8, 12.0], look: [1.1, 0.1, 0],  pos: [2.3, 0.1, 0],  rotY: -0.52, rotX: 0.04, fog: 0.044, key: 260, spec: 26, bloom: 0.38, rough: 0.30, keyPos: [-4.5, 7.5, 6.0], halo: 0.30, scrim: 0.00 },
  { id: 'services', cam: [-2.2, 1.0, 8.8], look: [-1.2, 0.1, 0], pos: [-2.0, 0.0, 0], rotY: -0.22, rotX: 0.02, fog: 0.034, key: 420, spec: 40, bloom: 0.52, rough: 0.26, keyPos: [-5.5, 6.0, 5.0], halo: 0.24, scrim: 0.62 },
  { id: 'work',     cam: [2.7, 1.1, 9.6],  look: [1.4, 0.0, 0],  pos: [1.8, -0.1, 0], rotY: 0.34,  rotX: -0.04, fog: 0.030, key: 330, spec: 42, bloom: 0.44, rough: 0.16, keyPos: [5.5, 5.5, 7.5], halo: 0.16, scrim: 0.74 },
  { id: 'process',  cam: [-1.9, -0.1, 6.6], look: [-1.6, 0.25, 0], pos: [-1.8, 0.1, 0], rotY: 0.36, rotX: -0.03, fog: 0.028, key: 280, spec: 26, bloom: 0.42, rough: 0.19, keyPos: [-5.0, 4.5, 6.5], halo: 0.12, scrim: 0.66 },
  { id: 'pillars',  cam: [1.2, 4.4, 17.5], look: [1.5, -1.9, 0], pos: [2.0, -1.3, 0], rotY: 0.12,  rotX: 0.0,  fog: 0.036, key: 560, spec: 34, bloom: 0.50, rough: 0.22, keyPos: [-3.0, 9.5, 8.0], halo: 0.34, scrim: 0.48 },
  { id: 'plans',    cam: [-0.4, 0.6, 10.4], look: [-1.0, 0.0, 0], pos: [-1.6, 0.0, 0], rotY: 0.02, rotX: 0.0,  fog: 0.038, key: 300, spec: 18, bloom: 0.40, rough: 0.22, keyPos: [-4.0, 7.0, 7.0], halo: 0.22, scrim: 0.78 },
  { id: 'faq',      cam: [2.4, 0.9, 12.6], look: [1.6, 0.1, 0],  pos: [2.4, 0.0, 0],  rotY: 0.44,  rotX: 0.02, fog: 0.038, key: 300, spec: 24, bloom: 0.40, rough: 0.24, keyPos: [4.5, 6.5, 7.0], halo: 0.20, scrim: 0.80 },
  { id: 'contact',  cam: [0.6, 1.7, 16.0], look: [0.4, -0.9, 0], pos: [0.2, -1.9, 0], rotY: -0.18, rotX: 0.0,  fog: 0.042, key: 420, spec: 22, bloom: 0.40, rough: 0.20, keyPos: [-3.2, 7.0, 8.0], halo: 0.30, scrim: 0.78 },
];

/* Bindet die Zustände an die Abschnitte. Kein Scrub, sondern ein gleitender
   Wechsel beim Betreten — die Kamera „fährt nach", statt am Rad zu kleben. */
export function bindSiteBeats({ world, gsap, ScrollTrigger }) {
  const w = world;
  const narrow = window.matchMedia('(max-width: 860px)').matches;
  const k = narrow ? 0.12 : 1;
  const zk = narrow ? 1.7 : 1;
  const root = document.documentElement;

  const applyTween = (b, instant) => {
    const d = instant ? 0 : 1.9;
    const e = 'power2.inOut';
    gsap.to(w.camGoal,  { x: b.cam[0] * k, y: b.cam[1], z: b.cam[2] * zk, duration: d, ease: e, overwrite: true });
    gsap.to(w.lookGoal, { x: b.look[0] * k, y: b.look[1], z: b.look[2], duration: d, ease: e, overwrite: true });
    gsap.to(w.logoRig.position, { x: b.pos[0] * k, y: b.pos[1], z: b.pos[2], duration: d, ease: e, overwrite: true });
    gsap.to(w.logoRig.rotation, { x: b.rotX, y: b.rotY, duration: d * 1.15, ease: e, overwrite: true });
    gsap.to(w.scene.fog, { density: b.fog, duration: d, ease: e, overwrite: true });
    gsap.to(w.key, { intensity: b.key, duration: d, ease: e, overwrite: true });
    gsap.to(w.key.position, { x: b.keyPos[0], y: b.keyPos[1], z: b.keyPos[2], duration: d, ease: e, overwrite: true });
    gsap.to(w.spec, { intensity: b.spec, duration: d, ease: e, overwrite: true });
    gsap.to(w.bloom, { strength: b.bloom, duration: d, ease: e, overwrite: true });
    gsap.to(w.mat, { roughness: b.rough, duration: d, ease: e, overwrite: true });
    gsap.to(w.contact.material, { opacity: b.id === 'pillars' ? 0.42 : 0.16, duration: d, ease: e, overwrite: true });
    gsap.to(w.halo.material, {
      opacity: b.halo, duration: d, ease: e, overwrite: true,
      onUpdate: () => { w.halo.visible = w.halo.material.opacity > 0.08; },
    });
    // Der Schleier dunkelt die Welt ab, sobald Text gelesen werden soll.
    gsap.to(root, { '--world-scrim': b.scrim, duration: d * 0.8, ease: e, overwrite: true });
  };

  applyTween(SITE_BEATS[0], true);

  SITE_BEATS.forEach((b, i) => {
    const el = i === 0 ? document.querySelector('.hero') : document.getElementById(b.id);
    if (!el) return;
    ScrollTrigger.create({
      trigger: el,
      start: 'top 62%',
      end: 'bottom 38%',
      onEnter: () => applyTween(b),
      onEnterBack: () => applyTween(b),
    });
  });

  return { applyTween, beats: SITE_BEATS };
}
