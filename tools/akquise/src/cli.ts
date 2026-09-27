/* ==========================================================================
   VECOM Akquise-Worker — Befehle

     npm run verbinden    Schluessel aus der Zwischenablage eintragen (einmal)
     npm run pruefen      Verbindung zur Verwaltung testen
     npm run recherche    wartende Rechercheauftraege abarbeiten (OSM)
     npm run audit        naechste Websites pruefen (Playwright, Lighthouse)
     npm run texte        Claude: Deutung + Kontaktvorlage fuer starke Leads
     npm run alles        recherche → audit → texte
     npm run einzel -- https://beispiel.it restaurant IT
                          eine Website pruefen, OHNE etwas zu melden

   Der Worker versendet nie etwas. Er meldet Firmen, Befunde und Entwuerfe;
   freigegeben und verschickt wird nur in der Verwaltung, von einem Menschen.
   ========================================================================== */
import { existsSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { api } from './api.js';
import { datenOrdner, konfig, VERSION } from './konfig.js';
import { log } from './log.js';
import { domainVon } from './hilfen.js';
import { laufAbarbeiten } from './recherche/lauf.js';
import { auditieren } from './audit/index.js';
import { browserZu } from './audit/browser.js';
import { texteLauf } from './ki/texte.js';
import { kiVerbrauch } from './ki/claude.js';
import type { FirmaKurz } from './audit/typen.js';
import { importieren, verbinden } from './einrichten.js';

const befehl = process.argv[2] ?? 'hilfe';
const SPERRE = join(datenOrdner(), 'lauf.lock');

/* Zwei Laeufe gleichzeitig (geplante Aufgabe + Handstart) wuerden dieselben
   Websites doppelt pruefen. Eine Sperrdatei mit PID verhindert das; ist der
   Prozess dahinter tot oder die Datei aelter als 6 Stunden, gilt sie nicht. */
function sperren(): boolean {
  if (existsSync(SPERRE)) {
    try {
      const { pid, zeit } = JSON.parse(readFileSync(SPERRE, 'utf8'));
      let lebt = false;
      try { process.kill(pid, 0); lebt = true; } catch { lebt = false; }
      if (lebt && Date.now() - zeit < 6 * 3600_000) return false;
    } catch { /* kaputte Datei: ueberschreiben */ }
  }
  writeFileSync(SPERRE, JSON.stringify({ pid: process.pid, zeit: Date.now(), befehl }));
  return true;
}
const freigeben = () => { try { rmSync(SPERRE); } catch { /* schon weg */ } };

async function pruefen(): Promise<boolean> {
  const h = await api('hallo');
  log.info('pruefen', `Verwaltung erreichbar · ${h.kennzahlen?.gesamt ?? 0} Firmen · Notbremse: ${h.stop ? 'GEZOGEN' : 'nein'} · Worker ${VERSION}`);
  if (!konfig.anthropicKey) log.warn('pruefen', 'Kein ANTHROPIC_API_KEY — Deutung und Texte laufen nicht (nur Regeltexte in der Verwaltung).');
  if (!konfig.psiKey) log.info('pruefen', 'Kein PSI-Schlüssel — Lighthouse läuft lokal (Laborwerte, kein INP).');
  return !h.stop;
}

async function recherche(): Promise<void> {
  for (let i = 0; i < 20; i++) {
    const r = await api('lauf_holen');
    if (!r.lauf) { if (i === 0) log.info('recherche', 'Kein wartender Auftrag.'); return; }
    await laufAbarbeiten(r.lauf);
  }
}

async function audits(): Promise<void> {
  const r = await api('audits_holen', { anzahl: konfig.auditsProLauf });
  const firmen = (r.firmen ?? []) as FirmaKurz[];
  log.info('audit', `${firmen.length} Website(s) zu prüfen`);
  for (const [i, f] of firmen.entries()) {
    const h = await api('hallo');
    if (h.stop) { log.warn('audit', 'Notbremse gezogen — Audits angehalten.'); break; }
    const t0 = Date.now();
    try {
      const e = await auditieren(f);
      const antwort = await api('audit_melden', { firma_id: f.id, ...e });
      const belegt = e.befunde.filter((b) => b.status === 'VERIFIED').length;
      log.info('audit', `[${i + 1}/${firmen.length}] ${f.name} (${f.domain}): ${e.befunde.length} Befunde, ${belegt} belegt · Score ${antwort.score ?? '—'} · ${Math.round((Date.now() - t0) / 1000)} s`);
    } catch (e) {
      const text = (e as Error).message;
      /* Liegt der Fehler bei UNS (Browser fehlt, Playwright kaputt), ist
         keine Website schuld. Frueher wurde das als Audit-Fehler gemeldet --
         am 26.09.2026 standen so acht Betriebe auf „gescheitert“, weil auf
         diesem Rechner die Headless-Shell fehlte. Jetzt: Lauf anhalten,
         nichts melden; die Firmen gehen nach zwei Stunden von selbst zurueck. */
      if (/browserType\.launch|Executable doesn't exist|playwright install/i.test(text)) {
        log.fehler('audit', `Der Browser auf diesem Rechner startet nicht — Lauf angehalten, nichts gemeldet. Abhilfe: npx playwright install chromium. (${text.split('\n')[0].slice(0, 160)})`);
        process.exitCode = 1;
        break;
      }
      log.fehler('audit', `${f.name}: ${text}`);
      await api('audit_melden', { firma_id: f.id, status: 'fehler', befunde: [], messwerte: { fehler: text.slice(0, 300) } }).catch(() => {});
    }
  }
  await browserZu();
}

async function einzel(): Promise<void> {
  const url = process.argv[3];
  if (!url) { console.log('Aufruf: npm run einzel -- https://beispiel.it [branche] [IT|DE]'); return; }
  const domain = domainVon(url)!;
  const f: FirmaKurz = { id: 0, kennung: 'L-EINZEL', name: domain, url, domain, land: (process.argv[5] ?? 'IT') as 'DE' | 'IT',
    branche: process.argv[4] ?? null, stadt: process.argv[6] ?? null, tourismus: 0 };
  const e = await auditieren(f);
  await browserZu();
  const { bilder, ...rest } = e;
  const datei = join(datenOrdner('einzel'), `${domain}-${Date.now()}.json`);
  writeFileSync(datei, JSON.stringify(rest, null, 1));
  for (const b of e.befunde) {
    console.log(`${b.status === 'VERIFIED' ? '●' : '○'} [${b.kategorie}] ${'▮'.repeat(b.schwere)}${'▯'.repeat(5 - b.schwere)} ${b.titel}`);
    if (b.beleg) console.log('     ' + b.beleg.split('\n')[0].slice(0, 140));
  }
  console.log(`\n${e.befunde.length} Befunde · Sprache ${e.sprache ?? '?'} · ${e.seiten} Seiten · Bericht: ${datei}`);
}

/**
 * Overture Maps (27.09.2026): ein Gebiet auf einmal in die Verwaltung.
 *   npm run overture -- IT Agrigento                 Provinz, alle Branchen, melden
 *   npm run overture -- IT Favara stadt friseur      Gemeinde, eine Branche
 *   npm run overture -- IT Agrigento kreis "" probe  nur zählen, nichts melden
 */
async function overture(): Promise<void> {
  const [land = 'IT', name, ebene = 'kreis', branchenRoh = '', modus = ''] = process.argv.slice(3);
  if (!name) { console.log('Aufruf: npm run overture -- IT Agrigento [kreis|stadt|region] [branchen] [probe]'); return; }
  const o = await import('./recherche/overture.js');
  const branchen = branchenRoh ? branchenRoh.split(',').filter(Boolean) : [];
  const g = await o.gebiet(land as 'IT', name, ebene as 'kreis');
  log.info('overture', `Gebiet ${g.name} (${g.region ?? '?'}), Rechteck ${g.bbox.map((n) => n.toFixed(3)).join(', ')}, ${g.polygone.length} Fläche(n)`);
  const zeilen = await o.orteIn(g);
  const firmen = zeilen.map((z) => o.alsFirma(z, g, branchen)).filter((f): f is NonNullable<typeof f> => f !== null);
  const jeBranche: Record<string, number> = {};
  for (const f of firmen) jeBranche[f.branche] = (jeBranche[f.branche] ?? 0) + 1;
  log.info('overture', `${firmen.length} Betriebe innerhalb der Grenze, ${firmen.filter((f) => f.url).length} mit Website · ` +
    Object.entries(jeBranche).sort((a, b) => b[1] - a[1]).map(([k, n]) => `${k} ${n}`).join(', '));
  if (modus === 'probe') { log.info('overture', 'Probe — nichts gemeldet.'); return; }
  if (!sperren()) { log.warn('overture', 'Es läuft schon ein Worker — dieser Start wird beendet.'); return; }
  try {
    if (!(await pruefen())) { log.warn('overture', 'Notbremse gezogen — es wird nichts gemeldet.'); return; }
    let neu = 0, dubletten = 0, fehler = 0;
    for (let i = 0; i < firmen.length; i += 100) {
      if (i > 0 && i % 2000 === 0 && (await api('hallo')).stop) { log.warn('overture', 'Notbremse gezogen — angehalten.'); break; }
      const r = await api('firmen_melden', { firmen: firmen.slice(i, i + 100) });
      neu += r.neu ?? 0; dubletten += r.dubletten ?? 0; fehler += r.fehler ?? 0;
      if ((i / 100) % 10 === 0) log.info('overture', `${Math.min(i + 100, firmen.length)}/${firmen.length} gemeldet · ${neu} neu`);
    }
    log.info('overture', `Fertig: ${neu} neu, ${dubletten} schon bekannt (ergänzt), ${fehler} Fehler.`);
  } finally { freigeben(); }
}

/** OSM-Abfrage ohne Verwaltung: zeigt, was eine Recherche finden wuerde. */
async function osm(): Promise<void> {
  const [land, ebene, gebiet, branchenRoh] = process.argv.slice(3);
  if (!land || !ebene || !gebiet) { console.log('Aufruf: npm run osm -- IT stadt Aragona restaurant,hotel'); return; }
  const { gebieteFinden, betriebeIn, alsFirma } = await import('./recherche/overpass.js');
  const branchen = branchenRoh ? branchenRoh.split(',') : [];
  const g = (await gebieteFinden(land, ebene as 'stadt', gebiet))[0];
  if (!g) { console.log('Kein Gebiet gefunden.'); return; }
  const ort = { region: g.region, kreis: g.kreis };
  const els = await betriebeIn(g, branchen);
  const firmen = els.map((e) => alsFirma(e, land as 'IT', { ...ort, stadt: g.name }, branchen)).filter((f) => f !== null);
  for (const f of firmen) console.log(`${f!.url ? '🌐' : '  '} ${f!.branche.padEnd(14)} ${f!.name}${f!.url ? '  ' + f!.url : ''}`);
  console.log(`\n${g.name} (${ort.kreis ?? '?'}, ${ort.region ?? '?'}): ${firmen.length} Betriebe, ${firmen.filter((f) => f!.url).length} mit Website`);
}

async function main(): Promise<void> {
  if (befehl === 'hilfe') {
    console.log('Befehle: verbinden · import <datei> · pruefen · recherche · audit · texte · alles · einzel <url> [branche] [IT|DE] [stadt] · osm <IT|DE> <stadt|kreis|region> <Name> [branchen] · overture <IT|DE> <Name> [kreis|stadt|region] [branchen] [probe]');
    return;
  }
  if (befehl === 'einzel') return einzel();
  if (befehl === 'osm') return osm();
  if (befehl === 'overture') return overture();
  if (befehl === 'verbinden') return verbinden();
  if (befehl === 'import') return importieren(process.argv[3]);
  if (!sperren()) { log.warn('start', 'Es läuft schon ein Worker — dieser Start wird beendet.'); return; }
  try {
    if (!(await pruefen())) { log.warn('start', 'Notbremse gezogen — es wird nichts getan.'); return; }
    if (befehl === 'recherche' || befehl === 'alles') await recherche();
    if (befehl === 'audit' || befehl === 'alles') await audits();
    if (befehl === 'texte' || befehl === 'alles') await texteLauf();
    if (kiVerbrauch()) log.info('ende', `Claude-Verbrauch in diesem Lauf: ${kiVerbrauch()} Token`);
  } finally {
    freigeben();
    await browserZu();
  }
}

main().catch((e) => { log.fehler('start', (e as Error).message); freigeben(); process.exitCode = 1; });
