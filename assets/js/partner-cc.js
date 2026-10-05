/* partner-cc.js — Command Center (Etappe 1b, 05.10.2026).
   1. Das goldene V beim ersten Öffnen: höchstens zwei Sekunden, ein Tipp oder
      eine Taste beendet es, bei „Bewegung reduzieren“ gar nicht.
   2. Das Marketingprofil als vier kurze Fragen nacheinander. Ohne JavaScript
      stehen alle vier untereinander in einem Formular — es geht genauso. */
(function () {
  'use strict';
  var ruhig = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- 1. Signature-Moment ---------- */
  var intro = document.getElementById('cc-intro');
  var MERK = 'vd_cc_intro';
  var gesehen = false;
  try { gesehen = localStorage.getItem(MERK) === '1'; } catch (e) { gesehen = true; } // ohne Speicher lieber nie als jedes Mal
  if (intro && !ruhig && !gesehen) {
    try { localStorage.setItem(MERK, '1'); } catch (e) { }
    intro.hidden = false;
    var zu = function () {
      if (!intro || intro.hidden) { return; }
      intro.hidden = true;
      document.removeEventListener('keydown', zu);
      var ziel = document.getElementById('cc-start');
      if (ziel) { try { ziel.focus({ preventScroll: true }); } catch (e) { } }
    };
    intro.addEventListener('click', zu);
    intro.addEventListener('animationend', function (e) { if (e.target === intro) { zu(); } });
    document.addEventListener('keydown', zu);
    setTimeout(zu, 2100);
  }

  /* ---------- 2. Marketingprofil in Schritten ---------- */
  var form = document.getElementById('cc-profil-form');
  if (!form) { return; }
  var schritte = [].slice.call(form.querySelectorAll('.cc-schritt'));
  var zurueck = form.querySelector('[data-cc-zurueck]');
  var weiter = form.querySelector('[data-cc-weiter]');
  var speichern = form.querySelector('[data-cc-speichern]');
  var stand = form.querySelector('[data-cc-stand]');
  if (schritte.length < 2 || !weiter || !speichern) { return; }
  document.body.classList.add('cc-js');
  var jetzt = 0;

  // Höchstens drei Branchen: die übrigen Felder werden gesperrt, bis eins frei wird.
  var max = parseInt(form.dataset.maxBranchen || '3', 10);
  var branchen = [].slice.call(form.querySelectorAll('input[name="branchen[]"]'));
  var grenze = function () {
    var n = branchen.filter(function (b) { return b.checked; }).length;
    branchen.forEach(function (b) { b.disabled = !b.checked && n >= max; });
  };
  branchen.forEach(function (b) { b.addEventListener('change', grenze); });
  grenze();

  var fertig = function (s) {
    var feld = s.querySelector('input[type=text]');
    if (feld) { return feld.value.trim().length >= 2; }
    return !!s.querySelector('input:checked');
  };
  var zeigen = function (i, fokus) {
    jetzt = Math.max(0, Math.min(schritte.length - 1, i));
    schritte.forEach(function (s, k) { s.hidden = k !== jetzt; });
    zurueck.hidden = jetzt === 0;
    weiter.hidden = jetzt === schritte.length - 1;
    speichern.hidden = jetzt !== schritte.length - 1;
    weiter.disabled = !fertig(schritte[jetzt]);
    speichern.disabled = !schritte.every(fertig);
    if (stand) { stand.textContent = (form.dataset.stand || '').replace('{n}', String(jetzt + 1)); }
    if (fokus) { var l = schritte[jetzt].querySelector('legend'); if (l) { l.setAttribute('tabindex', '-1'); l.focus(); } }
  };
  form.addEventListener('input', function () { zeigen(jetzt, false); });
  form.addEventListener('change', function () { zeigen(jetzt, false); });
  weiter.addEventListener('click', function () { if (fertig(schritte[jetzt])) { zeigen(jetzt + 1, true); } });
  zurueck.addEventListener('click', function () { zeigen(jetzt - 1, true); });
  // Enter im Ortsfeld geht weiter, statt halb fertig abzuschicken.
  form.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.type === 'text' && jetzt < schritte.length - 1) { e.preventDefault(); weiter.click(); }
  });
  zeigen(0, false);
})();
