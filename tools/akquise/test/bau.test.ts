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

/* Phase 7: Builder und Reviewer */
import { bauenText, reviewText, bauenLesen, reviewLesen, SCHEMA_BAUEN, SCHEMA_REVIEW } from '../src/ki/bau.js';
const b: BauAuftrag = { ...a, art: 'bauen', pflichtenheft: '# Pflichtenheft\n## Nicht enthalten\n- Shop', bau_erlaubt: true, regel: 'Baue die Website als statische Dateien.',
  kontakt: { telefon: '0922 123456', email: 'info@bar.example', adresse: 'Via Roma 1, Agrigento' }, versuch: 1, max_versuche: 3 };

test('Builder: Pflichtenheft verbindlich, keine Platzhalter, Kontakt nur aus den Angaben, beim Nachbessern Mängel und Quelltext', () => {
  const t = bauenText(b);
  assert.match(t, /ÜBERNOMMENE PFLICHTENHEFT/);
  assert.match(t, /KEINE Platzhalter/);
  assert.match(t, /Telefon 0922 123456/);
  assert.match(t, /lang="it"/);
  assert.doesNotMatch(t, /NACHBESSERN/);
  const n = bauenText({ ...b, versuch: 2, hinweis: '- index.html: H1 fehlt', fassung: { nummer: 4, dateien: [{ pfad: 'index.html', inhalt: '<p>alt</p>' }], tests: [], review: '# Review' } });
  assert.match(n, /NACHBESSERN — RUNDE 2 VON 3/);
  assert.match(n, /H1 fehlt/);
  assert.match(n, /===== index\.html =====\n<p>alt<\/p>/);
  assert.ok(SCHEMA_BAUEN.required.includes('dateien') && SCHEMA_REVIEW.required.includes('urteil'));
});

test('Builder-Antwort: index.html Pflicht, nur erlaubte Pfade und Endungen, Größe begrenzt', () => {
  const gut = { dateien: [{ pfad: 'index.html', inhalt: '<!doctype html>' }, { pfad: 'css/stil.css', inhalt: 'body{}' }], zusammenfassung: 'Onepager mit drei Abschnitten gebaut.' };
  assert.equal(bauenLesen(gut).dateien.length, 2);
  assert.throws(() => bauenLesen({ ...gut, dateien: [{ pfad: 'about.html', inhalt: 'x' }] }), /index\.html fehlt/);
  assert.throws(() => bauenLesen({ ...gut, dateien: [...gut.dateien, { pfad: '../boese.html', inhalt: 'x' }] }), /Unzulässige/);
  assert.throws(() => bauenLesen({ ...gut, dateien: [...gut.dateien, { pfad: 'shell.php', inhalt: 'x' }] }), /Unzulässige/);
  assert.throws(() => bauenLesen({ ...gut, dateien: [...gut.dateien, { pfad: '.htaccess', inhalt: 'x' }] }), /Unzulässige/);
  assert.throws(() => bauenLesen({ ...gut, dateien: [{ pfad: 'index.html', inhalt: 'x'.repeat(2_000_000) }] }), /Zu groß/);
  assert.throws(() => bauenLesen({ ...gut, zusammenfassung: '' }), /Zusammenfassung/);
});

test('Reviewer: Tests und Quelltext im Auftrag, Urteil nur bestanden/nachbessern, nachbessern braucht Mängel', () => {
  const r = reviewText({ ...b, art: 'review', fassung: { nummer: 2, dateien: [{ pfad: 'index.html', inhalt: '<h1>x</h1>' }], tests: [{ name: 'Titel', ok: false, schwer: true, detail: 'index.html' }], review: '' } });
  assert.match(r, /FEHLER Titel — index\.html/);
  assert.match(r, /Fassung V2/);
  assert.match(r, /===== index\.html =====/);
  const md = '# Review V2\n' + 'Text '.repeat(50);
  assert.equal(reviewLesen({ urteil: 'bestanden', markdown: md, maengel: [] }).urteil, 'bestanden');
  assert.equal(reviewLesen({ urteil: 'irgendwas', markdown: md, maengel: ['x'] }).urteil, 'nachbessern');
  assert.throws(() => reviewLesen({ urteil: 'nachbessern', markdown: md, maengel: [] }), /ohne Mängel/);
  assert.throws(() => reviewLesen({ urteil: 'bestanden', markdown: 'kurz', maengel: [] }), /zu kurz/);
});
