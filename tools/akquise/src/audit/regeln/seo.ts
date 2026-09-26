/* SEO: was Google von der Seite zu sehen bekommt -- Titel, Beschreibung,
   Ueberschriften, strukturierte Daten, Indexierbarkeit, Sprachfassungen. */
import { norm } from '../../hilfen.js';
import { BRANCHEN } from '../../branchen.js';
import { sprachenDerSeite, type Befund, type Rohdaten } from '../typen.js';

const GENERISCH = /^(home|homepage|startseite|benvenuti|benvenuto|welcome|index|untitled|sito web|website|willkommen)$/i;

export function seo(r: Rohdaten): Befund[] {
  const b: Befund[] = [];
  const m = r.browser?.mobil;
  if (!m) return b;
  const url = m.url;
  const titel = m.titel.trim();

  if (!titel) {
    b.push({ kategorie: 'seo', code: 'title_fehlt', schwere: 4, titel: 'Startseite hat keinen Seitentitel', url, beleg: '<title> leer oder nicht vorhanden', status: 'VERIFIED' });
  } else if (titel.length > 75) {
    b.push({ kategorie: 'seo', code: 'title_lang', schwere: 1, titel: `Seitentitel zu lang (${titel.length} Zeichen) — Google kürzt ihn`,
      url, messwert: { wert: titel.length, einheit: 'anzahl' }, beleg: `<title>${titel}</title>`, status: 'VERIFIED' });
  } else if (titel.length < 15 || GENERISCH.test(titel) || norm(titel) === norm(r.firma.domain)) {
    b.push({ kategorie: 'seo', code: 'title_schwach', schwere: 2, titel: `Seitentitel wenig aussagekräftig: „${titel.slice(0, 60)}“`,
      url, messwert: { text: `„${titel.slice(0, 60)}“`, laenge: titel.length }, beleg: `<title>${titel}</title> (${titel.length} Zeichen)`, status: 'VERIFIED' });
  }
  /* Der Ort in mehreren Sprachen: "Agrigento" steht auf einer deutschen
     Seite als "Agrigent", "München" auf einer italienischen als "Monaco".
     Verglichen wird deshalb der Wortstamm; fuer echte Fremdnamen reicht
     das nicht, darum ist dieser Befund nur ein Hinweis (UNVERIFIED), wenn
     die Seite nicht in der Landessprache ist. */
  const stadtVoll = r.firma.stadt ? norm(r.firma.stadt) : '';
  const stamm = stadtVoll.slice(0, Math.max(5, stadtVoll.length - 2));
  const inText = (t: string) => norm(t).includes(stamm);
  if (titel && stamm.length > 3 && !inText(titel) && !m.h1.some(inText)) {
    b.push({ kategorie: 'seo', code: 'ort_fehlt_im_title', schwere: 2, titel: `Ort „${r.firma.stadt}“ fehlt in Titel und Hauptüberschrift`,
      url, beleg: `Titel: „${titel.slice(0, 70)}“ · H1: ${m.h1.map((h) => `„${h.slice(0, 40)}“`).join(', ') || '—'}`,
      status: (m.lang ?? '').slice(0, 2).toLowerCase() === (r.firma.land === 'DE' ? 'de' : 'it') ? 'VERIFIED' : 'UNVERIFIED' });
  }
  if (!m.metaDescription || m.metaDescription.trim().length < 50) {
    b.push({ kategorie: 'seo', code: 'meta_description_fehlt', schwere: 2, titel: m.metaDescription ? 'Meta-Beschreibung sehr kurz' : 'Keine Meta-Beschreibung',
      url, beleg: m.metaDescription ? `„${m.metaDescription}“` : '<meta name="description"> fehlt', status: 'VERIFIED' });
  }
  if (!m.h1.length) {
    b.push({ kategorie: 'seo', code: 'h1_fehlt', schwere: 2, titel: 'Keine Hauptüberschrift (H1) auf der Startseite', url, beleg: 'kein <h1>', status: 'VERIFIED' });
  } else if (m.h1.length > 2) {
    b.push({ kategorie: 'seo', code: 'h1_mehrfach', schwere: 1, titel: `${m.h1.length} Hauptüberschriften (H1) auf der Startseite`, url,
      beleg: m.h1.map((h) => `„${h.slice(0, 40)}“`).join(', '), status: 'VERIFIED' });
  }
  const lokal = /(LocalBusiness|Restaurant|Hotel|LodgingBusiness|Organization|Store|FoodEstablishment|ProfessionalService|HomeAndConstructionBusiness|AutoDealer|HealthAndBeautyBusiness|RealEstateAgent|Bakery|CafeOrCoffeeShop|BedAndBreakfast|Winery|LegalService|Dentist|MedicalBusiness|SportsActivityLocation)/i;
  if (!m.jsonLdTypen.some((t) => lokal.test(t))) {
    b.push({ kategorie: 'seo', code: 'keine_strukturierten_daten', schwere: 2, titel: 'Keine strukturierten Unternehmensdaten (Schema.org)',
      url, beleg: m.jsonLdTypen.length ? `JSON-LD vorhanden, aber nur: ${m.jsonLdTypen.join(', ')}` : 'kein JSON-LD auf der Startseite', status: 'VERIFIED' });
  }
  if (!m.canonical) {
    b.push({ kategorie: 'seo', code: 'canonical_fehlt', schwere: 1, titel: 'Kein Canonical-Tag', url, beleg: '<link rel="canonical"> fehlt', status: 'VERIFIED' });
  }
  if (!r.sitemapDa) {
    b.push({ kategorie: 'seo', code: 'sitemap_fehlt', schwere: 1, titel: 'Keine sitemap.xml gefunden', beleg: '/sitemap.xml, /sitemap_index.xml und robots.txt ohne Sitemap-Angabe', status: 'VERIFIED' });
  }
  if (m.robotsMeta && /noindex/i.test(m.robotsMeta)) {
    b.push({ kategorie: 'seo', code: 'robots_sperrt', schwere: 5, titel: 'Startseite ist für Suchmaschinen gesperrt (noindex)',
      url, beleg: `<meta name="robots" content="${m.robotsMeta}">`, status: 'VERIFIED' });
  } else if (/^\s*disallow:\s*\/\s*$/im.test(r.robots.text) && /user-agent:\s*\*/i.test(r.robots.text)) {
    b.push({ kategorie: 'seo', code: 'robots_sperrt', schwere: 4, titel: 'robots.txt sperrt die ganze Website (Disallow: /)',
      beleg: r.robots.text.slice(0, 200), status: 'UNVERIFIED' });
  }
  if (m.bilder.inhalt >= 4 && m.bilder.ohneAlt / m.bilder.inhalt > 0.5) {
    b.push({ kategorie: 'seo', code: 'alt_texte_fehlen', schwere: 1, titel: `${m.bilder.ohneAlt} von ${m.bilder.inhalt} Bildern ohne Alternativtext`,
      url, messwert: { wert: m.bilder.ohneAlt, einheit: 'anzahl' }, beleg: 'Inhaltsbilder ≥ 200 px ohne alt-Attribut', status: 'VERIFIED' });
  }
  const tourismus = !!(r.firma.branche && BRANCHEN[r.firma.branche]?.tourismus);
  const sprachen = sprachenDerSeite(m);
  if (tourismus && sprachen.size < 2) {
    b.push({ kategorie: 'seo', code: 'keine_sprachversion', schwere: 3, titel: 'Nur eine Sprache — bei touristischem Angebot',
      url, beleg: `hreflang: ${m.hreflang.join(', ') || '—'} · kein Sprachpfad, keine Umschaltung · lang="${m.lang ?? '—'}"`,
      status: (r.browser?.unterseiten.length ?? 0) >= 2 ? 'VERIFIED' : 'UNVERIFIED' });
  }
  return b;
}
