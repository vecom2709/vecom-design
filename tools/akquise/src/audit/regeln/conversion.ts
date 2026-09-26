/* ==========================================================================
   Conversion und UX -- je Branche.

   Was ein Besucher in DIESER Branche finden muss (akquise_branchen.json →
   "pflicht"), wird auf allen angesehenen Seiten gesucht: im Text, in den
   Linktexten, in den Adressen der Links, in eingebetteten Diensten.

   "Nicht gefunden" ist nur dann ein belegter Befund, wenn genug Seiten
   angesehen wurden (Start + mindestens zwei Unterseiten) und das Merkmal
   verlaesslich erkennbar ist. Sonst UNVERIFIED -- lieber ein Hinweis zu
   wenig als eine falsche Behauptung in einem Brief an den Inhaber.
   ========================================================================== */
import { BRANCHEN } from '../../branchen.js';
import { norm } from '../../hilfen.js';
import { alleSeiten, sprachenDerSeite, type Befund, type Rohdaten } from '../typen.js';

type Pruefer = { muster: RegExp; dienste?: RegExp; verlaesslich: boolean; schwere: 2 | 3 | 4; titel: string };

/* Muster auf normalisiertem Text (klein, ohne Akzente). */
const MERKMALE: Record<string, Pruefer> = {
  reservierung: { muster: /(prenota(re|zione|zioni)?( un| il)? tavolo|prenota ora|prenotazion|reserv(ieren|ierung|ation|e a table|e now)|tisch reserv|book a table)/,
    dienste: /(thefork|quandoo|opentable|resmio|tableo|covermanager|sevenrooms|plateformes|bookatable|restaurant-reservation)/, verlaesslich: true, schwere: 4, titel: 'Keine Möglichkeit zur Tischreservierung' },
  speisekarte: { muster: /(\bmenu\b|\bmenù\b|il nostro menu|speisekarte|la carta|carta dei vini|unsere karte|food menu)/, verlaesslich: true, schwere: 3, titel: 'Keine Speisekarte gefunden' },
  oeffnungszeiten: { muster: /(orari|orario|aperto|chiuso|oeffnungszeiten|offnungszeiten|geoffnet|ruhetag|opening hours|open daily|(lun|mar|mer|gio|ven|sab|dom|mo|di|mi|do|fr|sa|so|mon|tue|wed|thu|fri|sat|sun)[a-z]*\.?\s*[-–:]?\s*\d{1,2}[:.]\d{2})/,
    verlaesslich: true, schwere: 3, titel: 'Keine Öffnungszeiten gefunden' },
  anfahrt: { muster: /(come raggiungerci|dove siamo|indicazioni|anfahrt|so finden sie uns|wegbeschreibung|how to find us|directions|get directions)/,
    dienste: /(google\.[a-z.]+\/maps|maps\.google|goo\.gl\/maps|maps\.app\.goo\.gl|openstreetmap\.org|apple\.com\/maps|waze\.com)/, verlaesslich: true, schwere: 2, titel: 'Keine Karte oder Anfahrtsbeschreibung' },
  buchung: { muster: /(prenota ora|prenota il tuo|verifica disponibilita|disponibilita e prezzi|jetzt buchen|verfugbarkeit|book now|check availability|booking)/,
    dienste: /(octorate|simplebooking|vertical-?booking|bookingexpert|beds24|smoobu|lodgify|krossbooking|cloudbeds|synxis|siteminder|bookassist|hotelrunner|ericsoft|passepartout|bb-?planet|wubook|booking\.com|airbnb)/, verlaesslich: true, schwere: 4, titel: 'Keine direkte Online-Buchung' },
  zimmer: { muster: /(camer[ae]|stanz[ae]|suite|appartament|monolocal|bilocal|zimmer|appartement|ferienwohnung|rooms?\b|apartments?)/, verlaesslich: true, schwere: 3, titel: 'Keine Übersicht der Zimmer/Unterkünfte' },
  galerie: { muster: /(galleria|gallery|galerie|fotogalerie|le nostre foto|bilder|photos)/, verlaesslich: false, schwere: 2, titel: 'Keine Bildergalerie' },
  leistungen: { muster: /(servizi|i nostri servizi|cosa facciamo|leistungen|unsere leistungen|angebot|services|what we do)/, verlaesslich: true, schwere: 3, titel: 'Keine klare Leistungsübersicht' },
  referenzen: { muster: /(progetti|lavori|realizzazioni|referenze|portfolio|referenzen|projekte|unsere arbeiten|case stud|vorher|nachher|prima e dopo)/, verlaesslich: true, schwere: 3, titel: 'Keine Referenzen oder Projektbeispiele' },
  angebot_cta: { muster: /(preventivo|richiedi (un )?preventivo|angebot anfordern|kostenvoranschlag|jetzt anfragen|request a quote|get a quote|free quote)/, verlaesslich: true, schwere: 3, titel: 'Kein direkter Weg zur Angebotsanfrage' },
  objekte: { muster: /(immobili|annunci|vendita|affitto|in vendita|objekte|immobilienangebote|zum verkauf|zur miete|listings|properties|fahrzeuge|veicoli|auto usate|gebrauchtwagen|neuwagen)/,
    dienste: /(immobiliare\.it|idealista|casa\.it|immoscout|immowelt|mobile\.de|autoscout24|subito\.it)/, verlaesslich: true, schwere: 3, titel: 'Kein erkennbarer Bestand/keine Objektübersicht' },
  termin: { muster: /(prenota (un )?appuntamento|prenota online|appuntamento|termin (buchen|vereinbaren|online)|online-termin|book (an )?appointment)/,
    dienste: /(treatwell|fresha|timify|calendly|shore\.com|booksy|planity|uala|doctolib|miodottore|jameda|etermin)/, verlaesslich: true, schwere: 3, titel: 'Keine Online-Terminanfrage' },
  preise: { muster: /(listino|prezzi|tariffe|preise|preisliste|price list|prices|pricing|€\s?\d|\d\s?€|eur\s?\d)/, verlaesslich: false, schwere: 2, titel: 'Keine Preisangaben' },
  produkte: { muster: /(prodotti|i nostri prodotti|catalogo|produkte|sortiment|products|our products)/, verlaesslich: true, schwere: 3, titel: 'Keine Produktübersicht' },
  shop: { muster: /(carrello|aggiungi al carrello|acquista|shop online|warenkorb|in den warenkorb|online-?shop|add to cart|buy now)/,
    dienste: /(shopify|woocommerce|ecwid|wix.*store|prestashop|magento|etsy)/, verlaesslich: true, schwere: 3, titel: 'Keine Online-Bestellmöglichkeit' },
  bestellung: { muster: /(ordina|ordinazion|prenota (il tuo|la tua)|vorbestell|bestellen|order online|pre-?order)/, verlaesslich: false, schwere: 2, titel: 'Keine Vorbestellmöglichkeit' },
  sprachen: { muster: /$^/, verlaesslich: true, schwere: 3, titel: 'Keine zweite Sprachfassung' },
  einsatzgebiet: { muster: /(zona di intervento|operiamo in|serviamo|in tutta la provincia|einsatzgebiet|im umkreis|service area|we serve)/, verlaesslich: false, schwere: 2, titel: 'Einsatzgebiet nicht genannt' },
  zertifikate: { muster: /(certificat|certificazion|iso 9001|soa|zertifi|meisterbetrieb|innung|certified)/, verlaesslich: false, schwere: 2, titel: 'Keine Zertifikate/Qualifikationen genannt' },
  team: { muster: /(chi siamo|il nostro team|lo staff|team|uber uns|ueber uns|unser team|about us|our team)/, verlaesslich: false, schwere: 2, titel: 'Kein „Über uns“/Team' },
  probetraining: { muster: /(prova gratuita|lezione di prova|probetraining|kostenlos testen|free trial|trial session)/, verlaesslich: true, schwere: 3, titel: 'Kein Probetraining-Angebot' },
  kurse: { muster: /(corsi|orario corsi|kursplan|kurse|classes|class schedule|timetable)/, verlaesslich: true, schwere: 2, titel: 'Kein Kursplan' },
  suche: { muster: /$^/, verlaesslich: true, schwere: 2, titel: 'Keine Such- oder Filterfunktion' },
  angebote: { muster: /(escursion|tour|pacchett|offert|angebote|touren|ausfluge|pakete|excursions|packages|offers)/, verlaesslich: true, schwere: 3, titel: 'Keine Übersicht der Angebote/Touren' },
  tel_klickbar: { muster: /$^/, verlaesslich: true, schwere: 3, titel: '' },   // eigener Befund unter Mobile
};

export function conversion(r: Rohdaten): Befund[] {
  const b: Befund[] = [];
  const seiten = alleSeiten(r);
  if (!seiten.length) return b;
  const branche = r.firma.branche ? BRANCHEN[r.firma.branche] : undefined;
  const genug = seiten.length >= 3;
  const text = norm(seiten.map((s) => [s.titel, s.text, ...s.links.map((l) => l.text)].join(' ')).join(' '));
  const adressen = seiten.flatMap((s) => [...s.links.map((l) => l.href), ...s.iframes, ...s.skripte]).join(' ').toLowerCase();
  const m = r.browser!.mobil!;

  for (const x of branche?.pflicht ?? []) {
    const p = MERKMALE[x];
    if (!p || x === 'tel_klickbar') continue;
    let da = p.muster.test(text) || (p.dienste?.test(adressen) ?? false) || p.muster.test(adressen);
    if (x === 'sprachen') {
      // Doppelt mit seo/keine_sprachversion -- dort steht der Befund schon.
      continue;
    }
    if (x === 'suche') da = seiten.some((s) => s.suchfeld);
    if (x === 'reservierung' && seiten.some((s) => s.formulare.mitDatum > 0)) da = true;
    if (x === 'galerie' && m.bilder.inhalt >= 6) da = true;
    if (x === 'speisekarte' && da) {
      // Karte vorhanden -- aber nur als PDF?
      const pdfKarte = seiten.flatMap((s) => s.links).filter((l) => /\.pdf(\?|$)/i.test(l.href) && /(menu|menù|carta|karte|speise)/i.test(l.href + ' ' + l.text));
      const htmlKarte = seiten.some((s) => /(menu|menù|speisekarte|carta)/i.test(s.url) && !/\.pdf/i.test(s.url) && s.text.length > 800);
      if (pdfKarte.length && !htmlKarte) {
        b.push({ kategorie: 'conversion', code: 'speisekarte_nur_pdf', schwere: 3, titel: 'Speisekarte nur als PDF',
          url: pdfKarte[0].href, beleg: `PDF: ${pdfKarte[0].href}`, status: 'VERIFIED' });
      }
    }
    if (x === 'buchung' && da) {
      const portal = /(booking\.com|airbnb|expedia|hotels\.com)/i.test(adressen);
      const eigen = /(octorate|simplebooking|vertical-?booking|bookingexpert|beds24|smoobu|lodgify|krossbooking|cloudbeds|synxis|siteminder|bookassist|hotelrunner|ericsoft|wubook)/i.test(adressen)
        || seiten.some((s) => s.formulare.mitDatum > 0);
      if (portal && !eigen) {
        b.push({ kategorie: 'conversion', code: 'buchung_nur_portal', schwere: 3, titel: 'Buchung nur über externes Portal',
          beleg: 'Links auf Booking.com/Airbnb, keine eigene Buchungsmaschine oder Anfrage mit Datum', status: 'VERIFIED' });
      }
    }
    if (da) continue;
    b.push({
      kategorie: 'conversion', code: `fehlt_${x}`, schwere: p.schwere, titel: p.titel,
      beschreibung: `Für ${branche?.de ?? 'diese Branche'} gehört das zu dem, was Besucher suchen. Geprüft: ${seiten.length} Seite(n).`,
      wirkung: 'Mögliche Conversion-Hürde: kann die Zahl der Anfragen beeinflussen.',
      beleg: `nicht gefunden auf: ${seiten.map((s) => s.url).slice(0, 5).join(', ')}`,
      status: genug && p.verlaesslich ? 'VERIFIED' : 'UNVERIFIED',
    });
  }

  // Kontaktweg ueberhaupt
  const kontakt = seiten.some((s) => s.telLinks.length || s.mailtoLinks.length || s.waLinks.length || s.formulare.mitTextarea || s.formulare.mitEmail);
  if (!kontakt) {
    b.push({ kategorie: 'conversion', code: 'kein_kontaktweg', schwere: 4, titel: 'Kein direkter Kontaktweg (Anruf-Link, E-Mail, WhatsApp oder Formular)',
      beleg: `geprüft: ${seiten.length} Seite(n)`, status: genug ? 'VERIFIED' : 'UNVERIFIED' });
  } else if (!seiten.some((s) => s.formulare.mitTextarea || s.formulare.mitEmail) && ['handwerk', 'bau', 'beratung', 'industrie', 'immobilien'].includes(r.firma.branche ?? '')) {
    b.push({ kategorie: 'conversion', code: 'kein_kontaktformular', schwere: 2, titel: 'Kein Anfrageformular',
      beleg: `geprüft: ${seiten.length} Seite(n)`, status: genug ? 'VERIFIED' : 'UNVERIFIED' });
  }
  if (m.links.length > 150 && m.text.length < 3000) {
    b.push({ kategorie: 'ux', code: 'navigation_ueberladen', schwere: 2, titel: `${m.links.length} Links auf der Startseite bei wenig Text`,
      beleg: `${m.links.length} Links, ${m.text.length} Zeichen Text`, status: 'UNVERIFIED' });
  }
  const l = r.leistung;
  if (l?.kontrastFehler && l.kontrastFehler > 0) {
    b.push({ kategorie: 'ux', code: 'kontrast_gering', schwere: 2, titel: `Zu geringer Farbkontrast (${l.kontrastFehler} Stelle(n))`,
      beleg: `${l.quelle}: Barrierefreiheitsprüfung „color-contrast“ nicht bestanden`, status: 'VERIFIED' });
  }
  return b;
}
