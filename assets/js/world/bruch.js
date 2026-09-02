/* ==========================================================================
   BRUCH — die Marke zerspringt, und setzt sich wieder zusammen.

   Gebaut aus denselben Konturen wie der heile Körper (logo-shape.js), damit
   im Moment des Bruchs nichts springt: solange uBruch = 0 ist, steht hier
   exakt dasselbe Volumen wie sonst.

   Warum nicht einfach die Rendering-Dreiecke auseinanderfliegen lassen: die
   sind papierdünn. Fliegende Dreiecke sehen aus wie Konfetti, nicht wie
   gebrochenes Metall. Deshalb wird die Fläche vorher in Voronoi-Zellen
   zerlegt und jede Zelle auf volle Materialstärke gezogen — Brocken mit
   Volumen, die man beim Taumeln von der Seite sieht.
   ========================================================================== */
import * as THREE from 'three';
import { LOGO_CONTOURS } from './logo-shape.js';

/* ---------- Hilfen: Polygone ---------- */
function imPolygon(x, y, poly) {
  let d = false;
  for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) {
    const a = poly[i], b = poly[j];
    if (((a[1] > y) !== (b[1] > y)) && (x < (b[0] - a[0]) * (y - a[1]) / (b[1] - a[1]) + a[0])) d = !d;
  }
  return d;
}
/* Halbebene schneiden (Sutherland–Hodgman); behält nx*x + ny*y <= c */
function schneide(poly, nx, ny, c) {
  const out = [];
  for (let i = 0; i < poly.length; i++) {
    const A = poly[i], B = poly[(i + 1) % poly.length];
    const dA = nx * A[0] + ny * A[1] - c, dB = nx * B[0] + ny * B[1] - c;
    if (dA <= 0) out.push(A);
    if ((dA < 0 && dB > 0) || (dA > 0 && dB < 0)) {
      const t = dA / (dA - dB);
      out.push([A[0] + (B[0] - A[0]) * t, A[1] + (B[1] - A[1]) * t]);
    }
  }
  return out;
}
function flaeche(poly) {
  let a = 0;
  for (let i = 0; i < poly.length; i++) { const j = (i + 1) % poly.length;
    a += poly[i][0] * poly[j][1] - poly[j][0] * poly[i][1]; }
  return a / 2;
}
function aufKontur(mx, my, konturen, eps) {
  for (const poly of konturen) {
    for (let i = 0; i < poly.length; i++) {
      const a = poly[i], b = poly[(i + 1) % poly.length];
      const vx = b[0] - a[0], vy = b[1] - a[1], L2 = vx * vx + vy * vy || 1;
      const t = Math.max(0, Math.min(1, ((mx - a[0]) * vx + (my - a[1]) * vy) / L2));
      if (Math.hypot(mx - (a[0] + vx * t), my - (a[1] + vy * t)) < eps) return true;
    }
  }
  return false;
}
function gauss() {
  const u = 1 - Math.random(), v = Math.random();
  return Math.sqrt(-2 * Math.log(u)) * Math.cos(6.283185307 * v);
}

/* --------------------------------------------------------------------------
   Erzeugt EINE BufferGeometry aus allen Bruchstücken.
   Zusatzattribute je Vertex:
     aMit   — Mittelpunkt des Bruchstücks (Drehachse und Flugrichtung)
     aZuf   — Zufallswerte, je Stück gleich (Tempo, Achse, Drall)
     aInnen — 1 auf Schnittflächen, 0 auf der ursprünglichen Oberfläche.
              Nur die Schnittflächen glühen: frischer Bruch ist heiß und matt.
   -------------------------------------------------------------------------- */
export function bruchGeometrie({ tiefe = 0.34, mitte = new THREE.Vector3(), zellen = 30 } = {}) {
  const pos = [], nor = [], amit = [], azuf = [], ainn = [];

  LOGO_CONTOURS.forEach((kontur) => {
    /* Fächer-Triangulation liefert konvexe Stücke — nur die lassen sich
       zuverlässig an Mittelsenkrechten schneiden. */
    const shape = new THREE.Shape();
    kontur.forEach(([x, y], i) => (i ? shape.lineTo(x, y) : shape.moveTo(x, y)));
    const tris = THREE.ShapeUtils.triangulateShape(
      kontur.map(([x, y]) => new THREE.Vector2(x, y)), []
    );

    /* Saatpunkte: zur Bruchmitte verdichtet. Gleich große Trümmer sind das
       sicherste Zeichen für ein Partikelsystem. */
    const xs = kontur.map((p) => p[0]), ys = kontur.map((p) => p[1]);
    const x0 = Math.min(...xs), x1 = Math.max(...xs);
    const y0 = Math.min(...ys), y1 = Math.max(...ys);
    const zx = (x0 + x1) / 2, zy = y0 + (y1 - y0) * 0.30;
    const saat = [];
    let versuche = 0;
    while (saat.length < zellen && versuche < 3000) {
      versuche++;
      let sx, sy;
      if (Math.random() < 0.55) { sx = zx + gauss() * (x1 - x0) * 0.22; sy = zy + gauss() * (y1 - y0) * 0.22; }
      else { sx = x0 + Math.random() * (x1 - x0); sy = y0 + Math.random() * (y1 - y0); }
      if (!imPolygon(sx, sy, kontur)) continue;
      let nah = false;
      for (const s of saat) { if (Math.hypot(s[0] - sx, s[1] - sy) < (x1 - x0) * 0.055) { nah = true; break; } }
      if (nah) continue;
      saat.push([sx, sy, [Math.random(), Math.random(), Math.random()]]);
    }
    if (!saat.length) return;

    const zA = -tiefe / 2, zB = tiefe / 2;

    tris.forEach((tri) => {
      const T = tri.map((i) => kontur[i]);
      saat.forEach((S) => {
        let zelle = T;
        for (let k = 0; k < saat.length && zelle.length > 2; k++) {
          const O = saat[k];
          if (O === S) continue;
          const nx = O[0] - S[0], ny = O[1] - S[1];
          const c = (nx * (S[0] + O[0]) + ny * (S[1] + O[1])) / 2;
          zelle = schneide(zelle, nx, ny, c);
        }
        if (zelle.length < 3) return;
        if (Math.abs(flaeche(zelle)) < 0.0015) return;

        const M = [S[0] - mitte.x, S[1] - mitte.y, 0], R = S[2];
        const uhr = flaeche(zelle) < 0 ? -1 : 1;
        const m = zelle.length;

        const schieb = (p) => [p[0] - mitte.x, p[1] - mitte.y, 0];
        const setz = (v, n, innen) => {
          pos.push(v[0], v[1], v[2]); nor.push(n[0], n[1], n[2]);
          amit.push(M[0], M[1], M[2]); azuf.push(R[0], R[1], R[2]); ainn.push(innen);
        };

        /* Deckel vorn und hinten */
        for (let i = 1; i < m - 1; i++) {
          const a = schieb(zelle[0]), b = schieb(zelle[i]), c2 = schieb(zelle[i + 1]);
          setz([a[0], a[1], zB], [0, 0, 1], 0); setz([b[0], b[1], zB], [0, 0, 1], 0); setz([c2[0], c2[1], zB], [0, 0, 1], 0);
          setz([c2[0], c2[1], zA], [0, 0, -1], 0); setz([b[0], b[1], zA], [0, 0, -1], 0); setz([a[0], a[1], zA], [0, 0, -1], 0);
        }
        /* Seitenwände; Schnittflächen bekommen die Innenmarkierung */
        for (let i = 0; i < m; i++) {
          const a = schieb(zelle[i]), b = schieb(zelle[(i + 1) % m]);
          const dx = b[0] - a[0], dy = b[1] - a[1], L = Math.hypot(dx, dy) || 1;
          const n = [dy / L * -uhr, -dx / L * -uhr, 0];
          const innen = aufKontur(zelle[i][0], zelle[i][1], LOGO_CONTOURS, 0.02)
                     && aufKontur(zelle[(i + 1) % m][0], zelle[(i + 1) % m][1], LOGO_CONTOURS, 0.02) ? 0 : 1;
          setz([a[0], a[1], zB], n, innen); setz([b[0], b[1], zB], n, innen); setz([b[0], b[1], zA], n, innen);
          setz([a[0], a[1], zB], n, innen); setz([b[0], b[1], zA], n, innen); setz([a[0], a[1], zA], n, innen);
        }
      });
    });
  });

  const g = new THREE.BufferGeometry();
  g.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
  g.setAttribute('normal', new THREE.Float32BufferAttribute(nor, 3));
  g.setAttribute('uv', new THREE.Float32BufferAttribute(new Float32Array((pos.length / 3) * 2), 2));
  g.setAttribute('aMit', new THREE.Float32BufferAttribute(amit, 3));
  g.setAttribute('aZuf', new THREE.Float32BufferAttribute(azuf, 3));
  g.setAttribute('aInnen', new THREE.Float32BufferAttribute(ainn, 1));
  g.computeBoundingSphere();
  return g;
}

/* --------------------------------------------------------------------------
   Physik in das VORHANDENE Material einhängen, statt ein zweites zu bauen.
   So bleiben Klarlack, Anisotropie, Bloom und Umgebung identisch — der Bruch
   sieht aus wie dasselbe Metall, weil es dasselbe Metall ist.

   Bewegung: Impuls aus einem Bruchzentrum, exponentiell gedämpft (Luft-
   widerstand), Schwerkraft, Eigendrehung um eine zufällige Achse. Lineares
   Fliegen verrät jedes Partikelsystem sofort.
   -------------------------------------------------------------------------- */
export function bruchMaterial(THREEns, vorlage) {
  const mat = vorlage.clone();
  mat.uniformsNeedUpdate = true;
  const uniformen = { uBruch: { value: 0 }, uGlut: { value: 0 } };

  mat.onBeforeCompile = (shader) => {
    Object.assign(shader.uniforms, uniformen);
    shader.vertexShader = shader.vertexShader
      .replace('#include <common>', `#include <common>
        attribute vec3 aMit; attribute vec3 aZuf; attribute float aInnen;
        uniform float uBruch;
        varying float vInnen; varying float vRiss;
        vec3 vd_dreh(vec3 p, vec3 achse, float w){
          float c = cos(w), s = sin(w);
          return p*c + cross(achse,p)*s + achse*dot(achse,p)*(1.0-c);
        }`)
      .replace('#include <begin_vertex>', `#include <begin_vertex>
        vInnen = aInnen; vRiss = 0.0;
        if (uBruch > 0.0001) {
          float t = uBruch;
          vec3 impakt = vec3(0.0, -0.25, 0.35);
          vec3 richt = normalize(aMit - impakt + (aZuf - 0.5) * 0.55);
          float nah = 1.0 - smoothstep(0.0, 1.6, length(aMit - impakt));
          float kraft = (0.55 + aZuf.x * 1.10 + nah * 0.95) * 4.2;
          float k = 1.8; float weg = (1.0 - exp(-k * t)) / k;
          float riss = smoothstep(0.0, 0.08, t);
          vRiss = 1.0 - smoothstep(0.0, 0.32, t);
          vec3 achse = normalize(aZuf - 0.5 + vec3(0.013, 0.007, 0.011));
          float w = (aZuf.y - 0.5) * (16.0 + nah * 24.0) * weg;
          transformed = aMit + vd_dreh(transformed - aMit, achse, w);
          transformed += richt * kraft * weg * riss;
          transformed.y -= 3.1 * t * t;
          objectNormal = vd_dreh(objectNormal, achse, w);
          vNormal = normalize(normalMatrix * objectNormal);
        }`);
    shader.fragmentShader = shader.fragmentShader
      .replace('#include <common>', `#include <common>
        uniform float uGlut; varying float vInnen; varying float vRiss;`)
      .replace('#include <dithering_fragment>', `#include <dithering_fragment>
        gl_FragColor.rgb += vec3(1.0, 0.74, 0.42) * vInnen * pow(vRiss, 3.0) * 5.0 * uGlut;
        gl_FragColor.rgb += vec3(0.16, 0.78, 1.0) * vInnen * (0.18 + vRiss * 1.9) * uGlut;`);
  };
  mat.customProgramCacheKey = () => 'vecom-bruch';
  return { mat, uniformen };
}
