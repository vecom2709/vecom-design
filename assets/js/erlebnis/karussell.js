/* Branchen-Karussell (04.10.2026, Uwe: "nur das 3D-Karussell selbst, kein Raum
   dahinter"). Die Demo-Kacheln aus #demos stehen auf einem echten Kreis:
   jede Karte hat einen Winkel, rotateY + translateZ setzen sie auf den Ring,
   die vordere steht frontal. Kein Three.js -- zwanzig Karten sind DOM, die GPU
   setzt nur Transforms zusammen (eine Ebene je Karte, keine Filter pro Bild).

   Verantwortung:
   - erlebnis.js öffnet die Bühnen (Klick auf eine Kachel) -- unverändert.
   - Diese Datei dreht nur. Ein Klick auf eine hintere Karte holt sie nach
     vorn und wird abgefangen (Capture), damit erlebnis.js nichts öffnet;
     erst die vordere Karte öffnet ihre Demo.
   - Ohne dieses Skript bleibt die Kachelreihe ein Raster (CSS ohne .ist-karussell).

   Bewegung: eine Feder (kritisch gedämpft) zieht die Lage auf die Zielkarte.
   Ziehen und Wischen geben Schwung, der ausläuft und auf der nächsten Karte
   einrastet. Von selbst rückt der Ring alle 5 s einen Platz weiter (20 Plätze
   = eine Umdrehung in 100 s) und hält an, sobald jemand eingreift, eine Demo
   offen ist, die Maus über dem Ring steht, der Bereich nicht sichtbar ist oder
   der Tab im Hintergrund liegt.
   prefers-reduced-motion: keine Eigenbewegung, Wechsel ohne Fahrt. */

const demos = document.getElementById('demos');
const reihe = demos && demos.querySelector('.demos__reihe');
const huelle = demos && demos.querySelector('[data-karussell]');

if (demos && reihe && huelle && CSS.supports('transform-style', 'preserve-3d')) {
  const RUHIG = matchMedia('(prefers-reduced-motion: reduce)');
  const urKarten = [...reihe.querySelectorAll('.demo-kachel')];
  const D = urKarten.length;             // Zahl der Demos
  /* Ein geschlossener Ring wie in der Vorlage braucht mehr Plätze, als es Demos
     gibt -- mit zehn Karten wird er zur engen Trommel, vorne stünden nur drei.
     Darum steht jede Demo zweimal auf dem Ring, genau gegenüber (180°): nie
     zweimal zugleich vorne, die Kopie ist für Vorleser und Tastatur unsichtbar. */
  if (D < 14) for (const li of [...reihe.children]) {
    const kopie = li.cloneNode(true); kopie.setAttribute('aria-hidden', 'true'); kopie.classList.add('ist-kopie');
    const b = kopie.querySelector('.demo-kachel'); b.tabIndex = -1; b.removeAttribute('aria-expanded');
    reihe.appendChild(kopie);
  }
  const karten = [...reihe.querySelectorAll('.demo-kachel')];
  const plaetze = karten.map((k) => k.closest('li'));
  const N = karten.length;               // Plätze auf dem Ring
  const SCHRITT = 360 / N;               // Grad zwischen zwei Plätzen
  const VERWEILEN = 5000;                // ms je Platz im Selbstlauf: 20 Plätze = eine Umdrehung in 100 s
  const PAUSE_NACH_EINGRIFF = 7000;      // ms Ruhe nach Ziehen, Pfeil, Taste
  const kategorien = [...demos.querySelectorAll('.karussell__kat')];

  let lage = 0;          // Kartenindex als Kommazahl; ganzzahlig = Karte frontal
  let tempo = 0;         // Karten je Sekunde
  let ziel = 0;          // ganzzahliger Zielindex (nicht umgebrochen)
  let federHart = 2.8;   // Kreisfrequenz der Feder; Selbstlauf weich, Eingriff straffer
  let radius = 500, kartenBreite = 300, pxJeKarte = 260;
  let ziehen = null;     // { x, lage, t, v }
  let klickSperre = false;
  let ruheBis = 0, naechsterSchritt = performance.now() + VERWEILEN;
  let sichtbar = false, laeuft = false, letzte = 0;
  let selbstlauf = true; // "Alle Branchen" aktiv
  let schwebt = false;   // Maus über dem Ring: Selbstlauf wartet (wer liest, will keine Bewegung)

  demos.classList.add('ist-karussell');
  reihe.setAttribute('aria-roledescription', 'Karussell');

  const umbrechen = (a) => ((a % N) + N) % N;
  const vorne = () => umbrechen(Math.round(lage));

  /* Maße aus der verfügbaren Breite. Der Umfang ist N Karten plus Fuge,
     daraus der Radius. Telefon: eine große Karte, Nachbarn angeschnitten. */
  function vermessen() {
    const w = huelle.clientWidth || innerWidth;
    if (w < 600) kartenBreite = Math.min(w * 0.56, 250);
    else if (w < 1024) kartenBreite = Math.min(w * 0.24, 250);
    else kartenBreite = Math.max(210, Math.min(w * 0.152, 290));
    radius = (kartenBreite * 1.12) / (2 * Math.tan(Math.PI / N));
    pxJeKarte = radius * (SCHRITT * Math.PI / 180) * 0.92;
    const hoehe = kartenBreite * 1.38;
    huelle.style.setProperty('--kw', kartenBreite.toFixed(1) + 'px');
    huelle.style.setProperty('--kh', hoehe.toFixed(1) + 'px');
    huelle.style.setProperty('--r', radius.toFixed(1) + 'px');
    huelle.style.setProperty('--persp', Math.max(1200, radius * 2.7).toFixed(0) + 'px');
    zeichnen();
  }

  /* Je Karte: Winkel relativ zur Front. Hintere Karten (|w| > 90°) werden um
     180° gewendet, damit man ihr Bild sieht statt ihrer Rückseite -- wie in der
     Vorlage. Bei genau 90° steht die Karte auf der Kante, der Wechsel ist
     unsichtbar. Helligkeit über eine Abdunkel-Ebene (opacity), keine Filter. */
  function zeichnen() {
    for (let i = 0; i < N; i++) {
      let a = umbrechen(i - lage); if (a > N / 2) a -= N;
      const w = a * SCHRITT, aw = Math.abs(w);
      const aktiv = Math.max(0, 1 - Math.abs(a));
      const s = 1 + 0.2 * Math.max(0, 1 - Math.abs(a) / 1.15);
      const hinten = aw > 90;
      const dunkel = aw <= 18 ? 0.06 * (aw / 18) : aw <= 90 ? 0.06 + 0.16 * ((aw - 18) / 72) : 0.6 + 0.16 * Math.min(1, (aw - 90) / 72);
      const el = plaetze[i];
      el.style.transform = `rotateY(${w.toFixed(3)}deg) translateZ(${radius.toFixed(1)}px)${hinten ? ' rotateY(180deg)' : ''} scale(${s.toFixed(4)})`;
      el.style.setProperty('--dunkel', dunkel.toFixed(3));
      el.style.setProperty('--aktiv', aktiv.toFixed(3));
      el.classList.toggle('ist-vorne', Math.abs(a) < 0.5);
      // Hintere Karten nicht anklickbar machen, damit sie die vorderen nicht verdecken
      el.style.pointerEvents = aw > 100 ? 'none' : '';
    }
  }

  function bewegen(t) {
    if (!laeuft) return;
    const dt = Math.min(0.05, (t - letzte) / 1000 || 0.016); letzte = t;
    if (!ziehen) {
      if (selbstlauf && !RUHIG.matches && !schwebt && t > ruheBis && t > naechsterSchritt && !demoOffen()) {
        ziel = Math.round(ziel) + 1; federHart = 2.4; naechsterSchritt = t + VERWEILEN;
      }
      // kritisch gedämpfte Feder: kein Überschwingen, weiches Ankommen
      const w = federHart;
      tempo += (w * w * (ziel - lage) - 2 * w * tempo) * dt;
      lage += tempo * dt;
      if (Math.abs(ziel - lage) < 0.0005 && Math.abs(tempo) < 0.001) { lage = ziel; tempo = 0; }
    }
    zeichnen();
    kategorieMarkieren();
    requestAnimationFrame(bewegen);
  }
  function starten() { if (laeuft || !sichtbar || document.hidden) return; laeuft = true; letzte = performance.now(); requestAnimationFrame(bewegen); }
  function anhalten() { laeuft = false; }

  const demoOffen = () => urKarten.some((k) => k.getAttribute('aria-expanded') === 'true');
  function eingriff() { ruheBis = performance.now() + PAUSE_NACH_EINGRIFF; naechsterSchritt = ruheBis + VERWEILEN * 0.4; }

  /* Zu Karte i -- auf dem kürzeren Weg um den Ring. */
  function geheZu(i, { hart = 6, sofort = RUHIG.matches } = {}) {
    let d = umbrechen(i - Math.round(ziel)); if (d > N / 2) d -= N;
    ziel = Math.round(ziel) + d; federHart = hart;
    if (sofort) { lage = ziel; tempo = 0; zeichnen(); kategorieMarkieren(); }
    starten();
  }
  function schritt(r) { eingriff(); geheZu(umbrechen(Math.round(ziel) + r)); }

  /* Ziehen und Wischen (Zeiger-Ereignisse decken Maus, Stift und Finger ab).
     touch-action: pan-y im CSS lässt senkrechtes Scrollen der Seite durch. */
  huelle.addEventListener('pointerdown', (e) => {
    if (e.button !== 0 || e.target.closest('.karussell__pfeil')) return;
    ziehen = { x: e.clientX, y: e.clientY, lage, t: performance.now(), v: 0, weg: 0, id: e.pointerId, gefangen: false };
    tempo = 0; klickSperre = false;
  });
  huelle.addEventListener('pointermove', (e) => {
    if (!ziehen || e.pointerId !== ziehen.id) return;
    const dx = e.clientX - ziehen.x;
    ziehen.weg = Math.max(ziehen.weg, Math.abs(dx));
    if (!ziehen.gefangen && Math.abs(dx) > 6 && Math.abs(dx) > Math.abs(e.clientY - ziehen.y)) {
      ziehen.gefangen = true; huelle.setPointerCapture(e.pointerId); huelle.classList.add('ist-gezogen'); eingriff();
    }
    if (!ziehen.gefangen) return;
    const t = performance.now(), neu = ziehen.lage - dx / pxJeKarte;
    const v = (neu - lage) / Math.max(0.008, (t - ziehen.t) / 1000);
    ziehen.v = ziehen.v * 0.6 + v * 0.4; ziehen.t = t;
    lage = neu; ziel = lage; starten();
  });
  function loslassen(e) {
    if (!ziehen || (e && e.pointerId !== ziehen.id)) return;
    const z = ziehen; ziehen = null; huelle.classList.remove('ist-gezogen');
    if (!z.gefangen) return;
    klickSperre = z.weg > 6;
    // Trägheit: Schwung läuft aus, dann rastet die nächste Karte ein
    const v = Math.max(-6, Math.min(6, z.v));
    tempo = v; ziel = Math.round(lage + v * 0.32); federHart = 5;
    eingriff(); starten();
  }
  huelle.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse') schwebt = true; });
  huelle.addEventListener('pointerleave', (e) => { if (e.pointerType === 'mouse') { schwebt = false; naechsterSchritt = performance.now() + VERWEILEN * 0.5; } });
  huelle.addEventListener('pointerup', loslassen);
  // Bilder nicht als Datei ziehen lassen -- sonst bricht der Browser das Ziehen ab (pointercancel)
  huelle.addEventListener('dragstart', (e) => e.preventDefault());
  for (const img of reihe.querySelectorAll('img')) img.draggable = false;
  huelle.addEventListener('pointercancel', loslassen);

  /* Klicks: nach dem Ziehen keiner; auf eine hintere Karte -> nach vorn holen.
     Capture-Phase, damit erlebnis.js (lauscht an #demos) davon nichts merkt. */
  reihe.addEventListener('click', (e) => {
    const k = e.target.closest('.demo-kachel'); if (!k) return;
    if (klickSperre) { klickSperre = false; e.preventDefault(); e.stopPropagation(); return; }
    const i = karten.indexOf(k);
    if (i !== vorne() || Math.abs(lage - Math.round(lage)) > 0.08) { e.preventDefault(); e.stopPropagation(); eingriff(); geheZu(i); }
  }, true);

  /* Mausrad: nur seitliches Rollen (Trackpad), senkrecht bleibt Seitenscroll. */
  let radSumme = 0, radZeit = 0;
  huelle.addEventListener('wheel', (e) => {
    if (Math.abs(e.deltaX) <= Math.abs(e.deltaY)) return;
    e.preventDefault(); radSumme += e.deltaX;
    const t = performance.now();
    if (Math.abs(radSumme) > 60 && t - radZeit > 380) { schritt(Math.sign(radSumme)); radSumme = 0; radZeit = t; }
  }, { passive: false });

  /* Tastatur: Pfeile drehen und legen den Fokus auf die neue vordere Karte;
     wer mit Tab auf eine Karte springt, bekommt sie nach vorn gedreht. */
  huelle.addEventListener('keydown', (e) => {
    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
    e.preventDefault(); schritt(e.key === 'ArrowRight' ? 1 : -1);
    if (e.target.closest('.demo-kachel')) urKarten[umbrechen(Math.round(ziel)) % D].focus({ preventScroll: true });
  });
  reihe.addEventListener('focusin', (e) => {
    const k = e.target.closest('.demo-kachel'); if (!k) return;
    const i = karten.indexOf(k); if (i % D !== umbrechen(Math.round(ziel)) % D) { eingriff(); geheZu(naechsterPlatz(i % D)); }
  });

  huelle.querySelector('[data-karussell-zurueck]')?.addEventListener('click', () => schritt(-1));
  huelle.querySelector('[data-karussell-weiter]')?.addEventListener('click', () => schritt(1));

  /* Kategorien unten: "Alle" lässt den Ring wieder selbst laufen, jede andere
     dreht zur ersten Karte ihrer Gruppe; ein zweiter Klick geht in der Gruppe weiter. */
  const gruppe = (kat) => urKarten.map((k, i) => (k.dataset.kategorie === kat ? i : -1)).filter((i) => i >= 0);
  // Platz einer Demo, der vom jetzigen Ziel aus am nächsten liegt (Original oder Kopie)
  const naechsterPlatz = (d) => { let best = d, bd = 1e9; for (let p = d; p < N; p += D) { let x = umbrechen(p - Math.round(ziel)); if (x > N / 2) x -= N; if (Math.abs(x) < bd) { bd = Math.abs(x); best = p; } } return best; };
  let gewaehlt = 'alle';
  for (const b of kategorien) b.addEventListener('click', () => {
    const kat = b.dataset.kategorie;
    if (kat === 'alle') { selbstlauf = true; gewaehlt = 'alle'; ruheBis = 0; naechsterSchritt = performance.now() + 1200; kategorieMarkieren(true); starten(); return; }
    const g = gruppe(kat); if (!g.length) return;
    const jetzt = umbrechen(Math.round(ziel)) % D;
    const n = gewaehlt === kat && g.includes(jetzt) ? g[(g.indexOf(jetzt) + 1) % g.length] : g[0];
    selbstlauf = false; gewaehlt = kat; eingriff(); geheZu(naechsterPlatz(n), { hart: 3.4 }); kategorieMarkieren(true);
  });
  let markiert = null;
  function kategorieMarkieren(erzwingen) {
    const kat = selbstlauf && gewaehlt === 'alle' ? 'alle' : (karten[vorne()].dataset.kategorie || 'alle');
    if (kat === markiert && !erzwingen) return;
    markiert = kat; if (!selbstlauf) gewaehlt = kat;
    for (const b of kategorien) b.setAttribute('aria-pressed', String(b.dataset.kategorie === kat));
  }
  // Eingriffe beenden den Selbstlauf nicht, sie pausieren ihn nur -- außer eine Kategorie ist gewählt.

  new IntersectionObserver((es) => { sichtbar = es[0].isIntersecting; sichtbar ? starten() : anhalten(); }, { rootMargin: '120px 0px' }).observe(huelle);
  document.addEventListener('visibilitychange', () => (document.hidden ? anhalten() : starten()));
  new ResizeObserver(() => vermessen()).observe(huelle);
  vermessen(); kategorieMarkieren(true);
  /* Bilder: Im Ring liegen alle Karten an derselben Stelle im Layout, das
     eingebaute Lazy-Loading erkennt die gedrehten nicht als sichtbar. Darum
     laden alle zehn Fotos (je 30-90 KB), sobald der Ring 600 px vor dem Bild
     ist -- Kopien teilen sich die Datei mit dem Original. Die vordere zuerst. */
  const laden = new IntersectionObserver((es) => {
    if (!es[0].isIntersecting) return; laden.disconnect();
    plaetze.forEach((li, i) => { const img = li.querySelector('img'); if (!img) return; if (i === 0) img.fetchPriority = 'high'; img.loading = 'eager'; });
  }, { rootMargin: '600px 0px' });
  laden.observe(huelle);
}
