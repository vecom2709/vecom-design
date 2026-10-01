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

import { inhalteText, SCHEMA_INHALTE } from '../src/ki/marketing.js';

test('Schreibauftrag: Formate, Grenzen, Profil, Funde mit id — und keine Links im Text', () => {
  const t = inhalteText({ id: 9, art: 'inhalte', branche: 'restaurant', land: 'IT', beschreibung: 'Inhalte · Ristoranti — 6 Stück',
    zielgruppe: { id: 3, name: 'Restaurant', profil: { titel: 'Ristoranti in Sicilia', probleme: ['Provision pro Gedeck'] } },
    plattformen: ['instagram', 'google'], umfang: 'beides', anzahl: 6, thema: 'Nebensaison',
    formate: { beitrag: { wort: 'Beitrag', art: 'organisch', plattformen: ['instagram'] }, google_anzeige: { wort: 'Google-Suchanzeige', art: 'bezahlt', plattformen: ['google'] } },
    grenzen: { g_ueberschrift: 30, g_beschreibung: 90 }, meta_cta: { LEARN_MORE: 'Mehr dazu' }, zielseite: '/siti-web-ristoranti.html',
    funde: [{ id: 42, art: 'trend', titel: 'Nur 13,5 % mit Online-Reservierung', text: 'FIPE', relevanz: 5, gemerkt: true, quellen: ['https://example.org'] }], bisherige_titel: ['Alt'] });
  assert.match(t, /AUFTRAG #9/);
  assert.match(t, /genau 6 Stück/);
  assert.match(t, /Thema: Nebensaison/);
  assert.match(t, /JEDE ≤30 Zeichen/);
  assert.match(t, /Italienisch, Anrede „Lei“/);
  assert.match(t, /Keine Links und keine Telefonnummern/);
  assert.match(t, /"id": 42/);
  assert.match(t, /Ristoranti in Sicilia/);
  assert.ok((SCHEMA_INHALTE.properties.inhalte as any).items.required.includes('fund_ids'));
});

import { uebersetzenText, SCHEMA_UEBERSETZEN } from '../src/ki/marketing.js';

test('Deutsch und Italienisch (Marketing-Studio 5): Italien bekommt die deutsche Fassung, Deutschland die deutschen Seiten', () => {
  const basis = { id: 11, branche: 'friseur', beschreibung: 'Friseur', zielgruppen_fuer: [{ branche: 'friseur', name: 'Friseur' }], vorhandene_profile: {}, daten: {} };
  const it = auftragText({ ...basis, land: 'IT' });
  const de = auftragText({ ...basis, land: 'DE' });
  assert.match(it, /gleiche Reihenfolge, gleiche Anzahl wie das italienische Original/);
  assert.match(de, /alle Listen leer/);
  assert.match(de, /\/de\/website-friseur\.html/);
  assert.match(de, /Anrede „Sie“/);
  assert.doesNotMatch(de, /siti-web-parrucchieri/);
  const zg = (SCHEMA.properties.zielgruppen as any).items;
  assert.ok(zg.required.includes('de'));
  assert.ok((SCHEMA_INHALTE.properties.inhalte as any).items.required.includes('uebersetzung'));
});

test('Übersetzungsauftrag: nur übersetzen, nichts erfinden — Profile und Inhalte mit id', () => {
  const t = uebersetzenText({ id: 12, art: 'uebersetzen', beschreibung: 'Deutsche Fassung · 1 Zielgruppen, 1 Inhalte',
    profile: [{ id: 3, titel: 'Ristoranti', listen: { fragen: ['Quanto costa un sito?'] } }],
    inhalte: [{ id: 8, format: 'beitrag', felder: { text: 'Prenotazioni dirette' } }] });
  assert.match(t, /AUFTRAG #12/);
  assert.match(t, /Keine Recherche nötig/);
  assert.match(t, /erfinde nichts dazu/);
  assert.match(t, /Quanto costa un sito\?/);
  assert.match(t, /"id": 8/);
  assert.deepEqual(SCHEMA_UEBERSETZEN.required, ['profile', 'inhalte']);
});
