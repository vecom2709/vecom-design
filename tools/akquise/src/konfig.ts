/* Einstellungen aus .env (neben package.json) und der Umgebung.
   Kein dotenv-Paket: zwanzig Zeilen, die man lesen kann, sind besser als
   eine Abhaengigkeit, die man glauben muss. */
import { readFileSync, existsSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

export const WURZEL = join(dirname(fileURLToPath(import.meta.url)), '..');
export const DATEN = join(WURZEL, 'daten');
export const VERSION = '1.0.0';

function envLesen(): Record<string, string> {
  const datei = join(WURZEL, '.env');
  const werte: Record<string, string> = {};
  if (existsSync(datei)) {
    for (const zeile of readFileSync(datei, 'utf8').split(/\r?\n/)) {
      const m = zeile.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
      if (!m) continue;
      werte[m[1]] = m[2].replace(/^["']|["']$/g, '');
    }
  }
  return werte;
}

const datei = envLesen();
const wert = (k: string, vorgabe = ''): string => process.env[k] ?? datei[k] ?? vorgabe;
const zahl = (k: string, vorgabe: number): number => {
  const n = Number(wert(k, String(vorgabe)));
  return Number.isFinite(n) ? n : vorgabe;
};

export const konfig = {
  url: wert('AKQUISE_URL', 'https://vecom-design.it/akquise.php'),
  schluessel: wert('AKQUISE_SCHLUESSEL'),
  anthropicKey: wert('ANTHROPIC_API_KEY'),
  modell: wert('AKQUISE_CLAUDE_MODELL', 'claude-sonnet-5'),
  kiMaxToken: zahl('AKQUISE_KI_MAX_TOKEN', 250_000),
  psiKey: wert('AKQUISE_PSI_SCHLUESSEL'),
  auditsProLauf: zahl('AKQUISE_AUDITS_PRO_LAUF', 20),
  texteProLauf: zahl('AKQUISE_TEXTE_PRO_LAUF', 5),
  scoreMinText: zahl('AKQUISE_SCORE_MIN_TEXT', 51),
  overpass: wert('AKQUISE_OVERPASS', 'https://overpass-api.de/api/interpreter'),
  overpassPauseMs: zahl('AKQUISE_OVERPASS_PAUSE_S', 12) * 1000,
  trocken: wert('AKQUISE_TROCKEN', '0') === '1',
  /* Wer wir sind -- offen, mit Adresse. Ein Pruefprogramm, das sich als
     Browser ausgibt, wuerde robots.txt-Regeln fuer Bots umgehen. Playwright
     und Lighthouse brauchen einen echten Browser-Kennsatz, damit die Seite
     so erscheint wie beim Besucher; der Zusatz am Ende bleibt trotzdem stehen. */
  botName: 'VecomAudit',
  botKennung: 'VecomAudit/1.0 (+https://vecom-design.it; Website-Pruefung, kontakt@vecom-design.it)',
  /* Hoeflichkeit: hoechstens eine Anfrage je Domain alle 1,5 s, hoechstens
     acht Seiten je Website, hoechstens 25 Links im Linkcheck. */
  domainPauseMs: 1500,
  maxSeiten: 8,
  maxLinks: 25,
};

export function datenOrdner(...teile: string[]): string {
  const p = join(DATEN, ...teile);
  if (!existsSync(p)) mkdirSync(p, { recursive: true });
  return p;
}
