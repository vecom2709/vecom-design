/* ============================================================================
   Die Zeichenprüfung der Kette — hier, vor dem Push.

   WARUM ES DIESE DATEI GIBT

   kette.php prüft in Abschnitt 19, ob auf der Website typografisch richtige
   Zeichen stehen: kein gerades Hochkomma zwischen zwei Buchstaben, keine
   halbe deutsche Anführung, keine italienischen Wörter ohne Akzent. Die
   Kette läuft im Deploy VOR der Auslieferung. Reißt sie, wird nichts
   hochgeladen — auch nichts, was mit dem Fehler gar nichts zu tun hat.

   Am 16.09.2026 ist genau das passiert: "un'immagine" und "Don't" in neuen
   Texten, sechs Zeichen insgesamt, und der ganze Deploy stand. Gemerkt hat
   man es erst, als die Seite nach vier Minuten immer noch die alte war.

   Diese Datei ist dieselbe Prüfung in klein, ohne Datenbank und ohne PHP.
   Sie braucht zwei Sekunden und sagt genau, wo das Zeichen steht.

       node tools/zeichen.mjs

   Sie liegt in tools/ und geht damit nie auf den Webspace (siehe die
   Ausschlussliste in .github/workflows/ftp-deploy.yml).
   ============================================================================ */
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const WURZEL = join(dirname(fileURLToPath(import.meta.url)), '..');

/* Dieselbe Liste wie in kette.php, $netzDateien. Die drei italienischen
   Seitenquellen stehen zusätzlich drin: Sie sind Quelle für /de/ und /en/,
   ein Zeichen darin wandert also in alle drei Fassungen. */
const DATEIEN = [
  'index.html', 'showroom.html', 'tavolo.html', 'prezzi.html', 'assistenza.html',
  'assets/js/i18n-it.js', 'assets/js/i18n-de.js', 'assets/js/i18n-en.js',
  'assets/js/legal-it.js', 'assets/js/legal-de.js', 'assets/js/legal-en.js',
];

/* Wie in kette.php: geprüft wird der TEXT, nicht der Quelltext. Strukturierte
   Daten, Kommentarköpfe und HTML-Auszeichnung bleiben draußen — sonst meldet
   die Prüfung "meta" als fehlenden Akzent auf "metà" und wird nie wieder
   gelesen. */
const nurText = (roh) => roh
  .replace(/<script type="application\/ld\+json">[\s\S]*?<\/script>/g, '')
  .replace(/\/\*[\s\S]*?\*\//g, '')
  .replace(/<[^>]+>/g, ' ');

const AKZENTE = {
  perche: 'perché', piu: 'più', gia: 'già', puo: 'può', cosi: 'così',
  citta: 'città', qualita: 'qualità', attivita: 'attività', pero: 'però',
  novita: 'novità',
};

let treffer = 0;
const zeige = (datei, art, stelle) => {
  console.log(`  ${datei}\n      ${art}: …${stelle.replace(/\s+/g, ' ').trim()}…`);
  treffer++;
};

for (const d of DATEIEN) {
  let roh;
  try { roh = readFileSync(join(WURZEL, d), 'utf8'); }
  catch { console.log(`  ${d}: fehlt`); treffer++; continue; }
  const t = nurText(roh);

  for (const m of t.matchAll(/[A-Za-zÀ-ÿ]'[A-Za-zÀ-ÿ]/gu)) {
    zeige(d, 'gerades Hochkomma (richtig wäre ’)',
          t.slice(Math.max(0, m.index - 45), m.index + 45));
  }
  for (const m of t.matchAll(/„[^„“]{0,120}"/gu)) {
    zeige(d, 'deutsche Anführung unten, gerade oben', m[0].slice(0, 80));
  }
  if (d.includes('-it')) {
    for (const [falsch, richtig] of Object.entries(AKZENTE)) {
      const r = new RegExp(`(?<![\\p{L}])${falsch}(?![\\p{L}])`, 'u');
      const m = t.match(r);
      if (m) { zeige(d, `${falsch} → ${richtig}`, t.slice(Math.max(0, m.index - 45), m.index + 45)); }
    }
  }
}

if (treffer === 0) {
  console.log('Zeichen in Ordnung — die Kette wird hier nicht reissen.');
  process.exit(0);
}
console.log(`\n${treffer} Stelle(n). Die Kette wuerde den Deploy anhalten.`);
process.exit(1);
