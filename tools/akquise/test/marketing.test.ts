/* Marketing-Recherche per Knopf (01.10.2026): Antwort von Claude Code lesen, Auftragstext. */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { auftragText, ergebnisLesen, SCHEMA } from '../src/ki/marketing.js';

const inhalt = { zielgruppen: [], funde: [{ art: 'trend', titel: 'T', text: 'x', branche: '', land: 'IT', relevanz: 3, quellen: [{ titel: 'Q', url: 'https://example.org' }] }], zusammenfassung: 'kurz' };

test('strukturierte Ausgabe (--json-schema) wird gelesen', () => {
  const e = ergebnisLesen(JSON.stringify({ type: 'result', subtype: 'success', is_error: false, result: '', structured_output: inhalt }));
  assert.equal(e.funde.length, 1);
  assert.equal(e.zusammenfassung, 'kurz');
});

test('ohne structured_output: JSON aus dem Text, auch im Codezaun', () => {
  const e = ergebnisLesen(JSON.stringify({ type: 'result', subtype: 'success', result: 'Hier:\n```json\n' + JSON.stringify(inhalt) + '\n```' }));
  assert.equal(e.funde[0].titel, 'T');
});

test('Abbruch (Limit, Fehler) wird als Fehler gemeldet, nicht als leeres Ergebnis', () => {
  assert.throws(() => ergebnisLesen(JSON.stringify({ type: 'result', subtype: 'error_during_execution', is_error: true, result: 'Usage limit reached' })), /abgebrochen.*Usage limit/);
  assert.throws(() => ergebnisLesen('kein json'), /kein JSON/);
  assert.throws(() => ergebnisLesen(JSON.stringify({ subtype: 'success', structured_output: { funde: [] } })), /zielgruppen/);
});

test('Auftragstext: Land, Sprache, Regeln, Daten — und das Schema verlangt Quellen', () => {
  const t = auftragText({ id: 7, branche: 'restaurant', land: 'IT', beschreibung: 'Restaurant · Italien', zielgruppen_fuer: [{ branche: 'restaurant', name: 'Restaurant' }],
    vorhandene_profile: {}, daten: { wortschatz: { restaurant: 'Restaurant' } } });
  assert.match(t, /AUFTRAG #7/);
  assert.match(t, /Italienisch mit „Lei“/);
  assert.match(t, /Art\. 130 Codice Privacy/);
  assert.match(t, /Erfinde nie Zahlen und nie URLs/);
  assert.match(t, /"restaurant": "Restaurant"/);
  const fund = (SCHEMA.properties.funde as any).items;
  assert.ok(fund.required.includes('quellen'));
  assert.equal(fund.properties.quellen.minItems, 1);
});
