/* ==========================================================================
   VECOM DESIGN — app.js
   Keine externen Bibliotheken. Alles läuft auch ohne Netz.
   Reihenfolge: 1 Sprache · 2 Navigation · 3 Bewegung · 4 Formular
   ========================================================================== */
(function () {
  'use strict';

  var DICT = window.VECOM_I18N || {};
  var LANGS = ['it', 'de', 'en'];
  var DEFAULT = 'it';                       // Domain .it → Italienisch zuerst
  var STORE = 'vecom-lang';
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  document.body.classList.add('js');

  /* ---------- 1. Sprache -------------------------------------------------- */
  function get(lang, path) {
    return path.split('.').reduce(function (o, k) { return (o || {})[k]; }, DICT[lang]);
  }

  function pickLang() {
    // Statisch vorgerenderte Sprachseite (/de/, /en/): die URL entscheidet,
    // nicht der gespeicherte Wunsch — sonst zeigt /de/ plötzlich Italienisch.
    var fixed = document.documentElement.getAttribute('data-lang-fixed');
    if (fixed && LANGS.indexOf(fixed) > -1) return fixed;
    var url = new URLSearchParams(location.search).get('lang');
    if (LANGS.indexOf(url) > -1) return url;
    try {
      var saved = localStorage.getItem(STORE);
      if (LANGS.indexOf(saved) > -1) return saved;
    } catch (e) {}
    var nav = (navigator.languages || [navigator.language || ''])
      .map(function (l) { return String(l).slice(0, 2).toLowerCase(); });
    for (var i = 0; i < nav.length; i++) if (LANGS.indexOf(nav[i]) > -1) return nav[i];
    return DEFAULT;
  }

  /* ======================================================================
     DIE SPRACHE, DIE GEBRAUCHT WIRD — UND NUR DIE

     Vorher standen alle drei Sprachen in einer Datei von 137 KB, und jede
     Seite lud sie ganz. Benutzt wurde ein Drittel: Die Umschaltung oben
     rechts ist auf den meisten Seiten ein Verweis auf /de/ oder /en/, kein
     Tausch im laufenden Dokument.

     Vier Seiten schalten doch im Dokument um (legal, pakete, danke, 404).
     Dort steht beim Laden Italienisch bereit, und die zweite Sprache kommt
     in dem Moment, in dem jemand den Knopf drueckt.

     Woher die Adresse kommt: aus dem Skript-Tag, das schon dasteht. Die
     Seiten liegen in verschiedenen Tiefen (/, /de/, /cockpit/), und ein
     fest verdrahteter Pfad waere auf zwei Dritteln davon falsch. Der
     Sprachcode wird darin getauscht, sonst bleibt alles stehen — auch die
     Versionsnummer, sonst umginge das Nachladen den Zwischenspeicher. */
  var QUELLEN = (function () {
    var aus = [];
    [].slice.call(document.scripts).forEach(function (el) {
      var m = /^(.*\/)?(i18n|legal)-(it|de|en)\.js(\?.*)?$/.exec(el.getAttribute('src') || '');
      if (m) { aus.push({ pfad: m[1] || '', name: m[2], v: m[4] || '' }); }
    });
    return aus;
  })();

  var laedt = {};
  function nachladen(lang, fertig) {
    if (DICT[lang] || !QUELLEN.length) { fertig(); return; }
    if (laedt[lang]) { laedt[lang].push(fertig); return; }
    laedt[lang] = [fertig];
    var offen = QUELLEN.length;
    function eins() {
      if (--offen > 0) { return; }
      DICT = window.VECOM_I18N || DICT;
      var rufe = laedt[lang];
      /* Der Eintrag bleibt stehen, wenn nichts ankam — sonst wuerde bei
         jedem weiteren Klick derselbe fehlgeschlagene Ladeversuch neu
         beginnen. */
      if (DICT[lang]) { delete laedt[lang]; }
      rufe.forEach(function (f) { f(); });
    }
    QUELLEN.forEach(function (q) {
      var el = document.createElement('script');
      el.src = q.pfad + q.name + '-' + lang + '.js' + q.v;
      el.onload = el.onerror = eins;
      document.head.appendChild(el);
    });
  }

  function apply(lang) {
    /* Faellt die gewuenschte Sprache aus, wird nicht auf Italienisch
       zurueckgefallen, wenn Italienisch gar nicht geladen ist: Auf /de/
       liegt nur Deutsch, und ein Rueckfall auf ein leeres Woerterbuch
       loescht keine Texte — er laesst nur alles stehen. Genommen wird,
       was da ist. */
    if (!DICT[lang]) {
      var da = LANGS.filter(function (l) { return DICT[l]; });
      if (!da.length) { return; }
      lang = da.indexOf(DEFAULT) > -1 ? DEFAULT : da[0];
    }

    // Textknoten
    document.querySelectorAll('[data-i18n]').forEach(function (el) {
      var v = get(lang, el.getAttribute('data-i18n'));
      if (typeof v === 'string') el.textContent = v;
    });

    // Listen: ein Schlüssel, Einträge mit | getrennt
    document.querySelectorAll('[data-i18n-list]').forEach(function (el) {
      var v = get(lang, el.getAttribute('data-i18n-list'));
      if (typeof v !== 'string') return;
      el.innerHTML = '';
      v.split('|').forEach(function (item) {
        var li = document.createElement('li');
        var txt = item.trim();
        // Zeilen, die auf ":" enden, sind Zwischenüberschriften, keine Merkmale
        if (/:$/.test(txt)) li.className = 'is-lead';
        li.textContent = txt;
        el.appendChild(li);
      });
    });

    // Attribute: "placeholder:contact.phName, aria-label:nav.cta"
    document.querySelectorAll('[data-i18n-attr]').forEach(function (el) {
      el.getAttribute('data-i18n-attr').split(',').forEach(function (pair) {
        var p = pair.split(':');
        var v = get(lang, (p[1] || '').trim());
        if (typeof v === 'string') el.setAttribute(p[0].trim(), v);
      });
    });

    // Laufband: Inhalt zweimal, damit die Schleife nahtlos ist
    var track = document.querySelector('[data-marquee]');
    if (track) {
      var text = get(lang, 'marquee') || '';
      track.innerHTML = '';
      for (var i = 0; i < 2; i++) {
        var s = document.createElement('span');
        s.textContent = text;
        if (i === 1) s.setAttribute('aria-hidden', 'true');
        track.appendChild(s);
      }
    }

    // Kopfdaten der Seite
    var t = get(lang, document.documentElement.getAttribute('data-title-key') || 'meta.title');
    var d = get(lang, 'meta.desc');
    if (t) document.title = t;
    var md = document.querySelector('meta[name="description"]');
    if (md && d) md.setAttribute('content', d);
    var ot = document.querySelector('meta[property="og:title"]');
    if (ot && t) ot.setAttribute('content', t);
    var od = document.querySelector('meta[property="og:description"]');
    if (od && d) od.setAttribute('content', d);
    var ol = document.querySelector('meta[property="og:locale"]');
    var loc = get(lang, 'meta.locale');
    if (ol && loc) ol.setAttribute('content', loc);

    document.documentElement.lang = lang;
    document.querySelectorAll('[data-lang]').forEach(function (b) {
      b.setAttribute('aria-pressed', String(b.getAttribute('data-lang') === lang));
    });
    if (!document.documentElement.getAttribute('data-lang-fixed')) {
      try { localStorage.setItem(STORE, lang); } catch (e) {}
    }
  }

  /* Beim Laden: Steht die gewuenschte Sprache noch nicht bereit, wird sie
     geholt. Das betrifft nur die vier Seiten mit Knopf-Umschalter und nur
     Besucher, die vorher einmal umgeschaltet haben — fuer sie steht kurz
     Italienisch da, dann ihre Sprache. Auf allen anderen Seiten liegt die
     richtige Sprache von Anfang an vor, weil die Seite selbst fest auf sie
     eingestellt ist. */
  var gewuenscht = pickLang();
  apply(gewuenscht);
  if (!DICT[gewuenscht]) { nachladen(gewuenscht, function () { apply(gewuenscht); }); }

  document.querySelectorAll('[data-lang]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var ziel = btn.getAttribute('data-lang');
      document.body.classList.remove('nav-open');
      if (DICT[ziel]) { apply(ziel); return; }
      btn.setAttribute('aria-busy', 'true');
      nachladen(ziel, function () {
        btn.removeAttribute('aria-busy');
        apply(ziel);
      });
    });
  });

  /* ---------- 2. Navigation ---------------------------------------------- */
  var header = document.querySelector('.header');
  var burger = document.querySelector('.burger');

  if (burger) {
    burger.addEventListener('click', function () {
      var open = document.body.classList.toggle('nav-open');
      burger.setAttribute('aria-expanded', String(open));
    });
  }
  document.querySelectorAll('.nav a').forEach(function (a) {
    a.addEventListener('click', function () {
      document.body.classList.remove('nav-open');
      if (burger) burger.setAttribute('aria-expanded', 'false');
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') document.body.classList.remove('nav-open');
  });

  // Aktiver Menüpunkt nach sichtbarem Abschnitt
  var sections = [].slice.call(document.querySelectorAll('main section[id]'));
  var navLinks = {};
  document.querySelectorAll('.nav a[href^="#"]').forEach(function (a) {
    navLinks[a.getAttribute('href').slice(1)] = a;
  });
  if (sections.length && 'IntersectionObserver' in window) {
    var spy = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        var link = navLinks[en.target.id];
        if (!link) return;
        if (en.isIntersecting) {
          Object.keys(navLinks).forEach(function (k) { navLinks[k].removeAttribute('aria-current'); });
          link.setAttribute('aria-current', 'true');
        }
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    sections.forEach(function (s) { spy.observe(s); });
  }

  /* ---------- 3. Bewegung ------------------------------------------------- */

  // 3a Reveals — Endzustand ist im CSS Standard, hier kommt nur das Timing dazu
  var revealables = [].slice.call(document.querySelectorAll('[data-reveal], .line-mask'));
  if ('IntersectionObserver' in window && !reduced) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        en.target.classList.add('in');
        io.unobserve(en.target);
      });
    }, { rootMargin: '0px 0px -12% 0px', threshold: 0.08 });

    var vh = window.innerHeight || 800;
    revealables.forEach(function (el) {
      // Staffelung innerhalb einer Gruppe: 60 ms pro Geschwisterelement
      var group = el.closest('[data-stagger]');
      if (group) {
        var kids = [].slice.call(group.querySelectorAll('[data-reveal], .line-mask'));
        el.style.setProperty('--delay', Math.min(kids.indexOf(el), 8) * 60 + 'ms');
      }
      // Was beim Laden schon im Bild steht, gehört zum Auftritt und wird sofort
      // eingeblendet — sonst fehlt der untere Rand des ersten Bildschirms.
      if (el.getBoundingClientRect().top < vh) {
        requestAnimationFrame(function () { el.classList.add('in'); });
      } else {
        io.observe(el);
      }
    });
  } else {
    revealables.forEach(function (el) { el.classList.add('in'); });
  }

  // 3b Kopfzeile und Fortschrittsbalken
  var progress = document.querySelector('.progress');
  var ticking = false;
  /* Die Kopfleiste geht beim Abwaertsscrollen aus dem Weg und kommt beim
     Aufwaertsscrollen zurueck. Sie bleibt aber stehen, wenn man ganz oben
     ist, wenn das Menue offen ist oder wenn der Tastaturfokus in ihr liegt —
     eine Leiste, die unter dem Finger wegrutscht, waehrend man sie bedient,
     ist kein Feature. */
  var letztesY = window.scrollY || window.pageYOffset;
  var burger = document.querySelector('.burger');
  function kopfLeiste(y) {
    if (!header) { return; }
    var offen = burger && burger.getAttribute('aria-expanded') === 'true';
    var drin = header.contains(document.activeElement);
    var runter = y > letztesY + 4;
    var hoch = y < letztesY - 4;
    if (y <= 90 || offen || drin || hoch) { header.classList.remove('ist-weg'); }
    else if (runter && y > 140) { header.classList.add('ist-weg'); }
    if (Math.abs(y - letztesY) > 3) { letztesY = y; }
  }

  function onScroll() {
    var y = window.scrollY || window.pageYOffset;
    if (header) header.classList.toggle('is-stuck', y > 24);
    kopfLeiste(y);
    document.body.classList.toggle('scrolled', y > 40);
    if (progress) {
      var max = document.documentElement.scrollHeight - window.innerHeight;
      progress.style.setProperty('--p', max > 0 ? (y / max).toFixed(4) : 0);
    }
    ticking = false;
  }
  window.addEventListener('scroll', function () {
    if (!ticking) { ticking = true; requestAnimationFrame(onScroll); }
  }, { passive: true });
  onScroll();

  // 3c Hero: Bildmarke reagiert auf Zeiger und Scrollposition
  var mark = document.querySelector('.hero__mark');
  var heroGrid = document.querySelector('.hero__grid');
  if (mark && !reduced) {
    var tx = 0, ty = 0, cx = 0, cy = 0, raf = null;
    window.addEventListener('pointermove', function (e) {
      if (e.pointerType !== 'mouse') return;
      tx = (e.clientX / window.innerWidth - 0.5) * 34;
      ty = (e.clientY / window.innerHeight - 0.5) * 26;
      if (!raf) raf = requestAnimationFrame(loop);
    }, { passive: true });

    function loop() {
      cx += (tx - cx) * 0.06;
      cy += (ty - cy) * 0.06;
      mark.style.setProperty('--mx', cx.toFixed(2) + 'px');
      mark.style.setProperty('--my', cy.toFixed(2) + 'px');
      if (heroGrid) heroGrid.style.transform = 'perspective(700px) rotateX(' + (cy * 0.08).toFixed(2) + 'deg) translate3d(' + (cx * -0.3).toFixed(2) + 'px,0,0)';
      raf = (Math.abs(tx - cx) > 0.1 || Math.abs(ty - cy) > 0.1) ? requestAnimationFrame(loop) : null;
    }

    // Beim Scrollen zieht sich die Marke leicht zurück — Tiefe statt Parallax-Kitsch
    window.addEventListener('scroll', function () {
      var y = Math.min(window.scrollY / (window.innerHeight || 1), 1);
      mark.style.setProperty('--mk', (1 + y * 0.16).toFixed(3));
      mark.style.opacity = (0.17 - y * 0.14).toFixed(3);
    }, { passive: true });
  }

  // 3d Leistungskarten: Lichtkegel folgt dem Zeiger
  document.querySelectorAll('.service').forEach(function (card) {
    card.addEventListener('pointermove', function (e) {
      var r = card.getBoundingClientRect();
      card.style.setProperty('--gx', ((e.clientX - r.left) / r.width * 100).toFixed(1) + '%');
    }, { passive: true });
  });

  // 3e Eigener Cursor plus magnetische Knöpfe
  var fine = window.matchMedia('(pointer: fine)').matches;
  if (fine && !reduced) {
    var ring = document.querySelector('.cursor');
    var dot = document.querySelector('.cursor-dot');
    if (ring && dot) {
      var mxp = window.innerWidth / 2, myp = window.innerHeight / 2, rx = mxp, ry = myp;
      document.addEventListener('pointermove', function (e) {
        document.body.classList.add('cursor-ready');
        mxp = e.clientX; myp = e.clientY;
        dot.style.transform = 'translate3d(' + mxp + 'px,' + myp + 'px,0)';
      }, { passive: true });
      (function ride() {
        rx += (mxp - rx) * 0.16; ry += (myp - ry) * 0.16;
        ring.style.transform = 'translate3d(' + rx.toFixed(2) + 'px,' + ry.toFixed(2) + 'px,0)';
        requestAnimationFrame(ride);
      })();
      document.querySelectorAll('a, button, input, textarea, select, .card').forEach(function (el) {
        el.addEventListener('pointerenter', function () { document.body.classList.add('is-hovering'); });
        el.addEventListener('pointerleave', function () { document.body.classList.remove('is-hovering'); });
      });
    }

    document.querySelectorAll('[data-magnetic]').forEach(function (el) {
      el.addEventListener('pointermove', function (e) {
        var r = el.getBoundingClientRect();
        el.style.transform = 'translate(' + ((e.clientX - r.left - r.width / 2) * 0.16).toFixed(2) + 'px,' +
          ((e.clientY - r.top - r.height / 2) * 0.22).toFixed(2) + 'px)';
      });
      el.addEventListener('pointerleave', function () { el.style.transform = ''; });
    });
  }

  /* ---------- 4. Formular ------------------------------------------------- */
  // Ohne Server: das Formular baut eine fertige E-Mail. Kein Datenversand von hier.
  var form = document.querySelector('.form');
  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!form.reportValidity()) return;
      var d = new FormData(form);
      var to = form.getAttribute('data-mailto') || '';
      var subject = 'Vecom Design — ' + (d.get('type') || 'Anfrage') + ' · ' + (d.get('name') || '');
      var body = [
        (d.get('name') || ''),
        (d.get('email') || ''),
        (d.get('type') || ''),
        '',
        (d.get('message') || '')
      ].join('\n');
      window.location.href = 'mailto:' + to +
        '?subject=' + encodeURIComponent(subject) +
        '&body=' + encodeURIComponent(body);
    });
  }

  /* ---------- 5. Jahr in der Fußzeile ------------------------------------ */
  var year = document.querySelector('[data-year]');
  if (year) year.textContent = new Date().getFullYear();
})();


/* Hier stand der Ueberspringen-Knopf des Auftaktfilms. Der Film ist weg —
   die Szene macht ihre Eroeffnung selbst, und 1,16 MB sind es nicht wert,
   dass jemand sie ueberspringen darf. */
