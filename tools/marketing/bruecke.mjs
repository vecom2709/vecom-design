/* ==========================================================================
   bruecke.mjs — Marketing-Studio: Claude ↔ Verwaltung (01.10.2026).

   Recherche und Texte schreibt Claude über Uwes Claude-Abo, nicht der Server.
   Diese Brücke läuft auf Uwes Rechner und spricht mit der Worker-Tür
   (akquise.php) — mit dem Schlüssel aus tools/akquise/.env. Der Schlüssel
   verlässt den Rechner nie und wird nie ausgegeben.

     node bruecke.mjs daten [branche] [land]      Zahlen je Branche (JSON, ohne Personen)
     node bruecke.mjs zielgruppe datei.json       Profil(e) als ENTWURF abliefern
     node bruecke.mjs recherche datei.json        Funde abliefern (je 50)

   Freigeben kann die Brücke nichts — das geht nur in der Verwaltung.
   Keine Abhängigkeiten: Node 18+ bringt fetch mit.
   ========================================================================== */
import { readFileSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const hier = dirname(fileURLToPath(import.meta.url));
const envDatei = join(hier, '..', 'akquise', '.env');
const env = {};
if (existsSync(envDatei)) {
  for (const zeile of readFileSync(envDatei, 'utf8').split(/\r?\n/)) {
    const m = zeile.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
    if (m) env[m[1]] = m[2].replace(/^["']|["']$/g, '');
  }
}
const url = process.env.AKQUISE_URL || env.AKQUISE_URL || 'https://vecom-design.it/akquise.php';
const schluessel = process.env.AKQUISE_SCHLUESSEL || env.AKQUISE_SCHLUESSEL || '';
if (!schluessel) { console.error('Kein AKQUISE_SCHLUESSEL in tools/akquise/.env — die Brücke kann nicht sprechen.'); process.exit(2); }

async function tuer(aktion, rumpf = {}) {
  const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Vecom-Akquise': schluessel }, body: JSON.stringify({ aktion, ...rumpf }) });
  let j = null;
  try { j = await r.json(); } catch { j = { ok: false, hinweis: 'Antwort war kein JSON (HTTP ' + r.status + ')' }; }
  return j;
}

const lesen = (datei) => {
  const inhalt = JSON.parse(readFileSync(datei, 'utf8'));
  return Array.isArray(inhalt) ? inhalt : [inhalt];
};

const [, , befehl, a1, a2] = process.argv;
if (befehl === 'daten') {
  const j = await tuer('marketing_daten', { ...(a1 ? { branche: a1 } : {}), ...(a2 ? { land: a2 } : {}) });
  process.stdout.write(JSON.stringify(j, null, 1) + '\n');
  process.exit(j && j.ok ? 0 : 1);
} else if (befehl === 'zielgruppe' && a1) {
  let fehler = 0;
  for (const z of lesen(a1)) {
    const j = await tuer('marketing_zielgruppe', { zielgruppe: z });
    console.log((j.ok ? 'OK   ' : 'FEHL ') + (z.branche || '?') + '/' + (z.land || '?') + (j.ok ? ' → Entwurf #' + j.id : ' — ' + (j.hinweis || 'unbekannt')));
    if (!j.ok) fehler++;
  }
  process.exit(fehler ? 1 : 0);
} else if (befehl === 'recherche' && a1) {
  const funde = lesen(a1);
  let neu = 0, doppelt = 0; const fehler = [];
  for (let i = 0; i < funde.length; i += 50) {
    const j = await tuer('marketing_recherche', { funde: funde.slice(i, i + 50) });
    if (!j.ok) { console.log('FEHL — ' + (j.hinweis || 'unbekannt')); process.exit(1); }
    neu += j.neu; doppelt += j.doppelt; fehler.push(...(j.fehler || []));
  }
  console.log(`OK   ${neu} neu, ${doppelt} schon vorhanden` + (fehler.length ? `, übersprungen: ${fehler.join('; ')}` : ''));
  process.exit(0);
} else {
  console.log('node bruecke.mjs daten [branche] [land] | zielgruppe datei.json | recherche datei.json');
  process.exit(2);
}
