/* partner-3d.js — 3D-Galerie der Partner (Marketing-Studio 11, 01.10.2026,
   Uwe: Ja zu P1 und P3).

   Die Bilder und Filme rechnet Vecoms PC fotorealistisch (Blender). Hier, im
   Browser des Partners, kommen Text, QR-Code und Link drauf — beim Bild in
   einem Band unten, beim Film als Abspann der letzten zweieinhalb Sekunden.
   Nichts geht über einen fremden Server; der Kanal im Link (/bild3d,
   /video3d) zeigt unter „Was wirkt“, woher die Kunden kamen.

   Braucht qrcode.js (window.qrcode) und <script type="application/json"
   id="g3_daten">. */
(function () {
  'use strict';
  var datenEl = document.getElementById('g3_daten');
  if (!datenEl || typeof qrcode !== 'function') { return; }
  var D = JSON.parse(datenEl.textContent);
  if (!D.items || !D.items.length) { return; }
  var FORMATE = { quadrat: [1080, 1080], hoch: [1080, 1350], story: [1080, 1920] };
  var FARBE = { text: '#f7f3ea', leise: '#cfc7b8', gold: '#f1d38b', dunkel: '#0a0908' };
  var ABSPANN = 2.5;
  var wahl = { stueck: 0, format: 'hoch' };

  var logo = new Image(); logo.src = '/assets/img/logo-mark.webp?v=gold2609';
  var schriften = (document.fonts && document.fonts.load)
    ? Promise.all(['800 80px Archivo', '400 40px Inter', '600 40px Inter'].map(function (f) { return document.fonts.load(f).catch(function () {}); }))
    : Promise.resolve();

  function qr(t) { var q = qrcode(0, 'M'); q.addData(t); q.make(); return q; }
  function zeichneQr(x, text, px, py, groesse) {
    var q = qr(text), n = q.getModuleCount(), rand = Math.round(groesse * 0.07), z = (groesse - 2 * rand) / n;
    x.fillStyle = '#fff'; rund(x, px, py, groesse, groesse, groesse * 0.06); x.fill();
    x.fillStyle = '#111';
    for (var r = 0; r < n; r++) { for (var c = 0; c < n; c++) { if (q.isDark(r, c)) { x.fillRect(px + rand + c * z, py + rand + r * z, Math.ceil(z), Math.ceil(z)); } } }
  }
  function rund(x, px, py, b, h, r) {
    x.beginPath(); x.moveTo(px + r, py); x.arcTo(px + b, py, px + b, py + h, r); x.arcTo(px + b, py + h, px, py + h, r);
    x.arcTo(px, py + h, px, py, r); x.arcTo(px, py, px + b, py, r); x.closePath();
  }
  function zeilen(x, text, breite) {
    var w = String(text).split(/\s+/), aus = [], z = '';
    w.forEach(function (t) { var p = z ? z + ' ' + t : t; if (x.measureText(p).width > breite && z) { aus.push(z); z = t; } else { z = p; } });
    if (z) { aus.push(z); } return aus;
  }
  /* Bild oder Videoframe formatfüllend (wie object-fit: cover). */
  function decke(x, quelle, B, H) {
    var qb = quelle.videoWidth || quelle.naturalWidth, qh = quelle.videoHeight || quelle.naturalHeight;
    if (!qb || !qh) { x.fillStyle = FARBE.dunkel; x.fillRect(0, 0, B, H); return; }
    var s = Math.max(B / qb, H / qh), w = qb * s, h = qh * s;
    x.drawImage(quelle, (B - w) / 2, (H - h) / 2, w, h);
  }
  function motiv(stueck) { return (D.motive && D.motive[stueck.studio]) || D.allgemein; }
  /* Band unten: abgedunkelt, Titel groß, Satz darunter, QR rechts — gut lesbar (Uwe, 03.09.2026). */
  function band(x, B, H, m, link) {
    var hoehe = Math.round(H * (H > B * 1.5 ? 0.30 : 0.36)), y0 = H - hoehe;
    var g = x.createLinearGradient(0, y0 - hoehe * 0.35, 0, H);
    g.addColorStop(0, 'rgba(10,9,8,0)'); g.addColorStop(0.3, 'rgba(10,9,8,0.72)'); g.addColorStop(1, 'rgba(10,9,8,0.92)');
    x.fillStyle = g; x.fillRect(0, y0 - hoehe * 0.35, B, hoehe * 1.35);
    var rand = Math.round(B * 0.06), qrG = Math.round(Math.min(B, H) * 0.24), textB = B - 3 * rand - qrG;
    x.textAlign = 'left'; x.textBaseline = 'top'; x.fillStyle = FARBE.text;
    var gr = Math.round(B * 0.062); x.font = '800 ' + gr + 'px Archivo, sans-serif';
    var z = zeilen(x, m.titel, textB).slice(0, 3), y = y0 + Math.round(hoehe * 0.12);
    z.forEach(function (t) { x.fillText(t, rand, y); y += gr * 1.12; });
    var kl = Math.round(B * 0.032); x.font = '400 ' + kl + 'px Inter, sans-serif'; x.fillStyle = FARBE.leise;
    zeilen(x, m.unter, textB).slice(0, 2).forEach(function (t) { x.fillText(t, rand, y + kl * 0.4); y += kl * 1.35; });
    x.font = '600 ' + kl + 'px Inter, sans-serif'; x.fillStyle = FARBE.gold; x.fillText(D.kurz, rand, H - rand - kl);
    zeichneQr(x, link, B - rand - qrG, H - rand - qrG, qrG);
    if (logo.complete && logo.naturalWidth) { var lg = Math.round(B * 0.075); x.drawImage(logo, rand, rand, lg, lg * logo.naturalHeight / logo.naturalWidth); }
  }
  /* Abspann des Films: dunkel, großer QR, Link, „Empfohlen von …“. a = 0..1 Einblendung. */
  function abspann(x, B, H, a) {
    x.save(); x.globalAlpha = a;
    x.fillStyle = 'rgba(10,9,8,0.94)'; x.fillRect(0, 0, B, H);
    var qrG = Math.round(Math.min(B, H) * 0.42);
    zeichneQr(x, D.link, (B - qrG) / 2, H * 0.30, qrG);
    x.textAlign = 'center'; x.textBaseline = 'top'; x.fillStyle = FARBE.text;
    var gr = Math.round(B * 0.058); x.font = '800 ' + gr + 'px Archivo, sans-serif'; x.fillText(D.scan, B / 2, H * 0.30 - gr * 1.8);
    x.font = '600 ' + Math.round(B * 0.045) + 'px Inter, sans-serif'; x.fillStyle = FARBE.gold; x.fillText(D.kurz, B / 2, H * 0.30 + qrG + gr * 0.7);
    x.font = '400 ' + Math.round(B * 0.034) + 'px Inter, sans-serif'; x.fillStyle = FARBE.leise; x.fillText(D.empf, B / 2, H * 0.30 + qrG + gr * 1.9);
    if (logo.complete && logo.naturalWidth) { var lg = Math.round(B * 0.14); x.drawImage(logo, (B - lg) / 2, H * 0.08, lg, lg * logo.naturalHeight / logo.naturalWidth); }
    x.restore();
  }

  var knoepfe = [].slice.call(document.querySelectorAll('[data-g3]'));
  var formate = [].slice.call(document.querySelectorAll('[data-g3format]'));
  var leinwand = document.getElementById('g3_vorschau');
  var laden = document.getElementById('g3_laden'), teilen = document.getElementById('g3_teilen'), vKnopf = document.getElementById('g3_video');
  var stand = document.getElementById('g3_stand'), ergebnis = document.getElementById('g3_ergebnis');
  var vLaden = document.getElementById('g3_vladen'), vTeilen = document.getElementById('g3_vteilen'), vDatei = null;
  var quellen = {};

  function quelle(i) {
    if (quellen[i]) { return quellen[i]; }
    var s = D.items[i];
    quellen[i] = new Promise(function (ok) {
      if (s.art === 'video') {
        var v = document.createElement('video'); v.muted = true; v.playsInline = true; v.preload = 'auto'; v.src = s.url;
        v.addEventListener('loadeddata', function () { try { v.currentTime = Math.min(1.5, (v.duration || 3) / 2); } catch (e) { } ok(v); }, { once: true });
        v.addEventListener('error', function () { ok(null); }, { once: true });
      } else {
        var b = new Image(); b.onload = function () { ok(b); }; b.onerror = function () { ok(null); }; b.src = s.url;
      }
    });
    return quellen[i];
  }
  function vorschau() {
    var f = FORMATE[wahl.format], s = D.items[wahl.stueck];
    leinwand.width = f[0]; leinwand.height = f[1];
    Promise.all([quelle(wahl.stueck), schriften]).then(function (r) {
      var q = r[0], x = leinwand.getContext('2d');
      if (q && q.tagName === 'VIDEO') { setTimeout(function () { decke(x, q, f[0], f[1]); band(x, f[0], f[1], motiv(s), D.linkBild); }, 120); return; }
      if (q) { decke(x, q, f[0], f[1]); } band(x, f[0], f[1], motiv(s), D.linkBild);
    });
    var istVideo = s.art === 'video';
    vKnopf.hidden = !istVideo; laden.hidden = istVideo; teilen.hidden = istVideo || !teilenMoeglich(new File([new Blob(['x'], { type: 'image/png' })], 'p.png', { type: 'image/png' }));
    zurueck();
  }
  function zurueck() { vDatei = null; ergebnis.hidden = true; ergebnis.removeAttribute('src'); vLaden.hidden = true; vTeilen.hidden = true; stand.textContent = ''; }
  function teilenMoeglich(d) { try { return !!(navigator.canShare && navigator.canShare({ files: [d] })); } catch (e) { return false; } }
  function name(endung) { return 'vecom-3d-' + D.code.toLowerCase() + '-' + (D.items[wahl.stueck].studio || 'motiv') + '-' + wahl.format + '.' + endung; }
  function herunterladen(blob, n) {
    var a = document.createElement('a'), u = URL.createObjectURL(blob); a.href = u; a.download = n;
    document.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { URL.revokeObjectURL(u); }, 4000);
  }
  knoepfe.forEach(function (k) {
    k.addEventListener('click', function () {
      wahl.stueck = +k.dataset.g3; knoepfe.forEach(function (a) { a.setAttribute('aria-pressed', a === k ? 'true' : 'false'); }); vorschau();
    });
  });
  formate.forEach(function (k) {
    k.addEventListener('click', function () {
      wahl.format = k.dataset.g3format; formate.forEach(function (a) { a.setAttribute('aria-pressed', a === k ? 'true' : 'false'); }); vorschau();
    });
  });
  laden.addEventListener('click', function () { leinwand.toBlob(function (b) { herunterladen(b, name('png')); }, 'image/png'); });
  teilen.addEventListener('click', function () {
    leinwand.toBlob(function (b) { navigator.share({ files: [new File([b], name('png'), { type: 'image/png' })], text: D.kurz }).catch(function () {}); }, 'image/png');
  });

  /* Film mit Abspann: das Video wird in Echtzeit auf die Leinwand gezeichnet und aufgenommen. */
  function videoArt() {
    if (!window.MediaRecorder || !HTMLCanvasElement.prototype.captureStream) { return null; }
    var arten = ['video/mp4;codecs=avc1.42E01E', 'video/mp4;codecs=avc1', 'video/mp4', 'video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm'];
    for (var i = 0; i < arten.length; i++) { if (MediaRecorder.isTypeSupported(arten[i])) { return arten[i]; } }
    return null;
  }
  var art = videoArt();
  vKnopf.addEventListener('click', function () {
    if (!art) { stand.textContent = D.t.v_nein; return; }
    vKnopf.disabled = true; zurueck();
    quelle(wahl.stueck).then(function (v) {
      if (!v || v.tagName !== 'VIDEO') { vKnopf.disabled = false; stand.textContent = D.t.v_nein; return; }
      var klein = matchMedia('(max-width: 820px)').matches || (navigator.deviceMemory && navigator.deviceMemory < 4);
      var f = FORMATE[wahl.format], s = klein ? 720 / f[0] : 1;
      var B = Math.round(f[0] * s), H = Math.round(f[1] * s), c = document.createElement('canvas'); c.width = B; c.height = H;
      var x = c.getContext('2d'), teile = [], rec = new MediaRecorder(c.captureStream(30), { mimeType: art, videoBitsPerSecond: klein ? 5e6 : 9e6 });
      rec.ondataavailable = function (e) { if (e.data && e.data.size) { teile.push(e.data); } };
      rec.onstop = function () {
        var typ = art.split(';')[0], endung = typ === 'video/mp4' ? 'mp4' : 'webm', blob = new Blob(teile, { type: typ });
        vDatei = new File([blob], name(endung), { type: typ });
        ergebnis.src = URL.createObjectURL(blob); ergebnis.hidden = false; vLaden.hidden = false;
        if (teilenMoeglich(vDatei)) { vTeilen.hidden = false; }
        stand.textContent = D.t.v_fertig; vKnopf.disabled = false;
      };
      var dauer = (v.duration || 8) + ABSPANN, ende = null;
      v.currentTime = 0;
      v.play().then(function () {
        rec.start(250);
        (function schritt() {
          var t = ende === null ? v.currentTime : (v.duration || 8) + (performance.now() - ende) / 1000;
          decke(x, v, B, H);
          var vorAbspann = (v.duration || 8) - 0.6;
          if (t >= vorAbspann) { abspann(x, B, H, Math.min(1, (t - vorAbspann) / 0.6)); }
          stand.textContent = D.t.v_laeuft.replace('{s}', String(Math.max(0, Math.ceil(dauer - t))));
          if (v.ended && ende === null) { ende = performance.now(); }
          if (t < dauer) { requestAnimationFrame(schritt); } else { setTimeout(function () { rec.stop(); }, 150); }
        })();
      }).catch(function () { vKnopf.disabled = false; stand.textContent = D.t.v_nein; });
    });
  });
  vLaden.addEventListener('click', function () { if (vDatei) { herunterladen(vDatei, vDatei.name); } });
  vTeilen.addEventListener('click', function () { if (vDatei) { navigator.share({ files: [vDatei], text: D.kurz }).catch(function () {}); } });
  vorschau();
})();
