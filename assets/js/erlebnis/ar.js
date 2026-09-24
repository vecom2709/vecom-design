/* ==========================================================================
   ar.js — Im eigenen Raum ansehen (Wunsch A2, 24.09.2026).

   Zwei Wege, weil es zwei Welten gibt:
   - iPhone/iPad (Safari): AR Quick Look braucht eine USDZ-Datei. Sie
     entsteht hier aus dem geladenen Modell (USDZExporter), im Browser --
     vorab, sobald das Modell da ist, weil Safari den Sprung nach Quick Look
     nur direkt aus dem Fingertipp erlaubt.
   - Android (Chrome mit ARCore): WebXR "immersive-ar" mit Treffertest. Ein
     Ring zeigt, wo der Boden erkannt ist; ein Tipp stellt das Modell hin.
   Maßstab 1:1 -- die Modelle sind in Metern gebaut. Wo beides fehlt (Rechner),
   sagt der Knopf, wie es geht, statt ins Leere zu greifen.
   ========================================================================== */
import * as THREE from 'three';

export const hatQuickLook = (() => { try { return document.createElement('a').relList.supports('ar'); } catch { return false; } })();

export async function hatWebXR() {
  try { return !!(navigator.xr && await navigator.xr.isSessionSupported('immersive-ar')); } catch { return false; }
}

/* Nur Sichtbares, auf den Boden gestellt (tiefster Punkt auf y = 0), mittig */
function vorbereiten(quelle) {
  const k = quelle.clone(true);
  // Raum (Boden, Wände des Küchenplaners) und Hilfskörper bleiben weg
  k.traverse((o) => { if (o.userData && (o.userData.raum || o.userData.keinAR)) o.visible = false; });
  const weg = []; k.traverse((o) => { if (!o.visible && o !== k) weg.push(o); if (o.isLight) weg.push(o); });
  for (const o of weg) o.removeFromParent();
  k.updateMatrixWorld(true);
  const b = new THREE.Box3().setFromObject(k), c = b.getCenter(new THREE.Vector3());
  const huelle = new THREE.Group(); huelle.add(k);
  k.position.sub(new THREE.Vector3(c.x, b.min.y, c.z));
  return huelle;
}

export async function usdzAdresse(quelle) {
  const { USDZExporter } = await import('three/addons/exporters/USDZExporter.js');
  const obj = vorbereiten(quelle);
  const daten = await new USDZExporter().parseAsync(obj, { quickLookCompatible: true, maxTextureSize: 1024 });
  return URL.createObjectURL(new Blob([daten], { type: 'model/vnd.usdz+zip' }));
}

export async function webxrStarten(quelle, texte) {
  const obj = vorbereiten(quelle); obj.visible = false;
  const szene = new THREE.Scene();
  szene.add(new THREE.HemisphereLight(0xffffff, 0x8a8078, 1.6));
  const sonne = new THREE.DirectionalLight(0xffffff, 1.4); sonne.position.set(1, 3, 2); szene.add(sonne);
  szene.add(obj);
  const ring = new THREE.Mesh(new THREE.RingGeometry(0.12, 0.15, 48).rotateX(-Math.PI / 2), new THREE.MeshBasicMaterial({ color: 0xf1d38b }));
  ring.matrixAutoUpdate = false; ring.visible = false; szene.add(ring);
  const kam = new THREE.PerspectiveCamera();
  const r = new THREE.WebGLRenderer({ antialias: true, alpha: true });
  r.xr.enabled = true; r.outputColorSpace = THREE.SRGBColorSpace;
  r.setPixelRatio(window.devicePixelRatio || 1); r.setSize(innerWidth, innerHeight);
  // Hinweis und Schließen als DOM-Overlay über dem Kamerabild
  const ueber = document.createElement('div'); ueber.className = 'ar-ueber';
  const hinweis = document.createElement('p'); hinweis.textContent = texte.suchen;
  const zu = document.createElement('button'); zu.type = 'button'; zu.textContent = texte.zu;
  ueber.append(hinweis, zu); document.body.append(ueber);
  let sitzung;
  try {
    sitzung = await navigator.xr.requestSession('immersive-ar', { requiredFeatures: ['hit-test'], optionalFeatures: ['dom-overlay'], domOverlay: { root: ueber } });
  } catch (e) { ueber.remove(); r.dispose(); throw e; }
  r.xr.setReferenceSpaceType('local');
  await r.xr.setSession(sitzung);
  const blickRaum = await sitzung.requestReferenceSpace('viewer');
  const quelleTreffer = await sitzung.requestHitTestSource({ space: blickRaum });
  sitzung.addEventListener('select', () => {
    if (!ring.visible) return;
    obj.position.setFromMatrixPosition(ring.matrix);
    // zur Kamera gedreht, damit die Vorderseite zum Betrachter zeigt
    const k = new THREE.Vector3(); r.xr.getCamera().getWorldPosition(k);
    obj.rotation.y = Math.atan2(k.x - obj.position.x, k.z - obj.position.z);
    obj.visible = true; hinweis.textContent = texte.steht;
  });
  zu.addEventListener('click', () => sitzung.end());
  sitzung.addEventListener('end', () => { quelleTreffer.cancel(); r.setAnimationLoop(null); r.dispose(); ueber.remove(); });
  r.setAnimationLoop((t, frame) => {
    if (frame) {
      const treffer = frame.getHitTestResults(quelleTreffer);
      if (treffer.length) {
        const pose = treffer[0].getPose(r.xr.getReferenceSpace());
        ring.visible = true; ring.matrix.fromArray(pose.transform.matrix);
        if (!obj.visible) hinweis.textContent = texte.tippen;
      } else ring.visible = false;
    }
    r.render(szene, kam);
  });
}

/* Ein Tipp auf "Im eigenen Raum ansehen". zustand merkt sich die fertige
   USDZ-Datei zwischen zwei Tipps (iPhone: Der erste baut sie, der zweite
   öffnet Quick Look -- Safari lässt den Sprung nur direkt aus einem Tipp
   zu, nicht nach einer Wartezeit). zustand.url = null, wenn sich das Modell
   geändert hat. */
export async function ausloesen(zustand, knopf, q, T, melden) {
  if (!q) { melden(T.laden); return; }
  if (hatQuickLook) {
    if (zustand.url) {
      let a = zustand.a;
      if (!a) { a = zustand.a = document.createElement('a'); a.rel = 'ar'; a.hidden = true; a.append(document.createElement('img')); document.body.append(a); }
      a.href = zustand.url; a.click(); return;
    }
    zustand.text = zustand.text || knopf.textContent;
    knopf.textContent = T.vorbereiten; knopf.disabled = true;
    try { zustand.url = await usdzAdresse(q); knopf.textContent = T.oeffnen; } catch (e) { console.warn('AR:', e); knopf.textContent = zustand.text; melden(T.fehler); }
    knopf.disabled = false; return;
  }
  if (await hatWebXR()) {
    try { await webxrStarten(q, T); } catch (e) { console.warn('AR:', e); melden(T.fehler); }
    return;
  }
  melden(T.handy);
}
export function veraltet(zustand, knopf) {
  if (zustand.url) URL.revokeObjectURL(zustand.url);
  zustand.url = null; if (zustand.text) knopf.textContent = zustand.text;
}
