/* ==========================================================================
   Die Website so ansehen, wie ein Besucher sie sieht: Playwright, einmal als
   Smartphone (390 × 844) und einmal am Schreibtisch (1366 × 768).

   Hier wird nur GEMESSEN und GESAMMELT -- was davon ein Befund ist,
   entscheiden die Regeln in audit/regeln/. So lassen sich die Regeln mit
   festen Rohdaten pruefen, ohne jedes Mal einen Browser zu starten.
   ========================================================================== */
import { chromium, type Browser, type Page } from 'playwright';
import { konfig } from '../konfig.js';
import { gleicheSite, hoeflich } from '../hilfen.js';

export interface SeitenSignale {
  url: string;
  titel: string;
  metaDescription: string | null;
  lang: string | null;
  h1: string[];
  h2: number;
  canonical: string | null;
  robotsMeta: string | null;
  viewport: string | null;
  generator: string | null;
  jsonLdTypen: string[];
  hreflang: string[];
  text: string;                    // sichtbarer Text, gekuerzt
  links: { href: string; text: string }[];
  bilder: { gesamt: number; ohneAlt: number; kaputt: string[]; inhalt: number };
  telLinks: string[];
  mailtoLinks: string[];
  waLinks: string[];
  telefonImText: string[];
  formulare: { anzahl: number; mitTextarea: number; mitEmail: number; mitDatum: number };
  suchfeld: boolean;
  iframes: string[];
  skripte: string[];
  jquery: string | null;
  flash: boolean;
  frames: boolean;
  fontTags: number;
  marquee: boolean;
  layoutTabellen: number;
  copyrightJahre: number[];
  // nur mobil sinnvoll:
  ueberbreitePx: number;
  kleineSchriftAnteil: number;
  kleinsteSchriftPx: number;
  tapZiele: number;
  kleineTapZiele: number;
  ctaImErstenBildschirm: string[];
  kontaktErsteY: number | null;
  viewportHoehe: number;
  seitenHoehe: number;
  inhaltsBreite: number;
}

export interface BrowserBild {
  startUrl: string;
  endUrl: string;
  status: number | null;
  mobil: SeitenSignale | null;
  desktop: SeitenSignale | null;
  unterseiten: SeitenSignale[];
  konsoleFehler: string[];
  seitenFehler: string[];
  gemischteInhalte: string[];
  fehlgeschlagen: string[];
  bildBytes: { url: string; bytes: number }[];
  bytesGesamt: number;
  screenshotMobil: string | null;   // base64 JPEG
  screenshotDesktop: string | null;
  fehler?: string;
}

/* Die Auswertung im Browser. Laeuft IN der Seite -- darf daher nichts von
   aussen benutzen und muss mit kaputtem HTML zurechtkommen. */
function auswerten(): Omit<SeitenSignale, 'url'> {
  const q = (s: string) => Array.from(document.querySelectorAll(s));
  const meta = (n: string) => (document.querySelector(`meta[name="${n}" i]`) as HTMLMetaElement | null)?.content ?? null;
  const sichtbar = (el: Element) => {
    const r = (el as HTMLElement).getBoundingClientRect();
    const st = getComputedStyle(el as HTMLElement);
    return r.width > 0 && r.height > 0 && st.visibility !== 'hidden' && st.display !== 'none' && Number(st.opacity) > 0.05;
  };
  const vh = window.innerHeight;
  const text = (document.body?.innerText ?? '').replace(/\s+/g, ' ').trim();
  const links = q('a[href]').map((a) => ({ href: (a as HTMLAnchorElement).href, text: ((a as HTMLElement).innerText || a.getAttribute('aria-label') || a.getAttribute('title') || '').replace(/\s+/g, ' ').trim().slice(0, 80) }));

  // Kleine Schrift: Anteil der sichtbaren Textmenge unter 12 px.
  let klein = 0, gesamt = 0, kleinste = 99;
  const walker = document.createTreeWalker(document.body ?? document.documentElement, NodeFilter.SHOW_TEXT);
  let knoten: Node | null;
  let zaehler = 0;
  while ((knoten = walker.nextNode()) && zaehler < 4000) {
    zaehler++;
    const t = (knoten.textContent ?? '').trim();
    if (t.length < 3 || !knoten.parentElement) continue;
    const el = knoten.parentElement;
    if (!sichtbar(el)) continue;
    const px = parseFloat(getComputedStyle(el).fontSize);
    gesamt += t.length;
    if (px < 12) { klein += t.length; kleinste = Math.min(kleinste, px); }
  }

  // Tap-Ziele: sichtbare Links/Knoepfe kleiner als 32 × 32 px (Empfehlung 48, harte Grenze 24).
  const ziele = q('a[href], button, input[type=submit], [role=button]').filter(sichtbar);
  const kleineZiele = ziele.filter((el) => {
    const r = (el as HTMLElement).getBoundingClientRect();
    return (r.width < 32 || r.height < 24) && ((el as HTMLElement).innerText ?? '').trim().length > 0;
  });

  // Handlungsknoepfe im ersten Bildschirm.
  const ctaWoerter = /(prenot|contatt|chiam|richied|preventiv|ordina|scriv|whatsapp|buchen|reserv|kontakt|anfrag|anruf|termin|angebot|bestell|book|contact|call|enquir|quote|order|reserve)/i;
  const cta = ziele.filter((el) => {
    const r = (el as HTMLElement).getBoundingClientRect();
    const href = (el as HTMLAnchorElement).href ?? '';
    const t = ((el as HTMLElement).innerText || el.getAttribute('aria-label') || '').trim();
    return r.top < vh && r.bottom > 0 && (ctaWoerter.test(t) || /^(tel:|mailto:|https:\/\/wa\.me)/i.test(href));
  }).map((el) => ((el as HTMLElement).innerText || (el as HTMLAnchorElement).href || '').trim().slice(0, 40));

  // Erste Kontaktmoeglichkeit (Tel, Mail, WhatsApp, Kontakt-Link, Formular): wie weit unten?
  const kontaktEls = q('a[href^="tel:"], a[href^="mailto:"], a[href*="wa.me"], a[href*="whatsapp"], form, a[href*="contatt"], a[href*="kontakt"], a[href*="contact"]').filter(sichtbar);
  const ys = kontaktEls.map((el) => (el as HTMLElement).getBoundingClientRect().top + window.scrollY);
  const kontaktErsteY = ys.length ? Math.min(...ys) : null;

  // Telefonnummern im Text (IT/DE), die nicht als tel:-Link stehen.
  const telMuster = /(?:\(?(?:\+|00)\s?(?:39|49)\)?[\s./-]*)?\(?0?\d{2,4}\)?[\s./-]?\d{3,4}[\s./-]?\d{2,7}/g;
  const telefonImText = Array.from(new Set((text.match(telMuster) ?? []).map((s) => s.trim()).filter((s) => s.replace(/\D/g, '').length >= 8 && s.replace(/\D/g, '').length <= 14))).slice(0, 5);

  const bilder = q('img') as HTMLImageElement[];
  const inhaltsBilder = bilder.filter((b) => b.naturalWidth >= 200 || b.width >= 200);
  const jahre = Array.from(text.matchAll(/(?:©|&copy;|copyright)\s*(?:\d{4}\s*[-–]\s*)?(\d{4})/gi)).map((m) => Number(m[1])).filter((y) => y > 1995 && y < 2100);

  const main = document.querySelector('main, #main, .main, #content, .content, .container, body > div') as HTMLElement | null;
  return {
    titel: (document.title ?? '').trim(),
    metaDescription: meta('description'),
    lang: document.documentElement.getAttribute('lang'),
    h1: q('h1').map((h) => ((h as HTMLElement).innerText ?? '').trim()).filter(Boolean).slice(0, 5),
    h2: q('h2').length,
    canonical: (document.querySelector('link[rel="canonical"]') as HTMLLinkElement | null)?.href ?? null,
    robotsMeta: meta('robots'),
    viewport: meta('viewport'),
    generator: meta('generator'),
    jsonLdTypen: q('script[type="application/ld+json"]').flatMap((s) => {
      try {
        const j = JSON.parse(s.textContent ?? '');
        const alle = Array.isArray(j) ? j : j['@graph'] ? j['@graph'] : [j];
        return alle.map((x: any) => String(x?.['@type'] ?? '')).filter(Boolean);
      } catch { return []; }
    }),
    hreflang: q('link[rel="alternate"][hreflang]').map((l) => l.getAttribute('hreflang') ?? ''),
    text: text.slice(0, 20000),
    links: links.slice(0, 400),
    bilder: { gesamt: bilder.length, ohneAlt: inhaltsBilder.filter((b) => !(b.getAttribute('alt') ?? '').trim()).length,
      kaputt: bilder.filter((b) => b.complete && b.naturalWidth === 0 && !!b.currentSrc).map((b) => b.currentSrc).slice(0, 10), inhalt: inhaltsBilder.length },
    telLinks: links.filter((l) => l.href.startsWith('tel:')).map((l) => l.href),
    mailtoLinks: links.filter((l) => l.href.startsWith('mailto:')).map((l) => l.href.replace(/^mailto:/, '').split('?')[0]),
    waLinks: links.filter((l) => /wa\.me|whatsapp\.com/i.test(l.href)).map((l) => l.href),
    telefonImText,
    formulare: { anzahl: q('form').length, mitTextarea: q('form textarea').length, mitEmail: q('form input[type=email], form input[name*=mail i]').length,
      mitDatum: q('form input[type=date], form input[name*=data i], form input[name*=datum i]').length },
    suchfeld: q('input[type=search], input[name=s], input[name*=search i], input[name*=cerca i], input[name*=such i]').length > 0,
    iframes: q('iframe[src]').map((f) => (f as HTMLIFrameElement).src).slice(0, 20),
    skripte: q('script[src]').map((s) => (s as HTMLScriptElement).src).slice(0, 60),
    jquery: (window as any).jQuery?.fn?.jquery ?? null,
    flash: q('object[type*=flash], embed[type*=flash], embed[src$=".swf"], object[data$=".swf"]').length > 0,
    frames: q('frameset, frame').length > 0,
    fontTags: q('font').length,
    marquee: q('marquee, blink').length > 0,
    layoutTabellen: q('table table, table[width], body > table').length,
    copyrightJahre: jahre,
    ueberbreitePx: Math.max(0, document.documentElement.scrollWidth - window.innerWidth),
    kleineSchriftAnteil: gesamt ? Math.round((klein / gesamt) * 100) / 100 : 0,
    kleinsteSchriftPx: kleinste === 99 ? 0 : Math.round(kleinste * 10) / 10,
    tapZiele: ziele.length,
    kleineTapZiele: kleineZiele.length,
    ctaImErstenBildschirm: cta.slice(0, 6),
    kontaktErsteY,
    viewportHoehe: vh,
    seitenHoehe: document.documentElement.scrollHeight,
    inhaltsBreite: main ? Math.round(main.getBoundingClientRect().width) : 0,
  };
}

/** Welche Unterseiten sich lohnen: Kontakt, Impressum, Menue, Leistungen … */
const WICHTIG = /(contatt|kontakt|contact|impressum|imprint|note-legali|privacy|datenschutz|chi-siamo|about|ueber|uber-uns|menu|speisekarte|carta|servizi|leistungen|services|prenot|buch|book|camer|zimmer|rooms|galler|galerie|prezzi|preise|listino|progett|referenz|lavori|portfolio|prodott|produkte|shop|team)/i;

async function seiteAuswerten(page: Page, url: string): Promise<SeitenSignale | null> {
  try {
    await hoeflich(url, konfig.domainPauseMs);
    await page.goto(url, { waitUntil: 'load', timeout: 30_000 });
    await page.waitForTimeout(1200);
    const s = await page.evaluate(auswerten);
    return { url: page.url(), ...s };
  } catch {
    return null;
  }
}

/* tsx/esbuild versieht benannte Funktionen mit einem Helfer __name(). Wird
   auswerten() in die Seite geschickt, fehlt dieser Helfer dort -- und die
   ganze Auswertung bricht mit "ReferenceError: __name is not defined" ab.
   Gefunden beim ersten echten Lauf (sizilienreisen.com, 0 Seiten). Der
   Helfer wird deshalb vor jedem Laden in die Seite gelegt. */
const SHIM = 'window.__name = window.__name || function (f) { return f; };';

function proxy() {
  const p = process.env.HTTPS_PROXY || process.env.https_proxy;
  return p ? { server: p } : undefined;
}

let browser: Browser | null = null;
export async function browserHolen(): Promise<Browser> {
  if (!browser) browser = await chromium.launch({ headless: true, proxy: proxy() });
  return browser;
}
export async function browserZu(): Promise<void> {
  await browser?.close().catch(() => {});
  browser = null;
}

export async function ansehen(startUrl: string, land: 'DE' | 'IT'): Promise<BrowserBild> {
  const b = await browserHolen();
  const bild: BrowserBild = {
    startUrl, endUrl: startUrl, status: null, mobil: null, desktop: null, unterseiten: [],
    konsoleFehler: [], seitenFehler: [], gemischteInhalte: [], fehlgeschlagen: [], bildBytes: [], bytesGesamt: 0,
    screenshotMobil: null, screenshotDesktop: null,
  };
  const sprache = land === 'DE' ? 'de-DE' : 'it-IT';

  /* ---------- mobil ---------- */
  const mobil = await b.newContext({
    viewport: { width: 390, height: 844 }, deviceScaleFactor: 1, isMobile: true, hasTouch: true, locale: sprache,
    userAgent: 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Mobile Safari/537.36 ' + konfig.botKennung,
    ignoreHTTPSErrors: false, reducedMotion: 'reduce',
  });
  await mobil.addInitScript({ content: SHIM });
  const page = await mobil.newPage();
  page.on('console', (m) => { if (m.type() === 'error') bild.konsoleFehler.push(m.text().slice(0, 200)); });
  page.on('pageerror', (e) => bild.seitenFehler.push(e.message.slice(0, 200)));
  page.on('requestfailed', (r) => bild.fehlgeschlagen.push(`${r.url().slice(0, 150)} — ${r.failure()?.errorText ?? ''}`));
  page.on('response', async (r) => {
    try {
      const url = r.url();
      if (page.url().startsWith('https:') && url.startsWith('http:')) bild.gemischteInhalte.push(url.slice(0, 150));
      const len = Number(r.headers()['content-length'] ?? 0);
      const typ = r.headers()['content-type'] ?? '';
      let bytes = len;
      if (!bytes && typ.startsWith('image/')) bytes = (await r.body().catch(() => Buffer.alloc(0))).length;
      bild.bytesGesamt += bytes;
      if (typ.startsWith('image/')) bild.bildBytes.push({ url: url.slice(0, 200), bytes });
    } catch { /* Messung ist Beiwerk */ }
  });
  try {
    await hoeflich(startUrl, konfig.domainPauseMs);
    const antwort = await page.goto(startUrl, { waitUntil: 'load', timeout: 45_000 });
    bild.status = antwort?.status() ?? null;
    await page.waitForLoadState('networkidle', { timeout: 8_000 }).catch(() => {});
    await page.waitForTimeout(1500);
    bild.endUrl = page.url();
    bild.mobil = { url: page.url(), ...(await page.evaluate(auswerten)) };
    bild.screenshotMobil = (await page.screenshot({ type: 'jpeg', quality: 68, fullPage: false })).toString('base64');

    // Unterseiten: nur dieselbe Website, nur die, die fuer die Pruefung zaehlen.
    const kandidaten = Array.from(new Set(bild.mobil.links
      .filter((l) => l.href.startsWith('http') && gleicheSite(l.href, bild.endUrl) && !/\.(pdf|jpe?g|png|gif|zip|docx?)(\?|$)/i.test(l.href))
      .filter((l) => WICHTIG.test(l.href) || WICHTIG.test(l.text))
      .map((l) => l.href.split('#')[0])))
      .filter((u) => u !== bild.endUrl.split('#')[0])
      .slice(0, konfig.maxSeiten - 1);
    for (const u of kandidaten) {
      const s = await seiteAuswerten(page, u);
      if (s) bild.unterseiten.push(s);
    }
  } catch (e) {
    bild.fehler = (e as Error).message.split('\n')[0].slice(0, 200);
  } finally {
    await mobil.close().catch(() => {});
  }

  /* ---------- Desktop ---------- */
  if (bild.mobil) {
    const desk = await b.newContext({ viewport: { width: 1366, height: 768 }, locale: sprache, reducedMotion: 'reduce',
      userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Safari/537.36 ' + konfig.botKennung });
    await desk.addInitScript({ content: SHIM });
    const dp = await desk.newPage();
    try {
      await hoeflich(bild.endUrl, konfig.domainPauseMs);
      await dp.goto(bild.endUrl, { waitUntil: 'load', timeout: 45_000 });
      await dp.waitForTimeout(1500);
      bild.desktop = { url: dp.url(), ...(await dp.evaluate(auswerten)) };
      bild.screenshotDesktop = (await dp.screenshot({ type: 'jpeg', quality: 60, fullPage: false })).toString('base64');
    } catch { /* Desktop ist Zugabe */ } finally {
      await desk.close().catch(() => {});
    }
  }
  return bild;
}
