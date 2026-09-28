/* Empfehlungsseite der Partner (p.php) -- 27.09.2026.
   Eigene Datei statt eingebetteter Skripte (Sicherheits-Header script-src 'self').
   Alles hier ist Komfort: Ohne Skript funktioniert die Seite vollständig. */
(function () {
  'use strict';
  function $(id) { return document.getElementById(id); }

  /* Vorher/Nachher-Regler */
  document.querySelectorAll('input[data-vn]').forEach(function (r) {
    r.addEventListener('input', function () { r.parentNode.style.setProperty('--pos', r.value + '%'); });
  });

  /* Werbefilm: Hochformat auf schmalen Bildschirmen, spielt stumm, sobald er
     zu sehen ist (nicht bei „Bewegung reduzieren“), Ton per Knopf. */
  document.querySelectorAll('[data-film]').forEach(function (box) {
    var v = box.querySelector('video'), ton = box.querySelector('.lp-ton');
    if (!v) { return; }
    var hoch = window.matchMedia('(max-width: 640px) and (orientation: portrait)').matches;
    if (hoch && v.dataset.hochMp4) {
      box.classList.add('hoch');
      while (v.firstChild) { v.removeChild(v.firstChild); }
      [['hochWebm', 'video/webm'], ['hochMp4', 'video/mp4']].forEach(function (q) {
        if (v.dataset[q[0]]) { var s = document.createElement('source'); s.src = v.dataset[q[0]]; s.type = q[1]; v.appendChild(s); }
      });
      v.poster = v.dataset.hochPoster; v.load();
    }
    var ruhig = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!ruhig && 'IntersectionObserver' in window) {
      v.removeAttribute('controls');
      new IntersectionObserver(function (e) {
        e.forEach(function (x) {
          if (x.isIntersecting && x.intersectionRatio > 0.4) { v.preload = 'auto'; var p = v.play(); if (p && p.catch) { p.catch(function () { v.setAttribute('controls', ''); }); } }
          else { v.pause(); }
        });
      }, { threshold: [0, 0.4, 0.8] }).observe(v);
    }
    if (ton) {
      ton.hidden = false;
      ton.addEventListener('click', function () {
        v.muted = !v.muted; ton.textContent = v.muted ? ton.dataset.an : ton.dataset.aus;
        if (!v.muted && v.paused) { v.play().catch(function () {}); }
      });
    }
  });

  /* Weiterleiten: Link kopieren, Teilen-Menü */
  var k = $('wl_kopieren'), t = $('wl_teilen');
  if (k && navigator.clipboard) {
    k.hidden = false;
    k.addEventListener('click', function () { navigator.clipboard.writeText(k.dataset.link).then(function () { k.textContent = k.dataset.fertig; }); });
  }
  if (t && navigator.share) {
    t.hidden = false;
    t.addEventListener('click', function () { navigator.share({ text: t.dataset.text }).catch(function () {}); });
  }

  /* Werbe-Häkchen: dann ist der Betriebsname Pflicht */
  var wb = document.querySelector('.lp-werbung input'), betrieb = $('ld_betrieb');
  if (wb && betrieb) {
    var pflicht = function () { betrieb.required = wb.checked; };
    wb.addEventListener('change', pflicht); pflicht();
  }

  /* Rückruf: heute vergangene Zeitfenster sperren */
  var tag = $('rr_tag'), fenster = $('rr_fenster');
  if (tag && fenster) {
    var sperren = function () {
      var heute = tag.value === tag.dataset.heute, erstes = null;
      Array.prototype.forEach.call(fenster.options, function (o) {
        o.disabled = heute && o.hasAttribute('data-vorbei');
        if (!o.disabled && erstes === null) { erstes = o; }
      });
      if (fenster.selectedOptions[0] && fenster.selectedOptions[0].disabled && erstes) { erstes.selected = true; }
    };
    tag.addEventListener('change', sperren); sperren();
  }

  /* Mitlaufende Leiste: weg, solange das Formular oben zu sehen ist */
  var leiste = $('lp_leiste'), form = $('lp_form');
  if (leiste && form && 'IntersectionObserver' in window) {
    new IntersectionObserver(function (e) { leiste.classList.toggle('weg', e[0].isIntersecting); }, { threshold: 0.2 }).observe(form);
  }
})();
