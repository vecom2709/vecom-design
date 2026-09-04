/* ==========================================================================
   frage.js — Rueckfragen, die die Seite nicht anhalten.

   An den heiklen Knoepfen der Kundenseiten stand ein confirm() des Browsers.
   Das tut, was es soll, hat aber zwei Nachteile: Es friert das ganze Fenster
   ein, bis jemand klickt -- und es sieht aus wie eine Warnung des Browsers,
   nicht wie eine Frage von dieser Seite. Gerade beim Annehmen eines Angebots
   ist das falsch herum: Wer noch einmal nachlesen will, worauf er gerade Ja
   sagt, kann es hinter einem solchen Fenster nicht.

   Ein Formular sagt ueber data-frage, was gefragt werden soll, und ueber
   data-ja, wie die Zusage heisst. "Ja, annehmen" ist eine bessere Antwort
   als "OK": Man liest sie auch, wenn man die Frage ueberflogen hat.
   ========================================================================== */
(function () {
  var offen = null;

  function schliessen() { if (offen) { offen.remove(); offen = null; } }

  function fragen(anker, frage, jaText, neinText, aufJa) {
    schliessen();
    var kasten = document.createElement('div');
    kasten.className = 'frage';
    kasten.setAttribute('role', 'group');

    var text = document.createElement('span');
    text.textContent = frage;
    kasten.appendChild(text);

    var ja = document.createElement('button');
    ja.type = 'button';
    ja.className = 'knopf haupt';
    ja.textContent = jaText;
    ja.addEventListener('click', function () { schliessen(); aufJa(); });

    var nein = document.createElement('button');
    nein.type = 'button';
    nein.className = 'knopf';
    nein.textContent = neinText;
    nein.addEventListener('click', schliessen);

    kasten.appendChild(ja);
    kasten.appendChild(nein);

    /* IN EINER TABELLE GEHOERT SIE IN EINE EIGENE ZEILE
       ----------------------------------------------------------------
       Steht der Knopf in einer Zelle, waere der Streifen dort auch --
       und legte sich quer ueber die Spalten, mit den Antworten
       untereinander in einer Zelle, die dafuer zu schmal ist. Eine
       eigene Zeile ueber die volle Breite ist das, was eine Tabelle
       dafuer vorsieht. */
    var zeile = anker.closest ? anker.closest('tr') : null;
    if (zeile && zeile.parentNode) {
      var neueZeile = document.createElement('tr');
      var zelle = document.createElement('td');
      zelle.colSpan = zeile.children.length || 1;
      zelle.style.padding = '0';
      zelle.appendChild(kasten);
      neueZeile.appendChild(zelle);
      neueZeile.className = 'fragezeile';
      zeile.insertAdjacentElement('afterend', neueZeile);
      offen = neueZeile;
    } else {
      anker.insertAdjacentElement('afterend', kasten);
      offen = kasten;
    }
    ja.focus({ preventScroll: true });
    kasten.scrollIntoView({ block: 'nearest' });
  }

  window.vecomFrage = fragen;

  /* Auf dem Dokument und in der EINFANGENDEN Phase: So wird gefragt, bevor
     irgendein anderer Zuhoerer am Formular reagiert. Haengte die Frage
     hinten dran, haette ein Skript am Formular schon gehandelt, waehrend
     die Frage noch offen ist. */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f || !f.getAttribute) { return; }
    var frage = f.getAttribute('data-frage');
    if (!frage || f.dataset.beantwortet === 'ja') { return; }

    e.preventDefault();
    /* Wer welchen Knopf gedrueckt hat, merkt sich das Skript: In einem
       Formular mit mehreren Knoepfen waere hinterher sonst der erste
       gemeint und nicht der gedrueckte. */
    var knopf = e.submitter || f.querySelector('button, input[type=submit]');
    fragen(f, frage, f.getAttribute('data-ja') || 'Ja',
           f.getAttribute('data-nein') || 'Abbrechen', function () {
      f.dataset.beantwortet = 'ja';
      if (f.requestSubmit) { f.requestSubmit(knopf); } else { f.submit(); }
    });
  }, true);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { schliessen(); }
  });
})();
