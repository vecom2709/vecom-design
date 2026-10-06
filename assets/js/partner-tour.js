/* Einführung für Partner (06.10.2026, Uwe: Ja zu Tour, Mini-Touren, Checkliste, „?“).
 *
 * Liest die Daten aus <script type="application/json" id="tour-daten"> (PartnerTour::daten).
 * Ein Schritt zeigt auf [data-tour="…"]; der erste sichtbare Kandidat gilt. Fehlt er, steht die
 * Sprechblase mittig — die Tour bleibt nie hängen. Liegt ein Schritt auf einer anderen Seite,
 * führt „Weiter“ dorthin und die Tour läuft dort am selben Schritt weiter (?tour=…&ab=…).
 * Ohne Bibliothek, ohne Inline-Skript (CSP), Tastatur: ←/→, Esc = überspringen.
 */
(function () {
  'use strict';
  var quelle = document.getElementById('tour-daten');
  if (!quelle) { return; }
  var D; try { D = JSON.parse(quelle.textContent || '{}'); } catch (e) { return; }
  var T = D.texte || {};
  var reduziert = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------------- Melden (Tour fertig/übersprungen, Ereignisse der Checkliste) ---------------- */
  function melden(felder) {
    if (!D.melden) { return; }
    var fd = new FormData();
    fd.append('_csrf', D.csrf);
    Object.keys(felder).forEach(function (k) { fd.append(k, felder[k]); });
    try {
      if (navigator.sendBeacon && navigator.sendBeacon(D.melden, fd)) { return; }
    } catch (e) { }
    try { fetch(D.melden, { method: 'POST', body: fd, credentials: 'same-origin', keepalive: true }); } catch (e) { }
  }
  var gemeldet = {};
  function ereignis(ev) { if (gemeldet[ev]) { return; } gemeldet[ev] = 1; melden({ tat: 'tour_ev', ev: ev }); }

  /* Was nur der Browser sieht — Kopieren, Teilen, die eigene Seite, eine Nachricht. */
  document.addEventListener('click', function (e) {
    var el = e.target.closest ? e.target.closest('a, button') : null;
    if (!el) { return; }
    var href = el.getAttribute('href') || '';
    if (el.hasAttribute('data-angeschrieben')) { ereignis('nachricht'); return; }   // Text an einen Betrieb — kein Teilen
    if (el.hasAttribute('data-cc-kopie') || el.hasAttribute('data-kopie') || el.hasAttribute('data-kopie-text')) {
      ereignis(el.closest('#kurzlink') || /kl|link/i.test(el.getAttribute('data-cc-kopie') || '') ? 'link_kopiert' : 'geteilt');
    }
    if (/^https:\/\/(wa\.me|t\.me|www\.facebook\.com\/sharer)/.test(href) && !el.hasAttribute('data-angeschrieben')) { ereignis('geteilt'); }
    if (D.code && href.indexOf('/p/' + D.code) !== -1) { ereignis('seite'); }
  }, true);
  try { if (window.matchMedia('(display-mode: standalone)').matches || navigator.standalone) { ereignis('app'); } } catch (e) { }

  /* ---------------- Die Tour ---------------- */
  var lauf = null;   // { tour, i, schritte }
  var schicht, kegel, blase, titel, text, zaehler, knZurueck, knWeiter, knWeg, vorherFokus;

  function sichtbar(el) {
    if (!el) { return false; }
    var r = el.getClientRects();
    if (!r.length) { return false; }
    var st = window.getComputedStyle(el);
    return st.visibility !== 'hidden' && st.display !== 'none';
  }
  function zielFinden(s) {
    var kandidaten = s.ziel || [];
    for (var i = 0; i < kandidaten.length; i++) {
      var alle = document.querySelectorAll('[data-tour="' + kandidaten[i] + '"]');
      for (var j = 0; j < alle.length; j++) {
        var el = alle[j];
        if (sichtbar(el)) { return el; }
        /* Liegt das Ziel in einer zugeklappten Liste, klappen wir sie auf — nur Ansicht, nichts wird geändert. */
        var d = el.parentElement ? el.parentElement.closest('details:not([open])') : null;
        while (d) { d.open = true; d = d.parentElement ? d.parentElement.closest('details:not([open])') : null; }
        if (sichtbar(el)) { return el; }
      }
    }
    return null;
  }
  function gleicheSeite(a, b) { return a === b || (a.indexOf('voll') === 0 && b.indexOf('voll') === 0); }
  function urlMit(seite, tour, ab) {
    var u = D.urls[seite]; if (!u) { return null; }
    var h = ''; var i = u.indexOf('#'); if (i !== -1) { h = u.slice(i); u = u.slice(0, i); }
    return u + (u.indexOf('?') === -1 ? '?' : '&') + 'tour=' + encodeURIComponent(tour) + '&ab=' + encodeURIComponent(ab) + h;
  }

  function bauen() {
    if (schicht) { return; }
    schicht = document.createElement('div'); schicht.className = 'tour-schicht'; schicht.setAttribute('aria-hidden', 'true');
    kegel = document.createElement('div'); kegel.className = 'tour-kegel';
    blase = document.createElement('div'); blase.className = 'tour-blase';
    blase.setAttribute('role', 'dialog'); blase.setAttribute('aria-modal', 'true'); blase.setAttribute('aria-labelledby', 'tour-titel');
    blase.innerHTML = '<p class="tour-zaehler"></p><h2 id="tour-titel" class="tour-titel"></h2><p class="tour-text" id="tour-text"></p>'
      + '<div class="tour-knoepfe"><button type="button" class="tour-weg"></button><span class="tour-luecke"></span>'
      + '<button type="button" class="tour-zurueck"></button><button type="button" class="tour-weiter"></button></div>';
    blase.setAttribute('aria-describedby', 'tour-text');
    document.body.appendChild(schicht); document.body.appendChild(kegel); document.body.appendChild(blase);
    titel = blase.querySelector('.tour-titel'); text = blase.querySelector('.tour-text'); zaehler = blase.querySelector('.tour-zaehler');
    knZurueck = blase.querySelector('.tour-zurueck'); knWeiter = blase.querySelector('.tour-weiter'); knWeg = blase.querySelector('.tour-weg');
    knZurueck.textContent = T.zurueck; knWeg.textContent = T.ueberspringen;
    knWeiter.addEventListener('click', function () { gehe(lauf.i + 1); });
    knZurueck.addEventListener('click', function () { gehe(lauf.i - 1); });
    knWeg.addEventListener('click', function () { ende('uebersprungen'); });
    schicht.addEventListener('click', function () { /* Klick daneben schließt nicht — erst lesen, dann weiter. */ blase.querySelector('.tour-weiter').focus(); });
    document.addEventListener('keydown', taste, true);
    document.addEventListener('click', klickDurch, true);
    window.addEventListener('resize', setzen); window.addEventListener('scroll', setzen, true);
  }
  function abbauen() {
    if (!schicht) { return; }
    document.removeEventListener('keydown', taste, true);
    document.removeEventListener('click', klickDurch, true);
    window.removeEventListener('resize', setzen); window.removeEventListener('scroll', setzen, true);
    [schicht, kegel, blase].forEach(function (x) { x.remove(); });
    schicht = kegel = blase = null;
    document.documentElement.classList.remove('tour-an');
    if (vorherFokus && vorherFokus.focus) { try { vorherFokus.focus({ preventScroll: true }); } catch (e) { } }
  }
  function taste(e) {
    if (!lauf) { return; }
    if (e.key === 'Escape') { e.preventDefault(); ende('uebersprungen'); }
    else if (e.key === 'ArrowRight') { e.preventDefault(); gehe(lauf.i + 1); }
    else if (e.key === 'ArrowLeft') { e.preventDefault(); if (lauf.i > 0) { gehe(lauf.i - 1); } }
    else if (e.key === 'Tab') {   // Fokus bleibt in der Sprechblase
      var f = Array.prototype.filter.call(blase.querySelectorAll('button'), function (b) { return !b.hidden; });
      var i = f.indexOf(document.activeElement);
      if (e.shiftKey && i <= 0) { e.preventDefault(); f[f.length - 1].focus(); }
      else if (!e.shiftKey && i === f.length - 1) { e.preventDefault(); f[0].focus(); }
    }
  }

  var aktuellesZiel = null;
  /* Der leuchtende Knopf ist echt: Ein Tipp darauf tut, was er immer tut — und zählt als „gesehen“.
     Alles andere bleibt gesperrt, solange die Sprechblase offen ist. */
  function klickDurch(e) {
    if (!lauf || !blase || blase.contains(e.target)) { return; }
    if (aktuellesZiel && aktuellesZiel.contains(e.target)) {
      var letzter = lauf.i === lauf.schritte.length - 1;
      var tour = lauf.tour;
      if (letzter || e.target.closest('a[href]:not([href^="#"])')) {
        melden({ tat: 'tour_stand', tour: tour, status: letzter ? 'fertig' : 'uebersprungen' });
        try { localStorage.setItem('vd_tour_' + tour, letzter ? 'fertig' : 'uebersprungen'); } catch (x) { }
        lauf = null; aktuellesZiel = null; abbauen();
      }
      return;
    }
    e.preventDefault(); e.stopPropagation();
    if (knWeiter) { knWeiter.focus(); }
  }
  function setzen() {
    if (!lauf || !blase) { return; }
    var schmal = window.innerWidth < 620;
    blase.classList.toggle('unten', schmal);
    if (!aktuellesZiel) {
      kegel.style.display = 'none'; schicht.classList.add('voll'); schicht.style.pointerEvents = '';
      blase.classList.add('mitte'); blase.style.top = ''; blase.style.left = '';
      return;
    }
    schicht.classList.remove('voll'); kegel.style.display = 'block'; blase.classList.remove('mitte');
    schicht.style.pointerEvents = 'none';   // der Knopf im Lichtkegel bleibt anklickbar (klickDurch sperrt den Rest)
    var r = aktuellesZiel.getBoundingClientRect(), rand = 8;
    kegel.style.top = (r.top - rand) + 'px'; kegel.style.left = (r.left - rand) + 'px';
    kegel.style.width = (r.width + 2 * rand) + 'px'; kegel.style.height = (r.height + 2 * rand) + 'px';
    if (schmal) { blase.style.top = ''; blase.style.left = '';
      /* Am Handy steht die Blase unten — außer das Ziel liegt selbst unten (Leiste), dann oben. */
      blase.classList.toggle('oben', r.top > window.innerHeight * 0.55);
      return; }
    blase.classList.remove('oben');
    var b = blase.getBoundingClientRect(), abstand = 14;
    var top = r.bottom + abstand;
    if (top + b.height > window.innerHeight - 10) { top = Math.max(10, r.top - b.height - abstand); }
    var left = Math.min(Math.max(10, r.left), window.innerWidth - b.width - 10);
    blase.style.top = top + 'px'; blase.style.left = left + 'px';
  }

  function gehe(i) {
    var s = lauf.schritte;
    if (i >= s.length) { ende('fertig'); return; }
    if (i < 0) { i = 0; }
    var schritt = s[i];
    if (!gleicheSeite(schritt.seite, D.seite)) {
      var u = urlMit(schritt.seite, lauf.tour, schritt.k);
      if (u) { location.href = u; return; }
    } else if (schritt.seite !== D.seite && D.urls[schritt.seite]) {
      /* Gleiche Seite, anderer Teil (Partnerseite: Reiter) — der Anker schaltet ihn um. */
      var h = D.urls[schritt.seite].split('#')[1];
      if (h && location.hash !== '#' + h) { location.hash = h; D.seite = schritt.seite; setTimeout(function () { gehe(i); }, 160); return; }
    }
    lauf.i = i;
    aktuellesZiel = zielFinden(schritt);
    zaehler.textContent = (T.von || '{a}/{b}').replace('{a}', i + 1).replace('{b}', s.length);
    titel.textContent = schritt.titel;
    text.textContent = schritt.text + (!aktuellesZiel && schritt.ziel && schritt.ziel.length ? ' ' + T.fehlt : '');
    knZurueck.hidden = i === 0;
    knWeiter.textContent = i === s.length - 1 ? T.fertig : T.weiter;
    if (aktuellesZiel) {
      aktuellesZiel.scrollIntoView({ block: 'center', behavior: reduziert ? 'auto' : 'smooth' });
      setTimeout(setzen, reduziert ? 0 : 320);
    }
    setzen();
    knWeiter.focus({ preventScroll: true });
  }

  function starten(tour, ab) {
    var s = (D.touren || {})[tour];
    if (!s || !s.length) { return; }
    vorherFokus = document.activeElement;
    lauf = { tour: tour, i: 0, schritte: s };
    bauen();
    document.documentElement.classList.add('tour-an');
    var i = 0;
    if (ab) { for (var k = 0; k < s.length; k++) { if (s[k].k === ab) { i = k; break; } } }
    gehe(i);
  }
  function ende(status) {
    if (!lauf) { return; }
    melden({ tat: 'tour_stand', tour: lauf.tour, status: status });
    try { localStorage.setItem('vd_tour_' + lauf.tour, status); } catch (e) { }
    lauf = null; aktuellesZiel = null;
    abbauen();
    /* Tour-Parameter aus der Adresse nehmen, damit „Neu laden“ sie nicht wieder startet. */
    try {
      var u = new URL(location.href); u.searchParams.delete('tour'); u.searchParams.delete('ab');
      history.replaceState(null, '', u.pathname + u.search + u.hash);
    } catch (e) { }
  }
  window.vecomTour = { starten: starten };

  /* ---------------- „?“: ein Satz und die Tour für diesen Bereich ---------------- */
  function hilfeKnopf() {
    var kn = document.querySelector('[data-tour="hilfe"]');
    if (!kn) {   // Partnerseite (alter Bereich): schwebend oben rechts
      kn = document.createElement('button'); kn.type = 'button'; kn.className = 'tour-hilfe-knopf schwebend';
      kn.setAttribute('data-tour', 'hilfe'); kn.textContent = '?';
      document.body.appendChild(kn);
    }
    kn.setAttribute('aria-label', T.hilfe_aria); kn.setAttribute('aria-haspopup', 'dialog');
    var feld = null;
    function zu() { if (feld) { feld.remove(); feld = null; kn.setAttribute('aria-expanded', 'false'); } }
    kn.addEventListener('click', function (e) {
      e.preventDefault();
      if (feld) { zu(); return; }
      feld = document.createElement('div'); feld.className = 'tour-hilfe'; feld.setAttribute('role', 'dialog'); feld.setAttribute('aria-label', T.hilfe);
      var p = document.createElement('p'); p.textContent = D.hilfe; feld.appendChild(p);
      var reihe = document.createElement('div'); reihe.className = 'tour-knoepfe';
      if (D.tour_hier && D.tour_hier !== 'haupt') {
        var b1 = document.createElement('button'); b1.type = 'button'; b1.className = 'knopf haupt'; b1.textContent = T.tour_bereich;
        b1.addEventListener('click', function () { zu(); starten(D.tour_hier); }); reihe.appendChild(b1);
      }
      var b2 = document.createElement('button'); b2.type = 'button'; b2.className = 'knopf' + (D.tour_hier === 'haupt' ? ' haupt' : ''); b2.textContent = T.tour_ganz;
      b2.addEventListener('click', function () { zu(); if (D.seite === 'start') { starten('haupt'); } else { location.href = urlMit('start', 'haupt', 'hallo'); } });
      reihe.appendChild(b2);
      var b3 = document.createElement('button'); b3.type = 'button'; b3.className = 'knopf stumm'; b3.textContent = T.schliessen;
      b3.addEventListener('click', zu); reihe.appendChild(b3);
      feld.appendChild(reihe);
      document.body.appendChild(feld);
      var r = kn.getBoundingClientRect();
      feld.style.top = Math.min(window.innerHeight - feld.offsetHeight - 10, r.bottom + 8) + 'px';
      feld.style.left = Math.max(10, Math.min(window.innerWidth - feld.offsetWidth - 10, r.right - feld.offsetWidth)) + 'px';
      kn.setAttribute('aria-expanded', 'true');
      (b1Fokus(feld) || b2).focus();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && feld) { zu(); kn.focus(); } });
    document.addEventListener('click', function (e) { if (feld && !feld.contains(e.target) && e.target !== kn) { zu(); } });
  }
  function b1Fokus(f) { return f.querySelector('.knopf.haupt'); }

  /* ---------------- „Zeig mir, wo“ in der Checkliste ---------------- */
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('[data-tour-zeig]') : null;
    if (!a) { return; }
    var tour = a.getAttribute('data-tour-zeig'), ab = a.getAttribute('data-tour-ab') || '';
    var s = (D.touren || {})[tour]; if (!s) { return; }
    var schritt = null; s.forEach(function (x) { if (x.k === ab) { schritt = x; } });
    if (schritt && gleicheSeite(schritt.seite, D.seite) && schritt.seite === D.seite) { e.preventDefault(); starten(tour, ab); }
    /* sonst führt der Link selbst auf die richtige Seite, dort startet die Tour über ?tour=…&ab=… */
  });

  /* ---------------- Start ---------------- */
  function los() {
    hilfeKnopf();
    if (D.start) { setTimeout(function () { starten(D.start.tour, D.start.ab); }, reduziert ? 0 : 450); return; }
    if (!D.auto) { return; }
    try { if (localStorage.getItem('vd_tour_' + D.auto)) { return; } } catch (e) { }
    /* Eine Mini-Tour startet nur, wenn ihr erster Schritt hier auch zu sehen ist (Partnerseite: richtiger Reiter). */
    var erste = (D.touren[D.auto] || [])[0];
    if (!erste) { return; }
    if (D.auto !== 'haupt' && !zielFinden(erste)) { return; }
    setTimeout(function () { starten(D.auto); }, reduziert ? 0 : 900);   // nach dem Einblenden der Seite
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', los); } else { los(); }
})();
