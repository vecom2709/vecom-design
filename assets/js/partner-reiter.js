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

  /* Werben neu geordnet (03.10.2026, Uwe: Ja zu „Werben neu ordnen“): oben, was
   * fertig zum Teilen ist, darunter, was man selbst gestaltet. Jede Gruppe hat
   * eine Sprungleiste. Umgestellt wird nur im Browser -- die Blöcke selbst und
   * ihre Sprungmarken bleiben, wie sie sind. */
  Object.keys(daten.ordnung || {}).forEach(function (rid) {
    var erst = document.querySelector('[data-reiter="' + rid + '"]');
    if (!erst) { return; }
    var marke = document.createComment('ordnung'), mutter = erst.parentNode;
    mutter.insertBefore(marke, erst);
    daten.ordnung[rid].forEach(function (g) {
      var da = g.ids.map(function (id) { return document.getElementById(id); })
        .filter(function (b) { return b && b.dataset.reiter === rid && b.parentNode === mutter; });
      if (!da.length) { return; }
      var kopf = document.createElement('div');
      kopf.className = 'app-gruppe';
      kopf.dataset.reiter = rid;
      kopf.innerHTML = '<h2></h2><p></p><nav class="app-sprung"></nav>';
      kopf.querySelector('h2').textContent = g.titel;
      kopf.querySelector('p').textContent = g.satz;
      da.forEach(function (b) {
        var t = b.querySelector('h2'), a = document.createElement('a');
        a.href = '#' + b.id;
        a.textContent = t ? t.textContent.replace(/\s+/g, ' ').trim() : b.id;
        kopf.querySelector('nav').appendChild(a);
      });
      kopf.querySelector('nav').setAttribute('aria-label', g.titel);
      mutter.insertBefore(kopf, marke);
      da.forEach(function (b) { mutter.insertBefore(b, marke); });
    });
    mutter.removeChild(marke);
  });
  bloecke = [].slice.call(document.querySelectorAll('[data-reiter]'));

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
    // So geht's (03.10.2026): drei Schritte je Bereich, zugeklappt -- wer es kann, sieht nur eine Zeile.
    if (r.so1) {
      var so = document.createElement('details');
      so.className = 'app-so';
      so.innerHTML = '<summary></summary><ol><li></li><li></li><li></li></ol>';
      so.querySelector('summary').textContent = daten.so;
      [r.so1, r.so2, r.so3].forEach(function (t, i) { so.querySelectorAll('li')[i].textContent = t; });
      kopf.appendChild(so);
    }
    erster.parentNode.insertBefore(kopf, erster);
    // Heißt der erste Block wie der Reiter, stünde der Titel zweimal untereinander.
    var ersteH2 = erster.querySelector('h2');
    if (ersteH2 && ersteH2.textContent.trim() === r.titel) { ersteH2.hidden = true; }
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

  /* Schnellsuche (03.10.2026, Uwe: Ja zu „Schnellsprung/Suche“): durchsucht die
   * Überschriften aller Reiter -- auch die in zugeklappten Listen -- und springt
   * mit einem Tipp hin, egal in welchem Reiter es steht. */
  (function () {
    if (!daten.suche) { return; }
    var norm = function (t) { return (t || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, ' ').trim(); };
    var index = [], gesehen = {};
    bloecke.forEach(function (b) {
      [].forEach.call(b.querySelectorAll('h2,h3,h4,.md-l,summary,label[for]'), function (el) {
        var t = (el.textContent || '').replace(/\s+/g, ' ').trim();
        if (t.length < 3 || t.length > 90) { return; }
        var k = b.dataset.reiter + '|' + norm(t);
        if (gesehen[k]) { return; }
        gesehen[k] = true;
        index.push({ t: t, n: norm(t + ' ' + (b.querySelector('h2') ? b.querySelector('h2').textContent : '')), r: b.dataset.reiter, el: el });
      });
    });
    var box = document.createElement('div');
    box.className = 'app-suche';
    box.setAttribute('role', 'search');
    box.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/></svg>'
      + '<input type="search" autocomplete="off" enterkeyhint="search" aria-autocomplete="list" aria-controls="app_suche_liste" aria-expanded="false">'
      + '<ul id="app_suche_liste" role="listbox" hidden></ul>';
    var feld = box.querySelector('input'), liste = box.querySelector('ul'), treffer = [], wahl = -1;
    feld.placeholder = daten.suche;
    feld.setAttribute('aria-label', daten.suche_aria);
    nav.insertAdjacentElement('afterend', box);

    function schliessen() { liste.hidden = true; feld.setAttribute('aria-expanded', 'false'); wahl = -1; }
    function springen(e) {
      schliessen(); feld.value = ''; feld.blur();
      for (var x = e.el; x; x = x.parentElement) { if (x.tagName === 'DETAILS') { x.open = true; } }
      zeigen(e.r, e.el);
      try { history.replaceState(null, '', '#r-' + e.r); } catch (y) { }
    }
    function markieren() {
      [].forEach.call(liste.querySelectorAll('button'), function (b, i) { b.setAttribute('aria-selected', i === wahl ? 'true' : 'false'); });
    }
    feld.addEventListener('input', function () {
      var q = norm(feld.value).split(' ').filter(Boolean);
      liste.innerHTML = '';
      if (!q.length) { schliessen(); return; }
      treffer = index.filter(function (e) { return q.every(function (w) { return e.n.indexOf(w) !== -1; }); }).slice(0, 8);
      if (!treffer.length) {
        var leer = document.createElement('li'); leer.className = 'leer'; leer.textContent = daten.suche_leer; liste.appendChild(leer);
      }
      treffer.forEach(function (e) {
        var li = document.createElement('li'), b = document.createElement('button'), s = document.createElement('small');
        b.type = 'button'; b.setAttribute('role', 'option');
        b.appendChild(document.createTextNode(e.t));
        s.textContent = daten.reiter[e.r].titel; b.appendChild(s);
        b.addEventListener('click', function () { springen(e); });
        li.appendChild(b); liste.appendChild(li);
      });
      wahl = -1; liste.hidden = false; feld.setAttribute('aria-expanded', 'true');
    });
    feld.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') { schliessen(); return; }
      if (!treffer.length) { return; }
      if (ev.key === 'ArrowDown') { ev.preventDefault(); wahl = (wahl + 1) % treffer.length; markieren(); }
      else if (ev.key === 'ArrowUp') { ev.preventDefault(); wahl = (wahl - 1 + treffer.length) % treffer.length; markieren(); }
      else if (ev.key === 'Enter') { ev.preventDefault(); springen(treffer[wahl < 0 ? 0 : wahl]); }
    });
    document.addEventListener('click', function (ev) { if (!box.contains(ev.target)) { schliessen(); } });
  })();

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

/* Handy-Vorschau (03.10.2026, Uwe: Ja zu „Handy-Vorschau der Werbung“): Der
 * Beitrag erscheint in einem Telefon als Nachricht -- mit Bild, Link und Uhrzeit --,
 * bevor der Partner ihn teilt. Gebaut wird nur im Browser aus dem Text, der
 * ohnehin auf der Seite steht; es geht nichts hinaus. */
(function () {
  if (typeof HTMLDialogElement === 'undefined') { return; }
  var dlg = null;
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-handy]');
    if (!b) { return; }
    var quelle = document.getElementById(b.dataset.handy);
    if (!quelle) { return; }
    if (!dlg) {
      dlg = document.createElement('dialog');
      dlg.className = 'hv';
      dlg.innerHTML = '<div class="hv-rahmen"><div class="hv-kopf"><i aria-hidden="true"></i><b></b></div><div class="hv-chat"><div class="hv-blase"></div></div></div>'
        + '<div class="hv-unten"><span></span><button class="knopf" type="button"></button></div>';
      dlg.querySelector('.hv-unten button').addEventListener('click', function () { dlg.close(); });
      dlg.addEventListener('click', function (ev) { if (ev.target === dlg) { dlg.close(); } });
      document.body.appendChild(dlg);
    }
    dlg.querySelector('.hv-kopf b').textContent = b.dataset.handyWer || '';
    dlg.querySelector('.hv-unten span').textContent = b.dataset.handyTitel || '';
    dlg.querySelector('.hv-unten button').textContent = b.dataset.handyZu || '×';
    var blase = dlg.querySelector('.hv-blase');
    blase.textContent = '';
    if (b.dataset.handyBild) { var img = document.createElement('img'); img.src = b.dataset.handyBild; img.alt = ''; blase.appendChild(img); }
    // Links als Links zeigen, alles andere als Text -- nie als HTML.
    (quelle.value || quelle.textContent || '').split(/(https?:\/\/[^\s]+)/).forEach(function (teil, i) {
      if (i % 2) { var a = document.createElement('a'); a.textContent = teil; a.href = teil; a.target = '_blank'; a.rel = 'noopener'; blase.appendChild(a); }
      else { blase.appendChild(document.createTextNode(teil)); }
    });
    var zeit = document.createElement('small'), d = new Date();
    zeit.textContent = ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2) + ' ✓✓';
    blase.appendChild(zeit);
    dlg.showModal();
  });
})();
