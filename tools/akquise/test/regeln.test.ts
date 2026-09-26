/* Die Regeln gegen feste Rohdaten -- ohne Netz, ohne Browser.
   Zwei Faelle stammen aus dem ersten echten Lauf (sizilienreisen.com) und
   halten fest, was dort beinahe falsch behauptet worden waere. */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { regelnAnwenden, spracheErkennen } from '../src/audit/index.js';
import { pivaGueltig } from '../src/audit/regeln/vertrauen.js';
import { istZielbetrieb } from '../src/recherche/overpass.js';
import type { Rohdaten } from '../src/audit/typen.js';
import type { SeitenSignale } from '../src/audit/browser.js';

function seite(teil: Partial<SeitenSignale> = {}): SeitenSignale {
  return {
    url: 'https://esempio.it/', titel: 'Trattoria Esempio – Cucina siciliana ad Aragona', metaDescription: 'Cucina tipica siciliana nel centro di Aragona, pesce fresco e dolci fatti in casa.',
    lang: 'it', h1: ['Trattoria Esempio Aragona'], h2: 3, canonical: 'https://esempio.it/', robotsMeta: null,
    viewport: 'width=device-width, initial-scale=1', generator: null, jsonLdTypen: ['Restaurant'], hreflang: ['it', 'en'],
    text: 'Benvenuti. Orari: lun-sab 12:00-15:00. Prenota un tavolo. Il nostro menu. Via Roma 1, 92021 Aragona. P.IVA 05721190824. Privacy. Recensioni.',
    links: [{ href: 'https://esempio.it/menu', text: 'Menu' }, { href: 'https://www.google.com/maps/place/x', text: 'Mappa' }],
    bilder: { gesamt: 10, ohneAlt: 0, kaputt: [], inhalt: 8 }, telLinks: ['tel:+390922000111'], mailtoLinks: ['info@esempio.it'], waLinks: [],
    telefonImText: ['0922 000111'], formulare: { anzahl: 1, mitTextarea: 1, mitEmail: 1, mitDatum: 1 }, suchfeld: false, iframes: [], skripte: [],
    jquery: null, flash: false, frames: false, fontTags: 0, marquee: false, layoutTabellen: 0, copyrightJahre: [2026],
    ueberbreitePx: 0, kleineSchriftAnteil: 0, kleinsteSchriftPx: 14, tapZiele: 20, kleineTapZiele: 0,
    ctaImErstenBildschirm: ['Prenota'], kontaktErsteY: 300, viewportHoehe: 844, seitenHoehe: 3000, inhaltsBreite: 1200,
    ...teil,
  };
}

function roh(teil: Partial<Rohdaten> = {}, mobil: Partial<SeitenSignale> = {}, unter: Partial<SeitenSignale>[] = [{ url: 'https://esempio.it/contatti' }, { url: 'https://esempio.it/menu' }]): Rohdaten {
  const m = seite(mobil);
  return {
    firma: { id: 1, kennung: 'L-TEST0001', name: 'Trattoria Esempio', url: 'https://esempio.it', domain: 'esempio.it', land: 'IT', branche: 'restaurant', stadt: 'Aragona', tourismus: 1 },
    netz: { domain: 'esempio.it', dnsOk: true, https: [{ url: 'https://esempio.it/', status: 200, ms: 200 }], http: [{ url: 'http://esempio.it/', status: 301, location: 'https://esempio.it/', ms: 100 }, { url: 'https://esempio.it/', status: 200, ms: 200 }],
      endUrl: 'https://esempio.it/', endStatus: 200, ttfbMs: 300, kopf: { 'content-type': 'text/html', 'content-encoding': 'br' } },
    robots: { erlaubt: true, sitemap: true, text: '' },
    browser: { startUrl: 'https://esempio.it', endUrl: 'https://esempio.it/', status: 200, mobil: m, desktop: { ...m, inhaltsBreite: 1200 },
      unterseiten: unter.map((u) => seite({ ...mobil, ...u })), konsoleFehler: [], seitenFehler: [], gemischteInhalte: [], fehlgeschlagen: [], bildBytes: [], bytesGesamt: 900_000,
      screenshotMobil: null, screenshotDesktop: null },
    leistung: { quelle: 'Lighthouse lokal', lcp_ms: 1800, cls: 0.02, tbt_ms: 100, fcp_ms: 1200, bytes: 900_000 },
    kaputteLinks: [], sitemapDa: true, jahr: 2026,
    ...teil,
  };
}
const codes = (r: Rohdaten) => regelnAnwenden(r).map((b) => b.code);

test('eine gepflegte Seite erzeugt keine Befunde außer dem Experience-Hinweis', () => {
  const c = codes(roh());
  assert.deepEqual(c.filter((x) => x !== 'experience_potenzial'), []);
});

test('tote Domain: genau ein Befund, belegt', () => {
  const r = roh({ netz: { ...roh().netz, dnsOk: false, dnsFehler: 'ENOTFOUND', https: [], http: [], endUrl: null, endStatus: null }, browser: null });
  const b = regelnAnwenden(r);
  assert.equal(b.length, 1);
  assert.equal(b[0].code, 'domain_tot');
  assert.equal(b[0].status, 'VERIFIED');
});

test('kein HTTPS, Zertifikatsfehler, HTTP ohne Umleitung', () => {
  const n = roh().netz;
  assert.ok(codes(roh({ netz: { ...n, https: [{ url: 'https://esempio.it/', status: null, ms: 1, fehler: 'ECONNREFUSED' }], http: [{ url: 'http://esempio.it/', status: 200, ms: 1 }] } })).includes('kein_https'));
  assert.ok(codes(roh({ netz: { ...n, https: [{ url: 'https://esempio.it/', status: null, ms: 1, fehler: 'CERT_HAS_EXPIRED' }], http: [{ url: 'http://esempio.it/', status: 200, ms: 1 }] } })).includes('ssl_fehler'));
  assert.ok(codes(roh({ netz: { ...n, http: [{ url: 'http://esempio.it/', status: 200, ms: 1 }] } })).includes('http_nicht_umgeleitet'));
});

test('Platzhaltertext und altes Copyright werden belegt', () => {
  const c = codes(roh({}, { text: 'Lorem ipsum dolor sit amet. © 2019 Esempio. Orari 12:00 Prenota tavolo menu Via Roma 1 92021 P.IVA 05721190824 privacy recensioni', copyrightJahre: [2019] }));
  assert.ok(c.includes('platzhalter_text'));
  assert.ok(c.includes('copyright_alt'));
});

test('Mobil: fehlender Viewport, nicht antippbare Nummer', () => {
  assert.ok(codes(roh({}, { viewport: null })).includes('kein_viewport'));
  assert.ok(codes(roh({}, { telLinks: [] }, [{ url: 'https://esempio.it/contatti', telLinks: [] }, { url: 'https://esempio.it/menu', telLinks: [] }])).includes('tel_nicht_klickbar'));
  assert.ok(!codes(roh({}, { telLinks: [] }, [{ url: 'https://esempio.it/contatti', telLinks: ['tel:+390922000111'] }])).includes('tel_nicht_klickbar'),
    'tel:-Link auf einer Unterseite reicht');
});

test('Restaurant ohne Reservierung → fehlt_reservierung (belegt bei ≥ 3 Seiten); mit TheFork nicht', () => {
  const ohne = { text: 'Orari 12:00-15:00. Il nostro menu. Via Roma 1, 92021 Aragona. P.IVA 05721190824. Privacy. Recensioni.', formulare: { anzahl: 0, mitTextarea: 0, mitEmail: 0, mitDatum: 0 } };
  const b = regelnAnwenden(roh({}, ohne, [{ url: 'https://esempio.it/contatti', ...ohne }, { url: 'https://esempio.it/menu', ...ohne }]));
  const r = b.find((x) => x.code === 'fehlt_reservierung');
  assert.ok(r);
  assert.equal(r!.status, 'VERIFIED');
  const mit = { ...ohne, iframes: ['https://widget.thefork.com/abc'] };
  assert.ok(!codes(roh({}, mit, [{ url: 'https://esempio.it/contatti', ...mit }])).includes('fehlt_reservierung'));
});

test('Speisekarte nur als PDF', () => {
  const pdf = { links: [{ href: 'https://esempio.it/files/menu-2024.pdf', text: 'Menu' }] };
  assert.ok(codes(roh({}, pdf, [{ url: 'https://esempio.it/contatti', ...pdf }])).includes('speisekarte_nur_pdf'));
});

/* ---- aus dem ersten echten Lauf ---- */
test('REGRESSION sizilienreisen.com: /englisch/ als Flaggenlink zählt als zweite Sprache', () => {
  const m = { lang: null, hreflang: [], links: [{ href: 'https://esempio.it/deutsch/indexd.html', text: '' }, { href: 'https://esempio.it/englisch/index.html', text: '' }] };
  assert.ok(!codes(roh({}, m, [{ url: 'https://esempio.it/deutsch/kontakt.html', ...m }])).includes('keine_sprachversion'));
});

test('REGRESSION sizilienreisen.com: Partita IVA als „Steuernummer“ beschriftet', () => {
  const m = { text: 'Steuernummer: 05721190824. Orari 12:00. Prenota un tavolo. menu. Via Roma 1, 92021 Aragona. Privacy. Recensioni.' };
  assert.ok(!codes(roh({}, m, [{ url: 'https://esempio.it/impressum', ...m }])).includes('piva_fehlt'));
  assert.ok(pivaGueltig('05721190824'));
  assert.ok(!pivaGueltig('03933806024'), 'eine Telefonnummer besteht die Prüfziffer nicht');
});

test('REGRESSION: „Agrigent“ auf einer deutschen Seite ist Agrigento', () => {
  const r = roh({ firma: { ...roh().firma, stadt: 'Agrigento' } }, { titel: 'Stadtführungen in Agrigent und Palermo', h1: ['Agrigent'] });
  assert.ok(!codes(r).includes('ort_fehlt_im_title'));
});

test('ein Titel mit 88 Zeichen ist „zu lang“, nicht „wenig aussagekräftig“', () => {
  const c = codes(roh({}, { titel: 'Sizilienreisen - Stadtführungen und Besichtigungstouren in Palermo, Agrigent, Aragona ...' }));
  assert.ok(c.includes('title_lang'));
  assert.ok(!c.includes('title_schwach'));
});

test('Experience: Kanzlei nie, Hotel mit wenig Bildern belegt', () => {
  const k = roh({ firma: { ...roh().firma, branche: 'kanzlei', tourismus: 0 } });
  assert.ok(!codes(k).includes('experience_potenzial'));
  const h = regelnAnwenden(roh({ firma: { ...roh().firma, branche: 'hotel' } }, { bilder: { gesamt: 2, ohneAlt: 0, kaputt: [], inhalt: 1 } }));
  const e = h.find((x) => x.code === 'experience_potenzial');
  assert.ok(e);
  assert.equal(e!.status, 'VERIFIED');
});

test('Sprache erkennen', () => {
  assert.equal(spracheErkennen('it-IT', ''), 'it');
  assert.equal(spracheErkennen(null, 'Wir sind ein Familienbetrieb und für Sie da, mit der besten Küche und das seit 1990. Die Zimmer sind hell.'), 'de');
});

test('jeder Befund hat Kategorie, Code, Schwere 1–5, Titel und Status', () => {
  const alle = regelnAnwenden(roh({}, { viewport: null, telLinks: [], titel: 'Home', metaDescription: null, h1: [], jsonLdTypen: [], canonical: null, copyrightJahre: [2015] }));
  for (const b of alle) {
    assert.ok(b.kategorie && b.code && b.titel);
    assert.ok(b.schwere >= 1 && b.schwere <= 5);
    assert.ok(b.status === 'VERIFIED' || b.status === 'UNVERIFIED');
  }
});

test('Recherche: nur echte Zielbetriebe (erster Lauf Aragona)', () => {
  assert.ok(istZielbetrieb({ name: 'Pizzeria Trattoria Olimpia', amenity: 'restaurant' }));
  for (const name of ['Chiuso', 'info 3403363033', 'CAF patronato', 'ACLI', 'Centro sportivo comunale']) {
    assert.ok(!istZielbetrieb({ name }), name);
  }
  assert.ok(!istZielbetrieb({ name: 'UnipolSai', 'brand:wikidata': 'Q2037863' }), 'Kettenfiliale');
});
