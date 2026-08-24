/* ==========================================================================
   Einstieg in die Erfahrung.
   Zustände: boot → loading → ready → idle ⇄ story, dazu suspended und fallback.
   Ohne WebGL, bei reduzierter Bewegung oder auf sehr schwachen Geräten läuft
   die Seite als vollständige, statische Fassung — es fehlt nichts an Inhalt.
   ========================================================================== */
import * as THREE from 'three';
import { World } from './scene.js';
import { Quality, detectLevel, supportsWebGL } from './quality.js';
import { buildStory } from './story.js';

const root = document.documentElement;
const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const canvas = document.querySelector('[data-canvas]');
const loader = document.querySelector('[data-loader]');

function setState(s) { root.setAttribute('data-state', s); }
setState('boot');

function fallback(reason) {
  root.setAttribute('data-fallback', reason);
  setState('fallback');
  if (loader) loader.hidden = true;
  if (canvas) canvas.hidden = true;
}

if (!supportsWebGL()) {
  fallback('no-webgl');
} else if (reduced) {
  fallback('reduced-motion');
} else {
  start();
}

async function start() {
  setState('loading');

  const quality = new Quality(detectLevel());
  let world;
  try {
    world = new World(canvas, quality);
  } catch (e) {
    console.warn('WebGL-Aufbau fehlgeschlagen:', e);
    fallback('init-error');
    return;
  }

  // Kontextverlust ist auf Mobilgeräten real — dann sauber in die statische
  // Fassung wechseln statt schwarz zu bleiben.
  canvas.addEventListener('webglcontextlost', (e) => { e.preventDefault(); fallback('context-lost'); });

  const { gsap } = window;
  const ScrollTrigger = window.ScrollTrigger;
  gsap.registerPlugin(ScrollTrigger);

  /* ---------- Ein Scrollsystem: Lenis treibt ScrollTrigger ---------- */
  const lenis = new window.Lenis({ duration: 1.15, smoothWheel: true, touchMultiplier: 1.4 });
  lenis.on('scroll', ScrollTrigger.update);
  gsap.ticker.add((t) => lenis.raf(t * 1000));
  gsap.ticker.lagSmoothing(0);

  await (document.fonts ? document.fonts.ready : Promise.resolve());
  const story = buildStory({ world, gsap, ScrollTrigger });

  /* ---------- Zeiger-Parallaxe, gedämpft in der Szene ---------- */
  window.addEventListener('pointermove', (e) => {
    if (e.pointerType !== 'mouse') return;
    world.setParallax(
      (e.clientX / window.innerWidth - 0.5) * 2,
      -(e.clientY / window.innerHeight - 0.5) * 2
    );
  }, { passive: true });

  /* ---------- Frame-Schleife hält im Hintergrund-Tab an ---------- */
  let running = true;
  document.addEventListener('visibilitychange', () => {
    running = !document.hidden;
    setState(running ? 'story' : 'suspended');
    if (running) requestAnimationFrame(loop);
  });

  function loop() {
    if (!running) return;
    world.render();
    requestAnimationFrame(loop);
  }

  // Erst rendern, wenn wirklich ein Bild da ist — sonst blitzt Schwarz auf.
  world.render();
  requestAnimationFrame(() => {
    if (loader) loader.hidden = true;
    setState('story');
    requestAnimationFrame(loop);
    ScrollTrigger.refresh();
  });

  /* ---------- Kapitel-Sprünge über die Punktleiste und die Tastatur ------- */
  document.querySelectorAll('.chapters__dot').forEach((dot) => {
    dot.addEventListener('click', () => {
      const t = document.getElementById(dot.dataset.target);
      if (t) lenis.scrollTo(t, { offset: 0, duration: 1.2 });
    });
  });

  /* ---------- Qualität von Hand ---------- */
  const select = document.querySelector('[data-quality]');
  if (select) {
    select.value = quality.level;
    select.addEventListener('change', () => {
      if (select.value === 'auto') { quality.locked = false; quality.set(detectLevel()); }
      else { quality.locked = true; quality.set(select.value); }
    });
    quality.onChange = ((prev) => (s, level) => {
      prev.call(world, s);
      select.value = quality.locked ? level : level;
    })(world._applyQuality.bind(world));
  }

  window.__vecomWorld = { world, quality, story, lenis, THREE };
}
