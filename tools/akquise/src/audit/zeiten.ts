/* ==========================================================================
   Öffnungszeiten von der eigenen Website des Betriebs (29.09.2026, Uwe: D3).

   Der Partner soll vor dem Anruf sehen, wann der Betrieb offen hat. Quelle
   ist ausschließlich die Website des Betriebs selbst -- kein Google Maps.

   REIHENFOLGE
   1. Strukturierte Daten (Schema.org openingHours / openingHoursSpecification,
      JSON-LD oder Microdata). Die hat der Betrieb bewusst so hinterlegt.
   2. Nur wenn es die nicht gibt: Text direkt hinter „Orari“, „Öffnungszeiten“,
      „Opening hours“ mit Wochentag UND Uhrzeit. Lieber nichts als etwas
      Geratenes -- ein falsches „jetzt geöffnet“ schickt den Partner in die
      Stoßzeit.

   Ausgabe: [{ t: [1..7], v: 'HH:MM', b: 'HH:MM' }], 1 = Montag, 7 = Sonntag.
   Schließt ein Zeitraum nach Mitternacht, ist b kleiner als v.
   ========================================================================== */

export interface Zeitraum { t: number[]; v: string; b: string }
export interface Oeffnung { zeiten: Zeitraum[]; quelle: 'daten' | 'text' }

const EN: Record<string, number> = { mo: 1, tu: 2, we: 3, th: 4, fr: 5, sa: 6, su: 7 };
const LANG: Record<string, number> = { monday: 1, tuesday: 2, wednesday: 3, thursday: 4, friday: 5, saturday: 6, sunday: 7 };
/** Wochentage in Texten (it, de, en) -- Anfang des Wortes genügt. */
const TEXT_TAGE: [RegExp, number][] = [
  [/^(dom|so|sun)/i, 7],   // zuerst: „dom“ (Domenica) darf nicht als „do“ (Donnerstag) gelten
  [/^(lun|mo|mon)/i, 1], [/^(mar|di|die|tue)/i, 2], [/^(mer|mi|wed)/i, 3], [/^(gio|do|don|thu)/i, 4],
  [/^(ven|fr|fri)/i, 5], [/^(sab|sa|sat)/i, 6],
];

function uhr(s: unknown): string | null {
  const m = String(s ?? '').trim().match(/^(\d{1,2})[:.h](\d{2})/);
  if (!m) return null;
  const h = Number(m[1]); const min = Number(m[2]);
  if (h > 24 || min > 59) return null;
  return `${String(h === 24 ? 0 : h).padStart(2, '0')}:${String(min).padStart(2, '0')}`;
}

function spanne(a: number, b: number): number[] {
  const r: number[] = [];
  for (let d = a, n = 0; n < 7; n++) { r.push(d); if (d === b) break; d = d === 7 ? 1 : d + 1; }
  return r;
}

/** „Mo-Fr 09:00-18:00“, „Mo,We 10:00-12:00,15:00-19:00“, „Mo-Su“ (= rund um die Uhr). */
export function openingHoursLesen(s: string): Zeitraum[] {
  const out: Zeitraum[] = [];
  for (const teil of s.split(/;|\n/)) {
    const m = teil.trim().match(/^([A-Za-z]{2}(?:\s*[-,]\s*[A-Za-z]{2})*)\s*(.*)$/);
    if (!m) continue;
    const tage = new Set<number>();
    for (const stueck of m[1].split(',')) {
      const [a, b] = stueck.split('-').map((x) => EN[x.trim().toLowerCase()]);
      if (!a) continue;
      for (const d of b ? spanne(a, b) : [a]) tage.add(d);
    }
    if (!tage.size) continue;
    const zeiten = m[2].trim();
    if (zeiten === '') { out.push({ t: [...tage].sort(), v: '00:00', b: '00:00' }); continue; }
    for (const z of zeiten.split(',')) {
      const [v, b] = z.split('-').map(uhr);
      if (v && b) out.push({ t: [...tage].sort(), v, b });
    }
  }
  return out;
}

function tagAusSpez(x: unknown): number | null {
  const s = String(x ?? '').replace(/^https?:\/\/schema\.org\//i, '').toLowerCase();
  return LANG[s] ?? EN[s.slice(0, 2)] ?? null;
}

/** openingHoursSpecification: Objekt oder Liste mit dayOfWeek, opens, closes. */
export function spezLesen(spez: unknown): Zeitraum[] {
  const out: Zeitraum[] = [];
  for (const e of Array.isArray(spez) ? spez : [spez]) {
    if (!e || typeof e !== 'object') continue;
    const o = e as Record<string, unknown>;
    if (o.validThrough && Date.parse(String(o.validThrough)) < Date.now()) continue;   // abgelaufene Sonderzeiten
    const tage = (Array.isArray(o.dayOfWeek) ? o.dayOfWeek : [o.dayOfWeek]).map(tagAusSpez).filter((d): d is number => d !== null);
    const v = uhr(o.opens); const b = uhr(o.closes);
    if (tage.length && v && b) out.push({ t: [...new Set(tage)].sort(), v, b });
  }
  return out;
}

/** Sucht in JSON-LD (auch @graph, verschachtelt) nach Öffnungszeiten. */
export function ausJsonLd(daten: unknown, tiefe = 0): Zeitraum[] {
  if (tiefe > 5 || daten === null || typeof daten !== 'object') return [];
  if (Array.isArray(daten)) return daten.flatMap((x) => ausJsonLd(x, tiefe + 1));
  const o = daten as Record<string, unknown>;
  const eigene: Zeitraum[] = [];
  if (o.openingHoursSpecification) eigene.push(...spezLesen(o.openingHoursSpecification));
  if (!eigene.length && o.openingHours) {
    for (const s of Array.isArray(o.openingHours) ? o.openingHours : [o.openingHours]) eigene.push(...openingHoursLesen(String(s)));
  }
  if (eigene.length) return eigene;
  return Object.values(o).flatMap((x) => (x && typeof x === 'object' ? ausJsonLd(x, tiefe + 1) : []));
}

const SCHLUESSEL = /(orari(?:o)?(?: di apertura)?|apertura|öffnungszeiten|oeffnungszeiten|geöffnet|opening hours|opening times)\s*:?/i;
const TAG = '(lun(?:edì|edi)?|mar(?:tedì|tedi)?|mer(?:coledì|coledi)?|gio(?:vedì|vedi)?|ven(?:erdì|erdi)?|sab(?:ato)?|dom(?:enica)?|mo(?:ntag)?|di(?:enstag)?|mi(?:ttwoch)?|do(?:nnerstag)?|fr(?:eitag)?|sa(?:mstag)?|so(?:nntag)?|mon(?:day)?|tue(?:sday)?|wed(?:nesday)?|thu(?:rsday)?|fri(?:day)?|sat(?:urday)?|sun(?:day)?)\\.?';
const ZEIT = '(\\d{1,2}[:.]\\d{2})\\s*(?:-|–|—|alle|bis|to)\\s*(\\d{1,2}[:.]\\d{2})';
const ZEILE = new RegExp(`\\b${TAG}(?:\\s*(?:-|–|—|al|bis|to)\\s*${TAG})?\\s*:?\\s*${ZEIT}(?:\\s*(?:,|e|und|and|/)\\s*${ZEIT})?`, 'gi');

function textTag(w: string): number | null {
  for (const [re, d] of TEXT_TAGE) if (re.test(w)) return d;
  return null;
}

/** Nur der Text direkt hinter einem Stichwort -- sonst trifft „Mo“ jedes „Montag“ im Fließtext. */
export function ausText(text: string): Zeitraum[] {
  const i = text.search(SCHLUESSEL);
  if (i < 0) return [];
  const stueck = text.slice(i, i + 400);
  const out: Zeitraum[] = [];
  for (const m of stueck.matchAll(ZEILE)) {
    const a = textTag(m[1]); const b = m[2] ? textTag(m[2]) : null;
    if (!a) continue;
    const tage = b ? spanne(a, b) : [a];
    for (const [v0, b0] of [[m[3], m[4]], [m[5], m[6]]]) {
      const v = uhr(v0); const bis = uhr(b0);
      if (v && bis) out.push({ t: tage, v, b: bis });
    }
  }
  return out;
}

/** Gleiche Einträge zusammenlegen, höchstens 14 behalten. */
export function aufraeumen(z: Zeitraum[]): Zeitraum[] {
  const gesehen = new Set<string>();
  return z.filter((x) => { const k = `${x.t.join(',')}|${x.v}|${x.b}`; if (gesehen.has(k)) return false; gesehen.add(k); return true; }).slice(0, 14);
}

/** Aus allen gesehenen Seiten: erst strukturierte Daten, dann Text. */
export function oeffnungLesen(roh: unknown[], texte: string[]): Oeffnung | null {
  const daten = aufraeumen(roh.flatMap((r) => ausJsonLd(r)));
  if (daten.length) return { zeiten: daten, quelle: 'daten' };
  for (const t of texte) {
    const z = aufraeumen(ausText(t));
    if (z.length) return { zeiten: z, quelle: 'text' };
  }
  return null;
}
