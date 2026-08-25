/* ==========================================================================
   finish-pass.js — Der letzte Durchgang über das fertige Bild.

   Vier Effekte in EINEM Pass statt vier hintereinander. Grund: Jeder
   zusätzliche Pass kostet eine volle Bildschirmauflösung an Lese- und
   Schreibarbeit. Zusammengelegt kostet das Ganze etwa so viel wie ein
   einzelner Weichzeichner.

   1. Lichtstreuung (God Rays) — Strahlen aus der Lichtquelle, an der Marke
      vorbei. Das ist der Effekt, der Tiefe und Atmosphäre erzeugt.
   2. Tiefenunschärfe — Näherung im Bildraum: was weit vom Schärfepunkt liegt,
      wird weich. Kein echtes Tiefenpuffer-Bokeh, aber ein Bruchteil der Kosten.
   3. Farbsaum (chromatische Aberration) — zum Rand hin trennen sich die
      Farbkanäle minimal, wie bei einem echten Objektiv.
   4. Vignette — dunkler Rand, führt den Blick zur Mitte.
   ========================================================================== */

export const FinishShader = {
  name: 'FinishShader',
  uniforms: {
    tDiffuse:     { value: null },
    uLightPos:    { value: null },   // Lichtquelle in Bildkoordinaten (0–1)
    uRays:        { value: 0.0 },    // aus: die Strahlen zogen helle Kanten zu Schlieren,
                                    // die wie doppelte Spiegelungen aussahen
    uRayDecay:    { value: 0.94 },
    uBlur:        { value: 0.35 },   // Stärke der Tiefenunschärfe
    uFocus:       { value: 0.30 },   // Radius des scharfen Bereichs
    uAberration:  { value: 0.0 },   // aus: an dünnen Kanten entstanden Farbränder
    uVignette:    { value: 0.55 },
    uAspect:      { value: 1.0 },
  },
  vertexShader: /* glsl */`
    varying vec2 vUv;
    void main() {
      vUv = uv;
      gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
    }`,
  fragmentShader: /* glsl */`
    uniform sampler2D tDiffuse;
    uniform vec2  uLightPos;
    uniform float uRays, uRayDecay, uBlur, uFocus, uAberration, uVignette, uAspect;
    varying vec2 vUv;

    const int RAY_SAMPLES = 20;
    const int BLUR_TAPS   = 6;

    float luma(vec3 c) { return dot(c, vec3(0.2126, 0.7152, 0.0722)); }

    void main() {
      vec2 uv = vUv;
      vec2 centered = uv - 0.5;
      centered.x *= uAspect;
      float r = length(centered);

      /* --- 3. Farbsaum: Kanäle zum Rand hin minimal versetzt --------------- */
      vec2 off = centered * uAberration * (0.4 + r);
      vec3 col;
      col.r = texture2D(tDiffuse, uv + off).r;
      col.g = texture2D(tDiffuse, uv).g;
      col.b = texture2D(tDiffuse, uv - off).b;

      /* --- 2. Tiefenunschärfe: nur außerhalb des Schärfepunkts ------------- */
      float d = smoothstep(uFocus, uFocus + 0.55, r) * uBlur;
      if (d > 0.001) {
        vec3 blurred = col;
        // Kleiner Radius: bei größeren Werten erzeugen helle Punkte (Staub, Glanzlichter)
        // sichtbare Kopien statt weicher Unschärfe — das sah aus wie Doppelbilder.
        float radius = d * 0.008;
        for (int i = 0; i < BLUR_TAPS; i++) {
          float a = float(i) * 1.0472;                 // 60° Schritte
          vec2 dir = vec2(cos(a), sin(a)) * radius;
          blurred += texture2D(tDiffuse, uv + dir).rgb;
          blurred += texture2D(tDiffuse, uv - dir * 0.55).rgb;
        }
        blurred /= float(BLUR_TAPS) * 2.0 + 1.0;
        col = mix(col, blurred, clamp(d, 0.0, 1.0));
      }

      /* --- 1. Lichtstreuung: Strahlen von der Lichtquelle nach außen ------- */
      if (uRays > 0.001) {
        vec2 delta = (uv - uLightPos) / float(RAY_SAMPLES) * 0.85;
        vec2 pos = uv;
        float decay = 1.0;
        vec3 rays = vec3(0.0);
        for (int i = 0; i < RAY_SAMPLES; i++) {
          pos -= delta;
          vec3 s = texture2D(tDiffuse, pos).rgb;
          // Nur helle Stellen streuen — sonst wird das ganze Bild milchig.
          s *= smoothstep(0.72, 1.0, luma(s));
          rays += s * decay;
          decay *= uRayDecay;
        }
        rays /= float(RAY_SAMPLES);
        // Nach außen hin schwächer, damit der Text lesbar bleibt.
        col += rays * uRays * (1.0 - smoothstep(0.15, 0.95, r)) * 3.2;
      }

      /* --- 4. Vignette ----------------------------------------------------- */
      col *= 1.0 - uVignette * smoothstep(0.35, 1.15, r);

      gl_FragColor = vec4(col, 1.0);
    }`,
};
