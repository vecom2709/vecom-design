// Sicherung außer Haus — gemeinsame Teile für holen.mjs und probe.mjs (AI Office Stufe 0, 06.10.2026).
// Gegenstück zu app/src/SicherungAussen.php. Keine Abhängigkeiten außer Node selbst.
//
// Der private Schlüssel liegt nur auf diesem Rechner, in <Ordner>\schluessel\privat.pem.
// Er gehört in kein Repository, keine Mail und keinen Chat.
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';

export const BASIS = process.env.VECOM_BASIS || 'https://vecom-design.it';
export const ORDNER = process.env.VECOM_SICHERUNG || path.join(os.homedir(), 'Vecom-Sicherung');
export const PRIVAT = path.join(ORDNER, 'schluessel', 'privat.pem');
export const OEFFENTLICH = path.join(ORDNER, 'oeffentlich.pem');

export function protokoll(text) {
  const zeile = `${new Date().toISOString()}  ${text}`;
  console.log(zeile);
  try { fs.mkdirSync(ORDNER, { recursive: true }); fs.appendFileSync(path.join(ORDNER, 'protokoll.txt'), zeile + '\n'); } catch {}
}

/** Gleicher Fingerabdruck wie SicherungAussen::fingerabdruck(): sha256 über DER, 16 Zeichen in Vierern. */
export function fingerabdruck(pem) {
  const der = crypto.createPublicKey(pem).export({ type: 'spki', format: 'der' });
  return crypto.createHash('sha256').update(der).digest('hex').slice(0, 16).match(/.{4}/g).join(':');
}

/** Schlüsselpaar anlegen, falls es fehlt. Gibt true zurück, wenn es neu ist. */
export function schluesselSicherstellen() {
  if (fs.existsSync(PRIVAT)) { return false; }
  fs.mkdirSync(path.dirname(PRIVAT), { recursive: true });
  const { publicKey, privateKey } = crypto.generateKeyPairSync('rsa', { modulusLength: 4096 });
  fs.writeFileSync(PRIVAT, privateKey.export({ type: 'pkcs8', format: 'pem' }), { mode: 0o600 });
  fs.writeFileSync(OEFFENTLICH, publicKey.export({ type: 'spki', format: 'pem' }));
  return true;
}

export function privat() { return crypto.createPrivateKey(fs.readFileSync(PRIVAT)); }

let zuletzt = 0;
/** Eine unterschriebene Anfrage. Die Zeit steigt streng — der Server lehnt Wiederholungen ab. */
export async function anfrage(aktion, name = '', rumpf = '') {
  let zeit = Date.now();
  if (zeit <= zuletzt) { zeit = zuletzt + 1; }
  zuletzt = zeit;
  const nachricht = `${aktion}\n${zeit}\n${name}\n${crypto.createHash('sha256').update(rumpf).digest('hex')}`;
  const signatur = crypto.sign('sha256', Buffer.from(nachricht), privat()).toString('base64');
  const url = `${BASIS}/sicherung.php?aktion=${encodeURIComponent(aktion)}${name ? '&name=' + encodeURIComponent(name) : ''}`;
  const antwort = await fetch(url, {
    method: rumpf ? 'POST' : 'GET',
    headers: { 'X-Vecom-Zeit': String(zeit), 'X-Vecom-Signatur': signatur, ...(rumpf ? { 'Content-Type': 'application/json' } : {}) },
    body: rumpf || undefined,
  });
  if (!antwort.ok) { throw new Error(`${aktion} ${name}: HTTP ${antwort.status}`); }
  return antwort;
}

/**
 * VCS1 entschlüsseln (Format: app/src/SicherungAussen.php). Schreibt nach ziel, oder nur prüfen, wenn ziel null ist.
 * Wirft, wenn ein Abschnitt fehlt, verändert ist oder das Ende abgeschnitten wurde.
 */
export function entschluesseln(quelle, ziel = null) {
  const fd = fs.openSync(quelle, 'r');
  const aus = ziel ? fs.openSync(ziel, 'w') : null;
  const lies = (n) => {
    const b = Buffer.alloc(n);
    let gelesen = 0;
    while (gelesen < n) {
      const r = fs.readSync(fd, b, gelesen, n - gelesen, null);
      if (r === 0) { throw new Error('Datei endet zu früh'); }
      gelesen += r;
    }
    return b;
  };
  try {
    if (lies(4).toString() !== 'VCS1') { throw new Error('Kein VCS1'); }
    const laenge = lies(2).readUInt16BE(0);
    const schluessel = crypto.privateDecrypt({ key: privat(), padding: crypto.constants.RSA_PKCS1_OAEP_PADDING, oaepHash: 'sha1' }, lies(laenge));
    const anfang = lies(8);
    for (let nr = 0; ; nr++) {
      const kopf = lies(4).readUInt32BE(0);
      const letzter = (kopf & 0x80000000) !== 0;
      const n = kopf & 0x7fffffff;
      const geheim = n > 0 ? lies(n) : Buffer.alloc(0);
      const tag = lies(16);
      const nummer = Buffer.alloc(4); nummer.writeUInt32BE(nr, 0);
      const d = crypto.createDecipheriv('aes-256-gcm', schluessel, Buffer.concat([anfang, nummer]));
      d.setAAD(Buffer.concat([Buffer.from('VCS1'), nummer, Buffer.from([letzter ? 1 : 0])]));
      d.setAuthTag(tag);
      const klar = Buffer.concat([d.update(geheim), d.final()]);
      if (aus !== null) { fs.writeSync(aus, klar); }
      if (letzter) { break; }
    }
    const rest = Buffer.alloc(1);
    if (fs.readSync(fd, rest, 0, 1, null) !== 0) { throw new Error('Daten nach dem Ende'); }
  } finally {
    fs.closeSync(fd);
    if (aus !== null) { fs.closeSync(aus); }
  }
}
