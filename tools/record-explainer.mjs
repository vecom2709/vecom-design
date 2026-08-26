/* ==========================================================================
   tools/record-explainer.mjs — nimmt explainer.html als Video auf.

   Aufruf:  node tools/record-explainer.mjs [url]
   Ergebnis: video/erklaervideo.webm  → danach mit ffmpeg zu .mp4

   Die Haltezeiten unten bestimmen das Tempo. Faustregel: rund 2,5 Sekunden
   pro Textzeile, die gelesen werden soll — lieber zu langsam als zu hektisch.
   ========================================================================== */
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';

const LANG = (process.argv[3] || process.argv[2] || 'de').replace(/^--/, '');
const BASE = (process.argv[2] && process.argv[2].startsWith('http'))
  ? process.argv[2] : 'http://localhost:8181/explainer.html';
const URL = `${BASE}?lang=${LANG}`;
mkdirSync('video', { recursive: true });

// Szene → Standzeit in Millisekunden
const HOLD = [4200, 6200, 7000, 6800, 6200, 6600, 6600, 5600, 5200];

const ctx = await (await chromium.launch()).newContext({
  viewport: { width: 1280, height: 720 },
  recordVideo: { dir: 'video', size: { width: 1280, height: 720 } },
  reducedMotion: 'no-preference',
});
const page = await ctx.newPage();
await page.goto(URL, { waitUntil: 'load' });
await page.waitForTimeout(1200);          // Schriften laden lassen

for (let i = 1; i <= HOLD.length; i++) {
  await page.evaluate((n) => {
    document.querySelectorAll('.sc').forEach((s) => s.classList.remove('on'));
    document.querySelector(`.sc[data-sc="${n}"]`).classList.add('on');
  }, i);
  await page.waitForTimeout(HOLD[i - 1]);
  // Kurze Dunkelphase zwischen den Szenen — ein Schnitt, kein Sprung
  await page.evaluate(() => document.querySelectorAll('.sc').forEach((s) => s.classList.remove('on')));
  await page.waitForTimeout(650);
}

await ctx.close();
console.log(`Fertig (${LANG}) — Video liegt im Ordner video`);
