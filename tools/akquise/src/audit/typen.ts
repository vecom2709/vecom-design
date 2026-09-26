import type { NetzBild } from '../crawler/netz.js';
import type { BrowserBild, SeitenSignale } from './browser.js';
import type { Leistung } from './lighthouse.js';

export type Kategorie = 'technik' | 'performance' | 'mobile' | 'ux' | 'design' | 'seo' | 'conversion' | 'vertrauen' | 'experience';

/** Ein Befund, wie ihn die Verwaltung erwartet (akq_befunde). */
export interface Befund {
  kategorie: Kategorie;
  code: string;
  schwere: 1 | 2 | 3 | 4 | 5;
  titel: string;
  beschreibung?: string;
  wirkung?: string;
  url?: string;
  messwert?: { wert?: number; einheit?: 's' | 'MB' | 'KB' | 'px' | 'anzahl' | 'jahr'; text?: string; [k: string]: unknown };
  beleg?: string;
  status: 'VERIFIED' | 'UNVERIFIED';
}

export interface FirmaKurz {
  id: number; kennung: string; name: string; url: string; domain: string; land: 'DE' | 'IT';
  branche: string | null; stadt: string | null; sprache?: string | null; tourismus?: number | string;
}

/** Alles, was die Regeln sehen. Reine Daten -- testbar ohne Netz. */
export interface Rohdaten {
  firma: FirmaKurz;
  netz: NetzBild;
  robots: { erlaubt: boolean; sitemap: boolean; text: string };
  browser: BrowserBild | null;
  leistung: Leistung | null;
  kaputteLinks: { url: string; status: number | null }[];
  sitemapDa: boolean;
  jahr: number;
}

/** Alle Seiten, die angesehen wurden (Start mobil + Unterseiten). */
export function alleSeiten(r: Rohdaten): SeitenSignale[] {
  // Doppelte raus: Eine Unterseite, die auf die Startseite umleitet, ist die Startseite.
  const gesehen = new Set<string>();
  return [r.browser?.mobil, ...(r.browser?.unterseiten ?? [])].filter((s): s is SeitenSignale => {
    if (!s) return false;
    const k = s.url.split('#')[0].replace(/\/$/, '');
    if (gesehen.has(k)) return false;
    gesehen.add(k);
    return true;
  });
}

/* ==========================================================================
   Welche Sprachen bietet die Seite an?

   LEHRE AUS DEM ERSTEN ECHTEN LAUF (sizilienreisen.com, 24.09.2026): Die
   Seite hat eine englische Fassung unter /englisch/ -- verlinkt ueber eine
   Flagge ohne Text. Die erste Fassung dieser Pruefung kannte nur /en/ und
   haette der Inhaberin geschrieben, es gebe nur eine Sprache. Deshalb:
   Sprachnamen in allen drei Sprachen, Flaggenbilder, hreflang, ?lang=, und
   im Zweifel lieber "da" als eine falsche Behauptung.
   ========================================================================== */
const SPRACH_PFAD: [RegExp, string][] = [
  [/(^|[\/_.-])(en|eng|english|englisch|inglese|anglais)([\/_.-]|$)/i, 'en'],
  [/(^|[\/_.-])(de|deu|ger|deutsch|german|tedesco|allemand)([\/_.-]|$)/i, 'de'],
  [/(^|[\/_.-])(it|ita|italiano|italian|italienisch|italien)([\/_.-]|$)/i, 'it'],
  [/(^|[\/_.-])(fr|fra|francais|french|franzoesisch|francese)([\/_.-]|$)/i, 'fr'],
  [/(^|[\/_.-])(es|esp|espanol|spanish|spanisch|spagnolo)([\/_.-]|$)/i, 'es'],
];
const SPRACH_WORT = /^(en|de|it|fr|es|eng|deu|ita|english|deutsch|italiano|englisch|italienisch|inglese|tedesco|français|español)$/i;

export function sprachenDerSeite(s: SeitenSignale): Set<string> {
  const gefunden = new Set<string>();
  for (const h of s.hreflang) { const k = h.slice(0, 2).toLowerCase(); if (/^[a-z]{2}$/.test(k)) gefunden.add(k); }
  if (s.lang) gefunden.add(s.lang.slice(0, 2).toLowerCase());
  for (const l of s.links) {
    let pfad = '';
    try { const u = new URL(l.href); pfad = u.pathname + '?' + u.search; } catch { continue; }
    const q = pfad.match(/[?&](lang|lng|language|hl)=([a-z]{2})/i);
    if (q) gefunden.add(q[2].toLowerCase());
    for (const [re, k] of SPRACH_PFAD) if (re.test(pfad)) gefunden.add(k);
    const t = l.text.trim();
    if (SPRACH_WORT.test(t)) gefunden.add(t.slice(0, 2).toLowerCase());
  }
  if (/(english version|versione inglese|englische version|in english|auf deutsch|in italiano|versione italiana)/i.test(s.text)) gefunden.add('x');
  return gefunden;
}
