/* ==========================================================================
   Firmenrecherche ueber OpenStreetMap (Overpass API).

   WARUM OPENSTREETMAP
   Offene Daten unter ODbL, abfragbar nach amtlichen Gebieten (Bundesland,
   Landkreis, Gemeinde / Regione, Provincia, Comune) und nach Branche --
   ohne Google-Karten abzugrasen, deren Nutzungsbedingungen das verbieten.
   Die Quelle steht an jeder Firma (osm:node/123 + Lizenz), damit jederzeit
   nachvollziehbar ist, woher eine Angabe kommt.

   FAIR BLEIBEN
   Eine Abfrage je Gemeinde, dazwischen eine Pause (Vorgabe 12 s). Bei 429
   oder 504 wartet der Worker laenger und versucht es hoechstens dreimal.
   Eine Region mit 390 Gemeinden dauert damit gut eine Stunde -- das ist
   Absicht, nicht Schwaeche.
   ========================================================================== */
import { konfig } from '../konfig.js';
import { log } from '../log.js';
import { warten } from '../hilfen.js';
import { BRANCHEN, overpassFilter, brancheFuer } from '../branchen.js';

export interface OsmElement {
  type: 'node' | 'way' | 'relation';
  id: number;
  lat?: number; lon?: number;
  center?: { lat: number; lon: number };
  tags?: Record<string, string>;
}

export interface Gebiet { name: string; osmId: number; region?: string; kreis?: string; lat?: number; lon?: number }

export interface GefundeneFirma {
  name: string; land: 'DE' | 'IT'; region?: string; kreis?: string; stadt?: string; plz?: string;
  adresse?: string; lat?: number; lon?: number; url?: string; telefon?: string; email?: string;
  branche: string; unternehmensart: string; quelle: string; quelle_lizenz: string;
}

const LIZENZ = 'ODbL (© OpenStreetMap-Mitwirkende)';
const EBENE: Record<string, string> = { region: '4', kreis: '6', stadt: '8' };
let letzteAbfrage = 0;

export async function overpass(abfrage: string): Promise<OsmElement[]> {
  for (let versuch = 1; versuch <= 3; versuch++) {
    const noch = letzteAbfrage + konfig.overpassPauseMs - Date.now();
    if (noch > 0) await warten(noch);
    letzteAbfrage = Date.now();
    try {
      const r = await fetch(konfig.overpass, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'User-Agent': konfig.botKennung },
        body: 'data=' + encodeURIComponent(abfrage),
        signal: AbortSignal.timeout(200_000),
      });
      if (r.status === 429 || r.status === 504 || r.status === 503) {
        const pause = 60_000 * versuch;
        log.warn('overpass', `Server ausgelastet (${r.status}) — warte ${pause / 1000}s`);
        await warten(pause);
        continue;
      }
      if (!r.ok) throw new Error(`Overpass ${r.status}: ${(await r.text()).slice(0, 200)}`);
      const j = (await r.json()) as { elements?: OsmElement[]; remark?: string };
      if (j.remark && /runtime error|timed out/i.test(j.remark)) throw new Error('Overpass: ' + j.remark);
      return j.elements ?? [];
    } catch (e) {
      if (versuch === 3) throw e;
      log.warn('overpass', `Versuch ${versuch} gescheitert: ${(e as Error).message}`);
      await warten(20_000 * versuch);
    }
  }
  return [];
}

const landArea = (land: string) => `area["ISO3166-1"="${land}"]["admin_level"="2"]->.land;`;
const esc = (s: string) => s.replace(/\\/g, '\\\\').replace(/"/g, '\\"');

/** Sucht Verwaltungsgebiete einer Ebene mit diesem Namen. Erst exakt, dann tolerant (z. B. "Libero consorzio … di Agrigento"). */
export async function gebieteFinden(land: string, ebene: 'region' | 'kreis' | 'stadt', name: string): Promise<Gebiet[]> {
  const lvl = EBENE[ebene];
  const lvls = land === 'DE' && ebene === 'stadt' ? '^(8|6)$' : `^${lvl}$`;   // kreisfreie Staedte liegen in DE auf Ebene 6
  for (const filter of [`["name"="${esc(name)}"]`, `["name"~"(^|[ '])${esc(name)}$",i]`]) {
    const els = await overpass(`[out:json][timeout:90];${landArea(land)}
      rel(area.land)["boundary"="administrative"]["admin_level"~"${lvls}"]${filter};
      out tags center;`);
    if (els.length) {
      return els.map((e) => ({ name: e.tags?.name ?? name, osmId: e.id, lat: e.center?.lat, lon: e.center?.lon }));
    }
  }
  return [];
}

/** Unter-Gebiete (Provinzen einer Region, Gemeinden einer Provinz). */
export async function untergebiete(gebiet: Gebiet, ebene: 'kreis' | 'stadt'): Promise<Gebiet[]> {
  const els = await overpass(`[out:json][timeout:120];
    rel(${gebiet.osmId});map_to_area->.g;
    rel(area.g)["boundary"="administrative"]["admin_level"="${EBENE[ebene]}"]["name"];
    out tags center;`);
  const gesehen = new Set<number>();
  return els.filter((e) => !gesehen.has(e.id) && gesehen.add(e.id))
    .map((e) => ({ name: e.tags!.name, osmId: e.id, lat: e.center?.lat, lon: e.center?.lon }))
    .sort((a, b) => a.name.localeCompare(b.name));
}

/** In welcher Region und welchem Kreis liegt ein Punkt? */
export async function einordnen(lat: number, lon: number): Promise<{ region?: string; kreis?: string }> {
  const els = await overpass(`[out:json][timeout:60];is_in(${lat},${lon})->.a;
    area.a["boundary"="administrative"]["admin_level"~"^(4|6)$"];out tags;`);
  const out: { region?: string; kreis?: string } = {};
  for (const e of els) {
    if (e.tags?.admin_level === '4') out.region = e.tags.name;
    if (e.tags?.admin_level === '6') out.kreis = e.tags.name;
  }
  return out;
}

/** Alle Betriebe der gewuenschten Branchen in einem Gebiet. */
export async function betriebeIn(gebiet: Gebiet, branchen: string[]): Promise<OsmElement[]> {
  const liste = branchen.length ? branchen : Object.keys(BRANCHEN);
  const teile = liste.flatMap((b) => BRANCHEN[b]?.osm ?? []).map((s) => `nwr${overpassFilter(s)}(area.g);`);
  return overpass(`[out:json][timeout:180];rel(${gebiet.osmId});map_to_area->.g;(${teile.join('')});out center tags;`);
}

/** Betriebe mit einer bestimmten PLZ/CAP (OSM-Adressfeld). */
export async function betriebeMitPlz(land: string, plz: string, branchen: string[]): Promise<OsmElement[]> {
  const liste = branchen.length ? branchen : Object.keys(BRANCHEN);
  const teile = liste.flatMap((b) => BRANCHEN[b]?.osm ?? []).map((s) => `nwr${overpassFilter(s)}["addr:postcode"="${esc(plz)}"](area.land);`);
  return overpass(`[out:json][timeout:180];${landArea(land)}(${teile.join('')});out center tags;`);
}

/** Aus einem OSM-Element eine Firma fuer die Verwaltung. Null, wenn es keiner Branche zugeordnet werden kann. */
export function alsFirma(e: OsmElement, land: 'DE' | 'IT', ort: { region?: string; kreis?: string; stadt?: string }, branchen: string[]): GefundeneFirma | null {
  const t = e.tags ?? {};
  if (!t.name) return null;
  const branche = brancheFuer(t, branchen);
  if (!branche) return null;
  // Geschlossene Betriebe nicht aufnehmen.
  if (t['disused:shop'] || t['disused:amenity'] || t.disused === 'yes' || t['abandoned'] === 'yes') return null;
  const strasse = [t['addr:street'] ?? t['addr:place'], t['addr:housenumber']].filter(Boolean).join(' ');
  const url = t.website ?? t['contact:website'] ?? t.url ?? t['brand:website'];
  const art = Object.entries(t).find(([k]) => ['amenity', 'shop', 'tourism', 'craft', 'office', 'leisure', 'healthcare', 'man_made'].includes(k));
  return {
    name: t.name,
    land,
    region: ort.region,
    kreis: ort.kreis,
    stadt: t['addr:city'] ?? ort.stadt,
    plz: t['addr:postcode'],
    adresse: strasse || undefined,
    lat: e.lat ?? e.center?.lat,
    lon: e.lon ?? e.center?.lon,
    url: url ? (/^https?:\/\//i.test(url) ? url : 'http://' + url) : undefined,
    telefon: t.phone ?? t['contact:phone'] ?? t['contact:mobile'],
    email: t.email ?? t['contact:email'],
    branche,
    unternehmensart: art ? `${art[0]}=${art[1]}` : '',
    quelle: `osm:${e.type}/${e.id}`,
    quelle_lizenz: LIZENZ,
  };
}
