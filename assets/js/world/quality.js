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
      if (i < this.order.length - 1) this.set(this.order[i + 1]);
    }
  }
}

export function supportsWebGL() {
  try {
    const c = document.createElement('canvas');
    return !!(window.WebGL2RenderingContext && c.getContext('webgl2'));
  } catch (e) {
    return false;
  }
}
