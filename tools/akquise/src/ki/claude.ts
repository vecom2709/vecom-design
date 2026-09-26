/* ==========================================================================
   Claude — nur fuer das, was keine Maschine kann: Deutung, Design- und
   UX-Einschaetzung am Bildschirmfoto, branchengerechte Loesung, Text.

   KOSTENKONTROLLE
   - nur Firmen ab Score-Grenze (Verwaltung liefert sie ueber texte_holen)
   - ein Aufruf je Firma fuer Deutung UND Text
   - Ergebnis zwischengespeichert (daten/cache) -- ein zweiter Lauf kostet nichts
   - harte Obergrenze an Tokens je Lauf (AKQUISE_KI_MAX_TOKEN)
   ========================================================================== */
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { createHash } from 'node:crypto';
import { datenOrdner, konfig } from '../konfig.js';
import { log } from '../log.js';

let verbraucht = 0;
export const kiVerbrauch = () => verbraucht;
export const kiMoeglich = () => !!konfig.anthropicKey && verbraucht < konfig.kiMaxToken;

export interface Nachricht { role: 'user' | 'assistant'; content: any }

export async function claude(system: string, nachrichten: Nachricht[], maxToken = 2500): Promise<{ text: string; ein: number; aus: number }> {
  if (!konfig.anthropicKey) throw new Error('ANTHROPIC_API_KEY fehlt.');
  if (verbraucht >= konfig.kiMaxToken) throw new Error(`Token-Obergrenze für diesen Lauf erreicht (${verbraucht}).`);
  for (let versuch = 1; versuch <= 3; versuch++) {
    const r = await fetch('https://api.anthropic.com/v1/messages', {
      method: 'POST',
      headers: { 'content-type': 'application/json', 'x-api-key': konfig.anthropicKey, 'anthropic-version': '2023-06-01' },
      body: JSON.stringify({ model: konfig.modell, max_tokens: maxToken, system, messages: nachrichten }),
      signal: AbortSignal.timeout(180_000),
    });
    if (r.status === 429 || r.status === 529 || r.status >= 500) {
      log.warn('claude', `API ${r.status} — neuer Versuch in ${20 * versuch}s`);
      await new Promise((ok) => setTimeout(ok, 20_000 * versuch));
      continue;
    }
    const j: any = await r.json();
    if (!r.ok) throw new Error(`Claude ${r.status}: ${j?.error?.message ?? JSON.stringify(j).slice(0, 200)}`);
    const ein = j.usage?.input_tokens ?? 0, aus = j.usage?.output_tokens ?? 0;
    verbraucht += ein + aus;
    const text = (j.content ?? []).filter((c: any) => c.type === 'text').map((c: any) => c.text).join('');
    return { text, ein, aus };
  }
  throw new Error('Claude nicht erreichbar.');
}

/** JSON aus einer Antwort holen -- auch wenn Claude es in ```json einpackt. */
export function jsonAus(text: string): any {
  const m = text.match(/```(?:json)?\s*([\s\S]*?)```/);
  const roh = (m ? m[1] : text).trim();
  const start = roh.indexOf('{');
  const ende = roh.lastIndexOf('}');
  return JSON.parse(roh.slice(start, ende + 1));
}

export function cacheSchluessel(...teile: unknown[]): string {
  return createHash('sha256').update(JSON.stringify(teile)).digest('hex').slice(0, 24);
}
export function cacheLesen<T>(schluessel: string): T | null {
  const p = join(datenOrdner('cache'), schluessel + '.json');
  return existsSync(p) ? (JSON.parse(readFileSync(p, 'utf8')) as T) : null;
}
export function cacheSchreiben(schluessel: string, wert: unknown): void {
  writeFileSync(join(datenOrdner('cache'), schluessel + '.json'), JSON.stringify(wert, null, 1));
}
