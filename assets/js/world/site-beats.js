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
  // id, Kamera, Blickpunkt, Position der Marke, Drehung, Atmosphäre, Licht,
  // Material (metal|glass|glow), Bildeffekte (rays/blur/focus), Schleier.
  /* Reihenfolge folgt der Seite. Wer einen Abschnitt verschiebt, muss die
     Zeile hier mitnehmen — sonst springt die Kamera unmotiviert. */
  { id: 'hero',     cam: [1.1, 0.8, 12.4],  look: [1.7, 0.1, 0],  pos: [3.3, 0.1, 0],  rotY: -0.52, rotX: 0.04, fog: 0.044, key: 260, spec: 26, bloom: 0.38, rough: 0.30, keyPos: [-4.5, 7.5, 6.0], halo: 0.30, mat: 'metal', rays: 0.0, blur: 0.22, focus: 0.34, scrim: 0.00 },
  { id: 'work',     cam: [-2.4, 1.1, 9.6],   look: [-1.3, 0.0, 0],  pos: [-1.8, -0.1, 0], rotY: -0.34,  rotX: -0.04, fog: 0.030, key: 330, spec: 42, bloom: 0.44, rough: 0.16, keyPos: [-5.5, 5.5, 7.5], halo: 0.16, mat: 'glass', rays: 0.0, blur: 0.32, focus: 0.24, scrim: 0.74 },
  { id: 'services', cam: [2.4, 1.0, 8.8],  look: [1.2, 0.1, 0], pos: [2.0, 0.0, 0], rotY: 0.22, rotX: 0.02, fog: 0.034, key: 420, spec: 40, bloom: 0.52, rough: 0.26, keyPos: [5.5, 6.0, 5.0], halo: 0.24, mat: 'metal', rays: 0.0, blur: 0.26, focus: 0.28, scrim: 0.62 },
  { id: 'plans',    cam: [-0.4, 0.6, 10.4], look: [-1.0, 0.0, 0], pos: [-1.6, 0.0, 0], rotY: 0.02, rotX: 0.0,  fog: 0.038, key: 300, spec: 18, bloom: 0.40, rough: 0.22, keyPos: [-4.0, 7.0, 7.0], halo: 0.22, mat: 'metal', rays: 0.0, blur: 0.28, focus: 0.26, scrim: 0.78 },
  { id: 'process',  cam: [-2.2, 0.6, 19.0], look: [-1.6, -0.2, 0], pos: [6.4, -0.4, 0], rotY: 0.36, rotX: -0.03, fog: 0.040, key: 320, spec: 26, bloom: 0.36, rough: 0.19, keyPos: [5.0, 5.5, 7.0], halo: 0.16, mat: 'glass', rays: 0.0, blur: 0.30, focus: 0.24, scrim: 0.86 },
  { id: 'about',    cam: [1.6, 0.7, 13.0], look: [1.2, 0.1, 0],  pos: [-3.2, 0.0, 0], rotY: -0.34, rotX: 0.0,  fog: 0.040, key: 300, spec: 24, bloom: 0.36, rough: 0.24, keyPos: [-5.0, 6.5, 7.0], halo: 0.18, mat: 'metal', rays: 0.0, blur: 0.28, focus: 0.26, scrim: 0.84 },
  { id: 'video',    cam: [-1.0, 0.8, 14.5], look: [-0.6, 0.1, 0], pos: [3.4, 0.0, 0],  rotY: 0.20,  rotX: 0.0,  fog: 0.040, key: 300, spec: 24, bloom: 0.38, rough: 0.24, keyPos: [4.5, 6.5, 7.0], halo: 0.18, mat: 'metal', rays: 0.0, blur: 0.28, focus: 0.26, scrim: 0.84 },
  { id: 'pillars',  cam: [1.2, 4.4, 17.5],  look: [1.5, -1.9, 0], pos: [2.0, -1.3, 0], rotY: 0.12,  rotX: 0.0,  fog: 0.036, key: 560, spec: 34, bloom: 0.50, rough: 0.22, keyPos: [-3.0, 9.5, 8.0], halo: 0.34, mat: 'glow',  rays: 0.0, blur: 0.24, focus: 0.30, scrim: 0.48 },
  { id: 'partner',  cam: [-1.4, 1.4, 14.0], look: [-1.4, 0.2, 0], pos: [-2.6, 0.2, 0], rotY: -0.40, rotX: 0.02, fog: 0.040, key: 300, spec: 22, bloom: 0.36, rough: 0.26, keyPos: [-5.0, 7.0, 6.5], halo: 0.18, mat: 'metal', rays: 0.0, blur: 0.30, focus: 0.26, scrim: 0.80 },
  { id: 'faq',      cam: [2.4, 0.9, 12.6],  look: [1.6, 0.1, 0],  pos: [2.4, 0.0, 0],  rotY: 0.44,  rotX: 0.02, fog: 0.038, key: 300, spec: 24, bloom: 0.40, rough: 0.24, keyPos: [4.5, 6.5, 7.0], halo: 0.20, mat: 'metal', rays: 0.0, blur: 0.22, focus: 0.24, scrim: 0.80 },
  { id: 'contact',  cam: [0.6, 1.7, 16.0],  look: [0.4, -0.9, 0], pos: [0.2, -1.9, 0], rotY: -0.18, rotX: 0.0,  fog: 0.042, key: 420, spec: 22, bloom: 0.40, rough: 0.20, keyPos: [-3.2, 7.0, 8.0], halo: 0.30, mat: 'glow',  rays: 0.0, blur: 0.22, focus: 0.32, scrim: 0.78 },
];

/* Der Eröffnungsflug. Startpunkt weit außerhalb des Nebels; von hier fährt die
   Kamera beim Laden in 3,4 s auf den Hero-Zustand zu. Nur einmal, nie wieder. */
export const OPENING = {
  cam: [7.5, 3.4, 34.0], look: [0.9, 0.4, 0], pos: [3.3, 0.1, 0],
  rotY: -1.35, rotX: 0.16, fog: 0.115, key: 60, spec: 8, bloom: 0.22, rough: 0.42,
  keyPos: [-6.5, 9.0, 4.0], halo: 0.12, mat: 'metal', rays: 0.0, blur: 0.45, focus: 0.10, scrim: 0.0,
};

/* Bindet die Zustände an die Abschnitte. Kein Scrub, sondern ein gleitender
   Wechsel beim Betreten — die Kamera „fährt nach", statt am Rad zu kleben. */
export function bindSiteBeats({ world, gsap, ScrollTrigger }) {
  const w = world;
  const narrow = window.matchMedia('(max-width: 860px)').matches;
  const k = narrow ? 0.12 : 1;
  const zk = narrow ? 1.7 : 1;

  /* Ab 900px trennt die Komposition: Text links (.hero__lead bekommt dort eine
     max-width), Marke rechts. Darunter gibt es kein Rechts — der Text läuft
     über die volle Breite und die Marke steht mitten dahinter. Gemessen lagen
     dadurch 17,9 % der Fließtextfläche auf dem Handy unter 4.5:1, an der
     hellsten Stelle 2.4:1. Deshalb tritt die Marke genau dort zurück, wo die
     Spaltentrennung nicht greift: weniger Licht, kaum Glanzlichter, mehr Nebel
     und derselbe Schleier, den jeder andere Abschnitt ohnehin benutzt. Sie
     bleibt sichtbar — sie hört nur auf, mit dem Text um dieselbe Fläche zu
     streiten. Der Bruch liegt bewusst auf demselben Wert wie in app.css; wer
     den einen ändert, muss den anderen mitziehen. */
  const heroGeteilt = window.matchMedia('(min-width: 900px)').matches;
  const HERO_GEDRAENGT = { fog: 0.088, key: 125, spec: 3, bloom: 0.09, halo: 0.16, scrim: 0.60 };
  const BEATS = SITE_BEATS.map((b, i) => (!heroGeteilt && i === 0 ? { ...b, ...HERO_GEDRAENGT } : b));
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

    // Materialwechsel als Dramaturgie: gebürstetes Metall → Glas → glühende Kante.
    const M = w.constructor.MATERIALS[b.mat] || w.constructor.MATERIALS.metal;
    gsap.to(w.mat, {
      metalness: M.metalness, transmission: M.transmission, ior: M.ior,
      thickness: M.thickness, clearcoatRoughness: M.clearcoatRoughness,
      iridescence: M.iridescence, emissiveIntensity: M.emissiveIntensity,
      envMapIntensity: M.envMapIntensity,
      duration: d * 1.2, ease: e, overwrite: 'auto',
    });

    if (w.finish) {
      const u = w.finish.uniforms;
      gsap.to(u.uRays,  { value: b.rays,  duration: d, ease: e, overwrite: true });
      gsap.to(u.uBlur,  { value: b.blur,  duration: d, ease: e, overwrite: true });
      gsap.to(u.uFocus, { value: b.focus, duration: d, ease: e, overwrite: true });
    }
    gsap.to(w.contact.material, { opacity: b.id === 'pillars' ? 0.42 : 0.16, duration: d, ease: e, overwrite: true });
    gsap.to(w.halo.material, {
      opacity: b.halo, duration: d, ease: e, overwrite: true,
      onUpdate: () => { w.halo.visible = w.halo.material.opacity > 0.08; },
    });
    // Filmischer Schnitt: ein kurzer Lichtschleier kaschiert den Wechsel.
    if (!instant) {
      root.setAttribute('data-cut', '1');
      gsap.delayedCall(0.6, () => root.removeAttribute('data-cut'));
    }

    // Der Schleier dunkelt die Welt ab, sobald Text gelesen werden soll.
    gsap.to(root, { '--world-scrim': b.scrim, duration: d * 0.8, ease: e, overwrite: true });
  };

  /* Eröffnungsflug: einmal beim Laden von weit außen an die Marke heran.
     Danach übernimmt das Scrollen. */
  applyTween(OPENING, true);

  /* Aufbau der Bühne: Die Marke materialisiert sich, statt einfach da zu sein.
     Zwei Sekunden, die den Ton setzen. */
  w.logo.scale.setScalar(0.55);
  gsap.to(w.logo.scale, { x: 1.75, y: 1.75, z: 1.75, duration: 2.2, ease: 'expo.out', delay: 0.15 });
  gsap.fromTo(w.mat, { emissiveIntensity: 1.4 }, { emissiveIntensity: 0, duration: 1.9, ease: 'power2.out', delay: 0.15 });

  /* --------------------------------------------------------------------
     Der Auftakt. Die Marke fährt heran, zieht an, zerspringt — und setzt
     sich wieder zusammen, während die Kamera auf dem Hero landet.

     Das Zusammensetzen ist kein Schmuck, sondern die Lösung eines Problems:
     dieselbe Marke fliegt danach durch die ganze Seite. Ein Auftakt, der sie
     zerstört und liegen lässt, hätte kein Ende, sondern ein Loch.

     Der Inhalt der Seite wird nie verdeckt. Ein Vorhang vor dem Text kostet
     genau die Besucher, die auf einem alten Telefon in der Sonne stehen.
     -------------------------------------------------------------------- */
  const bruchZurueck = () => {
    if (!w.bruchMesh) return;
    w.bruchMesh.visible = false;
    if (w.logoLeft) w.logoLeft.visible = true;
    if (w.logoRight) w.logoRight.visible = true;
    if (w.bruchU) { w.bruchU.uBruch.value = 0; w.bruchU.uGlut.value = 0; }
    w.drift.auftaktRot = 0;
  };

  const opening = gsap.timeline();
  opening.call(() => applyOpening(), null, 0.25);

  if (w.bruchMesh && w.bruchU) {
    const U = w.bruchU;
    opening
      /* Anziehen. Eigener Griff, nicht extraRot: den setzt der Schluss-Trigger
         direkt, und er wuerde die Auftaktdrehung stumm ueberschreiben. */
      .fromTo(w.drift, { auftaktRot: 0 }, { auftaktRot: Math.PI * 5.0, duration: 1.00, ease: 'power3.in' }, 0.55)
      /* auf den Bruchkörper umschalten; bei uBruch = 0 deckungsgleich, kein Sprung */
      .call(() => {
        w.bruchMat.emissiveIntensity = w.mat.emissiveIntensity;
        w.bruchMesh.visible = true;
        if (w.logoLeft) w.logoLeft.visible = false;
        if (w.logoRight) w.logoRight.visible = false;
      }, null, 1.50)
      /* Der Bruch. Er lief vorher in 0,62 s bis zum Anschlag — dabei waren die
         Brocken nach drei Zehntelsekunden aus dem Bild und der Bruch war weg,
         bevor man ihn gesehen hatte. Jetzt: 0,9 s auseinander, ein halber
         Takt Halt auf dem Hoehepunkt, dann zurueck. */
      .fromTo(U.uGlut, { value: 1 }, { value: 0, duration: 1.30, ease: 'power2.out' }, 1.60)
      .fromTo(U.uBruch, { value: 0 }, { value: 0.62, duration: 0.90, ease: 'power2.out' }, 1.60)
      .to(U.uBruch, { value: 0.74, duration: 0.50, ease: 'none' }, 2.50)
      .to(w.bloom, { strength: 1.25, duration: 0.16, ease: 'power2.out' }, 1.60)
      .to(w.bloom, { strength: BEATS[0].bloom, duration: 1.3, ease: 'power2.out' }, 1.85)
      /* Kamera weicht im Bruch etwas zurueck, damit die Truemmer ins Bild passen */
      .to(w.camGoal, { z: '+=2.2', duration: 0.75, ease: 'power2.out' }, 1.60)
      .to(w.camGoal, { z: '-=2.2', duration: 1.30, ease: 'power2.inOut' }, 2.95)
      /* Drall läuft aus, Brocken kehren zurück */
      .to(w.drift, { auftaktRot: 0, duration: 1.8, ease: 'power2.out' }, 1.60)
      .to(U.uBruch, { value: 0, duration: 1.15, ease: 'power2.inOut' }, 3.00)
      .call(bruchZurueck, null, 4.15)
      /* Ein Atemzug auf der wieder ganzen Marke — ein Schluss braucht ein Bild,
         auf dem er stehen bleibt, sonst wirkt er abgeschnitten. */
      .to({}, { duration: 0.40 }, 4.15)
      /* Erst jetzt oeffnet die Seite. */
      .call(function () { window.dispatchEvent(new Event('vecom:auftakt-bruch')); }, null, 4.55);
  }
  function applyOpening() {
    const b = BEATS[0];
    const d = 3.4;
    const e = 'power3.out';
    gsap.to(w.camGoal,  { x: b.cam[0] * k, y: b.cam[1], z: b.cam[2] * zk, duration: d, ease: e, overwrite: true });
    gsap.to(w.lookGoal, { x: b.look[0] * k, y: b.look[1], z: b.look[2], duration: d, ease: e, overwrite: true });
    gsap.to(w.logoRig.rotation, { x: b.rotX, y: b.rotY, duration: d * 1.1, ease: e, overwrite: true });
    gsap.to(w.scene.fog, { density: b.fog, duration: d, ease: e, overwrite: true });
    gsap.to(w.key, { intensity: b.key, duration: d * 0.8, ease: e, overwrite: true });
    gsap.to(w.key.position, { x: b.keyPos[0], y: b.keyPos[1], z: b.keyPos[2], duration: d, ease: e, overwrite: true });
    gsap.to(w.spec, { intensity: b.spec, duration: d, ease: e, overwrite: true });
    gsap.to(w.bloom, { strength: b.bloom, duration: d, ease: e, overwrite: true });
    gsap.to(w.halo.material, { opacity: b.halo, duration: d, ease: e, overwrite: true });
    if (w.finish) {
      gsap.to(w.finish.uniforms.uRays,  { value: b.rays,  duration: d, ease: e, overwrite: true });
      gsap.to(w.finish.uniforms.uBlur,  { value: b.blur,  duration: d, ease: e, overwrite: true });
      gsap.to(w.finish.uniforms.uFocus, { value: b.focus, duration: d, ease: e, overwrite: true });
    }
    // Der Eröffnungsflug muss den Schleier mitziehen, sonst bliebe der Hero im
    // Hochformat ungeschleiert, bis man einmal weg- und zurückgescrollt hat.
    gsap.to(root, { '--world-scrim': b.scrim, duration: d * 0.7, ease: e, overwrite: true });
    document.documentElement.setAttribute('data-opening', 'done');
  }
  // Wer sofort scrollt, will die Show nicht — dann sofort in den Hero-Zustand.
  window.addEventListener('wheel', () => {
    if (opening.progress() < 1) { opening.kill(); bruchZurueck(); applyOpening(); }
  }, { once: true, passive: true });

  BEATS.forEach((b, i) => {
    const el = i === 0 ? document.querySelector('.hero') : document.getElementById(b.id);
    if (!el) return;
    // Der Hero-Zustand kommt beim Laden aus dem Eröffnungsflug; der Trigger
    // greift erst, wenn man später zurückscrollt.
    ScrollTrigger.create({
      trigger: el,
      start: 'top 62%',
      end: 'bottom 38%',
      onEnter: () => { if (i > 0 || opening.progress() >= 1) applyTween(b); },
      onEnterBack: () => { if (i > 0 || opening.progress() >= 1) applyTween(b); },
    });
  });

  /* --------------------------------------------------------------------
     Dauerbewegung über die gesamte Seite. Die Beats setzen Zielpunkte, aber
     zwischen zwei Abschnitten stand die Marke bisher still — besonders im
     langen Hero und im Bereich unter dem Kontakt. Diese Spur läuft durchgehend
     mit dem Scrollbalken und hört nie auf.
     -------------------------------------------------------------------- */
  ScrollTrigger.create({
    trigger: document.body,
    start: 'top top',
    end: 'bottom bottom',
    scrub: 0.5,
    onUpdate: (self) => {
      const t = self.progress;
      // Genau eine volle Umdrehung über die ganze Seite: Am Ende steht die
      // Marke wieder frontal wie beim Eintreten.
      w.drift.rotY = t * Math.PI * 2;
      w.drift.rotX = Math.sin(t * Math.PI * 2) * 0.12;
      w.drift.bob = Math.sin(t * Math.PI * 3) * 0.25;
      w.drift.vel = Math.max(-1, Math.min(1, self.getVelocity() / 4000));
    },
  });

  /* Abspann: Unter dem Kontakt lief nichts mehr. Jetzt zieht sich die Marke
     langsam in die Tiefe zurück, während der Fuß erscheint. */
  const footer = document.querySelector('.footer');
  if (footer) {
    ScrollTrigger.create({
      trigger: footer,
      start: 'top bottom',        // sobald der Fuß ins Bild kommt
      end: 'bottom bottom',
      scrub: 0.8,
      onEnter: () => {
        // Die Beat-Tweens laufen noch nach und würden die Werte hier gleich
        // wieder überschreiben — deshalb erst anhalten, dann übernehmen.
        gsap.killTweensOf([w.mat, w.bloom, w.spec, w.halo.material]);
      },
      onUpdate: (self) => {
        const t = self.progress;
        w.camGoal.z = (16.0 + t * 12) * zk;
        w.camGoal.y = 1.7 + t * 2.6;
        w.lookGoal.y = -0.9 - t * 1.2;

        // Finale: Auf den letzten Metern zieht die Drehung noch einmal an und
        // die Marke leuchtet von innen auf — ein Schlussbild statt Auslaufen.
        w.drift.extraRot = t * Math.PI * 1.4;
        w.mat.emissiveIntensity = t * 1.3;
        w.bloom.strength = 0.40 + t * 0.55;
        w.spec.intensity = 20 + t * 45;
        w.halo.material.opacity = 0.26 + t * 0.34;
        w.halo.visible = true;
      },
      onLeaveBack: () => {
        w.drift.extraRot = 0;
        w.mat.emissiveIntensity = 0;
      },
    });
  }

  /* --------------------------------------------------------------------
     Angeheftetes Kapitel: Der Ablauf bleibt stehen, während die Kamera um die
     Marke fährt und die vier Schritte nacheinander in den Vordergrund treten.
     Das ist der Unterschied zwischen „Hintergrund bewegt sich" und wirklichem
     Scrollytelling — man bleibt an einer Stelle und die Szene erzählt weiter.
     -------------------------------------------------------------------- */
  const process = document.getElementById('process');
  const steps = process ? [...process.querySelectorAll('.step')] : [];
  if (process && steps.length && !narrow) {
    const from = SITE_BEATS.find((b) => b.id === 'process');
    const to = { cam: [-1.4, 1.4, 17.0], look: [-1.0, -0.6, 0], rotY: -0.30, pos: [6.4, -0.4, 0] };

    ScrollTrigger.create({
      trigger: process,
      start: 'top top',
      end: () => '+=' + Math.round(window.innerHeight * 1.6),
      pin: true,
      pinSpacing: true,
      scrub: 0.8,
      invalidateOnRefresh: true,
      onUpdate: (self) => {
        const t = self.progress;
        // Kamera fährt über die gesamte Strecke einmal um die Marke herum.
        w.camGoal.set(
          gsap.utils.interpolate(from.cam[0], to.cam[0], t) * k,
          gsap.utils.interpolate(from.cam[1], to.cam[1], t),
          gsap.utils.interpolate(from.cam[2], to.cam[2], t) * zk
        );
        w.lookGoal.set(
          gsap.utils.interpolate(from.look[0], to.look[0], t) * k,
          gsap.utils.interpolate(from.look[1], to.look[1], t), 0
        );
        // Auch die Position gehört hierher: Durch das Anheften läuft der
        // normale Abschnitts-Trigger nie durch, der Beat wird also nie
        // angewandt — die Marke stand deshalb weiter mitten im Text.
        w.logoRig.position.set(
          gsap.utils.interpolate(from.pos[0], to.pos[0], t) * k,
          gsap.utils.interpolate(from.pos[1], to.pos[1], t),
          0
        );
        w.logoRig.rotation.y = gsap.utils.interpolate(from.rotY, to.rotY, t);

        // Die beiden Schenkel gehen auseinander und wieder zusammen — das Motiv
        // erzählt „ein Zeichen entsteht durch das Weggelassene" ohne Worte.
        const split = Math.sin(t * Math.PI) * 0.55;      // 0 → max → 0
        w.logoLeft.position.x = -split;
        w.logoRight.position.x = split;
        w.gap.intensity = split * 26;

        // Schritt für Schritt: immer genau einer im Vordergrund.
        const active = Math.min(steps.length - 1, Math.floor(t * steps.length));
        steps.forEach((el, i) => el.classList.toggle('is-active', i === active));
      },
      onLeaveBack: () => {
        steps.forEach((el) => el.classList.remove('is-active'));
        w.logoLeft.position.x = 0; w.logoRight.position.x = 0; w.gap.intensity = 0;
      },
      onLeave: () => {
        w.logoLeft.position.x = 0; w.logoRight.position.x = 0; w.gap.intensity = 0;
      },
    });
  }

  return { applyTween, beats: SITE_BEATS };
}
