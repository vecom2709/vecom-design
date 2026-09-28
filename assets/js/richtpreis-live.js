/* ==========================================================================
   richtpreis-live.js — der Richtpreis rechnet bei jedem Klick neu
   (28.09.2026, Uwe: Ja zu R1–R4).

   Die Zahl entsteht NICHT hier: Das Skript schickt die Antworten an
   /richtpreis.php, und dort rechnet dieselbe Formel wie Ergebnisseite und
   Angebot. Hier wird nur angezeigt -- und gesagt, was die letzte Antwort
   am Preis geändert hat („2 Sprachen: + 180 €“).

   Ohne Skript steht der Preis trotzdem da: Der Server schreibt ihn bei jedem
   Schritt in dieselbe Leiste.

   Daten: <script type="application/json" id="livepreis_daten"> mit
   antworten (gespeicherte), schritt, lang, von (angezeigte untere Grenze),
   fragen (die Fragen dieser Seite) und t (Texte).
   ========================================================================== */
(function () {
  'use strict';
  var datenEl = document.getElementById('livepreis_daten');
  var leiste = document.getElementById('livepreis');
  if (!datenEl || !leiste || !window.fetch || !window.JSON) { return; }
  var d;
  try { d = JSON.parse(datenEl.textContent); } catch (e) { return; }
  var form = leiste.closest('form') || document.querySelector(leiste.getAttribute('data-form') || 'form');
  if (!form) { return; }
  var zahl = document.getElementById('lp_zahl');
  var delta = document.getElementById('lp_delta');
  var monat = document.getElementById('lp_monat');
  var vorher = typeof d.von === 'number' ? d.von : 0;
  var vorherMonat = typeof d.monat === 'number' ? d.monat : 0;
  var laufend = 0;

  function geld(c) {
    var e = Math.round(c / 100);
    if (d.lang === 'en') { return '€' + e.toLocaleString('en-US'); }
    return e.toLocaleString('de-DE') + ' €';
  }

  /* Die Antworten dieser Seite aus dem Formular, über die gespeicherten gelegt. */
  function antworten() {
    var a = {};
    Object.keys(d.antworten || {}).forEach(function (k) { a[k] = d.antworten[k]; });
    (d.fragen || []).forEach(function (name) {
      var mehr = form.querySelectorAll('input[name="' + name + '[]"]');
      if (mehr.length) {
        a[name] = [];
        mehr.forEach(function (i) { if (i.checked) { a[name].push(i.value); } });
      } else {
        var an = form.querySelector('input[name="' + name + '"]:checked');
        a[name] = an ? an.value : '';
      }
    });
    return a;
  }

  function beschriftung(input) {
    var l = input && input.closest('label');
    var w = l && l.querySelector('.wort');
    return w ? w.textContent.trim() : '';
  }

  function rechnen(ausloeser, offen) {
    var nr = ++laufend;
    fetch('/richtpreis.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'omit',
      body: JSON.stringify({ antworten: antworten(), bis: d.schritt, lang: d.lang })
    }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
      if (!j || nr !== laufend) { return; }
      if (!j.zeigen) { leiste.hidden = true; return; }
      zahl.textContent = j.text;
      monat.textContent = j.monatText || '';
      var diff = j.von - vorher;
      var was = beschriftung(ausloeser);
      var diffMonat = (j.monat || 0) - vorherMonat;
      if (was && diff === 0 && diffMonat !== 0 && d.t.monat) {
        delta.textContent = (diffMonat > 0 ? d.t.monat : d.t.monatWeg).replace('{was}', was).replace('{betrag}', geld(Math.abs(diffMonat)));
      } else if (offen && diff > 0) {
        delta.textContent = d.t.offen.replace('{betrag}', geld(diff));
      } else if (was && vorher > 0) {
        delta.textContent = (diff > 0 ? d.t.plus : diff < 0 ? d.t.minus : d.t.gleich)
          .replace('{was}', was).replace('{betrag}', geld(Math.abs(diff)));
      } else if (was) {
        delta.textContent = '';
      }
      vorher = j.von; vorherMonat = j.monat || 0;
      zahl.classList.remove('neu'); void zahl.offsetWidth; zahl.classList.add('neu');
    }).catch(function () { /* dann bleibt die zuletzt gezeigte Zahl stehen */ });
  }

  form.addEventListener('change', function (e) {
    var t = e.target;
    if (!t || t.tagName !== 'INPUT' || (t.type !== 'radio' && t.type !== 'checkbox')) { return; }
    rechnen(t, false);
  });

  /* Eine Seite mit Mehrfachfrage (etwa „Was haben Sie schon fertig?“) gilt
     beim Öffnen als gesehen: Was nicht angekreuzt ist, mache ich -- und das
     sagt die Leiste sofort, statt erst auf der nächsten Seite zu springen. */
  var mehrfach = (d.fragen || []).some(function (n) { return form.querySelector('input[name="' + n + '[]"]'); });
  if (mehrfach && d.schritt > 1) { rechnen(null, true); }
})();
