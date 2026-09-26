/* ==========================================================================
   Einrichten und Uebernehmen -- zwei Befehle, die man einmal braucht.

   npm run verbinden
     Traegt den Worker-Schluessel aus der Zwischenablage in .env ein und
     prueft die Verbindung. Der Schluessel wird dabei nie angezeigt und nach
     dem Eintragen aus der Zwischenablage geloescht: Er soll weder im
     Terminalverlauf noch in einem Protokoll noch in einem Chat landen.
     Ablauf fuer Uwe: Verwaltung → Neue Kunden finden → Regeln & Versand →
     „Schluessel erzeugen" → „Kopieren" → hier `npm run verbinden`.

   npm run import -- daten/lead-scout.json
     Meldet eine Liste von Firmen an die Verwaltung (wie die Recherche).
     Gebaut fuer die Uebernahme des alten Lead-Scouts; die Datei liegt in
     daten/ und kommt nie ins Repository -- es stehen Namen darin.
   ========================================================================== */
import { execFileSync } from 'node:child_process';
import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { api } from './api.js';
import { konfig, WURZEL } from './konfig.js';
import { log } from './log.js';

function zwischenablage(): string {
  if (process.platform === 'win32') {
    return execFileSync('powershell.exe', ['-NoProfile', '-Command', 'Get-Clipboard -Raw'], { encoding: 'utf8' });
  }
  if (process.platform === 'darwin') return execFileSync('pbpaste', { encoding: 'utf8' });
  return execFileSync('xclip', ['-o', '-selection', 'clipboard'], { encoding: 'utf8' });
}

function zwischenablageLeeren(): void {
  try {
    if (process.platform === 'win32') execFileSync('powershell.exe', ['-NoProfile', '-Command', "Set-Clipboard -Value ' '"]);
    else if (process.platform === 'darwin') execFileSync('pbcopy', { input: '' });
  } catch { /* nicht schlimm */ }
}

/** Setzt oder ersetzt eine Zeile KEY=wert in .env, ohne den Rest anzufassen. */
function envSetzen(schluessel: string, wert: string): void {
  const datei = join(WURZEL, '.env');
  let inhalt = existsSync(datei) ? readFileSync(datei, 'utf8')
    : existsSync(join(WURZEL, '.env.beispiel')) ? readFileSync(join(WURZEL, '.env.beispiel'), 'utf8') : '';
  const zeile = `${schluessel}=${wert}`;
  const muster = new RegExp(`^\\s*${schluessel}\\s*=.*$`, 'm');
  inhalt = muster.test(inhalt) ? inhalt.replace(muster, zeile) : inhalt.replace(/\s*$/, '\n') + zeile + '\n';
  writeFileSync(datei, inhalt);
}

export async function verbinden(): Promise<void> {
  const roh = zwischenablage().trim();
  if (!/^[a-f0-9]{48}$/.test(roh)) {
    console.log('In der Zwischenablage liegt kein Worker-Schlüssel.\n'
      + 'Verwaltung → Neue Kunden finden → Regeln & Versand → „Schlüssel erzeugen“ → „Kopieren“ — dann noch einmal `npm run verbinden`.');
    process.exitCode = 1;
    return;
  }
  envSetzen('AKQUISE_SCHLUESSEL', roh);
  zwischenablageLeeren();
  konfig.schluessel = roh;
  try {
    const h = await api('hallo');
    log.info('verbinden', `Verbunden mit ${konfig.url} — Schlüssel in .env eingetragen (Zwischenablage geleert).`
      + (h.stop ? ' Achtung: Die Notbremse ist gezogen.' : ''));
  } catch (e) {
    log.fehler('verbinden', `Schlüssel eingetragen, aber die Verwaltung antwortet nicht wie erwartet: ${(e as Error).message}`);
    process.exitCode = 1;
  }
}

export async function importieren(datei: string | undefined): Promise<void> {
  if (!datei) { console.log('Aufruf: npm run import -- daten/lead-scout.json'); return; }
  const pfad = resolve(WURZEL, datei);
  const liste: Record<string, unknown>[] = JSON.parse(readFileSync(pfad, 'utf8'));
  if (!Array.isArray(liste)) throw new Error('Die Datei muss eine Liste von Firmen sein.');
  if (konfig.trocken) { log.info('import', `Trocken: ${liste.length} Firmen würden gemeldet.`); return; }
  let neu = 0, bekannt = 0, fehler = 0;
  for (let i = 0; i < liste.length; i += 100) {
    const r = await api('firmen_melden', { firmen: liste.slice(i, i + 100) });
    neu += r.neu ?? 0; bekannt += r.dubletten ?? 0; fehler += r.fehler ?? 0;
    for (const e of r.ergebnisse ?? []) if (e.fehler) log.warn('import', `${e.quelle}: ${e.fehler}`);
  }
  log.info('import', `${liste.length} übernommen: ${neu} neu, ${bekannt} schon bekannt, ${fehler} Fehler.`);
}
