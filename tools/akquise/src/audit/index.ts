/* ==========================================================================
   Ein Audit von vorn bis hinten: robots.txt → Netz → Browser (mobil,
   Desktop, Unterseiten) → Linkcheck → Lighthouse → Regeln.

   ZUERST MASCHINELL, DANN (vielleicht) CLAUDE
   Hier laeuft kein einziger KI-Aufruf. Alles, was sich messen laesst, wird
   gemessen. Claude kommt erst im Schritt "texte" dazu -- und nur fuer
   Firmen, deren Score die Mindestgrenze erreicht (Kostenkontrolle).
   ========================================================================== */
import { writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { datenOrdner, konfig, VERSION } from '../konfig.js';
import { log } from '../log.js';
import { gleicheSite } from '../hilfen.js';
import { abrufen, linksPruefen, netzBild, robotsErlaubt } from '../crawler/netz.js';
import { ansehen } from './browser.js';
import { leistungMessen } from './lighthouse.js';
import { technik } from './regeln/technik.js';
import { performance } from './regeln/performance.js';
import { mobile } from './regeln/mobile.js';
import { seo } from './regeln/seo.js';
import { conversion } from './regeln/conversion.js';
import { vertrauen } from './regeln/vertrauen.js';
import { design, experience } from './regeln/design.js';
import { oeffnungLesen, type Oeffnung } from './zeiten.js';
import { alleSeiten, type Befund, type FirmaKurz, type Rohdaten } from './typen.js';

export interface AuditErgebnis {
  status: 'fertig' | 'fehler' | 'uebersprungen';
  befunde: Befund[];
  messwerte: Record<string, unknown>;
  seiten: number;
  geprueft_url: string;
  gestartet_am: string;
  beendet_am: string;
  worker_version: string;
  sprache?: string;
  email?: string;
  telefon?: string;
  /** Nummer aus einem wa.me-/WhatsApp-Link der Website (06.10.2026). */
  whatsapp?: string;
  /** Partita IVA / USt-IdNr. aus Impressum oder Fußzeile (07.10.2026, Kunden finden). */
  piva?: string;
  /** „Realizzato da …“ im Seitenfuß: die Seite betreut schon eine Agentur (07.10.2026). */
  agentur?: string;
  bilder?: { mobil?: string; desktop?: string };
  marken?: { art: string; x: number; y: number; b: number; h: number }[];
  /** Öffnungszeiten laut Website (D3, 29.09.2026) -- nur wenn eindeutig gefunden. */
  oeffnungszeiten?: Oeffnung;
  grund?: string;
}

/* E-Mail und WhatsApp von der Website (06.10.2026, Uwe: „finde von allen die E-Mail-Adressen“).
   Erst mailto-Links, dann Adressen im sichtbaren Text (Kontakt, Impressum, Fußzeile) -- auch
   „info [at] firma.it“. Eine Adresse der eigenen Domain schlägt jede fremde. Bilddateien
   („logo@2x.png“) und Platzhalter fallen weg. */
const MAIL = /[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,24}/gi;
const MAIL_VERSTECKT = /([a-z0-9._%+-]+)\s*(?:\[at\]|\(at\)|\s+at\s+|\[chiocciola\]|\(chiocciola\))\s*([a-z0-9-]+(?:\s*(?:\.|\[dot\]|\(dot\)|\[punto\])\s*[a-z0-9-]+)+)/gi;
const KEINE_MAIL = /\.(png|jpe?g|gif|webp|svg|avif)$|^(example|esempio|name|nome|email|mail|tuo|your)@|@(example|sentry|wixpress|domain|dominio)\./i;

/* Kunden finden (07.10.2026): Partita IVA (IT, 11 Ziffern) oder USt-IdNr. (DE, 9 Ziffern) -- für den Abgleich
   mit der Kundenliste. Nur mit Kennwort davor, sonst wäre jede Telefonnummer eine. */
export function pivaAusText(text: string): string | undefined {
  const m = text.match(/\b(?:p\.?\s?iva|partita\s+iva|vat(?:\s+(?:no|number|id))?|c\.?\s?f\.?\s*(?:e|\/)\s*p\.?\s?iva)\s*[:.\-]?\s*(?:it\s?)?(\d{11})\b/i)
    ?? text.match(/\b(?:ust\.?-?\s?id(?:nr)?\.?|umsatzsteuer-identifikationsnummer)\s*[:.\-]?\s*de\s?(\d{9})\b/i);
  return m?.[1];
}

/* „Realizzato da Studio X“, „Webdesign by …“, „Erstellt von …“ -- die Seite betreut schon jemand.
   Baukästen („Powered by WordPress“) zählen nicht. Dieselbe Regel steht in KundenFinden::agenturAusText (PHP). */
const AGENTUR_WORT = /\b(?:realizzat[oa]|sviluppat[oa]|progettat[oa]|design(?:ed)?|sito(?: web)?|web ?design|made|erstellt|gestaltet|umgesetzt|realisiert|entwickelt|credits?)\s*(?:da|by|von|:)\s*:?\s*/i;
const AGENTUR_NAME = /^([\p{Lu}0-9][\p{L}0-9&.'\- ]{1,48})/u;   // der Name beginnt groß -- ohne i-Schalter
const BAUKASTEN = /^(wordpress|wix|jimdo|squarespace|shopify|joomla|webnode|ionos|aruba|godaddy|google|weebly|strato|elementor|divi)\b/i;
export function agenturAusText(text: string): string | undefined {
  const w = AGENTUR_WORT.exec(text);
  if (!w) return undefined;
  const m = AGENTUR_NAME.exec(text.slice(w.index + w[0].length));
  if (!m) return undefined;
  const n = m[1].replace(/\s+/g, ' ').trim().replace(/^[ .\-']+|[ .\-']+$/g, '');
  if (n.length < 2 || BAUKASTEN.test(n) || /vecom/i.test(n)) return undefined;
  return n.slice(0, 120);
}

export function mailsAusText(text: string): string[] {
  const roh = [...(text.match(MAIL) ?? [])];
  for (const m of text.matchAll(MAIL_VERSTECKT)) {
    roh.push(`${m[1]}@${m[2].replace(/\s*(?:\[dot\]|\(dot\)|\[punto\])\s*/gi, '.').replace(/\s+/g, '')}`);
  }
  return Array.from(new Set(roh.map((e) => e.toLowerCase().replace(/^[._-]+|[._-]+$/g, '')).filter((e) => !KEINE_MAIL.test(e))));
}

export function whatsappAusLinks(links: string[]): string | undefined {
  for (const l of links) {
    let nr = '';
    try {
      const u = new URL(l);
      nr = /wa\.me$/i.test(u.hostname) ? u.pathname.replace(/\D/g, '') : (u.searchParams.get('phone') ?? '').replace(/\D/g, '');
    } catch { continue; }
    if (nr.length >= 8 && nr.length <= 15) return `+${nr}`;
  }
  return undefined;
}

export function kontaktAusSeiten(seiten: { mailtoLinks: string[]; waLinks?: string[]; text: string }[], basis: string): { email?: string; whatsapp?: string } {
  const eigen = (e: string) => gleicheSite('https://' + e.split('@')[1] + '/', basis);
  const mailto = seiten.flatMap((s) => s.mailtoLinks).map((e) => decodeURIComponent(e).trim().toLowerCase()).filter((e) => /@/.test(e) && !KEINE_MAIL.test(e));
  const imText = seiten.flatMap((s) => mailsAusText(s.text ?? ''));
  const email = mailto.find(eigen) ?? imText.find(eigen) ?? mailto[0] ?? imText[0];
  return { email, whatsapp: whatsappAusLinks(seiten.flatMap((s) => s.waLinks ?? [])) };
}

/** Alle Regeln auf feste Rohdaten -- ohne Netz, deshalb gut zu pruefen. */
export function regelnAnwenden(r: Rohdaten): Befund[] {
  const b = [...technik(r), ...performance(r), ...mobile(r), ...seo(r), ...conversion(r), ...vertrauen(r), ...design(r)];
  return [...b, ...experience(r, b)];
}

/** Sprache der Website: lang-Attribut, sonst Haeufigkeit kurzer Woerter. */
export function spracheErkennen(lang: string | null, text: string): string | undefined {
  const l = (lang ?? '').slice(0, 2).toLowerCase();
  if (['de', 'it', 'en'].includes(l)) return l;
  const w = ` ${text.toLowerCase().slice(0, 6000)} `;
  const zaehle = (liste: string[]) => liste.reduce((n, x) => n + (w.split(` ${x} `).length - 1), 0);
  const werte = { de: zaehle(['und', 'der', 'die', 'das', 'mit', 'für', 'ist', 'wir']), it: zaehle(['e', 'il', 'la', 'di', 'che', 'per', 'con', 'gli']), en: zaehle(['and', 'the', 'with', 'for', 'our', 'is', 'we', 'you']) };
  const [beste, n] = Object.entries(werte).sort((a, b) => b[1] - a[1])[0];
  return n >= 5 ? beste : undefined;
}

export async function auditieren(firma: FirmaKurz): Promise<AuditErgebnis> {
  const gestartet = new Date().toISOString();
  const leer = (status: AuditErgebnis['status'], grund: string, befunde: Befund[] = []): AuditErgebnis => ({
    status, befunde, messwerte: { grund }, seiten: 0, geprueft_url: firma.url, gestartet_am: gestartet,
    beendet_am: new Date().toISOString(), worker_version: VERSION, grund,
  });

  const netz = await netzBild(firma.domain, firma.url);
  const basis = netz.endUrl ?? `https://${firma.domain}/`;
  const robots = netz.dnsOk && netz.endStatus !== null ? await robotsErlaubt(basis) : { erlaubt: true, sitemap: false, text: '' };
  if (!robots.erlaubt) {
    log.info('audit', `${firma.domain}: robots.txt untersagt die Prüfung — übersprungen`);
    return leer('uebersprungen', 'robots.txt untersagt die Prüfung für VecomAudit bzw. alle Programme');
  }

  const roh: Rohdaten = {
    firma, netz, robots, browser: null, leistung: null, kaputteLinks: [], sitemapDa: robots.sitemap, jahr: new Date().getFullYear(),
  };

  if (netz.dnsOk && netz.endStatus !== null && netz.endStatus < 400) {
    roh.browser = await ansehen(basis, firma.land);
    if (!roh.sitemapDa) {
      for (const pfad of ['/sitemap.xml', '/sitemap_index.xml', '/wp-sitemap.xml']) {
        const a = await abrufen(new URL(pfad, basis).toString(), 'HEAD', 15_000);
        if (a.status === 200 || (a.status && a.status >= 300 && a.status < 400)) { roh.sitemapDa = true; break; }
      }
    }
    const intern = Array.from(new Set(alleSeiten(roh).flatMap((s) => s.links.map((l) => l.href.split('#')[0]))
      .filter((h) => /^https?:/i.test(h) && gleicheSite(h, basis) && !/\/(wp-admin|wp-login|cart|carrello|checkout|logout)/i.test(h))));
    roh.kaputteLinks = await linksPruefen(intern);
    roh.leistung = await leistungMessen(roh.browser.endUrl);
  }

  const befunde = regelnAnwenden(roh);
  const m = roh.browser?.mobil;
  const seiten = alleSeiten(roh);
  const { email, whatsapp } = kontaktAusSeiten(seiten, basis);
  const telefon = seiten.flatMap((s) => s.telLinks).map((t) => decodeURIComponent(t.replace(/^tel:/, '')))[0];

  // Rohdaten lokal aufheben (Beleg, Nachpruefung) -- nie ins Repository.
  try {
    const { screenshotMobil, screenshotDesktop, ...rest } = roh.browser ?? ({} as any);
    writeFileSync(join(datenOrdner('audits'), `${firma.kennung}-${Date.now()}.json`), JSON.stringify({ ...roh, browser: rest }, null, 1));
    if (screenshotMobil) writeFileSync(join(datenOrdner('bilder'), `${firma.kennung}-mobil.jpg`), Buffer.from(screenshotMobil, 'base64'));
    if (screenshotDesktop) writeFileSync(join(datenOrdner('bilder'), `${firma.kennung}-desktop.jpg`), Buffer.from(screenshotDesktop, 'base64'));
  } catch { /* Beleg ist Beiwerk */ }

  const l = roh.leistung;
  return {
    status: 'fertig',   // auch eine tote Domain ist ein fertiges Audit -- mit genau diesem Befund
    befunde,
    messwerte: {
      ...(l ? { quelle: l.quelle, performance: l.performance, seo: l.seo, accessibility: l.accessibility, best_practices: l.bestPractices,
        lcp_ms: l.lcp_ms, cls: l.cls, tbt_ms: l.tbt_ms, fcp_ms: l.fcp_ms, inp_ms: l.inp_ms, bytes: l.bytes } : {}),
      ttfb_ms: netz.ttfbMs, end_url: netz.endUrl, end_status: netz.endStatus,
      seiten: seiten.map((s) => s.url), konsolen_fehler: roh.browser?.konsoleFehler.length ?? 0,
    },
    seiten: seiten.length,
    geprueft_url: roh.browser?.endUrl ?? basis,
    gestartet_am: gestartet,
    beendet_am: new Date().toISOString(),
    worker_version: VERSION,
    sprache: m ? spracheErkennen(m.lang, m.text) : undefined,
    email, telefon, whatsapp,
    piva: seiten.map((s) => pivaAusText(`${s.fuss ?? ''} ${s.text ?? ''}`)).find(Boolean),
    agentur: seiten.map((s) => agenturAusText(s.fuss ?? s.text.slice(-1500))).find(Boolean),
    bilder: { mobil: roh.browser?.screenshotMobil ?? undefined, desktop: roh.browser?.screenshotDesktop ?? undefined },
    marken: roh.browser?.mobil?.marken ?? [],   // A2 (28.09.2026): Stellen auf dem Handyfoto
    oeffnungszeiten: oeffnungLesen(seiten.flatMap((s) => s.oeffnungRoh ?? []), seiten.map((s) => s.text)) ?? undefined,
  };
}
