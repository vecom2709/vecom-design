/* ==========================================================================
   preise-live.js — die Preisseite holt sich die Zahlen dort, wo sie entstehen.

   WARUM UEBERHAUPT

   Die Seite behauptet, sie zeige die Preise, mit denen wirklich gerechnet
   wird. Fest ins HTML geschriebene Zahlen waeren spaetestens beim ersten
   Preisschritt eine Luege — und zwar eine, die monatelang niemandem
   auffaellt, weil niemand eine Textseite nachrechnet.

   Also stehen die Zahlen zwar im HTML, aber nur als Rueckfall: Sie sind
   richtig, solange die Verwaltung nichts anderes sagt. Antwortet sie, gilt
   sie. Antwortet sie nicht, bleibt der Rueckfall stehen — eine Seite ohne
   Preise waere schlechter als eine mit leicht veralteten.

   WARUM NAMEN UND TEXTE TROTZDEM IM HTML STEHEN

   Damit die Seite auch ohne Javascript vollstaendig ist. Was hier
   ausgetauscht wird, sind Zahlen, keine Inhalte.

   WAS VERSCHWINDET

   Ein Baustein, den es in der Verwaltung nicht mehr gibt, faellt aus der
   Liste. Sonst stuende auf der Preisseite ein Posten, den niemand mehr
   anbietet — und genau danach fragt dann jemand.
   ========================================================================== */
(function () {
  'use strict';

  var SPRACHEN = ['it', 'de', 'en'];

  function sprache() {
    var s = document.documentElement.getAttribute('data-lang-fixed')
         || (document.documentElement.getAttribute('lang') || '').slice(0, 2).toLowerCase();
    return SPRACHEN.indexOf(s) > -1 ? s : 'it';
  }

  /* Die Seite liegt in /de/ und /en/ eine Ebene tiefer als die PHP-Datei. */
  function quelle(l) {
    var tief = location.pathname.replace(/[^/]*$/, '');
    var hoch = /\/(de|en)\/$/.test(tief) ? '../' : './';
    return hoch + 'preise-daten.php?lang=' + encodeURIComponent(l);
  }

  function text(el, wert) {
    if (el && typeof wert === 'string' && wert !== '') { el.textContent = wert; }
  }

  function einsetzen(daten) {
    if (!daten || !daten.bausteine || !daten.bausteine.length) { return; }

    // 1. Die vier Faelle oben.
    Object.keys(daten.faelle || {}).forEach(function (schluessel) {
      text(document.querySelector('[data-fall="' + schluessel + '"]'), daten.faelle[schluessel]);
    });

    // 2. Das Listenblatt.
    var koerper = document.querySelector('[data-bausteine]');
    if (!koerper) { return; }

    var gesehen = {};
    daten.bausteine.forEach(function (b) {
      gesehen[b.slug] = true;
      var zelle = koerper.querySelector('[data-preis="' + b.slug + '"]');

      if (!zelle) {
        // Ein Baustein, den diese Seite noch nicht kennt: Zeile nachtragen,
        // mit Namen und Text aus der Verwaltung.
        var zeile = document.createElement('tr');
        var kopf  = document.createElement('th');
        kopf.setAttribute('scope', 'row');
        var fett  = document.createElement('b');
        fett.textContent = b.name || b.slug;
        var klein = document.createElement('span');
        klein.textContent = b.text || '';
        kopf.appendChild(fett);
        kopf.appendChild(klein);
        zelle = document.createElement('td');
        zelle.className = 'num';
        zelle.setAttribute('data-preis', b.slug);
        zeile.appendChild(kopf);
        zeile.appendChild(zelle);
        koerper.appendChild(zeile);
      }

      // "Auf Anfrage" bleibt stehen — dort steht bewusst keine Zahl.
      if (b.anfrage || !b.preis) { return; }
      zelle.textContent = b.preis;
      if (b.monatlich || b.je) {
        var zusatz = document.createElement('small');
        var schluessel = b.monatlich ? 'preise.bauMonat' : 'preise.bauJe';
        var woerter = (window.VECOM_I18N || {})[sprache()];
        var wort = woerter && woerter.preise ? woerter.preise[b.monatlich ? 'bauMonat' : 'bauJe'] : '';
        if (wort) {
          zusatz.textContent = wort;
          zusatz.setAttribute('data-i18n', schluessel);
          zelle.appendChild(document.createTextNode(' '));
          zelle.appendChild(zusatz);
        }
      }
    });

    // 3. Was es nicht mehr gibt, steht auch nicht mehr da.
    Array.prototype.slice.call(koerper.querySelectorAll('[data-preis]')).forEach(function (zelle) {
      var slug = zelle.getAttribute('data-preis');
      if (!gesehen[slug] && zelle.parentNode && zelle.parentNode.parentNode) {
        zelle.parentNode.parentNode.removeChild(zelle.parentNode);
      }
    });

    // 4. Einfuehrungspreise: eine Angabe, kein Countdown.
    var kasten = document.querySelector('[data-einfuehrung]');
    var zahl   = document.querySelector('[data-einfuehrung-zahl]');
    if (kasten && zahl && daten.einfuehrung && typeof daten.einfuehrung.ziel === 'number') {
      zahl.textContent = daten.einfuehrung.fertig + ' / ' + daten.einfuehrung.ziel;
      kasten.hidden = false;
    }
  }

  function holen() {
    var l = sprache();
    if (!window.fetch) { return; }
    fetch(quelle(l), { credentials: 'omit' })
      .then(function (a) { return a.ok ? a.json() : null; })
      .then(einsetzen)
      .catch(function () { /* Rueckfall bleibt stehen. */ });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', holen);
  } else {
    holen();
  }
})();
