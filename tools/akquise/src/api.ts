/* Die Tuer zur Verwaltung (akquise.php). Nur melden und holen --
   eine Aktion zum Senden gibt es dort nicht, also auch hier nicht. */
import { konfig } from './konfig.js';
import { log } from './log.js';
import { warten } from './hilfen.js';

export class ApiFehler extends Error {}

export async function api<T = any>(aktion: string, daten: Record<string, unknown> = {}): Promise<T> {
  if (!konfig.schluessel) {
    throw new ApiFehler('AKQUISE_SCHLUESSEL fehlt in tools/akquise/.env — in der Verwaltung unter „Compliance & Versand" erzeugen.');
  }
  if (konfig.trocken && !['hallo', 'lauf_holen', 'audits_holen', 'texte_holen'].includes(aktion)) {
    log.info('trocken', `würde „${aktion}" melden`, { bytes: JSON.stringify(daten).length });
    return { ok: true, trocken: true } as T;
  }
  let letzter: unknown;
  for (let versuch = 1; versuch <= 4; versuch++) {
    try {
      const antwort = await fetch(konfig.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Vecom-Akquise': konfig.schluessel, 'User-Agent': konfig.botKennung },
        body: JSON.stringify({ aktion, ...daten }),
        signal: AbortSignal.timeout(60_000),
      });
      const text = await antwort.text();
      let json: any;
      try { json = JSON.parse(text); } catch { throw new ApiFehler(`Keine JSON-Antwort (${antwort.status}): ${text.slice(0, 200)}`); }
      if (antwort.status === 429 || antwort.status >= 500) {
        throw new Error(`Server ${antwort.status}: ${json?.hinweis ?? ''}`);
      }
      if (!json.ok) throw new ApiFehler(`${aktion}: ${json.hinweis ?? 'abgelehnt'} (${antwort.status})`);
      return json as T;
    } catch (e) {
      letzter = e;
      if (e instanceof ApiFehler) throw e;
      const pause = 2000 * versuch * versuch;
      log.warn('api', `${aktion} — Versuch ${versuch} gescheitert, neuer Anlauf in ${pause / 1000}s: ${(e as Error).message}`);
      await warten(pause);
    }
  }
  throw new ApiFehler(`${aktion}: Verwaltung nicht erreichbar — ${(letzter as Error)?.message}`);
}
