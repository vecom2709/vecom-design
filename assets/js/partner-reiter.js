/* Reiter der Partnerseite wie in einer App (27.09.2026, Uwe: „Reiter wie eine App“).
 *
 * Die Seite war eine lange Rolle aus sechzehn Blöcken. Jetzt fünf Bereiche:
 * Start · Werben · Kunden finden · Geld · Profil -- am Rechner als Leiste oben,
 * am Handy als Leiste unten wie in einer App.
 *
 * Nichts wurde umgebaut: Jeder Block trägt nur data-reiter="…", dieses Skript
 * blendet ein und aus. Ohne Skript steht alles untereinander wie bisher.
 *
 * Sprungmarken bleiben gültig: #wege, #profil, #seite … öffnen den Reiter, in
 * dem der Block steht, und springen hin -- so funktionieren die „Jetzt“-Knöpfe
 * der ersten Schritte, die Rücksprünge nach dem Speichern und Stripe weiter.
 */
(function () {
  var datenEl = document.getElementById('reiter_daten');
  var bloecke = [].slice.call(document.querySelectorAll('[data-reiter]'));
  if (!datenEl || !bloecke.length || !document.body.classList) { return; }
  var daten;
  try { daten = JSON.parse(datenEl.textContent); } catch (e) { return; }

  var ICON = {
    start:  '<path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>',
    werben: '<path d="M3 10.5v3a1 1 0 0 0 1 1h2.5L12 18.5v-13L6.5 9.5H4a1 1 0 0 0-1 1z"/><path d="M15.5 9a4 4 0 0 1 0 6"/><path d="M18.5 6.5a8 8 0 0 1 0 11"/>',
    finden: '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/>',
    geld:   '<rect x="3" y="6" width="18" height="13" rx="2.5"/><path d="M3 10h18"/><path d="M15.5 14.5h2.5"/>',
    profil: '<circle cx="12" cy="8.5" r="4"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>'
  };
  var SPEICHER = 'vd_partner_reiter';

  var reihe = daten.reihe.filter(function (id) { return bloecke.some(function (b) { return b.dataset.reiter === id; }); });
  var nav = document.createElement('nav');
  nav.className = 'app-reiter';
  nav.setAttribute('aria-label', daten.aria);
  var links = {}, koepfe = {};

  reihe.forEach(function (id) {
    var r = daten.reiter[id];
    var a = document.createElement('a');
    a.href = '#r-' + id;
    a.dataset.r = id;
    a.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' + ICON[id] + '</svg>'
      + '<span class="k"></span><span class="l"></span>';
    a.querySelector('.k').textContent = r.kurz;
    a.querySelector('.l').textContent = r.titel;
    if (bloecke.some(function (b) { return b.dataset.reiter === id && b.dataset.punkt === '1'; })) {
      a.classList.add('punkt');
      var sr = document.createElement('span');
      sr.className = 'sr-nur';
      sr.textContent = ' · ' + daten.neu;
      a.appendChild(sr);
    }
    nav.appendChild(a);
    links[id] = a;

    // Kopf des Reiters: Titel und ein Satz, wozu er da ist. Der erste Reiter
    // hat schon die Überschrift der Seite -- zwei Überschriften übereinander
    // sähen aus wie ein Versehen.
    if (id === reihe[0]) { return; }
    var erster = bloecke.filter(function (b) { return b.dataset.reiter === id; })[0];
    var kopf = document.createElement('div');
    kopf.className = 'app-kopf';
    kopf.innerHTML = '<h2 tabindex="-1"></h2><p></p>';
    kopf.querySelector('h2').textContent = r.titel;
    kopf.querySelector('p').textContent = r.satz;
    erster.parentNode.insertBefore(kopf, erster);
    koepfe[id] = kopf;
  });

  var wortmarke = document.querySelector('.wortmarke');
  (wortmarke && wortmarke.parentNode ? wortmarke : bloecke[0]).insertAdjacentElement(wortmarke ? 'afterend' : 'beforebegin', nav);
  document.body.classList.add('mit-reitern');

  var aktiv = null;
  function zeigen(id, ziel, fokus) {
    if (!links[id]) { id = reihe[0]; }
    bloecke.forEach(function (b) { b.hidden = b.dataset.reiter !== id; });
    reihe.forEach(function (r) {
      if (koepfe[r]) { koepfe[r].hidden = r !== id; }
      if (r === id) { links[r].setAttribute('aria-current', 'page'); } else { links[r].removeAttribute('aria-current'); }
    });
    if (aktiv !== null && aktiv !== id) { links[id].classList.remove('punkt'); }
    aktiv = id;
    try { sessionStorage.setItem(SPEICHER, id); } catch (e) { }
    if (ziel) {
      ziel.scrollIntoView({ block: 'start' });
    } else if (fokus !== undefined) {
      window.scrollTo(0, 0);
    }
    if (fokus) {
      var z = koepfe[id] ? koepfe[id].querySelector('h2') : document.querySelector('h1');
      if (z) { if (!z.hasAttribute('tabindex')) { z.setAttribute('tabindex', '-1'); } z.focus({ preventScroll: true }); }
    }
  }

  function ausHash(h) {
    h = (h || '').replace(/^#/, '');
    if (!h) { return null; }
    if (h.indexOf('r-') === 0) { return links[h.slice(2)] ? { id: h.slice(2) } : null; }
    var el = document.getElementById(h);
    var b = el && el.closest('[data-reiter]');
    return b ? { id: b.dataset.reiter, el: el } : null;
  }

  nav.addEventListener('click', function (e) {
    var a = e.target.closest('a[data-r]');
    if (!a) { return; }
    e.preventDefault();
    zeigen(a.dataset.r, null, true);
    try { history.replaceState(null, '', '#r-' + a.dataset.r); } catch (x) { }
  });

  // Sprünge innerhalb der Seite in einen anderen Reiter.
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey) { return; }
    var a = e.target.closest('a[href^="#"]');
    if (!a || a.closest('.app-reiter')) { return; }
    var z = ausHash(a.getAttribute('href'));
    if (!z || z.id === aktiv) { return; }
    e.preventDefault();
    zeigen(z.id, z.el);
    try { history.pushState(null, '', a.getAttribute('href')); } catch (x) { }
  });

  window.addEventListener('hashchange', function () {
    var z = ausHash(location.hash);
    if (z) { zeigen(z.id, z.el); }
  });

  var start = ausHash(location.hash), gemerkt = null;
  try { gemerkt = sessionStorage.getItem(SPEICHER); } catch (e) { }
  if (start) {
    zeigen(start.id, start.el);
  } else {
    zeigen(gemerkt && links[gemerkt] ? gemerkt : reihe[0]);
  }
})();

/* Einklappbare Listen (03.10.2026, Uwe: „die Anruflisten einklappbar, dass man
 * nicht ewig nach unten scrollen muss“). Jede Liste merkt sich, ob der Partner
 * sie zu- oder aufgeklappt hat. Wer über eine Sprungmarke kommt -- nach dem
 * Speichern eines Ergebnisses steht #anrufliste in der Adresse --, sieht die
 * Liste offen, auch wenn er sie vorher zugeklappt hatte. Ohne Skript sind die
 * Listen so offen, wie die Seite sie ausliefert. */
(function () {
  var VORSILBE = 'vd_klapp_';
  [].forEach.call(document.querySelectorAll('details[data-klapp]'), function (d) {
    var schluessel = VORSILBE + d.dataset.klapp, wert = null;
    try { wert = localStorage.getItem(schluessel); } catch (e) { }
    if (wert === 'zu') { d.open = false; } else if (wert === 'auf') { d.open = true; }
    d.addEventListener('toggle', function () { try { localStorage.setItem(schluessel, d.open ? 'auf' : 'zu'); } catch (e) { } });
  });
  function oeffnen() {
    var id = ''; try { id = decodeURIComponent(location.hash.slice(1)); } catch (e) { return; }
    var ziel = id ? document.getElementById(id) : null;
    if (!ziel) { return; }
    for (var e = ziel; e; e = e.parentElement) { if (e.tagName === 'DETAILS') { e.open = true; } }
    [].forEach.call(ziel.querySelectorAll('details[data-klapp]'), function (d) { d.open = true; });
  }
  oeffnen();
  window.addEventListener('hashchange', oeffnen);
})();
