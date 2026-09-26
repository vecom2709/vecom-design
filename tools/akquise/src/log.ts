/* Protokoll: jede Zeile auf die Konsole und als JSON-Zeile in
   daten/logs/JJJJ-MM-TT.jsonl. Die Verwaltung fuehrt ihr eigenes Protokoll
   je Firma; dieses hier sagt, was der Worker selbst getan hat -- auch das,
   was nie bei der Verwaltung ankam. */
import { appendFileSync } from 'node:fs';
import { join } from 'node:path';
import { datenOrdner } from './konfig.js';

type Stufe = 'info' | 'warn' | 'fehler';

function schreiben(stufe: Stufe, schritt: string, text: string, meta?: unknown): void {
  const zeit = new Date();
  const zeile = { zeit: zeit.toISOString(), stufe, schritt, text, ...(meta !== undefined ? { meta } : {}) };
  const farbe = stufe === 'fehler' ? '\x1b[31m' : stufe === 'warn' ? '\x1b[33m' : '\x1b[36m';
  console.log(`${farbe}${zeit.toLocaleTimeString('de-DE')} ${schritt}\x1b[0m ${text}`);
  try {
    appendFileSync(join(datenOrdner('logs'), zeit.toISOString().slice(0, 10) + '.jsonl'), JSON.stringify(zeile) + '\n');
  } catch { /* Protokoll ist Beiwerk */ }
}

export const log = {
  info: (schritt: string, text: string, meta?: unknown) => schreiben('info', schritt, text, meta),
  warn: (schritt: string, text: string, meta?: unknown) => schreiben('warn', schritt, text, meta),
  fehler: (schritt: string, text: string, meta?: unknown) => schreiben('fehler', schritt, text, meta),
};
