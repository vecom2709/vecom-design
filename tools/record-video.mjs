/* ==========================================================================
   tools/record-video.mjs — nimmt aus der echten Website ein Erklärvideo auf.

   Kein KI-Video, keine Stockbilder: Was zu sehen ist, ist die laufende Seite.
   Über die Aufnahme werden Titelkarten eingeblendet, dazwischen wird gescrollt
   und geklickt wie bei einem echten Besuch.

   Aufruf:
     node tools/record-video.mjs                 (nimmt die Live-Seite auf)
     node tools/record-video.mjs http://localhost:8181/de/
     node tools/record-video.mjs <url> --3d      (mit 3D-Bühne, braucht eine GPU)

   Ergebnis: video/webseite-erstellen.webm  →  danach mit ffmpeg zu .mp4
   ========================================================================== */
import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';

const URL = process.argv[2] || 'https://vecom-design.it/de/';
const WITH_3D = process.argv.includes('--3d');
const OUT = 'video';
mkdirSync(OUT, { recursive: true });

/* Das Drehbuch: Titelkarten und was dazwischen zu sehen ist. */
const SCRIPT = [
  { card: ['Wie deine Website entsteht', 'In vier Schritten — vom ersten Gespräch bis zum Launch'], hold: 3200 },
  { scroll: '.hero', pan: 900, hold: 2600, label: 'vecom-design.it' },

  { card: ['01 — Gespräch', 'Eine Stunde. Was verkaufst du, an wen, und was fehlt heute?'], hold: 3000 },
  { scroll: '#contact', hold: 1200 },
  { form: true },

  { card: ['02 — Richtung', 'Du siehst den ersten Bildschirm, bevor der Rest gebaut wird.'], hold: 3000 },
  { scroll: '#services', pan: 1400, hold: 2400 },

  { card: ['03 — Umsetzung', 'Sektion für Sektion. Du siehst die Seite wachsen, nicht erst am Ende eine Datei.'], hold: 3200 },
  { scroll: '#work', pan: 1800, hold: 3400 },

  { card: ['04 — Launch & Wachstum', 'Domain, E-Mail, Rechtstexte — und danach bleiben wir erreichbar.'], hold: 3200 },
  { scroll: '#plans', pan: 1200, hold: 2600 },

  { card: ['Ab 499 € einmalig', 'Angebot und Erstgespräch sind kostenlos.'], hold: 2800 },
  { card: ['vecom-design.it', 'kontakt@vecom-design.it'], hold: 3000, brand: true },
];

const overlayCSS = `
  #vcard {
    position: fixed; inset: 0; z-index: 99999; display: grid; place-content: center;
    text-align: center; padding: 8vw; gap: 1.2rem;
    background: radial-gradient(80% 70% at 50% 45%, rgba(6,10,20,.92), rgba(2,4,9,.98));
    backdrop-filter: blur(6px);
    opacity: 0; transition: opacity 700ms cubic-bezier(.16,1,.3,1);
    font-family: 'Archivo', system-ui, sans-serif; color: #e9eef8;
  }
  #vcard.on { opacity: 1; }
  #vcard:not(.on) { pointer-events: none; }   /* sonst fängt die Karte alle Klicks ab */
  #vcard h1 { font-size: clamp(2rem,4.6vw,4rem); font-stretch: 112%; font-weight: 700;
    letter-spacing: -.03em; margin: 0; line-height: 1.05; }
  #vcard h1 em { font-style: normal;
    background: linear-gradient(100deg,#cfe0ff,#fff 35%,#79c8ff 70%,#1fe8ff);
    -webkit-background-clip: text; background-clip: text; color: transparent; }
  #vcard p { font-family: 'Inter', system-ui, sans-serif; font-size: clamp(1rem,1.5vw,1.35rem);
    color: #97a5bf; margin: 0; max-width: 46ch; margin-inline: auto; }
  #vlabel { position: fixed; left: 3vw; bottom: 3vw; z-index: 99998;
    font-family: 'Inter', system-ui, sans-serif; font-size: 14px; letter-spacing: .22em;
    text-transform: uppercase; color: rgba(150,180,230,.75); opacity: 0; transition: opacity 500ms; }
  #vlabel.on { opacity: 1; }
`;

const ctx = await (await chromium.launch()).newContext({
  viewport: { width: 1280, height: 720 },
  recordVideo: { dir: OUT, size: { width: 1280, height: 720 } },
  locale: 'de-DE',
  reducedMotion: WITH_3D ? 'no-preference' : 'reduce',
});
const page = await ctx.newPage();
await page.goto(URL, { waitUntil: 'load' });
await page.waitForTimeout(WITH_3D ? 6000 : 2500);

await page.addStyleTag({ content: overlayCSS });
await page.evaluate((with3d) => {
  if (!with3d) { const c = document.querySelector('canvas.stage'); if (c) c.remove(); }
  document.body.insertAdjacentHTML('beforeend',
    '<div id="vcard"><h1></h1><p></p></div><div id="vlabel"></div>');
}, WITH_3D);

const card = async (lines, hold, brand) => {
  await page.evaluate(([t, s, brand]) => {
    const c = document.getElementById('vcard');
    c.querySelector('h1').innerHTML = brand ? `<em>${t}</em>` : t;
    c.querySelector('p').textContent = s;
    c.classList.add('on');
  }, [lines[0], lines[1], !!brand]);
  await page.waitForTimeout(hold);
  await page.evaluate(() => document.getElementById('vcard').classList.remove('on'));
  await page.waitForTimeout(800);
};

const label = async (text) => {
  await page.evaluate((t) => {
    const l = document.getElementById('vlabel');
    l.textContent = t; l.classList.add('on');
    setTimeout(() => l.classList.remove('on'), 3000);
  }, text);
};

/* Langsames, gleichmäßiges Scrollen — ruckartige Sprünge sehen im Video billig aus. */
const panTo = async (sel, extra = 0) => {
  await page.evaluate(([s, e]) => {
    const el = document.querySelector(s);
    const y = el.getBoundingClientRect().top + window.scrollY - 90 + e;
    window.scrollTo({ top: y, behavior: 'smooth' });
  }, [sel, extra]);
  await page.waitForTimeout(1400);
};

for (const beat of SCRIPT) {
  if (beat.card) { await card(beat.card, beat.hold, beat.brand); continue; }
  if (beat.scroll) {
    await panTo(beat.scroll);
    if (beat.label) await label(beat.label);
    if (beat.pan) { await panTo(beat.scroll, beat.pan); }
    await page.waitForTimeout(beat.hold || 1500);
  }
  if (beat.form) {
    // Formular vorführen: klicken statt tippen — genau das ist die Botschaft
    await page.waitForTimeout(300);
    const chips = await page.$$('.qform__step[data-step="1"] .chip-opt');
    for (const i of [0, 7, 12]) { if (chips[i]) { await chips[i].click(); await page.waitForTimeout(700); } }
    await page.click('.qform__next').catch(() => {});
    await page.waitForTimeout(1600);
    const chips2 = await page.$$('.qform__step[data-step="2"] .chip-opt');
    for (const i of [1, 6]) { if (chips2[i]) { await chips2[i].click(); await page.waitForTimeout(650); } }
    await page.click('.qform__next').catch(() => {});
    await page.waitForTimeout(1800);
  }
}

await ctx.close();
console.log('Fertig — Video liegt im Ordner', OUT);
