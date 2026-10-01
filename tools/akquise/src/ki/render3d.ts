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
   aus der Bildidee (ki/szene3d.ts) — nur für Bilder.
   ========================================================================== */
import { spawn } from 'node:child_process';
import { existsSync, readFileSync, statSync, writeFileSync, createWriteStream } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { api } from '../api.js';
import { datenOrdner } from '../konfig.js';
import { log } from '../log.js';
import { hochladen, type MedienAuftrag } from './kie.js';

export type DreiD = { studio: string | null; generativ: boolean; seed: number; sprache: string; film_titel: string; abspann: string };
export type DreiDAuftrag = MedienAuftrag & { drei_d: DreiD };

/** Repo-Wurzel (tools/akquise/src/ki → ../../../../). */
const WURZEL = fileURLToPath(new URL('../../../../', import.meta.url));
export const SKRIPTE = join(WURZEL, '3d-produktion', 'scripts');

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

/** Der Auftrag fürs Blender-Skript (Texte bewusst als Datei, nicht über die Befehlszeile). */
export function blenderAuftrag(a: DreiDAuftrag, aus: string): Record<string, unknown> {
  const film = a.medium === 'video';
  return {
    px: pixel(a.format, a.medium), seed: a.drei_d.seed, aus,
    ...(film ? { sekunden: 8, titel: a.drei_d.film_titel, abspann: a.drei_d.abspann || 'vecom-design.it' } : {}),
  };
}

/** Blender im Hintergrund; Fortschritt aus der Ausgabe („FILM n / N“). */
function blenderLaufen(args: string[], logPfad: string, minuten: number, fortschritt: (n: number, von: number) => void): Promise<number> {
  return new Promise((fertig, scheitern) => {
    const p = spawn(blenderPfad(), args, { windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
    const datei = createWriteStream(logPfad);
    const uhr = setTimeout(() => { p.kill(); scheitern(new Error(`Blender nach ${minuten} Minuten abgebrochen.`)); }, minuten * 60_000);
    const lesen = (d: Buffer) => {
      datei.write(d);
      const m = /FILM (\d+) \/ (\d+)/.exec(d.toString());
      if (m) fortschritt(Number(m[1]), Number(m[2]));
    };
    p.stdout.on('data', lesen); p.stderr.on('data', lesen);
    p.on('error', (e) => { clearTimeout(uhr); datei.end(); scheitern(new Error('Blender ließ sich nicht starten: ' + e.message)); });
    p.on('close', (code) => { clearTimeout(uhr); datei.end(); fertig(code ?? -1); });
  });
}

/** Ein Satz für die Verwaltung aus dem Bericht des Skripts. */
export function berichtText(b: any, film: boolean): string {
  if (!b) return film ? 'Blender-Film fertig' : 'Blender-Bild fertig';
  const teile = [film ? 'Blender-Film' : 'Blender-Bild', `Szene ${b.was}`, b.variante ? `Variante ${b.variante}` : '',
    b.sekunden ? `${Math.round(b.sekunden)} s gerechnet` : '',
    typeof b.mittel === 'number' ? `Belichtung gemessen ${String(b.mittel).replace('.', ',')}` : (typeof b.probe_mittel === 'number' ? `Belichtung gemessen ${String(b.probe_mittel).replace('.', ',')}` : ''),
    b.korrektur_ev ? `nachgeführt ${b.korrektur_ev > 0 ? '+' : ''}${String(b.korrektur_ev).replace('.', ',')} EV` : '', 'ohne Credits'];
  return teile.filter(Boolean).join(' · ');
}

export async function dreiDLauf(a: DreiDAuftrag, szeneBauen?: (a: DreiDAuftrag, ordner: string) => Promise<string>): Promise<void> {
  const film = a.medium === 'video';
  log.info('marketing', `Auftrag #${a.id}: ${a.beschreibung} — ${film ? 'Blender-Film' : 'Blender-Bild'} auf dem PC`);
  await api('status_melden', { art: 'marketing', stand: 0, ziel: film ? 192 : 1, text: a.beschreibung }).catch(() => {});
  const ordner = datenOrdner('marketing', '3d');
  try {
    if (a.modell === 'unreal') log.info('marketing', 'Unreal ist noch im Probelauf — dieser Film entsteht mit Blender.');
    let skript = join(SKRIPTE, 'branchen_ort.py');
    let studio = a.drei_d.studio;
    let szene: string | null = null;
    if (!studio) {
      if (film || !szeneBauen) throw new Error('Für diese Branche gibt es keine 3D-Szene — Videos dafür über Kie.ai.');
      szene = await szeneBauen(a, ordner);        // Claude baut die Szene als Blender-Skript (ohne Werkzeuge)
      skript = join(SKRIPTE, 'marketing_szene.py');
      studio = 'claude';
    }
    if (!existsSync(skript)) throw new Error(`Blender-Skript fehlt: ${skript}`);
    const aus = join(ordner, `mk-${a.id}.${film ? 'mp4' : 'png'}`);
    const auftragPfad = join(ordner, `mk-${a.id}.auftrag.json`);
    writeFileSync(auftragPfad, JSON.stringify({ ...blenderAuftrag(a, aus), ...(szene ? { szene } : {}) }, null, 1));
    let zuletzt = 0;
    const code = await blenderLaufen(['-b', '-P', skript, '--', studio, film ? 'marketing_film' : 'marketing', `auftrag=${auftragPfad}`],
      join(ordner, `mk-${a.id}.log`), film ? 360 : 40, (n, von) => {
        if (Date.now() - zuletzt < 60_000) return;
        zuletzt = Date.now();
        api('status_melden', { art: 'marketing', stand: n, ziel: von, text: `${a.beschreibung} — Bild ${n} von ${von}` }).catch(() => {});
      });
    if (!existsSync(aus) || statSync(aus).size < 10_000) throw new Error(`Blender endete ohne Ergebnis (Code ${code}) — siehe daten/marketing/3d/mk-${a.id}.log`);
    const datei = readFileSync(aus);
    if (datei.length > (a.max_bytes || 60 * 1024 * 1024)) throw new Error(`Datei zu groß (${Math.round(datei.length / 1048576)} MB).`);
    let bericht: any = null;
    try { bericht = JSON.parse(readFileSync(aus.replace(/\.(png|mp4)$/, '.json'), 'utf8')); } catch { /* ohne Bericht */ }
    await hochladen(a, datei, {});
    const text = berichtText(bericht, film) + (a.modell === 'unreal' ? ' (Unreal noch im Probelauf, mit Blender gerechnet)' : '');
    await api('marketing_auftrag_melden', { id: a.id, ok: true, text });
    log.info('marketing', `Auftrag #${a.id}: ${text}`);
  } catch (x) {
    const grund = (x as Error).message;
    log.fehler('marketing', `Auftrag #${a.id}: ${grund}`);
    await api('marketing_auftrag_melden', { id: a.id, ok: false, text: grund.slice(0, 900) }).catch(() => {});
  }
}
