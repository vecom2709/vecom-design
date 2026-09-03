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
