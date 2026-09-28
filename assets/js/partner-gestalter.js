/* Gestalter der Partnerseite (28.09.2026, Uwe: Ja zu L5 „Passende Farbe zum
   Titelbild vorschlagen“). Wählt der Partner ein Bild aus unserer Auswahl,
   erscheint ein Vorschlag für Vorlage und Akzent -- übernommen wird erst per
   Klick. Reiner Komfort: ohne Skript bleibt jede Wahl von Hand möglich. */
(function () {
  'use strict';
  var box = document.getElementById('gs_vorschlag'), daten = document.getElementById('gs_farben');
  if (!box || !daten) { return; }
  var F, N;
  try { F = JSON.parse(daten.textContent); N = JSON.parse(box.dataset.namen); } catch (e) { return; }
  var form = box.closest('form'), was = box.querySelector('[data-was]'), muster = box.querySelectorAll('.gs-muster i');
  var ziel = null;
  function gewaehlt(name) { var r = form.querySelector('input[name="' + name + '"]:checked'); return r ? r.value : ''; }
  function zeigen(r) {
    var v = r && r.dataset.vorlage, a = r && r.dataset.akzent;
    if (!v || !a || !F[v] || !F._akzente[a] || (gewaehlt('vorlage') === v && gewaehlt('akzent') === a)) { box.hidden = true; ziel = null; return; }
    ziel = [v, a];
    was.textContent = (N.v[v] || v) + ' · ' + (N.a[a] || a);
    muster[0].style.background = F[v].grund;
    muster[1].style.background = F._akzente[a][F[v].hell ? 'hell' : 'dunkel'];
    box.hidden = false;
  }
  form.addEventListener('change', function (e) {
    var t = e.target;
    if (t.name === 'bild') { zeigen(t); }
    else if (t.name === 'vorlage' || t.name === 'akzent') { zeigen(form.querySelector('input[name="bild"]:checked')); }
  });
  box.querySelector('[data-uebernehmen]').addEventListener('click', function () {
    if (!ziel) { return; }
    var v = form.querySelector('input[name="vorlage"][value="' + ziel[0] + '"]'), a = form.querySelector('input[name="akzent"][value="' + ziel[1] + '"]');
    if (v) { v.checked = true; } if (a) { a.checked = true; }
    box.hidden = true; ziel = null;
    if (v) { v.focus(); }
  });
})();
