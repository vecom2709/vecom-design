/* ==========================================================================
   ERZEUGT — NICHT VON HAND AENDERN.

   Zusammengesetzt aus (in dieser Reihenfolge):
     assets/js/screens.js
     assets/js/schau.js
     assets/js/polish.js
     assets/js/pakete-live.js
     assets/js/stimmen-live.js
     assets/js/rail.js
     assets/js/vids.js
     assets/js/depth.js
     assets/js/sig.js
     assets/js/social.js

   Geaendert wird an diesen Dateien. Diese hier entsteht bei jedem Deploy
   neu (build.mjs) und wird ueberschrieben.
   ========================================================================== */

/* ----- screens.js ----- */
/* ==========================================================================
   screens.js — Vorführung echter Projektseiten.

   Die Bilder sind keine Montagen: Es sind Vollseiten-Aufnahmen der wirklich
   laufenden Seiten. Hier bewegt sich ein Zeiger darüber, scrollt, klickt und
   "lädt" — die Anmutung einer Bildschirmaufnahme, aber als leichtes Bild statt
   als Video (ein Video derselben Länge wöge das Zwanzigfache).

   Regeln:
   - läuft nur, wenn die Karte im Bild ist (IntersectionObserver)
   - hält im Hintergrund-Tab an
   - bei prefers-reduced-motion: Standbild, kein Zeiger
   - der Inhalt der Karte steht im DOM und ist ohne all das vollständig
   ========================================================================== */
(function () {
  'use strict';

  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Ein Drehbuch für alle Vorführungen: scrollen, zielen, klicken, laden.
  // Werte sind Anteile (0–1) der Seitenhöhe bzw. der Rahmenbreite/-höhe.
  var SCRIPT = [
    { a: 'wait',   t: 700 },
    { a: 'scroll', to: 0.22, t: 2600 },
    { a: 'move',   x: 0.62, y: 0.10, t: 1000 },
    { a: 'click' },
    { a: 'load',   t: 700 },
    { a: 'scroll', to: 0.46, t: 2400 },
    { a: 'move',   x: 0.30, y: 0.64, t: 900 },
    { a: 'click' },
    { a: 'load',   t: 600 },
    { a: 'scroll', to: 0.74, t: 2800 },
    { a: 'move',   x: 0.72, y: 0.42, t: 900 },
    { a: 'scroll', to: 0.97, t: 2400 },
    { a: 'wait',   t: 1100 },
    { a: 'scroll', to: 0.00, t: 900 },
    { a: 'move',   x: 0.5,  y: 0.5,  t: 700 }
  ];

  var easeInOut = function (t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; };

  function Screen(el) {
    this.el = el;
    this.page = el.querySelector('.screen__page');
    this.cursor = el.querySelector('.screen__cursor');
    this.bar = el.querySelector('.screen__progress');
    this.view = el.querySelector('.screen__viewport');
    this.i = 0;
    this.t0 = 0;
    this.from = { y: 0, x: 0.5, cy: 0.5 };
    this.state = { y: 0, x: 0.5, cy: 0.5 };
    this.running = false;
    this.raf = null;
  }

  Screen.prototype.maxScroll = function () {
    var pageH = this.page.getBoundingClientRect().height;
    var viewH = this.view.getBoundingClientRect().height;
    return Math.max(0, pageH - viewH);
  };

  Screen.prototype.apply = function () {
    this.page.style.transform = 'translate3d(0,' + (-this.state.y * this.maxScroll()).toFixed(2) + 'px,0)';
    if (this.cursor) {
      this.cursor.style.transform =
        'translate3d(' + (this.state.x * 100).toFixed(2) + 'cqw,' + (this.state.cy * 100).toFixed(2) + 'cqh,0)';
    }
  };

  Screen.prototype.step = function () {
    var s = SCRIPT[this.i % SCRIPT.length];
    this.t0 = performance.now();
    this.from = { y: this.state.y, x: this.state.x, cy: this.state.cy };

    if (s.a === 'click') {
      this.el.classList.add('is-clicking');
      var self = this;
      setTimeout(function () { self.el.classList.remove('is-clicking'); }, 420);
      this.i++;
      return this.step();
    }
    if (s.a === 'load') {
      this.el.classList.add('is-loading');
      var me = this;
      setTimeout(function () { me.el.classList.remove('is-loading'); }, s.t);
    }
    this.cur = s;
  };

  Screen.prototype.frame = function (now) {
    if (!this.running) return;
    var s = this.cur;
    var p = s.t ? Math.min(1, (now - this.t0) / s.t) : 1;
    var e = easeInOut(p);

    if (s.a === 'scroll') this.state.y = this.from.y + (s.to - this.from.y) * e;
    if (s.a === 'move') {
      this.state.x = this.from.x + (s.x - this.from.x) * e;
      this.state.cy = this.from.cy + (s.y - this.from.cy) * e;
    }
    this.apply();

    if (p >= 1) { this.i++; this.step(); }
    this.raf = requestAnimationFrame(this.frame.bind(this));
  };

  Screen.prototype.start = function () {
    if (this.running || reduced) return;
    this.running = true;
    this.el.classList.add('is-playing');
    this.step();
    this.raf = requestAnimationFrame(this.frame.bind(this));
  };

  Screen.prototype.stop = function () {
    this.running = false;
    this.el.classList.remove('is-playing');
    if (this.raf) cancelAnimationFrame(this.raf);
    this.raf = null;
  };

  var screens = [].map.call(document.querySelectorAll('[data-screen]'), function (el) { return new Screen(el); });
  if (!screens.length) return;

  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        var s = screens.find(function (x) { return x.el === en.target; });
        if (!s) return;
        if (en.isIntersecting && !document.hidden) s.start(); else s.stop();
      });
    }, { rootMargin: '10% 0px', threshold: 0.3 });
    screens.forEach(function (s) { io.observe(s.el); });
  }

  document.addEventListener('visibilitychange', function () {
    screens.forEach(function (s) { if (document.hidden) s.stop(); });
  });

  // Antippen/Klicken hält an oder setzt fort — wer genau hinsehen will, soll das können.
  screens.forEach(function (s) {
    s.el.addEventListener('click', function () {
      if (s.running) s.stop(); else s.start();
    });
  });
})();


/* ----- schau.js ----- */
/* ==========================================================================
   schau.js — Zwei Karussells, ein Zeiger.

   Oben die Notebooks, unten die Telefone, beide zeigen dasselbe Projekt. Das
   vordere Geraet kommt auf einen zu, die anderen weichen zurueck und drehen
   sich zur Mitte. Faehrt man mit der Maus darueber, dreht sich der Koerper —
   und weil es ein Koerper ist und kein gekipptes Bild, stimmt die Perspektive:
   man sieht die Deckelkante auf der richtigen Seite.

   Regeln wie ueberall auf dieser Seite:
   - nur das vordere Geraet laeuft; die anderen zeigen ein Standbild
   - die schweren Aufnahmen und Filme laden erst, wenn ein Geraet vorn steht
   - haelt an, sobald die Schau aus dem Bild ist oder der Tab in den Hintergrund
   - bei prefers-reduced-motion und auf schmalen Schirmen bleibt eine Liste
   ========================================================================== */
(function () {
  'use strict';

  var ruhig = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var fein  = window.matchMedia('(pointer: fine)').matches;
  var flach = window.matchMedia('(max-width: 779px)').matches;

  /* Haltepunkte je Projekt — aus den Ankern der jeweiligen Kundenseite
     gerechnet, nicht geraten. Filme brauchen keine, sie laufen selbst. */
  var TOUR = {
    cavaleri: {
      laptop: [
        { a:'warten',t:900 }, { a:'rollen',zu:0.077,t:1900 }, { a:'rollen',zu:0.369,t:2600 },
        { a:'warten',t:1100 }, { a:'rollen',zu:0.511,t:2200 }, { a:'warten',t:1000 },
        { a:'rollen',zu:0.594,t:2000 }, { a:'rollen',zu:0.774,t:2000 },
        { a:'rollen',zu:1.000,t:2400 }, { a:'warten',t:1500 }, { a:'rollen',zu:0.000,t:1300 }
      ],
      handy: [
        { a:'warten',t:1500 }, { a:'rollen',zu:0.052,t:1600 }, { a:'rollen',zu:0.124,t:1500 },
        { a:'rollen',zu:0.366,t:3000 }, { a:'warten',t:1100 }, { a:'rollen',zu:0.454,t:2000 },
        { a:'rollen',zu:0.577,t:2200 }, { a:'rollen',zu:0.731,t:2000 },
        { a:'rollen',zu:0.998,t:2600 }, { a:'warten',t:1300 }, { a:'rollen',zu:0.000,t:1500 }
      ]
    },
    trendonix: {
      laptop: [
        { a:'warten',t:1000 }, { a:'rollen',zu:0.229,t:2400 }, { a:'warten',t:900 },
        { a:'rollen',zu:0.339,t:2600 }, { a:'rollen',zu:0.551,t:2400 }, { a:'warten',t:1000 },
        { a:'rollen',zu:0.662,t:2200 }, { a:'rollen',zu:0.859,t:2600 }, { a:'warten',t:1200 },
        { a:'rollen',zu:1.000,t:1900 }, { a:'warten',t:1400 }, { a:'rollen',zu:0.000,t:1400 }
      ],
      handy: [
        { a:'warten',t:1200 }, { a:'rollen',zu:0.125,t:2000 }, { a:'rollen',zu:0.246,t:2200 },
        { a:'rollen',zu:0.538,t:3000 }, { a:'warten',t:800 }, { a:'rollen',zu:0.667,t:2000 },
        { a:'rollen',zu:0.891,t:2600 }, { a:'warten',t:900 }, { a:'rollen',zu:1.000,t:1400 },
        { a:'warten',t:1100 }, { a:'rollen',zu:0.000,t:1600 }
      ]
    }
  };
  var weich = function (t) { return t < 0.5 ? 4*t*t*t : 1 - Math.pow(-2*t+2,3)/2; };

  /* ---- Ein Bildschirm: entweder rollende Aufnahme oder Film -------------- */
  function Schirm(el, drehbuch) {
    this.el = el;
    this.bahn = el.querySelector('[data-seiten]');
    this.video = el.querySelector('video');
    this.drehbuch = drehbuch;
    this.i = 0; this.pos = 0; this.laeuft = false; this.geweckt = -1;
  }
  Schirm.prototype.sichtbar = function () { return this.el.getClientRects().length > 0; };
  Schirm.prototype.wecken = function (bis) {
    if (!this.bahn || bis <= this.geweckt) { return; }
    var b = this.bahn.querySelectorAll('img'), i;
    for (i = this.geweckt + 1; i <= bis && i < b.length; i++) { b[i].loading = 'eager'; }
    this.geweckt = Math.min(bis, b.length - 1);
  };
  Schirm.prototype.messen = function () {
    if (!this.bahn) { return; }
    this.strecke = Math.max(0, this.bahn.getBoundingClientRect().height - this.el.getBoundingClientRect().height);
  };
  Schirm.prototype.setzen = function () {
    if (!this.bahn) { return; }
    if (this.strecke === undefined) { this.messen(); }
    if (this.pos > 0.28) { this.wecken(1); }
    this.bahn.style.transform = 'translate3d(0,' + (-this.pos * this.strecke) + 'px,0)';
  };
  Schirm.prototype.start = function () {
    if (ruhig || !this.sichtbar()) { return; }
    if (this.video) {
      if (this.video.preload === 'none') { this.video.preload = 'auto'; this.video.load(); }
      var l = this.video.play(); if (l && l.catch) { l.catch(function () {}); }
      return;
    }
    this.wecken(0);
    if (this.laeuft) { return; }
    this.laeuft = true; this.schritt();
  };
  Schirm.prototype.halt = function () {
    this.laeuft = false;
    if (this.video) { this.video.pause(); }
  };
  Schirm.prototype.schritt = function () {
    if (!this.laeuft || !this.drehbuch) { return; }
    var s = this.drehbuch[this.i % this.drehbuch.length]; this.i++;
    var ich = this, weiter = function () { if (ich.laeuft) { ich.schritt(); } };
    if (s.a === 'warten') { setTimeout(weiter, s.t); return; }
    this.messen();
    var von = this.pos, nach = s.zu, t0 = performance.now();
    (function lauf(t) {
      if (!ich.laeuft) { return; }
      var k = Math.min((t - t0) / s.t, 1);
      ich.pos = von + (nach - von) * weich(k);
      ich.setzen();
      if (k < 1) { requestAnimationFrame(lauf); } else { weiter(); }
    })(t0);
  };

  /* ---- Die Schau ---------------------------------------------------------- */
  document.querySelectorAll('[data-schau]').forEach(function (schau) {
    var gleise = [].slice.call(schau.querySelectorAll('[data-gleis]'));
    var knoepfe = [].slice.call(schau.querySelectorAll('.schau__waehler button'));
    var texte = [].slice.call(schau.parentElement.querySelectorAll('[data-text]'));
    if (!gleise.length) { return; }
    var anzahl = gleise[0].querySelectorAll('.stueck').length;
    var aktiv = 0, imBild = false;
    var schirme = [];        /* [gleisIndex][stueckIndex] */

    gleise.forEach(function (gl) {
      var art = gl.getAttribute('data-gleis');
      var reihe = [];
      [].slice.call(gl.querySelectorAll('.stueck')).forEach(function (st, i) {
        var name = st.getAttribute('data-p');
        var buch = TOUR[name] && TOUR[name][art];
        var sch = st.querySelector('.stueck__schirm');
        reihe.push(new Schirm(sch, buch));
        st.setAttribute('role', 'button');
        st.setAttribute('tabindex', '0');
        st.addEventListener('click', function () { zu(i); });
        st.addEventListener('keydown', function (e) {
          if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); zu(i); }
        });
      });
      schirme.push(reihe);
    });

    /* Wo ein Stueck steht: seitlich in Vielfachen der eigenen Breite, nach
       hinten in Pixeln, und zur Mitte gedreht. Die Abstaende werden nach
       aussen kleiner, sonst laufen vier Geraete aus dem Bild. */
    /* Ein Karussell ist rund: das Stueck hinter dem letzten steht wieder links.
       Ohne das faechern bei Projekt 1 alle drei anderen nach rechts und die
       linke Haelfte bleibt leer. */
    function versatz(i) {
      var d = i - aktiv;
      if (d >  anzahl / 2) { d -= anzahl; }
      if (d < -anzahl / 2) { d += anzahl; }
      return d;
    }
    function platz(d) {
      var a = Math.abs(d), s = d < 0 ? -1 : 1;
      /* Das gegenueberliegende Stueck wartet hinter dem vorderen — sichtbar
         sind immer drei: links, vorn, rechts. Ueber die Knoepfe ist trotzdem
         jedes erreichbar. */
      if (a >= 2) { return { x: 0, z: -330, ry: 0, sc: 0.62, op: 0 }; }
      return {
        x:  a === 0 ? 0 : s * 0.62,
        z:  -a * 130,
        ry: a === 0 ? 0 : -s * 33,
        sc: a === 0 ? 1 : 0.84,
        op: 1
      };
    }
    function grund(st, i) {
      var d = versatz(i), p = platz(d);
      st.dataset.gx = p.x; st.dataset.gz = p.z; st.dataset.gry = p.ry; st.dataset.gsc = p.sc;
      st.style.zIndex = String(20 - Math.abs(d));
      st.style.opacity = String(p.op);
      st.style.pointerEvents = p.op ? '' : 'none';
      male(st, 0, 0);
    }
    function male(st, kx, ky) {
      st.style.transform =
        'translate3d(calc(var(--st-b) * ' + st.dataset.gx + '), 0, ' + st.dataset.gz + 'px)' +
        ' rotateY(' + (+st.dataset.gry + kx) + 'deg)' +
        ' rotateX(' + ky + 'deg)' +
        ' scale(' + st.dataset.gsc + ')';
    }
    function ordnen() {
      gleise.forEach(function (gl) {
        [].slice.call(gl.querySelectorAll('.stueck')).forEach(function (st, i) {
          st.classList.toggle('ist-vorn', i === aktiv);
          grund(st, i);
        });
      });
      knoepfe.forEach(function (b, i) { b.setAttribute('aria-current', i === aktiv ? 'true' : 'false'); });
      texte.forEach(function (t, i) { t.hidden = (i !== aktiv); });
      var adr = schau.querySelector('[data-schau-adresse]');
      if (adr && texte[aktiv]) { adr.textContent = texte[aktiv].getAttribute('data-adresse') || ''; }
      schirme.forEach(function (reihe) {
        reihe.forEach(function (s, i) {
          if (i === aktiv && imBild) { s.start(); } else { s.halt(); }
        });
      });
    }
    function zu(i) {
      if (i === aktiv || i < 0 || i >= anzahl) { return; }
      aktiv = i; ordnen();
    }
    knoepfe.forEach(function (b, i) { b.addEventListener('click', function () { zu(i); }); });
    schau.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight') { zu((aktiv + 1) % anzahl); }
      if (e.key === 'ArrowLeft')  { zu((aktiv - 1 + anzahl) % anzahl); }
    });

    /* Nur laufen lassen, was zu sehen ist. */
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (e) {
        imBild = e[0].isIntersecting && !document.hidden;
        ordnen();
      }, { rootMargin: '140px' }).observe(schau);
    } else { imBild = true; }
    document.addEventListener('visibilitychange', function () {
      imBild = imBild && !document.hidden; ordnen();
    });
    window.addEventListener('resize', function () {
      schirme.forEach(function (r) { r.forEach(function (s) { s.messen(); s.setzen(); }); });
    }, { passive: true });

    ordnen();

    /* ---- Drehen beim Ueberfahren --------------------------------------- */
    if (ruhig || !fein || flach) { return; }
    var lauf = null, ziele = new Map();
    function daempfen() {
      var offen = false;
      ziele.forEach(function (z, st) {
        z.ix += (z.zx - z.ix) * 0.12; z.iy += (z.zy - z.iy) * 0.12;
        male(st, z.ix, z.iy);
        if (Math.abs(z.zx - z.ix) + Math.abs(z.zy - z.iy) > 0.02) { offen = true; }
      });
      if (offen) { lauf = requestAnimationFrame(daempfen); }
      else { lauf = null; ziele.forEach(function (z, st) { if (!z.drin) { ziele.delete(st); } }); }
    }
    function anstossen() { if (!lauf) { lauf = requestAnimationFrame(daempfen); } }
    gleise.forEach(function (gl) {
      [].slice.call(gl.querySelectorAll('.stueck')).forEach(function (st) {
        function z() {
          if (!ziele.has(st)) { ziele.set(st, { zx:0, zy:0, ix:0, iy:0, drin:false }); }
          return ziele.get(st);
        }
        st.addEventListener('pointermove', function (e) {
          var r = st.getBoundingClientRect(), o = z();
          o.zx = ((e.clientX - r.left) / r.width - 0.5) * 18;    /* nach rechts/links */
          o.zy = -((e.clientY - r.top) / r.height - 0.5) * 11;   /* nach oben/unten  */
          o.drin = true; anstossen();
        }, { passive: true });
        st.addEventListener('pointerleave', function () {
          var o = z(); o.zx = 0; o.zy = 0; o.drin = false; anstossen();
        }, { passive: true });
      });
    });
  });
})();


/* ----- polish.js ----- */
/* ==========================================================================
   polish.js — Bewegung an der Oberfläche.

   Alles hier ist Zugabe: Ohne dieses Skript bleibt die Seite vollständig
   bedienbar und lesbar. Deshalb läuft nichts davon bei reduzierter Bewegung.
   ========================================================================== */
(function () {
  'use strict';
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const gsap = window.gsap;

  /* ---------- 1. Kinetische Überschrift im Hero -------------------------- */
  // Bewusst ohne GSAP: Der Auftritt der Titelzeilen darf nicht davon abhängen,
  // ob eine Bibliothek geladen und ihr Ticker aktiv ist. Zwei CSS-Klassen und
  // eine Verzögerung je Zeile reichen — und es läuft auch, wenn 3D ausfällt.
  if (!reduced) {
    const showHero = () => document.documentElement.classList.add('hero-in');
    requestAnimationFrame(() => requestAnimationFrame(showHero));
    // Sicherheitsnetz: Läuft die Bildschleife gedrosselt (schwaches Gerät,
    // 3D-Aufbau), käme der zweite Frame sonst erst nach Sekunden.
    setTimeout(showHero, 400);
  } else {
    document.documentElement.classList.add('hero-in');
  }

  /* ---------- 5. Überschriften wortweise (nur Überschriften) ------------- */
  // Bewusst nicht im Fließtext: Dort stört es beim zweiten Besuch und
  // zerlegt den Text für Vorleseprogramme in Einzelwörter.
  if (gsap && window.ScrollTrigger && !reduced) {
    document.querySelectorAll('.sechead h2, .contact h2').forEach((h) => {
      if (h.dataset.split) return;
      h.dataset.split = '1';
      const words = h.textContent.trim().split(/\s+/);
      h.setAttribute('aria-label', h.textContent.trim());
      h.innerHTML = words
        .map((w) => `<span class="w" aria-hidden="true"><i>${w}</i></span>`)
        .join(' ');
      gsap.set(h.querySelectorAll('i'), { yPercent: 105 });
      gsap.to(h.querySelectorAll('i'), {
        yPercent: 0, duration: 0.85, ease: 'expo.out', stagger: 0.045,
        scrollTrigger: { trigger: h, start: 'top 85%' },
      });
    });
  }

  /* ---------- 3. Projektkarten neigen sich zum Zeiger --------------------- */
  if (!reduced && window.matchMedia('(pointer: fine)').matches) {
    document.querySelectorAll('.card').forEach((card) => {
      card.addEventListener('pointermove', (e) => {
        const r = card.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width - 0.5;
        const y = (e.clientY - r.top) / r.height - 0.5;
        card.style.setProperty('--rx', (-y * 5).toFixed(2) + 'deg');
        card.style.setProperty('--ry', (x * 6).toFixed(2) + 'deg');
        card.style.setProperty('--lx', ((x + 0.5) * 100).toFixed(1) + '%');
        card.style.setProperty('--ly', ((y + 0.5) * 100).toFixed(1) + '%');
        card.classList.add('is-tilted');
      });
      card.addEventListener('pointerleave', () => {
        card.classList.remove('is-tilted');
        card.style.setProperty('--rx', '0deg');
        card.style.setProperty('--ry', '0deg');
      });
    });
  }

  /* ---------- 4. Kennzahlen zählen hoch ---------------------------------- */
  if (!reduced && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((en) => {
        if (!en.isIntersecting) return;
        const el = en.target;
        io.unobserve(el);
        const raw = el.textContent.trim();
        const target = parseFloat(raw.replace(/[^\d.]/g, ''));
        if (!isFinite(target) || target === 0) return;   // „0" bleibt stehen
        const suffix = raw.replace(/[\d.,]/g, '');
        const t0 = performance.now();
        const dur = 1100;
        (function tick(now) {
          const p = Math.min(1, ((now || performance.now()) - t0) / dur);
          const e = 1 - Math.pow(1 - p, 3);
          el.textContent = Math.round(target * e) + suffix;
          if (p < 1) requestAnimationFrame(tick);
        })();
        // Sicherheitsnetz: Läuft die Bildschleife gedrosselt (schwaches Gerät,
        // Hintergrund-Tab), bleibt der Zähler sonst auf einem Zwischenwert stehen.
        setTimeout(() => { el.textContent = target + suffix; }, dur + 250);
      });
    }, { threshold: 0.6 });
    document.querySelectorAll('.pillar strong').forEach((el) => io.observe(el));
  }

  /* ---------- 6. Preise: einmalig oder Gesamtkosten über 12 Monate -------- */
  const toggle = document.querySelector('[data-price-toggle]');
  if (toggle) {
    // Zahlen auslesen, damit später nur noch gerechnet wird. Als Funktion,
    // weil die Karten aus der Verwaltung nachgeladen werden können.
    const sammeln = () => [...document.querySelectorAll('.plan')].map((plan) => {
      const once = plan.querySelector('.plan__price > span');
      const month = plan.querySelector('.plan__month');
      const parse = (el) => {
        const m = (el ? el.textContent : '').replace(/\./g, '').match(/(\d+)/);
        return m ? parseInt(m[1], 10) : null;
      };
      return { once, month, o: parse(once), m: parse(month), oText: once ? once.textContent : '' };
    });
    let data = sammeln();
    const fmt = (n) => n.toLocaleString(document.documentElement.lang || 'de-DE');

    const render = (mode) => {
      data.forEach((d) => {
        if (!d.once || d.o === null) return;
        if (mode === 'year' && d.m !== null) {
          d.once.textContent = fmt(d.o + d.m * 12) + ' €';
        } else {
          d.once.textContent = d.oText;
        }
      });
      toggle.querySelectorAll('button').forEach((b) =>
        b.setAttribute('aria-pressed', String(b.dataset.mode === mode)));
      document.querySelectorAll('.plan__price small').forEach((s) => {
        s.textContent = s.dataset[mode] || s.textContent;
      });
    };

    toggle.addEventListener('click', (e) => {
      const b = e.target.closest('button');
      if (b) render(b.dataset.mode);
    });
    // Beschriftungen für beide Zustände merken, bevor sie überschrieben werden.
    const beschriftungenMerken = () => {
      document.querySelectorAll('.plan__price small').forEach((s) => {
        if (s.dataset.once) return;
        s.dataset.once = s.textContent;
        s.dataset.year = toggle.dataset.yearNote || s.textContent;
      });
    };
    beschriftungenMerken();
    render('once');

    // Kommen die Karten aus der Verwaltung, sind es andere Elemente —
    // dann müssen Zahlen und Beschriftungen neu eingelesen werden.
    document.addEventListener('vecom:pakete', () => {
      data = sammeln();
      beschriftungenMerken();
      const aktiv = toggle.querySelector('button[aria-pressed="true"]');
      render(aktiv ? aktiv.dataset.mode : 'once');
    });
  }

  /* ---------- 8b. Sprungmarken sauber anfahren ---------------------------- */
  // Mit weichem Scrollen (Lenis) hakt der native Sprung über #anker: Der Browser
  // springt, Lenis scrollt gleichzeitig zurück. Deshalb übernehmen wir die
  // Sprünge selbst — inklusive Abstand für die feste Kopfzeile.
  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[href^="#"]');
    if (!a) return;
    const id = a.getAttribute('href').slice(1);
    if (!id) return;
    const target = document.getElementById(id);
    if (!target) return;
    e.preventDefault();
    const lenis = window.__vecomLenis;
    if (lenis) {
      lenis.scrollTo(target, { offset: -84, duration: 1.1 });
    } else {
      const y = target.getBoundingClientRect().top + window.scrollY - 84;
      window.scrollTo({ top: y, behavior: reduced ? 'auto' : 'smooth' });
    }
    history.replaceState(null, '', '#' + id);
    document.body.classList.remove('nav-open');
  });

  /* ---------- 9. Sprachwechsel gestaffelt --------------------------------- */
  if (!reduced) {
    document.querySelectorAll('[data-lang]').forEach((b) => {
      b.addEventListener('click', () => {
        document.body.classList.add('is-swapping');
        setTimeout(() => document.body.classList.remove('is-swapping'), 420);
      });
    });
  }
})();


/* ----- pakete-live.js ----- */
/* ============================================================================
   Preiskarten aus der Verwaltung.

   Die drei Karten im HTML bleiben unangetastet und sind der Rückfall: Solange
   die Verwaltung nicht eingerichtet ist, die Datenbank schweigt oder gar kein
   Paket öffentlich steht, ändert dieses Skript nichts.

   Kommen Pakete zurück, wird der Block neu aufgebaut — eine vorhandene Karte
   dient als Vorlage, damit Klassen, Aufbau und Feinheiten erhalten bleiben.
   Felder, die aus der Datenbank stammen, verlieren dabei ihr data-i18n, sonst
   würde die Sprachumschaltung sie gleich wieder überschreiben. Gemeinsame
   Beschriftungen (Preiszusatz, Knöpfe, Abzeichen) behalten es.
   ========================================================================== */
(function () {
  'use strict';

  var behaelter = document.querySelector('.plans');
  var vorlage = behaelter && behaelter.querySelector('.plan');
  if (!behaelter || !vorlage) { return; }

  var original = behaelter.innerHTML;          // Rückfall, falls etwas schiefgeht
  var muster = vorlage.cloneNode(true);
  var letzte = null;                           // zuletzt geladene Pakete
  var kaufText = '';                           // Beschriftung des Kaufknopfs, je Sprache

  /* Dieselbe Reihenfolge wie pickLang() in app.js — sonst holt dieses Skript
     die Texte in einer anderen Sprache, als die Seite gerade zeigt. */
  function sprache() {
    var moeglich = ['it', 'de', 'en'];
    var fest = document.documentElement.getAttribute('data-lang-fixed');
    if (moeglich.indexOf(fest) > -1) { return fest; }
    var ausUrl = new URLSearchParams(location.search).get('lang');
    if (moeglich.indexOf(ausUrl) > -1) { return ausUrl; }
    try {
      var gemerkt = localStorage.getItem('vecom-lang');
      if (moeglich.indexOf(gemerkt) > -1) { return gemerkt; }
    } catch (e) {}
    var vomBrowser = (navigator.languages || [navigator.language || ''])
      .map(function (l) { return String(l).slice(0, 2).toLowerCase(); });
    for (var i = 0; i < vomBrowser.length; i++) {
      if (moeglich.indexOf(vomBrowser[i]) > -1) { return vomBrowser[i]; }
    }
    var amElement = (document.documentElement.getAttribute('lang') || '').slice(0, 2).toLowerCase();
    return moeglich.indexOf(amElement) > -1 ? amElement : 'it';
  }

  function geld(betrag, waehrung) {
    var z = Number(betrag);
    var text = (z % 1 === 0 ? z : z.toFixed(2)).toLocaleString('de-DE');
    return text + ' ' + (waehrung === 'EUR' ? '€' : waehrung);
  }

  /** Setzt Text und nimmt data-i18n weg, damit die Sprachumschaltung nicht überschreibt. */
  function setzen(el, text) {
    if (!el) { return; }
    el.removeAttribute('data-i18n');
    el.textContent = text;
  }

  function karteBauen(p, monatsText) {
    var k = muster.cloneNode(true);
    k.classList.toggle('plan--featured', !!p.beliebt);

    var abzeichen = k.querySelector('.plan__badge');
    if (p.beliebt && !abzeichen) {
      var vorhanden = document.querySelector('.plan__badge');
      if (vorhanden) { k.insertBefore(vorhanden.cloneNode(true), k.firstChild); }
    } else if (!p.beliebt && abzeichen) {
      abzeichen.remove();
    }

    setzen(k.querySelector('h3'), p.name);
    setzen(k.querySelector('.plan__sub'), p.sub);
    setzen(k.querySelector('.plan__price > span'), geld(p.preis, p.waehrung));

    var monat = k.querySelector('.plan__month');
    if (monat) {
      if (p.monat > 0 && monatsText) {
        setzen(monat, monatsText.replace(/\d[\d.,]*\s*€/, geld(p.monat, p.waehrung)));
        monat.hidden = false;
      } else if (p.monat > 0) {
        setzen(monat, '+ ' + geld(p.monat, p.waehrung));
      } else {
        monat.remove();
      }
    }

    var liste = k.querySelector('ul');
    if (liste) {
      liste.removeAttribute('data-i18n-list');
      liste.innerHTML = '';
      p.features.forEach(function (f) {
        var text = String(f).trim();
        if (!text) { return; }
        var li = document.createElement('li');
        // Dieselbe Regel wie in app.js: Zeilen mit Doppelpunkt am Ende sind
        // Zwischenüberschriften, keine Merkmale.
        if (/:$/.test(text)) { li.className = 'is-lead'; }
        li.textContent = text;
        liste.appendChild(li);
      });
    }

    var ideal = k.querySelector('.plan__ideal');
    if (ideal) {
      if (p.ideal) { setzen(ideal, p.ideal); } else { ideal.remove(); }
    }

    var detail = k.querySelector('.det-link');
    if (detail) { detail.setAttribute('href', p.detail); }

    // Auch die Karten aus der Verwaltung tragen ihre Wahl ins Formular.
    if (p.slug) {
      k.setAttribute('data-paket', p.slug);
      var anfr = k.querySelector('a.btn:not(.det-link)');
      if (anfr) { anfr.setAttribute('href', '?paket=' + encodeURIComponent(p.slug) + '#contact'); }
    }

    // Kaufknopf nur, wenn das Paket dafür freigegeben und Stripe bereit ist.
    // Er wird aus dem vorhandenen Anfrage-Knopf geklont, damit Form und
    // Verhalten (auch das magnetische Mitziehen) erhalten bleiben.
    if (p.kaufbar && kaufText) {
      var anfrage = k.querySelector('a.btn:not(.det-link)');
      if (anfrage) {
        var kauf = anfrage.cloneNode(true);
        kauf.className = anfrage.className + ' plan__kauf';
        kauf.removeAttribute('data-i18n');
        kauf.setAttribute('href', p.kauf_url);
        kauf.textContent = kaufText;
        anfrage.parentNode.insertBefore(kauf, anfrage);
      }
    }

    k.classList.add('in');                     // die Einblendung lief schon
    return k;
  }

  function zeichnen(pakete) {
    if (!pakete || !pakete.length) { return; }
    var monatsText = (document.querySelector('.plan__month') || {}).textContent || '';
    try {
      var neu = document.createDocumentFragment();
      pakete.forEach(function (p) { neu.appendChild(karteBauen(p, monatsText)); });
      behaelter.innerHTML = '';
      behaelter.appendChild(neu);
      // Der Preisumschalter muss seine Zahlen neu einlesen.
      document.dispatchEvent(new CustomEvent('vecom:pakete'));
    } catch (e) {
      behaelter.innerHTML = original;          // im Zweifel der alte Zustand
    }
  }

  /* Der Betreuungsblock. Dieselbe Karte, nur zeigt sie den Monatspreis als
     Preis — ein Vertrag ohne Einmalbetrag hat keinen anderen. Fehlt der Block
     auf der Seite, passiert hier nichts. */
  function betreuungZeichnen(liste, monatsWort) {
    var kasten = document.querySelector('[data-betreuung]');
    if (!kasten || !liste || !liste.length) { return; }

    /* Die Startseite zeigt eine Karte, die Betreuungsseite alle. Beide holen
       dieselben Daten — was sie unterscheidet, ist eine Frage der Darstellung
       und steht deshalb in der Seite, nicht in der Datenbank. */
    if (kasten.getAttribute('data-betreuung') !== 'alle') { liste = liste.slice(0, 1); }

    /* Ein Paket ohne Leistungstexte in der Verwaltung wuerde die eingebaute
       Karte durch eine leere ersetzen — Name aus dem Bezeichner, kein
       Untertitel, keine Liste. Das ist schlechter als gar nicht zu
       aktualisieren, und es faellt niemandem auf, weil die Seite ja aufgeht.
       Also: Wer nichts zu sagen hat, ersetzt nichts. */
    liste = liste.filter(function (p) { return p && p.features && p.features.length; });
    if (!liste.length) { return; }
    var vorher = kasten.innerHTML;
    try {
      var neu = document.createDocumentFragment();
      liste.forEach(function (p) {
        var karte = karteBauen({
          slug: p.slug, name: p.name, sub: p.sub, ideal: p.ideal,
          features: p.features, preis: p.monat, monat: 0,
          waehrung: p.waehrung, beliebt: p.beliebt, detail: p.detail,
          kaufbar: false, kauf_url: p.kauf_url
        }, '');
        var klein = karte.querySelector('.plan__price > small');
        if (klein && monatsWort) { klein.removeAttribute('data-i18n'); klein.textContent = monatsWort; }
        // Fuer die Betreuung gibt es keine Detailseite — ein Link, der ins
        // Leere zeigt, ist schlimmer als kein Link.
        var det = karte.querySelector('.det-link');
        if (det) { det.remove(); }
        neu.appendChild(karte);
      });
      kasten.innerHTML = '';
      kasten.appendChild(neu);
    } catch (e) {
      kasten.innerHTML = vorher;
    }
  }

  function holen() {
    fetch('/pakete-daten.php?lang=' + encodeURIComponent(sprache()), { cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d || !d.pakete || !d.pakete.length) { return; }
        kaufText = d.kauf_text || '';
        letzte = d.pakete;
        var monatsWort = (document.querySelector('[data-betreuung] .plan__price > small') || {}).textContent || '';
        zeichnen(letzte);
        betreuungZeichnen(d.betreuung, monatsWort);
      })
      .catch(function () { /* Website behält ihre eingebauten Karten */ });
  }

  // Sprachwechsel: kurz warten, bis die Seitenübersetzung durch ist, dann neu füllen.
  document.querySelectorAll('[data-lang]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setTimeout(function () {
        if (letzte) { holen(); }
      }, 60);
    });
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', holen);
  } else {
    holen();
  }
})();


/* ----- stimmen-live.js ----- */
/* ==========================================================================
   Kundenstimmen aus der Verwaltung.

   Dieselbe Idee wie bei den Preiskarten: Was Uwe freigibt, steht danach von
   allein hier. Und solange nichts freigegeben ist, bleibt der Abschnitt
   verborgen — eine Ueberschrift "Was Kunden sagen" ueber einer leeren Flaeche
   ist die schlechteste aller Antworten auf diese Frage.
   ========================================================================== */
(function () {
  'use strict';
  var abschnitt = document.querySelector('[data-stimmen]');
  var liste = document.querySelector('[data-stimmen-liste]');
  if (!abschnitt || !liste) { return; }

  function sprache() {
    var l = (document.documentElement.getAttribute('lang') || 'it').slice(0, 2).toLowerCase();
    return ['it', 'de', 'en'].indexOf(l) >= 0 ? l : 'it';
  }

  function sterne(n) {
    if (!n) { return ''; }
    var voll = '', leer = '';
    for (var i = 0; i < n; i++) { voll += '★'; }
    for (var j = n; j < 5; j++) { leer += '★'; }
    return '<span class="stimme__sterne">' + voll + '<i>' + leer + '</i></span>';
  }

  function zeichnen(stimmen) {
    if (!stimmen || !stimmen.length) { abschnitt.hidden = true; return; }
    var teile = [];
    stimmen.forEach(function (s) {
      var wer = [s.firma || s.name, s.ort].filter(Boolean).join(' · ');
      var el = document.createElement('figure');
      el.className = 'stimme';
      el.innerHTML = sterne(s.sterne) + '<blockquote></blockquote><figcaption></figcaption>';
      // Text ueber textContent, nicht ueber innerHTML: Er kommt von aussen.
      el.querySelector('blockquote').textContent = s.text;
      el.querySelector('figcaption').textContent = wer;
      teile.push(el);
    });
    liste.innerHTML = '';
    teile.forEach(function (el) { liste.appendChild(el); });
    abschnitt.hidden = false;
  }

  function holen() {
    fetch('/stimmen-daten.php?lang=' + encodeURIComponent(sprache()), { cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { zeichnen(d && d.stimmen); })
      .catch(function () { abschnitt.hidden = true; });
  }

  document.querySelectorAll('[data-lang]').forEach(function (b) {
    b.addEventListener('click', function () { setTimeout(holen, 60); });
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', holen);
  } else { holen(); }
})();


/* ----- rail.js ----- */
/* Kapitelleiste rechts: zeigt, wo man ist. Kein Ersatz für das Menü oben,
   sondern eine Orientierung während des Scrollens. */
(function () {
  const rail = document.querySelector('.rail');
  if (!rail || !('IntersectionObserver' in window)) return;
  const links = [...rail.querySelectorAll('a')];
  const map = new Map(links.map((a) => [a.getAttribute('href').slice(1), a]));

  const io = new IntersectionObserver((entries) => {
    entries.forEach((en) => {
      const a = map.get(en.target.id);
      if (!a) return;
      if (en.isIntersecting) {
        links.forEach((l) => l.removeAttribute('aria-current'));
        a.setAttribute('aria-current', 'true');
      }
    });
  }, { rootMargin: '-45% 0px -50% 0px' });

  map.forEach((_, id) => { const el = document.getElementById(id); if (el) io.observe(el); });

  // Erst zeigen, wenn der Hero vorbei ist — im Aufmacher stört sie.
  const hero = document.querySelector('.hero');
  if (hero) new IntersectionObserver(([e]) => rail.classList.toggle('is-on', !e.isIntersecting),
    { threshold: 0.1 }).observe(hero);
})();


/* ----- vids.js ----- */
/* Videos erst laden, wenn jemand sie sehen will. Vorher liegt nur ein
   Vorschaubild da (10 KB statt 2,3 MB) — die Seite bleibt schnell. */
(function () {
  document.querySelectorAll('.vid').forEach((fig) => {
    const start = () => {
      if (fig.classList.contains('is-playing')) return;
      const v = document.createElement('video');
      v.src = fig.dataset.src;
      v.controls = true;
      v.playsInline = true;
      v.preload = 'auto';
      fig.querySelector('img').replaceWith(v);
      fig.classList.add('is-playing');
      /* Seit das Video eine Tonspur hat, genuegt das autoplay-Attribut nicht
         mehr: Safari laesst Ton nur zu, wenn das Abspielen aus einer echten
         Nutzeraktion heraus angestossen wird. Der Klick auf den Knopf ist
         eine — also hier abspielen und nicht dem Attribut ueberlassen.
         Weigert sich der Browser trotzdem, laeuft es stumm weiter, statt
         gar nicht zu starten. */
      const los = v.play();
      if (los && typeof los.catch === 'function') {
        los.catch(() => { v.muted = true; v.play().catch(() => {}); });
      }
    };
    fig.querySelector('.vid__play').addEventListener('click', start);
    fig.querySelector('img')?.addEventListener('click', start);
  });
})();


/* ----- depth.js ----- */
/* ==========================================================================
   depth.js — Tiefen-Parallaxe für Bilder ohne laufende Website.

   Grundlage: eine Tiefenkarte, die mit Depth Anything V2 aus dem Bild selbst
   errechnet wurde (siehe tools/make-depth.py). Der Shader verschiebt jedes
   Pixel entlang seiner Tiefe: Nahes wandert stark, Fernes kaum. Dadurch wirkt
   ein Foto räumlich, ohne dass ein 3D-Modell nötig wäre.

   Kosten: zwei kleine Bilder (3–4 KB je Tiefenkarte) und ein Shader mit
   knapp zwanzig Zeilen. Kein three.js.

   Fällt WebGL aus oder ist Bewegung reduziert, bleibt das Bild einfach stehen.
   ========================================================================== */
(function () {
  'use strict';
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const nodes = [...document.querySelectorAll('[data-depth]')];
  if (!nodes.length || reduced) return;

  const VERT = `
    attribute vec2 p; varying vec2 v;
    void main(){ v = p * 0.5 + 0.5; v.y = 1.0 - v.y; gl_Position = vec4(p, 0.0, 1.0); }`;

  const FRAG = `
    precision mediump float;
    varying vec2 v;
    uniform sampler2D uImg, uDep;
    uniform vec2 uMouse;      // -1 … 1
    uniform float uAmt;       // Stärke der Verschiebung
    void main(){
      float d = texture2D(uDep, v).r;          // 0 = fern, 1 = nah
      vec2 off = uMouse * uAmt * (d - 0.35);
      gl_FragColor = texture2D(uImg, v + off);
    }`;

  const make = (gl, type, src) => {
    const s = gl.createShader(type);
    gl.shaderSource(s, src); gl.compileShader(s);
    return gl.getShaderParameter(s, gl.COMPILE_STATUS) ? s : null;
  };

  nodes.forEach((box) => {
    const canvas = box.querySelector('canvas');
    const gl = canvas.getContext('webgl', { antialias: false, alpha: false });
    if (!gl) return;                     // ohne WebGL bleibt das <img> stehen

    const prog = gl.createProgram();
    const vs = make(gl, gl.VERTEX_SHADER, VERT);
    const fs = make(gl, gl.FRAGMENT_SHADER, FRAG);
    if (!vs || !fs) return;
    gl.attachShader(prog, vs); gl.attachShader(prog, fs); gl.linkProgram(prog);
    gl.useProgram(prog);

    const buf = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 1,-1, -1,1, 1,1]), gl.STATIC_DRAW);
    const loc = gl.getAttribLocation(prog, 'p');
    gl.enableVertexAttribArray(loc);
    gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);

    const uMouse = gl.getUniformLocation(prog, 'uMouse');
    const uAmt = gl.getUniformLocation(prog, 'uAmt');
    gl.uniform1f(uAmt, 0.035);

    const tex = (unit, name) => {
      const t = gl.createTexture();
      gl.activeTexture(gl.TEXTURE0 + unit);
      gl.bindTexture(gl.TEXTURE_2D, t);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
      gl.uniform1i(gl.getUniformLocation(prog, name), unit);
      return t;
    };
    const tImg = tex(0, 'uImg');
    const tDep = tex(1, 'uDep');

    let ready = 0;
    const load = (src, unit, texture) => {
      const im = new Image();
      im.onload = () => {
        gl.activeTexture(gl.TEXTURE0 + unit);
        gl.bindTexture(gl.TEXTURE_2D, texture);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, im);
        if (++ready === 2) { box.classList.add('is-live'); resize(); draw(); }
      };
      im.src = src;
    };

    function resize() {
      const r = box.getBoundingClientRect();
      const dpr = Math.min(window.devicePixelRatio || 1, 1.75);
      canvas.width = Math.round(r.width * dpr);
      canvas.height = Math.round(r.height * dpr);
      gl.viewport(0, 0, canvas.width, canvas.height);
    }

    let mx = 0, my = 0, cx = 0, cy = 0, raf = null;
    function draw() {
      cx += (mx - cx) * 0.07;
      cy += (my - cy) * 0.07;
      gl.uniform2f(uMouse, cx, cy);
      gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
      raf = (Math.abs(mx - cx) > 0.001 || Math.abs(my - cy) > 0.001)
        ? requestAnimationFrame(draw) : null;
    }

    box.addEventListener('pointermove', (e) => {
      const r = box.getBoundingClientRect();
      mx = ((e.clientX - r.left) / r.width - 0.5) * 2;
      my = ((e.clientY - r.top) / r.height - 0.5) * 2;
      if (!raf) raf = requestAnimationFrame(draw);
    }, { passive: true });
    box.addEventListener('pointerleave', () => {
      mx = 0; my = 0;
      if (!raf) raf = requestAnimationFrame(draw);
    });
    window.addEventListener('resize', () => { resize(); draw(); }, { passive: true });

    load(box.dataset.img, 0, tImg);
    load(box.dataset.depthSrc, 1, tDep);
  });
})();


/* ----- sig.js ----- */
/* Der Namenszug schreibt sich, sobald er ins Bild kommt — einmal, nicht bei
   jedem Vorbeiscrollen. Ohne JS steht er einfach da. */
(function () {
  const sig = document.querySelector('[data-sig]');
  if (!sig || !('IntersectionObserver' in window)) { if (sig) sig.classList.add('is-writing'); return; }
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const io = new IntersectionObserver(([e]) => {
    if (!e.isIntersecting) return;
    sig.classList.add('is-writing');
    io.disconnect();
  }, { threshold: 0.6 });
  io.observe(sig);
})();


/* ----- social.js ----- */
/* ==========================================================================
   social.js — Verweise auf die sozialen Kanäle.

   HIER die vier Adressen eintragen. Ein leerer Eintrag bedeutet: Das Symbol
   wird gar nicht angezeigt. Ein Symbol, das ins Leere führt, schadet mehr,
   als es nützt — deshalb erscheint nur, was auch wirklich existiert.

   Beispiel:  facebook: 'https://www.facebook.com/vecomdesign',
   ========================================================================== */
window.VECOM_SOCIAL = {
  facebook:  '',
  instagram: '',
  tiktok:    '',
  x:         '',
};

(function () {
  const cfg = window.VECOM_SOCIAL || {};
  document.querySelectorAll('[data-social]').forEach((a) => {
    const url = cfg[a.dataset.social];
    if (url) {
      a.href = url;
      a.hidden = false;
    } else {
      a.hidden = true;               // noch keine Adresse hinterlegt
    }
  });
  const list = document.querySelector('.social');
  if (list && !list.querySelector('a:not([hidden])')) list.hidden = true;
})();
