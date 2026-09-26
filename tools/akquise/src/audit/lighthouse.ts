/* ==========================================================================
   Leistungsmessung: Google PageSpeed Insights (wenn ein Schluessel da ist),
   sonst Lighthouse lokal im Chromium von Playwright.

   PSI hat zwei Vorteile: Die Messung laeuft in Googles Netz statt auf einem
   ausgelasteten Laptop (weniger Rauschen), und es gibt Felddaten echter
   Besucher (CrUX) -- INP ist nur so ehrlich zu bekommen. Lokal ist die
   Laborzahl trotzdem brauchbar und kostet nichts.

   Lighthouse 13 hat viele Einzelaudits durch "Insights" ersetzt; deshalb
   wird jede Kennzahl unter mehreren moeglichen Namen gesucht.
   ========================================================================== */
import { chromium } from 'playwright';
import * as chromeLauncher from 'chrome-launcher';
import { konfig } from '../konfig.js';
import { log } from '../log.js';

export interface Leistung {
  quelle: 'PageSpeed Insights' | 'Lighthouse lokal';
  performance?: number; seo?: number; accessibility?: number; bestPractices?: number;
  lcp_ms?: number; cls?: number; tbt_ms?: number; fcp_ms?: number; si_ms?: number; ttfb_ms?: number; bytes?: number;
  inp_ms?: number;              // nur Felddaten (PSI)
  feld_lcp_ms?: number; feld_cls?: number;
  renderBlockingMs?: number; unusedJsBytes?: number; unusedCssBytes?: number;
  cacheSchwach?: boolean; kompressionFehlt?: boolean; bildSparBytes?: number; kontrastFehler?: number;
  fehler?: string;
}

type Lhr = { categories: Record<string, { score: number | null }>; audits: Record<string, any> };

const zahl = (a: any) => (typeof a?.numericValue === 'number' ? Math.round(a.numericValue * 1000) / 1000 : undefined);
function erstes(audits: Record<string, any>, ids: string[]): any {
  for (const id of ids) if (audits[id]) return audits[id];
  return undefined;
}
function einsparung(a: any, art: 'ms' | 'bytes'): number | undefined {
  if (!a) return undefined;
  const d = a.details ?? {};
  const v = art === 'ms' ? (d.overallSavingsMs ?? a.metricSavings?.FCP ?? a.metricSavings?.LCP) : (d.overallSavingsBytes);
  return typeof v === 'number' ? Math.round(v) : undefined;
}

export function ausLhr(lhr: Lhr, quelle: Leistung['quelle']): Leistung {
  const a = lhr.audits;
  const kat = (k: string) => (lhr.categories[k]?.score != null ? Math.round((lhr.categories[k].score as number) * 100) : undefined);
  const cache = erstes(a, ['cache-insight', 'uses-long-cache-ttl']);
  const doc = erstes(a, ['document-latency-insight', 'uses-text-compression']);
  const kompression = doc?.details?.items?.find?.((i: any) => /compression/i.test(i?.label ?? i?.key ?? ''))
    ?? (doc?.id === 'uses-text-compression' ? doc : undefined);
  const kontrast = a['color-contrast'];
  return {
    quelle,
    performance: kat('performance'), seo: kat('seo'), accessibility: kat('accessibility'), bestPractices: kat('best-practices'),
    lcp_ms: zahl(a['largest-contentful-paint']), cls: zahl(a['cumulative-layout-shift']), tbt_ms: zahl(a['total-blocking-time']),
    fcp_ms: zahl(a['first-contentful-paint']), si_ms: zahl(a['speed-index']), ttfb_ms: zahl(a['server-response-time']),
    bytes: zahl(a['total-byte-weight']),
    renderBlockingMs: einsparung(erstes(a, ['render-blocking-insight', 'render-blocking-resources']), 'ms'),
    unusedJsBytes: einsparung(a['unused-javascript'], 'bytes'),
    unusedCssBytes: einsparung(a['unused-css-rules'], 'bytes'),
    cacheSchwach: cache ? (cache.score !== null && cache.score < 0.5) : undefined,
    kompressionFehlt: kompression ? (kompression.score === 0 || kompression.value === false || kompression.score === false) : undefined,
    bildSparBytes: einsparung(erstes(a, ['image-delivery-insight', 'uses-optimized-images', 'modern-image-formats']), 'bytes'),
    kontrastFehler: kontrast && kontrast.score === 0 ? (kontrast.details?.items?.length ?? 1) : 0,
  };
}

async function psi(url: string): Promise<Leistung> {
  const p = new URLSearchParams({ url, strategy: 'mobile', key: konfig.psiKey });
  for (const c of ['performance', 'seo', 'accessibility', 'best-practices']) p.append('category', c);
  const r = await fetch('https://www.googleapis.com/pagespeedonline/v5/runPagespeed?' + p, { signal: AbortSignal.timeout(120_000) });
  if (!r.ok) throw new Error(`PSI ${r.status}: ${(await r.text()).slice(0, 160)}`);
  const j: any = await r.json();
  const l = ausLhr(j.lighthouseResult, 'PageSpeed Insights');
  const f = j.loadingExperience?.metrics ?? {};
  if (f.INTERACTION_TO_NEXT_PAINT?.percentile) l.inp_ms = f.INTERACTION_TO_NEXT_PAINT.percentile;
  if (f.LARGEST_CONTENTFUL_PAINT_MS?.percentile) l.feld_lcp_ms = f.LARGEST_CONTENTFUL_PAINT_MS.percentile;
  if (f.CUMULATIVE_LAYOUT_SHIFT_SCORE?.percentile !== undefined) l.feld_cls = f.CUMULATIVE_LAYOUT_SHIFT_SCORE.percentile / 100;
  return l;
}

async function lokal(url: string): Promise<Leistung> {
  const { default: lighthouse } = await import('lighthouse');
  const flags = ['--headless=new', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage'];
  const px = process.env.HTTPS_PROXY || process.env.https_proxy;
  if (px) flags.push(`--proxy-server=${px}`);
  const chrome = await chromeLauncher.launch({ chromePath: chromium.executablePath(), chromeFlags: flags, logLevel: 'silent' });
  try {
    const r = await lighthouse(url, {
      port: chrome.port, output: 'json', logLevel: 'error',
      onlyCategories: ['performance', 'seo', 'accessibility', 'best-practices'],
      formFactor: 'mobile', throttlingMethod: 'simulate', maxWaitForLoad: 45_000,
    });
    if (!r?.lhr) throw new Error('Lighthouse lieferte kein Ergebnis');
    if (r.lhr.runtimeError) throw new Error(`Lighthouse: ${r.lhr.runtimeError.code} ${r.lhr.runtimeError.message ?? ''}`);
    return ausLhr(r.lhr as unknown as Lhr, 'Lighthouse lokal');
  } finally {
    await chrome.kill();
  }
}

export async function leistungMessen(url: string): Promise<Leistung | null> {
  try {
    return konfig.psiKey ? await psi(url) : await lokal(url);
  } catch (e) {
    log.warn('lighthouse', `${url}: ${(e as Error).message.slice(0, 160)}`);
    if (konfig.psiKey) {
      try { return await lokal(url); } catch { /* fallthrough */ }
    }
    return null;
  }
}
