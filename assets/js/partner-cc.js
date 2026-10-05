/* partner-cc.js — Command Center (Etappe 1b/2, 05.10.2026).
   1. Das goldene V beim ersten Öffnen: höchstens zwei Sekunden, ein Tipp oder
      eine Taste beendet es, bei „Bewegung reduzieren“ gar nicht.
   2. Assistenten (Marketingprofil, neue Kampagne) als Fragen nacheinander.
      Ohne JavaScript stehen alle Fragen untereinander in einem Formular — es
      geht genauso.
   3. Kopieren-Knöpfe für Link und fertige Texte. */
(function () {
  'use strict';

  /* ---------- 0. Alte Sprunglinks (Startseite live, 05.10.2026) ----------
     Der schlichte Partnerlink öffnet jetzt das Command Center. Mails, Push-Hinweise
     und Lesezeichen mit Anker (…#nachrichten, #wege, #r-geld) meinten den vollen
     Bereich — der Anker erreicht den Server nie. Gibt es ihn hier nicht, geht es
     mit demselben Anker dorthin weiter, bevor irgendetwas anderes passiert. */
  var voll = document.body.getAttribute('data-cc-voll');
  var anker = (window.location.hash || '').slice(1);
  if (voll && anker && /^[A-Za-z0-9_-]{1,80}$/.test(anker) && !document.getElementById(anker)) {
    window.location.replace(voll + '#' + anker);
    return;
  }

  /* Sprungziele im Zugeklappten (Startseite nach Punkt 4, 05.10.2026): #kampagnen und
     #profil stehen unter „Ziele, Weg zur Provision und Kampagnen“. Zeigt der Anker
     dorthin, klappt alles darüber auf — beim Laden und beim Tippen auf einen Schnellzugriff. */
  var aufklappen = function () {
    var id = (window.location.hash || '').slice(1);
    var ziel = id && /^[A-Za-z0-9_-]{1,80}$/.test(id) ? document.getElementById(id) : null;
    if (!ziel) { return; }
    for (var e = ziel.parentElement; e; e = e.parentElement) { if (e.tagName === 'DETAILS') { e.open = true; } }
    ziel.scrollIntoView({ block: 'start' });
  };
  aufklappen();
  window.addEventListener('hashchange', aufklappen);
  /* MEHR in der Leiste: ein Tipp daneben schließt die Liste wieder. */
  var mehr = document.querySelector('.cc-mehr');
  if (mehr) {
    document.addEventListener('click', function (e) { if (mehr.open && !mehr.contains(e.target)) { mehr.open = false; } });
  }

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

  /* ---------- 2. Assistenten in Schritten ---------- */
  [].forEach.call(document.querySelectorAll('form.cc-assistent'), function (form) {
    var schritte = [].slice.call(form.querySelectorAll('.cc-schritt'));
    var zurueck = form.querySelector('[data-cc-zurueck]');
    var weiter = form.querySelector('[data-cc-weiter]');
    var speichern = form.querySelector('[data-cc-speichern]');
    var stand = form.querySelector('[data-cc-stand]');
    if (schritte.length < 2 || !weiter || !speichern) { return; }
    document.body.classList.add('cc-js');
    var jetzt = 0;

    // Höchstens n Branchen (Profil): die übrigen Felder werden gesperrt, bis eins frei wird.
    var max = parseInt(form.dataset.maxBranchen || '0', 10);
    var branchen = [].slice.call(form.querySelectorAll('input[type=checkbox][name="branchen[]"]'));
    var grenze = function () {
      if (!max) { return; }
      var n = branchen.filter(function (b) { return b.checked; }).length;
      branchen.forEach(function (b) { b.disabled = !b.checked && n >= max; });
    };
    branchen.forEach(function (b) { b.addEventListener('change', grenze); });
    grenze();

    // Fertig ist ein Schritt mit einer Auswahl — oder mit einem Pflichtfeld (data-pflicht) ab zwei Zeichen.
    var fertig = function (s) {
      var pflicht = s.querySelector('input[data-pflicht]');
      if (pflicht) { return pflicht.value.trim().length >= 2; }
      if (s.querySelector('input[type=radio], input[type=checkbox]')) { return !!s.querySelector('input:checked'); }
      return true;
    };
    var zeigen = function (i, fokus) {
      jetzt = Math.max(0, Math.min(schritte.length - 1, i));
      schritte.forEach(function (s, k) { s.hidden = k !== jetzt; });
      zurueck.hidden = jetzt === 0;
      weiter.hidden = jetzt === schritte.length - 1;
      speichern.hidden = jetzt !== schritte.length - 1;
      weiter.disabled = !fertig(schritte[jetzt]);
      speichern.disabled = !schritte.every(fertig);
      if (stand) { stand.textContent = (form.dataset.stand || '').replace('{n}', String(jetzt + 1)).replace('{alle}', String(schritte.length)); }
      if (fokus) { var l = schritte[jetzt].querySelector('legend'); if (l) { l.setAttribute('tabindex', '-1'); l.focus(); } }
    };
    form.addEventListener('input', function () { zeigen(jetzt, false); });
    form.addEventListener('change', function () { zeigen(jetzt, false); });
    weiter.addEventListener('click', function () { if (fertig(schritte[jetzt])) { zeigen(jetzt + 1, true); } });
    zurueck.addEventListener('click', function () { zeigen(jetzt - 1, true); });
    // Enter in einem Textfeld geht weiter, statt halb fertig abzuschicken.
    form.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && e.target.type === 'text' && jetzt < schritte.length - 1) { e.preventDefault(); weiter.click(); }
    });
    zeigen(0, false);
  });

  /* ---------- 4. KUNDEN (Phase 2, 05.10.2026) ----------
     a) Stufe und Priorität speichern beim Wählen — ohne Skript steht ein Knopf daneben.
     b) Anrufen, WhatsApp, E-Mail: der Tipp öffnet das Telefon wie immer und schreibt
        nebenbei „kontaktiert“ in den Verlauf (sendBeacon, die Seite wartet nicht).
     c) „Meine Kontakte“ aus diesem Browser übernehmen: nur auf Klick des Partners. */
  [].forEach.call(document.querySelectorAll('form[data-cc-auto]'), function (f) {
    f.addEventListener('change', function () { if (f.requestSubmit) { f.requestSubmit(); } else { f.submit(); } });
  });
  var csrf = document.querySelector('input[name=_csrf]');
  document.addEventListener('click', function (e) {
    var a = e.target.closest('[data-cc-kontakt]');
    if (!a || !csrf || !a.dataset.id) { return; }
    var d = new FormData();
    d.append('tat', 'lead_kontakt'); d.append('_csrf', csrf.value); d.append('id', a.dataset.id); d.append('art', a.dataset.ccKontakt);
    var ziel = window.location.pathname + window.location.search;
    if (navigator.sendBeacon) { navigator.sendBeacon(ziel, d); }
    else { fetch(ziel, { method: 'POST', body: d, credentials: 'same-origin', keepalive: true }).catch(function () { }); }
  }, true);
  var imp = document.querySelector('[data-cc-import]');
  if (imp) {
    var schluessel = imp.getAttribute('data-cc-import'), liste = [], schon = false;
    try {
      liste = JSON.parse(localStorage.getItem(schluessel) || '[]');
      schon = localStorage.getItem(schluessel + '_uebernommen') === String(liste.length);
    } catch (e) { liste = []; }
    if (Array.isArray(liste) && liste.length && !schon) {
      var rein = liste.slice(0, 40).map(function (k) {
        return { name: String(k.name || '').slice(0, 60), branche: String(k.branche || ''), notiz: String(k.notiz || '').slice(0, 120), status: String(k.status || 'neu') };
      });
      var tx = imp.querySelector('[data-cc-import-text]');
      if (tx) { tx.textContent = (tx.dataset.vorlage || '').replace('{n}', String(rein.length)); }
      var form = imp.querySelector('form');
      form.querySelector('input[name=kontakte]').value = JSON.stringify(rein);
      // Gemerkt wird die Anzahl: kommt im Browser ein Kontakt dazu, erscheint der Kasten wieder (der Server lässt Doppelte weg).
      form.addEventListener('submit', function () { try { localStorage.setItem(schluessel + '_uebernommen', String(liste.length)); } catch (e) { } });
      imp.hidden = false;
    }
  }

  /* ---------- 3. Kopieren ---------- */
  document.addEventListener('click', function (e) {
    var k = e.target.closest('[data-cc-kopie]');
    if (!k) { return; }
    var feld = document.getElementById(k.dataset.ccKopie);
    if (!feld) { return; }
    var fertig = function () {
      var alt = k.textContent; k.textContent = k.dataset.fertig || '✓';
      setTimeout(function () { k.textContent = alt; }, 1600);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(feld.value).then(fertig, function () { feld.select(); });
    } else { feld.select(); try { document.execCommand('copy'); fertig(); } catch (x) { } }
  });
})();
