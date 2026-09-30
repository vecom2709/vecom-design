/* Besucher zaehlen (26.09.2026) -- ohne IP, ohne Keks, nichts von fremden
   Servern (z.php). Die Seite schickt, woher der Besucher kam: Ein <img>
   traegt als Referer nur die eigene Seite, und damit war die Herkunft in
   jeder Zeile leer. Mit ?utm_source=instagram in einem geteilten Link
   erscheint „instagram“ als Kampagne. */
(function () {
  try {
    var q = new URLSearchParams(location.search);
    var s = q.get('utm_source') || q.get('ref') || '';
    new Image().src = '/z.php?r=' + encodeURIComponent(document.referrer || '')
      + '&p=' + encodeURIComponent(location.pathname) + '&s=' + encodeURIComponent(s);
  } catch (e) { /* Zaehlen ist Beiwerk */ }
})();

/* Partner-Tracking (30.09.2026, Uwe: „Alles“). Nur wenn der Besuch über
   einen Partnerlink begann (Cookie vdsp=1 von p.php) oder der Besucher
   zugestimmt hat, sich den Partner zu merken (vecomspurok, serverseitig):
   Seitenwechsel, Kontaktformular geöffnet, ein Lebenszeichen je Minute,
   solange die Seite sichtbar ist. Welcher Partner, entscheidet der Server. */
(function () {
  var istPartner = /(?:^|;\s*)vdsp=1/.test(document.cookie);
  if (!istPartner || !window.fetch) { return; }
  var sp = (document.documentElement.lang || 'it').slice(0, 2);
  function senden(daten, fertig) {
    try {
      fetch('/t.php', { method: 'POST', credentials: 'same-origin', keepalive: true,
        headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(daten) })
        .then(function (r) { return r.json(); }).then(function (j) { if (fertig) { fertig(j); } }).catch(function () {});
    } catch (e) { /* Beiwerk */ }
  }
  var q = new URLSearchParams(location.search), u = {};
  ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content'].forEach(function (k) { if (q.get(k)) { u[k] = q.get(k).slice(0, 80); } });
  senden({ e: 'page_view', p: location.pathname, r: document.referrer || '', l: sp, u: u }, function (j) {
    if (j && j.aus) { return; }
    if (j && j.frage && j.partner) { fragen(j.partner, j.tage || 30); }
  });

  /* Kontaktformular (E-Mail-Einstieg oder Rückruf) zum ersten Mal berührt */
  var gemeldet = false;
  document.addEventListener('focusin', function (ev) {
    if (gemeldet) { return; }
    var f = ev.target && ev.target.closest && ev.target.closest('form[data-zugang], form[data-rueckruf-form]');
    if (!f) { return; }
    gemeldet = true;
    senden({ e: 'contact_form_opened', p: location.pathname, f: f.getAttribute('data-zugang') || 'rueckruf' });
  });

  /* Lebenszeichen für „Live“: höchstens 30 Minuten, nur bei sichtbarer Seite */
  var pings = 0;
  var uhr = setInterval(function () {
    if (document.visibilityState !== 'visible') { return; }
    if (++pings > 30) { clearInterval(uhr); return; }
    senden({ e: 'ping' });
  }, 60000);

  /* Die Frage: gleichwertige Knöpfe, nichts vorausgewählt, jederzeit widerrufbar */
  function fragen(name, tage) {
    if (document.getElementById('vd-spur-frage')) { return; }
    var T = {
      it: ['Consiglio di ' + name, 'Possiamo ricordare per ' + tage + ' giorni che è arrivato tramite ' + name + '? Così la raccomandazione vale anche se torna più tardi. Senza consenso vale solo questa visita.', 'Sì, ricordare', 'No', 'Privacy'],
      de: ['Empfehlung von ' + name, 'Dürfen wir uns ' + tage + ' Tage merken, dass Sie über ' + name + ' gekommen sind? Dann zählt die Empfehlung auch, wenn Sie später wiederkommen. Ohne Zustimmung gilt sie nur für diesen Besuch.', 'Ja, merken', 'Nein', 'Datenschutz'],
      en: ['Recommended by ' + name, 'May we remember for ' + tage + ' days that you came via ' + name + '? The recommendation then still counts if you come back later. Without consent it only applies to this visit.', 'Yes, remember', 'No', 'Privacy']
    }[sp] || null;
    if (!T) { return; }
    var st = document.createElement('style');
    st.textContent = '#vd-spur-frage{position:fixed;left:16px;right:16px;bottom:16px;z-index:2147483000;max-width:520px;margin:0 auto;'
      + 'background:#11100d;color:#f7f3ea;border:1px solid rgba(241,211,139,.35);border-radius:16px;padding:16px 18px;'
      + 'box-shadow:0 18px 50px rgba(0,0,0,.55);font:15px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}'
      + '#vd-spur-frage b{display:block;font-size:15px;color:#f1d38b;margin-bottom:4px}'
      + '#vd-spur-frage p{margin:0 0 12px;color:#d9d2c3;font-size:14px}'
      + '#vd-spur-frage .vds-k{display:flex;gap:10px;flex-wrap:wrap;align-items:center}'
      + '#vd-spur-frage button{flex:1 1 130px;min-height:44px;border-radius:999px;border:1px solid rgba(241,211,139,.55);background:transparent;'
      + 'color:#f7f3ea;font-family:inherit;font-weight:600;font-size:15px;cursor:pointer;padding:0 16px}'
      + '#vd-spur-frage button:hover{background:rgba(241,211,139,.1)}'
      + '#vd-spur-frage button:focus-visible,#vd-spur-frage a:focus-visible{outline:3px solid #f1d38b;outline-offset:2px}'
      + '#vd-spur-frage a{color:#a39b8d;font-size:13px}';
    document.head.appendChild(st);
    var box = document.createElement('div');
    box.id = 'vd-spur-frage'; box.setAttribute('role', 'region'); box.setAttribute('aria-label', T[0]);
    box.innerHTML = '<b></b><p></p><div class="vds-k"><button type="button" data-a="ja"></button><button type="button" data-a="nein"></button>'
      + '<a href="/legal.html?lang=' + sp + '#privacy"></a></div>';
    box.querySelector('b').textContent = T[0];
    box.querySelector('p').textContent = T[1];
    box.querySelector('[data-a="ja"]').textContent = T[2];
    box.querySelector('[data-a="nein"]').textContent = T[3];
    box.querySelector('a').textContent = T[4];
    box.addEventListener('click', function (ev) {
      var a = ev.target && ev.target.getAttribute && ev.target.getAttribute('data-a');
      if (!a) { return; }
      senden({ e: a });
      box.remove();
    });
    document.body.appendChild(box);
  }
})();
