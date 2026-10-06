/* Töne am PC (Akquise-CRM D-2): Auftragstext und Prüfung der Antwort. */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { tonText, tonLesen, SCHEMA_TON, type TonAuftrag } from '../src/ki/ton.js';

const a: TonAuftrag = { id: 7, art: 'ton', beschreibung: 'Umformulieren · Kürzer · E-Mail an Bar Centrale', ton: 'kuerzer',
  anweisung: 'Kürzer: höchstens etwa die Hälfte.', kanal: 'email', sprache: 'it', betreff: 'Il vostro sito',
  text: 'Buongiorno,\nil prezzo lo vede in 90 secondi: vecom-design.it/bedarf.php\nCordiali saluti', zahlen: ['90'], links: ['vecom-design.it/bedarf.php'] };

test('Auftragstext: Sprache, Anweisung, nur Zahlen und Links aus dem Original, Original dabei', () => {
  const t = tonText(a);
  assert.match(t, /SPRACHE: Italienisch/);
  assert.match(t, /Kürzer: höchstens/);
  assert.match(t, /Nichts dazuerfinden/);
  assert.match(t, /90 · vecom-design\.it\/bedarf\.php/);
  assert.match(t, /Betreff: Il vostro sito/);
  assert.match(t, /rechtssicher/);
  const wa = tonText({ ...a, kanal: 'whatsapp', zahlen: [], links: [] });
  assert.match(wa, /betreff bleibt leer/);
  assert.match(wa, /\(keine Zahlen\)/);
  assert.ok(SCHEMA_TON.required.includes('text'));
});

test('Antwort: Text Pflicht, Betreff bei E-Mail Pflicht, kein HTML; WhatsApp ohne Betreff', () => {
  assert.deepEqual(tonLesen({ betreff: 'Il sito', text: 'Buongiorno, ecco il link: vecom-design.it' }, a), { betreff: 'Il sito', text: 'Buongiorno, ecco il link: vecom-design.it' });
  assert.throws(() => tonLesen({ betreff: '', text: 'Buongiorno, ecco il link: vecom-design.it' }, a), /Betreff/);
  assert.throws(() => tonLesen({ betreff: 'x', text: 'kurz' }, a), /zu kurz/);
  assert.throws(() => tonLesen({ betreff: 'x', text: 'Buongiorno <a href="x">qui</a> e basta così' }, a), /HTML/);
  assert.equal(tonLesen({ betreff: 'egal', text: 'Buongiorno, ecco il link: vecom-design.it' }, { kanal: 'whatsapp' }).betreff, '');
});
