/* Vertrauen: Pflichtangaben (Impressum/Partita IVA, Datenschutz), Adresse,
   Bewertungen. Die Pflichtangaben sind je Land verschieden. */
import { norm } from '../../hilfen.js';
import { alleSeiten, type Befund, type Rohdaten } from '../typen.js';

/* ==========================================================================
   Partita IVA: jede gueltige 11-stellige Nummer zaehlt, egal wie sie
   beschriftet ist.

   LEHRE AUS DEM ERSTEN ECHTEN LAUF: Eine deutsche Inhaberin in Palermo
   fuehrt ihre Partita IVA als "Steuernummer: 05721190824". Die erste
   Fassung suchte nach "P.IVA" und haette ihr geschrieben, die Nummer fehle.
   Jetzt wird die Pruefziffer gerechnet (Luhn-Verfahren der Agenzia delle
   Entrate) -- eine Telefonnummer besteht diese Pruefung fast nie, eine
   echte Partita IVA immer.
   ========================================================================== */
export function pivaGueltig(z: string): boolean {
  if (!/^\d{11}$/.test(z) || /^0{11}$/.test(z)) return false;
  let summe = 0;
  for (let i = 0; i < 10; i++) {
    let d = Number(z[i]);
    if (i % 2 === 1) { d *= 2; if (d > 9) d -= 9; }
    summe += d;
  }
  return (10 - (summe % 10)) % 10 === Number(z[10]);
}

export function pivaGefunden(text: string): boolean {
  for (const m of text.matchAll(/(?<!\d)(?:IT\s?)?(\d{11})(?!\d)/g)) {
    if (pivaGueltig(m[1])) return true;
  }
  return false;
}

export function vertrauen(r: Rohdaten): Befund[] {
  const b: Befund[] = [];
  const seiten = alleSeiten(r);
  if (!seiten.length) return b;
  const genug = seiten.length >= 3;
  const roh = seiten.map((s) => s.text + ' ' + s.links.map((l) => l.text + ' ' + l.href).join(' ')).join(' ');
  const text = norm(roh);

  if (r.firma.land === 'DE' && !/impressum|imprint/.test(text)) {
    b.push({ kategorie: 'vertrauen', code: 'impressum_fehlt', schwere: 4, titel: 'Kein Impressum gefunden',
      beleg: `kein Link und kein Text „Impressum“ auf ${seiten.length} Seite(n)`, status: genug ? 'VERIFIED' : 'UNVERIFIED' });
  }
  if (r.firma.land === 'IT' && !pivaGefunden(roh)) {
    const rechtsseite = seiten.some((s) => /(impressum|note-?legali|privacy|contatt|chi-siamo|dati-societari|azienda)/i.test(s.url));
    b.push({ kategorie: 'vertrauen', code: 'piva_fehlt', schwere: 3, titel: 'Keine Partita IVA auf der Website',
      beleg: `keine gültige 11-stellige Partita IVA (Prüfziffer) auf ${seiten.length} Seite(n)${rechtsseite ? '' : ' — keine Kontakt-/Rechtsseite gefunden'}`,
      status: genug && rechtsseite ? 'VERIFIED' : 'UNVERIFIED' });
  }
  if (!/(privacy|datenschutz|cookie policy|informativa)/.test(text)) {
    b.push({ kategorie: 'vertrauen', code: 'datenschutz_fehlt', schwere: 3, titel: 'Keine Datenschutzerklärung gefunden',
      beleg: `kein Link „Privacy/Datenschutz“ auf ${seiten.length} Seite(n)`, status: genug ? 'VERIFIED' : 'UNVERIFIED' });
  }
  const adresse = r.firma.land === 'IT'
    ? /\b(via|viale|piazza|corso|contrada|c\.da|largo|vicolo)\s+[a-z]/i.test(roh) && /\b9\d{4}\b|\b\d{5}\b/.test(roh)
    : /\b\d{5}\s+[A-ZÄÖÜ][a-zäöüß]+/.test(roh) && /(str\.|straße|strasse|weg|platz|allee|gasse)\b/i.test(roh);
  if (!adresse) {
    b.push({ kategorie: 'vertrauen', code: 'adresse_fehlt', schwere: 2, titel: 'Keine vollständige Postanschrift erkennbar',
      beleg: `geprüft: ${seiten.length} Seite(n)`, status: 'UNVERIFIED' });
  }
  const bewertungen = /(recension|opinioni dei clienti|dicono di noi|testimonian|bewertung|kundenstimmen|erfahrungen|referenzen|reviews|testimonials|what our (guests|clients) say|stelle su|sterne)/.test(text)
    || /(trustindex|elfsight|tripadvisor\.[a-z]+\/(widget|wejs)|trustpilot|provenexpert|google.*review|reviews\.io)/i.test(seiten.flatMap((s) => [...s.skripte, ...s.iframes]).join(' '));
  if (!bewertungen) {
    b.push({ kategorie: 'vertrauen', code: 'keine_bewertungen', schwere: 2, titel: 'Keine Kundenstimmen oder Bewertungen auf der Website',
      beleg: `geprüft: ${seiten.length} Seite(n)`, status: genug ? 'VERIFIED' : 'UNVERIFIED' });
  }
  return b;
}
