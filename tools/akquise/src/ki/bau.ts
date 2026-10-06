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

export type Datei = { pfad: string; inhalt: string };
export type BauAuftrag = {
  id: number; art: 'analyse' | 'pflichtenheft' | 'bauen' | 'review'; projekt: number; beschreibung: string; titel: string;
  versuch?: number; max_versuche?: number;
  grenzen?: { dateien: number; bytes: number; endungen: string[] };
  kontakt?: { telefon: string; email: string; adresse: string };
  fassung?: { nummer: number; dateien: Datei[]; tests: { name: string; ok: boolean; schwer: boolean; detail: string }[]; review: string };
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

/* AutoBuild Phase 7: Builder liefert Dateien, Reviewer ein Urteil. */
export const SCHEMA_BAUEN = {
  type: 'object',
  required: ['dateien', 'zusammenfassung'],
  properties: {
    dateien: { type: 'array', items: { type: 'object', required: ['pfad', 'inhalt'], properties: { pfad: { type: 'string' }, inhalt: { type: 'string' } } } },
    zusammenfassung: { type: 'string', description: '3–6 Sätze auf Deutsch für Uwe: was gebaut wurde, was bewusst fehlt, was der Kunde noch liefern muss' },
  },
};
export const SCHEMA_REVIEW = {
  type: 'object',
  required: ['urteil', 'markdown', 'maengel'],
  properties: {
    urteil: { type: 'string', enum: ['bestanden', 'nachbessern'] },
    markdown: { type: 'string', description: 'Review auf Deutsch, Markdown' },
    maengel: { type: 'array', items: { type: 'string' }, description: 'Konkrete, umsetzbare Mängel — leer bei bestanden' },
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

function quelltextBlock(d: Datei[]): string {
  return d.map((f) => `===== ${f.pfad} =====\n${f.inhalt}`).join('\n\n');
}

export function bauenText(a: BauAuftrag): string {
  const umfang = a.umfang.length ? a.umfang.map((u) => `- ${u.menge > 1 ? u.menge + '× ' : ''}${u.bezeichnung}${u.beschreibung ? ' — ' + u.beschreibung : ''}`).join('\n') : '(kein Angebot im System)';
  const g = a.grenzen ?? { dateien: 120, bytes: 1_500_000, endungen: ['html', 'css', 'js', 'svg', 'txt', 'xml', 'json', 'webmanifest'] };
  const nach = a.fassung ? `\nNACHBESSERN — RUNDE ${a.versuch ?? 2} VON ${a.max_versuche ?? 3}
Ausgangsfassung ist V${a.fassung.nummer}. Behebe die Mängel unten, ändere sonst nur, was dafür nötig ist, und liefere wieder ALLE Textdateien vollständig.
MÄNGEL:\n${a.hinweis || '(siehe Review)'}
${a.fassung.review ? `REVIEW DER AUSGANGSFASSUNG:\n${a.fassung.review.slice(0, 15000)}\n` : ''}
QUELLTEXT V${a.fassung.nummer}:\n${quelltextBlock(a.fassung.dateien)}\n` : (a.hinweis ? `\nZUSATZWUNSCH VON UWE: ${a.hinweis}\n` : '');
  return `Du baust für Vecom Design (Webdesign, Uwe Vetter) die Website eines Kunden — als statische Dateien.
AUFTRAG #${a.id}: ${a.beschreibung}

${a.regel}

GRUNDLAGE IST DAS ÜBERNOMMENE PFLICHTENHEFT. Es ist verbindlich: dieselben Seiten, Funktionen und Sprachen; was dort unter „Nicht enthalten“ steht, wird nicht gebaut.

QUALITÄT (Premium-Agentur, kein Template)
- Mobil zuerst (390 px), sauber bis 1440 px. Semantisches HTML, eine H1 je Seite, Überschriften in Reihenfolge.
- Jede Seite: <!doctype html>, <html lang="${a.kunde.sprache || 'it'}">, <meta name="viewport">, eindeutiger <title>, <meta name="description"> (mind. 20 Zeichen).
- Barrierefrei: Kontrast mind. 4.5:1, sichtbarer Fokus, Tippflächen mind. 44 px, alt-Texte, Tastatur bedienbar, prefers-reduced-motion beachtet.
- Gestaltung: eigene Farbwelt aus Branche und Pflichtenheft, eine Display- und eine Textschrift (Google Fonts erlaubt, höchstens zwei Familien), Design-Tokens als CSS-Variablen, viel Weißraum, klare Hierarchie. Keine lila Verläufe, keine Emojis als Symbole, keine drei gleichen Karten ohne Grund.
- Bewegung nur mit Zweck (transform/opacity), kein Scroll-Hijacking.
- JavaScript nur wenn nötig (z. B. Menü), als eigene .js-Datei, keine Skripte von fremden Servern, keine Tracker, keine Cookies.
- Kontakt als Links: tel:, mailto:, WhatsApp (wa.me) — nur mit dem Geschäftskontakt unten; fehlt eine Angabe, den Weg weglassen, nichts erfinden. Keine Formulare, die ins Leere senden; verlangt das Pflichtenheft ein Formular, nenne das in der Zusammenfassung als offenen Punkt für den Livegang.
- Datenschutz-/Impressumsseite verlinken, wenn das Pflichtenheft sie vorsieht; Inhalte dort nur aus den Angaben, sonst neutral und knapp.

HARTE REGELN
- Nichts erfinden: keine Kundennamen, Bewertungen, Zahlen, Preise, Auszeichnungen, Zitate. Fehlt ein Inhalt, formuliere neutral oder lass den Abschnitt weg.
- KEINE Platzhalter: kein Lorem ipsum, kein [Platzhalter], kein TODO, keine example.com-Adressen, keine Platzhalter-Bilddienste.
- Bilder: keine erfundenen Bildadressen. Erlaubt sind Fotos von der bisherigen Website (absolute https-Adressen, die du mit WebFetch gesehen hast), eigene SVG-Grafiken oder reine CSS-Flächen.
- Alle internen Links und Dateien müssen existieren. Pfade relativ, nur Kleinbuchstaben, Ziffern, Bindestrich, Punkt, Unterstrich und /.
- Erlaubte Endungen: ${g.endungen.join(', ')}. Höchstens ${g.dateien} Dateien und ${Math.round(g.bytes / 1000)} KB zusammen. index.html liegt ganz oben.
- Antworte nur mit dem JSON: dateien (pfad + vollständiger inhalt jeder Datei) und zusammenfassung.

KUNDE
Betrieb: ${a.kunde.firma || '(unbekannt)'} · Branche: ${a.kunde.branche || '(unbekannt)'} · Ort: ${a.kunde.ort || '(unbekannt)'}
Bisherige Website: ${a.kunde.website || '(keine bekannt)'}
Geschäftskontakt für die Seite: Telefon ${a.kontakt?.telefon || '(keins)'} · E-Mail ${a.kontakt?.email || '(keine)'} · Adresse ${a.kontakt?.adresse || '(keine)'}
${nach}
LEISTUNGSUMFANG (ohne Preise)
${umfang}

ÜBERNOMMENES PFLICHTENHEFT
${a.pflichtenheft.slice(0, 40000)}

${a.analyse.trim() ? `ÜBERNOMMENE ANALYSE\n${a.analyse.slice(0, 15000)}\n` : ''}BRIEFING
${a.briefing.slice(0, 30000) || '(leer)'}

HAUSREGELN VON VECOM
${a.hausregeln || '(keine)'}`;
}

export function reviewText(a: BauAuftrag): string {
  const f = a.fassung;
  const tests = f?.tests.length ? f.tests.map((t) => `- ${t.ok ? 'OK ' : (t.schwer ? 'FEHLER ' : 'HINWEIS ')}${t.name}${t.detail && !t.ok ? ' — ' + t.detail : ''}`).join('\n') : '(keine Tests)';
  return `Du bist der unabhängige Reviewer bei Vecom Design. Ein anderer Claude-Lauf hat eine Fassung gebaut; du prüfst sie streng, bevor ein Mensch Zeit hineinsteckt.
AUFTRAG #${a.id}: ${a.beschreibung} · Fassung V${f?.nummer ?? '?'} · Runde ${a.versuch ?? 1} von ${a.max_versuche ?? 3}

${a.regel}

PRÜFE GEGEN
1. Das Pflichtenheft: Sind alle Seiten, Funktionen, Sprachen und Abnahmekriterien erfüllt? Wurde etwas gebaut, das unter „Nicht enthalten“ steht (Scope)?
2. Ehrlichkeit: erfundene Fakten, Zahlen, Bewertungen, Zitate, Versprechen („garantiert“, „Nr. 1“)? Platzhalter?
3. Qualität: Hierarchie, Typografie, Abstände, Mobil, Barrierefreiheit (Kontrast, Fokus, alt, Überschriften), SEO-Grundlagen, Ladegewicht.
4. Technik: tote Links, kaputtes HTML, Skripte von fremden Servern, Konsolenfehler-verdächtiger Code.
5. Die automatischen Tests unten (FEHLER = muss behoben werden).

URTEIL
- „bestanden“ nur, wenn alle Abnahmekriterien erfüllt sind, kein FEHLER-Test offen ist und nichts erfunden ist. Sonst „nachbessern“.
- maengel: konkrete, umsetzbare Punkte (Datei + was genau), höchstens 12, wichtigste zuerst. Bei „bestanden“ leer.
- markdown: kurzes Review auf Deutsch mit # Review V${f?.nummer ?? ''}, ## Ergebnis, ## Erfüllt, ## Mängel, ## Hinweise für Uwe.
- Nur das JSON, nichts ändern.

AUTOMATISCHE TESTS
${tests}

PFLICHTENHEFT
${a.pflichtenheft.slice(0, 40000)}

LEISTUNGSUMFANG
${a.umfang.map((u) => '- ' + u.bezeichnung).join('\n') || '(kein Angebot im System)'}

QUELLTEXT V${f?.nummer ?? ''}
${quelltextBlock(f?.dateien ?? [])}`;
}

export function bauenLesen(innen: any, grenzen: { dateien: number; bytes: number; endungen: string[] } = { dateien: 120, bytes: 1_500_000, endungen: ['html', 'css', 'js', 'svg', 'txt', 'xml', 'json', 'webmanifest'] }): { dateien: Datei[]; zusammenfassung: string } {
  if (!innen || !Array.isArray(innen.dateien)) throw new Error('Claude hat keine Dateien geliefert.');
  const dateien: Datei[] = [];
  let summe = 0;
  for (const f of innen.dateien) {
    const pfad = String(f?.pfad ?? '').replace(/\\/g, '/').replace(/^\/+/, '');
    const endung = pfad.split('.').pop()?.toLowerCase() ?? '';
    if (!/^[A-Za-z0-9._\/-]+$/.test(pfad) || /(^|\/)\.\.?(\/|$)/.test(pfad) || /(^|\/)\./.test(pfad) || !grenzen.endungen.includes(endung)) throw new Error(`Unzulässige Datei: ${pfad.slice(0, 80)}`);
    const inhalt = String(f?.inhalt ?? '');
    summe += Buffer.byteLength(inhalt, 'utf8');
    dateien.push({ pfad, inhalt });
  }
  if (!dateien.some((d) => d.pfad === 'index.html')) throw new Error('index.html fehlt.');
  if (dateien.length > grenzen.dateien || summe > grenzen.bytes) throw new Error(`Zu groß: ${dateien.length} Dateien, ${Math.round(summe / 1000)} KB.`);
  const zusammenfassung = String(innen.zusammenfassung ?? '').trim();
  if (zusammenfassung.length < 20) throw new Error('Zusammenfassung fehlt.');
  return { dateien, zusammenfassung };
}

export function reviewLesen(innen: any): { urteil: 'bestanden' | 'nachbessern'; markdown: string; maengel: string[] } {
  if (!innen || typeof innen !== 'object') throw new Error('Claude hat kein Review geliefert.');
  const urteil = innen.urteil === 'bestanden' ? 'bestanden' : 'nachbessern';
  const markdown = String(innen.markdown ?? '').trim();
  if (markdown.length < 200) throw new Error('Das Review ist leer oder zu kurz.');
  const maengel = Array.isArray(innen.maengel) ? innen.maengel.map((m: unknown) => String(m).trim()).filter(Boolean).slice(0, 12) : [];
  if (urteil === 'nachbessern' && maengel.length === 0) throw new Error('„nachbessern“ ohne Mängel.');
  return { urteil, markdown, maengel };
}

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
${GLIEDERUNG[a.art === 'pflichtenheft' ? 'pflichtenheft' : 'analyse'].replace('{titel}', a.titel)}

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
    if (a.art === 'bauen') {   // Phase 7: Builder
      const roh = await w.ausfuehren(bauenText(a), ordner, SCHEMA_BAUEN, a.kunde.website ? 'WebFetch' : '');
      writeFileSync(join(ordner, `auftrag-${a.id}.json`), roh);
      const e = bauenLesen(w.lesen(roh), a.grenzen);
      await api('bau_auftrag_melden', { id: a.id, ok: true, text: e.zusammenfassung, dateien: e.dateien });
      log.info('bau', `Auftrag #${a.id}: ${e.dateien.length} Dateien geliefert — Tests und Review folgen`);
      return true;
    }
    if (a.art === 'review') {   // Phase 7: Reviewer, ohne Werkzeuge
      const roh = await w.ausfuehren(reviewText(a), ordner, SCHEMA_REVIEW, '');
      writeFileSync(join(ordner, `auftrag-${a.id}.json`), roh);
      const r = reviewLesen(w.lesen(roh));
      await api('bau_auftrag_melden', { id: a.id, ok: true, text: r.markdown, urteil: r.urteil, maengel: r.maengel });
      log.info('bau', `Auftrag #${a.id}: Review ${r.urteil} (${r.maengel.length} Mängel)`);
      return true;
    }
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
