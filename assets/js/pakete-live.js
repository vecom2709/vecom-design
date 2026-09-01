/* ============================================================================
   Preiskarten aus der Verwaltung.

   Die drei Karten im HTML bleiben unangetastet und sind der Rückfall: Solange
   die Verwaltung nicht eingerichtet ist, die Datenbank schweigt oder gar kein
   Paket öffentlich steht, ändert dieses Skript nichts.

   Kommen Pakete zurück, wird der Block neu aufgebaut — eine vorhandene Karte
   dient als Vorlage, damit Klassen, Aufbau und Feinheiten erhalten bleiben.
   Felder, die aus der Datenbank stammen, verlieren dabei ihr data-i18n, sonst
   würde die Sprachumschaltung sie gleich wieder überschreiben. Gemeinsame
   Beschriftungen (Preiszusatz, Knöpfe, Abzeichen) behalten es.
   ========================================================================== */
(function () {
  'use strict';

  var behaelter = document.querySelector('.plans');
  var vorlage = behaelter && behaelter.querySelector('.plan');
  if (!behaelter || !vorlage) { return; }

  var original = behaelter.innerHTML;          // Rückfall, falls etwas schiefgeht
  var muster = vorlage.cloneNode(true);
  var letzte = null;                           // zuletzt geladene Pakete
  var kaufText = '';                           // Beschriftung des Kaufknopfs, je Sprache

  /* Dieselbe Reihenfolge wie pickLang() in app.js — sonst holt dieses Skript
     die Texte in einer anderen Sprache, als die Seite gerade zeigt. */
  function sprache() {
    var moeglich = ['it', 'de', 'en'];
    var fest = document.documentElement.getAttribute('data-lang-fixed');
    if (moeglich.indexOf(fest) > -1) { return fest; }
    var ausUrl = new URLSearchParams(location.search).get('lang');
    if (moeglich.indexOf(ausUrl) > -1) { return ausUrl; }
    try {
      var gemerkt = localStorage.getItem('vecom-lang');
      if (moeglich.indexOf(gemerkt) > -1) { return gemerkt; }
    } catch (e) {}
    var vomBrowser = (navigator.languages || [navigator.language || ''])
      .map(function (l) { return String(l).slice(0, 2).toLowerCase(); });
    for (var i = 0; i < vomBrowser.length; i++) {
      if (moeglich.indexOf(vomBrowser[i]) > -1) { return vomBrowser[i]; }
    }
    var amElement = (document.documentElement.getAttribute('lang') || '').slice(0, 2).toLowerCase();
    return moeglich.indexOf(amElement) > -1 ? amElement : 'it';
  }

  function geld(betrag, waehrung) {
    var z = Number(betrag);
    var text = (z % 1 === 0 ? z : z.toFixed(2)).toLocaleString('de-DE');
    return text + ' ' + (waehrung === 'EUR' ? '€' : waehrung);
  }

  /** Setzt Text und nimmt data-i18n weg, damit die Sprachumschaltung nicht überschreibt. */
  function setzen(el, text) {
    if (!el) { return; }
    el.removeAttribute('data-i18n');
    el.textContent = text;
  }

  function karteBauen(p, monatsText) {
    var k = muster.cloneNode(true);
    k.classList.toggle('plan--featured', !!p.beliebt);

    var abzeichen = k.querySelector('.plan__badge');
    if (p.beliebt && !abzeichen) {
      var vorhanden = document.querySelector('.plan__badge');
      if (vorhanden) { k.insertBefore(vorhanden.cloneNode(true), k.firstChild); }
    } else if (!p.beliebt && abzeichen) {
      abzeichen.remove();
    }

    setzen(k.querySelector('h3'), p.name);
    setzen(k.querySelector('.plan__sub'), p.sub);
    setzen(k.querySelector('.plan__price > span'), geld(p.preis, p.waehrung));

    var monat = k.querySelector('.plan__month');
    if (monat) {
      if (p.monat > 0 && monatsText) {
        setzen(monat, monatsText.replace(/\d[\d.,]*\s*€/, geld(p.monat, p.waehrung)));
        monat.hidden = false;
      } else if (p.monat > 0) {
        setzen(monat, '+ ' + geld(p.monat, p.waehrung));
      } else {
        monat.remove();
      }
    }

    var liste = k.querySelector('ul');
    if (liste) {
      liste.removeAttribute('data-i18n-list');
      liste.innerHTML = '';
      p.features.forEach(function (f) {
        var text = String(f).trim();
        if (!text) { return; }
        var li = document.createElement('li');
        // Dieselbe Regel wie in app.js: Zeilen mit Doppelpunkt am Ende sind
        // Zwischenüberschriften, keine Merkmale.
        if (/:$/.test(text)) { li.className = 'is-lead'; }
        li.textContent = text;
        liste.appendChild(li);
      });
    }

    var ideal = k.querySelector('.plan__ideal');
    if (ideal) {
      if (p.ideal) { setzen(ideal, p.ideal); } else { ideal.remove(); }
    }

    var detail = k.querySelector('.det-link');
    if (detail) { detail.setAttribute('href', p.detail); }

    // Auch die Karten aus der Verwaltung tragen ihre Wahl ins Formular.
    if (p.slug) {
      k.setAttribute('data-paket', p.slug);
      var anfr = k.querySelector('a.btn:not(.det-link)');
      if (anfr) { anfr.setAttribute('href', '?paket=' + encodeURIComponent(p.slug) + '#contact'); }
    }

    // Kaufknopf nur, wenn das Paket dafür freigegeben und Stripe bereit ist.
    // Er wird aus dem vorhandenen Anfrage-Knopf geklont, damit Form und
    // Verhalten (auch das magnetische Mitziehen) erhalten bleiben.
    if (p.kaufbar && kaufText) {
      var anfrage = k.querySelector('a.btn:not(.det-link)');
      if (anfrage) {
        var kauf = anfrage.cloneNode(true);
        kauf.className = anfrage.className + ' plan__kauf';
        kauf.removeAttribute('data-i18n');
        kauf.setAttribute('href', p.kauf_url);
        kauf.textContent = kaufText;
        anfrage.parentNode.insertBefore(kauf, anfrage);
      }
    }

    k.classList.add('in');                     // die Einblendung lief schon
    return k;
  }

  function zeichnen(pakete) {
    if (!pakete || !pakete.length) { return; }
    var monatsText = (document.querySelector('.plan__month') || {}).textContent || '';
    try {
      var neu = document.createDocumentFragment();
      pakete.forEach(function (p) { neu.appendChild(karteBauen(p, monatsText)); });
      behaelter.innerHTML = '';
      behaelter.appendChild(neu);
      // Der Preisumschalter muss seine Zahlen neu einlesen.
      document.dispatchEvent(new CustomEvent('vecom:pakete'));
    } catch (e) {
      behaelter.innerHTML = original;          // im Zweifel der alte Zustand
    }
  }

  function holen() {
    fetch('/pakete-daten.php?lang=' + encodeURIComponent(sprache()), { cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d || !d.pakete || !d.pakete.length) { return; }
        kaufText = d.kauf_text || '';
        letzte = d.pakete;
        zeichnen(letzte);
      })
      .catch(function () { /* Website behält ihre eingebauten Karten */ });
  }

  // Sprachwechsel: kurz warten, bis die Seitenübersetzung durch ist, dann neu füllen.
  document.querySelectorAll('[data-lang]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      setTimeout(function () {
        if (letzte) { holen(); }
      }, 60);
    });
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', holen);
  } else {
    holen();
  }
})();
