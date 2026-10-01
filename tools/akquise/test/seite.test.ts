/* Landingpage je Zielgruppe (S6, 01.10.2026): Auftragstext und Prüfung der Antwort. */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { seiteText, seiteLesen, SCHEMA_SEITE, type SeiteAuftrag } from '../src/ki/seite.js';

const a: SeiteAuftrag = { id: 9, art: 'seite', beschreibung: 'Landingpage · Restaurant · Italien', zielgruppe_id: 4, land: 'IT', sprache: 'it',
  branche: 'restaurant', branche_name: 'Ristorante', profil: { titel: 'Ristoranti in Sicilia', einwaende: ['Costa troppo'] }, grenzen: { h1: 100 }, bisher: null };

test('Auftragstext: Sprache, Profil, harte Regeln (nichts erfinden, kein HTML), deutsche Lesefassung nur für Italien', () => {
  const t = seiteText(a);
  assert.match(t, /Italienisch, Lei-Form/);
  assert.match(t, /Costa troppo/);
  assert.match(t, /keine Kundenstimmen/);
  assert.match(t, /kein HTML/);
  assert.match(t, /h1 \(≤100\)/);
  assert.match(t, /GANZE Seite sinngemäß auf Deutsch/);
  assert.doesNotMatch(t, /BISHERIGE FASSUNG/);
  const de = seiteText({ ...a, land: 'DE', sprache: 'de', bisher: { h1: 'Alt' } });
  assert.match(de, /Deutsch, Sie-Form/);
  assert.match(de, /lesen_de: leer lassen/);
  assert.match(de, /BISHERIGE FASSUNG/);
  assert.ok(SCHEMA_SEITE.required.includes('lesen_de'));
});

test('Antwort: Pflichtfelder, mindestens zwei Abschnitte, kein HTML', () => {
  const gut = { titel: 'T', h1: 'H', lead: 'L', abschnitte: [{ h2: 'a', text: 'x', punkte: [] }, { h2: 'b', text: 'y', punkte: [] }], faq: [] };
  assert.equal(seiteLesen(gut), gut);
  assert.throws(() => seiteLesen({ ...gut, h1: '' }), /h1/);
  assert.throws(() => seiteLesen({ ...gut, abschnitte: [gut.abschnitte[0]] }), /Zu wenige/);
  assert.throws(() => seiteLesen({ ...gut, lead: 'Hallo <script>x</script>' }), /HTML/);
});
