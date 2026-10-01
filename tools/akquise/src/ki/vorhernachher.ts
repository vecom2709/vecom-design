/* ==========================================================================
   Vorher/Nachher-Bild am PC (Marketing-Studio 9, 01.10.2026, Uwe: „ja“ zu
   S4 — „Vorher/Nachher-Serie“).

   Kein Kie.ai, keine Credits: Der PC fotografiert die NEUE Website wie ein
   Besucher am Handy (390 × 844), holt das Bildschirmfoto der ALTEN aus dem
   Website-Check der Akquise (nur solange der Kunde zugestimmt hat — das
   prüft die Verwaltung bei jeder Anfrage) und setzt beides zu einem Bild
   im Instagram-Format 4:5 (1080 × 1350) zusammen. Gibt es kein altes Foto,
   zeigt das Bild nur die neue Seite.

   Cookie-Hinweise der Seite werden für das Foto nur AUSGEBLENDET, nie
   bestätigt. Hochgeladen wird über dieselbe Tür wie die Kie-Bilder; die
   Verwaltung verteilt das Bild dann auf alle drei Entwürfe.
   ========================================================================== */
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { api } from '../api.js';
import { browserHolen } from '../audit/browser.js';
import { datenOrdner, konfig } from '../konfig.js';
import { log } from '../log.js';
import { hochladen, type MedienAuftrag } from './kie.js';

export type VnDaten = { url: string; betrieb: string; sprache: string; kunde_id: number; vorher: boolean; geschwister: number[] };

export const BREITE = 1080;
export const HOEHE = 1350;

/** Handy-Bildschirm wie im Website-Check — damit Vorher und Nachher gleich groß sind. */
const HANDY = { width: 390, height: 844 };

const WORTE = {
  it: { vorher: 'Prima', nachher: 'Dopo', nur: 'Il nuovo sito', zeile: 'Nuovo sito online' },
  de: { vorher: 'Vorher', nachher: 'Nachher', nur: 'Die neue Website', zeile: 'Neue Website online' },
} as const;

const ORDNER_SCHRIFTEN = fileURLToPath(new URL('../../../../assets/fonts/', import.meta.url));
const ORDNER_BILDER = fileURLToPath(new URL('../../../../assets/img/', import.meta.url));

function datei64(pfad: string): string | null {
  try { return existsSync(pfad) ? readFileSync(pfad).toString('base64') : null; } catch { return null; }
}

function schrift(familie: string, datei: string, gewicht: number): string {
  const b = datei64(join(ORDNER_SCHRIFTEN, datei));
  return b ? `@font-face{font-family:'${familie}';src:url(data:font/woff2;base64,${b}) format('woff2');font-weight:${gewicht};font-style:normal}` : '';
}

export function esc(s: string): string {
  return s.replace(/[&<>"']/g, (z) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[z] as string));
}

/** Schriftgröße des Betriebsnamens nach Länge — zwei Zeilen höchstens, nichts wird abgeschnitten. */
export function namenGroesse(name: string): number {
  const n = [...name].length;
  return n <= 16 ? 92 : n <= 24 ? 78 : n <= 36 ? 64 : 52;
}

/**
 * Das Bild als HTML — rein, ohne Browser testbar. Bilder kommen als base64
 * (JPEG oder PNG) herein, Schriften und Marke aus dem Repo (fehlen sie, greifen
 * Systemschriften).
 */
export function vnHtml(o: { betrieb: string; sprache: string; domain: string; nachher: string; vorher: string | null }): string {
  const w = o.sprache === 'de' ? WORTE.de : WORTE.it;
  const marke = datei64(join(ORDNER_BILDER, 'logo-mark.webp'));
  const zwei = o.vorher !== null;
  /* Zwei Handys: 880 hoch → 407 breit (390:844), Abstand 72. Eines: 920 hoch → 425 breit. */
  const h = zwei ? 880 : 920;
  const b = Math.round(h * HANDY.width / HANDY.height);
  const telefon = (bild: string, etikett: string, art: 'vorher' | 'nachher') =>
    `<figure class="telefon ${art}" style="width:${b}px;height:${h}px"><img src="data:${bild.startsWith('iVBOR') ? 'image/png' : 'image/jpeg'};base64,${bild}" alt=""><figcaption>${esc(etikett)}</figcaption></figure>`;
  return `<!doctype html><html lang="${o.sprache === 'de' ? 'de' : 'it'}"><head><meta charset="utf-8"><style>
${schrift('VD Cormorant', 'cormorant-latin-500-normal.woff2', 500)}
${schrift('VD Inter', 'inter-latin.woff2', 400)}
${schrift('VD Archivo', 'archivo-latin.woff2', 700)}
*{box-sizing:border-box;margin:0;padding:0}
html,body{width:${BREITE}px;height:${HOEHE}px;overflow:hidden}
body{position:relative;background:#0d0c0a;background-image:radial-gradient(120% 62% at 50% 0%,#2b2314 0%,#15120d 45%,#0d0c0a 75%);color:#f3ece0;font-family:'VD Inter',system-ui,sans-serif;-webkit-font-smoothing:antialiased}
body::after{content:'';position:absolute;inset:0;pointer-events:none;opacity:.07;mix-blend-mode:overlay;background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='220' height='220'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.85' numOctaves='2' stitchTiles='stitch'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>")}
.kopf{position:absolute;top:60px;left:80px;right:80px;display:flex;align-items:center;justify-content:space-between}
.marke{display:flex;align-items:center;gap:16px;font-family:'VD Archivo',system-ui,sans-serif;font-weight:700;font-size:19px;letter-spacing:.34em;color:#c79a43;text-transform:uppercase}
.marke img{height:36px;width:auto}
.zeile{font-size:19px;letter-spacing:.16em;text-transform:uppercase;color:rgba(243,236,224,.62)}
h1{position:absolute;top:128px;left:80px;right:80px;font-family:'VD Cormorant',Georgia,serif;font-weight:500;font-size:${namenGroesse(o.betrieb)}px;line-height:1.02;letter-spacing:-.005em;color:#f5e2a6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.buehne{position:absolute;left:0;right:0;top:${zwei ? 318 : 300}px;display:flex;justify-content:center;align-items:flex-start;gap:72px}
.telefon{position:relative;border-radius:46px;padding:11px;background:#1c1a15;box-shadow:0 34px 70px rgba(0,0,0,.6),inset 0 0 0 1.5px rgba(255,255,255,.08)}
.telefon img{display:block;width:100%;height:100%;object-fit:cover;object-position:top center;border-radius:36px;background:#fff}
.vorher img{filter:grayscale(.85) contrast(.92) brightness(.82)}
.nachher{box-shadow:0 0 0 2px #c79a43,0 34px 90px rgba(199,154,67,.28),0 34px 70px rgba(0,0,0,.6)}
figcaption{position:absolute;top:-24px;left:50%;transform:translateX(-50%);padding:11px 28px;border-radius:999px;font-size:21px;font-weight:400;letter-spacing:.2em;text-transform:uppercase;white-space:nowrap}
.vorher figcaption{background:#2b2822;color:#bdb5a7}
.nachher figcaption{background:#c79a43;color:#17130b}
.pfeil{position:absolute;top:${318 + 440 - 30}px;left:50%;width:60px;height:60px;margin-left:-30px;border-radius:50%;background:#0d0c0a;box-shadow:0 0 0 2px #c79a43;display:flex;align-items:center;justify-content:center;color:#c79a43;font-size:30px;line-height:1}
.fuss{position:absolute;left:80px;right:80px;bottom:52px;display:flex;align-items:center;justify-content:space-between;font-size:23px}
.domain{display:flex;align-items:center;gap:14px;color:#f3ece0}
.domain::before{content:'';width:10px;height:10px;border-radius:50%;background:#c79a43}
.vecom{color:#c79a43;letter-spacing:.06em}
</style></head><body>
<div class="kopf"><div class="marke">${marke ? `<img src="data:image/webp;base64,${marke}" alt="">` : ''}Vecom Design</div><div class="zeile">${esc(w.zeile)}</div></div>
<h1>${esc(o.betrieb)}</h1>
<div class="buehne">${zwei ? telefon(o.vorher as string, w.vorher, 'vorher') + telefon(o.nachher, w.nachher, 'nachher') : telefon(o.nachher, w.nur, 'nachher')}</div>
${zwei ? '<div class="pfeil" aria-hidden="true">→</div>' : ''}
<div class="fuss"><div class="domain">${esc(o.domain)}</div><div class="vecom">vecom-design.it</div></div>
</body></html>`;
}

/** Die neue Website wie ein Besucher am Handy — erster Bildschirm, scharf (2×). */
async function nachherFoto(url: string, sprache: string): Promise<Buffer> {
  const b = await browserHolen();
  const ctx = await b.newContext({
    viewport: HANDY, deviceScaleFactor: 2, isMobile: true, hasTouch: true, locale: sprache === 'de' ? 'de-DE' : 'it-IT',
    userAgent: 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Mobile Safari/537.36 ' + konfig.botKennung,
  });
  try {
    const page = await ctx.newPage();
    const r = await page.goto(url, { waitUntil: 'load', timeout: 45_000 });
    if (!r || r.status() >= 400) throw new Error(`Die neue Website antwortet nicht sauber (HTTP ${r?.status() ?? '–'}).`);
    await page.waitForLoadState('networkidle', { timeout: 10_000 }).catch(() => {});
    /* Cookie-Hinweise nur ausblenden — nie bestätigen. Nur feste/klebende Ebenen, damit kein Inhalt verschwindet. */
    await page.evaluate(() => {
      const muster = /cookie|consent|gdpr|iubenda|cookiebot|onetrust|cmplz|privacy-banner/i;
      for (const el of Array.from(document.querySelectorAll<HTMLElement>('body *'))) {
        const s = getComputedStyle(el);
        if ((s.position === 'fixed' || s.position === 'sticky') && muster.test(`${el.id} ${el.getAttribute('class') ?? ''}`)) el.style.setProperty('display', 'none', 'important');
      }
    }).catch(() => {});
    /* Einstiegs-Animationen ausklingen lassen. */
    await page.waitForTimeout(3000);
    return await page.screenshot({ type: 'jpeg', quality: 90, fullPage: false });
  } finally {
    await ctx.close().catch(() => {});
  }
}

/** HTML → JPEG 1080 × 1350 (Instagram 4:5). */
async function zusammensetzen(html: string): Promise<Buffer> {
  const b = await browserHolen();
  const ctx = await b.newContext({ viewport: { width: BREITE, height: HOEHE }, deviceScaleFactor: 1 });
  try {
    const page = await ctx.newPage();
    await page.setContent(html, { waitUntil: 'load', timeout: 30_000 });
    await page.evaluate(() => document.fonts.ready.then(() => true));
    return await page.screenshot({ type: 'jpeg', quality: 92, fullPage: false });
  } finally {
    await ctx.close().catch(() => {});
  }
}

export async function vnLauf(a: MedienAuftrag & { vn: VnDaten }): Promise<void> {
  log.info('marketing', `Auftrag #${a.id}: ${a.beschreibung} — Vorher/Nachher am PC`);
  await api('status_melden', { art: 'marketing', stand: 0, ziel: 1, text: a.beschreibung }).catch(() => {});
  try {
    const vn = a.vn;
    if (!/^https:\/\/[^\s"'<>]{4,300}$/.test(String(vn.url ?? ''))) throw new Error('Adresse der neuen Website fehlt oder ist ungültig.');
    /* Prüft serverseitig die Zustimmung — ist sie zurückgezogen, bricht der Lauf hier ab. */
    const alt = await api<{ ok: boolean; bild?: string | null }>('marketing_vorher_bild', { auftrag_id: a.id });
    const vorher = typeof alt.bild === 'string' && alt.bild.length > 200 ? alt.bild : null;
    const nachher = await nachherFoto(vn.url, vn.sprache);
    const html = vnHtml({ betrieb: String(vn.betrieb ?? ''), sprache: vn.sprache, domain: new URL(vn.url).hostname.replace(/^www\./, ''),
      nachher: nachher.toString('base64'), vorher });
    const bild = await zusammensetzen(html);
    writeFileSync(join(datenOrdner('marketing'), `vorher-nachher-${a.id}.jpg`), bild);
    await hochladen(a, bild, {});
    const text = vorher ? 'Vorher/Nachher-Bild fertig · ohne Credits' : 'Bild der neuen Website fertig (kein altes Bildschirmfoto im Website-Check) · ohne Credits';
    await api('marketing_auftrag_melden', { id: a.id, ok: true, text });
    log.info('marketing', `Auftrag #${a.id}: ${text}`);
  } catch (x) {
    const grund = (x as Error).message;
    log.fehler('marketing', `Auftrag #${a.id}: ${grund}`);
    await api('marketing_auftrag_melden', { id: a.id, ok: false, text: grund.slice(0, 900) }).catch(() => {});
  }
}
