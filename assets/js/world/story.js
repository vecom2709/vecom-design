/* ==========================================================================
   Drehbuch. Scroll ist der Playhead einer durchgehenden Timeline, nicht eine
   Aneinanderreihung von Sektionen. Ein Hauptereignis pro Beat, dazwischen Ruhe.

   BEAT POS   AUSSAGE              KAMERA                LICHT
   1    0.00  Ankunft im Dunkeln   weit, tief, statisch  nur Kante
   2    0.20  Das Licht kommt      Dolly in, seitlich    Key fährt hoch
   3    0.40  Das Material         Orbit +40°            Reflexe, Klarlack
   4    0.60  Der Schnitt          Macro auf die Kerbe   hartes Kantenlicht
   5    0.80  Der Raum             Kranfahrt zurück      Bodenspiegelung
   6    1.00  Ruhelage             frontal, Augenhöhe    ausgeglichen
   ========================================================================== */

/* pos = Standort der Marke im Raum. Sie weicht dem Text aus: Text links →
   Marke rechts und umgekehrt. Auf schmalen Bildschirmen wird der Versatz in
   buildStory() halbiert, sonst schneidet sie an. */
export const BEATS = [
  { id: 'arrival',  cam: [0.0, 0.7, 13.5], look: [0.9, 0.1, 0],  pos: [2.1, 0.1, 0],  rotY: -0.62, rotX: 0.05, fog: 0.052, key: 150, spec: 20, bloom: 0.34, rough: 0.36, keyPos: [-4.5, 7.5, 6.0], halo: 0.30 },
  { id: 'light',    cam: [-2.4, 1.0, 8.4], look: [-1.0, 0.1, 0], pos: [-1.9, 0.0, 0], rotY: -0.26, rotX: 0.02, fog: 0.034, key: 420, spec: 40, bloom: 0.58, rough: 0.28, keyPos: [-5.5, 6.0, 5.0], halo: 0.24 },
  { id: 'material', cam: [2.7, 1.1, 9.6],  look: [1.4, 0.0, 0],  pos: [1.7, -0.1, 0], rotY: 0.34,  rotX: -0.05, fog: 0.030, key: 330, spec: 42, bloom: 0.46, rough: 0.15, keyPos: [5.5, 5.5, 7.5], halo: 0.16 },
  { id: 'cut',      cam: [-1.9, -0.1, 6.6], look: [-1.6, 0.25, 0], pos: [-1.7, 0.1, 0], rotY: 0.36, rotX: -0.03, fog: 0.028, key: 280, spec: 26, bloom: 0.44, rough: 0.19, keyPos: [-5.0, 4.5, 6.5], halo: 0.12 },
  { id: 'space',    cam: [1.2, 4.4, 17.5], look: [1.5, -1.9, 0], pos: [2.0, -1.3, 0], rotY: 0.12,  rotX: 0.0,  fog: 0.036, key: 560, spec: 34, bloom: 0.56, rough: 0.22, keyPos: [-3.0, 9.5, 8.0], halo: 0.34 },
  { id: 'rest',     cam: [0.0, 0.35, 8.6], look: [-0.7, 0.0, 0], pos: [-1.1, -0.1, 0], rotY: 0.0,  rotX: 0.0,  fog: 0.036, key: 290, spec: 16, bloom: 0.46, rough: 0.22, keyPos: [-4.0, 7.0, 7.0], halo: 0.22 },
];

export function buildStory({ world, gsap, ScrollTrigger }) {
  const { camGoal, lookGoal, logoRig, mat, key, spec, scene, bloom, halo, contact } = {
    camGoal: world.camGoal, lookGoal: world.lookGoal, logoRig: world.logoRig,
    mat: world.mat, key: world.key, spec: world.spec, scene: world.scene,
    bloom: world.bloom, halo: world.halo, contact: world.contact,
  };

  // Auf schmalen Bildschirmen ist seitlicher Versatz keine Komposition, sondern
  // ein Anschnitt — dort rückt die Marke in die Mitte und der Text darüber.
  const narrow = window.matchMedia('(max-width: 860px)').matches;
  const k = narrow ? 0.12 : 1;

  const set = (b) => {
    camGoal.set(b.cam[0] * k, b.cam[1], b.cam[2] * (narrow ? 1.75 : 1));
    lookGoal.set(b.look[0] * k, b.look[1], b.look[2]);
    logoRig.position.set(b.pos[0] * k, b.pos[1], b.pos[2]);
    logoRig.rotation.y = b.rotY;
    logoRig.rotation.x = b.rotX;
    scene.fog.density = b.fog;
    key.intensity = b.key;
    key.position.set(b.keyPos[0], b.keyPos[1], b.keyPos[2]);
    spec.intensity = b.spec;
    bloom.strength = b.bloom;
    mat.roughness = b.rough;
    contact.material.opacity = b.id === 'space' ? 0.42 : 0.16;
    halo.material.opacity = b.halo;
    halo.visible = b.halo > 0.08;   // in Nahaufnahmen ganz weg, sonst blüht er das Bild aus
  };
  set(BEATS[0]);

  const story = document.querySelector('[data-story]');
  if (!story) return null;

  const tl = gsap.timeline({
    defaults: { ease: 'none' },
    scrollTrigger: {
      trigger: story,
      start: 'top top',
      // Wichtig: die Scrollstrecke endet, wenn das letzte Kapitel oben steht —
      // sonst liegen Beat-Positionen und Kapitel um ein Sechstel versetzt und
      // man sieht Beat 5 nie.
      end: () => '+=' + (story.offsetHeight - window.innerHeight),
      scrub: 0.9,
      invalidateOnRefresh: true,
    },
  });

  // Zwischen je zwei Beats ein Segment. Kamera und Licht laufen synchron,
  // die Marke dreht sich mit — eine Wahrheit, keine konkurrierenden Systeme.
  for (let i = 1; i < BEATS.length; i++) {
    const b = BEATS[i];
    // Startzeit in Timeline-Sekunden, nicht als Anteil: bei Dauer 1 je Segment
    // ergibt das eine Gesamtdauer von 5 — und Beat i liegt exakt auf
    // Scroll-Fortschritt (i-1)/5, also genau auf Kapitel i.
    const pos = i - 1;
    tl.to(camGoal,  { x: b.cam[0] * k, y: b.cam[1], z: b.cam[2] * (narrow ? 1.75 : 1), duration: 1 }, pos)
      .to(lookGoal, { x: b.look[0] * k, y: b.look[1], z: b.look[2], duration: 1 }, pos)
      .to(logoRig.position, { x: b.pos[0] * k, y: b.pos[1], z: b.pos[2], duration: 1 }, pos)
      .to(logoRig.rotation, { y: b.rotY, x: b.rotX, duration: 1 }, pos)
      .to(scene.fog, { density: b.fog, duration: 1 }, pos)
      .to(key, { intensity: b.key, duration: 1 }, pos)
      .to(key.position, { x: b.keyPos[0], y: b.keyPos[1], z: b.keyPos[2], duration: 1 }, pos)
      .to(spec, { intensity: b.spec, duration: 1 }, pos)
      .to(contact.material, { opacity: b.id === 'space' ? 0.42 : 0.16, duration: 1 }, pos)
      .to(bloom, { strength: b.bloom, duration: 1 }, pos)
      .to(mat, { roughness: b.rough, duration: 1 }, pos)
      .to(halo.material, { opacity: b.halo, duration: 1,
            onUpdate: () => { halo.visible = halo.material.opacity > 0.08; } }, pos);
  }

  /* Kapitel: Text erscheint, wenn sein Beat im Bild ist. Der Inhalt steht
     vollständig im DOM — die Inszenierung ist nur die Schicht darüber. */
  const chapters = gsap.utils.toArray('.chapter');
  const dots = gsap.utils.toArray('.chapters__dot');
  chapters.forEach((el, i) => {
    gsap.timeline({
      scrollTrigger: {
        trigger: el, start: 'top 78%', end: 'bottom 32%',
        toggleActions: 'play reverse play reverse',
        onToggle: ({ isActive }) => {
          if (!isActive || !dots[i]) return;
          dots.forEach((d) => d.removeAttribute('aria-current'));
          dots[i].setAttribute('aria-current', 'step');
        },
      },
    })
      .fromTo(el.querySelectorAll('.chapter__line > span'),
        { yPercent: 108 }, { yPercent: 0, duration: 0.9, stagger: 0.07, ease: 'expo.out' })
      .fromTo(el.querySelectorAll('.chapter__body, .chapter__meta'),
        { opacity: 0, y: 18 }, { opacity: 1, y: 0, duration: 0.6, stagger: 0.08, ease: 'expo.out' }, 0.12);
  });

  return { timeline: tl, setBeat: set };
}
