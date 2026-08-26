/* Nimmt einen Hochkant-Clip aus tiktok.html auf.
   Aufruf:  node tools/record-tiktok.mjs 1        (Clip-Nummer)
   Ergebnis: video/clip-<n>.webm  → mit ffmpeg zu .mp4                        */
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
const SPOT = process.argv[2] || '1';
const URL = process.argv[3] || 'http://localhost:8181/tiktok.html';
mkdirSync('video', { recursive: true });

const ctx = await (await chromium.launch()).newContext({
  viewport: { width: 720, height: 1280 },
  recordVideo: { dir: 'video', size: { width: 720, height: 1280 } },
});
const page = await ctx.newPage();
await page.goto(URL, { waitUntil: 'load' });
await page.waitForTimeout(900);
await page.evaluate((n) => {
  document.querySelector(`.spot[data-spot="${n}"]`).classList.add('on');
}, SPOT);

// Beats nacheinander: kurze, harte Schnitte halten die Aufmerksamkeit
const beats = await page.$$(`.spot[data-spot="${SPOT}"] .beat`);
for (let i = 0; i < beats.length; i++) {
  await beats[i].evaluate((el) => el.classList.add('on'));
  await page.waitForTimeout(i === beats.length - 1 ? 3400 : 1900);
}
await page.evaluate(() => document.querySelector('.brand').classList.add('on'));
await page.waitForTimeout(2200);
await ctx.close();
console.log('Clip', SPOT, 'fertig');
