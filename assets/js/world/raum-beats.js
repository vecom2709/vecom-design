/* ==========================================================================
   raum-beats.js — Wohin die Kamera im Showroom fährt, Abschnitt für Abschnitt.

   Die Welt liegt hinter der ganzen Seite, nicht auf einer eigenen Unterseite.
   Jeder Abschnitt hat einen Kamerazustand; beim Betreten gleitet die Kamera
   dorthin. Kein durchgehender Zeitstrahl: Die Abschnitte sind unterschiedlich
   hoch und wachsen mit dem Inhalt — ein fester Strahl würde bei jeder
   Textänderung verrutschen, ein Zustand je Abschnitt bleibt richtig.

   DIE ZAHLEN SIND GEMESSEN, NICHT GERATEN
   Der Raum wurde am 13.09.2026 ausgemessen (siehe PROJEKT.md):
     Boden       x −11,5 … +11,5   z −21 … +9    (Gang weiter bis z −34)
     Decke       y 7,4
     Marke_V     Mitte (0 | 3,33 | −4), 5,1 × 4,1 m auf dem Podest
     Portale     z −9, −17, −25
     Displays    z −20 … −22, x −10,6 … +10,6, Höhe 2,9 … 3,25
     Lamellenwand z −33,2
   Wer eine Kamera setzt, muss innerhalb dieser Grenzen bleiben — ein Meter
   zu weit, und man steht in der Wand oder über der Decke.

   DIE DRAMATURGIE
   hero      Ankunft von draußen, Marke groß und frei
   work      die Displaywand — hier hängen die echten Arbeiten
   services  der Gang mit den Portalen: Tiefe, Handwerk
   plans     zurück zur Marke, frontal und ruhig
   process   nah an die Kante, fast ein Detailbild
   about     weit von der Seite, der ganze Raum
   video     tief vom Podest aus nach oben
   pillars   Kranfahrt: von oben auf Marke und Boden
   partner   tief im Gang, die Portale von innen
   faq       Marke von rechts, mittlere Distanz
   contact   Ruhelage, Augenhöhe, mittig
   ========================================================================== */
import { b2t } from './raum.js';

/* cam/ziel in Blender-Koordinaten (Z nach oben) — eins zu eins zur .blend.
   fov: Blickwinkel. fog: Nebeldichte (der Raum ist 40 m tief, deshalb sind
   die Werte klein). key/spitze/wand: Lichtstärken. bloom: Leuchten.
   scrim: wie stark der Schleier die Welt hinter dem Text abdunkelt. */
export const RAUM_BEATS = [
  { id: 'hero',     cam: b2t(-8.0, -13.0, 2.60), ziel: b2t(-3.2,  4.0, 3.30), fov: 48, fog: 0.0098, key: 2.2, spitze: 260, wand: 120, bloom: 0.58, scrim: 0.00 },
  { id: 'work',     cam: b2t(-4.5,  12.5, 3.40), ziel: b2t( 2.0, 21.0, 3.00), fov: 52, fog: 0.0125, key: 1.6, spitze: 120, wand: 210, bloom: 0.50, scrim: 0.74 },
  { id: 'services', cam: b2t( 3.5,  -2.0, 2.90), ziel: b2t(-2.0, 14.0, 3.40), fov: 50, fog: 0.0150, key: 2.0, spitze: 190, wand: 170, bloom: 0.52, scrim: 0.62 },
  { id: 'plans',    cam: b2t( 2.2,  -6.5, 3.50), ziel: b2t( 0.0,  4.0, 3.35), fov: 42, fog: 0.0110, key: 2.4, spitze: 240, wand: 110, bloom: 0.46, scrim: 0.78 },
  { id: 'process',  cam: b2t(-2.6,  -1.2, 3.90), ziel: b2t( 0.4,  4.0, 3.30), fov: 38, fog: 0.0105, key: 2.6, spitze: 320, wand:  90, bloom: 0.54, scrim: 0.86 },
  { id: 'about',    cam: b2t( 7.5,  -4.0, 3.00), ziel: b2t(-1.5,  8.0, 3.20), fov: 54, fog: 0.0135, key: 1.8, spitze: 150, wand: 150, bloom: 0.48, scrim: 0.84 },
  { id: 'video',    cam: b2t(-1.2,  -4.5, 1.50), ziel: b2t( 0.0,  4.2, 3.90), fov: 48, fog: 0.0115, key: 2.2, spitze: 280, wand: 120, bloom: 0.52, scrim: 0.84 },
  { id: 'pillars',  cam: b2t( 2.0,  -6.0, 6.20), ziel: b2t( 0.0,  6.0, 2.40), fov: 50, fog: 0.0100, key: 2.6, spitze: 240, wand: 140, bloom: 0.48, scrim: 0.68 },
  { id: 'partner',  cam: b2t(-1.0,  18.0, 3.00), ziel: b2t( 1.5, 30.0, 3.20), fov: 52, fog: 0.0165, key: 1.5, spitze:  90, wand: 150, bloom: 0.42, scrim: 0.88 },
  /* FAQ stand mit der Kamera dicht an der Marke -- das ganze Bild war
     mittelblau, und die Glasscheiben darauf verloren jeden Kontrast.
     Jetzt von weiter hinten und an der Marke vorbei in den Gang: dunkler
     Grund, ruhige Flaeche, die Marke nur noch am Rand. */
  { id: 'faq',      cam: b2t( 7.5,   2.0, 3.10), ziel: b2t( 2.0, 16.0, 3.10), fov: 50, fog: 0.0170, key: 1.4, spitze:  80, wand: 130, bloom: 0.34, scrim: 0.86 },
  { id: 'contact',  cam: b2t(-3.0, -11.0, 2.50), ziel: b2t( 0.0,  4.6, 3.40), fov: 44, fog: 0.0105, key: 2.3, spitze: 250, wand: 130, bloom: 0.50, scrim: 0.78 },
];

/* Der Eröffnungsflug. Startpunkt weit vor dem Raum und hoch; von hier fährt
   die Kamera beim Laden in 3,4 s auf den Hero-Zustand. Nur einmal, nie wieder.
   Viel Nebel am Anfang: Der Raum soll aus dem Dunst kommen, nicht da sein. */
export const RAUM_OPENING = {
  id: 'opening',
  cam: b2t(-9.0, -33.0, 4.60), ziel: b2t(0, 5.0, 3.30), fov: 54,
  fog: 0.0330, key: 0.9, spitze: 70, wand: 40, bloom: 0.30, scrim: 0.00,
};

/* Auf schmalen Schirmen liegt der Hero-Text über der Marke statt neben ihr —
   dort gibt es kein Links und Rechts, der Text läuft über die volle Breite.

   Die frühere Antwort darauf war, die Bühne stark zurückzunehmen: viel Nebel,
   wenig Licht, dazu ein Schleier von 0,60. Mit dem Textschleier, der jetzt in
   app.css steht, ist das zu viel des Guten — gemessen am 13.09.2026 auf
   390x844: Kontrast überall über 4.5:1, aber vom Raum war nichts mehr zu
   sehen, ein schwarzes Bild mit einer Ahnung von Blau. Ein Hintergrund, den
   niemand erkennt, ist kein Hintergrund, sondern verschenkte Ladezeit.

   Also nur noch ein leichter Rückzug. Den Kontrast trägt der Verlauf, der
   von unten nach oben läuft: dunkel, wo die Zeilen stehen, offen darüber,
   wo der Raum zu sehen sein soll. Der Bruch liegt bewusst auf demselben
   Wert wie in app.css; wer den einen ändert, muss den anderen mitziehen. */
const HERO_GEDRAENGT = { fog: 0.0125, key: 2.0, spitze: 195, bloom: 0.42, scrim: 0.06 };

export function bindRaumBeats({ raum, gsap, ScrollTrigger }) {
  const r = raum;
  const root = document.documentElement;
  const heroGeteilt = window.matchMedia('(min-width: 900px)').matches;
  const BEATS = RAUM_BEATS.map((b, i) => (!heroGeteilt && i === 0 ? { ...b, ...HERO_GEDRAENGT } : b));

  /* ------------------------------------------------------------------
     DIE SONNE GEHÖRT NICHT DEN ABSCHNITTEN

     Jeder Beat bringt seine eigene Lichtstärke mit. Würde er sie einfach
     setzen, wäre der Tagesgang beim ersten Scrollen weg. Also rechnen die
     Beats durch die Sonne hindurch: Richtung und Höhe kommen vom Tag, der
     Charakter vom Abschnitt. Nachts (keine Sonne) bleibt jeder Wert exakt
     der, der im Beat steht.
     ------------------------------------------------------------------ */
  const sKey  = (v) => v * (r.sonne ? r.sonne.key : 1);
  const sSpec = (v) => v * (r.sonne ? r.sonne.spec : 1);
  let letzterBeat = null;

  const anfahren = (b, sofort) => {
    letzterBeat = b;
    const d = sofort ? 0 : 1.9;
    const e = 'power2.inOut';
    gsap.to(r.camGoal,  { x: b.cam.x,  y: b.cam.y,  z: b.cam.z,  duration: d, ease: e, overwrite: true });
    gsap.to(r.lookGoal, { x: b.ziel.x, y: b.ziel.y, z: b.ziel.z, duration: d, ease: e, overwrite: true });
    gsap.to(r, { fovZiel: b.fov, duration: d, ease: e, overwrite: 'auto' });
    gsap.to(r.scene.fog, { density: b.fog, duration: d, ease: e, overwrite: true });
    gsap.to(r.key,    { intensity: sKey(b.key),     duration: d, ease: e, overwrite: true });
    gsap.to(r.spitze, { intensity: sSpec(b.spitze), duration: d, ease: e, overwrite: true });
    gsap.to(r.wand,   { intensity: b.wand,          duration: d, ease: e, overwrite: true });
    gsap.to(r.bloom,  { strength: b.bloom,          duration: d, ease: e, overwrite: true });

    /* Filmischer Schnitt: ein kurzer Lichtschleier kaschiert den Wechsel. */
    if (!sofort) {
      root.setAttribute('data-cut', '1');
      gsap.delayedCall(0.6, () => root.removeAttribute('data-cut'));
    }
    /* Der Schleier dunkelt die Welt ab, sobald Text gelesen werden soll. */
    gsap.to(root, { '--world-scrim': b.scrim, duration: d * 0.8, ease: e, overwrite: true });
  };

  /* Der Eröffnungsflug: erst den Startzustand hart setzen, dann in 3,4 s
     auf den Hero zu. Danach übernimmt das Scrollen. */
  anfahren(RAUM_OPENING, true);
  r.camGoal.copy(RAUM_OPENING.cam);
  r.lookGoal.copy(RAUM_OPENING.ziel);

  const hero = BEATS[0];
  let flugLaeuft = true;
  const flug = gsap.timeline({ onComplete: () => { flugLaeuft = false; } });
  flug.to({}, { duration: 0.25 });
  flug.add(() => {
    const d = 3.4, e = 'power3.out';
    gsap.to(r.camGoal,  { x: hero.cam.x,  y: hero.cam.y,  z: hero.cam.z,  duration: d, ease: e, overwrite: true });
    gsap.to(r.lookGoal, { x: hero.ziel.x, y: hero.ziel.y, z: hero.ziel.z, duration: d, ease: e, overwrite: true });
    gsap.to(r, { fovZiel: hero.fov, duration: d, ease: e, overwrite: 'auto' });
    gsap.to(r.scene.fog, { density: hero.fog, duration: d, ease: e, overwrite: true });
    gsap.to(r.key,    { intensity: sKey(hero.key),     duration: d * 0.8, ease: e, overwrite: true });
    gsap.to(r.spitze, { intensity: sSpec(hero.spitze), duration: d,       ease: e, overwrite: true });
    gsap.to(r.wand,   { intensity: hero.wand,          duration: d,       ease: e, overwrite: true });
    gsap.to(r.bloom,  { strength: hero.bloom,          duration: d,       ease: e, overwrite: true });
    /* Der Eröffnungsflug muss den Schleier mitziehen, sonst bliebe der Hero
       auf schmalen Schirmen ungeschleiert, bis man einmal weg- und
       zurückgescrollt hat. */
    gsap.to(root, { '--world-scrim': hero.scrim, duration: d * 0.7, ease: e, overwrite: true });
    letzterBeat = hero;
    root.setAttribute('data-opening', 'done');
  });
  flug.to({}, { duration: 3.4 });

  /* Wer sofort scrollt, will die Show nicht — dann sofort in den Hero. */
  const abkuerzen = () => {
    if (!flugLaeuft) return;
    flug.progress(1);
    flugLaeuft = false;
    anfahren(hero, true);
  };
  window.addEventListener('wheel', abkuerzen, { once: true, passive: true });
  window.addEventListener('touchstart', abkuerzen, { once: true, passive: true });

  /* Die Uhr springt alle fünf Minuten weiter. Ohne das hier zöge die Sonne
     erst beim nächsten Scrollen nach — wer die Seite offen stehen lässt,
     sähe den Übergang nie. */
  window.addEventListener('vecom:sonne', () => { if (letzterBeat) anfahren(letzterBeat); });

  /* Jeder Abschnitt sein Zustand. Fehlt ein Abschnitt auf der Seite, fällt
     nur seine Zeile aus — nie das ganze Band. */
  BEATS.forEach((b, i) => {
    const el = i === 0 ? document.querySelector('.hero') : document.getElementById(b.id);
    if (!el) return;
    ScrollTrigger.create({
      trigger: el,
      start: 'top 62%',
      end: 'bottom 38%',
      onEnter:     () => { if (i > 0 || !flugLaeuft) anfahren(b); },
      onEnterBack: () => { if (i > 0 || !flugLaeuft) anfahren(b); },
    });
  });

  /* --------------------------------------------------------------------
     Dauerbewegung über die ganze Seite. Die Beats setzen Zielpunkte, aber
     zwischen zwei Abschnitten stünde die Marke sonst still — besonders im
     langen Hero. Diese Spur läuft durchgehend mit dem Scrollbalken.

     Genau eine Vierteldrehung über die ganze Seite, nicht mehr: Die Marke
     ist ein Körper mit Vorder- und Rückseite, und ihre Rückseite ist eine
     flache Platte. Eine volle Umdrehung wie beim alten, beidseitig gebauten
     Logo würde sie zeigen.
     -------------------------------------------------------------------- */
  ScrollTrigger.create({
    trigger: document.body,
    start: 'top top',
    end: 'bottom bottom',
    scrub: 0.5,
    onUpdate: (self) => {
      const t = self.progress;
      r.drift.rotY = Math.sin(t * Math.PI) * 0.42;
      r.drift.rotX = Math.sin(t * Math.PI * 2) * 0.05;
      r.drift.bob = Math.sin(t * Math.PI * 3) * 0.18;
      r.drift.vel = Math.max(-1, Math.min(1, self.getVelocity() / 4000));
    },
  });

  /* Abspann: Unter dem Kontakt liefe sonst nichts mehr. Jetzt zieht sich die
     Kamera langsam in die Tiefe des Gangs zurück, während der Fuß erscheint. */
  const fuss = document.querySelector('.footer');
  if (fuss) {
    ScrollTrigger.create({
      trigger: fuss,
      start: 'top bottom',
      end: 'bottom bottom',
      scrub: 0.8,
      onEnter: () => {
        /* Die Beat-Tweens laufen noch nach und würden die Werte gleich wieder
           überschreiben — deshalb erst anhalten, dann übernehmen. */
        gsap.killTweensOf(r.camGoal);
        gsap.killTweensOf(r.lookGoal);
      },
      /* NUR WENN DER FUSS WIRKLICH IM BILD IST
         ------------------------------------------------------------------
         Das hier hat einen halben Abend gekostet. Ein Trigger mit `scrub`
         ruft onUpdate auch beim Aufbau der Seite auf — mit progress 0. Ohne
         die Abfrage schrieb der Abspann damit beim Laden seine Kamera in
         camGoal und lookGoal, und zwar NACH dem Eroeffnungsflug. Die
         Startseite zeigte deshalb dauerhaft die Ruhelage des Abspanns,
         der Hero-Zustand kam nie an, und jede Aenderung an den Beats
         wirkte folgenlos — gemessen an drei Kameravarianten, die alle
         exakt dasselbe Bild ergaben.

         isActive ist hier die richtige Abfrage und nicht progress > 0:
         Beim Zurueckscrollen ueber den Fuss hinaus faellt progress ohnehin
         auf 0, und dann soll der Abspann auch nichts mehr schreiben. */
      onUpdate: (self) => {
        if (!self.isActive) return;
        const t = self.progress;
        r.camGoal.set(-3.0 + t * 3.0, 2.50 + t * 1.2, 11.0 + t * 7.0);
        r.lookGoal.set(0, 3.40, -4.6 - t * 6.0);
      },
    });
  }
}
