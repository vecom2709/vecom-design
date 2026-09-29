/* Öffnungszeiten von der eigenen Website (29.09.2026, D3). */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { ausJsonLd, ausText, oeffnungLesen, openingHoursLesen } from '../src/audit/zeiten.js';

test('openingHours als Text (Schema.org)', () => {
  assert.deepEqual(openingHoursLesen('Mo-Fr 09:00-18:00'), [{ t: [1, 2, 3, 4, 5], v: '09:00', b: '18:00' }]);
  assert.deepEqual(openingHoursLesen('Tu,Th 12:00-15:00,19:00-23:30'),
    [{ t: [2, 4], v: '12:00', b: '15:00' }, { t: [2, 4], v: '19:00', b: '23:30' }]);
  assert.deepEqual(openingHoursLesen('Fr-Mo 18:00-02:00'), [{ t: [1, 5, 6, 7], v: '18:00', b: '02:00' }]);
});

test('openingHoursSpecification in @graph, abgelaufene Sonderzeit fällt weg', () => {
  const ld = { '@graph': [{ '@type': 'WebSite' }, { '@type': 'Restaurant', openingHoursSpecification: [
    { dayOfWeek: ['https://schema.org/Monday', 'Tuesday'], opens: '12:00:00', closes: '15:00:00' },
    { dayOfWeek: 'Sunday', opens: '10:00', closes: '14:00', validThrough: '2020-01-01' }] }] };
  assert.deepEqual(ausJsonLd(ld), [{ t: [1, 2], v: '12:00', b: '15:00' }]);
});

test('Text hinter „Orari“ (it) und „Öffnungszeiten“ (de)', () => {
  assert.deepEqual(ausText('Benvenuti. Orari: lun-sab 12:00-15:00. Prenota un tavolo.'), [{ t: [1, 2, 3, 4, 5, 6], v: '12:00', b: '15:00' }]);
  assert.deepEqual(ausText('Öffnungszeiten: Di – So 11.30 – 14.30 und 17.30 – 22.00'),
    [{ t: [2, 3, 4, 5, 6, 7], v: '11:30', b: '14:30' }, { t: [2, 3, 4, 5, 6, 7], v: '17:30', b: '22:00' }]);
  assert.deepEqual(ausText('Orario: domenica 12:00-15:00'), [{ t: [7], v: '12:00', b: '15:00' }]);
});

test('ohne Stichwort wird nichts geraten', () => {
  assert.deepEqual(ausText('Wir sind am Montag 9:00-12:00 beim Kunden. Radio 12.00-13.00'), []);
  assert.equal(oeffnungLesen([], ['Nur Text ohne Zeiten']), null);
});

test('strukturierte Daten gehen vor Text', () => {
  const r = oeffnungLesen([{ '@type': 'Bakery', openingHours: ['Mo-Sa 06:00-13:00'] }], ['Orari: lun-dom 08:00-20:00']);
  assert.deepEqual(r, { quelle: 'daten', zeiten: [{ t: [1, 2, 3, 4, 5, 6], v: '06:00', b: '13:00' }] });
});
