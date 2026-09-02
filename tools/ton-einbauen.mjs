/* ==========================================================================
   tools/ton-einbauen.mjs — legt die Sprachaufnahmen unter das Video.

   Aufruf: node tools/ton-einbauen.mjs de
   Voraussetzung: video/ton/<sprache>/szene-01.mp3 … (aus tools/ton-kie.sh)
                  ffmpeg und ffprobe im Pfad

   WARUM DAS VIDEO DANACH NEU AUFGENOMMEN WIRD
   Ein Sprechertext laesst sich nicht unter ein fertiges Bild schieben und
   hoffen, dass es passt. Umgekehrt wird es richtig: Erst steht der Ton, dann
   richtet sich das Bild danach. Das Skript misst jede Aufnahme, leitet daraus
   die Standzeit jeder Szene ab, nimmt das Video mit diesen Zeiten neu auf und
   legt den Ton darunter. So endet jede Szene, wenn der Satz zu Ende ist —
   und nicht zwei Sekunden davor oder danach.
   ========================================================================== */
import { existsSync, readdirSync, writeFileSync, mkdtempSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const SPRACHE = (process.argv[2] || 'de').replace(/^--/, '');
const ORDNER  = `video/ton/${SPRACHE}`;
const BASIS   = process.argv[3] || 'http://localhost:8181';

/* Luft vor und nach dem gesprochenen Satz. Ohne die klingt jede Szene, als
   wuerde sie abgeschnitten. */
const VORLAUF   = 0.55;
const NACHLAUF  = 1.10;
const SCHNITT   = 0.65;   // Dunkelphase zwischen zwei Szenen, wie im Recorder
const MINDEST   = 3.4;    // kuerzer als das liest niemand die Stichpunkte mit

if (!existsSync(ORDNER)) {
  console.error(`${ORDNER} gibt es nicht. Zuerst: bash tools/ton-kie.sh ${SPRACHE}`);
  process.exit(1);
}
const stuecke = readdirSync(ORDNER).filter((f) => /^szene-\d+\.mp3$/.test(f)).sort();
if (!stuecke.length) { console.error(`Keine Aufnahmen in ${ORDNER}.`); process.exit(1); }

const dauer = (pfad) => parseFloat(execFileSync('ffprobe', [
  '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', pfad]).toString().trim());

const laengen = stuecke.map((f) => dauer(join(ORDNER, f)));
const halten  = laengen.map((l) => Math.max(MINDEST, l + VORLAUF + NACHLAUF));

console.log(`${SPRACHE}: ${stuecke.length} Aufnahmen`);
stuecke.forEach((f, i) => console.log(`  ${f}  ${laengen[i].toFixed(1)}s gesprochen → ${halten[i].toFixed(1)}s Standzeit`));
const gesamt = halten.reduce((a, b) => a + b, 0) + halten.length * SCHNITT;
console.log(`Gesamtlaenge: ${gesamt.toFixed(1)}s`);

/* 1. Die Standzeiten fuer den Recorder ablegen. */
writeFileSync(`${ORDNER}/zeiten.json`,
  JSON.stringify({ sprache: SPRACHE, halten: halten.map((h) => Math.round(h * 1000)), schnitt: SCHNITT * 1000 }, null, 2));
console.log(`\nStandzeiten in ${ORDNER}/zeiten.json`);

/* 2. Video mit genau diesen Zeiten neu aufnehmen. */
console.log('Nehme das Video mit den neuen Zeiten auf …');
execFileSync('node', ['tools/record-ablauf.mjs', BASIS, SPRACHE, `${ORDNER}/zeiten.json`],
  { stdio: 'inherit', env: { ...process.env, PLAYWRIGHT_BROWSERS_PATH: process.env.PLAYWRIGHT_BROWSERS_PATH || '/opt/pw-browsers' } });

/* 3. Die Tonspur zusammensetzen: jede Aufnahme an den Anfang ihrer Szene,
      dazwischen Stille. */
const tmp = mkdtempSync(join(tmpdir(), 'vecomton-'));
let start = 0;
const eingaenge = [];
const filter = [];
stuecke.forEach((f, i) => {
  eingaenge.push('-i', join(ORDNER, f));
  const versatz = Math.round((start + VORLAUF) * 1000);
  filter.push(`[${i}:a]adelay=${versatz}|${versatz}[a${i}]`);
  start += halten[i] + SCHNITT;
});
filter.push(`${stuecke.map((_, i) => `[a${i}]`).join('')}amix=inputs=${stuecke.length}:normalize=0[aus]`);
const spur = join(tmp, 'stimme.m4a');
execFileSync('ffmpeg', ['-v', 'error', '-y', ...eingaenge,
  '-filter_complex', filter.join(';'), '-map', '[aus]', '-c:a', 'aac', '-b:a', '128k', spur]);

/* 4. Ton unter das Bild legen. */
const roh = `video/ablauf-${SPRACHE}.webm`;
const ziel = `video/ablauf-${SPRACHE}.mp4`;
execFileSync('ffmpeg', ['-v', 'error', '-y', '-i', roh, '-i', spur,
  '-c:v', 'libx264', '-preset', 'slow', '-crf', '23', '-pix_fmt', 'yuv420p',
  '-movflags', '+faststart', '-r', '30', '-c:a', 'aac', '-shortest', ziel], { stdio: 'inherit' });

console.log(`\nFertig: ${ziel} — mit Ton.`);
console.log('Danach nicht vergessen: die Laufzeit in den Sprachdaten unter video.d1');
console.log('anpassen und "ohne Ton" dort streichen.');
