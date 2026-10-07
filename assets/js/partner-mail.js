/* Mailprogramm öffnen — mit Rückfall (07.10.2026, Uwe: „bei den Partnern … im E-Mail-Programm öffnen geht nicht“).

   Warum ein mailto:-Link manchmal gar nichts tut:
   - Auf dem Gerät ist kein Standard-Mailprogramm eingerichtet (Windows ohne Outlook, Chrome ohne Handler,
     viele Handys nutzen Gmail nur im Browser) — dann passiert still nichts.
   - Windows verwirft Links über etwa 2000 Zeichen kommentarlos; ein Text mit Fußzeile ist schnell so lang.
   - Nach einer Server-Antwort kann der Browser den Klick „vergessen“ haben und blockiert den Sprung.

   Darum hier: Jeder mailto:-Link im Partnerbereich geht durch vecomMail.oeffnen(). Bei langem Text wird nur
   Empfänger und Betreff übergeben und der Text in die Zwischenablage gelegt. Bleibt die Seite danach im
   Vordergrund (kein Programm hat übernommen), erscheint ein Fenster mit „Erneut öffnen“, Gmail und Outlook
   im Browser und „Text kopieren“. Gesendet wird nie von hier — immer vom Partner selbst. */
(function () {
  if (window.vecomMail) { return; }
  var T = {
    it: { titel: 'Il programma di posta non si è aperto?', hinweis: 'Su questo dispositivo forse non è impostato un programma di posta. Può scrivere con Gmail o Outlook nel browser — tutto è già compilato.',
          nochmal: 'Riprova con il programma di posta', gmail: 'Apri in Gmail', outlook: 'Apri in Outlook.com', kopieren: 'Copia testo', kopiert: 'Testo copiato ✓',
          zwischen: 'Il testo era lungo: è negli appunti — lo incolli nel messaggio.', an: 'A', betreff: 'Oggetto', zu: 'Chiudi' },
    de: { titel: 'Das E-Mail-Programm hat sich nicht geöffnet?', hinweis: 'Auf diesem Gerät ist vielleicht kein E-Mail-Programm eingerichtet. Schreiben Sie mit Gmail oder Outlook im Browser — alles ist schon ausgefüllt.',
          nochmal: 'Noch einmal im E-Mail-Programm öffnen', gmail: 'In Gmail öffnen', outlook: 'In Outlook.com öffnen', kopieren: 'Text kopieren', kopiert: 'Text kopiert ✓',
          zwischen: 'Der Text war lang: Er liegt in der Zwischenablage — in die Nachricht einfügen.', an: 'An', betreff: 'Betreff', zu: 'Schließen' },
    en: { titel: 'Your email app did not open?', hinweis: 'This device may have no email app set up. Write with Gmail or Outlook in the browser — everything is already filled in.',
          nochmal: 'Try the email app again', gmail: 'Open in Gmail', outlook: 'Open in Outlook.com', kopieren: 'Copy text', kopiert: 'Text copied ✓',
          zwischen: 'The text was long: it is on the clipboard — paste it into the message.', an: 'To', betreff: 'Subject', zu: 'Close' }
  };
  var t = T[(document.documentElement.lang || 'it').slice(0, 2)] || T.it;
  var LANG = 1900;

  function zerlegen(href) {
    var u = href.replace(/^mailto:/i, ''), q = u.indexOf('?');
    var p = new URLSearchParams(q < 0 ? '' : u.slice(q + 1).replace(/\+/g, '%2B'));
    var an = ''; try { an = decodeURIComponent(q < 0 ? u : u.slice(0, q)); } catch (e) { an = q < 0 ? u : u.slice(0, q); }
    return { an: an, betreff: p.get('subject') || '', text: p.get('body') || '' };
  }
  function kurz(m) {
    return 'mailto:' + encodeURIComponent(m.an).replace(/%40/g, '@').replace(/%2C/gi, ',') + (m.betreff ? '?subject=' + encodeURIComponent(m.betreff) : '');
  }
  function kopieren(text, knopf) {
    var fertig = function () { if (knopf) { knopf.textContent = t.kopiert; } };
    if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(text).then(fertig, function () {}); return; }
    var ta = document.createElement('textarea'); ta.value = text; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); fertig(); } catch (e) { } ta.remove();
  }

  function fenster(m, href, warLang) {
    var alt = document.getElementById('vm-fenster'); if (alt) { alt.remove(); }
    var d = document.createElement('div');
    d.id = 'vm-fenster'; d.setAttribute('role', 'dialog'); d.setAttribute('aria-modal', 'true'); d.setAttribute('aria-labelledby', 'vm-titel');
    d.style.cssText = 'position:fixed;inset:0;z-index:99999;display:grid;place-items:center;padding:16px;background:rgba(6,8,14,.72)';
    var k = document.createElement('div');
    k.style.cssText = 'width:min(560px,100%);max-height:90vh;overflow:auto;background:#fff;color:#141414;border-radius:16px;padding:22px 22px 18px;font:17px/1.5 system-ui,-apple-system,Segoe UI,sans-serif;box-shadow:0 20px 60px rgba(0,0,0,.4)';
    var h = document.createElement('h2'); h.id = 'vm-titel'; h.textContent = t.titel; h.style.cssText = 'margin:0 0 8px;font-size:21px;line-height:1.3';
    var p = document.createElement('p'); p.textContent = t.hinweis; p.style.cssText = 'margin:0 0 14px;color:#444';
    k.appendChild(h); k.appendChild(p);
    if (warLang) { var z = document.createElement('p'); z.textContent = t.zwischen; z.style.cssText = 'margin:0 0 14px;padding:10px 12px;background:#fff6dd;border-radius:10px'; k.appendChild(z); }
    var info = document.createElement('p'); info.style.cssText = 'margin:0 0 12px;font-size:15px;color:#333';
    info.textContent = t.an + ': ' + (m.an || '—') + (m.betreff ? '  ·  ' + t.betreff + ': ' + m.betreff : ''); k.appendChild(info);
    var knopf = function (text, url, haupt) {
      var a = document.createElement('a'); a.textContent = text; a.href = url;
      if (!/^mailto:/i.test(url)) { a.target = '_blank'; a.rel = 'noopener'; }
      a.style.cssText = 'display:block;text-align:center;text-decoration:none;font-weight:650;padding:13px 16px;border-radius:12px;margin:0 0 9px;'
        + (haupt ? 'background:#1d4ed8;color:#fff' : 'background:#eef1f6;color:#141414;border:1px solid #d5dbe5');
      a.addEventListener('click', function () { setTimeout(function () { d.remove(); }, 400); });
      k.appendChild(a);
    };
    var q = function (o) { return Object.keys(o).filter(function (x) { return o[x]; }).map(function (x) { return x + '=' + encodeURIComponent(o[x]); }).join('&'); };
    knopf(t.gmail, 'https://mail.google.com/mail/?view=cm&fs=1&' + q({ to: m.an, su: m.betreff, body: m.text }), true);
    knopf(t.outlook, 'https://outlook.live.com/mail/0/deeplink/compose?' + q({ to: m.an, subject: m.betreff, body: m.text }), false);
    knopf(t.nochmal, warLang ? kurz(m) : href, false);
    if (m.text) {
      var ta = document.createElement('textarea'); ta.readOnly = true; ta.value = m.text; ta.rows = 6;
      ta.style.cssText = 'width:100%;box-sizing:border-box;margin:6px 0 9px;padding:10px;border:1px solid #d5dbe5;border-radius:10px;font:15px/1.45 system-ui,sans-serif;color:#141414;background:#fafbfc';
      k.appendChild(ta);
      var kb = document.createElement('button'); kb.type = 'button'; kb.textContent = t.kopieren;
      kb.style.cssText = 'width:100%;padding:12px 16px;border-radius:12px;border:1px solid #d5dbe5;background:#eef1f6;color:#141414;font:650 16px system-ui,sans-serif;cursor:pointer;margin:0 0 9px';
      kb.addEventListener('click', function () { kopieren(m.text, kb); });
      k.appendChild(kb);
    }
    var zu = document.createElement('button'); zu.type = 'button'; zu.textContent = t.zu;
    zu.style.cssText = 'width:100%;padding:10px;border:0;background:none;color:#555;font:16px system-ui,sans-serif;cursor:pointer';
    zu.addEventListener('click', function () { d.remove(); });
    k.appendChild(zu);
    d.appendChild(k);
    d.addEventListener('click', function (e) { if (e.target === d) { d.remove(); } });
    document.addEventListener('keydown', function esc(e) { if (e.key === 'Escape') { d.remove(); document.removeEventListener('keydown', esc); } });
    document.body.appendChild(d);
    var erster = k.querySelector('a'); if (erster) { erster.focus(); }
  }

  function oeffnen(href) {
    var m = zerlegen(href), warLang = href.length > LANG && !!m.text;
    if (warLang) { kopieren(m.text, null); }
    var weg = false, fort = function () { weg = true; };
    window.addEventListener('blur', fort);
    document.addEventListener('visibilitychange', fort);
    try { window.location.href = warLang ? kurz(m) : href; } catch (e) { }
    setTimeout(function () {
      window.removeEventListener('blur', fort); document.removeEventListener('visibilitychange', fort);
      if (!weg && document.visibilityState === 'visible') { fenster(m, href, warLang); }
    }, 1600);
  }

  window.vecomMail = { oeffnen: oeffnen };

  /* Jeder mailto:-Link im Partnerbereich (Kunden-Akte, Besuche, Recherche, Marketing, Teilen). */
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
    var a = e.target.closest ? e.target.closest('a[href^="mailto:"]') : null;
    if (!a || a.closest('#vm-fenster')) { return; }
    e.preventDefault();
    oeffnen(a.getAttribute('href'));
  });
})();
