/**
 * Die kurze Laufzeitmessung.
 *
 * Merkmale sind eine Vermutung, gemessene Bildzeiten sind eine Tatsache. Ein
 * Buerorechner mit acht Kernen und 16 GB kann durch einen Treiber, eine
 * Fernsitzung oder eine stromsparende Grafikeinstellung trotzdem kriechen —
 * das sieht man nur, wenn man es ausprobiert.
 *
 * Die Messung laeuft unsichtbar: ein kleines Canvas ausserhalb des Bildes,
 * wenige Bilder lang, mit einer Last, die genug fordert, um zu unterscheiden,
 * und wenig genug, um niemanden warten zu lassen. Sie darf die Seite nicht
 * fuehlbar verzoegern und bricht bei jedem Fehler still ab.
 */
const VERTEX = `#version 300 es
in vec2 lage;
out vec2 uv;
void main() {
  uv = lage * 0.5 + 0.5;
  gl_Position = vec4(lage, 0.0, 1.0);
}`;
// Genug Arbeit je Pixel, um schwache von starken Geraeten zu trennen, aber
// ohne Schleife mit unbestimmtem Ende — solche Shader haengen auf manchen
// Treibern beim Uebersetzen.
const FRAGMENT = `#version 300 es
precision highp float;
in vec2 uv;
uniform float zeit;
uniform sampler2D probe;
out vec4 farbe;

float rausch(vec2 p) {
  return fract(sin(dot(p, vec2(12.9898, 78.233))) * 43758.5453);
}

void main() {
  vec3 summe = vec3(0.0);
  vec2 p = uv * 8.0;
  for (int i = 0; i < 24; i++) {
    float f = float(i);
    p += vec2(sin(p.y + zeit + f), cos(p.x - zeit + f)) * 0.35;
    summe += vec3(rausch(p + f), rausch(p * 1.3 - f), rausch(p * 0.7 + f)) * 0.04;
  }
  summe += texture(probe, uv).rgb * 0.15;
  farbe = vec4(summe, 1.0);
}`;
const LEER = {
    fps: 0,
    frameTimeMs: 0,
    frameTimeP95: 0,
    textureUploadMs: 0,
    gueltig: false,
};
function uebersetze(gl, typ, quelle) {
    const s = gl.createShader(typ);
    if (!s)
        return null;
    gl.shaderSource(s, quelle);
    gl.compileShader(s);
    if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) {
        gl.deleteShader(s);
        return null;
    }
    return s;
}
/**
 * Fuehrt die Messung durch. Gibt bei jedem Problem ein ungueltiges Ergebnis
 * zurueck — der Aufrufer entscheidet dann allein nach Merkmalen weiter.
 */
export async function messeLeistung(o = {}) {
    const bilder = o.bilder ?? 30;
    const aufwaermen = o.aufwaermen ?? 8;
    const groesse = o.groesse ?? 256;
    const zeitlimit = o.zeitlimitMs ?? 2000;
    let canvas = null;
    let gl = null;
    try {
        canvas = document.createElement('canvas');
        canvas.width = groesse;
        canvas.height = groesse;
        // Ausserhalb des Bildes, aber nicht display:none — sonst ueberspringen
        // manche Browser das Zeichnen ganz und die Messung misst nichts.
        canvas.style.cssText =
            'position:fixed;left:-9999px;top:0;width:1px;height:1px;pointer-events:none;opacity:0.01';
        document.body.appendChild(canvas);
        gl = canvas.getContext('webgl2', { antialias: false, powerPreference: 'high-performance' });
        if (!gl)
            return LEER;
        const vs = uebersetze(gl, gl.VERTEX_SHADER, VERTEX);
        const fs = uebersetze(gl, gl.FRAGMENT_SHADER, FRAGMENT);
        if (!vs || !fs)
            return LEER;
        const prog = gl.createProgram();
        if (!prog)
            return LEER;
        gl.attachShader(prog, vs);
        gl.attachShader(prog, fs);
        gl.linkProgram(prog);
        if (!gl.getProgramParameter(prog, gl.LINK_STATUS))
            return LEER;
        gl.useProgram(prog);
        const puffer = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, puffer);
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
        const ort = gl.getAttribLocation(prog, 'lage');
        gl.enableVertexAttribArray(ort);
        gl.vertexAttribPointer(ort, 2, gl.FLOAT, false, 0, 0);
        // Texturupload messen: auf schwachen Geraeten ist das der eigentliche
        // Engpass beim Szenenstart, nicht das Zeichnen.
        const kante = 512;
        const daten = new Uint8Array(kante * kante * 4);
        for (let i = 0; i < daten.length; i++)
            daten[i] = (i * 37) & 255;
        const tex = gl.createTexture();
        gl.bindTexture(gl.TEXTURE_2D, tex);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        const uploadStart = performance.now();
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, kante, kante, 0, gl.RGBA, gl.UNSIGNED_BYTE, daten);
        gl.finish();
        const textureUploadMs = performance.now() - uploadStart;
        const zeitOrt = gl.getUniformLocation(prog, 'zeit');
        gl.viewport(0, 0, groesse, groesse);
        const zeiten = [];
        const start = performance.now();
        let letzte = start;
        for (let i = 0; i < aufwaermen + bilder; i++) {
            if (performance.now() - start > zeitlimit)
                break;
            gl.uniform1f(zeitOrt, i * 0.05);
            gl.drawArrays(gl.TRIANGLES, 0, 3);
            // finish() erzwingt, dass die GPU wirklich fertig ist. Ohne das misst
            // man nur, wie schnell Befehle in die Warteschlange fallen.
            gl.finish();
            const jetzt = performance.now();
            if (i >= aufwaermen)
                zeiten.push(jetzt - letzte);
            letzte = jetzt;
            // Dem Hauptfaden Luft lassen: die Seite soll waehrenddessen bedienbar
            // bleiben. Genau dafuer ist die Messung kurz.
            if (i % 10 === 9)
                await new Promise((w) => requestAnimationFrame(() => w(null)));
        }
        gl.deleteTexture(tex);
        gl.deleteBuffer(puffer);
        gl.deleteProgram(prog);
        gl.deleteShader(vs);
        gl.deleteShader(fs);
        if (zeiten.length < 5)
            return { ...LEER, textureUploadMs };
        let summe = 0;
        for (const t of zeiten)
            summe += t;
        const mittel = summe / zeiten.length;
        const sortiert = [...zeiten].sort((a, b) => a - b);
        const p95 = sortiert[Math.min(sortiert.length - 1, Math.floor(sortiert.length * 0.95))] ?? mittel;
        return {
            fps: mittel > 0 ? 1000 / mittel : 0,
            frameTimeMs: mittel,
            frameTimeP95: p95,
            textureUploadMs,
            gueltig: true,
        };
    }
    catch {
        return LEER;
    }
    finally {
        try {
            gl?.getExtension('WEBGL_lose_context')?.loseContext();
            canvas?.remove();
        }
        catch {
            /* egal */
        }
    }
}
//# sourceMappingURL=benchmark.js.map