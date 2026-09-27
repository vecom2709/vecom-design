/* ==========================================================================
   sprache.js — die Sprache entscheidet sich an EINER Stelle, auf JEDER Seite.

   WARUM ES DIESE DATEI GIBT (27.09.2026)

   Die Sprachweiche lebte in app.js. Die laden aber nicht alle Seiten: Der
   Showroom und der Tischkonfigurator tragen ihre Texte fertig im HTML, die
   Landeseiten ebenso. Wer aus Deutschland auf /siti-web-ristoranti.html kam,
   blieb deshalb auf Italienisch -- die Startseite schaltete um, die
   Unterseite nicht.

   Jetzt steht die Weiche in einer eigenen, kleinen Datei, die jede gebaute
   Seite im Kopf laedt, bevor irgendetwas gezeichnet wird. Welche Seite es in
   welcher Sprache gibt, steht in assets/js/seiten.js — die erzeugt build.mjs
   aus derselben Liste, aus der auch die Seiten selbst entstehen. Zwei
   Wahrheiten kann es damit nicht geben.

   DIE REGELN, IN DIESER REIHENFOLGE

   1. ?lang=xx in der Adresse ist eine Entscheidung. Sie gilt und wird
      gemerkt.
   2. Eine frühere Wahl (Keks oder Speicher) gilt beim Direkteinstieg.
   3. Beim ERSTEN Besuch entscheidet das Land — aus der Zeitzone des
      Geraets, ohne Geodienst, ohne IP an Dritte.
   4. Klicks INNERHALB der Seite leiten nie um: Wer bewusst auf IT
      zurueckschaltet, soll dort ankommen.
   5. Eine unbekannte Zeitzone (auch UTC, wie Suchroboter sie melden)
      leitet nicht um.
   ========================================================================== */
(function () {
  'use strict';

  var LANGS = ['it', 'de', 'en'];
  var STORE = 'vecom-lang';
  var AUTO  = 'vecom-lang-auto';   // die Sprache kam aus dem Land, nicht aus einem Klick

  /* Zeitzone -> Sprache. Keine Ortung, eine Vermutung fuer den ersten
     Augenblick; jede Wahl schlaegt sie. */
  var ZONEN = {
    'europe/rome': 'it', 'europe/vatican': 'it', 'europe/san_marino': 'it', 'europe/malta': 'it',
    'europe/berlin': 'de', 'europe/vienna': 'de', 'europe/zurich': 'de',
    'europe/busingen': 'de', 'europe/vaduz': 'de', 'europe/luxembourg': 'de',
    'europe/london': 'en', 'europe/dublin': 'en', 'europe/gibraltar': 'en'
  };
  var ZONEN_PRAEFIX = { 'america/': 'en', 'australia/': 'en', 'pacific/auckland': 'en',
                        'canada/': 'en', 'us/': 'en' };

  function lesen(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function schreiben(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function loeschen(k) { try { localStorage.removeItem(k); } catch (e) {} }

  function gemerkt() {
    var keks = /(?:^|;)\s*vecomlang=([a-z]{2})/.exec(document.cookie || '');
    if (keks && LANGS.indexOf(keks[1]) > -1) { return keks[1]; }
    var gespeichert = lesen(STORE);
    return LANGS.indexOf(gespeichert) > -1 ? gespeichert : null;
  }

  function merken(lang) {
    if (LANGS.indexOf(lang) < 0) { return; }
    schreiben(STORE, lang);
    try {
      document.cookie = 'vecomlang=' + lang + ';path=/;max-age=31536000;SameSite=Lax';
    } catch (e) {}
  }

  function landessprache() {
    var zone = '';
    try {
      zone = String(Intl.DateTimeFormat().resolvedOptions().timeZone || '').toLowerCase();
    } catch (e) { return null; }
    if (!zone) { return null; }
    if (ZONEN[zone]) { return ZONEN[zone]; }
    for (var p in ZONEN_PRAEFIX) {
      if (zone.indexOf(p) === 0) { return ZONEN_PRAEFIX[p]; }
    }
    return null;
  }

  /* Die Karte aller Seiten je Sprache. Fehlt sie (eine handgeschriebene
     Seite laedt sie nicht), bleiben wenigstens die drei Startseiten. */
  var SEITEN = window.VECOM_SEITEN || {
    start: { it: '/', de: '/de/', en: '/en/' }
  };

  /** Welche Rolle und welche Sprache hat dieser Pfad? */
  function seiteVon(pfad) {
    if (pfad.slice(-11) === '/index.html') { pfad = pfad.slice(0, -10); }
    for (var rolle in SEITEN) {
      for (var l in SEITEN[rolle]) {
        if (SEITEN[rolle][l] === pfad) { return { rolle: rolle, lang: l }; }
      }
    }
    return null;
  }

  /** Dieselbe Seite in einer anderen Sprache — oder null. */
  function uebersetzung(pfad, lang) {
    var hier = seiteVon(pfad);
    if (!hier || !SEITEN[hier.rolle][lang]) { return null; }
    return SEITEN[hier.rolle][lang];
  }

  function vonInnen() {
    return document.referrer.indexOf(location.origin + '/') === 0
        || document.referrer === location.origin;
  }

  function weiche() {
    var fest = document.documentElement.getAttribute('data-lang-fixed')
            || (document.documentElement.getAttribute('lang') || '').slice(0, 2).toLowerCase();
    if (LANGS.indexOf(fest) < 0) { return; }

    /* Eine Sprache in der Adresse ist eine Entscheidung. */
    var ausUrl = null;
    try { ausUrl = new URLSearchParams(location.search).get('lang'); } catch (e) {}
    if (LANGS.indexOf(ausUrl) > -1) {
      loeschen(AUTO);
      merken(ausUrl);
      if (ausUrl !== fest) {
        var zielUrl = uebersetzung(location.pathname, ausUrl);
        if (zielUrl) { location.replace(zielUrl + location.hash); return; }
      }
      return;
    }

    var wunsch = gemerkt();
    if (!wunsch) {
      wunsch = landessprache();
      if (wunsch) { schreiben(AUTO, '1'); }
    }

    if (wunsch && wunsch !== fest && !vonInnen()) {
      var ziel = uebersetzung(location.pathname, wunsch);
      if (ziel) { location.replace(ziel + location.hash); return; }

      /* Seiten, die der Server baut (bedarf.php, hosting.php, angebot.php)
         stehen nicht in der Karte: Es gibt sie nur einmal, in jeder Sprache
         dieselbe Adresse. Dort wird nicht umgeleitet, sondern die Sprache
         in der Adresse nachgereicht -- einmal, beim allerersten Aufruf, und
         nur wenn der Server das erlaubt (data-lang-neu). Er setzt die Marke
         nur bei einem gewoehnlichen Aufruf, nie nach einem abgeschickten
         Formular: Ein Nachladen wuerde dessen Ergebnis wegwerfen. */
      if (document.documentElement.getAttribute('data-lang-neu') === '1') {
        merken(wunsch);
        try {
          var u = new URL(location.href);
          u.searchParams.set('lang', wunsch);
          location.replace(u.toString());
          return;
        } catch (e) { /* dann bleibt es bei dieser Fassung */ }
      }
    }
    merken(fest);
  }

  window.VecomSprache = {
    LANGS: LANGS, STORE: STORE, AUTO: AUTO,
    gemerkt: gemerkt, merken: merken, landessprache: landessprache,
    seiteVon: seiteVon, uebersetzung: uebersetzung,
    autoMerken: function () { schreiben(AUTO, '1'); },
    autoLoeschen: function () { loeschen(AUTO); },
    istVermutung: function () { return lesen(AUTO) === '1'; }
  };

  weiche();
})();
