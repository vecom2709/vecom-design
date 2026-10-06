/* Nachrichten-Werkstatt (Akquise-CRM Modul D, 06.10.2026): Beim Tippen fragt die Seite den Server nach der
   Prüfliste — die Regeln stehen nur in AkquiseWerkstatt.php, nicht ein zweites Mal hier. Gesendet wird nichts;
   der Senden-Knopf bleibt bei ⛔ gesperrt, und der Server prüft beim Senden ohnehin noch einmal. */
(function () {
  var zeichen = { ok: '✅', leer: '✅', hinweis: '⚠', stopp: '⛔' };
  var csrfFeld = document.querySelector('input[name=_csrf]');
  document.querySelectorAll('[data-werkstatt]').forEach(function (ws) {
    var betreff = ws.dataset.betreff ? document.getElementById(ws.dataset.betreff) : null;
    var text = document.getElementById(ws.dataset.text);
    if (!text) { return; }
    var liste = ws.querySelector('[data-ws-liste]');
    var zahl = ws.querySelector('[data-ws-zahl]');
    var gelesen = ws.querySelector('[data-ws-gelesen]');
    var form = ws.closest('form');
    var knopf = form ? form.querySelector('button.haupt') : null;
    var uhr = null, lauf = 0;

    ws.querySelector('[data-ws-alle]').addEventListener('click', function (e) {
      var an = ws.classList.toggle('alle');
      e.currentTarget.textContent = an ? 'Nur Offenes zeigen' : 'Alles Erfüllte zeigen';
    });

    function zeigen(d) {
      liste.textContent = '';
      var eintraege = d.liste.slice();
      if (d.stopp + d.hinweise === 0) { eintraege.unshift({ stufe: 'leer', text: 'Nichts offen — alle Punkte erfüllt.' }); }
      eintraege.forEach(function (x) {
        var li = document.createElement('li'); li.className = 'ws-' + x.stufe;
        var s = document.createElement('span'); s.setAttribute('aria-hidden', 'true'); s.textContent = zeichen[x.stufe] || '';
        li.appendChild(s); li.appendChild(document.createTextNode(x.text)); liste.appendChild(li);
      });
      zahl.textContent = (d.stopp ? '⛔ ' + d.stopp + ' · ' : '') + '⚠ ' + d.hinweise + ' · ✅ ' + (d.liste.length - d.stopp - d.hinweise);
      if (gelesen) {
        gelesen.hidden = d.hinweise === 0;
        var c = gelesen.querySelector('input'); c.required = d.hinweise > 0; if (d.hinweise === 0) { c.checked = false; }
      }
      if (knopf) { knopf.disabled = d.stopp > 0; knopf.title = d.stopp > 0 ? 'Erst die ⛔-Punkte beheben' : ''; }
      if (d.vorschau) {
        ['von', 'an', 'betreff', 'text'].forEach(function (k) {
          var el = ws.querySelector('[data-ws-v="' + k + '"]'); if (el) { el.textContent = d.vorschau[k] || (k === 'betreff' ? '— fehlt —' : ''); }
        });
      }
    }

    function fragen() {
      if (!csrfFeld) { return; }
      var n = ++lauf;
      var d = new FormData();
      d.append('_csrf', csrfFeld.value); d.append('tat', 'akq_werkstatt'); d.append('firma', ws.dataset.firma);
      d.append('kanal', ws.dataset.kanal); d.append('senden', ws.dataset.senden);
      d.append('betreff', betreff ? betreff.value : ''); d.append('text', text.value);
      fetch(ws.dataset.url, { method: 'POST', body: d, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok && (r.headers.get('content-type') || '').indexOf('json') >= 0 ? r.json() : null; })
        .then(function (j) { if (j && n === lauf) { zeigen(j); } })
        .catch(function () { /* Ohne Antwort bleibt die letzte Liste stehen — der Server prüft beim Senden. */ });
    }
    function bald() { clearTimeout(uhr); uhr = setTimeout(fragen, 450); }

    /* D-2: Töne über den PC. Bestellen, nachsehen, übernehmen — gesendet wird hier nichts. */
    var vs = ws.querySelector('[data-ws-vs]');
    var staende = { wartet: 'wartet auf deinen PC …', laeuft: 'Claude schreibt …', fertig: 'bereit', fehler: 'nicht geklappt' };
    var tonUhr = null, tonBis = 0, aktuell = null;
    function post(tat, mehr) {
      var d = new FormData();
      d.append('_csrf', csrfFeld ? csrfFeld.value : ''); d.append('tat', tat); d.append('firma', ws.dataset.firma); d.append('kanal', ws.dataset.kanal);
      Object.keys(mehr || {}).forEach(function (k) { d.append(k, mehr[k]); });
      return fetch(ws.dataset.url, { method: 'POST', body: d, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return (r.headers.get('content-type') || '').indexOf('json') >= 0 ? r.json() : { ok: false, hinweis: 'Keine Antwort (HTTP ' + r.status + ').' }; });
    }
    function vsZeigen(v, hinweis) {
      if (!vs) { return; }
      aktuell = v || null;
      if (!v && !hinweis) { vs.hidden = true; return; }
      vs.hidden = false;
      vs.querySelector('[data-ws-vs-titel]').textContent = v ? 'Vorschlag „' + v.wort + '“' : 'Umformulieren';
      vs.querySelector('[data-ws-vs-stand]').textContent = v ? (staende[v.stand] || v.stand) : '';
      var g = vs.querySelector('[data-ws-vs-grund]'); g.textContent = hinweis || (v && v.grund) || ''; g.hidden = g.textContent === '';
      var inhalt = vs.querySelector('[data-ws-vs-inhalt]'); inhalt.hidden = !(v && v.stand === 'fertig');
      vs.querySelector('[data-ws-vs-zu]').hidden = !((v && v.stand === 'fehler') || (!v && hinweis));   // Wartendes lässt sich nicht wegklicken
      if (v && v.stand === 'fertig') {
        var b = vs.querySelector('[data-ws-vs-betreff]'); if (b) { b.textContent = v.betreff; }
        vs.querySelector('[data-ws-vs-text]').textContent = v.text;
      }
      clearTimeout(tonUhr);
      if (v && (v.stand === 'wartet' || v.stand === 'laeuft') && Date.now() < tonBis) { tonUhr = setTimeout(nachsehen, 15000); }
    }
    function nachsehen() { post('akq_ton_stand').then(function (j) { if (j && j.ok) { vsZeigen(j.vorschlag); } }).catch(function () {}); }
    ws.querySelectorAll('[data-ws-ton]').forEach(function (k) {
      k.addEventListener('click', function () {
        k.disabled = true;
        post('akq_ton', { ton: k.dataset.wsTon, betreff: betreff ? betreff.value : '', text: text.value }).then(function (j) {
          k.disabled = false; tonBis = Date.now() + 40 * 60000;
          if (j && j.ok) { vsZeigen(j.vorschlag); } else { vsZeigen(aktuell, (j && j.hinweis) || 'Nicht geklappt.'); }
        }).catch(function () { k.disabled = false; vsZeigen(aktuell, 'Keine Verbindung zur Verwaltung.'); });
      });
    });
    if (vs) {
      var schliessen = function (was) {
        if (aktuell && aktuell.id) { post('akq_ton_vorschlag', { vorschlag: aktuell.id, was: was }).catch(function () {}); }
        vs.hidden = true; aktuell = null; clearTimeout(tonUhr);
      };
      vs.querySelector('[data-ws-vs-nehmen]').addEventListener('click', function () {
        if (!aktuell || aktuell.stand !== 'fertig') { return; }
        if (betreff && aktuell.betreff) { betreff.value = aktuell.betreff; betreff.dispatchEvent(new Event('input', { bubbles: true })); }
        text.value = aktuell.text; text.dispatchEvent(new Event('input', { bubbles: true }));
        schliessen('uebernommen');
      });
      vs.querySelectorAll('[data-ws-vs-weg]').forEach(function (b) { b.addEventListener('click', function () { schliessen('verworfen'); }); });
      if (vs.dataset.id !== '0') {
        aktuell = { id: +vs.dataset.id, stand: vs.dataset.stand, betreff: (vs.querySelector('[data-ws-vs-betreff]') || {}).textContent || '',
                    text: vs.querySelector('[data-ws-vs-text]').textContent, wort: '' };
        if (vs.dataset.stand === 'wartet' || vs.dataset.stand === 'laeuft') { tonBis = Date.now() + 40 * 60000; tonUhr = setTimeout(nachsehen, 15000); }
      }
    }
    text.addEventListener('input', bald);
    if (betreff) { betreff.addEventListener('input', bald); }
    if (knopf && ws.querySelector('li.ws-stopp')) { knopf.disabled = true; knopf.title = 'Erst die ⛔-Punkte beheben'; }
  });
})();
