// Vereinfacht die Echtzeitfassung eines Serienautos je Teil unterschiedlich
// stark: Blechteile (Spiegelungen verraten jede Delle) mit 0,01 %, alles
// andere mit 0,04 % Abweichung vom Teilradius. Raender bleiben fest, sonst
// oeffnen sich die Fugen. Aufruf aus tools/fahrzeug-web.py:
//   node tools/fahrzeug-vereinfachen.mjs <ein.glb> <aus.glb>
// GLTF_MODULE_DIR zeigt auf ein node_modules mit @gltf-transform/* und meshoptimizer.
import path from 'node:path';
const M = process.env.GLTF_MODULE_DIR || '/home/claude/demos/npm/node_modules';
const imp = (p) => import(path.join(M, p));
const { NodeIO } = await imp('@gltf-transform/core/dist/index.js');
const { ALL_EXTENSIONS } = await imp('@gltf-transform/extensions/dist/index.js');
const F = await imp('@gltf-transform/functions/dist/index.js');
const { MeshoptSimplifier } = await imp('meshoptimizer/index.js');
await MeshoptSimplifier.ready;
const [ein, aus] = process.argv.slice(2);
const io = new NodeIO().registerExtensions(ALL_EXTENSIONS);
const doc = await io.read(ein);
const BLECH = /^(karosserie|haube|klappe|klappe_spoiler|stoss_[vh]|tuer_[vh]_[lr]|frontscheibe|boden)$/;
let vor = 0, nach = 0;
for (const node of doc.getRoot().listNodes()) {
  const mesh = node.getMesh(); if (!mesh) continue;
  const fehler = BLECH.test(node.getName()) ? 0.0001 : 0.0004;
  for (const prim of mesh.listPrimitives()) {
    vor += prim.getIndices().getCount() / 3;
    F.simplifyPrimitive(prim, { simplifier: MeshoptSimplifier, ratio: 0, error: fehler, lockBorder: true });
    nach += prim.getIndices().getCount() / 3;
  }
}
// Glas fuer die Echtzeit abdunkeln (three.js ohne Transmission). Der
// Innenraum wurde bis 23.09.2026 hier pauschal abgedunkelt; seitdem bringt
// er seine Verdeckung als gebackene Punktfarbe mit (fahrzeug_bau.py, AO).
const DUNKLER = { 'Glass': 0.62 };
for (const m of doc.getRoot().listMaterials()) {
  const f = DUNKLER[m.getName()]; if (!f) continue;
  const c = m.getBaseColorFactor(); m.setBaseColorFactor([c[0] * f, c[1] * f, c[2] * f, c[3]]);
}
// Texturen: WebP, Stoffkarten 512 px (eine Kachel sind 5-8 cm -- mehr sieht
// man im Browser nicht), Displays 1024 px (Schrift muss lesbar bleiben),
// Reifenflanke 2048 x 256 (die Karte laeuft einmal um den Reifen; bei 512
// waeren die 15-mm-Buchstaben 4 px breit und nur noch Rauschen).
const sharp = (await import(path.join(M, 'sharp/dist/index.cjs'))).default;
// Produktdemos (tools/produkt-web.py) geben ihre Regeln mit: [[Muster, Kante], ...]
const REGELN = JSON.parse(process.env.TEXTUR_REGELN || '[]');
if (REGELN.length) {
  await doc.transform(...REGELN.map(([m, k]) => F.textureCompress({ encoder: sharp, targetFormat: 'webp', resize: [k, k], pattern: new RegExp(m, 'i'), quality: 86 })));
} else await doc.transform(
  F.textureCompress({ encoder: sharp, targetFormat: 'webp', resize: [1024, 1024], pattern: /display/i, quality: 88 }),
  F.textureCompress({ encoder: sharp, targetFormat: 'webp', resize: [2048, 2048], pattern: /reifen/i, quality: 90 }),
  // Positiv benennen, nicht per Ausschluss: textureCompress prueft Name ODER
  // URI, und die leere URI eingebetteter Bilder passt auf jedes /^(?!...)/ --
  // so wurden bis 23.09.2026 auch Displays und Reifen auf 512 px gestaucht.
  F.textureCompress({ encoder: sharp, targetFormat: 'webp', resize: [512, 512], pattern: /^(innen|neu|lack)-/i, quality: 82 }),
);
await io.write(aus, doc);
console.log(JSON.stringify({ vor, nach }));
