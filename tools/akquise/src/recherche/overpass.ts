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

/* Mehrere Overpass-Server, der Reihe nach. Beim ersten Lauf auf Uwes
   Rechner (26.09.2026) antwortete overpass-api.de zweimal mit 504 -- der
   Hauptserver ist abends oft voll. Ein ausgelasteter Server ist kein
   Grund, eine Stunde zu warten, wenn ein anderer frei ist. */
const SERVER = konfig.overpass.split(',').map((s) => s.trim()).filter(Boolean);
let serverNr = 0;

export async function overpass(abfrage: string): Promise<OsmElement[]> {
  for (let versuch = 1; versuch <= 2 * SERVER.length + 1; versuch++) {
    const noch = letzteAbfrage + konfig.overpassPauseMs - Date.now();
    if (noch > 0) await warten(noch);
    letzteAbfrage = Date.now();
    const server = SERVER[serverNr % SERVER.length];
    try {
      const r = await fetch(server, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'User-Agent': konfig.botKennung },
        body: 'data=' + encodeURIComponent(abfrage),
        signal: AbortSignal.timeout(200_000),
      });
      if (r.status === 429 || r.status === 504 || r.status === 503) {
        serverNr++;
        // Erst alle Server einmal versuchen, dann laenger warten.
        const pause = versuch < SERVER.length ? 2_000 : 45_000 * Math.ceil(versuch / SERVER.length);
        log.warn('overpass', `${new URL(server).host} ausgelastet (${r.status}) — weiter mit ${new URL(SERVER[serverNr % SERVER.length]).host} in ${pause / 1000}s`);
        await warten(pause);
        continue;
      }
      if (!r.ok) throw new Error(`Overpass ${r.status}: ${(await r.text()).slice(0, 200)}`);
      const j = (await r.json()) as { elements?: OsmElement[]; remark?: string };
      if (j.remark && /runtime error|timed out/i.test(j.remark)) throw new Error('Overpass: ' + j.remark);
      return j.elements ?? [];
    } catch (e) {
      if (versuch >= 2 * SERVER.length + 1) throw e;
      log.warn('overpass', `${new URL(server).host}: ${(e as Error).message}`);
      serverNr++;
      await warten(10_000 * versuch);
    }
  }
  throw new Error('Kein Overpass-Server hat geantwortet.');
}

const landArea = (land: string) => `area["ISO3166-1"="${land}"]["admin_level"="2"]->.land;`;
const esc = (s: string) => s.replace(/\\/g, '\\\\').replace(/"/g, '\\"');

/* ==========================================================================
   Gebiete finden: ueber Nominatim, nicht ueber Overpass.

   GEMESSEN AM 26.09.2026 auf Uwes Rechner: Die Overpass-Abfrage "alle
   Verwaltungsgrenzen Italiens mit diesem Namen" lief auf drei Servern
   jeweils in den 504 -- nicht weil die Server voll waren, sondern weil die
   Abfrage fuer ganz Italien zu schwer ist. Nominatim ist genau fuer diese
   Frage gebaut, antwortet in unter einer Sekunde und liefert Region und
   Kreis gleich mit. Nutzungsregel: hoechstens eine Anfrage je Sekunde,
   mit erkennbarem Absender -- beides eingehalten.
   ========================================================================== */
const NOMINATIM = 'https://nominatim.openstreetmap.org';
let letzteNominatim = 0;
async function nominatim(pfad: string, sprache = 'it'): Promise<any> {
  const noch = letzteNominatim + 1100 - Date.now();
  if (noch > 0) await warten(noch);
  letzteNominatim = Date.now();
  const r = await fetch(NOMINATIM + pfad, { headers: { 'User-Agent': konfig.botKennung, 'Accept-Language': sprache }, signal: AbortSignal.timeout(30_000) });
  if (!r.ok) throw new Error(`Nominatim ${r.status}`);
  return r.json();
}

const TYPEN: Record<string, string[]> = {
  region: ['state', 'region'],
  kreis: ['county', 'province', 'state_district'],
  stadt: ['city', 'town', 'village', 'municipality', 'hamlet'],
};

/** Verwaltungsgebiete einer Ebene mit diesem Namen, mit Region und Kreis. */
export async function gebieteFinden(land: string, ebene: 'region' | 'kreis' | 'stadt', name: string): Promise<Gebiet[]> {
  const q = new URLSearchParams({ format: 'jsonv2', addressdetails: '1', limit: '15', countrycodes: land.toLowerCase(), q: name });
  const liste: any[] = await nominatim('/search?' + q, land === 'DE' ? 'de' : 'it');
  const n = name.toLowerCase().trim();
  const grenzen = liste.filter((r) => r.osm_type === 'relation' && r.category === 'boundary' && TYPEN[ebene].includes(r.addresstype));
  // Genau dieser Name -- sonst alle, die so anfangen ("Neustadt" → "Neustadt in Holstein" …), damit
  // die Mehrdeutigkeit mit Auswahl gemeldet wird statt "nichts gefunden".
  let passend = grenzen.filter((r) => String(r.name ?? '').toLowerCase().trim() === n);
  if (!passend.length) passend = grenzen.filter((r) => String(r.name ?? '').toLowerCase().startsWith(n));
  const gesehen = new Set<number>();
  return passend.filter((r) => !gesehen.has(r.osm_id) && gesehen.add(r.osm_id)).map((r) => ({
    name: r.name, osmId: Number(r.osm_id), lat: Number(r.lat), lon: Number(r.lon),
    region: r.address?.state ?? r.address?.region,
    kreis: ebene === 'stadt' ? (r.address?.county ?? r.address?.province ?? r.address?.state_district) : undefined,
  }));
}

/** In welcher Region und welchem Kreis liegt ein Punkt? */
export async function einordnen(lat: number, lon: number): Promise<{ region?: string; kreis?: string }> {
  const r = await nominatim(`/reverse?format=jsonv2&addressdetails=1&zoom=10&lat=${lat}&lon=${lon}`, 'de,it');
  return { region: r?.address?.state ?? r?.address?.region, kreis: r?.address?.county ?? r?.address?.province ?? r?.address?.state_district };
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
