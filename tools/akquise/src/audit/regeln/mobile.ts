/* Mobile First: gemessen im 390-px-Browser, nicht geschaetzt. */
import type { Befund, Rohdaten } from '../typen.js';

export function mobile(r: Rohdaten): Befund[] {
  const b: Befund[] = [];
  const m = r.browser?.mobil;
  if (!m) return b;
  const url = m.url;

  if (!m.viewport || !/width\s*=\s*device-width/i.test(m.viewport)) {
    b.push({ kategorie: 'mobile', code: 'kein_viewport', schwere: 5, titel: 'Nicht für Smartphones eingerichtet (kein Viewport)',
      beschreibung: 'Ohne Viewport-Angabe zeigt das Smartphone die Desktop-Fassung verkleinert.',
      url, beleg: `<meta name="viewport"> ${m.viewport ? `= "${m.viewport}"` : 'fehlt'}`, status: 'VERIFIED' });
  }
  if (m.ueberbreitePx > 8) {
    b.push({ kategorie: 'mobile', code: 'horizontal_scroll', schwere: m.ueberbreitePx > 60 ? 3 : 2,
      titel: `Seite ist mobil ${m.ueberbreitePx} px breiter als der Bildschirm`,
      url, messwert: { wert: m.ueberbreitePx, einheit: 'px' }, beleg: `scrollWidth − innerWidth = ${m.ueberbreitePx} px bei 390 px Breite`, status: 'VERIFIED' });
  }
  if (m.kleineSchriftAnteil >= 0.2) {
    b.push({ kategorie: 'mobile', code: 'schrift_klein', schwere: m.kleineSchriftAnteil >= 0.4 ? 3 : 2,
      titel: `${Math.round(m.kleineSchriftAnteil * 100)} % des Textes mobil unter 12 px`,
      url, messwert: { wert: m.kleinsteSchriftPx, einheit: 'px', anteil: m.kleineSchriftAnteil },
      beleg: `Textanteil < 12 px: ${Math.round(m.kleineSchriftAnteil * 100)} %, kleinste Schrift ${m.kleinsteSchriftPx} px`, status: 'VERIFIED' });
  }
  if (m.kleineTapZiele >= 5 && m.tapZiele > 0 && m.kleineTapZiele / m.tapZiele >= 0.25) {
    b.push({ kategorie: 'mobile', code: 'tap_ziele_klein', schwere: 2, titel: `${m.kleineTapZiele} von ${m.tapZiele} Bedienelementen mobil sehr klein`,
      url, messwert: { wert: m.kleineTapZiele, einheit: 'anzahl' }, beleg: 'Links/Knöpfe mit Text kleiner als 32 × 24 px', status: 'VERIFIED' });
  }
  const telText = [m, ...(r.browser?.unterseiten ?? [])].flatMap((s) => s.telefonImText);
  const telLinks = [m, ...(r.browser?.unterseiten ?? [])].flatMap((s) => s.telLinks);
  if (telText.length && !telLinks.length) {
    b.push({ kategorie: 'mobile', code: 'tel_nicht_klickbar', schwere: 4, titel: 'Telefonnummer mobil nicht antippbar',
      beschreibung: 'Die Nummer steht als Text auf der Seite, aber ohne tel:-Verweis.',
      url, beleg: `gefunden: ${telText[0]} · keine tel:-Links auf ${1 + (r.browser?.unterseiten.length ?? 0)} geprüften Seiten`, status: 'VERIFIED' });
  }
  if (m.kontaktErsteY === null && !telLinks.length) {
    // wird unter Conversion als fehlender Kontaktweg gefuehrt
  } else if (m.kontaktErsteY !== null && m.kontaktErsteY > m.viewportHoehe * 2.5) {
    const bildschirme = Math.round((m.kontaktErsteY / m.viewportHoehe) * 10) / 10;
    b.push({ kategorie: 'mobile', code: 'kontakt_mobil_weit', schwere: 3, titel: `Erste Kontaktmöglichkeit mobil erst nach ${bildschirme.toLocaleString('de-DE')} Bildschirmhöhen`,
      url, messwert: { wert: Math.round(m.kontaktErsteY), einheit: 'px', bildschirme }, beleg: `erste Kontaktmöglichkeit bei y = ${Math.round(m.kontaktErsteY)} px (Bildschirm ${m.viewportHoehe} px)`, status: 'VERIFIED' });
  }
  if (!m.ctaImErstenBildschirm.length) {
    b.push({ kategorie: 'ux', code: 'kein_cta_oben', schwere: 3, titel: 'Kein klarer Handlungsknopf im ersten Bildschirm (mobil)',
      url, beleg: 'kein Link/Knopf mit „Kontakt/Anfragen/Buchen/Prenota/Chiama …“ und kein tel:/mailto:/WhatsApp im ersten Bildschirm', status: 'VERIFIED' });
  }
  return b;
}
