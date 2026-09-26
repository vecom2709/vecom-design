/* Performance: Kennzahlen aus Lighthouse/PSI (mobil) plus eigene Messungen.
   Schwellen nach den Core-Web-Vitals-Grenzen von Google (gut / verbesserungs-
   wuerdig / schlecht): LCP 2,5 / 4 s, CLS 0,1 / 0,25, INP 200 / 500 ms. */
import type { Befund, Rohdaten } from '../typen.js';

const s1 = (ms: number) => Math.round(ms / 100) / 10;
const mb = (b: number) => Math.round((b / 1048576) * 10) / 10;

export function performance(r: Rohdaten): Befund[] {
  const b: Befund[] = [];
  const l = r.leistung;
  const quelle = l ? `${l.quelle}, mobil, ${l.quelle === 'Lighthouse lokal' ? 'simulierte 4G-Drosselung' : 'Googles Messnetz'}` : '';
  const url = r.browser?.endUrl ?? r.netz.endUrl ?? undefined;

  if (l?.lcp_ms !== undefined && l.lcp_ms > 2500) {
    const schlecht = l.lcp_ms > 4000;
    b.push({ kategorie: 'performance', code: 'langsam_lcp', schwere: schlecht ? 4 : 3,
      titel: `Hauptinhalt mobil erst nach ${s1(l.lcp_ms).toLocaleString('de-DE')} s sichtbar (LCP)`,
      beschreibung: 'Largest Contentful Paint: Zeit, bis das größte sichtbare Element (meist Bild oder Überschrift) steht.',
      wirkung: 'Längere Ladezeiten können Besucher auf dem Smartphone abbremsen.',
      url, messwert: { wert: s1(l.lcp_ms), einheit: 's', lcp_ms: l.lcp_ms, grenze_gut_ms: 2500 },
      beleg: `${quelle}: LCP ${Math.round(l.lcp_ms)} ms (gut ≤ 2500 ms)`, status: 'VERIFIED' });
  }
  if (l?.cls !== undefined && l.cls > 0.1) {
    b.push({ kategorie: 'performance', code: 'cls_hoch', schwere: l.cls > 0.25 ? 3 : 2,
      titel: `Inhalte verspringen beim Laden (CLS ${l.cls.toFixed(2).replace('.', ',')})`,
      wirkung: 'Springende Inhalte führen zu Fehlklicks und wirken unruhig.',
      url, messwert: { wert: Math.round(l.cls * 100) / 100, cls: l.cls }, beleg: `${quelle}: CLS ${l.cls} (gut ≤ 0,1)`, status: 'VERIFIED' });
  }
  if (l?.inp_ms !== undefined && l.inp_ms > 200) {
    b.push({ kategorie: 'performance', code: 'inp_langsam', schwere: l.inp_ms > 500 ? 3 : 2,
      titel: `Reaktion auf Eingaben verzögert (INP ${l.inp_ms} ms, echte Besucher)`,
      url, messwert: { wert: l.inp_ms, inp_ms: l.inp_ms }, beleg: `Felddaten (CrUX) über PageSpeed Insights: INP p75 ${l.inp_ms} ms (gut ≤ 200 ms)`, status: 'VERIFIED' });
  } else if (l?.tbt_ms !== undefined && l.tbt_ms > 600) {
    b.push({ kategorie: 'performance', code: 'tbt_hoch', schwere: 2, titel: `Hauptthread lange blockiert (TBT ${Math.round(l.tbt_ms)} ms)`,
      beschreibung: 'Laborwert; deutet auf verzögerte Reaktionen hin, ist aber kein Messwert echter Besucher.',
      url, messwert: { wert: Math.round(l.tbt_ms), tbt_ms: l.tbt_ms }, beleg: `${quelle}: TBT ${Math.round(l.tbt_ms)} ms (gut ≤ 200 ms)`, status: 'VERIFIED' });
  }
  if (l?.fcp_ms !== undefined && l.fcp_ms > 3000) {
    b.push({ kategorie: 'performance', code: 'fcp_langsam', schwere: 2, titel: `Erster Inhalt erst nach ${s1(l.fcp_ms).toLocaleString('de-DE')} s (FCP)`,
      url, messwert: { wert: s1(l.fcp_ms), einheit: 's', fcp_ms: l.fcp_ms }, beleg: `${quelle}: FCP ${Math.round(l.fcp_ms)} ms (gut ≤ 1800 ms)`, status: 'VERIFIED' });
  }
  const ttfb = r.netz.ttfbMs;
  if (ttfb !== null && ttfb > 1800) {
    b.push({ kategorie: 'performance', code: 'ttfb_langsam', schwere: 2, titel: `Server antwortet langsam (${ttfb} ms bis zur ersten Antwort)`,
      url, messwert: { wert: ttfb, ttfb_ms: ttfb }, beleg: `schnellster von zwei Abrufen: ${ttfb} ms (gut ≤ 800 ms)`, status: 'VERIFIED' });
  }
  const bytes = l?.bytes ?? r.browser?.bytesGesamt ?? 0;
  if (bytes > 3 * 1048576) {
    b.push({ kategorie: 'performance', code: 'seite_schwer', schwere: bytes > 6 * 1048576 ? 3 : 2, titel: `Startseite lädt ${mb(bytes).toLocaleString('de-DE')} MB`,
      url, messwert: { wert: mb(bytes), einheit: 'MB', bytes }, beleg: `${l ? quelle : 'eigene Messung'}: Übertragungsgröße ${bytes} Byte`, status: 'VERIFIED' });
  }
  const gross = (r.browser?.bildBytes ?? []).filter((x) => x.bytes > 500_000).sort((a, c) => c.bytes - a.bytes);
  if (gross.length) {
    b.push({ kategorie: 'performance', code: 'bilder_gross', schwere: gross.length >= 3 ? 3 : 2,
      titel: `${gross.length} Bild(er) über 500 KB, größtes ${mb(gross[0].bytes).toLocaleString('de-DE')} MB`,
      messwert: { wert: mb(gross[0].bytes), einheit: 'MB', anzahl: gross.length },
      beleg: gross.slice(0, 4).map((g) => `${Math.round(g.bytes / 1024)} KB ${g.url}`).join('\n'), status: 'VERIFIED' });
  }
  if (l?.renderBlockingMs !== undefined && l.renderBlockingMs >= 1000) {
    b.push({ kategorie: 'performance', code: 'render_blocking', schwere: 2, titel: `Render-blockierende Dateien (≈ ${s1(l.renderBlockingMs).toLocaleString('de-DE')} s Einsparpotenzial)`,
      url, messwert: { wert: s1(l.renderBlockingMs), einheit: 's' }, beleg: `${quelle}: geschätzte Einsparung ${l.renderBlockingMs} ms`, status: 'VERIFIED' });
  }
  const unnoetig = (l?.unusedJsBytes ?? 0) + (l?.unusedCssBytes ?? 0);
  if (unnoetig > 250_000) {
    b.push({ kategorie: 'performance', code: 'unnoetiges_js_css', schwere: 1, titel: `≈ ${Math.round(unnoetig / 1024)} KB ungenutztes JavaScript/CSS`,
      url, messwert: { wert: Math.round(unnoetig / 1024), einheit: 'KB' }, beleg: `${quelle}: JS ${l?.unusedJsBytes ?? 0} B, CSS ${l?.unusedCssBytes ?? 0} B ungenutzt`, status: 'VERIFIED' });
  }
  if (l?.cacheSchwach) {
    b.push({ kategorie: 'performance', code: 'kein_caching', schwere: 1, titel: 'Dateien werden kaum im Browser zwischengespeichert', url, beleg: `${quelle}: Cache-Prüfung nicht bestanden`, status: 'VERIFIED' });
  }
  const kopf = r.netz.kopf;
  if (kopf && Object.keys(kopf).length && !kopf['content-encoding'] && (kopf['content-type'] ?? '').includes('html')) {
    b.push({ kategorie: 'performance', code: 'kompression_fehlt', schwere: 1, titel: 'HTML wird ohne Kompression (gzip/brotli) ausgeliefert',
      url, beleg: `Antwortkopf ohne Content-Encoding (${kopf['server'] ?? 'Server unbekannt'})`, status: 'VERIFIED' });
  }
  return b;
}
