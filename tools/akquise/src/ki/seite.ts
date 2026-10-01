/* ==========================================================================
   Landingpage je Zielgruppe am PC (01.10.2026, Uwe: Ja zu S6).

   Claude Code (Uwes Abo, ohne Werkzeuge — alles Nötige steht im Profil)
   schreibt aus dem freigegebenen Zielgruppen-Profil den TEXT einer Seite,
   die genau diese Betriebe anspricht. Nur JSON, kein HTML: Aufbau, Knöpfe
   und Links setzt die Verwaltung (MkSeite.php). Online geht die Seite erst
   nach Uwes Freigabe.
   ========================================================================== */
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { api } from '../api.js';
import { datenOrdner } from '../konfig.js';
import { log } from '../log.js';

export type SeiteAuftrag = {
  id: number; art: 'seite'; beschreibung: string;
  zielgruppe_id: number; land: 'IT' | 'DE'; sprache: 'it' | 'de';
  branche: string; branche_name: string;
  profil: Record<string, unknown>;
  grenzen: Record<string, number>;
  bisher: Record<string, unknown> | null;
};

const TEXT = { type: 'string' };
export const SCHEMA_SEITE = {
  type: 'object',
  required: ['titel', 'beschreibung', 'kicker', 'h1', 'lead', 'abschnitte', 'faq', 'cta_titel', 'cta_text', 'lesen_de'],
  properties: {
    titel: TEXT, beschreibung: TEXT, kicker: TEXT, h1: TEXT, lead: TEXT,
    abschnitte: { type: 'array', items: { type: 'object', required: ['h2', 'text', 'punkte'], properties: { h2: TEXT, text: TEXT, punkte: { type: 'array', items: TEXT } } } },
    faq: { type: 'array', items: { type: 'object', required: ['frage', 'antwort'], properties: { frage: TEXT, antwort: TEXT } } },
    cta_titel: TEXT, cta_text: TEXT,
    lesen_de: { type: 'string', description: 'Die ganze Seite auf Deutsch zum Lesen für Uwe (bei deutschen Seiten leer)' },
  },
};

const SPRACHE = { it: 'Italienisch, Lei-Form (formell, warm, klar)', de: 'Deutsch, Sie-Form (klar, freundlich, ohne Werbesprech)' };

export function seiteText(a: SeiteAuftrag): string {
  const g = a.grenzen ?? {};
  const it = a.sprache === 'it';
  return `Du schreibst für Vecom Design den Text einer LANDINGPAGE für genau eine Zielgruppe: ${a.branche_name} in ${it ? 'Italien' : 'Deutschland'}.
Auf diese Seite führen Beiträge und Anzeigen dieser Zielgruppe. Sie soll in Sekunden zeigen: „Die verstehen meinen Betrieb“ — und zum
kostenlosen Website-Check führen. Uwe (Inhaber, Deutscher) liest die Seite und stellt sie selbst online.

AUFTRAG #${a.id}: ${a.beschreibung}
SPRACHE DER SEITE: ${SPRACHE[a.sprache] ?? SPRACHE.it}

VECOM DESIGN — NUR DIESE FAKTEN VERWENDEN
- Websites für kleine Betriebe, gebaut von einem kleinen Studio in Sizilien (Aragona); ein Mensch begleitet den Kunden von Anfang bis Ende.
- Der Preis steht vorher fest und ist auf der Preisseite offen sichtbar; im Preisrechner sieht man in etwa anderthalb Minuten seine Preisspanne.
- Kostenloser Website-Check: zwölf Punkte als Ampel, in Sekunden, ohne Anmeldung (Handy-Tauglichkeit, Tempo, Auffindbarkeit, Kontakt mit einem Tipp …).
- Auf Wunsch eine kostenlose Vorschau der neuen Startseite. ${it ? 'Seiten auf Italienisch, Deutsch und Englisch möglich.' : 'Seiten auf Deutsch, Italienisch und Englisch möglich.'}
Alles andere ist ERFUNDEN und verboten: keine Preise oder Zahlen, keine Kundenstimmen, Bewertungen, Sterne, Referenzen, Kundennamen,
keine Garantien („garantiert mehr Gäste“, „Platz 1 bei Google“), keine Fristen, keine Rabatte, keine Förderzusagen.

DAS PROFIL DER ZIELGRUPPE (freigegeben von Uwe — daraus schreibst du; Probleme und Wünsche stehen auf Deutsch, Einwände, Fragen und
Botschaften in der Sprache der Kunden)
${JSON.stringify(a.profil ?? {}, null, 1).slice(0, 40_000)}
${a.bisher ? `\nBISHERIGE FASSUNG DER SEITE (überarbeiten, nicht wiederholen — besser, klarer, näher an den Einwänden):\n${JSON.stringify(a.bisher, null, 1).slice(0, 15_000)}\n` : ''}
SO IST DIE SEITE GEBAUT (alles in der Sprache der Seite, außer lesen_de)
- titel (≤${g.titel ?? 70}, für Google, mit Branche) · beschreibung (≤${g.beschreibung ?? 160}, für Google) · kicker (≤${g.kicker ?? 48}, kleine Zeile über der Überschrift)
- h1 (≤${g.h1 ?? 100}): spricht das wichtigste Problem oder den wichtigsten Wunsch dieser Betriebe an, in ihren Worten.
- lead (≤${g.lead ?? 360}): zwei, drei Sätze — was Vecom für genau diese Branche anders macht.
- abschnitte: 3 bis 4, je h2 (≤${g.h2 ?? 90}), text (≤${g.text ?? 900}, Absätze mit Leerzeile) und 0–5 punkte (≤${g.punkt ?? 220}).
  Empfohlen: (1) was diese Betriebe heute online verlieren — konkret für die Branche, (2) was ihre Website können muss
  (z. B. Speisekarte statt PDF, Buchungsanfrage, Anruf mit einem Tipp — passend zur Branche), (3) wie die Zusammenarbeit abläuft und was es kostet
  (Preis vorher, Preisseite), (4) optional: was gegen die häufigsten Einwände spricht.
- faq: 3 bis 6 echte Fragen aus profil.fragen / profil.einwaende (≤${g.frage ?? 160}) mit ehrlicher Antwort (≤${g.antwort ?? 600}) — nur mit den Fakten oben.
- cta_titel (≤${g.cta_titel ?? 90}) und cta_text (≤${g.cta_text ?? 320}): führt zum kostenlosen Website-Check. Die Knöpfe setzt die Verwaltung.
- lesen_de: ${it ? 'die GANZE Seite sinngemäß auf Deutsch, in derselben Reihenfolge (Überschriften in eigenen Zeilen) — nur zum Lesen für Uwe.' : 'leer lassen.'}

STIL: kurze Sätze, konkrete Wörter der Branche, keine Floskeln („innovativ“, „maßgeschneidert“, „digitale Präsenz“), keine Ausrufezeichen-Ketten,
keine Emojis, kein HTML, kein Markdown.`;
}

/** Antwort prüfen, bevor sie zur Verwaltung geht. */
export function seiteLesen(innen: any): Record<string, unknown> {
  if (!innen || typeof innen !== 'object') throw new Error('Claude hat keine Seite geliefert.');
  for (const f of ['titel', 'h1', 'lead']) if (String(innen[f] ?? '').trim() === '') throw new Error(`Feld „${f}“ fehlt.`);
  const abschnitte = Array.isArray(innen.abschnitte) ? innen.abschnitte : [];
  if (abschnitte.length < 2) throw new Error('Zu wenige Abschnitte.');
  const alles = JSON.stringify(innen);
  if (/<\s*(script|iframe|style|a\s)/i.test(alles)) throw new Error('Die Seite enthält HTML — nur Text erlaubt.');
  return innen;
}

type Werkzeug = { ausfuehren: (text: string, ordner: string, schema: object, werkzeuge?: string) => Promise<string>; lesen: (roh: string) => any };

export async function seiteLauf(a: SeiteAuftrag, w: Werkzeug): Promise<void> {
  log.info('marketing', `Auftrag #${a.id}: ${a.beschreibung} — Claude schreibt die Seite`);
  await api('status_melden', { art: 'marketing', stand: 0, ziel: 0, text: a.beschreibung }).catch(() => {});
  const ordner = datenOrdner('marketing');
  try {
    const roh = await w.ausfuehren(seiteText(a), ordner, SCHEMA_SEITE, '');
    writeFileSync(join(ordner, `auftrag-${a.id}.json`), roh);
    const seite = seiteLesen(w.lesen(roh));
    const j = await api('marketing_seite_melden', { auftrag_id: a.id, seite });
    if (!j.ok) throw new Error(String(j.hinweis ?? 'Verwaltung hat die Seite abgelehnt.'));
    await api('marketing_auftrag_melden', { id: a.id, ok: true, text: 'Seite fertig — wartet bei der Zielgruppe auf Uwes Ja.' });
    log.info('marketing', `Auftrag #${a.id}: Seite fertig`);
  } catch (x) {
    const grund = (x as Error).message;
    log.fehler('marketing', `Auftrag #${a.id}: ${grund}`);
    await api('marketing_auftrag_melden', { id: a.id, ok: false, text: grund.slice(0, 900) }).catch(() => {});
  }
}
