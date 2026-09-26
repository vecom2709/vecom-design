/* ==========================================================================
   Netzpruefungen ohne Browser: DNS, HTTPS, Zertifikat, Weiterleitungen,
   Antwortzeit, robots.txt, Linkcheck.

   Jede Pruefung liefert Tatsachen mit Beleg (Statuscode, Fehlercode,
   Weiterleitungskette) -- Bewertung passiert erst in audit/regeln/.
   ========================================================================== */
import { promises as dns } from 'node:dns';
import robotsParserModul from 'robots-parser';

/* robots-parser ist CommonJS; zur Laufzeit ist der Standardexport die
   Funktion, die Typen sehen unter NodeNext nur das Modul. */
type Robots = { isAllowed(url: string, ua?: string): boolean | undefined };
const robotsParser = robotsParserModul as unknown as (url: string, text: string) => Robots;
import { konfig } from '../konfig.js';
import { hoeflich } from '../hilfen.js';

export interface Abruf {
  url: string;
  status: number | null;
  location?: string | null;
  ms: number;
  fehler?: string;          // Fehlercode (z. B. CERT_HAS_EXPIRED, ENOTFOUND, ECONNREFUSED, TIMEOUT)
  kopf?: Record<string, string>;
}

const TLS_CODES = ['CERT_HAS_EXPIRED', 'ERR_TLS_CERT_ALTNAME_INVALID', 'DEPTH_ZERO_SELF_SIGNED_CERT', 'SELF_SIGNED_CERT_IN_CHAIN',
  'UNABLE_TO_VERIFY_LEAF_SIGNATURE', 'CERT_NOT_YET_VALID', 'UNABLE_TO_GET_ISSUER_CERT_LOCALLY', 'ERR_SSL_WRONG_VERSION_NUMBER',
  'EPROTO', 'ERR_SSL_TLSV1_ALERT_INTERNAL_ERROR'];

export const istTlsFehler = (code?: string) => !!code && TLS_CODES.some((c) => code.includes(c));

function fehlerCode(e: unknown): string {
  const err = e as { name?: string; code?: string; cause?: { code?: string; message?: string } };
  if (err?.name === 'TimeoutError' || err?.name === 'AbortError') return 'TIMEOUT';
  return err?.cause?.code ?? err?.code ?? (err?.cause?.message ?? String(e)).slice(0, 80);
}

/** Ein einzelner Abruf ohne Weiterleitung zu folgen. */
export async function abrufen(url: string, methode: 'GET' | 'HEAD' = 'GET', timeoutMs = 20_000): Promise<Abruf> {
  await hoeflich(url, konfig.domainPauseMs);
  const start = performance.now();
  try {
    const r = await fetch(url, {
      method: methode, redirect: 'manual',
      headers: { 'User-Agent': konfig.botKennung, 'Accept': 'text/html,application/xhtml+xml,*/*;q=0.8', 'Accept-Language': 'it,de;q=0.8,en;q=0.6' },
      signal: AbortSignal.timeout(timeoutMs),
    });
    const ms = Math.round(performance.now() - start);
    const kopf: Record<string, string> = {};
    r.headers.forEach((v, k) => { kopf[k] = v; });
    if (methode === 'GET') { await r.arrayBuffer().catch(() => null); }
    return { url, status: r.status, location: r.headers.get('location'), ms, kopf };
  } catch (e) {
    return { url, status: null, ms: Math.round(performance.now() - start), fehler: fehlerCode(e) };
  }
}

/** Folgt Weiterleitungen und merkt sich jeden Schritt. */
export async function kette(start: string, max = 8): Promise<Abruf[]> {
  const schritte: Abruf[] = [];
  let url = start;
  for (let i = 0; i < max; i++) {
    const a = await abrufen(url);
    schritte.push(a);
    if (a.status && a.status >= 300 && a.status < 400 && a.location) {
      url = new URL(a.location, url).toString();
      continue;
    }
    break;
  }
  return schritte;
}

export interface NetzBild {
  domain: string;
  dnsOk: boolean;
  dnsFehler?: string;
  https: Abruf[];          // Kette ab https://domain/
  http: Abruf[];           // Kette ab http://domain/
  endUrl: string | null;   // wo ein Besucher landet
  endStatus: number | null;
  ttfbMs: number | null;   // Median aus zwei Abrufen der Endadresse
  kopf: Record<string, string>;
}

export async function netzBild(domain: string, startUrl: string): Promise<NetzBild> {
  let dnsOk = false, dnsFehler: string | undefined;
  for (const host of [domain, 'www.' + domain]) {
    try { await dns.lookup(host); dnsOk = true; break; } catch (e) { dnsFehler = fehlerCode(e); }
  }
  if (!dnsOk) {
    // Zweiter Versuch nach einer Pause -- ein kurzer DNS-Aussetzer ist kein Befund.
    await new Promise((r) => setTimeout(r, 3000));
    try { await dns.lookup(domain); dnsOk = true; } catch (e) { dnsFehler = fehlerCode(e); }
  }
  const basisHost = new URL(/^https?:/i.test(startUrl) ? startUrl : 'http://' + startUrl).hostname;
  const https = dnsOk ? await kette(`https://${basisHost}/`) : [];
  const http = dnsOk ? await kette(`http://${basisHost}/`) : [];
  const beste = [https, http].find((k) => k.length && k[k.length - 1].status !== null && k[k.length - 1].status! < 400)
    ?? [https, http].find((k) => k.length && k[k.length - 1].status !== null);
  const letzte = beste?.[beste.length - 1];
  let ttfbMs: number | null = null;
  if (letzte?.status && letzte.status < 400) {
    const b = await abrufen(letzte.url, 'GET');
    const werte = [letzte.ms, b.ms].sort((x, y) => x - y);
    ttfbMs = werte[0];
  }
  return { domain, dnsOk, dnsFehler, https, http, endUrl: letzte?.url ?? null, endStatus: letzte?.status ?? null, ttfbMs, kopf: letzte?.kopf ?? {} };
}

/** robots.txt lesen. Liefert, ob wir die Startseite pruefen duerfen. */
export async function robotsErlaubt(basisUrl: string): Promise<{ erlaubt: boolean; sitemap: boolean; text: string }> {
  const u = new URL('/robots.txt', basisUrl).toString();
  const a = await kette(u, 3);
  const letzte = a[a.length - 1];
  if (!letzte || letzte.status !== 200) return { erlaubt: true, sitemap: false, text: '' };
  try {
    await hoeflich(letzte.url, konfig.domainPauseMs);
    const text = await (await fetch(letzte.url, { headers: { 'User-Agent': konfig.botKennung }, signal: AbortSignal.timeout(15_000) })).text();
    const r = robotsParser(u, text.slice(0, 200_000));
    const erlaubt = r.isAllowed(basisUrl, konfig.botName) !== false;
    return { erlaubt, sitemap: /^\s*sitemap\s*:/im.test(text), text: text.slice(0, 4000) };
  } catch {
    return { erlaubt: true, sitemap: false, text: '' };
  }
}

/** Prueft Links auf Fehlerseiten. HEAD zuerst, bei 405/501 GET. */
export async function linksPruefen(links: string[]): Promise<{ url: string; status: number | null; fehler?: string }[]> {
  const kaputt: { url: string; status: number | null; fehler?: string }[] = [];
  for (const url of links.slice(0, konfig.maxLinks)) {
    let a = await abrufen(url, 'HEAD', 15_000);
    if (a.status === 405 || a.status === 501 || a.status === 403) a = await abrufen(url, 'GET', 15_000);
    // Weiterleitungen einmal folgen: 301 auf eine gueltige Seite ist kein toter Link.
    if (a.status && a.status >= 300 && a.status < 400 && a.location) {
      a = await abrufen(new URL(a.location, url).toString(), 'GET', 15_000);
    }
    if (a.status === 404 || a.status === 410 || (a.status !== null && a.status >= 500)) {
      kaputt.push({ url, status: a.status });
    }
  }
  return kaputt;
}
