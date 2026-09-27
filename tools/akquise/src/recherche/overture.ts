/* ==========================================================================
   Firmenrecherche über Overture Maps (27.09.2026, Uwe: Ja).

   WARUM OVERTURE
   Offene Ortsdaten der Overture Maps Foundation (Meta, Microsoft, Foursquare
   u. a.), kommerziell frei nutzbar: CDLA-Permissive-2.0, Foursquare-Teil
   Apache-2.0, AllThePlaces CC0. GEMESSEN AM 27.09.2026 für das Rechteck um
   Agrigento: 32.210 Orte, 13.528 mit Website, 27.817 mit Telefon -- in
   Favara 2.482 Orte gegen 56 aus OpenStreetMap.

   WIE
   DuckDB liest die Parquet-Dateien direkt aus dem öffentlichen S3-Eimer
   (ohne Konto) und holt dank Rechteck-Filter nur die nötigen Teile. Das
   Gebiet (Provinz/Landkreis/Gemeinde) kommt von Nominatim als vereinfachtes
   Polygon; was im Rechteck, aber außerhalb der Grenze liegt, fällt raus.

   AUSLESE WIE BEI OSM
   Geschlossen (operating_status), unsicher (confidence < 0,5), Ketten
   (brand gesetzt), Behörden/Banken/Apotheken/Kirchen: keine Kunden für Vecom.

   SEIT SEPTEMBER 2026 gibt es `categories` nicht mehr -- die Branche kommt
   aus `taxonomy.hierarchy` (jede Ebene), der tiefste Treffer gewinnt.
   ========================================================================== */
import { konfig } from '../konfig.js';
import { log } from '../log.js';
import { BRANCHEN } from '../branchen.js';
import type { GefundeneFirma } from './overpass.js';

export const LIZENZ = 'CDLA-Permissive-2.0 / Apache-2.0 (Overture Maps)';
const STAC = 'https://stac.overturemaps.org/catalog.json';
const NIE = new Set(['bank', 'bank_or_credit_union', 'atm', 'pharmacy', 'post_office', 'package_locker', 'hospital', 'fueling_station', 'gas_station']);

export interface OvertureGebiet {
  name: string; land: 'IT' | 'DE'; region?: string; kreis?: string;
  bbox: [number, number, number, number];        // xmin, ymin, xmax, ymax (Länge, Breite)
  polygone: number[][][];                         // Ringe [[lon,lat], …] -- äußere Ringe aller Teile
}

export interface OvertureZeile {
  id: string; name: string | null; th: string[] | null; website: string | null; telefon: string | null; email: string | null;
  strasse: string | null; ort: string | null; plz: string | null; land: string | null;
  lon: number; lat: number; confidence: number | null; status: string | null; marke: string | null;
}

/** Aktuelle Veröffentlichung aus dem STAC-Katalog (z. B. „2026-09-23.1“). */
export async function neuesteVersion(): Promise<string> {
  const r = await fetch(STAC, { headers: { 'User-Agent': konfig.botKennung }, signal: AbortSignal.timeout(30_000) });
  if (!r.ok) throw new Error(`Overture-Katalog ${r.status}`);
  const j = (await r.json()) as { latest?: string };
  if (!j.latest || !/^\d{4}-\d{2}-\d{2}\.\d+$/.test(j.latest)) throw new Error('Overture-Katalog ohne gültige „latest“-Angabe.');
  return j.latest;
}

/** Gebiet über Nominatim -- mit vereinfachtem Polygon (polygon_threshold), damit der Punkt-in-Fläche-Test schnell bleibt. */
export async function gebiet(land: 'IT' | 'DE', name: string, ebene: 'kreis' | 'stadt' | 'region' = 'kreis'): Promise<OvertureGebiet> {
  const typen: Record<string, string[]> = { region: ['state', 'region'], kreis: ['county', 'province', 'state_district'], stadt: ['city', 'town', 'village', 'municipality'] };
  const q = new URLSearchParams({ format: 'jsonv2', addressdetails: '1', polygon_geojson: '1', polygon_threshold: '0.003', limit: '10', countrycodes: land.toLowerCase(), q: name });
  const r = await fetch('https://nominatim.openstreetmap.org/search?' + q, { headers: { 'User-Agent': konfig.botKennung, 'Accept-Language': land === 'DE' ? 'de' : 'it' }, signal: AbortSignal.timeout(30_000) });
  if (!r.ok) throw new Error(`Nominatim ${r.status}`);
  const liste = (await r.json()) as any[];
  const g = liste.find((x) => x.osm_type === 'relation' && x.category === 'boundary' && typen[ebene].includes(x.addresstype))
    ?? liste.find((x) => x.osm_type === 'relation' && x.category === 'boundary');
  if (!g || !g.geojson) throw new Error(`„${name}“ (${ebene}) in ${land} nicht als Verwaltungsgrenze gefunden.`);
  const bb = (g.boundingbox as string[]).map(Number);   // [lat_min, lat_max, lon_min, lon_max]
  const polygone: number[][][] = g.geojson.type === 'Polygon' ? [g.geojson.coordinates[0]]
    : g.geojson.type === 'MultiPolygon' ? (g.geojson.coordinates as number[][][][]).map((p) => p[0]) : [];
  return {
    name: g.name, land, region: g.address?.state ?? g.address?.region,
    kreis: ebene === 'kreis' ? g.name : (g.address?.county ?? g.address?.province ?? g.address?.state_district),
    bbox: [bb[2], bb[0], bb[3], bb[1]], polygone,
  };
}

/** Punkt in Fläche (Strahlverfahren); Inseln einer Provinz sind eigene Ringe. */
export function drin(lon: number, lat: number, polygone: number[][][]): boolean {
  if (!polygone.length) return true;
  for (const ring of polygone) {
    let innen = false;
    for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
      const [xi, yi] = ring[i], [xj, yj] = ring[j];
      if ((yi > lat) !== (yj > lat) && lon < ((xj - xi) * (lat - yi)) / (yj - yi) + xi) innen = !innen;
    }
    if (innen) return true;
  }
  return false;
}

/** Branche aus der Overture-Hierarchie: tiefster Treffer gewinnt; Namensmuster (Agriturismo) schlägt Unterkunft/Hof. */
export function brancheAusOverture(th: string[] | null, name: string, erlaubt: string[] = []): string | null {
  if (!th || !th.length || th.some((t) => NIE.has(t))) return null;
  const liste = Object.entries(BRANCHEN).filter(([k]) => !erlaubt.length || erlaubt.includes(k) || BRANCHEN[k].name_muster);
  let basis: string | null = null;
  for (const t of [...th].reverse()) {
    const treffer = liste.find(([, b]) => !b.name_muster && (b.overture ?? []).includes(t));
    if (treffer) { basis = treffer[0]; break; }
  }
  for (const [k, b] of liste) {
    if (b.name_muster && new RegExp(b.name_muster, 'i').test(name)
        && (th.some((t) => (b.overture ?? []).includes(t)) || ['hotel', 'ferienwohnung', 'produzent', 'restaurant'].includes(basis ?? ''))) {
      return !erlaubt.length || erlaubt.includes(k) ? k : null;
    }
  }
  return basis && (!erlaubt.length || erlaubt.includes(basis)) ? basis : null;
}

const KEIN_BETRIEB = /\b(chiuso|chiusa|closed|geschlossen|comunale|comune di|municipio|patronato|caf|acli|parrocchia|chiesa|scuola|istituto comprensivo|asp|asl|gemeinde|stadtverwaltung|rathaus|kirche|schule|kita)\b/i;

/** Eine Overture-Zeile als Firma für die Verwaltung -- oder null. */
export function alsFirma(z: OvertureZeile, g: OvertureGebiet, erlaubt: string[] = []): GefundeneFirma | null {
  const name = (z.name ?? '').trim();
  if (name.length < 3 || KEIN_BETRIEB.test(name) || (name.match(/\d/g) ?? []).length >= 6) return null;
  if (z.status === 'permanently_closed' || z.status === 'temporarily_closed') return null;
  if ((z.confidence ?? 0) < 0.5 || z.marke) return null;
  if (!drin(z.lon, z.lat, g.polygone)) return null;
  const branche = brancheAusOverture(z.th, name, erlaubt);
  if (!branche) return null;
  let url = (z.website ?? '').trim();
  if (url && !/^https?:\/\//i.test(url)) url = 'http://' + url;
  return {
    name, land: g.land, region: g.region, kreis: g.kreis,
    stadt: z.ort ? z.ort.replace(/\b\p{L}+/gu, (w) => w[0].toUpperCase() + w.slice(1).toLowerCase()) : undefined,
    plz: z.plz ?? undefined, adresse: z.strasse ? z.strasse.slice(0, 250) : undefined, lat: z.lat, lon: z.lon,
    url: url || undefined, telefon: z.telefon ?? undefined, email: z.email ?? undefined,
    branche, unternehmensart: (z.th ?? []).slice(-1)[0] ?? '', quelle: 'overture:' + z.id, quelle_lizenz: LIZENZ,
  };
}

/** Alle Orte im Rechteck des Gebiets, direkt aus S3 (DuckDB). */
export async function orteIn(g: OvertureGebiet, version?: string): Promise<OvertureZeile[]> {
  const v = version ?? await neuesteVersion();
  const { DuckDBInstance } = await import('@duckdb/node-api');
  const db = await DuckDBInstance.create(':memory:');
  const con = await db.connect();
  await con.run("INSTALL httpfs; LOAD httpfs; SET s3_region='us-west-2';");
  const [x0, y0, x1, y1] = g.bbox.map((n) => Number(n.toFixed(5)));
  const sql = `SELECT id, names.primary AS name, taxonomy.hierarchy AS th,
      websites[1] AS website, phones[1] AS telefon, emails[1] AS email,
      addresses[1].freeform AS strasse, addresses[1].locality AS ort, addresses[1].postcode AS plz, addresses[1].country AS land,
      bbox.xmin AS lon, bbox.ymin AS lat, confidence, operating_status AS status,
      COALESCE(brand.wikidata, brand.names.primary) AS marke
    FROM read_parquet('s3://overturemaps-us-west-2/release/${v}/theme=places/type=place/*', hive_partitioning=1)
    WHERE bbox.xmin BETWEEN ${x0} AND ${x1} AND bbox.ymin BETWEEN ${y0} AND ${y1}`;
  const t0 = Date.now();
  const r = await con.runAndReadAll(sql);
  const zeilen = r.getRowObjectsJson() as unknown as OvertureZeile[];
  log.info('overture', `${g.name}: ${zeilen.length} Orte im Rechteck (Version ${v}, ${Math.round((Date.now() - t0) / 1000)} s)`);
  con.closeSync?.();
  return zeilen.map((z) => ({ ...z, lon: Number(z.lon), lat: Number(z.lat), confidence: z.confidence === null ? null : Number(z.confidence) }));
}
