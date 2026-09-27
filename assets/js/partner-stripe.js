/* Eingebettete Stripe-Einrichtung des Auszahlungskontos (27.09.2026).
 *
 * Warum eingebettet: Der gehostete Einrichtungslink von Stripe kennt keine
 * Sprachangabe und nimmt die Sprache des Browsers -- ein deutscher Partner mit
 * italienischem Handy bekam Felder halb auf Italienisch. Hier bekommt Stripe
 * die Sprache der Partnerseite fest mit (data-sprache).
 *
 * Rückfall: Jeder Fehler -- Sitzung nicht zu bekommen, connect.js lädt nicht
 * oder nicht rechtzeitig -- schickt das Formular ganz normal ab, also auf die
 * gehostete Stripe-Seite wie bisher. Der Partner landet nie vor einem leeren
 * Feld.
 *
 * connect.js wird erst beim Klick geladen: Wer die Seite nur ansieht, lädt
 * nichts von Stripe.
 */
(function () {
  var form = document.getElementById('stripe-form');
  var feld = document.getElementById('stripe-einrichtung');
  if (!form || !feld || !form.dataset.pk || !window.fetch) { return; }

  var gestartet = false;

  function sitzung() {
    var d = new FormData();
    d.append('tat', 'stripe_sitzung');
    d.append('_csrf', form.querySelector('[name=_csrf]').value);
    var land = form.querySelector('[name=land]');   // 28.09.2026: Stripe legt das Land beim Anlegen fest
    if (land) { d.append('land', land.value); }
    return fetch(form.action, { method: 'POST', body: d, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && !j.ok && j.grund) { var f = new Error('absage'); f.grund = j.grund; throw f; }
        if (!j || !j.ok || !j.secret) { throw new Error('keine Sitzung'); }
        return j.secret;
      });
  }

  function rueckfall(fehler) {
    // Eine Absage wegen des Landes (keins gewählt, nicht unterstützt, Konto im
    // anderen Land): Die gehostete Seite hülfe da auch nicht -- also gleich
    // die Seite mit dem freundlichen Hinweis zeigen. Stripes Wortlaut kommt
    // nie hier an, nur der Schlüssel des Hinweises. Jede andere Absage geht
    // wie bisher den gehosteten Weg (dort meldet der Server sie ebenso).
    if (fehler && /^(konto_land_fehlt|konto_land_nicht|konto_abweichend)$/.test(fehler.grund || '')) {
      window.location.href = (form.dataset.fehler || window.location.pathname) + '&m=' + encodeURIComponent(fehler.grund) + '#wege';
      return;
    }
    if (fehler && fehler.grund === 'konto_bereit') { window.location.href = form.dataset.zurueck; return; }
    // Sonst dasselbe Formular, derselbe Weg wie ohne Skript.
    feld.hidden = true;
    form.hidden = false;
    form.submit();
  }

  function ladeSkript() {
    return new Promise(function (ja, nein) {
      if (window.StripeConnect && window.StripeConnect.init) { ja(window.StripeConnect); return; }
      window.StripeConnect = window.StripeConnect || {};
      var fertig = false;
      var gut = function () { if (!fertig && window.StripeConnect.init) { fertig = true; ja(window.StripeConnect); } };
      window.StripeConnect.onLoad = gut;
      var s = document.createElement('script');
      s.src = 'https://connect-js.stripe.com/v1.0/connect.js';
      s.async = true;
      s.onload = function () { setTimeout(gut, 0); };
      s.onerror = function () { if (!fertig) { fertig = true; nein(new Error('connect.js')); } };
      document.head.appendChild(s);
      setTimeout(function () { if (!fertig) { fertig = true; nein(new Error('Zeit')); } }, 12000);
    });
  }

  form.addEventListener('submit', function (e) {
    if (gestartet) { return; }       // Rückfall: diesmal wirklich abschicken
    e.preventDefault();
    gestartet = true;
    form.hidden = true;
    feld.hidden = false;
    feld.innerHTML = '';
    var p = document.createElement('p');
    p.className = 'laedt';
    p.setAttribute('role', 'status');
    p.textContent = form.dataset.laden || '…';
    feld.appendChild(p);

    // Sitzung und Skript gleichzeitig -- die erste Sitzung ist frisch und wird
    // sofort benutzt; jede weitere holt Stripe über fetchClientSecret selbst.
    var erste = sitzung();
    Promise.all([erste, ladeSkript()]).then(function (w) {
      var vorrat = w[0];
      var sc = w[1].init({
        publishableKey: form.dataset.pk,
        locale: form.dataset.sprache,
        fetchClientSecret: function () {
          if (vorrat) { var s = vorrat; vorrat = null; return Promise.resolve(s); }
          return sitzung();
        },
        appearance: {
          overlays: 'dialog',
          variables: {
            colorPrimary: '#f1d38b',
            colorBackground: '#141311',
            colorText: '#f7f3ea',
            colorSecondaryText: '#b4ada2',
            colorBorder: '#3a362f',
            colorDanger: '#ff8a7a',
            buttonPrimaryColorText: '#141311',
            borderRadius: '10px',
            fontFamily: 'Inter, system-ui, sans-serif',
            fontSizeBase: '15px'
          }
        }
      });
      var einrichtung = sc.create('account-onboarding');
      // Alles abfragen, was Stripe irgendwann will (z. B. den Ausweis), nicht nur das sofort Fällige.
      if (einrichtung.setCollectionOptions) { einrichtung.setCollectionOptions({ fields: 'eventually_due', futureRequirements: 'include' }); }
      einrichtung.setOnExit(function () {
        // Zurück auf die Seite: sie fragt bei Stripe nach, ob das Konto bereit ist.
        window.location.href = form.dataset.zurueck;
      });
      feld.innerHTML = '';
      feld.appendChild(einrichtung);
    }).catch(rueckfall);
  });
})();
