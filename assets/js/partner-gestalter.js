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

/* Branchentexte (28.09.2026, Uwe: Ja zu R1): zum gewählten Titelbild die
   passenden Texte in alle drei Sprachen setzen. Stehen dort schon eigene
   Texte, fragt der Knopf einmal nach („wirklich ersetzen?“). */
(function () {
  'use strict';
  var box = document.getElementById('gs_branche'), daten = document.getElementById('gs_branchen');
  if (!box || !daten) { return; }
  var B; try { B = JSON.parse(daten.textContent); } catch (e) { return; }
  var form = box.closest('form'), was = box.querySelector('[data-was]'), knopf = box.querySelector('[data-einsetzen]');
  var gruppe = '', frage = false, felder = ['titel', 'lead', 'p1', 'p2', 'p3'], sprachen = ['it', 'de', 'en'];
  function feld(l, k) { return document.getElementById('gs_' + l + k); }
  function eigene() { return sprachen.some(function (l) { return felder.some(function (k) { var f = feld(l, k); return f && f.value.trim() !== ''; }); }); }
  function zeigen(r) {
    gruppe = r && r.dataset.branche && B[r.dataset.branche] ? r.dataset.branche : '';
    frage = false; knopf.textContent = box.dataset.knopf;
    if (!gruppe) { box.hidden = true; return; }
    was.textContent = B[gruppe].name; box.hidden = false;
  }
  form.addEventListener('change', function (e) { if (e.target.name === 'bild') { zeigen(e.target); } });
  zeigen(form.querySelector('input[name="bild"]:checked'));
  knopf.addEventListener('click', function () {
    if (!gruppe) { return; }
    if (eigene() && !frage) { frage = true; knopf.textContent = box.dataset.ersetzen; return; }
    sprachen.forEach(function (l) {
      felder.forEach(function (k) { var f = feld(l, k); if (f && B[gruppe][l]) { f.value = B[gruppe][l][k] || ''; } });
    });
    document.querySelectorAll('.gs-sprachen details').forEach(function (d) { d.open = true; });
    frage = false; knopf.textContent = box.dataset.fertig;
  });
})();

/* Sprachnachricht aufnehmen (28.09.2026, Uwe: Ja zu R6). Hält nach 30
   Sekunden von selbst an und legt die Aufnahme in das Dateifeld -- gespeichert
   wird sie mit dem normalen „Speichern“. Ohne Mikrofon bleibt das Dateifeld. */
(function () {
  'use strict';
  var box = document.getElementById('gs_gruss'), datei = document.getElementById('gs_gruss_datei');
  if (!box || !datei || !window.MediaRecorder || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.DataTransfer) { return; }
  var knopf = box.querySelector('[data-aufnahme]'), zeit = box.querySelector('[data-zeit]'), probe = box.querySelector('[data-probe]'), hinweis = box.querySelector('[data-hinweis]');
  var rec = null, teile = [], uhr = null, start = 0, MAX = 30;
  knopf.hidden = false;
  function typ() {
    var t = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/webm', 'audio/ogg;codecs=opus'];
    for (var i = 0; i < t.length; i++) { if (MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(t[i])) { return t[i]; } }
    return '';
  }
  function anzeigen() {
    var s = Math.min(MAX, Math.floor((Date.now() - start) / 1000));
    zeit.textContent = '0:' + (s < 10 ? '0' : '') + s + ' / 0:30';
    if (s >= MAX) { stoppen(); }
  }
  function stoppen() { if (rec && rec.state !== 'inactive') { rec.stop(); } }
  knopf.addEventListener('click', function () {
    if (rec && rec.state === 'recording') { stoppen(); return; }
    navigator.mediaDevices.getUserMedia({ audio: true }).then(function (strom) {
      var t = typ();
      rec = t ? new MediaRecorder(strom, { mimeType: t, audioBitsPerSecond: 64000 }) : new MediaRecorder(strom);
      teile = [];
      rec.ondataavailable = function (e) { if (e.data && e.data.size) { teile.push(e.data); } };
      rec.onstop = function () {
        clearInterval(uhr); strom.getTracks().forEach(function (s) { s.stop(); });
        knopf.classList.remove('laeuft'); knopf.textContent = box.dataset.auf;
        var art = (rec.mimeType || 'audio/webm').split(';')[0];
        var blob = new Blob(teile, { type: art });
        var ende = art.indexOf('mp4') >= 0 ? 'm4a' : (art.indexOf('ogg') >= 0 ? 'ogg' : 'webm');
        var dt = new DataTransfer();
        dt.items.add(new File([blob], 'sprachnachricht.' + ende, { type: art }));
        datei.files = dt.files;
        probe.src = URL.createObjectURL(blob); probe.hidden = false;
        hinweis.textContent = box.dataset.neu; hinweis.hidden = false;
      };
      rec.start(250); start = Date.now(); zeit.hidden = false; anzeigen(); uhr = setInterval(anzeigen, 250);
      knopf.classList.add('laeuft'); knopf.textContent = box.dataset.stop;
    }).catch(function () { knopf.hidden = true; });
  });
})();
