/* ==========================================================================
   signatur.js — Der Signature Moment.

   Licht wird zu Punkten, die Punkte finden ihre Form, die Form ist die
   Bildmarke. Darüber und darunter zwei Sätze: „Ideen werden sichtbar." /
   „Marken werden Erlebnisse."

   ZWEI ENTSCHEIDUNGEN, BEIDE MIT GRUND

   1. 2D-Leinwand, nicht WebGL. Auf dieser Seite laufen schon zwei
      Grafikkontexte — die Welt hinter dem Inhalt und, sobald jemand
      hinscrollt, die Werkbank oder das Haus. Ein dritter nur für ein paar
      tausend Punkte wäre der teuerste Weg zum billigsten Bild und auf dem
      Telefon der, der einen der beiden anderen abräumt. 2D-Canvas kostet
      hier nichts und läuft überall, auch ohne WebGL.

   2. Die Umrisse kommen aus `world/logo-shape.js` — denselben Konturen,
      aus denen die 3D-Bildmarke der Startseite entsteht. Kein nachgebautes
      „V": Wenn die Marke sich zusammensetzt, setzt sie sich zur echten
      Marke zusammen, bis auf den Punkt.

   Läuft genau einmal, wenn der Abschnitt ins Bild kommt, und hört danach
   auf zu rechnen. Bei „weniger Bewegung" steht das Bild sofort fertig da.
   ========================================================================== */
import { LOGO_CONTOURS } from './world/logo-shape.js';

const wurzel = document.querySelector('[data-signatur]');
const leinwand = wurzel && wurzel.querySelector('[data-signatur-leinwand]');
const g = leinwand && leinwand.getContext('2d');

if (g) {
  const ruhig = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const DAUER = 2600;
  /* Weniger Punkte auf schmalen Geräten. Die Zahl steht hier und nicht in
     quality.js: Das ist keine 3D-Szene, sie belegt kein Budget dort. */
  const ANZAHL = window.innerWidth < 720 ? 1400 : 3400;

  let b = 0, h = 0, dpr = 1;
  let punkte = [];
  let anfang = 0, laeuft = false, fertig = false;

  function messen() {
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    /* Die eigene Box messen, nicht den Abschnitt: Die Leinwand sitzt in
       der mittleren Rasterzeile zwischen den beiden Saetzen. */
    b = leinwand.clientWidth;
    h = leinwand.clientHeight;
    if (!b || !h) return;
    leinwand.width = Math.round(b * dpr);
    leinwand.height = Math.round(h * dpr);
    g.setTransform(dpr, 0, 0, dpr, 0, 0);
    zieleRechnen();
    zeichnen(fertig ? 1 : 0);
  }

  /* Punkte gleichmäßig INNERHALB der Marke verteilen: Kandidat würfeln,
     behalten, wenn er drin liegt. Nur der Umriss wäre eine Linie aus
     Punkten — das sieht nach Konfetti aus, nicht nach Körper. */
  function zieleRechnen() {
    const mass = Math.min(b * 0.34, h * 0.80);
    const mx = b / 2, my = h * 0.50;

    const pfad = new Path2D();
    for (const kontur of LOGO_CONTOURS) {
      kontur.forEach(([x, y], i) => {
        const px = mx + x * mass * 0.5;
        const py = my - y * mass * 0.5;
        if (i === 0) pfad.moveTo(px, py); else pfad.lineTo(px, py);
      });
      pfad.closePath();
    }

    /* WICHTIG: isPointInPath rechnet in GERÄTEPIXELN, die Punkte liegen in
       CSS-Pixeln. Ohne die Umrechnung trifft auf einem 2×-Schirm kein
       einziger Kandidat, und die Marke bleibt leer. */
    const ziele = [];
    let versuche = 0;
    while (ziele.length < ANZAHL && versuche < ANZAHL * 90) {
      versuche++;
      const x = mx + (Math.random() - 0.5) * mass * 1.6;
      const y = my + (Math.random() - 0.5) * mass * 1.3;
      if (g.isPointInPath(pfad, x * dpr, y * dpr)) ziele.push([x, y]);
    }

    punkte = ziele.map(([zx, zy]) => ({
      zx, zy,
      x: b / 2 + (Math.random() - 0.5) * b * 1.35,
      y: h / 2 + (Math.random() - 0.5) * h * 1.9,
      v: 0.55 + Math.random() * 0.45,
      t: Math.random() * 0.34,   /* Versatz, damit sie nicht im Gleichschritt landen */
    }));
  }

  function zeichnen(k) {
    if (!b || !h) return;
    g.clearRect(0, 0, b, h);

    /* Der Lichtkern, aus dem alles kommt. Er verlischt, sobald die Form
       steht — sonst überstrahlt er sie. */
    const kern = Math.max(0, 1 - k * 1.6);
    if (kern > 0.01) {
      const r = g.createRadialGradient(b / 2, h * 0.5, 0, b / 2, h * 0.5, Math.min(b, h) * 0.5);
      r.addColorStop(0, 'rgba(241, 211, 139, ' + (0.36 * kern) + ')');
      r.addColorStop(0.45, 'rgba(200, 150, 62, ' + (0.13 * kern) + ')');
      r.addColorStop(1, 'rgba(200, 150, 62, 0)');
      g.fillStyle = r;
      g.fillRect(0, 0, b, h);
    }

    g.globalCompositeOperation = 'lighter';
    for (const p of punkte) {
      const roh = Math.max(0, Math.min(1, (k - p.t) / (1 - p.t)));
      const e = 1 - Math.pow(1 - roh, 3);
      const x = p.x + (p.zx - p.x) * e;
      const y = p.y + (p.zy - p.y) * e;
      /* Weit weg klein und blau, am Ziel hell und weiß — der Weg selbst
         erzählt, dass aus Streulicht eine Form wird. */
      const s = 0.7 + e * 1.15;
      g.fillStyle = e > 0.94
        ? 'rgba(253, 248, 235, ' + (0.55 + 0.4 * p.v) + ')'
        : 'rgba(241, 211, 139, ' + (0.18 + 0.45 * e) + ')';
      g.fillRect(x - s / 2, y - s / 2, s, s);
    }
    g.globalCompositeOperation = 'source-over';
  }

  function bild(jetzt) {
    const k = Math.min(1, (jetzt - anfang) / DAUER);
    zeichnen(k);
    if (k < 1) { requestAnimationFrame(bild); }
    else { laeuft = false; fertig = true; wurzel.setAttribute('data-signatur-fertig', ''); }
  }

  function los() {
    if (laeuft || fertig) return;
    if (ruhig) { fertig = true; zeichnen(1); wurzel.setAttribute('data-signatur-fertig', ''); return; }
    laeuft = true;
    anfang = performance.now();
    requestAnimationFrame(bild);
  }

  messen();
  window.addEventListener('resize', messen, { passive: true });

  if ('IntersectionObserver' in window) {
    const beob = new IntersectionObserver((e) => {
      if (e[0].isIntersecting) { los(); beob.disconnect(); }
    }, { threshold: 0.4 });
    beob.observe(wurzel);
  } else {
    los();
  }
}
