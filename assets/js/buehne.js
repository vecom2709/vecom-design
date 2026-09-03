/* ==========================================================================
   buehne.js — Ein Projekt in echten Geraeten.

   Die Huellen sind fotografiert (aus kie.ai), die Bildschirminhalte sind
   ECHTE Vollseiten-Aufnahmen der laufenden Kundenseite. Das ist der
   entscheidende Punkt: eine KI kann eine bestimmte Website nicht
   nachzeichnen, sie erfindet Inhalte. Also liefert sie das Geraet, und die
   Seite kommt vom Original.

   Regeln, wie ueberall auf dieser Seite:
   - laeuft nur, wenn die Buehne im Bild ist
   - haelt im Hintergrund-Tab an
   - bei prefers-reduced-motion: Standbild, kein Zeiger, keine Neigung
   - der Text der Karte steht im DOM und ist ohne all das vollstaendig
   ========================================================================== */
(function () {
  'use strict';

  var ruhig = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var feinerZeiger = window.matchMedia('(pointer: fine)').matches;

  /* Die Haltepunkte sind aus der jeweiligen Kundenseite ausgelesen: erst die
     Ankerpositionen ihrer Abschnitte, dann auf das Fenster des jeweiligen
     Bildschirms umgerechnet (Anker mal Seitenhoehe geteilt durch den Rollweg).
     Geraten ist hier nichts.

     Beide Bühnen stehen auf derselben Platte. Die Drehbuecher sind es nicht:
     jede Seite ist anders gebaut, und wenn beide gleich lang liefen, saehe man
     im Vorbeiscrollen zweimal denselben Takt. */
  var DREHBUECHER = {

    /* cavaleri-trasporti.netlify.app
       Notebook 1200x8367, Fenster 831 · Telefon 560x17479, Fenster 1072.
       Die Seite hat lange weisse Textabschnitte (azienda, storia, persone) —
       dort wird kuerzer verweilt als bei rotta, filmato und contatti. */
    cavaleri: {
      laptop: [
        { a: 'warten', t: 900 },
        { a: 'zeiger', x: 0.50, y: 0.55, t: 900 },
        { a: 'rollen', zu: 0.077, t: 1900 },
        { a: 'zeiger', x: 0.30, y: 0.40, t: 800 },
        { a: 'rollen', zu: 0.369, t: 2600 },
        { a: 'warten', t: 1100 },
        { a: 'zeiger', x: 0.62, y: 0.55, t: 800 },
        { a: 'klick' },
        { a: 'laden', t: 600 },
        { a: 'rollen', zu: 0.511, t: 2200 },
        { a: 'warten', t: 1000 },
        { a: 'rollen', zu: 0.594, t: 2000 },
        { a: 'rollen', zu: 0.774, t: 2000 },
        { a: 'zeiger', x: 0.45, y: 0.30, t: 700 },
        { a: 'rollen', zu: 1.000, t: 2400 },
        { a: 'warten', t: 1500 },
        { a: 'rollen', zu: 0.000, t: 1300 },
        { a: 'zeiger', x: 0.50, y: 0.50, t: 700 }
      ],
      handy: [
        { a: 'warten', t: 1500 },
        { a: 'rollen', zu: 0.052, t: 1600 },
        { a: 'warten', t: 500 },
        { a: 'rollen', zu: 0.124, t: 1500 },
        { a: 'rollen', zu: 0.366, t: 3000 },
        { a: 'warten', t: 1100 },
        { a: 'rollen', zu: 0.454, t: 2000 },
        { a: 'warten', t: 900 },
        { a: 'rollen', zu: 0.577, t: 2200 },
        { a: 'rollen', zu: 0.731, t: 2000 },
        { a: 'rollen', zu: 0.998, t: 2600 },
        { a: 'warten', t: 1300 },
        { a: 'rollen', zu: 0.000, t: 1500 }
      ]
    },

    /* trendonix-buecher.de
       Notebook 1200x6938, Fenster 831 · Telefon 560x15174, Fenster 1072.
       Diese Seite hat keine leeren Strecken — sie wechselt bewusst zwischen
       Nacht und Papier. Also werden schlicht ihre Abschnitte abgefahren:
       welten · buecher · weitere-buecher · leserstimmen · verteiler, und zum
       Schluss der Fuss, wo seine eigene Signatur steht. */
    trendonix: {
      laptop: [
        { a: 'warten', t: 1000 },
        { a: 'zeiger', x: 0.48, y: 0.62, t: 900 },
        { a: 'rollen', zu: 0.229, t: 2400 },
        { a: 'warten', t: 900 },
        { a: 'zeiger', x: 0.27, y: 0.45, t: 800 },
        { a: 'rollen', zu: 0.339, t: 2600 },
        { a: 'zeiger', x: 0.58, y: 0.52, t: 900 },
        { a: 'klick' },
        { a: 'laden', t: 650 },
        { a: 'rollen', zu: 0.551, t: 2400 },
        { a: 'warten', t: 1000 },
        { a: 'rollen', zu: 0.662, t: 2200 },
        { a: 'zeiger', x: 0.40, y: 0.35, t: 800 },
        { a: 'rollen', zu: 0.859, t: 2600 },
        { a: 'warten', t: 1200 },
        { a: 'rollen', zu: 1.000, t: 1900 },
        { a: 'warten', t: 1400 },
        { a: 'rollen', zu: 0.000, t: 1400 },
        { a: 'zeiger', x: 0.50, y: 0.50, t: 700 }
      ],
      handy: [
        { a: 'warten', t: 1200 },
        { a: 'rollen', zu: 0.125, t: 2000 },
        { a: 'warten', t: 600 },
        { a: 'rollen', zu: 0.246, t: 2200 },
        { a: 'rollen', zu: 0.538, t: 3000 },
        { a: 'warten', t: 800 },
        { a: 'rollen', zu: 0.667, t: 2000 },
        { a: 'rollen', zu: 0.891, t: 2600 },
        { a: 'warten', t: 900 },
        { a: 'rollen', zu: 1.000, t: 1400 },
        { a: 'warten', t: 1100 },
        { a: 'rollen', zu: 0.000, t: 1600 }
      ]
    }
  };

  var weich = function (t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; };

  function Geraet(el, drehbuch) {
    this.el = el;
    this.bahn = el.querySelector('[data-seiten]');
    this.zeiger = el.querySelector('.geraet__zeiger');
    this.schirm = el.querySelector('.geraet__schirm');
    this.drehbuch = drehbuch;
    this.i = 0; this.pos = 0; this.zx = 0.5; this.zy = 0.5;
    this.laeuft = false;
    this.setzen();
  }
  Geraet.prototype.messen = function () {
    /* Wie weit die Aufnahme hoeher ist als der Bildschirm. Einmal pro Abschnitt
       gemessen, nicht pro Bild — ein getBoundingClientRect im Animationsrahmen
       erzwingt jedes Mal ein neues Layout, und davon hat diese Seite genug. */
    var b = this.schirm.getBoundingClientRect();
    var g = this.bahn.getBoundingClientRect();
    this.strecke = Math.max(0, g.height - b.height);
  };
  Geraet.prototype.wecken = function () {
    /* Die zweite Haelfte der Telefon-Aufnahme liegt rund 7000 px unter der
       sichtbaren Kante. Der Browser laedt sie von sich aus erst spaet — der
       Zeiger waere dann ueber eine leere Flaeche gelaufen. Sobald die Buehne
       im Bild ist, holen wir sie bewusst. Vorher nicht: das ist der ganze
       Sinn von lazy. */
    if (this.geweckt) { return; }
    this.geweckt = true;
    var bilder = this.bahn.querySelectorAll('img'), i;
    for (i = 0; i < bilder.length; i++) { bilder[i].loading = 'eager'; }
  };
  Geraet.prototype.setzen = function () {
    if (this.strecke === undefined) { this.messen(); }
    this.bahn.style.transform = 'translate3d(0,' + (-this.pos * this.strecke) + 'px,0)';
    if (this.zeiger) {
      this.zeiger.style.transform = 'translate3d(' + (this.zx * 100) + '%,' + (this.zy * 100) + '%,0)';
    }
  };
  Geraet.prototype.sichtbar = function () {
    /* Auf schmalen Schirmen ist das Notebook ausgeblendet. Dann darf seine
       1100 px breite Aufnahme auch nicht geweckt werden — das waeren 286 kB
       fuer etwas, das niemand sieht. */
    return this.el.getClientRects().length > 0;
  };
  Geraet.prototype.start = function () {
    if (ruhig || !this.sichtbar()) { return; }
    this.wecken();
    if (this.laeuft) { return; }
    this.laeuft = true;
    this.schritt();
  };
  Geraet.prototype.halt = function () { this.laeuft = false; };
  Geraet.prototype.schritt = function () {
    if (!this.laeuft) { return; }
    var s = this.drehbuch[this.i % this.drehbuch.length];
    this.i++;
    var ich = this;
    var weiter = function () { if (ich.laeuft) { ich.schritt(); } };

    if (s.a === 'warten') { setTimeout(weiter, s.t); return; }
    if (s.a === 'klick') {
      this.zeiger && this.zeiger.classList.add('ist-klick');
      setTimeout(function () { ich.zeiger && ich.zeiger.classList.remove('ist-klick'); weiter(); }, 260);
      return;
    }
    if (s.a === 'laden') {
      this.el.classList.add('laedt');
      setTimeout(function () { ich.el.classList.remove('laedt'); weiter(); }, s.t);
      return;
    }
    this.messen();
    var vonP = this.pos, vonX = this.zx, vonY = this.zy;
    var nachP = s.a === 'rollen' ? s.zu : vonP;
    var nachX = s.a === 'zeiger' ? s.x : vonX;
    var nachY = s.a === 'zeiger' ? s.y : vonY;
    var t0 = performance.now();
    (function lauf(t) {
      if (!ich.laeuft) { return; }
      var k = Math.min((t - t0) / s.t, 1), e = weich(k);
      ich.pos = vonP + (nachP - vonP) * e;
      ich.zx = vonX + (nachX - vonX) * e;
      ich.zy = vonY + (nachY - vonY) * e;
      ich.setzen();
      if (k < 1) { requestAnimationFrame(lauf); } else { weiter(); }
    })(t0);
  };

  document.querySelectorAll('[data-buehne]').forEach(function (buehne) {
    var buch = DREHBUECHER[buehne.getAttribute('data-buehne')];
    if (!buch) { return; }
    var laptop = buehne.querySelector('.geraet--laptop');
    var handy  = buehne.querySelector('.geraet--handy');
    var teile = [];
    if (laptop) { teile.push(new Geraet(laptop, buch.laptop)); }
    if (handy)  { teile.push(new Geraet(handy, buch.handy)); }

    /* Nur laufen lassen, was zu sehen ist. */
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (e) {
        var da = e[0].isIntersecting && !document.hidden;
        teile.forEach(function (g) { da ? g.start() : g.halt(); });
      }, { rootMargin: '120px' }).observe(buehne);
    } else {
      teile.forEach(function (g) { g.start(); });
    }
    document.addEventListener('visibilitychange', function () {
      teile.forEach(function (g) { document.hidden ? g.halt() : g.start(); });
    });
    window.addEventListener('resize', function () {
      teile.forEach(function (g) { g.messen(); g.setzen(); g.start(); });
    }, { passive: true });

    /* ---- Tiefe beim Ueberfahren ---------------------------------------
       Die Karte neigt sich bereits (polish.js, +-5/6 Grad). Ein zweites
       Drehen der Szene obendrauf waere der doppelte Winkel und saehe nach
       Spielerei aus. Was der flachen Aufnahme wirklich fehlt, ist Parallaxe:
       das Telefon steht rund sieben Zentimeter VOR dem Notebook, also muss
       es beim Neigen einen anderen Weg gehen. Genau das macht dieser Block —
       und sonst nichts. Richtung und Betrag sind aus dem Kartenwinkel
       gerechnet, nicht geraten: bei rotateY(+t) wandert ein naeher Punkt
       nach rechts, bei rotateX(-t) nach unten. */
    if (ruhig || !feinerZeiger) { return; }
    var vorn = buehne.querySelector('[data-vorn]');
    if (!vorn) { return; }
    /* Auf der KARTE horchen, nicht auf der Buehne: polish.js neigt die ganze
       Karte, und die reicht unter die Aufnahme bis in den Text. Haengt die
       Parallaxe an der Buehne, faellt das Telefon flach zurueck, sobald der
       Zeiger in den Text wandert — waehrend die Karte noch schraeg steht. */
    var feld = buehne.closest ? (buehne.closest('.card') || buehne) : buehne;
    var zx = 0, zy = 0, ix = 0, iy = 0, drin = false, rahmen = null;

    function malen() {
      ix += (zx - ix) * 0.10;
      iy += (zy - iy) * 0.10;
      vorn.style.transform = 'translate3d(' + (ix * 12).toFixed(2) + 'px,' + (iy * 6).toFixed(2) + 'px,0)';
      if (Math.abs(zx - ix) + Math.abs(zy - iy) > 0.0015) {
        rahmen = requestAnimationFrame(malen);
        return;
      }
      rahmen = null;
      if (!drin) { vorn.style.willChange = ''; vorn.style.transform = ''; }
    }
    function anstossen() {
      if (rahmen) { return; }
      vorn.style.willChange = 'transform';
      rahmen = requestAnimationFrame(malen);
    }
    feld.addEventListener('pointermove', function (e) {
      var r = feld.getBoundingClientRect();
      zx = ((e.clientX - r.left) / r.width - 0.5) * 2;
      zy = ((e.clientY - r.top) / r.height - 0.5) * 2;
      drin = true;
      anstossen();
    }, { passive: true });
    feld.addEventListener('pointerleave', function () {
      zx = 0; zy = 0; drin = false;
      anstossen();
    }, { passive: true });
  });
})();
