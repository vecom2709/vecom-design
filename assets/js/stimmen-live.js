/* ==========================================================================
   Kundenstimmen aus der Verwaltung.

   Dieselbe Idee wie bei den Preiskarten: Was Uwe freigibt, steht danach von
   allein hier. Und solange nichts freigegeben ist, bleibt der Abschnitt
   verborgen — eine Ueberschrift "Was Kunden sagen" ueber einer leeren Flaeche
   ist die schlechteste aller Antworten auf diese Frage.
   ========================================================================== */
(function () {
  'use strict';
  var abschnitt = document.querySelector('[data-stimmen]');
  var liste = document.querySelector('[data-stimmen-liste]');
  if (!abschnitt || !liste) { return; }

  function sprache() {
    var l = (document.documentElement.getAttribute('lang') || 'it').slice(0, 2).toLowerCase();
    return ['it', 'de', 'en'].indexOf(l) >= 0 ? l : 'it';
  }

  function sterne(n) {
    if (!n) { return ''; }
    var voll = '', leer = '';
    for (var i = 0; i < n; i++) { voll += '★'; }
    for (var j = n; j < 5; j++) { leer += '★'; }
    return '<span class="stimme__sterne">' + voll + '<i>' + leer + '</i></span>';
  }

  function zeichnen(stimmen) {
    if (!stimmen || !stimmen.length) { abschnitt.hidden = true; return; }
    var teile = [];
    stimmen.forEach(function (s) {
      var wer = [s.firma || s.name, s.ort].filter(Boolean).join(' · ');
      var el = document.createElement('figure');
      el.className = 'stimme';
      el.innerHTML = sterne(s.sterne) + '<blockquote></blockquote><figcaption></figcaption>';
      // Text ueber textContent, nicht ueber innerHTML: Er kommt von aussen.
      el.querySelector('blockquote').textContent = s.text;
      el.querySelector('figcaption').textContent = wer;
      teile.push(el);
    });
    liste.innerHTML = '';
    teile.forEach(function (el) { liste.appendChild(el); });
    abschnitt.hidden = false;
  }

  function holen() {
    fetch('/stimmen-daten.php?lang=' + encodeURIComponent(sprache()), { cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { zeichnen(d && d.stimmen); })
      .catch(function () { abschnitt.hidden = true; });
  }

  document.querySelectorAll('[data-lang]').forEach(function (b) {
    b.addEventListener('click', function () { setTimeout(holen, 60); });
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', holen);
  } else { holen(); }
})();
