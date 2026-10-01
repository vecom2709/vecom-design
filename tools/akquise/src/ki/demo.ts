/* ==========================================================================
   Demo-Vorschau am PC (Marketing-Studio 10, 01.10.2026, Uwe: „ja“ zu S1).

   Ein Interessent hat in seinem Bereich ausdrücklich um eine Vorschau seiner
   neuen Startseite gebeten. Claude Code (Uwes Abo, nur WebSearch/WebFetch)
   liest seine bisherige Website und baut daraus EINE Seite — nur belegte
   Angaben, kein JavaScript. Die Verwaltung bereinigt sie noch einmal, zeigt
   sie Uwe, und erst Uwes Freigabe schickt den Link an den Interessenten.
   ========================================================================== */
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { api } from '../api.js';
import { datenOrdner } from '../konfig.js';
import { log } from '../log.js';

export type DemoAuftrag = {
  id: number; art: 'demo'; beschreibung: string; demo_id: number;
  betrieb: string; url: string; stadt: string; adresse: string; telefon: string; branche: string;
  sprache: string; hinweis: string; verbessern: string[]; max_bytes: number;
};

export const SCHEMA_DEMO = {
  type: 'object',
  required: ['html', 'zusammenfassung', 'quellen'],
  properties: {
    html: { type: 'string', description: 'Vollständiges HTML-Dokument der Startseite, nur Inline-CSS, ohne JavaScript' },
    zusammenfassung: { type: 'string', description: '2–4 Sätze auf Deutsch für Uwe' },
    quellen: { type: 'array', items: { type: 'string' } },
  },
};

const SPRACHE = { it: 'Italienisch (Lei-Form)', de: 'Deutsch (Sie-Form)', en: 'Englisch' } as Record<string, string>;

export function demoText(a: DemoAuftrag): string {
  const grenze = Math.floor(Math.min(a.max_bytes || 160_000, 160_000) * 0.5 / 1000);
  return `Du baust für Vecom Design (Webdesign aus Sizilien, Inhaber Uwe Vetter) eine KOSTENLOSE VORSCHAU der neuen Startseite eines Betriebs.
Der Betrieb hat selbst darum gebeten. Uwe sieht sie an, bevor der Betrieb sie bekommt.

AUFTRAG #${a.id}: ${a.beschreibung}
BETRIEB: ${a.betrieb || '(Name aus der Website lesen)'}
ORT: ${a.stadt || '–'}${a.adresse ? ` · Adresse laut Akquise: ${a.adresse}` : ''}
BRANCHE: ${a.branche || '–'}
TELEFON laut Akquise: ${a.telefon || '–'}
BISHERIGE WEBSITE: ${a.url}
SPRACHE DER SEITE: ${SPRACHE[a.sprache] ?? SPRACHE.it}
${a.verbessern.length ? `WAS UNSER WEBSITE-CHECK AN DER ALTEN SEITE FAND (die neue löst das):\n${a.verbessern.map((v) => '- ' + v).join('\n')}\n` : ''}${a.hinweis ? `HINWEIS VON UWE FÜR DIESEN NEUBAU: ${a.hinweis}\n` : ''}
VORGEHEN
1. Lies die bisherige Website mit WebFetch: die Startseite und höchstens drei Unterseiten (Angebot/Menü/Leistungen, Über uns, Kontakt).
   Sammle NUR belegte Fakten: Name, Angebot, Adresse, Telefon, E-Mail, Öffnungszeiten, Markenfarben, Fotos (absolute https-Adressen, die du gesehen hast).
2. Erfinde nichts. Keine Preise, Bewertungen, Sterne, Auszeichnungen, Gründungsjahre, Zitate, Personennamen oder Zahlen, die nicht auf der Seite stehen.
   Fehlt etwas, lass den Abschnitt weg oder formuliere neutral.
3. Baue EINE Startseite als vollständiges HTML-Dokument (<!doctype html>, <html lang="${a.sprache || 'it'}">, <head>, <body>):
   - Mobil zuerst (390 px breit), sauber bis 1280 px. Große Tippflächen (mindestens 48 px), Kontrast mindestens 4.5:1, echte Überschriften-Reihenfolge.
   - Nur CSS in einem <style>-Block. KEIN JavaScript, keine <script>, keine Formulare, keine iframes, keine Ereignis-Attribute.
     Externe Quellen nur: Google Fonts (höchstens zwei Familien) und Fotos von der bisherigen Website des Betriebs.
     Sonst Flächen, Verläufe und Typografie — keine Stockfotos, keine erfundenen Bildadressen, keine Platzhalterdienste.
   - Aufbau: Kopf mit Name · Hero mit klarem Nutzen und Hauptaktion (Anrufen über tel:, WhatsApp über wa.me nur bei Handynummer, Route über
     https://www.google.com/maps/search/?api=1&query=… mit der Adresse) · Angebot · kurz über den Betrieb aus echten Angaben · Öffnungszeiten und Kontakt · Fuß.
   - Hochwertig und eigenständig: eine klare Farbwelt aus den Markenfarben der alten Seite (veredelt, nicht kopiert), eine Display- und eine Textschrift,
     viel Weißraum, ruhige Hierarchie. Kein Template-Look, keine lila Verläufe, keine Emojis als Symbole.
   - Höchstens ${grenze} KB. Kein Hinweis auf „Vorschau“ nötig — den setzt die Verwaltung selbst darüber.
4. zusammenfassung: 2–4 Sätze AUF DEUTSCH für Uwe — was du übernommen hast, was fehlte, worauf er vor dem Freigeben schauen soll.
5. quellen: die Adressen, die du gelesen hast.`;
}

/** Das Ergebnis prüfen, bevor es zur Verwaltung geht. */
export function demoLesen(innen: any, maxBytes: number): { html: string; zusammenfassung: string; quellen: string[] } {
  const html = String(innen?.html ?? '').trim();
  if (html.length < 400 || !/<body[\s>]/i.test(html)) throw new Error('Claude hat keine vollständige Seite geliefert.');
  const bytes = Buffer.byteLength(html, 'utf8');
  if (bytes > (maxBytes || 160_000)) throw new Error(`Seite zu groß (${bytes} Bytes).`);
  const quellen = (Array.isArray(innen?.quellen) ? innen.quellen : []).map((q: unknown) => String(q)).filter((q: string) => /^https?:\/\/\S+$/.test(q)).slice(0, 12);
  return { html, zusammenfassung: String(innen?.zusammenfassung ?? '').slice(0, 1500), quellen };
}

type Werkzeug = { ausfuehren: (text: string, ordner: string, schema: object) => Promise<string>; lesen: (roh: string) => any };

export async function demoLauf(a: DemoAuftrag, w: Werkzeug): Promise<void> {
  log.info('marketing', `Auftrag #${a.id}: ${a.beschreibung} — Claude baut die Vorschau`);
  await api('status_melden', { art: 'marketing', stand: 0, ziel: 0, text: a.beschreibung }).catch(() => {});
  const ordner = datenOrdner('marketing');
  try {
    const roh = await w.ausfuehren(demoText(a), ordner, SCHEMA_DEMO);
    writeFileSync(join(ordner, `auftrag-${a.id}.json`), roh);
    const e = demoLesen(w.lesen(roh), a.max_bytes);
    writeFileSync(join(ordner, `demo-${a.demo_id}.html`), e.html);
    const j = await api('marketing_demo_melden', { auftrag_id: a.id, html: e.html, zusammenfassung: e.zusammenfassung, quellen: e.quellen });
    const text = `Vorschau fertig · ${Math.round((j.bytes ?? e.html.length) / 1000)} KB — wartet unter „Freigeben“ auf Uwe`;
    await api('marketing_auftrag_melden', { id: a.id, ok: true, text });
    log.info('marketing', `Auftrag #${a.id}: ${text}`);
  } catch (x) {
    const grund = (x as Error).message;
    log.fehler('marketing', `Auftrag #${a.id}: ${grund}`);
    await api('marketing_auftrag_melden', { id: a.id, ok: false, text: grund.slice(0, 900) }).catch(() => {});
  }
}
