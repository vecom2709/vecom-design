/* Overture-Auslese gegen feste Zeilen -- ohne Netz. Die Beispiele stammen aus
   der Probe „Provinz Agrigento“ vom 27.09.2026 (Version 2026-09-23.1). */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { alsFirma, brancheAusOverture, drin, type OvertureGebiet, type OvertureZeile } from '../src/recherche/overture.js';

const quadrat = [[13.5, 37.2], [13.8, 37.2], [13.8, 37.4], [13.5, 37.4], [13.5, 37.2]];
const g: OvertureGebiet = { name: 'Agrigento', land: 'IT', region: 'Sicilia', kreis: 'Agrigento', bbox: [13.5, 37.2, 13.8, 37.4], polygone: [quadrat] };
const zeile = (z: Partial<OvertureZeile>): OvertureZeile => ({ id: '08f1e7a0-test', name: 'Osteria Esempio', th: ['food_and_drink', 'restaurant', 'italian_restaurant'],
  website: 'www.esempio.it', telefon: '+390922000000', email: null, strasse: 'VIA ROMA 5', ort: 'FAVARA', plz: '92026', land: 'IT',
  lon: 13.66, lat: 37.31, confidence: 0.9, status: 'open', marke: null, ...z });

test('Branche: tiefster Treffer gewinnt (Bäckerei unter Lebensmittelladen, nicht Einzelhandel)', () => {
  assert.equal(brancheAusOverture(['shopping', 'food_and_beverage_store', 'bakery'], 'Panificio Rossi'), 'baeckerei');
  assert.equal(brancheAusOverture(['shopping', 'food_and_beverage_store', 'grocery_store'], 'Market Sole'), 'einzelhandel');
  assert.equal(brancheAusOverture(['lifestyle_services', 'personal_or_beauty_service', 'hair_salon'], 'Charme'), 'friseur');
  assert.equal(brancheAusOverture(['lodging', 'bed_and_breakfast'], 'Albachiara'), 'ferienwohnung');
});

test('Branche: Agriturismo über den Namen, Banken und Apotheken nie', () => {
  assert.equal(brancheAusOverture(['lodging', 'bed_and_breakfast'], 'Agriturismo Il Mandorlo'), 'agriturismo');
  assert.equal(brancheAusOverture(['services_and_business', 'financial_service', 'bank'], 'Banca Sicula'), null);
  assert.equal(brancheAusOverture(['shopping', 'specialty_store', 'pharmacy'], 'Farmacia Centrale'), null);
  assert.equal(brancheAusOverture(['food_and_drink', 'restaurant'], 'Da Nino', ['friseur']), null);
});

test('Punkt in Fläche', () => {
  assert.equal(drin(13.66, 37.31, [quadrat]), true);
  assert.equal(drin(14.1, 37.31, [quadrat]), false);
});

test('Firma: Quelle, Lizenz, Ort lesbar geschrieben, Website mit Schema', () => {
  const f = alsFirma(zeile({}), g)!;
  assert.equal(f.quelle, 'overture:08f1e7a0-test');
  assert.match(f.quelle_lizenz, /CDLA/);
  assert.equal(f.stadt, 'Favara');
  assert.equal(f.url, 'http://www.esempio.it');
  assert.equal(f.branche, 'restaurant');
});

test('Firma: geschlossen, unsicher, Kette, außerhalb der Grenze, Behörde → nichts', () => {
  assert.equal(alsFirma(zeile({ status: 'permanently_closed' }), g), null);
  assert.equal(alsFirma(zeile({ confidence: 0.3 }), g), null);
  assert.equal(alsFirma(zeile({ marke: 'Q38076' }), g), null);
  assert.equal(alsFirma(zeile({ lon: 14.2 }), g), null);
  assert.equal(alsFirma(zeile({ name: 'Comune di Favara' }), g), null);
});
