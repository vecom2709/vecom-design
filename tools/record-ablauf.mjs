/* ==========================================================================
   tools/record-ablauf.mjs — nimmt explainer-ablauf.html als Video auf.

   Aufruf:  node tools/record-ablauf.mjs [basis-url] [sprache]
   Beispiel: node tools/record-ablauf.mjs http://localhost:8181 de
   Ergebnis: video/ablauf-<sprache>.webm  → danach mit ffmpeg zu .mp4

   Die Haltezeiten unten bestimmen das Tempo. Faustregel: rund 2,5 Sekunden
   je Textzeile, die gelesen werden soll — lieber zu langsam als zu hektisch.
   Wer beim ersten Ansehen mitkommt, sieht es nicht ein zweites Mal an, weil
   er etwas verpasst hat; wer nicht mitkommt, sieht es gar nicht zu Ende.

   Zwischen den Szenen liegt eine kurze Dunkelphase. Das ist ein Schnitt und
   kein Sprung: ohne sie wirken zwei aufeinanderfolgende Szenen wie eine, die
   ruckelt.
   ========================================================================== */
import { chromium } from 'playwright';
import { mkdirSync, renameSync, readdirSync, readFileSync, existsSync } from 'node:fs';

const BASIS   = process.argv[2] || 'http://localhost:8181';
const SPRACHE = (process.argv[3] || 'de').replace(/^--/, '');
const ZEITEN  = process.argv[4] || '';   // optional: Standzeiten aus dem Ton
const URL     = `${BASIS}/explainer-ablauf.html?lang=${SPRACHE}`;

/* Szene → Standzeit in Millisekunden. Reihenfolge wie in der Bühne. */
const HALT = [
  4800,   //  1 Titel
  7600,   //  2 Du schreibst mir
  8200,   //  3 Deine eigene Seite
  7800,   //  4 Immer nur eine Sache
  7600,   //  5 Angebot und Anzahlung
  7200,   //  6 Während ich baue
  8200,   //  7 Entwurf und Freigabe
  7800,   //  8 Online
  8000,   //  9 Betreuung
  7600,   // 10 Was du davon hast
  5200,   // 11 Abbinder
];
let SCHNITT = 650;   // Dunkelphase zwischen zwei Szenen

/* Gibt es eine Tonspur, richtet sich das Bild nach ihr und nicht umgekehrt.
   tools/ton-einbauen.mjs misst jede Aufnahme und schreibt die Standzeiten
   hierher — dann endet eine Szene, wenn der Satz zu Ende ist. */
if (ZEITEN && existsSync(ZEITEN)) {
  const z = JSON.parse(readFileSync(ZEITEN, 'utf8'));
  if (Array.isArray(z.halten) && z.halten.length) {
    HALT.length = 0;
    HALT.push(...z.halten);
    if (z.schnitt) { SCHNITT = z.schnitt; }
    console.log(`Standzeiten aus ${ZEITEN} uebernommen (${HALT.length} Szenen).`);
  }
}

mkdirSync('video', { recursive: true });

const browser = await chromium.launch();
const ctx = await browser.newContext({
  viewport: { width: 1280, height: 720 },
  recordVideo: { dir: 'video/roh', size: { width: 1280, height: 720 } },
  reducedMotion: 'no-preference',
  deviceScaleFactor: 1,
});
const page = await ctx.newPage();
await page.goto(URL, { waitUntil: 'load' });
await page.waitForTimeout(1500);          // Schriften und Bilder laden lassen

const anzahl = await page.evaluate(() => document.querySelectorAll('.sc').length);
if (anzahl !== HALT.length) {
  console.warn(`Achtung: ${anzahl} Szenen in der Bühne, aber ${HALT.length} Haltezeiten.`);
}

for (let i = 1; i <= Math.min(anzahl, HALT.length); i++) {
  await page.evaluate((n) => {
    document.querySelectorAll('.sc').forEach((s) => s.classList.remove('on'));
    const sc = document.querySelector(`.sc[data-sc="${n}"]`);
    if (sc) { sc.classList.add('on'); }
  }, i);
  await page.waitForTimeout(HALT[i - 1]);
  await page.evaluate(() => document.querySelectorAll('.sc').forEach((s) => s.classList.remove('on')));
  await page.waitForTimeout(SCHNITT);
}

await ctx.close();
await browser.close();

/* Playwright vergibt einen zufälligen Dateinamen — der wird umbenannt, damit
   die Sprachfassungen auseinanderzuhalten sind. */
const roh = readdirSync('video/roh').filter((f) => f.endsWith('.webm'));
if (roh.length) {
  renameSync('video/roh/' + roh[roh.length - 1], `video/ablauf-${SPRACHE}.webm`);
  console.log(`Fertig (${SPRACHE}) — video/ablauf-${SPRACHE}.webm`);
} else {
  console.error('Keine Aufnahme gefunden.');
  process.exit(1);
}
