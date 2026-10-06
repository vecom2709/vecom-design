/* ==========================================================================
   tools/wissen.mjs — das AI-Wissen aus PROJEKT.md (AI Office Stufe 2, V8, 07.10.2026).

   Claude soll beim Lesen der Verwaltung dasselbe wissen wie hier im
   Repository: warum etwas so gebaut ist, was entschieden wurde, welche Regel
   aus welchem Fehler kam. Die .md-Dateien gehen nie auf den Webspace (der
   Deploy schließt sie aus, die .htaccess sperrt sie). Deshalb entsteht beim
   Bauen eine JSON-Datei mit den Kapiteln — in app/data/, das für Besucher
   gesperrt ist — und die Schnittstelle liest nur die.

   Ein Kapitel ist ein Abschnitt unter „## “ oder „### “ (dann „Oben › Unten“
   als Titel). Gemessen am 07.10.2026: nur an „## “ geteilt, war das größte
   Kapitel 281.000 Zeichen lang — zu groß, um es Claude am Stück zu geben.
   In Codeblöcken wird nie geteilt.

   Die Datei liegt nicht im Repository (.gitignore): Sie entsteht bei jedem
   Deploy neu aus dem Stand, der hochgeladen wird — zwei Arbeitskopien, die
   beide PROJEKT.md ergänzen, sollen sich nicht auch noch um ein erzeugtes
   JSON streiten.

   Aufruf:  node tools/wissen.mjs [ziel.json]   (build.mjs ruft es mit auf)
   ========================================================================== */
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const QUELLEN = [
  ['PROJEKT.md', 'projekt', 'Was gebaut wurde und warum — Entscheidungen mit Datum'],
  ['CLAUDE.md', 'regeln', 'Wie in diesem Repository gearbeitet wird'],
  ['VECOM-STANDARD.md', 'standard', 'Der Standard für Kundenseiten'],
  ['AKQUISE.md', 'akquise', 'Wie die Akquise arbeitet'],
];

const MONATE = { januar: 1, februar: 2, 'märz': 3, april: 4, mai: 5, juni: 6, juli: 7, august: 8, september: 9, oktober: 10, november: 11, dezember: 12 };

/** Ein Datum aus der Überschrift: „(01.09.2026)“ oder „7. Oktober 2026“. */
function datum(titel) {
  let m = titel.match(/(\d{1,2})\.(\d{1,2})\.(20\d\d)/);
  if (m) { return `${m[3]}-${m[2].padStart(2, '0')}-${m[1].padStart(2, '0')}`; }
  m = titel.toLowerCase().match(/(\d{1,2})\.\s*(januar|februar|märz|april|mai|juni|juli|august|september|oktober|november|dezember)\s+(20\d\d)/);
  if (m) { return `${m[3]}-${String(MONATE[m[2]]).padStart(2, '0')}-${m[1].padStart(2, '0')}`; }
  return null;
}

export function zerlegen(text, quelle, kurz) {
  const zeilen = text.replace(/\r\n/g, '\n').split('\n');
  const kapitel = [];
  let titel = 'Überblick', oben = '', start = 1, puffer = [], imCode = false, protokollTag = '';
  const abschliessen = () => {
    const t = puffer.join('\n').trim();
    if (t !== '') {
      kapitel.push({ id: `${kurz}-${String(kapitel.length + 1).padStart(3, '0')}`, quelle, titel,
        datum: datum(titel) ?? (oben ? datum(oben) : null), zeile: start, text: t });
    }
  };
  zeilen.forEach((z, i) => {
    if (/^\s*(```|~~~)/.test(z)) { imCode = !imCode; }
    /* Das Entscheidungsprotokoll am Ende von PROJEKT.md ist eine lange Liste
       „- 28.09.2026 · …“ (gemessen 07.10.2026: 260.000 Zeichen unter einer
       Überschrift). Dort wird je Tag ein Kapitel daraus. */
    const tag = !imCode ? z.match(/^- (\d{2}\.\d{2}\.20\d\d) · /) : null;
    if (tag && tag[1] !== protokollTag) {
      abschliessen();
      protokollTag = tag[1];
      titel = 'Entscheidungsprotokoll ' + tag[1];
      start = i + 1;
      puffer = [];
    }
    if (!imCode && /^## (?!#)/.test(z)) {
      abschliessen();
      protokollTag = '';
      titel = oben = z.slice(3).trim();
      start = i + 1;
      puffer = [];
      return;
    }
    if (!imCode && /^### (?!#)/.test(z)) {
      abschliessen();
      protokollTag = '';
      titel = (oben ? oben + ' › ' : '') + z.slice(4).trim();
      start = i + 1;
      puffer = [];
      return;
    }
    if (!imCode && /^# (?!#)/.test(z) && kapitel.length === 0 && puffer.every((p) => p.trim() === '')) { return; }  // Dateititel
    puffer.push(z);
  });
  abschliessen();
  return kapitel;
}

export function wissenBauen(wurzel = '.', ziel = 'app/data/wissen.json') {
  const quellen = [];
  const kapitel = [];
  for (const [datei, kurz, wozu] of QUELLEN) {
    const pfad = resolve(wurzel, datei);
    if (!existsSync(pfad)) { continue; }
    const k = zerlegen(readFileSync(pfad, 'utf8'), datei, kurz);
    quellen.push({ datei, wozu, kapitel: k.length });
    kapitel.push(...k);
  }
  const daten = { stand: new Date().toISOString().slice(0, 19) + 'Z', quellen, kapitel };
  const json = JSON.stringify(daten);
  const zielPfad = resolve(wurzel, ziel);
  mkdirSync(dirname(zielPfad), { recursive: true });
  const alt = existsSync(zielPfad) ? readFileSync(zielPfad, 'utf8') : '';
  // Nur der Zeitstempel geändert? Dann nicht neu schreiben — sonst sähe jeder Bau wie eine Änderung aus.
  const ohneStand = (s) => s.replace(/^\{"stand":"[^"]*",/, '{');
  if (ohneStand(alt) !== ohneStand(json)) {
    writeFileSync(zielPfad, json);
    console.log(`geschrieben: ${ziel} (${kapitel.length} Kapitel aus ${quellen.length} Dateien, ${(json.length / 1024).toFixed(0)} KB)`);
  } else {
    console.log(`unveraendert: ${ziel}`);
  }
  return daten;
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const wurzel = resolve(dirname(fileURLToPath(import.meta.url)), '..');
  wissenBauen(wurzel, process.argv[2] ?? 'app/data/wissen.json');
}
