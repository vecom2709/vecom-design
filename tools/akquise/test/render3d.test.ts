/* 3D auf dem PC (Marketing-Studio 11, 01.10.2026): Format, Auftrag fürs Blender-Skript, Bericht, Prüfung von Claudes Szene. */
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { pixel, blenderAuftrag, berichtText, type DreiDAuftrag } from '../src/ki/render3d.js';
import { szenePruefen, szeneText, SCHEMA_SZENE } from '../src/ki/szene3d.js';

const basis = { id: 9, art: 'medien' as const, beschreibung: 'Bild · Test', medium: 'bild' as const, modell: 'blender', format: '4:5', prompt: 'A set table at sunset in a Sicilian trattoria',
  startbild: null, credits_ca: 0, teil_bytes: 3145728, max_bytes: 62914560,
  drei_d: { studio: 'gastro', generativ: false, seed: 42, sprache: 'it', film_titel: 'Il suo ristorante', abspann: 'vecom-design.it' } } satisfies DreiDAuftrag;

test('Format: kurze Kante 1080, Hochformat für Reels', () => {
  assert.equal(pixel('4:5', 'bild'), '1080x1350');
  assert.equal(pixel('9:16', 'video'), '1080x1920');
  assert.equal(pixel('16:9', 'video'), '1920x1080');
  assert.equal(pixel('quatsch', 'video'), '1080x1920');
});

test('Auftrag fürs Blender-Skript: Bild ohne Filmtexte, Film mit Titel, Abspann und 8 Sekunden', () => {
  const b = blenderAuftrag(basis, 'C:/x/mk-9.png');
  assert.deepEqual(b, { px: '1080x1350', seed: 42, aus: 'C:/x/mk-9.png' });
  const f = blenderAuftrag({ ...basis, medium: 'video', format: '9:16' }, 'C:/x/mk-9.mp4');
  assert.equal(f.titel, 'Il suo ristorante');
  assert.equal(f.abspann, 'vecom-design.it');
  assert.equal(f.sekunden, 8);
  assert.equal(f.px, '1080x1920');
});

test('Bericht: Szene, Variante, Zeit, gemessene Belichtung — und „ohne Credits“', () => {
  const t = berichtText({ was: 'gastro', variante: 'Terrakotta', sekunden: 46.3, mittel: 0.395, korrektur_ev: 0 }, false);
  assert.equal(t, 'Blender-Bild · Szene gastro · Variante Terrakotta · 46 s gerechnet · Belichtung gemessen 0,395 · ohne Credits');
  assert.match(berichtText({ was: 'wein', probe_mittel: 0.17, korrektur_ev: 0.4 }, true), /^Blender-Film · Szene wein · Belichtung gemessen 0,17 · nachgeführt \+0,4 EV/);
  assert.equal(berichtText(null, true), 'Blender-Film fertig');
});

test('Claudes Szene: nur bauen — kein import, open, exec, Rendern; Kamera Pflicht', () => {
  const gut = "hdri('comfy_cafe_4k.exr')\nboden()\nbpy.ops.mesh.primitive_cube_add(size=1)\nkamera((1,-2,1.5),(0,0,0.8))\n";
  assert.equal(szenePruefen(gut), null);
  assert.match(szenePruefen('import os\n' + gut) ?? '', /unerlaubter/);
  assert.match(szenePruefen(gut + "open('x')") ?? '', /unerlaubter/);
  assert.match(szenePruefen(gut + 'bpy.ops.render.render()') ?? '', /unerlaubter/);
  assert.match(szenePruefen(gut + '__import__("os")') ?? '', /unerlaubter/);
  assert.equal(szenePruefen("hdri('x')\nboden()"), 'keine Kamera');
});

test('Auftrag an Claude: Bildidee, Format, Bausteine und Realismus-Regeln', () => {
  const t = szeneText({ ...basis, drei_d: { ...basis.drei_d, studio: null, generativ: true } });
  assert.match(t, /Sicilian trattoria/);
  assert.match(t, /1080x1350/);
  assert.match(t, /holz\(name/);
  assert.match(t, /comfy_cafe_4k\.exr/);
  assert.match(t, /Keine Schrift, keine Logos, keine Menschen/);
  assert.match(t, /nie schwebend/);
  assert.deepEqual(SCHEMA_SZENE.required, ['skript', 'beschreibung']);
});
