/* Demo-Vorschau (Marketing-Studio 10, 01.10.2026): Auftragstext und Prüfung der Antwort. */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { demoText, demoLesen, SCHEMA_DEMO, type DemoAuftrag } from '../src/ki/demo.js';

const a: DemoAuftrag = { id: 7, art: 'demo', beschreibung: 'Demo-Vorschau · Trattoria Rossi', demo_id: 3, betrieb: 'Trattoria Rossi', url: 'https://trattoria-rossi.example',
  stadt: 'Sciacca', adresse: 'Via Roma 1', telefon: '+39 0925 000000', branche: 'ristorante', sprache: 'it', hinweis: '', verbessern: ['Kein Anruf-Knopf → Anrufen mit einem Tipp'], max_bytes: 160000 };

test('Auftragstext: Website, Sprache, Befunde — und die harten Regeln (nichts erfinden, kein JavaScript)', () => {
  const t = demoText(a);
  assert.match(t, /https:\/\/trattoria-rossi\.example/);
  assert.match(t, /Italienisch \(Lei-Form\)/);
  assert.match(t, /Kein Anruf-Knopf/);
  assert.match(t, /Erfinde nichts/);
  assert.match(t, /KEIN JavaScript/);
  assert.match(t, /lang="it"/);
  assert.doesNotMatch(t, /HINWEIS VON UWE/);
  assert.match(demoText({ ...a, sprache: 'de', hinweis: 'Fotos größer' }), /Deutsch \(Sie-Form\)[\s\S]*HINWEIS VON UWE FÜR DIESEN NEUBAU: Fotos größer/);
  assert.deepEqual(SCHEMA_DEMO.required, ['html', 'zusammenfassung', 'quellen']);
});

test('Antwort: nur eine vollständige Seite in der Größengrenze; Quellen nur als Adressen', () => {
  const html = '<!doctype html><html lang="it"><head><style>body{margin:0}</style></head><body><h1>Trattoria Rossi</h1>' + 'x'.repeat(500) + '</body></html>';
  const e = demoLesen({ html, zusammenfassung: 'Übernommen: Name, Adresse.', quellen: ['https://trattoria-rossi.example/', 'kein link'] }, 160000);
  assert.equal(e.html, html);
  assert.deepEqual(e.quellen, ['https://trattoria-rossi.example/']);
  assert.throws(() => demoLesen({ html: '<p>kurz</p>' }, 160000), /keine vollständige Seite/);
  assert.throws(() => demoLesen({ html }, 300), /zu groß/);
});
