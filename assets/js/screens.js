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
