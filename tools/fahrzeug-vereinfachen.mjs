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
// Innenraum und Glas fuer die Echtzeit abdunkeln: three.js beleuchtet den
// Innenraum mit der vollen Umgebung (keine Verdeckung durch das Dach wie in
// Cycles) -- die Sitze standen im ersten Test hellgrau hinter der Scheibe.
const DUNKLER = { 'Seat Fabric': 0.35, 'Interior Dark': 0.35, 'Headliner': 0.3, 'Leather Dark': 0.5, 'Glass': 0.62 };
for (const m of doc.getRoot().listMaterials()) {
  const f = DUNKLER[m.getName()]; if (!f) continue;
  const c = m.getBaseColorFactor(); m.setBaseColorFactor([c[0] * f, c[1] * f, c[2] * f, c[3]]);
}
await io.write(aus, doc);
console.log(JSON.stringify({ vor, nach }));
