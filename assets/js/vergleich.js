/* ==========================================================================
   vergleich.js — "Derselbe Betrieb, zwei Seiten".

   Ein Schieber, der zwei Entwürfe übereinanderlegt. Der Abschnitt
   „Echtzeit statt Standbild" argumentiert bis dahin mit Worten; hier sieht
   man den Unterschied in einer Sekunde.

   Der Regler ist ein echtes <input type="range">. Tastatur, Screenreader
   und Touch funktionieren damit ohne eine Zeile Zusatzcode — und der
   sichtbare Griff ist reine Dekoration darüber. Ein nachgebauter Schieber
   aus <div> und pointermove hätte all das kosten müssen.

   Kein three.js, kein Bild: beide Seiten sind CSS. Der ganze Abschnitt
   kostet die Seite nichts.
   ========================================================================== */
(function () {
  'use strict';

  const wurzel = document.querySelector('[data-vergleich]');
  if (!wurzel) return;

  const feld = wurzel.querySelector('[data-feld]');
  const regler = wurzel.querySelector('[data-regler]');
  if (!feld || !regler) return;

  const setzen = (v) => feld.style.setProperty('--schnitt', v + '%');
  setzen(regler.value);
  regler.addEventListener('input', () => setzen(regler.value));

  /* Ziehen direkt auf der Fläche. Ein <input range> springt sonst beim
     ersten Klick auf den Wert unter dem Zeiger und zieht erst danach mit —
     das fühlt sich an wie ein Ruckler, obwohl keiner da ist. */
  let zieht = false;
  let angefasst = false;

  function ausZeiger(e) {
    const r = feld.getBoundingClientRect();
    const v = Math.max(0, Math.min(100, ((e.clientX - r.left) / r.width) * 100));
    regler.value = v;
    setzen(v);
  }

  feld.addEventListener('pointerdown', (e) => {
    zieht = true;
    angefasst = true;
    try { feld.setPointerCapture(e.pointerId); } catch (_) { /* egal */ }
    ausZeiger(e);
  });
  feld.addEventListener('pointermove', (e) => { if (zieht) ausZeiger(e); });
  feld.addEventListener('pointerup', () => { zieht = false; });
  feld.addEventListener('pointercancel', () => { zieht = false; });
  regler.addEventListener('keydown', () => { angefasst = true; });

  /* EINE EINZIGE BEWEGUNG, EINMAL
     ------------------------------------------------------------------
     Kommt der Vergleich zum ersten Mal ins Bild, fährt der Griff einmal
     von rechts zur Mitte. Ohne das sieht die Fläche aus wie ein Bild, und
     niemand fasst sie an — mit einer Dauerschleife dagegen sieht sie aus
     wie Werbung.

     Wer vorher selbst zieht, bekommt die Vorführung nicht mehr: Er hat
     schon verstanden, worum es geht. */
  const ruhig = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let gelaufen = ruhig;

  function vorfuehren() {
    if (gelaufen || angefasst) return;
    gelaufen = true;
    const anfang = performance.now();
    const von = 90, bis = 50, dauer = 1500;
    (function schritt(t) {
      if (angefasst) return;
      const k = Math.min(1, (t - anfang) / dauer);
      const v = von + (bis - von) * (1 - Math.pow(1 - k, 3));
      regler.value = v;
      setzen(v);
      if (k < 1) requestAnimationFrame(schritt);
    })(anfang);
  }

  if (!gelaufen && 'IntersectionObserver' in window) {
    const beob = new IntersectionObserver((e) => {
      if (e[0].isIntersecting) { vorfuehren(); beob.disconnect(); }
    }, { threshold: 0.45 });
    beob.observe(feld);
  }
})();
