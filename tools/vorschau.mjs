/* ==========================================================================
   tools/vorschau.mjs — örtliche Vorschau ohne PHP.

   Auf dem Windows-Rechner gibt es kein PHP (siehe CLAUDE.md), und für die
   statischen Seiten braucht es auch keins. Dieser Server liefert den
   Arbeitsordner so aus, wie All-Inkl es täte, soweit es für eine Vorschau
   zählt: richtige Inhaltstypen (.glb, .webp, .mjs), Ordner -> index.html,
   kein Zwischenspeicher (damit jede Änderung sofort sichtbar ist).

   Was er NICHT kann: PHP. bedarf.php, formular.php und die Verwaltung
   laufen hier nicht -- Knöpfe dorthin führen in der Vorschau ins Leere.
   Das ist Absicht und kein Fehler der Seite.

   Aufruf:  node tools/vorschau.mjs [port]      (Standard 8090)
   Danach:  http://127.0.0.1:8090/esperienza.html
            http://127.0.0.1:8090/de/erlebnis.html
   Vorher:  node build.mjs   (baut /de/ und /en/)

   Nur an 127.0.0.1 gebunden: Die Vorschau ist für diesen Rechner, nicht
   fürs Netz.
   ========================================================================== */
import { createServer } from 'node:http';
import { readFile, stat } from 'node:fs/promises';
import { extname, join, normalize, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

// fileURLToPath statt .pathname: Der Ordner heisst "Vecom Design", und in
// einer Adresse wird aus dem Leerzeichen %20 -- dann faende der Server nichts.
const WURZEL = resolve(fileURLToPath(new URL('..', import.meta.url)));
const PORT = Number(process.argv[2] || 8090);
const ARTEN = {
  '.html': 'text/html; charset=utf-8', '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8', '.mjs': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8', '.xml': 'application/xml; charset=utf-8',
  '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.webp': 'image/webp',
  '.avif': 'image/avif', '.ico': 'image/x-icon', '.glb': 'model/gltf-binary',
  '.mp4': 'video/mp4', '.webm': 'video/webm', '.woff2': 'font/woff2', '.txt': 'text/plain; charset=utf-8',
};
/* Was nie ausgeliefert wird, auch nicht örtlich: Zugangsdaten und Git. */
const GESPERRT = /(^|[\\/])(\.git|config\.local\.php|app[\\/]config\.local\.php|app[\\/]sicherungen|app[\\/]notfall)([\\/]|$)/i;

createServer(async (anf, ant) => {
  try {
    const pfad = decodeURIComponent(new URL(anf.url, 'http://x').pathname);
    let datei = normalize(join(WURZEL, pfad));
    if (!datei.startsWith(WURZEL) || GESPERRT.test(datei.slice(WURZEL.length))) { ant.writeHead(403).end('gesperrt'); return; }
    let info = await stat(datei).catch(() => null);
    if (info && info.isDirectory()) { datei = join(datei, 'index.html'); info = await stat(datei).catch(() => null); }
    if (!info) { ant.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' }).end('nicht gefunden: ' + pfad); return; }
    if (extname(datei) === '.php') {
      ant.writeHead(501, { 'Content-Type': 'text/plain; charset=utf-8' }).end('PHP läuft in der örtlichen Vorschau nicht. Auf der echten Seite führt dieser Knopf weiter.');
      return;
    }
    const inhalt = await readFile(datei);
    ant.writeHead(200, { 'Content-Type': ARTEN[extname(datei).toLowerCase()] || 'application/octet-stream', 'Cache-Control': 'no-store' });
    ant.end(inhalt);
  } catch (f) {
    ant.writeHead(500).end(String(f));
  }
}).listen(PORT, '127.0.0.1', () => console.log(`Vorschau: http://127.0.0.1:${PORT}/  (Ordner ${WURZEL})`));
