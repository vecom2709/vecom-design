/* Die Branchenliste kommt aus derselben Datei wie in der Verwaltung
   (app/src/akquise_branchen.json) -- eine Wahrheit, zwei Leser. */
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { WURZEL } from './konfig.js';

export interface Branche {
  de: string; it: string; en: string;
  tourismus: boolean;
  experience: number;
  osm: string[];
  pflicht: string[];
  experience_idee?: string;
  name_muster?: string;
  berufsrecht?: boolean;
}

const roh = JSON.parse(readFileSync(join(WURZEL, '..', '..', 'app', 'src', 'akquise_branchen.json'), 'utf8'));
delete roh._hinweis;
export const BRANCHEN: Record<string, Branche> = roh;

/** Ein Selektor "k=v&k2~regex&k3" als Liste von Bedingungen. */
export interface Bedingung { schluessel: string; art: '=' | '~' | 'da'; wert?: string }

export function selektorLesen(s: string): Bedingung[] {
  return s.split('&').map((teil) => {
    const m = teil.match(/^([a-z_:]+)(=|~)(.+)$/i);
    if (m) return { schluessel: m[1], art: m[2] as '=' | '~', wert: m[3] };
    return { schluessel: teil, art: 'da' as const };
  });
}

/** Overpass-Filter, z. B. ["amenity"="restaurant"]["name"] */
export function overpassFilter(s: string): string {
  return selektorLesen(s).map((b) =>
    b.art === 'da' ? `["${b.schluessel}"]` : b.art === '=' ? `["${b.schluessel}"="${b.wert}"]` : `["${b.schluessel}"~"${b.wert}"]`,
  ).join('') + '["name"]';
}

export function passt(tags: Record<string, string>, s: string): boolean {
  return selektorLesen(s).every((b) => {
    const v = tags[b.schluessel];
    if (v === undefined) return false;
    if (b.art === 'da') return true;
    if (b.art === '=') return v === b.wert;
    return new RegExp(b.wert!).test(v);
  });
}

/** Ordnet ein OSM-Element einer Branche zu. Namensmuster zuerst (Agriturismo vor Gaestehaus). */
export function brancheFuer(tags: Record<string, string>, erlaubt?: string[]): string | null {
  const liste = Object.entries(BRANCHEN).filter(([k]) => !erlaubt || erlaubt.length === 0 || erlaubt.includes(k));
  // Branchen mit Namensmuster zuerst: ihre Selektoren sind enger (Agriturismo vor Gaestehaus).
  for (const [k, b] of liste) {
    if (b.name_muster && b.osm.some((s) => passt(tags, s))) return k;
  }
  for (const [k, b] of liste) {
    if (b.name_muster) continue;
    if (b.osm.some((s) => passt(tags, s))) return k;
  }
  return null;
}
