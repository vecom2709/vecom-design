/* ==========================================================================
   sprachhinweis.js — Vorschlagen, nicht umleiten.

   WARUM KEINE AUTOMATISCHE WEITERLEITUNG

   Eine Umleitung nach Herkunftsland kostet Sichtbarkeit: Suchmaschinen
   crawlen ueberwiegend aus den USA. Wer nach IP umleitet, zeigt dem Crawler
   also immer nur eine Fassung — die beiden anderen koennen aus dem Index
   fallen. Genau die Sprachen, fuer die die Seite dreisprachig gebaut wurde.

   Deshalb steht hier ein Vorschlag: ein schmaler Streifen oben, den man
   annehmen oder wegklicken kann. Der Inhalt der Seite bleibt fuer jeden
   Besucher und jeden Crawler derselbe.

   WARUM DIE BROWSERSPRACHE UND NICHT DAS LAND

   Ein deutscher Gast in Sizilien hat eine italienische IP und einen
   deutschen Browser. Das Land sagt, wo jemand steht; die Browsersprache
   sagt, was er liest. Fuer die Frage "welchen Text soll ich zeigen" ist
   nur das Zweite brauchbar.

   EINMAL ENTSCHIEDEN IST ENTSCHIEDEN

   Wer umschaltet oder wegklickt, wird nicht wieder gefragt. Ein Hinweis,
   der bei jedem Aufruf wiederkommt, ist keine Hilfe mehr, sondern ein
   Bettelstreifen.
   ========================================================================== */
(function () {
  'use strict';

  var SPRACHEN = ['it', 'de', 'en'];
  var STORE    = 'vecom-lang';        // dieselbe Ablage wie app.js
  var WEG      = 'vecom-lang-nein';   // "frag mich nicht mehr"

  var ZIEL = {
    it: { pfad: '/',    text: 'Questa pagina è disponibile anche in italiano.',
          ja: 'Passa all’italiano', nein: 'No, grazie' },
    de: { pfad: '/de/', text: 'Diese Seite gibt es auch auf Deutsch.',
          ja: 'Auf Deutsch ansehen',     nein: 'Nein, danke' },
    en: { pfad: '/en/', text: 'This page is also available in English.',
          ja: 'View in English',         nein: 'No thanks' }
  };

  function lesen(schluessel) {
    try { return localStorage.getItem(schluessel); } catch (e) { return null; }
  }
  function schreiben(schluessel, wert) {
    try { localStorage.setItem(schluessel, wert); } catch (e) { /* privates Fenster */ }
  }

  /** Die Sprache dieser Seite. Steht fest im HTML, die URL entscheidet. */
  function seitensprache() {
    var s = document.documentElement.getAttribute('data-lang-fixed')
         || (document.documentElement.getAttribute('lang') || '').slice(0, 2).toLowerCase();
    return SPRACHEN.indexOf(s) > -1 ? s : 'it';
  }

  /** Die erste Browsersprache, die es hier ueberhaupt gibt. */
  function wunschsprache() {
    var liste = navigator.languages || [navigator.language || ''];
    for (var i = 0; i < liste.length; i++) {
      var k = String(liste[i]).slice(0, 2).toLowerCase();
      if (SPRACHEN.indexOf(k) > -1) { return k; }
    }
    return null;
  }

  function zeigen(sprache) {
    var z = ZIEL[sprache];
    if (!z) { return; }

    var leiste = document.createElement('div');
    leiste.className = 'sprachhinweis';
    leiste.setAttribute('role', 'region');
    leiste.setAttribute('aria-label', z.text);
    leiste.setAttribute('lang', sprache);

    var text = document.createElement('span');
    text.className = 'sprachhinweis__text';
    text.textContent = z.text;

    // Ein echter Verweis, kein Knopf mit Javascript dahinter: Er laesst sich
    // in einem neuen Tab oeffnen, und ohne Javascript funktioniert er auch.
    var ja = document.createElement('a');
    ja.className = 'sprachhinweis__ja';
    ja.href = z.pfad;
    ja.textContent = z.ja;
    ja.addEventListener('click', function () { schreiben(STORE, sprache); });

    var nein = document.createElement('button');
    nein.type = 'button';
    nein.className = 'sprachhinweis__nein';
    nein.textContent = z.nein;
    nein.addEventListener('click', function () {
      schreiben(WEG, '1');
      leiste.parentNode && leiste.parentNode.removeChild(leiste);
      hoeheMelden(null);
    });

    leiste.appendChild(text);
    leiste.appendChild(ja);
    leiste.appendChild(nein);
    document.body.insertBefore(leiste, document.body.firstChild);
    hoeheMelden(leiste);

    // Auf dem Handy bricht der Text um, die Leiste wird hoeher — dann muss die
    // Kopfzeile mitrutschen.
    if (window.ResizeObserver) {
      new ResizeObserver(function () { hoeheMelden(leiste); }).observe(leiste);
    } else {
      window.addEventListener('resize', function () { hoeheMelden(leiste); });
    }
  }

  /** Sagt dem Stylesheet, wie viel Platz die Leiste braucht. */
  function hoeheMelden(leiste) {
    var wurzel = document.documentElement;
    if (!leiste || !leiste.parentNode) {
      wurzel.classList.remove('hat-sprachhinweis');
      wurzel.style.removeProperty('--sprachhinweis-h');
      return;
    }
    wurzel.classList.add('hat-sprachhinweis');
    wurzel.style.setProperty('--sprachhinweis-h', Math.ceil(leiste.offsetHeight) + 'px');
  }

  function los() {
    // Wer schon entschieden hat, wird nicht wieder gefragt.
    if (lesen(WEG) === '1') { return; }

    var hier = seitensprache();
    var will = wunschsprache();
    if (!will || will === hier) { return; }

    // Eine gespeicherte Wahl ist eine Entscheidung — auch dann nicht fragen.
    var gemerkt = lesen(STORE);
    if (gemerkt && SPRACHEN.indexOf(gemerkt) > -1) {
      if (gemerkt === hier) { return; }
      will = gemerkt;
    }
    zeigen(will);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', los);
  } else {
    los();
  }
})();
