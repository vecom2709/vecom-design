// Wiederherstellungsprobe (AI Office Stufe 0, 06.10.2026, Uwe: „Ja“ zu V4).
// Aufruf: node probe.mjs   — Windows-Aufgabe „VECOM Sicherungsprobe“, sonntags 04:30.
//
// Nimmt den jüngsten abgeholten Auszug, entschlüsselt und entpackt ihn, spielt ihn in
// eine eigene MariaDB ein (nur für die Probe gestartet, nur auf 127.0.0.1, Port 3399,
// danach wieder beendet), zählt Tabellen und Zeilen und meldet das Ergebnis
// unterschrieben an die Verwaltung. Die echte Datenbank wird dabei nie berührt.
import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';
import { spawn, spawnSync } from 'node:child_process';
import { pipeline } from 'node:stream/promises';
import readline from 'node:readline';
import { ORDNER, protokoll, anfrage, entschluesseln } from './vcs.mjs';

const BIN = path.join(ORDNER, 'mariadb', 'bin');
const DATEN = path.join(ORDNER, 'probe-daten');
const PORT = '3399';
const exe = (n) => path.join(BIN, n + (process.platform === 'win32' ? '.exe' : ''));
// --no-defaults zuerst: Eine installierte MariaDB/MySQL auf dem Rechner hat eine eigene my.ini — die Probe darf
// davon nichts lesen (gemessen 06.10.2026: ohne das teilte sich die Probe die Socket-Datei mit dem echten Server
// und löschte sie beim Beenden).
const EIGEN = process.platform === 'win32' ? [] : ['--socket=' + path.join(DATEN, 'probe.sock')];
// --host=/--port= ausgeschrieben: „-h127.0.0.1“ zerlegt PowerShell in „127“ (gemessen 06.10.2026) — so geht es überall.
const ZIEL = ['--host=127.0.0.1', '--port=' + PORT];
const client = (args, eingabe) => spawnSync(exe('mariadb'), ['--no-defaults', '-uroot', ...ZIEL, ...args],
  { input: eingabe, encoding: 'utf8', maxBuffer: 64 << 20 });
const warte = (ms) => new Promise((r) => setTimeout(r, ms));

async function main() {
  const start = Date.now();
  const auszuege = path.join(ORDNER, 'auszuege');
  const juengster = (fs.existsSync(auszuege) ? fs.readdirSync(auszuege) : []).filter((n) => n.endsWith('.sql.gz.vcs')).sort().pop();
  const ergebnis = { datei: juengster ?? '', ok: false, tabellen: 0, erwartet: 0, zeilen: {}, dauer_s: 0, fehler: '' };
  const arbeit = path.join(ORDNER, 'probe-arbeit');
  let server = null;
  try {
    if (!juengster) { throw new Error('Noch kein Auszug abgeholt.'); }
    if (!fs.existsSync(exe('mariadbd'))) { throw new Error('MariaDB für die Probe fehlt (' + BIN + ').'); }
    fs.mkdirSync(arbeit, { recursive: true });
    const gz = path.join(arbeit, 'auszug.sql.gz');
    const sql = path.join(arbeit, 'auszug.sql');
    entschluesseln(path.join(auszuege, juengster), gz);
    await pipeline(fs.createReadStream(gz), zlib.createGunzip(), fs.createWriteStream(sql));
    // Wie viele Tabellen stehen im Auszug? Daran misst der Server die Probe — nicht an der heutigen
    // Datenbank, die nach einem Deploy mit neuen Migrationen mehr Tabellen hat als der Auszug von gestern
    // (sonst gäbe es an solchen Tagen falschen Alarm; aufgefallen 06.10.2026).
    for await (const zeile of readline.createInterface({ input: fs.createReadStream(sql), crlfDelay: Infinity })) {
      if (zeile.startsWith('CREATE TABLE')) { ergebnis.erwartet++; }
    }

    if (!fs.existsSync(path.join(DATEN, 'mysql'))) {
      // Die Windows-Fassung von mariadb-install-db kennt --no-defaults nicht (gemessen 06.10.2026).
      const init = spawnSync(exe('mariadb-install-db'), [...(process.platform === 'win32' ? [] : ['--no-defaults']), '--datadir=' + DATEN], { encoding: 'utf8' });
      if (init.status !== 0) { throw new Error('Probe-Datenbank ließ sich nicht anlegen: ' + (init.stderr || init.stdout).slice(0, 200)); }
    }
    server = spawn(exe('mariadbd'), ['--no-defaults', '--datadir=' + DATEN, '--port=' + PORT, '--bind-address=127.0.0.1', '--console',
      '--max-allowed-packet=256M', '--pid-file=' + path.join(DATEN, 'probe.pid'), ...EIGEN],
      { stdio: 'ignore', windowsHide: true });
    let bereit = false;
    for (let i = 0; i < 60 && !bereit; i++) { await warte(1000); bereit = client(['-e', 'SELECT 1']).status === 0; }
    if (!bereit) { throw new Error('Probe-Datenbank startet nicht.'); }

    client(['-e', 'DROP DATABASE IF EXISTS vecom_probe; CREATE DATABASE vecom_probe CHARACTER SET utf8mb4;']);
    // sql_mode leer: Auszüge von vor dem 06.10.2026 schreiben Werte in berechnete Spalten (akq_firmen.email_found),
    // was ein strenger Server mit ERROR 1906 ablehnt — nachsichtig wird daraus eine Warnung, und der Rest kommt herein.
    const imp = spawnSync(exe('mariadb'), ['--no-defaults', '-uroot', ...ZIEL, '--max-allowed-packet=256M', "--init-command=SET SESSION sql_mode=''", 'vecom_probe'],
      { stdio: [fs.openSync(sql, 'r'), 'pipe', 'pipe'], encoding: 'utf8', maxBuffer: 64 << 20 });
    if (imp.status !== 0) { throw new Error('Einspielen gescheitert: ' + String(imp.stderr).trim().split('\n').filter((z) => /^ERROR/.test(z)).join(' ').slice(0, 240)); }

    const t = client(['-N', '-e', "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'vecom_probe'"]);
    ergebnis.tabellen = parseInt(String(t.stdout).trim(), 10) || 0;
    for (const tab of ['customers', 'orders', 'payments', 'invoices', 'projects', 'partner', 'akq_firmen', 'audit_log']) {
      const r = client(['-N', 'vecom_probe', '-e', `SELECT COUNT(*) FROM \`${tab}\``]);
      ergebnis.zeilen[tab] = r.status === 0 ? (parseInt(String(r.stdout).trim(), 10) || 0) : -1;
    }
    ergebnis.ok = ergebnis.tabellen > 0 && ergebnis.zeilen.customers >= 0 && (ergebnis.erwartet === 0 || ergebnis.tabellen >= ergebnis.erwartet);
  } catch (e) {
    ergebnis.fehler = e.message;
  } finally {
    if (server) {
      spawnSync(exe('mariadb-admin'), ['--no-defaults', '-uroot', ...ZIEL, 'shutdown'], { encoding: 'utf8' });
      await warte(2000);
      try { server.kill(); } catch {}
    }
    fs.rmSync(arbeit, { recursive: true, force: true });   // entschlüsselte Daten nie liegen lassen
  }
  ergebnis.dauer_s = Math.round((Date.now() - start) / 1000);
  protokoll(`Probe ${ergebnis.ok ? 'in Ordnung' : 'mit Problem'}: ${ergebnis.datei}, ${ergebnis.tabellen} Tabellen, ${JSON.stringify(ergebnis.zeilen)}${ergebnis.fehler ? ' — ' + ergebnis.fehler : ''}`);
  try {
    const r = await (await anfrage('probe', '', JSON.stringify(ergebnis))).json();
    protokoll('Gemeldet: ' + r.text);
  } catch (e) { protokoll('Melden gescheitert: ' + e.message); process.exitCode = 2; }
  if (!ergebnis.ok) { process.exitCode = 1; }
}

main();
