/* ==========================================================================
   Design und Experience-Potenzial -- maschinell nur, was sich messen laesst.

   Ob eine Gestaltung "modern" wirkt, ist ein Urteil. Die Maschine liefert
   hier nur harte Zeichen (Tabellenlayout, <font>, feste Breite, wenige
   Bilder); das Urteil ueber Typografie, Farbsystem und Markenwirkung faellt
   Claude anhand des Bildschirmfotos -- und kommt als UNVERIFIED herein.

   Experience: 3D oder Unreal nie, weil es geht. Die Grundeignung kommt aus
   der Branche; hoch wird sie nur, wenn die Seite heute kaum zeigt, was der
   Betrieb anbietet (wenige, kleine Bilder) -- dann erzeugt Visualisierung
   tatsaechlich einen geschaeftlichen Mehrwert.
   ========================================================================== */
import { BRANCHEN } from '../../branchen.js';
import type { Befund, Rohdaten } from '../typen.js';

export function design(r: Rohdaten): Befund[] {
  const b: Befund[] = [];
  const m = r.browser?.mobil;
  const d = r.browser?.desktop;
  if (!m) return b;
  const zeichen: string[] = [];
  if (m.layoutTabellen >= 2) zeichen.push(`Tabellenlayout (${m.layoutTabellen} Layout-Tabellen)`);
  if (m.fontTags >= 3) zeichen.push(`${m.fontTags} <font>-Tags`);
  if (m.marquee) zeichen.push('Lauftext (<marquee>/<blink>)');
  if (!m.viewport) zeichen.push('keine mobile Fassung');
  if (d && d.inhaltsBreite > 0 && d.inhaltsBreite <= 1000 && d.seitenHoehe > 0) zeichen.push(`feste Inhaltsbreite ${d.inhaltsBreite} px am Desktop`);
  const jahre = m.copyrightJahre.length ? Math.max(...m.copyrightJahre) : null;
  if (jahre && jahre <= r.jahr - 5) zeichen.push(`Copyright ${jahre}`);
  if (zeichen.length >= 2) {
    b.push({ kategorie: 'design', code: 'design_veraltet', schwere: zeichen.length >= 3 ? 4 : 3, titel: 'Gestaltung technisch erkennbar veraltet',
      beschreibung: 'Mehrere technische Merkmale deuten auf eine ältere Gestaltungsgrundlage hin.',
      beleg: zeichen.join(' · '), status: 'VERIFIED' });
  }
  if (m.bilder.inhalt < 3) {
    b.push({ kategorie: 'design', code: 'wenig_bilder', schwere: 2, titel: `Nur ${m.bilder.inhalt} größere Bild(er) auf der Startseite`,
      beschreibung: 'Leistungen und Atmosphäre werden kaum visuell gezeigt.',
      messwert: { wert: m.bilder.inhalt, einheit: 'anzahl' }, beleg: `Bilder ≥ 200 px: ${m.bilder.inhalt} von ${m.bilder.gesamt}`, status: 'VERIFIED' });
  }
  return b;
}

export function experience(r: Rohdaten, bisher: Befund[]): Befund[] {
  const br = r.firma.branche ? BRANCHEN[r.firma.branche] : undefined;
  if (!br || !r.browser?.mobil) return [];
  let eignung = br.experience;                                    // 0–10 aus der Branche
  const schwachVisuell = bisher.some((x) => x.code === 'wenig_bilder' || x.code === 'design_veraltet');
  if (schwachVisuell) eignung += 1;
  if (r.firma.tourismus && Number(r.firma.tourismus) === 1) eignung += 1;
  eignung = Math.min(10, eignung);
  if (eignung < 5) return [];
  const schwere = Math.max(1, Math.min(5, Math.round(eignung / 2))) as 1 | 2 | 3 | 4 | 5;
  return [{
    kategorie: 'experience', code: 'experience_potenzial', schwere,
    titel: `Experience-Potenzial ${eignung}/10: ${br.experience_idee ?? 'visuelle Präsentation'}`,
    beschreibung: 'Einschätzung aus Branche und heutiger Darstellung — nur umsetzen, wenn es für diesen Betrieb einen geschäftlichen Mehrwert erzeugt.',
    messwert: { wert: eignung, grund_branche: br.experience, visuell_schwach: schwachVisuell },
    beleg: `Branche ${br.de}: Grundeignung ${br.experience}/10${schwachVisuell ? ' · Seite zeigt heute wenig visuell (+1)' : ''}${Number(r.firma.tourismus) === 1 ? ' · touristisch (+1)' : ''}`,
    // Eine Einschaetzung, keine Messung -- belegt nur, wenn die Seite visuell nachweislich schwach ist.
    status: schwachVisuell && eignung >= 7 ? 'VERIFIED' : 'UNVERIFIED',
  }];
}
