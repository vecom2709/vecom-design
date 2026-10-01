/* Vorher/Nachher-Bild (Marketing-Studio 9, 01.10.2026): das HTML fürs Bild, ohne Browser geprüft. */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { vnHtml, namenGroesse, esc, BREITE, HOEHE } from '../src/ki/vorhernachher.js';

const bild = 'A'.repeat(300);

test('zwei Handys: Prima/Dopo auf Italienisch, Vorher/Nachher auf Deutsch', () => {
  const it = vnHtml({ betrieb: 'Pizzeria Da Mario', sprache: 'it', domain: 'pizzeriadamario.it', nachher: bild, vorher: bild });
  assert.match(it, /<figcaption>Prima<\/figcaption>/);
  assert.match(it, /<figcaption>Dopo<\/figcaption>/);
  assert.match(it, /class="pfeil"/);
  assert.match(it, /lang="it"/);
  assert.equal((it.match(/class="telefon /g) ?? []).length, 2);
  const de = vnHtml({ betrieb: 'Friseur Kamm', sprache: 'de', domain: 'friseur-kamm.de', nachher: bild, vorher: bild });
  assert.match(de, /<figcaption>Vorher<\/figcaption>/);
  assert.match(de, /<figcaption>Nachher<\/figcaption>/);
  assert.match(de, /Neue Website online/);
});

test('ohne altes Bildschirmfoto: ein Handy, kein Pfeil, ehrliche Beschriftung', () => {
  const h = vnHtml({ betrieb: 'Bar Centrale', sprache: 'it', domain: 'barcentrale.it', nachher: bild, vorher: null });
  assert.equal((h.match(/class="telefon /g) ?? []).length, 1);
  assert.doesNotMatch(h, /class="pfeil"/);
  assert.doesNotMatch(h, />Prima</);
  assert.match(h, /<figcaption>Il nuovo sito<\/figcaption>/);
});

test('Betriebsname und Domain werden maskiert — kein HTML aus der Kundenakte', () => {
  const h = vnHtml({ betrieb: '<script>x</script> & "Söhne"', sprache: 'de', domain: 'a.de"><img', nachher: bild, vorher: null });
  assert.doesNotMatch(h, /<script>x/);
  assert.match(h, /&lt;script&gt;x&lt;\/script&gt; &amp; &quot;Söhne&quot;/);
  assert.match(h, /a\.de&quot;&gt;&lt;img/);
  assert.equal(esc("O'Neil"), 'O&#39;Neil');
});

test('Format 4:5 und Schrift passt sich der Namenslänge an', () => {
  assert.equal(BREITE / HOEHE, 0.8);
  assert.ok(namenGroesse('Bar Roma') > namenGroesse('Ristorante Pizzeria La Bella Napoli dal 1972'));
  assert.ok(namenGroesse('x'.repeat(80)) >= 48);
});
