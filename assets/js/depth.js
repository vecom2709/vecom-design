/* ==========================================================================
   depth.js — Tiefen-Parallaxe für Bilder ohne laufende Website.

   Grundlage: eine Tiefenkarte, die mit Depth Anything V2 aus dem Bild selbst
   errechnet wurde (siehe tools/make-depth.py). Der Shader verschiebt jedes
   Pixel entlang seiner Tiefe: Nahes wandert stark, Fernes kaum. Dadurch wirkt
   ein Foto räumlich, ohne dass ein 3D-Modell nötig wäre.

   Kosten: zwei kleine Bilder (3–4 KB je Tiefenkarte) und ein Shader mit
   knapp zwanzig Zeilen. Kein three.js.

   Fällt WebGL aus oder ist Bewegung reduziert, bleibt das Bild einfach stehen.
   ========================================================================== */
(function () {
  'use strict';
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const nodes = [...document.querySelectorAll('[data-depth]')];
  if (!nodes.length || reduced) return;

  const VERT = `
    attribute vec2 p; varying vec2 v;
    void main(){ v = p * 0.5 + 0.5; v.y = 1.0 - v.y; gl_Position = vec4(p, 0.0, 1.0); }`;

  const FRAG = `
    precision mediump float;
    varying vec2 v;
    uniform sampler2D uImg, uDep;
    uniform vec2 uMouse;      // -1 … 1
    uniform float uAmt;       // Stärke der Verschiebung
    void main(){
      float d = texture2D(uDep, v).r;          // 0 = fern, 1 = nah
      vec2 off = uMouse * uAmt * (d - 0.35);
      gl_FragColor = texture2D(uImg, v + off);
    }`;

  const make = (gl, type, src) => {
    const s = gl.createShader(type);
    gl.shaderSource(s, src); gl.compileShader(s);
    return gl.getShaderParameter(s, gl.COMPILE_STATUS) ? s : null;
  };

  nodes.forEach((box) => {
    const canvas = box.querySelector('canvas');
    const gl = canvas.getContext('webgl', { antialias: false, alpha: false });
    if (!gl) return;                     // ohne WebGL bleibt das <img> stehen

    const prog = gl.createProgram();
    const vs = make(gl, gl.VERTEX_SHADER, VERT);
    const fs = make(gl, gl.FRAGMENT_SHADER, FRAG);
    if (!vs || !fs) return;
    gl.attachShader(prog, vs); gl.attachShader(prog, fs); gl.linkProgram(prog);
    gl.useProgram(prog);

    const buf = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 1,-1, -1,1, 1,1]), gl.STATIC_DRAW);
    const loc = gl.getAttribLocation(prog, 'p');
    gl.enableVertexAttribArray(loc);
    gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);

    const uMouse = gl.getUniformLocation(prog, 'uMouse');
    const uAmt = gl.getUniformLocation(prog, 'uAmt');
    gl.uniform1f(uAmt, 0.035);

    const tex = (unit, name) => {
      const t = gl.createTexture();
      gl.activeTexture(gl.TEXTURE0 + unit);
      gl.bindTexture(gl.TEXTURE_2D, t);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
      gl.uniform1i(gl.getUniformLocation(prog, name), unit);
      return t;
    };
    const tImg = tex(0, 'uImg');
    const tDep = tex(1, 'uDep');

    let ready = 0;
    const load = (src, unit, texture) => {
      const im = new Image();
      im.onload = () => {
        gl.activeTexture(gl.TEXTURE0 + unit);
        gl.bindTexture(gl.TEXTURE_2D, texture);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, im);
        if (++ready === 2) { box.classList.add('is-live'); resize(); draw(); }
      };
      im.src = src;
    };

    function resize() {
      const r = box.getBoundingClientRect();
      const dpr = Math.min(window.devicePixelRatio || 1, 1.75);
      canvas.width = Math.round(r.width * dpr);
      canvas.height = Math.round(r.height * dpr);
      gl.viewport(0, 0, canvas.width, canvas.height);
    }

    let mx = 0, my = 0, cx = 0, cy = 0, raf = null;
    function draw() {
      cx += (mx - cx) * 0.07;
      cy += (my - cy) * 0.07;
      gl.uniform2f(uMouse, cx, cy);
      gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
      raf = (Math.abs(mx - cx) > 0.001 || Math.abs(my - cy) > 0.001)
        ? requestAnimationFrame(draw) : null;
    }

    box.addEventListener('pointermove', (e) => {
      const r = box.getBoundingClientRect();
      mx = ((e.clientX - r.left) / r.width - 0.5) * 2;
      my = ((e.clientY - r.top) / r.height - 0.5) * 2;
      if (!raf) raf = requestAnimationFrame(draw);
    }, { passive: true });
    box.addEventListener('pointerleave', () => {
      mx = 0; my = 0;
      if (!raf) raf = requestAnimationFrame(draw);
    });
    window.addEventListener('resize', () => { resize(); draw(); }, { passive: true });

    load(box.dataset.img, 0, tImg);
    load(box.dataset.depthSrc, 1, tDep);
  });
})();
