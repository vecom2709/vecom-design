/* Kunden finden (07.10.2026): Partita IVA und Agentur aus der Fußzeile. */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { pivaAusText, agenturAusText } from '../src/audit/index.js';
import { alsFirma } from '../src/recherche/overpass.js';

test('Partita IVA mit Kennwort, nie eine Telefonnummer', () => {
  assert.equal(pivaAusText('Bar Roma srl · P.IVA 01234567890 · Via Atenea 1'), '01234567890');
  assert.equal(pivaAusText('C.F. e P.IVA: IT 09876543210'), '09876543210');
  assert.equal(pivaAusText('USt-IdNr.: DE123456789'), '123456789');
  assert.equal(pivaAusText('Tel. 09221234567 · info@bar.it'), undefined);
});

test('Agentur im Seitenfuß, Baukasten nicht', () => {
  assert.equal(agenturAusText('© 2025 Bar Roma · Realizzato da Studio Lumen'), 'Studio Lumen');
  assert.equal(agenturAusText('Webdesign by Pixelwerk Mainz'), 'Pixelwerk Mainz');
  assert.equal(agenturAusText('Powered by WordPress'), undefined);
  assert.equal(agenturAusText('Sito realizzato da WordPress'), undefined);
  assert.equal(agenturAusText('Nur Text ohne Hinweis'), undefined);
});

test('Neueröffnung: Version und Zeit aus OpenStreetMap', () => {
  const f = alsFirma({ type: 'node', id: 7, lat: 37.3, lon: 13.5, version: 1, timestamp: '2026-09-30T10:00:00Z',
    tags: { name: 'Pizzeria Nuova', amenity: 'restaurant' } }, 'IT', { stadt: 'Agrigento' }, []);
  assert.equal(f?.osm_version, 1);
  assert.equal(f?.osm_zeit, '2026-09-30T10:00:00Z');
});
