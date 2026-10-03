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

  /* Mitlaufende Leiste: weg, solange das Formular oben zu sehen ist. Am
     Computer (28.09.2026, R5) steht sie oben und erscheint nur mit Skript. */
  var leiste = $('lp_leiste'), form = $('lp_form');
  if (leiste && form && 'IntersectionObserver' in window) {
    leiste.classList.add('weg');
    new IntersectionObserver(function (e) { leiste.classList.toggle('weg', e[0].isIntersecting); }, { threshold: 0.2 }).observe(form);
    requestAnimationFrame(function () { leiste.classList.add('bereit'); });
  }

  /* Anfrage in zwei Schritten (28.09.2026, R3): erst „Was brauchen Sie?“,
     dann die E-Mail. Ohne Skript stehen beide Schritte zugleich da. */
  if (form) {
    var wahl = form.querySelectorAll('input[name="wunsch"]'), weiter = form.querySelector('.lp-wunsch-weiter');
    var mail = $('ld_email');
    if (wahl.length && mail && !mail.value) {
      form.classList.add('zweistufig');
      Array.prototype.forEach.call(wahl, function (r) {
        r.addEventListener('change', function () {
          var erstes = !form.classList.contains('gewaehlt');
          form.classList.add('gewaehlt');
          if (weiter) { weiter.hidden = false; }
          if (erstes) { mail.focus({ preventScroll: false }); }
        });
      });
    }
  }
})();

/* Verweildauer (03.10.2026, Uwe: Ja zu K1/N1): Solange die Seite sichtbar ist,
   alle 30 Sekunden ein Lebenszeichen an /t.php — höchstens 20 Mal. Daraus
   wird die Dauer in der Besucherliste des Partners; bleibt jemand über zwei
   Minuten, bekommt der Partner den Sofort-Hinweis. Nur in der echten Seite,
   nicht in der Vorschau des Partners (n=1). Keine Daten über den Besucher. */
(function () {
  'use strict';
  if (!window.fetch || /[?&]n=1(&|$)/.test(location.search)) { return; }
  var n = 0;
  var uhr = setInterval(function () {
    if (document.visibilityState !== 'visible') { return; }
    if (++n > 20) { clearInterval(uhr); return; }
    try {
      fetch('/t.php', { method: 'POST', credentials: 'same-origin', keepalive: true, headers: { 'Content-Type': 'application/json' }, body: '{"e":"ping"}' })
        .then(function (r) { return r.json(); }).then(function (j) { if (j && j.aus) { clearInterval(uhr); } }).catch(function () {});
    } catch (e) { /* Beiwerk */ }
  }, 30000);
})();

/* Kino-Kopf und Piazza-Intro (03.10.2026, Uwe: Ja zu B1/B3/B4). Das Standbild
   steht sofort; der Film lädt erst, wenn der Kopf zu sehen ist, und nie bei
   „Bewegung reduzieren“, Datensparen oder langsamem Netz. Er blendet erst ein,
   wenn er wirklich läuft — bis dahin sieht man dasselbe Standbild. Ein Knopf
   hält ihn an (länger als fünf Sekunden Bewegung braucht einen Halt). */
(function () {
  'use strict';
  var v = document.querySelector('video[data-kino]');
  if (!v) { return; }
  var netz = navigator.connection || {};
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || netz.saveData || /(^|-)2g$/.test(netz.effectiveType || '')) { return; }
  if (!('IntersectionObserver' in window)) { return; }
  var halt = v.parentNode.querySelector('.lp-kino-halt'), angehalten = false, zuEnde = false;
  function knopf() {
    if (!halt) { return; }
    halt.setAttribute('aria-label', angehalten ? halt.dataset.weiter : halt.dataset.halt);
    halt.firstChild.textContent = angehalten ? '▶' : '❚❚';
  }
  v.addEventListener('playing', function () { v.classList.add('an'); if (halt) { halt.hidden = false; } });
  v.addEventListener('ended', function () { zuEnde = true; if (halt) { halt.hidden = true; } });   // Intro: bleibt auf dem letzten Bild
  v.addEventListener('error', function () { v.classList.remove('an'); if (halt) { halt.hidden = true; } }, true);
  if (halt) {
    halt.addEventListener('click', function () {
      angehalten = !angehalten;
      if (angehalten) { v.pause(); } else { v.play().catch(function () {}); }
      knopf();
    });
  }
  new IntersectionObserver(function (e) {
    e.forEach(function (x) {
      if (x.isIntersecting && !angehalten && !zuEnde) { v.preload = 'auto'; var p = v.play(); if (p && p.catch) { p.catch(function () {}); } }
      else if (!x.isIntersecting) { v.pause(); }
    });
  }, { threshold: 0.25 }).observe(v.parentNode);
})();
