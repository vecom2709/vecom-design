/* ==========================================================================
   tools/sprechertext.mjs — gibt den Sprechertext je Szene aus, eine Zeile
   je Szene, in der gewuenschten Sprache.

   Aufruf: node tools/sprechertext.mjs de

   WARUM DER TEXT NICHT EIGENS GESCHRIEBEN IST
   Er entsteht aus genau den Saetzen, die im Video zu sehen sind
   (assets/js/i18n-data.js, Abschnitt "abl"). Das hat zwei Gruende: Die Saetze
   sind von Muttersprachlern gelesen und geprueft — ein zweiter, eigener
   Sprechertext waere ein zweiter Ort, an dem dieselbe Aussage veralten kann.
   Und was man hoert, deckt sich mit dem, was man liest; die Stichpunkte
   daneben bleiben dem Auge vorbehalten, sonst klaenge es wie Vorlesen.
   ========================================================================== */
import { readFileSync } from 'node:fs';

const SPRACHE = (process.argv[2] || 'de').replace(/^--/, '');
global.window = {};
await import('../assets/js/i18n-data.js');
const d = (global.window.VECOM_I18N || {})[SPRACHE];
if (!d || !d.abl) {
  console.error(`Keine Sprachdaten fuer "${SPRACHE}".`);
  process.exit(1);
}
const a = d.abl;

/* Szene → welche Bausteine gesprochen werden. Zwei Zeilen werden zu einem
   Absatz; die Stimme macht daraus von selbst eine kurze Pause. */
const SZENEN = [
  ['s1a', 's1b'],
  ['s2a', 's2b'],
  ['s3a', 's3b'],
  ['s4a', 's4b'],
  ['s5a', 's5b'],
  ['s6a', 's6b'],
  ['s7a', 's7b'],
  ['s8a', 's8b'],
  ['s9a', 's9b'],
  ['s10a'],
  ['s11b'],
];

for (const teile of SZENEN) {
  /* Die Ueberschrift endet oft ohne Satzzeichen ("Wie es fuer dich ablaeuft").
     Ohne Punkt liest eine Stimme sie in den naechsten Satz hinein, und aus
     zwei Aussagen wird ein Wortschwall. Also einen setzen, wo keiner ist. */
  const text = teile
    .map((k) => (a[k] || '').trim())
    .filter(Boolean)
    .map((t) => (/[.!?:…]$/.test(t) ? t : t + '.'))
    .join(' ');
  // Eine Zeile je Szene — das Aufnahmeskript liest sie mit mapfile ein.
  console.log(text.replace(/\s+/g, ' '));
}
