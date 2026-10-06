/* Bau-Warteschlange am PC (AutoBuild Phase 5): Auftragstext und Prüfung der Antwort. */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { bauText, bauLesen, SCHEMA_BAU, type BauAuftrag } from '../src/ki/bau.js';

const a: BauAuftrag = { id: 11, art: 'pflichtenheft', projekt: 5, beschreibung: 'Pflichtenheft · Website Bar Centrale', titel: 'Website Bar Centrale',
  kunde: { firma: 'Bar Centrale', branche: 'gastro', ort: 'Lecce IT', sprache: 'it', website: 'https://barcentrale.example' },
  hinweis: 'Speisekarte wichtig', briefing: 'Ziel: mehr Reservierungen.', hausregeln: 'Keine Stockfotos.',
  umfang: [{ bezeichnung: 'Onepager', beschreibung: 'bis 6 Abschnitte', menge: 1, monatlich: false }, { bezeichnung: 'Hosting', beschreibung: '', menge: 1, monatlich: true }],
  analyse: '# Analyse\nRisiko gelb', pflichtenheft: '', bau_erlaubt: false, regel: 'Nur lesen, analysieren und planen.' };

test('Auftragstext: Regel, Bausperre, Umfang ohne Preise, Gliederung, Analyse als Grundlage', () => {
  const t = bauText(a);
  assert.match(t, /Nur lesen, analysieren und planen/);
  assert.match(t, /Bausperre gilt noch/);
  assert.match(t, /- Onepager — bis 6 Abschnitte/);
  assert.match(t, /Hosting \(monatlich\)/);
  assert.match(t, /## Abnahmekriterien/);
  assert.match(t, /## Nicht enthalten/);
  assert.match(t, /ÜBERNOMMENE ANALYSE/);
  assert.match(t, /auf Italienisch gebaut/);
  assert.match(t, /Speisekarte wichtig/);
  assert.doesNotMatch(bauText({ ...a, bau_erlaubt: true }), /Bausperre gilt noch/);
  assert.match(bauText({ ...a, umfang: [] }), /Kein Angebot im System/);
  assert.match(bauText({ ...a, art: 'analyse' }), /## Risiken/);
  assert.ok(SCHEMA_BAU.required.includes('markdown'));
});

test('Antwort: lang genug, Pflichtabschnitte, kein HTML', () => {
  const gut = '# Pflichtenheft\n' + 'Text '.repeat(60) + '\n## Nicht enthalten\n- Shop\n## Abnahmekriterien\n- Formular kommt an';
  assert.equal(bauLesen({ markdown: gut }, a), gut);
  assert.throws(() => bauLesen({ markdown: 'kurz' }, a), /zu kurz/);
  assert.throws(() => bauLesen({ markdown: gut.replace('## Abnahmekriterien', '## Ende') }, a), /Abnahmekriterien/);
  assert.throws(() => bauLesen({ markdown: gut + '<script>x</script>' }, a), /HTML/);
  assert.throws(() => bauLesen({ markdown: gut }, { art: 'analyse' }), /Risiken/);
  assert.throws(() => bauLesen(null, a), /kein Dokument/);
});
