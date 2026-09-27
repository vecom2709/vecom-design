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
