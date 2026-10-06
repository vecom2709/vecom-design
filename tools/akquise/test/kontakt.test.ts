import { test } from 'node:test';
import assert from 'node:assert/strict';
import { kontaktAusSeiten, mailsAusText, whatsappAusLinks } from '../src/audit/index.js';

test('E-Mail aus dem Text, auch versteckt; Bilddateien und Platzhalter nicht', () => {
  assert.deepEqual(mailsAusText('Scrivici: info@trattoria.it — logo@2x.png — nome@example.com'), ['info@trattoria.it']);
  assert.deepEqual(mailsAusText('Kontakt: kontakt [at] baeckerei-mueller [dot] de'), ['kontakt@baeckerei-mueller.de']);
});

test('eigene Domain schlägt fremde, mailto vor Text', () => {
  const r = kontaktAusSeiten([{ mailtoLinks: ['webmaster@agentur.it'], text: 'prenotazioni@ristorante-sole.it' }], 'https://www.ristorante-sole.it/');
  assert.equal(r.email, 'prenotazioni@ristorante-sole.it');
  const r2 = kontaktAusSeiten([{ mailtoLinks: [], text: 'nichts hier' }], 'https://x.it/');
  assert.equal(r2.email, undefined);
});

test('WhatsApp-Nummer aus wa.me und api.whatsapp.com', () => {
  assert.equal(whatsappAusLinks(['https://wa.me/393331234567?text=ciao']), '+393331234567');
  assert.equal(whatsappAusLinks(['https://api.whatsapp.com/send?phone=4915112345678']), '+4915112345678');
  assert.equal(whatsappAusLinks(['https://www.whatsapp.com/']), undefined);
});
