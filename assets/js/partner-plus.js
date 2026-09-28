/* Marketing-Ausbau der Partnerseite (28.09.2026).
 *
 * 1. Branchen-Pakete: Umschalten zwischen den Branchen; „Bild für diese
 *    Branche“ wählt das passende Motiv in den Bildern und springt hin.
 * 2. Angeschrieben: Klickt der Partner bei einem reservierten Betrieb auf
 *    WhatsApp/E-Mail/Kopieren, merkt sich der Server den Tag -- daraus wird
 *    nach 3 und 7 Tagen die Nachfass-Erinnerung. sendBeacon, damit der Link
 *    sofort aufgeht.
 * 3. Meine Kontakte: Liste NUR im Browser (localStorage). Wenn der Speicher
 *    nicht geht (privates Fenster), bleibt die Liste für diesen Besuch im
 *    Arbeitsspeicher der Seite.
 */
(function () {
  'use strict';

  /* ---------- 1. Branchen ---------- */
  var brKnoepfe = document.querySelectorAll('[data-br]');
  [].forEach.call(brKnoepfe, function (b) {
    b.addEventListener('click', function () {
      [].forEach.call(brKnoepfe, function (a) { a.setAttribute('aria-pressed', a === b ? 'true' : 'false'); });
      [].forEach.call(document.querySelectorAll('[data-br-feld]'), function (f) { f.hidden = f.getAttribute('data-br-feld') !== b.getAttribute('data-br'); });
    });
  });
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-br-bild]'); if (!a) { return; }
    var chip = document.querySelector('[data-motiv="' + a.getAttribute('data-br-bild') + '"]');
    if (chip) { chip.click(); }
  });

  /* Beispielarbeiten: Sprache des Beitrags */
  var bwKnoepfe = document.querySelectorAll('[data-bw-sprache]');
  [].forEach.call(bwKnoepfe, function (b) {
    b.addEventListener('click', function () {
      [].forEach.call(bwKnoepfe, function (a) { a.setAttribute('aria-pressed', a === b ? 'true' : 'false'); });
      [].forEach.call(document.querySelectorAll('[data-bw-feld]'), function (f) { f.hidden = f.getAttribute('data-bw-feld') !== b.getAttribute('data-bw-sprache'); });
    });
  });

  /* ---------- 2. Angeschrieben ---------- */
  var csrfFeld = document.querySelector('input[name=_csrf]');
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-angeschrieben]'); if (!a || !csrfFeld) { return; }
    var d = new FormData();
    d.append('tat', 'angeschrieben'); d.append('_csrf', csrfFeld.value); d.append('f', a.getAttribute('data-angeschrieben'));
    var ziel = window.location.pathname + window.location.search;
    if (navigator.sendBeacon) { navigator.sendBeacon(ziel, d); }
    else { fetch(ziel, { method: 'POST', body: d, credentials: 'same-origin', keepalive: true }).catch(function () {}); }
  }, true);

  /* ---------- 3. Meine Kontakte ---------- */
  var datenEl = document.getElementById('kontakte_daten');
  if (!datenEl) { return; }
  var D = JSON.parse(datenEl.textContent);
  var form = document.getElementById('mk_neu'), liste = document.getElementById('mk_liste'), meldung = document.getElementById('mk_meldung');
  var TAG = 86400000, speicherGeht = true, kontakte = [];
  try { kontakte = JSON.parse(localStorage.getItem(D.speicher) || '[]'); if (!Array.isArray(kontakte)) { kontakte = []; } }
  catch (e) { speicherGeht = false; kontakte = []; }
  form.hidden = false;

  function sichern() {
    if (!speicherGeht) { return; }
    try { localStorage.setItem(D.speicher, JSON.stringify(kontakte)); } catch (e) { speicherGeht = false; }
  }
  function nachricht(k) {
    return D.msg.replace('{name}', k.name.split(' ')[0]).replace('{satz}', D.saetze[k.branche] || '').replace('{link}', D.link).replace(/  +/g, ' ');
  }
  function el(tag, klasse, text) { var x = document.createElement(tag); if (klasse) { x.className = klasse; } if (text != null) { x.textContent = text; } return x; }

  function zeigen() {
    liste.innerHTML = '';
    if (!kontakte.length) { liste.appendChild(el('li', 'pp-mk-leer', D.t.leer)); return; }
    kontakte.forEach(function (k, i) {
      var li = el('li', 'pp-mk-k st-' + k.status);
      var kopf = el('div', 'pp-mk-kopf');
      var name = el('b', null, k.name);
      var br = el('small', null, (D.branchen[k.branche] || '') + (k.notiz ? ' · ' + k.notiz : ''));
      var wer = el('div', 'pp-mk-wer'); wer.appendChild(name); wer.appendChild(br);
      var sel = document.createElement('select'); sel.setAttribute('aria-label', k.name);
      Object.keys(D.status).forEach(function (s) { var o = document.createElement('option'); o.value = s; o.textContent = D.status[s]; if (s === k.status) { o.selected = true; } sel.appendChild(o); });
      sel.addEventListener('change', function () {
        k.status = sel.value; if (sel.value === 'angeschrieben' && !k.am) { k.am = Date.now(); } sichern(); zeigen();
      });
      kopf.appendChild(wer); kopf.appendChild(sel); li.appendChild(kopf);
      if (k.status === 'angeschrieben' && k.am) {
        var tage = Math.floor((Date.now() - k.am) / TAG);
        if (tage >= 3) { li.appendChild(el('p', 'pp-mk-nach', D.t.nachhaken.replace('{n}', tage))); }
      }
      var knoepfe = el('div', 'knoepfe');
      var text = nachricht(k);
      var wa = el('a', 'knopf klein-knopf' + (k.status === 'neu' ? ' haupt' : ''), D.t.nachricht);
      wa.href = 'https://wa.me/?text=' + encodeURIComponent(text); wa.target = '_blank'; wa.rel = 'noopener';
      wa.addEventListener('click', function () { if (k.status === 'neu') { k.status = 'angeschrieben'; k.am = Date.now(); sichern(); setTimeout(zeigen, 300); } });
      var ko = el('button', 'knopf klein-knopf', D.t.kopieren); ko.type = 'button'; ko.setAttribute('data-kopie-text', text);
      var weg = el('button', 'knopf klein-knopf leise-knopf', D.t.weg); weg.type = 'button';
      weg.addEventListener('click', function () { kontakte.splice(i, 1); sichern(); zeigen(); });
      knoepfe.appendChild(wa); knoepfe.appendChild(ko); knoepfe.appendChild(weg);
      li.appendChild(knoepfe);
      liste.appendChild(li);
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (kontakte.length >= D.max) { meldung.textContent = D.t.voll; return; }
    var name = document.getElementById('mk_name').value.trim();
    if (!name) { return; }
    kontakte.push({ name: name.slice(0, 60), branche: document.getElementById('mk_branche').value, notiz: document.getElementById('mk_notiz').value.trim().slice(0, 120), status: 'neu', am: 0 });
    sichern(); form.reset(); meldung.textContent = ''; zeigen();
    document.getElementById('mk_name').focus();
  });
  zeigen();
})();
