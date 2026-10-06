// Sicherung außer Haus: jede Nacht abholen (AI Office Stufe 0, 06.10.2026, Uwe: „Auf Ihren PC“).
// Aufruf: node holen.mjs   — Windows-Aufgabe „VECOM Sicherung holen“, täglich 03:30.
//
// Erster Lauf ohne Schlüssel: legt das Schlüsselpaar an und hört auf. Den Inhalt von
// oeffentlich.pem trägt Uwe in der Verwaltung ein (Einstellungen → Überwachung →
// Sicherung außer Haus). Danach holt jeder Lauf, was neu ist:
//   auszuege\vecom-JJJJ-MM-TT.sql.gz.vcs   Datenbank, verschlüsselt (die letzten 60 bleiben)
//   dateien\<name>.vcs                      Kundendateien, verschlüsselt
// Jede Datei wird nach dem Laden einmal testweise entschlüsselt — eine Sicherung, die
// sich nicht öffnen lässt, ist keine.
import fs from 'node:fs';
import path from 'node:path';
import { Readable } from 'node:stream';
import { pipeline } from 'node:stream/promises';
import { ORDNER, OEFFENTLICH, protokoll, schluesselSicherstellen, fingerabdruck, anfrage, entschluesseln } from './vcs.mjs';

const AUFHEBEN = 60;

async function laden(aktion, name, ziel) {
  const teil = ziel + '.teil';
  const antwort = await anfrage(aktion, name);
  await pipeline(Readable.fromWeb(antwort.body), fs.createWriteStream(teil));
  entschluesseln(teil, null);           // prüfen, ohne zu entpacken
  fs.renameSync(teil, ziel);
}

async function main() {
  if (schluesselSicherstellen()) {
    protokoll('Neues Schlüsselpaar angelegt. Fingerabdruck ' + fingerabdruck(fs.readFileSync(OEFFENTLICH)));
    protokoll('Jetzt den Inhalt von ' + OEFFENTLICH + ' in der Verwaltung eintragen (Einstellungen → Überwachung → Sicherung außer Haus).');
    return;
  }
  const auszuege = path.join(ORDNER, 'auszuege');
  const dateien = path.join(ORDNER, 'dateien');
  fs.mkdirSync(auszuege, { recursive: true });
  fs.mkdirSync(dateien, { recursive: true });
  const verzeichnis = path.join(ORDNER, 'dateien.json');
  const bekannt = fs.existsSync(verzeichnis) ? JSON.parse(fs.readFileSync(verzeichnis, 'utf8')) : {};

  const liste = await (await anfrage('liste')).json();
  let neuA = 0, neuD = 0, fehler = 0;
  for (const a of liste.auszuege ?? []) {
    const ziel = path.join(auszuege, a.name + '.vcs');
    // Gleicher Name, andere Größe: Der Server hat den Auszug des Tages neu geschrieben — noch einmal holen.
    if (fs.existsSync(ziel) && bekannt['auszug:' + a.name] === `${a.bytes}:${a.zeit}`) { continue; }
    try { await laden('holen', a.name, ziel); bekannt['auszug:' + a.name] = `${a.bytes}:${a.zeit}`; neuA++; } catch (e) { fehler++; protokoll('Fehler bei ' + a.name + ': ' + e.message); }
  }
  for (const d of liste.dateien ?? []) {
    const stand = `${d.bytes}:${d.zeit}`;
    const ziel = path.join(dateien, ...String(d.name).split('/')) + '.vcs';
    if (!path.resolve(ziel).startsWith(path.resolve(dateien) + path.sep)) { continue; }   // nie außerhalb des Ordners
    fs.mkdirSync(path.dirname(ziel), { recursive: true });
    if (bekannt[d.name] === stand && fs.existsSync(ziel)) { continue; }
    try { await laden('datei', d.name, ziel); bekannt[d.name] = stand; neuD++; } catch (e) { fehler++; protokoll('Fehler bei Datei ' + d.name + ': ' + e.message); }
  }
  fs.writeFileSync(verzeichnis, JSON.stringify(bekannt));

  const alle = fs.readdirSync(auszuege).filter((n) => n.endsWith('.sql.gz.vcs')).sort();
  for (const alt of alle.slice(0, Math.max(0, alle.length - AUFHEBEN))) { fs.unlinkSync(path.join(auszuege, alt)); }

  protokoll(`Abgeholt: ${neuA} Auszüge, ${neuD} Dateien, ${fehler} Fehler. Vorrat: ${Math.min(alle.length, AUFHEBEN)} Auszüge.`);
  if (fehler > 0) { process.exitCode = 1; }
}

main().catch((e) => { protokoll('Abbruch: ' + e.message); process.exitCode = 2; });
