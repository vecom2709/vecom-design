/* ==========================================================================
   VECOM Akquise-Worker — Befehle

     npm run verbinden    Schluessel aus der Zwischenablage eintragen (einmal)
     npm run pruefen      Verbindung zur Verwaltung testen
     npm run recherche    wartende Rechercheauftraege abarbeiten (OSM)
     npm run audit        naechste Websites pruefen (Playwright, Lighthouse)
     npm run texte        Claude: Deutung + Kontaktvorlage fuer starke Leads
     npm run alles        recherche → audit → texte
     npm run marketing    wartende Marketing-Recherche abarbeiten (Claude Code, Uwes Abo)
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
import { marketingLauf } from './ki/marketing.js';
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

/** Läuft schon ein Worker? (ohne die Sperre zu nehmen) */
function gesperrt(): boolean {
  if (!existsSync(SPERRE)) return false;
  try {
    const { pid, zeit } = JSON.parse(readFileSync(SPERRE, 'utf8'));
    process.kill(pid, 0);
    return Date.now() - zeit < 6 * 3600_000;
  } catch { return false; }
}

/** Der Verwaltung sagen, was der PC gerade tut (Knopf „Starten/Stoppen“, 29.09.2026). */
async function status(art: 'audit' | 'recherche' | 'frei', stand = 0, ziel = 0, text = ''): Promise<void> {
  await api('status_melden', { art, stand, ziel, text }).catch(() => {});
}

/* „steuern“ (alle fünf Minuten, Windows-Aufgabe „VECOM Akquise Abruf“):
   In der Verwaltung auf „Starten“ gedrückt? Wartet ein Suchauftrag? Dann
   jetzt loslegen -- sonst nur kurz melden, dass der PC an ist. Leise: Wenn
   nichts zu tun ist, steht nichts im Protokoll. */
async function steuern(): Promise<void> {
  if (gesperrt()) return;                       // ein Lauf ist schon unterwegs und meldet selbst
  const b = await api('befehl_holen');
  if (b.jetzt && b.audit) {
    if (!sperren()) return;
    try { log.info('steuern', 'In der Verwaltung gestartet: Websites prüfen'); await audits(); }
    finally { freigeben(); await browserZu(); await status('frei'); }
    return;
  }
  if (b.suche_wartet && b.recherche) {
    if (!sperren()) return;
    try { log.info('steuern', 'Suchauftrag wartet: Betriebe suchen'); await recherche(); }
    finally { freigeben(); await status('frei'); }
    return;
  }
  /* Recherche per Knopf (01.10.2026): In Marketing → Recherche „Recherche starten“ gedrückt. */
  if (b.marketing_wartet) {
    if (!sperren()) return;
    /* Marketing-Studio 6: Eine Ein-Klick-Kampagne bringt nach den Texten mehrere Bilder — nacheinander abarbeiten
       statt eins alle fünf Minuten (höchstens 12 Aufträge oder 40 Minuten je Lauf). */
    try {
      const t0 = Date.now();
      for (let i = 0; i < 12 && Date.now() - t0 < 40 * 60_000; i++) { if (!(await marketingLauf())) break; }
    }
    finally { freigeben(); await status('frei'); }
    return;
  }
  await status('frei');
}

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
    await status('recherche', 0, 0, `${r.lauf.gebiet} (${r.lauf.land})`);
    if (r.lauf.quelle === 'overture') { await (await import('./recherche/overture.js')).overtureLauf(r.lauf); continue; }
    await laufAbarbeiten(r.lauf);
  }
}

async function audits(): Promise<void> {
  /* Die Verwaltung gibt höchstens 50 auf einmal heraus. Mehr pro Nacht
     (29.09.2026: 300) heißt: in Paketen nachholen, bis die Zahl erreicht
     oder nichts mehr offen ist. */
  const ziel = Math.max(1, konfig.auditsProLauf);
  let geprueft = 0;
  while (geprueft < ziel) {
    const r = await api('audits_holen', { anzahl: Math.min(50, ziel - geprueft) });
    const paket = (r.firmen ?? []) as FirmaKurz[];
    if (paket.length === 0) { if (geprueft === 0) { log.info('audit', '0 Website(s) zu prüfen'); } break; }
    const weiter = await auditPaket(paket, geprueft, ziel);
    geprueft += paket.length;
    if (!weiter) { break; }
  }
  await browserZu();
}

const AUDIT_ZEITLIMIT_MS = 5 * 60_000;

/** Prüft ein Paket. Gibt false zurück, wenn der Lauf anhalten soll (Notbremse, Browser kaputt).
    Seit 30.09.2026 mehrere Websites gleichzeitig (konfig.auditsParallel): Bei 300 je Nacht
    hätte die Liste von 176.000 Betrieben Jahre gebraucht. Jede Website hat weiter ihr eigenes
    Zeitlimit; ein hängender Browser wird erst neu gestartet, wenn das Paket durch ist -- sonst
    risse der Neustart die anderen, noch laufenden Prüfungen mit. */
async function auditPaket(firmen: FirmaKurz[], vorher: number, ziel: number): Promise<boolean> {
  const parallel = Math.max(1, Math.min(6, Math.floor(konfig.auditsParallel)));
  log.info('audit', `${firmen.length} Website(s) zu prüfen (${vorher + 1}–${vorher + firmen.length} von höchstens ${ziel}, ${parallel} gleichzeitig)`);
  let naechster = 0;
  let fertig = 0;
  let weiter = true;
  let neustart = false;

  const eine = async (f: FirmaKurz): Promise<void> => {
    const t0 = Date.now();
    try {
      /* Höchstens 5 Minuten je Website (29.09.2026): Der Nachtlauf blieb am
         28./29.09. an der ersten Seite hängen und wurde Stunden später ohne
         ein einziges Ergebnis beendet. Hängt eine Seite, wird sie als Fehler
         gemeldet, und es geht mit der nächsten weiter. */
      let wecker: NodeJS.Timeout | undefined;
      const e = await Promise.race([
        auditieren(f),
        new Promise<never>((_, nein) => { wecker = setTimeout(() => nein(new Error('Zeitlimit: Prüfung dauerte länger als 5 Minuten')), AUDIT_ZEITLIMIT_MS); }),
      ]).finally(() => clearTimeout(wecker));
      const antwort = await api('audit_melden', { firma_id: f.id, ...e });
      const belegt = e.befunde.filter((b) => b.status === 'VERIFIED').length;
      fertig++;
      log.info('audit', `[${vorher + fertig}/${ziel}] ${f.name} (${f.domain}): ${e.befunde.length} Befunde, ${belegt} belegt · Score ${antwort.score ?? '—'} · ${Math.round((Date.now() - t0) / 1000)} s`);
    } catch (e) {
      fertig++;
      const text = (e as Error).message;
      /* Liegt der Fehler bei UNS (Browser fehlt, Playwright kaputt), ist
         keine Website schuld. Frueher wurde das als Audit-Fehler gemeldet --
         am 26.09.2026 standen so acht Betriebe auf „gescheitert“, weil auf
         diesem Rechner die Headless-Shell fehlte. Jetzt: Lauf anhalten,
         nichts melden; die Firmen gehen nach zwei Stunden von selbst zurueck. */
      if (/browserType\.launch|Executable doesn't exist|playwright install/i.test(text)) {
        log.fehler('audit', `Der Browser auf diesem Rechner startet nicht — Lauf angehalten, nichts gemeldet. Abhilfe: npx playwright install chromium. (${text.split('\n')[0].slice(0, 160)})`);
        process.exitCode = 1;
        weiter = false;
        return;
      }
      log.fehler('audit', `${f.name}: ${text}`);
      if (text.startsWith('Zeitlimit')) { neustart = true; }
      await api('audit_melden', { firma_id: f.id, status: 'fehler', befunde: [], messwerte: { fehler: text.slice(0, 300) } }).catch(() => {});
    }
  };

  const arbeiter = async (): Promise<void> => {
    while (weiter) {
      const i = naechster++;
      if (i >= firmen.length) { return; }
      const h = await api('hallo');
      if (h.stop) { log.warn('audit', 'Notbremse gezogen — Audits angehalten.'); weiter = false; return; }
      if (h.schalter && h.schalter.audit === false) { log.warn('audit', 'In der Verwaltung gestoppt — Prüfung angehalten.'); weiter = false; return; }
      await status('audit', vorher + fertig, ziel, firmen[i].domain ?? '');
      await eine(firmen[i]);
    }
  };
  await Promise.all(Array.from({ length: Math.min(parallel, firmen.length) }, () => arbeiter()));
  if (neustart) { await browserZu().catch(() => {}); }
  return weiter;
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
    console.log('Befehle: verbinden · import <datei> · pruefen · recherche · audit · texte · alles · marketing · einzel <url> [branche] [IT|DE] [stadt] · osm <IT|DE> <stadt|kreis|region> <Name> [branchen] · overture <IT|DE> <Name> [kreis|stadt|region] [branchen] [probe]');
    return;
  }
  if (befehl === 'einzel') return einzel();
  if (befehl === 'osm') return osm();
  if (befehl === 'overture') return overture();
  if (befehl === 'verbinden') return verbinden();
  if (befehl === 'import') return importieren(process.argv[3]);
  if (befehl === 'steuern') return steuern();
  if (befehl === 'marketing') {
    if (!sperren()) { log.warn('start', 'Es läuft schon ein Worker — dieser Start wird beendet.'); return; }
    try { if (!(await marketingLauf())) log.info('marketing', 'Kein wartender Auftrag.'); } finally { freigeben(); await status('frei'); }
    return;
  }
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
    await status('frei');
  }
}

main().catch((e) => { log.fehler('start', (e as Error).message); freigeben(); process.exitCode = 1; });
