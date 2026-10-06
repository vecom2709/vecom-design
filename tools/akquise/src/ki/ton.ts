/* ==========================================================================
   Töne am PC (Akquise-CRM Modul D-2, 06.10.2026, Uwe: KI „weiter über den
   PC-Worker“).

   In der Verwaltung klickt jemand unter einem Text „Kürzer“, „Lockerer“ oder
   „Professioneller“. Der Auftrag kommt wie alle Marketing-Aufträge über
   marketing_auftrag_holen; Claude Code (Uwes Abo, OHNE Werkzeuge) schreibt
   den Text um, und zwar nur mit dem, was im Text steht. Die Verwaltung lehnt
   jeden Vorschlag ab, der eine neue Zahl, einen neuen Link oder eine neue
   Adresse enthält. Übernommen wird von Hand; gesendet wird nie.
   ========================================================================== */
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { api } from '../api.js';
import { datenOrdner } from '../konfig.js';
import { log } from '../log.js';

export type TonAuftrag = {
  id: number; art: 'ton'; beschreibung: string;
  ton: 'kuerzer' | 'lockerer' | 'professioneller'; anweisung: string;
  kanal: 'email' | 'whatsapp'; sprache: 'it' | 'de' | 'en';
  betreff: string; text: string; zahlen: string[]; links: string[];
};

export const SCHEMA_TON = {
  type: 'object',
  required: ['betreff', 'text'],
  properties: {
    betreff: { type: 'string', description: 'Neuer Betreff (bei WhatsApp leer)' },
    text: { type: 'string', description: 'Der umgeschriebene Text, Absätze mit Leerzeile' },
  },
};

const SPRACHE = { it: 'Italienisch', de: 'Deutsch', en: 'Englisch' } as const;

export function tonText(a: TonAuftrag): string {
  const mail = a.kanal === 'email';
  return `Du formulierst eine ${mail ? 'E-Mail' : 'WhatsApp-Nachricht'} von Vecom Design (Webdesign, Uwe Vetter) an einen Betrieb um.
Uwe liest den Vorschlag und entscheidet selbst, ob er ihn übernimmt. Gesendet wird von dir nichts.

AUFTRAG #${a.id}: ${a.beschreibung}
SPRACHE: ${SPRACHE[a.sprache] ?? 'Italienisch'} — dieselbe Sprache und dieselbe Anredeform wie im Original.
WIE: ${a.anweisung}

HARTE REGELN
- Nichts dazuerfinden: keine neuen Fakten, keine Zahlen, Preise, Fristen, Rabatte, Garantien, Kundennamen, Bewertungen.
- Zahlen und Links nur aus dem Original, unverändert: ${a.zahlen.length ? a.zahlen.join(', ') : '(keine Zahlen)'} · ${a.links.length ? a.links.join(' ') : '(keine Links)'}
- Keine Zusicherungen wie „rechtssicher“, „abmahnsicher“, „garantiert“. Keine Angst- oder Druckformulierungen.
- Keine internen Wörter (Score, Lead, Akquise, Opportunity). Keine Platzhalter in eckigen Klammern.
- Der Satz, wie man weitere Nachrichten ablehnt, bleibt sinngemäß erhalten, wenn er im Original steht.
${mail ? '- Betreff: kurz, ehrlich, ohne Großbuchstaben-Wörter und ohne Ausrufezeichen. Er darf nie leer sein.' : '- WhatsApp: betreff bleibt leer. Kurze Absätze, keine Emojis.'}
- Kein HTML, kein Markdown, keine Erklärung — nur betreff und text im JSON.

ORIGINAL
${mail ? `Betreff: ${a.betreff}\n` : ''}${a.text}`;
}

/** Antwort prüfen, bevor sie zur Verwaltung geht — die Verwaltung prüft noch einmal. */
export function tonLesen(innen: any, a: Pick<TonAuftrag, 'kanal'>): { betreff: string; text: string } {
  if (!innen || typeof innen !== 'object') throw new Error('Claude hat keinen Text geliefert.');
  const text = String(innen.text ?? '').trim();
  const betreff = a.kanal === 'email' ? String(innen.betreff ?? '').trim() : '';
  if (text.length < 20) throw new Error('Der umgeschriebene Text ist leer oder zu kurz.');
  if (a.kanal === 'email' && betreff === '') throw new Error('Der Betreff fehlt.');
  if (/<\s*(script|iframe|style|a\s|p>|br)/i.test(text + betreff)) throw new Error('Der Text enthält HTML — nur Text erlaubt.');
  return { betreff, text };
}

type Werkzeug = { ausfuehren: (text: string, ordner: string, schema: object, werkzeuge?: string) => Promise<string>; lesen: (roh: string) => any };

export async function tonLauf(a: TonAuftrag, w: Werkzeug): Promise<void> {
  log.info('marketing', `Auftrag #${a.id}: ${a.beschreibung} — Claude formuliert um`);
  await api('status_melden', { art: 'marketing', stand: 0, ziel: 0, text: a.beschreibung }).catch(() => {});
  const ordner = datenOrdner('marketing');
  try {
    const roh = await w.ausfuehren(tonText(a), ordner, SCHEMA_TON, '');   // '' = gar keine Werkzeuge
    writeFileSync(join(ordner, `auftrag-${a.id}.json`), roh);
    const t = tonLesen(w.lesen(roh), a);
    const j = await api('akquise_ton_melden', { id: a.id, betreff: t.betreff, text: t.text });
    if (!j.ok) { log.warn('marketing', `Auftrag #${a.id}: ${j.hinweis ?? 'abgelehnt'}`); return; }   // Die Verwaltung hat den Grund schon vermerkt
    log.info('marketing', `Auftrag #${a.id}: Vorschlag liegt bereit`);
  } catch (x) {
    const grund = (x as Error).message;
    log.fehler('marketing', `Auftrag #${a.id}: ${grund}`);
    await api('marketing_auftrag_melden', { id: a.id, ok: false, text: grund.slice(0, 900) }).catch(() => {});
  }
}
