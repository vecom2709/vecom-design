/* ==========================================================================
   story.js — Story-Scrolling der Startseite (02.10.2026, Uwe: „wichtige
   Textbausteine schöner, immersiver einblenden per Storyscrolling,
   insgesamt mehr Storyscrolling“ — S1 bis S7 mit „alles“ bestätigt).

     S1  „Perché fidarsi“ als Szene: Versprechen nacheinander, groß, golden
     S2  Leitsätze: Wörter hellen sich im Lesetempo auf
     S3  Ablauf als seitliche Fahrt mit wachsender Goldlinie
     S4  Leistungen als Kapitel mit stehender Nummer
     S5  Preisstapel: Posten fügen sich zur Summe
     S6  „Chi sono“: Foto öffnet sich, Text zeilenweise
     S7  Einleitungssätze zeilenweise scharf, Kapitel-Leiste rechts

   Regeln: Der Text bleibt Text im Dokument (Google, Vorleseprogramme).
   Ohne GSAP, bei „weniger Bewegung“ und ohne JS steht alles still und
   lesbar. Szenen mit Stehenbleiben (S1, S3, S4, Leiste) nur am großen
   Bildschirm; auf dem Handy bleibt die gewohnte, senkrechte Fassung.
   ========================================================================== */
(function () {
  'use strict';
  var gsap = window.gsap, ST = window.ScrollTrigger;
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!gsap || !ST || reduced) return;
  gsap.registerPlugin(ST);
  document.documentElement.classList.add('story-an');

  /* ---------- Hilfen ------------------------------------------------------ */
  /* Wörter in Spannen legen — der Text bleibt derselbe, nur umhüllt. */
  function woerter(el, klasse) {
    var text = el.textContent.replace(/\s+/g, ' ').trim();
    el.dataset.storyText = text;
    el.innerHTML = '';
    text.split(' ').forEach(function (w, i) {
      if (i) el.appendChild(document.createTextNode(' '));
      var s = document.createElement('span'); s.className = klasse; s.textContent = w; el.appendChild(s);
    });
    return el.querySelectorAll('.' + klasse);
  }
  /* Zeilen: Wörter nach ihrer Höhe gruppieren und je Zeile umhüllen. */
  function zeilen(el) {
    var ws = woerter(el, 'zw'), gruppen = [], oben = null;
    ws.forEach(function (w) {
      var t = w.offsetTop;
      if (oben === null || Math.abs(t - oben) > 4) { gruppen.push([]); oben = t; }
      gruppen[gruppen.length - 1].push(w.textContent);
    });
    el.innerHTML = '';
    gruppen.forEach(function (g) {
      var z = document.createElement('span'); z.className = 'zeile'; z.textContent = g.join(' '); el.appendChild(z);
    });
    return el.querySelectorAll('.zeile');
  }
  function zurueck(el) { if (el.dataset.storyText) el.textContent = el.dataset.storyText; }

  var mm = gsap.matchMedia();

  function aufbauen() {
    mm.revert();
    mm = gsap.matchMedia();

    /* ---------- S2 Leitsätze (alle Größen) ------------------------------ */
    mm.add('(min-width: 0px)', function () {
      var weg0 = [];
      document.querySelectorAll('[data-leitsatz] .leitsatz__text').forEach(function (p) {
        var ws = woerter(p, 'lw');
        gsap.to(ws, {
          color: function (i) { return i === ws.length - 1 ? '#f1d38b' : 'rgba(243,239,230,1)'; },
          stagger: 0.08, ease: 'none',
          scrollTrigger: { trigger: p, start: 'top 82%', end: 'bottom 42%', scrub: 0.6 },
        });
        weg0.push(function () { zurueck(p); });
      });

      /* ---------- S5 Preisstapel ---------------------------------------- */
      var stapel = document.querySelector('[data-preisstapel]');
      if (stapel) {
        var tl = gsap.timeline({ scrollTrigger: { trigger: stapel, start: 'top 85%', end: 'top 35%', scrub: 0.6 } });
        tl.from(stapel.querySelectorAll('li'), { y: -26, opacity: 0, stagger: 0.18, ease: 'power2.out' })
          .from(stapel.querySelector('.preisstapel__summe'), { opacity: 0, clipPath: 'inset(0 100% 0 0)', ease: 'power2.out' }, '>-0.05');
      }

      /* ---------- S7 Einleitungssätze zeilenweise ----------------------- */
      document.querySelectorAll('.sechead p, #about .about__text > p:not(.eyebrow):not(.about__role)').forEach(function (p) {
        if (!p.textContent.trim()) return;
        p.classList.add('in');   // die Zeilen tragen die Bewegung, nicht der Absatz
        var zs = zeilen(p);
        gsap.from(zs, {
          opacity: 0, y: 14, filter: 'blur(8px)', duration: 0.95, ease: 'expo.out', stagger: 0.09,
          scrollTrigger: { trigger: p, start: 'top 86%', once: true },
        });
        weg0.push(function () { zurueck(p); });
      });

      /* ---------- S6 Foto öffnet sich ----------------------------------- */
      var foto = document.querySelector('#about .about__photo');
      if (foto) {
        gsap.fromTo(foto, { clipPath: 'inset(14% 12% 14% 12% round 22px)' },
          { clipPath: 'inset(0% 0% 0% 0% round 0px)', ease: 'none',
            scrollTrigger: { trigger: foto, start: 'top 92%', end: 'top 28%', scrub: 0.6 } });
      }
      return function () { weg0.forEach(function (f) { f(); }); };
    });

    /* ---------- Szenen nur am großen Bildschirm ------------------------- */
    mm.add('(min-width: 900px)', function () {
      var aufraeumen = [];

      /* ---------- S1 Vertrauen ------------------------------------------ */
      var sek = document.querySelector('.vertrauen');
      var liste = sek && sek.querySelector('.vertrauen__liste');
      if (liste) {
        var items = [].slice.call(liste.children);
        var buehne = document.createElement('div'); buehne.className = 'vertrauen__buehne';
        liste.parentNode.insertBefore(buehne, liste); buehne.appendChild(liste);
        var kicker = document.createElement('p'); kicker.className = 'vertrauen__kicker'; kicker.setAttribute('aria-hidden', 'true');
        kicker.textContent = (sek.querySelector('h2') || {}).textContent || '';
        buehne.appendChild(kicker);
        var punkte = document.createElement('ol'); punkte.className = 'vertrauen__punkte'; punkte.setAttribute('aria-hidden', 'true');
        items.forEach(function () { punkte.appendChild(document.createElement('li')); });
        buehne.appendChild(punkte);
        var ende = liste.cloneNode(true); ende.className = 'vertrauen__liste vertrauen__liste--ende'; ende.setAttribute('aria-hidden', 'true');
        ende.removeAttribute('data-reveal');
        buehne.appendChild(ende);
        sek.classList.add('ist-szene');
        liste.classList.add('in'); liste.style.opacity = '1';

        var tl = gsap.timeline({
          defaults: { ease: 'power2.out' },
          scrollTrigger: {
            trigger: buehne, start: 'top top', end: '+=' + (items.length * 85) + '%', pin: true, scrub: 0.7,
            onUpdate: function (st) {
              var n = Math.min(items.length - 1, Math.floor(st.progress * (items.length + 0.6)));
              [].forEach.call(punkte.children, function (p, i) { p.classList.toggle('ist-an', i === n && st.progress < 0.93); });
            },
          },
        });
        gsap.set(ende, { opacity: 0, y: 30 });
        items.forEach(function (li, i) {
          var strong = li.querySelector('strong');
          tl.fromTo(li, { opacity: 0, y: 60, filter: 'blur(10px)' }, { opacity: 1, y: 0, filter: 'blur(0px)', duration: 1 })
            .to(strong, { backgroundPosition: '0% 0', duration: 1, ease: 'none' }, '<0.2')
            .to(li, { duration: 0.6 })
            .to(li, { opacity: 0, y: -60, filter: 'blur(8px)', duration: 0.9, ease: 'power2.in' });
        });
        tl.to(ende, { opacity: 1, y: 0, duration: 1 });
        aufraeumen.push(function () {
          sek.classList.remove('ist-szene');
          ende.remove(); punkte.remove(); kicker.remove();
          buehne.parentNode.insertBefore(liste, buehne); buehne.remove();
          gsap.set(items.concat(items.map(function (li) { return li.querySelector('strong'); })), { clearProps: 'all' });
        });
      }

      /* ---------- S3 Ablauf als seitliche Fahrt ------------------------- */
      var fahrt = document.querySelector('[data-zeitfahrt]');
      var ol = fahrt && fahrt.querySelector('.zeitleiste');
      if (ol) {
        fahrt.classList.add('ist-fahrt');
        ol.classList.add('in'); ol.style.opacity = '1';
        var weg = function () { return Math.max(0, ol.scrollWidth - fahrt.clientWidth + 40); };
        var tw = gsap.to(ol, {
          x: function () { return -weg(); }, ease: 'none',
          scrollTrigger: {
            trigger: fahrt, start: 'center center', end: function () { return '+=' + (weg() + window.innerHeight * 0.4); },
            pin: true, scrub: 0.7, invalidateOnRefresh: true,
            onUpdate: function (st) {
              ol.style.setProperty('--fahrt', st.progress.toFixed(3));
              /* Betrag und Zusage erscheinen, sobald der Schritt ins Bild fährt */
              var grenze = window.innerWidth * 0.8;
              [].forEach.call(ol.children, function (li) { li.classList.toggle('ist-da', li.getBoundingClientRect().left < grenze); });
            },
            onRefresh: function (st) { [].forEach.call(ol.children, function (li) { li.classList.toggle('ist-da', li.getBoundingClientRect().left < window.innerWidth * 0.8); }); },
          },
        });
        aufraeumen.push(function () { [].forEach.call(ol.children, function (li) { li.classList.remove('ist-da'); }); fahrt.classList.remove('ist-fahrt'); ol.style.removeProperty('--fahrt'); gsap.set(ol, { clearProps: 'transform' }); });
      }

      /* ---------- Kapitel-Leiste (S7) ----------------------------------- */
      var ziele = [['#betrieb', 'nav.betrieb'], ['#work', 'nav.work'], ['#services', 'nav.services'], ['#plans', 'nav.plans'],
                   ['#process', 'nav.process'], ['#about', null], ['#contact', 'nav.contact']];
      var leiste = document.createElement('ol'); leiste.className = 'kapitel-leiste';
      leiste.setAttribute('aria-label', (document.documentElement.lang || 'it').slice(0, 2) === 'de' ? 'Kapitel' : (document.documentElement.lang || '').slice(0, 2) === 'en' ? 'Chapters' : 'Capitoli');
      ziele.forEach(function (z) {
        var ziel = document.querySelector(z[0]);
        if (!ziel || ziel.hidden) return;
        var wort = z[1] ? (document.querySelector('[data-i18n="' + z[1] + '"]') || {}).textContent
                        : ((ziel.querySelector('.eyebrow') || {}).textContent || '');
        var li = document.createElement('li');
        var a = document.createElement('a'); a.href = z[0];
        var s = document.createElement('span'); s.textContent = (wort || '').trim();
        a.appendChild(s); a.appendChild(document.createElement('i')); li.appendChild(a); leiste.appendChild(li);
        a.addEventListener('click', function (e) {
          e.preventDefault();
          if (window.__vecomLenis) window.__vecomLenis.scrollTo(ziel, { offset: -60, duration: 1.2 });
          else ziel.scrollIntoView({ behavior: 'smooth' });
        });
        ST.create({ trigger: ziel, start: 'top 55%', end: 'bottom 55%',
          onToggle: function (st) { a.classList.toggle('ist-an', st.isActive); } });
      });
      document.body.appendChild(leiste);
      ST.create({ trigger: document.body, start: function () { return window.innerHeight * 0.85; }, end: 'max',
        onToggle: function (st) { leiste.classList.toggle('ist-sichtbar', st.isActive); } });
      aufraeumen.push(function () { leiste.remove(); });

      return function () { aufraeumen.forEach(function (f) { f(); }); };
    });

    /* ---------- S4 Leistungen als Kapitel ------------------------------- */
    mm.add('(min-width: 1120px)', function () {
      var nr = document.querySelector('.kapitel-nr__zahl');
      if (!nr) return;
      var karten = document.querySelectorAll('#services .service');
      karten.forEach(function (k, i) {
        ST.create({
          trigger: k, start: 'top 58%', end: 'bottom 58%',
          onToggle: function (st) {
            k.classList.toggle('ist-aktiv', st.isActive);
            if (!st.isActive) return;
            var neu = String(i + 1).padStart(2, '0');
            if (nr.textContent === neu) return;
            gsap.fromTo(nr, { yPercent: st.direction > 0 ? 30 : -30, opacity: 0 },
              { yPercent: 0, opacity: 1, duration: 0.55, ease: 'expo.out', onStart: function () { nr.textContent = neu; } });
          },
        });
      });
      return function () { karten.forEach(function (k) { k.classList.remove('ist-aktiv'); }); nr.textContent = '01'; };
    });

    ST.refresh();
  }

  /* Erst wenn die Texte in der Seitensprache stehen (app.js setzt
     data-i18n-fertig) — sonst würden die Wörter der Vorlage zerlegt. */
  var gebaut = false;
  function los() { if (gebaut) return; gebaut = true; aufbauen(); }
  if (document.documentElement.getAttribute('data-i18n-fertig')) los();
  document.addEventListener('vecom:sprache', function () { gebaut = false; los(); });
  setTimeout(los, 1500);
  window.addEventListener('load', function () { ST.refresh(); });
  /* Nur bei geänderter Breite neu bauen (Zeilen brechen anders) — die
     Adressleiste am Handy ändert nur die Höhe. */
  var rz, breite = window.innerWidth;
  window.addEventListener('resize', function () {
    if (Math.abs(window.innerWidth - breite) < 2) return;
    breite = window.innerWidth;
    clearTimeout(rz);
    rz = setTimeout(function () { if (gebaut) aufbauen(); }, 350);
  });
})();
