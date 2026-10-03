/* ==========================================================================
   3D-Bilder und -Videos auf Uwes PC (Marketing-Studio 11, 01.10.2026, Uwe:
   „Bilder, Videos … zusätzlich oder alternativ hochqualitativ mit Blender
   und Unreal Engine“).

   Kein Dienst, keine Credits: Blender rechnet die Branchen-Szene am echten
   Ort (3d-produktion/scripts/branchen_ort.py, Modus marketing bzw.
   marketing_film) mit Cycles auf der RTX 5070. Vor jedem Bild misst das
   Skript die Belichtung an einer kleinen Probe (Reality Check) und schreibt
   einen Bericht daneben. Die Verwaltung gibt diese Aufträge nur im
   Nachtfenster heraus (oder mit „3D jetzt rechnen“).

   Gibt es für die Branche keine Szene, baut Claude sie als Blender-Skript
   aus der Bildidee (ki/szene3d.ts) — für Bilder und (seit W2) für Filme mit
   einer langsamen Fahrt um das Motiv. Auch der freie Wunsch eines Partners
   (erst nach Uwes Ja) läuft diesen Weg.

   Unreal (Modell „unreal“, nur Filme): Blender exportiert die Szene samt
   Kamerafahrt (Modus marketing_unreal), Unreal rechnet mit dem Path Tracer
   (3d-produktion/unreal/ue-marketing.ps1). Vorher ein Probebild: dessen
   Helligkeit wird gegen Blenders Referenzbild gemessen und die Belichtung
   nachgeführt. Scheitert Unreal, entsteht der Film mit Blender.
   ========================================================================== */
import { spawn } from 'node:child_process';
import { existsSync, readFileSync, readdirSync, statSync, writeFileSync, createWriteStream } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { api } from '../api.js';
import { datenOrdner } from '../konfig.js';
import { log } from '../log.js';
import { hochladen, type MedienAuftrag } from './kie.js';

export type DreiD = { studio: string | null; generativ: boolean; seed: number; sprache: string; film_titel: string; abspann: string;
  /** Partner-Wunsch (W1–W3): Feinwahl für Branchen-Szenen; partner_wunsch = prompt ist der freie Text eines Partners. */
  wunsch?: { blick?: string; naehe?: string; stimmung?: string } | null; partner_wunsch?: boolean;
  /** Werbespot (01.10.2026, marketing_spot.py): Abspann; beim Vecom-Spot Szenenfolge, Branchen-Zeilen und das goldene V. */
  spot?: { marke?: string; claim?: string; url?: string; montage?: string[]; etiketten?: string[]; endclip?: boolean } | null;
  /** Titelbild einer Partnerseite (03.10.2026, B1/B3/B4): nahtlose Schleife, Licht der Jahreszeit, eigene Länge und Pixel, nie Text im Bild. */
  kopf?: { schleife?: boolean; saison?: string; sekunden?: number; px?: string } | null };
export type DreiDAuftrag = MedienAuftrag & { drei_d: DreiD };

/** Repo-Wurzel (tools/akquise/src/ki → ../../../../). */
const WURZEL = fileURLToPath(new URL('../../../../', import.meta.url));
export const SKRIPTE = join(WURZEL, '3d-produktion', 'scripts');
export const UNREAL = join(WURZEL, '3d-produktion', 'unreal');

/** Grundbelichtung für Unreal in Blenden gegenüber ISO 100, 1/60 s, f/2,8
 *  (Probelauf 01.10.2026, Restaurant: +8,2 ≈ Blender). Je Film nachgemessen. */
export function unrealBlenden(): number {
  const v = Number(process.env.VECOM_UE_BLENDEN);
  return Number.isFinite(v) && process.env.VECOM_UE_BLENDEN ? v : 8.2;
}

/** Wie viele Blenden heller/dunkler, damit Unreal so hell wird wie Blenders
 *  Referenzbild (Mittelwerte in sRGB, Gamma 2,2). Höchstens ±2, auf 0,1. */
export function abgleichBlenden(b: any): number {
  const m = Number(b?.mittel), r = Number(b?.referenz_mittel);
  if (!(m > 0.002) || !(r > 0.002)) return 0;
  const ev = Math.log2(r / m) * 2.2;
  return Math.round(Math.max(-2, Math.min(2, ev)) * 10) / 10;
}

export function blenderPfad(): string {
  const env = process.env.BLENDER_EXE;
  if (env && existsSync(env)) return env;
  return process.platform === 'win32' ? 'C:\\Program Files\\Blender Foundation\\Blender 5.2\\blender.exe' : 'blender';
}

/** Seitenverhältnis → Pixel (kurze Kante 1080, wie Instagram und Reels). */
export function pixel(format: string, medium: 'bild' | 'video'): string {
  const tab: Record<string, string> = { '4:5': '1080x1350', '1:1': '1080x1080', '9:16': '1080x1920', '16:9': '1920x1080', '4:3': '1440x1080', '3:4': '1080x1440' };
  return tab[format] ?? (medium === 'video' ? '1080x1920' : '1080x1350');
}

/** Ist es ein Werbespot (mehrere Einstellungen) statt der einen Fahrt? */
export function istSpot(a: DreiDAuftrag): boolean {
  return a.medium === 'video' && (a.modell === 'spot' || !!a.drei_d.spot);
}

/** Musik für Spots: Dateien in 3d-produktion/musik (einmal mit tools/kie-ton.ps1 erzeugt, Guthaben vorher geprüft).
 *  Gewählt über die Zufallszahl — reproduzierbar. Ohne Ordner: Spot ohne Ton, der Bericht sagt es. */
export const MUSIK_STIMMUNG: Record<string, string> = { gastro: 'mediterraneo', wein: 'mediterraneo', salon: 'elegant', schmuck: 'elegant', schuh: 'elegant',
  kueche: 'elegant', vecom: 'elegant', auto: 'energie', mittelklasse: 'energie', kleinwagen: 'energie', lkw: 'energie' };

export function musikWahl(seed: number, ordner = join(WURZEL, '3d-produktion', 'musik'), studio = ''): string | null {
  try {
    const alle = readdirSync(ordner).filter((d) => /\.(mp3|wav|m4a|flac|ogg)$/i.test(d)).sort();
    // Erst die Stimmung der Szene (Dateiname spot-<stimmung>-n.mp3), sonst irgendeine
    const st = MUSIK_STIMMUNG[studio];
    const passend = st ? alle.filter((d) => d.startsWith(`spot-${st}`)) : [];
    const liste = passend.length ? passend : alle;
    return liste.length ? join(ordner, liste[Math.abs(seed) % liste.length]) : null;
  } catch { return null; }
}

/** Das gegossene goldene V (assets/video/intro-*) als Schluss des Vecom-Spots. */
export function endclipPfad(format: string): string {
  return join(WURZEL, 'assets', 'video', format === '16:9' ? 'intro-quer.mp4' : 'intro-hoch.mp4');
}

function spotTexte(a: DreiDAuftrag): Record<string, unknown> {
  const sp = a.drei_d.spot ?? {};
  const musik = musikWahl(a.drei_d.seed, undefined, a.drei_d.studio ?? '');
  return { marke: sp.marke ?? 'Vecom Design', claim: sp.claim ?? '', url: sp.url ?? a.drei_d.abspann ?? 'vecom-design.it',
    ...(sp.etiketten?.length ? { etiketten: sp.etiketten } : {}), ...(musik ? { musik } : {}),
    ...(sp.endclip ? { endclip: endclipPfad(a.format) } : {}) };
}

/** Der Auftrag fürs Blender-Skript (Texte bewusst als Datei, nicht über die Befehlszeile). */
export function blenderAuftrag(a: DreiDAuftrag, aus: string): Record<string, unknown> {
  const film = a.medium === 'video';
  const w = a.drei_d.wunsch ?? {};
  const fein = Object.fromEntries((['blick', 'naehe', 'stimmung'] as const).filter((k) => typeof w[k] === 'string' && w[k]).map((k) => [k, w[k] as string]));
  const k = a.drei_d.kopf;
  if (k) {
    // Titelbild: kein Titel, kein Abspann — der Text der Seite steht daneben, nicht im Bild.
    const px = /^\d{3,4}x\d{3,4}$/.test(k.px ?? '') ? (k.px as string) : pixel(a.format, a.medium);
    const saison = ['fruehling', 'sommer', 'herbst', 'winter'].includes(k.saison ?? '') ? { saison: k.saison } : {};
    const sek = Number(k.sekunden);
    return { px, seed: a.drei_d.seed, aus, ...fein, ...saison,
      ...(film ? { sekunden: sek >= 3 && sek <= 12 ? sek : 6, titel: '', abspann: '', ...(k.schleife ? { schleife: true } : {}) } : {}) };
  }
  return {
    px: pixel(a.format, a.medium), seed: a.drei_d.seed, aus, ...fein,
    ...(film ? { sekunden: 8, titel: a.drei_d.film_titel, abspann: a.drei_d.abspann || 'vecom-design.it' } : {}),
    ...(istSpot(a) ? { spot: spotTexte(a) } : {}),
  };
}

/** Blender im Hintergrund; Fortschritt aus der Ausgabe („FILM n / N“). */
function blenderLaufen(args: string[], logPfad: string, minuten: number, fortschritt: (n: number, von: number) => void, exe = blenderPfad()): Promise<number> {
  return new Promise((fertig, scheitern) => {
    const name = exe === blenderPfad() ? 'Blender' : 'Unreal';
    const p = spawn(exe, args, { windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
    const datei = createWriteStream(logPfad);
    const uhr = setTimeout(() => { p.kill(); scheitern(new Error(`${name} nach ${minuten} Minuten abgebrochen.`)); }, minuten * 60_000);
    const lesen = (d: Buffer) => {
      datei.write(d);
      const m = /FILM (\d+) \/ (\d+)/.exec(d.toString());
      if (m) fortschritt(Number(m[1]), Number(m[2]));
    };
    p.stdout.on('data', lesen); p.stderr.on('data', lesen);
    p.on('error', (e) => { clearTimeout(uhr); datei.end(); scheitern(new Error(`${name} ließ sich nicht starten: ` + e.message)); });
    p.on('close', (code) => { clearTimeout(uhr); datei.end(); fertig(code ?? -1); });
  });
}

/** Ein Satz für die Verwaltung aus dem Bericht des Skripts. */
export function berichtText(b: any, film: boolean): string {
  if (!b) return film ? 'Blender-Film fertig' : 'Blender-Bild fertig';
  if (b.einstellungen_n) {
    const ausweg = Array.isArray(b.einstellungen) ? b.einstellungen.filter((e: any) => e?.ausweg).map((e: any) => `${e.name} ${e.ausweg}`).join(', ') : '';
    return [b.vecom ? 'Vecom-Werbespot' : 'Werbespot', `${b.einstellungen_n} Einstellungen`, b.dauer_s ? `${String(b.dauer_s).replace('.', ',')} s` : '',
      b.was && !b.vecom ? `Szene ${b.was}` : '', ausweg ? `ausgewichen: ${ausweg}` : '', b.musik ? 'mit Musik' : 'ohne Musik (Ordner 3d-produktion/musik leer)',
      b.sekunden ? `${Math.round(b.sekunden / 60)} min gerechnet` : '', typeof b.mittel === 'number' ? `Belichtung gemessen ${String(b.mittel).replace('.', ',')}` : '', 'ohne Credits'].filter(Boolean).join(' · ');
  }
  const teile = [b.motor === 'unreal' ? 'Unreal-Film (Path Tracer)' : (film ? 'Blender-Film' : 'Blender-Bild'), `Szene ${b.was}`, b.variante ? `Variante ${b.variante}` : '',
    b.sekunden ? `${Math.round(b.sekunden)} s gerechnet` : '',
    typeof b.mittel === 'number' ? `Belichtung gemessen ${String(b.mittel).replace('.', ',')}` : (typeof b.probe_mittel === 'number' ? `Belichtung gemessen ${String(b.probe_mittel).replace('.', ',')}` : ''),
    b.korrektur_ev ? `nachgeführt ${b.korrektur_ev > 0 ? '+' : ''}${String(b.korrektur_ev).replace('.', ',')} EV` : '', 'ohne Credits'];
  return teile.filter(Boolean).join(' · ');
}

function jsonLesen(pfad: string): any {
  try { return JSON.parse(readFileSync(pfad, 'utf8')); } catch { return null; }
}

/** Vecom-Spot: je Branche eine Einstellung (eigener Blender-Lauf, nur Bilder), dann Schnitt mit goldenem V. Liefert den Bericht. */
async function vecomSpot(a: DreiDAuftrag, ordner: string, aus: string, melden: (t: string) => void): Promise<any> {
  const szenen = a.drei_d.spot?.montage ?? [];
  if (!szenen.length) throw new Error('Vecom-Spot ohne Szenen.');
  const px = pixel(a.format, 'video');
  const teile: string[] = [];
  const t0 = Date.now();
  for (const [i, studio] of szenen.entries()) {
    const teil = join(ordner, `mk-${a.id}-teil${i}`);
    const auftragPfad = `${teil}.auftrag.json`;
    writeFileSync(auftragPfad, JSON.stringify({ px, seed: a.drei_d.seed + i, aus: join(teil, 'teil.mp4'), ordner: teil, einstellungen: ['held'], sekunden_je: 2.6, nur_bilder: true }, null, 1));
    melden(`Einstellung ${i + 1} von ${szenen.length}: ${studio}`);
    await blenderLaufen(['-b', '-P', join(SKRIPTE, 'branchen_ort.py'), '--', studio, 'marketing_spot', `auftrag=${auftragPfad}`], `${teil}.log`, 60, () => {});
    if (!existsSync(join(teil, 'schnitte.json'))) throw new Error(`Einstellung ${studio} ohne Bilder — siehe daten/marketing/3d/mk-${a.id}-teil${i}.log`);
    teile.push(teil);
  }
  melden('Schnitt mit goldenem V');
  const schnitt = join(ordner, `mk-${a.id}.spot.json`);
  writeFileSync(schnitt, JSON.stringify({ modus: 'spot', ordner_liste: teile, aus, px: px.split('x').map(Number), fps: 24, titel: a.drei_d.film_titel, spot: spotTexte(a) }, null, 1));
  await blenderLaufen(['-b', '-P', join(SKRIPTE, 'marketing_schnitt.py'), '--', `auftrag=${schnitt}`], join(ordner, `mk-${a.id}.schnitt.log`), 30, () => {});
  if (!existsSync(aus) || statSync(aus).size < 10_000) throw new Error(`Schnitt ohne Film — siehe daten/marketing/3d/mk-${a.id}.schnitt.log`);
  return { ...(jsonLesen(aus.replace(/\.mp4$/, '.json')) ?? {}), vecom: true, sekunden: Math.round((Date.now() - t0) / 1000) };
}

/** Unreal-Film: Export, Probebild mit Belichtungsabgleich, ganzer Film. Liefert den Bericht. */
async function unrealLauf(a: DreiDAuftrag, ordner: string, studio: string, aus: string, melden: (t: string) => void): Promise<any> {
  const stamm = join(ordner, `mk-${a.id}`);
  const szene = `${stamm}.szene.json`;
  const auftragPfad = `${stamm}.unreal.json`;
  writeFileSync(auftragPfad, JSON.stringify(blenderAuftrag(a, szene), null, 1));
  melden('Blender baut die Szene für Unreal');
  await blenderLaufen(['-b', '-P', join(SKRIPTE, 'branchen_ort.py'), '--', studio, 'marketing_unreal', `auftrag=${auftragPfad}`],
    `${stamm}.export.log`, 20, () => {});
  if (!existsSync(szene)) throw new Error('Blender-Export für Unreal ohne Ergebnis.');
  const ps1 = join(UNREAL, 'ue-marketing.ps1');
  const ue = (extra: string[], log: string, minuten: number) => blenderLaufen(
    ['-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', ps1, '-Szene', szene, ...extra], log, minuten, () => {}, 'powershell.exe');
  const basis = unrealBlenden();
  melden('Unreal: Probebild und Belichtung');
  await ue(['-Aus', `${stamm}-probe.mp4`, '-NurBilder', '1', '-Spp', '16', '-Raumproben', '4', '-Blenden', String(basis)], `${stamm}.ue-probe.log`, 30);
  const probe = jsonLesen(`${stamm}-probe.json`);
  if (!probe) throw new Error('Unreal-Probebild ohne Ergebnis.');
  const nach = abgleichBlenden(probe);
  melden(`Unreal rechnet den Film (Belichtung ${nach >= 0 ? '+' : ''}${nach} EV nachgeführt)`);
  // Proben je Bild = Raumabtastung der Render Queue (das CVar zählt dort nicht; gemessen 01.10.2026: 8 → 0,13 s je Bild).
  await ue(['-Aus', aus, '-Spp', '32', '-Raumproben', '64', '-Blenden', String(Math.round((basis + nach) * 10) / 10)], `${stamm}.ue.log`, 360);
  if (!existsSync(aus) || statSync(aus).size < 10_000) throw new Error('Unreal endete ohne Film.');
  const b = jsonLesen(aus.replace(/\.mp4$/, '.json')) ?? {};
  return { ...b, was: studio, motor: 'unreal', probe_mittel: probe.mittel, korrektur_ev: nach, sekunden: b.render_sekunden ?? b.sekunden };
}

export async function dreiDLauf(a: DreiDAuftrag, szeneBauen?: (a: DreiDAuftrag, ordner: string) => Promise<string>): Promise<void> {
  const film = a.medium === 'video';
  log.info('marketing', `Auftrag #${a.id}: ${a.beschreibung} — ${film ? 'Blender-Film' : 'Blender-Bild'} auf dem PC`);
  const spot = istSpot(a);
  await api('status_melden', { art: 'marketing', stand: 0, ziel: spot ? 480 : (film ? 192 : 1), text: a.beschreibung }).catch(() => {});
  const ordner = datenOrdner('marketing', '3d');
  try {
    let skript = join(SKRIPTE, 'branchen_ort.py');
    let studio = a.drei_d.studio;
    let szene: string | null = null;
    if (!studio) {
      if (!szeneBauen) throw new Error('Für diese Branche gibt es keine 3D-Szene.');
      szene = await szeneBauen(a, ordner);        // Claude baut die Szene als Blender-Skript (ohne Werkzeuge)
      skript = join(SKRIPTE, 'marketing_szene.py');
      studio = 'claude';
    }
    if (spot && studio === 'claude') throw new Error('Werbespots gibt es nur für Branchen mit fertiger 3D-Szene.');
    if (!existsSync(skript)) throw new Error(`Blender-Skript fehlt: ${skript}`);
    const aus = join(ordner, `mk-${a.id}.${film ? 'mp4' : 'png'}`);
    let ueBericht: any = null;
    let ueFehler = '';
    if (spot && studio === 'vecom') {
      ueBericht = await vecomSpot(a, ordner, aus, (t) => {
        api('status_melden', { art: 'marketing', stand: 0, ziel: 1, text: `${a.beschreibung} — ${t}` }).catch(() => {});
      });
    } else if (film && a.modell === 'unreal' && a.drei_d.studio) {
      try {
        ueBericht = await unrealLauf(a, ordner, studio, aus, (t) => {
          api('status_melden', { art: 'marketing', stand: 0, ziel: 1, text: `${a.beschreibung} — ${t}` }).catch(() => {});
        });
      } catch (x) {
        ueFehler = (x as Error).message;
        log.fehler('marketing', `Auftrag #${a.id}: Unreal gescheitert (${ueFehler}) — Film entsteht mit Blender.`);
      }
    }
    if (!ueBericht) {
      const auftragPfad = join(ordner, `mk-${a.id}.auftrag.json`);
      writeFileSync(auftragPfad, JSON.stringify({ ...blenderAuftrag(a, aus), ...(szene ? { szene } : {}) }, null, 1));
      let zuletzt = 0;
      const code = await blenderLaufen(['-b', '-P', skript, '--', studio, spot ? 'marketing_spot' : (film ? 'marketing_film' : 'marketing'), `auftrag=${auftragPfad}`],
        join(ordner, `mk-${a.id}.log`), spot ? 420 : (film ? 360 : 40), (n, von) => {
          if (Date.now() - zuletzt < 60_000) return;
          zuletzt = Date.now();
          api('status_melden', { art: 'marketing', stand: n, ziel: von, text: `${a.beschreibung} — Bild ${n} von ${von}` }).catch(() => {});
        });
      if (!existsSync(aus) || statSync(aus).size < 10_000) throw new Error(`Blender endete ohne Ergebnis (Code ${code}) — siehe daten/marketing/3d/mk-${a.id}.log`);
    }
    const datei = readFileSync(aus);
    if (datei.length > (a.max_bytes || 60 * 1024 * 1024)) throw new Error(`Datei zu groß (${Math.round(datei.length / 1048576)} MB).`);
    const bericht: any = ueBericht ?? jsonLesen(aus.replace(/\.(png|mp4)$/, '.json'));
    await hochladen(a, datei, {});
    const text = berichtText(bericht, film) + (a.modell === 'unreal' && !ueBericht ? ` (Unreal gescheitert, mit Blender gerechnet: ${ueFehler.slice(0, 160) || 'keine 3D-Szene'})` : '');
    await api('marketing_auftrag_melden', { id: a.id, ok: true, text });
    log.info('marketing', `Auftrag #${a.id}: ${text}`);
  } catch (x) {
    const grund = (x as Error).message;
    log.fehler('marketing', `Auftrag #${a.id}: ${grund}`);
    await api('marketing_auftrag_melden', { id: a.id, ok: false, text: grund.slice(0, 900) }).catch(() => {});
  }
}
