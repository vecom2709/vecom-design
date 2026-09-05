/* ==========================================================================
   thema.js — Tag, Nacht, System.

   WAS HIER NICHT STEHT

   Das Setzen des Zustands beim Laden. Das muss VOR dem ersten Bild passieren,
   sonst blitzt die Nachtfassung auf, bevor der Tagmodus greift — und ein
   Blitz beim Laden ist genau die Sorte Fehler, die man auf einer
   Webdesigner-Seite nicht macht. Dafuer steht ein kurzer Block direkt im
   <head> jeder Seite; hier laeuft nur, was danach kommt: die Knoepfe, das
   Merken, der Uebergang und die Nachricht an die Szene.

   DREI ZUSTAENDE

   'system' ist kein Zwischending, sondern die Vorgabe: Wer nichts waehlt,
   folgt seinem Geraet, und das ist die Mehrheit. Erst eine ausdrueckliche
   Wahl schreibt data-theme und damit einen Wert, der gegen das Geraet
   gewinnt.
   ========================================================================== */
(function () {
  'use strict';

  var SCHLUESSEL = 'vecom-thema';
  var root = document.documentElement;
  var hell = window.matchMedia('(prefers-color-scheme: light)');
  var ruhig = window.matchMedia('(prefers-reduced-motion: reduce)');

  /** Was gerade wirklich zu sehen ist — 'light' oder 'dark'. */
  function wirklich() {
    var gewaehlt = root.getAttribute('data-theme');
    if (gewaehlt === 'light' || gewaehlt === 'dark') { return gewaehlt; }
    return hell.matches ? 'light' : 'dark';
  }

  /** Der gespeicherte Wunsch — 'system', 'light' oder 'dark'. */
  function wunsch() {
    try {
      var w = localStorage.getItem(SCHLUESSEL);
      return (w === 'light' || w === 'dark') ? w : 'system';
    } catch (e) { return 'system'; }
  }

  /* Die Szene liegt hinter dem Inhalt und muss mitschalten — sonst sitzt ein
     Nachtkopf ueber einer Tagseite. Sie haengt sich an dieses Ereignis; ist
     sie nicht geladen (kein WebGL, schwaches Geraet, reduzierte Bewegung),
     hoert schlicht niemand zu, und das ist in Ordnung. */
  function melden() {
    var jetzt = wirklich();
    root.setAttribute('data-thema-aktiv', jetzt);
    try {
      window.dispatchEvent(new CustomEvent('vecom:thema', { detail: { thema: jetzt } }));
    } catch (e) { /* aeltere Browser: dann eben ohne Szenenwechsel */ }
    var farbe = document.querySelector('meta[name="theme-color"]');
    if (farbe) { farbe.setAttribute('content', jetzt === 'light' ? '#eef1f7' : '#05070d'); }
  }

  function anwenden(w) {
    if (w === 'system') { root.removeAttribute('data-theme'); }
    else { root.setAttribute('data-theme', w); }
    knoepfeStellen(w);
    melden();
  }

  function knoepfeStellen(w) {
    var alle = document.querySelectorAll('[data-thema]');
    for (var i = 0; i < alle.length; i++) {
      alle[i].setAttribute('aria-pressed', String(alle[i].getAttribute('data-thema') === w));
    }
  }

  function setzen(w) {
    try {
      if (w === 'system') { localStorage.removeItem(SCHLUESSEL); }
      else { localStorage.setItem(SCHLUESSEL, w); }
    } catch (e) { /* privates Fenster: gilt dann nur fuer diesen Besuch */ }

    /* Ein Farbumschlag ueber die ganze Seite ist ein Schnitt. Die View
       Transition macht daraus eine Blende. Wer reduzierte Bewegung
       eingestellt hat, bekommt den Schnitt — hier ist er die ruhigere
       Variante, nicht die aermere. */
    if (document.startViewTransition && !ruhig.matches) {
      document.startViewTransition(function () { anwenden(w); });
    } else {
      anwenden(w);
    }
  }

  /* --- Knoepfe ------------------------------------------------------------
     Ereignis am Dokument statt an jedem Knopf: Der Kopf wird auf manchen
     Seiten nachtraeglich zusammengesetzt, und ein Knopf, der zu spaet
     entsteht, waere sonst tot. */
  document.addEventListener('click', function (e) {
    var knopf = e.target.closest && e.target.closest('[data-thema]');
    if (!knopf) { return; }
    e.preventDefault();
    setzen(knopf.getAttribute('data-thema'));
  });

  /* Aendert sich die Systemeinstellung, waehrend die Seite offen ist, folgt
     nur, wer 'system' gewaehlt hat. Wer sich entschieden hat, bleibt. */
  var lauschen = hell.addEventListener ? 'addEventListener' : 'addListener';
  hell[lauschen]('change', function () { if (wunsch() === 'system') { melden(); } });

  knoepfeStellen(wunsch());
  melden();

  /* Fuer die Szene und fuer alles, was spaeter dazukommt. */
  window.vecomThema = { jetzt: wirklich, wunsch: wunsch, setzen: setzen };
})();
