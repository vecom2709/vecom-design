/* ==========================================================================
   Ein Rechercheauftrag aus der Verwaltung, systematisch abgearbeitet:
   LAND → REGION → KREIS → GEMEINDE → BRANCHE → FIRMA.

   Grosse Gebiete werden zerlegt und Gemeinde fuer Gemeinde abgefragt. Der
   Fortschritt steht in daten/recherche/lauf-<id>.json: Bricht der Worker
   ab (Rechner aus, Netz weg), macht der naechste Lauf bei der naechsten
   offenen Gemeinde weiter statt von vorn.
   ========================================================================== */
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';
import { api } from '../api.js';
import { datenOrdner } from '../konfig.js';
import { log } from '../log.js';
import { alsFirma, betriebeIn, betriebeMitPlz, gebieteFinden, untergebiete, type Gebiet, type GefundeneFirma } from './overpass.js';

interface Lauf { id: number; land: 'DE' | 'IT'; ebene: 'auto' | 'region' | 'kreis' | 'stadt' | 'plz'; gebiet: string; branchen: string[] }
interface Stand { erledigt: number[]; gemeinden?: (Gebiet & { region?: string; kreis?: string })[] }

function standLesen(id: number): Stand {
  const p = join(datenOrdner('recherche'), `lauf-${id}.json`);
  return existsSync(p) ? JSON.parse(readFileSync(p, 'utf8')) : { erledigt: [] };
}
function standSchreiben(id: number, s: Stand): void {
  writeFileSync(join(datenOrdner('recherche'), `lauf-${id}.json`), JSON.stringify(s, null, 1));
}

async function melden(laufId: number, firmen: GefundeneFirma[]): Promise<{ neu: number; dubletten: number }> {
  let neu = 0, dubletten = 0;
  for (let i = 0; i < firmen.length; i += 100) {
    const r = await api('firmen_melden', { lauf_id: laufId, firmen: firmen.slice(i, i + 100) });
    neu += r.neu ?? 0; dubletten += r.dubletten ?? 0;
  }
  return { neu, dubletten };
}

/** Zerlegt das Gebiet in Gemeinden (mit Region und Kreis dazu). */
async function gemeindenFuer(lauf: Lauf): Promise<(Gebiet & { region?: string; kreis?: string })[]> {
  // "Neustadt, Landkreis Harburg": der Teil nach dem Komma waehlt unter gleichnamigen Gebieten.
  const [name, oberhalb] = lauf.gebiet.split(',').map((s) => s.trim());
  let ebene: 'region' | 'kreis' | 'stadt';
  let kandidaten: (Gebiet & { region?: string; kreis?: string })[] = [];
  if (lauf.ebene === 'auto') {
    /* „Jetzt suchen" in der Verwaltung: ein Name, keine Ebene. Der kleinste
       Treffer gewinnt -- wer „Agrigento" tippt, meint meist die Stadt, nicht
       die Provinz mit 43 Gemeinden. Die Provinz geht mit „Provinz Agrigento"
       oder unter Suchauftraege mit fester Ebene. */
    const prov = /^(provinz|provincia|landkreis|kreis|libero consorzio( comunale)?( di)?)\s+/i;
    const reg = /^(region|regione|bundesland)\s+/i;
    const reihe: ('stadt' | 'kreis' | 'region')[] = prov.test(name) ? ['kreis'] : reg.test(name) ? ['region'] : ['stadt', 'kreis', 'region'];
    const rein = name.replace(prov, '').replace(reg, '');
    ebene = reihe[0];
    for (const e of reihe) {
      kandidaten = await gebieteFinden(lauf.land, e, rein);
      if (kandidaten.length) { ebene = e; break; }
    }
    if (!kandidaten.length) throw new Error(`„${name}" in ${lauf.land} nicht gefunden — weder als Ort noch als Provinz/Kreis noch als Region.`);
    log.info('recherche', `„${name}" erkannt als ${{ stadt: 'Ort', kreis: 'Provinz/Kreis', region: 'Region' }[ebene]}`);
  } else {
    ebene = lauf.ebene as 'region' | 'kreis' | 'stadt';
    kandidaten = await gebieteFinden(lauf.land, ebene, name);
    if (!kandidaten.length) throw new Error(`Kein Gebiet „${name}" auf Ebene ${ebene} in ${lauf.land} gefunden.`);
  }

  if (kandidaten.length > 1 || oberhalb) {
    const mitOrt = kandidaten;   // Region und Kreis liefert Nominatim schon mit
    if (oberhalb) {
      const n = oberhalb.toLowerCase();
      kandidaten = mitOrt.filter((k) => (k.kreis ?? '').toLowerCase().includes(n) || (k.region ?? '').toLowerCase().includes(n));
    } else {
      kandidaten = mitOrt;
    }
    if (kandidaten.length > 1) {
      throw new Error(`„${name}" ist mehrdeutig: ` + mitOrt.map((k) => `${k.name}, ${k.kreis ?? k.region ?? '?'}`).join(' | ')
        + ' — bitte als „Name, Kreis" neu anlegen.');
    }
    if (!kandidaten.length) throw new Error(`Kein „${name}" in „${oberhalb}" gefunden.`);
  }
  const g = kandidaten[0];

  if (ebene === 'stadt') return [g];
  if (ebene === 'kreis') {
    const gem = await untergebiete(g, 'stadt');
    // Kreisfreie Stadt: keine Gemeinden darunter -- dann ist der Kreis selbst die Gemeinde.
    return (gem.length ? gem : [g]).map((x) => ({ ...x, region: g.region, kreis: g.name }));
  }
  // Region: erst die Kreise/Provinzen, dann deren Gemeinden.
  const kreise = await untergebiete(g, 'kreis');
  const alle: (Gebiet & { region?: string; kreis?: string })[] = [];
  for (const k of kreise) {
    const gem = await untergebiete(k, 'stadt');
    alle.push(...(gem.length ? gem : [k]).map((x) => ({ ...x, region: g.name, kreis: k.name })));
    log.info('recherche', `${g.name} › ${k.name}: ${gem.length} Gemeinden`);
  }
  return alle;
}

export async function laufAbarbeiten(lauf: Lauf): Promise<void> {
  log.info('recherche', `Auftrag #${lauf.id}: ${lauf.land} / ${lauf.ebene} „${lauf.gebiet}" / ${lauf.branchen.length ? lauf.branchen.join(', ') : 'alle Branchen'}`);
  const stand = standLesen(lauf.id);
  try {
    if (lauf.ebene === 'plz') {
      const els = await betriebeMitPlz(lauf.land, lauf.gebiet, lauf.branchen);
      const firmen = els.map((e) => alsFirma(e, lauf.land, {}, lauf.branchen)).filter((f): f is GefundeneFirma => f !== null);
      const r = await melden(lauf.id, firmen);
      log.info('recherche', `PLZ ${lauf.gebiet}: ${firmen.length} Betriebe, ${r.neu} neu, ${r.dubletten} schon bekannt`);
    } else {
      stand.gemeinden ??= await gemeindenFuer(lauf);
      standSchreiben(lauf.id, stand);
      const offen = stand.gemeinden.filter((g) => !stand.erledigt.includes(g.osmId));
      log.info('recherche', `${stand.gemeinden.length} Gemeinden, davon ${offen.length} offen`);
      for (const [i, g] of offen.entries()) {
        const hallo = await api('hallo');
        if (hallo.stop) { log.warn('recherche', 'Notbremse in der Verwaltung gezogen — Recherche angehalten.'); return; }
        const els = await betriebeIn(g, lauf.branchen);
        const firmen = els.map((e) => alsFirma(e, lauf.land, { region: g.region, kreis: g.kreis, stadt: g.name }, lauf.branchen))
          .filter((f): f is GefundeneFirma => f !== null);
        const r = firmen.length ? await melden(lauf.id, firmen) : { neu: 0, dubletten: 0 };
        stand.erledigt.push(g.osmId);
        standSchreiben(lauf.id, stand);
        const mitWeb = firmen.filter((f) => f.url).length;
        log.info('recherche', `[${i + 1}/${offen.length}] ${g.name}: ${firmen.length} Betriebe (${mitWeb} mit Website), ${r.neu} neu, ${r.dubletten} bekannt`);
      }
    }
    await api('lauf_melden', { lauf_id: lauf.id, status: 'fertig' });
    log.info('recherche', `Auftrag #${lauf.id} abgeschlossen`);
  } catch (e) {
    log.fehler('recherche', `Auftrag #${lauf.id} gescheitert: ${(e as Error).message}`);
    await api('lauf_melden', { lauf_id: lauf.id, status: 'fehler', fehler: (e as Error).message }).catch(() => {});
  }
}
