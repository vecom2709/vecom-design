/* ==========================================================================
   Bilder und Videos über Kie.ai (Marketing-Studio Schritt 3, 01.10.2026,
   Uwe: „Bilder und Videos über kie.ai, ansonsten Blender und Unreal Engine“).

   Der Schlüssel gehört Uwe und steht NUR in seiner Umgebung (Benutzer-
   Umgebungsvariable KIE_API_KEY, wie bei tools/kie-ton.ps1). Er wird hier
   gelesen, nie ausgegeben, nie gespeichert, nie an die Verwaltung geschickt.
   Auf den Server kommt nur die fertige Datei.

   Jeder Lauf fragt ZUERST das Guthaben ab und bricht ab, wenn es nicht
   reicht. Danach steht der echte Verbrauch beim Bild.

     Bild:  Nano Banana Pro   POST /api/v1/jobs/createTask   → /api/v1/jobs/recordInfo
     Video: Veo 3.1           POST /api/v1/veo/generate      → /api/v1/veo/record-info
   ========================================================================== */
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { api } from '../api.js';
import { datenOrdner } from '../konfig.js';
import { log } from '../log.js';
import { warten } from '../hilfen.js';

const BASIS = 'https://api.kie.ai';

export type MedienAuftrag = {
  id: number; art: 'medien'; beschreibung: string; medium: 'bild' | 'video'; modell: string; format: string;
  prompt: string; startbild: string | null; credits_ca: number; teil_bytes: number; max_bytes: number;
};

/** Der Schlüssel aus Uwes Umgebung — Prozess, sonst Benutzer-Umgebung (Windows). Nie ausgeben. */
function schluessel(): string {
  if (process.env.KIE_API_KEY) return process.env.KIE_API_KEY.trim();
  if (process.platform === 'win32') {
    try {
      return execFileSync('powershell', ['-NoProfile', '-Command', "[Environment]::GetEnvironmentVariable('KIE_API_KEY','User')"],
        { encoding: 'utf8', windowsHide: true, timeout: 20_000 }).trim();
    } catch { return ''; }
  }
  return '';
}

async function kie(pfad: string, s: string, body?: unknown): Promise<any> {
  const r = await fetch(BASIS + pfad, {
    method: body ? 'POST' : 'GET',
    headers: { Authorization: `Bearer ${s}`, 'Content-Type': 'application/json' },
    body: body ? JSON.stringify(body) : undefined,
    signal: AbortSignal.timeout(60_000),
  });
  let j: any = null;
  try { j = await r.json(); } catch { throw new Error(`Kie.ai antwortet nicht mit JSON (HTTP ${r.status}).`); }
  if (j?.code !== 200) throw new Error(`Kie.ai: ${String(j?.msg ?? 'abgelehnt').slice(0, 200)} (Code ${j?.code ?? r.status})`);
  return j.data;
}

export async function guthaben(s: string): Promise<number> {
  const d = await kie('/api/v1/chat/credit', s);
  const n = Number(d);
  if (!Number.isFinite(n)) throw new Error('Kie.ai: Guthaben nicht lesbar.');
  return n;
}

/** Bild mit Nano Banana Pro. @returns [Adresse, verbrauchte Credits | null] */
async function bild(s: string, a: MedienAuftrag): Promise<[string, number | null]> {
  const anlegen = (format: string) => kie('/api/v1/jobs/createTask', s, {
    model: a.modell || 'nano-banana-pro',
    input: { prompt: a.prompt, image_input: [], aspect_ratio: a.format, resolution: '2K', output_format: format },
  });
  let d: any;
  try { d = await anlegen('jpg'); } catch (x) {
    if (!/format/i.test((x as Error).message)) throw x;
    d = await anlegen('png');
  }
  const id = String(d?.taskId ?? '');
  if (!id) throw new Error('Kie.ai: keine Auftragsnummer.');
  for (let i = 0; i < 90; i++) {
    await warten(5_000);
    const st = await kie(`/api/v1/jobs/recordInfo?taskId=${encodeURIComponent(id)}`, s);
    if (st?.state === 'success') {
      const urls = JSON.parse(String(st.resultJson ?? '{}')).resultUrls ?? [];
      if (!urls[0]) throw new Error('Kie.ai: fertig, aber ohne Datei.');
      return [String(urls[0]), Number.isFinite(Number(st.creditsConsumed)) ? Number(st.creditsConsumed) : null];
    }
    if (st?.state === 'fail') throw new Error('Kie.ai: Bild nicht erzeugt — ' + String(st.failMsg ?? '').slice(0, 200));
  }
  throw new Error('Kie.ai: Bild nach 7 Minuten nicht fertig.');
}

/** Video mit Veo 3.1 (8 s mit Ton); mit gewähltem Bild als erstem Frame, wenn vorhanden. */
async function video(s: string, a: MedienAuftrag): Promise<[string, number | null]> {
  const d = await kie('/api/v1/veo/generate', s, {
    prompt: a.prompt, model: a.modell || 'veo3_fast', aspect_ratio: a.format === '16:9' ? '16:9' : '9:16', enableTranslation: true,
    generationType: a.startbild ? 'FIRST_AND_LAST_FRAMES_2_VIDEO' : 'TEXT_2_VIDEO', ...(a.startbild ? { imageUrls: [a.startbild] } : {}),
  });
  const id = String(d?.taskId ?? '');
  if (!id) throw new Error('Kie.ai: keine Auftragsnummer.');
  for (let i = 0; i < 100; i++) {
    await warten(10_000);
    const st = await kie(`/api/v1/veo/record-info?taskId=${encodeURIComponent(id)}`, s);
    const flag = Number(st?.successFlag);
    if (flag === 1) {
      const urls = st?.response?.resultUrls ?? [];
      if (!urls[0]) throw new Error('Kie.ai: Video fertig, aber ohne Datei.');
      return [String(urls[0]), null];
    }
    if (flag === 2 || flag === 3) throw new Error('Kie.ai: Video nicht erzeugt — ' + String(st?.errorMessage ?? '').slice(0, 200));
  }
  throw new Error('Kie.ai: Video nach 16 Minuten nicht fertig.');
}

/** Datei in Stücken über die Worker-Tür hochladen (die Tür liest höchstens 6 MB je Anfrage). */
export async function hochladen(a: { id: number; teil_bytes: number }, datei: Buffer, extra: Record<string, unknown>): Promise<number> {
  const sha = createHash('sha256').update(datei).digest('hex');
  const groesse = Math.max(256 * 1024, Math.min(a.teil_bytes || 3 * 1024 * 1024, 3 * 1024 * 1024));
  const von = Math.max(1, Math.ceil(datei.length / groesse));
  let id = 0;
  for (let teil = 1; teil <= von; teil++) {
    const stueck = datei.subarray((teil - 1) * groesse, teil * groesse);
    const j = await api('marketing_medium_teil', { auftrag_id: a.id, teil, von, daten: stueck.toString('base64'), sha256: sha, ...(teil === von ? extra : {}) });
    if (teil === von) id = Number(j.id ?? 0);
  }
  return id;
}

export async function medienLauf(a: MedienAuftrag): Promise<void> {
  log.info('marketing', `Auftrag #${a.id}: ${a.beschreibung} — Kie.ai`);
  await api('status_melden', { art: 'marketing', stand: 0, ziel: 1, text: a.beschreibung }).catch(() => {});
  try {
    const s = schluessel();
    if (!s) throw new Error('Kein KIE_API_KEY in deiner Benutzer-Umgebung — Windows: „Umgebungsvariablen für dieses Konto bearbeiten“ → Neu → KIE_API_KEY.');
    const vorher = await guthaben(s);
    const noetig = Math.ceil((a.credits_ca || 30) * 1.2);
    if (vorher < noetig) throw new Error(`Kie-Guthaben ${vorher} Credits — zu wenig für etwa ${a.credits_ca} Credits. Bitte bei Kie.ai aufladen.`);
    const [url, verbrauchKie] = a.medium === 'video' ? await video(s, a) : await bild(s, a);
    const r = await fetch(url, { signal: AbortSignal.timeout(300_000) });
    if (!r.ok) throw new Error(`Datei bei Kie.ai nicht abholbar (HTTP ${r.status}).`);
    const datei = Buffer.from(await r.arrayBuffer());
    if (datei.length < 1000 || datei.length > (a.max_bytes || 60 * 1024 * 1024)) throw new Error(`Datei hat eine unerwartete Größe (${datei.length} Bytes).`);
    const nachher = await guthaben(s).catch(() => NaN);
    const verbrauch = verbrauchKie ?? (Number.isFinite(nachher) ? Math.round((vorher - nachher) * 100) / 100 : null);
    writeFileSync(join(datenOrdner('marketing'), `medium-${a.id}.${a.medium === 'video' ? 'mp4' : 'bild'}`), datei);
    await hochladen(a, datei, { quelle_url: url, credits: verbrauch });
    const text = `${a.medium === 'video' ? 'Video' : 'Bild'} fertig${verbrauch !== null ? ` · ${verbrauch} Credits` : ''}${Number.isFinite(nachher) ? ` · Guthaben danach ${nachher}` : ''}`;
    await api('marketing_auftrag_melden', { id: a.id, ok: true, text });
    log.info('marketing', `Auftrag #${a.id}: ${text}`);
  } catch (x) {
    const grund = (x as Error).message;
    log.fehler('marketing', `Auftrag #${a.id}: ${grund}`);
    await api('marketing_auftrag_melden', { id: a.id, ok: false, text: grund.slice(0, 900) }).catch(() => {});
  }
}
