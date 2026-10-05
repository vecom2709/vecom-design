/* partner-academy.js — kleine Hilfen der Partner Academy (05.10.2026).
 * Ohne Skript funktioniert alles: Liste der Einwände vollständig, alle drei
 * Erklärungsebenen untereinander. Mit Skript: Filter und Reiter kurz/normal/ausführlich. */
(function () {
  'use strict';
  // Einwände filtern, während man tippt
  [].forEach.call(document.querySelectorAll('[data-ak-filter]'), function (feld) {
    var liste = document.querySelector(feld.getAttribute('data-ak-filter'));
    if (!liste) { return; }
    feld.hidden = false;
    feld.addEventListener('input', function () {
      var q = feld.value.trim().toLowerCase();
      [].forEach.call(liste.children, function (li) {
        li.hidden = q !== '' && (li.getAttribute('data-such') || '').indexOf(q) === -1;
      });
    });
  });
  // Drei Erklärungsebenen als Reiter
  [].forEach.call(document.querySelectorAll('[data-ak-ebenen]'), function (box) {
    var teile = [].slice.call(box.querySelectorAll('section'));
    if (teile.length < 2) { return; }
    var leiste = document.createElement('div');
    leiste.className = 'ak-tabs'; leiste.setAttribute('role', 'tablist');
    teile.forEach(function (t, i) {
      var b = document.createElement('button');
      b.type = 'button'; b.setAttribute('role', 'tab');
      b.textContent = (t.querySelector('h3') || {}).textContent || String(i + 1);
      b.addEventListener('click', function () { waehle(i); });
      leiste.appendChild(b);
    });
    function waehle(n) {
      teile.forEach(function (t, i) { t.classList.toggle('an', i === n); });
      [].forEach.call(leiste.children, function (b, i) { b.setAttribute('aria-selected', i === n ? 'true' : 'false'); });
    }
    box.parentNode.insertBefore(leiste, box);
    box.classList.add('mit-tabs');
    waehle(0);
  });
  // Partnerlink kopieren (Etappe 2). Ohne Skript bleibt das Feld zum Markieren.
  [].forEach.call(document.querySelectorAll('[data-ak-kopie]'), function (b) {
    var feld = document.querySelector(b.getAttribute('data-ak-kopie'));
    if (!feld) { return; }
    b.hidden = false;
    var vorher = b.textContent;
    b.addEventListener('click', function () {
      function ok() { b.textContent = b.getAttribute('data-fertig') || vorher; setTimeout(function () { b.textContent = vorher; }, 1800); }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(feld.value).then(ok, function () { feld.select(); });
      } else { feld.select(); try { document.execCommand('copy'); ok(); } catch (e) { } }
    });
  });
  // Vorlesen (Etappe 3): Sprachausgabe des Geräts, wenn es keine Sprecheraufnahme gibt.
  if ('speechSynthesis' in window && window.SpeechSynthesisUtterance) {
    [].forEach.call(document.querySelectorAll('[data-ak-vorlesen]'), function (b) {
      var art = b.closest('article'); if (!art) { return; }
      var lab = b.querySelector('span'), vorher = lab.textContent, laeuft = false;
      b.hidden = false;
      b.addEventListener('click', function () {
        window.speechSynthesis.cancel();
        if (laeuft) { laeuft = false; lab.textContent = vorher; b.setAttribute('aria-pressed', 'false'); return; }
        var h2 = art.querySelector('h2'), txt = art.querySelector('.ak-lesetext');
        var u = new SpeechSynthesisUtterance(((h2 ? h2.textContent + '. ' : '') + (txt ? txt.innerText : '')).replace(/\s+/g, ' '));
        u.lang = b.getAttribute('data-ak-vorlesen'); u.rate = 0.98;
        u.onend = u.onerror = function () { laeuft = false; lab.textContent = vorher; b.setAttribute('aria-pressed', 'false'); };
        laeuft = true; lab.textContent = b.getAttribute('data-stopp') || vorher; b.setAttribute('aria-pressed', 'true');
        window.speechSynthesis.speak(u);
      });
    });
    window.addEventListener('pagehide', function () { window.speechSynthesis.cancel(); });
  }
  // Simulator: ans Ende des Gesprächs springen, Eingabe fokussieren.
  var simEnde = document.getElementById('sim-ende');
  if (simEnde && location.hash === '#sim-ende') { var ta = document.getElementById('ak-sim'); if (ta) { ta.focus({ preventScroll: true }); } }
  // PDF drucken: am Rechner direkt den Druckdialog, am Handy öffnet der Link das PDF.
  if (!window.matchMedia || !window.matchMedia('(pointer:coarse)').matches) {
    [].forEach.call(document.querySelectorAll('[data-ak-drucken]'), function (a) {
      a.addEventListener('click', function (ev) {
        ev.preventDefault();
        var alt = document.getElementById('ak-druck'); if (alt) { alt.remove(); }
        var f = document.createElement('iframe');
        f.id = 'ak-druck'; f.title = ''; f.setAttribute('aria-hidden', 'true');
        f.style.cssText = 'position:fixed;right:0;bottom:0;width:1px;height:1px;border:0;opacity:0';
        f.onload = function () {
          try { f.contentWindow.focus(); f.contentWindow.print(); } catch (e) { window.open(a.href, '_blank', 'noopener'); }
        };
        f.src = a.href;
        document.body.appendChild(f);
      });
    });
  }
})();
