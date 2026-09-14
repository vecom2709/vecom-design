/* Adaptive Qualität — von Anfang an, nicht nachgerüstet.
   Reduziert wird in fester Reihenfolge: Postprocessing → Partikel → DPR →
   Schattenqualität. Die Inszenierung (Kamera, Beats) bleibt immer erhalten. */

const LEVELS = {
  ultra:  { dpr: 2.0, bloom: true, particles: 2600, shadow: 2048, aa: true },
  high:   { dpr: 1.75, bloom: true, particles: 1800, shadow: 1024, aa: true },
  medium: { dpr: 1.35, bloom: true, particles: 1000, shadow: 512,  aa: false },
  low:    { dpr: 1.0, bloom: false, particles: 450,  shadow: 0,    aa: false },
};

export function detectLevel() {
  const dpr = window.devicePixelRatio || 1;
  const mem = navigator.deviceMemory || 4;
  const cores = navigator.hardwareConcurrency || 4;
  const coarse = window.matchMedia('(pointer: coarse)').matches;
  const small = Math.min(window.innerWidth, window.innerHeight) < 700;

  if (coarse || small) return mem >= 6 && cores >= 6 ? 'medium' : 'low';
  if (cores >= 10 && mem >= 8) return dpr > 1.5 ? 'ultra' : 'high';
  if (cores >= 6) return 'high';
  return 'medium';
}

export class Quality {
  constructor(level = detectLevel()) {
    this.order = ['ultra', 'high', 'medium', 'low'];
    this.level = level;
    this.settings = { ...LEVELS[level] };
    this.samples = [];
    this.locked = false;      // vom Nutzer gesetzt → nicht mehr automatisch ändern
    this.onChange = null;
  }

  set(level) {
    if (!LEVELS[level]) return;
    this.level = level;
    this.settings = { ...LEVELS[level] };
    this.samples.length = 0;
    if (this.onChange) this.onChange(this.settings, level);
  }

  /* Frame-Zeit beobachten. Zwei Sekunden über 24 ms → eine Stufe zurück.
     Es wird nie automatisch hochgestuft; ein Sprung hin und her wäre sichtbarer
     als eine dauerhaft niedrigere Stufe. */
  sample(ms) {
    if (this.locked) return;
    this.samples.push(ms);
    if (this.samples.length < 90) return;
    const avg = this.samples.reduce((a, b) => a + b, 0) / this.samples.length;
    this.samples.length = 0;
    if (avg > 24) {
      const i = this.order.indexOf(this.level);
      if (i < this.order.length - 1) { this.set(this.order[i + 1]); return; }
      /* UND WENN AUCH DIE UNTERSTE STUFE NICHT REICHT
         ------------------------------------------------------------------
         Bis zum 14.09.2026 endete die Kette hier: Wer auf "low" noch immer
         nicht mitkam, blieb auf "low" -- also bei einer ruckelnden Buehne,
         fuer immer. Und schlimmer: sample() wurde nie aufgerufen, die ganze
         Abstufung lief gar nicht. Sie stand seit Monaten im Code und hat nie
         gemessen.

         34 ms sind unter 30 Bildern je Sekunde auf der kleinsten Stufe. Das
         ist kein Geraet, das eine Echtzeitbuehne tragen will. Es bekommt das
         gerechnete Standbild -- und ab da kostet die Seite gar nichts mehr. */
      if (avg > 34 && this.onAufgeben) { this.locked = true; this.onAufgeben(avg); }
    }
  }
}

/* WAS FUER EINE GRAFIK STECKT DA DRIN
   ---------------------------------------------------------------------------
   detectLevel() fragt nach Kernen und Speicher. Das sagt nichts ueber die
   Grafik, und die entscheidet hier alles. Uwes eigener Rechner ist der Beleg:
   ein i7-4600U meldet vier Threads und landet damit auf "medium" -- mit
   Bloom, mit dpr 1.35. Die Grafik darin ist eine Intel HD 4400 von 2013. Am
   14.09.2026 blieb die Seite auf genau diesem Geraet so lange haengen, dass
   Chrome ueber eine halbe Minute kein Skript mehr ausfuehren konnte.

   Software-Rasterizer (SwiftShader, llvmpipe, "Software") sind der klare
   Fall: Sie rechnen auf der CPU, jeder Frame kostet Zehntelsekunden. Die
   alten Intel-Generationen davor (HD 2000 bis 5500, ohne Nummer) sind der
   haeufige Fall in Buerorechnern.

   Wer hier durchfaellt, bekommt kein schlechteres 3D, sondern das gerechnete
   Standbild -- und das sieht besser aus. */
const SOFTWARE = /swiftshader|llvmpipe|software|basic render|microsoft basic|mesa offscreen/i;

/* WELCHE ZAHL ALT BEDEUTET, HAENGT DAVON AB, WIE VIELE STELLEN SIE HAT.
   Intel hat die Zaehlweise mittendrin gewechselt: Die alten Generationen
   tragen vier Stellen (HD 4400, 2013), die neueren drei (UHD 620, 630).
   Eine einzige Schwelle kann das nicht treffen -- mein erster Versuch
   verglich 4400 gegen 600 und hielt eine Grafik von 2013 fuer modern.

     vier Stellen, bis 6000   -> alt   (HD 2000 ... HD 6000, 2011-2015)
     drei Stellen, bis 630    -> alt   (HD 510 ... UHD 630, 2015-2020)
     Iris, Xe, Arc            -> reicht
   Keine Zahl hinter "Graphics" heisst aelter als jede Zahl. */
export function grafikKennung() {
  try {
    const c = document.createElement('canvas');
    const g = c.getContext('webgl2') || c.getContext('webgl');
    if (!g) return '';
    const d = g.getExtension('WEBGL_debug_renderer_info');
    return d ? String(g.getParameter(d.UNMASKED_RENDERER_WEBGL) || '') : '';
  } catch (e) {
    return '';
  }
}

export function grafikZuSchwach(kennung) {
  const k = kennung === undefined ? grafikKennung() : kennung;
  if (!k) return false;                    /* keine Auskunft -> nicht raten */
  if (SOFTWARE.test(k)) return true;
  if (/\b(iris|arc)\b|\bxe\b/i.test(k)) return false;

  const m = k.match(/\b(?:hd|uhd)\s*graphics(?:\s+(\d{3,4}))?/i);
  if (!m) return false;                    /* keine Intel-Integrierte */
  if (!m[1]) return true;                  /* "HD Graphics" ohne Zahl */
  const n = Number(m[1]);
  return m[1].length === 4 ? n <= 6000 : n <= 630;
}

export function supportsWebGL() {
  try {
    const c = document.createElement('canvas');
    return !!(window.WebGL2RenderingContext && c.getContext('webgl2'));
  } catch (e) {
    return false;
  }
}
