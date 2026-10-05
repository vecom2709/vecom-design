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
})();
