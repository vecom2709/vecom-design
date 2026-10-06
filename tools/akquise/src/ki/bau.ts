/* ==========================================================================
   Bau-Warteschlange am PC (AutoBuild Phase 5, 06.10.2026, Uwe: „ja“).

   An der Projektkarte „Bauen“ klickt jemand „Analyse erstellen“ oder
   „Pflichtenheft erstellen“. steuern sieht bau_wartet, holt den Auftrag über
   bau_auftrag_holen und lässt Claude Code (Uwes Abo) arbeiten — höchstens mit
   WebSearch/WebFetch, um die bisherige Website des Kunden anzusehen. Keine
   Dateien, keine Befehle, kein Bauen. Das Ergebnis geht als Markdown zurück;
   gültig wird es erst, wenn ein Admin es in der Verwaltung übernimmt.
   ========================================================================== */
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { api } from '../api.js';
import { datenOrdner } from '../konfig.js';
import { log } from '../log.js';

export type BauAuftrag = {
  id: number; art: 'analyse' | 'pflichtenheft'; projekt: number; beschreibung: string; titel: string;
  kunde: { firma: string; branche: string; ort: string; sprache: string; website: string };
  hinweis: string; briefing: string; hausregeln: string;
  umfang: { bezeichnung: string; beschreibung: string; menge: number; monatlich: boolean }[];
  analyse: string; pflichtenheft: string; bau_erlaubt: boolean; regel: string;
};

export const SCHEMA_BAU = {
  type: 'object',
  required: ['markdown'],
  properties: {
    markdown: { type: 'string', description: 'Das ganze Dokument als Markdown (Überschriften mit #, Listen mit -)' },
  },
};

const SPRACHE: Record<string, string> = { it: 'Italienisch', de: 'Deutsch', en: 'Englisch' };

const GLIEDERUNG = {
  analyse: `# Analyse — {titel}
## Kurzfazit (3–5 Sätze: machbar? wie aufwendig? was ist kritisch?)
## Ausgangslage (bisherige Website: was gibt es, was fehlt, was ist veraltet — nur was du wirklich gesehen hast)
## Machbarkeit und Aufwand (grob in Arbeitstagen, je Bereich; als Schätzung kennzeichnen)
## Risiken (jeweils: Risiko — Folge — Gegenmaßnahme; am Ende eine Gesamteinschätzung grün/gelb/rot)
## Was im Angebot steht und was darüber hinausginge (Scope: Wünsche aus dem Briefing, die nicht im Leistungsumfang stehen, ausdrücklich nennen)
## Fehlende Inhalte und Zugänge (Texte, Fotos, Logo, Domain, Zugänge — was der Kunde liefern muss)
## Offene Fragen an den Kunden (konkret, beantwortbar)`,
  pflichtenheft: `# Pflichtenheft — {titel}
## Ziel der Website (ein Absatz, messbar wo möglich)
## Zielgruppe
## Designrichtung (Stimmung, Farben, Schrift, Bildsprache — aus Briefing und Branche abgeleitet)
## Seitenstruktur (jede Seite mit Zweck und Abschnitten)
## Funktionen (Formulare, WhatsApp, Karte, Buchung … — nur was im Leistungsumfang steht)
## Sprachen
## SEO (Titel-Muster, wichtige Suchbegriffe, lokale Signale, strukturierte Daten)
## Technik (Hosting, Domain, Formular-Ziel, Cookie-Hinweis, Barrierefreiheit)
## Inhalte und Bilder (wer liefert was bis wann; was vorerst als Platzhalter markiert wird)
## Integrationen
## Nicht enthalten (was ausdrücklich NICHT gebaut wird — Schutz vor ungeplanten Erweiterungen)
## Abnahmekriterien (prüfbare Liste: „… ist erfüllt, wenn …“)
## Offene Fragen`,
} as const;

export function bauText(a: BauAuftrag): string {
  const umfang = a.umfang.length
    ? a.umfang.map((u) => `- ${u.menge > 1 ? u.menge + '× ' : ''}${u.bezeichnung}${u.monatlich ? ' (monatlich)' : ''}${u.beschreibung ? ' — ' + u.beschreibung : ''}`).join('\n')
    : '(Kein Angebot im System — Leistungsumfang aus dem Briefing ableiten und als „nicht bestätigt“ kennzeichnen.)';
  const vorher = a.art === 'pflichtenheft' && a.analyse.trim() ? `\nÜBERNOMMENE ANALYSE (Grundlage, nicht wiederholen)\n${a.analyse.slice(0, 20000)}\n` : '';
  const alt = a.art === 'pflichtenheft' && a.pflichtenheft.trim() ? `\nBISHERIGES PFLICHTENHEFT (überarbeiten, Gutes behalten)\n${a.pflichtenheft.slice(0, 30000)}\n` : '';
  return `Du arbeitest für Vecom Design (Webdesign, Uwe Vetter) an einem Kundenprojekt.
AUFTRAG #${a.id}: ${a.beschreibung}

${a.regel}
${a.bau_erlaubt ? '' : 'Die Bausperre gilt noch (Angebot nicht angenommen oder Anzahlung offen): erst recht nur planen.\n'}
HARTE REGELN
- Schreibe auf Deutsch für Uwe (intern). Die Website selbst wird später auf ${SPRACHE[a.kunde.sprache] ?? a.kunde.sprache} gebaut — nenne das unter Sprachen.
- Nichts erfinden: keine Kundenzahlen, Bewertungen, Auszeichnungen, Preise, Fristen. Was du nicht weißt, gehört unter „Offene Fragen“.
- Schätzungen ausdrücklich als Schätzung kennzeichnen. Keine Zusicherungen wie „garantiert“, „rechtssicher“, „Platz 1 bei Google“.
- Der Leistungsumfang unten ist verbindlich. Alles darüber hinaus nur als „zusätzlich, nicht im Angebot“ aufführen.
- Bisherige Website: höchstens mit WebFetch/WebSearch ansehen, nichts absenden, nichts anmelden, niemanden kontaktieren.
- Keine personenbezogenen Daten (E-Mail, Telefon) ins Dokument.
- Nur das JSON-Feld markdown, kein HTML.

GLIEDERUNG (genau diese Überschriften)
${GLIEDERUNG[a.art].replace('{titel}', a.titel)}

KUNDE
Betrieb: ${a.kunde.firma || '(unbekannt)'} · Branche: ${a.kunde.branche || '(unbekannt)'} · Ort: ${a.kunde.ort || '(unbekannt)'}
Bisherige Website: ${a.kunde.website || '(keine bekannt)'}
${a.hinweis ? `\nZUSATZWUNSCH VON UWE\n${a.hinweis}\n` : ''}
LEISTUNGSUMFANG (aus dem Angebot, ohne Preise)
${umfang}
${vorher}${alt}
BRIEFING
${a.briefing || '(leer)'}

HAUSREGELN VON VECOM
${a.hausregeln || '(keine)'}`;
}

/** Antwort prüfen, bevor sie zur Verwaltung geht — die Verwaltung prüft noch einmal. */
export function bauLesen(innen: any, a: Pick<BauAuftrag, 'art'>): string {
  if (!innen || typeof innen !== 'object') throw new Error('Claude hat kein Dokument geliefert.');
  const md = String(innen.markdown ?? '').trim();
  if (md.length < 200) throw new Error('Das Dokument ist leer oder zu kurz.');
  if (/<\s*(script|iframe|style|object|embed)\b/i.test(md)) throw new Error('Das Dokument enthält HTML — nur Markdown erlaubt.');
  const pflicht = a.art === 'pflichtenheft' ? ['Abnahmekriterien', 'Nicht enthalten'] : ['Risiken', 'Offene Fragen'];
  for (const p of pflicht) if (!md.includes(p)) throw new Error(`Abschnitt „${p}“ fehlt.`);
  return md;
}

type Werkzeug = { ausfuehren: (text: string, ordner: string, schema: object, werkzeuge?: string) => Promise<string>; lesen: (roh: string) => any };

/** Einen Auftrag abholen und erledigen. false = nichts zu tun. */
export async function bauLauf(w: Werkzeug): Promise<boolean> {
  const r = await api('bau_auftrag_holen');
  const a = r.auftrag as BauAuftrag | null;
  if (!a) return false;
  log.info('bau', `Auftrag #${a.id}: ${a.beschreibung} — Claude arbeitet`);
  await api('status_melden', { art: 'marketing', stand: 0, ziel: 0, text: a.beschreibung }).catch(() => {});
  const ordner = datenOrdner('bau');
  try {
    const roh = await w.ausfuehren(bauText(a), ordner, SCHEMA_BAU, a.kunde.website ? 'WebSearch,WebFetch' : '');
    writeFileSync(join(ordner, `auftrag-${a.id}.json`), roh);
    const md = bauLesen(w.lesen(roh), a);
    const j = await api('bau_auftrag_melden', { id: a.id, ok: true, text: md });
    if (!j.ok) { log.warn('bau', `Auftrag #${a.id}: ${j.hinweis ?? 'abgelehnt'}`); return true; }
    log.info('bau', `Auftrag #${a.id}: fertig (${md.length} Zeichen) — wartet aufs Übernehmen`);
  } catch (x) {
    const grund = (x as Error).message;
    log.fehler('bau', `Auftrag #${a.id}: ${grund}`);
    await api('bau_auftrag_melden', { id: a.id, ok: false, fehler: grund.slice(0, 900) }).catch(() => {});
  }
  return true;
}
