/* Technik: Erreichbarkeit, HTTPS, Zertifikat, Weiterleitungen, Fehler,
   veraltete Technik, Platzhaltertexte. Jeder Befund traegt den Beleg, an
   dem er haengt -- Statuscode, Fehlercode, Kette, Fundstelle. */
import { istTlsFehler } from '../../crawler/netz.js';
import { alleSeiten, type Befund, type Rohdaten } from '../typen.js';

const PLATZHALTER: [RegExp, string][] = [
  [/lorem ipsum/i, 'Lorem ipsum'],
  [/\b(coming soon|under construction)\b/i, 'Coming soon / Under construction'],
  [/\bsito (in|web in) (costruzione|allestimento|manutenzione)\b/i, 'Sito in costruzione'],
  [/\b(prossimamente online|stiamo lavorando al (nostro )?sito)\b/i, 'Prossimamente online'],
  [/\b(seite im aufbau|website im aufbau|in kürze online|in kuerze online)\b/i, 'Seite im Aufbau'],
  [/just another wordpress site/i, 'Just another WordPress site'],
  [/\b(hello world!|ciao mondo!|hallo welt!)/i, 'Hello world!'],
  [/\b(sample page|pagina di esempio|beispiel-seite)\b/i, 'Beispielseite'],
  [/\bnomesito\b/i, 'nomesito'],
  [/\b(your company name|il tuo nome|nome azienda|ihr firmenname)\b/i, 'Firmenname-Platzhalter'],
];

export function technik(r: Rohdaten): Befund[] {
  const b: Befund[] = [];
  const n = r.netz;
  const start = `https://${n.domain}/`;

  if (!n.dnsOk) {
    b.push({ kategorie: 'technik', code: 'domain_tot', schwere: 5, titel: `Domain ${n.domain} löst nicht auf (DNS)`,
      beschreibung: 'Die Adresse, die für diesen Betrieb im Netz steht, führt zu keinem Server.',
      wirkung: 'Wer der Adresse folgt, findet keine Seite.', url: `http://${n.domain}/`,
      messwert: { text: n.domain }, beleg: `DNS-Abfrage zweimal gescheitert (${n.dnsFehler ?? 'kein Eintrag'})`, status: 'VERIFIED' });
    return b;
  }

  const hLetzt = n.https[n.https.length - 1];
  const pLetzt = n.http[n.http.length - 1];
  const httpsTls = n.https.find((a) => istTlsFehler(a.fehler));
  const httpsGeht = !!hLetzt?.status && hLetzt.status < 400 && hLetzt.url.startsWith('https:');
  const landetAufHttp = n.https.length > 1 && hLetzt?.url.startsWith('http:') && !!hLetzt?.status && hLetzt.status < 400;

  if (n.endStatus === null) {
    b.push({ kategorie: 'technik', code: 'seite_nicht_erreichbar', schwere: 5, titel: 'Website antwortet nicht',
      url: start, beleg: `HTTPS: ${hLetzt?.fehler ?? '—'} · HTTP: ${pLetzt?.fehler ?? '—'}`, status: 'VERIFIED' });
    return b;
  }
  if (n.endStatus >= 400) {
    b.push({ kategorie: 'technik', code: 'seite_nicht_erreichbar', schwere: 5, titel: `Startseite antwortet mit Fehler ${n.endStatus}`,
      url: n.endUrl ?? start, messwert: { wert: n.endStatus, einheit: 'anzahl' }, beleg: `HTTP-Status ${n.endStatus} auf ${n.endUrl}`, status: 'VERIFIED' });
  }
  if (httpsTls) {
    b.push({ kategorie: 'technik', code: 'ssl_fehler', schwere: 5, titel: 'Sicherheitszertifikat fehlerhaft',
      beschreibung: 'Beim verschlüsselten Aufruf meldet die Verbindung einen Zertifikatsfehler; Browser zeigen eine Warnseite.',
      url: start, messwert: { text: httpsTls.fehler }, beleg: `${httpsTls.url} → ${httpsTls.fehler}`, status: 'VERIFIED' });
  } else if (landetAufHttp) {
    b.push({ kategorie: 'technik', code: 'https_auf_http', schwere: 4, titel: 'HTTPS leitet auf unverschlüsselte Seite zurück',
      url: start, beleg: n.https.map((a) => `${a.url} ${a.status ?? a.fehler}`).join(' → '), status: 'VERIFIED' });
  } else if (!httpsGeht && pLetzt?.status && pLetzt.status < 400) {
    b.push({ kategorie: 'technik', code: 'kein_https', schwere: 4, titel: 'Keine verschlüsselte Verbindung (HTTPS)',
      url: `http://${n.domain}/`, beleg: `HTTPS: ${hLetzt?.status ?? hLetzt?.fehler ?? '—'} · HTTP: ${pLetzt.status}`, status: 'VERIFIED' });
  } else if (httpsGeht && pLetzt?.status && pLetzt.status < 400 && pLetzt.url.startsWith('http:')) {
    b.push({ kategorie: 'technik', code: 'http_nicht_umgeleitet', schwere: 2, titel: 'HTTP wird nicht auf HTTPS umgeleitet',
      url: `http://${n.domain}/`, beleg: `http://${n.domain}/ antwortet ${pLetzt.status} ohne Umleitung`, status: 'VERIFIED' });
  }
  const laengste = Math.max(n.https.length, n.http.length) - 1;
  if (laengste >= 3) {
    b.push({ kategorie: 'technik', code: 'weiterleitungskette', schwere: 1, titel: `${laengste} Weiterleitungen bis zur Startseite`,
      url: start, messwert: { wert: laengste, einheit: 'anzahl' }, beleg: (n.https.length > n.http.length ? n.https : n.http).map((a) => a.url).join(' → '), status: 'VERIFIED' });
  }

  const br = r.browser;
  if (!br?.mobil) return b;

  if (r.kaputteLinks.length) {
    b.push({ kategorie: 'technik', code: 'defekte_links', schwere: r.kaputteLinks.length >= 3 ? 3 : 2,
      titel: `${r.kaputteLinks.length} Verweis(e) führen auf Fehlerseiten`,
      messwert: { wert: r.kaputteLinks.length, einheit: 'anzahl' },
      beleg: r.kaputteLinks.slice(0, 6).map((k) => `${k.status ?? 'x'} ${k.url}`).join('\n'), status: 'VERIFIED' });
  }
  const kaputt = Array.from(new Set(alleSeiten(r).flatMap((s) => s.bilder.kaputt)));
  if (kaputt.length) {
    b.push({ kategorie: 'technik', code: 'kaputte_bilder', schwere: kaputt.length >= 3 ? 3 : 2, titel: `${kaputt.length} Bild(er) werden nicht geladen`,
      messwert: { wert: kaputt.length, einheit: 'anzahl' }, beleg: kaputt.slice(0, 5).join('\n'), status: 'VERIFIED' });
  }
  const js = [...br.seitenFehler, ...br.konsoleFehler.filter((t) => !/favicon|net::ERR_BLOCKED|google|facebook|analytics|doubleclick/i.test(t))];
  if (js.length >= 2) {
    b.push({ kategorie: 'technik', code: 'js_fehler', schwere: 1, titel: `${js.length} JavaScript-Fehler beim Laden`,
      beschreibung: 'Im Hintergrund meldet die Seite Fehler; sichtbare Folgen sind nicht immer gegeben.',
      messwert: { wert: js.length, einheit: 'anzahl' }, beleg: js.slice(0, 4).join('\n'), status: 'VERIFIED' });
  }
  if (br.gemischteInhalte.length) {
    b.push({ kategorie: 'technik', code: 'gemischte_inhalte', schwere: 2, titel: 'Unverschlüsselte Inhalte auf HTTPS-Seite',
      messwert: { wert: br.gemischteInhalte.length, einheit: 'anzahl' }, beleg: br.gemischteInhalte.slice(0, 4).join('\n'), status: 'VERIFIED' });
  }

  const m = br.mobil;
  const alt: string[] = [];
  if (m.jquery && /^1\./.test(m.jquery)) alt.push(`jQuery ${m.jquery}`);
  if (m.flash) alt.push('Flash-Inhalte');
  if (m.frames) alt.push('Frames');
  if (m.marquee) alt.push('<marquee>/<blink>');
  const wp = m.generator?.match(/WordPress\s+(\d+)\.(\d+)/i);
  if (wp && Number(wp[1]) < 6) alt.push(`WordPress ${wp[1]}.${wp[2]} (laut Generator-Angabe)`);
  const joomla = m.generator?.match(/Joomla!?\s*(\d+)/i);
  if (joomla && Number(joomla[1]) < 4) alt.push(`Joomla ${joomla[1]}`);
  if (alt.length) {
    b.push({ kategorie: 'technik', code: 'veraltete_technik', schwere: alt.length >= 2 ? 3 : 2, titel: 'Veraltete Technik im Einsatz',
      messwert: { text: alt.join(', ') }, beleg: alt.join(' · '), status: 'VERIFIED' });
  }

  for (const s of alleSeiten(r)) {
    const t = PLATZHALTER.find(([re]) => re.test(s.text) || re.test(s.titel));
    if (t) {
      const stelle = s.text.search(t[0]);
      b.push({ kategorie: 'technik', code: 'platzhalter_text', schwere: 4, titel: `Platzhaltertext live: „${t[1]}“`, url: s.url,
        messwert: { text: `„${t[1]}“` }, beleg: stelle >= 0 ? '…' + s.text.slice(Math.max(0, stelle - 40), stelle + 80) + '…' : `Titel: ${s.titel}`, status: 'VERIFIED' });
      break;
    }
  }

  const jahre = alleSeiten(r).flatMap((s) => s.copyrightJahre);
  if (jahre.length) {
    const hoechst = Math.max(...jahre);
    if (hoechst <= r.jahr - 3) {
      b.push({ kategorie: 'technik', code: 'copyright_alt', schwere: 2, titel: `Copyright-Jahr ${hoechst} im Fußbereich`,
        messwert: { wert: hoechst, einheit: 'jahr' }, beleg: `höchstes gefundenes Jahr: ${hoechst}`, status: 'VERIFIED' });
    }
  }
  return b;
}
