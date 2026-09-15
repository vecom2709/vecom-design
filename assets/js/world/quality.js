/* Adaptive Qualität — seit dem 15.09.2026 nur noch die Übersetzung.
   ---------------------------------------------------------------------------
   Diese Datei hat früher selbst entschieden. Jetzt entscheidet
   assets/vendor/experience/ (die VECOM Adaptive Experience Platform), und hier
   steht nur noch, wie deren Werte in das Vokabular dieser Seite übersetzt
   werden: ultra/high/medium/low, { dpr, bloom, particles, shadow, aa }.

   WARUM DER UMBAU
   ---------------------------------------------------------------------------
   Die alte Fassung entschied nach Kernen, Speicher und Fenstergröße. Drei
   Dinge fehlten ihr, und jedes davon hat schon Zeit gekostet:

   1. Sie stufte NIE hoch. Wer einmal unten war, blieb unten — auch wenn nur
      ein Ladevorgang kurz gebremst hatte. Der Kommentar dazu war richtig
      begründet ("ein Sprung hin und her wäre sichtbarer"), aber die Antwort
      darauf ist nicht "nie", sondern Hysterese: zehn Sekunden stabil über
      55 fps, danach Sperrzeit, und eine Stufe, die zweimal zusammengebrochen
      ist, wird nicht mehr angeboten.

   2. Sie reduzierte in der Reihenfolge Postprocessing → Partikel → DPR.
      Gemessen bringt die Auflösung am meisten und fällt am wenigsten auf;
      Bloom trägt die halbe Stimmung dieser Bühne. Die Reihenfolge ist jetzt
      umgekehrt: Auflösung, Partikel, Schatten, Reflexionen, Nachbearbeitung,
      Nebenanimationen, Texturen, LOD — und erst dann fällt eine Stufe.

   3. Sie maß nichts, bevor sie entschied. Jetzt läuft eine kurze, unsichtbare
      Messung mit: Merkmale sind eine Vermutung, Bildzeiten sind eine Tatsache.

   WAS GEBLIEBEN IST
   ---------------------------------------------------------------------------
   Die Grafikkennung — SwiftShader, llvmpipe, die zwei Intel-Zählweisen — ist
   unverändert übernommen und liegt jetzt im Kern (gpu-kennung.js), wo auch
   andere Projekte sie bekommen. Sie war hier teuer bezahlt: am 14.09.2026 mit
   einer halben Minute Stillstand auf einer HD 4400.

   Die Schnittstelle nach außen ist Zeile für Zeile dieselbe. site-world.js,
   raum.js und raum-beats.js wurden NICHT angefasst. */

import {
  AdaptiveExperienceManager,
  SZENE_HERO,
  grafikZuSchwach as kennungZuSchwach,
  istSoftwareRasterizer,
} from '../../vendor/experience/index.js';

/* Absolute Partikelzahl dieser Bühne bei vollem Budget. Der Kern rechnet in
   Anteilen, weil er nichts über diese Szene weiß. */
const PARTIKEL_VOLL = 2600;

const STUFE_ZU_NAME = { ULTRA: 'ultra', HIGH: 'high', MEDIUM: 'medium', LOW: 'low', SAFE: 'safe' };
const NAME_ZU_STUFE = { ultra: 'ULTRA', high: 'HIGH', medium: 'MEDIUM', low: 'LOW' };

/* Die Werte des Kerns in das Vokabular dieser Seite. */
function uebersetze(einstellungen) {
  const geraeteDpr = window.devicePixelRatio || 1;
  return {
    dpr: Math.min(geraeteDpr, einstellungen.maxPixelRatio) * (einstellungen.resolutionScale || 1),
    bloom: einstellungen.postProcessing.bloom,
    particles: Math.round(PARTIKEL_VOLL * einstellungen.particleBudget),
    shadow: einstellungen.shadowMapSize,
    aa: einstellungen.antialias,
  };
}

/* Startwerte, bevor der Kern geantwortet hat. Sie müssen sofort da sein: die
   Bühne baut sich damit auf, während Messung und Netzprüfung noch laufen.
   Bewusst vorsichtig — lieber eine Stufe zu tief starten und nach zehn
   Sekunden hochgehen als umgekehrt. */
const VORLAEUFIG = {
  ultra:  { dpr: 2.0,  bloom: true,  particles: 2600, shadow: 2048, aa: true },
  high:   { dpr: 1.75, bloom: true,  particles: 1560, shadow: 1024, aa: true },
  medium: { dpr: 1.35, bloom: true,  particles: 780,  shadow: 512,  aa: false },
  low:    { dpr: 1.0,  bloom: false, particles: 260,  shadow: 0,    aa: false },
};

/* Synchrone Ersteinschätzung. WebGPU lässt sich nur asynchron prüfen, deshalb
   entscheidet hier nur, was sofort verfügbar ist — der Kern korrigiert gleich
   darauf. */
export function detectLevel() {
  const kennung = grafikKennung();
  if (kennungZuSchwach(kennung) || istSoftwareRasterizer(kennung)) return 'low';
  if (!supportsWebGL()) return 'low';

  const mem = navigator.deviceMemory || 4;
  const cores = navigator.hardwareConcurrency || 4;
  const mobil = window.matchMedia('(pointer: coarse)').matches;

  /* Nur die Breite zählt, nicht die kleinere Seite: Ein Browserfenster von
     653 px Höhe am Schreibtisch ist kein Telefon. Dieser Fehler stufte am
     12.09.2026 einen Desktop auf medium. */
  const schmal = window.innerWidth < 760;

  if (mobil || schmal) return mem >= 6 && cores >= 6 ? 'medium' : 'low';
  if (cores >= 10 && mem >= 8) return 'high';
  if (cores >= 6) return 'high';
  return 'medium';
}

export class Quality {
  constructor(level = detectLevel()) {
    this.order = ['ultra', 'high', 'medium', 'low'];
    this.level = level;
    this.settings = { ...VORLAEUFIG[level] };
    this.locked = false;
    this.onChange = null;
    this.onAufgeben = null;

    /* Der Kern arbeitet für den Kopfbereich: eine Bühne, kein Showroom —
       deshalb SZENE_HERO. Sie darf nie in die Cloud, egal wie gut die
       Leitung ist. Eine gemietete GPU für eine Kopfanimation wäre Unfug. */
    this._manager = new AdaptiveExperienceManager({ szene: SZENE_HERO });
    this._bereit = false;

    this._manager
      .initialisieren()
      .then((entscheidung) => {
        this._bereit = true;
        if (this.locked) return;
        if (entscheidung.strategie === 'SAFE_MEDIA' || entscheidung.stufe === 'SAFE') {
          this._aufgeben('Kein tragfähiger Echtzeitweg: ' + entscheidung.begruendung);
          return;
        }
        this._uebernehmen(entscheidung.stufe);
      })
      .catch(() => {
        /* Wenn die Ermittlung scheitert, bleibt die vorläufige Stufe stehen.
           Eine Seite, die wegen einer fehlgeschlagenen Messung stehenbleibt,
           wäre schlechter als eine, die vorsichtig weiterläuft. */
        this._bereit = true;
      });

    this._manager.abonnieren((zustand) => {
      if (!this._bereit) return;
      if (zustand.entscheidung.stufe === 'SAFE') {
        this._aufgeben('Auch die unterste Stufe trägt nicht.');
        return;
      }
      this._uebernehmen(zustand.entscheidung.stufe, zustand.einstellungen);
    });
  }

  _uebernehmen(kernStufe, einstellungen) {
    const name = STUFE_ZU_NAME[kernStufe] || 'medium';
    if (name === 'safe') {
      this._aufgeben('Stufe SAFE.');
      return;
    }
    const neu = einstellungen ? uebersetze(einstellungen) : { ...VORLAEUFIG[name] };
    const gleich =
      this.level === name &&
      Math.abs(neu.dpr - this.settings.dpr) < 0.01 &&
      neu.particles === this.settings.particles &&
      neu.shadow === this.settings.shadow &&
      neu.bloom === this.settings.bloom;
    if (gleich) return;
    this.level = name;
    this.settings = neu;
    if (this.onChange) this.onChange(this.settings, name);
  }

  _aufgeben(grund) {
    if (this._aufgegeben) return;
    this._aufgegeben = true;
    this.locked = true;
    if (this.onAufgeben) this.onAufgeben(grund);
  }

  /* Feste Wahl aus dem Menü. 'auto' gibt die Regelung wieder frei. */
  set(level) {
    if (!NAME_ZU_STUFE[level]) return;
    this.level = level;
    this.settings = { ...VORLAEUFIG[level] };
    try {
      this._manager.setzeNutzerWahl({ stufe: this.locked ? NAME_ZU_STUFE[level] : 'AUTO' });
    } catch (e) {
      /* vor der Initialisierung: die vorläufigen Werte gelten weiter */
    }
    if (this.onChange) this.onChange(this.settings, level);
  }

  /* Bildzeit aus dem Renderloop. Der Regler im Kern entscheidet, ob etwas
     passiert — hier wird nur weitergereicht. */
  sample(ms) {
    if (this._aufgegeben) return;
    try {
      this._manager.bildGemeldet(ms);
    } catch (e) {
      /* vor der Initialisierung gibt es nichts zu regeln */
    }
  }

  /* Für das Entwicklerfenster: der vollständige Zustand des Kerns. */
  get zustand() {
    try {
      return this._manager.zustand;
    } catch (e) {
      return null;
    }
  }
}

/* --------------------------------------------------------------------------
   Unverändert übernommen, weil site-world.js sie direkt einbindet. Die Logik
   dahinter liegt jetzt im Kern; hier steht nur noch die Abfrage am Browser. */

export function grafikKennung() {
  try {
    const c = document.createElement('canvas');
    const g = c.getContext('webgl2') || c.getContext('webgl');
    if (!g) return '';
    const d = g.getExtension('WEBGL_debug_renderer_info');
    const k = d ? String(g.getParameter(d.UNMASKED_RENDERER_WEBGL) || '') : '';
    /* Kontext sofort freigeben — Browser erlauben nur wenige gleichzeitig. */
    g.getExtension('WEBGL_lose_context') && g.getExtension('WEBGL_lose_context').loseContext();
    return k;
  } catch (e) {
    return '';
  }
}

export function grafikZuSchwach(kennung) {
  const k = kennung === undefined ? grafikKennung() : kennung;
  return kennungZuSchwach(k) || istSoftwareRasterizer(k);
}

export function supportsWebGL() {
  try {
    const c = document.createElement('canvas');
    return !!(window.WebGL2RenderingContext && c.getContext('webgl2'));
  } catch (e) {
    return false;
  }
}
