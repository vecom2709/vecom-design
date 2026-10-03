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

/* Gestalter als Assistent (03.10.2026, Uwe: Ja zu E1–E4).
   - Drei Schritte statt einer langen Seite; ohne Skript steht alles untereinander.
   - Ein-Klick-Looks je Branche: Bild, Vorlage, Farbe, Schrift und Texte auf einmal.
   - Live-Vorschau: jede Änderung sofort in der Vorschau (p.php prüft wie beim Speichern,
     gespeichert wird nichts) — am Handy in einem Telefonrahmen.
   - Reihenfolge der Abschnitte mit Pfeilen; die Zahlenlisten bleiben die Wahrheit. */
(function () {
  'use strict';
  var form = document.getElementById('gs_form');
  if (!form) { return; }
  var nav = form.querySelector('.ga-schritte'), schritte = [].slice.call(form.querySelectorAll('.ga-schritt'));
  var zurueck = form.querySelector('[data-ga-zurueck]'), weiter = form.querySelector('[data-ga-weiter]'), vKnopf = form.querySelector('[data-ga-vorschau]');
  var jetzt = 1;
  form.classList.add('js-ga');

  function zeigen(n, rollen) {
    jetzt = Math.max(1, Math.min(3, n));
    schritte.forEach(function (s) { s.hidden = +s.dataset.schritt !== jetzt; });
    [].forEach.call(nav.querySelectorAll('[data-ga]'), function (b) { b.setAttribute('aria-current', +b.dataset.ga === jetzt ? 'step' : 'false'); });
    zurueck.hidden = jetzt === 1; weiter.hidden = jetzt === 3;
    if (rollen) { nav.scrollIntoView({ block: 'start', behavior: 'smooth' }); }
  }
  nav.hidden = false;
  nav.addEventListener('click', function (e) { var b = e.target.closest('[data-ga]'); if (b) { zeigen(+b.dataset.ga, true); } });
  zurueck.addEventListener('click', function () { zeigen(jetzt - 1, true); });
  weiter.addEventListener('click', function () { zeigen(jetzt + 1, true); });
  // Sprünge aus „Es fehlt: …“ in den richtigen Schritt
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href^="#gs_"]'); if (!a) { return; }
    var ziel = document.getElementById(a.getAttribute('href').slice(1)); if (!ziel || !form.contains(ziel)) { return; }
    e.preventDefault();
    var s = ziel.closest('.ga-schritt'); zeigen(s ? +s.dataset.schritt : 1, false);
    ziel.scrollIntoView({ block: 'center', behavior: 'smooth' }); if (ziel.focus) { ziel.focus({ preventScroll: true }); }
  });
  zeigen(1, false);

  /* Live-Vorschau */
  var basis = form.dataset.vorschau, rahmen = document.querySelector('.gs-vorschau iframe'), dlg = null, uhr = null;
  function daten() {
    var o = {};
    new FormData(form).forEach(function (wert, schluessel) {
      if (typeof wert !== 'string' || ['_csrf', 'tat', 'MAX_FILE_SIZE'].indexOf(schluessel) >= 0) { return; }
      var teile = schluessel.replace(/\]/g, '').split('['), z = o;
      for (var i = 0; i < teile.length - 1; i++) { z[teile[i]] = z[teile[i]] || {}; z = z[teile[i]]; }
      z[teile[teile.length - 1]] = wert;
    });
    return o;
  }
  function adresse() {
    var b64 = btoa(unescape(encodeURIComponent(JSON.stringify(daten())))).replace(/\+/g, '-').replace(/\//g, '_');
    return basis + '&vs=' + encodeURIComponent(b64);
  }
  function auffrischen() {
    clearTimeout(uhr);
    uhr = setTimeout(function () {
      var u = adresse();
      if (rahmen) { rahmen.src = u; }
      if (dlg && dlg.open) { dlg.querySelector('iframe').src = u; }
    }, 450);
  }
  form.addEventListener('input', auffrischen);
  form.addEventListener('change', auffrischen);
  if (vKnopf && typeof HTMLDialogElement !== 'undefined') {
    vKnopf.hidden = false;
    vKnopf.addEventListener('click', function () {
      if (!dlg) {
        dlg = document.createElement('dialog');
        dlg.className = 'ga-vorschau-dlg';
        dlg.innerHTML = '<div class="hv-rahmen"><iframe title=""></iframe></div><div class="hv-unten"><span></span><button class="knopf" type="button"></button></div>';
        dlg.querySelector('iframe').title = vKnopf.textContent;
        dlg.querySelector('span').textContent = form.dataset.live || '';
        dlg.querySelector('button').textContent = form.dataset.zu || '×';
        dlg.querySelector('button').addEventListener('click', function () { dlg.close(); });
        dlg.addEventListener('click', function (e) { if (e.target === dlg) { dlg.close(); } });
        document.body.appendChild(dlg);
      }
      dlg.querySelector('iframe').src = adresse();
      dlg.showModal();
    });
  }

  /* Ein-Klick-Looks */
  var B = {}; try { B = JSON.parse(document.getElementById('gs_branchen').textContent); } catch (e) { }
  var looks = [].slice.call(form.querySelectorAll('[data-look]')), frage = document.getElementById('ga_ersetzen'), offen = null;
  var felder = ['titel', 'lead', 'p1', 'p2', 'p3'], sprachen = ['it', 'de', 'en'];
  function setzen(name, wert) { var r = form.querySelector('input[name="' + name + '"][value="' + wert + '"]'); if (r) { r.checked = true; } }
  function texte(gruppe, ueberall) {
    if (!B[gruppe]) { return; }
    sprachen.forEach(function (l) {
      felder.forEach(function (k) { var f = document.getElementById('gs_' + l + k); if (f && B[gruppe][l] && (ueberall || f.value.trim() === '')) { f.value = B[gruppe][l][k] || ''; } });
    });
  }
  looks.forEach(function (k) {
    k.addEventListener('click', function () {
      setzen('bild', k.dataset.bild); setzen('vorlage', k.dataset.vorlage); setzen('akzent', k.dataset.akzent); setzen('schrift', k.dataset.schrift);
      looks.forEach(function (a) { a.setAttribute('aria-pressed', a === k ? 'true' : 'false'); });
      var vs = document.getElementById('gs_vorschlag'); if (vs) { vs.hidden = true; }
      var eigene = sprachen.some(function (l) { return felder.some(function (f) { var e = document.getElementById('gs_' + l + f); return e && e.value.trim() !== ''; }); });
      texte(k.dataset.look, false);
      offen = eigene ? k.dataset.look : null;
      if (frage) { frage.hidden = !eigene; }
      auffrischen();
    });
  });
  if (frage) {
    frage.querySelector('[data-ja]').addEventListener('click', function () { if (offen) { texte(offen, true); auffrischen(); } frage.hidden = true; offen = null; });
  }

  /* Reihenfolge mit Pfeilen */
  var reihe = form.querySelector('.ga-reihe');
  if (reihe) {
    var zeilen = function () { return [].slice.call(reihe.querySelectorAll('.gs-zeile')); };
    var nummern = function () {
      zeilen().forEach(function (z, i, alle) {
        var s = z.querySelector('select'); if (s) { s.value = String(i + 1); s.hidden = true; }
        z.querySelector('[data-hoch]').disabled = i === 0; z.querySelector('[data-runter]').disabled = i === alle.length - 1;
      });
    };
    zeilen().forEach(function (z) {
      ['hoch', 'runter'].forEach(function (r) {
        var b = document.createElement('button'); b.type = 'button'; b.className = 'ga-pfeil'; b.dataset[r] = '1';
        b.textContent = r === 'hoch' ? '↑' : '↓'; b.setAttribute('aria-label', reihe.dataset[r] + ' — ' + z.querySelector('label').textContent.trim());
        b.addEventListener('click', function () {
          if (r === 'hoch' && z.previousElementSibling) { reihe.insertBefore(z, z.previousElementSibling); }
          if (r === 'runter' && z.nextElementSibling) { reihe.insertBefore(z.nextElementSibling, z); }
          nummern(); b.focus(); auffrischen();
        });
        z.appendChild(b);
      });
    });
    nummern();
  }
})();
